<?php
/**
 * AJAX Security Handler
 *
 * Provides centralized security checks for all AJAX endpoints
 * Prevents CSRF attacks, implements rate limiting, and validates permissions
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Ajax_Security {

	/**
	 * Rate limit configuration
	 */
	const RATE_LIMIT_WINDOW  = 60; // seconds
	const DEFAULT_RATE_LIMIT = 30; // requests per window

	/**
	 * Rate limits for specific actions (requests per minute)
	 */
	private static $rate_limits = array(
		'wcpol_create_purchase_order' => 5,  // Max 5 PO creations per minute
		'wcpol_submit_po_request'     => 5,      // Max 5 PO requests per minute
		'wcpol_vendor_create_po'      => 10,      // Max 10 vendor PO issues per minute
		'wcpol_customer_approve_po'   => 10,   // Max 10 approvals per minute
		'wcpol_customer_reject_po'    => 10,    // Max 10 rejections per minute
		'default'                     => 30,                      // Default for other actions
	);

	/**
	 * Verify AJAX request with enhanced security checks
	 *
	 * @param string $action The AJAX action being performed
	 * @param array  $capabilities Required capabilities for this action
	 * @param array  $options Additional security options
	 * @return bool|WP_Error True if verified, WP_Error on failure
	 */
	public static function verify_request( $action, $capabilities = array(), $options = array() ) {
		// Default options
		$defaults = array(
			'check_nonce'      => true,
			'require_login'    => true,
			'check_rate_limit' => true,
			'validate_origin'  => true,
			'log_attempt'      => true,
		);
		$options  = wp_parse_args( $options, $defaults );

		// 1. Check nonce (CSRF protection).
		if ( $options['check_nonce'] ) {
			$nonce_action = isset( $options['nonce_action'] ) ? $options['nonce_action'] : 'wcpol_nonce';
			$nonce_param  = isset( $options['nonce_param'] ) ? $options['nonce_param'] : 'nonce';

			$nonce_value = isset( $_REQUEST[ $nonce_param ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_param ] ) ) : '';
			if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
				self::log_security_event( 'nonce_failure', $action );
				wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'wc-purchase-order-limits' ) );
				return false;
			}
		}

		// 2. Check user authentication
		if ( $options['require_login'] && ! is_user_logged_in() ) {
			self::log_security_event( 'auth_failure', $action );
			wp_send_json_error( __( 'You must be logged in to perform this action.', 'wc-purchase-order-limits' ) );
			return false;
		}

		// 3. Check user capabilities
		if ( ! empty( $capabilities ) ) {
			$has_capability = false;
			foreach ( $capabilities as $cap ) {
				if ( current_user_can( $cap ) ) {
					$has_capability = true;
					break;
				}
			}

			if ( ! $has_capability ) {
				self::log_security_event( 'capability_failure', $action, get_current_user_id() );
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'wc-purchase-order-limits' ) );
				return false;
			}
		}

		// 4. Check rate limiting
		if ( $options['check_rate_limit'] && ! self::check_rate_limit( $action ) ) {
			self::log_security_event( 'rate_limit_exceeded', $action, get_current_user_id() );
			wp_send_json_error( __( 'Too many requests. Please wait a moment and try again.', 'wc-purchase-order-limits' ) );
			return false;
		}

		// 5. Validate request origin
		if ( $options['validate_origin'] && ! self::validate_request_origin() ) {
			self::log_security_event( 'origin_failure', $action );
			wp_send_json_error( __( 'Invalid request origin.', 'wc-purchase-order-limits' ) );
			return false;
		}

		// 6. Log successful authentication if requested
		if ( $options['log_attempt'] ) {
			self::log_security_event( 'request_authorized', $action, get_current_user_id() );
		}

		return true;
	}

	/**
	 * Check if the current user has hit the rate limit
	 *
	 * @param string $action The action being performed
	 * @return bool True if within rate limit, false if exceeded
	 */
	private static function check_rate_limit( $action ) {
		// Skip rate limiting for administrators
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$user_id = self::get_client_ip(); // Use IP for non-logged in users
		}

		// Get the rate limit for this action
		$limit = isset( self::$rate_limits[ $action ] ) ? self::$rate_limits[ $action ] : self::$rate_limits['default'];

		// Create a unique key for this user/action combination
		$transient_key = 'wcpol_rate_' . md5( $action . '_' . $user_id );

		// Get current attempt count
		$attempts = get_transient( $transient_key );

		if ( false === $attempts ) {
			// First attempt within the window
			set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
			return true;
		}

		if ( $attempts >= $limit ) {
			// Rate limit exceeded
			return false;
		}

		// Increment attempt counter
		set_transient( $transient_key, $attempts + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Validate that the request is coming from the same origin
	 *
	 * @return bool True if origin is valid
	 */
	private static function validate_request_origin() {
		// Check if request has a referrer.
		$referrer = wp_get_referer();

		if ( ! $referrer ) {
			// No referrer might be legitimate for some requests.
			return true;
		}

		// wp_get_referer() can return a relative path (e.g., "/cart/?po_request=1")
		// when the _wp_http_referer hidden field is set by wp_nonce_field().
		// A relative path is same-origin by definition — allow it.
		$referrer_url = wp_parse_url( $referrer );
		if ( ! isset( $referrer_url['host'] ) ) {
			return true;
		}

		// Parse site URL.
		$site_url = wp_parse_url( home_url() );

		if ( ! isset( $site_url['host'] ) ) {
			return false;
		}

		// Compare hosts (allowing for www variations).
		$site_host     = str_replace( 'www.', '', $site_url['host'] );
		$referrer_host = str_replace( 'www.', '', $referrer_url['host'] );

		return $site_host === $referrer_host;
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address
	 */
	private static function get_client_ip() {
		// Prefer Cloudflare's client IP header when behind CF proxy.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- filter_var validates below.
			$cf_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $cf_ip;
			}
		}

		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- filter_var validates below.
				$ip = $_SERVER[ $key ];
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip );
					$ip = trim( $ip[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Log security events for audit trail
	 *
	 * @param string $event_type Type of security event
	 * @param string $action The action that was attempted
	 * @param int    $user_id User ID if available
	 */
	private static function log_security_event( $event_type, $action, $user_id = 0 ) {
		// Only log failures and rate limits in production
		$events_to_log = array( 'nonce_failure', 'auth_failure', 'capability_failure', 'rate_limit_exceeded', 'origin_failure' );

		if ( ! in_array( $event_type, $events_to_log ) ) {
			return;
		}

		// Create log entry
		$log_entry = array(
			'timestamp'  => current_time( 'mysql' ),
			'event_type' => $event_type,
			'action'     => $action,
			'user_id'    => $user_id ? $user_id : get_current_user_id(),
			'ip_address' => self::get_client_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'   => wp_get_referer(),
		);

		// Store in transient for review (auto-expires after 7 days)
		$log_key = 'wcpol_security_log';
		$log     = get_transient( $log_key );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Keep only last 100 entries
		if ( count( $log ) >= 100 ) {
			array_shift( $log );
		}

		$log[] = $log_entry;
		set_transient( $log_key, $log, 7 * DAY_IN_SECONDS );

		// Also log to error log for critical events
		if ( in_array( $event_type, array( 'rate_limit_exceeded', 'capability_failure' ) ) ) {
			error_log(
				sprintf(
					'WCPOL Security Event: %s | Action: %s | User: %d | IP: %s',
					$event_type,
					$action,
					$log_entry['user_id'],
					$log_entry['ip_address']
				)
			);
		}
	}

	/**
	 * Verify vendor permissions for a specific PO
	 *
	 * @param int $po_id Purchase order ID
	 * @param int $vendor_id Vendor user ID
	 * @return bool True if vendor has access
	 */
	public static function verify_vendor_po_access( $po_id, $vendor_id = null ) {
		if ( ! $vendor_id ) {
			$vendor_id = get_current_user_id();
		}

		global $wpdb;

		// Check if vendor has items in this PO
		$has_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wcpol_po_items 
             WHERE po_id = %d AND vendor_id = %d",
				$po_id,
				$vendor_id
			)
		);

		return $has_items > 0;
	}

	/**
	 * Verify customer ownership of a PO
	 *
	 * @param int $po_id Purchase order ID
	 * @param int $user_id User ID
	 * @return bool True if user owns the PO
	 */
	public static function verify_customer_po_ownership( $po_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		global $wpdb;

		// Check if user owns this PO
		$owner_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}wcpol_purchase_orders 
             WHERE id = %d AND status != 'trashed'",
				$po_id
			)
		);

		return (int) $owner_id === (int) $user_id;
	}

	/**
	 * Get security log for admin review
	 *
	 * @return array Security log entries
	 */
	public static function get_security_log() {
		$log = get_transient( 'wcpol_security_log' );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear security log
	 */
	public static function clear_security_log() {
		delete_transient( 'wcpol_security_log' );
	}
}
