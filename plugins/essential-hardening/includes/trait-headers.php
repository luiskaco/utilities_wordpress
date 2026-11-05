<?php
/**
 * Essential Hardening: Módulos 3 y 18: cabeceras HTTP de seguridad, HSTS y Content-Security-Policy en modo solo-reporte.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Headers {

	private function module_security_headers(): void {
		add_action( 'send_headers', function (): void {
			if ( ! headers_sent() ) {
				header( 'X-Frame-Options: SAMEORIGIN' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'X-XSS-Protection: 0' ); // El filtro XSS de navegadores antiguos está obsoleto y puede introducir fallos; se desactiva a propósito.
				header( 'Referrer-Policy: strict-origin-when-cross-origin' );
				header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
			}
		} );

		add_filter( 'wp_headers', function ( array $headers ): array {
			unset( $headers['X-Pingback'] );
			return $headers;
		} );
	}

	/**
	 * Valores de max-age permitidos para HSTS (segundos).
	 *
	 * @return int[]
	 */
	public static function get_hsts_max_age_choices(): array {
		return array( 300, 86400, 2592000, 15552000, 31536000 );
	}

	private function module_transport_headers(): void {
		add_action( 'send_headers', function (): void {
			if ( headers_sent() ) {
				return;
			}
			foreach ( $this->get_transport_headers() as $header ) {
				header( $header );
			}
		} );
	}

	/**
	 * Construye las cabeceras HSTS y CSP según la configuración.
	 *
	 * - HSTS solo se envía por HTTPS: por HTTP el navegador lo ignora y solo daría una falsa seguridad.
	 * - La CSP va siempre en modo Report-Only: registra en la consola del navegador lo que bloquearía,
	 *   pero no bloquea nada. Así se ve el impacto antes de endurecerla.
	 *
	 * @return string[] Líneas de cabecera completas.
	 */
	public function get_transport_headers(): array {
		$headers = array();

		if ( ! empty( $this->options['hsts_enabled'] ) && is_ssl() ) {
			$max_age = absint( $this->options['hsts_max_age'] ?? 2592000 );
			if ( ! in_array( $max_age, self::get_hsts_max_age_choices(), true ) ) {
				$max_age = 2592000;
			}

			$value = 'max-age=' . $max_age;

			/**
			 * Añadir includeSubDomains a HSTS. Solo si TODOS los subdominios tienen HTTPS válido.
			 *
			 * @param bool $include_subdomains Por defecto false.
			 */
			if ( apply_filters( 'essential_hardening_hsts_include_subdomains', false ) ) {
				$value .= '; includeSubDomains';
			}

			$headers[] = 'Strict-Transport-Security: ' . $value;
		}

		if ( ! empty( $this->options['csp_report_only'] ) ) {
			$default_policy = "default-src 'self'; "
				. "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
				. "style-src 'self' 'unsafe-inline'; "
				. "img-src 'self' data: https:; "
				. "font-src 'self' data: https:; "
				. "connect-src 'self'; "
				. "frame-ancestors 'self'; "
				. "base-uri 'self'; "
				. "form-action 'self'";

			/**
			 * Política CSP en modo solo-reporte. Ajustarla a los dominios externos reales del sitio.
			 *
			 * @param string $policy Política por defecto, compatible con un WordPress estándar.
			 */
			$policy = (string) apply_filters( 'essential_hardening_csp_policy', $default_policy );

			// Evitar saltos de línea que permitirían inyectar cabeceras adicionales
			$policy = trim( preg_replace( '/[\r\n]+/', ' ', $policy ) );

			if ( '' !== $policy ) {
				$headers[] = 'Content-Security-Policy-Report-Only: ' . $policy;
			}
		}

		return $headers;
	}
}
