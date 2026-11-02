<?php
/**
 * Modern WP Migrator - System Status Sidebar
 *
 * Attribution: Forked under GNU General Public License (GPLv3).
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}
?>

<div class="ai1wm-right">
	<div class="ai1wm-sidebar">
		<div class="ai1wm-segment" style="padding: 18px; background: #fff; border: 1px solid #ccd0d4; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.05);">

			<h2 style="margin: 0 0 12px 0; padding-bottom: 8px; border-bottom: 1px solid #e2e4e7; font-size: 15px; font-weight: 600; color: #1d2327;">
				<span class="dashicons dashicons-admin-settings" style="vertical-align: middle; margin-right: 6px; color: #2271b1;"></span>
				<?php esc_html_e( 'System Status', 'modern-wp-migrator' ); ?>
			</h2>

			<table style="width: 100%; font-size: 13px; line-height: 2; color: #3c434a; border-collapse: collapse;">
				<tr>
					<td><strong>Upload Limit:</strong></td>
					<td style="text-align: right;"><span style="color: #008a20; font-weight: 600;">500 GB (Unlimited)</span></td>
				</tr>
				<tr>
					<td><strong>Direct Restore:</strong></td>
					<td style="text-align: right;"><span style="color: #008a20; font-weight: 600;">Enabled</span></td>
				</tr>
				<tr>
					<td><strong>PHP Engine:</strong></td>
					<td style="text-align: right;"><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<td><strong>Memory Limit:</strong></td>
					<td style="text-align: right;"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Max Execution:</strong></td>
					<td style="text-align: right;"><?php echo esc_html( ini_get( 'max_execution_time' ) . 's' ); ?></td>
				</tr>
			</table>

			<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #e2e4e7; font-size: 12px; color: #646970;">
				<p style="margin: 0 0 6px 0; font-weight: 600; color: #1d2327;">Backup Storage Directory:</p>
				<code style="display: block; word-break: break-all; padding: 6px 8px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; font-size: 11px; color: #2c3338;">
					<?php echo esc_html( AI1WM_STORAGE_PATH ); ?>
				</code>
			</div>

			<div style="margin-top: 14px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 2px; font-size: 12px; color: #1d2327; line-height: 1.4;">
				<strong>Modern WP Migrator</strong><br>
				Independent migration and backup utility optimized for modern PHP 8.x environments.
			</div>

			<?php do_action( 'ai1wm_sidebar_right_end' ); ?>

		</div>
	</div>
</div>
