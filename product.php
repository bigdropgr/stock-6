<?php
/**
 * Product Edit Page
 * 
 * Allows editing a product in the physical inventory with proper variable product support
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$product_data = $product->getById($id);
if (!$product_data) {
    set_flash_message('error', 'Product not found.');
    redirect('search.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : DEFAULT_LOW_STOCK_THRESHOLD;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    $update_data = [
        'stock' => $stock,
        'low_stock_threshold' => $low_stock_threshold,
        'notes' => $notes
    ];
    
    if ($product->update($id, $update_data)) {
        set_flash_message('success', 'Product updated successfully.');
        redirect('product.php?id=' . $id);
    } else {
        set_flash_message('error', 'Failed to update product.');
    }
}

$last_sync = $sync->getLastSync();

include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Edit Product</h1>
    <div>
        <a href="/search.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-search"></i> Back to Search
        </a>
        <a href="/dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Product Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    Edit Product Details
                    <?php if ($product->isVariableProduct($product_data)): ?>
                    <span class="badge bg-info ms-2">Variable Product</span>
                    <?php elseif ($product->isVariation($product_data)): ?>
                    <span class="badge bg-secondary ms-2">Product Variation</span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">Product Title</label>
                            <input type="text" class="form-control" id="title" value="<?php echo htmlspecialchars($product_data->title); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" value="<?php echo $product_data->sku; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" value="<?php echo htmlspecialchars($product_data->category); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="text" class="form-control" id="price" value="<?php echo number_format($product_data->price, 2); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$product->isVariableProduct($product_data)): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?php echo $product_data->stock; ?>" min="0" required>
                            <div class="form-text">Current physical inventory stock.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                            <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo $product_data->low_stock_threshold; ?>" min="1" required>
                            <div class="form-text">Products with stock below this will be marked as low stock.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($product_data->notes); ?></textarea>
                        <div class="form-text">Add any notes about this product's inventory.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Last Updated</label>
                        <p class="form-control-static"><?php echo format_date($product_data->last_updated); ?></p>
                    </div>
                    
                    <?php if (!$product->isVariableProduct($product_data)): ?>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Update Product</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($product->isVariableProduct($product_data)): ?>
        <!-- Variable Product Variations -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Product Variations</h6>
                <span class="badge bg-info"><?php 
                    $total_stock = $product->getVariableProductTotalStock($product_data->id);
                    echo "Total Stock: " . $total_stock;
                ?></span>
            </div>
            <div class="card-body">
                <?php 
                $variations = $product->getVariations($product_data->product_id);
                if (empty($variations)): 
                ?>
                    <p class="text-center text-muted">No variations found for this product.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Variation</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($variations as $variation): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($variation->title); ?></div>
                                        <?php if ($variation->notes): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($variation->notes); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $variation->sku; ?></td>
                                    <td><?php echo format_price($variation->price); ?></td>
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 120px;">
                                            <input type="number" class="form-control variation-stock" 
                                                   id="variation-stock-<?php echo $variation->id; ?>"
                                                   value="<?php echo $variation->stock; ?>" min="0">
                                            <button type="button" class="btn btn-outline-primary update-variation-stock" 
                                                    data-variation-id="<?php echo $variation->id; ?>">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </div>
                                        <?php
                                        $stock_class = 'text-success';
                                        if ($variation->stock <= 5) {
                                            $stock_class = 'text-danger';
                                        } elseif ($variation->stock <= 10) {
                                            $stock_class = 'text-warning';
                                        }
                                        ?>
                                        <small class="<?php echo $stock_class; ?>">
                                            <?php if ($variation->stock <= $variation->low_stock_threshold): ?>
                                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="product.php?id=<?php echo $variation->id; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-variation" 
                                                data-variation-id="<?php echo $variation->id; ?>"
                                                data-wc-variation-id="<?php echo $variation->product_id; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Product Image -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Product Image</h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($product_data->image_url)): ?>
                <img src="<?php echo $product_data->image_url; ?>" class="img-fluid product-image-preview" alt="<?php echo htmlspecialchars($product_data->title); ?>">
                <?php else: ?>
                <div class="p-5 bg-light">
                    <i class="fas fa-box fa-5x text-muted"></i>
                    <p class="mt-3 text-muted">No image available</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <p class="text-muted">
                        Image from online store.<br>
                        Last updated: <?php echo format_date($product_data->last_updated); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Product Information</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Product ID
                        <span class="badge bg-primary rounded-pill"><?php echo $product_data->id; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        WooCommerce ID
                        <span class="badge bg-secondary rounded-pill"><?php echo $product_data->product_id; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Product Type
                        <span class="badge bg-info rounded-pill"><?php echo ucfirst($product_data->product_type); ?></span>
                    </li>
                    <?php if ($product->isVariation($product_data) && $product_data->parent_id): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Parent Product
                        <?php 
                        $parent = $product->getById($product_data->parent_id);
                        if ($parent): 
                        ?>
                        <a href="product.php?id=<?php echo $parent->id; ?>" class="btn btn-sm btn-outline-primary">
                            View Parent
                        </a>
                        <?php endif; ?>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Created
                        <span><?php echo format_date($product_data->created_at); ?></span>
                    </li>
                </ul>
                
                <div class="mt-3">
                    <a href="https://vakoufaris.com/wp-admin/post.php?post=<?php echo $product_data->product_id; ?>&action=edit" target="_blank" class="btn btn-outline-info w-100">
                        <i class="fas fa-external-link-alt"></i> View in WooCommerce
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'templates/footer.php';
?>