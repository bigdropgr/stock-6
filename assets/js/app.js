/**
 * Custom JavaScript for the Physical Store Inventory System
 */

$(document).ready(function() {
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Search functionality
    // Search functionality - simple form submission
    // We're not using the AJAX version for now to simplify
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $(this).closest('form').submit();
        }
    });
    
    // Display search results
    function displaySearchResults(results) {
        var html = '';
        
        if (results.length === 0) {
            html = '<div class="alert alert-info">No products found matching your search.</div>';
        } else {
            html = '<div class="row">';
            
            $.each(results, function(index, product) {
                var stockClass = 'stock-high';
                if (product.stock <= 5) {
                    stockClass = 'stock-low';
                } else if (product.stock <= 10) {
                    stockClass = 'stock-medium';
                }
                
                html += '<div class="col-md-4 mb-4">';
                html += '<div class="card product-card">';
                
                if (product.image_url) {
                    html += '<img src="' + product.image_url + '" class="card-img-top" alt="' + product.title + '">';
                } else {
                    html += '<div class="text-center p-4 bg-light card-img-top"><i class="fas fa-box fa-4x text-muted"></i></div>';
                }
                
                html += '<div class="card-body">';
                html += '<h5 class="card-title">' + product.title + '</h5>';
                html += '<p class="product-sku mb-1">SKU: ' + product.sku + '</p>';
                html += '<p class="mb-1">Category: ' + (product.category || 'N/A') + '</p>';
                html += '<p class="product-price mb-1">Price: €' + parseFloat(product.price).toFixed(2) + '</p>';
                html += '<p class="product-stock mb-2">Stock: <span class="' + stockClass + '">' + product.stock + '</span></p>';
                html += '<a href="product.php?id=' + product.id + '" class="btn btn-primary">Edit</a>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        $('#search-results').html(html);
    }
    
    // Quick stock update
    $('.quick-stock-update').on('click', function(e) {
        e.preventDefault();
        
        var productId = $(this).data('product-id');
        var currentStock = parseInt($('#current-stock-' + productId).text());
        
        $('#quick-stock-product-id').val(productId);
        $('#quick-stock-value').val(currentStock);
        $('#quickStockModal').modal('show');
    });
    
    // Stock update form
    $('#quick-stock-form').on('submit', function(e) {
        e.preventDefault();
        
        var productId = $('#quick-stock-product-id').val();
        var newStock = $('#quick-stock-value').val();
        
        $.ajax({
            url: 'api/products.php',
            type: 'POST',
            data: {
                action: 'update_stock',
                product_id: productId,
                stock: newStock
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#quickStockModal').modal('hide');
                    showAlert('Stock updated successfully', 'success');
                    
                    // Update the displayed stock value
                    var stockElement = $('#current-stock-' + productId);
                    stockElement.text(newStock);
                    
                    // Update stock class
                    stockElement.removeClass('stock-high stock-medium stock-low');
                    if (newStock <= 5) {
                        stockElement.addClass('stock-low');
                    } else if (newStock <= 10) {
                        stockElement.addClass('stock-medium');
                    } else {
                        stockElement.addClass('stock-high');
                    }
                } else {
                    showAlert('Error updating stock: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error updating stock. Please try again.', 'danger');
                console.error(error);
            }
        });
    });
    
    // Sync products
    $('#sync-products-btn').on('click', function(e) {
        e.preventDefault();
        
        // Disable button and show loading
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing...');
        
        // Show sync status
        $('#sync-status').html('<div class="alert alert-info">Sync in progress... This may take a few minutes.</div>');
        
        $.ajax({
            url: 'api/sync.php',
            type: 'POST',
            data: {
                action: 'sync_products'
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html('Sync Products');
                
                if (response.status === 'success') {
                    $('#sync-status').html('<div class="alert alert-success">Sync completed successfully.<br>Products added: ' + response.products_added + '<br>Products updated: ' + response.products_updated + '<br>Duration: ' + response.duration.toFixed(2) + ' seconds</div>');
                    
                    // Reload sync logs
                    loadSyncLogs();
                } else {
                    $('#sync-status').html('<div class="alert alert-danger">Sync failed: ' + response.errors.join('<br>') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('Sync Products');
                $('#sync-status').html('<div class="alert alert-danger">Error during sync. Please try again.</div>');
                console.error(error);
            }
        });
    });
    
    // Load sync logs
    function loadSyncLogs() {
        $.ajax({
            url: 'api/sync.php',
            type: 'GET',
            data: {
                action: 'get_sync_logs'
            },
            dataType: 'json',
            success: function(response) {
                if (response.logs && response.logs.length > 0) {
                    displaySyncLogs(response.logs);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading sync logs:', error);
            }
        });
    }
    
    // Display sync logs
    function displaySyncLogs(logs) {
        var html = '<table class="table table-striped">';
        html += '<thead><tr><th>Date</th><th>Products Added</th><th>Products Updated</th><th>Status</th></tr></thead>';
        html += '<tbody>';
        
        $.each(logs, function(index, log) {
            var statusClass = log.status === 'success' ? 'sync-status-success' : 'sync-status-error';
            
            html += '<tr>';
            html += '<td>' + formatDate(log.sync_date) + '</td>';
            html += '<td>' + log.products_added + '</td>';
            html += '<td>' + log.products_updated + '</td>';
            html += '<td class="' + statusClass + '">' + log.status + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        $('#sync-logs').html(html);
    }
    
    // Helper function to format date
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    // Load sync logs if on sync page
    if ($('#sync-logs').length > 0) {
        loadSyncLogs();
    }
    
    // Dashboard refresh
    $('#refresh-dashboard').on('click', function(e) {
        e.preventDefault();
        location.reload();
    });

    /**
     * JavaScript for managing product variations
     */

    // Update variation stock
    $(document).on('click', '.update-variation-stock', function() {
        var variationId = $(this).data('variation-id');
        var stock = $('#variation-stock-' + variationId).val();
        
        // Disable the button and show loading state
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        
        $.ajax({
            url: 'api/products.php',
            type: 'POST',
            data: {
                action: 'update_variation_stock',
                variation_id: variationId,
                stock: stock
            },
            dataType: 'json',
            success: function(response) {
                // Restore button state
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save');
                
                if (response.success) {
                    showAlert('Stock updated successfully for variation.', 'success');
                    
                    // Update stock class if needed
                    var $stockInput = $('#variation-stock-' + variationId);
                    var newStock = parseInt(stock);
                    
                    // Update any parent row stock class if present
                    var $row = $btn.closest('tr');
                    $row.removeClass('table-danger table-warning');
                    
                    if (newStock <= 5 && newStock > 0) {
                        $row.addClass('table-warning');
                    } else if (newStock === 0) {
                        $row.addClass('table-danger');
                    }
                } else {
                    showAlert('Error updating variation stock: ' + response.message, 'danger');
                }
            },
            error: function() {
                // Restore button state
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save');
                showAlert('Error communicating with server. Please try again.', 'danger');
            }
        });
    });
    
    // Delete variation
    $(document).on('click', '.delete-variation', function() {
        if (!confirm('Are you sure you want to delete this variation? It will not be imported again in future syncs.')) {
            return;
        }
        
        var variationId = $(this).data('variation-id');
        var wcVariationId = $(this).data('wc-variation-id');
        var $row = $(this).closest('tr');
        
        // Disable the button and show loading state
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        
        $.ajax({
            url: 'api/products.php',
            type: 'POST',
            data: {
                action: 'delete_variation',
                variation_id: variationId,
                wc_variation_id: wcVariationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fade out and remove the row
                    $row.fadeOut(300, function() { 
                        $(this).remove();
                        
                        // If no more rows, show the "No variations" message
                        if ($row.siblings().length === 0) {
                            var noVariationsMsg = '<p class="text-center text-muted">No variations found for this product.</p>';
                            $row.closest('tbody').replaceWith(noVariationsMsg);
                        }
                    });
                    
                    showAlert('Variation deleted successfully', 'success');
                } else {
                    // Restore button state
                    $btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                    showAlert('Error deleting variation: ' + response.message, 'danger');
                }
            },
            error: function() {
                // Restore button state
                $btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                showAlert('Error communicating with server. Please try again.', 'danger');
            }
        });
    });
    
    // Show alert
    function showAlert(message, type) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">';
        alertHtml += message;
        alertHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        alertHtml += '</div>';
        
        $('#alert-container').html(alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('#alert-container .alert').alert('close');
        }, 5000);
    }
    
    // Update variation stock using direct endpoint
    $(document).on('click', '.update-variation-stock', function(e) {
        e.preventDefault();
        
        var variationId = $(this).data('variation-id');
        var stock = $('#variation-stock-' + variationId).val();
        
        // Validate input
        if (!stock || isNaN(stock) || parseInt(stock) < 0) {
            showAlert('Please enter a valid stock quantity (0 or greater)', 'danger');
            return;
        }
        
        // Disable the button and show loading state
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        
        // Use direct endpoint instead of api/products.php
        $.ajax({
            url: 'update-variation-stock.php', // Direct endpoint
            type: 'POST',
            data: {
                variation_id: variationId,
                stock: parseInt(stock)
            },
            dataType: 'json',
            timeout: 15000, // 15 second timeout
            success: function(response) {
                // Restore button state
                $btn.prop('disabled', false).html(originalHtml);
                
                if (response.success) {
                    showAlert('Stock updated successfully for variation.', 'success');
                    
                    // Update stock class based on new value
                    var newStock = parseInt(stock);
                    var $row = $btn.closest('tr');
                    $row.removeClass('table-danger table-warning');
                    
                    if (newStock <= 5 && newStock > 0) {
                        $row.addClass('table-warning');
                    } else if (newStock === 0) {
                        $row.addClass('table-danger');
                    }
                    
                    // Update total stock display
                    updateVariationTotalStock();
                    
                    console.log('Stock update successful:', response);
                } else {
                    showAlert('Error updating variation stock: ' + (response.message || 'Unknown error'), 'danger');
                    console.error('Stock update failed:', response);
                }
            },
            error: function(xhr, status, error) {
                // Restore button state
                $btn.prop('disabled', false).html(originalHtml);
                
                var errorMessage = 'Error communicating with server.';
                
                if (xhr.status === 401) {
                    errorMessage = 'Access denied. Please refresh the page and try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Forbidden. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again.';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                showAlert(errorMessage, 'danger');
                
                // Detailed error logging
                console.error('AJAX Error Details:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    variationId: variationId,
                    stock: stock
                });
            }
        });
    });
    
    // Function to update total stock display for variable products
    function updateVariationTotalStock() {
        var totalStock = 0;
        
        // Find all variation stock inputs and sum them up
        $('input[id^="variation-stock-"]').each(function() {
            var stock = parseInt($(this).val()) || 0;
            totalStock += stock;
        });
        
        // Update the total stock badge if it exists
        var $totalStockBadge = $('.badge:contains("Total Stock:")');
        if ($totalStockBadge.length > 0) {
            $totalStockBadge.text('Total Stock: ' + totalStock);
        }
        
        console.log('Updated total stock to:', totalStock);
    }
});