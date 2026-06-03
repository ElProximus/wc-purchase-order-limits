<?php
/**
 * Admin view: Purchase Orders List
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
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Quotes', 'wc-purchase-order-limits' ); ?></h1>

	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Quote permanently deleted.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['trashed'] ) && '1' === $_GET['trashed'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Quote moved to trash.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['restored'] ) && '1' === $_GET['restored'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Quote restored successfully.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php
	// Count total and trashed POs
	global $wpdb;
	$total_count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_purchase_orders WHERE status != 'trashed'" );
	$trash_count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_purchase_orders WHERE status = 'trashed'" );
	$current_status = isset( $_GET['status'] ) && 'trashed' === $_GET['status'] ? 'trashed' : 'all';
	?>
	
	<ul class="subsubsub">
		<li class="all">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders' ) ); ?>"
				class="<?php echo 'all' === $current_status ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'wc-purchase-order-limits' ); ?>
				<span class="count">(<?php echo (int) $total_count; ?>)</span>
			</a> |
		</li>
		<li class="trash">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders&status=trashed' ) ); ?>"
				class="<?php echo 'trashed' === $current_status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Trash', 'wc-purchase-order-limits' ); ?>
				<span class="count">(<?php echo (int) $trash_count; ?>)</span>
			</a>
		</li>
	</ul>
	<br class="clear">

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Quote updated successfully.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped wcpol-po-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Quote Number', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Date', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Total', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wc-purchase-order-limits' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $pos ) ) : ?>
				<?php foreach ( $pos as $po ) : ?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders&po_id=' . $po->id ) ); ?>">
									<?php echo esc_html( $po->po_number ); ?>
								</a>
							</strong>
						</td>
						<td>
							<?php if ( $po->display_name ) : ?>
								<?php echo esc_html( $po->display_name ); ?><br>
								<small><?php echo esc_html( $po->user_email ); ?></small>
							<?php else : ?>
								<?php esc_html_e( 'Guest', 'wc-purchase-order-limits' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ) ); ?></td>
						<td><?php echo wc_price( $po->total_amount ); ?></td>
						<td>
							<span class="wcpol-status wcpol-status-<?php echo esc_attr( $po->status ); ?>">
								<?php echo esc_html( ucfirst( $po->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( 'trashed' === $po->status ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wcpol-purchase-orders&action=restore&po_id=' . $po->id ), 'restore_po_' . $po->id ) ); ?>" 
									class="button button-small">
									<?php esc_html_e( 'Restore', 'wc-purchase-order-limits' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wcpol-purchase-orders&action=delete&po_id=' . $po->id ), 'delete_po_' . $po->id ) ); ?>" 
									class="button button-small" style="color: #a00;">
									<?php esc_html_e( 'Delete Permanently', 'wc-purchase-order-limits' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpol-purchase-orders&po_id=' . $po->id ) ); ?>" 
									class="button button-small">
									<?php esc_html_e( 'View', 'wc-purchase-order-limits' ); ?>
								</a>
								<?php if ( 'pending' === $po->status ) : ?>
									<button type="button" 
											class="button button-small wcpol-quick-approve" 
											data-po-id="<?php echo esc_attr( $po->id ); ?>">
										<?php esc_html_e( 'Issue', 'wc-purchase-order-limits' ); ?>
									</button>
								<?php endif; ?>
								<?php if ( current_user_can( 'administrator' ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wcpol-purchase-orders&action=trash&po_id=' . $po->id ), 'trash_po_' . $po->id ) ); ?>" 
										class="button button-small wcpol-trash-po">
										<?php esc_html_e( 'Trash', 'wc-purchase-order-limits' ); ?>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No quotes found.', 'wc-purchase-order-limits' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<script>
jQuery(document).ready(function($) {
	$('.wcpol-quick-approve').on('click', function() {
		var poId = $(this).data('po-id');
		var $button = $(this);
		$button.prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'wc-purchase-order-limits' ); ?>');
		
		$.ajax({
			url: wcpol_admin.ajax_url,
			type: 'POST',
			data: {
				action: 'wcpol_update_po_status',
				po_id: poId,
				status: 'approved',
				nonce: wcpol_admin.nonce
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					// Show inline error next to button
					var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">' + (response.data || '<?php esc_html_e( 'Error updating status', 'wc-purchase-order-limits' ); ?>') + '</span>');
					$button.after($error);
					setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
					$button.prop('disabled', false).text('<?php esc_html_e( 'Approve', 'wc-purchase-order-limits' ); ?>');
				}
			},
			error: function() {
				// Show inline error next to button
				var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;"><?php esc_html_e( 'Error updating status', 'wc-purchase-order-limits' ); ?></span>');
				$button.after($error);
				setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
				$button.prop('disabled', false).text('<?php esc_html_e( 'Approve', 'wc-purchase-order-limits' ); ?>');
			}
		});
	});
});
</script>