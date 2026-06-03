<?php
/**
 * Vendor Utility Class - Centralized vendor functionality
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 * @since      1.1.0
 */

class WCPOL_Vendor_Utility {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if user is a vendor (compatible with LS Vendors and WC Vendors)
	 *
	 * @param int|null $user_id User ID to check, defaults to current user
	 * @return bool True if user is a vendor
	 */
	public function is_vendor( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Try LSV_Vendors class first (LS Vendors)
		if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'is_vendor' ) ) {
			return LSV_Vendors::is_vendor( $user_id );
		}

		// Try WCV_Vendors class (WC Vendors or LS Vendors backwards compat alias)
		if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'is_vendor' ) ) {
			return WCV_Vendors::is_vendor( $user_id );
		}

		// Try WC_Vendors class (older WC Vendors versions)
		if ( class_exists( 'WC_Vendors' ) && method_exists( 'WC_Vendors', 'is_vendor' ) ) {
			return WC_Vendors::is_vendor( $user_id );
		}

		// Fallback to checking user roles (includes LS Vendors tiers)
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$vendor_roles = array( 'vendor', 'digital_vendor', 'community_vendor', 'pending_vendor' );
			return ! empty( array_intersect( $vendor_roles, $user->roles ) );
		}

		return false;
	}

	/**
	 * Get vendor shop name
	 *
	 * @param int $vendor_id Vendor user ID
	 * @return string Shop name or display name
	 */
	public function get_vendor_shop_name( $vendor_id ) {
		// Try LSV_Vendors method first (LS Vendors)
		if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
			return LSV_Vendors::get_vendor_shop_name( $vendor_id );
		}

		// Try WCV_Vendors method (WC Vendors or LS Vendors backwards compat alias)
		if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
			return WCV_Vendors::get_vendor_shop_name( $vendor_id );
		}

		// Try WC_Vendors method (older WC Vendors)
		if ( class_exists( 'WC_Vendors' ) && method_exists( 'WC_Vendors', 'get_vendor_shop_name' ) ) {
			return WC_Vendors::get_vendor_shop_name( $vendor_id );
		}

		// Fallback to user display name
		$user = get_user_by( 'id', $vendor_id );
		return $user ? $user->display_name : __( 'Unknown Vendor', 'wc-purchase-order-limits' );
	}

	/**
	 * Get vendor dashboard URL
	 *
	 * @param string $page Optional page slug
	 * @return string Dashboard URL
	 */
	public function get_vendor_dashboard_url( $page = '' ) {
		// Check if WCVendors_Pro_Dashboard class exists
		if ( class_exists( 'WCVendors_Pro_Dashboard' ) && method_exists( 'WCVendors_Pro_Dashboard', 'get_dashboard_page_url' ) ) {
			return WCVendors_Pro_Dashboard::get_dashboard_page_url( $page );
		}

		// Try LS Vendors dashboard page first
		$dashboard_page_id = get_option( 'lsv_vendor_dashboard_page_id' );

		// Fallback to WC Vendors option
		if ( ! $dashboard_page_id ) {
			$dashboard_page_id = get_option( 'wcvendors_dashboard_page_id' );
		}

		if ( is_array( $dashboard_page_id ) ) {
			$dashboard_page_id = reset( $dashboard_page_id );
		}

		if ( $dashboard_page_id ) {
			$url = get_permalink( $dashboard_page_id );
			if ( $page ) {
				$url = trailingslashit( $url ) . $page;
			}
			return $url;
		}

		return home_url();
	}

	/**
	 * Check if vendor can manage purchase order
	 *
	 * @param int $vendor_id Vendor user ID
	 * @param int $po_id Purchase order ID
	 * @return bool True if vendor can manage this PO
	 */
	public function vendor_can_manage_po( $vendor_id, $po_id ) {
		global $wpdb;

		if ( ! $this->is_vendor( $vendor_id ) ) {
			return false;
		}

		// Check if vendor has items in this PO
		$has_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_po_items 
             WHERE po_id = %d AND vendor_id = %d",
				$po_id,
				$vendor_id
			)
		);

		return $has_items > 0;
	}

	/**
	 * Get vendor's pending purchase orders count
	 *
	 * @param int $vendor_id Vendor user ID
	 * @return int Count of pending POs
	 */
	public function get_vendor_pending_pos_count( $vendor_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT po.id) 
             FROM {$wpdb->prefix}wcpol_purchase_orders po
             INNER JOIN {$wpdb->prefix}wcpol_po_items poi ON po.id = poi.po_id
             WHERE poi.vendor_id = %d 
             AND poi.status = 'pending'
             AND po.status != 'trashed'",
				$vendor_id
			)
		);
	}

	/**
	 * Get vendor commission rate
	 *
	 * @param int $vendor_id Vendor user ID
	 * @return float Commission rate as decimal (0.15 for 15%)
	 */
	public function get_vendor_commission_rate( $vendor_id ) {
		// Try LS Vendors option first
		$default_rate = get_option( 'lsv_vendor_commission_rate', '' );

		// Fallback to WC Vendors option
		if ( '' === $default_rate ) {
			$default_rate = get_option( 'wcvendors_vendor_commission_rate', 0 );
		}

		// Check for vendor-specific rate (LS Vendors uses lsv_ prefix)
		$vendor_rate = get_user_meta( $vendor_id, 'lsv_commission_rate', true );

		// Fallback to WC Vendors meta key
		if ( '' === $vendor_rate ) {
			$vendor_rate = get_user_meta( $vendor_id, 'wcv_commission_rate', true );
		}

		if ( '' !== $vendor_rate ) {
			return floatval( $vendor_rate ) / 100;
		}

		return floatval( $default_rate ) / 100;
	}
}
