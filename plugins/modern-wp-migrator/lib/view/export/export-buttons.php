<?php
/**
 * Modern WP Migrator - Export Buttons View
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}
?>

<div class="ai1wm-field-set mwm-export-action-box">
	<div class="ai1wm-buttons">
		<div class="ai1wm-button-group ai1wm-button-export ai1wm-expandable mwm-export-btn-group">
			<div class="ai1wm-button-main mwm-btn-export-main">
				<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 6px;"></span>
				<span role="list" aria-label="<?php esc_attr_e( 'Exportar Sitio A', 'modern-wp-migrator' ); ?>">
					<?php esc_html_e( 'Exportar Sitio A...', 'modern-wp-migrator' ); ?>
				</span>
				<span class="ai1mw-lines">
					<span class="ai1wm-line ai1wm-line-first"></span>
					<span class="ai1wm-line ai1wm-line-second"></span>
					<span class="ai1wm-line ai1wm-line-third"></span>
				</span>
			</div>
			<ul class="ai1wm-dropdown-menu ai1wm-export-providers">
				<?php foreach ( apply_filters( 'ai1wm_export_buttons', array() ) as $button ) : ?>
					<li>
						<?php echo wp_kses( $button, ai1wm_allowed_html_tags() ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
