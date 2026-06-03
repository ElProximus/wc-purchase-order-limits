<?php
/**
 * Vendor dashboard integration
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/vendor-dashboard
 */

class WCPOL_Vendor_Dashboard {

	/**
	 * Check if user is a vendor (compatible with LS Vendors and WC Vendors)
	 */
	private function is_vendor( $user_id = null ) {
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
	 * Get dashboard page URL
	 */
	private function get_dashboard_page_url( $page = '' ) {
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
	 * Register shortcodes for standalone vendor quotes pages.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wcpol_vendor_quotes', array( $this, 'shortcode_vendor_quotes' ) );
	}

	/**
	 * Shortcode handler: [wcpol_vendor_quotes]
	 * Renders the vendor quotes list or single quote detail based on URL parameter.
	 */
	public function shortcode_vendor_quotes() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your quotes.', 'wc-purchase-order-limits' ) . '</p>';
		}

		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'You must be a vendor to access this page.', 'wc-purchase-order-limits' ) . '</p>';
		}

		ob_start();
		$this->render_po_page();
		return ob_get_clean();
	}

	/**
	 * Get the quotes page URL for links within the vendor views.
	 * Uses the current page when accessed via shortcode, otherwise falls back to dashboard.
	 *
	 * @return string The quotes page URL.
	 */
	private function get_quotes_page_url() {
		global $post;

		// If we're on a page with the shortcode, use that page's URL.
		if ( $post && has_shortcode( $post->post_content, 'wcpol_vendor_quotes' ) ) {
			return get_permalink( $post->ID );
		}

		return $this->get_dashboard_page_url( 'purchase_orders' );
	}

	/**
	 * Add PO menu item to vendor dashboard (WC Vendors Pro)
	 */
	public function add_po_menu_item( $pages ) {
		$pages['purchase_orders'] = array(
			'label'      => __( 'Quotes', 'wc-purchase-order-limits' ),
			'slug'       => 'purchase_orders',
			'id'         => 'purchase_orders',
			'permission' => true,
			'priority'   => 45,
		);

		return $pages;
	}

	/**
	 * Register custom page with WC Vendors Pro
	 */
	public function register_custom_page( $custom_pages ) {
		$custom_pages['purchase_orders'] = array(
			'slug' => 'purchase_orders',
		);

		return $custom_pages;
	}

	/**
	 * Handle custom page display
	 */
	public function handle_custom_page( $object, $object_id, $template, $custom ) {
		// Check both custom and object parameters since WC Vendors Pro handles them differently
		if ( 'purchase_orders' === $custom || 'purchase_orders' === $object ) {
			$this->render_po_page();
		}
	}

	/**
	 * Maybe load purchase orders page if object matches
	 * This is a fallback for when the custom page system doesn't catch it
	 */
	public function maybe_load_purchase_orders() {
		// Check if we're on the purchase_orders page using multiple methods
		$object = get_query_var( 'object' );

		if ( 'purchase_orders' === $object ) {
			// Output the PO page content
			$this->render_po_page();

			// Add a closing div if needed for dashboard wrapper
			echo '</div>'; // Close any open dashboard content div

			// Prevent default dashboard content from loading
			return true;
		}
	}

	/**
	 * Render PO page in vendor dashboard
	 */
	public function render_po_page() {
		// Check vendor status with compatibility
		if ( ! $this->is_vendor( get_current_user_id() ) ) {
			echo '<p>' . __( 'You must be a vendor to access this page.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}

		$vendor_id = get_current_user_id();

		// Check if viewing single PO
		if ( isset( $_GET['po_id'] ) ) {
			$this->render_single_po( intval( $_GET['po_id'] ), $vendor_id );
		} else {
			$this->render_po_list( $vendor_id );
		}
	}

	/**
	 * Render list of vendor's POs
	 */
	private function render_po_list( $vendor_id ) {
		$vendor_handler = new WCPOL_Vendor_Handler();
		$pending_pos    = $vendor_handler->get_vendor_pending_pos( $vendor_id );
		$stats          = $vendor_handler->get_vendor_stats( $vendor_id );

		?>
		<div class="wcpol-vendor-dashboard">
			<h2><?php _e( 'Quotes', 'wc-purchase-order-limits' ); ?></h2>
			
			<!-- Stats Dashboard -->
			<div class="wcpol-vendor-stats">
				<div class="wcpol-stat-box">
					<span class="wcpol-stat-value"><?php echo intval( $stats['pending'] ); ?></span>
					<span class="wcpol-stat-label"><?php _e( 'Pending', 'wc-purchase-order-limits' ); ?></span>
				</div>
				<div class="wcpol-stat-box">
					<span class="wcpol-stat-value"><?php echo intval( $stats['approved'] ); ?></span>
					<span class="wcpol-stat-label"><?php _e( 'Approved', 'wc-purchase-order-limits' ); ?></span>
				</div>
				<div class="wcpol-stat-box">
					<span class="wcpol-stat-value"><?php echo intval( $stats['rejected'] ); ?></span>
					<span class="wcpol-stat-label"><?php _e( 'Rejected', 'wc-purchase-order-limits' ); ?></span>
				</div>
				<div class="wcpol-stat-box">
					<span class="wcpol-stat-value"><?php echo wc_price( $stats['total_value'] ); ?></span>
					<span class="wcpol-stat-label"><?php _e( 'Approved Value', 'wc-purchase-order-limits' ); ?></span>
				</div>
			</div>

			<!-- Pending POs requiring action -->
			<?php if ( ! empty( $pending_pos ) ) : ?>
				<h3><?php _e( 'Pending Approval', 'wc-purchase-order-limits' ); ?></h3>
				<table class="wcpol-vendor-po-table">
					<thead>
						<tr>
							<th><?php _e( 'Quote Number', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Customer', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Date', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Items', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Total', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Actions', 'wc-purchase-order-limits' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $pending_pos as $po ) :
							$customer     = get_user_by( 'id', $po->user_id );
							$vendor_items = $vendor_handler->get_vendor_po_items( $vendor_id, $po->id );
							$vendor_total = array_sum( array_column( $vendor_items, 'total' ) );
							?>
							<tr>
								<td><?php echo esc_html( $po->po_number ); ?></td>
								<td><?php echo $customer ? esc_html( $customer->display_name ) : __( 'Unknown', 'wc-purchase-order-limits' ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ) ); ?></td>
								<td><?php echo count( $vendor_items ); ?></td>
								<td><?php echo wc_price( $vendor_total ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'po_id', $po->id, $this->get_quotes_page_url() ) ); ?>" class="button">
										<?php _e( 'Review', 'wc-purchase-order-limits' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e( 'No pending quotes requiring your approval.', 'wc-purchase-order-limits' ); ?></p>
			<?php endif; ?>

			<!-- All POs -->
			<h3><?php _e( 'All Quotes', 'wc-purchase-order-limits' ); ?></h3>
			<?php
			$all_vendor_items = $vendor_handler->get_vendor_po_items( $vendor_id );

			if ( empty( $all_vendor_items ) ) {
				echo '<p>' . __( 'No quotes found.', 'wc-purchase-order-limits' ) . '</p>';
			} else {
				// Group items by PO
				$grouped_pos = array();
				foreach ( $all_vendor_items as $item ) {
					if ( ! isset( $grouped_pos[ $item->po_id ] ) ) {
						$grouped_pos[ $item->po_id ] = array(
							'po_number' => $item->po_number,
							'user_id'   => $item->user_id,
							'po_status' => $item->po_status,
							'items'     => array(),
						);
					}
					$grouped_pos[ $item->po_id ]['items'][] = $item;
				}
				?>
				<table class="wcpol-vendor-po-table">
					<thead>
						<tr>
							<th><?php _e( 'Quote Number', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Customer', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Items', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Total', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Status', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Actions', 'wc-purchase-order-limits' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $grouped_pos as $po_id => $po_data ) :
							$customer = get_user_by( 'id', $po_data['user_id'] );
							$total    = array_sum( array_column( $po_data['items'], 'total' ) );
							?>
							<tr>
								<td><?php echo esc_html( $po_data['po_number'] ); ?></td>
								<td><?php echo $customer ? esc_html( $customer->display_name ) : __( 'Unknown', 'wc-purchase-order-limits' ); ?></td>
								<td><?php echo count( $po_data['items'] ); ?></td>
								<td><?php echo wc_price( $total ); ?></td>
								<td>
									<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po_data['po_status'] ); ?>">
										<?php
										if ( 'partial' === $po_data['po_status'] ) {
											echo esc_html( __( 'Processing', 'wc-purchase-order-limits' ) );
										} else {
											echo esc_html( ucfirst( $po_data['po_status'] ) );
										}
										?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'po_id', $po_id, $this->get_quotes_page_url() ) ); ?>" class="button">
										<?php _e( 'View', 'wc-purchase-order-limits' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render single PO for vendor
	 */
	private function render_single_po( $po_id, $vendor_id ) {
		$po_handler     = new WCPOL_Purchase_Order();
		$vendor_handler = new WCPOL_Vendor_Handler();

		$po = $po_handler->get_po( $po_id );
		if ( ! $po || 'trashed' === $po->status ) {
			echo '<p>' . __( 'Quote not found.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}

		$vendor_items = $vendor_handler->get_vendor_po_items( $vendor_id, $po_id );
		if ( empty( $vendor_items ) ) {
			echo '<p>' . __( 'You have no items in this order.', 'wc-purchase-order-limits' ) . '</p>';
			return;
		}

		$customer = get_user_by( 'id', $po->user_id );

		// Get existing shipping for this vendor/PO.
		$existing_shipping_row   = $vendor_handler->get_vendor_shipping( $po_id, $vendor_id );
		$existing_shipping       = $existing_shipping_row ? floatval( $existing_shipping_row->shipping_amount ) : 0;
		$existing_shipping_notes = $existing_shipping_row ? $existing_shipping_row->shipping_notes : '';

		?>
		<div class="wcpol-vendor-po-detail">
			<h2>
			<?php
			/* translators: %s: Quote number */
			printf( __( 'Quote %s', 'wc-purchase-order-limits' ), $po->po_number );
			?>
			</h2>
			
			<div class="wcpol-po-meta">
				<p><strong><?php _e( 'Customer:', 'wc-purchase-order-limits' ); ?></strong> <?php echo $customer ? esc_html( $customer->display_name . ' (' . $customer->user_email . ')' ) : __( 'Unknown', 'wc-purchase-order-limits' ); ?></p>
				<p><strong><?php _e( 'Date:', 'wc-purchase-order-limits' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ); ?></p>
				<p><strong><?php _e( 'Status:', 'wc-purchase-order-limits' ); ?></strong>
					<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po->status ); ?>">
						<?php
						if ( 'partial' === $po->status ) {
							_e( 'Processing (Waiting for other vendors)', 'wc-purchase-order-limits' );
						} else {
							echo ucfirst( $po->status );
						}
						?>
					</span>
				</p>
			</div>

			<?php if ( $po->reference ) : ?>
				<div class="wcpol-po-reference">
					<p><strong><?php _e( 'Reference Number:', 'wc-purchase-order-limits' ); ?></strong> <?php echo esc_html( $po->reference ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( $po->required_date ) : ?>
				<div class="wcpol-po-required-date">
					<p><strong><?php _e( 'Requested Delivery:', 'wc-purchase-order-limits' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $po->required_date ) ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( $po->notes ) : ?>
				<div class="wcpol-po-notes">
					<h3><?php _e( 'Customer Notes', 'wc-purchase-order-limits' ); ?></h3>
					<p><?php echo nl2br( esc_html( $po->notes ) ); ?></p>
				</div>
			<?php endif; ?>

			<h3><?php _e( 'Your Items', 'wc-purchase-order-limits' ); ?></h3>
			
			<form id="wcpol-vendor-po-form" method="post">
				<?php wp_nonce_field( 'wcpol_vendor_po_' . $po_id ); ?>
				<input type="hidden" name="po_id" value="<?php echo esc_attr( $po_id ); ?>" />
				
				<table class="wcpol-vendor-items-table">
					<thead>
						<tr>
							<th><?php _e( 'Product', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'SKU', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Quantity', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Original Price', 'wc-purchase-order-limits' ); ?></th>
							<?php if ( get_option( 'wcpol_vendor_can_edit_price', 'yes' ) === 'yes' ) : ?>
								<th><?php _e( 'Your Price', 'wc-purchase-order-limits' ); ?></th>
							<?php endif; ?>
							<th><?php _e( 'Total', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Availability', 'wc-purchase-order-limits' ); ?></th>
							<th><?php _e( 'Notes', 'wc-purchase-order-limits' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$total       = 0;
						$all_pending = true;
						foreach ( $vendor_items as $item ) :
							$product = wc_get_product( $item->product_id );
							if ( ! $product ) {
								continue;
							}

							$unit_price = $item->vendor_price ? $item->vendor_price : $item->price;
							// Use the stored total from database - it's already calculated correctly
							$line_total = $item->total;
							$total     += $line_total;

							if ( 'pending' !== $item->status ) {
								$all_pending = false;
							}
							?>
							<tr>
								<td>
									<?php echo esc_html( $product->get_name() ); ?>
								</td>
								<td>
									<?php
										$sku = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
										echo '' !== $sku ? esc_html( $sku ) : '—';
									?>
								</td>
								<td><?php echo esc_html( $item->quantity ); ?></td>
								<td><?php echo wc_price( $item->price ); ?></td>
								<?php if ( 'yes' === get_option( 'wcpol_vendor_can_edit_price', 'yes' ) ) : ?>
									<td>
										<?php if ( 'pending' === $item->status ) : ?>
											<input type="number" 
													name="vendor_price[<?php echo $item->id; ?>]" 
													value="<?php echo esc_attr( wc_format_localized_price( $unit_price ) ); ?>" 
													step="0.01" 
													min="0"
													class="wcpol-vendor-price-input"
													data-item-id="<?php echo $item->id; ?>"
													data-quantity="<?php echo $item->quantity; ?>" />
										<?php else : ?>
											<?php echo wc_price( $unit_price ); ?>
										<?php endif; ?>
									</td>
								<?php endif; ?>
								<td class="wcpol-item-total" data-item-id="<?php echo $item->id; ?>">
									<?php
									// Display the calculated line total (will be 0 for unavailable items)
									echo get_woocommerce_currency_symbol() . number_format( $line_total, 2 );
									?>
								</td>
								<td>
									<?php if ( 'pending' === $item->status ) : ?>
										<div class="wcpol-availability-options">
											<label>
												<input type="radio" 
														name="item_availability[<?php echo $item->id; ?>]" 
														value="available" 
														class="wcpol-item-availability"
														data-item-id="<?php echo $item->id; ?>"
														checked />
												<?php _e( 'Available', 'wc-purchase-order-limits' ); ?>
											</label>
											<br>
											<label>
												<input type="radio" 
														name="item_availability[<?php echo $item->id; ?>]" 
														value="not_available" 
														class="wcpol-item-availability"
														data-item-id="<?php echo $item->id; ?>" />
												<?php _e( 'Not Available', 'wc-purchase-order-limits' ); ?>
											</label>
										</div>
									<?php else : ?>
										<span class="wcpol-item-status wcpol-item-status-<?php echo esc_attr( $item->status ); ?>">
											<?php
											if ( 'available' === $item->status ) {
												_e( 'Available', 'wc-purchase-order-limits' );
											} elseif ( 'not_available' === $item->status ) {
												_e( 'Not Available', 'wc-purchase-order-limits' );
											} else {
												echo esc_html( ucfirst( str_replace( '_', ' ', $item->status ) ) );
											}
											?>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'pending' === $item->status ) : ?>
										<textarea name="vendor_notes[<?php echo $item->id; ?>]" 
													class="wcpol-vendor-notes-input"
													rows="2" 
													cols="20"><?php echo esc_textarea( $item->vendor_notes ); ?></textarea>
									<?php else : ?>
										<?php echo nl2br( esc_html( $item->vendor_notes ) ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="<?php echo get_option( 'wcpol_vendor_can_edit_price', 'yes' ) === 'yes' ? '4' : '3'; ?>" style="text-align: right;">
								<?php _e( 'Items Subtotal:', 'wc-purchase-order-limits' ); ?>
							</th>
							<th class="wcpol-vendor-subtotal"><?php echo get_woocommerce_currency_symbol() . number_format( $total, 2 ); ?></th>
							<th colspan="2"></th>
						</tr>
						<tr>
							<th colspan="<?php echo get_option( 'wcpol_vendor_can_edit_price', 'yes' ) === 'yes' ? '4' : '3'; ?>" style="text-align: right;">
								<?php _e( 'Shipping:', 'wc-purchase-order-limits' ); ?>
							</th>
							<th class="wcpol-vendor-shipping-display"><?php echo get_woocommerce_currency_symbol() . number_format( $existing_shipping, 2 ); ?></th>
							<th colspan="2"></th>
						</tr>
						<tr style="font-weight: bold; font-size: 1.1em;">
							<th colspan="<?php echo get_option( 'wcpol_vendor_can_edit_price', 'yes' ) === 'yes' ? '4' : '3'; ?>" style="text-align: right;">
								<?php _e( 'Grand Total:', 'wc-purchase-order-limits' ); ?>
							</th>
							<th class="wcpol-vendor-total"><?php echo get_woocommerce_currency_symbol() . number_format( $total + $existing_shipping, 2 ); ?></th>
							<th colspan="2"></th>
						</tr>
					</tfoot>
				</table>

				<?php if ( $all_pending && in_array( $po->status, array( 'pending', 'requested', 'partial' ) ) ) : ?>

					<!-- Vendor Shipping Section -->
					<div class="wcpol-vendor-shipping-section" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
						<h3><?php _e( 'Shipping Cost', 'wc-purchase-order-limits' ); ?></h3>
						<p><?php _e( 'Enter the shipping cost for all your items in this order:', 'wc-purchase-order-limits' ); ?></p>
						<div class="wcpol-shipping-input-wrapper" style="display: flex; align-items: center; gap: 5px; margin-bottom: 10px;">
							<label for="vendor_shipping" style="font-weight: bold;">
								<?php echo get_woocommerce_currency_symbol(); ?>
							</label>
							<input type="number"
								id="vendor_shipping"
								name="vendor_shipping"
								value="<?php echo esc_attr( number_format( $existing_shipping, 2, '.', '' ) ); ?>"
								step="0.01"
								min="0"
								class="wcpol-vendor-shipping-input"
								placeholder="0.00"
								style="width: 120px;" />
						</div>
						<textarea name="shipping_notes"
							id="shipping_notes"
							rows="2"
							style="width: 100%; max-width: 400px;"
							placeholder="<?php esc_attr_e( 'Optional shipping notes (e.g., carrier, delivery estimate)...', 'wc-purchase-order-limits' ); ?>"><?php echo esc_textarea( $existing_shipping_notes ); ?></textarea>
					</div>

					<?php
					// Check if this is a multi-vendor PO
					$vendor_count    = isset( $po->vendor_count ) ? intval( $po->vendor_count ) : 0;
					$is_multi_vendor = ( $vendor_count > 1 );

					if ( $is_multi_vendor ) :
						?>
						<!-- Multi-vendor PO - Fixed 30 days, no adjustment -->
						<div class="wcpol-validity-period" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
							<h3><?php _e( 'Validity Period', 'wc-purchase-order-limits' ); ?></h3>
							<p><?php _e( 'This is a multi-vendor order. It will be valid for:', 'wc-purchase-order-limits' ); ?></p>
							<p style="font-size: 18px; font-weight: bold; color: #333;">
								<?php _e( '30 Days', 'wc-purchase-order-limits' ); ?>
							</p>
							<input type="hidden" name="validity_days" value="30" />
							<p style="margin-top: 10px; font-style: italic; color: #666;">
								<?php _e( 'Multi-vendor quotes have a fixed validity period of 30 days.', 'wc-purchase-order-limits' ); ?>
							</p>
						</div>
					<?php else : ?>
						<!-- Single vendor PO - Can choose validity period -->
						<div class="wcpol-validity-period" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
							<h3><?php _e( 'Validity Period', 'wc-purchase-order-limits' ); ?></h3>
							<p><?php _e( 'Select how long this quote will be valid for:', 'wc-purchase-order-limits' ); ?></p>
							<div class="wcpol-validity-options">
								<label style="display: block; margin: 10px 0;">
									<input type="radio" name="validity_days" value="30" checked required />
									<?php _e( '30 Days', 'wc-purchase-order-limits' ); ?>
								</label>
								<label style="display: block; margin: 10px 0;">
									<input type="radio" name="validity_days" value="60" required />
									<?php _e( '60 Days', 'wc-purchase-order-limits' ); ?>
								</label>
								<label style="display: block; margin: 10px 0;">
									<input type="radio" name="validity_days" value="90" required />
									<?php _e( '90 Days', 'wc-purchase-order-limits' ); ?>
								</label>
							</div>
							<p style="margin-top: 10px; font-style: italic; color: #666;">
								<?php _e( 'The customer will need to approve this quote within the selected timeframe.', 'wc-purchase-order-limits' ); ?>
							</p>
						</div>
					<?php endif; ?>
					
					<div class="wcpol-vendor-po-actions" style="margin-top: 20px;">
						<button type="button" class="button button-primary wcpol-issue-po" data-po-id="<?php echo $po_id; ?>" style="margin-right: 10px;">
							<?php _e( 'Issue Quote', 'wc-purchase-order-limits' ); ?>
						</button>
						
						<a href="<?php echo esc_url( $this->get_quotes_page_url() ); ?>" class="button">
							<?php _e( 'Back to Quotes', 'wc-purchase-order-limits' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="wcpol-vendor-po-actions" style="margin-top: 20px;">
						<a href="<?php echo esc_url( $this->get_quotes_page_url() ); ?>" class="button">
							<?php _e( 'Back to Quotes', 'wc-purchase-order-limits' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</form>
		</div>

		
		<?php

		// Enqueue vendor scripts
		wp_enqueue_script(
			'wcpol-vendor',
			WCPOL_PLUGIN_URL . 'assets/js/wcpol-vendor.js',
			array( 'jquery' ),
			WCPOL_VERSION,
			true
		);

		wp_localize_script(
			'wcpol-vendor',
			'wcpol_vendor',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'wcpol_vendor_nonce' ),
				'currency_symbol'   => get_woocommerce_currency_symbol(),
				'currency_decimals' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
				'strings'           => array(
					'confirm_approve' => __( 'Are you sure you want to approve all items in this quote?', 'wc-purchase-order-limits' ),
					'confirm_reject'  => __( 'Are you sure you want to reject all items in this quote?', 'wc-purchase-order-limits' ),
					'reason_required' => __( 'Please provide a reason for rejection.', 'wc-purchase-order-limits' ),
					'processing'      => __( 'Processing...', 'wc-purchase-order-limits' ),
					'updating'        => __( 'Updating...', 'wc-purchase-order-limits' ),
					'approve'         => __( 'Approve All Items', 'wc-purchase-order-limits' ),
					'reject'          => __( 'Reject All Items', 'wc-purchase-order-limits' ),
					'update_items'    => __( 'Update Items', 'wc-purchase-order-limits' ),
					'error'           => __( 'An error occurred. Please try again.', 'wc-purchase-order-limits' ),
				),
			)
		);
	}
}
