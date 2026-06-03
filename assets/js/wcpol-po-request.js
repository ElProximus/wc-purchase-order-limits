/**
 * Purchase Order Request Page JavaScript
 */

(function($) {
    'use strict';

    // PO Request Form Handler
    var WCPOLRequest = {

        init: function() {
            this.initGooglePlaces();
            this.initAddressValidation();
            this.bindEvents();
            this.checkCartStatus();
        },

        /**
         * Initialize Google Places autocomplete on quote address field
         */
        initGooglePlaces: function() {
            if (typeof LSGooglePlaces === 'undefined' || !LSGooglePlaces.isAvailable()) {
                return;
            }

            var addressInput = document.getElementById('wcpol_new_shipping_address_1');
            if (addressInput) {
                this.autocompleteController = LSGooglePlaces.initAutocomplete(addressInput, 'wcpol_new_shipping');
            }
        },

        /**
         * Initialize address validation on quote address fields
         */
        initAddressValidation: function() {
            if (typeof LSAddressValidation === 'undefined') {
                return;
            }

            var config = (typeof lsAddressValidationConfig !== 'undefined') ? lsAddressValidationConfig : {};

            LSAddressValidation.init('wcpol_new_shipping', {
                containerSelector: '#wcpol-new-shipping-form',
                statusSelector: '#wcpol-new-shipping-validation-status',
                consentSelector: '#wcpol-new-shipping-consent-row',
                requiredFields: ['first_name', 'last_name', 'address_1', 'city', 'state', 'postcode'],
                enableApiValidation: config.enabled || false,
                nonce: config.nonce || '',
                ajaxUrl: config.ajaxUrl || ''
            });
        },

        bindEvents: function() {
            // Handle PO request form submission
            $('#wcpol-po-request-form').on('submit', this.submitPORequest);

            // Handle date field min value
            this.setMinDate();

            // Enforce 4-digit year for native date input without changing locale display order
            $('#wcpol_po_required_date').on('input', this.enforceYearLength);

            // Shipping address dropdown change
            $(document).on('change', '#wcpol-shipping-address-select', function() {
                WCPOLRequest.handleAddressDropdownChange($(this));
            });

            // Billing same as shipping toggle
            $('#wcpol_billing_same').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#wcpol-billing-address-content').slideUp(200);
                } else {
                    $('#wcpol-billing-address-content').slideDown(200);
                }
            });

            // Billing address dropdown change
            $(document).on('change', '#wcpol-billing-address-select', function() {
                WCPOLRequest.handleBillingDropdownChange($(this));
            });

            // Cancel new shipping address — go back to saved addresses selector.
            $(document).on('click', '#wcpol-cancel-new-address', function(e) {
                e.preventDefault();
                $('#wcpol-new-shipping-form').slideUp(200);
                $('#wcpol-saved-addresses-selector').slideDown(200);
            });

            // Cancel new billing address — go back to saved billing selector.
            $(document).on('click', '#wcpol-cancel-new-billing', function(e) {
                e.preventDefault();
                $('#wcpol-new-billing-form').slideUp(200);
                $('#wcpol-saved-billing-selector').slideDown(200);
            });
        },

        setMinDate: function() {
            // Set minimum date to today for required date field
            var today = new Date().toISOString().split('T')[0];
            $('#wcpol_po_required_date').attr('min', today);
        },

        // Ensure the year portion stays 4 digits in the value (YYYY-MM-DD)
        enforceYearLength: function() {
            var val = this.value;
            if (!val) return;
            // Expecting ISO format value regardless of UI locale: YYYY-MM-DD
            var parts = val.split('-');
            if (parts.length > 0 && parts[0].length > 4) {
                parts[0] = parts[0].slice(0, 4);
                // Keep month/day intact
                this.value = parts.join('-');
            }
        },

        /**
         * Handle saved address dropdown change
         */
        handleAddressDropdownChange: function($dropdown) {
            var selectedValue = $dropdown.val();
            var $option = $dropdown.find('option:selected');

            if (selectedValue === '' ) {
                return;
            }

            if (selectedValue === 'new') {
                // Show new address form, hide the entire saved addresses selector
                $('#wcpol-new-shipping-form').slideDown(200);
                $('#wcpol-saved-addresses-selector').slideUp(200);
                // Clear hidden fields so new_ fields are used on submit
                $('#wcpol_shipping_first_name, #wcpol_shipping_last_name, #wcpol_shipping_address_1, #wcpol_shipping_address_2, #wcpol_shipping_city, #wcpol_shipping_state, #wcpol_shipping_postcode, #wcpol_shipping_country, #wcpol_shipping_phone').val('');
                $dropdown.val('');
                return;
            }

            // Selected a saved address — update card and hidden fields
            var addressData = {
                first_name: $option.data('first-name'),
                last_name: $option.data('last-name'),
                label: $option.data('label') || '',
                address_1: $option.data('address-1'),
                address_2: $option.data('address-2') || '',
                city: $option.data('city'),
                state: $option.data('state'),
                postcode: $option.data('postcode'),
                country: $option.data('country') || 'US',
                phone: $option.data('phone') || ''
            };

            var prevAddressId = $dropdown.attr('data-current');

            // Update card display
            this.updateAddressCard(addressData);

            // Update hidden fields
            $('#wcpol_shipping_first_name').val(addressData.first_name);
            $('#wcpol_shipping_last_name').val(addressData.last_name);
            $('#wcpol_shipping_address_1').val(addressData.address_1);
            $('#wcpol_shipping_address_2').val(addressData.address_2);
            $('#wcpol_shipping_city').val(addressData.city);
            $('#wcpol_shipping_state').val(addressData.state);
            $('#wcpol_shipping_postcode').val(addressData.postcode);
            $('#wcpol_shipping_country').val(addressData.country);
            $('#wcpol_shipping_phone').val(addressData.phone);

            // Swap dropdown options
            this.swapDropdownAddresses($dropdown, prevAddressId, selectedValue, $option);

            $dropdown.attr('data-current', selectedValue);
            $dropdown.val('');

            // Hide new form, show saved addresses selector
            $('#wcpol-new-shipping-form').slideUp(200);
            $('#wcpol-saved-addresses-selector').slideDown(200);
        },

        /**
         * Update address card display
         */
        updateAddressCard: function(addr) {
            var $card = $('#wcpol-default-address-card');
            if (!$card.length) return;

            var html = '';
            if (addr.label) {
                html += '<div class="wcpol-address-nickname">' + this.escHtml(addr.label) + '</div>';
            }
            html += '<div class="wcpol-address-details">';
            html += '<div class="wcpol-address-name">' + this.escHtml(addr.first_name + ' ' + addr.last_name) + '</div>';
            html += '<div class="wcpol-address-line">' + this.escHtml(addr.address_1) + '</div>';
            if (addr.address_2) {
                html += '<div class="wcpol-address-line">' + this.escHtml(addr.address_2) + '</div>';
            }
            html += '<div class="wcpol-address-line">' + this.escHtml(addr.city + ', ' + addr.state + ' ' + addr.postcode) + '</div>';
            html += '</div>';

            $card.html(html);
        },

        /**
         * Handle billing address dropdown change
         */
        handleBillingDropdownChange: function($dropdown) {
            var selectedValue = $dropdown.val();
            var $option = $dropdown.find('option:selected');

            if (selectedValue === '') {
                return;
            }

            if (selectedValue === 'new') {
                // Show new billing form, hide saved billing selector
                $('#wcpol-new-billing-form').slideDown(200);
                $('#wcpol-saved-billing-selector').slideUp(200);
                // Clear hidden billing fields so new_ fields are used on submit
                $('#wcpol_billing_first_name, #wcpol_billing_last_name, #wcpol_billing_address_1, #wcpol_billing_address_2, #wcpol_billing_city, #wcpol_billing_state, #wcpol_billing_postcode, #wcpol_billing_country, #wcpol_billing_phone').val('');
                $dropdown.val('');
                return;
            }

            // Selected a saved address — update card and hidden fields
            var addr = {
                first_name: $option.data('first-name'),
                last_name: $option.data('last-name'),
                label: $option.data('label') || '',
                address_1: $option.data('address-1'),
                address_2: $option.data('address-2') || '',
                city: $option.data('city'),
                state: $option.data('state'),
                postcode: $option.data('postcode'),
                country: $option.data('country') || 'US',
                phone: $option.data('phone') || ''
            };

            // Update billing card display
            this.updateBillingCard(addr);

            // Update hidden billing fields
            $('#wcpol_billing_first_name').val(addr.first_name);
            $('#wcpol_billing_last_name').val(addr.last_name);
            $('#wcpol_billing_address_1').val(addr.address_1);
            $('#wcpol_billing_address_2').val(addr.address_2);
            $('#wcpol_billing_city').val(addr.city);
            $('#wcpol_billing_state').val(addr.state);
            $('#wcpol_billing_postcode').val(addr.postcode);
            $('#wcpol_billing_country').val(addr.country);
            $('#wcpol_billing_phone').val(addr.phone);

            $dropdown.attr('data-current', selectedValue);
            $dropdown.val('');

            // Hide new form, show saved billing selector
            $('#wcpol-new-billing-form').slideUp(200);
            $('#wcpol-saved-billing-selector').slideDown(200);
        },

        /**
         * Update billing address card display
         */
        updateBillingCard: function(addr) {
            var $card = $('#wcpol-default-billing-card');
            if (!$card.length) return;

            var html = '';
            if (addr.label) {
                html += '<div class="wcpol-address-nickname">' + this.escHtml(addr.label) + '</div>';
            }
            html += '<div class="wcpol-address-details">';
            html += '<div class="wcpol-address-name">' + this.escHtml(addr.first_name + ' ' + addr.last_name) + '</div>';
            html += '<div class="wcpol-address-line">' + this.escHtml(addr.address_1) + '</div>';
            if (addr.address_2) {
                html += '<div class="wcpol-address-line">' + this.escHtml(addr.address_2) + '</div>';
            }
            html += '<div class="wcpol-address-line">' + this.escHtml(addr.city + ', ' + addr.state + ' ' + addr.postcode) + '</div>';
            html += '</div>';

            $card.html(html);
        },

        /**
         * Swap dropdown addresses after selection
         */
        swapDropdownAddresses: function($dropdown, prevId, newId) {
            // All addresses stay visible in dropdown. Just update the current tracker.
            $dropdown.attr('data-current', newId);
        },

        checkCartStatus: function() {
            // Check if cart is empty on page load
            if ($('.wcpol-cart-items tbody tr').length === 0) {
                this.showMessage('Your cart is empty. Please add items before requesting a purchase order.', 'error');
                $('.wcpol-po-form').hide();
            }
        },

        submitPORequest: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('.wcpol-submit-po');
            var $message = $('#wcpol-po-message');

            // Check address validation if the new address form is visible.
            var $newForm = $('#wcpol-new-shipping-form');
            if ($newForm.is(':visible') && typeof LSAddressValidation !== 'undefined' && !LSAddressValidation.isReady('wcpol_new_shipping')) {
                WCPOLRequest.showMessage('Please verify your shipping address or confirm you want to use an unverified address.', 'error');
                var $consentRow = $('#wcpol-new-shipping-consent-row');
                if ($consentRow.is(':visible')) {
                    $('html, body').animate({ scrollTop: $consentRow.offset().top - 100 }, 300);
                }
                return;
            }

            // Disable submit button and show loading
            $submitBtn.prop('disabled', true).addClass('wcpol-loading');
            $message.hide();

            // Gather all form data including address fields
            var formData = $form.serialize();
            formData += '&action=wcpol_submit_po_request';
            formData += '&nonce=' + encodeURIComponent($('#wcpol_po_nonce').val());

            // Submit via AJAX
            $.ajax({
                url: wcpol_public.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        WCPOLRequest.showMessage(response.data.message, 'success');

                        // Redirect to thank you page after 2 seconds
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        }
                    } else {
                        WCPOLRequest.showMessage(response.data || 'An error occurred. Please try again.', 'error');
                        $submitBtn.prop('disabled', false).removeClass('wcpol-loading');
                    }
                },
                error: function() {
                    WCPOLRequest.showMessage('An error occurred. Please try again.', 'error');
                    $submitBtn.prop('disabled', false).removeClass('wcpol-loading');
                }
            });
        },

        showMessage: function(message, type) {
            var $messageDiv = $('#wcpol-po-message');

            $messageDiv
                .removeClass('success error')
                .addClass(type)
                .html(message)
                .fadeIn();

            // Auto-hide error messages after 5 seconds
            if (type === 'error') {
                setTimeout(function() {
                    $messageDiv.fadeOut();
                }, 5000);
            }
        },

        escHtml: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };


    // Initialize on document ready
    $(document).ready(function() {
        // Initialize PO request form if present
        if ($('#wcpol-po-request-form').length) {
            WCPOLRequest.init();
        }
    });


})(jQuery);
