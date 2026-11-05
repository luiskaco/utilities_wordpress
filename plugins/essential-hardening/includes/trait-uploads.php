<?php
/**
 * Essential Hardening: Módulo 10: protección del directorio de subidas (wp-content/uploads).
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Uploads {

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

		// Apache 2.4 usa Require; Order/Deny solo se emite si existe mod_access_compat (evita error 500).
		$match  = '"(?i)\\.(php|phtml|php3|php4|php5|php7|phps|phar|inc)$"';
		$rules  = $marker_start . "\n";
		$rules .= "<IfModule mod_authz_core.c>\n";
		$rules .= "    <FilesMatch {$match}>\n";
		$rules .= "        Require all denied\n";
		$rules .= "    </FilesMatch>\n";
		$rules .= "</IfModule>\n";
		$rules .= "<IfModule !mod_authz_core.c>\n";
		$rules .= "    <IfModule mod_access_compat.c>\n";
		$rules .= "        <FilesMatch {$match}>\n";
		$rules .= "            Order Deny,Allow\n";
		$rules .= "            Deny from all\n";
		$rules .= "        </FilesMatch>\n";
		$rules .= "    </IfModule>\n";
		$rules .= "</IfModule>\n";
		$rules .= $marker_end . "\n";

		if ( file_exists( $htaccess_file ) ) {
			$content = (string) file_get_contents( $htaccess_file );
			if ( false === strpos( $content, $marker_start ) ) {
				file_put_contents( $htaccess_file, $content . "\n\n" . $rules );
			} elseif ( false === strpos( $content, $rules ) ) {
				// Migrar el bloque de versiones anteriores a la 1.4.2 (daba error 500 sin mod_access_compat).
				$pattern = '/' . preg_quote( $marker_start, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '\R?/s';
				$updated = preg_replace_callback( $pattern, static function () use ( $rules ): string {
					return $rules;
				}, $content, 1 );
				if ( null !== $updated ) {
					file_put_contents( $htaccess_file, $updated );
				}
			}
		} else {
			file_put_contents( $htaccess_file, $rules );
		}
	}
}
