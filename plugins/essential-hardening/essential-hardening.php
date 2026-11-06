<?php
/**
 * Plugin Name:       Essential Hardening & Security
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Suite ligera, modular y de rendimiento cero para hardening en WordPress. Cortafuegos Micro-WAF, mitigación de fuerza bruta, protección de uploads, bloqueo de enumeración de usuarios, desactivación total de comentarios y spam, cabeceras HTTP defensivas y anonimización de huellas del CMS.
 * Version:           1.8.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Web Security Tools
 * License:           GPL-2.0-or-later
 * Text Domain:       essential-hardening
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ESSENTIAL_HARDENING_FILE' ) ) {
	define( 'ESSENTIAL_HARDENING_FILE', __FILE__ );
}

// Módulos del plugin: cada uno vive en su propio archivo dentro de includes/.
require_once __DIR__ . '/includes/trait-support.php';
require_once __DIR__ . '/includes/trait-updater.php';
require_once __DIR__ . '/includes/trait-comments.php';
require_once __DIR__ . '/includes/trait-privacy.php';
require_once __DIR__ . '/includes/trait-headers.php';
require_once __DIR__ . '/includes/trait-firewall.php';
require_once __DIR__ . '/includes/trait-login.php';
require_once __DIR__ . '/includes/trait-two-factor.php';
require_once __DIR__ . '/includes/trait-accounts.php';
require_once __DIR__ . '/includes/trait-uploads.php';
require_once __DIR__ . '/includes/trait-notifications.php';
require_once __DIR__ . '/includes/trait-integrity.php';
require_once __DIR__ . '/includes/trait-admin.php';

final class Essential_Hardening {

	use Essential_Hardening_Support,
		Essential_Hardening_Updater,
		Essential_Hardening_Comments,
		Essential_Hardening_Privacy,
		Essential_Hardening_Headers,
		Essential_Hardening_Firewall,
		Essential_Hardening_Login,
		Essential_Hardening_Two_Factor,
		Essential_Hardening_Accounts,
		Essential_Hardening_Uploads,
		Essential_Hardening_Notifications,
		Essential_Hardening_Integrity,
		Essential_Hardening_Admin;

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.8.0';

	/**
	 * Máximo número de eventos de seguridad registrados en el mini-log.
	 */
	public const MAX_EVENT_LOG_ENTRIES = 25;

	/**
	 * Clave de opciones en la base de datos.
	 */
	public const OPTION_NAME = 'essential_hardening_settings';

	/**
	 * Máximo de archivos que se listan por categoría (correo y panel).
	 */
	public const MAX_INTEGRITY_FILES_LISTED = 25;

	/**
	 * Instancia Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Opciones actuales cargadas.
	 *
	 * @var array<string, bool>
	 */
	private $options = array();

	/**
	 * Limpieza al desactivar el plugin: cron programado y bloque de .htaccess de uploads.
	 */
	public static function on_deactivation(): void {
		wp_clear_scheduled_hook( 'eh_daily_update_check_cron' );
		wp_clear_scheduled_hook( 'eh_daily_core_integrity_cron' );

		$upload_dir = wp_upload_dir();
		$htaccess   = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) . '.htaccess' : '';
		if ( $htaccess && file_exists( $htaccess ) && is_writable( $htaccess ) ) {
			$content = (string) file_get_contents( $htaccess );
			$pattern = '/\R*# BEGIN Essential Hardening Uploads Protection.*?# END Essential Hardening Uploads Protection\R?/s';
			$cleaned = preg_replace( $pattern, "\n", $content, 1 );
			if ( null !== $cleaned && $cleaned !== $content ) {
				if ( '' === trim( $cleaned ) ) {
					unlink( $htaccess );
				} else {
					file_put_contents( $htaccess, ltrim( $cleaned ) );
				}
			}
		}
	}

	/**
	 * Obtener instancia singleton.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		$this->options = $this->get_settings();
		$this->init_modules();
		$this->init_update_checker();

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( ESSENTIAL_HARDENING_FILE ), array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Opciones por defecto (todos los escudos activos al instalar).
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return array(
			// Módulos Core (v1.0.0)
			'disable_comments'        => true,
			'block_user_enumeration'  => true,
			'security_headers'        => true,
			'hide_wp_version'         => true,
			'disable_xmlrpc'          => true,
			'generic_login_errors'    => true,
			'disallow_file_edit'      => true,
			// Módulos de Protección Avanzada (v1.1.0)
			'query_firewall'          => true,
			'login_throttle'          => true,
			'protect_uploads'         => true,
			'clean_head_meta'         => true,
			'hide_author_body_class'  => true,
			// Módulos v1.2.0 (Anti-Bot y Rate Limiting Configurable)
			'login_honeypot'            => true,
			'throttle_max_attempts'     => 5,
			'throttle_lockout_duration' => 900,
			// Módulos v1.3.0 (Notificaciones de Actualizaciones)
			'update_notifications'      => true,
			'update_notify_email'       => '',
			// Módulos v1.4.0 (Alertas de Admin, Registro en Vivo y 2FA)
			'admin_login_alerts'        => true,
			'security_event_log'        => true,
			'email_2fa_enabled'         => false,
			// Módulos v1.5.0 (HSTS y CSP en modo solo-reporte). Desactivados: dependen del sitio.
			'hsts_enabled'              => false,
			'hsts_max_age'              => 2592000,
			'csp_report_only'           => false,
			// Módulos v1.6.0 (cambios de administradores e integridad del núcleo)
			'admin_change_alerts'       => true,
			'core_integrity_check'      => false,
			// Módulos v1.7.0 (protección de cuentas)
			'lostpassword_protection'   => true,
			'disable_application_passwords' => false,
			'strong_admin_passwords'    => false,
			// Módulos v1.8.0 (bloqueo de IP escaneadora y país de las IP). Desactivados: dependen del sitio.
			'recon_ip_ban'              => false,
			'show_ip_country'           => false,
		);
	}

	/**
	 * Obtener configuración activa fusionando con valores por defecto.
	 *
	 * @return array<string, bool>
	 */
	public function get_settings(): array {
		$defaults = $this->get_default_settings();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Inicializar los módulos de seguridad según las opciones activas.
	 */
	private function init_modules(): void {
		// 1. Desactivación de comentarios y pings
		if ( ! empty( $this->options['disable_comments'] ) ) {
			$this->module_disable_comments();
		}

		// 2. Anti-enumeración de usuarios
		if ( ! empty( $this->options['block_user_enumeration'] ) ) {
			$this->module_block_user_enumeration();
		}

		// 3. Cabeceras HTTP de seguridad
		if ( ! empty( $this->options['security_headers'] ) ) {
			$this->module_security_headers();
		}

		// 4. Ocultar versión de WordPress y huellas
		if ( ! empty( $this->options['hide_wp_version'] ) ) {
			$this->module_hide_wp_version();
		}

		// 5. Desactivar XML-RPC y X-Pingback
		if ( ! empty( $this->options['disable_xmlrpc'] ) ) {
			$this->module_disable_xmlrpc();
		}

		// 6. Mensaje de login genérico
		if ( ! empty( $this->options['generic_login_errors'] ) ) {
			add_filter( 'login_errors', static function (): string {
				return __( 'Usuario o contraseña incorrectos.', 'essential-hardening' );
			} );
		}

		// 7. Desactivar editor de temas y plugins desde el panel
		if ( ! empty( $this->options['disallow_file_edit'] ) ) {
			if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
		}

		// 8. Micro-WAF / Cortafuegos de Query Strings
		if ( ! empty( $this->options['query_firewall'] ) ) {
			$this->module_query_firewall();
		}

		// 9. Mitigación de fuerza bruta / Login Throttle
		if ( ! empty( $this->options['login_throttle'] ) ) {
			$this->module_login_throttle();
		}

		// 10. Protección de directorio uploads
		if ( ! empty( $this->options['protect_uploads'] ) ) {
			$this->module_protect_uploads();
		}

		// 11. Limpieza de metadatos obsoletos en <head>
		if ( ! empty( $this->options['clean_head_meta'] ) ) {
			$this->module_clean_head_meta();
		}

		// 12. Ocultar logins de usuario en body_class
		if ( ! empty( $this->options['hide_author_body_class'] ) ) {
			$this->module_hide_author_body_class();
		}

		// 13. Honeypot Anti-Bot en pantalla de login (v1.2.0)
		if ( ! empty( $this->options['login_honeypot'] ) ) {
			$this->module_login_honeypot();
		}

		// 14. Alertas de Actualizaciones Pendientes y Completadas (v1.3.0)
		if ( ! empty( $this->options['update_notifications'] ) ) {
			$this->module_update_notifications();
		}

		// 15. Alertas de Inicio de Sesión de Administradores (v1.4.0)
		if ( ! empty( $this->options['admin_login_alerts'] ) ) {
			$this->module_admin_login_alerts();
		}

		// 16. Doble Factor de Autenticación por Correo (v1.4.0)
		if ( ! empty( $this->options['email_2fa_enabled'] ) ) {
			$this->module_email_2fa();
		}

		// 17. HSTS y CSP en modo solo-reporte (v1.5.0)
		if ( ! empty( $this->options['hsts_enabled'] ) || ! empty( $this->options['csp_report_only'] ) ) {
			$this->module_transport_headers();
		}

		// 18. Alertas de administradores nuevos o con rol elevado (v1.6.0)
		if ( ! empty( $this->options['admin_change_alerts'] ) ) {
			$this->module_admin_change_alerts();
		}

		// 19. Verificación de integridad de los archivos del núcleo (v1.6.0)
		if ( ! empty( $this->options['core_integrity_check'] ) ) {
			$this->module_core_integrity();
		} elseif ( wp_next_scheduled( 'eh_daily_core_integrity_cron' ) ) {
			wp_clear_scheduled_hook( 'eh_daily_core_integrity_cron' );
		}

		// 20. Protección de la recuperación de contraseña (v1.7.0)
		if ( ! empty( $this->options['lostpassword_protection'] ) ) {
			$this->module_lostpassword_protection();
		}

		// 21. Desactivar contraseñas de aplicación (v1.7.0)
		if ( ! empty( $this->options['disable_application_passwords'] ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}

		// 22. Contraseñas robustas para administradores (v1.7.0)
		if ( ! empty( $this->options['strong_admin_passwords'] ) ) {
			$this->module_strong_admin_passwords();
		}

		// Manejador para vaciar el mini-log de seguridad desde el panel
		if ( is_admin() ) {
			add_action( 'admin_post_eh_clear_security_log', array( $this, 'handle_clear_security_log' ) );
		}
	}

}

// Iniciar plugin
add_action( 'plugins_loaded', array( 'Essential_Hardening', 'get_instance' ) );

// Al desactivar: quitar el cron y las reglas de .htaccess para no dejar residuos
register_deactivation_hook( ESSENTIAL_HARDENING_FILE, array( 'Essential_Hardening', 'on_deactivation' ) );
