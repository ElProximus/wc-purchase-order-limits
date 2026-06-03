<?php
/**
 * Security and validation helper class
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Security {

	/**
	 * Check if user is a vendor (compatible with LS Vendors and WC Vendors)
	 */
	public static function is_vendor_user( $user_id = null ) {
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
	 * Validate and sanitize amount
	 */
	public static function sanitize_amount( $amount ) {
		$amount = str_replace( array( ',', '$', '€', '£' ), '', $amount );
		return floatval( $amount );
	}

	/**
	 * Validate email address
	 */
	public static function validate_email( $email ) {
		return is_email( $email );
	}

	/**
	 * Sanitize PO status
	 */
	public static function sanitize_status( $status ) {
		$valid_statuses = array( 'draft', 'pending', 'approved', 'rejected', 'partial' );
		return in_array( $status, $valid_statuses ) ? $status : 'draft';
	}

	/**
	 * Check if user can manage PO
	 */
	public static function can_manage_po( $po_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Admins can manage all POs
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		// Check if user owns the PO
		global $wpdb;
		$po_owner = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d",
				$po_id
			)
		);

		return (int) $po_owner === (int) $user_id;
	}

	/**
	 * Check if vendor can manage PO item
	 */
	public static function vendor_can_manage_item( $item_id, $vendor_id = null ) {
		if ( ! $vendor_id ) {
			$vendor_id = get_current_user_id();
		}

		// Check vendor status with compatibility
		if ( ! self::is_vendor_user( $vendor_id ) ) {
			return false;
		}

		global $wpdb;
		$item_vendor = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT vendor_id FROM {$wpdb->prefix}wcpol_po_items WHERE id = %d",
				$item_id
			)
		);

		return (int) $item_vendor === (int) $vendor_id;
	}

	/**
	 * Sanitize file upload
	 */
	public static function validate_upload( $file, $allowed_types = array( 'pdf', 'jpg', 'jpeg', 'png' ) ) {
		$file_type = wp_check_filetype( $file['name'] );

		if ( ! in_array( $file_type['ext'], $allowed_types ) ) {
			return new WP_Error( 'invalid_file_type', __( 'Invalid file type.', 'wc-purchase-order-limits' ) );
		}

		if ( $file['size'] > 5 * 1024 * 1024 ) { // 5MB limit
			return new WP_Error( 'file_too_large', __( 'File size exceeds limit.', 'wc-purchase-order-limits' ) );
		}

		return true;
	}

	/**
	 * Generate secure token
	 */
	public static function generate_token( $length = 32 ) {
		return wp_generate_password( $length, false );
	}

	/**
	 * Verify nonce with logging
	 */
	public static function verify_nonce( $nonce, $action ) {
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			WCPOL_Logger::warning( sprintf( 'Nonce verification failed for action: %s', $action ) );
			return false;
		}
		return true;
	}

	/**
	 * Sanitize text input
	 */
	public static function sanitize_text( $text, $allow_html = false ) {
		if ( $allow_html ) {
			$allowed_tags = wp_kses_allowed_html( 'post' );
			return wp_kses( $text, $allowed_tags );
		}
		return sanitize_text_field( $text );
	}

	/**
	 * Validate date
	 */
	public static function validate_date( $date, $format = 'Y-m-d' ) {
		$d = DateTime::createFromFormat( $format, $date );
		return $d && $d->format( $format ) === $date;
	}

	/**
	 * Check rate limiting
	 */
	public static function check_rate_limit( $action, $user_id = null, $limit = 10, $window = 60 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$transient_key = 'wcpol_rate_' . $action . '_' . $user_id;
		$attempts      = get_transient( $transient_key );

		if ( false === $attempts ) {
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( $attempts >= $limit ) {
			WCPOL_Logger::warning( sprintf( 'Rate limit exceeded for action %s by user %d', $action, $user_id ) );
			return false;
		}

		set_transient( $transient_key, $attempts + 1, $window );
		return true;
	}

	/**
	 * Escape output
	 */
	public static function esc_output( $data, $context = 'html' ) {
		if ( is_array( $data ) ) {
			return array_map(
				function ( $item ) use ( $context ) {
					return self::esc_output( $item, $context );
				},
				$data
			);
		}

		switch ( $context ) {
			case 'attr':
				return esc_attr( $data );
			case 'url':
				return esc_url( $data );
			case 'js':
				return esc_js( $data );
			case 'textarea':
				return esc_textarea( $data );
			default:
				return esc_html( $data );
		}
	}

	/**
	 * Validate AJAX request
	 */
	public static function validate_ajax_request( $nonce_action ) {
		// Check if AJAX request
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		// Check nonce.
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! self::verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wc-purchase-order-limits' ) );
			return false;
		}

		// Check user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in.', 'wc-purchase-order-limits' ) );
			return false;
		}

		return true;
	}

	/**
	 * Get safe redirect URL
	 */
	public static function get_safe_redirect( $url, $fallback = '' ) {
		if ( ! $fallback ) {
			$fallback = home_url();
		}

		$url = wp_sanitize_redirect( $url );
		$url = wp_validate_redirect( $url, $fallback );

		return $url;
	}
}
