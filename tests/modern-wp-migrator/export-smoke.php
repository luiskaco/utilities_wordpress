<?php
/**
 * Prueba de humo del motor de exportación de Modern WP Migrator.
 *
 * Ejecuta, en orden, las etapas reales de exportación (filtro `ai1wm_export`) contra el WordPress
 * local y comprueba que se genera un archivo .wpress. Sirve para detectar avisos de código obsoleto
 * y errores en versiones nuevas de PHP, porque esas rutas solo se ejecutan al exportar.
 *
 * Uso (con la versión de PHP que quieras probar):
 *   php -d error_reporting=-1 -d display_errors=1 tests/modern-wp-migrator/export-smoke.php
 *
 * Borra la copia que genera al terminar. Se niega a correr si el sitio no parece local.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Solo CLI.\n" );
}

$host = getenv( 'EH_TEST_HOST' ) ?: 'pluginwordpress.test';
if ( ! preg_match( '/(\.test|\.local|localhost)$/', $host ) && '1' !== getenv( 'EH_TESTS_ALLOW' ) ) {
	exit( "Rechazado: el host '$host' no parece local.\n" );
}

$_SERVER['HTTP_HOST']      = $host;
$_SERVER['SERVER_NAME']    = $host;
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';

require dirname( __DIR__, 2 ) . '/wp-load.php';

if ( ! function_exists( 'ai1wm_get_filters' ) ) {
	exit( "Modern WP Migrator no está cargado: actívalo en el WordPress local.\n" );
}

echo 'PHP ' . PHP_VERSION . ' / WordPress ' . get_bloginfo( 'version' ) . PHP_EOL;

ai1wm_setup_environment();

$backups_dir = AI1WM_BACKUPS_PATH;
$before      = is_dir( $backups_dir ) ? glob( $backups_dir . '/*.wpress' ) : array();

$params = array(
	'secret_key' => get_option( AI1WM_SECRET_KEY ),
	'priority'   => 5,
);

// La última etapa (Ai1wm_Export_Clean) termina el proceso con exit() por diseño, así que la
// verificación del resultado y la limpieza se hacen al cerrar.
register_shutdown_function(
	function () use ( $backups_dir, $before ) {
		$stage = isset( $GLOBALS['eh_stage'] ) ? $GLOBALS['eh_stage'] : '(ninguna)';
		if ( 0 !== strpos( $stage, '300 ' ) ) {
			echo 'FALLO: la ejecución terminó antes de la última etapa, en: ' . $stage . PHP_EOL;
			exit( 1 );
		}

		$after = is_dir( $backups_dir ) ? glob( $backups_dir . '/*.wpress' ) : array();
		$new   = array_values( array_diff( $after, $before ) );
		if ( empty( $new ) ) {
			echo 'FALLO: se ejecutaron todas las etapas pero no se generó ningún .wpress' . PHP_EOL;
			exit( 1 );
		}

		foreach ( $new as $file ) {
			echo 'Copia generada: ' . basename( $file ) . ' (' . number_format( filesize( $file ) ) . ' bytes)' . PHP_EOL;
			unlink( $file ); // Solo se borra lo que creó esta prueba.
		}

		echo 'OK: todas las etapas de exportación se ejecutaron sin avisos ni errores' . PHP_EOL;
		exit( 0 );
	}
);

try {
	foreach ( ai1wm_get_filters( 'ai1wm_export' ) as $priority => $hooks ) {
		$guard = 0;
		do {
			unset( $params['completed'] );
			$params['priority'] = $priority;
			foreach ( $hooks as $hook ) {
				$GLOBALS['eh_stage'] = $priority . ' ' . ( is_string( $hook['function'] ) ? $hook['function'] : 'callable' );
				$params              = call_user_func( $hook['function'], $params );
			}
			$again = isset( $params['completed'] ) && false === $params['completed'];
		} while ( $again && ++$guard < 2000 );
	}
} catch ( Throwable $e ) {
	echo 'FALLO en la exportación: ' . get_class( $e ) . ': ' . $e->getMessage() . PHP_EOL;
	echo '  en ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
	$GLOBALS['eh_stage'] = 'excepción';
	exit( 1 );
}
