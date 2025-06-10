<?php
/**
 * Profile Page
 * 
 * Allows users to view and edit their profile information
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';
require_once 'includes/i18n.php';

$auth = new Auth();
$auth->requireAuth();

$current_user = $auth->getCurrentUser();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($name)) {
        $error = __('Name is required');
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('Please enter a valid email address');
    } else {
        if ($auth->updateProfile($current_user->id, $name, $email)) {
            set_flash_message('success', __('Profile updated successfully'));
            redirect('profile.php');
        } else {
            $error = __('Failed to update profile');
        }
    }
}

include 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo __('Profile'); ?></h1>
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo __('Back to Dashboard'); ?>
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('Profile Information'); ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label"><?php echo __('Username'); ?></label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($current_user->username); ?>" readonly>
                            <div class="form-text"><?php echo __('Username cannot be changed'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label"><?php echo __('Role'); ?></label>
                            <input type="text" class="form-control" id="role" value="<?php echo ucfirst($current_user->role); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label"><?php echo __('Full Name'); ?></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($current_user->name); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo __('Email Address'); ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user->email ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('Member Since'); ?></label>
                        <p class="form-control-static"><?php echo format_date($current_user->created_at); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('Last Login'); ?></label>
                        <p class="form-control-static">
                            <?php echo $current_user->last_login ? format_date($current_user->last_login) : __('Never'); ?>
                        </p>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="change-password.php" class="btn btn-outline-warning">
                            <i class="fas fa-key"></i> <?php echo __('Change Password'); ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo __('Update Profile'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'templates/footer.php';
?>