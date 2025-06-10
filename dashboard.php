<?php
/**
 * Dashboard Page
 * 
 * Main dashboard with overview statistics and key metrics
 */

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/WooCommerce.php';
require_once 'includes/Sync.php';
require_once 'includes/functions.php';

// Initialize classes
$auth = new Auth();
$product = new Product();
$woocommerce = new WooCommerce();
$sync = new Sync();

// Require authentication
$auth->requireAuth();

// Check if user needs to change password
if ($auth->requiresPasswordReset()) {
    set_flash_message('warning', 'Please change your default password for security reasons.');
    redirect('change-password.php');
}

// Get statistics
$total_products = $product->countAll();
$total_value = $product->getTotalValue();
$low_stock_products = $product->getLowStock(5);
$recently_updated = $product->getRecentlyUpdated(5);

// Get WooCommerce data
$top_selling = $woocommerce->getTopSellingProducts(5);
$wc_low_stock = $woocommerce->getLowStockProducts(5, 5);
$recently_added = $woocommerce->getRecentlyAddedProducts(7, 5);

// Get last sync
$last_sync = $sync->getLastSync();

// Include header
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
                            Inventory Value</div>
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

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Last Sync
                        </div>
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
                                <td><?php echo $product->sku; ?></td>
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
                                <td><?php echo $product->sku; ?></td>
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

<div class="row">
    <!-- Recently Added from Shop -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-info">Recently Added to Online Shop</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recently_added)): ?>
                <p class="text-center text-muted">No recently added products in online shop.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recently_added as $product): ?>
                            <tr>
                                <td><?php echo truncate($product->name, 30); ?></td>
                                <td><?php echo $product->sku; ?></td>
                                <td><?php echo format_price($product->price); ?></td>
                                <td><?php echo time_ago($product->date_created); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-success">Top Selling Products</h6>
            </div>
            <div class="card-body">
                <?php if (empty($top_selling)): ?>
                <p class="text-center text-muted">No sales data available.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_selling as $product): ?>
                            <tr>
                                <td><?php echo truncate($product->name, 30); ?></td>
                                <td><?php echo $product->sku; ?></td>
                                <td><?php echo format_price($product->price); ?></td>
                                <td><?php echo $product->total_sales; ?></td>
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

<div class="row">
    <!-- Low Stock in Online Shop -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-warning">Products Low in Stock in Online Shop</h6>
            </div>
            <div class="card-body">
                <?php if (empty($wc_low_stock)): ?>
                <p class="text-center text-muted">No products with low stock in online shop.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wc_low_stock as $product): ?>
                            <tr>
                                <td><?php echo truncate($product->name, 30); ?></td>
                                <td><?php echo $product->sku; ?></td>
                                <td><?php echo format_price($product->price); ?></td>
                                <td><span class="text-warning"><?php echo $product->stock_quantity; ?></span></td>
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

<?php
// Include footer
include 'templates/footer.php';
?>