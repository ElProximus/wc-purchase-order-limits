<?php
/**
 * Purchase Order Request Handler
 *
 * Handles the dedicated PO request page functionality
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_PO_Request_Handler {

	/**
	 * Get the PO request page URL
	 * Always uses cart page with parameter for simplicity
	 */
	public function get_po_request_page_url() {
		return add_query_arg( 'po_request', '1', wc_get_cart_url() );
	}

	/**
	 * Check if we're on the PO request page
	 * Simply checks for the URL parameter
	 */
	public function is_po_request_page() {
		return isset( $_GET['po_request'] ) && '1' === $_GET['po_request'];
	}

	/**
	 * Redirect to PO request page if cart exceeds limit when accessing checkout
	 */
	public function maybe_redirect_to_po_request() {
		// Only check on checkout pages (both standard WooCommerce and custom fullscreen)
		$is_checkout = is_checkout() ||
						is_page_template( 'page-templates/fullscreen-shop-checkout.php' );

		if ( ! $is_checkout ) {
			return;
		}

		// Don't redirect if already on PO request page
		if ( $this->is_po_request_page() ) {
			return;
		}

		// Don't redirect on AJAX requests
		if ( wp_doing_ajax() ) {
			return;
		}

		// Don't redirect in admin
		if ( is_admin() ) {
			return;
		}

		// Check if limits are enabled
		if ( get_option( 'wcpol_enable_limits', 'yes' ) !== 'yes' ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if cart exceeds limit
		$cart_validator = new WCPOL_Cart_Validator();
		if ( $cart_validator->is_cart_over_limit() ) {
			// Redirect to PO request page - no notice needed as the page explains everything
			wp_safe_redirect( $this->get_po_request_page_url() );
			exit;
		}
	}

	/**
	 * Render the PO request form
	 */
	public function render_po_request_form( $args = array() ) {

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to request a quote.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}
		// Check if cart is empty
		if ( WC()->cart->is_empty() ) {
			echo '<p>' . esc_html__( 'Your cart is empty.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}

		$cart_validator = new WCPOL_Cart_Validator();
		$limit          = $cart_validator->get_user_limit();
		$cart_total     = $cart_validator->get_cart_total_for_limit();
		$is_over_limit  = $cart_validator->is_cart_over_limit();
		// Allow shortcode to force voluntary flow
		$voluntary_override = isset( $args['voluntary'] ) ? (bool) $args['voluntary'] : null;
		$is_voluntary       = is_null( $voluntary_override ) ? ( isset( $_GET['voluntary'] ) && '1' === $_GET['voluntary'] ) : $voluntary_override;
		// If not over limit and not voluntary, redirect to checkout
		if ( ! $is_over_limit && ! $is_voluntary ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Check if voluntary PO is allowed when under limit
		if ( ! $is_over_limit && $is_voluntary ) {
			if ( get_option( 'wcpol_allow_voluntary_po', 'no' ) !== 'yes' ) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
		}

		?>
		<div class="wcpol-po-request-wrapper">
			<div class="wcpol-po-request-header">
				<h2><?php esc_html_e( 'Quote Request', 'wc-purchase-order-limits' ); ?></h2>
				<?php if ( $is_voluntary ) : ?>
					<div class="wcpol-voluntary-notice">
						<p>
							<?php
							printf(
								/* translators: %s: cart total price */
								esc_html__( 'You have chosen to request a quote for this order. Your cart total is %s.', 'wc-purchase-order-limits' ),
								wc_price( $cart_total )
							);
							?>
						</p>
						<p><?php esc_html_e( 'Complete the form below to submit your request.', 'wc-purchase-order-limits' ); ?></p>
					</div>
				<?php else : ?>
					<div class="wcpol-limit-exceeded-notice">
						<p>
							<?php
							printf(
								/* translators: 1: cart total, 2: purchase limit */
								esc_html__( 'Your cart total of %1$s exceeds your purchase limit of %2$s.', 'wc-purchase-order-limits' ),
								wc_price( $cart_total ),
								wc_price( $limit )
							);
							?>
						</p>
						<p><?php esc_html_e( 'Please request a quote or contact us to be approved for larger purchases.', 'wc-purchase-order-limits' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<div class="wcpol-po-request-content">
				<div class="wcpol-cart-summary">
					<h3><?php esc_html_e( 'Order Summary', 'wc-purchase-order-limits' ); ?></h3>
					<table class="wcpol-cart-items">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'wc-purchase-order-limits' ); ?></th>
								<th><?php esc_html_e( 'Quantity', 'wc-purchase-order-limits' ); ?></th>
								<th><?php esc_html_e( 'Price', 'wc-purchase-order-limits' ); ?></th>
								<th><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
								$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

								if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) {
									$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
									?>
									<tr>
										<td class="product-name">
											<?php
											if ( ! $product_permalink ) {
												echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
											} else {
												echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
											}
											?>
										</td>
										<td class="product-quantity">
											<?php echo esc_html( $cart_item['quantity'] ); ?>
										</td>
										<td class="product-price">
											<?php echo WC()->cart->get_product_price( $_product ); ?>
										</td>
										<td class="product-subtotal">
											<?php echo WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ); ?>
										</td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr class="cart-subtotal">
								<th colspan="3"><?php esc_html_e( 'Subtotal', 'wc-purchase-order-limits' ); ?></th>
								<td><?php echo wc_price( WC()->cart->get_subtotal() ); ?></td>
							</tr>
							<tr class="shipping">
								<th colspan="3"><?php esc_html_e( 'Shipping', 'wc-purchase-order-limits' ); ?></th>
								<td><em><?php esc_html_e( 'To be determined by vendor(s)', 'wc-purchase-order-limits' ); ?></em></td>
							</tr>
							<tr class="order-total">
								<th colspan="3"><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
								<td><strong><?php echo wc_price( WC()->cart->get_subtotal() ); ?></strong></td>
							</tr>
						</tfoot>
					</table>
				</div>

				<form id="wcpol-po-request-form" class="wcpol-po-form">
					<?php $this->render_address_sections(); ?>

					<div class="wcpol-form-section">
						<h3><?php esc_html_e( 'Additional Information', 'wc-purchase-order-limits' ); ?></h3>

						<div class="wcpol-form-row">
							<label for="wcpol_po_reference">
								<?php esc_html_e( 'Your PO Number / Reference (Optional)', 'wc-purchase-order-limits' ); ?>
							</label>
							<input type="text" id="wcpol_po_reference" name="reference" placeholder="<?php esc_attr_e( 'e.g., PO-12345 or department budget code', 'wc-purchase-order-limits' ); ?>" />
						</div>

						<div class="wcpol-form-row">
							<label for="wcpol_po_notes">
								<?php esc_html_e( 'Notes or Special Instructions (Optional)', 'wc-purchase-order-limits' ); ?>
							</label>
							<textarea id="wcpol_po_notes" name="notes" rows="4" placeholder="<?php esc_attr_e( 'Any additional information for this order...', 'wc-purchase-order-limits' ); ?>"></textarea>
						</div>

						<div class="wcpol-form-row">
							<label for="wcpol_po_required_date">
								<?php esc_html_e( 'Requested Delivery (Optional)', 'wc-purchase-order-limits' ); ?>
							</label>
							<input type="date" id="wcpol_po_required_date" name="required_date" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" max="9999-12-31" />
						</div>
					</div>

					<div class="wcpol-form-actions">
						<button type="submit" class="button alt wcpol-submit-po">
							<?php esc_html_e( 'Submit Request', 'wc-purchase-order-limits' ); ?>
						</button>
						<?php if ( $is_voluntary ) : ?>
							<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button wcpol-back-to-checkout">
								<?php esc_html_e( 'Back to Checkout', 'wc-purchase-order-limits' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<?php wp_nonce_field( 'wcpol_po_request', 'wcpol_po_nonce' ); ?>
					<input type="hidden" name="is_voluntary" value="<?php echo esc_attr( $is_voluntary ? '1' : '0' ); ?>">
				</form>

				<div id="wcpol-po-message" class="wcpol-message" style="display: none;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Register shortcodes
	 */
	public function register_shortcodes() {

		add_shortcode( 'wcpol_po_request', array( $this, 'shortcode_po_request' ) );
	}

	/**
	 * Shortcode handler: [wcpol_po_request]
	 * Attributes:
	 * - voluntary ("1"|"0"): treat as voluntary when under limit. Default "1".
	 */
	public function shortcode_po_request( $atts = array() ) {

		$atts = shortcode_atts( array( 'voluntary' => '1' ), $atts, 'wcpol_po_request' );
		ob_start();
		$this->render_po_request_form( array( 'voluntary' => ( '1' === $atts['voluntary'] || 1 === $atts['voluntary'] || true === $atts['voluntary'] ) ) );
		return ob_get_clean();
	}

	/**
	 * Handle AJAX PO request submission
	 */
	public function ajax_submit_po_request() {
		// Enhanced security checks with rate limiting (max 5 PO requests per minute)
		WCPOL_Ajax_Security::verify_request(
			'wcpol_submit_po_request',
			array( 'read' ),
			array(
				'nonce_action' => 'wcpol_po_request',
			)
		);

		// Check if cart is empty
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Your cart is empty.', 'wc-purchase-order-limits' ) );
		}

		// Check if this is a voluntary PO request
		$is_voluntary = isset( $_POST['is_voluntary'] ) && '1' === $_POST['is_voluntary'];

		// Check if over limit or voluntary PO is allowed
		$cart_validator = new WCPOL_Cart_Validator();
		if ( ! $cart_validator->is_cart_over_limit() ) {
			// If not over limit, check if voluntary PO is allowed
			if ( ! $is_voluntary || get_option( 'wcpol_allow_voluntary_po', 'no' ) !== 'yes' ) {
				wp_send_json_error( __( 'Your cart does not exceed the purchase limit.', 'wc-purchase-order-limits' ) );
			}
		}

		// Get form data.
		$reference     = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$notes         = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$required_date = isset( $_POST['required_date'] ) ? sanitize_text_field( wp_unslash( $_POST['required_date'] ) ) : '';

		// Get and validate address data.
		$addresses = $this->get_submitted_addresses();

		$required_address_fields = array( 'first_name', 'last_name', 'address_1', 'city', 'state', 'postcode' );
		$missing_fields          = array();
		foreach ( $required_address_fields as $field ) {
			if ( empty( $addresses['shipping'][ $field ] ) ) {
				$missing_fields[] = ucfirst( str_replace( '_', ' ', $field ) );
			}
		}
		if ( ! empty( $missing_fields ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Shipping address is incomplete. Missing: %s', 'wc-purchase-order-limits' ),
					implode( ', ', $missing_fields )
				)
			);
		}

		// Create purchase order
		$po_handler = new WCPOL_Purchase_Order();

		$po_id = $po_handler->create_from_cart( $notes, $reference, $required_date, $addresses );

		if ( $po_id ) {
			// Clear the cart
			WC()->cart->empty_cart();

			// Get thank you page URL
			$thank_you_url = $this->get_po_thank_you_url( $po_id );

			wp_send_json_success(
				array(
					'message'      => __( 'Quote request submitted successfully!', 'wc-purchase-order-limits' ),
					'po_id'        => $po_id,
					'redirect_url' => $thank_you_url,
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to create quote. Please try again.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * Display PO form on cart page when parameter is present
	 */
	public function maybe_display_po_form_on_cart() {
		// Only show if we have the po_request parameter
		if ( ! isset( $_GET['po_request'] ) || '1' !== $_GET['po_request'] ) {
			return;
		}

		// Hide the normal cart elements and show PO form
		?>
		<style>
			.woocommerce-cart-form,
			.cart-collaterals,
			.cart_totals,
			.cart-shipping-section,
			.cross-sells {
				display: none !important;
			}
			.wcpol-po-request-cart-wrapper {
				margin: 20px 0;
			}
		</style>
		<div class="wcpol-po-request-cart-wrapper">
			<?php $this->render_po_request_form(); ?>
		</div>
		<?php

		// Prevent further cart display actions
		remove_all_actions( 'woocommerce_cart_collaterals' );
		remove_all_actions( 'woocommerce_after_cart' );
	}

	/**
	 * Get PO thank you page URL
	 * Directs to My Account purchase orders section
	 */
	private function get_po_thank_you_url( $po_id ) {
		// Always use my account page with PO view
		return wc_get_account_endpoint_url( 'purchase-orders' ) . $po_id;
	}

	/**
	 * Sanitize and return submitted address data from POST
	 *
	 * @return array Array with 'shipping' and 'billing' keys.
	 */
	private function get_submitted_addresses() {
		$address_fields = array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );

		// Check if using saved address (hidden fields) or new address form.
		// If shipping_first_name is populated, it's from hidden fields (saved address).
		// If not, check new_shipping_first_name (new address form).
		$using_saved = ! empty( $_POST['shipping_first_name'] );

		$shipping = array();
		foreach ( $address_fields as $field ) {
			if ( $using_saved ) {
				$key = 'shipping_' . $field;
			} else {
				$key = 'new_shipping_' . $field;
			}
			$shipping[ $field ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}

		// Ensure country defaults to US.
		if ( empty( $shipping['country'] ) ) {
			$shipping['country'] = 'US';
		}

		// Check billing same as shipping.
		$billing_same = isset( $_POST['billing_same_as_shipping'] ) && '1' === $_POST['billing_same_as_shipping'];

		if ( $billing_same ) {
			$billing = $shipping;
		} else {
			// Check if using saved billing address (hidden fields) or new billing form.
			$using_saved_billing = ! empty( $_POST['billing_first_name'] );

			$billing = array();
			foreach ( $address_fields as $field ) {
				if ( $using_saved_billing ) {
					$key = 'billing_' . $field;
				} else {
					$key = 'new_billing_' . $field;
				}
				$billing[ $field ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}
		}

		// Add email to billing.
		$billing['email'] = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		// Default billing phone from user meta if empty (common when billing=same-as-shipping).
		if ( empty( $billing['phone'] ) ) {
			$billing['phone'] = get_user_meta( get_current_user_id(), 'billing_phone', true );
		}

		return array(
			'shipping' => $shipping,
			'billing'  => $billing,
		);
	}

	/**
	 * Render the shipping and billing address sections for the quote form.
	 */
	private function render_address_sections() {
		$user_id          = get_current_user_id();
		$saved_addresses  = array();
		$default_address  = null;

		// Get saved addresses from child theme handler if available.
		if ( class_exists( 'BBChild_Custom_Checkout_Handler' ) ) {
			$saved_addresses = BBChild_Custom_Checkout_Handler::get_saved_addresses( $user_id );
		}

		if ( ! empty( $saved_addresses ) ) {
			foreach ( $saved_addresses as $addr ) {
				if ( ! empty( $addr['is_default'] ) ) {
					$default_address = $addr;
					break;
				}
			}
			if ( ! $default_address ) {
				$default_address = $saved_addresses[0];
			}
		}

		// Pre-fill from WC customer session.
		$session_first_name = WC()->customer->get_shipping_first_name();
		$session_last_name  = WC()->customer->get_shipping_last_name();
		$session_address_1  = WC()->customer->get_shipping_address_1();
		$session_address_2  = WC()->customer->get_shipping_address_2();
		$session_city       = WC()->customer->get_shipping_city();
		$session_state      = WC()->customer->get_shipping_state();
		$session_postcode   = WC()->customer->get_shipping_postcode();

		// If no session data, try user meta.
		if ( empty( $session_first_name ) ) {
			$session_first_name = get_user_meta( $user_id, 'shipping_first_name', true );
			if ( empty( $session_first_name ) ) {
				$session_first_name = get_user_meta( $user_id, 'billing_first_name', true );
			}
		}
		if ( empty( $session_last_name ) ) {
			$session_last_name = get_user_meta( $user_id, 'shipping_last_name', true );
			if ( empty( $session_last_name ) ) {
				$session_last_name = get_user_meta( $user_id, 'billing_last_name', true );
			}
		}

		$user        = wp_get_current_user();
		$user_email  = $user->user_email;
		$user_phone  = get_user_meta( $user_id, 'billing_phone', true );
		$states      = WC()->countries->get_states( 'US' );
		?>

		<!-- Shipping Address -->
		<div class="wcpol-form-section wcpol-address-section">
			<h3><?php esc_html_e( 'Shipping Address', 'wc-purchase-order-limits' ); ?></h3>

			<?php if ( ! empty( $saved_addresses ) ) : ?>
				<?php $default_address_id = $default_address ? ( $default_address['id'] ?? 'addr_0' ) : ''; ?>
				<div class="wcpol-saved-addresses-selector" id="wcpol-saved-addresses-selector">
					<div class="wcpol-default-address-card" id="wcpol-default-address-card">
						<?php if ( ! empty( $default_address['label'] ) ) : ?>
						<div class="wcpol-address-nickname"><?php echo esc_html( $default_address['label'] ); ?></div>
						<?php endif; ?>
						<div class="wcpol-address-details">
							<div class="wcpol-address-name"><?php echo esc_html( $default_address['first_name'] . ' ' . $default_address['last_name'] ); ?></div>
							<div class="wcpol-address-line"><?php echo esc_html( $default_address['address_1'] ); ?></div>
							<?php if ( ! empty( $default_address['address_2'] ) ) : ?>
							<div class="wcpol-address-line"><?php echo esc_html( $default_address['address_2'] ); ?></div>
							<?php endif; ?>
							<div class="wcpol-address-line"><?php echo esc_html( $default_address['city'] . ', ' . $default_address['state'] . ' ' . $default_address['postcode'] ); ?></div>
						</div>
					</div>

					<div class="wcpol-address-change-row">
						<select id="wcpol-shipping-address-select" class="wcpol-address-select" data-current="<?php echo esc_attr( $default_address_id ); ?>">
							<option value="" disabled selected><?php esc_html_e( 'Choose another address', 'wc-purchase-order-limits' ); ?></option>
							<?php foreach ( $saved_addresses as $index => $address ) : ?>
								<?php
								$address_id     = isset( $address['id'] ) ? $address['id'] : 'addr_' . $index;
								$display_label  = ! empty( $address['label'] ) ? $address['label'] . ' - ' : '';
								$display_label .= $address['address_1'] . ', ' . $address['city'];
								?>
								<option value="<?php echo esc_attr( $address_id ); ?>"
										data-first-name="<?php echo esc_attr( $address['first_name'] ); ?>"
										data-last-name="<?php echo esc_attr( $address['last_name'] ); ?>"
										data-label="<?php echo esc_attr( $address['label'] ?? '' ); ?>"
										data-address-1="<?php echo esc_attr( $address['address_1'] ); ?>"
										data-address-2="<?php echo esc_attr( $address['address_2'] ?? '' ); ?>"
										data-city="<?php echo esc_attr( $address['city'] ); ?>"
										data-state="<?php echo esc_attr( $address['state'] ); ?>"
										data-postcode="<?php echo esc_attr( $address['postcode'] ); ?>"
										data-country="<?php echo esc_attr( $address['country'] ); ?>"
										data-phone="<?php echo esc_attr( $address['phone'] ?? '' ); ?>">
									<?php echo esc_html( $display_label ); ?>
								</option>
							<?php endforeach; ?>
							<option value="new"><?php esc_html_e( '+ Add new address', 'wc-purchase-order-limits' ); ?></option>
						</select>
					</div>

					<!-- Hidden fields for selected saved address -->
					<input type="hidden" id="wcpol_shipping_first_name" name="shipping_first_name" value="<?php echo esc_attr( $default_address['first_name'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_last_name" name="shipping_last_name" value="<?php echo esc_attr( $default_address['last_name'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_address_1" name="shipping_address_1" value="<?php echo esc_attr( $default_address['address_1'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_address_2" name="shipping_address_2" value="<?php echo esc_attr( $default_address['address_2'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_city" name="shipping_city" value="<?php echo esc_attr( $default_address['city'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_state" name="shipping_state" value="<?php echo esc_attr( $default_address['state'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_postcode" name="shipping_postcode" value="<?php echo esc_attr( $default_address['postcode'] ?? '' ); ?>">
					<input type="hidden" id="wcpol_shipping_country" name="shipping_country" value="<?php echo esc_attr( $default_address['country'] ?? 'US' ); ?>">
					<input type="hidden" id="wcpol_shipping_phone" name="shipping_phone" value="<?php echo esc_attr( $default_address['phone'] ?? '' ); ?>">
				</div>
			<?php endif; ?>
			<?php // Note: The new address form below is hidden when saved addresses exist.
			// When user selects "Add new address", JS hides the entire selector above and shows the form. ?>

			<!-- New Address Form -->
			<div class="wcpol-new-address-form" id="wcpol-new-shipping-form" <?php echo ! empty( $saved_addresses ) ? 'style="display:none;"' : ''; ?>>
				<div class="wcpol-form-row-group">
					<div class="wcpol-form-row wcpol-form-row-half">
						<label for="wcpol_new_shipping_first_name"><?php esc_html_e( 'First Name', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<input type="text" id="wcpol_new_shipping_first_name" name="new_shipping_first_name" value="<?php echo esc_attr( $session_first_name ); ?>">
					</div>
					<div class="wcpol-form-row wcpol-form-row-half">
						<label for="wcpol_new_shipping_last_name"><?php esc_html_e( 'Last Name', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<input type="text" id="wcpol_new_shipping_last_name" name="new_shipping_last_name" value="<?php echo esc_attr( $session_last_name ); ?>">
					</div>
				</div>
				<div class="wcpol-form-row">
					<label for="wcpol_new_shipping_address_1"><?php esc_html_e( 'Address', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
					<input type="text" id="wcpol_new_shipping_address_1" name="new_shipping_address_1" value="<?php echo esc_attr( $session_address_1 ); ?>">
				</div>
				<div class="wcpol-form-row">
					<label for="wcpol_new_shipping_address_2"><?php esc_html_e( 'Apartment, suite, etc. (optional)', 'wc-purchase-order-limits' ); ?></label>
					<input type="text" id="wcpol_new_shipping_address_2" name="new_shipping_address_2" value="<?php echo esc_attr( $session_address_2 ); ?>">
				</div>
				<div class="wcpol-form-row-group">
					<div class="wcpol-form-row wcpol-form-row-third">
						<label for="wcpol_new_shipping_city"><?php esc_html_e( 'City', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<input type="text" id="wcpol_new_shipping_city" name="new_shipping_city" value="<?php echo esc_attr( $session_city ); ?>">
					</div>
					<div class="wcpol-form-row wcpol-form-row-third">
						<label for="wcpol_new_shipping_state"><?php esc_html_e( 'State', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<select id="wcpol_new_shipping_state" name="new_shipping_state">
							<option value=""><?php esc_html_e( 'Select state', 'wc-purchase-order-limits' ); ?></option>
							<?php foreach ( $states as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $session_state, $code ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wcpol-form-row wcpol-form-row-third">
						<label for="wcpol_new_shipping_postcode"><?php esc_html_e( 'ZIP Code', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<input type="text" id="wcpol_new_shipping_postcode" name="new_shipping_postcode" value="<?php echo esc_attr( $session_postcode ); ?>">
					</div>
				</div>
				<input type="hidden" name="new_shipping_country" value="US">

				<!-- Address Validation Status -->
				<div class="ls-validation-status" id="wcpol-new-shipping-validation-status"></div>

				<div class="ls-consent-row" id="wcpol-new-shipping-consent-row" style="display:none;">
					<label class="ls-consent-label">
						<input type="checkbox" id="wcpol_new_shipping_address_consent" name="address_validation_consent" value="yes">
						<span class="ls-consent-text"><?php esc_html_e( 'This address could not be verified. Unverified addresses may cause delivery delays. I confirm this address is correct.', 'wc-purchase-order-limits' ); ?></span>
					</label>
				</div>

				<input type="hidden" id="wcpol_new_shipping_validation_status" name="address_validation_status" value="">

				<?php if ( ! empty( $saved_addresses ) ) : ?>
				<div class="wcpol-form-row" style="margin-top: 10px;">
					<a href="#" class="wcpol-cancel-new-address" id="wcpol-cancel-new-address"><?php esc_html_e( 'Cancel — use saved address', 'wc-purchase-order-limits' ); ?></a>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Billing Address -->
		<div class="wcpol-form-section wcpol-address-section">
			<h3><?php esc_html_e( 'Billing Address', 'wc-purchase-order-limits' ); ?></h3>

			<div class="wcpol-form-row wcpol-billing-same-row">
				<label class="wcpol-checkbox-label">
					<input type="checkbox" name="billing_same_as_shipping" id="wcpol_billing_same" value="1" checked>
					<span><?php esc_html_e( 'Same as shipping address', 'wc-purchase-order-limits' ); ?></span>
				</label>
			</div>

			<div class="wcpol-billing-address-content" id="wcpol-billing-address-content" style="display:none;">
				<?php if ( ! empty( $saved_addresses ) ) : ?>
					<?php
					$default_billing    = $default_address;
					$default_billing_id = $default_billing ? ( $default_billing['id'] ?? 'addr_0' ) : '';
					?>
					<div class="wcpol-saved-addresses-selector" id="wcpol-saved-billing-selector">
						<div class="wcpol-default-address-card" id="wcpol-default-billing-card">
							<?php if ( ! empty( $default_billing['label'] ) ) : ?>
							<div class="wcpol-address-nickname"><?php echo esc_html( $default_billing['label'] ); ?></div>
							<?php endif; ?>
							<div class="wcpol-address-details">
								<div class="wcpol-address-name"><?php echo esc_html( $default_billing['first_name'] . ' ' . $default_billing['last_name'] ); ?></div>
								<div class="wcpol-address-line"><?php echo esc_html( $default_billing['address_1'] ); ?></div>
								<?php if ( ! empty( $default_billing['address_2'] ) ) : ?>
								<div class="wcpol-address-line"><?php echo esc_html( $default_billing['address_2'] ); ?></div>
								<?php endif; ?>
								<div class="wcpol-address-line"><?php echo esc_html( $default_billing['city'] . ', ' . $default_billing['state'] . ' ' . $default_billing['postcode'] ); ?></div>
							</div>
						</div>

						<div class="wcpol-address-change-row">
							<select id="wcpol-billing-address-select" class="wcpol-address-select" data-current="<?php echo esc_attr( $default_billing_id ); ?>">
								<option value="" disabled selected><?php esc_html_e( 'Choose another address', 'wc-purchase-order-limits' ); ?></option>
								<?php foreach ( $saved_addresses as $index => $address ) : ?>
									<?php
									$address_id     = isset( $address['id'] ) ? $address['id'] : 'addr_' . $index;
									$display_label  = ! empty( $address['label'] ) ? $address['label'] . ' - ' : '';
									$display_label .= $address['address_1'] . ', ' . $address['city'];
									?>
									<option value="<?php echo esc_attr( $address_id ); ?>"
											data-first-name="<?php echo esc_attr( $address['first_name'] ); ?>"
											data-last-name="<?php echo esc_attr( $address['last_name'] ); ?>"
											data-label="<?php echo esc_attr( $address['label'] ?? '' ); ?>"
											data-address-1="<?php echo esc_attr( $address['address_1'] ); ?>"
											data-address-2="<?php echo esc_attr( $address['address_2'] ?? '' ); ?>"
											data-city="<?php echo esc_attr( $address['city'] ); ?>"
											data-state="<?php echo esc_attr( $address['state'] ); ?>"
											data-postcode="<?php echo esc_attr( $address['postcode'] ); ?>"
											data-country="<?php echo esc_attr( $address['country'] ); ?>"
											data-phone="<?php echo esc_attr( $address['phone'] ?? '' ); ?>">
										<?php echo esc_html( $display_label ); ?>
									</option>
								<?php endforeach; ?>
								<option value="new"><?php esc_html_e( '+ Add new address', 'wc-purchase-order-limits' ); ?></option>
							</select>
						</div>

						<!-- Hidden fields for selected saved billing address -->
						<input type="hidden" id="wcpol_billing_first_name" name="billing_first_name" value="<?php echo esc_attr( $default_billing['first_name'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_last_name" name="billing_last_name" value="<?php echo esc_attr( $default_billing['last_name'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_address_1" name="billing_address_1" value="<?php echo esc_attr( $default_billing['address_1'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_address_2" name="billing_address_2" value="<?php echo esc_attr( $default_billing['address_2'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_city" name="billing_city" value="<?php echo esc_attr( $default_billing['city'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_state" name="billing_state" value="<?php echo esc_attr( $default_billing['state'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_postcode" name="billing_postcode" value="<?php echo esc_attr( $default_billing['postcode'] ?? '' ); ?>">
						<input type="hidden" id="wcpol_billing_country" name="billing_country" value="<?php echo esc_attr( $default_billing['country'] ?? 'US' ); ?>">
						<input type="hidden" id="wcpol_billing_phone" name="billing_phone" value="<?php echo esc_attr( $default_billing['phone'] ?? $user_phone ); ?>">
					</div>
				<?php endif; ?>

				<!-- New Billing Address Form -->
				<div class="wcpol-new-address-form" id="wcpol-new-billing-form" <?php echo ! empty( $saved_addresses ) ? 'style="display:none;"' : ''; ?>>
					<div class="wcpol-form-row-group">
						<div class="wcpol-form-row wcpol-form-row-half">
							<label for="wcpol_new_billing_first_name"><?php esc_html_e( 'First Name', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
							<input type="text" id="wcpol_new_billing_first_name" name="new_billing_first_name">
						</div>
						<div class="wcpol-form-row wcpol-form-row-half">
							<label for="wcpol_new_billing_last_name"><?php esc_html_e( 'Last Name', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
							<input type="text" id="wcpol_new_billing_last_name" name="new_billing_last_name">
						</div>
					</div>
					<div class="wcpol-form-row">
						<label for="wcpol_new_billing_address_1"><?php esc_html_e( 'Address', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
						<input type="text" id="wcpol_new_billing_address_1" name="new_billing_address_1">
					</div>
					<div class="wcpol-form-row">
						<label for="wcpol_new_billing_address_2"><?php esc_html_e( 'Apartment, suite, etc. (optional)', 'wc-purchase-order-limits' ); ?></label>
						<input type="text" id="wcpol_new_billing_address_2" name="new_billing_address_2">
					</div>
					<div class="wcpol-form-row-group">
						<div class="wcpol-form-row wcpol-form-row-third">
							<label for="wcpol_new_billing_city"><?php esc_html_e( 'City', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
							<input type="text" id="wcpol_new_billing_city" name="new_billing_city">
						</div>
						<div class="wcpol-form-row wcpol-form-row-third">
							<label for="wcpol_new_billing_state"><?php esc_html_e( 'State', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
							<select id="wcpol_new_billing_state" name="new_billing_state">
								<option value=""><?php esc_html_e( 'Select state', 'wc-purchase-order-limits' ); ?></option>
								<?php foreach ( $states as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="wcpol-form-row wcpol-form-row-third">
							<label for="wcpol_new_billing_postcode"><?php esc_html_e( 'ZIP Code', 'wc-purchase-order-limits' ); ?> <span class="required">*</span></label>
							<input type="text" id="wcpol_new_billing_postcode" name="new_billing_postcode">
						</div>
					</div>
					<input type="hidden" name="new_billing_country" value="US">
					<div class="wcpol-form-row">
						<label for="wcpol_new_billing_phone"><?php esc_html_e( 'Phone (optional)', 'wc-purchase-order-limits' ); ?></label>
						<input type="text" id="wcpol_new_billing_phone" name="new_billing_phone" value="<?php echo esc_attr( $user_phone ); ?>">
					</div>
					<?php if ( ! empty( $saved_addresses ) ) : ?>
					<div class="wcpol-form-row" style="margin-top: 10px;">
						<a href="#" class="wcpol-cancel-new-address" id="wcpol-cancel-new-billing"><?php esc_html_e( 'Cancel — use saved address', 'wc-purchase-order-limits' ); ?></a>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<input type="hidden" name="billing_email" value="<?php echo esc_attr( $user_email ); ?>">
		</div>
		<?php
	}
}
