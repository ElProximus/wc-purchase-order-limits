<?php
/**
 * Admin view: User Limits
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
	<h1 class="wp-heading-inline"><?php esc_html_e( 'User Purchase Limits', 'wc-purchase-order-limits' ); ?></h1>
	
	<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Limit saved successfully.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Limit deleted successfully.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wcpol-add-limit-form">
		<h2><?php esc_html_e( 'Add/Edit Limit', 'wc-purchase-order-limits' ); ?></h2>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'wcpol_user_limits' ); ?>
			<input type="hidden" name="limit_id" value="" />
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="limit_type"><?php esc_html_e( 'Limit Type', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<select name="limit_type" id="limit_type">
							<option value="role"><?php esc_html_e( 'Role-based', 'wc-purchase-order-limits' ); ?></option>
							<option value="user"><?php esc_html_e( 'User-specific', 'wc-purchase-order-limits' ); ?></option>
						</select>
					</td>
				</tr>
				
				<tr id="role_row">
					<th scope="row">
						<label for="user_role"><?php esc_html_e( 'User Role', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<select name="user_role" id="user_role">
							<?php foreach ( wp_roles()->get_names() as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>">
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				
				<tr id="user_row" style="display: none;">
					<th scope="row">
						<label for="user_id"><?php esc_html_e( 'User', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<select name="user_id" id="user_id">
							<option value=""><?php esc_html_e( 'Select a user', 'wc-purchase-order-limits' ); ?></option>
							<?php
							$users = get_users( array( 'orderby' => 'display_name' ) );
							foreach ( $users as $user ) :
								?>
								<option value="<?php echo esc_attr( $user->ID ); ?>">
									<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="limit_amount"><?php esc_html_e( 'Limit Amount', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<input type="number" 
								name="limit_amount" 
								id="limit_amount" 
								step="0.01" 
								min="0" 
								required 
								class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Maximum cart amount before purchase order is required.', 'wc-purchase-order-limits' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="limit_calculation"><?php esc_html_e( 'Calculation Method', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<select name="limit_calculation" id="limit_calculation">
							<option value="subtotal"><?php esc_html_e( 'Cart Subtotal', 'wc-purchase-order-limits' ); ?></option>
							<option value="total"><?php esc_html_e( 'Cart Total (including tax/shipping)', 'wc-purchase-order-limits' ); ?></option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="priority"><?php esc_html_e( 'Priority', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<input type="number" 
								name="priority" 
								id="priority" 
								value="0" 
								class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Higher priority limits override lower ones.', 'wc-purchase-order-limits' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="active"><?php esc_html_e( 'Active', 'wc-purchase-order-limits' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="active" id="active" value="1" checked />
						<label for="active"><?php esc_html_e( 'Enable this limit', 'wc-purchase-order-limits' ); ?></label>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Limit', 'wc-purchase-order-limits' ); ?>" />
			</p>
		</form>
	</div>

	<h2><?php esc_html_e( 'Existing Limits', 'wc-purchase-order-limits' ); ?></h2>
	
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Type', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'User/Role', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Limit Amount', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Calculation', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Priority', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wc-purchase-order-limits' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wc-purchase-order-limits' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $limits ) ) : ?>
				<?php foreach ( $limits as $limit ) : ?>
					<tr>
						<td>
							<?php echo $limit->user_id ? esc_html__( 'User', 'wc-purchase-order-limits' ) : esc_html__( 'Role', 'wc-purchase-order-limits' ); ?>
						</td>
						<td>
							<?php
							if ( $limit->user_id ) {
								echo esc_html( $limit->display_name ?: __( 'User #', 'wc-purchase-order-limits' ) . $limit->user_id );
							} else {
								$role_names = wp_roles()->get_names();
								echo esc_html( $role_names[ $limit->user_role ] ?? $limit->user_role );
							}
							?>
						</td>
						<td><?php echo wc_price( $limit->limit_amount ); ?></td>
						<td><?php echo esc_html( ucfirst( $limit->limit_type ) ); ?></td>
						<td><?php echo esc_html( $limit->priority ); ?></td>
						<td>
							<?php if ( $limit->active ) : ?>
								<span style="color: green;">✓ <?php esc_html_e( 'Active', 'wc-purchase-order-limits' ); ?></span>
							<?php else : ?>
								<span style="color: red;">✗ <?php esc_html_e( 'Inactive', 'wc-purchase-order-limits' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" 
									class="button button-small wcpol-edit-limit" 
									data-limit='<?php echo esc_attr( wp_json_encode( $limit ) ); ?>'>
								<?php esc_html_e( 'Edit', 'wc-purchase-order-limits' ); ?>
							</button>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wcpol-user-limits&action=delete&limit_id=' . $limit->id ), 'delete_limit_' . $limit->id ) ); ?>" 
								class="button button-small"
								onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this limit?', 'wc-purchase-order-limits' ); ?>');">
								<?php esc_html_e( 'Delete', 'wc-purchase-order-limits' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No limits configured. The global limit will apply to all users.', 'wc-purchase-order-limits' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<script>
jQuery(document).ready(function($) {
	$('#limit_type').on('change', function() {
		if ($(this).val() === 'user') {
			$('#user_row').show();
			$('#role_row').hide();
		} else {
			$('#user_row').hide();
			$('#role_row').show();
		}
	});
	
	$('.wcpol-edit-limit').on('click', function() {
		var limit = $(this).data('limit');
		
		$('input[name="limit_id"]').val(limit.id);
		$('#limit_amount').val(limit.limit_amount);
		$('#limit_calculation').val(limit.limit_type);
		$('#priority').val(limit.priority);
		$('#active').prop('checked', limit.active == 1);
		
		if (limit.user_id) {
			$('#limit_type').val('user').trigger('change');
			$('#user_id').val(limit.user_id);
		} else {
			$('#limit_type').val('role').trigger('change');
			$('#user_role').val(limit.user_role);
		}
		
		$('html, body').animate({
			scrollTop: $('.wcpol-add-limit-form').offset().top - 50
		}, 500);
	});
});
</script>