<?php
/**
 * Dashboard Page
 * 
 * Updated with wholesale inventory value and performance improvements
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/WooCommerce.php';
require_once 'includes/Sync.php';
require_once 'includes/functions.php';

$auth = new Auth();
$product = new Product();
$woocommerce = new WooCommerce();
$sync = new Sync();

$auth->requireAuth();

if ($auth->requiresPasswordReset()) {
    set_flash_message('warning', 'Please change your default password for security reasons.');
    redirect('change-password.php');
}

// Get statistics (these are fast database queries)
$total_products = $product->countAll();
$total_value = $product->getTotalValue();
$total_wholesale_value = $product->getTotalWholesaleValue(); // New wholesale value
$low_stock_products = $product->getLowStock(5);
$recently_updated = $product->getRecentlyUpdated(5);

// Get last sync
$last_sync = $sync->getLastSync();

// For WooCommerce data, we'll load them asynchronously to improve page load speed
// These will be loaded via AJAX after the page loads
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
    <h1 class="h3 mb-0">Dashboard</h1>
    <button id="refresh-dashboard" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-sync"></i> Refresh
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
                            Total Products</div>
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
                            Retail Inventory Value</div>
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
                            Wholesale Inventory Value</div>
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
                            Low Stock Items</div>
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
                            Last Sync</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $last_sync ? time_ago($last_sync->sync_date) : 'Never'; ?>
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
                <h6 class="m-0 font-weight-bold text-primary">Recently Updated Products</h6>
                <a href="/search.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recently_updated)): ?>
                <p class="text-center text-muted">No recently updated products.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Updated</th>
                                <th>Action</th>
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
                <h6 class="m-0 font-weight-bold text-danger">Products Low in Stock</h6>
                <a href="/search.php?filter=low_stock" class="btn btn-sm btn-danger">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                <p class="text-center text-muted">No products with low stock.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Action</th>
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
                <h6 class="m-0 font-weight-bold text-info">Recently Added to Online Shop</h6>
            </div>
            <div class="card-body" id="recently-added-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-success">Top Selling Products</h6>
            </div>
            <div class="card-body" id="top-selling-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
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
                <h6 class="m-0 font-weight-bold text-warning">Products Low in Stock in Online Shop</h6>
            </div>
            <div class="card-body" id="wc-low-stock-container">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
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
                document.getElementById('recently-added-container').innerHTML = '<p class="text-center text-muted">Failed to load data from online shop.</p>';
                document.getElementById('top-selling-container').innerHTML = '<p class="text-center text-muted">Failed to load data from online shop.</p>';
                document.getElementById('wc-low-stock-container').innerHTML = '<p class="text-center text-muted">Failed to load data from online shop.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading WooCommerce data:', error);
            document.getElementById('recently-added-container').innerHTML = '<p class="text-center text-muted">Error loading data from online shop.</p>';
            document.getElementById('top-selling-container').innerHTML = '<p class="text-center text-muted">Error loading data from online shop.</p>';
            document.getElementById('wc-low-stock-container').innerHTML = '<p class="text-center text-muted">Error loading data from online shop.</p>';
        });
});

function loadRecentlyAdded(products) {
    const container = document.getElementById('recently-added-container');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No recently added products in online shop.</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Added</th></tr></thead><tbody>';
    
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
        container.innerHTML = '<p class="text-center text-muted">No sales data available.</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Sales</th></tr></thead><tbody>';
    
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
        container.innerHTML = '<p class="text-center text-muted">No products with low stock in online shop.</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th></tr></thead><tbody>';
    
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
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}
</script>

<?php
include 'templates/footer.php';
?>