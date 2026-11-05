<?php
/**
 * Plugin Name:       WP LLMs.txt Manager
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Genera, edita y sirve archivos llms.txt y llms-full.txt de forma virtual en la raíz del sitio según la especificación llmstxt.org para optimizar la indexación por modelos de lenguaje (LLMs) y motores de IA.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WordPress AI Utilities
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-llms-txt
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_LLMS_TXT_FILE' ) ) {
	define( 'WP_LLMS_TXT_FILE', __FILE__ );
}

final class WP_LLMs_Txt_Manager {

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.0.2';

	/**
	 * Clave de opciones en wp_options.
	 */
	public const OPTION_KEY = 'wp_llms_txt_options';

	/**
	 * Clave de wp_options con los hashes de los archivos físicos creados por el plugin.
	 */
	public const MANAGED_FILES_KEY = 'wp_llms_txt_managed_files';

	/**
	 * Instancia Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Opciones actuales del plugin.
	 *
	 * @var array<string, mixed>
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
	 * Constructor.
	 */
	private function __construct() {
		$this->options = $this->get_options();

		// Verificador de actualizaciones automáticas desde GitHub (PUC)
		$this->init_update_checker();

		// Enrutamiento virtual
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_endpoint_request' ) );

		// Panel de administración
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( WP_LLMS_TXT_FILE ), array( $this, 'add_plugin_action_links' ) );
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
			WP_LLMS_TXT_FILE,
			'wp-llms-txt'
		);

		// Filtrar solo los releases con tag de este plugin (ej: wp-llms-txt-v1.0.1)
		$vcs_api = $update_checker->getVcsApi();
		if ( method_exists( $vcs_api, 'setReleaseVersionFilter' ) ) {
			$vcs_api->setReleaseVersionFilter( '/^wp-llms-txt-/i' );
		}

		// Descargar exclusivamente el archivo ZIP correspondiente a este plugin
		if ( method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets(
				'/^wp-llms-txt.*\.zip$/i',
				$vcs_api::REQUIRE_RELEASE_ASSETS
			);
		}

		// Normalizar la versión devuelta por el tag para la comparación semver de WordPress
		$normalize_version = function( $info_or_update ) {
			if ( $info_or_update && isset( $info_or_update->version ) ) {
				if ( preg_match( '/^wp-llms-txt-v?([0-9\.]+)/i', $info_or_update->version, $matches ) ) {
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

				if ( false !== strpos( $url, 'contents/wp-llms-txt.php' ) ) {
					$in_flight = true;
					$target_url = str_replace( 'contents/wp-llms-txt.php', 'contents/plugins/wp-llms-txt/wp-llms-txt.php', $url );
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
	 * Obtener opciones de la base de datos fusionadas con los valores por defecto.
	 *
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		$defaults = array(
			'llms_txt'            => '',
			'llms_full_txt'       => '',
			'sync_physical_file'  => false,
			'enable_full_txt'     => true,
		);

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Obtener el contenido de llms.txt (guardado o generado dinámicamente si está vacío).
	 */
	public function get_llms_txt(): string {
		if ( ! empty( $this->options['llms_txt'] ) ) {
			return $this->options['llms_txt'];
		}
		return $this->build_default_llms_txt();
	}

	/**
	 * Registrar reglas de reescritura para URLs amigables.
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?wp_llms_endpoint=llms', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?wp_llms_endpoint=llms-full', 'top' );
	}

	/**
	 * Registrar variables de consulta personalizadas.
	 *
	 * @param array<string> $vars
	 * @return array<string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'wp_llms_endpoint';
		return $vars;
	}

	/**
	 * Detectar y servir la solicitud de /llms.txt o /llms-full.txt.
	 */
	public function handle_endpoint_request(): void {
		$endpoint = get_query_var( 'wp_llms_endpoint' );

		// Fallback transparente por URL directa si las reglas de reescritura no se han refrescado
		if ( empty( $endpoint ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_path = untrailingslashit( (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) );
			$home_path    = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

			// Solo en la raíz del sitio (soporta WordPress instalado en subcarpeta)
			if ( $home_path . '/llms.txt' === $request_path ) {
				$endpoint = 'llms';
			} elseif ( $home_path . '/llms-full.txt' === $request_path ) {
				$endpoint = 'llms-full';
			}
		}

		if ( 'llms' !== $endpoint && 'llms-full' !== $endpoint ) {
			return;
		}

		// Si se solicita llms-full pero está deshabilitado
		if ( 'llms-full' === $endpoint && empty( $this->options['enable_full_txt'] ) ) {
			status_header( 404 );
			nocache_headers();
			exit( 'Recurso llms-full.txt no disponible.' );
		}

		$content = ( 'llms-full' === $endpoint )
			? ( ! empty( $this->options['llms_full_txt'] ) ? $this->options['llms_full_txt'] : $this->get_llms_txt() )
			: $this->get_llms_txt();

		// Enviar cabeceras HTTP limpias para texto plano
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: index, follow' );
		header( 'X-Content-Type-Options: nosniff' );

		// Cache control razonable para crawlers (1 hora)
		header( 'Cache-Control: public, max-age=3600, stale-while-revalidate=86400' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generar borrador de llms.txt con la información pública del sitio.
	 */
	public function build_default_llms_txt(): string {
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$home_url  = home_url( '/' );

		$markdown  = "# {$site_name}\n\n";
		if ( ! empty( $site_desc ) ) {
			$markdown .= "> {$site_desc}\n\n";
		}

		$markdown .= "Este archivo `llms.txt` proporciona un resumen estructurado para asistentes de IA y agentes LLM sobre el contenido y recursos disponibles en {$site_name}.\n\n";

		// Evitar consultas a permalinks antes de que WordPress haya inicializado $wp_rewrite
		global $wp_rewrite;
		if ( null === $wp_rewrite || ! did_action( 'init' ) ) {
			$markdown .= "## Páginas Principales\n\n";
			$markdown .= "- [Inicio]({$home_url})\n";
			return trim( $markdown );
		}

		// Páginas clave
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 15,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		) );

		if ( ! empty( $pages ) ) {
			$markdown .= "## Páginas Principales\n\n";
			foreach ( $pages as $page ) {
				$permalink   = get_permalink( $page->ID );
				$clean_title = trim( $page->post_title );
				$excerpt     = ! empty( $page->post_excerpt ) ? trim( wp_strip_all_tags( $page->post_excerpt ) ) : '';
				
				if ( ! empty( $excerpt ) ) {
					$markdown .= "- [{$clean_title}]({$permalink}): {$excerpt}\n";
				} else {
					$markdown .= "- [{$clean_title}]({$permalink})\n";
				}
			}
			$markdown .= "\n";
		}

		// Artículos o entradas recientes
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( ! empty( $posts ) ) {
			$markdown .= "## Entradas y Noticias\n\n";
			foreach ( $posts as $post ) {
				$permalink   = get_permalink( $post->ID );
				$clean_title = trim( $post->post_title );
				$excerpt     = ! empty( $post->post_excerpt ) ? trim( wp_strip_all_tags( $post->post_excerpt ) ) : '';

				if ( ! empty( $excerpt ) ) {
					$markdown .= "- [{$clean_title}]({$permalink}): {$excerpt}\n";
				} else {
					$markdown .= "- [{$clean_title}]({$permalink})\n";
				}
			}
			$markdown .= "\n";
		}

		$markdown .= "## Información Opcional\n\n";
		$markdown .= "- [Página de inicio]({$home_url}): Portal principal del sitio.\n";
		$markdown .= "- [Versión completa de documentación]({$home_url}llms-full.txt): Índice extendido y recursos detallados.\n";

		return trim( $markdown );
	}

	/**
	 * Registrar menú en el panel de administración.
	 */
	public function register_admin_menu(): void {
		add_options_page(
			__( 'Configuración LLMs.txt', 'wp-llms-txt' ),
			__( 'LLMs.txt', 'wp-llms-txt' ),
			'manage_options',
			'wp-llms-txt',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Procesar guardado y acciones del panel de administración.
	 */
	public function handle_admin_actions(): void {
		if ( ! isset( $_POST['wp_llms_txt_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para realizar esta acción.', 'wp-llms-txt' ) );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_llms_txt_nonce'] ) ), 'wp_llms_txt_save_action' ) ) {
			wp_die( esc_html__( 'Error de validación de seguridad (nonce inválido).', 'wp-llms-txt' ) );
		}

		// Acción: Generar borrador automático
		if ( isset( $_POST['wp_llms_txt_generate_draft'] ) ) {
			// El borrador solo se muestra en el editor; no se guarda hasta pulsar «Guardar Cambios».
			$this->options['llms_txt'] = $this->build_default_llms_txt();
			add_settings_error(
				'wp_llms_txt_messages',
				'draft_generated',
				__( 'Se generó un borrador en el editor. Todavía no se guardó: revísalo y pulsa «Guardar Cambios» para publicarlo.', 'wp-llms-txt' ),
				'updated'
			);
			return;
		}

		// Acción: Guardar configuración
		if ( isset( $_POST['wp_llms_txt_submit'] ) ) {
			$raw_llms      = isset( $_POST['llms_txt'] ) ? wp_unslash( $_POST['llms_txt'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_llms_full = isset( $_POST['llms_full_txt'] ) ? wp_unslash( $_POST['llms_full_txt'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Sanitizar preservando caracteres Markdown (saltos de línea, corchetes, asteriscos, enlaces)
			$this->options['llms_txt']           = $this->sanitize_markdown_text( (string) $raw_llms );
			$this->options['llms_full_txt']      = $this->sanitize_markdown_text( (string) $raw_llms_full );
			$this->options['sync_physical_file'] = ! empty( $_POST['sync_physical_file'] );
			$this->options['enable_full_txt']    = ! empty( $_POST['enable_full_txt'] );

			update_option( self::OPTION_KEY, $this->options );

			// Sincronizar archivo físico si la opción está activa
			if ( $this->options['sync_physical_file'] ) {
				$this->write_physical_files();
			} else {
				$this->remove_physical_files();
			}

			// Asegurar que las reglas de reescritura estén activas
			$this->register_rewrite_rules();
			flush_rewrite_rules( false );

			add_settings_error(
				'wp_llms_txt_messages',
				'settings_saved',
				__( 'Configuración de LLMs.txt guardada exitosamente.', 'wp-llms-txt' ),
				'updated'
			);
		}
	}

	/**
	 * Sanitizar contenido markdown eliminando caracteres nulos y normalizando saltos de línea.
	 */
	private function sanitize_markdown_text( string $content ): string {
		$content = str_replace( "\0", '', $content );
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		return trim( $content );
	}

	/**
	 * Escribir copias físicas en la raíz del sitio si hay permisos de escritura.
	 */
	private function write_physical_files(): void {
		$root_path = untrailingslashit( ABSPATH );

		if ( ! is_writable( $root_path ) ) {
			add_settings_error(
				'wp_llms_txt_messages',
				'filesystem_permission_warning',
				__( 'Aviso: La raíz de WordPress no tiene permisos de escritura. El archivo seguirá sirviéndose sin problemas mediante la ruta virtual de WordPress.', 'wp-llms-txt' ),
				'warning'
			);
			return;
		}

		$managed = get_option( self::MANAGED_FILES_KEY, array() );
		$managed = is_array( $managed ) ? $managed : array();

		$targets = array( 'llms.txt' => $this->options['llms_txt'] );
		if ( $this->options['enable_full_txt'] && ! empty( $this->options['llms_full_txt'] ) ) {
			$targets['llms-full.txt'] = $this->options['llms_full_txt'];
		}

		foreach ( $targets as $name => $body ) {
			$path = $root_path . '/' . $name;
			// No pisar un archivo ajeno que ya existía antes de este plugin.
			if ( file_exists( $path ) && ( empty( $managed[ $name ] ) || md5_file( $path ) !== $managed[ $name ] ) ) {
				add_settings_error(
					'wp_llms_txt_messages',
					'foreign_file_' . $name,
					sprintf(
						/* translators: %s: nombre de archivo */
						__( 'Ya existe un %s en la raíz que no fue creado por este plugin, así que no se sobrescribió.', 'wp-llms-txt' ),
						$name
					),
					'warning'
				);
				continue;
			}
			if ( false !== file_put_contents( $path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$managed[ $name ] = md5_file( $path );
			}
		}

		update_option( self::MANAGED_FILES_KEY, $managed, false );

		// Si llms-full se deshabilitó, retirar su copia física (solo si la creó el plugin).
		if ( ! isset( $targets['llms-full.txt'] ) ) {
			$this->remove_physical_files( array( 'llms-full.txt' ) );
		}
	}

	/**
	 * Eliminar archivos físicos creados por este plugin (nunca archivos ajenos).
	 *
	 * @param array<string>|null $only Limitar a estos nombres; null = todos los gestionados.
	 */
	private function remove_physical_files( ?array $only = null ): void {
		$root_path = untrailingslashit( ABSPATH );
		$managed   = get_option( self::MANAGED_FILES_KEY, array() );
		$managed   = is_array( $managed ) ? $managed : array();

		foreach ( array_keys( $managed ) as $name ) {
			if ( null !== $only && ! in_array( $name, $only, true ) ) {
				continue;
			}
			$path = $root_path . '/' . $name;
			// Solo borrar si sigue siendo exactamente lo que escribió el plugin.
			if ( file_exists( $path ) && md5_file( $path ) === $managed[ $name ] && is_writable( $path ) ) {
				unlink( $path );
			}
			unset( $managed[ $name ] );
		}

		update_option( self::MANAGED_FILES_KEY, $managed, false );
	}

	/**
	 * Enlaces rápidos en la lista de plugins.
	 *
	 * @param array<string> $links
	 * @return array<string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=wp-llms-txt' ) ),
			esc_html__( 'Ajustes', 'wp-llms-txt' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Renderizar la página de administración.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url_llms_live = home_url( '/llms.txt' );
		$url_full_live = home_url( '/llms-full.txt' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gestor de LLMs.txt', 'wp-llms-txt' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Administra y publica el archivo estandarizado llms.txt para guiar a los modelos de inteligencia artificial y crawlers sobre la estructura y contenido relevante de tu sitio.', 'wp-llms-txt' ); ?>
			</p>

			<?php settings_errors( 'wp_llms_txt_messages' ); ?>

			<div style="display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap;">
				<!-- Columna Principal -->
				<div style="flex: 1 1 650px; min-width: 320px;">
					<form method="post" action="">
						<?php wp_nonce_field( 'wp_llms_txt_save_action', 'wp_llms_txt_nonce' ); ?>

						<div class="postbox" style="padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
							<h2><?php esc_html_e( 'Contenido de llms.txt', 'wp-llms-txt' ); ?></h2>
							<p class="description">
								<?php esc_html_e( 'Sintaxis Markdown estándar (título con #, descripción breve con >, enlaces en viñetas).', 'wp-llms-txt' ); ?>
							</p>
							<textarea 
								name="llms_txt" 
								id="llms_txt" 
								rows="16" 
								style="width: 100%; font-family: Consolas, Monaco, monospace; font-size: 13px; line-height: 1.5; padding: 10px;"><?php echo esc_textarea( $this->get_llms_txt() ); ?></textarea>

							<p style="margin-top: 10px;">
								<button type="submit" name="wp_llms_txt_generate_draft" value="1" class="button button-secondary" onclick="return confirm('¿Deseas autogenerar un borrador? Esto reemplazará el contenido actual del editor con la información pública del sitio.');">
									<?php esc_html_e( '⚡ Autogenerar borrador desde el contenido del sitio', 'wp-llms-txt' ); ?>
								</button>
							</p>
						</div>

						<div class="postbox" style="padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
							<h2><?php esc_html_e( 'Contenido de llms-full.txt (Extendido)', 'wp-llms-txt' ); ?></h2>
							<p class="description">
								<?php esc_html_e( 'Opcional. Contiene el contexto completo, documentación detallada o listado exhaustivo de recursos.', 'wp-llms-txt' ); ?>
							</p>

							<p>
								<label>
									<input type="checkbox" name="enable_full_txt" value="1" <?php checked( ! empty( $this->options['enable_full_txt'] ) ); ?>>
									<strong><?php esc_html_e( 'Habilitar endpoint /llms-full.txt', 'wp-llms-txt' ); ?></strong>
								</label>
							</p>

							<textarea 
								name="llms_full_txt" 
								id="llms_full_txt" 
								rows="12" 
								style="width: 100%; font-family: Consolas, Monaco, monospace; font-size: 13px; line-height: 1.5; padding: 10px;"><?php echo esc_textarea( $this->options['llms_full_txt'] ); ?></textarea>
						</div>

						<div class="postbox" style="padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
							<h2><?php esc_html_e( 'Opciones de Entrega', 'wp-llms-txt' ); ?></h2>
							<label>
								<input type="checkbox" name="sync_physical_file" value="1" <?php checked( ! empty( $this->options['sync_physical_file'] ) ); ?>>
								<?php esc_html_e( 'Generar además archivo físico estático en la raíz (si los permisos del servidor lo permiten).', 'wp-llms-txt' ); ?>
							</label>
							<p class="description" style="margin-left: 24px;">
								<?php esc_html_e( 'Por defecto el plugin sirve /llms.txt de forma virtual inmediata sin necesidad de tocar archivos físicos.', 'wp-llms-txt' ); ?>
							</p>
						</div>

						<p class="submit">
							<input type="submit" name="wp_llms_txt_submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Guardar Cambios', 'wp-llms-txt' ); ?>">
						</p>
					</form>
				</div>

				<!-- Barra Lateral Informativa -->
				<div style="flex: 0 0 320px;">
					<div class="postbox" style="padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Verificación en Vivo', 'wp-llms-txt' ); ?></h3>
						<ul style="line-height: 1.8;">
							<li>
								📄 <strong>llms.txt:</strong><br>
								<a href="<?php echo esc_url( $url_llms_live ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $url_llms_live ); ?> ↗
								</a>
							</li>
							<li style="margin-top: 10px;">
								📚 <strong>llms-full.txt:</strong><br>
								<a href="<?php echo esc_url( $url_full_live ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $url_full_live ); ?> ↗
								</a>
							</li>
						</ul>
					</div>

					<div class="postbox" style="padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 20px;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Estándar llmstxt.org', 'wp-llms-txt' ); ?></h3>
						<p style="font-size: 13px; color: #555;">
							Un archivo <code>llms.txt</code> es un documento Markdown diseñado para que los modelos de lenguaje encuentren fácilmente información clave y enlaces canónicos.
						</p>
						<p style="font-size: 12px; color: #666;">
							Estructura recomendada:<br>
							<code># Nombre del Proyecto</code><br>
							<code>> Breve resumen explicativo</code><br>
							<code>## Sección</code><br>
							<code>- [Título](URL): Descripción opcional</code>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

// Inicialización del plugin
add_action( 'plugins_loaded', array( 'WP_LLMs_Txt_Manager', 'get_instance' ) );

// Hooks de activación y desactivación
register_activation_hook( WP_LLMS_TXT_FILE, function() {
	$manager = WP_LLMs_Txt_Manager::get_instance();
	$manager->register_rewrite_rules();
	flush_rewrite_rules();
} );

register_deactivation_hook( WP_LLMS_TXT_FILE, function() {
	flush_rewrite_rules();
} );
