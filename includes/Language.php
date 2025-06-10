<?php
/**
 * Internationalization (i18n) Class
 * 
 * Handles multi-language support for the application
 */

class Language {
    private static $instance = null;
    private $language = 'en';
    private $translations = [];
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->initializeLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Initialize the current language
     */
    private function initializeLanguage() {
        // Priority: User preference > Default language > English
        if (isset($_SESSION['user_language'])) {
            $this->language = $_SESSION['user_language'];
        } elseif (defined('DEFAULT_LANGUAGE')) {
            $this->language = DEFAULT_LANGUAGE;
        } else {
            $this->language = 'en';
        }
        
        // Validate language
        if (!in_array($this->language, ['en', 'el'])) {
            $this->language = 'en';
        }
    }
    
    /**
     * Load translations for current language
     */
    private function loadTranslations() {
        $this->translations = [
            'en' => [
                // Navigation
                'dashboard' => 'Dashboard',
                'search' => 'Search',
                'sync' => 'Sync',
                'profile' => 'Profile',
                'change_password' => 'Change Password',
                'logout' => 'Logout',
                
                // Common
                'save' => 'Save',
                'cancel' => 'Cancel',
                'delete' => 'Delete',
                'edit' => 'Edit',
                'add' => 'Add',
                'update' => 'Update',
                'back' => 'Back',
                'continue' => 'Continue',
                'yes' => 'Yes',
                'no' => 'No',
                'loading' => 'Loading...',
                'success' => 'Success',
                'error' => 'Error',
                'warning' => 'Warning',
                'info' => 'Information',
                
                // Authentication
                'login' => 'Login',
                'username' => 'Username',
                'password' => 'Password',
                'login_title' => 'Physical Store Inventory Management',
                'invalid_credentials' => 'Invalid username or password',
                'session_expired' => 'Session expired. Please login again.',
                
                // Dashboard
                'total_products' => 'Total Products',
                'retail_inventory_value' => 'Retail Inventory Value',
                'wholesale_inventory_value' => 'Wholesale Inventory Value',
                'low_stock_items' => 'Low Stock Items',
                'last_sync' => 'Last Sync',
                'recently_updated_products' => 'Recently Updated Products',
                'products_low_in_stock' => 'Products Low in Stock',
                'recently_added_to_online_shop' => 'Recently Added to Online Shop',
                'top_selling_products' => 'Top Selling Products',
                'products_low_in_stock_online' => 'Products Low in Stock in Online Shop',
                'never' => 'Never',
                'refresh' => 'Refresh',
                
                // Search
                'product_search' => 'Product Search',
                'search_placeholder' => 'Search by product name, SKU, aisle, or shelf...',
                'search_help' => 'You can search by product name, SKU, aisle location, or shelf position.',
                'clear' => 'Clear',
                'low_stock' => 'Low Stock',
                'no_products_found' => 'No products found.',
                'try_different_search' => 'Try a different search term.',
                'all_products' => 'All Products',
                'retail' => 'Retail',
                'wholesale' => 'Wholesale',
                'stock' => 'Stock',
                'aisle' => 'Aisle',
                'shelf' => 'Shelf',
                'update_stock' => 'Update Stock',
                
                // Product
                'edit_product' => 'Edit Product',
                'product_title' => 'Product Title',
                'sku' => 'SKU',
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
                'product_id' => 'Product ID',
                'woocommerce_id' => 'WooCommerce ID',
                'product_type' => 'Product Type',
                'parent_product' => 'Parent Product',
                'created' => 'Created',
                'view_in_woocommerce' => 'View in WooCommerce',
                'location_in_physical_store' => 'Location in Physical Store',
                'current_location' => 'Current Location',
                'storage_notes' => 'Storage Notes',
                'date_of_entry' => 'Date of Entry',
                'product_variations' => 'Product Variations',
                'variation' => 'Variation',
                'actions' => 'Actions',
                'total_stock' => 'Total Stock',
                'no_variations_found' => 'No variations found for this product.',
                'variable_product' => 'Variable Product',
                'product_variation' => 'Product Variation',
                'view_parent' => 'View Parent',
                'entry_date' => 'Entry Date',
                
                // Profile
                'profile_information' => 'Profile Information',
                'username_cannot_be_changed' => 'Username cannot be changed',
                'role' => 'Role',
                'full_name' => 'Full Name',
                'email_address' => 'Email Address',
                'member_since' => 'Member Since',
                'last_login' => 'Last Login',
                'update_profile' => 'Update Profile',
                'name_required' => 'Name is required',
                'valid_email_required' => 'Please enter a valid email address',
                'profile_updated_successfully' => 'Profile updated successfully',
                'failed_to_update_profile' => 'Failed to update profile',
                
                // Sync
                'synchronize_with_woocommerce' => 'Synchronize with WooCommerce',
                'sync_products' => 'Sync Products',
                'sync_products_description' => 'Synchronize your physical inventory with products from your WooCommerce store. This will import any new products from WooCommerce and update existing product information.',
                'stock_levels_not_overwritten' => 'Product stock levels in your physical inventory will NOT be overwritten during synchronization.',
                'sync_products_now' => 'Sync Products Now',
                'full_sync' => 'Full sync (update all product data, not just new products)',
                'scheduled_sync' => 'Scheduled Sync',
                'automatic_sync_description' => 'The system is configured to automatically synchronize with WooCommerce every Monday.',
                'automatic_sync_note' => 'Automatic sync will only import newly added products, not update existing ones.',
                'next_scheduled_sync' => 'Next scheduled sync:',
                'sync_history' => 'Sync History',
                'date' => 'Date',
                'added' => 'Added',
                'updated' => 'Updated',
                'status' => 'Status',
                'no_sync_logs' => 'No sync logs available.',
                'sync_options' => 'Sync Options',
                'regular_sync' => 'Regular Sync',
                'regular_sync_description' => 'Imports new products and updates basic information for existing products.',
                'full_sync_description' => 'Updates all product data from WooCommerce, but preserves your physical inventory stock levels.',
                'large_catalog_warning' => 'For large catalogs, sync operations will process products in batches to avoid timeouts.',
                
                // Messages
                'product_not_found' => 'Product not found.',
                'product_updated_successfully' => 'Product updated successfully.',
                'failed_to_update_product' => 'Failed to update product.',
                'stock_updated_successfully' => 'Stock updated successfully.',
                'error_updating_stock' => 'Error updating stock',
                'please_enter_valid_stock' => 'Please enter a valid stock quantity (0 or greater)',
                'session_expired_refresh' => 'Session expired. Please refresh the page and try again.',
                'access_denied' => 'Access denied. Please check your permissions.',
                'server_error' => 'Server error. Please try again later.',
                'request_timed_out' => 'Request timed out. Please try again.',
                'error_communicating_server' => 'Error communicating with server. Please try again.',
                
                // Time
                'just_now' => 'just now',
                'minutes_ago' => 'minutes ago',
                'hours_ago' => 'hours ago',
                'days_ago' => 'days ago',
                'second' => 'second',
                'minute' => 'minute',
                'hour' => 'hour',
                'day' => 'day',
                'month' => 'month',
                'year' => 'year',
                
                // Location
                'aisle_placeholder' => 'e.g., A1, B2, Main',
                'aisle_help' => 'Which aisle or section is this product located in?',
                'shelf_placeholder' => 'e.g., Top, Middle, 3rd',
                'shelf_help' => 'Which shelf level or position?',
                'storage_notes_placeholder' => 'Additional storage or location notes...',
                'storage_notes_help' => 'Any additional notes about storage requirements or location details.',
                'date_of_entry_help' => 'When was this product first received/entered into inventory?',
                'update_location_information' => 'Update Location Information',
                
                // Change Password
                'current_password' => 'Current Password',
                'new_password' => 'New Password',
                'confirm_new_password' => 'Confirm New Password',
                'password_requirements' => 'Password must be at least 8 characters long.',
                'all_fields_required' => 'All fields are required',
                'passwords_do_not_match' => 'New password and confirmation do not match',
                'password_too_short' => 'New password must be at least 8 characters long',
                'password_changed_successfully' => 'Password changed successfully',
                'failed_to_change_password' => 'Failed to change password. Current password may be incorrect.',
                'using_default_password' => 'You are using the default password. Please change it for security reasons.',
                
                // Stock Status
                'in_stock' => 'In Stock',
                'out_of_stock' => 'Out of Stock',
                'low_stock_warning' => 'Low Stock',
                'current_physical_inventory' => 'Current physical inventory stock.',
                'cost_price_help' => 'Cost price for this product.',
                'low_stock_threshold_help' => 'Products with stock below this will be marked as low stock.',
                'inventory_notes_help' => 'Add any notes about this product\'s inventory.',
                
                // Languages
                'language' => 'Language',
                'english' => 'English',
                'greek' => 'Greek',
            ],
            
            'el' => [
                // Navigation
                'dashboard' => 'Πίνακας Ελέγχου',
                'search' => 'Αναζήτηση',
                'sync' => 'Συγχρονισμός',
                'profile' => 'Προφίλ',
                'change_password' => 'Αλλαγή Κωδικού',
                'logout' => 'Αποσύνδεση',
                
                // Common
                'save' => 'Αποθήκευση',
                'cancel' => 'Ακύρωση',
                'delete' => 'Διαγραφή',
                'edit' => 'Επεξεργασία',
                'add' => 'Προσθήκη',
                'update' => 'Ενημέρωση',
                'back' => 'Πίσω',
                'continue' => 'Συνέχεια',
                'yes' => 'Ναι',
                'no' => 'Όχι',
                'loading' => 'Φόρτωση...',
                'success' => 'Επιτυχία',
                'error' => 'Σφάλμα',
                'warning' => 'Προειδοποίηση',
                'info' => 'Πληροφορία',
                
                // Authentication
                'login' => 'Σύνδεση',
                'username' => 'Όνομα Χρήστη',
                'password' => 'Κωδικός Πρόσβασης',
                'login_title' => 'Διαχείριση Αποθέματος Φυσικού Καταστήματος',
                'invalid_credentials' => 'Λάθος όνομα χρήστη ή κωδικός πρόσβασης',
                'session_expired' => 'Η συνεδρία έληξε. Παρακαλώ συνδεθείτε ξανά.',
                
                // Dashboard
                'total_products' => 'Σύνολο Προϊόντων',
                'retail_inventory_value' => 'Αξία Λιανικού Αποθέματος',
                'wholesale_inventory_value' => 'Αξία Χονδρικού Αποθέματος',
                'low_stock_items' => 'Προϊόντα με Χαμηλό Απόθεμα',
                'last_sync' => 'Τελευταίος Συγχρονισμός',
                'recently_updated_products' => 'Πρόσφατα Ενημερωμένα Προϊόντα',
                'products_low_in_stock' => 'Προϊόντα με Χαμηλό Απόθεμα',
                'recently_added_to_online_shop' => 'Πρόσφατα Προστεθέντα στο Ηλεκτρονικό Κατάστημα',
                'top_selling_products' => 'Κορυφαία Προϊόντα Πωλήσεων',
                'products_low_in_stock_online' => 'Προϊόντα με Χαμηλό Απόθεμα στο Ηλεκτρονικό Κατάστημα',
                'never' => 'Ποτέ',
                'refresh' => 'Ανανέωση',
                
                // Search
                'product_search' => 'Αναζήτηση Προϊόντων',
                'search_placeholder' => 'Αναζήτηση ανά όνομα προϊόντος, SKU, διάδρομο ή ράφι...',
                'search_help' => 'Μπορείτε να αναζητήσετε ανά όνομα προϊόντος, SKU, τοποθεσία διαδρόμου ή θέση ραφιού.',
                'clear' => 'Καθαρισμός',
                'low_stock' => 'Χαμηλό Απόθεμα',
                'no_products_found' => 'Δεν βρέθηκαν προϊόντα.',
                'try_different_search' => 'Δοκιμάστε διαφορετικό όρο αναζήτησης.',
                'all_products' => 'Όλα τα Προϊόντα',
                'retail' => 'Λιανική',
                'wholesale' => 'Χονδρική',
                'stock' => 'Απόθεμα',
                'aisle' => 'Διάδρομος',
                'shelf' => 'Ράφι',
                'update_stock' => 'Ενημέρωση Αποθέματος',
                
                // Product
                'edit_product' => 'Επεξεργασία Προϊόντος',
                'product_title' => 'Τίτλος Προϊόντος',
                'sku' => 'SKU',
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
                'product_id' => 'ID Προϊόντος',
                'woocommerce_id' => 'WooCommerce ID',
                'product_type' => 'Τύπος Προϊόντος',
                'parent_product' => 'Γονικό Προϊόν',
                'created' => 'Δημιουργήθηκε',
                'view_in_woocommerce' => 'Προβολή στο WooCommerce',
                'location_in_physical_store' => 'Τοποθεσία στο Φυσικό Κατάστημα',
                'current_location' => 'Τρέχουσα Τοποθεσία',
                'storage_notes' => 'Σημειώσεις Αποθήκευσης',
                'date_of_entry' => 'Ημερομηνία Εισαγωγής',
                'product_variations' => 'Παραλλαγές Προϊόντος',
                'variation' => 'Παραλλαγή',
                'actions' => 'Ενέργειες',
                'total_stock' => 'Συνολικό Απόθεμα',
                'no_variations_found' => 'Δεν βρέθηκαν παραλλαγές για αυτό το προϊόν.',
                'variable_product' => 'Μεταβλητό Προϊόν',
                'product_variation' => 'Παραλλαγή Προϊόντος',
                'view_parent' => 'Προβολή Γονικού',
                'entry_date' => 'Ημερομηνία Εισαγωγής',
                
                // Profile
                'profile_information' => 'Πληροφορίες Προφίλ',
                'username_cannot_be_changed' => 'Το όνομα χρήστη δεν μπορεί να αλλάξει',
                'role' => 'Ρόλος',
                'full_name' => 'Πλήρες Όνομα',
                'email_address' => 'Διεύθυνση Email',
                'member_since' => 'Μέλος από',
                'last_login' => 'Τελευταία Σύνδεση',
                'update_profile' => 'Ενημέρωση Προφίλ',
                'name_required' => 'Το όνομα είναι υποχρεωτικό',
                'valid_email_required' => 'Παρακαλώ εισάγετε μια έγκυρη διεύθυνση email',
                'profile_updated_successfully' => 'Το προφίλ ενημερώθηκε επιτυχώς',
                'failed_to_update_profile' => 'Αποτυχία ενημέρωσης προφίλ',
                
                // Sync
                'synchronize_with_woocommerce' => 'Συγχρονισμός με WooCommerce',
                'sync_products' => 'Συγχρονισμός Προϊόντων',
                'sync_products_description' => 'Συγχρονίστε το φυσικό σας απόθεμα με προϊόντα από το κατάστημα WooCommerce. Αυτό θα εισάγει τυχόν νέα προϊόντα από το WooCommerce και θα ενημερώσει τις υπάρχουσες πληροφορίες προϊόντων.',
                'stock_levels_not_overwritten' => 'Τα επίπεδα αποθέματος προϊόντων στο φυσικό σας απόθεμα ΔΕΝ θα αντικατασταθούν κατά τον συγχρονισμό.',
                'sync_products_now' => 'Συγχρονισμός Προϊόντων Τώρα',
                'full_sync' => 'Πλήρης συγχρονισμός (ενημέρωση όλων των δεδομένων προϊόντων, όχι μόνο νέων προϊόντων)',
                'scheduled_sync' => 'Προγραμματισμένος Συγχρονισμός',
                'automatic_sync_description' => 'Το σύστημα είναι ρυθμισμένο να συγχρονίζεται αυτόματα με το WooCommerce κάθε Δευτέρα.',
                'automatic_sync_note' => 'Ο αυτόματος συγχρονισμός θα εισάγει μόνο νέα προστεθέντα προϊόντα, όχι ενημέρωση υπαρχόντων.',
                'next_scheduled_sync' => 'Επόμενος προγραμματισμένος συγχρονισμός:',
                'sync_history' => 'Ιστορικό Συγχρονισμού',
                'date' => 'Ημερομηνία',
                'added' => 'Προστέθηκαν',
                'updated' => 'Ενημερώθηκαν',
                'status' => 'Κατάσταση',
                'no_sync_logs' => 'Δεν υπάρχουν διαθέσιμα αρχεία συγχρονισμού.',
                'sync_options' => 'Επιλογές Συγχρονισμού',
                'regular_sync' => 'Κανονικός Συγχρονισμός',
                'regular_sync_description' => 'Εισάγει νέα προϊόντα και ενημερώνει βασικές πληροφορίες για υπάρχοντα προϊόντα.',
                'full_sync_description' => 'Ενημερώνει όλα τα δεδομένα προϊόντων από το WooCommerce, αλλά διατηρεί τα επίπεδα αποθέματος του φυσικού σας αποθέματος.',
                'large_catalog_warning' => 'Για μεγάλους καταλόγους, οι λειτουργίες συγχρονισμού θα επεξεργάζονται προϊόντα σε παρτίδες για να αποφύγουν τα timeouts.',
                
                // Messages
                'product_not_found' => 'Το προϊόν δεν βρέθηκε.',
                'product_updated_successfully' => 'Το προϊόν ενημερώθηκε επιτυχώς.',
                'failed_to_update_product' => 'Αποτυχία ενημέρωσης προϊόντος.',
                'stock_updated_successfully' => 'Το απόθεμα ενημερώθηκε επιτυχώς.',
                'error_updating_stock' => 'Σφάλμα ενημέρωσης αποθέματος',
                'please_enter_valid_stock' => 'Παρακαλώ εισάγετε έγκυρη ποσότητα αποθέματος (0 ή μεγαλύτερη)',
                'session_expired_refresh' => 'Η συνεδρία έληξε. Παρακαλώ ανανεώστε τη σελίδα και δοκιμάστε ξανά.',
                'access_denied' => 'Απαγορεύεται η πρόσβαση. Παρακαλώ ελέγξτε τα δικαιώματά σας.',
                'server_error' => 'Σφάλμα διακομιστή. Παρακαλώ δοκιμάστε ξανά αργότερα.',
                'request_timed_out' => 'Η αίτηση έληξε. Παρακαλώ δοκιμάστε ξανά.',
                'error_communicating_server' => 'Σφάλμα επικοινωνίας με τον διακομιστή. Παρακαλώ δοκιμάστε ξανά.',
                
                // Time
                'just_now' => 'μόλις τώρα',
                'minutes_ago' => 'λεπτά πριν',
                'hours_ago' => 'ώρες πριν',
                'days_ago' => 'ημέρες πριν',
                'second' => 'δευτερόλεπτο',
                'minute' => 'λεπτό',
                'hour' => 'ώρα',
                'day' => 'ημέρα',
                'month' => 'μήνας',
                'year' => 'έτος',
                
                // Location
                'aisle_placeholder' => 'π.χ., A1, B2, Κύριος',
                'aisle_help' => 'Σε ποιον διάδρομο ή τμήμα βρίσκεται αυτό το προϊόν;',
                'shelf_placeholder' => 'π.χ., Πάνω, Μέσο, 3ο',
                'shelf_help' => 'Ποιο επίπεδο ή θέση ραφιού;',
                'storage_notes_placeholder' => 'Επιπλέον σημειώσεις αποθήκευσης ή τοποθεσίας...',
                'storage_notes_help' => 'Τυχόν επιπλέον σημειώσεις σχετικά με τις απαιτήσεις αποθήκευσης ή λεπτομέρειες τοποθεσίας.',
                'date_of_entry_help' => 'Πότε παραλήφθηκε/εισήχθη για πρώτη φορά αυτό το προϊόν στο απόθεμα;',
                'update_location_information' => 'Ενημέρωση Πληροφοριών Τοποθεσίας',
                
                // Change Password
                'current_password' => 'Τρέχων Κωδικός Πρόσβασης',
                'new_password' => 'Νέος Κωδικός Πρόσβασης',
                'confirm_new_password' => 'Επιβεβαίωση Νέου Κωδικού',
                'password_requirements' => 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες.',
                'all_fields_required' => 'Όλα τα πεδία είναι υποχρεωτικά',
                'passwords_do_not_match' => 'Ο νέος κωδικός πρόσβασης και η επιβεβαίωση δεν ταιριάζουν',
                'password_too_short' => 'Ο νέος κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες',
                'password_changed_successfully' => 'Ο κωδικός πρόσβασης άλλαξε επιτυχώς',
                'failed_to_change_password' => 'Αποτυχία αλλαγής κωδικού πρόσβασης. Ο τρέχων κωδικός μπορεί να είναι λάθος.',
                'using_default_password' => 'Χρησιμοποιείτε τον προεπιλεγμένο κωδικό πρόσβασης. Παρακαλώ αλλάξτε τον για λόγους ασφαλείας.',
                
                // Stock Status
                'in_stock' => 'Σε Απόθεμα',
                'out_of_stock' => 'Εκτός Αποθέματος',
                'low_stock_warning' => 'Χαμηλό Απόθεμα',
                'current_physical_inventory' => 'Τρέχον απόθεμα φυσικού καταστήματος.',
                'cost_price_help' => 'Τιμή κόστους για αυτό το προϊόν.',
                'low_stock_threshold_help' => 'Προϊόντα με απόθεμα κάτω από αυτό θα σημειωθούν ως χαμηλό απόθεμα.',
                'inventory_notes_help' => 'Προσθέστε τυχόν σημειώσεις σχετικά με το απόθεμα αυτού του προϊόντος.',
                
                // Languages
                'language' => 'Γλώσσα',
                'english' => 'Αγγλικά',
                'greek' => 'Ελληνικά',
            ]
        ];
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->language;
    }
    
    /**
     * Set language
     */
    public function setLanguage($language) {
        if (in_array($language, ['en', 'el'])) {
            $this->language = $language;
            $_SESSION['user_language'] = $language;
        }
    }
    
    /**
     * Get translation for a key
     */
    public function translate($key, $default = null) {
        if (isset($this->translations[$this->language][$key])) {
            return $this->translations[$this->language][$key];
        }
        
        // Fallback to English
        if ($this->language !== 'en' && isset($this->translations['en'][$key])) {
            return $this->translations['en'][$key];
        }
        
        // Return default or key itself
        return $default !== null ? $default : $key;
    }
    
    /**
     * Get all available languages
     */
    public function getAvailableLanguages() {
        return [
            'en' => 'English',
            'el' => 'Ελληνικά'
        ];
    }
}

/**
 * Global translation function
 */
function __($key, $default = null) {
    return Language::getInstance()->translate($key, $default);
}