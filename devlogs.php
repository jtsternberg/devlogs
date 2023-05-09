<?php
/**
 * Plugin Name: Dev Logs
 * Plugin URI:  https://github.com/jtsternberg/devlogs
 * Description: A post-type Logger/debugger
 * Author:      Jtsternberg
 * Author URI:  https:/jtsternberg.com
 * Version:     1.0.0
 * Text Domain: devlogs
 * Domain Path: languages
 *
 * WPForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WPForms. If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DEVLOGS_VERSION', '1.0.0' );
define( 'DEVLOGS_FILE', __FILE__ );
define( 'DEVLOGS_PATH', __DIR__ );

// Load the class.
function includeDevLogs() {
   require_once DEVLOGS_PATH . 'src/class-devlogs.php';
   add_action( 'init', '\Jsternberg\DevLogs::init' );
}
add_action( 'plugins_loaded', 'includeDevLogs' );

