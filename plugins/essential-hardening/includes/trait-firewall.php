<?php
/**
 * Essential Hardening: Módulo 8: Micro-WAF / cortafuegos de URL y query strings.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Firewall {

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

			/**
			 * Permite omitir el WAF en peticiones concretas (ej. un endpoint de un plugin que envía código en la URL).
			 *
			 * @param bool   $skip        True para no inspeccionar la petición.
			 * @param string $request_uri URI solicitada.
			 */
			if ( apply_filters( 'essential_hardening_waf_skip', false, $request_uri ) ) {
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

			/**
			 * Permite añadir o quitar patrones del Micro-WAF (expresiones regulares PCRE).
			 *
			 * @param string[] $patterns Patrones activos.
			 */
			$patterns = (array) apply_filters( 'essential_hardening_waf_patterns', $patterns );
			$targets  = array_values( array_unique( array_filter( $targets ) ) );

			foreach ( $targets as $target ) {
				foreach ( $patterns as $pattern ) {
					if ( preg_match( $pattern, $target ) ) {
						// Un solo registro por IP y minuto: evita escrituras en BD por cada petición de un escáner
						$ip_key = 'eh_waf_log_' . md5( $this->get_client_ip() );
						if ( ! get_transient( $ip_key ) ) {
							set_transient( $ip_key, 1, MINUTE_IN_SECONDS );
							$this->log_security_event( 'Micro-WAF', $this->get_client_ip(), sprintf( __( 'Patrón sospechoso bloqueado: %s', 'essential-hardening' ), substr( $target, 0, 70 ) ) );
						}
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
}
