<?php
/**
 * Plugin Name:       Essential Hardening & Security
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Suite ligera, modular y de rendimiento cero para hardening en WordPress. Cortafuegos Micro-WAF, mitigación de fuerza bruta, protección de uploads, bloqueo de enumeración de usuarios, desactivación total de comentarios y spam, cabeceras HTTP defensivas y anonimización de huellas del CMS.
 * Version:           1.4.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Web Security Tools
 * License:           GPL-2.0-or-later
 * Text Domain:       essential-hardening
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ESSENTIAL_HARDENING_FILE' ) ) {
	define( 'ESSENTIAL_HARDENING_FILE', __FILE__ );
}

final class Essential_Hardening {

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.4.1';

	/**
	 * Máximo número de eventos de seguridad registrados en el mini-log.
	 */
	public const MAX_EVENT_LOG_ENTRIES = 25;

	/**
	 * Clave de opciones en la base de datos.
	 */
	public const OPTION_NAME = 'essential_hardening_settings';

	/**
	 * Instancia Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Opciones actuales cargadas.
	 *
	 * @var array<string, bool>
	 */
	private $options = array();

	/**
	 * Obtener instancia singleton.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		$this->options = $this->get_settings();
		$this->init_modules();
		$this->init_update_checker();

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( ESSENTIAL_HARDENING_FILE ), array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Inicializar el verificador de actualizaciones automáticas desde GitHub (PUC).
	 */
	private function init_update_checker(): void {
		$puc_file = __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
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
				\YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS
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

	/**
	 * Opciones por defecto (todos los escudos activos al instalar).
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return array(
			// Módulos Core (v1.0.0)
			'disable_comments'        => true,
			'block_user_enumeration'  => true,
			'security_headers'        => true,
			'hide_wp_version'         => true,
			'disable_xmlrpc'          => true,
			'generic_login_errors'    => true,
			'disallow_file_edit'      => true,
			// Módulos de Protección Avanzada (v1.1.0)
			'query_firewall'          => true,
			'login_throttle'          => true,
			'protect_uploads'         => true,
			'clean_head_meta'         => true,
			'hide_author_body_class'  => true,
			// Módulos v1.2.0 (Anti-Bot y Rate Limiting Configurable)
			'login_honeypot'            => true,
			'throttle_max_attempts'     => 5,
			'throttle_lockout_duration' => 900,
			// Módulos v1.3.0 (Notificaciones de Actualizaciones)
			'update_notifications'      => true,
			'update_notify_email'       => '',
			// Módulos v1.4.0 (Alertas de Admin, Registro en Vivo y 2FA)
			'admin_login_alerts'        => true,
			'security_event_log'        => true,
			'email_2fa_enabled'         => false,
		);
	}

	/**
	 * Obtener configuración activa fusionando con valores por defecto.
	 *
	 * @return array<string, bool>
	 */
	public function get_settings(): array {
		$defaults = $this->get_default_settings();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Inicializar los módulos de seguridad según las opciones activas.
	 */
	private function init_modules(): void {
		// 1. Desactivación de comentarios y pings
		if ( ! empty( $this->options['disable_comments'] ) ) {
			$this->module_disable_comments();
		}

		// 2. Anti-enumeración de usuarios
		if ( ! empty( $this->options['block_user_enumeration'] ) ) {
			$this->module_block_user_enumeration();
		}

		// 3. Cabeceras HTTP de seguridad
		if ( ! empty( $this->options['security_headers'] ) ) {
			$this->module_security_headers();
		}

		// 4. Ocultar versión de WordPress y huellas
		if ( ! empty( $this->options['hide_wp_version'] ) ) {
			$this->module_hide_wp_version();
		}

		// 5. Desactivar XML-RPC y X-Pingback
		if ( ! empty( $this->options['disable_xmlrpc'] ) ) {
			$this->module_disable_xmlrpc();
		}

		// 6. Mensaje de login genérico
		if ( ! empty( $this->options['generic_login_errors'] ) ) {
			add_filter( 'login_errors', static function (): string {
				return __( 'Usuario o contraseña incorrectos.', 'essential-hardening' );
			} );
		}

		// 7. Desactivar editor de temas y plugins desde el panel
		if ( ! empty( $this->options['disallow_file_edit'] ) ) {
			if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
		}

		// 8. Micro-WAF / Cortafuegos de Query Strings
		if ( ! empty( $this->options['query_firewall'] ) ) {
			$this->module_query_firewall();
		}

		// 9. Mitigación de fuerza bruta / Login Throttle
		if ( ! empty( $this->options['login_throttle'] ) ) {
			$this->module_login_throttle();
		}

		// 10. Protección de directorio uploads
		if ( ! empty( $this->options['protect_uploads'] ) ) {
			$this->module_protect_uploads();
		}

		// 11. Limpieza de metadatos obsoletos en <head>
		if ( ! empty( $this->options['clean_head_meta'] ) ) {
			$this->module_clean_head_meta();
		}

		// 12. Ocultar logins de usuario en body_class
		if ( ! empty( $this->options['hide_author_body_class'] ) ) {
			$this->module_hide_author_body_class();
		}

		// 13. Honeypot Anti-Bot en pantalla de login (v1.2.0)
		if ( ! empty( $this->options['login_honeypot'] ) ) {
			$this->module_login_honeypot();
		}

		// 14. Alertas de Actualizaciones Pendientes y Completadas (v1.3.0)
		if ( ! empty( $this->options['update_notifications'] ) ) {
			$this->module_update_notifications();
		}

		// 15. Alertas de Inicio de Sesión de Administradores (v1.4.0)
		if ( ! empty( $this->options['admin_login_alerts'] ) ) {
			$this->module_admin_login_alerts();
		}

		// 16. Doble Factor de Autenticación por Correo (v1.4.0)
		if ( ! empty( $this->options['email_2fa_enabled'] ) ) {
			$this->module_email_2fa();
		}

		// Manejador para vaciar el mini-log de seguridad desde el panel
		if ( is_admin() ) {
			add_action( 'admin_post_eh_clear_security_log', array( $this, 'handle_clear_security_log' ) );
		}
	}

	/* ======================================================================
	   MÓDULO 1: Desactivación Total de Comentarios y Pings
	   ====================================================================== */

	private function module_disable_comments(): void {
		// Cerrar en front-end
		add_filter( 'comments_open', '__return_false', 9999 );
		add_filter( 'pings_open', '__return_false', 9999 );
		add_filter( 'comments_array', '__return_empty_array', 9999 );

		// Remover soporte de tipos de contenido
		add_action( 'init', function (): void {
			$post_types = get_post_types();
			foreach ( $post_types as $post_type ) {
				if ( post_type_supports( $post_type, 'comments' ) ) {
					remove_post_type_support( $post_type, 'comments' );
					remove_post_type_support( $post_type, 'trackbacks' );
				}
			}
		}, 10 );

		// Bloquear peticiones directas a wp-comments-post.php
		add_action( 'init', function (): void {
			if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'wp-comments-post.php' ) ) {
				wp_die(
					esc_html__( 'Los comentarios están deshabilitados en este portal.', 'essential-hardening' ),
					esc_html__( 'Función Deshabilitada', 'essential-hardening' ),
					array( 'response' => 403 )
				);
			}
		}, 1 );

		// Deshabilitar feeds de comentarios
		add_action( 'do_feed_rss2_comments', array( $this, 'block_comment_feed' ), 1 );
		add_action( 'do_feed_atom_comments', array( $this, 'block_comment_feed' ), 1 );

		// Deshabilitar endpoints de comentarios en la REST API
		add_filter( 'rest_endpoints', function ( array $endpoints ): array {
			if ( isset( $endpoints['/wp/v2/comments'] ) ) {
				unset( $endpoints['/wp/v2/comments'] );
			}
			if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
			}
			return $endpoints;
		} );

		// Limpieza de interfaz en wp-admin
		if ( is_admin() ) {
			add_action( 'admin_menu', function (): void {
				remove_menu_page( 'edit-comments.php' );
			} );

			add_action( 'admin_init', function (): void {
				global $pagenow;
				if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php' ), true ) ) {
					wp_safe_redirect( admin_url( 'index.php' ) );
					exit;
				}
				remove_meta_box( 'commentsdiv', 'post', 'normal' );
				remove_meta_box( 'commentsdiv', 'page', 'normal' );
				remove_meta_box( 'commentstatusdiv', 'post', 'normal' );
				remove_meta_box( 'commentstatusdiv', 'page', 'normal' );
				remove_meta_box( 'trackbacksdiv', 'post', 'normal' );
				remove_meta_box( 'trackbacksdiv', 'page', 'normal' );
			} );

			add_action( 'wp_before_admin_bar_render', function (): void {
				global $wp_admin_bar;
				if ( is_object( $wp_admin_bar ) ) {
					$wp_admin_bar->remove_node( 'comments' );
				}
			} );
		}
	}

	public function block_comment_feed(): void {
		wp_die(
			esc_html__( 'Los feeds de comentarios están deshabilitados.', 'essential-hardening' ),
			esc_html__( 'No disponible', 'essential-hardening' ),
			array( 'response' => 403 )
		);
	}

	/* ======================================================================
	   MÓDULO 2: Bloqueo de Enumeración de Usuarios (Anti-Reconnaissance)
	   ====================================================================== */

	private function module_block_user_enumeration(): void {
		// Bloquear sondeo por query string (?author=1, ?author=2)
		add_action( 'template_redirect', function (): void {
			if ( ! is_admin() && isset( $_REQUEST['author'] ) ) {
				$this->log_security_event( 'Anti-Recon', $this->get_client_ip(), sprintf( __( 'Escaneo de autor bloqueado: ?author=%s', 'essential-hardening' ), esc_html( (string) $_REQUEST['author'] ) ) );
				wp_safe_redirect( home_url( '/' ), 301 );
				exit;
			}
		} );

		// Ocultar /wp/v2/users a visitantes no autenticados
		add_filter( 'rest_endpoints', function ( array $endpoints ): array {
			if ( ! is_user_logged_in() ) {
				if ( isset( $endpoints['/wp/v2/users'] ) ) {
					unset( $endpoints['/wp/v2/users'] );
				}
				if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
					unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
				}
			}
			return $endpoints;
		} );
	}

	/* ======================================================================
	   MÓDULO 3: Cabeceras HTTP de Seguridad
	   ====================================================================== */

	private function module_security_headers(): void {
		add_action( 'send_headers', function (): void {
			if ( ! headers_sent() ) {
				header( 'X-Frame-Options: SAMEORIGIN' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'X-XSS-Protection: 1; mode=block' );
				header( 'Referrer-Policy: strict-origin-when-cross-origin' );
				header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
			}
		} );

		add_filter( 'wp_headers', function ( array $headers ): array {
			unset( $headers['X-Pingback'] );
			return $headers;
		} );
	}

	/* ======================================================================
	   MÓDULO 4: Ocultar Versión de WordPress
	   ====================================================================== */

	private function module_hide_wp_version(): void {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		add_filter( 'style_loader_src', array( $this, 'remove_ver_query_arg' ), 9999 );
		add_filter( 'script_loader_src', array( $this, 'remove_ver_query_arg' ), 9999 );
	}

	public function remove_ver_query_arg( string $src ): string {
		if ( ! is_admin() && strpos( $src, 'ver=' ) !== false ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/* ======================================================================
	   MÓDULO 5: Desactivación de XML-RPC
	   ====================================================================== */

	private function module_disable_xmlrpc(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	/* ======================================================================
	   MÓDULO 8: Micro-WAF / Cortafuegos de Peticiones y Query Strings
	   ====================================================================== */

	private function module_query_firewall(): void {
		add_action( 'init', function (): void {
			// No interferir con administradores autenticados dentro del panel
			if ( is_admin() && current_user_can( 'manage_options' ) ) {
				return;
			}

			$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
			$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

			if ( empty( $query_string ) && empty( $request_uri ) ) {
				return;
			}

			// Probar variaciones codificadas y decodificadas
			$targets = array(
				$query_string,
				rawurldecode( $query_string ),
				urldecode( $query_string ),
				$request_uri,
				rawurldecode( $request_uri ),
				urldecode( $request_uri ),
			);

			$patterns = array(
				'/union\s+(?:all\s+)?select/i',        // SQLi UNION SELECT
				'/benchmark\s*\(/i',                   // SQLi Benchmark DoS
				'/sleep\s*\(\s*\d+\s*\)/i',             // SQLi Time-based Sleep
				'/base64_decode\s*\(/i',               // RCE Obfuscation
				'/eval\s*\(/i',                        // RCE Eval
				'/gzinflate\s*\(/i',                   // RCE Inflate
				'/passthru\s*\(|shell_exec\s*\(/i',    // Command Injection
				'/(?:\.\.\/|\.\.\\\)/i',               // Path Traversal
				'/wp-config\.php/i',                   // Local File Inclusion sensible
				'/\/etc\/passwd/i',                    // Linux LFI
				'/<script[^>]*>/i',                    // Inyección XSS en query
				'/javascript\s*:/i',                   // JavaScript URI Scheme
			);

			foreach ( $targets as $target ) {
				foreach ( $patterns as $pattern ) {
					if ( preg_match( $pattern, $target ) ) {
						$this->log_security_event( 'Micro-WAF', $this->get_client_ip(), sprintf( __( 'Patrón sospechoso bloqueado: %s', 'essential-hardening' ), esc_html( substr( $target, 0, 70 ) ) ) );
						wp_die(
							esc_html__( 'Petición bloqueada por reglas de seguridad (Micro-WAF).', 'essential-hardening' ),
							esc_html__( 'Acceso Denegado (403)', 'essential-hardening' ),
							array( 'response' => 403 )
						);
					}
				}
			}
		}, 1 );
	}

	/* ======================================================================
	   MÓDULO 9: Mitigación de Fuerza Bruta (Login Throttle & Rate Limiting)
	   ====================================================================== */

	private function module_login_throttle(): void {
		// Verificar bloqueo antes de procesar credenciales
		add_filter( 'authenticate', array( $this, 'check_login_throttle' ), 5, 3 );

		// Registrar intento fallido y aplicar retardo
		add_action( 'wp_login_failed', array( $this, 'record_failed_login' ) );

		// Limpiar intentos si la autenticación fue satisfactoria
		add_action( 'wp_login', array( $this, 'clear_failed_login' ), 10, 2 );
	}

	private function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
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

		$attempts = (int) get_transient( $count_key );
		$attempts++;

		// Retardo de 2 segundos para amortiguar ataques masivos de diccionario
		sleep( 2 );

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

	/* ======================================================================
	   MÓDULO 13: Honeypot Anti-Bot en Pantalla de Login (v1.2.0)
	   ====================================================================== */

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
			// Aplicar retardo disuasivo
			sleep( 2 );

			return new WP_Error(
				'eh_bot_detected',
				esc_html__( 'Acceso bloqueado: actividad automatizada detectada.', 'essential-hardening' )
			);
		}

		return $user;
	}

	/* ======================================================================
	   MÓDULO 10: Protección del Directorio de Subidas (wp-content/uploads)
	   ====================================================================== */

	private function module_protect_uploads(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'ensure_uploads_protection' ) );
		}
	}

	public function ensure_uploads_protection(): void {
		$upload_dir = wp_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

		if ( empty( $basedir ) || ! is_dir( $basedir ) || ! is_writable( $basedir ) ) {
			return;
		}

		$htaccess_file = trailingslashit( $basedir ) . '.htaccess';
		$marker_start  = '# BEGIN Essential Hardening Uploads Protection';
		$marker_end    = '# END Essential Hardening Uploads Protection';

		$rules  = $marker_start . "\n";
		$rules .= "<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$\">\n";
		$rules .= "    Order Deny,Allow\n";
		$rules .= "    Deny from all\n";
		$rules .= "</FilesMatch>\n";
		$rules .= "<IfModule mod_authz_core.c>\n";
		$rules .= "    <FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$\">\n";
		$rules .= "        Require all denied\n";
		$rules .= "    </FilesMatch>\n";
		$rules .= "</IfModule>\n";
		$rules .= $marker_end . "\n";

		if ( file_exists( $htaccess_file ) ) {
			$content = (string) file_get_contents( $htaccess_file );
			if ( false === strpos( $content, $marker_start ) ) {
				file_put_contents( $htaccess_file, $content . "\n\n" . $rules );
			}
		} else {
			file_put_contents( $htaccess_file, $rules );
		}
	}

	/* ======================================================================
	   MÓDULO 11: Limpieza de Metadatos Obsoletos en <head>
	   ====================================================================== */

	private function module_clean_head_meta(): void {
		// Remover Really Simple Discovery (RSD)
		remove_action( 'wp_head', 'rsd_link' );

		// Remover manifiesto de Windows Live Writer
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// Remover shortlinks (?p=123)
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

		// Remover enlaces oEmbed y REST API en el head
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );

		// Remover enlaces relacionales adyacentes
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
	}

	/* ======================================================================
	   MÓDULO 12: Ocultar Nombres de Usuario en body_class
	   ====================================================================== */

	private function module_hide_author_body_class(): void {
		add_filter( 'body_class', function ( array $classes ): array {
			return array_values( array_filter( $classes, function ( string $class ): bool {
				return 0 !== strpos( $class, 'author-' );
			} ) );
		}, 9999 );
	}

	/* ======================================================================
	   MÓDULO 14: Notificaciones de Actualizaciones Pendientes y Completadas (v1.3.0)
	   ====================================================================== */

	private function module_update_notifications(): void {
		// Programar evento cron diario si no existe
		if ( ! wp_next_scheduled( 'eh_daily_update_check_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'eh_daily_update_check_cron' );
		}

		add_action( 'eh_daily_update_check_cron', array( $this, 'check_and_notify_updates' ) );

		// Enrutar correos de auto-actualización de WordPress al email configurado
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'filter_auto_update_email' ), 10, 4 );
		add_filter( 'auto_core_update_email', array( $this, 'filter_auto_core_update_email' ), 10, 4 );

		// Procesar envío de correo de prueba manual desde el panel
		if ( is_admin() ) {
			add_action( 'admin_post_eh_test_update_email', array( $this, 'handle_test_update_email' ) );
		}
	}

	public function filter_auto_update_email( array $email, string $type, array $successful_updates, array $failed_updates ): array {
		if ( ! empty( $this->options['update_notifications'] ) && ! empty( $this->options['update_notify_email'] ) ) {
			if ( is_email( $this->options['update_notify_email'] ) ) {
				$email['to'] = $this->options['update_notify_email'];
			}
		}
		return $email;
	}

	public function filter_auto_core_update_email( array $email, string $type, $core_update, $result ): array {
		if ( ! empty( $this->options['update_notifications'] ) && ! empty( $this->options['update_notify_email'] ) ) {
			if ( is_email( $this->options['update_notify_email'] ) ) {
				$email['to'] = $this->options['update_notify_email'];
			}
		}
		return $email;
	}

	public function handle_test_update_email(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'essential-hardening' ) );
		}

		check_admin_referer( 'eh_test_email_nonce' );

		$dummy_plugins = array(
			array(
				'name'            => 'Essential Hardening & Security (Prueba)',
				'current_version' => self::VERSION,
				'new_version'     => self::VERSION,
			),
		);

		$sent = $this->send_update_notification_email( $dummy_plugins, array(), false, '', true );

		$redirect_url = add_query_arg(
			array(
				'page'     => 'essential-hardening',
				'eh_email' => $sent ? 'sent' : 'failed',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function check_and_notify_updates(): void {
		if ( empty( $this->options['update_notifications'] ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'wp_get_update_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		wp_update_plugins();
		wp_update_themes();

		$pending_plugins = array();
		$update_plugins  = get_site_transient( 'update_plugins' );
		if ( ! empty( $update_plugins->response ) && is_array( $update_plugins->response ) ) {
			foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
				$plugin_full_path = WP_PLUGIN_DIR . '/' . $plugin_file;
				$current_version  = '';
				$plugin_name      = $plugin_file;

				if ( file_exists( $plugin_full_path ) ) {
					$info = get_plugin_data( $plugin_full_path, false, false );
					$plugin_name     = ! empty( $info['Name'] ) ? $info['Name'] : $plugin_file;
					$current_version = ! empty( $info['Version'] ) ? $info['Version'] : '';
				}

				$new_version = is_object( $plugin_data ) && isset( $plugin_data->new_version ) ? $plugin_data->new_version : ( is_array( $plugin_data ) && isset( $plugin_data['new_version'] ) ? $plugin_data['new_version'] : '' );

				$pending_plugins[] = array(
					'name'            => $plugin_name,
					'current_version' => $current_version,
					'new_version'     => $new_version,
				);
			}
		}

		$pending_themes = array();
		$update_themes  = get_site_transient( 'update_themes' );
		if ( ! empty( $update_themes->response ) && is_array( $update_themes->response ) ) {
			foreach ( $update_themes->response as $theme_slug => $theme_data ) {
				$theme           = wp_get_theme( $theme_slug );
				$theme_name      = $theme->exists() ? $theme->get( 'Name' ) : $theme_slug;
				$current_version = $theme->exists() ? $theme->get( 'Version' ) : '';
				$new_version     = is_object( $theme_data ) && isset( $theme_data->new_version ) ? $theme_data->new_version : ( is_array( $theme_data ) && isset( $theme_data['new_version'] ) ? $theme_data['new_version'] : '' );

				$pending_themes[] = array(
					'name'            => $theme_name,
					'current_version' => $current_version,
					'new_version'     => $new_version,
				);
			}
		}

		$pending_core = false;
		$core_version = '';
		$update_core  = get_site_transient( 'update_core' );
		if ( ! empty( $update_core->updates ) && is_array( $update_core->updates ) ) {
			foreach ( $update_core->updates as $core_update ) {
				if ( isset( $core_update->response ) && 'upgrade' === $core_update->response ) {
					$pending_core = true;
					$core_version = $core_update->current;
					break;
				}
			}
		}

		if ( empty( $pending_plugins ) && empty( $pending_themes ) && ! $pending_core ) {
			return;
		}

		$current_hash = md5( wp_json_encode( array( $pending_plugins, $pending_themes, $pending_core ) ) );
		$last_hash    = get_transient( 'eh_last_update_notice_hash' );
		if ( $current_hash === $last_hash ) {
			return;
		}

		$this->send_update_notification_email( $pending_plugins, $pending_themes, $pending_core, $core_version );
		set_transient( 'eh_last_update_notice_hash', $current_hash, 24 * HOUR_IN_SECONDS );
	}

	private function send_update_notification_email( array $plugins, array $themes, bool $core, string $core_version, bool $is_test = false ): bool {
		$recipient = ! empty( $this->options['update_notify_email'] ) ? sanitize_email( $this->options['update_notify_email'] ) : get_option( 'admin_email' );
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$site_url  = home_url();
		$admin_url = admin_url( 'update-core.php' );

		$subject = sprintf(
			/* translators: %s: Nombre del sitio */
			__( '[Seguridad] Actualizaciones pendientes en %s', 'essential-hardening' ),
			$site_name
		);

		if ( $is_test ) {
			$subject = '[Prueba] ' . $subject;
		}

		$message  = "Hola,\n\n";
		$message .= sprintf( "Essential Hardening ha detectado actualizaciones de seguridad pendientes en tu sitio web %s (%s):\n\n", $site_name, $site_url );

		if ( $core ) {
			$message .= "● NÚCLEO DE WORDPRESS:\n";
			$message .= sprintf( "  Nueva versión disponible: WordPress %s\n\n", $core_version );
		}

		if ( ! empty( $plugins ) ) {
			$message .= "● PLUGINS PENDIENTES DE ACTUALIZAR (" . count( $plugins ) . "):\n";
			foreach ( $plugins as $p ) {
				$message .= sprintf( "  - %s: versión actual %s → nueva versión %s\n", $p['name'], $p['current_version'], $p['new_version'] );
			}
			$message .= "\n";
		}

		if ( ! empty( $themes ) ) {
			$message .= "● TEMAS PENDIENTES DE ACTUALIZAR (" . count( $themes ) . "):\n";
			foreach ( $themes as $t ) {
				$message .= sprintf( "  - %s: versión actual %s → nueva versión %s\n", $t['name'], $t['current_version'], $t['new_version'] );
			}
			$message .= "\n";
		}

		$message .= "Mantener los plugins y temas al día es la principal medida para evitar intrusiones y vulnerabilidades en WordPress.\n\n";
		$message .= "Podés aplicar las actualizaciones directamente desde tu panel de administración:\n";
		$message .= $admin_url . "\n\n";
		$message .= "--\nNotificación enviada por Essential Hardening & Security.";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return $this->safe_wp_mail( $recipient, $subject, $message, $headers );
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

	/* ======================================================================
	   MÓDULO 15: Alertas de Inicio de Sesión de Administradores (v1.4.0)
	   ====================================================================== */

	private function module_admin_login_alerts(): void {
		add_action( 'wp_login', array( $this, 'notify_admin_login' ), 20, 2 );
	}

	public function notify_admin_login( string $user_login, $user ): void {
		if ( ! ( $user instanceof WP_User ) || ! $user->has_cap( 'manage_options' ) ) {
			return;
		}

		$ip         = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Desconocido';
		$site_name  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$datetime   = current_time( 'Y-m-d H:i:s' );

		$recipient = ! empty( $this->options['update_notify_email'] ) ? sanitize_email( $this->options['update_notify_email'] ) : $user->user_email;
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$subject = sprintf(
			/* translators: 1: Usuario, 2: Sitio */
			__( '[Alerta de Seguridad] Administrador conectado: %1$s en %2$s', 'essential-hardening' ),
			$user_login,
			$site_name
		);

		$message  = "Hola,\n\n";
		$message .= sprintf( "Se ha iniciado sesión con cuenta de Administrador en tu sitio %s:\n\n", $site_name );
		$message .= sprintf( "● Usuario: %s\n", $user_login );
		$message .= sprintf( "● Fecha y Hora: %s\n", $datetime );
		$message .= sprintf( "● Dirección IP: %s\n", $ip );
		$message .= sprintf( "● Dispositivo / Navegador: %s\n\n", $user_agent );
		$message .= "Si fuiste vos, podés ignorar este correo con tranquilidad.\n";
		$message .= "Si NO reconocés este acceso, cambiá tu contraseña inmediatamente desde:\n";
		$message .= wp_lostpassword_url() . "\n\n";
		$message .= "--\nNotificación enviada por Essential Hardening & Security.";

		$this->safe_wp_mail( $recipient, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/* ======================================================================
	   MÓDULO 16: Doble Factor de Autenticación (2FA) por Correo (v1.4.0)
	   ====================================================================== */

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

		// Si ya fue validado por 2FA, continuar
		if ( ! empty( $_POST['eh_2fa_verified'] ) ) {
			return $user;
		}

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

	/* ======================================================================
	   MÓDULO 17: Mini-Registro en Vivo de Eventos de Seguridad (v1.4.0)
	   ====================================================================== */

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

	/* ======================================================================
	   PANEL DE CONTROL EN WP-ADMIN (Ajustes > Hardening)
	   ====================================================================== */

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
								<p class="eh-item-desc"><?php esc_html_e( 'Inyecta X-Frame-Options (anti-clickjacking), X-Content-Type-Options: nosniff, Referrer-Policy y Permissions-Policy en cada respuesta HTTP.', 'essential-hardening' ); ?></p>
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
								<p class="eh-item-desc"><?php esc_html_e( 'Inserta un campo trampa invisible en /wp-login.php. Los humanos no lo ven ni deben resolver acertijos, pero los bots automatizados lo completan y quedan bloqueados al instante con HTTP 403.', 'essential-hardening' ); ?></p>
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
								<p class="eh-item-desc"><?php esc_html_e( 'Revisa diariamente el sitio y envía un correo consolidado si hay plugins o temas pendientes de actualizar. También confirma cuando se ejecutan auto-actualizaciones con éxito.', 'essential-hardening' ); ?></p>

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
					</div>

					<!-- Tabla de Eventos Recientes -->
					<?php if ( empty( $recent_events ) ) : ?>
						<div style="background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 6px; padding: 20px; text-align: center; color: #50575e; font-size: 13px;">
							<p style="margin: 0; font-weight: 500;">🛡️ <?php esc_html_e( 'No hay incidentes de seguridad registrados recientemente.', 'essential-hardening' ); ?></p>
							<p style="margin: 5px 0 0; font-size: 12px; color: #646970;"><?php esc_html_e( 'Cuando el WAF o los escudos de acceso bloqueen un intento sospechoso, aparecerá detallado aquí con fecha, hora e IP.', 'essential-hardening' ); ?></p>
						</div>
					<?php else : ?>
						<div style="overflow-x: auto; border: 1px solid #dcdcde; border-radius: 6px;">
							<table class="wp-list-table widefat striped" style="border: none;">
								<thead>
									<tr>
										<th style="font-weight: 600; font-size: 12px; width: 140px;"><?php esc_html_e( 'Fecha / Hora', 'essential-hardening' ); ?></th>
										<th style="font-weight: 600; font-size: 12px; width: 120px;"><?php esc_html_e( 'Escudo', 'essential-hardening' ); ?></th>
										<th style="font-weight: 600; font-size: 12px; width: 130px;"><?php esc_html_e( 'Dirección IP', 'essential-hardening' ); ?></th>
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
												} elseif ( 'Honeypot' === ( $event['type'] ?? '' ) ) {
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

// Iniciar plugin
add_action( 'plugins_loaded', array( 'Essential_Hardening', 'get_instance' ) );
