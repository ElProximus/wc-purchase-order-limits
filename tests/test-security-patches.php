<?php
/**
 * Security Patches Test Suite
 *
 * This file tests the security patches implemented to fix:
 * 1. Email header injection vulnerability
 * 2. CSRF protection enhancements
 *
 * Run this test by including it in a WordPress environment with the plugin active
 */

// Test 1: Email Header Injection Prevention
function test_email_header_injection() {
	echo "Testing Email Header Injection Prevention...\n";

	// Create an instance of the email handler
	$email_handler = new WCPOL_Email_Handler();

	// Test malicious inputs
	$malicious_inputs = array(
		"Normal Site\r\nBcc: attacker@evil.com",
		"Site Name\nContent-Type: text/plain",
		'Test%0ANew-Header: malicious',
		"Test\rAnother-Header: bad",
		"Site\x00Name",
		"Site\tWith\tTabs",
	);

	$passed = true;

	// Use reflection to access private method
	$reflection = new ReflectionClass( $email_handler );
	$method     = $reflection->getMethod( 'sanitize_email_header' );
	$method->setAccessible( true );

	foreach ( $malicious_inputs as $input ) {
		$sanitized = $method->invoke( $email_handler, $input );

		// Check that dangerous characters are removed
		if ( strpos( $sanitized, "\r" ) !== false ||
			strpos( $sanitized, "\n" ) !== false ||
			strpos( $sanitized, "\t" ) !== false ||
			strpos( $sanitized, "\x00" ) !== false ) {
			echo '  ❌ Failed to sanitize: ' . json_encode( $input ) . "\n";
			$passed = false;
		} else {
			echo '  ✅ Successfully sanitized: ' . json_encode( $input ) . ' -> ' . json_encode( $sanitized ) . "\n";
		}
	}

	return $passed;
}

// Test 2: AJAX Security Class Exists and Works
function test_ajax_security_class() {
	echo "\nTesting AJAX Security Class...\n";

	$passed = true;

	// Check if class exists
	if ( ! class_exists( 'WCPOL_Ajax_Security' ) ) {
		echo "  ❌ WCPOL_Ajax_Security class not found\n";
		return false;
	} else {
		echo "  ✅ WCPOL_Ajax_Security class exists\n";
	}

	// Check if methods exist
	$required_methods = array(
		'verify_request',
		'verify_vendor_po_access',
		'verify_customer_po_ownership',
		'get_security_log',
	);

	foreach ( $required_methods as $method ) {
		if ( ! method_exists( 'WCPOL_Ajax_Security', $method ) ) {
			echo "  ❌ Method $method not found\n";
			$passed = false;
		} else {
			echo "  ✅ Method $method exists\n";
		}
	}

	return $passed;
}

// Test 3: Verify Rate Limiting Configuration
function test_rate_limiting() {
	echo "\nTesting Rate Limiting Configuration...\n";

	// Use reflection to check rate limit configuration
	$reflection = new ReflectionClass( 'WCPOL_Ajax_Security' );
	$property   = $reflection->getProperty( 'rate_limits' );
	$property->setAccessible( true );
	$rate_limits = $property->getValue();

	$expected_limits = array(
		'wcpol_create_purchase_order' => 5,
		'wcpol_submit_po_request'     => 5,
		'wcpol_vendor_create_po'      => 10,
		'wcpol_customer_approve_po'   => 10,
		'wcpol_customer_reject_po'    => 10,
	);

	$passed = true;

	foreach ( $expected_limits as $action => $limit ) {
		if ( ! isset( $rate_limits[ $action ] ) || $rate_limits[ $action ] != $limit ) {
			echo "  ❌ Rate limit for $action not configured correctly\n";
			$passed = false;
		} else {
			echo "  ✅ Rate limit for $action: $limit requests/minute\n";
		}
	}

	return $passed;
}

// Test 4: Verify AJAX Handlers Use Security Class
function test_ajax_handlers_secured() {
	echo "\nTesting AJAX Handler Security...\n";

	$files_to_check = array(
		'includes/class-wcpol-vendor-handler.php' => array(
			'ajax_update_po_item',
			'ajax_approve_po',
			'ajax_reject_po',
			'ajax_create_vendor_po',
		),
		'includes/class-wcpol-purchase-order.php' => array(
			'ajax_customer_approve_po',
			'ajax_customer_reject_po',
			'ajax_create_purchase_order',
		),
		'admin/class-wcpol-admin.php'             => array(
			'ajax_get_po_details',
			'ajax_update_po_status',
		),
	);

	$passed     = true;
	$plugin_dir = WP_PLUGIN_DIR . '/wc-purchase-order-limits/';

	foreach ( $files_to_check as $file => $methods ) {
		$file_path = $plugin_dir . $file;
		if ( ! file_exists( $file_path ) ) {
			echo "  ❌ File not found: $file\n";
			$passed = false;
			continue;
		}

		$content = file_get_contents( $file_path );

		foreach ( $methods as $method ) {
			// Check if method uses WCPOL_Ajax_Security::verify_request
			if ( strpos( $content, $method ) !== false &&
				strpos( $content, 'WCPOL_Ajax_Security::verify_request' ) !== false ) {
				echo "  ✅ $file::$method() is secured\n";
			} else {
				echo "  ❌ $file::$method() may not be properly secured\n";
				$passed = false;
			}
		}
	}

	return $passed;
}

// Run all tests
function run_security_tests() {
	echo "=================================\n";
	echo "WC Purchase Order Limits Security Tests\n";
	echo "=================================\n\n";

	$all_passed = true;

	// Test 1: Email Header Injection
	if ( ! test_email_header_injection() ) {
		$all_passed = false;
	}

	// Test 2: AJAX Security Class
	if ( ! test_ajax_security_class() ) {
		$all_passed = false;
	}

	// Test 3: Rate Limiting
	if ( ! test_rate_limiting() ) {
		$all_passed = false;
	}

	// Test 4: AJAX Handlers
	if ( ! test_ajax_handlers_secured() ) {
		$all_passed = false;
	}

	echo "\n=================================\n";
	if ( $all_passed ) {
		echo "✅ ALL SECURITY TESTS PASSED\n";
	} else {
		echo "❌ SOME SECURITY TESTS FAILED\n";
	}
	echo "=================================\n";

	return $all_passed;
}

// Run tests if called directly
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	run_security_tests();
}
