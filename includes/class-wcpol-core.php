<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->version     = WCPOL_VERSION;
		$this->plugin_name = 'wc-purchase-order-limits';

		$this->load_dependencies();
		$this->maybe_run_migrations();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_vendor_hooks();
		$this->define_notification_hooks();
	}

	/**
	 * Run database migrations if the plugin version has changed.
	 */
	private function maybe_run_migrations() {
		$db_version = get_option( 'wcpol_db_version', '0' );
		if ( version_compare( $db_version, $this->version, '<' ) ) {
			require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-activator.php';
			WCPOL_Activator::activate();
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// The class responsible for orchestrating the actions and filters
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-loader.php';

		// The class responsible for defining internationalization functionality
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-i18n.php';

		// Core functionality classes
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-logger.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-security.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-ajax-security.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-status.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-vendor-utility.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-purchase-order.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-cart-validator.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-vendor-handler.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-notifications.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-pdf-generator.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-email-handler.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-po-request-handler.php';
		require_once WCPOL_PLUGIN_DIR . 'includes/class-wcpol-order-creator.php';

		// Admin classes
		require_once WCPOL_PLUGIN_DIR . 'admin/class-wcpol-admin.php';
		require_once WCPOL_PLUGIN_DIR . 'admin/class-wcpol-settings.php';

		// Public classes
		require_once WCPOL_PLUGIN_DIR . 'public/class-wcpol-public.php';

		// Vendor dashboard classes
		require_once WCPOL_PLUGIN_DIR . 'vendor-dashboard/class-wcpol-vendor-dashboard.php';

		$this->loader = new WCPOL_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$plugin_i18n = new WCPOL_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin    = new WCPOL_Admin( $this->get_plugin_name(), $this->get_version() );
		$plugin_settings = new WCPOL_Settings( $this->get_plugin_name(), $this->get_version() );

		// Admin scripts and styles
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles', 10, 1 );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts', 10, 1 );

		// Admin menu
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Settings
		$this->loader->add_action( 'admin_init', $plugin_settings, 'register_settings' );

		// AJAX handlers for admin
		$this->loader->add_action( 'wp_ajax_wcpol_get_po_details', $plugin_admin, 'ajax_get_po_details' );
		$this->loader->add_action( 'wp_ajax_wcpol_update_po_status', $plugin_admin, 'ajax_update_po_status' );
		$this->loader->add_action( 'wp_ajax_wcpol_generate_pdf', $plugin_admin, 'ajax_generate_pdf' );
		$this->loader->add_action( 'wp_ajax_wcpol_export_pos', $plugin_admin, 'ajax_export_pos' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public      = new WCPOL_Public( $this->get_plugin_name(), $this->get_version() );
		$cart_validator     = new WCPOL_Cart_Validator();
		$purchase_order     = new WCPOL_Purchase_Order();
		$po_request_handler = new WCPOL_PO_Request_Handler();

		// Public scripts and styles
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Cart validation hooks
		$this->loader->add_action( 'woocommerce_check_cart_items', $cart_validator, 'validate_cart_limit' );
		// Removed limit display on cart/checkout pages
		// $this->loader->add_action( 'woocommerce_cart_totals_after_order_total', $cart_validator, 'display_limit_info' );
		// Removed button modifications - now using checkout redirect instead

		// Checkout redirect hooks - redirects to PO page when checkout is accessed with cart over limit
		$this->loader->add_action( 'template_redirect', $po_request_handler, 'maybe_redirect_to_po_request' );

		// Display PO form on cart page when parameter is present
		$this->loader->add_action( 'woocommerce_before_cart', $po_request_handler, 'maybe_display_po_form_on_cart', 1 );

		// PO request AJAX
		$this->loader->add_action( 'wp_ajax_wcpol_submit_po_request', $po_request_handler, 'ajax_submit_po_request' );
		$this->loader->add_action( 'wp_ajax_nopriv_wcpol_submit_po_request', $po_request_handler, 'ajax_submit_po_request' );

		// Shortcodes
		$this->loader->add_action( 'init', $po_request_handler, 'register_shortcodes' );

		// Purchase order creation
		$this->loader->add_action( 'wp_ajax_wcpol_create_purchase_order', $purchase_order, 'ajax_create_purchase_order' );
		$this->loader->add_action( 'wp_ajax_nopriv_wcpol_create_purchase_order', $purchase_order, 'ajax_create_purchase_order' );

		// Customer PO actions
		$this->loader->add_action( 'wp_ajax_wcpol_customer_approve_po', $purchase_order, 'ajax_customer_approve_po' );
		$this->loader->add_action( 'wp_ajax_wcpol_customer_reject_po', $purchase_order, 'ajax_customer_reject_po' );

		// Customer account pages
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public, 'add_po_menu_item' );
		$this->loader->add_action( 'init', $plugin_public, 'add_po_endpoint' );
		$this->loader->add_action( 'woocommerce_account_purchase-orders_endpoint', $plugin_public, 'po_content' );

		// PDF download
		$this->loader->add_action( 'init', $plugin_public, 'handle_pdf_download' );

		// Voluntary PO request button on cart page
		$this->loader->add_action( 'woocommerce_proceed_to_checkout', $plugin_public, 'add_voluntary_po_button', 15 );
	}

	/**
	 * Register hooks related to vendor functionality.
	 */
	private function define_vendor_hooks() {
		$vendor_dashboard = new WCPOL_Vendor_Dashboard();
		$vendor_handler   = new WCPOL_Vendor_Handler();

		// Add menu item to vendor dashboard - use correct WC Vendors Pro filter
		$this->loader->add_filter( 'wcv_dashboard_pages_nav', $vendor_dashboard, 'add_po_menu_item' );

		// Register custom page with WC Vendors Pro
		$this->loader->add_filter( 'wcv_dashboard_custom_pages', $vendor_dashboard, 'register_custom_page' );

		// Handle custom page display
		$this->loader->add_action( 'wcv_pro_dashboard_custom_template', $vendor_dashboard, 'handle_custom_page', 10, 4 );

		// Also hook into the dashboard object loader as a fallback
		$this->loader->add_action( 'wcvendors_pro_after_dashboard_nav', $vendor_dashboard, 'maybe_load_purchase_orders' );

		// Register vendor quotes shortcode.
		$this->loader->add_action( 'init', $vendor_dashboard, 'register_shortcodes' );

		// Vendor AJAX handlers
		$this->loader->add_action( 'wp_ajax_wcpol_vendor_update_po_item', $vendor_handler, 'ajax_update_po_item' );
		$this->loader->add_action( 'wp_ajax_wcpol_vendor_update_items', $vendor_handler, 'ajax_update_items' );
		$this->loader->add_action( 'wp_ajax_wcpol_vendor_approve_po', $vendor_handler, 'ajax_approve_po' );
		$this->loader->add_action( 'wp_ajax_wcpol_vendor_reject_po', $vendor_handler, 'ajax_reject_po' );
		$this->loader->add_action( 'wp_ajax_wcpol_vendor_create_po', $vendor_handler, 'ajax_create_vendor_po' );

		// Vendor notification when PO is created
		$this->loader->add_action( 'wcpol_po_created', $vendor_handler, 'notify_vendors', 10, 2 );
	}

	/**
	 * Register hooks related to BuddyBoss notifications.
	 */
	private function define_notification_hooks() {
		$notifications = new WCPOL_Notifications();

		// Register component with BuddyBoss
		$this->loader->add_filter( 'bp_notifications_get_registered_components', $notifications, 'register_component' );

		// Format notifications
		$this->loader->add_filter( 'bp_notifications_get_notifications_for_user', $notifications, 'format_notifications', 10, 8 );

		// Notification triggers
		$this->loader->add_action( 'wcpol_po_created', $notifications, 'notify_customer_po_created', 10, 2 );
		$this->loader->add_action( 'wcpol_po_approved', $notifications, 'notify_customer_po_approved', 10, 2 );
		$this->loader->add_action( 'wcpol_po_approved', $notifications, 'notify_vendors_po_approved', 15, 2 ); // Also notify vendors
		$this->loader->add_action( 'wcpol_po_rejected', $notifications, 'notify_customer_po_rejected', 10, 2 );
		$this->loader->add_action( 'wcpol_vendor_po_request', $notifications, 'notify_vendor_new_po', 10, 3 );

		// Generate PDF when PO is approved
		$pdf_generator = new WCPOL_PDF_Generator();
		$this->loader->add_action( 'wcpol_po_approved', $pdf_generator, 'generate_pdf', 20, 1 );

		// Create WooCommerce order when PO is approved (with pending payment status)
		$order_creator = new WCPOL_Order_Creator();
		$this->loader->add_action( 'wcpol_po_approved', $order_creator, 'create_order_from_po', 5, 2 );

		// Send email notifications
		$email_handler = new WCPOL_Email_Handler();
		$this->loader->add_action( 'wcpol_po_created', $email_handler, 'send_customer_po_created', 10, 2 );
		$this->loader->add_action( 'wcpol_po_created', $email_handler, 'send_admin_po_created', 10, 2 );
		$this->loader->add_action( 'wcpol_po_approved', $email_handler, 'send_vendor_po_approved_email', 25, 2 );
		$this->loader->add_action( 'wcpol_po_approved', $email_handler, 'send_customer_po_approval_confirmation', 26, 2 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
