/**
 * Vendor Dashboard JavaScript for WC Purchase Order Limits
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize vendor dashboard
        var WCPOLVendor = {
            
            init: function() {
                this.bindEvents();
                this.initPriceCalculations();
            },
            
            bindEvents: function() {
                // Approve PO
                $('.wcpol-approve-po').on('click', this.approvePO);
                
                // Reject PO
                $('.wcpol-reject-po').on('click', this.showRejectReason);
                
                // Update items
                $('.wcpol-update-items').on('click', this.updateItems);
                
                // Price changes
                $('.wcpol-vendor-price-input')
                    .on('input', this.clampPriceDecimals)
                    .on('blur', this.formatPriceField)
                    .on('change keyup', this.updateLineTotal);

                // Shipping input changes
                $('#vendor_shipping')
                    .on('input', this.clampPriceDecimals)
                    .on('blur', this.formatPriceField)
                    .on('change keyup', this.updateShippingDisplay);

                // Availability changes (delegated for robustness)
                $(document).on('change', '.wcpol-item-availability', this.handleAvailabilityChange);

                // Issue PO (delegated)
                $(document).on('click', '.wcpol-issue-po', this.issuePO);
                
                // Submit rejection
                $(document).on('click', '.wcpol-submit-rejection', this.rejectPO);
            },
            
            initPriceCalculations: function() {
                // Calculate initial totals
                this.updateVendorTotal();
            },
            
            approvePO: function(e) {
                e.preventDefault();
                
                if (!confirm(wcpol_vendor.strings.confirm_approve)) {
                    return;
                }
                
                var poId = $(this).data('po-id');
                var $button = $(this);
                
                $button.prop('disabled', true).text(wcpol_vendor.strings.processing);
                
                $.ajax({
                    url: wcpol_vendor.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcpol_vendor_approve_po',
                        po_id: poId,
                        nonce: wcpol_vendor.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Success - just reload to show new status
                            location.reload();
                        } else {
                            // Show inline error
                            var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + (response.data || wcpol_vendor.strings.error) + '</div>');
                            $button.parent().append($error);
                            setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                            $button.prop('disabled', false).text(wcpol_vendor.strings.approve);
                        }
                    },
                    error: function() {
                        // Show inline error
                        var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + wcpol_vendor.strings.error + '</div>');
                        $button.parent().append($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text(wcpol_vendor.strings.approve);
                    }
                });
            },
            
            showRejectReason: function(e) {
                e.preventDefault();
                $('.wcpol-reject-reason').slideToggle();
            },
            
            rejectPO: function(e) {
                e.preventDefault();
                
                var reason = $('#reject_reason').val();
                if (!reason) {
                    // Show inline validation error
                    var $reasonField = $('#reject_reason');
                    $reasonField.css('border-color', 'red');
                    var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 5px;">' + wcpol_vendor.strings.reason_required + '</div>');
                    if (!$reasonField.next('.wcpol-inline-error').length) {
                        $reasonField.after($error);
                    }
                    setTimeout(function() {
                        $error.fadeOut(function() { $(this).remove(); });
                        $reasonField.css('border-color', '');
                    }, 3000);
                    return;
                }
                
                if (!confirm(wcpol_vendor.strings.confirm_reject)) {
                    return;
                }
                
                var poId = $('.wcpol-reject-po').data('po-id');
                var $button = $(this);
                
                $button.prop('disabled', true).text(wcpol_vendor.strings.processing);
                
                $.ajax({
                    url: wcpol_vendor.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcpol_vendor_reject_po',
                        po_id: poId,
                        reason: reason,
                        nonce: wcpol_vendor.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Success - just reload to show new status
                            location.reload();
                        } else {
                            // Show inline error
                            var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + (response.data || wcpol_vendor.strings.error) + '</div>');
                            $button.parent().append($error);
                            setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                            $button.prop('disabled', false).text(wcpol_vendor.strings.reject);
                        }
                    },
                    error: function() {
                        // Show inline error
                        var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + wcpol_vendor.strings.error + '</div>');
                        $button.parent().append($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text(wcpol_vendor.strings.reject);
                    }
                });
            },
            
            updateItems: function(e) {
                e.preventDefault();
                
                var $form = $('#wcpol-vendor-po-form');
                var $button = $(this);
                
                $button.prop('disabled', true).text(wcpol_vendor.strings.updating);
                
                // Collect updated data
                var updates = [];
                $('.wcpol-vendor-price-input').each(function() {
                    var $input = $(this);
                    var itemId = $input.data('item-id');
                    var status = $('input[name="item_availability[' + itemId + ']"]:checked').val() || 'available';
                    updates.push({
                        item_id: itemId,
                        vendor_price: $input.val(),
                        vendor_notes: $('textarea[name="vendor_notes[' + itemId + ']"]').val(),
                        status: status
                    });
                });
                
                // Send updates
                $.ajax({
                    url: wcpol_vendor.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcpol_vendor_update_items',
                        updates: updates,
                        nonce: wcpol_vendor.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Success - just reload to show updated values
                            location.reload();
                        } else {
                            // Show inline error
                            var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + (response.data || wcpol_vendor.strings.error) + '</div>');
                            $button.parent().append($error);
                            setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                            $button.prop('disabled', false).text(wcpol_vendor.strings.update_items);
                        }
                    },
                    error: function() {
                        // Show inline error
                        var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;">' + wcpol_vendor.strings.error + '</div>');
                        $button.parent().append($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text(wcpol_vendor.strings.update_items);
                    }
                });
            },
            
            updateLineTotal: function() {
                var $input = $(this);
                var price = parseFloat($input.val()) || 0;
                var quantity = parseInt($input.data('quantity')) || 1;
                var itemId = $input.data('item-id');

                // Respect availability: if not available, treat as zero
                var $availability = $('input[name="item_availability[' + itemId + ']"]:checked');
                var isAvailable = !$availability.length || $availability.val() === 'available';
                var total = isAvailable ? (price * quantity) : 0;

                $('.wcpol-item-total[data-item-id="' + itemId + '"]').html(
                    WCPOLVendor.formatPrice(total)
                );

                WCPOLVendor.updateVendorTotal();
            },
            
            updateVendorTotal: function() {
                // Check if there are any price inputs first
                var $inputs = $('.wcpol-vendor-price-input');
                if ($inputs.length === 0) {
                    return; // No inputs = issued PO, keep server-rendered total
                }

                var itemsSubtotal = 0;
                $inputs.each(function() {
                    var $input = $(this);
                    // Skip disabled inputs (set when item is marked not available)
                    if ($input.prop('disabled')) {
                        return; // continue
                    }
                    var itemId = $input.data('item-id');
                    var $availability = $('input[name="item_availability[' + itemId + ']"]:checked');
                    var isAvailable = !$availability.length || $availability.val() === 'available';
                    if (!isAvailable) {
                        return; // skip
                    }
                    var price = parseFloat($input.val()) || 0;
                    var quantity = parseInt($input.data('quantity')) || 1;
                    itemsSubtotal += price * quantity;
                });

                // Get shipping amount
                var shipping = parseFloat($('#vendor_shipping').val()) || 0;

                // Calculate grand total
                var grandTotal = itemsSubtotal + shipping;

                // Update displays
                $('.wcpol-vendor-subtotal').html(this.formatPrice(itemsSubtotal));
                $('.wcpol-vendor-shipping-display').html(this.formatPrice(shipping));
                $('.wcpol-vendor-total').html(this.formatPrice(grandTotal));
            },

            updateShippingDisplay: function() {
                WCPOLVendor.updateVendorTotal();
            },
            
            formatPrice: function(price) {
                // Get currency symbol from localized data or default to $
                var symbol = wcpol_vendor.currency_symbol || '$';
                var decimals = (typeof wcpol_vendor.currency_decimals !== 'undefined') ? wcpol_vendor.currency_decimals : 2;

                return symbol + price.toFixed(decimals);
            },

            // Handle available/not available toggle
            handleAvailabilityChange: function() {
                var $radio = $(this);
                var itemId = $radio.data('item-id');
                var isAvailable = $radio.val() === 'available';
                var $priceInput = $('.wcpol-vendor-price-input[data-item-id="' + itemId + '"]');
                var $itemTotal = $('.wcpol-item-total[data-item-id="' + itemId + '"]');

                var decimals = (typeof wcpol_vendor.currency_decimals !== 'undefined') ? wcpol_vendor.currency_decimals : 2;
                var quantity = parseInt($priceInput.data('quantity')) || 1;

                if (isAvailable) {
                    // Restore previous price if we stored one when marking unavailable
                    var prev = $priceInput.data('prevPrice');
                    $priceInput.prop('disabled', false).css('opacity', '1');
                    if (typeof prev !== 'undefined' && prev !== null && prev !== '') {
                        var prevNum = parseFloat(prev);
                        if (!isNaN(prevNum)) {
                            $priceInput.val(prevNum.toFixed(decimals));
                            $itemTotal.html(WCPOLVendor.formatPrice(prevNum * quantity));
                        }
                    } else {
                        // No previous price stored, recalc from current value
                        var cur = parseFloat($priceInput.val()) || 0;
                        $itemTotal.html(WCPOLVendor.formatPrice(cur * quantity));
                    }
                } else {
                    // Store current price before zeroing out
                    var currentVal = parseFloat($priceInput.val());
                    if (!isNaN(currentVal)) {
                        $priceInput.data('prevPrice', currentVal);
                    }
                    $priceInput.val((0).toFixed(decimals)).prop('disabled', true).css('opacity', '0.5');
                    $itemTotal.html(WCPOLVendor.formatPrice(0));
                }

                WCPOLVendor.updateVendorTotal();
            },

            // Issue purchase order (vendor finalize/issue)
            issuePO: function() {
                if (WCPOLVendor._issuing) {
                    return false;
                }
                WCPOLVendor._issuing = true;

                var $button = $(this);
                var poId = $button.data('po-id');
                var formData = $('#wcpol-vendor-po-form').serialize();

                $button.prop('disabled', true).text(wcpol_vendor.strings && wcpol_vendor.strings.processing ? wcpol_vendor.strings.processing : 'Processing...');

                $.ajax({
                    url: wcpol_vendor.ajax_url,
                    type: 'POST',
                    data: formData + '&action=wcpol_vendor_create_po&po_id=' + poId + '&nonce=' + wcpol_vendor.nonce,
                    success: function(response) {
                        if (response.success) {
                            $button.text('Quote Issued Successfully!');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            WCPOLVendor._issuing = false;
                            var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;"></div>').text(response.data || (wcpol_vendor.strings && wcpol_vendor.strings.error ? wcpol_vendor.strings.error : 'An error occurred.'));
                            $('.wcpol-vendor-po-actions').after($error);
                            setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                            $button.prop('disabled', false).text('Issue Quote');
                        }
                    },
                    error: function() {
                        WCPOLVendor._issuing = false;
                        var $error = $('<div class="wcpol-inline-error" style="color: red; margin-top: 10px;"></div>').text(wcpol_vendor.strings && wcpol_vendor.strings.error ? wcpol_vendor.strings.error : 'An error occurred.');
                        $('.wcpol-vendor-po-actions').after($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text('Issue Quote');
                    }
                });
            },

            // Clamp to 2 decimal places as user types (without changing caret intent too much)
            clampPriceDecimals: function() {
                var decimals = (typeof wcpol_vendor.currency_decimals !== 'undefined') ? wcpol_vendor.currency_decimals : 2;
                var val = this.value;
                if (!val) return;
                // Keep only digits and a single dot
                val = val.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
                var parts = val.split('.');
                if (parts.length > 1) {
                    parts[1] = parts[1].slice(0, decimals);
                    val = parts[0] + '.' + parts[1];
                }
                this.value = val;
            },

            // On blur, format to fixed decimals exactly
            formatPriceField: function() {
                var decimals = (typeof wcpol_vendor.currency_decimals !== 'undefined') ? wcpol_vendor.currency_decimals : 2;
                var num = parseFloat(this.value);
                if (isNaN(num)) {
                    this.value = '';
                    return;
                }
                this.value = num.toFixed(decimals);
            }
        };
        
        // Initialize
        WCPOLVendor.init();
        
        // Expose for external use
        window.WCPOLVendor = WCPOLVendor;
    });

})(jQuery);

// Default strings if not localized
if (typeof wcpol_vendor === 'undefined') {
    var wcpol_vendor = {
        ajax_url: ajaxurl,
        nonce: '',
        currency_symbol: '$',
        currency_decimals: 2,
        strings: {
            confirm_approve: 'Are you sure you want to approve all items in this quote?',
            confirm_reject: 'Are you sure you want to reject all items in this quote?',
            reason_required: 'Please provide a reason for rejection.',
            processing: 'Processing...',
            updating: 'Updating...',
            approve: 'Approve All Items',
            reject: 'Reject All Items',
            update_items: 'Update Items',
            items_updated: 'Items updated successfully.',
            error: 'An error occurred. Please try again.'
        }
    };
}
