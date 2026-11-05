<?php
/**
 * Essential Hardening: Actualizaciones automáticas desde GitHub con Plugin Update Checker (PUC).
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Updater {

	/**
	 * Inicializar el verificador de actualizaciones automáticas desde GitHub (PUC).
	 */
	private function init_update_checker(): void {
		$puc_file = dirname( ESSENTIAL_HARDENING_FILE ) . '/vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $puc_file ) ) {
			return;
		}

		require_once $puc_file;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/luiskaco/utilities_wordpress/',
			ESSENTIAL_HARDENING_FILE,
			'essential-hardening'
		);

		// Filtrar solo los releases con tag de este plugin (ej: essential-hardening-v1.1.1)
		$vcs_api = $update_checker->getVcsApi();
		if ( method_exists( $vcs_api, 'setReleaseVersionFilter' ) ) {
			$vcs_api->setReleaseVersionFilter( '/^essential-hardening-/i' );
		}

		// Descargar exclusivamente el archivo ZIP correspondiente a este plugin
		if ( method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets(
				'/^essential-hardening.*\.zip$/i',
				$vcs_api::REQUIRE_RELEASE_ASSETS
			);
		}

		// Normalizar la versión devuelta por el tag para la comparación semver de WordPress
		$normalize_version = function( $info_or_update ) {
			if ( $info_or_update && isset( $info_or_update->version ) ) {
				if ( preg_match( '/^essential-hardening-v?([0-9\.]+)/i', $info_or_update->version, $matches ) ) {
					$info_or_update->version = $matches[1];
				}
			}
			return $info_or_update;
		};

		$update_checker->addFilter( 'request_info_result', $normalize_version );
		$update_checker->addFilter( 'request_update_result', $normalize_version );

		// Monorepo rewrite: GitHub API busca en la raíz por defecto, reescribir a la subcarpeta del plugin
		add_filter(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) {
				static $in_flight = false;
				if ( $in_flight || ! is_string( $url ) ) {
					return $preempt;
				}

				if ( false !== strpos( $url, 'contents/essential-hardening.php' ) ) {
					$in_flight = true;
					$target_url = str_replace( 'contents/essential-hardening.php', 'contents/plugins/essential-hardening/essential-hardening.php', $url );
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
