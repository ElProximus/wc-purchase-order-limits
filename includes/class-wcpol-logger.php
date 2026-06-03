<?php
/**
 * Logger class for debugging and error tracking
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_Logger {

	/**
	 * Log file name
	 */
	private static $log_file = 'wcpol-debug.log';

	/**
	 * Whether logging is enabled
	 */
	private static $enabled = null;

	/**
	 * Check if logging is enabled
	 */
	private static function is_enabled() {
		if ( null === self::$enabled ) {
			self::$enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ||
							( defined( 'WCPOL_DEBUG' ) && WCPOL_DEBUG );
		}
		return self::$enabled;
	}

	/**
	 * Log a message
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Use WooCommerce logger if available
		if ( class_exists( 'WC_Logger' ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wc-purchase-order-limits' );

			switch ( $level ) {
				case 'error':
					$logger->error( $message, $context );
					break;
				case 'warning':
					$logger->warning( $message, $context );
					break;
				case 'notice':
					$logger->notice( $message, $context );
					break;
				default:
					$logger->info( $message, $context );
			}
		} else {
			// Fallback to WordPress debug log
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[WCPOL %s] %s', strtoupper( $level ), $message ) );
			}
		}
	}

	/**
	 * Log an error
	 */
	public static function error( $message ) {
		self::log( $message, 'error' );
	}

	/**
	 * Log a warning
	 */
	public static function warning( $message ) {
		self::log( $message, 'warning' );
	}

	/**
	 * Log info
	 */
	public static function info( $message ) {
		self::log( $message, 'info' );
	}

	/**
	 * Log debug information
	 */
	public static function debug( $message ) {
		self::log( $message, 'debug' );
	}

	/**
	 * Log an exception
	 */
	public static function exception( $exception ) {
		$message = sprintf(
			'Exception: %s in %s on line %d. Stack trace: %s',
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$exception->getTraceAsString()
		);
		self::error( $message );
	}

	/**
	 * Log database errors
	 */
	public static function db_error( $operation, $error = null ) {
		global $wpdb;

		if ( ! $error ) {
			$error = $wpdb->last_error;
		}

		if ( $error ) {
			$message = sprintf( 'Database error during %s: %s', $operation, $error );
			self::error( $message );
		}
	}

	/**
	 * Log API requests
	 */
	public static function api_request( $endpoint, $data, $response ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$message = sprintf(
			'API Request to %s with data: %s. Response: %s',
			$endpoint,
			wp_json_encode( $data ),
			wp_json_encode( $response )
		);
		self::debug( $message );
	}

	/**
	 * Log performance metrics
	 */
	public static function performance( $operation, $start_time ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$execution_time = microtime( true ) - $start_time;
		$message        = sprintf(
			'Performance: %s took %.4f seconds',
			$operation,
			$execution_time
		);

		if ( $execution_time > 1 ) {
			self::warning( $message );
		} else {
			self::debug( $message );
		}
	}

	/**
	 * Clear log files
	 */
	public static function clear_logs() {
		if ( class_exists( 'WC_Logger' ) ) {
			$logger = wc_get_logger();
			if ( method_exists( $logger, 'clear' ) ) {
				$logger->clear( 'wc-purchase-order-limits' );
			}
		}
	}
}
