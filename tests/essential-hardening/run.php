<?php
/**
 * Pruebas de Essential Hardening contra el WordPress local (Laragon).
 *
 * Uso:
 *   php tests/essential-hardening/run.php
 *
 * No hay PHPUnit ni suite de WordPress en el repo: este script carga wp-load.php, ejecuta los
 * métodos públicos del plugin y deja la base de datos como estaba (usuario y transients temporales).
 * Por seguridad se niega a correr si el sitio no parece local.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Solo CLI.\n" );
}

$host = getenv( 'EH_TEST_HOST' ) ?: 'pluginwordpress.test';
if ( ! preg_match( '/(\.test|\.local|localhost)$/', $host ) && '1' !== getenv( 'EH_TESTS_ALLOW' ) ) {
	exit( "Rechazado: el host '$host' no parece local. Usa EH_TESTS_ALLOW=1 bajo tu responsabilidad.\n" );
}

$_SERVER['HTTP_HOST']      = $host;
$_SERVER['REMOTE_ADDR']    = '203.0.113.77'; // IP de documentación (RFC 5737), nunca real.
$_SERVER['REQUEST_METHOD'] = 'GET';

require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

if ( ! class_exists( 'Essential_Hardening' ) ) {
	exit( "Essential Hardening no está cargado: actívalo en el WordPress local.\n" );
}

// ---------------------------------------------------------------------------
// Mini framework de aserciones
// ---------------------------------------------------------------------------
$GLOBALS['eh_pass'] = 0;
$GLOBALS['eh_fail'] = array();

function eh_assert( bool $condition, string $name ): void {
	if ( $condition ) {
		$GLOBALS['eh_pass']++;
		echo "  ok   $name\n";
	} else {
		$GLOBALS['eh_fail'][] = $name;
		echo "  FAIL $name\n";
	}
}

function eh_section( string $title ): void {
	echo "\n$title\n";
}

/** Excepción para interceptar wp_safe_redirect() sin que el script termine. */
class EH_Redirect_Exception extends Exception {}

$eh = Essential_Hardening::get_instance();

/** Cambia las opciones internas del plugin (propiedad privada) para la prueba. */
function eh_set_options( Essential_Hardening $eh, array $overrides ): void {
	$prop = new ReflectionProperty( $eh, 'options' );
	$prop->setAccessible( true );
	$prop->setValue( $eh, array_merge( $eh->get_default_settings(), $overrides ) );
}

function eh_clear_ip_state( string $ip ): void {
	delete_transient( 'eh_lockout_' . md5( $ip ) );
	delete_transient( 'eh_fail_count_' . md5( $ip ) );
}

$ip = $_SERVER['REMOTE_ADDR'];
add_filter( 'essential_hardening_failed_login_delay', '__return_zero' );

// Usuarios temporales
$suffix   = wp_generate_password( 6, false );
$admin_id = wp_insert_user( array(
	'user_login' => 'eh_test_admin_' . $suffix,
	'user_pass'  => wp_generate_password( 20 ),
	'user_email' => 'eh-test-admin-' . $suffix . '@example.invalid',
	'role'       => 'administrator',
) );
$editor_id = wp_insert_user( array(
	'user_login' => 'eh_test_editor_' . $suffix,
	'user_pass'  => wp_generate_password( 20 ),
	'user_email' => 'eh-test-editor-' . $suffix . '@example.invalid',
	'role'       => 'editor',
) );
$admin  = get_user_by( 'id', $admin_id );
$editor = get_user_by( 'id', $editor_id );

$original_settings = get_option( Essential_Hardening::OPTION_NAME, null );
$original_log      = get_option( 'eh_security_events_log', null );

try {

	// -----------------------------------------------------------------------
	eh_section( '2FA por correo' );
	// -----------------------------------------------------------------------
	eh_set_options( $eh, array( 'email_2fa_enabled' => true ) );

	$sent_mails = array();
	add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$sent_mails ) {
		$sent_mails[] = $atts;
		return true; // Simula envío correcto, sin enviar nada.
	}, 10, 2 );
	add_filter( 'wp_redirect', function ( $location ) {
		throw new EH_Redirect_Exception( (string) $location );
	} );

	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array( 'log' => $admin->user_login );

	$redirect = '';
	try {
		$result = $eh->intercept_login_for_2fa( $admin, $admin->user_login, 'x' );
	} catch ( EH_Redirect_Exception $e ) {
		$redirect = $e->getMessage();
		$result   = null;
	}
	eh_assert( '' !== $redirect && false !== strpos( $redirect, 'action=eh_2fa' ), 'admin con contraseña correcta es enviado a la pantalla 2FA' );
	eh_assert( 1 === count( $sent_mails ), 'se envía un correo con el código' );
	preg_match( '/\b(\d{6})\b/', $sent_mails[0]['message'] ?? '', $m );
	eh_assert( ! empty( $m[1] ), 'el correo contiene un código de 6 dígitos' );

	// REGRESIÓN: el parámetro eh_2fa_verified permitía saltarse el 2FA (corregido en 1.4.2)
	$_POST = array( 'log' => $admin->user_login, 'eh_2fa_verified' => '1' );
	$bypassed = true;
	try {
		$result = $eh->intercept_login_for_2fa( $admin, $admin->user_login, 'x' );
	} catch ( EH_Redirect_Exception $e ) {
		$bypassed = false;
	}
	eh_assert( ! $bypassed, 'REGRESIÓN: eh_2fa_verified=1 NO salta el 2FA' );

	// Rate limit: ya van 2 emisiones; la 5ª pasa y la 6ª se rechaza
	delete_transient( 'eh_2fa_rate_' . $admin->ID );
	$_POST   = array( 'log' => $admin->user_login );
	$blocked = null;
	for ( $i = 1; $i <= 6; $i++ ) {
		try {
			$r = $eh->intercept_login_for_2fa( $admin, $admin->user_login, 'x' );
			if ( $r instanceof WP_Error ) {
				$blocked = $i;
				break;
			}
		} catch ( EH_Redirect_Exception $e ) {
			// Emisión normal.
		}
	}
	eh_assert( 6 === $blocked, 'la 6ª solicitud de código en 10 min se rechaza (rate limit)' );
	delete_transient( 'eh_2fa_rate_' . $admin->ID );

	// Usuarios sin manage_options no pasan por 2FA
	$_POST = array( 'log' => $editor->user_login );
	$r     = $eh->intercept_login_for_2fa( $editor, $editor->user_login, 'x' );
	eh_assert( $r instanceof WP_User && $r->ID === $editor->ID, 'un editor no pasa por 2FA' );

	// Peticiones que no son el POST de login no se interceptan (REST, cron, CLI)
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = array();
	$r = $eh->intercept_login_for_2fa( $admin, $admin->user_login, 'x' );
	eh_assert( $r instanceof WP_User, 'una petición que no es POST de login no se intercepta' );
	$_SERVER['REQUEST_METHOD'] = 'POST';

	// Fallo de correo: no dejar al administrador fuera (fail-open documentado)
	remove_all_filters( 'pre_wp_mail' );
	add_filter( 'pre_wp_mail', '__return_false' );
	$_POST = array( 'log' => $admin->user_login );
	try {
		$r = $eh->intercept_login_for_2fa( $admin, $admin->user_login, 'x' );
	} catch ( EH_Redirect_Exception $e ) {
		$r = null;
	}
	eh_assert( $r instanceof WP_User, 'si el correo falla, el administrador no queda bloqueado' );
	remove_all_filters( 'pre_wp_mail' );
	remove_all_filters( 'wp_redirect' );
	delete_transient( 'eh_2fa_rate_' . $admin->ID );

	// -----------------------------------------------------------------------
	eh_section( 'Bloqueo de login (throttle)' );
	// -----------------------------------------------------------------------
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = array();
	eh_set_options( $eh, array( 'login_throttle' => true, 'throttle_max_attempts' => 3, 'throttle_lockout_duration' => 900 ) );
	eh_clear_ip_state( $ip );

	$eh->record_failed_login( 'x' );
	$eh->record_failed_login( 'x' );
	eh_assert( false === get_transient( 'eh_lockout_' . md5( $ip ) ), 'con 2 fallos de 3 la IP todavía no está bloqueada' );
	$eh->record_failed_login( 'x' );
	eh_assert( (bool) get_transient( 'eh_lockout_' . md5( $ip ) ), 'al 3er fallo la IP queda bloqueada' );

	$r = $eh->check_login_throttle( null, 'x', 'y' );
	eh_assert( $r instanceof WP_Error && 'eh_ip_locked' === $r->get_error_code(), 'una IP bloqueada recibe error eh_ip_locked' );

	$eh->record_failed_login( 'x' );
	eh_assert( false === get_transient( 'eh_fail_count_' . md5( $ip ) ), 'intentos durante el bloqueo no suman ni extienden el castigo' );

	$eh->clear_failed_login( 'x', $admin );
	eh_assert( false === get_transient( 'eh_lockout_' . md5( $ip ) ), 'un login correcto limpia el bloqueo' );
	$r = $eh->check_login_throttle( null, 'x', 'y' );
	eh_assert( ! ( $r instanceof WP_Error ), 'sin bloqueo, check_login_throttle deja pasar' );
	eh_clear_ip_state( $ip );

	// Filtro de IP real (proxy de confianza)
	add_filter( 'essential_hardening_client_ip', function () {
		return '198.51.100.5';
	} );
	$eh->record_failed_login( 'x' );
	eh_assert( 1 === (int) get_transient( 'eh_fail_count_' . md5( '198.51.100.5' ) ), 'el filtro essential_hardening_client_ip cambia la IP usada' );
	remove_all_filters( 'essential_hardening_client_ip' );
	delete_transient( 'eh_fail_count_' . md5( '198.51.100.5' ) );

	// IP inválida del filtro cae a 0.0.0.0 (no rompe)
	add_filter( 'essential_hardening_client_ip', function () {
		return 'no-es-una-ip';
	} );
	$eh->record_failed_login( 'x' );
	eh_assert( 1 === (int) get_transient( 'eh_fail_count_' . md5( '0.0.0.0' ) ), 'una IP inválida del filtro se normaliza a 0.0.0.0' );
	remove_all_filters( 'essential_hardening_client_ip' );
	delete_transient( 'eh_fail_count_' . md5( '0.0.0.0' ) );
	eh_clear_ip_state( $ip );

	// -----------------------------------------------------------------------
	eh_section( 'Honeypot' );
	// -----------------------------------------------------------------------
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array( 'eh_login_company' => 'soy-un-bot' );
	$t = microtime( true );
	$r = $eh->validate_login_honeypot( null, 'u', 'p' );
	eh_assert( $r instanceof WP_Error && 'eh_bot_detected' === $r->get_error_code(), 'campo trampa completado => login rechazado' );
	eh_assert( ( microtime( true ) - $t ) < 1, 'el rechazo del bot es inmediato (sin sleep)' );
	$_POST = array();
	$r     = $eh->validate_login_honeypot( $admin, 'u', 'p' );
	eh_assert( $r instanceof WP_User, 'campo trampa vacío => no interfiere' );
	$_SERVER['REQUEST_METHOD'] = 'GET';

	// -----------------------------------------------------------------------
	eh_section( 'Protección de uploads (.htaccess)' );
	// -----------------------------------------------------------------------
	$tmp = sys_get_temp_dir() . '/eh_uploads_' . $suffix;
	mkdir( $tmp );
	$override = function ( $dirs ) use ( $tmp ) {
		$dirs['basedir'] = $tmp;
		return $dirs;
	};
	add_filter( 'upload_dir', $override );
	$ht = $tmp . '/.htaccess';

	$eh->ensure_uploads_protection();
	$c = (string) file_get_contents( $ht );
	eh_assert( false !== strpos( $c, 'Require all denied' ), 'crea reglas Require all denied (Apache 2.4)' );
	eh_assert( (bool) preg_match( '/<IfModule mod_access_compat\.c>\s*<FilesMatch[^>]*>\s*Order Deny,Allow/', $c ), 'Order/Deny solo dentro de IfModule mod_access_compat (evita error 500)' );
	eh_assert( 0 === preg_match( '/^Order /m', $c ), 'no hay directivas Order sueltas fuera de IfModule' );

	// Migración del bloque antiguo (anterior a 1.4.2)
	file_put_contents( $ht, "Mio\n\n# BEGIN Essential Hardening Uploads Protection\n<FilesMatch \"x\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\n# END Essential Hardening Uploads Protection\nFinal\n" );
	$eh->ensure_uploads_protection();
	$c = (string) file_get_contents( $ht );
	eh_assert( false !== strpos( $c, 'mod_access_compat' ), 'migra el bloque antiguo al nuevo' );
	eh_assert( false !== strpos( $c, "Mio\n" ) && false !== strpos( $c, 'Final' ), 'la migración conserva el contenido ajeno del .htaccess' );
	eh_assert( 1 === substr_count( $c, '# BEGIN Essential Hardening' ), 'queda un único bloque del plugin' );

	// Idempotente
	$before = (string) file_get_contents( $ht );
	$eh->ensure_uploads_protection();
	eh_assert( $before === (string) file_get_contents( $ht ), 'ejecutarlo de nuevo no cambia el archivo' );

	// Desactivación: quita solo su bloque
	Essential_Hardening::on_deactivation();
	$c = (string) file_get_contents( $ht );
	eh_assert( false === strpos( $c, 'Essential Hardening' ) && false !== strpos( $c, 'Mio' ) && false !== strpos( $c, 'Final' ), 'on_deactivation quita su bloque y respeta el resto' );

	remove_filter( 'upload_dir', $override );
	@unlink( $ht );
	@rmdir( $tmp );

	// -----------------------------------------------------------------------
	eh_section( 'HSTS y CSP en modo solo-reporte' );
	// -----------------------------------------------------------------------
	// is_ssl() lee $_SERVER['HTTPS']
	unset( $_SERVER['HTTPS'] );
	eh_set_options( $eh, array( 'hsts_enabled' => true, 'hsts_max_age' => 86400, 'csp_report_only' => false ) );
	eh_assert( array() === $eh->get_transport_headers(), 'HSTS NO se envía por HTTP' );

	$_SERVER['HTTPS'] = 'on';
	eh_assert( array( 'Strict-Transport-Security: max-age=86400' ) === $eh->get_transport_headers(), 'HSTS se envía por HTTPS con el max-age configurado' );

	eh_set_options( $eh, array( 'hsts_enabled' => true, 'hsts_max_age' => 999 ) );
	eh_assert( array( 'Strict-Transport-Security: max-age=2592000' ) === $eh->get_transport_headers(), 'un max-age no permitido cae al valor seguro de 30 días' );

	add_filter( 'essential_hardening_hsts_include_subdomains', '__return_true' );
	eh_assert( false !== strpos( $eh->get_transport_headers()[0], 'includeSubDomains' ), 'includeSubDomains solo con el filtro' );
	remove_all_filters( 'essential_hardening_hsts_include_subdomains' );

	eh_set_options( $eh, array( 'hsts_enabled' => false, 'csp_report_only' => true ) );
	$h = $eh->get_transport_headers();
	eh_assert( 1 === count( $h ) && 0 === strpos( $h[0], 'Content-Security-Policy-Report-Only: ' ), 'la CSP se envía SOLO como Report-Only (nunca bloquea)' );
	eh_assert( false === strpos( $h[0], 'Content-Security-Policy: ' ), 'no existe la cabecera CSP que bloquea' );

	add_filter( 'essential_hardening_csp_policy', function () {
		return "default-src 'self'
Set-Cookie: x=1";
	} );
	$h = $eh->get_transport_headers();
	eh_assert( 1 === count( $h ) && false === strpbrk( $h[0], "
" ), 'saltos de línea en la política no inyectan cabeceras' );
	remove_all_filters( 'essential_hardening_csp_policy' );

	eh_set_options( $eh, array( 'hsts_enabled' => false, 'csp_report_only' => false ) );
	eh_assert( array() === $eh->get_transport_headers(), 'con ambas opciones apagadas no se envía nada (defecto)' );

	$clean = $eh->sanitize_settings( array( 'hsts_enabled' => '1', 'hsts_max_age' => '7' ) );
	eh_assert( true === $clean['hsts_enabled'] && 2592000 === $clean['hsts_max_age'], 'el guardado de ajustes valida el max-age' );
	$clean = $eh->sanitize_settings( array() );
	eh_assert( false === $clean['hsts_enabled'] && false === $clean['csp_report_only'], 'al guardar sin marcar, HSTS y CSP quedan apagados' );
	unset( $_SERVER['HTTPS'] );

	// -----------------------------------------------------------------------
	eh_section( 'Alertas de administradores nuevos o promovidos' );
	// -----------------------------------------------------------------------
	eh_set_options( $eh, array( 'admin_change_alerts' => true, 'security_event_log' => true ) );
	delete_option( 'eh_security_events_log' );

	$alert_mails = array();
	add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$alert_mails ) {
		$alert_mails[] = $atts;
		return true;
	}, 10, 2 );

	// Usuario nuevo con rol Administrador => alerta (camino real: wp_insert_user dispara user_register)
	$new_admin_id = wp_insert_user( array(
		'user_login' => 'eh_test_newadmin_' . $suffix,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'eh-test-newadmin-' . $suffix . '@example.invalid',
		'role'       => 'administrator',
	) );
	eh_assert( 1 === count( $alert_mails ), 'crear un Administrador envía una alerta' );
	eh_assert( isset( $alert_mails[0]['subject'] ) && false !== strpos( $alert_mails[0]['subject'], 'Cambio de administradores' ), 'el asunto identifica el cambio de administradores' );
	eh_assert( false !== strpos( (string) ( $alert_mails[0]['message'] ?? '' ), 'eh_test_newadmin_' . $suffix ), 'el correo nombra al usuario afectado' );

	// Usuario nuevo sin privilegios => sin alerta
	$alert_mails = array();
	$sub_id      = wp_insert_user( array(
		'user_login' => 'eh_test_sub_' . $suffix,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'eh-test-sub-' . $suffix . '@example.invalid',
		'role'       => 'subscriber',
	) );
	eh_assert( 0 === count( $alert_mails ), 'crear un suscriptor NO envía alerta' );

	// Promoción de editor a administrador => alerta
	$alert_mails = array();
	( new WP_User( $editor_id ) )->set_role( 'administrator' );
	eh_assert( 1 === count( $alert_mails ), 'promover a una cuenta existente a Administrador envía una alerta' );

	// Volver a asignar el rol dentro de 5 minutos no repite el aviso
	$alert_mails = array();
	( new WP_User( $editor_id ) )->add_role( 'administrator' );
	eh_assert( 0 === count( $alert_mails ), 'el mismo usuario no genera más de un aviso en 5 minutos' );

	// Degradar no avisa
	$alert_mails = array();
	( new WP_User( $editor_id ) )->set_role( 'editor' );
	eh_assert( 0 === count( $alert_mails ), 'quitar el rol de Administrador NO envía alerta' );

	$events = get_option( 'eh_security_events_log', array() );
	$types  = is_array( $events ) ? wp_list_pluck( $events, 'type' ) : array();
	eh_assert( in_array( 'Cambio de Admin', $types, true ), 'el cambio queda en el registro de eventos' );

	eh_set_options( $eh, array( 'admin_change_alerts' => false ) );
	eh_assert( true === $eh->get_default_settings()['admin_change_alerts'], 'las alertas de administradores vienen activas por defecto' );
	eh_assert( false === $eh->get_default_settings()['core_integrity_check'], 'la verificación del núcleo viene apagada por defecto' );

	remove_all_filters( 'pre_wp_mail' );
	delete_transient( 'eh_admin_change_' . $new_admin_id );
	delete_transient( 'eh_admin_change_' . $editor_id );
	wp_delete_user( $new_admin_id );
	wp_delete_user( $sub_id );

	// -----------------------------------------------------------------------
	eh_section( 'Integridad del núcleo de WordPress' );
	// -----------------------------------------------------------------------
	$root = sys_get_temp_dir() . '/eh_core_' . $suffix;
	foreach ( array( '', '/wp-admin', '/wp-includes', '/wp-content/plugins' ) as $d ) {
		mkdir( $root . $d, 0777, true );
	}
	file_put_contents( $root . '/index.php', 'limpio-index' );
	file_put_contents( $root . '/wp-admin/a.php', 'MODIFICADO' );
	file_put_contents( $root . '/wp-includes/ok.php', 'limpio-ok' );
	file_put_contents( $root . '/wp-includes/notes.txt', 'texto ajeno (no ejecutable)' );
	file_put_contents( $root . '/wp-includes/evil.php', 'webshell' );
	file_put_contents( $root . '/shell.php', 'webshell' );
	file_put_contents( $root . '/wp-config.php', 'config propia' );
	file_put_contents( $root . '/wp-content/plugins/x.php', 'plugin propio' );

	$checksums = array(
		'index.php'              => md5( 'limpio-index' ),
		'wp-admin/a.php'         => md5( 'original-a' ),
		'wp-includes/ok.php'     => md5( 'limpio-ok' ),
		'wp-includes/missing.php' => md5( 'x' ),
		'readme.html'            => md5( 'readme' ),
		'wp-content/plugins/hello.php' => md5( 'hello' ),
		'wp-includes/version.php' => false,
	);
	file_put_contents( $root . '/wp-includes/version.php', 'version propia del idioma' );

	$r = $eh->compare_core_files( $checksums, $root );
	eh_assert( array( 'wp-admin/a.php' ) === $r['modified'], 'detecta un archivo del núcleo modificado' );
	eh_assert( array( 'wp-includes/missing.php' ) === $r['missing'], 'detecta un archivo del núcleo que falta' );
	eh_assert( array( 'shell.php', 'wp-includes/evil.php' ) === $r['unexpected'], 'detecta PHP ajeno en la raíz y en wp-includes' );
	eh_assert( ! in_array( 'wp-config.php', $r['unexpected'], true ), 'wp-config.php no se marca como inesperado' );
	eh_assert( ! in_array( 'wp-includes/notes.txt', $r['unexpected'], true ), 'archivos no ejecutables no generan ruido' );
	eh_assert( ! in_array( 'wp-includes/version.php', array_merge( $r['modified'], $r['unexpected'], $r['missing'] ), true ), 'un archivo marcado como "no comparar" (version.php con idioma sin lista) no genera avisos' );
	eh_assert( ! in_array( 'readme.html', $r['missing'], true ), 'readme.html ausente no se reporta (lo borran muchos hostings)' );
	eh_assert( ! in_array( 'wp-content/plugins/hello.php', $r['missing'], true ) && ! in_array( 'wp-content/plugins/x.php', $r['unexpected'], true ), 'wp-content no se revisa' );

	// Ejecución completa: informe, un solo correo por hallazgo y recuperación
	eh_set_options( $eh, array( 'core_integrity_check' => true ) );
	add_filter( 'essential_hardening_core_checksums', function () use ( $checksums ) {
		return $checksums;
	} );
	add_filter( 'essential_hardening_core_root', function () use ( $root ) {
		return $root;
	} );
	$core_mails = array();
	add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$core_mails ) {
		$core_mails[] = $atts;
		return true;
	}, 10, 2 );
	delete_transient( 'eh_last_core_alert_hash' );
	delete_option( 'eh_core_integrity_last' );

	$report = $eh->run_core_integrity_check();
	eh_assert( 'issues' === $report['status'], 'la verificación informa "issues" cuando hay diferencias' );
	eh_assert( 1 === count( $core_mails ) && false === strpos( $core_mails[0]['message'], $root ), 'envía un correo y no revela la ruta absoluta del servidor' );
	eh_assert( false !== strpos( $core_mails[0]['message'], 'wp-includes/evil.php' ), 'el correo lista los archivos afectados' );
	eh_assert( is_array( get_option( 'eh_core_integrity_last' ) ), 'guarda el último informe para el panel' );

	$eh->run_core_integrity_check();
	eh_assert( 1 === count( $core_mails ), 'el mismo hallazgo no repite el correo' );

	$eh->run_core_integrity_check( false );
	eh_assert( 1 === count( $core_mails ), 'la verificación manual (notify=false) no envía correo' );

	// Se corrige el sitio => limpio y se reinicia la alerta
	file_put_contents( $root . '/wp-admin/a.php', 'original-a' );
	file_put_contents( $root . '/wp-includes/missing.php', 'x' );
	unlink( $root . '/wp-includes/evil.php' );
	unlink( $root . '/shell.php' );
	$report = $eh->run_core_integrity_check();
	eh_assert( 'clean' === $report['status'], 'tras corregir los archivos, el informe es "clean"' );
	eh_assert( false === get_transient( 'eh_last_core_alert_hash' ), 'al quedar limpio se reinicia el control de correos repetidos' );

	// Lista oficial no disponible (sin internet): no rompe nada ni avisa en falso
	remove_all_filters( 'essential_hardening_core_checksums' );
	add_filter( 'essential_hardening_core_checksums', '__return_false' );
	$before = count( $core_mails );
	$report = $eh->run_core_integrity_check();
	eh_assert( 'unavailable' === $report['status'] && count( $core_mails ) === $before, 'sin la lista oficial informa "unavailable" y no envía correo' );

	remove_all_filters( 'essential_hardening_core_checksums' );
	remove_all_filters( 'essential_hardening_core_root' );
	remove_all_filters( 'pre_wp_mail' );

	// Limpieza del árbol temporal (solo lo creado arriba)
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $root );

} finally {
	// Restaurar el estado del sitio local
	remove_all_filters( 'pre_wp_mail' );
	remove_all_filters( 'wp_redirect' );
	eh_clear_ip_state( $ip );
	delete_transient( 'eh_2fa_rate_' . $admin_id );
	delete_option( 'eh_core_integrity_last' );
	delete_transient( 'eh_last_core_alert_hash' );
	delete_transient( 'eh_admin_change_' . $editor_id );
	if ( null === $original_settings ) {
		delete_option( Essential_Hardening::OPTION_NAME );
	} else {
		update_option( Essential_Hardening::OPTION_NAME, $original_settings );
	}
	if ( null === $original_log ) {
		delete_option( 'eh_security_events_log' );
	} else {
		update_option( 'eh_security_events_log', $original_log, false );
	}
	wp_delete_user( $admin_id );
	wp_delete_user( $editor_id );
}

echo "\n----------------------------------------\n";
printf( "%d pruebas correctas, %d fallidas\n", $GLOBALS['eh_pass'], count( $GLOBALS['eh_fail'] ) );
foreach ( $GLOBALS['eh_fail'] as $name ) {
	echo " - $name\n";
}
exit( empty( $GLOBALS['eh_fail'] ) ? 0 : 1 );
