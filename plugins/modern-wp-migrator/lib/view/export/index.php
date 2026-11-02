<?php
/**
 * Modern WP Migrator - Export Site View
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}
?>

<div class="ai1wm-container">
	<?php require_once AI1WM_TEMPLATES_PATH . '/common/header-nav.php'; ?>

	<div class="ai1wm-row">
		<div class="ai1wm-left">
			<div class="ai1wm-holder">
				<div class="mwm-section-header">
					<h2>
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Exportar Sitio Web', 'modern-wp-migrator' ); ?>
					</h2>
					<p class="mwm-section-subtitle">
						<?php esc_html_e( 'Genera un paquete .wpress completo o personalizado con tu base de datos, temas, plugins y archivos multimedia para migrar a cualquier otro servidor.', 'modern-wp-migrator' ); ?>
					</p>
				</div>

				<?php if ( is_readable( AI1WM_STORAGE_PATH ) && is_writable( AI1WM_STORAGE_PATH ) ) : ?>

					<form action="" method="post" id="ai1wm-export-form" class="ai1wm-clear">

						<?php require_once AI1WM_TEMPLATES_PATH . '/export/find-replace.php'; ?>

						<?php do_action( 'ai1wm_export_left_options' ); ?>

						<?php require_once AI1WM_TEMPLATES_PATH . '/export/advanced-settings.php'; ?>

						<?php require_once AI1WM_TEMPLATES_PATH . '/export/export-buttons.php'; ?>

						<input type="hidden" name="ai1wm_manual_export" value="1" />

					</form>

					<?php do_action( 'ai1wm_export_left_end' ); ?>

				<?php else : ?>

					<?php require_once AI1WM_TEMPLATES_PATH . '/export/export-permissions.php'; ?>

				<?php endif; ?>
			</div>
		</div>

		<?php require_once AI1WM_TEMPLATES_PATH . '/common/sidebar-right.php'; ?>

	</div>
</div>
