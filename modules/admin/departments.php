<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'Department Management';

$db = getDBConnection();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name)) {
            $error = 'Department name is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO departments (name, description, email, phone, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $email, $phone, $status])) {
                logActivity(getCurrentUserId(), 'department_create', "Created department: $name");
                $success = 'Department added successfully.';
            } else {
                $error = 'Failed to add department.';
            }
        }
    } elseif (isset($_POST['update_department'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name)) {
            $error = 'Department name is required.';
        } else {
            $stmt = $db->prepare("UPDATE departments SET name = ?, description = ?, email = ?, phone = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $email, $phone, $status, $id])) {
                logActivity(getCurrentUserId(), 'department_update', "Updated department: $name");
                $success = 'Department updated successfully.';
            } else {
                $error = 'Failed to update department.';
            }
        }
    } elseif (isset($_POST['delete_department'])) {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $dept = $stmt->fetch();
        
        if ($dept) {
            // Check if department has users or complaints
            $checkUsers = $db->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
            $checkUsers->execute([$id]);
            $userCount = $checkUsers->fetchColumn();
            
            $checkComplaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE assigned_department_id = ?");
            $checkComplaints->execute([$id]);
            $complaintCount = $checkComplaints->fetchColumn();
            
            if ($userCount > 0 || $complaintCount > 0) {
                $error = "Cannot delete department. It has $userCount user(s) and $complaintCount complaint(s) assigned.";
            } else {
                $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
                if ($stmt->execute([$id])) {
                    logActivity(getCurrentUserId(), 'department_delete', "Deleted department: {$dept['name']}");
                    $success = 'Department deleted successfully.';
                } else {
                    $error = 'Failed to delete department.';
                }
            }
        }
    }
}

// Get all departments
$departments = $db->query("SELECT d.*, 
    (SELECT COUNT(*) FROM users WHERE department_id = d.id) as user_count,
    (SELECT COUNT(*) FROM complaints WHERE assigned_department_id = d.id) as complaint_count
    FROM departments d ORDER BY d.name")->fetchAll();

// Get department for editing
$editDepartment = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $editDepartment = $stmt->fetch();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-building"></i> Department Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="bi bi-plus-circle"></i> Add Department
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
                                    <th>Description</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Users</th>
                                    <th>Complaints</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No departments found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><?php echo $dept['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dept['description'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($dept['email'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($dept['phone'] ?? '-'); ?></td>
                                            <td><span class="badge bg-info"><?php echo $dept['user_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $dept['complaint_count']; ?></span></td>
                                            <td>
                                                <?php if ($dept['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $dept['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                                    <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                    <button type="submit" name="delete_department" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
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

<!-- Add/Edit Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editDepartment ? 'Edit Department' : 'Add Department'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($editDepartment): ?>
                        <input type="hidden" name="id" value="<?php echo $editDepartment['id']; ?>">
                        <input type="hidden" name="update_department" value="1">
                    <?php else: ?>
                        <input type="hidden" name="add_department" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label required-field">Department Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($editDepartment['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($editDepartment['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($editDepartment['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($editDepartment['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($editDepartment['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editDepartment['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editDepartment ? 'Update' : 'Add'; ?> Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editDepartment): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addDepartmentModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
