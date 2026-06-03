<?php
/**
 * Cart validation and limit checking
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Cart_Validator {

	/**
	 * Get the purchase limit for the current user
	 */
	public function get_user_limit() {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		// First check for user-specific limit - Fixed SQL injection vulnerability
		$user_limit = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT limit_amount FROM {$wpdb->prefix}wcpol_user_limits 
             WHERE user_id = %d AND active = 1 
             ORDER BY priority DESC LIMIT 1",
				$user_id
			)
		);

		if ( $user_limit ) {
			return floatval( $user_limit->limit_amount );
		}

		// Check for role-based limit
		if ( ! empty( $user->roles ) ) {
			// Build placeholders for prepared statement.
			$placeholders     = array_fill( 0, count( $user->roles ), '%s' );
			$placeholders_str = implode( ',', $placeholders );

			// Fixed SQL injection vulnerability - use proper table reference.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic IN placeholders built safely above.
			$query = $wpdb->prepare(
				"SELECT limit_amount FROM {$wpdb->prefix}wcpol_user_limits
                 WHERE user_role IN ($placeholders_str) AND active = 1
                 ORDER BY priority DESC, limit_amount DESC LIMIT 1",
				$user->roles
			);

			$role_limit = $wpdb->get_row( $query );

			if ( $role_limit ) {
				return floatval( $role_limit->limit_amount );
			}
		}

		// Return global limit
		$global_limit = get_option( 'wcpol_global_limit', 5000 );
		return floatval( $global_limit );
	}

	/**
	 * Get cart total based on limit type setting
	 */
	public function get_cart_total_for_limit() {
		if ( ! WC()->cart ) {
			return 0;
		}

		$limit_type = get_option( 'wcpol_limit_type', 'total' );

		if ( 'subtotal' === $limit_type ) {
			return WC()->cart->get_subtotal();
		} else {
			return WC()->cart->get_total( 'edit' );
		}
	}

	/**
	 * Check if cart exceeds limit
	 */
	public function is_cart_over_limit() {
		$limit = $this->get_user_limit();

		if ( ! $limit || $limit <= 0 ) {
			return false;
		}

		$cart_total = $this->get_cart_total_for_limit();

		return $cart_total > $limit;
	}

	/**
	 * Validate cart limit during checkout
	 */
	public function validate_cart_limit() {
		if ( 'yes' !== get_option( 'wcpol_enable_limits', 'yes' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( $this->is_cart_over_limit() ) {
			// No action needed here - checkout redirect will handle everything
			// User can still click checkout button and will be redirected to PO form
		}
	}

	/**
	 * Check if voluntary PO requests are allowed
	 */
	public function can_request_voluntary_po() {
		// Check if feature is enabled
		if ( 'yes' !== get_option( 'wcpol_allow_voluntary_po', 'no' ) ) {
			return false;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check if cart has items
		if ( WC()->cart->is_empty() ) {
			return false;
		}

		// Check if NOT over limit (voluntary means under limit)
		if ( $this->is_cart_over_limit() ) {
			return false;
		}

		return true;
	}

	/**
	 * Display limit information in cart
	 */
	public function display_limit_info() {
		if ( 'yes' !== get_option( 'wcpol_enable_limits', 'yes' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$limit = $this->get_user_limit();
		if ( ! $limit || $limit <= 0 ) {
			return;
		}

		$cart_total = $this->get_cart_total_for_limit();
		$remaining  = $limit - $cart_total;
		$percentage = ( $cart_total / $limit ) * 100;

		?>
		<tr class="wcpol-limit-info">
			<th><?php _e( 'Purchase Limit', 'wc-purchase-order-limits' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Purchase Limit', 'wc-purchase-order-limits' ); ?>">
				<div class="wcpol-limit-display">
					<div class="wcpol-limit-text">
						<?php echo wc_price( $limit ); ?>
					</div>
					<div class="wcpol-limit-progress">
						<div class="wcpol-limit-progress-bar" style="width: <?php echo min( 100, $percentage ); ?>%"></div>
					</div>
					<?php if ( $remaining > 0 ) : ?>
						<div class="wcpol-limit-remaining">
							<?php
							printf(
								/* translators: %s: remaining amount */
								__( 'Remaining: %s', 'wc-purchase-order-limits' ),
								wc_price( $remaining )
							);
							?>
						</div>
					<?php else : ?>
						<div class="wcpol-limit-exceeded">
							<?php _e( 'Limit exceeded - Purchase Order required', 'wc-purchase-order-limits' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}
}