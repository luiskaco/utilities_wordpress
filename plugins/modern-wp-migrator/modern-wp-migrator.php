<?php
/**
 * Plugin Name: Modern WP Migrator
 * Plugin URI: https://github.com/luiskaco/utilities_wordpress
 * Description: Herramienta moderna de migración y respaldos para WordPress. Exporta, importa y restaura sitios completos sin restricciones de tamaño. Totalmente optimizado para PHP 8.x.
 * Author: Modern Dev Team
 * Author URI: https://github.com/luiskaco/utilities_wordpress
 * Version: 7.113.2
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

// Initialize Plugin Update Checker (PUC)
if ( file_exists( __DIR__ . '/lib/vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/lib/vendor/plugin-update-checker/plugin-update-checker.php';
	if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		$ai1wm_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/luiskaco/utilities_wordpress/',
			__FILE__,
			'modern-wp-migrator'
		);

		$ai1wm_vcs = $ai1wm_update_checker->getVcsApi();
		if ( method_exists( $ai1wm_vcs, 'setReleaseVersionFilter' ) ) {
			$ai1wm_vcs->setReleaseVersionFilter( '/^modern-wp-migrator-/i' );
		}
		if ( method_exists( $ai1wm_vcs, 'enableReleaseAssets' ) ) {
			$ai1wm_vcs->enableReleaseAssets(
				'/^modern-wp-migrator.*\.zip$/i',
				$ai1wm_vcs::REQUIRE_RELEASE_ASSETS
			);
		}

		$ai1wm_normalize_version = function( $info_or_update ) {
			if ( $info_or_update && isset( $info_or_update->version ) ) {
				if ( preg_match( '/^modern-wp-migrator-v?([0-9\.]+)/i', $info_or_update->version, $matches ) ) {
					$info_or_update->version = $matches[1];
				}
			}
			return $info_or_update;
		};

		$ai1wm_update_checker->addFilter( 'request_info_result', $ai1wm_normalize_version );
		$ai1wm_update_checker->addFilter( 'request_update_result', $ai1wm_normalize_version );

		// Monorepo rewrite: GitHub API busca en la raíz por defecto, reescribir a la subcarpeta del plugin
		add_filter(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) {
				static $in_flight = false;
				if ( $in_flight || ! is_string( $url ) ) {
					return $preempt;
				}

				if ( false !== strpos( $url, 'contents/modern-wp-migrator.php' ) ) {
					$in_flight = true;
					$target_url = str_replace( 'contents/modern-wp-migrator.php', 'contents/plugins/modern-wp-migrator/modern-wp-migrator.php', $url );
					$response = wp_remote_get( $target_url, $parsed_args );
					$in_flight = false;
					return $response;
				}

				return $preempt;
			},
			10,
			3
		);
	}
}
