# WC Purchase Order Limits

WooCommerce plugin for purchase limits with multi-vendor PO workflow, BuddyBoss notifications, and PDF generation.

## Features

- **Cart Purchase Limits** - Global, role-based, and user-specific limits with visual progress bar
- **Purchase Order System** - Auto-triggers when cart exceeds limit, or voluntary PO requests
- **Multi-Vendor Integration** - Vendors review, modify prices, approve/reject items via dashboard
- **BuddyBoss Notifications** - Real-time notifications for customers and vendors
- **PDF Generation** - Professional PDFs via TCPDF (falls back to HTML)
- **Email Notifications** - Configurable emails for all PO status changes

## Requirements

- WordPress 5.8+, PHP 7.4+
- WooCommerce 5.0+
- WC Vendors 2.0+
- BuddyBoss Platform 2.0+

## Structure

```
wc-purchase-order-limits/
├── wc-purchase-order-limits.php  # Entry point
├── includes/                     # Core classes (loader, security, emails, PDF)
├── admin/                        # Admin UI and views
├── public/                       # Customer-facing UI
├── vendor-dashboard/             # Vendor PO management
├── assets/                       # CSS and JS
├── templates/                    # Email/PDF templates
├── vendor/tcpdf/                 # PDF library
└── tests/                        # Security tests
```

## Configuration

1. **Settings**: Purchase Orders → Settings (limits, PO numbering, company details)
2. **User Limits**: Purchase Orders → User Limits (role/user-specific limits with priority)
3. **Vendor Config**: Enable price editing, require vendor approval

## Usage

### Shortcode
```
[wcpol_po_request voluntary="1"]
```
Renders PO request form. Requires logged-in user with cart items.

### Database Tables
- `wp_wcpol_purchase_orders` - Main PO records
- `wp_wcpol_po_items` - Line items
- `wp_wcpol_po_history` - Activity history
- `wp_wcpol_user_limits` - Limit configurations

### Hooks
**Actions**: `wcpol_po_created`, `wcpol_po_approved`, `wcpol_po_rejected`, `wcpol_po_status_changed`
**Filters**: `wcpol_user_limit`, `wcpol_po_number_format`, `wcpol_pdf_template`, `wcpol_email_content`

## Development

### Coding Standards
- PHP 7.4+, WordPress Coding Standards
- Class prefix: `WCPOL_`, filenames: `class-wcpol-*.php`
- Use `WCPOL_Ajax_Security` and `WCPOL_Security` for AJAX/nonces
- Always use `$wpdb->prepare()` - never concatenate SQL

### Testing
```bash
wp eval-file wp-content/plugins/wc-purchase-order-limits/tests/test-security-patches.php
```

## Troubleshooting

### Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WCPOL_DEBUG', true);
```

### Common Issues

| Issue | Solution |
|-------|----------|
| PDFs not generating | Check `/wp-content/uploads/wcpol-purchase-orders/` is writable, PHP memory ≥128MB |
| Emails not sending | Install WP Mail SMTP plugin |
| Vendor not seeing POs | Verify vendor role and WC Vendors config |
| Limits not applying | Check user role priority in User Limits |
| Class not found | Ensure all dependencies active, check file permissions (644/755) |
| BuddyBoss notifications missing | Enable Notifications component in BuddyBoss → Components |

### Log Locations
- WordPress: `/wp-content/debug.log`
- WooCommerce: WooCommerce → Status → Logs
