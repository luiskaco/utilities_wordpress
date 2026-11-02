<?php
/**
 * Plugin Name:       Essential Hardening & Security
 * Plugin URI:        https://github.com/
 * Description:       Suite ligera, modular y esencial de hardening para WordPress. Desactiva comentarios y spam, bloquea enumeración de usuarios, inyecta cabeceras HTTP de seguridad, oculta huellas del CMS y protege el panel administrativo sin dependencias ni sobrecarga.
 * Version:           1.0.0
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
	public const VERSION = '1.0.0';

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
	 * Opciones por defecto (todas las protecciones activas al instalar).
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return array(
			'disable_comments'        => true,
			'block_user_enumeration'  => true,
			'security_headers'        => true,
			'hide_wp_version'         => true,
			'disable_xmlrpc'          => true,
			'generic_login_errors'    => true,
			'disallow_file_edit'      => true,
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
			<h1><?php esc_html_e( 'Essential Hardening & Security', 'essential-hardening' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Módulos esenciales de protección y hardening para WordPress. Cada módulo puede activarse o desactivarse según las necesidades particulares de cada proyecto.', 'essential-hardening' ); ?>
			</p>

			<form method="post" action="options.php" style="margin-top: 25px; max-width: 820px;">
				<?php
				settings_fields( 'essential_hardening_group' );
				?>

				<div style="background: #ffffff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<h2 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:12px;">
						<?php esc_html_e( 'Escudos Activos', 'essential-hardening' ); ?>
					</h2>

					<table class="form-table" role="presentation">
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

						<tr>
							<th scope="row"><?php esc_html_e( 'Anti-Enumeración', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[block_user_enumeration]" value="1" <?php checked( $options['block_user_enumeration'] ); ?>>
									<strong><?php esc_html_e( 'Bloquear escaneos de nombres de usuario', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Redirige consultas ?author=N y oculta el endpoint REST API /wp/v2/users a visitantes anónimos, evitando ataques dirigidos de fuerza bruta.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

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

						<tr>
							<th scope="row"><?php esc_html_e( 'Huellas de WordPress', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_wp_version]" value="1" <?php checked( $options['hide_wp_version'] ); ?>>
									<strong><?php esc_html_e( 'Ocultar versión de WordPress en head y recursos', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Elimina la etiqueta generator y suprime el parámetro ?ver= de estilos y scripts para dificultar el perfilado automatizado por bots.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'XML-RPC', 'essential-hardening' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disable_xmlrpc]" value="1" <?php checked( $options['disable_xmlrpc'] ); ?>>
									<strong><?php esc_html_e( 'Deshabilitar interfaz XML-RPC', 'essential-hardening' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Cierra un vector histórico habitual de ataques de fuerza bruta y DDoS.', 'essential-hardening' ); ?>
								</p>
							</td>
						</tr>

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
