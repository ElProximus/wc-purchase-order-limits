<?php
/**
 * Notifications for purchase orders: the site's notification list (LS Authors)
 * when it is active, otherwise BuddyBoss notifications.
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Notifications {

	/**
	 * Whether any notification list is available.
	 *
	 * @return bool
	 */
	private function can_notify() {
		return function_exists( 'ls_authors_notify' ) || function_exists( 'bp_notifications_add_notification' );
	}

	/**
	 * Add one notification (same arguments as bp_notifications_add_notification()).
	 *
	 * @param array $args user_id, item_id, secondary_item_id, component_action, …
	 */
	private function add( array $args ) {
		if ( function_exists( 'ls_authors_notify' ) ) {
			$n = $this->format_notifications( '', $args['item_id'], $args['secondary_item_id'], 1, 'array', $args['component_action'], 'wcpol' );
			ls_authors_notify(
				(int) $args['user_id'],
				'quote',
				array(
					'text'      => is_array( $n ) ? $n['text'] : wp_strip_all_tags( (string) $n ),
					'url'       => is_array( $n ) ? $n['link'] : wc_get_account_endpoint_url( 'purchase-orders' ),
					'object_id' => (int) $args['item_id'],
					'push'      => true,
				)
			);
			return;
		}
		bp_notifications_add_notification( $args );
	}

	/**
	 * Register component with BuddyBoss
	 */
	public function register_component( $components ) {
		$components[] = 'wcpol';
		return $components;
	}

	/**
	 * Format notifications for display
	 */
	public function format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string', $component_action_name = '', $component_name = '', $id = 0 ) {

		if ( 'wcpol' !== $component_name ) {
			return $action;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po_number  = $po_handler->get_po_number( $item_id );
		$po_url     = wc_get_account_endpoint_url( 'purchase-orders' ) . $item_id;

		switch ( $component_action_name ) {
			case 'po_created':
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'Your Purchase Order Request %s has been created successfully.', 'wc-purchase-order-limits' ),
					$po_number
				);
				break;

			case 'po_approved':
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'You approved Purchase Order %s!', 'wc-purchase-order-limits' ),
					$po_number
				);
				break;

			case 'po_rejected':
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'Your Purchase Order %s has been rejected. Please contact support for details.', 'wc-purchase-order-limits' ),
					$po_number
				);
				break;

			case 'new_po_request':
				$text   = sprintf(
					/* translators: %s: PO number */
					__( 'Congratulations you have a new purchase order request %s.', 'wc-purchase-order-limits' ),
					$po_number
				);
				$po_url = home_url( '/vendor_dashboard/purchase_orders/?po_id=' . $item_id );
				break;

			case 'po_item_updated':
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'Purchase Order %s has been updated by a vendor.', 'wc-purchase-order-limits' ),
					$po_number
				);
				break;

			case 'vendor_po_created':
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'You have been issued a new Purchase Order to review %s', 'wc-purchase-order-limits' ),
					$po_number
				);
				break;

			case 'vendor_po_approved':
				// Simplified message without customer reference.
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'Congratulations I approved purchase order %s. I am ready to proceed and finalize purchase.', 'wc-purchase-order-limits' ),
					$po_number
				);
				// Don't create hyperlink - just return the text
				return $text;

			default:
				$text = sprintf(
					/* translators: %s: PO number */
					__( 'Purchase Order %s update', 'wc-purchase-order-limits' ),
					$po_number
				);
		}

		if ( 'string' === $format ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $po_url ), esc_html( $text ) );
		} else {
			return array(
				'text' => $text,
				'link' => $po_url,
			);
		}
	}

	/**
	 * Send notification when PO is created
	 */
	public function notify_customer_po_created( $po_id, $user_id ) {
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		$this->add(
			array(
				'user_id'           => $user_id,
				'item_id'           => $po_id,
				'secondary_item_id' => 1, // Admin user ID so notification appears from admin
				'component_name'    => 'wcpol',
				'component_action'  => 'po_created',
				'date_notified'     => current_time( 'mysql', true ),
				'is_new'            => 1,
			)
		);
	}

	/**
	 * Send notification when PO is approved
	 */
	public function notify_customer_po_approved( $po_id, $user_id ) {
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		$this->add(
			array(
				'user_id'           => $user_id,
				'item_id'           => $po_id,
				'secondary_item_id' => 1, // Admin user ID so notification appears from admin
				'component_name'    => 'wcpol',
				'component_action'  => 'po_approved',
				'date_notified'     => current_time( 'mysql', true ),
				'is_new'            => 1,
			)
		);
	}

	/**
	 * Send notification when PO is rejected
	 */
	public function notify_customer_po_rejected( $po_id, $user_id ) {
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		$this->add(
			array(
				'user_id'           => $user_id,
				'item_id'           => $po_id,
				'secondary_item_id' => 0,
				'component_name'    => 'wcpol',
				'component_action'  => 'po_rejected',
				'date_notified'     => current_time( 'mysql', true ),
				'is_new'            => 1,
			)
		);
	}

	/**
	 * Send notification to vendor for new PO
	 */
	public function notify_vendor_new_po( $po_id, $vendor_id, $product_ids ) {
		// TEMPORARILY DISABLED: Vendor notifications for new PO requests
		// Reason: Vendor dashboard not ready for mobile app experience yet
		// To re-enable: Uncomment the code below
		/*
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		// Only notify if vendor has products in this PO
		if ( empty( $product_ids ) ) {
			return;
		}

		$this->add(
			array(
				'user_id'           => $vendor_id,
				'item_id'           => $po_id,
				'secondary_item_id' => 1, // Admin user ID so notification appears from admin
				'component_name'    => 'wcpol',
				'component_action'  => 'new_po_request',
				'date_notified'     => current_time( 'mysql', true ),
				'is_new'            => 1,
			)
		);
		*/
	}

	/**
	 * Mark notifications as read when viewing PO
	 */
	public function mark_notifications_read( $po_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( function_exists( 'ls_authors_mark_object_read' ) ) {
			ls_authors_mark_object_read( $user_id, 'quote', $po_id );
		}

		if ( ! function_exists( 'bp_notifications_mark_notifications_by_item_id' ) ) {
			return;
		}

		bp_notifications_mark_notifications_by_item_id(
			$user_id,
			$po_id,
			'wcpol',
			false
		);
	}

	/**
	 * Delete notifications when PO is deleted
	 */
	public function delete_po_notifications( $po_id ) {
		if ( ! function_exists( 'bp_notifications_delete_notifications_by_item_id' ) ) {
			return;
		}

		bp_notifications_delete_notifications_by_item_id(
			false,
			$po_id,
			'wcpol'
		);
	}

	/**
	 * Send notification when vendor creates and sends PO to customer
	 */
	public function notify_customer_vendor_po_created( $po_id, $user_id, $vendor_id ) {
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		$this->add(
			array(
				'user_id'           => $user_id,
				'item_id'           => $po_id,
				'secondary_item_id' => $vendor_id,
				'component_name'    => 'wcpol',
				'component_action'  => 'vendor_po_created',
				'date_notified'     => current_time( 'mysql', true ),
				'is_new'            => 1,
			)
		);
	}

	/**
	 * Send notifications to vendors when customer approves PO
	 */
	public function notify_vendors_po_approved( $po_id, $customer_id ) {
		if ( ! $this->can_notify() ) {
			return;
		}

		if ( 'yes' !== get_option( 'wcpol_enable_bb_notifications', 'yes' ) ) {
			return;
		}

		global $wpdb;

		// Get all vendors associated with this PO
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

		// Get PO details for message content
		$po_handler    = new WCPOL_Purchase_Order();
		$po            = $po_handler->get_po( $po_id );
		$po_number     = $po->po_number;
		$customer      = get_user_by( 'id', $customer_id );
		$customer_name = $customer ? $customer->display_name : 'Customer';

		// Send notification to each vendor
		foreach ( $vendors as $vendor_id ) {
			$this->add(
				array(
					'user_id'           => $vendor_id,
					'item_id'           => $po_id,
					'secondary_item_id' => $customer_id, // Customer ID for message creation
					'component_name'    => 'wcpol',
					'component_action'  => 'vendor_po_approved',
					'date_notified'     => current_time( 'mysql', true ),
					'is_new'            => 1,
				)
			);

			// Create BuddyBoss message if messaging is available
			if ( function_exists( 'messages_new_message' ) ) {
				// Prepare message content with simplified approval message.
				$message_subject = sprintf(
					/* translators: %s: PO number */
					__( 'Purchase Order %s Approved', 'wc-purchase-order-limits' ),
					$po_number
				);
				$message_content = sprintf(
					/* translators: 1: PO number, 2: customer name, 3: customer email */
					__(
						"Congratulations I approved purchase order %s. I am ready to proceed and finalize purchase.\n\n" .
						"Customer Details:\n" .
						"Name: %s\n" .
						"Email: %s\n\n" .
						'Thanks',
						'wc-purchase-order-limits'
					),
					$po_number,
					$customer_name,
					$customer->user_email
				);

				// Send the message from customer to vendor
				messages_new_message(
					array(
						'sender_id'  => $customer_id,
						'recipients' => array( $vendor_id ),
						'subject'    => $message_subject,
						'content'    => $message_content,
					)
				);
			}
		}
	}
}
