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

		$options = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Essential Hardening & Security', 'essential-hardening' ); ?> <span style="font-size: 13px; font-weight: normal; color: #646970;">v<?php echo esc_html( self::VERSION ); ?></span></h1>
			<p class="description">
				<?php esc_html_e( 'Suite ligera y modular de protección para WordPress. Cada módulo puede activarse o desactivarse según las necesidades de cada proyecto sin sobrecarga de base de datos ni dependencias externas.', 'essential-hardening' ); ?>
			</p>

			<form method="post" action="options.php" style="margin-top: 25px; max-width: 860px;">
				<?php
				settings_fields( 'essential_hardening_group' );
				?>

				<div style="background: #ffffff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<h2 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:12px;">
						<?php esc_html_e( 'Escudos Activos', 'essential-hardening' ); ?>
					</h2>

					<table class="form-table" role="presentation">
						<!-- 1. Comentarios -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Comentarios & Spam', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_comments]" value="1" <?php checked( $options['disable_comments'] ); ?>>
									<strong><?php esc_html_e( 'Desactivar comentarios y trackbacks al 100%', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Cierra comentarios en front-end, elimina el endpoint wp-comments-post.php (HTTP 403), retira feeds de comentarios y oculta menús en el panel.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 2. Micro-WAF -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Micro-WAF', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[query_firewall]" value="1" <?php checked( $options['query_firewall'] ); ?>>
									<strong><?php esc_html_e( 'Cortafuegos de Query Strings y peticiones maliciosas', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Inspecciona cadenas de consulta URL y bloquea de inmediato (HTTP 403) patrones de inyección SQL, comandos remotos (eval, base64_decode), LFI (/etc/passwd, wp-config.php) y path traversal.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 3. Fuerza Bruta / Login Throttle -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Fuerza Bruta (Login)', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_throttle]" value="1" <?php checked( $options['login_throttle'] ); ?>>
									<strong><?php esc_html_e( 'Mitigación de fuerza bruta y rate-limiting en wp-login', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Añade retardo de 2s tras un fallo y bloquea la IP infractora por 15 minutos tras 5 intentos fallidos consecutivos usando transients nativos en memoria.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 4. Protección de Uploads -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Protección de Uploads', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protect_uploads]" value="1" <?php checked( $options['protect_uploads'] ); ?>>
									<strong><?php esc_html_e( 'Bloquear ejecución de PHP en wp-content/uploads', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Inserta reglas defensivas en wp-content/uploads/.htaccess para impedir que un atacante ejecute scripts PHP subidos mediante vulnerabilidades de otros plugins.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 5. Anti-Enumeración -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Anti-Enumeración', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[block_user_enumeration]" value="1" <?php checked( $options['block_user_enumeration'] ); ?>>
									<strong><?php esc_html_e( 'Bloquear escaneos de nombres de usuario (?author=N y REST API)', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Redirige consultas ?author=N y oculta el endpoint REST API /wp/v2/users a visitantes anónimos, evitando ataques dirigidos de fuerza bruta.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 6. Ocultar Autor en body_class -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Anonimización de Body', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_author_body_class]" value="1" <?php checked( $options['hide_author_body_class'] ); ?>>
									<strong><?php esc_html_e( 'Ocultar nombres de usuario en las clases de body (body_class)', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Filtra y elimina clases como "author-admin" o "author-username" del HTML de las páginas para que bots de scraping no recolecten usuarios del código fuente.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 7. Cabeceras HTTP -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Cabeceras HTTP', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[security_headers]" value="1" <?php checked( $options['security_headers'] ); ?>>
									<strong><?php esc_html_e( 'Inyectar cabeceras HTTP de seguridad', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Añade X-Frame-Options (anti-clickjacking), X-Content-Type-Options: nosniff, Referrer-Policy y Permissions-Policy.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 8. Huellas de WordPress -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Huellas de WordPress', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_wp_version]" value="1" <?php checked( $options['hide_wp_version'] ); ?>>
									<strong><?php esc_html_e( 'Ocultar versión de WordPress en head y recursos (?ver=)', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Elimina la etiqueta generator y suprime el parámetro ?ver= de estilos y scripts para dificultar el perfilado automatizado por bots.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 9. Limpieza de Head -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Limpieza de Head', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[clean_head_meta]" value="1" <?php checked( $options['clean_head_meta'] ); ?>>
									<strong><?php esc_html_e( 'Eliminar metadatos y enlaces obsoletos en <head>', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Remueve enlaces a Really Simple Discovery (RSD), Windows Live Writer (wlwmanifest), shortlinks redundantes y enlaces REST API públicos en cabecera.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 10. XML-RPC -->
						<tr>
							<th scope="row"><?php esc_html_e( 'XML-RPC', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_xmlrpc]" value="1" <?php checked( $options['disable_xmlrpc'] ); ?>>
									<strong><?php esc_html_e( 'Deshabilitar interfaz XML-RPC', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Cierra un vector histórico habitual de ataques de fuerza bruta y amplificación DDoS.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 11. Login Seguro -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Login Seguro', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[generic_login_errors]" value="1" <?php checked( $options['generic_login_errors'] ); ?>>
									<strong><?php esc_html_e( 'Mensajes de error de inicio de sesión genéricos', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'No revela si lo que falló fue el nombre de usuario o la contraseña.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<!-- 12. Integridad de Código -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Integridad de Código', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disallow_file_edit]" value="1" <?php checked( $options['disallow_file_edit'] ); ?>>
									<strong><?php esc_html_e( 'Bloquear edición de archivos PHP en el panel', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Define DISALLOW_FILE_EDIT para evitar inyecciones de código si una sesión administrativa llega a verse comprometida.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Guardar Cambios de Seguridad', 'essential-hardening' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}

// Iniciar plugin
add_action( 'plugins_loaded', array( 'Essential_Hardening', 'get_instance' ) );
