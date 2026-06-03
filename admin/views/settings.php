<?php
/**
 * Admin view: Settings
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

// Get active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>

<div class="wrap wcpol-admin-wrap">
	<h1><?php esc_html_e( 'Quote Settings', 'wc-purchase-order-limits' ); ?></h1>
	
	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>
	
	<h2 class="nav-tab-wrapper">
		<a href="?page=wcpol-settings&tab=general"
			class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'wc-purchase-order-limits' ); ?>
		</a>
		<a href="?page=wcpol-settings&tab=email"
			class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Email', 'wc-purchase-order-limits' ); ?>
		</a>
		<a href="?page=wcpol-settings&tab=buddyboss"
			class="nav-tab <?php echo 'buddyboss' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'BuddyBoss', 'wc-purchase-order-limits' ); ?>
		</a>
		<a href="?page=wcpol-settings&tab=pdf"
			class="nav-tab <?php echo 'pdf' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'PDF', 'wc-purchase-order-limits' ); ?>
		</a>
		<a href="?page=wcpol-settings&tab=vendor"
			class="nav-tab <?php echo 'vendor' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Vendor', 'wc-purchase-order-limits' ); ?>
		</a>
	</h2>
	
	<form method="post" action="options.php">
		<?php
		switch ( $active_tab ) {
			case 'email':
				settings_fields( 'wcpol_email_settings' );
				do_settings_sections( 'wcpol_email_settings' );
				break;

			case 'buddyboss':
				settings_fields( 'wcpol_bb_settings' );
				do_settings_sections( 'wcpol_bb_settings' );
				break;

			case 'pdf':
				settings_fields( 'wcpol_pdf_settings' );
				do_settings_sections( 'wcpol_pdf_settings' );
				?>
				<div class="wcpol-pdf-status">
					<h3><?php esc_html_e( 'PDF Library Status', 'wc-purchase-order-limits' ); ?></h3>
					<?php
					$tcpdf_path = WCPOL_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
					if ( file_exists( $tcpdf_path ) ) {
						echo '<p style="color: green;">✓ ' . esc_html__( 'TCPDF library is installed and ready.', 'wc-purchase-order-limits' ) . '</p>';
					} else {
						echo '<p style="color: orange;">⚠ ' . esc_html__( 'TCPDF library not found. PDF generation is disabled.', 'wc-purchase-order-limits' ) . '</p>';
						echo '<p>' . esc_html__( 'To enable PDF generation, install TCPDF in the vendor/tcpdf/ directory.', 'wc-purchase-order-limits' ) . '</p>';
					}
					?>
				</div>
				<?php
				break;

			case 'vendor':
				settings_fields( 'wcpol_vendor_settings' );
				do_settings_sections( 'wcpol_vendor_settings' );
				break;

			default:
				settings_fields( 'wcpol_general_settings' );
				do_settings_sections( 'wcpol_general_settings' );
				break;
		}

		submit_button();
		?>
	</form>
	
	<?php if ( 'general' === $active_tab ) : ?>
		<div class="wcpol-settings-info">
			<h3><?php esc_html_e( 'How Purchase Limits Work', 'wc-purchase-order-limits' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Cart totals are checked against the customer's purchase limit.', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'If the cart exceeds the limit, attempting to access checkout redirects the customer to a quote request form on the cart page.', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'The customer submits a quote request with optional notes, reference number, and requested delivery date.', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'Vendors receive notifications and can approve/reject items.', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'Once approved, the customer can download the quote as a PDF.', 'wc-purchase-order-limits' ); ?></li>
			</ol>
			
			<h4><?php esc_html_e( 'Limit Priority', 'wc-purchase-order-limits' ); ?></h4>
			<p><?php esc_html_e( 'Limits are applied in this order:', 'wc-purchase-order-limits' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'User-specific limits (highest priority)', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'Role-based limits', 'wc-purchase-order-limits' ); ?></li>
				<li><?php esc_html_e( 'Global limit (lowest priority)', 'wc-purchase-order-limits' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Voluntary Requests & Shortcode', 'wc-purchase-order-limits' ); ?></h4>
			<p><?php esc_html_e( 'When "Allow Voluntary Quote Requests" is enabled, customers under their limit will see a "Request Quote" button on the cart page. You can also place a dedicated quote request form anywhere using the shortcode:', 'wc-purchase-order-limits' ); ?></p>
			<pre style="background:#f6f7f7; padding:10px; border:1px solid #ccd0d4; border-radius:3px;">[wcpol_po_request]</pre>
			<p><?php esc_html_e( 'Optional attribute:', 'wc-purchase-order-limits' ); ?> <code>voluntary="1"</code>. <?php esc_html_e( 'If voluntary requests are disabled and the cart is under limit, the form will redirect to checkout.', 'wc-purchase-order-limits' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<style>
.wcpol-settings-info {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	margin-top: 20px;
	border-radius: 3px;
}
.wcpol-settings-info h3 {
	margin-top: 0;
}
.wcpol-settings-info ol {
	margin-left: 20px;
}
.wcpol-pdf-status {
	background: #f9f9f9;
	padding: 15px;
	margin: 20px 0;
	border-left: 4px solid #0073aa;
}
</style>
