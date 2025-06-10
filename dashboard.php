<?php
/**
 * Dashboard Page
 * 
 * Updated with proper translations and encoding fixes
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/WooCommerce.php';
require_once 'includes/Sync.php';
require_once 'includes/functions.php';
require_once 'includes/i18n.php';

$auth = new Auth();
$product = new Product();
$woocommerce = new WooCommerce();
$sync = new Sync();

$auth->requireAuth();

if ($auth->requiresPasswordReset()) {
    set_flash_message('warning', __('using_default_password'));
    redirect('change-password.php');
}

// Get statistics (these are fast database queries)
$total_products = $product->countAll();
$total_value = $product->getTotalValue();
$total_wholesale_value = $product->getTotalWholesaleValue();
$low_stock_products = $product->getLowStock(5);
$recently_updated = $product->getRecentlyUpdated(5);

// Get last sync
$last_sync = $sync->getLastSync();

// For WooCommerce data, we'll load them asynchronously to improve page load speed
$top_selling = [];
$wc_low_stock = [];
$recently_added = [];

// Only load WooCommerce data if specifically requested via AJAX
if (isset($_GET['load_wc_data']) && $_GET['load_wc_data'] === '1') {
    header('Content-Type: application/json');
    
    try {
        $wc_data = [
            'top_selling' => $woocommerce->getTopSellingProducts(5),
            'wc_low_stock' => $woocommerce->getLowStockProducts(5, 5),
            'recently_added' => $woocommerce->getRecentlyAddedProducts(7, 5)
        ];
        
        echo json_encode(['success' => true, 'data' => $wc_data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo __('dashboard'); ?></h1>
    <button id="refresh-dashboard" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-sync"></i> <?php echo __('refresh'); ?>
    </button>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo __('total_products'); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_products; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php echo __('retail_inventory_value'); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_price($total_value); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-euro-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?php echo __('wholesale_inventory_value'); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_price($total_wholesale_value); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <?php echo __('low_stock_items'); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($low_stock_products); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Second row of stats -->
<div class="row mb-4">
    <div class="col-xl-12">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            <?php echo __('last_sync'); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $last_sync ? time_ago($last_sync->sync_date) : __('never'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-sync fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recently Updated Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recently_updated_products'); ?></h6>
                <a href="search.php" class="btn btn-sm btn-primary"><?php echo __('view_all'); ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($recently_updated)): ?>
                <p class="text-center text-muted"><?php echo __('no_recently_updated_products'); ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo __('product'); ?></th>
                                <th><?php echo __('sku'); ?></th>
                                <th><?php echo __('stock'); ?></th>
                                <th><?php echo __('updated'); ?></th>
                                <th><?php echo __('action'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recently_updated as $product): ?>
                            <tr>
                                <td><?php echo truncate($product->title, 30); ?></td>
                                <td><?php echo htmlspecialchars($product->sku ?? ''); ?></td>
                                <td>
                                    <?php
                                    $stock_class = 'text-success';
                                    if ($product->stock <= 5) {
                                        $stock_class = 'text-danger';
                                    } elseif ($product->stock <= 10) {
                                        $stock_class = 'text-warning';
                                    }
                                    ?>
                                    <span class="<?php echo $stock_class; ?>"><?php echo $product->stock; ?></span>
                                </td>
                                <td><?php echo time_ago($product->last_updated); ?></td>
                                <td>
                                    <a href="product.php?id=<?php echo $product->id; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-danger"><?php echo __('products_low_in_stock'); ?></h6>
                <a href="search.php?filter=low_stock" class="btn btn-sm btn-danger"><?php echo __('view_all'); ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                <p class="text-center text-muted"><?php echo __('no_products_with_low_stock'); ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo __('product'); ?></th>
                                <th><?php echo __('sku'); ?></th>
                                <th><?php echo __('stock'); ?></th>
                                <th><?php echo __('action'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                            <tr>
                                <td><?php echo truncate($product->title, 30); ?></td>
                                <td><?php echo htmlspecialchars($product->sku ?? ''); ?></td>
                                <td><span class="text-danger"><?php echo $product->stock; ?></span></td>
                                <td>
                                    <a href="product.php?id=<?php echo $product->id; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- WooCommerce Data (Loaded Asynchronously) -->
<div class="row">
    <!-- Recently Added from Shop -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-info"><?php echo __('recently_added_to_online_shop'); ?></h6>
            </div>
            <div class="card-body" id="recently-added-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> <?php echo __('loading'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-success"><?php echo __('top_selling_products'); ?></h6>
            </div>
            <div class="card-body" id="top-selling-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> <?php echo __('loading'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Low Stock in Online Shop -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-warning"><?php echo __('products_low_in_stock_online'); ?></h6>
            </div>
            <div class="card-body" id="wc-low-stock-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> <?php echo __('loading'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load WooCommerce data asynchronously to improve page load speed
document.addEventListener('DOMContentLoaded', function() {
    fetch('dashboard.php?load_wc_data=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRecentlyAdded(data.data.recently_added);
                loadTopSelling(data.data.top_selling);
                loadWcLowStock(data.data.wc_low_stock);
            } else {
                document.getElementById('recently-added-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_recently_added_products'); ?></p>';
                document.getElementById('top-selling-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_sales_data_available'); ?></p>';
                document.getElementById('wc-low-stock-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_products_with_low_stock_online'); ?></p>';
            }
        })
        .catch(error => {
            console.error('Error loading WooCommerce data:', error);
            document.getElementById('recently-added-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_recently_added_products'); ?></p>';
            document.getElementById('top-selling-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_sales_data_available'); ?></p>';
            document.getElementById('wc-low-stock-container').innerHTML = '<p class="text-center text-muted"><?php echo __('no_products_with_low_stock_online'); ?></p>';
        });
});

function loadRecentlyAdded(products) {
    const container = document.getElementById('recently-added-container');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-center text-muted"><?php echo __('no_recently_added_products'); ?></p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo __('product'); ?></th><th><?php echo __('sku'); ?></th><th><?php echo __('price'); ?></th><th><?php echo __('added'); ?></th></tr></thead><tbody>';
    
    products.forEach(product => {
        html += `<tr>
            <td>${truncateText(product.name, 30)}</td>
            <td>${product.sku || ''}</td>
            <td>€${parseFloat(product.price || 0).toFixed(2)}</td>
            <td>${timeAgo(product.date_created)}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadTopSelling(products) {
    const container = document.getElementById('top-selling-container');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-center text-muted"><?php echo __('no_sales_data_available'); ?></p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo __('product'); ?></th><th><?php echo __('sku'); ?></th><th><?php echo __('price'); ?></th><th><?php echo __('sales'); ?></th></tr></thead><tbody>';
    
    products.forEach(product => {
        html += `<tr>
            <td>${truncateText(product.name, 30)}</td>
            <td>${product.sku || ''}</td>
            <td>€${parseFloat(product.price || 0).toFixed(2)}</td>
            <td>${product.total_sales || 0}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadWcLowStock(products) {
    const container = document.getElementById('wc-low-stock-container');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-center text-muted"><?php echo __('no_products_with_low_stock_online'); ?></p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo __('product'); ?></th><th><?php echo __('sku'); ?></th><th><?php echo __('price'); ?></th><th><?php echo __('stock'); ?></th></tr></thead><tbody>';
    
    products.forEach(product => {
        html += `<tr>
            <td>${truncateText(product.name, 30)}</td>
            <td>${product.sku || ''}</td>
            <td>€${parseFloat(product.price || 0).toFixed(2)}</td>
            <td><span class="text-warning">${product.stock_quantity || 0}</span></td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function truncateText(text, maxLength) {
    if (text.length > maxLength) {
        return text.substring(0, maxLength) + '...';
    }
    return text;
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return '<?php echo __('just_now'); ?>';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' <?php echo __('minutes_ago'); ?>';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' <?php echo __('hours_ago'); ?>';
    return Math.floor(diffInSeconds / 86400) + ' <?php echo __('days_ago'); ?>';
}
</script>

<?php
include 'templates/footer.php';
?>