<?php
/**
 * Essential Hardening: Módulo 1: desactivación total de comentarios y pings.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Comments {

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
}
