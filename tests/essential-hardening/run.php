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

	// -----------------------------------------------------------------------
	eh_section( 'Recuperación de contraseña' );
	// -----------------------------------------------------------------------
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$lp_key = 'eh_lostpw_' . md5( $ip );
	delete_transient( $lp_key );

	$limited_at = null;
	for ( $i = 1; $i <= 7; $i++ ) {
		$e = new WP_Error();
		$eh->limit_lostpassword_requests( $e, false );
		if ( null === $limited_at && $e->get_error_code() === 'eh_lostpw_limited' ) {
			$limited_at = $i;
		}
	}
	eh_assert( 6 === $limited_at, 'permite 5 solicitudes por hora y bloquea la 6ª' );
	$e = new WP_Error();
	$eh->limit_lostpassword_requests( $e, false );
	eh_assert( 'eh_lostpw_limited' === $e->get_error_code(), 'las siguientes solicitudes siguen bloqueadas' );

	$events = get_option( 'eh_security_events_log', array() );
	$types  = is_array( $events ) ? array_count_values( wp_list_pluck( $events, 'type' ) ) : array();
	eh_assert( 1 === ( $types['Recuperación'] ?? 0 ), 'el límite se registra una sola vez, no en cada intento' );

	delete_transient( $lp_key );
	add_filter( 'essential_hardening_lostpassword_max_per_hour', function () {
		return 2;
	} );
	$codes = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$e = new WP_Error();
		$eh->limit_lostpassword_requests( $e, false );
		$codes[] = $e->get_error_code();
	}
	eh_assert( array( '', '', 'eh_lostpw_limited' ) === $codes, 'el límite se ajusta con el filtro essential_hardening_lostpassword_max_per_hour' );
	remove_all_filters( 'essential_hardening_lostpassword_max_per_hour' );

	delete_transient( $lp_key );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$e = new WP_Error();
	for ( $i = 0; $i < 10; $i++ ) {
		$eh->limit_lostpassword_requests( $e, false );
	}
	eh_assert( ! $e->has_errors() && false === get_transient( $lp_key ), 'las peticiones GET no cuentan' );
	$_SERVER['REQUEST_METHOD'] = 'POST';

	// Respuesta genérica: misma salida exista o no la cuenta
	add_filter( 'wp_redirect', function ( $location ) {
		throw new EH_Redirect_Exception( (string) $location );
	} );
	$redirected = function ( WP_Error $errors, $user_data ) use ( $eh ) {
		try {
			$eh->generic_lostpassword_response( $errors, $user_data );
		} catch ( EH_Redirect_Exception $ex ) {
			return $ex->getMessage();
		}
		return null;
	};

	$loc = $redirected( new WP_Error( 'invalid_email', 'no existe' ), false );
	eh_assert( null !== $loc && false !== strpos( $loc, 'checkemail=confirm' ), 'correo inexistente => misma pantalla de "revisa tu correo"' );
	$loc = $redirected( new WP_Error(), false );
	eh_assert( null !== $loc && false !== strpos( $loc, 'checkemail=confirm' ), 'usuario inexistente => misma pantalla de "revisa tu correo"' );
	eh_assert( null === $redirected( new WP_Error(), $admin ), 'cuenta que existe => flujo normal de WordPress (no se intercepta)' );
	eh_assert( null === $redirected( new WP_Error( 'empty_username', 'vacío' ), false ), 'campo vacío => se muestra el error normal' );
	$mixed = new WP_Error( 'eh_lostpw_limited', 'límite' );
	$mixed->add( 'invalid_email', 'no existe' );
	eh_assert( null === $redirected( $mixed, false ), 'si hay límite de solicitudes, se muestra el error del límite' );
	remove_all_filters( 'wp_redirect' );
	delete_transient( $lp_key );
	$_SERVER['REQUEST_METHOD'] = 'GET';

	// -----------------------------------------------------------------------
	eh_section( 'Mapa del sitio de usuarios' );
	// -----------------------------------------------------------------------
	eh_assert( false === apply_filters( 'wp_sitemaps_add_provider', new stdClass(), 'users' ), 'el mapa del sitio de usuarios (lista de autores) está desactivado' );
	$posts_provider = new stdClass();
	eh_assert( $posts_provider === apply_filters( 'wp_sitemaps_add_provider', $posts_provider, 'posts' ), 'el mapa del sitio de entradas no se toca' );

	// -----------------------------------------------------------------------
	eh_section( 'Contraseñas robustas para administradores' );
	// -----------------------------------------------------------------------
	$pw_calls = array();
	$pw_mode  = 'clean';
	$breached = 'Clave-Filtrada-Larga-2026';
	$bhash    = strtoupper( sha1( $breached ) );
	add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$pw_calls, &$pw_mode, $bhash ) {
		if ( false === strpos( $url, 'api.pwnedpasswords.com/range/' ) ) {
			return $pre;
		}
		$pw_calls[] = array( 'url' => $url, 'args' => $args );
		if ( 'down' === $pw_mode ) {
			return new WP_Error( 'http_request_failed', 'sin conexión' );
		}
		if ( 'error500' === $pw_mode ) {
			return array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'body' => 'x', 'headers' => array(), 'cookies' => array(), 'filename' => null );
		}
		$body = "0018A45C4D1DEF81644B54AB7F969B88D65:3\r\n00D4F6E8FA6EECAD2A3AA415EEC418D38EC:0\r\n";
		if ( 'breached' === $pw_mode ) {
			$body .= substr( $bhash, 5 ) . ":42\r\n";
		}
		if ( 'padded' === $pw_mode ) {
			$body .= substr( $bhash, 5 ) . ":0\r\n"; // Relleno (padding) con 0 apariciones.
		}
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => $body, 'headers' => array(), 'cookies' => array(), 'filename' => null );
	}, 10, 3 );

	$pw_calls = array();
	$msg      = $eh->get_admin_password_problem( 'corta123' );
	eh_assert( '' !== $msg && 0 === count( $pw_calls ), 'una contraseña corta se rechaza sin consultar a ningún servicio' );

	$pw_mode = 'breached';
	$msg     = $eh->get_admin_password_problem( $breached );
	eh_assert( '' !== $msg && false !== strpos( $msg, '42' ), 'una contraseña filtrada se rechaza e indica cuántas veces aparece' );
	$sent = $pw_calls[0]['url'] ?? '';
	eh_assert( 1 === preg_match( '#/range/[0-9A-F]{5}$#', $sent ), 'solo se envían 5 caracteres del hash a la API' );
	eh_assert( false === strpos( $sent, substr( $bhash, 5 ) ) && false === strpos( $sent, $breached ), 'ni la contraseña ni el resto del hash salen del servidor' );
	eh_assert( 'true' === ( $pw_calls[0]['args']['headers']['Add-Padding'] ?? '' ), 'pide relleno (Add-Padding) para ocultar el tamaño de la respuesta' );

	$pw_mode = 'clean';
	eh_assert( '' === $eh->get_admin_password_problem( 'Una-Clave-Larga-Y-Unica-77' ), 'una contraseña larga que no aparece en filtraciones se acepta' );
	$pw_mode = 'padded';
	eh_assert( '' === $eh->get_admin_password_problem( $breached ), 'las entradas de relleno con 0 apariciones no cuentan como filtración' );
	$pw_mode = 'down';
	eh_assert( '' === $eh->get_admin_password_problem( $breached ), 'si el servicio no responde, no se bloquea a nadie' );
	$pw_mode = 'error500';
	eh_assert( '' === $eh->get_admin_password_problem( $breached ), 'si el servicio devuelve error, no se bloquea a nadie' );

	add_filter( 'essential_hardening_admin_password_min_length', function () {
		return 20;
	} );
	$pw_mode = 'clean';
	eh_assert( '' !== $eh->get_admin_password_problem( 'Quince-Letras-15' ), 'la longitud mínima se ajusta con el filtro' );
	remove_all_filters( 'essential_hardening_admin_password_min_length' );

	// Ganchos de WordPress
	$pw_mode  = 'breached';
	$pw_calls = array();
	$e        = new WP_Error();
	$eh->validate_admin_password_on_profile( $e, false, (object) array( 'user_pass' => $breached, 'role' => 'administrator' ) );
	eh_assert( 'eh_weak_password' === $e->get_error_code(), 'al crear un Administrador con contraseña filtrada se rechaza' );

	$pw_calls = array();
	$e        = new WP_Error();
	$eh->validate_admin_password_on_profile( $e, false, (object) array( 'user_pass' => $breached, 'role' => 'subscriber' ) );
	eh_assert( ! $e->has_errors() && 0 === count( $pw_calls ), 'un suscriptor no se valida ni consulta nada' );

	$e = new WP_Error();
	$eh->validate_admin_password_on_profile( $e, true, (object) array( 'user_pass' => $breached, 'ID' => $admin_id ) );
	eh_assert( 'eh_weak_password' === $e->get_error_code(), 'al editar a un administrador existente se valida su nueva contraseña' );

	$e = new WP_Error();
	$eh->validate_admin_password_on_profile( $e, true, (object) array( 'user_pass' => $breached, 'ID' => $editor_id ) );
	eh_assert( ! $e->has_errors(), 'editar a un editor no se valida' );

	$e = new WP_Error();
	$eh->validate_admin_password_on_profile( $e, true, (object) array( 'user_pass' => '', 'ID' => $admin_id ) );
	eh_assert( ! $e->has_errors(), 'si no se cambia la contraseña no se valida nada' );

	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST['pass1']            = wp_slash( $breached );
	$e = new WP_Error();
	$eh->validate_admin_password_on_reset( $e, $admin );
	eh_assert( 'eh_weak_password' === $e->get_error_code(), 'el restablecimiento de contraseña de un administrador también se valida' );
	$e = new WP_Error();
	$eh->validate_admin_password_on_reset( $e, $editor );
	eh_assert( ! $e->has_errors(), 'el restablecimiento de un editor no se valida' );
	unset( $_POST['pass1'] );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	remove_all_filters( 'pre_http_request' );

	// -----------------------------------------------------------------------
	eh_section( 'Contraseñas de aplicación' );
	// -----------------------------------------------------------------------
	eh_assert( false === $eh->get_default_settings()['disable_application_passwords'], 'las contraseñas de aplicación siguen permitidas por defecto' );
	eh_assert( false === $eh->get_default_settings()['strong_admin_passwords'], 'la comprobación de contraseñas viene apagada por defecto' );
	eh_assert( true === $eh->get_default_settings()['lostpassword_protection'], 'la protección de recuperación viene activa por defecto' );

	// Se registra en una instancia aparte, al final, para no alterar el resto de pruebas
	$fresh = ( new ReflectionClass( 'Essential_Hardening' ) )->newInstanceWithoutConstructor();
	eh_set_options( $fresh, array( 'disable_application_passwords' => true, 'update_notifications' => false, 'admin_login_alerts' => false, 'admin_change_alerts' => false ) );
	$init = new ReflectionMethod( $fresh, 'init_modules' );
	$init->setAccessible( true );
	$init->invoke( $fresh );
	eh_assert( false === wp_is_application_passwords_available(), 'con la opción activa, WordPress deja de ofrecer contraseñas de aplicación' );
	remove_all_filters( 'wp_is_application_passwords_available' );

	// -----------------------------------------------------------------------
	eh_section( 'Estructura del código' );
	// -----------------------------------------------------------------------
	$trait_files = glob( dirname( __DIR__, 2 ) . '/plugins/essential-hardening/includes/trait-*.php' );
	$used        = array_keys( class_uses( 'Essential_Hardening' ) );
	eh_assert( count( $trait_files ) > 0 && count( $trait_files ) === count( $used ), 'cada archivo de includes/ está incorporado a la clase (' . count( $used ) . ' módulos)' );
	foreach ( $trait_files as $trait_file ) {
		$expected = 'Essential_Hardening_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', substr( basename( $trait_file, '.php' ), 6 ) ) ) );
		eh_assert( trait_exists( $expected, false ), 'el archivo ' . basename( $trait_file ) . ' define el trait ' . $expected );
	}

	// -----------------------------------------------------------------------
	eh_section( 'Bloqueo de IP por escaneo de autores' );
	// -----------------------------------------------------------------------
	eh_set_options( $eh, array( 'security_event_log' => true, 'recon_ip_ban' => true ) );
	wp_set_current_user( 0 );
	delete_option( 'eh_security_events_log' );

	$recon_ref  = new ReflectionClass( $eh );
	$scan       = $recon_ref->getMethod( 'register_author_scan' );
	$bannable   = $recon_ref->getMethod( 'is_ip_bannable' );
	$scan->setAccessible( true );
	$bannable->setAccessible( true );

	$scanner_ip = '201.223.127.28';
	$count_key  = 'eh_recon_count_' . md5( $scanner_ip );
	$ban_key    = 'eh_recon_ban_' . md5( $scanner_ip );
	delete_transient( $count_key );
	delete_transient( $ban_key );

	eh_assert( true === $bannable->invoke( $eh, $scanner_ip ), 'una IP publica se puede banear' );
	eh_assert( false === $bannable->invoke( $eh, '127.0.0.1' ), 'localhost nunca se banea' );
	eh_assert( false === $bannable->invoke( $eh, '192.168.1.10' ), 'una IP de red local nunca se banea' );
	eh_assert( false === $bannable->invoke( $eh, '0.0.0.0' ), 'IP desconocida (0.0.0.0) nunca se banea' );

	for ( $i = 1; $i <= 4; $i++ ) {
		$scan->invoke( $eh, $scanner_ip );
	}
	eh_assert( false === get_transient( $ban_key ), '4 escaneos todavia no bloquean' );
	eh_assert( 4 === (int) get_transient( $count_key ), 'se cuentan los escaneos' );

	$scan->invoke( $eh, $scanner_ip );
	eh_assert( false !== get_transient( $ban_key ), 'el 5.o escaneo bloquea la IP' );
	eh_assert( false === get_transient( $count_key ), 'el contador se reinicia al bloquear' );
	$events = get_option( 'eh_security_events_log', array() );
	eh_assert( ! empty( $events ) && false !== strpos( $events[0]['detail'], 'IP bloqueada' ), 'el bloqueo queda en el registro de eventos' );

	// IP privada y usuario con sesion: no cuentan
	$scan->invoke( $eh, '127.0.0.1' );
	eh_assert( false === get_transient( 'eh_recon_count_' . md5( '127.0.0.1' ) ), 'una IP local no acumula escaneos' );
	delete_transient( $ban_key );
	wp_set_current_user( $admin_id );
	for ( $i = 1; $i <= 6; $i++ ) {
		$scan->invoke( $eh, $scanner_ip );
	}
	eh_assert( false === get_transient( $ban_key ) && false === get_transient( $count_key ), 'un usuario con sesion no cuenta ni se banea' );
	wp_set_current_user( 0 );

		// Con la casilla apagada (valor por defecto) no se cuenta ni se bloquea
	eh_assert( false === $eh->get_default_settings()['recon_ip_ban'], 'el bloqueo de IP viene desactivado por defecto' );
	eh_set_options( $eh, array( 'security_event_log' => true ) );
	for ( $i = 1; $i <= 6; $i++ ) {
		$scan->invoke( $eh, $scanner_ip );
	}
	eh_assert( false === get_transient( $ban_key ) && false === get_transient( $count_key ), 'con la opcion apagada no se cuenta ni se bloquea' );
	eh_set_options( $eh, array( 'security_event_log' => true, 'recon_ip_ban' => true ) );

	// Umbral 0 = desactivado
	add_filter( 'essential_hardening_recon_ban_threshold', '__return_zero' );
	for ( $i = 1; $i <= 6; $i++ ) {
		$scan->invoke( $eh, $scanner_ip );
	}
	eh_assert( false === get_transient( $ban_key ), 'umbral 0 desactiva el bloqueo' );
		remove_all_filters( 'essential_hardening_recon_ban_threshold' );

	// -----------------------------------------------------------------------
	eh_section( 'País y bandera de las IP' );
	// -----------------------------------------------------------------------
	$geo       = $recon_ref->getMethod( 'get_ip_countries' );
	$label     = $recon_ref->getMethod( 'render_country_label' );
	$geo->setAccessible( true );
	$label->setAccessible( true );
	$geo_calls = 0;
	add_filter( 'essential_hardening_ip_country', function ( $country, $ip ) use ( &$geo_calls ) {
		$geo_calls++;
		return '201.223.127.28' === $ip ? array( 'code' => 'EC', 'name' => 'Ecuador' ) : array( 'code' => 'zz<', 'name' => 'x' );
	}, 10, 2 );

	eh_assert( false === $eh->get_default_settings()['show_ip_country'], 'el pais de las IP viene desactivado por defecto' );
	eh_set_options( $eh, array() );
	eh_assert( array() === $geo->invoke( $eh, array( '201.223.127.28' ) ) && 0 === $geo_calls, 'apagado: no resuelve ni consulta nada' );

	eh_set_options( $eh, array( 'show_ip_country' => true ) );
	$res = $geo->invoke( $eh, array( '201.223.127.28', '201.223.127.28', '127.0.0.1', '192.168.0.4', '8.8.8.8' ) );
	eh_assert( isset( $res['201.223.127.28'] ) && 'EC' === $res['201.223.127.28']['code'], 'resuelve el pais de una IP publica' );
	eh_assert( ! isset( $res['127.0.0.1'] ) && ! isset( $res['192.168.0.4'] ), 'las IP locales o privadas no se consultan' );
	eh_assert( ! isset( $res['8.8.8.8'] ), 'un codigo de pais invalido se descarta' );
	eh_assert( 2 === $geo_calls, 'una IP repetida se resuelve una sola vez' );

	$html = $label->invoke( $eh, array( 'code' => 'EC', 'name' => 'Ecuador<script>' ) );
	eh_assert( false !== strpos( $html, 'flagcdn.com/w20/ec.png' ) && false === strpos( $html, '<script>' ), 'la bandera usa flagcdn y escapa el nombre' );
	remove_all_filters( 'essential_hardening_ip_country' );

	// El panel muestra el Top 5 y la columna Pais solo si esta activado
	update_option( 'eh_security_events_log', array(
		array( 'timestamp' => time(), 'type' => 'Anti-Recon', 'ip' => '201.223.127.28', 'detail' => 'a' ),
		array( 'timestamp' => time(), 'type' => 'Anti-Recon', 'ip' => '201.223.127.28', 'detail' => 'b' ),
		array( 'timestamp' => time(), 'type' => 'Micro-WAF', 'ip' => '8.8.8.8', 'detail' => 'c' ),
	), false );
	add_filter( 'essential_hardening_ip_country', function () {
		return array( 'code' => 'EC', 'name' => 'Ecuador' );
	} );
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	wp_set_current_user( $admin_id );
	foreach ( array( false, true ) as $on ) {
		// El panel lee los ajustes guardados en la base de datos; el original se restaura al final.
		update_option( Essential_Hardening::OPTION_NAME, array_merge( $eh->get_default_settings(), array( 'show_ip_country' => $on ) ) );
		eh_set_options( $eh, array( 'show_ip_country' => $on ) );
		ob_start();
		$eh->render_admin_page();
		$panel = ob_get_clean();
		if ( $on ) {
			eh_assert( false !== strpos( $panel, 'Top 5 de IP bloqueadas' ) && false !== strpos( $panel, 'flagcdn.com/w20/ec.png' ), 'activado: el panel muestra el Top 5 y las banderas' );
		} else {
			eh_assert( false === strpos( $panel, 'Top 5 de IP bloqueadas' ) && false === strpos( $panel, 'flagcdn.com/w20/' ), 'apagado: el panel no muestra Top 5 ni banderas' );
		}
	}
	wp_set_current_user( 0 );
	remove_all_filters( 'essential_hardening_ip_country' );
	delete_transient( 'eh_geo_' . md5( '201.223.127.28' ) );

} finally {
	delete_transient( 'eh_recon_count_' . md5( '201.223.127.28' ) );
		delete_transient( 'eh_recon_ban_' . md5( '201.223.127.28' ) );
	remove_all_filters( 'essential_hardening_ip_country' );
	// Restaurar el estado del sitio local
	remove_all_filters( 'pre_wp_mail' );
	remove_all_filters( 'wp_redirect' );
	eh_clear_ip_state( $ip );
	delete_transient( 'eh_2fa_rate_' . $admin_id );
	delete_transient( 'eh_lostpw_' . md5( $ip ) );
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
