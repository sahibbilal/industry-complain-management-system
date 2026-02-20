<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'Audit Logs';

$db = getDBConnection();

// Get filter parameters
$actionFilter = $_GET['action'] ?? '';
$userIdFilter = intval($_GET['user'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$whereConditions = [];
$params = [];

if ($actionFilter) {
    $whereConditions[] = "a.action = ?";
    $params[] = $actionFilter;
}

if ($userIdFilter) {
    $whereConditions[] = "a.user_id = ?";
    $params[] = $userIdFilter;
}

$whereConditions[] = "DATE(a.created_at) BETWEEN ? AND ?";
$params[] = $dateFrom;
$params[] = $dateTo;

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) FROM audit_logs a $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get audit logs
$query = "SELECT a.*, 
    u.name as user_name,
    u.email as user_email,
    c.tracking_number
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN complaints c ON a.complaint_id = c.id
    $whereClause
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = ITEMS_PER_PAGE;
$params[] = $offset;
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $db->query("SELECT DISTINCT u.id, u.name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE u.id IS NOT NULL ORDER BY u.name")->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-shield-check"></i> Audit Logs</h2>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="user" class="form-label">User</label>
                            <select class="form-select" id="user" name="user">
                                <option value="0">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $userIdFilter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Audit Logs Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Complaint</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No audit logs found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo formatDate($log['created_at']); ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <?php echo htmlspecialchars($log['user_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['user_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td>
                                                <?php if ($log['tracking_number']): ?>
                                                    <a href="/modules/complaints/view.php?id=<?php echo $log['complaint_id']; ?>">
                                                        <?php echo htmlspecialchars($log['tracking_number']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($actionFilter); ?>&user=<?php echo $userIdFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&action=<?php echo urlencode($actionFilter); ?>&user=<?php echo $userIdFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($actionFilter); ?>&user=<?php echo $userIdFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
