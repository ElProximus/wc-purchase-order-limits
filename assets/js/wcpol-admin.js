/**
 * Admin JavaScript for WC Purchase Order Limits
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Quick approve from list
        $('.wcpol-quick-approve').on('click', function() {
            if (!confirm('Are you sure you want to approve this purchase order?')) {
                return;
            }
            
            var poId = $(this).data('po-id');
            var $button = $(this);
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: wcpol_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpol_update_po_status',
                    po_id: poId,
                    status: 'approved',
                    nonce: wcpol_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        // Show inline error next to button
                        var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">' + (response.data || 'Error updating status') + '</span>');
                        $button.after($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text('Approve');
                    }
                },
                error: function() {
                    // Show inline error next to button
                    var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">Error updating status</span>');
                    $button.after($error);
                    setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                    $button.prop('disabled', false).text('Approve');
                }
            });
        });
        
        // Status change buttons
        $('.wcpol-change-status').on('click', function() {
            var newStatus = $(this).data('status');
            var confirmMsg = newStatus === 'approved' 
                ? 'Are you sure you want to approve this purchase order?'
                : 'Are you sure you want to reject this purchase order?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: wcpol_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpol_update_po_status',
                    po_id: $button.data('po-id'),
                    status: newStatus,
                    nonce: wcpol_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        // Show inline error next to button
                        var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">' + (response.data || 'Error updating status') + '</span>');
                        $button.after($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    // Show inline error next to button
                    var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">Error updating status</span>');
                    $button.after($error);
                    setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Generate PDF button
        $('.wcpol-generate-pdf').on('click', function() {
            var $button = $(this);
            var poId = $button.data('po-id');
            
            $button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: wcpol_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcpol_generate_pdf',
                    po_id: poId,
                    nonce: wcpol_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Just open PDF and reload - success is obvious
                        if (response.data.pdf_url) {
                            window.open(response.data.pdf_url, '_blank');
                        }
                        location.reload();
                    } else {
                        // Show inline error next to button
                        var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">' + (response.data || 'Error generating PDF') + '</span>');
                        $button.after($error);
                        setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        $button.prop('disabled', false).text('Generate PDF');
                    }
                },
                error: function() {
                    // Show inline error next to button
                    var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">Error generating PDF</span>');
                    $button.after($error);
                    setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                    $button.prop('disabled', false).text('Generate PDF');
                }
            });
        });
        
        // User limits form
        $('#limit_type').on('change', function() {
            if ($(this).val() === 'user') {
                $('#user_row').show();
                $('#role_row').hide();
            } else {
                $('#user_row').hide();
                $('#role_row').show();
            }
        });
        
        // Edit limit
        $('.wcpol-edit-limit').on('click', function() {
            var limit = $(this).data('limit');
            
            $('input[name="limit_id"]').val(limit.id);
            $('#limit_amount').val(limit.limit_amount);
            $('#limit_calculation').val(limit.limit_type);
            $('#priority').val(limit.priority);
            $('#active').prop('checked', limit.active == 1);
            
            if (limit.user_id) {
                $('#limit_type').val('user').trigger('change');
                $('#user_id').val(limit.user_id);
            } else {
                $('#limit_type').val('role').trigger('change');
                $('#user_role').val(limit.user_role);
            }
            
            $('html, body').animate({
                scrollTop: $('.wcpol-add-limit-form').offset().top - 50
            }, 500);
        });
        
        // Date range filters (if implemented)
        $('#wcpol-date-from, #wcpol-date-to').datepicker({
            dateFormat: 'yy-mm-dd',
            maxDate: 0
        });
        
        // Export functionality
        $('.wcpol-export-pos').on('click', function() {
            var format = $(this).data('format');
            var dateFrom = $('#wcpol-date-from').val();
            var dateTo = $('#wcpol-date-to').val();
            
            var url = wcpol_admin.ajax_url + '?action=wcpol_export_pos';
            url += '&format=' + format;
            url += '&date_from=' + dateFrom;
            url += '&date_to=' + dateTo;
            url += '&nonce=' + wcpol_admin.nonce;
            
            window.location.href = url;
        });
        
        // Bulk actions
        $('#wcpol-bulk-action').on('change', function() {
            var action = $(this).val();
            if (!action) return;
            
            var selected = $('.wcpol-po-checkbox:checked');
            if (selected.length === 0) {
                // Show inline message near the select
                var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">Please select at least one purchase order.</span>');
                $(this).after($error);
                setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 3000);
                $(this).val('');
                return;
            }
            
            if (confirm('Are you sure you want to ' + action + ' the selected purchase orders?')) {
                var poIds = [];
                selected.each(function() {
                    poIds.push($(this).val());
                });
                
                $.ajax({
                    url: wcpol_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcpol_bulk_action',
                        bulk_action: action,
                        po_ids: poIds,
                        nonce: wcpol_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            // Show inline error near bulk action select
                            var $error = $('<span class="wcpol-inline-error" style="color: red; margin-left: 10px;">' + (response.data || 'Error performing bulk action') + '</span>');
                            $('#wcpol-bulk-action').after($error);
                            setTimeout(function() { $error.fadeOut(function() { $(this).remove(); }); }, 5000);
                        }
                    }
                });
            }
            
            $(this).val('');
        });
        
        // Select all checkboxes
        $('#wcpol-select-all').on('change', function() {
            $('.wcpol-po-checkbox').prop('checked', $(this).is(':checked'));
        });
        
    });

})(jQuery);