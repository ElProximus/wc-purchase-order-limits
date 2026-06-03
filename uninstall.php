<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    WC_Purchase_Order_Limits
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only remove data if the option to delete data on uninstall is set
$delete_data = get_option( 'wcpol_delete_data_on_uninstall', 'no' );

if ( 'yes' === $delete_data ) {
	global $wpdb;

	// Drop custom tables
	$tables = array(
		$wpdb->prefix . 'wcpol_purchase_orders',
		$wpdb->prefix . 'wcpol_po_items',
		$wpdb->prefix . 'wcpol_po_history',
		$wpdb->prefix . 'wcpol_user_limits',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}

	// Delete options
	$options = array(
		'wcpol_db_version',
		'wcpol_enable_limits',
		'wcpol_global_limit',
		'wcpol_limit_type',
		'wcpol_po_prefix',
		'wcpol_po_starting_number',
		'wcpol_last_po_number',
		'wcpol_enable_customer_emails',
		'wcpol_enable_vendor_emails',
		'wcpol_enable_admin_emails',
		'wcpol_enable_bb_notifications',
		'wcpol_pdf_logo',
		'wcpol_pdf_company_name',
		'wcpol_pdf_company_address',
		'wcpol_pdf_terms',
		'wcpol_vendor_can_edit_price',
		'wcpol_vendor_approval_required',
		'wcpol_auto_approve_under',
		'wcpol_delete_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete uploaded PDFs
	$upload_dir = wp_upload_dir();
	$po_dir     = $upload_dir['basedir'] . '/wcpol-purchase-orders';

	if ( is_dir( $po_dir ) ) {
		// Delete all files in the directory
		$files = glob( $po_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		// Remove the directory
		rmdir( $po_dir );
	}

	// Clear any scheduled hooks
	wp_clear_scheduled_hook( 'wcpol_cleanup_expired_pos' );
	wp_clear_scheduled_hook( 'wcpol_send_reminder_emails' );

	// Remove capabilities (if any were added)
	$roles = array( 'administrator', 'shop_manager' );
	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->remove_cap( 'manage_purchase_orders' );
		}
	}
}
