<?php
/**
 * Search Page
 * 
 * Updated with location search and wholesale price display
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/Sync.php';
require_once 'includes/functions.php';

$auth = new Auth();
$product = new Product();
$sync = new Sync();

$auth->requireAuth();

// Get search parameters
$search_term = isset($_GET['term']) ? $_GET['term'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Perform search if term is provided
$results = [];
$total_results = 0;
$total_pages = 1;

if (!empty($search_term)) {
    error_log("Performing search for term: " . $search_term);
    $results = $product->search($search_term, $per_page);
    error_log("Search returned " . count($results) . " results");
} elseif ($filter === 'low_stock') {
    $sql = "SELECT * FROM physical_inventory WHERE is_low_stock = 1 AND stock > 0 ORDER BY stock ASC";
    $db = Database::getInstance();
    $query_result = $db->query($sql);
    
    if ($query_result) {
        while ($row = $query_result->fetch_object()) {
            $results[] = $row;
        }
    }
} else {
    // Default: get all products with pagination
    $offset = ($page - 1) * $per_page;
    $results = $product->getAll($per_page, $offset);
    $total_results = $product->countAll();
    $total_pages = ceil($total_results / $per_page);
}

$last_sync = $sync->getLastSync();

include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Product Search</h1>
    <a href="/dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Search Form -->
<div class="card shadow mb-4">
    <div class="card-body">
        <form action="/search.php" method="get" class="mb-0">
            <div class="row g-2 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" id="search-input" name="term" class="form-control form-control-lg" 
                               placeholder="Search by product name, SKU, aisle, or shelf..." 
                               value="<?php echo safe_htmlspecialchars($search_term); ?>">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="form-text">You can search by product name, SKU, aisle location, or shelf position.</div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-md-end mt-2 mt-md-0">
                        <a href="/search.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <a href="/search.php?filter=low_stock" class="btn <?php echo ($filter === 'low_stock') ? 'btn-danger' : 'btn-outline-danger'; ?>">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Search Results -->
<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <?php
            if (!empty($search_term)) {
                echo 'Search Results for "' . safe_htmlspecialchars($search_term) . '"';
            } elseif ($filter === 'low_stock') {
                echo 'Products Low in Stock';
            } else {
                echo 'All Products';
            }
            ?>
        </h6>
    </div>
    <div class="card-body">
        <div id="alert-container"></div>
        
        <?php if (empty($results)): ?>
        <div class="alert alert-info">
            No products found. <?php echo !empty($search_term) ? 'Try a different search term.' : ''; ?>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($results as $item): ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <div class="card h-100 product-card">
                    <?php if (!empty($item->image_url)): ?>
                    <img src="<?php echo $item->image_url; ?>" class="card-img-top" alt="<?php echo safe_htmlspecialchars($item->title); ?>">
                    <?php else: ?>
                    <div class="text-center p-4 bg-light card-img-top">
                        <i class="fas fa-box fa-4x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title" title="<?php echo safe_htmlspecialchars($item->title); ?>">
                            <?php echo truncate($item->title, 40); ?>
                        </h5>
                        <p class="product-sku mb-1">SKU: <?php echo safe_htmlspecialchars($item->sku ?? ''); ?></p>
                        <p class="mb-1">Category: <?php echo safe_htmlspecialchars($item->category ?: 'N/A'); ?></p>
                        
                        <!-- Pricing Information -->
                        <div class="mb-2">
                            <p class="product-price mb-1">Retail: <?php echo format_price($item->price); ?></p>
                            <?php if (isset($item->wholesale_price) && $item->wholesale_price > 0): ?>
                            <p class="mb-1"><small class="text-muted">Wholesale: <?php echo format_price($item->wholesale_price); ?></small></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Location Information -->
                        <?php if (!empty($item->aisle) || !empty($item->shelf)): ?>
                        <div class="mb-2">
                            <p class="mb-1">
                                <small class="text-info">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php 
                                    $location_parts = [];
                                    if (!empty($item->aisle)) $location_parts[] = "Aisle " . $item->aisle;
                                    if (!empty($item->shelf)) $location_parts[] = "Shelf " . $item->shelf;
                                    echo implode(', ', $location_parts);
                                    ?>
                                </small>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        $stock_class = 'stock-high';
                        if ($item->stock <= 5) {
                            $stock_class = 'stock-low';
                        } elseif ($item->stock <= 10) {
                            $stock_class = 'stock-medium';
                        }
                        ?>
                        
                        <p class="product-stock mb-2">
                            Stock: <span id="current-stock-<?php echo $item->id; ?>" class="<?php echo $stock_class; ?>"><?php echo $item->stock; ?></span>
                        </p>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-primary btn-sm quick-stock-update" data-product-id="<?php echo $item->id; ?>">
                                <i class="fas fa-edit"></i> Stock
                            </button>
                            <a href="product.php?id=<?php echo $item->id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 1 && empty($search_term) && empty($filter)): ?>
        <div class="d-flex justify-content-center mt-4">
            <?php echo pagination($page, $total_pages, 'search.php?page=%d'); ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stock Update Modal -->
<div class="modal fade" id="quickStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quick-stock-form">
                    <input type="hidden" id="quick-stock-product-id" name="product_id">
                    <div class="mb-3">
                        <label for="quick-stock-value" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="quick-stock-value" name="stock" min="0" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'templates/footer.php';
?>