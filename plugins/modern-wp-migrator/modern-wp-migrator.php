<?php
/**
 * Plugin Name: Modern WP Migrator
 * Plugin URI: https://github.com/custom/modern-wp-migrator
 * Description: Herramienta moderna de migración y respaldos para WordPress. Exporta, importa y restaura sitios completos sin restricciones de tamaño. Totalmente optimizado para PHP 8.x.
 * Author: Modern Dev Team
 * Author URI: https://github.com/custom
 * Version: 7.113.0
 * Text Domain: modern-wp-migrator
 * Domain Path: /languages
 * Network: True
 * License: GPLv3
 *
 * Attribution: Forked under GNU General Public License (GPLv3). Originally developed by ServMask Inc.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Check SSL mode
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && ( $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ) {
	$_SERVER['HTTPS'] = 'on';
}

// Plugin basename
define( 'AI1WM_PLUGIN_BASENAME', basename( __DIR__ ) . '/' . basename( __FILE__ ) );

// Plugin path
define( 'AI1WM_PATH', __DIR__ );

// Plugin URL
define( 'AI1WM_URL', plugins_url( '', AI1WM_PLUGIN_BASENAME ) );

// Plugin storage URL
define( 'AI1WM_STORAGE_URL', plugins_url( 'storage', AI1WM_PLUGIN_BASENAME ) );

// Include functions
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

// Include constants
require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

// Include deprecated
require_once __DIR__ . DIRECTORY_SEPARATOR . 'deprecated.php';

// Include exceptions
require_once __DIR__ . DIRECTORY_SEPARATOR . 'exceptions.php';

// Include loader
require_once __DIR__ . DIRECTORY_SEPARATOR . 'loader.php';

// Plugin initialization
$main_controller = new Ai1wm_Main_Controller();
