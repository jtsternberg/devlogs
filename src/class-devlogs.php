<?php
namespace Jsternberg;

class DevLogs {

   /**
    * Post type slug
    *
    * @since 1.0.0
    * @var string
    */
	const TYPE = 'devlogs';

   /**
    * Array of items to log.
    *
    * @since 1.0.0
    * @var array
    */
	protected static $toLog = [];

   /**
    * Unique request ID - 6 character hash of $_SERVER
    *
    * (used to group log entries from the same request)
    *
    * @since 1.0.0
    * @var string
    */
	protected static $requestId = '';

   /**
    * Initialize the hooks.
    *
    * @since 1.0.0
    */
	public static function init() {
		self::$requestId = substr( md5( serialize( $_SERVER ) ), 0, 6 );
		add_action( 'init', [ __CLASS__, 'initPostType' ] );
		add_action( 'current_screen', [ __CLASS__, 'handleLogPostTypeViews' ] );
		add_action( 'logDebugContent', [ __CLASS__, 'addDebug' ], 10, 3 );
	}

   /**
    * Handle the logging (logDebugContent) hook.
    *
    * @since 1.0.0
    * @param string $loggerName Name of the logger (post-type post title).
    * @param string $title Title of this debug entry.
    * @param mixed  $debugContent Content to log.
    *
    * @return void
    */
	public static function addDebug( $loggerName, $title, $debugContent ) {
		$content = $title . ' = ';
		$content .= print_r( $debugContent, true );

		self::handle( $loggerName, $content );
	}

	protected static function handle( $loggerName, $content ) {
		if ( ! self::canLog( $loggerName ) ) {
			return;
		}

		self::$toLog[ $loggerName ][] = '[' . date( 'Y-m-d H:i:s' ) . ' ' . self::$requestId .'] ' . $content;

		add_action( 'shutdown', [ __CLASS__, 'storeLogs' ] );
	}

	public static function storeLogs() {
		foreach ( self::$toLog as $loggerName => $contents ) {
			$post = self::getLogPost( $loggerName );

			wp_update_post( [
				'ID' => $post['ID'],
				'post_content' => $post['post_content'] . "\n" . implode( "\n", $contents ),
			] );
		}
	}

	public static function getLogPost( $loggerName ) {
		$logId = sanitize_title( 'logger_' . $loggerName );
		$post = (array) get_page_by_path( $logId, OBJECT, self::TYPE );
		if ( empty( $post ) ) {
			$post = (array) get_post( wp_insert_post( [
				'post_title'  => 'Logger: ' . $loggerName,
				'post_name'   => $logId,
				'post_type'   => self::TYPE,
			], true ) );
		}

		return $post;
	}

	public static function canLog( $loggerName ) {
		if ( defined( 'OM_DEVLOG_ENABLED' ) ) {

			// Nope.
			if ( empty( OM_DEVLOG_ENABLED ) ) {
				return false;
			}

			if ( is_string( OM_DEVLOG_ENABLED ) ) {
				return $loggerName === OM_DEVLOG_ENABLED;
			}

			return true;
		}

		return \AwesomeMotive\OptinMonsterCore\Utils\Env::isDevelopment();
	}

	public static function initPostType() {
		$canSeeLogs = \AwesomeMotive\OptinMonsterCore\Utils\Env::isDevelopment();
		if ( ! $canSeeLogs ) {
			$user = wp_get_current_user();
			$canSeeLogs = ! empty( $user->roles ) && in_array( self::TYPE, $user->roles, true );
		}
		register_post_type( self::TYPE, [
			'labels'          => [
				'name' => 'Dev Logs',
			],
			'public'          => false,
			'show_ui'         => $canSeeLogs,
			'show_in_menu'    => $canSeeLogs,
			'menu_icon'       => 'dashicons-welcome-write-blog',
			'query_var'       => false,
			'capability_type' => 'post',
			'has_archive'     => false,
			'hierarchical'    => false,
			'supports'        => [ 'title', 'editor' ],
			'show_in_rest'    => false,
			'menu_position'   => PHP_INT_MAX - 55,
		] );
	}

	public static function handleLogPostTypeViews( $screen ) {
		if ( empty( $screen->id ) ) {
			return;
		}


		switch ( $screen->id ) {
			case self::TYPE : {
				if ( isset( $_GET['post'], $_GET['action'] ) && 'edit' === $_GET['action'] ) {
					self::modifyEditView();
				}
			}
			case 'edit-' . self::TYPE : {
				add_filter( 'post_row_actions', function ( $actions, $post ) {
					if ( $post->post_type === self::TYPE ) {
						unset( $actions['clone'] );
						unset( $actions['trash'] );
						$actions['delete'] = '<a href="' . get_delete_post_link( $post, '', true ) . '" class="delete-devlogs submitdelete deletion" aria-label="Delete &#8220;Logger: dpa-debugging&#8221;">Delete</a>';
					}
					return $actions;
				}, 10, 2 );
			}
		}
	}

	public static function modifyEditView() {
		$postId        = absint( $_GET['post'] );
		$editLink      = get_edit_post_link( $postId, 'output' );
		$allLink       = admin_url( 'edit.php?post_type='. self::TYPE );
		$logViewLink   = add_query_arg( 'OmLogDebugger', 1, $editLink );
		$noLogViewLink = add_query_arg( 'OmLogDebugger', 0, $editLink );
		$emptyLogLink  = add_query_arg( 'OmLogDebugger', 'empty', $editLink );
		$deleteLogLink = get_delete_post_link( $postId, '', true );
		if ( ! isset( $_GET['OmLogDebugger'] ) ) {
			wp_safe_redirect( $logViewLink );
			exit;
		}

		if ( ! empty( $_GET['OmLogDebugger'] ) ) {
			$post = get_post( $postId );

			switch ( $_GET['OmLogDebugger'] ) {
				case 'empty' : {
					wp_update_post( [
						'ID' => $post->ID,
						'post_content' => '',
					] );
					wp_safe_redirect( $logViewLink );
					exit;
				}
			}

			$output   = html_entity_decode( $post->post_content );
			$output   = print_r( $output, true );
			$height   = count( explode( "\n", $output ) );
			$title    = esc_html( $post->post_title );
			$filename = sanitize_text_field( $post->post_name ) . '.log';

			ob_start();
			?>
				<style>
					#error-page {
						max-width: none;
						box-sizing: border-box;
						box-shadow: none;
						margin: 0;
						padding-top: 0;
					}
					.flex {
						display: flex;
						justify-content: space-between;
						align-content: center;
					}
					.flex-end {
						display: flex;
						flex-direction: column;
						justify-content: end;
					}
				</style>
				<h1><?php echo $title; ?></h1>
				<div class="flex">
					<p>
						<a href="<?php echo $allLink; ?>">&larr; All Logs</a><br/>
						<a href="<?php echo $noLogViewLink; ?>">&larr; Back to Editor</a> |
						<a style="color:red;" href="<?php echo $emptyLogLink; ?>">!! Empty this Log !!</a> |
						<a style="color:red;" href="<?php echo $deleteLogLink; ?>">!! Delete this Log !!</a>
					</p>
					<p class="flex-end">
						<button style="height: 2em;" onmouseup="window.downloadOutput()" type="button">Download Output</button>
					</p>
				</div>
				<textarea
					id="logger-data"
					style="width:100%;padding:5px;"
					rows="<?php echo $height + 20; ?>"
				><?php echo $output; ?></textarea>
				<script>
					window.downloadOutput = function() {
						var txt = document.getElementById('logger-data').value;
						var dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(txt);
						var fileName = <?php echo wp_json_encode( $filename ); ?>;
						var el = document.createElement('a');
						el.setAttribute('href', dataStr);
						el.setAttribute('download', fileName);
						document.body.appendChild(el);
						el.click();
						el.remove();

						return fileName;
					};
				</script>
			<?php
			// grab the data from the output buffer and add it to our $content variable
			$content = ob_get_clean();

			wp_die( $content, $title, [
				'response' => 200,
			] );
		}

		add_action( 'edit_form_top', function( $post ) use ( $logViewLink, $emptyLogLink, $deleteLogLink ) {
			echo '<p>
				<a href="' . $allLink . '">&larr; All Logs</a>
			</p>
			<p>
				<a style="color:red;" href="' . $emptyLogLink . '" class="dashicons-before dashicons-undo">Empty this Log</a> |
				<a style="color:red;" href="' . $deleteLogLink . '" class="dashicons-before dashicons-trash">Delete this Log</a> |
				<a href="' . $logViewLink . '">Logger View &rarr;</a>
			</p>';
		} );
	}
}
