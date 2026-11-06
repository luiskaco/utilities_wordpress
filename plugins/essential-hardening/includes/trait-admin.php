<?php
/**
 * Essential Hardening: Panel de control en Ajustes > Seguridad Esencial: menú, registro y saneado de ajustes y pantalla de configuración.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Admin {

	public function register_admin_menu(): void {
		add_options_page(
			__( 'Hardening & Seguridad', 'essential-hardening' ),
			__( 'Seguridad Esencial', 'essential-hardening' ),
			'manage_options',
			'essential-hardening',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'essential_hardening_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	public function sanitize_settings( $input ): array {
		$sanitized = array();
		$defaults  = $this->get_default_settings();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		foreach ( $defaults as $key => $default_val ) {
			if ( is_bool( $default_val ) ) {
				$sanitized[ $key ] = ! empty( $input[ $key ] );
			} elseif ( is_int( $default_val ) ) {
				$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $default_val;
			}
		}

		// Validar opciones válidas para rate limiting
		$valid_attempts = array( 3, 5, 10 );
		if ( ! in_array( $sanitized['throttle_max_attempts'], $valid_attempts, true ) ) {
			$sanitized['throttle_max_attempts'] = 5;
		}

		$valid_durations = array( 900, 1800, 3600, 14400, 86400 );
		if ( ! in_array( $sanitized['throttle_lockout_duration'], $valid_durations, true ) ) {
			$sanitized['throttle_lockout_duration'] = 900;
		}

		if ( ! in_array( $sanitized['hsts_max_age'], self::get_hsts_max_age_choices(), true ) ) {
			$sanitized['hsts_max_age'] = 2592000;
		}

		// Sanitizar email para notificaciones
		$sanitized['update_notify_email'] = ! empty( $input['update_notify_email'] ) ? sanitize_email( $input['update_notify_email'] ) : '';

		return $sanitized;
	}

	public function add_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=essential-hardening' ) ) . '">' . esc_html__( 'Configurar', 'essential-hardening' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options         = $this->get_settings();
		$boolean_options = array_filter( $options, 'is_bool' );
		$active_count    = count( array_filter( $boolean_options ) );
		$total_count     = count( array_filter( $this->get_default_settings(), 'is_bool' ) );
		$recent_events   = get_option( 'eh_security_events_log', array() );
		if ( ! is_array( $recent_events ) ) {
			$recent_events = array();
		}

		if ( isset( $_GET['eh_email'] ) ) {
			if ( 'sent' === $_GET['eh_email'] ) {
				$recipient = ! empty( $options['update_notify_email'] ) ? $options['update_notify_email'] : get_option( 'admin_email' );
				echo '<div class="notice notice-success is-dismissible" style="margin-top: 15px;"><p>' . sprintf( esc_html__( 'Correo de prueba enviado con éxito a %s.', 'essential-hardening' ), '<strong>' . esc_html( $recipient ) . '</strong>' ) . '</p></div>';
			} elseif ( 'failed' === $_GET['eh_email'] ) {
				$mail_func_missing = ! function_exists( 'mail' );
				echo '<div class="notice notice-error is-dismissible" style="margin-top: 15px;">';
				echo '<p><strong>' . esc_html__( 'No se pudo enviar el correo de prueba.', 'essential-hardening' ) . '</strong></p>';
				if ( $mail_func_missing ) {
					echo '<p>' . esc_html__( 'Causa detectada: La función nativa mail() de PHP está deshabilitada en tu hosting (cPanel). Para poder enviar notificaciones o usar el 2FA debes instalar un plugin de SMTP (como WP Mail SMTP, FluentSMTP o Post SMTP) con tus credenciales de correo.', 'essential-hardening' ) . '</p>';
				} else {
					echo '<p>' . esc_html__( 'Verifica que tu servidor o plugin SMTP tenga configurada correctamente la salida de correo.', 'essential-hardening' ) . '</p>';
				}
				echo '</div>';
			}
		}

		if ( isset( $_GET['eh_core'] ) ) {
			$core_status = sanitize_key( wp_unslash( $_GET['eh_core'] ) );
			if ( 'clean' === $core_status ) {
				echo '<div class="notice notice-success is-dismissible" style="margin-top: 15px;"><p>' . esc_html__( 'Verificación completada: los archivos del núcleo coinciden con los oficiales.', 'essential-hardening' ) . '</p></div>';
			} elseif ( 'issues' === $core_status ) {
				echo '<div class="notice notice-warning is-dismissible" style="margin-top: 15px;"><p><strong>' . esc_html__( 'Se encontraron diferencias en los archivos del núcleo.', 'essential-hardening' ) . '</strong> ' . esc_html__( 'Revisa el detalle en la sección 7.', 'essential-hardening' ) . '</p></div>';
			} elseif ( 'unavailable' === $core_status ) {
				echo '<div class="notice notice-error is-dismissible" style="margin-top: 15px;"><p>' . esc_html__( 'No se pudo obtener la lista oficial de sumas de control (api.wordpress.org). Inténtalo de nuevo más tarde.', 'essential-hardening' ) . '</p></div>';
			}
		}

		if ( isset( $_GET['eh_log_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible" style="margin-top: 15px;"><p>' . esc_html__( 'Registro de eventos de seguridad vaciado con éxito.', 'essential-hardening' ) . '</p></div>';
		}
		?>
		<div class="wrap" id="eh-settings-wrap">
			<style>
				#eh-settings-wrap {
					max-width: 960px;
					margin-top: 20px;
				}
				.eh-banner {
					background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
					color: #ffffff;
					padding: 24px 30px;
					border-radius: 8px 8px 0 0;
					display: flex;
					align-items: center;
					justify-content: space-between;
					flex-wrap: wrap;
					gap: 15px;
					border: 1px solid #1d2327;
					border-bottom: none;
				}
				.eh-banner-title {
					margin: 0;
					font-size: 22px;
					font-weight: 700;
					color: #ffffff;
					display: flex;
					align-items: center;
					gap: 12px;
				}
				.eh-banner-subtitle {
					margin: 6px 0 0;
					font-size: 13px;
					color: #c3c4c7;
					max-width: 620px;
					line-height: 1.45;
				}
				.eh-version-badge {
					background: #2271b1;
					color: #ffffff;
					font-size: 11px;
					padding: 3px 8px;
					border-radius: 12px;
					font-weight: 600;
					letter-spacing: 0.5px;
				}
				.eh-stats-pill {
					background: rgba(255, 255, 255, 0.1);
					border: 1px solid rgba(255, 255, 255, 0.15);
					padding: 10px 18px;
					border-radius: 20px;
					font-size: 13px;
					font-weight: 500;
					color: #ffffff;
					text-align: right;
				}
				.eh-stats-pill strong {
					color: #72aee6;
					font-size: 15px;
				}
				.eh-card {
					background: #ffffff;
					border: 1px solid #c3c4c7;
					border-top: none;
					padding: 24px 28px;
				}
				.eh-card:last-of-type {
					border-radius: 0 0 8px 8px;
					border-bottom: 1px solid #c3c4c7;
				}
				.eh-card-header {
					display: flex;
					align-items: center;
					gap: 12px;
					margin-bottom: 16px;
					padding-bottom: 12px;
					border-bottom: 2px solid #f0f0f1;
				}
				.eh-card-icon {
					font-size: 22px;
					line-height: 1;
				}
				.eh-card-title {
					margin: 0;
					font-size: 16px;
					font-weight: 600;
					color: #1d2327;
				}
				.eh-card-desc {
					margin: 2px 0 0;
					font-size: 12px;
					color: #646970;
				}
				.eh-grid {
					display: grid;
					grid-template-columns: 1fr;
					gap: 12px;
				}
				@media (min-width: 782px) {
					.eh-grid-2col {
						grid-template-columns: 1fr 1fr;
					}
				}
				.eh-item {
					display: flex;
					align-items: flex-start;
					gap: 14px;
					background: #f6f7f7;
					border: 1px solid #dcdcde;
					border-radius: 6px;
					padding: 14px 16px;
					transition: all 0.15s ease-in-out;
				}
				.eh-item:hover {
					background: #f0f6fc;
					border-color: #72aee6;
				}
				.eh-item input[type="checkbox"] {
					margin-top: 3px;
					transform: scale(1.15);
					cursor: pointer;
				}
				.eh-item-body {
					flex: 1;
				}
				.eh-item-title-row {
					display: flex;
					align-items: center;
					gap: 8px;
					flex-wrap: wrap;
				}
				.eh-item-label {
					font-size: 13.5px;
					font-weight: 600;
					color: #1d2327;
					cursor: pointer;
				}
				.eh-tag {
					font-size: 10px;
					font-weight: 600;
					padding: 2px 7px;
					border-radius: 4px;
					text-transform: uppercase;
					letter-spacing: 0.3px;
				}
				.eh-tag-firewall { background: #e7f5ea; color: #185a25; }
				.eh-tag-access   { background: #eef2fa; color: #1a4282; }
				.eh-tag-privacy  { background: #fef4e8; color: #8a4b08; }
				.eh-tag-spam     { background: #fbeae5; color: #8a2414; }
				.eh-item-desc {
					margin: 5px 0 0;
					font-size: 12px;
					color: #50575e;
					line-height: 1.45;
				}
				.eh-sub-settings {
					margin-top: 10px;
					padding-top: 10px;
					border-top: 1px dashed #dcdcde;
					display: flex;
					flex-wrap: wrap;
					gap: 12px;
				}
				.eh-sub-setting {
					display: flex;
					flex-direction: column;
					gap: 4px;
				}
				.eh-sub-setting label {
					font-size: 11px;
					font-weight: 600;
					color: #50575e;
				}
				.eh-sub-setting select {
					font-size: 12px;
					padding: 2px 8px;
					height: 30px;
					border-radius: 4px;
					border-color: #8c8f94;
				}
				.eh-submit-bar {
					margin-top: 20px;
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'essential_hardening_group' ); ?>

				<!-- Banner Principal -->
				<div class="eh-banner">
					<div>
						<h1 class="eh-banner-title">
							<span>🛡️ Essential Hardening & Security</span>
							<span class="eh-version-badge">v<?php echo esc_html( self::VERSION ); ?></span>
						</h1>
						<p class="eh-banner-subtitle">
							<?php esc_html_e( 'Suite modular de protección perimetral, control de accesos y endurecimiento de WordPress. Ajuste cada escudo individualmente según las características de su servidor o plugins complementarios.', 'essential-hardening' ); ?>
						</p>
					</div>
					<div class="eh-stats-pill">
						<span><?php esc_html_e( 'Estado General:', 'essential-hardening' ); ?></span><br>
						<strong><?php echo esc_html( (string) $active_count ); ?></strong> / <?php echo esc_html( (string) $total_count ); ?> <?php esc_html_e( 'escudos activos', 'essential-hardening' ); ?>
					</div>
				</div>

				<!-- CATEGORÍA 1: Cortafuegos & Perímetro -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🧱</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '1. Cortafuegos y Blindaje de Archivos', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Filtra peticiones en tiempo real y sella directorios vulnerables a nivel de servidor.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid eh-grid-2col">
						<!-- Micro-WAF -->
						<div class="eh-item">
							<input type="checkbox" id="eh_query_firewall" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[query_firewall]" value="1" <?php checked( $options['query_firewall'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_query_firewall" class="eh-item-label"><?php esc_html_e( 'Micro-WAF (Cortafuegos URL)', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Firewall', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Bloquea peticiones maliciosas (HTTP 403): inyecciones SQL (UNION SELECT), funciones RCE (eval, base64_decode), LFI (wp-config.php) y path traversal.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Protección de Uploads -->
						<div class="eh-item">
							<input type="checkbox" id="eh_protect_uploads" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protect_uploads]" value="1" <?php checked( $options['protect_uploads'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_protect_uploads" class="eh-item-label"><?php esc_html_e( 'Protección de Uploads', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Servidor', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Escribe reglas en .htaccess de uploads para impedir la ejecución directa de cualquier script PHP subido por plugins vulnerables.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Cabeceras HTTP -->
						<div class="eh-item">
							<input type="checkbox" id="eh_security_headers" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[security_headers]" value="1" <?php checked( $options['security_headers'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_security_headers" class="eh-item-label"><?php esc_html_e( 'Cabeceras HTTP Defensivas', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Headers', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Inyecta X-Frame-Options (anti-clickjacking), X-Content-Type-Options: nosniff, Referrer-Policy y Permissions-Policy en cada respuesta HTTP. No incluye CSP ni HSTS: dependen de cada sitio.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- HSTS -->
						<div class="eh-item">
							<input type="checkbox" id="eh_hsts_enabled" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hsts_enabled]" value="1" <?php checked( $options['hsts_enabled'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_hsts_enabled" class="eh-item-label"><?php esc_html_e( 'HSTS (Forzar HTTPS)', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Headers', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Ordena al navegador usar solo HTTPS en este sitio. Se envía únicamente en conexiones HTTPS. Actívalo solo si el certificado SSL es válido y está renovando bien: si caduca, los visitantes no podrán entrar.', 'essential-hardening' ); ?></p>
								<div class="eh-sub-settings">
									<div class="eh-sub-setting">
										<label for="eh_hsts_max_age"><?php esc_html_e( 'Duración:', 'essential-hardening' ); ?></label>
										<select id="eh_hsts_max_age" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hsts_max_age]">
											<option value="300" <?php selected( $options['hsts_max_age'], 300 ); ?>><?php esc_html_e( '5 minutos (prueba)', 'essential-hardening' ); ?></option>
											<option value="86400" <?php selected( $options['hsts_max_age'], 86400 ); ?>><?php esc_html_e( '1 día', 'essential-hardening' ); ?></option>
											<option value="2592000" <?php selected( $options['hsts_max_age'], 2592000 ); ?>><?php esc_html_e( '30 días (Recomendado)', 'essential-hardening' ); ?></option>
											<option value="15552000" <?php selected( $options['hsts_max_age'], 15552000 ); ?>><?php esc_html_e( '6 meses', 'essential-hardening' ); ?></option>
											<option value="31536000" <?php selected( $options['hsts_max_age'], 31536000 ); ?>><?php esc_html_e( '1 año', 'essential-hardening' ); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>

						<!-- CSP solo-reporte -->
						<div class="eh-item">
							<input type="checkbox" id="eh_csp_report_only" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[csp_report_only]" value="1" <?php checked( $options['csp_report_only'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_csp_report_only" class="eh-item-label"><?php esc_html_e( 'CSP en modo solo-reporte', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Headers', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Envía una Content-Security-Policy que NO bloquea nada: el navegador solo anota en su consola (F12) qué recursos externos incumplirían la política. Sirve para ver el impacto antes de endurecer.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Desactivar XML-RPC -->
						<div class="eh-item">
							<input type="checkbox" id="eh_disable_xmlrpc" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_xmlrpc]" value="1" <?php checked( $options['disable_xmlrpc'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_disable_xmlrpc" class="eh-item-label"><?php esc_html_e( 'Desactivar XML-RPC', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Protocolo', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Cierra la interfaz xmlrpc.php, neutralizando ataques históricos de fuerza bruta multicredencial y amplificación de ataques DDoS.', 'essential-hardening' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 2: Acceso & Fuerza Bruta -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🔑</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '2. Acceso y Control de Fuerza Bruta', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Amortigua y bloquea intentos automatizados de intrusión en el formulario de inicio de sesión.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid eh-grid-2col">
						<!-- Login Throttle -->
						<div class="eh-item">
							<input type="checkbox" id="eh_login_throttle" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_throttle]" value="1" <?php checked( $options['login_throttle'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_login_throttle" class="eh-item-label"><?php esc_html_e( 'Rate-Limiting de Login (Throttle)', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Fuerza Bruta', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Añade 2s de retardo tras cada fallo y bloquea temporalmente la IP si supera el umbral de intentos fallidos permitidos.', 'essential-hardening' ); ?></p>
								<div class="eh-sub-settings">
									<div class="eh-sub-setting">
										<label for="eh_throttle_max_attempts"><?php esc_html_e( 'Intentos permitidos:', 'essential-hardening' ); ?></label>
										<select id="eh_throttle_max_attempts" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[throttle_max_attempts]">
											<option value="3" <?php selected( $options['throttle_max_attempts'], 3 ); ?>><?php esc_html_e( '3 intentos (Estricto)', 'essential-hardening' ); ?></option>
											<option value="5" <?php selected( $options['throttle_max_attempts'], 5 ); ?>><?php esc_html_e( '5 intentos (Recomendado)', 'essential-hardening' ); ?></option>
											<option value="10" <?php selected( $options['throttle_max_attempts'], 10 ); ?>><?php esc_html_e( '10 intentos (Permisivo)', 'essential-hardening' ); ?></option>
										</select>
									</div>
									<div class="eh-sub-setting">
										<label for="eh_throttle_lockout_duration"><?php esc_html_e( 'Tiempo de bloqueo:', 'essential-hardening' ); ?></label>
										<select id="eh_throttle_lockout_duration" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[throttle_lockout_duration]">
											<option value="900" <?php selected( $options['throttle_lockout_duration'], 900 ); ?>><?php esc_html_e( '15 minutos (Estándar)', 'essential-hardening' ); ?></option>
											<option value="1800" <?php selected( $options['throttle_lockout_duration'], 1800 ); ?>><?php esc_html_e( '30 minutos', 'essential-hardening' ); ?></option>
											<option value="3600" <?php selected( $options['throttle_lockout_duration'], 3600 ); ?>><?php esc_html_e( '1 hora (Seguro)', 'essential-hardening' ); ?></option>
											<option value="14400" <?php selected( $options['throttle_lockout_duration'], 14400 ); ?>><?php esc_html_e( '4 horas', 'essential-hardening' ); ?></option>
											<option value="86400" <?php selected( $options['throttle_lockout_duration'], 86400 ); ?>><?php esc_html_e( '24 horas (Máxima protección)', 'essential-hardening' ); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>

						<!-- Honeypot Anti-Bot en Login -->
						<div class="eh-item">
							<input type="checkbox" id="eh_login_honeypot" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_honeypot]" value="1" <?php checked( $options['login_honeypot'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_login_honeypot" class="eh-item-label"><?php esc_html_e( 'Honeypot Anti-Bot en Login', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Sin Captcha', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Inserta un campo trampa invisible en /wp-login.php. Los humanos no lo ven ni deben resolver acertijos, pero los bots automatizados lo completan y se les rechaza el login al instante.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Login Errors Genéricos -->
						<div class="eh-item">
							<input type="checkbox" id="eh_generic_login_errors" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[generic_login_errors]" value="1" <?php checked( $options['generic_login_errors'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_generic_login_errors" class="eh-item-label"><?php esc_html_e( 'Errores de Login Genéricos', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Ofuscación', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Muestra siempre "Usuario o contraseña incorrectos", impidiendo saber si el nombre de usuario existe en la base de datos.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Bloquear Edición de Archivos -->
						<div class="eh-item">
							<input type="checkbox" id="eh_disallow_file_edit" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disallow_file_edit]" value="1" <?php checked( $options['disallow_file_edit'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_disallow_file_edit" class="eh-item-label"><?php esc_html_e( 'Bloquear Editor PHP en Panel', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Integridad', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Define DISALLOW_FILE_EDIT para deshabilitar el editor web de temas y plugins, impidiendo la inyección de webshells si una sesión se compromete.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Alertas de Acceso de Administrador -->
						<div class="eh-item">
							<input type="checkbox" id="eh_admin_login_alerts" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[admin_login_alerts]" value="1" <?php checked( $options['admin_login_alerts'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_admin_login_alerts" class="eh-item-label"><?php esc_html_e( 'Alertas de Acceso de Administradores', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Notificación', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Envía un aviso inmediato por correo electrónico cada vez que un usuario con rol de Administrador inicia sesión con éxito, detallando IP, fecha, hora y navegador.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Autenticación en Dos Pasos (2FA) por Correo -->
						<div class="eh-item">
							<input type="checkbox" id="eh_email_2fa_enabled" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[email_2fa_enabled]" value="1" <?php checked( $options['email_2fa_enabled'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_email_2fa_enabled" class="eh-item-label"><?php esc_html_e( 'Doble Factor de Autenticación (2FA) por Correo', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( '2FA OTP', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Exige un código numérico temporal de 6 dígitos enviado al correo del administrador tras ingresar usuario y contraseña. Bloquea el acceso tras 3 fallos.', 'essential-hardening' ); ?></p>
								<p style="margin: 6px 0 0; font-size: 11px; color: #8a4b08;">
									<?php esc_html_e( '⚠️ Rescate de emergencia: si tu servidor no puede enviar correos, puedes desactivarlo añadiendo define(\'EH_DISABLE_2FA\', true); en wp-config.php.', 'essential-hardening' ); ?>
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 3: Anti-Reconocimiento & Privacidad -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🕵️</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '3. Reconocimiento y Privacidad (Anti-Recon)', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Evita la extracción automatizada de nombres de usuario y oculta las huellas estructurales del CMS.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid eh-grid-2col">
						<!-- Anti-Enumeración de Usuarios -->
						<div class="eh-item">
							<input type="checkbox" id="eh_block_user_enumeration" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[block_user_enumeration]" value="1" <?php checked( $options['block_user_enumeration'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_block_user_enumeration" class="eh-item-label"><?php esc_html_e( 'Bloquear Enumeración de Usuarios', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Anti-Sondeo', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Redirige consultas ?author=N (HTTP 301) y oculta el endpoint /wp/v2/users en la REST API pública para que escáneres no extraigan las cuentas.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Bloqueo temporal de la IP que escanea autores -->
						<div class="eh-item">
							<input type="checkbox" id="eh_recon_ip_ban" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[recon_ip_ban]" value="1" <?php checked( $options['recon_ip_ban'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_recon_ip_ban" class="eh-item-label"><?php esc_html_e( 'Bloquear Temporalmente la IP que Escanea Autores', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Opcional', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Si una misma IP hace 5 escaneos ?author= en 10 minutos, recibe un error 403 en todo el sitio durante 1 hora y queda anotado en el registro de eventos. No afecta a usuarios con sesión iniciada ni a IPs locales o privadas. Requiere el escudo «Bloquear Enumeración de Usuarios».', 'essential-hardening' ); ?></p>
								<p style="margin: 6px 0 0; font-size: 11px; color: #8a4b08;">
									<?php esc_html_e( '⚠️ Si el sitio está detrás de Cloudflare u otro proxy, configura antes la IP real con el filtro essential_hardening_client_ip. Si no, todos los visitantes comparten la IP del proxy y un escáner podría bloquearlos a todos.', 'essential-hardening' ); ?>
								</p>
								<?php
								// Aviso activo: la petición del panel llegó por un proxy, pero la IP real no está configurada.
								$behind_proxy = ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) || ! empty( $_SERVER['HTTP_X_REAL_IP'] );
								$remote_addr  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
								if ( $behind_proxy && $this->get_client_ip() === $remote_addr ) :
									?>
									<div class="notice notice-warning inline" style="margin: 8px 0 0;">
										<p>
											<strong><?php esc_html_e( 'Se detectó un proxy o CDN delante del sitio.', 'essential-hardening' ); ?></strong>
											<?php esc_html_e( 'El plugin está viendo la IP del proxy y no la del visitante: el bloqueo por IP (esta opción, el límite de intentos de login y la recuperación de contraseña) podría afectar a todos los visitantes a la vez. Configura la IP real con el filtro essential_hardening_client_ip.', 'essential-hardening' ); ?>
										</p>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Ocultar Usuario en body_class -->
						<div class="eh-item">
							<input type="checkbox" id="eh_hide_author_body_class" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_author_body_class]" value="1" <?php checked( $options['hide_author_body_class'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_hide_author_body_class" class="eh-item-label"><?php esc_html_e( 'Anonimizar Usuarios en body_class', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'HTML Seguro', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Elimina clases como "author-admin" o "author-login" del tag <body> en páginas y entradas públicas para evitar raspado de usuarios en el código fuente.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Ocultar Versión de WP -->
						<div class="eh-item">
							<input type="checkbox" id="eh_hide_wp_version" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_wp_version]" value="1" <?php checked( $options['hide_wp_version'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_hide_wp_version" class="eh-item-label"><?php esc_html_e( 'Ocultar Versión de WordPress', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Huellas CMS', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Retira la etiqueta generator y elimina el parámetro ?ver= de estilos y scripts públicos para complicar el perfilado por versiones vulnerables.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<!-- Limpieza de Metadatos en Head -->
						<div class="eh-item">
							<input type="checkbox" id="eh_clean_head_meta" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[clean_head_meta]" value="1" <?php checked( $options['clean_head_meta'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_clean_head_meta" class="eh-item-label"><?php esc_html_e( 'Limpieza de Metadatos en <head>', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Optimización', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Remueve enlaces obsoletos del encabezado: Really Simple Discovery (RSD), Windows Live Writer (WLW), shortlinks y enlaces REST API públicos.', 'essential-hardening' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 4: Comentarios & Superficie de Spam -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">💬</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '4. Comentarios y Superficie de Spam', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Cierra la principal fuente de enlaces maliciosos y bots de spam en sitios corporativos o institucionales.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid">
						<!-- Desactivar Comentarios Totalmente -->
						<div class="eh-item">
							<input type="checkbox" id="eh_disable_comments" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_comments]" value="1" <?php checked( $options['disable_comments'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_disable_comments" class="eh-item-label"><?php esc_html_e( 'Desactivar Comentarios y Trackbacks al 100%', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-spam"><?php esc_html_e( 'Anti-Spam Total', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Cierra comentarios en front-end, bloquea llamadas directas a wp-comments-post.php con HTTP 403 Forbidden, suprime endpoints de la REST API (/wp/v2/comments), cancela feeds y limpia menús del panel.', 'essential-hardening' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 5: Mantenimiento & Notificaciones de Actualizaciones -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🔔</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '5. Notificaciones de Actualizaciones de Seguridad', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Monitorea periódicamente el estado de plugins, temas y WordPress para avisar ante versiones pendientes o vulnerables.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid">
						<div class="eh-item">
							<input type="checkbox" id="eh_update_notifications" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[update_notifications]" value="1" <?php checked( $options['update_notifications'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_update_notifications" class="eh-item-label"><?php esc_html_e( 'Alertas de Actualizaciones Pendientes y Completadas', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Monitoreo Activo', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Revisa diariamente el sitio y envía un correo consolidado si hay plugins o temas pendientes de actualizar. Además, redirige a este mismo correo los avisos de auto-actualización de WordPress y las alertas de acceso de administradores.', 'essential-hardening' ); ?></p>

								<div class="eh-sub-settings" style="align-items: flex-end;">
									<div class="eh-sub-setting" style="flex: 1; min-width: 260px;">
										<label for="eh_update_notify_email"><?php esc_html_e( 'Enviar notificaciones a:', 'essential-hardening' ); ?></label>
										<input type="email" id="eh_update_notify_email" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[update_notify_email]" value="<?php echo esc_attr( $options['update_notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" style="height: 32px; border-radius: 4px;" />
										<span style="font-size: 11px; color: #646970; margin-top: 2px;">
											<?php esc_html_e( 'Dejar en blanco para usar el correo del administrador del sitio.', 'essential-hardening' ); ?>
										</span>
									</div>
									<div>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eh_test_update_email' ), 'eh_test_email_nonce' ) ); ?>" class="button button-secondary" style="height: 32px; line-height: 30px; font-size: 12px;">
											<?php esc_html_e( '✉️ Probar Envío de Correo', 'essential-hardening' ); ?>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 6: Auditoría & Registro en Vivo de Ataques -->
				<div class="eh-card">
					<div class="eh-card-header" style="justify-content: space-between;">
						<div style="display: flex; align-items: center; gap: 12px;">
							<span class="eh-card-icon">📋</span>
							<div>
								<h2 class="eh-card-title"><?php esc_html_e( '6. Auditoría y Registro en Vivo de Ataques Neutralizados', 'essential-hardening' ); ?></h2>
								<p class="eh-card-desc"><?php esc_html_e( 'Monitorea en tiempo real los últimos incidentes bloqueados por el cortafuegos, trampas honeypot y rate-limiting.', 'essential-hardening' ); ?></p>
							</div>
						</div>
						<?php if ( ! empty( $recent_events ) ) : ?>
							<div>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eh_clear_security_log' ), 'eh_clear_log_nonce' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( '¿Seguro que deseas vaciar el registro de eventos?', 'essential-hardening' ) ); ?>');" style="font-size: 12px; color: #b32d2e;">
									<?php esc_html_e( '🗑️ Vaciar Registro', 'essential-hardening' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>

					<div class="eh-grid" style="margin-bottom: 16px;">
						<div class="eh-item">
							<input type="checkbox" id="eh_security_event_log" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[security_event_log]" value="1" <?php checked( $options['security_event_log'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_security_event_log" class="eh-item-label"><?php esc_html_e( 'Activar Registro de Eventos (Historial rotativo de 25 entradas)', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Sin impacto en BD', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Guarda de forma rotativa y ultraligera los últimos 25 intentos neutralizados (Micro-WAF, fuerza bruta, trampas bot y sondeo de usuarios) para diagnóstico visual sin sobrecargar la base de datos.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<div class="eh-item">
							<input type="checkbox" id="eh_show_ip_country" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_ip_country]" value="1" <?php checked( $options['show_ip_country'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_show_ip_country" class="eh-item-label"><?php esc_html_e( 'Mostrar País y Bandera de Cada IP', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Opcional', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Añade el país de cada IP del registro y una tabla con las 5 IP más bloqueadas. Privacidad: envía las IP de los atacantes (no las de tus visitantes) al servicio ipwho.is, una sola vez por IP y guardando el resultado 30 días, y tu navegador descarga las banderas de flagcdn.com. Desactivado por defecto porque hace consultas externas.', 'essential-hardening' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Tabla de Eventos Recientes -->
					<?php if ( empty( $recent_events ) ) : ?>
						<div style="background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 6px; padding: 20px; text-align: center; color: #50575e; font-size: 13px;">
							<p style="margin: 0; font-weight: 500;">🛡️ <?php esc_html_e( 'No hay incidentes de seguridad registrados recientemente.', 'essential-hardening' ); ?></p>
							<p style="margin: 5px 0 0; font-size: 12px; color: #646970;"><?php esc_html_e( 'Cuando el WAF o los escudos de acceso bloqueen un intento sospechoso, aparecerá detallado aquí con fecha, hora e IP.', 'essential-hardening' ); ?></p>
						</div>
					<?php else : ?>
						<?php
						// País de cada IP (solo si está activado) y las 5 IP con más bloqueos entre los eventos guardados.
						$event_ips = array();
						foreach ( $recent_events as $event ) {
							$event_ips[] = (string) ( $event['ip'] ?? '' );
						}
						$ip_countries = $this->get_ip_countries( $event_ips );
						$ip_hits      = array_count_values( array_filter( $event_ips ) );
						arsort( $ip_hits );
						$top_ips = array_slice( $ip_hits, 0, 5, true );
						?>
						<?php if ( ! empty( $options['show_ip_country'] ) ) : ?>
							<h3 style="margin: 0 0 8px; font-size: 14px;"><?php esc_html_e( 'Top 5 de IP bloqueadas (entre los eventos guardados)', 'essential-hardening' ); ?></h3>
							<div style="overflow-x: auto; border: 1px solid #dcdcde; border-radius: 6px; margin-bottom: 16px;">
								<table class="wp-list-table widefat striped" style="border: none;">
									<thead>
										<tr>
											<th style="font-weight: 600; font-size: 12px; width: 160px;"><?php esc_html_e( 'Dirección IP', 'essential-hardening' ); ?></th>
											<th style="font-weight: 600; font-size: 12px;"><?php esc_html_e( 'País', 'essential-hardening' ); ?></th>
											<th style="font-weight: 600; font-size: 12px; width: 110px;"><?php esc_html_e( 'Bloqueos', 'essential-hardening' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $top_ips as $top_ip => $top_count ) : ?>
											<tr>
												<td style="font-size: 12px; font-family: monospace;"><strong><?php echo esc_html( (string) $top_ip ); ?></strong></td>
												<td style="font-size: 12px;"><?php echo isset( $ip_countries[ $top_ip ] ) ? $this->render_country_label( $ip_countries[ $top_ip ] ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado dentro de render_country_label() ?></td>
												<td style="font-size: 12px;"><?php echo esc_html( (string) $top_count ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
						<div style="overflow-x: auto; border: 1px solid #dcdcde; border-radius: 6px;">
							<table class="wp-list-table widefat striped" style="border: none;">
								<thead>
									<tr>
										<th style="font-weight: 600; font-size: 12px; width: 140px;"><?php esc_html_e( 'Fecha / Hora', 'essential-hardening' ); ?></th>
										<th style="font-weight: 600; font-size: 12px; width: 120px;"><?php esc_html_e( 'Escudo', 'essential-hardening' ); ?></th>
										<th style="font-weight: 600; font-size: 12px; width: 130px;"><?php esc_html_e( 'Dirección IP', 'essential-hardening' ); ?></th>
										<?php if ( ! empty( $options['show_ip_country'] ) ) : ?>
											<th style="font-weight: 600; font-size: 12px; width: 170px;"><?php esc_html_e( 'País', 'essential-hardening' ); ?></th>
										<?php endif; ?>
										<th style="font-weight: 600; font-size: 12px;"><?php esc_html_e( 'Detalle del Incidente', 'essential-hardening' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_events as $event ) : ?>
										<tr>
											<td style="font-size: 12px; color: #50575e; white-space: nowrap;">
												<?php echo esc_html( date_i18n( 'd/m/Y H:i:s', $event['timestamp'] ?? time() ) ); ?>
											</td>
											<td>
												<?php
												$type_class = 'eh-tag-firewall';
												if ( 'Fuerza Bruta' === ( $event['type'] ?? '' ) || '2FA Fallido' === ( $event['type'] ?? '' ) ) {
													$type_class = 'eh-tag-access';
												} elseif ( 'Honeypot Anti-Bot' === ( $event['type'] ?? '' ) ) {
													$type_class = 'eh-tag-spam';
												} elseif ( 'Anti-Recon' === ( $event['type'] ?? '' ) ) {
													$type_class = 'eh-tag-privacy';
												}
												?>
												<span class="eh-tag <?php echo esc_attr( $type_class ); ?>">
													<?php echo esc_html( $event['type'] ?? 'Seguridad' ); ?>
												</span>
											</td>
											<td style="font-size: 12px; font-family: monospace;">
												<strong><?php echo esc_html( $event['ip'] ?? '0.0.0.0' ); ?></strong>
											</td>
											<?php if ( ! empty( $options['show_ip_country'] ) ) : ?>
												<td style="font-size: 12px; white-space: nowrap;">
													<?php echo isset( $ip_countries[ $event['ip'] ?? '' ] ) ? $this->render_country_label( $ip_countries[ $event['ip'] ] ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado dentro de render_country_label() ?>
												</td>
											<?php endif; ?>
											<td style="font-size: 12px; color: #1d2327;">
												<code><?php echo esc_html( $event['detail'] ?? '' ); ?></code>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- CATEGORÍA 7: Integridad y cambios de administradores -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🔍</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '7. Integridad del Sitio y Cambios de Administradores', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Detecta señales típicas de un sitio comprometido: archivos de WordPress alterados y administradores nuevos.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid">
						<div class="eh-item">
							<input type="checkbox" id="eh_admin_change_alerts" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[admin_change_alerts]" value="1" <?php checked( $options['admin_change_alerts'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_admin_change_alerts" class="eh-item-label"><?php esc_html_e( 'Alertas de Administradores Nuevos o Promovidos', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Notificación', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Envía un correo cuando se crea un usuario Administrador o se le da ese rol a una cuenta existente, indicando quién hizo el cambio y desde qué IP. Crear un administrador oculto es una de las primeras cosas que hace un atacante para volver a entrar.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<div class="eh-item">
							<input type="checkbox" id="eh_core_integrity_check" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[core_integrity_check]" value="1" <?php checked( $options['core_integrity_check'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_core_integrity_check" class="eh-item-label"><?php esc_html_e( 'Verificar la Integridad del Núcleo de WordPress', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-firewall"><?php esc_html_e( 'Diario', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Cada día compara los archivos de WordPress (wp-admin, wp-includes y raíz) con las sumas de control oficiales y avisa por correo si alguno fue modificado, falta o hay archivos PHP ajenos. No revisa wp-content. Descarga la lista oficial desde api.wordpress.org (una petición al día, sin enviar datos de tu sitio salvo la versión y el idioma de WordPress).', 'essential-hardening' ); ?></p>

								<?php
								$integrity = get_option( 'eh_core_integrity_last', array() );
								$integrity = is_array( $integrity ) ? $integrity : array();
								?>
								<div class="eh-sub-settings" style="align-items: center;">
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eh_verify_core' ), 'eh_verify_core_nonce' ) ); ?>" class="button button-secondary" style="height: 32px; line-height: 30px; font-size: 12px;">
										<?php esc_html_e( '🔎 Verificar ahora', 'essential-hardening' ); ?>
									</a>
									<span style="font-size: 12px; color: #50575e;">
										<?php if ( empty( $integrity['time'] ) ) : ?>
											<?php esc_html_e( 'Aún no se ha ejecutado ninguna verificación.', 'essential-hardening' ); ?>
										<?php else : ?>
											<?php
											printf(
												/* translators: %s: fecha y hora de la última verificación */
												esc_html__( 'Última verificación: %s', 'essential-hardening' ),
												esc_html( date_i18n( 'd/m/Y H:i', (int) $integrity['time'] ) )
											);
											?>
											—
											<?php if ( 'clean' === ( $integrity['status'] ?? '' ) ) : ?>
												<strong style="color: #185a25;"><?php esc_html_e( 'sin diferencias', 'essential-hardening' ); ?></strong>
											<?php elseif ( 'issues' === ( $integrity['status'] ?? '' ) ) : ?>
												<strong style="color: #b32d2e;"><?php esc_html_e( 'hay diferencias', 'essential-hardening' ); ?></strong>
											<?php else : ?>
												<strong><?php esc_html_e( 'no se pudo comprobar', 'essential-hardening' ); ?></strong>
											<?php endif; ?>
										<?php endif; ?>
									</span>
								</div>

								<?php if ( 'issues' === ( $integrity['status'] ?? '' ) ) : ?>
									<?php
									$integrity_labels = array(
										'modified'   => __( 'Modificados', 'essential-hardening' ),
										'unexpected' => __( 'PHP ajenos al núcleo', 'essential-hardening' ),
										'missing'    => __( 'Faltantes', 'essential-hardening' ),
									);
									foreach ( $integrity_labels as $integrity_key => $integrity_label ) :
										$integrity_files = isset( $integrity[ $integrity_key ] ) && is_array( $integrity[ $integrity_key ] ) ? $integrity[ $integrity_key ] : array();
										if ( empty( $integrity_files ) ) {
											continue;
										}
										?>
										<p style="margin: 10px 0 2px; font-size: 12px; font-weight: 600;">
											<?php echo esc_html( $integrity_label . ' (' . count( $integrity_files ) . ')' ); ?>
										</p>
										<ul style="margin: 0 0 0 18px; font-size: 12px; font-family: monospace; list-style: disc;">
											<?php foreach ( array_slice( $integrity_files, 0, self::MAX_INTEGRITY_FILES_LISTED ) as $integrity_file ) : ?>
												<li><?php echo esc_html( $integrity_file ); ?></li>
											<?php endforeach; ?>
											<?php if ( count( $integrity_files ) > self::MAX_INTEGRITY_FILES_LISTED ) : ?>
												<li><?php echo esc_html( sprintf( '… +%d', count( $integrity_files ) - self::MAX_INTEGRITY_FILES_LISTED ) ); ?></li>
											<?php endif; ?>
										</ul>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<!-- CATEGORÍA 8: Cuentas y contraseñas -->
				<div class="eh-card">
					<div class="eh-card-header">
						<span class="eh-card-icon">🔐</span>
						<div>
							<h2 class="eh-card-title"><?php esc_html_e( '8. Protección de Cuentas y Contraseñas', 'essential-hardening' ); ?></h2>
							<p class="eh-card-desc"><?php esc_html_e( 'Cierra las puertas alternativas al formulario de login: recuperación de contraseña, contraseñas de aplicación y contraseñas débiles.', 'essential-hardening' ); ?></p>
						</div>
					</div>

					<div class="eh-grid">
						<div class="eh-item">
							<input type="checkbox" id="eh_lostpassword_protection" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[lostpassword_protection]" value="1" <?php checked( $options['lostpassword_protection'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_lostpassword_protection" class="eh-item-label"><?php esc_html_e( 'Proteger la Recuperación de Contraseña', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-privacy"><?php esc_html_e( 'Anti-Sondeo', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Limita a 5 las solicitudes por IP y hora (evita el bombardeo de correos) y responde igual exista o no la cuenta, para que nadie pueda descubrir qué usuarios o correos están registrados.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<div class="eh-item">
							<input type="checkbox" id="eh_disable_application_passwords" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_application_passwords]" value="1" <?php checked( $options['disable_application_passwords'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_disable_application_passwords" class="eh-item-label"><?php esc_html_e( 'Desactivar Contraseñas de Aplicación', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Acceso', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Las contraseñas de aplicación permiten entrar a la REST API sin pasar por el login, así que saltan el 2FA y el bloqueo de intentos. Actívalo si no usas aplicaciones externas (app móvil de WordPress, Zapier, integraciones) que dependan de ellas.', 'essential-hardening' ); ?></p>
							</div>
						</div>

						<div class="eh-item">
							<input type="checkbox" id="eh_strong_admin_passwords" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[strong_admin_passwords]" value="1" <?php checked( $options['strong_admin_passwords'] ); ?>>
							<div class="eh-item-body">
								<div class="eh-item-title-row">
									<label for="eh_strong_admin_passwords" class="eh-item-label"><?php esc_html_e( 'Contraseñas Robustas para Administradores', 'essential-hardening' ); ?></label>
									<span class="eh-tag eh-tag-access"><?php esc_html_e( 'Filtraciones', 'essential-hardening' ); ?></span>
								</div>
								<p class="eh-item-desc"><?php esc_html_e( 'Al crear o cambiar la contraseña de un Administrador exige al menos 12 caracteres y comprueba que no aparezca en filtraciones de datos conocidas (servicio Have I Been Pwned). Solo se envían los 5 primeros caracteres de un hash: ni la contraseña ni el hash completo salen de tu servidor. Si el servicio no responde, no bloquea a nadie.', 'essential-hardening' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Barra Inferior de Guardado -->
				<div class="eh-submit-bar">
					<?php submit_button( __( 'Guardar Configuración de Seguridad', 'essential-hardening' ), 'primary large', 'submit', false ); ?>
					<span style="font-size: 12px; color: #646970;">
						<?php esc_html_e( 'Los cambios se aplican de forma inmediata en memoria.', 'essential-hardening' ); ?>
					</span>
				</div>
			</form>
		</div>
		<?php
	}
}
