<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'Category Management';

$db = getDBConnection();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $defaultDepartmentId = !empty($_POST['default_department_id']) ? intval($_POST['default_department_id']) : null;
        $keywords = sanitizeInput($_POST['keywords'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO complaint_categories (name, description, default_department_id, keywords, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $defaultDepartmentId, $keywords, $status])) {
                logActivity(getCurrentUserId(), 'category_create', "Created category: $name");
                $success = 'Category added successfully.';
            } else {
                $error = 'Failed to add category.';
            }
        }
    } elseif (isset($_POST['update_category'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $defaultDepartmentId = !empty($_POST['default_department_id']) ? intval($_POST['default_department_id']) : null;
        $keywords = sanitizeInput($_POST['keywords'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            $stmt = $db->prepare("UPDATE complaint_categories SET name = ?, description = ?, default_department_id = ?, keywords = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $defaultDepartmentId, $keywords, $status, $id])) {
                logActivity(getCurrentUserId(), 'category_update', "Updated category: $name");
                $success = 'Category updated successfully.';
            } else {
                $error = 'Failed to update category.';
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT name FROM complaint_categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if ($category) {
            // Check if category has complaints
            $checkComplaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE category_id = ?");
            $checkComplaints->execute([$id]);
            $complaintCount = $checkComplaints->fetchColumn();
            
            if ($complaintCount > 0) {
                $error = "Cannot delete category. It has $complaintCount complaint(s) assigned.";
            } else {
                $stmt = $db->prepare("DELETE FROM complaint_categories WHERE id = ?");
                if ($stmt->execute([$id])) {
                    logActivity(getCurrentUserId(), 'category_delete', "Deleted category: {$category['name']}");
                    $success = 'Category deleted successfully.';
                } else {
                    $error = 'Failed to delete category.';
                }
            }
        }
    }
}

// Get all categories with department info
$categories = $db->query("SELECT c.*, d.name as department_name 
    FROM complaint_categories c 
    LEFT JOIN departments d ON c.default_department_id = d.id 
    ORDER BY c.name")->fetchAll();

// Get all departments for dropdown
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();

// Get category for editing
$editCategory = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM complaint_categories WHERE id = ?");
    $stmt->execute([$id]);
    $editCategory = $stmt->fetch();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-tags"></i> Category Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle"></i> Add Category
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
                                    <th>Default Department</th>
                                    <th>Keywords</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No categories found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><?php echo $cat['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($cat['department_name'] ?? '-'); ?></td>
                                            <td><small><?php echo htmlspecialchars($cat['keywords'] ?? '-'); ?></small></td>
                                            <td>
                                                <?php if ($cat['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-sm btn-danger">
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

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editCategory ? 'Edit Category' : 'Add Category'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                        <input type="hidden" name="update_category" value="1">
                    <?php else: ?>
                        <input type="hidden" name="add_category" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label required-field">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_department_id" class="form-label">Default Department</label>
                        <select class="form-select" id="default_department_id" name="default_department_id">
                            <option value="">None</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo ($editCategory['default_department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Complaints in this category will be automatically routed to this department.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keywords" class="form-label">Keywords</label>
                        <input type="text" class="form-control" id="keywords" name="keywords" 
                               value="<?php echo htmlspecialchars($editCategory['keywords'] ?? ''); ?>"
                               placeholder="comma,separated,keywords">
                        <div class="form-text">Comma-separated keywords for automatic routing (e.g., computer,network,email)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($editCategory['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editCategory['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editCategory ? 'Update' : 'Add'; ?> Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editCategory): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
