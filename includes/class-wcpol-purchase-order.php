<?php
/**
 * Purchase Order management
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Purchase_Order {

	/**
	 * Generate unique PO number
	 */
	public function generate_po_number() {
		$prefix      = get_option( 'wcpol_po_prefix', 'PO-' );
		$last_number = get_option(
			'wcpol_last_po_number',
			get_option( 'wcpol_po_starting_number', 1000 )
		);

		$new_number = intval( $last_number ) + 1;
		update_option( 'wcpol_last_po_number', $new_number );

		return $prefix . str_pad( $new_number, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Create purchase order from cart
	 */
	/**
	 * Create purchase order from cart
	 *
	 * @param string $notes         Notes text.
	 * @param string $reference     Reference number.
	 * @param string $required_date Requested delivery date.
	 * @param array  $addresses     Optional. Shipping and billing address arrays.
	 * @return int|false PO ID on success, false on failure.
	 */
	public function create_from_cart( $notes = '', $reference = '', $required_date = '', $addresses = array() ) {
		global $wpdb;

		if ( ! is_user_logged_in() || ! WC()->cart ) {
			return false;
		}

		$user_id   = get_current_user_id();
		$cart      = WC()->cart;
		$po_number = $this->generate_po_number();

		// Create main PO record
		// Use subtotal only - shipping will be added by vendors when they review
		$po_data = array(
			'po_number'    => $po_number,
			'user_id'      => $user_id,
			'total_amount' => $cart->get_subtotal(),
			'status'       => 'requested',
			'notes'        => sanitize_textarea_field( $notes ),
			'reference'    => sanitize_text_field( $reference ),
			'created_date' => current_time( 'mysql' ),
		);

		// Add required_date if provided.
		if ( $required_date ) {
			$po_data['required_date'] = sanitize_text_field( $required_date );
		}

		// Add addresses if provided.
		if ( ! empty( $addresses['shipping'] ) ) {
			$po_data['shipping_address'] = wp_json_encode( $addresses['shipping'] );
		}
		if ( ! empty( $addresses['billing'] ) ) {
			$po_data['billing_address'] = wp_json_encode( $addresses['billing'] );
		}

		// Build format array using associative map for safe key-to-format alignment.
		$format_map = array(
			'po_number'        => '%s',
			'user_id'          => '%d',
			'total_amount'     => '%f',
			'status'           => '%s',
			'notes'            => '%s',
			'reference'        => '%s',
			'created_date'     => '%s',
			'required_date'    => '%s',
			'shipping_address' => '%s',
			'billing_address'  => '%s',
		);
		$formats = array();
		foreach ( array_keys( $po_data ) as $key ) {
			$formats[] = isset( $format_map[ $key ] ) ? $format_map[ $key ] : '%s';
		}

		$wpdb->insert(
			$wpdb->prefix . 'wcpol_purchase_orders',
			$po_data,
			$formats
		);

		$po_id = $wpdb->insert_id;

		if ( ! $po_id ) {
			return false;
		}

		// Add cart items to PO
		$vendors_items  = array();
		$unique_vendors = array();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product    = $cart_item['data'];
			$product_id = $cart_item['product_id'];
			$quantity   = $cart_item['quantity'];
			$price      = $product->get_price();
			$total      = $price * $quantity;

			// Get vendor ID
			$vendor_id = get_post_field( 'post_author', $product_id );

			// Check if vendor (LS Vendors or WC Vendors)
			if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'is_vendor' ) ) {
				if ( ! LSV_Vendors::is_vendor( $vendor_id ) ) {
					$vendor_id = 0; // Admin/shop product
				}
			} elseif ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'is_vendor' ) ) {
				if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
					$vendor_id = 0; // Admin/shop product
				}
			} else {
				$vendor_id = 0;
			}

			// Insert PO item
			$item_data = array(
				'po_id'      => $po_id,
				'product_id' => $product_id,
				'vendor_id'  => $vendor_id,
				'quantity'   => $quantity,
				'price'      => $price,
				'total'      => $total,
				'status'     => 'pending',
			);

			$wpdb->insert(
				$wpdb->prefix . 'wcpol_po_items',
				$item_data,
				array( '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
			);

			// Group items by vendor for notifications
			if ( ! isset( $vendors_items[ $vendor_id ] ) ) {
				$vendors_items[ $vendor_id ] = array();
			}
			$vendors_items[ $vendor_id ][] = $product_id;

			// Track unique vendors (excluding admin/shop products where vendor_id = 0)
			if ( $vendor_id > 0 ) {
				$unique_vendors[ $vendor_id ] = true;
			}
		}

		// Count unique vendors and update the PO
		$vendor_count = count( $unique_vendors );
		if ( $vendor_count > 0 ) {
			$wpdb->update(
				$wpdb->prefix . 'wcpol_purchase_orders',
				array( 'vendor_count' => $vendor_count ),
				array( 'id' => $po_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Add to PO history
		$this->add_history(
			$po_id,
			$user_id,
			'requested',
			'Purchase order requested'
		);

		// Trigger actions
		do_action( 'wcpol_po_created', $po_id, $user_id );

		// Notify vendors
		foreach ( $vendors_items as $vendor_id => $product_ids ) {
			if ( $vendor_id > 0 ) {
				do_action( 'wcpol_vendor_po_request', $po_id, $vendor_id, $product_ids );
			}
		}

		// Clear cart after successful PO creation
		$cart->empty_cart();

		return $po_id;
	}

	/**
	 * AJAX handler for PO creation
	 */
	public function ajax_create_purchase_order() {
		// Enhanced security checks with rate limiting (max 5 POs per minute)
		WCPOL_Ajax_Security::verify_request( 'wcpol_create_purchase_order', array( 'read' ) );

		$notes         = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$reference     = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$required_date = isset( $_POST['required_date'] ) ? sanitize_text_field( wp_unslash( $_POST['required_date'] ) ) : '';

		$po_id = $this->create_from_cart( $notes, $reference, $required_date );

		if ( $po_id ) {
			$po_number = $this->get_po_number( $po_id );

			wp_send_json_success(
				array(
					'message'   => sprintf(
						/* translators: %s: PO number */
						__( 'Purchase Order %s has been created successfully. You will receive an email confirmation and notification when vendors respond.', 'wc-purchase-order-limits' ),
						$po_number
					),
					'po_id'     => $po_id,
					'po_number' => $po_number,
					'redirect'  => wc_get_account_endpoint_url( 'purchase-orders' ),
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to create purchase order. Please try again.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * Get PO by ID
	 */
	public function get_po( $po_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d",
				$po_id
			)
		);
	}

	/**
	 * Get PO number by ID
	 */
	public function get_po_number( $po_id ) {
		$po = $this->get_po( $po_id );
		return $po ? $po->po_number : false;
	}

	/**
	 * Get PO items
	 */
	public function get_po_items( $po_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_po_items WHERE po_id = %d",
				$po_id
			)
		);
	}

	/**
	 * Get POs for user
	 */
	public function get_user_pos( $user_id, $status = '' ) {
		global $wpdb;

		$query  = "SELECT * FROM {$wpdb->prefix}wcpol_purchase_orders WHERE user_id = %d";
		$params = array( $user_id );

		// Exclude trashed POs unless specifically requested
		if ( $status ) {
			$query   .= ' AND status = %s';
			$params[] = $status;
		} else {
			// When no specific status requested, exclude trashed
			$query .= " AND status != 'trashed'";
		}

		$query .= ' ORDER BY created_date DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses prepare() with dynamic placeholders.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
	}

	/**
	 * Update PO status
	 */
	public function update_status( $po_id, $new_status, $user_id = null ) {
		global $wpdb;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$po = $this->get_po( $po_id );
		if ( ! $po ) {
			return false;
		}

		$old_status = $po->status;

		$update_data = array( 'status' => $new_status );

		if ( 'approved' === $new_status ) {
			$update_data['approved_date'] = current_time( 'mysql' );
			$update_data['approved_by']   = $user_id;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			$update_data,
			array( 'id' => $po_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->add_history(
				$po_id,
				$user_id,
				'status_change',
				"Status changed from $old_status to $new_status"
			);

			do_action( 'wcpol_po_status_changed', $po_id, $old_status, $new_status );

			if ( 'approved' === $new_status ) {
				do_action( 'wcpol_po_approved', $po_id, $po->user_id );
			} elseif ( 'rejected' === $new_status ) {
				do_action( 'wcpol_po_rejected', $po_id, $po->user_id );
			}

			return true;
		}

		return false;
	}

	/**
	 * Add history entry
	 */
	public function add_history( $po_id, $user_id, $action, $notes = '' ) {
		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . 'wcpol_po_history',
			array(
				'po_id'     => $po_id,
				'user_id'   => $user_id,
				'action'    => $action,
				'notes'     => $notes,
				'timestamp' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get PO history
	 */
	public function get_history( $po_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*, u.display_name 
             FROM {$wpdb->prefix}wcpol_po_history h
             LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
             WHERE h.po_id = %d
             ORDER BY h.timestamp DESC",
				$po_id
			)
		);
	}

	/**
	 * Check if all vendor items are approved
	 */
	public function are_all_items_approved( $po_id ) {
		global $wpdb;

		$pending_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_po_items 
             WHERE po_id = %d AND vendor_id > 0 AND status != 'approved'",
				$po_id
			)
		);

		return 0 === (int) $pending_count;
	}

	/**
	 * Auto-approve if under threshold
	 */
	public function maybe_auto_approve( $po_id ) {
		$auto_approve_limit = get_option( 'wcpol_auto_approve_under', 1000 );

		if ( $auto_approve_limit <= 0 ) {
			return false;
		}

		$po = $this->get_po( $po_id );

		if ( $po && $po->total_amount < $auto_approve_limit ) {
			return $this->update_status( $po_id, 'approved', 0 );
		}

		return false;
	}

	/**
	 * AJAX handler for customer approving PO
	 */
	public function ajax_customer_approve_po() {
		// Enhanced security checks with ownership validation
		WCPOL_Ajax_Security::verify_request( 'wcpol_customer_approve_po', array( 'read' ) );

		$po_id   = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $po_id ) {
			wp_send_json_error( __( 'Invalid purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Verify customer owns this PO
		if ( ! WCPOL_Ajax_Security::verify_customer_po_ownership( $po_id, $user_id ) ) {
			wp_send_json_error( __( 'You do not have permission to approve this purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Get the PO
		$po = $this->get_po( $po_id );
		if ( ! $po ) {
			wp_send_json_error( __( 'Purchase order not found.', 'wc-purchase-order-limits' ) );
		}

		if ( 'issued' !== $po->status ) {
			wp_send_json_error( __( 'This purchase order cannot be approved in its current state.', 'wc-purchase-order-limits' ) );
		}

		// Update status to approved
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array(
				'status'        => 'approved',
				'approved_date' => current_time( 'mysql' ),
				'approved_by'   => $user_id,  // Customer who approved
			),
			array( 'id' => $po_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Add history entry
			$this->add_history( $po_id, $user_id, 'customer_approved', __( 'Customer approved the purchase order', 'wc-purchase-order-limits' ) );

			// Send notifications and create order (order is created via wcpol_po_approved hook)
			do_action( 'wcpol_po_approved', $po_id, $user_id );

			// Get the created order to return the payment URL.
			$order_creator = new WCPOL_Order_Creator();
			$order_id      = $order_creator->get_order_id_for_po( $po_id );
			$payment_url   = '';

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order && $order->needs_payment() ) {
					$payment_url = $order->get_checkout_payment_url();
				}
			}

			wp_send_json_success(
				array(
					'message'     => __( 'Quote approved successfully! Redirecting to payment...', 'wc-purchase-order-limits' ),
					'order_id'    => $order_id,
					'payment_url' => $payment_url,
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to approve purchase order. Please try again.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * AJAX handler for customer rejecting PO
	 */
	public function ajax_customer_reject_po() {
		// Enhanced security checks with ownership validation
		WCPOL_Ajax_Security::verify_request( 'wcpol_customer_reject_po', array( 'read' ) );

		$po_id   = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		$reason  = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$user_id = get_current_user_id();

		if ( ! $po_id ) {
			wp_send_json_error( __( 'Invalid purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Verify customer owns this PO
		if ( ! WCPOL_Ajax_Security::verify_customer_po_ownership( $po_id, $user_id ) ) {
			wp_send_json_error( __( 'You do not have permission to reject this purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Get the PO
		$po = $this->get_po( $po_id );
		if ( ! $po ) {
			wp_send_json_error( __( 'Purchase order not found.', 'wc-purchase-order-limits' ) );
		}

		if ( 'issued' !== $po->status ) {
			wp_send_json_error( __( 'This purchase order cannot be rejected in its current state.', 'wc-purchase-order-limits' ) );
		}

		// Update status to rejected
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array(
				'status' => 'rejected',
			),
			array( 'id' => $po_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Add history entry with reason
			$history_note = __( 'Customer rejected the purchase order', 'wc-purchase-order-limits' );
			if ( $reason ) {
				$history_note .= ': ' . $reason;
			}
			$this->add_history( $po_id, $user_id, 'customer_rejected', $history_note );

			// Send notifications
			do_action( 'wcpol_po_rejected', $po_id, $user_id );

			wp_send_json_success( __( 'Purchase order rejected.', 'wc-purchase-order-limits' ) );
		} else {
			wp_send_json_error( __( 'Failed to reject purchase order. Please try again.', 'wc-purchase-order-limits' ) );
		}
	}
}
