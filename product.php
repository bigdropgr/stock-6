<?php
/**
 * Product Edit Page
 * 
 * Fixed with proper translations and language switching
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/Sync.php';
require_once 'includes/functions.php';
require_once 'includes/i18n.php';

$auth = new Auth();
$product = new Product();
$sync = new Sync();

$auth->requireAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$product_data = $product->getById($id);
if (!$product_data) {
    set_flash_message('error', __('product_not_found'));
    redirect('search.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $wholesale_price = isset($_POST['wholesale_price']) ? floatval($_POST['wholesale_price']) : 0.0;
    $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : DEFAULT_LOW_STOCK_THRESHOLD;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Location fields
    $aisle = isset($_POST['aisle']) ? $_POST['aisle'] : '';
    $shelf = isset($_POST['shelf']) ? $_POST['shelf'] : '';
    $storage_notes = isset($_POST['storage_notes']) ? $_POST['storage_notes'] : '';
    $date_of_entry = isset($_POST['date_of_entry']) && !empty($_POST['date_of_entry']) ? $_POST['date_of_entry'] : null;
    
    $update_data = [
        'stock' => $stock,
        'wholesale_price' => $wholesale_price,
        'low_stock_threshold' => $low_stock_threshold,
        'notes' => $notes,
        'aisle' => $aisle,
        'shelf' => $shelf,
        'storage_notes' => $storage_notes,
        'date_of_entry' => $date_of_entry
    ];
    
    if ($product->update($id, $update_data)) {
        set_flash_message('success', __('product_updated_successfully'));
        redirect('product.php?id=' . $id);
    } else {
        set_flash_message('error', __('failed_to_update_product'));
    }
}

$last_sync = $sync->getLastSync();

include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo __('edit_product'); ?></h1>
    <div>
        <a href="/search.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-search"></i> <?php echo __('back'); ?> <?php echo __('search'); ?>
        </a>
        <a href="/dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Product Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php echo __('edit_product'); ?> <?php echo __('product_information'); ?>
                    <?php if ($product->isVariableProduct($product_data)): ?>
                    <span class="badge bg-info ms-2"><?php echo __('variable_product'); ?></span>
                    <?php elseif ($product->isVariation($product_data)): ?>
                    <span class="badge bg-secondary ms-2"><?php echo __('product_variation'); ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label"><?php echo __('product_title'); ?></label>
                            <input type="text" class="form-control" id="title" value="<?php echo htmlspecialchars($product_data->title ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="sku" class="form-label"><?php echo __('sku'); ?></label>
                            <input type="text" class="form-control" id="sku" value="<?php echo htmlspecialchars($product_data->sku ?? ''); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label"><?php echo __('category'); ?></label>
                            <input type="text" class="form-control" id="category" value="<?php echo htmlspecialchars($product_data->category ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label"><?php echo __('retail_price'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="text" class="form-control" id="price" value="<?php echo number_format($product_data->price, 2); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$product->isVariableProduct($product_data)): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stock" class="form-label"><?php echo __('stock_quantity'); ?></label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?php echo $product_data->stock; ?>" min="0" required>
                            <div class="form-text"><?php echo __('current_physical_inventory'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="wholesale_price" class="form-label"><?php echo __('wholesale_price'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" class="form-control" id="wholesale_price" name="wholesale_price" value="<?php echo number_format($product_data->wholesale_price ?? 0, 2); ?>" min="0" step="0.01">
                            </div>
                            <div class="form-text"><?php echo __('cost_price_help'); ?></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="low_stock_threshold" class="form-label"><?php echo __('low_stock_threshold'); ?></label>
                            <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo $product_data->low_stock_threshold; ?>" min="1" required>
                            <div class="form-text"><?php echo __('low_stock_threshold_help'); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($product_data->notes ?? ''); ?></textarea>
                        <div class="form-text"><?php echo __('inventory_notes_help'); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('last_updated'); ?></label>
                        <p class="form-control-static"><?php echo format_date($product_data->last_updated); ?></p>
                    </div>
                    
                    <?php if (!$product->isVariableProduct($product_data)): ?>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg"><?php echo __('update'); ?> <?php echo __('product'); ?></button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Location in Physical Store Section -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-map-marker-alt"></i> <?php echo __('location_in_physical_store'); ?>
                </h6>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <!-- Hidden fields to preserve other data when submitting location updates -->
                    <?php if (!$product->isVariableProduct($product_data)): ?>
                    <input type="hidden" name="stock" value="<?php echo $product_data->stock; ?>">
                    <input type="hidden" name="wholesale_price" value="<?php echo $product_data->wholesale_price ?? 0; ?>">
                    <input type="hidden" name="low_stock_threshold" value="<?php echo $product_data->low_stock_threshold; ?>">
                    <input type="hidden" name="notes" value="<?php echo htmlspecialchars($product_data->notes ?? ''); ?>">
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="aisle" class="form-label"><?php echo __('aisle'); ?></label>
                            <input type="text" class="form-control" id="aisle" name="aisle" value="<?php echo htmlspecialchars($product_data->aisle ?? ''); ?>" placeholder="<?php echo __('aisle_placeholder'); ?>">
                            <div class="form-text"><?php echo __('aisle_help'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="shelf" class="form-label"><?php echo __('shelf'); ?></label>
                            <input type="text" class="form-control" id="shelf" name="shelf" value="<?php echo htmlspecialchars($product_data->shelf ?? ''); ?>" placeholder="<?php echo __('shelf_placeholder'); ?>">
                            <div class="form-text"><?php echo __('shelf_help'); ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="storage_notes" class="form-label"><?php echo __('storage_notes'); ?></label>
                        <textarea class="form-control" id="storage_notes" name="storage_notes" rows="3" placeholder="<?php echo __('storage_notes_placeholder'); ?>"><?php echo htmlspecialchars($product_data->storage_notes ?? ''); ?></textarea>
                        <div class="form-text"><?php echo __('storage_notes_help'); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_of_entry" class="form-label"><?php echo __('date_of_entry'); ?></label>
                        <input type="date" class="form-control" id="date_of_entry" name="date_of_entry" value="<?php echo $product_data->date_of_entry ?? ''; ?>">
                        <div class="form-text"><?php echo __('date_of_entry_help'); ?></div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success"><?php echo __('update_location_information'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($product->isVariableProduct($product_data)): ?>
        <!-- Variable Product Variations -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('product_variations'); ?></h6>
                <span class="badge bg-info"><?php 
                    $total_stock = $product->getVariableProductTotalStock($product_data->id);
                    echo __('total_stock') . ": " . $total_stock;
                ?></span>
            </div>
            <div class="card-body">
                <?php 
                $variations = $product->getVariations($product_data->product_id);
                if (empty($variations)): 
                ?>
                    <p class="text-center text-muted"><?php echo __('no_variations_found'); ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo __('variation'); ?></th>
                                    <th><?php echo __('sku'); ?></th>
                                    <th><?php echo __('retail_price'); ?></th>
                                    <th><?php echo __('wholesale_price'); ?></th>
                                    <th><?php echo __('stock'); ?></th>
                                    <th><?php echo __('actions'); ?></th>
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
                                    <td><?php echo htmlspecialchars($variation->sku ?? ''); ?></td>
                                    <td><?php echo format_price($variation->price); ?></td>
                                    <td><?php echo format_price($variation->wholesale_price ?? 0); ?></td>
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
                                                <i class="fas fa-exclamation-triangle"></i> <?php echo __('low_stock_warning'); ?>
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
                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('product_image'); ?></h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($product_data->image_url)): ?>
                <img src="<?php echo $product_data->image_url; ?>" class="img-fluid product-image-preview" alt="<?php echo htmlspecialchars($product_data->title); ?>">
                <?php else: ?>
                <div class="p-5 bg-light">
                    <i class="fas fa-box fa-5x text-muted"></i>
                    <p class="mt-3 text-muted"><?php echo __('no_image_available'); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <p class="text-muted">
                        <?php echo __('image_from_online_store'); ?><br>
                        <?php echo __('last_updated'); ?>: <?php echo format_date($product_data->last_updated); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('product_information'); ?></h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo __('product_id'); ?>
                        <span class="badge bg-primary rounded-pill"><?php echo $product_data->id; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo __('woocommerce_id'); ?>
                        <span class="badge bg-secondary rounded-pill"><?php echo $product_data->product_id; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo __('product_type'); ?>
                        <span class="badge bg-info rounded-pill"><?php echo ucfirst($product_data->product_type); ?></span>
                    </li>
                    <?php if ($product->isVariation($product_data) && $product_data->parent_id): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo __('parent_product'); ?>
                        <?php 
                        $parent = $product->getById($product_data->parent_id);
                        if ($parent): 
                        ?>
                        <a href="product.php?id=<?php echo $parent->id; ?>" class="btn btn-sm btn-outline-primary">
                            <?php echo __('view_parent'); ?>
                        </a>
                        <?php endif; ?>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo __('created'); ?>
                        <span><?php echo format_date($product_data->created_at); ?></span>
                    </li>
                </ul>
                
                <div class="mt-3">
                    <a href="https://vakoufaris.com/wp-admin/post.php?post=<?php echo $product_data->product_id; ?>&action=edit" target="_blank" class="btn btn-outline-info w-100">
                        <i class="fas fa-external-link-alt"></i> <?php echo __('view_in_woocommerce'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Location Summary -->
        <?php if ($product_data->aisle || $product_data->shelf || $product_data->storage_notes): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-map-marker-alt"></i> <?php echo __('current_location'); ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if ($product_data->aisle): ?>
                <p><strong><?php echo __('aisle'); ?>:</strong> <?php echo htmlspecialchars($product_data->aisle); ?></p>
                <?php endif; ?>
                
                <?php if ($product_data->shelf): ?>
                <p><strong><?php echo __('shelf'); ?>:</strong> <?php echo htmlspecialchars($product_data->shelf); ?></p>
                <?php endif; ?>
                
                <?php if ($product_data->storage_notes): ?>
                <p><strong><?php echo __('storage_notes'); ?>:</strong><br><?php echo nl2br(htmlspecialchars($product_data->storage_notes)); ?></p>
                <?php endif; ?>
                
                <?php if ($product_data->date_of_entry): ?>
                <p><strong><?php echo __('entry_date'); ?>:</strong> <?php echo format_date($product_data->date_of_entry); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include 'templates/footer.php';
?>