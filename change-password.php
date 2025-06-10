<?php
/**
 * Change Password Page
 * 
 * Allows users to change their password
 */

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';
require_once 'includes/i18n.php';

// Initialize authentication
$auth = new Auth();

// Require authentication
$auth->requireAuth();

// Get current user
$current_user = $auth->getCurrentUser();

// Process form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    } else {
        // Attempt to change password
        if ($auth->changePassword($current_user->id, $old_password, $new_password)) {
            set_flash_message('success', 'Password changed successfully');
            redirect('dashboard.php');
        } else {
            $error = 'Failed to change password. Current password may be incorrect.';
        }
    }
}

// Include header
include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Change Password</h1>
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Update Your Password</h6>
            </div>
            <div class="card-body">
                <?php if ($auth->requiresPasswordReset()): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    You are using the default password. Please change it for security reasons.
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    Password changed successfully!
                </div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="mb-3">
                        <label for="old_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="old_password" name="old_password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';
?>