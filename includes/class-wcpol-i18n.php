<?php
/**
 * Define the internationalization functionality
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_i18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'wc-purchase-order-limits',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
