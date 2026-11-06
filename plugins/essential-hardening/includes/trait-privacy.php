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
		// IP baneada por escanear autores: 403 en todo el sitio mientras dure el bloqueo
		add_action( 'init', function (): void {
			if ( empty( $this->options['recon_ip_ban'] ) || is_user_logged_in() ) {
				return;
			}
			$ip = $this->get_client_ip();
			if ( $this->is_ip_bannable( $ip ) && get_transient( 'eh_recon_ban_' . md5( $ip ) ) ) {
				status_header( 403 );
				header( 'Retry-After: ' . HOUR_IN_SECONDS );
				nocache_headers();
				exit( esc_html__( 'Acceso bloqueado temporalmente por actividad sospechosa.', 'essential-hardening' ) );
			}
		}, 0 );

		// Bloquear sondeo por query string (?author=1, ?author=2)
		add_action( 'template_redirect', function (): void {
			if ( ! is_admin() && isset( $_REQUEST['author'] ) ) {
				$ip = $this->get_client_ip();
				$this->log_security_event( 'Anti-Recon', $ip, sprintf( __( 'Escaneo de autor bloqueado: ?author=%s', 'essential-hardening' ), esc_html( (string) $_REQUEST['author'] ) ) );
				$this->register_author_scan( $ip );
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

	/**
	 * Una IP solo se puede banear si es pública: nunca la del propio servidor, una red local ni una
	 * dirección desconocida (0.0.0.0), porque detrás de un proxy sin configurar bloquearía a todos.
	 */
	private function is_ip_bannable( string $ip ): bool {
		return '0.0.0.0' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Cuenta los escaneos de autor de una IP y la banea si pasa el umbral.
	 * Los usuarios con sesión iniciada no cuentan.
	 */
	private function register_author_scan( string $ip ): void {
		if ( empty( $this->options['recon_ip_ban'] ) || is_user_logged_in() || ! $this->is_ip_bannable( $ip ) ) {
			return;
		}

		/**
		 * Escaneos de autor dentro de la ventana (10 min) que activan el bloqueo de la IP. 0 desactiva el bloqueo.
		 *
		 * @param int $threshold Umbral por defecto: 5.
		 */
		$threshold = absint( apply_filters( 'essential_hardening_recon_ban_threshold', 5 ) );
		if ( 0 === $threshold ) {
			return;
		}

		/**
		 * Segundos que dura el bloqueo de la IP.
		 *
		 * @param int $seconds Duración por defecto: 1 hora.
		 */
		$duration  = max( MINUTE_IN_SECONDS, absint( apply_filters( 'essential_hardening_recon_ban_duration', HOUR_IN_SECONDS ) ) );
		$count_key = 'eh_recon_count_' . md5( $ip );
		$count     = (int) get_transient( $count_key ) + 1;

		if ( $count < $threshold ) {
			set_transient( $count_key, $count, 10 * MINUTE_IN_SECONDS );
			return;
		}

		delete_transient( $count_key );
		set_transient( 'eh_recon_ban_' . md5( $ip ), 1, $duration );
		$this->log_security_event(
			'Anti-Recon',
			$ip,
			sprintf(
				/* translators: 1: número de escaneos, 2: duración legible */
				__( 'IP bloqueada por %2$s tras %1$d escaneos de autor seguidos', 'essential-hardening' ),
				$count,
				$this->get_human_lockout_duration( $duration )
			)
		);
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
