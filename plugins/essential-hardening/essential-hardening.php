<?php
/**
 * Plugin Name:       Essential Hardening & Security
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Suite ligera, modular y de rendimiento cero para hardening en WordPress. Cortafuegos Micro-WAF, mitigación de fuerza bruta, protección de uploads, bloqueo de enumeración de usuarios, desactivación total de comentarios y spam, cabeceras HTTP defensivas y anonimización de huellas del CMS.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Web Security Tools
 * License:           GPL-2.0-or-later
 * Text Domain:       essential-hardening
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

final class Essential_Hardening {

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.1.0';

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

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );
		}
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
		$ip           = $this->get_client_ip();
		$lockout_key  = 'eh_lockout_' . md5( $ip );

		if ( get_transient( $lockout_key ) ) {
			return new WP_Error(
				'eh_ip_locked',
				esc_html__( 'Demasiados intentos fallidos. Su dirección IP ha sido bloqueada temporalmente por 15 minutos.', 'essential-hardening' )
			);
		}

		return $user;
	}

	public function record_failed_login( string $username ): void {
		$ip          = $this->get_client_ip();
		$count_key   = 'eh_fail_count_' . md5( $ip );
		$lockout_key = 'eh_lockout_' . md5( $ip );

		$attempts = (int) get_transient( $count_key );
		$attempts++;

		// Retardo de 2 segundos para amortiguar ataques masivos de diccionario
		sleep( 2 );

		if ( $attempts >= 5 ) {
			// Bloqueo temporal por 15 minutos
			set_transient( $lockout_key, 1, 15 * MINUTE_IN_SECONDS );
			delete_transient( $count_key );
		} else {
			// Ventana móvil de conteo de 15 minutos
			set_transient( $count_key, $attempts, 15 * MINUTE_IN_SECONDS );
		}
	}

	public function clear_failed_login( string $username, $user ): void {
		$ip = $this->get_client_ip();
		delete_transient( 'eh_fail_count_' . md5( $ip ) );
		delete_transient( 'eh_lockout_' . md5( $ip ) );
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

		foreach ( $defaults as $key => $default_val ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

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

		$options      = $this->get_settings();
		$active_count = count( array_filter( $options ) );
		$total_count  = count( $this->get_default_settings() );
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
								<p class="eh-item-desc"><?php esc_html_e( 'Añade 2s de retardo tras cada fallo y bloquea la IP por 15 minutos al acumular 5 fallos consecutivos usando transients en memoria.', 'essential-hardening' ); ?></p>
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
