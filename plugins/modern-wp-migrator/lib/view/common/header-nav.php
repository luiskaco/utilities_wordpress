<?php
/**
 * Modern WP Migrator - Top Navigation Header
 *
 * Attribution: Part of Modern WP Migrator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

$current_page  = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'ai1wm_export';
$backups_count = class_exists( 'Ai1wm_Backups' ) ? Ai1wm_Backups::count_files() : 0;
?>

<div class="mwm-header-wrap">
	<div class="mwm-header-banner">
		<div class="mwm-brand">
			<div class="mwm-brand-icon">
				<span class="dashicons dashicons-cloud"></span>
			</div>
			<div>
				<h1 class="mwm-brand-title">
					Site Migrator
					<span class="mwm-version-pill">v<?php echo esc_html( AI1WM_VERSION ); ?></span>
				</h1>
				<p class="mwm-brand-desc">
					<?php esc_html_e( 'Motor de migración completo, copias de seguridad directas y restauración sin límites de subida.', 'modern-wp-migrator' ); ?>
				</p>
			</div>
		</div>

		<div class="mwm-status-badges">
			<span class="mwm-badge mwm-badge-unlocked">
				<span class="dashicons dashicons-yes-alt"></span>
				<strong>500 GB</strong> <?php esc_html_e( 'Desbloqueado', 'modern-wp-migrator' ); ?>
			</span>
			<span class="mwm-badge mwm-badge-php">
				PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?>
			</span>
		</div>
	</div>

	<nav class="nav-tab-wrapper mwm-tabs-nav" aria-label="<?php esc_attr_e( 'Navegación del migrador', 'modern-wp-migrator' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai1wm_export' ) ); ?>" class="nav-tab <?php echo ( 'ai1wm_export' === $current_page ) ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-upload"></span>
			<?php esc_html_e( 'Exportar Sitio', 'modern-wp-migrator' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai1wm_import' ) ); ?>" class="nav-tab <?php echo ( 'ai1wm_import' === $current_page ) ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Importar Sitio', 'modern-wp-migrator' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai1wm_backups' ) ); ?>" class="nav-tab <?php echo ( 'ai1wm_backups' === $current_page ) ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-backup"></span>
			<?php esc_html_e( 'Copias de Seguridad', 'modern-wp-migrator' ); ?>
			<?php if ( $backups_count > 0 ) : ?>
				<span class="mwm-count-badge"><?php echo esc_html( (string) $backups_count ); ?></span>
			<?php endif; ?>
		</a>
	</nav>
</div>
