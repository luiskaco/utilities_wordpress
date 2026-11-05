<?php
/**
 * Limpieza al desinstalar Essential Hardening & Security.
 * Elimina opciones, registro de eventos, cron y transients propios.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'essential_hardening_settings' );
delete_option( 'eh_security_events_log' );
wp_clear_scheduled_hook( 'eh_daily_update_check_cron' );

// Transients de bloqueo, 2FA y control de registro (se guardan con prefijo propio).
$eh_like_patterns = array(
	$wpdb->esc_like( '_transient_eh_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_eh_' ) . '%',
);
foreach ( $eh_like_patterns as $eh_pattern ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $eh_pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
