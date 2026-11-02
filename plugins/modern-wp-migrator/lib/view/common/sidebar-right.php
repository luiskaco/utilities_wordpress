<?php
/**
 * Modern WP Migrator - System Status Sidebar
 *
 * Attribution: Forked under GNU General Public License (GPLv3).
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

$free_space = function_exists( 'disk_free_space' ) && @disk_free_space( AI1WM_STORAGE_PATH ) ? size_format( disk_free_space( AI1WM_STORAGE_PATH ), 1 ) : null;
?>

<div class="ai1wm-right">
	<div class="ai1wm-sidebar">
		<div class="mwm-status-card">
			<div class="mwm-card-header">
				<span class="dashicons dashicons-dashboard mwm-icon-header"></span>
				<h2 class="mwm-card-title"><?php esc_html_e( 'Diagnóstico del Servidor', 'modern-wp-migrator' ); ?></h2>
			</div>

			<ul class="mwm-diag-list">
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Límite de Subida:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value mwm-text-success">
						<strong>500 GB</strong> (Ilimitado)
					</span>
				</li>
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Restauración Directa:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value mwm-text-success">
						<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Habilitada', 'modern-wp-migrator' ); ?>
					</span>
				</li>
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Motor PHP:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value">PHP <?php echo esc_html( PHP_VERSION ); ?></span>
				</li>
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Límite de Memoria:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></span>
				</li>
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Tiempo de Ejecución:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value"><?php echo esc_html( ini_get( 'max_execution_time' ) . 's' ); ?></span>
				</li>
				<?php if ( $free_space ) : ?>
				<li class="mwm-diag-item">
					<span class="mwm-diag-label"><?php esc_html_e( 'Espacio en Disco:', 'modern-wp-migrator' ); ?></span>
					<span class="mwm-diag-value mwm-text-info">~<?php echo esc_html( $free_space ); ?> <?php esc_html_e( 'libre', 'modern-wp-migrator' ); ?></span>
				</li>
				<?php endif; ?>
			</ul>

			<div class="mwm-storage-box">
				<span class="mwm-storage-title">
					<span class="dashicons dashicons-category"></span>
					<?php esc_html_e( 'Directorio de Almacenamiento:', 'modern-wp-migrator' ); ?>
				</span>
				<code class="mwm-storage-path"><?php echo esc_html( AI1WM_STORAGE_PATH ); ?></code>
			</div>

			<div class="mwm-info-box">
				<span class="dashicons dashicons-info"></span>
				<p>
					<?php esc_html_e( 'Los respaldos se almacenan localmente y quedan protegidos contra descargas no autorizadas mediante reglas .htaccess y web.config.', 'modern-wp-migrator' ); ?>
				</p>
			</div>

			<?php do_action( 'ai1wm_sidebar_right_end' ); ?>
		</div>
	</div>
</div>
