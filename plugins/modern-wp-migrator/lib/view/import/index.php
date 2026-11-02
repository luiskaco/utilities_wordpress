<?php
/**
 * Modern WP Migrator - Import Site View
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
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Importar Sitio Web', 'modern-wp-migrator' ); ?>
					</h2>
					<p class="mwm-section-subtitle">
						<?php esc_html_e( 'Restaura una copia de seguridad en formato .wpress. Soporta subidas directas de hasta 500 GB sin fragmentación.', 'modern-wp-migrator' ); ?>
					</p>
				</div>

				<?php if ( is_readable( AI1WM_STORAGE_PATH ) && is_writable( AI1WM_STORAGE_PATH ) ) : ?>

					<form action="" method="post" id="ai1wm-import-form" class="ai1wm-clear" enctype="multipart/form-data">

						<?php do_action( 'ai1wm_import_left_options' ); ?>

						<?php require_once AI1WM_TEMPLATES_PATH . '/import/import-buttons.php'; ?>

						<input type="hidden" name="ai1wm_manual_import" value="1" />

					</form>

					<?php do_action( 'ai1wm_import_left_end' ); ?>

				<?php else : ?>

					<?php require_once AI1WM_TEMPLATES_PATH . '/import/import-permissions.php'; ?>

				<?php endif; ?>
			</div>
		</div>

		<?php require_once AI1WM_TEMPLATES_PATH . '/common/sidebar-right.php'; ?>

	</div>
</div>
