<?php
/**
 * Email notifications handler
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Email_Handler {

	/**
	 * Send customer PO created email
	 */
	public function send_customer_po_created( $po_id, $user_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$user       = get_user_by( 'id', $user_id );

		if ( ! $po || ! $user ) {
			return;
		}

		$to = $user->user_email;
		/* translators: %s: Quote number */
		$subject = sprintf( __( 'Quote %s Requested', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: user display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$user->display_name
		);
		$message .= sprintf(
			/* translators: %s: Quote number */
			__( '<p>Your quote <strong>%s</strong> has been requested.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);
		$message .= '<p>' . __( 'You will be notified when your quote is issued.', 'wc-purchase-order-limits' ) . '</p>';

		$message .= '<h3>' . __( 'Order Details:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= $this->get_po_items_table( $po_id );

		$message .= '<p><a href="' . wc_get_account_endpoint_url( 'purchase-orders' ) . '" style="background-color: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'View Quote', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send admin notification when a new quote is requested.
	 *
	 * @param int $po_id   The purchase order ID.
	 * @param int $user_id The user who submitted the quote.
	 */
	public function send_admin_po_created( $po_id, $user_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_admin_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$user       = get_user_by( 'id', $user_id );

		if ( ! $po || ! $user ) {
			return;
		}

		$to = get_option( 'admin_email' );
		/* translators: %s: Quote number */
		$subject = sprintf( __( 'New Quote Request %s', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: 1: customer display name, 2: quote number */
			__( '<p>A new quote <strong>%2$s</strong> has been requested by %1$s.</p>', 'wc-purchase-order-limits' ),
			$user->display_name,
			$po->po_number
		);

		$message .= '<h3>' . __( 'Order Details:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= $this->get_po_items_table( $po_id );

		// Shipping address.
		if ( ! empty( $po->shipping_address ) ) {
			$shipping = json_decode( $po->shipping_address, true );
			if ( $shipping ) {
				$message .= '<h3>' . __( 'Shipping Address:', 'wc-purchase-order-limits' ) . '</h3>';
				$message .= '<p>';
				$message .= esc_html( ( $shipping['first_name'] ?? '' ) . ' ' . ( $shipping['last_name'] ?? '' ) ) . '<br>';
				$message .= esc_html( $shipping['address_1'] ?? '' );
				if ( ! empty( $shipping['address_2'] ) ) {
					$message .= '<br>' . esc_html( $shipping['address_2'] );
				}
				$message .= '<br>' . esc_html( ( $shipping['city'] ?? '' ) . ', ' . ( $shipping['state'] ?? '' ) . ' ' . ( $shipping['postcode'] ?? '' ) );
				$message .= '</p>';
			}
		}

		$message .= '<p><a href="' . admin_url( 'admin.php?page=wcpol-purchase-orders' ) . '" style="background-color: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'View in Dashboard', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send vendor PO notification
	 */
	public function send_vendor_po_notification( $vendor_id, $po_id, $vendor_items ) {
		if ( 'yes' !== get_option( 'wcpol_enable_vendor_emails', 'yes' ) ) {
			return;
		}

		$vendor     = get_user_by( 'id', $vendor_id );
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $vendor || ! $po ) {
			return;
		}

		$to = $vendor->user_email;
		/* translators: %s: Order number */
		$subject = sprintf( __( 'New Order %s Request', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: vendor display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$vendor->display_name
		);
		$message .= sprintf(
			/* translators: %s: Order number */
			__( '<p>A new request <strong>%s</strong> has been submitted that includes your products.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);

		$message .= '<h3>' . __( 'Your Items:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= '<table style="width: 100%; border-collapse: collapse;">';
		$message .= '<tr style="background-color: #f2f2f2;">';
		$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'wc-purchase-order-limits' ) . '</th>';
		$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'SKU', 'wc-purchase-order-limits' ) . '</th>';
		$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'wc-purchase-order-limits' ) . '</th>';
		$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Price', 'wc-purchase-order-limits' ) . '</th>';
		$message .= '</tr>';

		foreach ( $vendor_items as $item ) {
			$product = wc_get_product( $item->product_id );
			if ( ! $product ) {
				continue;
			}

			$message .= '<tr>';
			$sku      = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
			$message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $product->get_name() ) . '</td>';
			$message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ( '' !== $sku ? esc_html( $sku ) : '—' ) . '</td>';
			$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item->quantity . '</td>';
			$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $item->price ) . '</td>';
			$message .= '</tr>';
		}
		$message .= '</table>';

		$vendor_dashboard_url = home_url( '/vendor-dashboard/purchase-orders/' );
		$message             .= '<p><a href="' . $vendor_dashboard_url . '" style="background-color: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block; margin-top: 20px;">' . __( 'Review Quote', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send PO approved email (for admin approval)
	 */
	public function send_po_approved_email( $po_id, $user_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$user       = get_user_by( 'id', $user_id );

		if ( ! $po || ! $user ) {
			return;
		}

		$to = $user->user_email;
		/* translators: %s: Quote number */
		$subject = sprintf( __( 'Quote %s Issued', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: user display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$user->display_name
		);
		$message .= sprintf(
			/* translators: %s: Quote number */
			__( '<p>Great news! Your quote <strong>%s</strong> has been issued.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);
		$message .= '<p>' . __( 'You can now download the order PDF from your account.', 'wc-purchase-order-limits' ) . '</p>';

		$po_url   = wc_get_account_endpoint_url( 'purchase-orders' ) . $po_id;
		$message .= '<p><a href="' . $po_url . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'Download Quote', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send customer confirmation email when they approve a PO
	 */
	public function send_customer_po_approval_confirmation( $po_id, $customer_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$customer   = get_user_by( 'id', $customer_id );

		if ( ! $po || ! $customer ) {
			return;
		}

		$to = $customer->user_email;
		/* translators: %s: Order number */
		$subject = sprintf( __( 'You Approved Order %s', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: customer display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$customer->display_name
		);
		$message .= sprintf(
			/* translators: %s: Order number */
			__( '<p>This email confirms that you have approved your order <strong>%s</strong>.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);

		$message .= '<div style="padding: 15px; background-color: #e8f5e9; border-left: 4px solid #4caf50; margin: 20px 0;">';
		$message .= '<p style="margin: 0;"><strong>' . __( 'Important:', 'wc-purchase-order-limits' ) . '</strong></p>';
		$message .= '<p style="margin: 10px 0 0 0;">' . __( 'You will be contacted by the vendor(s) with payment instructions. Your order is not complete until you receive confirmation and complete payment.', 'wc-purchase-order-limits' ) . '</p>';
		$message .= '</div>';

		$message .= '<h3>' . __( 'Order Summary:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= $this->get_po_items_table_with_vendor_prices( $po_id );

		$message .= '<h3>' . __( 'What Happens Next:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= '<ol>';
		$message .= '<li>' . __( 'Vendors have been notified of your approval', 'wc-purchase-order-limits' ) . '</li>';
		$message .= '<li>' . __( 'You will receive payment instructions from the vendor(s)', 'wc-purchase-order-limits' ) . '</li>';
		$message .= '<li>' . __( 'Complete payment as instructed', 'wc-purchase-order-limits' ) . '</li>';
		$message .= '<li>' . __( 'Vendor(s) will arrange delivery of your items', 'wc-purchase-order-limits' ) . '</li>';
		$message .= '</ol>';

		$po_url   = wc_get_account_endpoint_url( 'purchase-orders' ) . $po_id;
		$message .= '<p><a href="' . $po_url . '" style="background-color: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'View Quote', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send PO rejected email
	 */
	public function send_po_rejected_email( $po_id, $user_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$user       = get_user_by( 'id', $user_id );

		if ( ! $po || ! $user ) {
			return;
		}

		$to = $user->user_email;
		/* translators: %s: Quote number */
		$subject = sprintf( __( 'Quote %s Rejected', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: user display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$user->display_name
		);
		$message .= sprintf(
			/* translators: %s: Quote number */
			__( '<p>Unfortunately, your quote <strong>%s</strong> has been rejected.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);
		$message .= '<p>' . __( 'Please contact our support team for more information or to discuss alternatives.', 'wc-purchase-order-limits' ) . '</p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Get PO items table for email
	 */
	private function get_po_items_table( $po_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po_items   = $po_handler->get_po_items( $po_id );

		$table  = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
		$table .= '<tr style="background-color: #f2f2f2;">';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'SKU', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Price', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Total', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '</tr>';

		foreach ( $po_items as $item ) {
			$product = wc_get_product( $item->product_id );
			if ( ! $product ) {
				continue;
			}

			$table .= '<tr>';
			$sku    = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $product->get_name() ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ( '' !== $sku ? esc_html( $sku ) : '—' ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item->quantity . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $item->price ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $item->total ) . '</td>';
			$table .= '</tr>';
		}

		$table .= '</table>';

		return $table;
	}

	/**
	 * Get email header
	 */
	private function get_email_header( $title ) {
		$header  = '<!DOCTYPE html>';
		$header .= '<html>';
		$header .= '<head>';
		$header .= '<meta charset="UTF-8">';
		$header .= '<title>' . $title . '</title>';
		$header .= '</head>';
		$header .= '<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">';
		$header .= '<div style="background-color: #f7f7f7; padding: 20px; border-radius: 5px;">';
		$header .= '<h1 style="color: #0073aa; margin: 0 0 20px 0;">' . $title . '</h1>';

		return $header;
	}

	/**
	 * Get email footer
	 */
	private function get_email_footer() {
		$footer  = '<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">';
		$footer .= '<p style="font-size: 12px; color: #666;">';
		$footer .= sprintf(
			/* translators: %s: site name */
			__( 'This email was sent from %s', 'wc-purchase-order-limits' ),
			$this->sanitize_email_header( get_bloginfo( 'name' ) )
		);
		$footer .= '</p>';
		$footer .= '</div>';
		$footer .= '</body>';
		$footer .= '</html>';

		return $footer;
	}

	/**
	 * Sanitize text for use in email headers
	 * Prevents email header injection attacks
	 */
	private function sanitize_email_header( $text ) {
		// Remove line breaks and control characters that could be used for header injection
		$text = str_replace( array( "\r", "\n", "\t", '%0a', '%0d', '%0A', '%0D' ), '', $text );

		// Remove any remaining control characters
		$text = preg_replace( '/[\x00-\x1F\x7F]/', '', $text );

		// Trim whitespace
		$text = trim( $text );

		// If empty after sanitization, return a default value
		if ( empty( $text ) ) {
			$text = 'WordPress Site';
		}

		return $text;
	}

	/**
	 * Send email
	 */
	private function send_email( $to, $subject, $message ) {
		// Sanitize the site name to prevent header injection
		$site_name = $this->sanitize_email_header( get_bloginfo( 'name' ) );

		// Validate and sanitize admin email
		$admin_email = sanitize_email( get_option( 'admin_email' ) );
		if ( ! is_email( $admin_email ) ) {
			// Fallback to a safe default if admin email is invalid
			$admin_email = 'noreply@' . parse_url( home_url(), PHP_URL_HOST );
		}

		// Sanitize the subject line as well
		$subject = $this->sanitize_email_header( $subject );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . $admin_email . '>',
		);

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send customer notification when vendor creates PO
	 */
	public function send_customer_vendor_po_notification( $user_id, $po_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_customer_emails', 'yes' ) ) {
			return;
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$user       = get_user_by( 'id', $user_id );

		if ( ! $po || ! $user ) {
			return;
		}

		$to = $user->user_email;
		/* translators: %s: Quote number */
		$subject = sprintf( __( 'New Quote %s', 'wc-purchase-order-limits' ), $po->po_number );

		$message  = $this->get_email_header( $subject );
		$message .= sprintf(
			/* translators: %s: user display name */
			__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
			$user->display_name
		);
		$message .= sprintf(
			/* translators: %s: Quote number */
			__( '<p>You have a new quote <strong>%s</strong> with updated pricing for your review.</p>', 'wc-purchase-order-limits' ),
			$po->po_number
		);
		$message .= '<p>' . __( 'The vendor(s) has reviewed your request and provided their pricing for the items.', 'wc-purchase-order-limits' ) . '</p>';

		$message .= '<h3>' . __( 'Order Details:', 'wc-purchase-order-limits' ) . '</h3>';
		$message .= $this->get_po_items_table_with_vendor_prices( $po_id );

		$message .= '<p><a href="' . wc_get_account_endpoint_url( 'purchase-orders' ) . $po_id . '" style="background-color: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'View Quote', 'wc-purchase-order-limits' ) . '</a></p>';

		$message .= $this->get_email_footer();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send vendor notification when customer approves PO
	 */
	public function send_vendor_po_approved_email( $po_id, $customer_id ) {
		if ( 'yes' !== get_option( 'wcpol_enable_vendor_emails', 'yes' ) ) {
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

		// Get PO details
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$customer   = get_user_by( 'id', $customer_id );

		if ( ! $po || ! $customer ) {
			return;
		}

		// Send email to each vendor
		foreach ( $vendors as $vendor_id ) {
			$vendor = get_user_by( 'id', $vendor_id );
			if ( ! $vendor ) {
				continue;
			}

			// Get vendor's items
			$vendor_handler = new WCPOL_Vendor_Handler();
			$vendor_items   = $vendor_handler->get_vendor_po_items( $vendor_id, $po_id );

			$to = $vendor->user_email;
			/* translators: %s: Order number */
			$subject = sprintf( __( 'Quote Approved by Customer - %s', 'wc-purchase-order-limits' ), $po->po_number );

			$message  = $this->get_email_header( $subject );
			$message .= sprintf(
				/* translators: %s: vendor display name */
				__( '<p>Hello %s,</p>', 'wc-purchase-order-limits' ),
				$vendor->display_name
			);
			$message .= '<p style="font-size: 18px; color: #4CAF50;"><strong>' . __( 'Congratulations!', 'wc-purchase-order-limits' ) . '</strong></p>';
			$message .= sprintf(
				/* translators: %s: Order number */
				__( '<p>I approved the order <strong>%s</strong>. I am ready to proceed and finalize purchase.</p>', 'wc-purchase-order-limits' ),
				$po->po_number
			);
			$message .= '<p>' . __( 'Please proceed with delivery and payment arrangements.', 'wc-purchase-order-limits' ) . '</p>';

			$message .= '<h3>' . __( 'Customer Information:', 'wc-purchase-order-limits' ) . '</h3>';
			$message .= '<ul>';
			$message .= '<li><strong>' . __( 'Name:', 'wc-purchase-order-limits' ) . '</strong> ' . $customer->display_name . '</li>';
			$message .= '<li><strong>' . __( 'Email:', 'wc-purchase-order-limits' ) . '</strong> <a href="mailto:' . $customer->user_email . '">' . $customer->user_email . '</a></li>';

			// Add phone if available
			$phone = get_user_meta( $customer_id, 'billing_phone', true );
			if ( $phone ) {
				$message .= '<li><strong>' . __( 'Phone:', 'wc-purchase-order-limits' ) . '</strong> ' . $phone . '</li>';
			}
			$message .= '</ul>';

			$message .= '<h3>' . __( 'Your Approved Items:', 'wc-purchase-order-limits' ) . '</h3>';
			$message .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
			$message .= '<tr style="background-color: #f2f2f2;">';
			$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'wc-purchase-order-limits' ) . '</th>';
			$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'SKU', 'wc-purchase-order-limits' ) . '</th>';
			$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'wc-purchase-order-limits' ) . '</th>';
			$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Your Price', 'wc-purchase-order-limits' ) . '</th>';
			$message .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Total', 'wc-purchase-order-limits' ) . '</th>';
			$message .= '</tr>';

			$vendor_total = 0;
			foreach ( $vendor_items as $item ) {
				$product = wc_get_product( $item->product_id );
				if ( ! $product ) {
					continue;
				}

				$price         = $item->vendor_price ? $item->vendor_price : $item->price;
				$total         = $item->total;
				$vendor_total += $total;

				$message .= '<tr>';
				$sku      = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
				$message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $product->get_name() ) . '</td>';
				$message .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ( '' !== $sku ? esc_html( $sku ) : '—' ) . '</td>';
				$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item->quantity . '</td>';
				$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $price ) . '</td>';
				$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $total ) . '</td>';
				$message .= '</tr>';
			}

			$message .= '<tr style="background-color: #f9f9f9; font-weight: bold;">';
			$message .= '<td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Your Total:', 'wc-purchase-order-limits' ) . '</td>';
			$message .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $vendor_total ) . '</td>';
			$message .= '</tr>';
			$message .= '</table>';

			$vendor_dashboard_url = home_url( '/vendor_dashboard/purchase_orders/?po_id=' . $po_id );
			$message             .= '<p><a href="' . $vendor_dashboard_url . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __( 'View Details', 'wc-purchase-order-limits' ) . '</a></p>';

			$message .= '<p style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107;">';
			$message .= '<strong>' . __( 'Next Steps:', 'wc-purchase-order-limits' ) . '</strong><br>';
			$message .= __( '1. Contact the customer to arrange delivery<br>', 'wc-purchase-order-limits' );
			$message .= __( '2. Coordinate payment method and terms<br>', 'wc-purchase-order-limits' );
			$message .= __( '3. Prepare the order for shipment', 'wc-purchase-order-limits' );
			$message .= '</p>';

			$message .= $this->get_email_footer();

			$this->send_email( $to, $subject, $message );
		}
	}

	/**
	 * Get PO items table with vendor prices for email
	 */
	private function get_po_items_table_with_vendor_prices( $po_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po_items   = $po_handler->get_po_items( $po_id );

		$table  = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
		$table .= '<tr style="background-color: #f2f2f2;">';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'SKU', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Vendor Price', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Total', 'wc-purchase-order-limits' ) . '</th>';
		$table .= '</tr>';

		$grand_total = 0;
		foreach ( $po_items as $item ) {
			$product = wc_get_product( $item->product_id );
			if ( ! $product ) {
				continue;
			}

			$price        = $item->vendor_price ? $item->vendor_price : $item->price;
			$total        = $price * $item->quantity;
			$grand_total += $total;

			$table .= '<tr>';
			$sku    = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $product->get_name() ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ( '' !== $sku ? esc_html( $sku ) : '—' ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item->quantity . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $price ) . '</td>';
			$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $total ) . '</td>';
			$table .= '</tr>';
		}

		// Add vendor shipping rows.
		$vendor_handler       = new WCPOL_Vendor_Handler();
		$vendor_shipping_rows = $vendor_handler->get_po_vendor_shipping( $po_id );

		if ( ! empty( $vendor_shipping_rows ) ) {
			foreach ( $vendor_shipping_rows as $shipping_row ) {
				if ( floatval( $shipping_row->shipping_amount ) > 0 ) {
					// Get vendor shop name (LS Vendors or WC Vendors).
					$shipping_vendor_name = __( 'Shop', 'wc-purchase-order-limits' );
					if ( $shipping_row->vendor_id > 0 ) {
						if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
							$shipping_vendor_name = LSV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
						} elseif ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
							$shipping_vendor_name = WCV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
						}
					}

					$table .= '<tr style="background-color: #f9f9f9;">';
					$table .= '<td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right;">';
					/* translators: %s: vendor name */
					$table .= sprintf( __( 'Shipping (%s):', 'wc-purchase-order-limits' ), esc_html( $shipping_vendor_name ) );
					$table .= '</td>';
					$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $shipping_row->shipping_amount ) . '</td>';
					$table .= '</tr>';

					$grand_total += floatval( $shipping_row->shipping_amount );
				}
			}
		}

		$table .= '<tr style="background-color: #f2f2f2; font-weight: bold;">';
		$table .= '<td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Total:', 'wc-purchase-order-limits' ) . '</td>';
		$table .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $grand_total ) . '</td>';
		$table .= '</tr>';

		$table .= '</table>';

		return $table;
	}
}
