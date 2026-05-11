<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/email.php';

requireLogin();
$pageTitle = 'Submit Complaint';

$db = getDBConnection();
$error = '';
$success = '';

// Get categories
$categories = $db->query("SELECT * FROM complaint_categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = intval($_POST['category_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $priority = sanitizeInput($_POST['priority'] ?? PRIORITY_MEDIUM);
    
    // Validation
    if (empty($categoryId) || empty($title) || empty($description)) {
        $error = 'Category, title, and description are required.';
    } elseif (strlen($title) < 5) {
        $error = 'Title must be at least 5 characters long.';
    } elseif (strlen($description) < 10) {
        $error = 'Description must be at least 10 characters long.';
    } else {
        // Generate tracking number
        $trackingNumber = 'ICMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Get category for routing
        $stmt = $db->prepare("SELECT * FROM complaint_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();
        
        // Determine assigned department using the selected category first,
        // then fall back to keyword-based routing.
        $assignedDepartmentId = routeComplaintDepartment($categoryId, $title, $description, $db);
        
        // Assign to an active department user when available.
        $assignedUserId = getDepartmentComplaintAssignee($assignedDepartmentId, $db);

        // Calculate SLA deadline based on priority
        $slaDeadline = calculateSLADeadline($priority, $db);
        
        // Insert complaint
        $stmt = $db->prepare("INSERT INTO complaints (tracking_number, user_id, category_id, title, description, priority, assigned_department_id, assigned_user_id, sla_deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$trackingNumber, getCurrentUserId(), $categoryId, $title, $description, $priority, $assignedDepartmentId, $assignedUserId, $slaDeadline])) {
            $complaintId = $db->lastInsertId();
            
            // Add status history
            $stmt = $db->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, notes) VALUES (?, NULL, ?, ?, 'Complaint submitted')");
            $stmt->execute([$complaintId, STATUS_NEW, getCurrentUserId()]);
            
            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                handleFileUploads($complaintId, $_FILES['attachments'], $db);
            }
            
            // Check for duplicate complaints
            $duplicates = checkDuplicateComplaints($title, $description, $complaintId, $db);
            
            // Log activity
            logActivity(getCurrentUserId(), 'complaint_submit', "Submitted complaint: $trackingNumber", $complaintId);
            
            // Send confirmation email
            $user = getCurrentUser();
            sendComplaintConfirmationEmail($user['email'], $trackingNumber, $title);
            
            $success = "Complaint submitted successfully! Your tracking number is: <strong>$trackingNumber</strong>";
            
            if (!empty($duplicates)) {
                $success .= "<br><small class='text-warning'><i class='bi bi-exclamation-triangle'></i> Similar complaints found. Please check if this is a duplicate.</small>";
            }
            
            // Clear form
            $categoryId = $title = $description = '';
        } else {
            $error = 'Failed to submit complaint. Please try again.';
        }
    }
}

/**
 * Calculate SLA deadline based on priority
 */
function calculateSLADeadline($priority, $db) {
    $stmt = $db->prepare("SELECT resolution_time_hours FROM sla_rules WHERE priority = ? AND status = 'active'");
    $stmt->execute([$priority]);
    $rule = $stmt->fetch();
    
    if ($rule) {
        $hours = intval($rule['resolution_time_hours']);
        return date('Y-m-d H:i:s', strtotime("+$hours hours"));
    }
    
    return null;
}

/**
 * Handle file uploads
 */
function handleFileUploads($complaintId, $files, $db) {
    $uploadDir = __DIR__ . '/../../uploads/complaints/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $originalName = $files['name'][$i];
            $tmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            
            // Validate file
            if ($fileSize > MAX_FILE_SIZE) {
                continue; // Skip oversized files
            }
            
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!isAllowedFileType($originalName)) {
                continue; // Skip invalid file types
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $ext;
            $filePath = $uploadDir . $filename;
            
            if (move_uploaded_file($tmpName, $filePath)) {
                // Save to database
                $stmt = $db->prepare("INSERT INTO complaint_attachments (complaint_id, filename, original_filename, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $relativePath = '/uploads/complaints/' . $filename;
                $stmt->execute([$complaintId, $filename, $originalName, $relativePath, $fileSize, $fileType, getCurrentUserId()]);
            }
        }
    }
}

/**
 * Check for duplicate complaints
 */
function checkDuplicateComplaints($title, $description, $excludeId, $db) {
    $stmt = $db->prepare("SELECT id, tracking_number, title, description FROM complaints WHERE id != ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$excludeId]);
    $complaints = $stmt->fetchAll();
    
    $duplicates = [];
    foreach ($complaints as $complaint) {
        $titleSimilarity = calculateSimilarity($title, $complaint['title']);
        $descSimilarity = calculateSimilarity($description, $complaint['description']);
        $avgSimilarity = ($titleSimilarity + $descSimilarity) / 2;
        
        if ($avgSimilarity >= DUPLICATE_SIMILARITY_THRESHOLD) {
            $duplicates[] = [
                'id' => $complaint['id'],
                'tracking_number' => $complaint['tracking_number'],
                'similarity' => round($avgSimilarity, 1)
            ];
        }
    }
    
    return $duplicates;
}
?>

<div class="container">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Submit New Complaint</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <div class="text-center mt-3">
                            <a href="/modules/complaints/list.php" class="btn btn-primary">View My Complaints</a>
                            <a href="/modules/complaints/submit.php" class="btn btn-outline-primary">Submit Another</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="category_id" class="form-label required-field">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($categoryId ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label required-field">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="<?php echo PRIORITY_LOW; ?>" <?php echo ($priority ?? PRIORITY_MEDIUM) === PRIORITY_LOW ? 'selected' : ''; ?>>Low</option>
                                    <option value="<?php echo PRIORITY_MEDIUM; ?>" <?php echo ($priority ?? PRIORITY_MEDIUM) === PRIORITY_MEDIUM ? 'selected' : ''; ?>>Medium</option>
                                    <option value="<?php echo PRIORITY_HIGH; ?>" <?php echo ($priority ?? PRIORITY_MEDIUM) === PRIORITY_HIGH ? 'selected' : ''; ?>>High</option>
                                    <option value="<?php echo PRIORITY_CRITICAL; ?>" <?php echo ($priority ?? PRIORITY_MEDIUM) === PRIORITY_CRITICAL ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label required-field">Complaint Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($title ?? ''); ?>" 
                                       minlength="5" maxlength="500" required>
                                <div class="form-text">Brief summary of your complaint (5-500 characters)</div>
                                <div class="invalid-feedback">Title must be at least 5 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="8" minlength="10" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                <div class="form-text">Provide detailed information about your complaint (minimum 10 characters)</div>
                                <div class="invalid-feedback">Description must be at least 10 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments</label>
                                <div class="file-upload-area">
                                    <input type="file" class="form-control" id="attachments" name="attachments[]" 
                                           multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> 
                                            Maximum file size: 5MB. Allowed types: PDF, DOC, DOCX, JPG, PNG, TXT
                                        </small>
                                    </div>
                                    <ul id="file-list" class="list-group mt-2"></ul>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send"></i> Submit Complaint
                                </button>
                                <a href="/modules/complaints/list.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
