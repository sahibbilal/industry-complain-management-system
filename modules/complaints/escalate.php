<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/email.php';

requireAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN]);
$pageTitle = 'Escalate Complaint';

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
    u.email as user_email
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.id
    LEFT JOIN departments d ON c.assigned_department_id = d.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = ?");
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    setFlashMessage('danger', 'Complaint not found.');
    redirect('/modules/complaints/list.php');
}

// Get managers for escalation
$stmt = $db->query("SELECT * FROM users WHERE role IN ('manager', 'senior_management', 'admin') AND status = 'active' ORDER BY name");
$managers = $stmt->fetchAll();

// Handle escalation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $escalatedTo = intval($_POST['escalated_to'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');
    $newPriority = sanitizeInput($_POST['new_priority'] ?? $complaint['priority']);
    
    if (empty($escalatedTo) || empty($reason)) {
        $error = 'Please select a manager and provide a reason for escalation.';
    } else {
        $oldPriority = $complaint['priority'];
        
        // Update complaint
        $stmt = $db->prepare("UPDATE complaints SET status = ?, priority = ?, assigned_user_id = ? WHERE id = ?");
        if ($stmt->execute([STATUS_ESCALATED, $newPriority, $escalatedTo, $complaintId])) {
            // Add status history
            $stmt = $db->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$complaintId, $complaint['status'], STATUS_ESCALATED, getCurrentUserId(), "Escalated: $reason"]);
            
            // Create escalation record
            $stmt = $db->prepare("INSERT INTO escalations (complaint_id, escalated_from, escalated_to, reason, priority_before, priority_after, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$complaintId, getCurrentUserId(), $escalatedTo, $reason, $oldPriority, $newPriority]);
            
            // Send escalation email
            $manager = $db->prepare("SELECT email, name FROM users WHERE id = ?");
            $manager->execute([$escalatedTo]);
            $managerData = $manager->fetch();
            
            if ($managerData) {
                sendEscalationEmail($managerData['email'], $complaint['tracking_number'], $complaint['title'], $reason);
            }
            
            logActivity(getCurrentUserId(), 'complaint_escalate', "Escalated complaint #{$complaint['tracking_number']} to {$managerData['name']}", $complaintId);
            $success = 'Complaint escalated successfully.';
            
            // Refresh complaint data
            $stmt = $db->prepare("SELECT c.*, 
                cat.name as category_name,
                d.name as department_name,
                u.name as user_name,
                u.email as user_email
                FROM complaints c
                LEFT JOIN complaint_categories cat ON c.category_id = cat.id
                LEFT JOIN departments d ON c.assigned_department_id = d.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.id = ?");
            $stmt->execute([$complaintId]);
            $complaint = $stmt->fetch();
        } else {
            $error = 'Failed to escalate complaint.';
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Escalate Complaint</h4>
                </div>
                <div class="card-body">
                    <!-- Complaint Info -->
                    <div class="alert alert-info">
                        <strong>Complaint:</strong> <?php echo htmlspecialchars($complaint['tracking_number']); ?><br>
                        <strong>Title:</strong> <?php echo htmlspecialchars($complaint['title']); ?><br>
                        <strong>Current Status:</strong> <?php echo getStatusBadge($complaint['status']); ?>
                        <strong>Current Priority:</strong> <?php echo getPriorityBadge($complaint['priority']); ?>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <div class="text-center mt-3">
                            <a href="/modules/complaints/view.php?id=<?php echo $complaintId; ?>" class="btn btn-primary">View Complaint</a>
                            <a href="/modules/complaints/list.php" class="btn btn-outline-secondary">Back to List</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="escalated_to" class="form-label required-field">Escalate To</label>
                                <select class="form-select" id="escalated_to" name="escalated_to" required>
                                    <option value="">Select a manager</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>">
                                            <?php echo htmlspecialchars($manager['name']); ?> 
                                            (<?php echo ucfirst(str_replace('_', ' ', $manager['role'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a manager.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_priority" class="form-label">New Priority</label>
                                <select class="form-select" id="new_priority" name="new_priority">
                                    <option value="<?php echo PRIORITY_LOW; ?>" <?php echo $complaint['priority'] === PRIORITY_LOW ? 'selected' : ''; ?>>Low</option>
                                    <option value="<?php echo PRIORITY_MEDIUM; ?>" <?php echo $complaint['priority'] === PRIORITY_MEDIUM ? 'selected' : ''; ?>>Medium</option>
                                    <option value="<?php echo PRIORITY_HIGH; ?>" <?php echo $complaint['priority'] === PRIORITY_HIGH ? 'selected' : ''; ?>>High</option>
                                    <option value="<?php echo PRIORITY_CRITICAL; ?>" <?php echo $complaint['priority'] === PRIORITY_CRITICAL ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label required-field">Reason for Escalation</label>
                                <textarea class="form-control" id="reason" name="reason" rows="5" required placeholder="Explain why this complaint needs to be escalated..."></textarea>
                                <div class="invalid-feedback">Please provide a reason for escalation.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-arrow-up-circle"></i> Escalate Complaint
                                </button>
                                <a href="/modules/complaints/view.php?id=<?php echo $complaintId; ?>" class="btn btn-outline-secondary">
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
