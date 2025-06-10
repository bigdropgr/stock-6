<?php
/**
 * Sync Page with Auto-Continue
 * 
 * Allows synchronizing products with WooCommerce
 * with auto-continuing batches to prevent timeouts
 */

// Enable detailed error reporting in development mode
if (!defined('APP_ENV') || APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Set maximum execution time to 5 minutes for sync operations
ini_set('max_execution_time', 300);

// Include required files - make sure to include these in the correct order
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/WooCommerce.php';
require_once 'includes/Product.php';
require_once 'includes/Sync.php'; // Include Sync.php last to avoid circular dependencies
require_once 'includes/functions.php';
require_once 'includes/i18n.php';

// Initialize classes
$auth = new Auth();
$woocommerce = new WooCommerce();
$sync = new Sync();

// Require authentication
$auth->requireAuth();

// Check if this is an AJAX request for sync progress
if (isset($_GET['action']) && $_GET['action'] === 'progress') {
    $progress = $sync->getSyncProgress();
    header('Content-Type: application/json');
    echo json_encode($progress);
    exit;
}

// Check if this is an AJAX request to continue sync
if (isset($_GET['action']) && $_GET['action'] === 'continue_sync') {
    // Perform sync (will handle continuation automatically)
    $full_sync = isset($_GET['full_sync']) && $_GET['full_sync'] == 1;
    $sync_result = $sync->syncProducts($full_sync);
    
    header('Content-Type: application/json');
    echo json_encode($sync_result);
    exit;
}

// Check if this is an AJAX request for sync logs
if (isset($_GET['action']) && $_GET['action'] === 'get_sync_logs') {
    $logs = $sync->getSyncLogs(10);
    header('Content-Type: application/json');
    echo json_encode(['logs' => $logs]);
    exit;
}

// Check WooCommerce connection
$wc_connection = $woocommerce->testConnection();
$wc_connection_status = $wc_connection['success'] ? 'OK' : 'Error: ' . $wc_connection['message'];

// Process sync request
$sync_error = '';
$sync_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_sync'])) {
        // Reset sync state
        $sync->resetSyncState();
        set_flash_message('info', 'Sync process has been reset.');
        redirect('sync.php');
    } else if (isset($_POST['sync'])) {
        $full_sync = isset($_POST['full_sync']) && $_POST['full_sync'] == 1;
        
        try {
            // Check if the syncProductsComplete method exists
            if (method_exists($sync, 'syncProductsComplete')) {
                // Use the complete sync method that includes variables
                $sync_result = $sync->syncProductsComplete($full_sync);
            } else {
                // Fallback to regular sync
                $sync_result = $sync->syncProducts($full_sync);
            }
            
            // If the sync completed in one go
            if ($sync_result['is_complete']) {
                $message = 'Sync completed successfully!';
                $message .= " Products: {$sync_result['products_added']} added, {$sync_result['products_updated']} updated";
                
                if (isset($sync_result['variations_added'])) {
                    $message .= " | Variations: {$sync_result['variations_added']} added, {$sync_result['variations_updated']} updated";
                }
                
                set_flash_message('success', $message);
                redirect('sync.php');
            }
            
            // Otherwise, show the sync in progress UI
            
        } catch (Exception $e) {
            $sync_error = 'Exception during sync: ' . $e->getMessage();
            set_flash_message('error', $sync_error);
            redirect('sync.php');
        }
    } else if (isset($_POST['manual_variable_sync'])) {
        // Manual variable products sync button
        try {
            $variable_results = $sync->syncVariableProductsComplete();
            
            $message = "Variable products sync completed! ";
            $message .= "Products: {$variable_results['products_added']} added, {$variable_results['products_updated']} updated | ";
            $message .= "Variations: {$variable_results['variations_added']} added, {$variable_results['variations_updated']} updated";
            
            set_flash_message('success', $message);
            redirect('sync.php');
            
        } catch (Exception $e) {
            set_flash_message('error', 'Variable sync failed: ' . $e->getMessage());
            redirect('sync.php');
        }
    }
}
// Get sync state and progress
$sync_state = $sync->getSyncState();
$sync_in_progress = ($sync_state['status'] === Sync::SYNC_STATE_IN_PROGRESS);
$sync_progress = $sync->getSyncProgress();

// Get sync logs
$sync_logs = $sync->getSyncLogs(10);
$last_sync = !empty($sync_logs) ? $sync_logs[0] : null;

// Include header
include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Synchronize with WooCommerce</h1>
    <a href="/dashboard.php" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
    </a>
</div>

<?php if (APP_ENV === 'development' && !$wc_connection['success']): ?>
<div class="alert alert-warning">
    <strong>WooCommerce API Connection Issue:</strong> <?php echo htmlspecialchars($wc_connection['message']); ?>
    <br>
    <small>This message is only visible in development mode.</small>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Sync Status -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sync Products</h6>
            </div>
            <div class="card-body">
                <?php if ($sync_in_progress): ?>
                <!-- Sync in progress UI -->
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i> 
                    <span id="sync-status-message">Sync in progress. This may take several minutes depending on the number of products.</span>
                </div>
                
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         id="sync-progress-bar"
                         style="width: <?php echo $sync_progress['percent']; ?>%" 
                         aria-valuenow="<?php echo $sync_progress['percent']; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?php echo $sync_progress['percent']; ?>%
                    </div>
                </div>
                
                <div id="sync-progress-info" class="text-center mb-4">
                    <p>
                        <strong>Products Processed:</strong> <span id="processed-count"><?php echo $sync_progress['processed']; ?></span>
                        <?php if ($sync_progress['total'] > 0): ?>
                        of <span id="total-count"><?php echo $sync_progress['total']; ?></span>
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong>Products Added:</strong> <span id="added-count"><?php echo $sync_progress['products_added']; ?></span>
                        <strong>Products Updated:</strong> <span id="updated-count"><?php echo $sync_progress['products_updated']; ?></span>
                    </p>
                    <p>
                        <span id="sync-phase-message">Importing simple products...</span>
                    </p>
                </div>
                
                <div class="d-flex justify-content-between">
                    <form action="" method="post" class="d-inline">
                        <input type="hidden" name="reset_sync" value="1">
                        <button type="submit" class="btn btn-danger" id="cancel-sync-btn">
                            <i class="fas fa-stop"></i> Cancel Sync
                        </button>
                    </form>
                    
                    <div id="sync-auto-continue-message" class="text-muted">
                        <i class="fas fa-info-circle"></i> Sync will automatically continue. Please keep this page open.
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Normal sync UI -->
                <p>
                    Synchronize your physical inventory with products from your WooCommerce store. 
                    This will import any new products from WooCommerce and update existing product information.
                </p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Product stock levels in your physical inventory will NOT be overwritten during synchronization.
                </div>
                
                <div id="sync-status">
                    <?php if (!empty($sync_error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($sync_error); ?>
                    </div>
                    <?php elseif ($last_sync): ?>
                    <div class="alert alert-<?php echo $last_sync->status === 'success' ? 'success' : 'danger'; ?>">
                        Last sync: <?php echo format_date($last_sync->sync_date); ?><br>
                        Products added: <?php echo $last_sync->products_added; ?><br>
                        Products updated: <?php echo $last_sync->products_updated; ?><br>
                        Status: <?php echo ucfirst($last_sync->status); ?>
                        <?php if ($last_sync->status === 'error' && !empty($last_sync->details)): ?>
                        <br>Error: <?php echo htmlspecialchars($last_sync->details); ?>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        No synchronization has been performed yet.
                    </div>
                    <?php endif; ?>
                </div>
                
                <form action="" method="post" id="sync-form">
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" name="sync" value="1" class="btn btn-primary btn-lg">
                            <i class="fas fa-sync"></i> Sync Products Now
                        </button>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="full-sync-check" name="full_sync" value="1">
                        <label class="form-check-label" for="full-sync-check">
                            Full sync (update all product data, not just new products)
                        </label>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Scheduled Sync -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Scheduled Sync</h6>
            </div>
            <div class="card-body">
                <p>
                    The system is configured to automatically synchronize with WooCommerce every Monday.
                </p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Automatic sync will only import newly added products, not update existing ones.
                </div>
                
                <div class="mt-3">
                    <h6>Next scheduled sync:</h6>
                    <p>
                        <?php
                        // Calculate next Monday
                        $now = time();
                        $days_to_monday = 1 - date('N', $now);
                        if ($days_to_monday <= 0) {
                            $days_to_monday += 7;
                        }
                        $next_monday = strtotime("+{$days_to_monday} days", $now);
                        echo date('l, F j, Y', $next_monday);
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Sync Logs -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sync History</h6>
            </div>
            <div class="card-body">
                <div id="sync-logs" class="sync-logs">
                    <?php if (empty($sync_logs)): ?>
                    <p class="text-center text-muted">No sync logs available.</p>
                    <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Added</th>
                                <th>Updated</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sync_logs as $log): ?>
                            <tr>
                                <td><?php echo format_date($log->sync_date); ?></td>
                                <td><?php echo $log->products_added; ?></td>
                                <td><?php echo $log->products_updated; ?></td>
                                <td class="sync-status-<?php echo $log->status; ?>"><?php echo ucfirst($log->status); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sync Help -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sync Options</h6>
            </div>
            <div class="card-body">
                <h6>Regular Sync</h6>
                <p>Imports new products and updates basic information for existing products.</p>
                
                <h6>Full Sync</h6>
                <p>Updates all product data from WooCommerce, but preserves your physical inventory stock levels.</p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    For large catalogs, sync operations will process products in batches to avoid timeouts.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($sync_in_progress): ?>
<!-- Make sure jQuery is loaded before using it -->
<script>
// Wait for jQuery to be fully loaded
document.addEventListener("DOMContentLoaded", function() {
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded! Auto-sync will not work.');
        document.getElementById('sync-status-message').innerHTML = 
            'Error: jQuery is not loaded. Please refresh the page or contact support.';
        return;
    }
    
    // Now we can use jQuery safely
    jQuery(function($) {
        // Set variables for sync
        var syncIntervalId;
        var fullSync = <?php echo isset($sync_state['full_sync']) && $sync_state['full_sync'] ? 'true' : 'false'; ?>;
        var variablePhaseStarted = false;
        var retryCount = 0;
        var maxRetries = 5;

        // Function to continue the sync automatically
        function continueSyncAutomatically() {
            $.ajax({
                url: 'sync.php?action=continue_sync',
                type: 'GET',
                data: {
                    full_sync: fullSync ? 1 : 0
                },
                dataType: 'json',
                success: function(syncResult) {
                    // Reset retry counter on success
                    retryCount = 0;
                    
                    updateProgressUI(syncResult);
                    
                    // If sync is complete, reload the page to show completion status
                    if (syncResult.is_complete) {
                        clearInterval(syncIntervalId);
                        $('#sync-status-message').html('Sync completed successfully! Refreshing page...');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                        return;
                    }
                    
                    // Check if we're in the variable products phase
                    if (syncResult.variations_added !== undefined || syncResult.variations_updated !== undefined) {
                        variablePhaseStarted = true;
                        $('#sync-phase-message').html('Importing variable products and variations...');
                    }
                    
                    // Schedule the next continuation after a short delay to avoid hammering the server
                    setTimeout(continueSyncAutomatically, 1000);
                },
                error: function(xhr, status, error) {
                    console.error('Error continuing sync:', error, xhr.responseText);
                    
                    retryCount++;
                    
                    if (retryCount <= maxRetries) {
                        // Try again after a longer delay
                        $('#sync-status-message').html('Sync encountered an error. Retrying... (Attempt ' + retryCount + ' of ' + maxRetries + ')');
                        setTimeout(continueSyncAutomatically, 5000);
                    } else {
                        // Give up after max retries
                        $('#sync-status-message').html('Sync process has failed after multiple attempts. Please try again later or contact support.');
                        $('#sync-auto-continue-message').html('<span class="text-danger">Automatic continuation stopped due to errors.</span>');
                        clearInterval(syncIntervalId);
                        
                        // Re-enable cancel button
                        $('#cancel-sync-btn').prop('disabled', false);
                    }
                }
            });
        }

        // Update the progress UI with the sync result data
        function updateProgressUI(syncResult) {
            // For debugging
            console.log('Sync result:', syncResult);
            
            // Update progress bar
            var percent = syncResult.progress_percent || 0;
            $('#sync-progress-bar').css('width', percent + '%').attr('aria-valuenow', percent).text(percent + '%');
            
            // Update counts
            $('#processed-count').text(syncResult.processed_products || 0);
            $('#total-count').text(syncResult.total_products || syncResult.estimated_total || 0);
            $('#added-count').text(syncResult.products_added || 0);
            $('#updated-count').text(syncResult.products_updated || 0);
            
            // Add variable products info if we're in that phase
            if (variablePhaseStarted || syncResult.variations_added !== undefined) {
                var variableHTML = '<p><strong>Variable Products Added:</strong> ' + 
                                (syncResult.variable_products_added || 0) + 
                                ' <strong>Variations Added:</strong> ' + 
                                (syncResult.variations_added || 0) + '</p>';
                
                if ($('#variable-products-info').length === 0) {
                    $('#sync-progress-info').append('<div id="variable-products-info">' + variableHTML + '</div>');
                } else {
                    $('#variable-products-info').html(variableHTML);
                }
            }
        }

        // Start the sync process as soon as the page loads
        $(document).ready(function() {
            console.log('Starting auto-sync process...');
            
            // Disable cancel button during automatic continuation
            $('#cancel-sync-btn').prop('disabled', true);
            
            // Start the automatic sync continuation
            continueSyncAutomatically();
            
            // Monitor progress using a separate progress check
            syncIntervalId = setInterval(function() {
                $.ajax({
                    url: 'sync.php?action=progress',
                    type: 'GET',
                    dataType: 'json',
                    success: function(progressData) {
                        if (!progressData.in_progress) {
                            clearInterval(syncIntervalId);
                            location.reload();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking progress:', error, xhr.responseText);
                    }
                });
            }, 10000); // Check overall progress every 10 seconds
            
            // Re-enable cancel button after 3 seconds
            setTimeout(function() {
                $('#cancel-sync-btn').prop('disabled', false);
            }, 3000);
        });
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include 'templates/footer.php';
?>