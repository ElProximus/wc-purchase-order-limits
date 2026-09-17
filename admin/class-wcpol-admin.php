<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/admin
 */

class WCPOL_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles( $hook_suffix = '' ) {
		if ( ! $this->is_wcpol_admin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			WCPOL_PLUGIN_URL . 'assets/css/wcpol-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		if ( ! $this->is_wcpol_admin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			WCPOL_PLUGIN_URL . 'assets/js/wcpol-admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'wcpol_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcpol_admin_nonce' ),
			)
		);
	}

	/**
	 * Check whether the current admin page belongs to this plugin.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return bool
	 */
	private function is_wcpol_admin_screen( $hook_suffix ) {
		if ( empty( $hook_suffix ) ) {
			return false;
		}

		return false !== strpos( $hook_suffix, 'wcpol-' );
	}

	/**
	 * AJAX: Generate PDF for a purchase order
	 */
	public function ajax_generate_pdf() {
		// Secure admin AJAX
		WCPOL_Ajax_Security::verify_request(
			'wcpol_generate_pdf',
			array( 'manage_woocommerce' ),
			array(
				'nonce_action' => 'wcpol_admin_nonce',
			)
		);

		$po_id = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		if ( ! $po_id ) {
			wp_send_json_error( __( 'Invalid quote ID.', 'wc-purchase-order-limits' ) );
		}

		// Generate PDF
		$pdf     = new WCPOL_PDF_Generator();
		$pdf_url = $pdf->generate_pdf( $po_id );

		if ( $pdf_url ) {
			wp_send_json_success( array( 'pdf_url' => $pdf_url ) );
		}

		wp_send_json_error( __( 'Unable to generate PDF.', 'wc-purchase-order-limits' ) );
	}

	/**
	 * AJAX: Export purchase orders as CSV/XLS
	 */
	public function ajax_export_pos() {
		// Secure admin AJAX
		WCPOL_Ajax_Security::verify_request(
			'wcpol_export_pos',
			array( 'manage_woocommerce' ),
			array(
				'nonce_action' => 'wcpol_admin_nonce',
			)
		);

		$format    = isset( $_GET['format'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['format'] ) ) ) : 'csv';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		// Validate dates (YYYY-MM-DD)
		$df_valid = '' === $date_from || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from );
		$dt_valid = '' === $date_to || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to );
		if ( ! $df_valid || ! $dt_valid ) {
			wp_die( __( 'Invalid date filters.', 'wc-purchase-order-limits' ) );
		}

		global $wpdb;
		$where  = 'WHERE po.status != "trashed"';
		$params = array();

		if ( '' !== $date_from ) {
			$where   .= ' AND DATE(po.created_date) >= %s';
			$params[] = $date_from;
		}
		if ( '' !== $date_to ) {
			$where   .= ' AND DATE(po.created_date) <= %s';
			$params[] = $date_to;
		}

		$sql = "SELECT po.*, u.display_name, u.user_email
                FROM {$wpdb->prefix}wcpol_purchase_orders po
                LEFT JOIN {$wpdb->users} u ON u.ID = po.user_id
                $where
                ORDER BY po.created_date DESC";

		$rows = empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		// Prepare output
		$filename_base = 'purchase-orders-' . date( 'Ymd-His' );
		if ( 'xls' === $format ) {
			header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename_base . '.xls' );
		} else {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename_base . '.csv' );
		}
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output BOM for Excel compatibility
		echo "\xEF\xBB\xBF";

		$out = fopen( 'php://output', 'w' );
		// Header row
		fputcsv( $out, array( 'PO ID', 'PO Number', 'Customer', 'Email', 'Date', 'Status', 'Total' ) );

		foreach ( $rows as $r ) {
			$customer = $r->display_name ? $r->display_name : 'Guest';
			$email    = $r->user_email ? $r->user_email : '';
			$date     = $r->created_date;
			$status   = $r->status;
			$total    = number_format( (float) $r->total_amount, 2, '.', '' );

			fputcsv( $out, array( $r->id, $r->po_number, $customer, $email, $date, $status, $total ) );
		}

		fclose( $out );
		exit;
	}
	/**
	 * Add admin menu items
	 */
	public function add_admin_menu() {
		// Check if user has permission before adding menu
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		try {
			add_menu_page(
				__( 'Quotes', 'wc-purchase-order-limits' ),
				__( 'Quotes', 'wc-purchase-order-limits' ),
				'manage_woocommerce',
				'wcpol-purchase-orders',
				array( $this, 'display_purchase_orders_page' ),
				'dashicons-clipboard',
				56
			);

			add_submenu_page(
				'wcpol-purchase-orders',
				__( 'All Quotes', 'wc-purchase-order-limits' ),
				__( 'All Quotes', 'wc-purchase-order-limits' ),
				'manage_woocommerce',
				'wcpol-purchase-orders',
				array( $this, 'display_purchase_orders_page' )
			);

			add_submenu_page(
				'wcpol-purchase-orders',
				__( 'User Limits', 'wc-purchase-order-limits' ),
				__( 'User Limits', 'wc-purchase-order-limits' ),
				'manage_woocommerce',
				'wcpol-user-limits',
				array( $this, 'display_user_limits_page' )
			);

			add_submenu_page(
				'wcpol-purchase-orders',
				__( 'Settings', 'wc-purchase-order-limits' ),
				__( 'Settings', 'wc-purchase-order-limits' ),
				'manage_woocommerce',
				'wcpol-settings',
				array( $this, 'display_settings_page' )
			);
		} catch ( Exception $e ) {
			if ( class_exists( 'WCPOL_Logger' ) ) {
				WCPOL_Logger::error( 'Failed to add admin menu: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Display purchase orders page
	 */
	public function display_purchase_orders_page() {
		global $wpdb;

		// Handle actions.
		if ( isset( $_GET['action'] ) && isset( $_GET['po_id'] ) ) {
			$po_id  = intval( $_GET['po_id'] );
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );

			if ( current_user_can( 'administrator' ) ) {
				switch ( $action ) {
					case 'trash':
						check_admin_referer( 'trash_po_' . $po_id );
						$this->trash_purchase_order( $po_id );
						wp_safe_redirect( admin_url( 'admin.php?page=wcpol-purchase-orders&trashed=1' ) );
						exit;

					case 'restore':
						check_admin_referer( 'restore_po_' . $po_id );
						$this->restore_purchase_order( $po_id );
						wp_safe_redirect( admin_url( 'admin.php?page=wcpol-purchase-orders&restored=1' ) );
						exit;

					case 'delete':
						check_admin_referer( 'delete_po_' . $po_id );
						$this->delete_purchase_order_permanently( $po_id );
						wp_safe_redirect( admin_url( 'admin.php?page=wcpol-purchase-orders&status=trashed&deleted=1' ) );
						exit;
				}
			} else {
				wp_die( __( 'Only administrators can manage quotes.', 'wc-purchase-order-limits' ) );
			}
		}

		// Check if viewing single PO
		if ( isset( $_GET['po_id'] ) ) {
			$this->display_single_po( intval( $_GET['po_id'] ) );
			return;
		}

		// Get all purchase orders (excluding trashed by default)
		$show_trashed     = isset( $_GET['status'] ) && 'trashed' === $_GET['status'];
		$status_condition = $show_trashed ? "po.status = 'trashed'" : "po.status != 'trashed'";

		$pos = $wpdb->get_results(
			"SELECT po.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}wcpol_purchase_orders po
             LEFT JOIN {$wpdb->users} u ON po.user_id = u.ID
             WHERE {$status_condition}
             ORDER BY po.created_date DESC"
		);

		include WCPOL_PLUGIN_DIR . 'admin/views/purchase-orders-list.php';
	}

	/**
	 * Trash a purchase order
	 */
	private function trash_purchase_order( $po_id ) {
		global $wpdb;

		// Only admins can trash
		if ( ! current_user_can( 'administrator' ) ) {
			return false;
		}

		// Update status to trashed
		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array( 'status' => 'trashed' ),
			array( 'id' => $po_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Add history entry
			$po_handler = new WCPOL_Purchase_Order();
			$po_handler->add_history(
				$po_id,
				get_current_user_id(),
				'admin_trashed',
				__( 'Administrator moved quote to trash', 'wc-purchase-order-limits' )
			);
		}

		return $result;
	}

	/**
	 * Restore a purchase order from trash
	 */
	private function restore_purchase_order( $po_id ) {
		global $wpdb;

		// Only admins can restore
		if ( ! current_user_can( 'administrator' ) ) {
			return false;
		}

		// Get the PO to determine previous status
		$po = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d AND status = 'trashed'",
				$po_id
			)
		);

		if ( ! $po ) {
			return false;
		}

		// Restore to 'requested' status as default
		$result = $wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array( 'status' => 'requested' ),
			array( 'id' => $po_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Add history entry
			$po_handler = new WCPOL_Purchase_Order();
			$po_handler->add_history(
				$po_id,
				get_current_user_id(),
				'admin_restored',
				__( 'Administrator restored quote from trash', 'wc-purchase-order-limits' )
			);
		}

		return $result;
	}

	/**
	 * Permanently delete a purchase order
	 */
	private function delete_purchase_order_permanently( $po_id ) {
		global $wpdb;

		// Only admins can delete permanently
		if ( ! current_user_can( 'administrator' ) ) {
			return false;
		}

		// Verify PO is trashed first
		$po = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcpol_purchase_orders WHERE id = %d AND status = 'trashed'",
				$po_id
			)
		);

		if ( ! $po ) {
			return false;
		}

		// Delete related records first
		$wpdb->delete(
			$wpdb->prefix . 'wcpol_po_items',
			array( 'po_id' => $po_id ),
			array( '%d' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'wcpol_po_history',
			array( 'po_id' => $po_id ),
			array( '%d' )
		);

		// Delete the PO itself
		$result = $wpdb->delete(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array( 'id' => $po_id ),
			array( '%d' )
		);

		// Delete any BuddyBoss notifications
		$notifications = new WCPOL_Notifications();
		$notifications->delete_po_notifications( $po_id );

		return $result;
	}

	/**
	 * Display single PO details
	 */
	public function display_single_po( $po_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $po ) {
			echo '<div class="notice notice-error"><p>' . __( 'Quote not found.', 'wc-purchase-order-limits' ) . '</p></div>';
			return;
		}

		$po_items   = $po_handler->get_po_items( $po_id );
		$po_history = $po_handler->get_history( $po_id );
		$user       = get_user_by( 'id', $po->user_id );

		include WCPOL_PLUGIN_DIR . 'admin/views/purchase-order-detail.php';
	}

	/**
	 * Display user limits page
	 */
	public function display_user_limits_page() {
		global $wpdb;

		// Handle form submission
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'wcpol_user_limits' ) ) {
			$this->save_user_limit();
		}

		// Handle delete
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['limit_id'] ) ) {
			$limit_id = intval( $_GET['limit_id'] );
			check_admin_referer( 'delete_limit_' . $limit_id );
			$wpdb->delete(
				$wpdb->prefix . 'wcpol_user_limits',
				array( 'id' => $limit_id ),
				array( '%d' )
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wcpol-user-limits&deleted=1' ) );
			exit;
		}

		// Get all limits
		$limits = $wpdb->get_results(
			"SELECT l.*, u.display_name 
             FROM {$wpdb->prefix}wcpol_user_limits l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             ORDER BY l.priority DESC, l.id DESC"
		);

		include WCPOL_PLUGIN_DIR . 'admin/views/user-limits.php';
	}

	/**
	 * Save user limit
	 */
	private function save_user_limit() {
		global $wpdb;

		$limit_type        = isset( $_POST['limit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['limit_type'] ) ) : 'role';
		$limit_amount      = isset( $_POST['limit_amount'] ) ? floatval( $_POST['limit_amount'] ) : 0;
		$limit_calculation = isset( $_POST['limit_calculation'] ) ? sanitize_text_field( wp_unslash( $_POST['limit_calculation'] ) ) : 'subtotal';
		$priority          = isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : 0;
		$active            = isset( $_POST['active'] ) ? 1 : 0;

		$data = array(
			'limit_amount' => $limit_amount,
			'limit_type'   => $limit_calculation,
			'priority'     => $priority,
			'active'       => $active,
		);

		if ( 'user' === $limit_type ) {
			$data['user_id']   = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
			$data['user_role'] = null;
		} else {
			$data['user_role'] = isset( $_POST['user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['user_role'] ) ) : '';
			$data['user_id']   = null;
		}

		$limit_id = isset( $_POST['limit_id'] ) ? intval( $_POST['limit_id'] ) : 0;
		if ( $limit_id ) {
			// Update existing
			$wpdb->update(
				$wpdb->prefix . 'wcpol_user_limits',
				$data,
				array( 'id' => $limit_id )
			);
		} else {
			// Insert new
			$wpdb->insert(
				$wpdb->prefix . 'wcpol_user_limits',
				$data
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wcpol-user-limits&saved=1' ) );
		exit;
	}

	/**
	 * Display settings page
	 */
	public function display_settings_page() {
		include WCPOL_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * AJAX get PO details
	 */
	public function ajax_get_po_details() {
		// Enhanced security checks for admin operations
		WCPOL_Ajax_Security::verify_request(
			'wcpol_get_po_details',
			array( 'manage_woocommerce' ),
			array(
				'nonce_action' => 'wcpol_admin_nonce',
			)
		);

		$po_id = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );
		$po_items   = $po_handler->get_po_items( $po_id );

		if ( ! $po ) {
			wp_send_json_error( 'PO not found' );
		}

		wp_send_json_success(
			array(
				'po'    => $po,
				'items' => $po_items,
			)
		);
	}

	/**
	 * AJAX update PO status
	 */
	public function ajax_update_po_status() {
		// Enhanced security checks for admin operations
		WCPOL_Ajax_Security::verify_request(
			'wcpol_update_po_status',
			array( 'manage_woocommerce' ),
			array(
				'nonce_action' => 'wcpol_admin_nonce',
			)
		);

		$po_id  = isset( $_POST['po_id'] ) ? intval( $_POST['po_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		$po_handler = new WCPOL_Purchase_Order();

		if ( $po_handler->update_status( $po_id, $status ) ) {
			// Send appropriate email notifications
			$po            = $po_handler->get_po( $po_id );
			$email_handler = new WCPOL_Email_Handler();

			if ( 'approved' === $status ) {
				$email_handler->send_po_approved_email( $po_id, $po->user_id );
			} elseif ( 'rejected' === $status ) {
				$email_handler->send_po_rejected_email( $po_id, $po->user_id );
			}

			wp_send_json_success( 'Status updated successfully' );
		} else {
			wp_send_json_error( 'Failed to update status' );
		}
	}
}
