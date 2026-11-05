<?php
/**
 * Essential Hardening: Módulo 19: verificación de la integridad de los archivos del núcleo de WordPress.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Integrity {

	private function module_core_integrity(): void {
		if ( ! wp_next_scheduled( 'eh_daily_core_integrity_cron' ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'eh_daily_core_integrity_cron' );
		}

		add_action( 'eh_daily_core_integrity_cron', array( $this, 'run_core_integrity_check' ) );

		if ( is_admin() ) {
			add_action( 'admin_post_eh_verify_core', array( $this, 'handle_verify_core_now' ) );
		}
	}

	/**
	 * Compara los archivos del núcleo con las sumas de control oficiales.
	 *
	 * - modified:   existe pero su contenido no coincide con el oficial.
	 * - missing:    figura en la lista oficial pero no existe.
	 * - unexpected: archivo PHP que no pertenece al núcleo (raíz, wp-admin o wp-includes).
	 *
	 * wp-content no se revisa (temas, plugins y subidas son propios de cada sitio).
	 *
	 * @param array<string, string|false> $checksums Mapa ruta relativa => md5 oficial. Un valor false indica "existe pero no se compara".
	 * @param string                $root      Directorio raíz de WordPress.
	 * @return array{modified: string[], missing: string[], unexpected: string[]}
	 */
	public function compare_core_files( array $checksums, string $root ): array {
		$root   = rtrim( str_replace( '\\', '/', $root ), '/' );
		$result = array(
			'modified'   => array(),
			'missing'    => array(),
			'unexpected' => array(),
		);

		/**
		 * Rutas relativas que se ignoran en la verificación. Por defecto, archivos que muchos hostings
		 * o guías de hardening eliminan a propósito.
		 *
		 * @param string[] $ignore Lista de rutas relativas al directorio raíz.
		 */
		$ignore = (array) apply_filters(
			'essential_hardening_core_integrity_ignore',
			array( 'readme.html', 'license.txt', 'wp-config-sample.php', 'wp-admin/install.php' )
		);

		foreach ( $checksums as $file => $expected ) {
			if ( 0 === strpos( $file, 'wp-content/' ) || in_array( $file, $ignore, true ) || ! is_string( $expected ) ) {
				continue;
			}

			$path = $root . '/' . $file;
			if ( ! is_file( $path ) ) {
				$result['missing'][] = $file;
				continue;
			}

			if ( md5_file( $path ) !== $expected ) {
				$result['modified'][] = $file;
			}
		}

		// Archivos ejecutables que no son del núcleo: típico de puertas traseras (webshells)
		$is_executable = static function ( string $name ): bool {
			return (bool) preg_match( '/\.(php|phtml|phar|php[3-8])$/i', $name );
		};

		foreach ( glob( $root . '/*' ) ?: array() as $path ) {
			$name = basename( $path );
			if ( is_file( $path ) && $is_executable( $name ) && ! isset( $checksums[ $name ] ) && 'wp-config.php' !== $name ) {
				$result['unexpected'][] = $name;
			}
		}

		foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
			if ( ! is_dir( $root . '/' . $dir ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $fileinfo ) {
				if ( ! $fileinfo->isFile() || ! $is_executable( $fileinfo->getFilename() ) ) {
					continue;
				}
				$relative = ltrim( substr( str_replace( '\\', '/', $fileinfo->getPathname() ), strlen( $root ) ), '/' );
				if ( ! isset( $checksums[ $relative ] ) ) {
					$result['unexpected'][] = $relative;
				}
			}
		}

		sort( $result['modified'] );
		sort( $result['missing'] );
		sort( $result['unexpected'] );

		return $result;
	}

	/**
	 * Descarga las sumas de control oficiales para la versión y el idioma instalados.
	 * Hace UNA petición a api.wordpress.org (la misma que usa WordPress y WP-CLI).
	 *
	 * @return array<string, string>|false
	 */
	private function fetch_core_checksums() {
		/**
		 * Permite proveer las sumas de control (pruebas o entornos sin salida a internet).
		 *
		 * @param array|false|null $checksums Null para usar la API oficial; false para simular que no está disponible.
		 */
		$custom = apply_filters( 'essential_hardening_core_checksums', null );
		if ( is_array( $custom ) || false === $custom ) {
			return $custom;
		}

		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		global $wp_version;
		$checksums = get_core_checksums( $wp_version, get_locale() );
		if ( ! is_array( $checksums ) && 'en_US' !== get_locale() ) {
			// Hay idiomas sin lista propia (ej. es_PE): se usa la de inglés. version.php cambia según el
			// paquete de idioma instalado: se marca con false para no compararlo (sin que cuente como archivo ajeno).
			$checksums = get_core_checksums( $wp_version, 'en_US' );
			if ( is_array( $checksums ) ) {
				$checksums['wp-includes/version.php'] = false;
			}
		}

		return is_array( $checksums ) ? $checksums : false;
	}

	/**
	 * Ejecuta la verificación, guarda el resultado y avisa por correo si cambió algo.
	 *
	 * @param bool $notify False para no enviar correo (verificación manual desde el panel).
	 * @return array<string, mixed> Resultado guardado.
	 */
	public function run_core_integrity_check( bool $notify = true ): array {
		// Leer unos 3.500 archivos puede tardar en discos lentos: se amplía el límite igual que hace WordPress en sus tareas largas.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		$checksums = $this->fetch_core_checksums();

		if ( false === $checksums ) {
			$report = array(
				'time'   => time(),
				'status' => 'unavailable',
			);
			update_option( 'eh_core_integrity_last', $report, false );
			return $report;
		}

		/**
		 * Directorio raíz de WordPress a verificar (por defecto ABSPATH). Útil en instalaciones con el núcleo en otra carpeta.
		 *
		 * @param string $root Ruta sin barra final.
		 */
		$root  = (string) apply_filters( 'essential_hardening_core_root', untrailingslashit( ABSPATH ) );
		$found = $this->compare_core_files( $checksums, $root );
		$total = count( $found['modified'] ) + count( $found['missing'] ) + count( $found['unexpected'] );

		$report = array(
			'time'       => time(),
			'status'     => 0 === $total ? 'clean' : 'issues',
			'modified'   => $found['modified'],
			'missing'    => $found['missing'],
			'unexpected' => $found['unexpected'],
		);
		update_option( 'eh_core_integrity_last', $report, false );

		if ( 0 === $total ) {
			delete_transient( 'eh_last_core_alert_hash' );
			return $report;
		}

		$this->log_security_event(
			'Integridad',
			$this->get_client_ip(),
			sprintf(
				/* translators: 1: modificados, 2: faltantes, 3: inesperados */
				__( 'Núcleo alterado: %1$d modificados, %2$d faltantes, %3$d inesperados', 'essential-hardening' ),
				count( $found['modified'] ),
				count( $found['missing'] ),
				count( $found['unexpected'] )
			)
		);

		// Mismo hallazgo = un solo correo (se repite como mucho una vez por semana)
		$hash = md5( wp_json_encode( $found ) );
		if ( $notify && get_transient( 'eh_last_core_alert_hash' ) !== $hash ) {
			$this->send_core_integrity_email( $found );
			set_transient( 'eh_last_core_alert_hash', $hash, WEEK_IN_SECONDS );
		}

		return $report;
	}

	/**
	 * @param array{modified: string[], missing: string[], unexpected: string[]} $found Hallazgos.
	 */
	private function send_core_integrity_email( array $found ): void {
		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: nombre del sitio */
			__( '[Alerta de Seguridad] Archivos del núcleo de WordPress alterados en %s', 'essential-hardening' ),
			$site_name
		);

		$sections = array(
			'modified'   => __( 'MODIFICADOS (su contenido no coincide con el oficial)', 'essential-hardening' ),
			'unexpected' => __( 'INESPERADOS (archivos PHP que no pertenecen a WordPress)', 'essential-hardening' ),
			'missing'    => __( 'FALTANTES', 'essential-hardening' ),
		);

		$message  = "Hola,\n\n";
		$message .= sprintf( "Essential Hardening comparó los archivos de WordPress de %s con las sumas de control oficiales y encontró diferencias:\n\n", $site_name );

		foreach ( $sections as $key => $label ) {
			if ( empty( $found[ $key ] ) ) {
				continue;
			}
			$message .= sprintf( "● %s: %d\n", $label, count( $found[ $key ] ) );
			foreach ( array_slice( $found[ $key ], 0, self::MAX_INTEGRITY_FILES_LISTED ) as $file ) {
				$message .= '  - ' . $file . "\n";
			}
			if ( count( $found[ $key ] ) > self::MAX_INTEGRITY_FILES_LISTED ) {
				$message .= sprintf( "  … y %d más\n", count( $found[ $key ] ) - self::MAX_INTEGRITY_FILES_LISTED );
			}
			$message .= "\n";
		}

		$message .= "Qué hacer:\n";
		$message .= "- Si acabas de actualizar WordPress a mano o un plugin tocó el núcleo a propósito, puede ser normal.\n";
		$message .= "- Si NO esperabas el cambio, el sitio puede estar comprometido: restaura los archivos del núcleo desde una copia limpia de WordPress, cambia las contraseñas y revisa los usuarios administradores.\n\n";
		$message .= admin_url( 'options-general.php?page=essential-hardening' ) . "\n\n";
		$message .= "--\nNotificación enviada por Essential Hardening & Security.";

		$this->safe_wp_mail( $this->get_alert_recipient(), $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	public function handle_verify_core_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'essential-hardening' ) );
		}

		check_admin_referer( 'eh_verify_core_nonce' );

		$report = $this->run_core_integrity_check( false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'essential-hardening',
					'eh_core' => $report['status'],
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
