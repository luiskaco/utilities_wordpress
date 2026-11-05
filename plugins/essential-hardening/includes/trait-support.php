<?php
/**
 * Essential Hardening: Utilidades compartidas: IP del cliente, envío seguro de correo, destinatario de alertas y el mini-registro de eventos de seguridad (módulo 17).
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Support {

	private function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Permite resolver la IP real detrás de un proxy o CDN de confianza (ej. Cloudflare).
		 * Por seguridad no se confía en cabeceras X-Forwarded-For por defecto: son falsificables.
		 *
		 * @param string $ip IP detectada desde REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'essential_hardening_client_ip', $ip );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Envío seguro de correos previniendo errores fatales si mail() está deshabilitada en el hosting.
	 *
	 * @param string|array $to      Destinatario.
	 * @param string       $subject Asunto.
	 * @param string       $message Cuerpo del mensaje.
	 * @param array        $headers Cabeceras opcionales.
	 * @return bool True si se despachó con éxito, false en caso de error o función mail() deshabilitada.
	 */
	private function safe_wp_mail( $to, string $subject, string $message, array $headers = array() ): bool {
		try {
			return (bool) wp_mail( $to, $subject, $message, $headers );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Essential Hardening - Error en envío de correo (PHPMailer): ' . $e->getMessage() );
			}
			return false;
		}
	}

	public function log_security_event( string $type, string $ip, string $detail ): void {
		if ( empty( $this->options['security_event_log'] ) ) {
			return;
		}

		$events = get_option( 'eh_security_events_log', array() );
		if ( ! is_array( $events ) ) {
			$events = array();
		}

		array_unshift(
			$events,
			array(
				'timestamp' => current_time( 'timestamp' ),
				'type'      => $type,
				'ip'        => $ip,
				'detail'    => $detail,
			)
		);

		if ( count( $events ) > self::MAX_EVENT_LOG_ENTRIES ) {
			$events = array_slice( $events, 0, self::MAX_EVENT_LOG_ENTRIES );
		}

		update_option( 'eh_security_events_log', $events, false );
	}

	public function handle_clear_security_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'essential-hardening' ) );
		}

		check_admin_referer( 'eh_clear_log_nonce' );
		delete_option( 'eh_security_events_log' );

		wp_safe_redirect( add_query_arg( array( 'page' => 'essential-hardening', 'eh_log_cleared' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Destinatario de las alertas: el correo configurado o el del administrador del sitio.
	 */
	private function get_alert_recipient(): string {
		$recipient = ! empty( $this->options['update_notify_email'] ) ? sanitize_email( $this->options['update_notify_email'] ) : '';
		if ( ! is_email( $recipient ) ) {
			$recipient = (string) get_option( 'admin_email' );
		}
		return $recipient;
	}
}
