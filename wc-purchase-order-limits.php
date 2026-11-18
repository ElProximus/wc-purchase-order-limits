<?php
/**
 * Plugin Name: WC Purchase Order Limits
 * Plugin URI: https://example.com/wc-purchase-order-limits
 * Description: Adds purchase order limits to WooCommerce with multi-vendor support via LS Vendors and BuddyBoss notifications
 * Version: 1.2.3
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-purchase-order-limits
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'WCPOL_VERSION', '1.2.3' );
define( 'WCPOL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPOL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPOL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wc_purchase_order_limits() {
	require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-activator.php';
	WCPOL_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wc_purchase_order_limits() {
	require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-deactivator.php';
	WCPOL_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wc_purchase_order_limits' );
register_deactivation_hook( __FILE__, 'deactivate_wc_purchase_order_limits' );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
// Note: Core class is loaded conditionally in run_wc_purchase_order_limits() function

/**
 * Begins execution of the plugin.
 */
function run_wc_purchase_order_limits() {
	try {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'wcpol_woocommerce_missing_notice' );
			return;
		}

		// Vendors and BuddyBoss are optional: without them purchase orders are handled by the shop.

		// Check if required files exist before loading
		$core_file = WCPOL_PLUGIN_DIR . 'includes/class-wcpol-core.php';
		if ( ! file_exists( $core_file ) ) {
			// Only log errors in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'WCPOL Error: Core file not found: ' . $core_file );
			}
			return;
		}

		// Load core class
		require_once $core_file;

		if ( ! class_exists( 'WCPOL_Core' ) ) {
			// Only log errors in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'WCPOL Error: Core class not defined after loading file.' );
			}
			return;
		}

		$plugin = new WCPOL_Core();
		$plugin->run();

	} catch ( Exception $e ) {
		// Only log errors in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'WCPOL Error: Failed to initialize plugin - ' . $e->getMessage() );
		}
		add_action(
			'admin_notices',
			function () use ( $e ) {
				?>
			<div class="notice notice-error">
				<p>
				<?php
				/* translators: %s: error message */
				echo esc_html( sprintf( __( 'WC Purchase Order Limits error: %s', 'wc-purchase-order-limits' ), $e->getMessage() ) );
				?>
				</p>
			</div>
				<?php
			}
		);
	}
}

/**
 * Admin notice for missing WooCommerce
 */
function wcpol_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php _e( 'WC Purchase Order Limits requires WooCommerce to be installed and active.', 'wc-purchase-order-limits' ); ?></p>
	</div>
	<?php
}

/**
 * Admin notice for missing LS Vendors
 */
function wcpol_wc_vendors_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php _e( 'WC Purchase Order Limits requires LS Vendors to be installed and active.', 'wc-purchase-order-limits' ); ?></p>
	</div>
	<?php
}

/**
 * Admin notice for missing BuddyBoss
 */
function wcpol_buddyboss_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php _e( 'WC Purchase Order Limits requires BuddyBoss Platform to be installed and active.', 'wc-purchase-order-limits' ); ?></p>
	</div>
	<?php
}

add_action( 'plugins_loaded', 'run_wc_purchase_order_limits' );
