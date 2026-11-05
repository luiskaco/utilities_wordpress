<?php
/**
 * Essential Hardening: Módulos 20 y 22: protección de la recuperación de contraseña y contraseñas robustas para administradores.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Accounts {

	private function module_lostpassword_protection(): void {
		add_action( 'lostpassword_post', array( $this, 'limit_lostpassword_requests' ), 10, 2 );
		add_filter( 'lostpassword_errors', array( $this, 'generic_lostpassword_response' ), 99, 2 );
	}

	/**
	 * Limita las solicitudes de recuperación por IP y hora: evita el bombardeo de correos a un usuario.
	 *
	 * @param WP_Error         $errors    Errores acumulados de la solicitud.
	 * @param WP_User|false    $user_data Usuario encontrado o false.
	 */
	public function limit_lostpassword_requests( $errors, $user_data = false ): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! ( $errors instanceof WP_Error ) ) {
			return;
		}

		/**
		 * Máximo de solicitudes de recuperación de contraseña por IP y por hora.
		 *
		 * @param int $max Por defecto 5.
		 */
		$max   = max( 1, (int) apply_filters( 'essential_hardening_lostpassword_max_per_hour', 5 ) );
		$ip    = $this->get_client_ip();
		$key   = 'eh_lostpw_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			if ( $count === $max ) {
				// Se registra solo la primera vez que se supera el límite (evita llenar el registro)
				$this->log_security_event( 'Recuperación', $ip, __( 'Demasiadas solicitudes de recuperación de contraseña desde esta IP', 'essential-hardening' ) );
				set_transient( $key, $count + 1, HOUR_IN_SECONDS );
			}
			$errors->add( 'eh_lostpw_limited', esc_html__( 'Has hecho demasiadas solicitudes de recuperación de contraseña. Inténtalo de nuevo en una hora.', 'essential-hardening' ) );
			return;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Da la misma respuesta si la cuenta existe o no. Sin esto, WordPress responde "no hay ninguna cuenta con ese
	 * usuario o correo", lo que permite descubrir qué usuarios existen. Los errores de entrada vacía y el límite
	 * de solicitudes se siguen mostrando.
	 *
	 * @param WP_Error      $errors    Errores acumulados.
	 * @param WP_User|false $user_data Usuario encontrado o false.
	 * @return WP_Error
	 */
	public function generic_lostpassword_response( $errors, $user_data = false ) {
		if ( ! ( $errors instanceof WP_Error ) ) {
			return $errors;
		}

		$codes        = $errors->get_error_codes();
		$only_unknown = empty( $codes ) ? ! $user_data : array() === array_diff( $codes, array( 'invalid_email' ) );

		if ( $only_unknown ) {
			wp_safe_redirect( add_query_arg( 'checkemail', 'confirm', wp_login_url() ) );
			exit;
		}

		return $errors;
	}

	private function module_strong_admin_passwords(): void {
		add_action( 'user_profile_update_errors', array( $this, 'validate_admin_password_on_profile' ), 10, 3 );
		add_action( 'validate_password_reset', array( $this, 'validate_admin_password_on_reset' ), 10, 2 );
	}

	/**
	 * @param WP_Error $errors Errores de la actualización.
	 * @param bool     $update True si se edita un usuario existente.
	 * @param object   $user   Datos del usuario (user_pass trae la contraseña nueva en texto plano).
	 */
	public function validate_admin_password_on_profile( $errors, $update, $user ): void {
		if ( ! ( $errors instanceof WP_Error ) || empty( $user->user_pass ) ) {
			return;
		}

		$will_be_admin = isset( $user->role ) && '' !== $user->role
			? 'administrator' === $user->role
			: ( ! empty( $user->ID ) && user_can( (int) $user->ID, 'manage_options' ) );

		if ( ! $will_be_admin ) {
			return;
		}

		$problem = $this->get_admin_password_problem( (string) $user->user_pass );
		if ( '' !== $problem ) {
			$errors->add( 'eh_weak_password', $problem );
		}
	}

	/**
	 * @param WP_Error $errors Errores del formulario de restablecimiento.
	 * @param WP_User  $user   Usuario que restablece la contraseña.
	 */
	public function validate_admin_password_on_reset( $errors, $user ): void {
		if ( ! ( $errors instanceof WP_Error ) || ! ( $user instanceof WP_User ) || ! user_can( $user, 'manage_options' ) ) {
			return;
		}

		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		if ( '' === $password ) {
			return;
		}

		$problem = $this->get_admin_password_problem( $password );
		if ( '' !== $problem ) {
			$errors->add( 'eh_weak_password', $problem );
		}
	}

	/**
	 * Devuelve el motivo por el que una contraseña de administrador no es aceptable, o '' si lo es.
	 */
	public function get_admin_password_problem( string $password ): string {
		/**
		 * Longitud mínima de la contraseña de un administrador.
		 *
		 * @param int $min Por defecto 12.
		 */
		$min = max( 8, (int) apply_filters( 'essential_hardening_admin_password_min_length', 12 ) );

		if ( strlen( $password ) < $min ) {
			return sprintf(
				/* translators: %d: longitud mínima */
				esc_html__( 'Las cuentas de administrador necesitan una contraseña de al menos %d caracteres.', 'essential-hardening' ),
				$min
			);
		}

		$count = $this->count_password_breaches( $password );
		if ( $count > 0 ) {
			return sprintf(
				/* translators: %s: cantidad de filtraciones */
				esc_html__( 'Esa contraseña aparece %s veces en filtraciones de datos conocidas. Elige otra distinta.', 'essential-hardening' ),
				number_format_i18n( $count )
			);
		}

		return '';
	}

	/**
	 * Consulta cuántas veces aparece una contraseña en filtraciones conocidas (Have I Been Pwned, rango k-anonimato).
	 *
	 * Solo se envían los 5 primeros caracteres del hash SHA-1: ni la contraseña ni el hash completo salen del servidor.
	 * Si el servicio no responde, la contraseña se acepta (no se bloquea a nadie por una caída externa).
	 *
	 * @return int Número de apariciones; 0 si no aparece o si no se pudo comprobar.
	 */
	public function count_password_breaches( string $password ): int {
		$hash   = strtoupper( sha1( $password ) );
		$prefix = substr( $hash, 0, 5 );
		$suffix = substr( $hash, 5 );

		$response = wp_remote_get(
			'https://api.pwnedpasswords.com/range/' . $prefix,
			array(
				'timeout'    => 5,
				'user-agent' => 'Essential-Hardening/' . self::VERSION,
				'headers'    => array( 'Add-Padding' => 'true' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return 0;
		}

		foreach ( preg_split( '/\r\n|\r|\n/', (string) wp_remote_retrieve_body( $response ) ) as $line ) {
			$parts = explode( ':', trim( $line ) );
			if ( 2 === count( $parts ) && strtoupper( $parts[0] ) === $suffix ) {
				return (int) $parts[1];
			}
		}

		return 0;
	}
}
