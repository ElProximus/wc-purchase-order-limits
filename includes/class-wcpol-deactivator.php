<?php
/**
 * Fired during plugin deactivation
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Deactivator {

	/**
	 * Plugin deactivation tasks.
	 *
	 * Clear scheduled hooks and flush rewrite rules.
	 */
	public static function deactivate() {
		// Clear any scheduled hooks
		wp_clear_scheduled_hook( 'wcpol_cleanup_expired_pos' );
		wp_clear_scheduled_hook( 'wcpol_send_reminder_emails' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
