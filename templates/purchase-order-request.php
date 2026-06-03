<?php
/**
 * Purchase Order Request Page Template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/wcpol/purchase-order-request.php
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 */
do_action( 'woocommerce_before_main_content' );
?>

<div class="wcpol-po-request-page">
	<?php
	/**
	 * Hook: wcpol_before_po_request_form.
	 */
	do_action( 'wcpol_before_po_request_form' );

	// Display the PO request form
	echo do_shortcode( '[wcpol_po_request]' );

	/**
	 * Hook: wcpol_after_po_request_form.
	 */
	do_action( 'wcpol_after_po_request_form' );
	?>
</div>

<?php
/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
?>
