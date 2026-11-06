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

	/**
	 * País de cada IP, solo si el usuario activó «Mostrar país de las IP».
	 *
	 * Consulta https://ipwho.is (sin clave) y guarda el resultado 30 días por IP, así que cada IP se
	 * consulta una sola vez. Para no volver lenta la pantalla, hace como mucho 6 consultas nuevas por
	 * carga: las IP que falten se completan al recargar. Las IP locales o reservadas no se consultan.
	 *
	 * @param string[] $ips IP a resolver.
	 * @return array<string, array{code: string, name: string}> IP => país. Las que no se pudieron resolver no aparecen.
	 */
	private function get_ip_countries( array $ips ): array {
		if ( empty( $this->options['show_ip_country'] ) ) {
			return array();
		}

		$result      = array();
		$lookups_left = 6;

		foreach ( array_unique( $ips ) as $ip ) {
			if ( ! $this->is_ip_bannable( (string) $ip ) ) {
				continue;
			}

			/**
			 * Permite resolver el país sin consulta externa (base de datos GeoIP propia, cabecera de la CDN o pruebas).
			 *
			 * @param array|null $country array( 'code' => 'ES', 'name' => 'España' ) o null para usar el servicio por defecto.
			 * @param string     $ip      IP a resolver.
			 */
			$country = apply_filters( 'essential_hardening_ip_country', null, $ip );

			if ( null === $country ) {
				$cache_key = 'eh_geo_' . md5( $ip );
				$cached    = get_transient( $cache_key );

				if ( false !== $cached ) {
					$country = is_array( $cached ) ? $cached : null;
				} elseif ( $lookups_left > 0 ) {
					--$lookups_left;
					$country = $this->lookup_ip_country( $ip );
					// Un fallo se recuerda solo 1 hora; un acierto, 30 días.
					set_transient( $cache_key, null === $country ? 'none' : $country, null === $country ? HOUR_IN_SECONDS : 30 * DAY_IN_SECONDS );
				}
			}

			if ( is_array( $country ) && ! empty( $country['code'] ) && preg_match( '/^[A-Z]{2}$/', (string) $country['code'] ) ) {
				$result[ $ip ] = array(
					'code' => (string) $country['code'],
					'name' => isset( $country['name'] ) ? (string) $country['name'] : (string) $country['code'],
				);
			}
		}

		return $result;
	}

	/**
	 * Consulta el país de una IP pública. Devuelve null si el servicio no responde o no la conoce.
	 *
	 * @return array{code: string, name: string}|null
	 */
	private function lookup_ip_country( string $ip ): ?array {
		$response = wp_remote_get(
			'https://ipwho.is/' . rawurlencode( $ip ) . '?fields=success,country,country_code',
			array(
				'timeout'    => 3,
				'user-agent' => 'EssentialHardening/' . self::VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) || empty( $data['country_code'] ) || ! preg_match( '/^[A-Za-z]{2}$/', (string) $data['country_code'] ) ) {
			return null;
		}

		return array(
			'code' => strtoupper( (string) $data['country_code'] ),
			'name' => isset( $data['country'] ) ? sanitize_text_field( (string) $data['country'] ) : strtoupper( (string) $data['country_code'] ),
		);
	}

	/**
	 * HTML de la bandera y el nombre del país (la imagen la carga el navegador desde flagcdn.com).
	 */
	private function render_country_label( array $country ): string {
		$code = strtolower( $country['code'] );
		return sprintf(
			'<img src="%1$s" srcset="%2$s 2x" width="20" height="15" alt="%3$s" loading="lazy" referrerpolicy="no-referrer" style="vertical-align: -2px; margin-right: 6px; border: 1px solid #dcdcde;"> %4$s',
			esc_url( 'https://flagcdn.com/w20/' . $code . '.png' ),
			esc_url( 'https://flagcdn.com/w40/' . $code . '.png' ),
			esc_attr( $country['code'] ),
			esc_html( $country['name'] )
		);
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
