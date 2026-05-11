<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/email.php';

requireLogin();
$pageTitle = 'Complaint Details';

$db = getDBConnection();
$error = '';
$success = '';

$complaintId = intval($_GET['id'] ?? 0);

if (!$complaintId) {
    redirect('/modules/complaints/list.php');
}

// Get complaint details
$stmt = $db->prepare("SELECT c.*, 
    cat.name as category_name,
    d.name as department_name,
    u.name as user_name,
    u.email as user_email,
    au.name as assigned_user_name
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.id
    LEFT JOIN departments d ON c.assigned_department_id = d.id
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN users au ON c.assigned_user_id = au.id
    WHERE c.id = ?");
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    setFlashMessage('danger', 'Complaint not found.');
    redirect('/modules/complaints/list.php');
}

// Check access permissions
if (hasRole(ROLE_COMPLAINANT) && $complaint['user_id'] != getCurrentUserId()) {
    setFlashMessage('danger', 'You do not have permission to view this complaint.');
    redirect('/modules/complaints/list.php');
}

if (hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER]) && !hasRole(ROLE_ADMIN)) {
    $currentUser = getCurrentUser();
    $isDepartmentMatch = !empty($currentUser['department_id']) && (int) $complaint['assigned_department_id'] === (int) $currentUser['department_id'];
    $isDirectAssignee = (int) $complaint['assigned_user_id'] === (int) getCurrentUserId();

    if (!$isDepartmentMatch && !$isDirectAssignee) {
        setFlashMessage('danger', 'You do not have permission to view this complaint.');
        redirect('/modules/complaints/list.php');
    }
}

// Get attachments
$stmt = $db->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY created_at");
$stmt->execute([$complaintId]);
$attachments = $stmt->fetchAll();

// Get status history
$stmt = $db->prepare("SELECT h.*, u.name as changed_by_name 
    FROM complaint_status_history h
    LEFT JOIN users u ON h.changed_by = u.id
    WHERE h.complaint_id = ? 
    ORDER BY h.created_at DESC");
$stmt->execute([$complaintId]);
$statusHistory = $stmt->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN])) {
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $resolution = sanitizeInput($_POST['resolution'] ?? '');
    
    if (in_array($newStatus, [STATUS_NEW, STATUS_IN_PROGRESS, STATUS_RESOLVED, STATUS_CLOSED, STATUS_ESCALATED])) {
        $oldStatus = $complaint['status'];
        
        $updateFields = ['status' => $newStatus];
        if ($newStatus === STATUS_RESOLVED && $resolution) {
            $updateFields['resolution'] = $resolution;
            $updateFields['resolved_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === STATUS_CLOSED) {
            $updateFields['closed_at'] = date('Y-m-d H:i:s');
        }
        
        $setClause = implode(', ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($updateFields)));
        $values = array_values($updateFields);
        $values[] = $complaintId;
        
        $stmt = $db->prepare("UPDATE complaints SET $setClause WHERE id = ?");
        if ($stmt->execute($values)) {
            // Add status history
            $stmt = $db->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$complaintId, $oldStatus, $newStatus, getCurrentUserId(), $notes]);
            
            // Send email notification
            if ($oldStatus != $newStatus) {
                sendStatusUpdateEmail($complaint['user_email'], $complaint['tracking_number'], $complaint['title'], $newStatus);
            }
            
            logActivity(getCurrentUserId(), 'status_update', "Updated complaint #{$complaint['tracking_number']} status from $oldStatus to $newStatus", $complaintId);
            $success = 'Status updated successfully.';
            
            // Refresh complaint data
            $stmt = $db->prepare("SELECT c.*, 
                cat.name as category_name,
                d.name as department_name,
                u.name as user_name,
                u.email as user_email,
                au.name as assigned_user_name
                FROM complaints c
                LEFT JOIN complaint_categories cat ON c.category_id = cat.id
                LEFT JOIN departments d ON c.assigned_department_id = d.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN users au ON c.assigned_user_id = au.id
                WHERE c.id = ?");
            $stmt->execute([$complaintId]);
            $complaint = $stmt->fetch();
            
            // Refresh status history
            $stmt = $db->prepare("SELECT h.*, u.name as changed_by_name 
                FROM complaint_status_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.complaint_id = ? 
                ORDER BY h.created_at DESC");
            $stmt->execute([$complaintId]);
            $statusHistory = $stmt->fetchAll();
        } else {
            $error = 'Failed to update status.';
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Complaint Details</h5>
                    <div>
                        <?php echo getStatusBadge($complaint['status']); ?>
                        <?php echo getPriorityBadge($complaint['priority']); ?>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Tracking Number</dt>
                        <dd class="col-sm-9"><strong><?php echo htmlspecialchars($complaint['tracking_number']); ?></strong></dd>
                        
                        <dt class="col-sm-3">Title</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($complaint['title']); ?></dd>
                        
                        <dt class="col-sm-3">Category</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($complaint['category_name']); ?></dd>
                        
                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></dd>
                        
                        <?php if ($complaint['resolution']): ?>
                            <dt class="col-sm-3">Resolution</dt>
                            <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($complaint['resolution'])); ?></dd>
                        <?php endif; ?>
                        
                        <?php if ($complaint['status'] == STATUS_RESOLVED && hasRole(ROLE_COMPLAINANT) && $complaint['user_id'] == getCurrentUserId()): ?>
                            <?php
                            // Check if feedback already exists
                            $stmt = $db->prepare("SELECT * FROM feedback WHERE complaint_id = ? AND user_id = ?");
                            $stmt->execute([$complaintId, getCurrentUserId()]);
                            $hasFeedback = $stmt->fetch();
                            ?>
                            <?php if (!$hasFeedback): ?>
                                <dt class="col-sm-3">Feedback</dt>
                                <dd class="col-sm-9">
                                    <a href="/modules/feedback/submit.php?id=<?php echo $complaintId; ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-star"></i> Provide Feedback
                                    </a>
                                </dd>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <dt class="col-sm-3">Submitted By</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($complaint['user_name']); ?></dd>
                        
                        <dt class="col-sm-3">Assigned Department</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($complaint['department_name'] ?? 'Unassigned'); ?></dd>
                        
                        <?php if ($complaint['assigned_user_name']): ?>
                            <dt class="col-sm-3">Assigned To</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($complaint['assigned_user_name']); ?></dd>
                        <?php endif; ?>
                        
                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9"><?php echo formatDate($complaint['created_at']); ?></dd>
                        
                        <?php if ($complaint['sla_deadline']): ?>
                            <dt class="col-sm-3">SLA Deadline</dt>
                            <dd class="col-sm-9">
                                <?php echo formatDate($complaint['sla_deadline']); ?>
                                <?php if (strtotime($complaint['sla_deadline']) < time() && $complaint['status'] != STATUS_RESOLVED && $complaint['status'] != STATUS_CLOSED): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            
            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-paperclip"></i> Attachments</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($attachments as $attachment): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-file-earmark"></i>
                                        <?php echo htmlspecialchars($attachment['original_filename']); ?>
                                        <small class="text-muted">(<?php echo number_format($attachment['file_size'] / 1024, 2); ?> KB)</small>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Status Update Form (for staff) -->
            <?php if (hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN]) && $complaint['status'] != STATUS_CLOSED): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Update Status</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="update_status" value="1">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label required-field">New Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="<?php echo STATUS_NEW; ?>" <?php echo $complaint['status'] === STATUS_NEW ? 'selected' : ''; ?>>New</option>
                                    <option value="<?php echo STATUS_IN_PROGRESS; ?>" <?php echo $complaint['status'] === STATUS_IN_PROGRESS ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="<?php echo STATUS_RESOLVED; ?>" <?php echo $complaint['status'] === STATUS_RESOLVED ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="<?php echo STATUS_CLOSED; ?>" <?php echo $complaint['status'] === STATUS_CLOSED ? 'selected' : ''; ?>>Closed</option>
                                    <option value="<?php echo STATUS_ESCALATED; ?>" <?php echo $complaint['status'] === STATUS_ESCALATED ? 'selected' : ''; ?>>Escalated</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="resolution-field" style="display: none;">
                                <label for="resolution" class="form-label">Resolution Details</label>
                                <textarea class="form-control" id="resolution" name="resolution" rows="4"><?php echo htmlspecialchars($complaint['resolution'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Status
                                </button>
                                <?php if ($complaint['status'] != STATUS_ESCALATED && $complaint['status'] != STATUS_CLOSED): ?>
                                    <a href="/modules/complaints/escalate.php?id=<?php echo $complaintId; ?>" class="btn btn-danger">
                                        <i class="bi bi-arrow-up-circle"></i> Escalate
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Status History -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Status History</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($statusHistory as $history): ?>
                            <div class="timeline-item">
                                <div class="mb-2">
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $history['new_status'])); ?></strong>
                                    <?php if ($history['old_status']): ?>
                                        <small class="text-muted">(from <?php echo ucfirst(str_replace('_', ' ', $history['old_status'])); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($history['notes']): ?>
                                    <p class="mb-1"><small><?php echo htmlspecialchars($history['notes']); ?></small></p>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($history['changed_by_name'] ?? 'System'); ?>
                                    <br>
                                    <i class="bi bi-calendar"></i> <?php echo formatDate($history['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide resolution field based on status
document.getElementById('status').addEventListener('change', function() {
    const resolutionField = document.getElementById('resolution-field');
    if (this.value === '<?php echo STATUS_RESOLVED; ?>') {
        resolutionField.style.display = 'block';
    } else {
        resolutionField.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
