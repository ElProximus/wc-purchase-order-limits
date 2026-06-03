<?php
/**
 * Status Constants and Helper Class
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 * @since      1.1.0
 */

class WCPOL_Status {

	/**
	 * Purchase Order Status Constants
	 */
	const PO_STATUS_DRAFT     = 'draft';
	const PO_STATUS_PENDING   = 'pending';
	const PO_STATUS_ISSUED    = 'issued';
	const PO_STATUS_ACCEPTED  = 'accepted';
	const PO_STATUS_DECLINED  = 'declined';
	const PO_STATUS_APPROVED  = 'approved';    // Legacy - kept for backward compatibility
	const PO_STATUS_REJECTED  = 'rejected';    // Legacy - kept for backward compatibility
	const PO_STATUS_CANCELLED = 'cancelled';
	const PO_STATUS_TRASHED   = 'trashed';
	const PO_STATUS_PARTIAL   = 'partial';

	/**
	 * Purchase Order Item Status Constants
	 */
	const ITEM_STATUS_PENDING  = 'pending';
	const ITEM_STATUS_APPROVED = 'approved';
	const ITEM_STATUS_REJECTED = 'rejected';

	/**
	 * Get all PO statuses with labels
	 *
	 * @return array Status => Label pairs
	 */
	public static function get_po_statuses() {
		return array(
			self::PO_STATUS_DRAFT     => __( 'Draft', 'wc-purchase-order-limits' ),
			self::PO_STATUS_PENDING   => __( 'Pending', 'wc-purchase-order-limits' ),
			self::PO_STATUS_ISSUED    => __( 'Issued', 'wc-purchase-order-limits' ),
			self::PO_STATUS_ACCEPTED  => __( 'Accepted', 'wc-purchase-order-limits' ),
			self::PO_STATUS_DECLINED  => __( 'Declined', 'wc-purchase-order-limits' ),
			self::PO_STATUS_CANCELLED => __( 'Cancelled', 'wc-purchase-order-limits' ),
			self::PO_STATUS_TRASHED   => __( 'Trashed', 'wc-purchase-order-limits' ),
			self::PO_STATUS_PARTIAL   => __( 'Partially Approved', 'wc-purchase-order-limits' ),
		);
	}

	/**
	 * Get status label
	 *
	 * @param string $status Status key
	 * @return string Translated label
	 */
	public static function get_status_label( $status ) {
		$statuses = self::get_po_statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : ucfirst( $status );
	}

	/**
	 * Get status badge HTML
	 *
	 * @param string $status Status key
	 * @param string $type 'po' or 'item'
	 * @return string HTML for status badge
	 */
	public static function get_status_badge( $status, $type = 'po' ) {
		$class_prefix = 'item' === $type ? 'wcpol-item-status' : 'wcpol-status';
		$label        = self::get_status_label( $status );

		return sprintf(
			'<span class="%s %s-%s">%s</span>',
			esc_attr( $class_prefix ),
			esc_attr( $class_prefix ),
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Check if status allows editing
	 *
	 * @param string $status PO status
	 * @return bool True if editing is allowed
	 */
	public static function can_edit( $status ) {
		return in_array(
			$status,
			array(
				self::PO_STATUS_DRAFT,
				self::PO_STATUS_PENDING,
			),
			true
		);
	}

	/**
	 * Check if status allows vendor actions
	 *
	 * @param string $status PO status
	 * @return bool True if vendor can take action
	 */
	public static function can_vendor_act( $status ) {
		return in_array(
			$status,
			array(
				self::PO_STATUS_PENDING,
				self::PO_STATUS_PARTIAL,
			),
			true
		);
	}

	/**
	 * Check if status allows customer actions
	 *
	 * @param string $status PO status
	 * @return bool True if customer can take action
	 */
	public static function can_customer_act( $status ) {
		return self::PO_STATUS_ISSUED === $status;
	}

	/**
	 * Check if status is final
	 *
	 * @param string $status PO status
	 * @return bool True if status is final
	 */
	public static function is_final( $status ) {
		return in_array(
			$status,
			array(
				self::PO_STATUS_ACCEPTED,
				self::PO_STATUS_DECLINED,
				self::PO_STATUS_APPROVED,  // Legacy
				self::PO_STATUS_REJECTED,  // Legacy
				self::PO_STATUS_CANCELLED,
				self::PO_STATUS_TRASHED,
			),
			true
		);
	}

	/**
	 * Get valid status transitions
	 *
	 * @param string $current_status Current status
	 * @return array Array of valid next statuses
	 */
	public static function get_valid_transitions( $current_status ) {
		$transitions = array(
			self::PO_STATUS_DRAFT   => array(
				self::PO_STATUS_PENDING,
				self::PO_STATUS_CANCELLED,
			),
			self::PO_STATUS_PENDING => array(
				self::PO_STATUS_ISSUED,
				self::PO_STATUS_CANCELLED,
				self::PO_STATUS_PARTIAL,
			),
			self::PO_STATUS_ISSUED  => array(
				self::PO_STATUS_ACCEPTED,
				self::PO_STATUS_DECLINED,
				self::PO_STATUS_CANCELLED,
			),
			self::PO_STATUS_PARTIAL => array(
				self::PO_STATUS_ISSUED,
				self::PO_STATUS_CANCELLED,
			),
		);

		// Admin can always trash
		if ( current_user_can( 'administrator' ) ) {
			foreach ( $transitions as &$states ) {
				$states[] = self::PO_STATUS_TRASHED;
			}
		}

		return isset( $transitions[ $current_status ] ) ? $transitions[ $current_status ] : array();
	}

	/**
	 * Validate status transition
	 *
	 * @param string $from Current status
	 * @param string $to New status
	 * @return bool True if transition is valid
	 */
	public static function is_valid_transition( $from, $to ) {
		$valid_transitions = self::get_valid_transitions( $from );
		return in_array( $to, $valid_transitions, true );
	}

	/**
	 * Get status color for display
	 *
	 * @param string $status Status key
	 * @return string Hex color code
	 */
	public static function get_status_color( $status ) {
		$colors = array(
			self::PO_STATUS_DRAFT     => '#9E9E9E',
			self::PO_STATUS_PENDING   => '#FFC107',
			self::PO_STATUS_ISSUED    => '#2196F3',
			self::PO_STATUS_ACCEPTED  => '#4CAF50',
			self::PO_STATUS_DECLINED  => '#f44336',
			self::PO_STATUS_APPROVED  => '#4CAF50',  // Legacy
			self::PO_STATUS_REJECTED  => '#f44336',  // Legacy
			self::PO_STATUS_CANCELLED => '#795548',
			self::PO_STATUS_TRASHED   => '#424242',
			self::PO_STATUS_PARTIAL   => '#FF9800',
		);

		return isset( $colors[ $status ] ) ? $colors[ $status ] : '#666666';
	}
}
