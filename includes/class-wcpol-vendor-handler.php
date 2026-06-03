<?php
/**
 * Vendor-specific functionality
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Vendor_Handler {

	/**
	 * Vendor utility instance
	 */
	private $vendor_utility;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->vendor_utility = WCPOL_Vendor_Utility::get_instance();
	}

	/**
	 * Check if user is a vendor (now uses vendor utility)
	 */
	private function is_vendor( $user_id = null ) {
		return $this->vendor_utility->is_vendor( $user_id );
	}

	/**
	 * Get vendor's PO items
	 */
	public function get_vendor_po_items( $vendor_id, $po_id = null ) {
		global $wpdb;

		$query = "SELECT poi.*, po.po_number, po.user_id, po.status as po_status 
                  FROM {$wpdb->prefix}wcpol_po_items poi
                  JOIN {$wpdb->prefix}wcpol_purchase_orders po ON poi.po_id = po.id
                  WHERE poi.vendor_id = %d
                  AND po.status != 'trashed'";

		$params = array( $vendor_id );

		if ( $po_id ) {
			$query   .= ' AND poi.po_id = %d';
			$params[] = $po_id;
		}

		$query .= ' ORDER BY po.created_date DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses prepare() with dynamic placeholders.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Get vendor's pending POs
	 */
	public function get_vendor_pending_pos( $vendor_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT po.* 
             FROM {$wpdb->prefix}wcpol_purchase_orders po
             JOIN {$wpdb->prefix}wcpol_po_items poi ON po.id = poi.po_id
             WHERE poi.vendor_id = %d 
             AND poi.status = 'pending'
             AND po.status IN ('pending', 'partial', 'requested')
             AND po.status != 'trashed'
             ORDER BY po.created_date DESC",
				$vendor_id
			)
		);
	}

	/**
	 * Update vendor's PO item
	 */
	public function update_vendor_item( $item_id, $vendor_id, $data ) {
		global $wpdb;

		// Verify vendor owns this item
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_po_items 
             WHERE id = %d AND vendor_id = %d",
				$item_id,
				$vendor_id
			)
		);

		if ( ! $item ) {
			return false;
		}

		$update_data     = array();
		$incoming_status = isset( $data['status'] ) ? $data['status'] : '';

		// Only allow vendor_price updates if item is not marked as not_available in this request
		if ( 'not_available' !== $incoming_status && isset( $data['vendor_price'] ) && 'yes' === get_option( 'wcpol_vendor_can_edit_price', 'yes' ) ) {
			$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
			$price    = wc_format_decimal( $data['vendor_price'], $decimals );
			if ( $price < 0 ) {
				$price = 0;
			}
			$update_data['vendor_price'] = $price;
			$update_data['total']        = wc_format_decimal( $price * (int) $item->quantity, $decimals );
		}

		if ( isset( $data['vendor_notes'] ) ) {
			$update_data['vendor_notes'] = sanitize_textarea_field( $data['vendor_notes'] );
		}

		// Accept the new status values: available, not_available, approved, rejected
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'available', 'not_available', 'approved', 'rejected' ) ) ) {
			$update_data['status'] = $data['status'];

			if ( in_array( $data['status'], array( 'available', 'approved' ) ) ) {
				$update_data['vendor_approved'] = current_time( 'mysql' );
			} elseif ( 'not_available' === $data['status'] ) {
				// Not available: force total to 0 and do not change vendor_approved
				$decimals             = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
				$update_data['total'] = wc_format_decimal( 0, $decimals );
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_po_items',
			$update_data,
			array( 'id' => $item_id ),
			null,
			array( '%d' )
		);

		if ( false !== $result ) {
			$po_handler = new WCPOL_Purchase_Order();
			$po_handler->add_history(
				$item->po_id,
				$vendor_id,
				'vendor_update',
				sprintf( 'Vendor updated item #%d', $item_id )
			);

			// Check if all items are approved
			if ( isset( $data['status'] ) && 'approved' === $data['status'] ) {
				$this->check_po_approval_status( $item->po_id );
			}

			return true;
		}

		return false;
	}

	/**
	 * AJAX handler for batch updating vendor items (price, notes, availability)
	 */
	public function ajax_update_items() {
		// Enhanced security checks
		WCPOL_Ajax_Security::verify_request(
			'wcpol_vendor_update_items',
			array(),
			array(
				'nonce_action'    => 'wcpol_vendor_nonce',
				'validate_origin' => false,
			)
		);

		// Must be a vendor
		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a vendor to update items.', 'wc-purchase-order-limits' ) );
		}

		$vendor_id = get_current_user_id();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized per-element below.
		$updates = isset( $_POST['updates'] ) ? wp_unslash( $_POST['updates'] ) : array();

		if ( ! is_array( $updates ) || empty( $updates ) ) {
			wp_send_json_error( __( 'No updates provided.', 'wc-purchase-order-limits' ) );
		}

		$all_ok = true;
		foreach ( $updates as $u ) {
			$item_id = isset( $u['item_id'] ) ? absint( $u['item_id'] ) : 0;
			if ( ! $item_id ) {
				$all_ok = false;
				continue;
			}

			$data = array();
			if ( isset( $u['vendor_price'] ) && '' !== $u['vendor_price'] ) {
				$data['vendor_price'] = $u['vendor_price'];
			}
			if ( isset( $u['vendor_notes'] ) ) {
				$data['vendor_notes'] = $u['vendor_notes'];
			}
			if ( isset( $u['status'] ) && in_array( $u['status'], array( 'available', 'not_available' ), true ) ) {
				$data['status'] = $u['status'];
			}

			// If marking not available, do not overwrite vendor_price; keep prior price in DB
			if ( isset( $data['status'] ) && 'not_available' === $data['status'] && isset( $data['vendor_price'] ) ) {
				unset( $data['vendor_price'] );
			}

			// Update item (method verifies ownership internally)
			$ok = $this->update_vendor_item( $item_id, $vendor_id, $data );
			if ( ! $ok ) {
				$all_ok = false;
			}
		}

		if ( $all_ok ) {
			wp_send_json_success( __( 'Items updated successfully.', 'wc-purchase-order-limits' ) );
		} else {
			wp_send_json_error( __( 'Some items could not be updated.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * AJAX handler for vendor item update
	 */
	public function ajax_update_po_item() {
		// Enhanced security checks (capability handled by vendor checks below)
		WCPOL_Ajax_Security::verify_request(
			'wcpol_vendor_update_po_item',
			array(),
			array(
				'nonce_action'    => 'wcpol_vendor_nonce',
				'validate_origin' => false,
			)
		);

		// Additional vendor-specific check
		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a vendor to update PO items.', 'wc-purchase-order-limits' ) );
		}

		$item_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$vendor_id = get_current_user_id();

		// Validate item ID
		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'wc-purchase-order-limits' ) );
		}

		$data = array();

		if ( isset( $_POST['vendor_price'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_format_decimal() handles sanitization.
			$data['vendor_price'] = wc_format_decimal( wp_unslash( $_POST['vendor_price'] ) );
		}

		if ( isset( $_POST['vendor_notes'] ) ) {
			$data['vendor_notes'] = sanitize_textarea_field( wp_unslash( $_POST['vendor_notes'] ) );
		}

		if ( $this->update_vendor_item( $item_id, $vendor_id, $data ) ) {
			wp_send_json_success( __( 'Item updated successfully.', 'wc-purchase-order-limits' ) );
		} else {
			wp_send_json_error( __( 'Failed to update item.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * AJAX handler for vendor PO approval
	 */
	public function ajax_approve_po() {
		// Enhanced security checks (capability handled by vendor checks below)
		WCPOL_Ajax_Security::verify_request(
			'wcpol_vendor_approve_po',
			array(),
			array(
				'nonce_action'    => 'wcpol_vendor_nonce',
				'validate_origin' => false,
			)
		);

		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a vendor to approve PO items.', 'wc-purchase-order-limits' ) );
		}

		$po_id     = isset( $_POST['po_id'] ) ? absint( $_POST['po_id'] ) : 0;
		$vendor_id = get_current_user_id();

		// Validate PO ID
		if ( ! $po_id ) {
			wp_send_json_error( __( 'Invalid purchase order ID.', 'wc-purchase-order-limits' ) );
		}

		// Verify vendor has access to this PO
		if ( ! WCPOL_Ajax_Security::verify_vendor_po_access( $po_id, $vendor_id ) ) {
			wp_send_json_error( __( 'You do not have access to this purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Approve all vendor's items in this PO
		$items = $this->get_vendor_po_items( $vendor_id, $po_id );

		foreach ( $items as $item ) {
			$this->update_vendor_item( $item->id, $vendor_id, array( 'status' => 'approved' ) );
		}

		wp_send_json_success( __( 'Purchase order items approved successfully.', 'wc-purchase-order-limits' ) );
	}

	/**
	 * AJAX handler for vendor PO rejection
	 */
	public function ajax_reject_po() {
		// Enhanced security checks (capability handled by vendor checks below)
		WCPOL_Ajax_Security::verify_request(
			'wcpol_vendor_reject_po',
			array(),
			array(
				'nonce_action'    => 'wcpol_vendor_nonce',
				'validate_origin' => false,
			)
		);

		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a vendor to reject PO items.', 'wc-purchase-order-limits' ) );
		}

		$po_id     = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		$vendor_id = get_current_user_id();
		$reason    = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		// Verify vendor has access to this PO
		if ( ! WCPOL_Ajax_Security::verify_vendor_po_access( $po_id, $vendor_id ) ) {
			wp_send_json_error( __( 'You do not have access to this purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Reject all vendor's items in this PO
		$items = $this->get_vendor_po_items( $vendor_id, $po_id );

		foreach ( $items as $item ) {
			$data = array( 'status' => 'rejected' );
			if ( $reason ) {
				$data['vendor_notes'] = $reason;
			}
			$this->update_vendor_item( $item->id, $vendor_id, $data );
		}

		// Update PO status to rejected if any vendor rejects
		$po_handler = new WCPOL_Purchase_Order();
		$po_handler->update_status( $po_id, 'rejected', $vendor_id );

		wp_send_json_success( __( 'Purchase order items rejected.', 'wc-purchase-order-limits' ) );
	}

	/**
	 * Check if PO should be approved
	 */
	private function check_po_approval_status( $po_id ) {
		$po_handler = new WCPOL_Purchase_Order();

		if ( $po_handler->are_all_items_approved( $po_id ) ) {
			$po_handler->update_status( $po_id, 'approved' );
		}
	}

	/**
	 * Notify vendors when PO is created
	 */
	public function notify_vendors( $po_id, $user_id ) {
		global $wpdb;

		// Get unique vendors for this PO
		$vendors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT vendor_id 
             FROM {$wpdb->prefix}wcpol_po_items 
             WHERE po_id = %d AND vendor_id > 0",
				$po_id
			)
		);

		if ( empty( $vendors ) ) {
			return;
		}

		// Send email to each vendor
		if ( 'yes' === get_option( 'wcpol_enable_vendor_emails', 'yes' ) ) {
			$email_handler = new WCPOL_Email_Handler();

			foreach ( $vendors as $vendor_id ) {
				$vendor_items = $this->get_vendor_po_items( $vendor_id, $po_id );
				$email_handler->send_vendor_po_notification( $vendor_id, $po_id, $vendor_items );
			}
		}
	}

	/**
	 * Get vendor dashboard stats
	 */
	public function get_vendor_stats( $vendor_id ) {
		global $wpdb;

		$stats = array(
			'pending'     => 0,
			'approved'    => 0,
			'rejected'    => 0,
			'total_value' => 0,
		);

		// Count distinct POs by status that contain this vendor's items
		$po_stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT po.status, COUNT(DISTINCT po.id) as count
             FROM {$wpdb->prefix}wcpol_purchase_orders po
             JOIN {$wpdb->prefix}wcpol_po_items poi ON poi.po_id = po.id
             WHERE poi.vendor_id = %d
             AND po.status IN ('requested', 'approved', 'rejected', 'issued')
             GROUP BY po.status",
				$vendor_id
			)
		);

		foreach ( $po_stats as $row ) {
			if ( 'requested' === $row->status ) {
				$stats['pending'] = $row->count;
			} elseif ( 'approved' === $row->status ) {
				$stats['approved'] = $row->count;
			} elseif ( 'rejected' === $row->status ) {
				$stats['rejected'] = $row->count;
			}
		}

		// Calculate total value of approved POs only, using vendor prices
		$total_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(
                CASE 
                    WHEN poi.vendor_price IS NOT NULL AND poi.vendor_price > 0 
                    THEN poi.vendor_price * poi.quantity 
                    ELSE poi.total 
                END
            ) as total_value
             FROM {$wpdb->prefix}wcpol_po_items poi
             JOIN {$wpdb->prefix}wcpol_purchase_orders po ON poi.po_id = po.id
             WHERE poi.vendor_id = %d
             AND po.status = 'approved'",
				$vendor_id
			)
		);

		$stats['total_value'] = $total_value ? $total_value : 0;

		return $stats;
	}

	/**
	 * Save or update vendor shipping for a PO
	 *
	 * @param int    $po_id           Purchase order ID.
	 * @param int    $vendor_id       Vendor user ID.
	 * @param float  $shipping_amount Shipping amount.
	 * @param string $shipping_notes  Optional shipping notes.
	 * @return int|false Insert ID or false on failure.
	 */
	public function save_vendor_shipping( $po_id, $vendor_id, $shipping_amount, $shipping_notes = '' ) {
		global $wpdb;

		$shipping_amount = max( 0, floatval( $shipping_amount ) );
		$shipping_notes  = sanitize_textarea_field( $shipping_notes );

		// Check if record exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wcpol_vendor_shipping
				WHERE po_id = %d AND vendor_id = %d",
				$po_id,
				$vendor_id
			)
		);

		if ( $existing ) {
			return $wpdb->update(
				$wpdb->prefix . 'wcpol_vendor_shipping',
				array(
					'shipping_amount' => $shipping_amount,
					'shipping_notes'  => $shipping_notes,
				),
				array( 'id' => $existing ),
				array( '%f', '%s' ),
				array( '%d' )
			);
		} else {
			return $wpdb->insert(
				$wpdb->prefix . 'wcpol_vendor_shipping',
				array(
					'po_id'           => $po_id,
					'vendor_id'       => $vendor_id,
					'shipping_amount' => $shipping_amount,
					'shipping_notes'  => $shipping_notes,
				),
				array( '%d', '%d', '%f', '%s' )
			);
		}
	}

	/**
	 * Get all vendor shipping for a PO
	 *
	 * @param int $po_id Purchase order ID.
	 * @return array Array of vendor shipping rows with vendor names.
	 */
	public function get_po_vendor_shipping( $po_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vs.*, u.display_name as vendor_name
				FROM {$wpdb->prefix}wcpol_vendor_shipping vs
				LEFT JOIN {$wpdb->users} u ON vs.vendor_id = u.ID
				WHERE vs.po_id = %d",
				$po_id
			)
		);
	}

	/**
	 * Get total shipping for a PO (sum of all vendors)
	 *
	 * @param int $po_id Purchase order ID.
	 * @return float Total shipping amount.
	 */
	public function get_po_total_shipping( $po_id ) {
		global $wpdb;

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(shipping_amount), 0)
				FROM {$wpdb->prefix}wcpol_vendor_shipping
				WHERE po_id = %d",
				$po_id
			)
		);
	}

	/**
	 * Get vendor shipping for a specific vendor on a PO
	 *
	 * @param int $po_id     Purchase order ID.
	 * @param int $vendor_id Vendor user ID.
	 * @return object|null Shipping row or null.
	 */
	public function get_vendor_shipping( $po_id, $vendor_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_vendor_shipping
				WHERE po_id = %d AND vendor_id = %d",
				$po_id,
				$vendor_id
			)
		);
	}

	/**
	 * AJAX handler for vendor creating and sending a PO to customer
	 */
	public function ajax_create_vendor_po() {
		// Enhanced security checks with rate limiting (capability handled by vendor checks below)
		WCPOL_Ajax_Security::verify_request(
			'wcpol_vendor_create_po',
			array(),
			array(
				'nonce_action'    => 'wcpol_vendor_nonce',
				'validate_origin' => false,
			)
		);

		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a vendor to create purchase orders.', 'wc-purchase-order-limits' ) );
		}

		$po_id     = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		$vendor_id = get_current_user_id();

		if ( ! $po_id ) {
			wp_send_json_error( __( 'Invalid purchase order ID.', 'wc-purchase-order-limits' ) );
		}

		// Verify vendor has access to this PO
		if ( ! WCPOL_Ajax_Security::verify_vendor_po_access( $po_id, $vendor_id ) ) {
			wp_send_json_error( __( 'You do not have access to this purchase order.', 'wc-purchase-order-limits' ) );
		}

		global $wpdb;

		// Get the original PO
		$po_handler  = new WCPOL_Purchase_Order();
		$original_po = $po_handler->get_po( $po_id );

		if ( ! $original_po ) {
			wp_send_json_error( __( 'Purchase order not found.', 'wc-purchase-order-limits' ) );
		}

		// Check if PO is already approved or rejected (but allow 'partial' and 'issued' for other vendors)
		if ( 'approved' === $original_po->status || 'rejected' === $original_po->status ) {
			wp_send_json_error( __( 'This purchase order has already been finalized.', 'wc-purchase-order-limits' ) );
		}

		// Update vendor items with new prices if provided
		$vendor_items = $this->get_vendor_po_items( $vendor_id, $po_id );

		// Check if vendor has already processed these items
		$has_pending = false;
		foreach ( $vendor_items as $item ) {
			if ( 'pending' === $item->status ) {
				$has_pending = true;
				break;
			}
		}

		if ( ! $has_pending ) {
			wp_send_json_error( __( 'You have already processed this purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Start transaction for atomic updates
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $vendor_items as $item ) {
			$update_data = array();

			// Check for item availability
			$availability = 'available'; // Default to available
			if ( isset( $_POST['item_availability'][ $item->id ] ) ) {
				$availability = 'not_available' === $_POST['item_availability'][ $item->id ] ? 'not_available' : 'available';
			}
			$update_data['status'] = $availability;

			// Only update price if item is available
			if ( 'available' === $availability && isset( $_POST['vendor_price'][ $item->id ] ) ) {
				$new_price                   = floatval( $_POST['vendor_price'][ $item->id ] );
				$update_data['vendor_price'] = $new_price;
				$update_data['total']        = $new_price * $item->quantity;
			} elseif ( 'not_available' === $availability ) {
				// Set total to 0 for unavailable items
				$update_data['total'] = 0;
			}

			// Check for vendor notes.
			if ( isset( $_POST['vendor_notes'][ $item->id ] ) ) {
				$update_data['vendor_notes'] = sanitize_textarea_field( wp_unslash( $_POST['vendor_notes'][ $item->id ] ) );
			}

			// Mark as vendor responded
			$update_data['vendor_approved'] = current_time( 'mysql' );

			// Update the item
			if ( ! empty( $update_data ) ) {
				// Build format array based on what's in update_data
				$formats = array();
				if ( isset( $update_data['status'] ) ) {
					$formats[] = '%s';
				}
				if ( isset( $update_data['vendor_price'] ) ) {
					$formats[] = '%f';
				}
				if ( isset( $update_data['total'] ) ) {
					$formats[] = '%f';
				}
				if ( isset( $update_data['vendor_notes'] ) ) {
					$formats[] = '%s';
				}
				if ( isset( $update_data['vendor_approved'] ) ) {
					$formats[] = '%s';
				}

				$wpdb->update(
					$wpdb->prefix . 'wcpol_po_items',
					$update_data,
					array( 'id' => $item->id ),
					$formats,
					array( '%d' )
				);
			}
		}

		// Save vendor shipping.
		$vendor_shipping = isset( $_POST['vendor_shipping'] ) ? floatval( $_POST['vendor_shipping'] ) : 0;
		$shipping_notes  = isset( $_POST['shipping_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shipping_notes'] ) ) : '';
		$this->save_vendor_shipping( $po_id, $vendor_id, $vendor_shipping, $shipping_notes );

		// Calculate the new total amount after vendor price adjustments.
		// Get items subtotal.
		$items_total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}wcpol_po_items WHERE po_id = %d",
				$po_id
			)
		);

		// Get total shipping from all vendors.
		$shipping_total = $this->get_po_total_shipping( $po_id );

		// Calculate new total (items + shipping).
		$new_total = $items_total + $shipping_total;

		// Get validity days from form (default to 30 if not set)
		$validity_days = isset( $_POST['validity_days'] ) ? intval( $_POST['validity_days'] ) : 30;

		// Check if multi-vendor PO - force 30 days for multi-vendor
		$vendor_count = isset( $original_po->vendor_count ) ? intval( $original_po->vendor_count ) : 0;
		if ( $vendor_count > 1 ) {
			// Multi-vendor PO - always 30 days
			$validity_days = 30;
		} else {
			// Single vendor - validate validity days (must be 30, 60, or 90)
			if ( ! in_array( $validity_days, array( 30, 60, 90 ) ) ) {
				$validity_days = 30;
			}
		}

		// Calculate expiry date
		$expiry_date = date( 'Y-m-d', strtotime( "+{$validity_days} days" ) );

		// Check how many vendors are in this PO and how many have issued
		// Use isset to check if columns exist (for backwards compatibility)
		$vendor_count   = isset( $original_po->vendor_count ) ? intval( $original_po->vendor_count ) : 0;
		$vendors_issued = isset( $original_po->vendors_issued ) ? intval( $original_po->vendors_issued ) : 0;

		// Increment vendors_issued counter
		++$vendors_issued;

		// Check if new columns exist in database.
		$table_name = $wpdb->prefix . 'wcpol_purchase_orders';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot use placeholders.
		$vendor_count_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'vendor_count'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot use placeholders.
		$vendors_issued_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'vendors_issued'" );

		// Determine new status based on vendor count
		$new_status = 'requested'; // Default
		if ( ! empty( $vendor_count_exists ) && ! empty( $vendors_issued_exists ) ) {
			// New columns exist - use multi-vendor logic
			if ( $vendor_count <= 1 || $vendors_issued >= $vendor_count ) {
				// Single vendor OR all vendors have issued
				$new_status = 'issued';
			} else {
				// Multiple vendors and not all have issued yet
				$new_status = 'partial';
			}
		} else {
			// Columns don't exist - use simple logic (always issue)
			$new_status = 'issued';
		}

		// Update PO with appropriate status
		$update_data = array(
			'status'        => $new_status,
			'validity_days' => $validity_days,
			'expiry_date'   => $expiry_date,
			'total_amount'  => $new_total,  // Update with recalculated total
		);

		// Only add vendors_issued if column exists
		if ( ! empty( $vendors_issued_exists ) ) {
			$update_data['vendors_issued'] = $vendors_issued;
		}

		// Only set these fields when fully issued
		if ( 'issued' === $new_status ) {
			$update_data['vendor_created'] = 1;
			$update_data['approved_date']  = current_time( 'mysql' );  // This now represents issued date
			$update_data['approved_by']    = $vendor_id;  // This now represents issued by
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			$update_data,
			array( 'id' => $po_id ),
			null,  // Let WordPress determine formats
			array( '%d' )
		);

		// Check if update failed or no rows updated
		if ( false === $result || 0 === $result ) {
			// Rollback item updates
			$wpdb->query( 'ROLLBACK' );

			// Log the error for debugging
			if ( false === $result ) {
				$db_error = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error';
				error_log( 'WCPOL: Database error updating PO status. PO ID: ' . $po_id . ', Last DB Error: ' . $db_error );

				// Temporarily show the actual database error to help debug.
				wp_send_json_error(
					sprintf(
						/* translators: 1: database error message, 2: PO ID */
						__( 'Database error: %1$s (PO ID: %2$d)', 'wc-purchase-order-limits' ),
						$db_error,
						$po_id
					)
				);
			} else {
				// No rows updated - check why
				$existing_po = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, status FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d",
						$po_id
					)
				);

				if ( ! $existing_po ) {
					error_log( 'WCPOL: PO not found with ID: ' . $po_id );
					wp_send_json_error( __( 'Purchase order not found.', 'wc-purchase-order-limits' ) );
				} else {
					error_log( 'WCPOL: No rows updated. PO ID: ' . $po_id . ', Current status: ' . $existing_po->status );
					wp_send_json_error( __( 'Unable to update purchase order status. Current status: ' . $existing_po->status, 'wc-purchase-order-limits' ) );
				}
			}
			return;
		}

		// Commit the transaction if everything succeeded
		$wpdb->query( 'COMMIT' );

		// Add appropriate history entry based on status
		if ( 'issued' === $new_status ) {
			$po_handler->add_history(
				$po_id,
				$vendor_id,
				'vendor_issued',
				__( 'All vendors have responded - Purchase order issued to customer', 'wc-purchase-order-limits' )
			);

			// Send notification to customer that PO is ready
			$notifications = new WCPOL_Notifications();
			$notifications->notify_customer_vendor_po_created( $po_id, $original_po->user_id, $vendor_id );

			// Send email to customer
			if ( 'yes' === get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
				$email_handler = new WCPOL_Email_Handler();
				$email_handler->send_customer_vendor_po_notification( $original_po->user_id, $po_id );
			}

			// Generate PDF when all vendors have issued
			$pdf_generator = new WCPOL_PDF_Generator();
			$pdf_generator->generate_pdf( $po_id );

			wp_send_json_success( __( 'Purchase order issued to customer successfully!', 'wc-purchase-order-limits' ) );
		} else {
			// Status is 'partial' - waiting for other vendors.
			$po_handler->add_history(
				$po_id,
				$vendor_id,
				'vendor_responded',
				sprintf(
					/* translators: 1: vendors issued count, 2: total vendor count */
					__( 'Vendor responded (%1$d of %2$d vendors completed)', 'wc-purchase-order-limits' ),
					$vendors_issued,
					$vendor_count
				)
			);

			wp_send_json_success(
				sprintf(
					/* translators: 1: vendors issued count, 2: total vendor count */
					__( 'Your response has been recorded. Waiting for other vendors to complete (%1$d of %2$d done).', 'wc-purchase-order-limits' ),
					$vendors_issued,
					$vendor_count
				)
			);
		}
	}

	/**
	 * Check if all vendors have responded and update PO status to issued if they have
	 */
	private function check_and_update_po_status( $po_id, $vendor_id ) {
		global $wpdb;

		// Check if there are any pending vendor items left (items that haven't been processed by vendors)
		// Note: We only check vendor items (vendor_id > 0), not admin/shop items
		$pending_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_po_items 
             WHERE po_id = %d AND vendor_id > 0 AND status = 'pending'",
				$po_id
			)
		);

		// If no pending items, all vendors have responded - change status to issued
		if ( 0 === (int) $pending_count ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'wcpol_purchase_orders',
				array(
					'status'         => 'issued',
					'vendor_created' => 1,
					'approved_date'  => current_time( 'mysql' ),  // This now represents issued date
					'approved_by'    => $vendor_id,  // Last vendor who responded
				),
				array( 'id' => $po_id ),
				array( '%s', '%d', '%s', '%d' ),
				array( '%d' )
			);

			// Check if update failed
			if ( false === $result ) {
				return false;
			}

			// Add history entry
			$po_handler = new WCPOL_Purchase_Order();
			$po_handler->add_history(
				$po_id,
				$vendor_id,
				'all_vendors_responded',
				__( 'All vendors have responded - Purchase order issued to customer', 'wc-purchase-order-limits' )
			);

			// Get PO details for notifications
			$po = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d",
					$po_id
				)
			);

			// Send notification to customer that PO is ready for review
			$notifications = new WCPOL_Notifications();
			$notifications->notify_customer_vendor_po_created( $po_id, $po->user_id, $vendor_id );

			// Send email to customer
			if ( 'yes' === get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
				$email_handler = new WCPOL_Email_Handler();
				$email_handler->send_customer_vendor_po_notification( $po->user_id, $po_id );
			}

			return true; // Status was updated to issued
		}

		return false; // No status update needed
	}
}
