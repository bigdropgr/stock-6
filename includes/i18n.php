<?php
/**
 * Fixed Internationalization Helper
 * 
 * Resolves encoding issues and adds missing translations
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set proper encoding
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Get current language
function getCurrentLanguage() {
    // Priority: Session > Default config > English
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    
    if (defined('DEFAULT_LANGUAGE')) {
        return DEFAULT_LANGUAGE;
    }
    
    return 'en';
}

// Set language
function setLanguage($language) {
    if (in_array($language, ['en', 'el'])) {
        $_SESSION['language'] = $language;
    }
}

// Translation function
function __($key, $default = null) {
    static $translations = null;
    
    if ($translations === null) {
        $translations = [
            'en' => [
                // Common
                'dashboard' => 'Dashboard',
                'search' => 'Search',
                'sync' => 'Sync',
                'profile' => 'Profile',
                'change_password' => 'Change Password',
                'logout' => 'Logout',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'delete' => 'Delete',
                'edit' => 'Edit',
                'back' => 'Back',
                'loading' => 'Loading...',
                'success' => 'Success',
                'error' => 'Error',
                'view_all' => 'View All',
                'action' => 'Action',
                'actions' => 'Actions',
                
                // Auth
                'login' => 'Login',
                'username' => 'Username',
                'password' => 'Password',
                'invalid_credentials' => 'Invalid username or password',
                
                // Dashboard
                'total_products' => 'Total Products',
                'retail_inventory_value' => 'Retail Inventory Value',
                'wholesale_inventory_value' => 'Wholesale Inventory Value',
                'low_stock_items' => 'Low Stock Items',
                'last_sync' => 'Last Sync',
                'never' => 'Never',
                'refresh' => 'Refresh',
                'recently_updated_products' => 'Recently Updated Products',
                'products_low_in_stock' => 'Products Low in Stock',
                'recently_added_to_online_shop' => 'Recently Added to Online Shop',
                'top_selling_products' => 'Top Selling Products',
                'products_low_in_stock_online' => 'Products Low in Stock in Online Shop',
                'no_recently_added_products' => 'No recently added products in online shop.',
                'no_sales_data_available' => 'No sales data available.',
                'no_products_with_low_stock_online' => 'No products with low stock in online shop.',
                
                // Product table headers
                'product' => 'Product',
                'sku' => 'SKU',
                'stock' => 'Stock',
                'updated' => 'Updated',
                'added' => 'Added',
                'price' => 'Price',
                'sales' => 'Sales',
                
                // Time
                'just_now' => 'just now',
                'minutes_ago' => 'minutes ago',
                'hours_ago' => 'hours ago',
                'days_ago' => 'days ago',
                'hour' => 'hour',
                'hours' => 'hours',
                'ago' => 'ago',
                
                // Search
                'product_search' => 'Product Search',
                'search_placeholder' => 'Search by product name, SKU, aisle, or shelf...',
                'clear' => 'Clear',
                'low_stock' => 'Low Stock',
                'no_products_found' => 'No products found.',
                'all_products' => 'All Products',
                'retail' => 'Retail',
                'wholesale' => 'Wholesale',
                'update_stock' => 'Update Stock',
                
                // Product
                'edit_product' => 'Edit Product',
                'product_title' => 'Product Title',
                'category' => 'Category',
                'retail_price' => 'Retail Price',
                'wholesale_price' => 'Wholesale Price',
                'stock_quantity' => 'Stock Quantity',
                'low_stock_threshold' => 'Low Stock Threshold',
                'notes' => 'Notes',
                'last_updated' => 'Last Updated',
                'product_image' => 'Product Image',
                'no_image_available' => 'No image available',
                'product_information' => 'Product Information',
                
                // Profile
                'profile_information' => 'Profile Information',
                'full_name' => 'Full Name',
                'email_address' => 'Email Address',
                'member_since' => 'Member Since',
                'last_login' => 'Last Login',
                'update_profile' => 'Update Profile',
                'name_required' => 'Name is required',
                'valid_email_required' => 'Please enter a valid email address',
                'profile_updated_successfully' => 'Profile updated successfully',
                'failed_to_update_profile' => 'Failed to update profile',
                'back_to_dashboard' => 'Back to Dashboard',
                'role' => 'Role',
                'username_cannot_be_changed' => 'Username cannot be changed',
                
                // Messages
                'product_updated_successfully' => 'Product updated successfully',
                'stock_updated_successfully' => 'Stock updated successfully',
                'please_enter_valid_stock' => 'Please enter a valid stock quantity (0 or greater)',
                
                // Language names
                'language_english' => 'English',
                'language_greek' => 'Greek',
            ],
            
            'el' => [
                // Common
                'dashboard' => 'Πίνακας Ελέγχου',
                'search' => 'Αναζήτηση',
                'sync' => 'Συγχρονισμός',
                'profile' => 'Προφίλ',
                'change_password' => 'Αλλαγή Κωδικού',
                'logout' => 'Αποσύνδεση',
                'save' => 'Αποθήκευση',
                'cancel' => 'Ακύρωση',
                'delete' => 'Διαγραφή',
                'edit' => 'Επεξεργασία',
                'back' => 'Πίσω',
                'loading' => 'Φόρτωση...',
                'success' => 'Επιτυχία',
                'error' => 'Σφάλμα',
                'view_all' => 'Προβολή Όλων',
                'action' => 'Ενέργεια',
                'actions' => 'Ενέργειες',
                
                // Auth
                'login' => 'Σύνδεση',
                'username' => 'Όνομα Χρήστη',
                'password' => 'Κωδικός Πρόσβασης',
                'invalid_credentials' => 'Λάθος όνομα χρήστη ή κωδικός πρόσβασης',
                
                // Dashboard
                'total_products' => 'Σύνολο Προϊόντων',
                'retail_inventory_value' => 'Αξία Λιανικού Αποθέματος',
                'wholesale_inventory_value' => 'Αξία Χονδρικού Αποθέματος',
                'low_stock_items' => 'Προϊόντα με Χαμηλό Απόθεμα',
                'last_sync' => 'Τελευταίος Συγχρονισμός',
                'never' => 'Ποτέ',
                'refresh' => 'Ανανέωση',
                'recently_updated_products' => 'Πρόσφατα Ενημερωμένα Προϊόντα',
                'products_low_in_stock' => 'Προϊόντα με Χαμηλό Απόθεμα',
                'recently_added_to_online_shop' => 'Πρόσφατα Προστεθέντα στο Ηλεκτρονικό Κατάστημα',
                'top_selling_products' => 'Κορυφαία Προϊόντα Πωλήσεων',
                'products_low_in_stock_online' => 'Προϊόντα με Χαμηλό Απόθεμα στο Ηλεκτρονικό Κατάστημα',
                'no_recently_added_products' => 'Δεν υπάρχουν πρόσφατα προστεθέντα προϊόντα στο ηλεκτρονικό κατάστημα.',
                'no_sales_data_available' => 'Δεν υπάρχουν διαθέσιμα δεδομένα πωλήσεων.',
                'no_products_with_low_stock_online' => 'Δεν υπάρχουν προϊόντα με χαμηλό απόθεμα στο ηλεκτρονικό κατάστημα.',
                
                // Product table headers
                'product' => 'Προϊόν',
                'sku' => 'SKU',
                'stock' => 'Απόθεμα',
                'updated' => 'Ενημερώθηκε',
                'added' => 'Προστέθηκε',
                'price' => 'Τιμή',
                'sales' => 'Πωλήσεις',
                
                // Time
                'just_now' => 'μόλις τώρα',
                'minutes_ago' => 'λεπτά πριν',
                'hours_ago' => 'ώρες πριν',
                'days_ago' => 'ημέρες πριν',
                'hour' => 'ώρα',
                'hours' => 'ώρες',
                'ago' => 'πριν',
                
                // Search
                'product_search' => 'Αναζήτηση Προϊόντων',
                'search_placeholder' => 'Αναζήτηση ανά όνομα προϊόντος, SKU, διάδρομο ή ράφι...',
                'clear' => 'Καθαρισμός',
                'low_stock' => 'Χαμηλό Απόθεμα',
                'no_products_found' => 'Δεν βρέθηκαν προϊόντα.',
                'all_products' => 'Όλα τα Προϊόντα',
                'retail' => 'Λιανική',
                'wholesale' => 'Χονδρική',
                'update_stock' => 'Ενημέρωση Αποθέματος',
                
                // Product
                'edit_product' => 'Επεξεργασία Προϊόντος',
                'product_title' => 'Τίτλος Προϊόντος',
                'category' => 'Κατηγορία',
                'retail_price' => 'Λιανική Τιμή',
                'wholesale_price' => 'Χονδρική Τιμή',
                'stock_quantity' => 'Ποσότητα Αποθέματος',
                'low_stock_threshold' => 'Όριο Χαμηλού Αποθέματος',
                'notes' => 'Σημειώσεις',
                'last_updated' => 'Τελευταία Ενημέρωση',
                'product_image' => 'Εικόνα Προϊόντος',
                'no_image_available' => 'Δεν υπάρχει διαθέσιμη εικόνα',
                'product_information' => 'Πληροφορίες Προϊόντος',
                
                // Profile
                'profile_information' => 'Πληροφορίες Προφίλ',
                'full_name' => 'Πλήρες Όνομα',
                'email_address' => 'Διεύθυνση Email',
                'member_since' => 'Μέλος από',
                'last_login' => 'Τελευταία Σύνδεση',
                'update_profile' => 'Ενημέρωση Προφίλ',
                'name_required' => 'Το όνομα είναι υποχρεωτικό',
                'valid_email_required' => 'Παρακαλώ εισάγετε μια έγκυρη διεύθυνση email',
                'profile_updated_successfully' => 'Το προφίλ ενημερώθηκε επιτυχώς',
                'failed_to_update_profile' => 'Αποτυχία ενημέρωσης προφίλ',
                'back_to_dashboard' => 'Επιστροφή στον Πίνακα Ελέγχου',
                'role' => 'Ρόλος',
                'username_cannot_be_changed' => 'Το όνομα χρήστη δεν μπορεί να αλλάξει',
                
                // Messages
                'product_updated_successfully' => 'Το προϊόν ενημερώθηκε επιτυχώς',
                'stock_updated_successfully' => 'Το απόθεμα ενημερώθηκε επιτυχώς',
                'please_enter_valid_stock' => 'Παρακαλώ εισάγετε έγκυρη ποσότητα αποθέματος (0 ή μεγαλύτερη)',
                
                // Language names  
                'language_english' => 'Αγγλικά',
                'language_greek' => 'Ελληνικά',
            ]
        ];
    }
    
    $lang = getCurrentLanguage();
    
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    
    // Fallback to English
    if ($lang !== 'en' && isset($translations['en'][$key])) {
        return $translations['en'][$key];
    }
    
    // Return default or key itself
    return $default !== null ? $default : $key;
}