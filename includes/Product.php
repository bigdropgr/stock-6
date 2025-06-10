<?php
/**
 * Product Class
 * 
 * Updated with wholesale price and location fields
 */

class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get a product by ID
     */
    public function getById($id) {
        $id = (int)$id;
        $sql = "SELECT * FROM physical_inventory WHERE id = $id";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_object();
        }
        
        return null;
    }
    
    /**
     * Get a product by WooCommerce product ID
     */
    public function getByProductId($product_id) {
        $product_id = (int)$product_id;
        $sql = "SELECT * FROM physical_inventory WHERE product_id = $product_id";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_object();
        }
        
        return null;
    }
    
    /**
     * Search products
     */
    public function search($term, $limit = 20) {
        $term = $this->db->escapeString($term);
        $limit = (int)$limit;
        
        $sql = "SELECT * FROM physical_inventory 
                WHERE (title LIKE '%$term%' OR sku LIKE '%$term%' OR aisle LIKE '%$term%' OR shelf LIKE '%$term%') 
                AND product_type IN ('simple', 'variable')
                ORDER BY title ASC 
                LIMIT $limit";
        
        $result = $this->db->query($sql);
        $products = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Get all products (excluding variations)
     */
    public function getAll($limit = 50, $offset = 0) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT * FROM physical_inventory 
                WHERE product_type IN ('simple', 'variable')
                ORDER BY title ASC 
                LIMIT $limit OFFSET $offset";
        
        $result = $this->db->query($sql);
        $products = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Count total products (excluding variations)
     */
    public function countAll() {
        $sql = "SELECT COUNT(*) as total FROM physical_inventory WHERE product_type IN ('simple', 'variable')";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_object();
            return (int)$row->total;
        }
        
        return 0;
    }
    
    /**
     * Add a new product with wholesale price and location support
     */
    public function add($data) {
        $product_id = (int)$data['product_id'];
        $title = $this->db->escapeString($data['title']);
        $sku = $this->db->escapeString($data['sku']);
        $category = $this->db->escapeString($data['category']);
        $price = (float)$data['price'];
        $wholesale_price = isset($data['wholesale_price']) ? (float)$data['wholesale_price'] : 0.0;
        $stock = isset($data['stock']) ? (int)$data['stock'] : 0;
        $image_url = $this->db->escapeString($data['image_url']);
        $now = date('Y-m-d H:i:s');
        $low_stock_threshold = isset($data['low_stock_threshold']) ? (int)$data['low_stock_threshold'] : DEFAULT_LOW_STOCK_THRESHOLD;
        $notes = isset($data['notes']) ? $this->db->escapeString($data['notes']) : '';
        
        // Location fields
        $aisle = isset($data['aisle']) ? $this->db->escapeString($data['aisle']) : '';
        $shelf = isset($data['shelf']) ? $this->db->escapeString($data['shelf']) : '';
        $storage_notes = isset($data['storage_notes']) ? $this->db->escapeString($data['storage_notes']) : '';
        $date_of_entry = isset($data['date_of_entry']) ? $data['date_of_entry'] : null;
        
        // Product type and parent handling
        $product_type = isset($data['product_type']) ? $this->db->escapeString($data['product_type']) : 'simple';
        
        $parent_id = null;
        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            $parent_id = (int)$data['parent_id'];
            
            $check_parent_sql = "SELECT id FROM physical_inventory WHERE id = $parent_id AND product_type = 'variable'";
            $check_result = $this->db->query($check_parent_sql);
            
            if (!$check_result || $check_result->num_rows === 0) {
                error_log("Warning: Invalid parent_id $parent_id for variation $product_id.");
                $find_parent_sql = "SELECT id FROM physical_inventory WHERE product_id = $parent_id AND product_type = 'variable'";
                $find_result = $this->db->query($find_parent_sql);
                
                if ($find_result && $find_result->num_rows > 0) {
                    $correct_parent = $find_result->fetch_object();
                    $parent_id = $correct_parent->id;
                } else {
                    $parent_id = null;
                }
            }
        }
        
        $variation_attributes = null;
        if (isset($data['variation_attributes']) && $data['variation_attributes'] !== null) {
            if (is_array($data['variation_attributes']) || is_object($data['variation_attributes'])) {
                $variation_attributes = $this->db->escapeString(json_encode($data['variation_attributes']));
            } else {
                $variation_attributes = $this->db->escapeString($data['variation_attributes']);
            }
        }
        
        $is_low_stock = ($stock <= $low_stock_threshold) ? 1 : 0;
        
        // Build the SQL query with new fields
        $sql = "INSERT INTO physical_inventory 
                (product_id, parent_id, product_type, variation_attributes, title, sku, category, price, wholesale_price, stock, image_url, last_updated, created_at, is_low_stock, low_stock_threshold, notes, aisle, shelf, storage_notes, date_of_entry) 
                VALUES 
                ($product_id, " . ($parent_id ? $parent_id : 'NULL') . ", '$product_type', " . ($variation_attributes ? "'$variation_attributes'" : 'NULL') . ", '$title', '$sku', '$category', $price, $wholesale_price, $stock, '$image_url', '$now', '$now', $is_low_stock, $low_stock_threshold, '$notes', " . ($aisle ? "'$aisle'" : 'NULL') . ", " . ($shelf ? "'$shelf'" : 'NULL') . ", " . ($storage_notes ? "'$storage_notes'" : 'NULL') . ", " . ($date_of_entry ? "'$date_of_entry'" : 'NULL') . ")";
        
        if ($this->db->query($sql)) {
            $new_id = $this->db->lastInsertId();
            
            if (defined('APP_ENV') && APP_ENV === 'development') {
                error_log("Successfully added product: ID=$new_id, WC_ID=$product_id, Type=$product_type, Parent_ID=" . ($parent_id ? $parent_id : 'NULL'));
            }
            
            return $new_id;
        } else {
            error_log("Failed to insert product: " . $this->db->getError());
            return false;
        }
    }
    
    /**
     * Update an existing product with wholesale price and location support
     */
    public function update($id, $data) {
        $id = (int)$id;
        $updates = [];
        
        if (isset($data['title'])) {
            $title = $this->db->escapeString($data['title']);
            $updates[] = "title = '$title'";
        }
        
        if (isset($data['sku'])) {
            $sku = $this->db->escapeString($data['sku']);
            $updates[] = "sku = '$sku'";
        }
        
        if (isset($data['category'])) {
            $category = $this->db->escapeString($data['category']);
            $updates[] = "category = '$category'";
        }
        
        if (isset($data['price'])) {
            $price = (float)$data['price'];
            $updates[] = "price = $price";
        }
        
        if (isset($data['wholesale_price'])) {
            $wholesale_price = (float)$data['wholesale_price'];
            $updates[] = "wholesale_price = $wholesale_price";
        }
        
        if (isset($data['image_url'])) {
            $image_url = $this->db->escapeString($data['image_url']);
            $updates[] = "image_url = '$image_url'";
        }
        
        if (isset($data['notes'])) {
            $notes = $this->db->escapeString($data['notes']);
            $updates[] = "notes = '$notes'";
        }
        
        // Location fields
        if (isset($data['aisle'])) {
            $aisle = $this->db->escapeString($data['aisle']);
            $updates[] = "aisle = " . ($aisle ? "'$aisle'" : 'NULL');
        }
        
        if (isset($data['shelf'])) {
            $shelf = $this->db->escapeString($data['shelf']);
            $updates[] = "shelf = " . ($shelf ? "'$shelf'" : 'NULL');
        }
        
        if (isset($data['storage_notes'])) {
            $storage_notes = $this->db->escapeString($data['storage_notes']);
            $updates[] = "storage_notes = " . ($storage_notes ? "'$storage_notes'" : 'NULL');
        }
        
        if (isset($data['date_of_entry'])) {
            $date_of_entry = $data['date_of_entry'];
            $updates[] = "date_of_entry = " . ($date_of_entry ? "'$date_of_entry'" : 'NULL');
        }
        
        if (isset($data['variation_attributes'])) {
            $variation_attributes = json_encode($data['variation_attributes']);
            $updates[] = "variation_attributes = '$variation_attributes'";
        }
        
        // Handle stock and low stock threshold updates
        if (isset($data['stock']) || isset($data['low_stock_threshold'])) {
            $current_product = $this->getById($id);
            if ($current_product) {
                $stock = isset($data['stock']) ? (int)$data['stock'] : $current_product->stock;
                $low_stock_threshold = isset($data['low_stock_threshold']) ? (int)$data['low_stock_threshold'] : $current_product->low_stock_threshold;
                
                if (isset($data['stock'])) {
                    $updates[] = "stock = $stock";
                }
                
                if (isset($data['low_stock_threshold'])) {
                    $updates[] = "low_stock_threshold = $low_stock_threshold";
                }
                
                $is_low_stock = ($stock <= $low_stock_threshold) ? 1 : 0;
                $updates[] = "is_low_stock = $is_low_stock";
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $updates[] = "last_updated = '$now'";
        
        $sql = "UPDATE physical_inventory SET " . implode(', ', $updates) . " WHERE id = $id";
        
        return $this->db->query($sql);
    }
    
    /**
     * Update product stock
     */
    public function updateStock($id, $stock) {
        return $this->update($id, ['stock' => (int)$stock]);
    }
    
    /**
     * Get recently updated products
     */
    public function getRecentlyUpdated($limit = 10) {
        $limit = (int)$limit;
        
        $sql = "SELECT * FROM physical_inventory 
                WHERE product_type IN ('simple', 'variable')
                ORDER BY last_updated DESC 
                LIMIT $limit";
        
        $result = $this->db->query($sql);
        $products = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Get products with low stock
     */
    public function getLowStock($limit = 10) {
        $limit = (int)$limit;
        
        $sql = "SELECT * FROM physical_inventory 
                WHERE is_low_stock = 1 AND stock > 0 
                ORDER BY stock ASC 
                LIMIT $limit";
        
        $result = $this->db->query($sql);
        $products = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Get total inventory value (retail)
     */
    public function getTotalValue() {
        $sql = "SELECT SUM(price * stock) as total_value FROM physical_inventory";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_object();
            return (float)$row->total_value;
        }
        
        return 0.0;
    }
    
    /**
     * Get total wholesale inventory value
     */
    public function getTotalWholesaleValue() {
        $sql = "SELECT SUM(wholesale_price * stock) as total_wholesale_value FROM physical_inventory";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_object();
            return (float)$row->total_wholesale_value;
        }
        
        return 0.0;
    }
    
    /**
     * Delete a product
     */
    public function delete($id) {
        $id = (int)$id;
        
        $product = $this->getById($id);
        if ($product && $product->product_type === 'variable') {
            $this->db->query("DELETE FROM physical_inventory WHERE parent_id = $id");
        }
        
        $sql = "DELETE FROM physical_inventory WHERE id = $id";
        return $this->db->query($sql);
    }
    
    /**
     * Get all variations for a parent product
     */
    public function getVariations($parent_product_id) {
        $parent_product_id = (int)$parent_product_id;
        
        $parent_record = $this->getByProductId($parent_product_id);
        if ($parent_record && $parent_record->product_type === 'variable') {
            $sql = "SELECT * FROM physical_inventory 
                    WHERE parent_id = {$parent_record->id} AND product_type = 'variation'
                    ORDER BY title ASC";
        } else {
            $sql = "SELECT * FROM physical_inventory 
                    WHERE notes LIKE '%Variation of product ID: $parent_product_id%' 
                    ORDER BY title ASC";
        }
        
        $result = $this->db->query($sql);
        $variations = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                if ($row->variation_attributes) {
                    $row->attributes = json_decode($row->variation_attributes, true);
                }
                $variations[] = $row;
            }
        }
        
        return $variations;
    }
    
    /**
     * Delete a variation
     */
    public function deleteVariation($id, $variation_id) {
        $this->db->beginTransaction();
        
        try {
            $now = date('Y-m-d H:i:s');
            $variation_id = (int)$variation_id;
            
            $sql = "INSERT INTO deleted_variations (variation_id, deleted_at)
                    VALUES ($variation_id, '$now')
                    ON DUPLICATE KEY UPDATE deleted_at = '$now'";
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to mark variation as deleted');
            }
            
            $id = (int)$id;
            $sql = "DELETE FROM physical_inventory WHERE id = $id AND product_type = 'variation'";
            
            if (!$this->db->query($sql)) {
                throw new Exception('Failed to delete variation from inventory');
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Check if a product is a variable product
     */
    public function isVariableProduct($product) {
        return (isset($product->product_type) && $product->product_type === 'variable');
    }
    
    /**
     * Check if a product is a variation
     */
    public function isVariation($product) {
        return (isset($product->product_type) && $product->product_type === 'variation');
    }
    
    /**
     * Get all parent variable products
     */
    public function getVariableProducts($limit = 50, $offset = 0) {
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT * FROM physical_inventory 
                WHERE product_type = 'variable' 
                ORDER BY title ASC 
                LIMIT $limit OFFSET $offset";
        
        $result = $this->db->query($sql);
        $products = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Count variable products
     */
    public function countVariableProducts() {
        $sql = "SELECT COUNT(*) as count FROM physical_inventory WHERE product_type = 'variable'";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return (int)$result->fetch_object()->count;
        }
        
        return 0;
    }
    
    /**
     * Get total stock for a variable product
     */
    public function getVariableProductTotalStock($variable_product_id) {
        $variable_product_id = (int)$variable_product_id;
        
        $sql = "SELECT SUM(stock) as total_stock FROM physical_inventory 
                WHERE parent_id = $variable_product_id AND product_type = 'variation'";
        
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_object();
            return (int)$row->total_stock;
        }
        
        return 0;
    }
}