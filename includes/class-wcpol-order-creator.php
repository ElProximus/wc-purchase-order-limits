<?php
/**
 * Order Creator - Converts approved POs to WooCommerce orders
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCPOL_Order_Creator class.
 *
 * Handles the conversion of approved purchase orders to WooCommerce orders
 * with pending payment status.
 */
class WCPOL_Order_Creator {

	/**
	 * Create a WooCommerce order from an approved PO.
	 *
	 * @param int $po_id   The purchase order ID.
	 * @param int $user_id The customer user ID.
	 * @return int|WP_Error The order ID on success, WP_Error on failure.
	 */
	public function create_order_from_po( $po_id, $user_id ) {
		global $wpdb;

		// Get the PO data.
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $po ) {
			return new WP_Error( 'invalid_po', __( 'Purchase order not found.', 'wc-purchase-order-limits' ) );
		}

		// Check if order already exists for this PO.
		$existing_order_id = $this->get_order_id_for_po( $po_id );
		if ( $existing_order_id ) {
			return $existing_order_id; // Order already created.
		}

		// Get PO items (only available items).
		$po_items = $po_handler->get_po_items( $po_id );
		if ( empty( $po_items ) ) {
			return new WP_Error( 'no_items', __( 'No items found in purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Filter to only available items.
		$available_items = array_filter(
			$po_items,
			function ( $item ) {
				return 'available' === $item->status || 'approved' === $item->status;
			}
		);

		if ( empty( $available_items ) ) {
			return new WP_Error( 'no_available_items', __( 'No available items in purchase order.', 'wc-purchase-order-limits' ) );
		}

		// Get customer data.
		$customer = get_user_by( 'id', $user_id );
		if ( ! $customer ) {
			return new WP_Error( 'invalid_customer', __( 'Customer not found.', 'wc-purchase-order-limits' ) );
		}

		try {
			// Create the WooCommerce order.
			$order = wc_create_order(
				array(
					'customer_id' => $user_id,
					'status'      => 'pending', // Pending payment.
				)
			);

			if ( is_wp_error( $order ) ) {
				return $order;
			}

			// Add items to the order.
			foreach ( $available_items as $item ) {
				$product = wc_get_product( $item->product_id );
				if ( ! $product ) {
					continue;
				}

				// Use vendor price if set, otherwise use original price.
				$price = $item->vendor_price ? floatval( $item->vendor_price ) : floatval( $item->price );

				// Add the product to the order.
				$order_item_id = $order->add_product(
					$product,
					$item->quantity,
					array(
						'subtotal' => $price * $item->quantity,
						'total'    => $price * $item->quantity,
					)
				);

				if ( $order_item_id ) {
					// Store vendor ID on the order item for commission tracking.
					wc_add_order_item_meta( $order_item_id, '_vendor_id', $item->vendor_id );
					wc_add_order_item_meta( $order_item_id, '_po_item_id', $item->id );
					wc_add_order_item_meta( $order_item_id, '_po_item_price', $price );
				}
			}

			// Add vendor shipping as fee items.
			$vendor_handler       = new WCPOL_Vendor_Handler();
			$vendor_shipping_rows = $vendor_handler->get_po_vendor_shipping( $po_id );

			if ( ! empty( $vendor_shipping_rows ) ) {
				foreach ( $vendor_shipping_rows as $shipping_row ) {
					if ( floatval( $shipping_row->shipping_amount ) > 0 ) {
						// Get vendor name for the fee label.
						$vendor_name = $this->get_vendor_name( $shipping_row->vendor_id );

						$fee = new WC_Order_Item_Fee();
						$fee->set_name(
							sprintf(
								/* translators: %s: vendor name */
								__( 'Shipping (%s)', 'wc-purchase-order-limits' ),
								$vendor_name
							)
						);
						$fee->set_amount( floatval( $shipping_row->shipping_amount ) );
						$fee->set_total( floatval( $shipping_row->shipping_amount ) );
						$fee->set_tax_status( 'none' );

						$order->add_item( $fee );

						// Store vendor ID on the fee.
						$fee->add_meta_data( '_vendor_id', $shipping_row->vendor_id );
						$fee->add_meta_data( '_shipping_type', 'vendor_shipping' );
						$fee->save();
					}
				}
			}

			// Set customer billing/shipping address from PO data or user meta.
			$this->set_order_addresses( $order, $customer, $po );

			// Add PO metadata to the order.
			$order->add_meta_data( '_wcpol_po_id', $po_id, true );
			$order->add_meta_data( '_wcpol_po_number', $po->po_number, true );

			if ( ! empty( $po->reference ) ) {
				$order->add_meta_data( '_ls_customer_po_number', $po->reference, true );
			}

			// Add order note linking to PO.
			$order->add_order_note(
				sprintf(
					/* translators: %s: PO number */
					__( 'Order created from approved quote %s', 'wc-purchase-order-limits' ),
					$po->po_number
				)
			);

			// Calculate totals.
			$order->calculate_totals();

			// Save the order.
			$order->save();

			$order_id = $order->get_id();

			// Store order ID on the PO record.
			$wpdb->update(
				$wpdb->prefix . 'wcpol_purchase_orders',
				array( 'order_id' => $order_id ),
				array( 'id' => $po_id ),
				array( '%d' ),
				array( '%d' )
			);

			// Add history entry.
			$po_handler->add_history(
				$po_id,
				$user_id,
				'order_created',
				sprintf(
					/* translators: %d: order ID */
					__( 'WooCommerce order #%d created from approved quote', 'wc-purchase-order-limits' ),
					$order_id
				)
			);

			// Fire action for other plugins to hook into.
			do_action( 'wcpol_order_created_from_po', $order_id, $po_id, $user_id );

			return $order_id;

		} catch ( Exception $e ) {
			return new WP_Error( 'order_creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Get the order ID associated with a PO.
	 *
	 * @param int $po_id The purchase order ID.
	 * @return int|null The order ID or null if not found.
	 */
	public function get_order_id_for_po( $po_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d",
				$po_id
			)
		);

		return $order_id ? intval( $order_id ) : null;
	}

	/**
	 * Get the PO ID associated with an order.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return int|null The PO ID or null if not found.
	 */
	public function get_po_id_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$po_id = $order->get_meta( '_wcpol_po_id' );
		return $po_id ? intval( $po_id ) : null;
	}

	/**
	 * Get vendor display name.
	 *
	 * @param int $vendor_id The vendor user ID.
	 * @return string The vendor name.
	 */
	private function get_vendor_name( $vendor_id ) {
		// Try LSV_Vendors first (LS Vendors).
		if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
			return LSV_Vendors::get_vendor_shop_name( $vendor_id );
		}

		// Try WCV_Vendors (WC Vendors or LS Vendors backwards compat alias).
		if ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
			return WCV_Vendors::get_vendor_shop_name( $vendor_id );
		}

		// Fallback to user display name.
		$vendor = get_user_by( 'id', $vendor_id );
		return $vendor ? $vendor->display_name : __( 'Vendor', 'wc-purchase-order-limits' );
	}

	/**
	 * Set billing and shipping addresses on the order.
	 * Uses PO-stored addresses when available, falls back to user meta.
	 *
	 * @param WC_Order $order    The WooCommerce order.
	 * @param WP_User  $customer The customer user object.
	 * @param object   $po       The purchase order record.
	 */
	private function set_order_addresses( $order, $customer, $po = null ) {
		// Try PO-stored addresses first.
		$po_shipping = ( $po && ! empty( $po->shipping_address ) ) ? json_decode( $po->shipping_address, true ) : null;
		$po_billing  = ( $po && ! empty( $po->billing_address ) ) ? json_decode( $po->billing_address, true ) : null;

		// Billing address.
		if ( $po_billing && ! empty( $po_billing['first_name'] ) ) {
			$order->set_billing_first_name( $po_billing['first_name'] );
			$order->set_billing_last_name( $po_billing['last_name'] ?? '' );
			$order->set_billing_email( $po_billing['email'] ?? $customer->user_email );
			$order->set_billing_phone( $po_billing['phone'] ?? '' );
			$order->set_billing_address_1( $po_billing['address_1'] ?? '' );
			$order->set_billing_address_2( $po_billing['address_2'] ?? '' );
			$order->set_billing_city( $po_billing['city'] ?? '' );
			$order->set_billing_state( $po_billing['state'] ?? '' );
			$order->set_billing_postcode( $po_billing['postcode'] ?? '' );
			$order->set_billing_country( $po_billing['country'] ?? '' );
		} else {
			// Fallback to user meta.
			$billing_first_name = get_user_meta( $customer->ID, 'billing_first_name', true );
			$order->set_billing_first_name( $billing_first_name ? $billing_first_name : $customer->first_name );
			$billing_last_name = get_user_meta( $customer->ID, 'billing_last_name', true );
			$order->set_billing_last_name( $billing_last_name ? $billing_last_name : $customer->last_name );
			$order->set_billing_email( $customer->user_email );
			$order->set_billing_phone( get_user_meta( $customer->ID, 'billing_phone', true ) );
			$order->set_billing_address_1( get_user_meta( $customer->ID, 'billing_address_1', true ) );
			$order->set_billing_address_2( get_user_meta( $customer->ID, 'billing_address_2', true ) );
			$order->set_billing_city( get_user_meta( $customer->ID, 'billing_city', true ) );
			$order->set_billing_state( get_user_meta( $customer->ID, 'billing_state', true ) );
			$order->set_billing_postcode( get_user_meta( $customer->ID, 'billing_postcode', true ) );
			$order->set_billing_country( get_user_meta( $customer->ID, 'billing_country', true ) );
			$order->set_billing_company( get_user_meta( $customer->ID, 'billing_company', true ) );
		}

		// Shipping address.
		if ( $po_shipping && ! empty( $po_shipping['first_name'] ) ) {
			$order->set_shipping_first_name( $po_shipping['first_name'] );
			$order->set_shipping_last_name( $po_shipping['last_name'] ?? '' );
			$order->set_shipping_address_1( $po_shipping['address_1'] ?? '' );
			$order->set_shipping_address_2( $po_shipping['address_2'] ?? '' );
			$order->set_shipping_city( $po_shipping['city'] ?? '' );
			$order->set_shipping_state( $po_shipping['state'] ?? '' );
			$order->set_shipping_postcode( $po_shipping['postcode'] ?? '' );
			$order->set_shipping_country( $po_shipping['country'] ?? '' );
			if ( ! empty( $po_shipping['phone'] ) && method_exists( $order, 'set_shipping_phone' ) ) {
				$order->set_shipping_phone( $po_shipping['phone'] );
			}
		} else {
			// Fallback to user meta, then copy billing if no shipping.
			$shipping_first = get_user_meta( $customer->ID, 'shipping_first_name', true );
			if ( $shipping_first ) {
				$order->set_shipping_first_name( $shipping_first );
				$order->set_shipping_last_name( get_user_meta( $customer->ID, 'shipping_last_name', true ) );
				$order->set_shipping_address_1( get_user_meta( $customer->ID, 'shipping_address_1', true ) );
				$order->set_shipping_address_2( get_user_meta( $customer->ID, 'shipping_address_2', true ) );
				$order->set_shipping_city( get_user_meta( $customer->ID, 'shipping_city', true ) );
				$order->set_shipping_state( get_user_meta( $customer->ID, 'shipping_state', true ) );
				$order->set_shipping_postcode( get_user_meta( $customer->ID, 'shipping_postcode', true ) );
				$order->set_shipping_country( get_user_meta( $customer->ID, 'shipping_country', true ) );
				$order->set_shipping_company( get_user_meta( $customer->ID, 'shipping_company', true ) );
			} else {
				// Copy billing to shipping.
				$order->set_shipping_first_name( $order->get_billing_first_name() );
				$order->set_shipping_last_name( $order->get_billing_last_name() );
				$order->set_shipping_address_1( $order->get_billing_address_1() );
				$order->set_shipping_address_2( $order->get_billing_address_2() );
				$order->set_shipping_city( $order->get_billing_city() );
				$order->set_shipping_state( $order->get_billing_state() );
				$order->set_shipping_postcode( $order->get_billing_postcode() );
				$order->set_shipping_country( $order->get_billing_country() );
				$order->set_shipping_company( $order->get_billing_company() );
			}
		}
	}

	/**
	 * Get checkout URL for an order created from a PO.
	 *
	 * @param int $order_id The order ID.
	 * @return string The checkout payment URL.
	 */
	public function get_order_pay_url( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		return $order->get_checkout_payment_url();
	}
}
