<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$pageTitle = 'My Profile';

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, getCurrentUserId()])) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $user['name'] = $name;
            $user['email'] = $email;
            logActivity(getCurrentUserId(), 'profile_update', 'User updated profile');
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        $db = getDBConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, getCurrentUserId()])) {
            logActivity(getCurrentUserId(), 'password_change', 'User changed password');
            $success = 'Password changed successfully.';
        } else {
            $error = 'Failed to change password.';
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-person-circle"></i> My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                                Profile Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">
                                Change Password
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="profileTabsContent">
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label required-field">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label required-field">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" value="<?php echo formatDate($user['created_at']); ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                        
                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label required-field">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label required-field">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label required-field">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
