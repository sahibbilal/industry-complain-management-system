<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Track Complaint';

$db = getDBConnection();
$complaint = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tracking'])) {
    $trackingNumber = sanitizeInput($_GET['tracking'] ?? '');
    
    if ($trackingNumber) {
        $stmt = $db->prepare("SELECT c.*, 
            cat.name as category_name,
            d.name as department_name,
            u.name as user_name
            FROM complaints c
            LEFT JOIN complaint_categories cat ON c.category_id = cat.id
            LEFT JOIN departments d ON c.assigned_department_id = d.id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.tracking_number = ?");
        $stmt->execute([$trackingNumber]);
        $complaint = $stmt->fetch();
        
        if (!$complaint) {
            $error = 'Complaint not found. Please check your tracking number.';
        }
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-search"></i> Track Your Complaint</h4>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" 
                                   name="tracking" 
                                   placeholder="Enter tracking number (e.g., ICMS-20240101-ABC123)" 
                                   value="<?php echo htmlspecialchars($_GET['tracking'] ?? ''); ?>" 
                                   required>
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="bi bi-search"></i> Track
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($complaint): ?>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Tracking Number:</strong><br>
                                        <span class="h5"><?php echo htmlspecialchars($complaint['tracking_number']); ?></span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <?php echo getStatusBadge($complaint['status']); ?>
                                        <?php echo getPriorityBadge($complaint['priority']); ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Title</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['title']); ?></dd>
                                    
                                    <dt class="col-sm-4">Category</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['category_name']); ?></dd>
                                    
                                    <dt class="col-sm-4">Status</dt>
                                    <dd class="col-sm-8"><?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?></dd>
                                    
                                    <dt class="col-sm-4">Assigned Department</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['department_name'] ?? 'Unassigned'); ?></dd>
                                    
                                    <dt class="col-sm-4">Created</dt>
                                    <dd class="col-sm-8"><?php echo formatDate($complaint['created_at']); ?></dd>
                                    
                                    <?php if ($complaint['sla_deadline']): ?>
                                        <dt class="col-sm-4">SLA Deadline</dt>
                                        <dd class="col-sm-8">
                                            <?php echo formatDate($complaint['sla_deadline']); ?>
                                            <?php if (strtotime($complaint['sla_deadline']) < time() && $complaint['status'] != STATUS_RESOLVED && $complaint['status'] != STATUS_CLOSED): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php endif; ?>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                                
                                <?php if (isLoggedIn() && (hasRole(ROLE_COMPLAINANT) && $complaint['user_id'] == getCurrentUserId()) || hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN])): ?>
                                    <hr>
                                    <div class="text-center">
                                        <a href="/modules/complaints/view.php?id=<?php echo $complaint['id']; ?>" class="btn btn-primary">
                                            <i class="bi bi-eye"></i> View Full Details
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
