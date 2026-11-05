<?php
/**
 * Limpieza al desinstalar WP LLMs.txt Manager.
 * Elimina las opciones y los archivos físicos que creó el propio plugin (nunca archivos ajenos).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wp_llms_managed = get_option( 'wp_llms_txt_managed_files', array() );
if ( is_array( $wp_llms_managed ) ) {
	foreach ( $wp_llms_managed as $wp_llms_name => $wp_llms_hash ) {
		$wp_llms_path = untrailingslashit( ABSPATH ) . '/' . basename( (string) $wp_llms_name );
		if ( file_exists( $wp_llms_path ) && md5_file( $wp_llms_path ) === $wp_llms_hash ) {
			unlink( $wp_llms_path );
		}
	}
}

delete_option( 'wp_llms_txt_options' );
delete_option( 'wp_llms_txt_managed_files' );
