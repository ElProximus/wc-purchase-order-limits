<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/public
 */

class WCPOL_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side.
	 */
	public function enqueue_styles() {
		if ( ! $this->should_enqueue_public_assets() ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			WCPOL_PLUGIN_URL . 'assets/css/wcpol-public.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue PO request styles on relevant pages or when shortcode is present
		if ( $this->is_po_request_page() || is_cart() || is_checkout() || $this->page_has_po_shortcode() ) {
			wp_enqueue_style(
				$this->plugin_name . '-po-request',
				WCPOL_PLUGIN_URL . 'assets/css/wcpol-po-request.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the public-facing side.
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_enqueue_public_assets() ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			WCPOL_PLUGIN_URL . 'assets/js/wcpol-public.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'wcpol_public',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcpol_nonce' ),
				'strings'  => array(
					'creating_po' => __( 'Creating quote...', 'wc-purchase-order-limits' ),
					'po_created'  => __( 'Quote created successfully!', 'wc-purchase-order-limits' ),
					'error'       => __( 'An error occurred. Please try again.', 'wc-purchase-order-limits' ),
				),
			)
		);

		// Enqueue PO request script on relevant pages or when shortcode is present
		if ( $this->is_po_request_page() || is_cart() || is_checkout() || $this->page_has_po_shortcode() ) {
			$po_script_deps = array( 'jquery', $this->plugin_name );

			// Enqueue shared Google Places and Address Validation from child theme if available.
			if ( class_exists( 'BBChild_Google_Places_Autocomplete' ) ) {
				$google_places  = BBChild_Google_Places_Autocomplete::get_instance();
				$places_enabled = $google_places->is_enabled();
				$places_api_key = $google_places->get_api_key();

				if ( $places_enabled && $places_api_key ) {
					wp_enqueue_script(
						'google-places-api',
						'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $places_api_key ) . '&libraries=places',
						array(),
						null,
						true
					);

					wp_enqueue_script(
						'ls-google-places',
						get_stylesheet_directory_uri() . '/assets/js/shared-utilities/google-places.js',
						array( 'jquery', 'google-places-api' ),
						filemtime( get_stylesheet_directory() . '/assets/js/shared-utilities/google-places.js' ),
						true
					);

					$po_script_deps[] = 'ls-google-places';

					// Localize Google Places config.
					wp_localize_script(
						$this->plugin_name . '-po-request',
						'googlePlacesConfig',
						$google_places->get_js_config()
					);
				}
			}

			// Enqueue address validation shared assets.
			if ( function_exists( 'bbchild_enqueue_address_validation_assets' ) ) {
				bbchild_enqueue_address_validation_assets( $this->plugin_name . '-po-request' );
				$po_script_deps[] = 'ls-address-validation';
			}

			wp_enqueue_script(
				$this->plugin_name . '-po-request',
				WCPOL_PLUGIN_URL . 'assets/js/wcpol-po-request.js',
				$po_script_deps,
				$this->version,
				true
			);

			// Localize saved addresses for dropdown swap logic.
			$saved_addresses = array();
			if ( is_user_logged_in() && class_exists( 'BBChild_Custom_Checkout_Handler' ) ) {
				$saved_addresses = BBChild_Custom_Checkout_Handler::get_saved_addresses( get_current_user_id() );
			}
			wp_localize_script(
				$this->plugin_name . '-po-request',
				'wcpol_addresses',
				$saved_addresses
			);
		}
	}

	/**
	 * Add PO menu item to My Account
	 */
	public function add_po_menu_item( $items ) {
		$items['purchase-orders'] = __( 'Quotes', 'wc-purchase-order-limits' );
		return $items;
	}

	/**
	 * Add PO endpoint
	 */
	public function add_po_endpoint() {
		add_rewrite_endpoint( 'purchase-orders', EP_ROOT | EP_PAGES );
	}

	/**
	 * Display PO content in My Account
	 */
	public function po_content() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if viewing single PO
		$po_id = get_query_var( 'purchase-orders' );
		if ( $po_id && is_numeric( $po_id ) ) {
			$this->display_single_po( $po_id, $user_id );
		} else {
			$this->display_po_list( $user_id );
		}
	}

	/**
	 * Display list of user's POs
	 */
	private function display_po_list( $user_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$pos        = $po_handler->get_user_pos( $user_id );

		?>
		<div class="wcpol-account-pos">
			<h2><?php esc_html_e( 'My Quotes', 'wc-purchase-order-limits' ); ?></h2>

			<p>
				<a href="<?php echo esc_url( add_query_arg( array( 'po_request' => '1', 'voluntary' => '1' ), wc_get_cart_url() ) ); ?>" class="button">
					<?php esc_html_e( 'Request a Quote', 'wc-purchase-order-limits' ); ?>
				</a>
			</p>

			<?php if ( empty( $pos ) ) : ?>
				<p><?php esc_html_e( 'You have no quotes yet.', 'wc-purchase-order-limits' ); ?></p>
			<?php else : ?>
				<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
					<thead>
						<tr>
							<th class="woocommerce-orders-table__header"><?php esc_html_e( 'Quote Number', 'wc-purchase-order-limits' ); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e( 'Date', 'wc-purchase-order-limits' ); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e( 'Status', 'wc-purchase-order-limits' ); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
							<th class="woocommerce-orders-table__header"><?php esc_html_e( 'Actions', 'wc-purchase-order-limits' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pos as $po ) : ?>
							<tr class="woocommerce-orders-table__row">
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Quote Number', 'wc-purchase-order-limits' ); ?>">
									<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'purchase-orders' ) . $po->id ); ?>">
										<?php echo esc_html( $po->po_number ); ?>
									</a>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Date', 'wc-purchase-order-limits' ); ?>">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ) ); ?>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Status', 'wc-purchase-order-limits' ); ?>">
									<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po->status ); ?>">
										<?php
										if ( 'partial' === $po->status ) {
											echo esc_html( __( 'Partially Issued', 'wc-purchase-order-limits' ) );
										} else {
											echo esc_html( ucfirst( $po->status ) );
										}
										?>
									</span>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Total', 'wc-purchase-order-limits' ); ?>">
									<?php echo wc_price( $po->total_amount ); ?>
								</td>
								<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Actions', 'wc-purchase-order-limits' ); ?>">
									<?php if ( ( 'approved' === $po->status || 'issued' === $po->status ) && $po->pdf_url ) : ?>
										<a href="
										<?php
										echo esc_url(
											add_query_arg(
												array(
													'wcpol_download_po' => $po->id,
													'nonce' => wp_create_nonce( 'download_po_' . $po->id ),
												),
												home_url()
											)
										);
										?>
													" class="woocommerce-button button download">
											<?php esc_html_e( 'PDF', 'wc-purchase-order-limits' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display single PO details
	 */
	private function display_single_po( $po_id, $user_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		// Check if user owns this PO and it's not trashed
		if ( ! $po || (int) $po->user_id !== (int) $user_id || 'trashed' === $po->status ) {
			echo '<p>' . esc_html__( 'Quote not found.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}

		$po_items   = $po_handler->get_po_items( $po_id );
		$po_history = $po_handler->get_history( $po_id );

		// Mark notifications as read
		$notifications = new WCPOL_Notifications();
		$notifications->mark_notifications_read( $po_id, $user_id );

		?>
		<div class="wcpol-po-detail">
			<?php /* translators: %s: Quote number */ ?>
			<h2><?php printf( esc_html__( 'Quote %s', 'wc-purchase-order-limits' ), esc_html( $po->po_number ) ); ?></h2>

			<?php if ( 'requested' === $po->status ) : ?>
				<div class="wcpol-request-notice" style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin: 20px 0; border-radius: 4px;">
					<strong><?php esc_html_e( 'You have requested a quote.', 'wc-purchase-order-limits' ); ?></strong><br>
					<?php esc_html_e( 'You will be notified when the quote is issued.', 'wc-purchase-order-limits' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( 'issued' === $po->status ) : ?>
				<div class="wcpol-issued-notice" style="background-color: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px 20px; margin: 20px 0; border-radius: 4px;">
					<strong><?php esc_html_e( 'A quote has been issued.', 'wc-purchase-order-limits' ); ?></strong><br>
					<?php esc_html_e( 'You may download a PDF. Approve the quote to proceed with the purchase.', 'wc-purchase-order-limits' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( 'approved' === $po->status ) : ?>
				<div class="wcpol-approval-notice" style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin: 20px 0; border-radius: 4px;">
					<strong><?php esc_html_e( 'You have approved this quote.', 'wc-purchase-order-limits' ); ?></strong><br>
					<?php esc_html_e( 'You will be contacted with payment instructions. Your order is not complete until you receive confirmation.', 'wc-purchase-order-limits' ); ?>
				</div>
			<?php endif; ?>
			
			<div class="wcpol-po-meta" style="background: #f9f9f9; border: 1px solid #e0e0e0; padding: 20px; margin: 20px 0; border-radius: 5px;">
				<p><strong><?php esc_html_e( 'Date:', 'wc-purchase-order-limits' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ) ); ?></p>
				<p><strong><?php esc_html_e( 'Status:', 'wc-purchase-order-limits' ); ?></strong>
					<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po->status ); ?>">
						<?php
						if ( 'partial' === $po->status ) {
							$vendors_issued = isset( $po->vendors_issued ) ? (int) $po->vendors_issued : 0;
							$vendor_count   = isset( $po->vendor_count ) ? (int) $po->vendor_count : 0;
							printf(
								/* translators: 1: vendors issued count, 2: total vendor count */
								esc_html__( 'Partially Issued (%1$d of %2$d vendors completed)', 'wc-purchase-order-limits' ),
								$vendors_issued,
								$vendor_count
							);
						} else {
							echo esc_html( ucfirst( $po->status ) );
						}
						?>
					</span>
				</p>
				<?php if ( $po->approved_date ) : ?>
					<p><strong><?php esc_html_e( 'Date Issued:', 'wc-purchase-order-limits' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->approved_date ) ) ); ?></p>
				<?php endif; ?>
				<p><strong><?php esc_html_e( 'Total:', 'wc-purchase-order-limits' ); ?></strong> <?php echo wc_price( $po->total_amount ); ?></p>

				<?php if ( $po->reference ) : ?>
					<p><strong><?php esc_html_e( 'PO Number / Reference:', 'wc-purchase-order-limits' ); ?></strong> <?php echo esc_html( $po->reference ); ?></p>
				<?php endif; ?>

				<?php if ( $po->required_date ) : ?>
					<p><strong><?php esc_html_e( 'Requested Delivery:', 'wc-purchase-order-limits' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->required_date ) ) ); ?></p>
				<?php endif; ?>

				<?php if ( $po->validity_days && $po->expiry_date ) : ?>
					<?php /* translators: %d: number of days */ ?>
					<p><strong><?php esc_html_e( 'Valid For:', 'wc-purchase-order-limits' ); ?></strong> <?php printf( esc_html__( '%d days', 'wc-purchase-order-limits' ), (int) $po->validity_days ); ?></p>
					<p><strong><?php esc_html_e( 'Expires On:', 'wc-purchase-order-limits' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->expiry_date ) ) ); ?>
						<?php
						// Check if expired
						$today          = current_time( 'Y-m-d' );
						$days_remaining = floor( ( strtotime( $po->expiry_date ) - strtotime( $today ) ) / ( 60 * 60 * 24 ) );
						if ( $days_remaining > 0 ) {
							/* translators: %d: number of days remaining */
							echo ' <em style="color: #4caf50;">(' . esc_html( sprintf( __( '%d days remaining', 'wc-purchase-order-limits' ), $days_remaining ) ) . ')</em>';
						} elseif ( 0 === $days_remaining ) {
							echo ' <em style="color: #ff6b6b;">' . esc_html__( '(Expires today)', 'wc-purchase-order-limits' ) . '</em>';
						} else {
							echo ' <em style="color: #ff0000;">' . esc_html__( '(Expired)', 'wc-purchase-order-limits' ) . '</em>';
						}
						?>
					</p>
				<?php endif; ?>
			</div>
			
			<?php if ( $po->notes ) : ?>
				<div class="wcpol-po-notes">
					<h3><?php esc_html_e( 'Notes', 'wc-purchase-order-limits' ); ?></h3>
					<p><?php echo nl2br( esc_html( $po->notes ) ); ?></p>
				</div>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Items', 'wc-purchase-order-limits' ); ?></h3>
			<table class="shop_table shop_table_responsive">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Quantity', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Price', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Availability', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'wc-purchase-order-limits' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $po_items as $item ) :
						$product = wc_get_product( $item->product_id );
						if ( ! $product ) {
							continue;
						}

						$vendor_name = __( 'Shop', 'wc-purchase-order-limits' );
						if ( $item->vendor_id > 0 ) {
							$vendor = get_user_by( 'id', $item->vendor_id );
							if ( $vendor ) {
								// Try LSV_Vendors first (LS Vendors), then WCV_Vendors
								if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
									$vendor_name = LSV_Vendors::get_vendor_shop_name( $item->vendor_id );
								} elseif ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
									$vendor_name = WCV_Vendors::get_vendor_shop_name( $item->vendor_id );
								} else {
									$vendor_name = $vendor->display_name;
								}
							}
						}

						$unit_price    = $item->vendor_price ? $item->vendor_price : $item->price;
						$show_original = $item->vendor_price && (float) $item->vendor_price !== (float) $item->price;
						?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Product', 'wc-purchase-order-limits' ); ?>">
								<?php echo esc_html( $product->get_name() ); ?>
							</td>
							<td data-title="<?php esc_attr_e( 'SKU', 'wc-purchase-order-limits' ); ?>">
								<?php
								$sku = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
								echo '' !== $sku ? esc_html( $sku ) : '—';
								?>
							</td>
							<td data-title="<?php esc_attr_e( 'Quantity', 'wc-purchase-order-limits' ); ?>">
								<?php echo esc_html( $item->quantity ); ?>
							</td>
							<td data-title="<?php esc_attr_e( 'Price', 'wc-purchase-order-limits' ); ?>">
								<?php
								echo wc_price( $unit_price );
								if ( $show_original ) {
									echo '<br><small style="text-decoration: line-through; color: #999;">' . wc_price( $item->price ) . '</small>';
								}
								?>
							</td>
							<td data-title="<?php esc_attr_e( 'Total', 'wc-purchase-order-limits' ); ?>">
								<?php echo wc_price( $item->total ); ?>
							</td>
							<td data-title="<?php esc_attr_e( 'Availability', 'wc-purchase-order-limits' ); ?>">
								<span class="wcpol-item-status wcpol-item-status-<?php echo esc_attr( $item->status ); ?>">
									<?php
									if ( 'not_available' === $item->status ) {
										echo esc_html( __( 'Not Available', 'wc-purchase-order-limits' ) );
									} else {
										echo esc_html( ucfirst( str_replace( '_', ' ', $item->status ) ) );
									}
									?>
								</span>
							</td>
							<td data-title="<?php esc_attr_e( 'Vendor', 'wc-purchase-order-limits' ); ?>">
								<?php echo esc_html( $vendor_name ); ?>
							</td>
						</tr>
						<?php if ( $item->vendor_notes ) : ?>
							<tr class="wcpol-vendor-notes">
								<td colspan="6">
									<strong><?php esc_html_e( 'Vendor Notes:', 'wc-purchase-order-limits' ); ?></strong>
									<?php echo nl2br( esc_html( $item->vendor_notes ) ); ?>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>

					<?php
					// Display vendor shipping rows.
					$vendor_handler       = new WCPOL_Vendor_Handler();
					$vendor_shipping_rows = $vendor_handler->get_po_vendor_shipping( $po_id );

					if ( ! empty( $vendor_shipping_rows ) ) :
						foreach ( $vendor_shipping_rows as $shipping_row ) :
							if ( floatval( $shipping_row->shipping_amount ) > 0 ) :
								// Get vendor shop name (LS Vendors or WC Vendors).
								$shipping_vendor_name = __( 'Shop', 'wc-purchase-order-limits' );
								if ( $shipping_row->vendor_id > 0 ) {
									if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
										$shipping_vendor_name = LSV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
									} elseif ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
										$shipping_vendor_name = WCV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
									}
								}
								?>
								<tr class="wcpol-shipping-row" style="background: #f9f9f9;">
									<td colspan="4" style="text-align: right;">
										<?php
										printf(
											/* translators: %s: vendor shop name */
											esc_html__( 'Shipping (%s):', 'wc-purchase-order-limits' ),
											esc_html( $shipping_vendor_name )
										);
										?>
									</td>
									<td><?php echo wc_price( $shipping_row->shipping_amount ); ?></td>
									<td colspan="2">
										<?php if ( $shipping_row->shipping_notes ) : ?>
											<small><?php echo esc_html( $shipping_row->shipping_notes ); ?></small>
										<?php endif; ?>
									</td>
								</tr>
								<?php
							endif;
						endforeach;
					endif;
					?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="4" style="text-align: right;"><?php esc_html_e( 'Total:', 'wc-purchase-order-limits' ); ?></th>
						<th><?php echo wc_price( $po->total_amount ); ?></th>
						<th colspan="2"></th>
					</tr>
				</tfoot>
			</table>

			<?php if ( ! empty( $po_history ) ) : ?>
				<h3><?php esc_html_e( 'History', 'wc-purchase-order-limits' ); ?></h3>
				<div class="wcpol-po-history">
					<?php foreach ( $po_history as $history ) : ?>
						<div class="wcpol-history-item">
							<span class="wcpol-history-date">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $history->timestamp ) ) ); ?>
							</span>
							<span class="wcpol-history-action">
								<?php
								$action_text = str_replace( '_', ' ', $history->action );
								// Show "Requested" instead of "Created" for initial PO creation
								if ( 'created' === $action_text ) {
									$action_text = 'requested';
								}
								echo esc_html( ucfirst( $action_text ) );
								?>
							</span>
							<?php if ( $history->display_name ) : ?>
								<span class="wcpol-history-user">
									<?php /* translators: %s: user display name */ ?>
									<?php printf( esc_html__( 'by %s', 'wc-purchase-order-limits' ), esc_html( $history->display_name ) ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $history->notes ) : ?>
								<div class="wcpol-history-notes">
									<?php echo esc_html( $history->notes ); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="wcpol-po-actions">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'purchase-orders' ) ); ?>" class="button">
					<?php esc_html_e( 'My Quotes', 'wc-purchase-order-limits' ); ?>
				</a>

				<?php if ( 'partial' === $po->status ) : ?>
					<p style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0;">
						<strong><?php esc_html_e( 'Waiting for Vendors', 'wc-purchase-order-limits' ); ?></strong><br>
						<?php
						$vendors_issued = isset( $po->vendors_issued ) ? (int) $po->vendors_issued : 0;
						$vendor_count   = isset( $po->vendor_count ) ? (int) $po->vendor_count : 0;
						printf(
							/* translators: 1: vendors issued count, 2: total vendor count */
							esc_html__( 'This quote is being processed. %1$d of %2$d vendors have completed their review.', 'wc-purchase-order-limits' ),
							$vendors_issued,
							$vendor_count
						);
						?>
					</p>
				<?php elseif ( 'issued' === $po->status ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'wcpol_download_po' => $po->id,
								'nonce'             => wp_create_nonce( 'download_po_' . $po->id ),
							),
							home_url()
						)
					);
					?>
								" class="button">
						<?php esc_html_e( 'PDF', 'wc-purchase-order-limits' ); ?>
					</a>
					<button type="button" class="button button-primary wcpol-approve-po" data-po-id="<?php echo (int) $po->id; ?>">
						<?php esc_html_e( 'Approve', 'wc-purchase-order-limits' ); ?>
					</button>
					<button type="button" class="button wcpol-reject-po" data-po-id="<?php echo (int) $po->id; ?>">
						<?php esc_html_e( 'Decline', 'wc-purchase-order-limits' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( 'approved' === $po->status ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'wcpol_download_po' => $po->id,
								'nonce'             => wp_create_nonce( 'download_po_' . $po->id ),
							),
							home_url()
						)
					);
					?>
								" class="button">
						<?php esc_html_e( 'PDF', 'wc-purchase-order-limits' ); ?>
					</a>
					<?php
					// Show Pay Now button if order exists.
					$order_creator = new WCPOL_Order_Creator();
					$order_id      = $order_creator->get_order_id_for_po( $po->id );
					if ( $order_id ) :
						$order = wc_get_order( $order_id );
						if ( $order && $order->needs_payment() ) :
							?>
							<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button button-primary wcpol-pay-order">
								<?php esc_html_e( 'Pay Now', 'wc-purchase-order-limits' ); ?>
							</a>
							<?php
						elseif ( $order ) :
							?>
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button">
								<?php esc_html_e( 'View Order', 'wc-purchase-order-limits' ); ?>
							</a>
							<?php
						endif;
					endif;
					?>
				<?php endif; ?>
			</div>

			<?php if ( 'issued' === $po->status ) : ?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.wcpol-approve-po').on('click', function() {
					var $button = $(this);
					var $rejectButton = $('.wcpol-reject-po');
					var poId = $button.data('po-id');
					var originalText = $button.html();

					// Disable both buttons and show loading state
					$button.prop('disabled', true).html('<span class="wcpol-spinner"></span> <?php echo esc_js( __( 'Approving...', 'wc-purchase-order-limits' ) ); ?>');
					$rejectButton.prop('disabled', true);

					$.ajax({
						url: wcpol_public.ajax_url,
						type: 'POST',
						data: {
							action: 'wcpol_customer_approve_po',
							po_id: poId,
							nonce: wcpol_public.nonce
						},
						success: function(response) {
							if (response.success) {
								// Show success message
								var message = response.data.message || '<?php echo esc_js( __( 'Approved Successfully!', 'wc-purchase-order-limits' ) ); ?>';
								$button.html('✓ ' + message).addClass('wcpol-success');

								// Redirect to payment page if available, otherwise reload
								setTimeout(function() {
									if (response.data.payment_url) {
										window.location.href = response.data.payment_url;
									} else {
										location.reload();
									}
								}, 1500);
							} else {
								// Show error and re-enable buttons
								var $error = $('<div class="wcpol-inline-error woocommerce-error" style="margin-top: 10px;">' + (response.data || '<?php echo esc_js( __( 'Failed to accept quote.', 'wc-purchase-order-limits' ) ); ?>') + '</div>');
								$('.wcpol-po-actions').prepend($error);
								setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
								$button.prop('disabled', false).html(originalText);
								$rejectButton.prop('disabled', false);
							}
						},
						error: function() {
							// Show error and re-enable buttons
							var $error = $('<div class="wcpol-inline-error woocommerce-error" style="margin-top: 10px;"><?php echo esc_js( __( 'An error occurred. Please try again.', 'wc-purchase-order-limits' ) ); ?></div>');
							$('.wcpol-po-actions').prepend($error);
							setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
							$button.prop('disabled', false).html(originalText);
							$rejectButton.prop('disabled', false);
						}
					});
				});
				
				$('.wcpol-reject-po').on('click', function() {
					var reason = prompt('<?php echo esc_js( __( 'Please provide a reason for rejection (optional):', 'wc-purchase-order-limits' ) ); ?>');
					if (reason !== null) {
						var $button = $(this);
						var $approveButton = $('.wcpol-approve-po');
						var poId = $button.data('po-id');
						var originalText = $button.html();

						// Disable both buttons and show loading state
						$button.prop('disabled', true).html('<span class="wcpol-spinner"></span> <?php echo esc_js( __( 'Declining...', 'wc-purchase-order-limits' ) ); ?>');
						$approveButton.prop('disabled', true);

						$.ajax({
							url: wcpol_public.ajax_url,
							type: 'POST',
							data: {
								action: 'wcpol_customer_reject_po',
								po_id: poId,
								reason: reason,
								nonce: wcpol_public.nonce
							},
							success: function(response) {
								if (response.success) {
									// Show success message
									$button.html('✓ <?php echo esc_js( __( 'Declined Successfully!', 'wc-purchase-order-limits' ) ); ?>').addClass('wcpol-success');
									setTimeout(function() {
										location.reload();
									}, 1500);
								} else {
									// Show error and re-enable buttons
									var $error = $('<div class="wcpol-inline-error woocommerce-error" style="margin-top: 10px;">' + (response.data || '<?php echo esc_js( __( 'Failed to reject quote.', 'wc-purchase-order-limits' ) ); ?>') + '</div>');
									$('.wcpol-po-actions').prepend($error);
									setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
									$button.prop('disabled', false).html(originalText);
									$approveButton.prop('disabled', false);
								}
							},
							error: function() {
								// Show error and re-enable buttons
								var $error = $('<div class="wcpol-inline-error woocommerce-error" style="margin-top: 10px;"><?php echo esc_js( __( 'An error occurred. Please try again.', 'wc-purchase-order-limits' ) ); ?></div>');
								$('.wcpol-po-actions').prepend($error);
								setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
								$button.prop('disabled', false).html(originalText);
								$approveButton.prop('disabled', false);
							}
						});
					}
				});
			});
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Check if we're on the PO request page
	 */
	private function is_po_request_page() {
		$po_handler = new WCPOL_PO_Request_Handler();
		return $po_handler->is_po_request_page();
	}

	/**
	 * Detect if current singular post content contains the PO request shortcode
	 */
	private function page_has_po_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( ! $post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'wcpol_po_request' );
	}

	/**
	 * Check whether public assets are needed on the current request.
	 *
	 * @return bool
	 */
	private function should_enqueue_public_assets() {
		if ( $this->is_po_request_page() || $this->page_has_po_shortcode() ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'purchase-orders' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle PDF download
	 */
	public function handle_pdf_download() {
		if ( ! isset( $_GET['wcpol_download_po'] ) ) {
			return;
		}

		$po_id = intval( $_GET['wcpol_download_po'] );

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'download_po_' . $po_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-purchase-order-limits' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to download quotes.', 'wc-purchase-order-limits' ) );
		}

		$user_id       = get_current_user_id();
		$pdf_generator = new WCPOL_PDF_Generator();

		if ( ! $pdf_generator->download_pdf( $po_id, $user_id ) ) {
			wp_die( esc_html__( 'Unable to download quote.', 'wc-purchase-order-limits' ) );
		}
	}

	/**
	 * Add voluntary PO request button on cart page
	 */
	public function add_voluntary_po_button() {
		// Check if voluntary PO is enabled
		if ( 'yes' !== get_option( 'wcpol_allow_voluntary_po', 'no' ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if cart is empty
		if ( WC()->cart->is_empty() ) {
			return;
		}

		// Check if cart is already over limit (mandatory PO)
		$cart_validator = new WCPOL_Cart_Validator();
		if ( $cart_validator->is_cart_over_limit() ) {
			return; // Will be redirected to PO form automatically
		}

		// Display voluntary PO button
		?>
		<div class="wcpol-voluntary-po-wrapper">
			<p class="wcpol-voluntary-po-text">
				<?php esc_html_e( 'Are you sales tax exempt? Purchasing in bulk or for your school/institution? Request a quote. Prices are guaranteed!', 'wc-purchase-order-limits' ); ?>
			</p>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'po_request' => '1',
						'voluntary'  => '1',
					),
					wc_get_cart_url()
				)
			);
			?>
						"
				class="button wcpol-voluntary-po-button">
				<?php esc_html_e( 'Request Quote', 'wc-purchase-order-limits' ); ?>
			</a>
		</div>
		<?php
	}
}
