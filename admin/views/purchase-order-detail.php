<?php
/**
 * Admin view: Purchase Order Detail
 *
 * @package WC_Purchase_Order_Limits
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security check
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-purchase-order-limits' ) );
}
?>

<div class="wrap wcpol-admin-wrap">
	<h1>
		<?php
		/* translators: %s: Quote number */
		printf( esc_html__( 'Quote %s', 'wc-purchase-order-limits' ), esc_html( $po->po_number ) );
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Back to List', 'wc-purchase-order-limits' ); ?>
		</a>
	</h1>

	<div class="wcpol-po-detail-wrap">
		<div class="wcpol-po-meta-box">
			<h2><?php esc_html_e( 'Order Information', 'wc-purchase-order-limits' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Customer:', 'wc-purchase-order-limits' ); ?></th>
					<td>
						<?php if ( $user ) : ?>
							<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>">
								<?php echo esc_html( $user->display_name ); ?>
							</a>
							(<?php echo esc_html( $user->user_email ); ?>)
						<?php else : ?>
							<?php esc_html_e( 'User not found', 'wc-purchase-order-limits' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Created Date:', 'wc-purchase-order-limits' ); ?></th>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $po->created_date ) ) ); ?></td>
				</tr>
				<?php if ( $po->approved_date ) : ?>
					<tr>
						<th><?php esc_html_e( 'Date Issued:', 'wc-purchase-order-limits' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $po->approved_date ) ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Status:', 'wc-purchase-order-limits' ); ?></th>
					<td>
						<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po->status ); ?>">
							<?php echo esc_html( ucfirst( $po->status ) ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Amount:', 'wc-purchase-order-limits' ); ?></th>
					<td><strong><?php echo wc_price( $po->total_amount ); ?></strong></td>
				</tr>
				<?php if ( $po->validity_days && $po->expiry_date ) : ?>
					<tr>
						<th><?php esc_html_e( 'Validity Period:', 'wc-purchase-order-limits' ); ?></th>
						<td><?php echo esc_html( $po->validity_days ); ?> <?php esc_html_e( 'days', 'wc-purchase-order-limits' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Expiry Date:', 'wc-purchase-order-limits' ); ?></th>
						<td>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->expiry_date ) ) ); ?>
							<?php
							// Check if expired
							$today          = current_time( 'Y-m-d' );
							$days_remaining = floor( ( strtotime( $po->expiry_date ) - strtotime( $today ) ) / ( 60 * 60 * 24 ) );
							if ( $days_remaining > 0 ) {
								/* translators: %d: number of days remaining */
								echo ' <em>(' . sprintf( esc_html__( '%d days remaining', 'wc-purchase-order-limits' ), $days_remaining ) . ')</em>';
							} elseif ( 0 === $days_remaining ) {
								echo ' <em style="color: #ff6b6b;">(' . esc_html__( 'Expires today', 'wc-purchase-order-limits' ) . ')</em>';
							} else {
								echo ' <em style="color: #ff0000;">(' . esc_html__( 'Expired', 'wc-purchase-order-limits' ) . ')</em>';
							}
							?>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( $po->reference ) : ?>
					<tr>
						<th><?php esc_html_e( 'Reference Number:', 'wc-purchase-order-limits' ); ?></th>
						<td><?php echo esc_html( $po->reference ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $po->required_date ) : ?>
					<tr>
						<th><?php esc_html_e( 'Requested Delivery:', 'wc-purchase-order-limits' ); ?></th>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->required_date ) ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $po->notes ) : ?>
					<tr>
						<th><?php esc_html_e( 'Customer Notes:', 'wc-purchase-order-limits' ); ?></th>
						<td><?php echo nl2br( esc_html( $po->notes ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $po->pdf_url ) : ?>
					<tr>
						<th><?php esc_html_e( 'PDF:', 'wc-purchase-order-limits' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $po->pdf_url ); ?>" target="_blank" class="button">
								<?php esc_html_e( 'View PDF', 'wc-purchase-order-limits' ); ?>
							</a>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</div>

		<div class="wcpol-po-items">
			<h2><?php esc_html_e( 'Order Items', 'wc-purchase-order-limits' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Quantity', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Price', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Availability', 'wc-purchase-order-limits' ); ?></th>
						<th><?php esc_html_e( 'Vendor Notes', 'wc-purchase-order-limits' ); ?></th>
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
								$vendor_name = $vendor->display_name;
							}
						}

						$unit_price = $item->vendor_price ? $item->vendor_price : $item->price;
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $item->product_id . '&action=edit' ) ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</a>
							</td>
							<td>
								<?php
								$sku = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
								echo '' !== $sku ? esc_html( $sku ) : '—';
								?>
							</td>
							<td>
								<?php if ( $item->vendor_id > 0 ) : ?>
									<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $item->vendor_id ) ); ?>">
										<?php echo esc_html( $vendor_name ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $vendor_name ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $item->quantity ); ?></td>
							<td><?php echo wc_price( $unit_price ); ?></td>
							<td><?php echo wc_price( $item->total ); ?></td>
							<td>
								<span class="wcpol-item-status wcpol-item-status-<?php echo esc_attr( $item->status ); ?>">
									<?php echo esc_html( ucfirst( $item->status ) ); ?>
								</span>
							</td>
							<td><?php echo $item->vendor_notes ? nl2br( esc_html( $item->vendor_notes ) ) : '-'; ?></td>
						</tr>
					<?php endforeach; ?>

					<?php
					// Display vendor shipping rows.
					$vendor_handler       = new WCPOL_Vendor_Handler();
					$vendor_shipping_rows = $vendor_handler->get_po_vendor_shipping( $po->id );

					if ( ! empty( $vendor_shipping_rows ) ) :
						foreach ( $vendor_shipping_rows as $shipping_row ) :
							if ( floatval( $shipping_row->shipping_amount ) > 0 ) :
								// Get vendor name.
								$shipping_vendor_name = __( 'Shop', 'wc-purchase-order-limits' );
								if ( $shipping_row->vendor_id > 0 ) {
									$shipping_vendor = get_user_by( 'id', $shipping_row->vendor_id );
									if ( $shipping_vendor ) {
										$shipping_vendor_name = $shipping_vendor->display_name;
									}
								}
								?>
								<tr style="background: #f9f9f9;">
									<td colspan="5" style="text-align: right;">
										<?php
										printf(
											/* translators: %s: vendor name */
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
						<th colspan="5" style="text-align: right;"><?php esc_html_e( 'Total:', 'wc-purchase-order-limits' ); ?></th>
						<th><?php echo wc_price( $po->total_amount ); ?></th>
						<th colspan="2"></th>
					</tr>
				</tfoot>
			</table>
		</div>

		<?php if ( ! empty( $po_history ) ) : ?>
			<div class="wcpol-po-history">
				<h2><?php esc_html_e( 'Order History', 'wc-purchase-order-limits' ); ?></h2>
				<?php foreach ( $po_history as $history ) : ?>
					<div class="wcpol-history-entry">
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
								<?php
								/* translators: %s: user display name */
								printf( esc_html__( 'by %s', 'wc-purchase-order-limits' ), esc_html( $history->display_name ) );
								?>
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

		<div class="wcpol-actions">
			<h2><?php esc_html_e( 'Actions', 'wc-purchase-order-limits' ); ?></h2>
			
			<?php if ( 'pending' === $po->status ) : ?>
				<button type="button" class="button button-primary wcpol-change-status" data-status="approved">
					<?php esc_html_e( 'Approve Quote', 'wc-purchase-order-limits' ); ?>
				</button>
				<button type="button" class="button wcpol-change-status" data-status="rejected">
					<?php esc_html_e( 'Reject Quote', 'wc-purchase-order-limits' ); ?>
				</button>
			<?php endif; ?>
			
			<?php if ( 'approved' === $po->status && ! $po->pdf_url ) : ?>
				<button type="button" class="button wcpol-generate-pdf">
					<?php esc_html_e( 'Generate PDF', 'wc-purchase-order-limits' ); ?>
				</button>
			<?php endif; ?>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders' ) ); ?>" class="button">
				<?php esc_html_e( 'Back to List', 'wc-purchase-order-limits' ); ?>
			</a>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('.wcpol-change-status').on('click', function() {
		var newStatus = $(this).data('status');
		var confirmMsg = newStatus === 'approved'
			? '<?php esc_html_e( 'Are you sure you want to approve this quote?', 'wc-purchase-order-limits' ); ?>'
			: '<?php esc_html_e( 'Are you sure you want to reject this quote?', 'wc-purchase-order-limits' ); ?>';
		
		if (!confirm(confirmMsg)) {
			return;
		}
		
		var $button = $(this);
		$button.prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'wc-purchase-order-limits' ); ?>');
		
		$.ajax({
			url: wcpol_admin.ajax_url,
			type: 'POST',
			data: {
				action: 'wcpol_update_po_status',
				po_id: <?php echo intval( $po->id ); ?>,
				status: newStatus,
				nonce: wcpol_admin.nonce
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					// Show inline error
					var $error = $('<div class="notice notice-error" style="margin-top: 10px;"><p>' + (response.data || '<?php esc_html_e( 'Error updating status', 'wc-purchase-order-limits' ); ?>') + '</p></div>');
					$('.wcpol-actions').prepend($error);
					setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
					$button.prop('disabled', false).text($button.text());
				}
			}
		});
	});
	
	$('.wcpol-generate-pdf').on('click', function() {
		var $button = $(this);
		$button.prop('disabled', true).text('<?php esc_html_e( 'Generating...', 'wc-purchase-order-limits' ); ?>');
		
		// This would trigger PDF generation - implement as needed
		// Show inline notice instead of alert
		var $notice = $('<div class="notice notice-info" style="margin-top: 10px;"><p><?php esc_html_e( 'PDF generation would be triggered here', 'wc-purchase-order-limits' ); ?></p></div>');
		$('.wcpol-actions').prepend($notice);
		setTimeout(function() { $notice.fadeOut(function() { $(this).remove(); }); }, 3000);
		$button.prop('disabled', false).text('<?php esc_html_e( 'Generate PDF', 'wc-purchase-order-limits' ); ?>');
	});
});
</script>
