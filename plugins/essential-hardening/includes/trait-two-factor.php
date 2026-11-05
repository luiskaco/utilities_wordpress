<?php
/**
 * Essential Hardening: Módulo 16: doble factor de autenticación (2FA) por correo.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Two_Factor {

	private function module_email_2fa(): void {
		// Interceptar login tras validar credenciales para usuarios administradores
		add_filter( 'authenticate', array( $this, 'intercept_login_for_2fa' ), 50, 3 );

		// Procesar la pantalla interactiva de 2FA en wp-login.php
		add_action( 'login_form_eh_2fa', array( $this, 'render_and_process_2fa_screen' ) );
	}

	public function intercept_login_for_2fa( $user, string $username, string $password ) {
		// Rescate de emergencia por constante en wp-config.php
		if ( defined( 'EH_DISABLE_2FA' ) && EH_DISABLE_2FA ) {
			return $user;
		}

		// Solo si es un objeto de usuario válido con capacidades de administración
		if ( ! ( $user instanceof WP_User ) || ! $user->has_cap( 'manage_options' ) ) {
			return $user;
		}

		// Solo en peticiones POST de wp-login.php (evita interferir en CLI, Cron, REST API)
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['log'] ) ) {
			return $user;
		}

		// Limitar la emisión de códigos por usuario (evita spam de correos y reintentos masivos de adivinación)
		$rate_key   = 'eh_2fa_rate_' . $user->ID;
		$rate_count = (int) get_transient( $rate_key );
		if ( $rate_count >= 5 ) {
			$this->log_security_event( '2FA Fallido', $this->get_client_ip(), sprintf( __( 'Demasiadas solicitudes de código 2FA (usuario: %s)', 'essential-hardening' ), $user->user_login ) );
			return new WP_Error( 'eh_2fa_rate_limited', esc_html__( 'Demasiados intentos de acceso. Espera unos minutos antes de volver a intentarlo.', 'essential-hardening' ) );
		}
		set_transient( $rate_key, $rate_count + 1, 10 * MINUTE_IN_SECONDS );

		$code     = sprintf( '%06d', wp_rand( 100000, 999999 ) );
		$token    = wp_generate_password( 32, false, false );
		$remember = ! empty( $_POST['rememberme'] );

		set_transient(
			'eh_2fa_' . $token,
			array(
				'user_id'  => $user->ID,
				'hash'     => wp_hash_password( $code ),
				'remember' => $remember,
				'attempts' => 0,
			),
			10 * MINUTE_IN_SECONDS
		);

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$subject   = sprintf( __( '[Código 2FA] Tu clave de acceso a %s', 'essential-hardening' ), $site_name );
		$message   = sprintf(
			"Hola %s,\n\nTu código de verificación de doble factor (2FA) para acceder a %s es:\n\n%s\n\nEste código expira en 10 minutos.\nSi no solicitaste este acceso, te sugerimos cambiar tu contraseña de inmediato.\n\n--\nEssential Hardening & Security",
			$user->display_name,
			$site_name,
			$code
		);

		$mail_sent = $this->safe_wp_mail( $user->user_email, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );

		if ( ! $mail_sent ) {
			// Si el hosting no tiene servicio de correo configurado o mail() está deshabilitada,
			// no atrapamos al administrador en una pantalla de verificación imposible.
			delete_transient( 'eh_2fa_' . $token );
			$this->log_security_event( '2FA Omitido', $this->get_client_ip(), sprintf( __( 'Fallo en envío de código 2FA a %s (servidor sin correo/SMTP). Acceso permitido.', 'essential-hardening' ), $user->user_login ) );
			return $user;
		}

		$redirect_url = add_query_arg(
			array(
				'action' => 'eh_2fa',
				'token'  => $token,
			),
			wp_login_url()
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_and_process_2fa_screen(): void {
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		if ( empty( $token ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$transient_key = 'eh_2fa_' . $token;
		$data          = get_transient( $transient_key );

		if ( empty( $data ) || ! is_array( $data ) ) {
			login_header( __( 'Código Expirado', 'essential-hardening' ), '<p class="message error">' . esc_html__( 'El código de seguridad ha expirado. Por favor, inicia sesión nuevamente.', 'essential-hardening' ) . '</p>' );
			login_footer();
			exit;
		}

		$user = get_user_by( 'id', $data['user_id'] );
		if ( ! ( $user instanceof WP_User ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$error_message = '';

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['eh_auth_code'] ) ) {
			check_admin_referer( 'eh_verify_2fa_action' );

			$submitted_code = sanitize_text_field( wp_unslash( $_POST['eh_auth_code'] ) );

			if ( wp_check_password( $submitted_code, $data['hash'] ) ) {
				delete_transient( $transient_key );
				wp_set_auth_cookie( $user->ID, ! empty( $data['remember'] ) );
				do_action( 'wp_login', $user->user_login, $user );

				wp_safe_redirect( admin_url() );
				exit;
			}

			$data['attempts'] = ( $data['attempts'] ?? 0 ) + 1;
			if ( $data['attempts'] >= 3 ) {
				delete_transient( $transient_key );
				$this->log_security_event( '2FA Fallido', $this->get_client_ip(), sprintf( __( '3 intentos fallidos de 2FA (usuario: %s)', 'essential-hardening' ), $user->user_login ) );
				if ( ! empty( $this->options['login_throttle'] ) ) {
					$this->record_failed_login( $user->user_login );
				}
				login_header( __( 'Acceso Bloqueado', 'essential-hardening' ), '<p class="message error">' . esc_html__( 'Demasiados intentos fallidos del código de seguridad. Intenta iniciar sesión de nuevo.', 'essential-hardening' ) . '</p>' );
				login_footer();
				exit;
			}

			set_transient( $transient_key, $data, 10 * MINUTE_IN_SECONDS );
			$error_message = sprintf( esc_html__( 'Código incorrecto. Le quedan %d intento(s).', 'essential-hardening' ), 3 - $data['attempts'] );
		}

		$masked_email = substr( $user->user_email, 0, 3 ) . '***@' . substr( strrchr( $user->user_email, '@' ), 1 );

		login_header(
			__( 'Doble Factor de Autenticación', 'essential-hardening' ),
			$error_message ? '<p class="message error">' . esc_html( $error_message ) . '</p>' : '<p class="message info">' . sprintf( esc_html__( 'Se ha enviado un código de 6 dígitos a %s. Ingréselo para acceder.', 'essential-hardening' ), '<strong>' . esc_html( $masked_email ) . '</strong>' ) . '</p>'
		);
		?>
		<form name="eh_2fa_form" id="loginform" method="post" action="<?php echo esc_url( add_query_arg( array( 'action' => 'eh_2fa', 'token' => $token ), wp_login_url() ) ); ?>">
			<?php wp_nonce_field( 'eh_verify_2fa_action' ); ?>
			<p>
				<label for="eh_auth_code"><?php esc_html_e( 'Código de 6 dígitos:', 'essential-hardening' ); ?></label>
				<input type="text" name="eh_auth_code" id="eh_auth_code" class="input" value="" size="6" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus style="font-size: 24px; text-align: center; letter-spacing: 6px; font-weight: bold;" />
			</p>
			<p class="submit" style="margin-top: 20px;">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Verificar e Iniciar Sesión', 'essential-hardening' ); ?>" style="width: 100%;" />
			</p>
		</form>
		<p id="nav" style="text-align: center;">
			<a href="<?php echo esc_url( wp_login_url() ); ?>">&larr; <?php esc_html_e( 'Volver a la pantalla de inicio de sesión', 'essential-hardening' ); ?></a>
		</p>
		<?php
		login_footer();
		exit;
	}
}
