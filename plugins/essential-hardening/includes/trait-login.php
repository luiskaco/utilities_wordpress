<?php
/**
 * Essential Hardening: Módulos 9 y 13: mitigación de fuerza bruta (rate limiting) y honeypot anti-bot en el login.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Login {

	private function module_login_throttle(): void {
		// Verificar bloqueo antes de procesar credenciales
		add_filter( 'authenticate', array( $this, 'check_login_throttle' ), 5, 3 );

		// Registrar intento fallido y aplicar retardo
		add_action( 'wp_login_failed', array( $this, 'record_failed_login' ) );

		// Limpiar intentos si la autenticación fue satisfactoria
		add_action( 'wp_login', array( $this, 'clear_failed_login' ), 10, 2 );
	}

	public function check_login_throttle( $user, string $username, string $password ) {
		$ip          = $this->get_client_ip();
		$lockout_key = 'eh_lockout_' . md5( $ip );

		if ( get_transient( $lockout_key ) ) {
			$duration_seconds = ! empty( $this->options['throttle_lockout_duration'] ) ? absint( $this->options['throttle_lockout_duration'] ) : 900;
			$human_time       = $this->get_human_lockout_duration( $duration_seconds );

			return new WP_Error(
				'eh_ip_locked',
				sprintf(
					/* translators: %s: tiempo de bloqueo */
					esc_html__( 'Demasiados intentos fallidos. Su dirección IP ha sido bloqueada temporalmente por %s.', 'essential-hardening' ),
					$human_time
				)
			);
		}

		return $user;
	}

	/**
	 * Obtener texto legible para la duración del bloqueo.
	 */
	private function get_human_lockout_duration( int $seconds ): string {
		if ( $seconds >= 86400 ) {
			$days = (int) ( $seconds / 86400 );
			return sprintf( _n( '%d día', '%d días', $days, 'essential-hardening' ), $days );
		}
		if ( $seconds >= 3600 ) {
			$hours = (int) ( $seconds / 3600 );
			return sprintf( _n( '%d hora', '%d horas', $hours, 'essential-hardening' ), $hours );
		}
		$minutes = (int) ( $seconds / 60 );
		return sprintf( _n( '%d minuto', '%d minutos', $minutes, 'essential-hardening' ), $minutes );
	}

	public function record_failed_login( string $username ): void {
		$ip          = $this->get_client_ip();
		$count_key   = 'eh_fail_count_' . md5( $ip );
		$lockout_key = 'eh_lockout_' . md5( $ip );

		// Si la IP ya está bloqueada no se suma ni se duerme: evita extender el bloqueo al usuario legítimo
		// y que un atacante mantenga procesos PHP ocupados.
		if ( get_transient( $lockout_key ) ) {
			return;
		}

		$attempts = (int) get_transient( $count_key );
		$attempts++;

		// Retardo corto para amortiguar ataques de diccionario (filtrable; 0 lo desactiva)
		$delay = (int) apply_filters( 'essential_hardening_failed_login_delay', 2 );
		if ( $delay > 0 ) {
			sleep( min( $delay, 5 ) );
		}

		$max_attempts     = ! empty( $this->options['throttle_max_attempts'] ) ? absint( $this->options['throttle_max_attempts'] ) : 5;
		$lockout_duration = ! empty( $this->options['throttle_lockout_duration'] ) ? absint( $this->options['throttle_lockout_duration'] ) : 900;

		if ( $attempts >= $max_attempts ) {
			// Bloqueo temporal por la duración configurada
			set_transient( $lockout_key, 1, $lockout_duration );
			delete_transient( $count_key );
			$this->log_security_event( 'Fuerza Bruta', $ip, sprintf( __( 'IP bloqueada por %s tras acumular %d fallos consecutivos', 'essential-hardening' ), $this->get_human_lockout_duration( $lockout_duration ), $max_attempts ) );
		} else {
			// Ventana móvil de conteo por la duración configurada
			set_transient( $count_key, $attempts, $lockout_duration );
		}
	}

	public function clear_failed_login( string $username, $user ): void {
		$ip = $this->get_client_ip();
		delete_transient( 'eh_fail_count_' . md5( $ip ) );
		delete_transient( 'eh_lockout_' . md5( $ip ) );
	}

	private function module_login_honeypot(): void {
		// Inyectar campo trampa en el formulario de login de WordPress
		add_action( 'login_form', array( $this, 'render_login_honeypot' ) );

		// Validar antes de evaluar credenciales
		add_filter( 'authenticate', array( $this, 'validate_login_honeypot' ), 4, 3 );
	}

	public function render_login_honeypot(): void {
		?>
		<p class="eh-hp-field" style="display:none !important; visibility:hidden !important; position:absolute !important; left:-9999px !important; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
			<label for="eh_login_company"><?php esc_html_e( 'Si eres humano, deja este campo en blanco', 'essential-hardening' ); ?></label>
			<input type="text" name="eh_login_company" id="eh_login_company" value="" tabindex="-1" autocomplete="off" />
		</p>
		<?php
	}

	public function validate_login_honeypot( $user, string $username, string $password ) {
		// Si es una petición POST de login y el campo honeypot viene completado, es un bot
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['eh_login_company'] ) ) {
			$this->log_security_event( 'Honeypot Anti-Bot', $this->get_client_ip(), __( 'Bot bloqueado por completar campo invisible de login', 'essential-hardening' ) );

			return new WP_Error(
				'eh_bot_detected',
				esc_html__( 'Acceso bloqueado: actividad automatizada detectada.', 'essential-hardening' )
			);
		}

		return $user;
	}
}
