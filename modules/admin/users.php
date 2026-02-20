<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'User Management';

$db = getDBConnection();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? ROLE_COMPLAINANT);
        $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, email, and password are required.';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $result = registerUser($name, $email, $password, $role);
            if ($result['success']) {
                $userId = $result['user_id'];
                if ($departmentId) {
                    $stmt = $db->prepare("UPDATE users SET department_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$departmentId, $status, $userId]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $userId]);
                }
                logActivity(getCurrentUserId(), 'user_create', "Created user: $name ($email)");
                $success = 'User added successfully.';
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? ROLE_COMPLAINANT);
        $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address.';
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, department_id = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$name, $email, $role, $departmentId, $status, $id])) {
                logActivity(getCurrentUserId(), 'user_update', "Updated user: $name ($email)");
                $success = 'User updated successfully.';
            } else {
                $error = 'Failed to update user.';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id == getCurrentUserId()) {
            $error = 'You cannot delete your own account.';
        } else {
            $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$id])) {
                    logActivity(getCurrentUserId(), 'user_delete', "Deleted user: {$user['name']} ({$user['email']})");
                    $success = 'User deleted successfully.';
                } else {
                    $error = 'Failed to delete user.';
                }
            }
        }
    }
}

// Get all users with department info
$users = $db->query("SELECT u.*, d.name as department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    ORDER BY u.created_at DESC")->fetchAll();

// Get all departments for dropdown
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();

// Get user for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-people"></i> User Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus"></i> Add User
                </button>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($user['department_name'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($user['status'] === 'inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($user['created_at']); ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <?php if ($user['id'] != getCurrentUserId()): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editUser ? 'Edit User' : 'Add User'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                        <input type="hidden" name="update_user" value="1">
                    <?php else: ?>
                        <input type="hidden" name="add_user" value="1">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label required-field">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($editUser['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <?php if (!$editUser): ?>
                    <div class="mb-3">
                        <label for="password" class="form-label required-field">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label required-field">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="<?php echo ROLE_COMPLAINANT; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_COMPLAINANT ? 'selected' : ''; ?>>Complainant</option>
                                <option value="<?php echo ROLE_SUPPORT_STAFF; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_SUPPORT_STAFF ? 'selected' : ''; ?>>Support Staff</option>
                                <option value="<?php echo ROLE_MANAGER; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_MANAGER ? 'selected' : ''; ?>>Manager</option>
                                <option value="<?php echo ROLE_QA_OFFICER; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_QA_OFFICER ? 'selected' : ''; ?>>QA Officer</option>
                                <option value="<?php echo ROLE_ADMIN; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_ADMIN ? 'selected' : ''; ?>>Administrator</option>
                                <option value="<?php echo ROLE_SENIOR_MANAGEMENT; ?>" <?php echo ($editUser['role'] ?? '') === ROLE_SENIOR_MANAGEMENT ? 'selected' : ''; ?>>Senior Management</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">None</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                        <?php echo ($editUser['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($editUser['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editUser['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($editUser['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editUser ? 'Update' : 'Add'; ?> User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editUser): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
