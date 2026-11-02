<?php
/**
 * Modern WP Migrator - Import Buttons View
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}
?>

<div class="ai1wm-import-messages"></div>

<div class="ai1wm-import-form">
	<div class="hide-if-no-js">
		<div class="ai1wm-drag-drop-area mwm-dropzone" id="ai1wm-drag-drop-area">
			<div id="ai1wm-import-init">
				<div class="mwm-dropzone-illustration">
					<span class="dashicons dashicons-cloud-upload mwm-cloud-icon"></span>
				</div>
				<h3 class="mwm-dropzone-title">
					<?php esc_html_e( 'Arrastra y suelta tu archivo de respaldo (.wpress) aquí', 'modern-wp-migrator' ); ?>
				</h3>
				<p class="mwm-dropzone-sub">
					<?php esc_html_e( 'O usa el botón inferior para explorar tus carpetas locales', 'modern-wp-migrator' ); ?>
				</p>
				<div class="mwm-dropzone-badge">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Límite de subida: hasta 500 GB de una sola vez', 'modern-wp-migrator' ); ?>
				</div>

				<div class="ai1wm-button-group ai1wm-button-import ai1wm-expandable mwm-import-btn-group">
					<div class="ai1wm-button-main mwm-btn-main">
						<span class="dashicons dashicons-open-folder" style="vertical-align: middle; margin-right: 6px;"></span>
						<span role="list" aria-label="<?php esc_attr_e( 'Importar Desde', 'modern-wp-migrator' ); ?>">
							<?php esc_html_e( 'Seleccionar Origen del Archivo', 'modern-wp-migrator' ); ?>
						</span>
						<span class="ai1mw-lines">
							<span class="ai1wm-line ai1wm-line-first"></span>
							<span class="ai1wm-line ai1wm-line-second"></span>
							<span class="ai1wm-line ai1wm-line-third"></span>
						</span>
					</div>
					<ul class="ai1wm-dropdown-menu ai1wm-import-providers">
						<?php foreach ( apply_filters( 'ai1wm_import_buttons', array() ) as $button ) : ?>
							<li>
								<?php echo wp_kses( $button, ai1wm_allowed_html_tags() ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<p style="margin: 0;"><?php echo wp_kses( apply_filters( 'ai1wm_pro', '' ), ai1wm_allowed_html_tags() ); ?></p>
