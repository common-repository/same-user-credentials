<?php
/**
 *
 * @package          SUCW
 *
 * @wordpress-plugin
 * Plugin Name:       Same user credentials
 * Description:       Share login credentials between multiple sites
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Giulio Pandolfelli
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 	  same-user-credentials
 * Domain Path: 	  /languages
 */
// errori php
namespace sucw;

define('SUCW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define ('SUCW_VERSION', '1.0.0');

require_once(__DIR__.'/includes/sucw-auth.php');
require_once(__DIR__.'/includes/sucw-api.php');
require_once(__DIR__.'/includes/sucw-menu.php');
require_once(__DIR__.'/includes/sucw-functions.php');
require_once(__DIR__.'/includes/sucw-options.php');
require_once(__DIR__.'/includes/sucw-loader.php');
require_once(__DIR__.'/includes/sucw-logs.php');
require_once(__DIR__.'/admin/sucw-admin.php');

new SUCW_Api();
new SUCW_loader();
new SUCW_menu();