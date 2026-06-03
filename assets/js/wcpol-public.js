/**
 * Public-facing JavaScript for WC Purchase Order Limits
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // PO Request Modal
        var modal = $('#wcpol-po-modal');
        var requestBtn = $('#wcpol-request-po');
        var closeBtn = $('.wcpol-modal-close, .wcpol-modal-cancel');
        
        // Open modal
        requestBtn.on('click', function() {
            modal.fadeIn();
        });
        
        // Close modal
        closeBtn.on('click', function() {
            modal.fadeOut();
        });
        
        // Disable closing modal by clicking overlay to avoid accidental closes
        
        // Handle PO form submission
        $('#wcpol-po-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Disable submit button and show loading
            $submitBtn.prop('disabled', true).text(wcpol_public.strings.creating_po);
            
            // Prepare data
            var formData = {
                action: 'wcpol_create_purchase_order',
                nonce: wcpol_public.nonce,
                notes: $('#wcpol-po-notes').val(),
                reference: $('#wcpol-po-reference').val()
            };
            
            // Create or find message container
            var $messageContainer = $form.find('.wcpol-inline-message');
            if (!$messageContainer.length) {
                $messageContainer = $('<div class="wcpol-inline-message"></div>');
                $submitBtn.after($messageContainer);
            }
            
            // Send AJAX request
            $.ajax({
                url: wcpol_public.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Success - just redirect, no message needed
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            location.reload();
                        }
                    } else {
                        // Show inline error message
                        $messageContainer.html('<div class="wcpol-error">' + (response.data || wcpol_public.strings.error) + '</div>');
                        $messageContainer.fadeIn();
                        
                        // Re-enable submit button
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    // Show inline error message
                    $messageContainer.html('<div class="wcpol-error">' + wcpol_public.strings.error + '</div>');
                    $messageContainer.fadeIn();
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Update cart display when limit changes
        $(document.body).on('updated_cart_totals', function() {
            // You could add AJAX here to update the limit display dynamically
        });
        
        // Format currency helper
        function formatCurrency(amount) {
            // This is a simple implementation - you might want to use WooCommerce's format
            return '$' + parseFloat(amount).toFixed(2);
        }
        
        // Smooth scroll to PO section
        $('.wcpol-view-po').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $('html, body').animate({
                scrollTop: $(target).offset().top - 100
            }, 500);
        });
        
        // Print PO functionality
        $('.wcpol-print-po').on('click', function(e) {
            e.preventDefault();
            window.print();
        });
        
        // Confirmation dialogs
        $('.wcpol-action-confirm').on('click', function(e) {
            var message = $(this).data('confirm-message') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
        
    });

})(jQuery);
