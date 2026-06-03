<?php
/**
 * Fired during plugin activation
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Activator {

	/**
	 * Plugin activation tasks.
	 *
	 * Create database tables and set default options.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();

		// Clear the permalinks to ensure vendor dashboard routes work
		flush_rewrite_rules( true );
	}

	/**
	 * Create plugin database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Purchase Orders table
		$table_purchase_orders = $wpdb->prefix . 'wcpol_purchase_orders';
		$sql_purchase_orders   = "CREATE TABLE IF NOT EXISTS $table_purchase_orders (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            po_number varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            total_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            approved_date datetime DEFAULT NULL,
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            vendor_created tinyint(1) DEFAULT 0,
            pdf_url text DEFAULT NULL,
            notes text DEFAULT NULL,
            reference varchar(255) DEFAULT NULL,
            required_date date DEFAULT NULL,
            validity_days int DEFAULT 30,
            expiry_date date DEFAULT NULL,
            vendor_count int DEFAULT 0,
            vendors_issued int DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            shipping_address text DEFAULT NULL,
            billing_address text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY po_number (po_number),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_date (created_date),
            KEY user_status (user_id, status),
            KEY status_created (status, created_date)
        ) $charset_collate;";

		// PO Items table
		$table_po_items = $wpdb->prefix . 'wcpol_po_items';
		$sql_po_items   = "CREATE TABLE IF NOT EXISTS $table_po_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            po_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            quantity int(11) NOT NULL DEFAULT '1',
            price decimal(10,2) NOT NULL DEFAULT '0.00',
            total decimal(10,2) NOT NULL DEFAULT '0.00',
            status varchar(20) NOT NULL DEFAULT 'pending',
            vendor_notes text DEFAULT NULL,
            vendor_price decimal(10,2) DEFAULT NULL,
            vendor_approved datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY po_id (po_id),
            KEY product_id (product_id),
            KEY vendor_id (vendor_id),
            KEY status (status),
            KEY vendor_status (vendor_id, status),
            KEY po_vendor (po_id, vendor_id)
        ) $charset_collate;";

		// PO History table
		$table_po_history = $wpdb->prefix . 'wcpol_po_history';
		$sql_po_history   = "CREATE TABLE IF NOT EXISTS $table_po_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            po_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(50) NOT NULL,
            notes text DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY po_id (po_id),
            KEY user_id (user_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";

		// User Limits table
		$table_user_limits = $wpdb->prefix . 'wcpol_user_limits';
		$sql_user_limits   = "CREATE TABLE IF NOT EXISTS $table_user_limits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            user_role varchar(50) DEFAULT NULL,
            limit_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            limit_type varchar(20) NOT NULL DEFAULT 'total',
            priority int(11) NOT NULL DEFAULT '0',
            active tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_role (user_role),
            KEY active (active),
            KEY user_active (user_id, active),
            KEY role_active (user_role, active)
        ) $charset_collate;";

		// Vendor Shipping table.
		$table_vendor_shipping = $wpdb->prefix . 'wcpol_vendor_shipping';
		$sql_vendor_shipping   = "CREATE TABLE IF NOT EXISTS $table_vendor_shipping (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            po_id bigint(20) UNSIGNED NOT NULL,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            shipping_amount decimal(10,2) NOT NULL DEFAULT '0.00',
            shipping_notes text DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY po_vendor (po_id, vendor_id),
            KEY po_id (po_id),
            KEY vendor_id (vendor_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_purchase_orders );
		dbDelta( $sql_po_items );
		dbDelta( $sql_po_history );
		dbDelta( $sql_user_limits );
		dbDelta( $sql_vendor_shipping );

		// Add missing columns if they don't exist (for upgrades)
		self::add_missing_columns();

		// Store database version for future upgrades
		update_option( 'wcpol_db_version', WCPOL_VERSION );
	}

	/**
	 * Add missing columns to existing tables
	 */
	private static function add_missing_columns() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcpol_purchase_orders';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.

		// Check if reference column exists.
		$reference_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'reference'" );
		if ( empty( $reference_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `reference` varchar(255) DEFAULT NULL" );
		}

		// Check if required_date column exists.
		$required_date_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'required_date'" );
		if ( empty( $required_date_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `required_date` date DEFAULT NULL" );
		}

		// Check if vendor_created column exists.
		$vendor_created_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'vendor_created'" );
		if ( empty( $vendor_created_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `vendor_created` tinyint(1) DEFAULT 0" );
		}

		// Check if validity_days column exists.
		$validity_days_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'validity_days'" );
		if ( empty( $validity_days_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `validity_days` int DEFAULT 30" );
		}

		// Check if expiry_date column exists.
		$expiry_date_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'expiry_date'" );
		if ( empty( $expiry_date_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `expiry_date` date DEFAULT NULL" );
		}

		// Check if vendor_count column exists.
		$vendor_count_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'vendor_count'" );
		if ( empty( $vendor_count_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `vendor_count` int DEFAULT 0" );
		}

		// Check if vendors_issued column exists.
		$vendors_issued_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'vendors_issued'" );
		if ( empty( $vendors_issued_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `vendors_issued` int DEFAULT 0" );
		}

		// Check if order_id column exists.
		$order_id_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'order_id'" );
		if ( empty( $order_id_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `order_id` bigint(20) UNSIGNED DEFAULT NULL" );
		}

		// Check if shipping_address column exists.
		$shipping_address_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'shipping_address'" );
		if ( empty( $shipping_address_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `shipping_address` text DEFAULT NULL" );
		}

		// Check if billing_address column exists.
		$billing_address_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'billing_address'" );
		if ( empty( $billing_address_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `billing_address` text DEFAULT NULL" );
		}

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Set default plugin options
	 */
	private static function set_default_options() {
		// General settings
		add_option( 'wcpol_enable_limits', 'yes' );
		add_option( 'wcpol_global_limit', '5000' );
		add_option( 'wcpol_limit_type', 'total' ); // 'subtotal' or 'total'
		add_option( 'wcpol_po_prefix', 'PO-' );
		add_option( 'wcpol_po_starting_number', '1000' );

		// Email settings
		add_option( 'wcpol_enable_customer_emails', 'yes' );
		add_option( 'wcpol_enable_vendor_emails', 'yes' );
		add_option( 'wcpol_enable_admin_emails', 'yes' );

		// BuddyBoss notifications
		add_option( 'wcpol_enable_bb_notifications', 'yes' );

		// PDF settings
		add_option( 'wcpol_pdf_logo', '' );
		add_option( 'wcpol_pdf_company_name', get_bloginfo( 'name' ) );
		add_option( 'wcpol_pdf_company_address', '' );
		add_option( 'wcpol_pdf_terms', '' );

		// Vendor settings
		add_option( 'wcpol_vendor_can_edit_price', 'yes' );
		add_option( 'wcpol_vendor_approval_required', 'yes' );
		add_option( 'wcpol_auto_approve_under', '1000' );
	}
}
