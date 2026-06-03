<?php
/**
 * PDF Generation for Purchase Orders
 *
 * @package    WC_Purchase_Order_Limits
 * @subpackage WC_Purchase_Order_Limits/includes
 */

class WCPOL_PDF_Generator {

	/**
	 * Generate PDF for purchase order
	 */
	public function generate_pdf( $po_id ) {
		// Require TCPDF. If not present, do not generate any file.
		if ( ! class_exists( 'TCPDF' ) ) {
			$tcpdf_path          = WCPOL_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
			$tcpdf_composer_path = WCPOL_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
			if ( file_exists( $tcpdf_path ) ) {
				require_once $tcpdf_path;
			} elseif ( file_exists( $tcpdf_composer_path ) ) {
				require_once $tcpdf_composer_path;
			} else {
				// No TCPDF found; abort without creating HTML fallback
				return false;
			}
		}

		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $po ) {
			return false;
		}

		$po_items = $po_handler->get_po_items( $po_id );
		$user     = get_user_by( 'id', $po->user_id );

		// Create new PDF document
		$pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );

		// Set document information
		$pdf->SetCreator( 'WC Purchase Order Limits' );
		$pdf->SetAuthor( get_bloginfo( 'name' ) );
		$pdf->SetTitle( 'Quote ' . $po->po_number );
		$pdf->SetSubject( 'Quote' );

		// Remove default header/footer
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );

		// Set margins
		$pdf->SetMargins( 15, 15, 15 );
		$pdf->SetAutoPageBreak( true, 15 );

		// Add a page
		$pdf->AddPage();

		// Company logo
		$logo = get_option( 'wcpol_pdf_logo' );
		if ( $logo ) {
			$pdf->Image( $logo, 15, 15, 40, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false );
			$pdf->Ln( 15 );
		}

		// Company information
		$pdf->SetFont( 'helvetica', 'B', 16 );
		$pdf->Cell( 0, 10, get_option( 'wcpol_pdf_company_name', get_bloginfo( 'name' ) ), 0, 1 );

		$pdf->SetFont( 'helvetica', '', 10 );
		$company_address = get_option( 'wcpol_pdf_company_address' );
		if ( $company_address ) {
			$pdf->MultiCell( 0, 5, $company_address, 0, 'L' );
		}
		$pdf->Ln( 10 );

		// Quote title and number
		$pdf->SetFont( 'helvetica', 'B', 20 );
		$pdf->Cell( 0, 10, 'QUOTE', 0, 1, 'C' );

		$pdf->SetFont( 'helvetica', '', 12 );
		$pdf->Cell( 0, 10, 'Quote Number: ' . $po->po_number, 0, 1, 'C' );
		$pdf->Ln( 10 );

		// Customer information
		$pdf->SetFont( 'helvetica', 'B', 12 );
		$pdf->Cell( 0, 7, 'Bill To:', 0, 1 );

		$pdf->SetFont( 'helvetica', '', 10 );

		// Try PO-stored billing address first, fallback to user meta.
		$po_billing = ! empty( $po->billing_address ) ? json_decode( $po->billing_address, true ) : null;

		if ( $po_billing && ! empty( $po_billing['first_name'] ) ) {
			$customer_name = trim( $po_billing['first_name'] . ' ' . ( $po_billing['last_name'] ?? '' ) );
			$pdf->Cell( 0, 5, $customer_name, 0, 1 );
			$pdf->Cell( 0, 5, $po_billing['email'] ?? $user->user_email, 0, 1 );

			$address_parts = array();
			if ( ! empty( $po_billing['address_1'] ) ) {
				$address_parts[] = $po_billing['address_1'];
			}
			if ( ! empty( $po_billing['address_2'] ) ) {
				$address_parts[] = $po_billing['address_2'];
			}
			$city_line = array();
			if ( ! empty( $po_billing['city'] ) ) {
				$city_line[] = $po_billing['city'];
			}
			if ( ! empty( $po_billing['state'] ) ) {
				$city_line[] = $po_billing['state'];
			}
			if ( ! empty( $po_billing['postcode'] ) ) {
				$city_line[] = $po_billing['postcode'];
			}
			if ( ! empty( $city_line ) ) {
				$address_parts[] = implode( ', ', $city_line );
			}
			if ( ! empty( $address_parts ) ) {
				$pdf->MultiCell( 0, 5, implode( "\n", $address_parts ), 0, 'L' );
			}
		} else {
			// Fallback to user meta.
			$billing_first_name = get_user_meta( $po->user_id, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $po->user_id, 'billing_last_name', true );
			$customer_name      = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( empty( $customer_name ) ) {
				$customer_name = $user->display_name;
			}
			$pdf->Cell( 0, 5, $customer_name, 0, 1 );
			$pdf->Cell( 0, 5, $user->user_email, 0, 1 );

			$billing_address = $this->get_user_billing_address( $po->user_id );
			if ( $billing_address ) {
				$pdf->MultiCell( 0, 5, $billing_address, 0, 'L' );
			}
		}
		$pdf->Ln( 5 );

		// Ship To section — only if shipping address exists and differs from billing.
		$po_shipping = ! empty( $po->shipping_address ) ? json_decode( $po->shipping_address, true ) : null;

		if ( $po_shipping && ! empty( $po_shipping['address_1'] ) ) {
			// Check if shipping differs from billing.
			$shipping_differs = ! $po_billing
				|| ( $po_shipping['address_1'] ?? '' ) !== ( $po_billing['address_1'] ?? '' )
				|| ( $po_shipping['postcode'] ?? '' ) !== ( $po_billing['postcode'] ?? '' );

			if ( $shipping_differs ) {
				$pdf->SetFont( 'helvetica', 'B', 12 );
				$pdf->Cell( 0, 7, 'Ship To:', 0, 1 );
				$pdf->SetFont( 'helvetica', '', 10 );

				$ship_name = trim( ( $po_shipping['first_name'] ?? '' ) . ' ' . ( $po_shipping['last_name'] ?? '' ) );
				if ( $ship_name ) {
					$pdf->Cell( 0, 5, $ship_name, 0, 1 );
				}

				$ship_parts = array();
				if ( ! empty( $po_shipping['address_1'] ) ) {
					$ship_parts[] = $po_shipping['address_1'];
				}
				if ( ! empty( $po_shipping['address_2'] ) ) {
					$ship_parts[] = $po_shipping['address_2'];
				}
				$ship_city_line = array();
				if ( ! empty( $po_shipping['city'] ) ) {
					$ship_city_line[] = $po_shipping['city'];
				}
				if ( ! empty( $po_shipping['state'] ) ) {
					$ship_city_line[] = $po_shipping['state'];
				}
				if ( ! empty( $po_shipping['postcode'] ) ) {
					$ship_city_line[] = $po_shipping['postcode'];
				}
				if ( ! empty( $ship_city_line ) ) {
					$ship_parts[] = implode( ', ', $ship_city_line );
				}
				if ( ! empty( $ship_parts ) ) {
					$pdf->MultiCell( 0, 5, implode( "\n", $ship_parts ), 0, 'L' );
				}

			}
		}

		$pdf->Ln( 10 );

		// Order details
		// Reference Number if provided
		if ( $po->reference ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 40, 7, 'Reference Number:', 0, 0 );
			$pdf->SetFont( 'helvetica', '', 10 );
			$pdf->Cell( 50, 7, $po->reference, 0, 1 );
		}

		// Date Issued
		if ( $po->approved_date ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 40, 7, 'Date Issued:', 0, 0 );
			$pdf->SetFont( 'helvetica', '', 10 );
			$pdf->Cell( 50, 7, date_i18n( get_option( 'date_format' ), strtotime( $po->approved_date ) ), 0, 1 );
		}

		// Add validity period and expiry date if set
		if ( $po->validity_days && $po->expiry_date ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 40, 7, 'Valid For:', 0, 0 );
			$pdf->SetFont( 'helvetica', '', 10 );
			$pdf->Cell( 50, 7, $po->validity_days . ' days', 0, 1 );

			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 40, 7, 'Expires On:', 0, 0 );
			$pdf->SetFont( 'helvetica', '', 10 );
			$pdf->Cell( 50, 7, date_i18n( get_option( 'date_format' ), strtotime( $po->expiry_date ) ), 0, 1 );

			// Add expiry warning if appropriate
			$today          = current_time( 'Y-m-d' );
			$days_remaining = floor( ( strtotime( $po->expiry_date ) - strtotime( $today ) ) / ( 60 * 60 * 24 ) );
			if ( $days_remaining <= 7 && $days_remaining > 0 ) {
				$pdf->SetFont( 'helvetica', 'I', 9 );
				$pdf->SetTextColor( 255, 0, 0 );
				$pdf->Cell( 0, 5, sprintf( 'This quote expires in %d days', $days_remaining ), 0, 1 );
				$pdf->SetTextColor( 0, 0, 0 );
			} elseif ( $days_remaining <= 0 ) {
				$pdf->SetFont( 'helvetica', 'B', 9 );
				$pdf->SetTextColor( 255, 0, 0 );
				$pdf->Cell( 0, 5, 'This quote has expired', 0, 1 );
				$pdf->SetTextColor( 0, 0, 0 );
			}
		}

		// Requested Delivery if available
		if ( $po->required_date ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 40, 7, 'Requested Delivery:', 0, 0 );
			$pdf->SetFont( 'helvetica', '', 10 );
			$pdf->Cell( 50, 7, date_i18n( get_option( 'date_format' ), strtotime( $po->required_date ) ), 0, 1 );
		}

		$pdf->Ln( 10 );

		// Items table
		$pdf->SetFont( 'helvetica', 'B', 10 );
		$pdf->SetFillColor( 240, 240, 240 );

		// Table header with separate SKU column; keep total width at 180mm
		$pdf->Cell( 50, 7, __( 'Product', 'wc-purchase-order-limits' ), 1, 0, 'L', true );
		$pdf->Cell( 20, 7, __( 'SKU', 'wc-purchase-order-limits' ), 1, 0, 'L', true );
		$pdf->Cell( 20, 7, __( 'Quantity', 'wc-purchase-order-limits' ), 1, 0, 'C', true );
		$pdf->Cell( 25, 7, __( 'Unit Price', 'wc-purchase-order-limits' ), 1, 0, 'R', true );
		$pdf->Cell( 30, 7, __( 'Availability', 'wc-purchase-order-limits' ), 1, 0, 'C', true );
		$pdf->Cell( 35, 7, __( 'Total', 'wc-purchase-order-limits' ), 1, 1, 'R', true );

		$pdf->SetFont( 'helvetica', '', 9 );

		// Table rows
		foreach ( $po_items as $item ) {
			$product = wc_get_product( $item->product_id );
			if ( ! $product ) {
				continue;
			}
			$unit_price = $item->vendor_price ? $item->vendor_price : $item->price;
			$total      = $item->total;  // Use stored total (0 for unavailable items)

			// Format availability status
			$availability = 'Available';
			if ( 'not_available' === $item->status ) {
				$availability = 'Not Available';
			}

			// Calculate the height needed for this row based on product name length
			$product_name = $product->get_name();
			$sku          = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';

			// Use TCPDF's getNumLines for accurate line count
			$num_lines   = $pdf->getNumLines( $product_name, 50 );
			$line_height = 6;
			$row_height  = max( $line_height, $num_lines * $line_height );

			// Store current position
			$x_start = $pdf->GetX();
			$y_start = $pdf->GetY();

			// Draw product name with MultiCell using line height for text spacing
			$pdf->MultiCell( 50, $line_height, $product_name, 1, 'L', false, 0, $x_start, $y_start );

			// Draw other cells at the same Y position with same height
			$pdf->SetXY( $x_start + 50, $y_start );
			$pdf->Cell( 20, $row_height, '' !== $sku ? $sku : '-', 1, 0, 'L' );
			$pdf->Cell( 20, $row_height, $item->quantity, 1, 0, 'C' );
			$pdf->Cell( 25, $row_height, html_entity_decode( get_woocommerce_currency_symbol() ) . number_format( $unit_price, 2 ), 1, 0, 'R' );
			$pdf->Cell( 30, $row_height, $availability, 1, 0, 'C' );
			$pdf->Cell( 35, $row_height, html_entity_decode( get_woocommerce_currency_symbol() ) . number_format( $total, 2 ), 1, 0, 'R' );

			// Move to next row
			$pdf->Ln( $row_height );
		}

		// Add vendor shipping rows.
		$vendor_handler       = new WCPOL_Vendor_Handler();
		$vendor_shipping_rows = $vendor_handler->get_po_vendor_shipping( $po_id );

		if ( ! empty( $vendor_shipping_rows ) ) {
			foreach ( $vendor_shipping_rows as $shipping_row ) {
				if ( floatval( $shipping_row->shipping_amount ) > 0 ) {
					// Get vendor shop name (LS Vendors or WC Vendors).
					$shipping_vendor_name = __( 'Shop', 'wc-purchase-order-limits' );
					if ( $shipping_row->vendor_id > 0 ) {
						if ( class_exists( 'LSV_Vendors' ) && method_exists( 'LSV_Vendors', 'get_vendor_shop_name' ) ) {
							$shipping_vendor_name = LSV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
						} elseif ( class_exists( 'WCV_Vendors' ) && method_exists( 'WCV_Vendors', 'get_vendor_shop_name' ) ) {
							$shipping_vendor_name = WCV_Vendors::get_vendor_shop_name( $shipping_row->vendor_id );
						}
					}

					$pdf->SetFillColor( 249, 249, 249 );
					$pdf->Cell( 50, 7, '', 1, 0, 'L' ); // Empty product column
					$pdf->Cell( 20, 7, '', 1, 0, 'L' ); // Empty SKU column
					$pdf->Cell( 20, 7, '', 1, 0, 'C' ); // Empty quantity column
					$pdf->Cell( 25, 7, '', 1, 0, 'R' ); // Empty price column
					/* translators: %s: vendor name */
					$pdf->Cell( 30, 7, sprintf( __( 'Shipping (%s)', 'wc-purchase-order-limits' ), $shipping_vendor_name ), 1, 0, 'L', true );
					$pdf->Cell( 35, 7, html_entity_decode( get_woocommerce_currency_symbol() ) . number_format( $shipping_row->shipping_amount, 2 ), 1, 1, 'R', true );
				}
			}
		}

		// Total - adjusted to span correct columns
		$pdf->SetFillColor( 240, 240, 240 );
		$pdf->SetFont( 'helvetica', 'B', 10 );
		$pdf->Cell( 145, 7, 'Total:', 1, 0, 'R', true );
		$pdf->Cell( 35, 7, html_entity_decode( get_woocommerce_currency_symbol() ) . number_format( $po->total_amount, 2 ), 1, 1, 'R', true );
		$pdf->Ln( 10 );

		// Notes
		if ( $po->notes ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 0, 7, 'Notes:', 0, 1 );
			$pdf->SetFont( 'helvetica', '', 9 );
			$pdf->MultiCell( 0, 5, $po->notes, 0, 'L' );
			$pdf->Ln( 10 );
		}

		// Terms and conditions
		$terms = get_option( 'wcpol_pdf_terms' );
		if ( $terms ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->Cell( 0, 7, 'Terms and Conditions:', 0, 1 );
			$pdf->SetFont( 'helvetica', '', 8 );
			$pdf->MultiCell( 0, 5, $terms, 0, 'L' );
		}

		// Save PDF
		$upload_dir = wp_upload_dir();
		$po_dir     = $upload_dir['basedir'] . '/wcpol-purchase-orders';

		if ( ! file_exists( $po_dir ) ) {
			wp_mkdir_p( $po_dir );
		}

		$filename = 'PO-' . $po->po_number . '-' . time() . '.pdf';
		$filepath = $po_dir . '/' . $filename;

		$pdf->Output( $filepath, 'F' );

		// Save PDF URL to database
		$pdf_url = $upload_dir['baseurl'] . '/wcpol-purchase-orders/' . $filename;
		$this->save_pdf_url( $po_id, $pdf_url );

		return $pdf_url;
	}

	/**
	 * Generate HTML version as fallback
	 */
	public function generate_html_version( $po_id ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $po ) {
			return false;
		}

		$po_items = $po_handler->get_po_items( $po_id );
		$user     = get_user_by( 'id', $po->user_id );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Quote <?php echo esc_html( $po->po_number ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; margin: 20px; }
				.header { text-align: center; margin-bottom: 30px; }
				.company-info { margin-bottom: 20px; }
				.po-details { margin-bottom: 20px; }
				table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
				th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
				th { background-color: #f2f2f2; }
				.total-row { font-weight: bold; }
				.notes { margin-top: 20px; }
				.terms { margin-top: 20px; font-size: 12px; }
				@media print { body { margin: 0; } }
			</style>
		</head>
		<body>
			<div class="header">
				<h1>QUOTE</h1>
				<h2>Quote Number: <?php echo esc_html( $po->po_number ); ?></h2>
			</div>

			<div class="company-info">
				<h3><?php echo esc_html( get_option( 'wcpol_pdf_company_name', get_bloginfo( 'name' ) ) ); ?></h3>
				<?php if ( $address = get_option( 'wcpol_pdf_company_address' ) ) : ?>
					<p><?php echo nl2br( esc_html( $address ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="po-details">
				<p><strong>Bill To:</strong><br>
				<?php
				$html_po_billing = ! empty( $po->billing_address ) ? json_decode( $po->billing_address, true ) : null;
				if ( $html_po_billing && ! empty( $html_po_billing['first_name'] ) ) :
					echo esc_html( trim( $html_po_billing['first_name'] . ' ' . ( $html_po_billing['last_name'] ?? '' ) ) );
					?><br><?php
					echo esc_html( $html_po_billing['email'] ?? $user->user_email );
					if ( ! empty( $html_po_billing['address_1'] ) ) :
						?><br><?php echo esc_html( $html_po_billing['address_1'] );
					endif;
					if ( ! empty( $html_po_billing['address_2'] ) ) :
						?><br><?php echo esc_html( $html_po_billing['address_2'] );
					endif;
					$html_city_parts = array_filter( array( $html_po_billing['city'] ?? '', $html_po_billing['state'] ?? '', $html_po_billing['postcode'] ?? '' ) );
					if ( ! empty( $html_city_parts ) ) :
						?><br><?php echo esc_html( implode( ', ', $html_city_parts ) );
					endif;
				else :
					echo esc_html( $user->display_name ); ?><br><?php
					echo esc_html( $user->user_email ); ?><br><?php
					echo nl2br( esc_html( $this->get_user_billing_address( $po->user_id ) ) );
				endif;
				?></p>

				<?php
				$html_po_shipping = ! empty( $po->shipping_address ) ? json_decode( $po->shipping_address, true ) : null;
				if ( $html_po_shipping && ! empty( $html_po_shipping['address_1'] ) ) :
					$html_ship_differs = ! $html_po_billing
						|| ( $html_po_shipping['address_1'] ?? '' ) !== ( $html_po_billing['address_1'] ?? '' )
						|| ( $html_po_shipping['postcode'] ?? '' ) !== ( $html_po_billing['postcode'] ?? '' );
					if ( $html_ship_differs ) :
						?>
						<p><strong>Ship To:</strong><br>
						<?php
						$html_ship_name = trim( ( $html_po_shipping['first_name'] ?? '' ) . ' ' . ( $html_po_shipping['last_name'] ?? '' ) );
						if ( $html_ship_name ) :
							echo esc_html( $html_ship_name ); ?><br><?php
						endif;
						if ( ! empty( $html_po_shipping['address_1'] ) ) :
							echo esc_html( $html_po_shipping['address_1'] );
						endif;
						if ( ! empty( $html_po_shipping['address_2'] ) ) :
							?><br><?php echo esc_html( $html_po_shipping['address_2'] );
						endif;
						$html_ship_city_parts = array_filter( array( $html_po_shipping['city'] ?? '', $html_po_shipping['state'] ?? '', $html_po_shipping['postcode'] ?? '' ) );
						if ( ! empty( $html_ship_city_parts ) ) :
							?><br><?php echo esc_html( implode( ', ', $html_ship_city_parts ) );
						endif;
						?></p>
						<?php
					endif;
				endif;
				?>

				<p><strong>Order Date:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->created_date ) ) ); ?></p>
				<?php if ( $po->approved_date ) : ?>
					<p><strong>Date Issued:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->approved_date ) ) ); ?></p>
				<?php endif; ?>
				<p><strong>Status:</strong> <?php echo esc_html( ucfirst( $po->status ) ); ?></p>
				<?php if ( $po->validity_days && $po->expiry_date ) : ?>
					<p><strong>Valid For:</strong> <?php echo esc_html( $po->validity_days ); ?> days</p>
					<p><strong>Expires On:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->expiry_date ) ) ); ?></p>
					<?php
					$today          = current_time( 'Y-m-d' );
					$days_remaining = floor( ( strtotime( $po->expiry_date ) - strtotime( $today ) ) / ( 60 * 60 * 24 ) );
					if ( $days_remaining <= 7 && $days_remaining > 0 ) :
						?>
						<p style="color: red; font-style: italic;">This quote expires in <?php echo $days_remaining; ?> days</p>
					<?php elseif ( $days_remaining <= 0 ) : ?>
						<p style="color: red; font-weight: bold;">This quote has expired</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<table>
				<thead>
					<tr>
						<th>Product</th>
						<th>SKU</th>
						<th>Quantity</th>
						<th>Unit Price</th>
						<th>Total</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $po_items as $item ) :
						$product = wc_get_product( $item->product_id );
						if ( ! $product ) {
							continue;
						}
						$unit_price = $item->vendor_price ? $item->vendor_price : $item->price;
						$total      = $unit_price * $item->quantity;
						?>
						<tr>
							<td><?php echo esc_html( $product->get_name() ); ?></td>
							<td><?php echo ( $sku = method_exists( $product, 'get_sku' ) ? $product->get_sku() : '' ) ? esc_html( $sku ) : '—'; ?></td>
							<td><?php echo esc_html( $item->quantity ); ?></td>
							<td><?php echo wc_price( $unit_price ); ?></td>
							<td><?php echo wc_price( $total ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr class="total-row">
						<td colspan="3" align="right">Total:</td>
						<td><?php echo wc_price( $po->total_amount ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $po->notes ) : ?>
				<div class="notes">
					<h3>Notes:</h3>
					<p><?php echo nl2br( esc_html( $po->notes ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $terms = get_option( 'wcpol_pdf_terms' ) ) : ?>
				<div class="terms">
					<h3>Terms and Conditions:</h3>
					<p><?php echo nl2br( esc_html( $terms ) ); ?></p>
				</div>
			<?php endif; ?>
		</body>
		</html>
		<?php

		$html = ob_get_clean();

		// Save HTML file
		$upload_dir = wp_upload_dir();
		$po_dir     = $upload_dir['basedir'] . '/wcpol-purchase-orders';

		if ( ! file_exists( $po_dir ) ) {
			wp_mkdir_p( $po_dir );
		}

		$filename = 'PO-' . $po->po_number . '-' . time() . '.html';
		$filepath = $po_dir . '/' . $filename;

		file_put_contents( $filepath, $html );

		$file_url = $upload_dir['baseurl'] . '/wcpol-purchase-orders/' . $filename;
		// Note: HTML fallback is no longer used for new files, but keep
		// this as a utility if explicitly called elsewhere.
		$this->save_pdf_url( $po_id, $file_url );

		return $file_url;
	}

	/**
	 * Save PDF URL to database
	 */
	private function save_pdf_url( $po_id, $url ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'wcpol_purchase_orders',
			array( 'pdf_url' => $url ),
			array( 'id' => $po_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get user billing address
	 */
	private function get_user_billing_address( $user_id ) {
		$address = array();

		$address[] = get_user_meta( $user_id, 'billing_address_1', true );
		$address[] = get_user_meta( $user_id, 'billing_address_2', true );
		$address[] = get_user_meta( $user_id, 'billing_city', true );
		$address[] = get_user_meta( $user_id, 'billing_state', true );
		$address[] = get_user_meta( $user_id, 'billing_postcode', true );
		$address[] = get_user_meta( $user_id, 'billing_country', true );

		$address = array_filter( $address );

		return implode( "\n", $address );
	}

	/**
	 * Download PDF
	 */
	public function download_pdf( $po_id, $user_id = null ) {
		$po_handler = new WCPOL_Purchase_Order();
		$po         = $po_handler->get_po( $po_id );

		if ( ! $po ) {
			return false;
		}

		// Check permissions
		if ( $user_id && (int) $po->user_id !== (int) $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		// Generate PDF if not exists
		if ( ! $po->pdf_url || ! file_exists( $this->url_to_path( $po->pdf_url ) ) ) {
			$pdf_url = $this->generate_pdf( $po_id );
		} else {
			$pdf_url = $po->pdf_url;
		}

		if ( ! $pdf_url ) {
			return false;
		}

		// Force download
		$filepath = $this->url_to_path( $pdf_url );

		// Only serve real PDFs; if an older HTML file is present, treat as unavailable
		$ext = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
		if ( file_exists( $filepath ) && 'pdf' === $ext ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="PO-' . $po->po_number . '.pdf"' );
			header( 'Content-Length: ' . filesize( $filepath ) );
			readfile( $filepath );
			exit;
		}

		return false;
	}

	/**
	 * Convert URL to file path
	 */
	private function url_to_path( $url ) {
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
	}
}
