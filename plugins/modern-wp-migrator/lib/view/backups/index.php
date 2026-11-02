<?php
/**
 * Modern WP Migrator - Backups View
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
						<span class="dashicons dashicons-backup"></span>
						<?php esc_html_e( 'Copias de Seguridad Disponibles', 'modern-wp-migrator' ); ?>
					</h2>
					<p class="mwm-section-subtitle">
						<?php esc_html_e( 'Gestiona, descarga o restaura con 1 solo clic tus respaldos locales almacenados en el servidor.', 'modern-wp-migrator' ); ?>
					</p>
				</div>

				<?php if ( is_readable( AI1WM_BACKUPS_PATH ) && is_writable( AI1WM_BACKUPS_PATH ) ) : ?>
					<div id="ai1wm-backups-list">
						<?php require_once AI1WM_TEMPLATES_PATH . '/backups/backups-list.php'; ?>
					</div>

					<form action="" method="post" id="ai1wm-export-form" class="ai1wm-clear">
						<div id="ai1wm-backups-create">
							<p class="ai1wm-backups-empty-spinner-holder ai1wm-hide">
								<span class="spinner"></span>
								<?php esc_html_e( 'Actualizando lista de respaldos...', 'modern-wp-migrator' ); ?>
							</p>
							<p class="ai1wm-backups-empty <?php echo empty( $backups ) ? null : 'ai1wm-hide'; ?>">
								<?php esc_html_e( 'No hay copias de seguridad aún. ¿Deseas generar la primera ahora mismo?', 'modern-wp-migrator' ); ?>
							</p>
							<p>
								<a href="#" id="ai1wm-create-backup" class="ai1wm-button-green mwm-create-backup-btn">
									<span class="dashicons dashicons-plus-alt"></span>
									<?php esc_html_e( 'Crear Nueva Copia de Seguridad', 'modern-wp-migrator' ); ?>
								</a>
							</p>
						</div>
						<input type="hidden" name="ai1wm_manual_export" value="1" />
					</form>

					<?php do_action( 'ai1wm_backups_left_end' ); ?>

				<?php else : ?>

					<?php require_once AI1WM_TEMPLATES_PATH . '/backups/backups-permissions.php'; ?>

				<?php endif; ?>
			</div>

			<div id="ai1wm-backups-list-archive-browser">
				<archive-browser></archive-browser>
			</div>

		</div>

		<?php require_once AI1WM_TEMPLATES_PATH . '/common/sidebar-right.php'; ?>

	</div>
</div>
