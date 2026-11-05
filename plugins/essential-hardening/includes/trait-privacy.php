<?php
/**
 * Essential Hardening: Módulos 2, 4, 5, 11 y 12: anti-enumeración de usuarios, ocultar versión y huellas de WordPress, XML-RPC, limpieza de <head> y body_class.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Privacy {

	private function module_block_user_enumeration(): void {
		// Bloquear sondeo por query string (?author=1, ?author=2)
		add_action( 'template_redirect', function (): void {
			if ( ! is_admin() && isset( $_REQUEST['author'] ) ) {
				$this->log_security_event( 'Anti-Recon', $this->get_client_ip(), sprintf( __( 'Escaneo de autor bloqueado: ?author=%s', 'essential-hardening' ), esc_html( (string) $_REQUEST['author'] ) ) );
				wp_safe_redirect( home_url( '/' ), 301 );
				exit;
			}
		} );

		// Quitar el mapa del sitio de usuarios (/wp-sitemap-users-1.xml), que lista a los autores
		add_filter( 'wp_sitemaps_add_provider', static function ( $provider, $name ) {
			return 'users' === $name ? false : $provider;
		}, 10, 2 );

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

	private function module_disable_xmlrpc(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

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

	private function module_hide_author_body_class(): void {
		add_filter( 'body_class', function ( array $classes ): array {
			return array_values( array_filter( $classes, function ( string $class ): bool {
				return 0 !== strpos( $class, 'author-' );
			} ) );
		}, 9999 );
	}
}
