<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$pageTitle = 'My Complaints';

$db = getDBConnection();

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$categoryFilter = intval($_GET['category'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query based on user role
$whereConditions = [];
$params = [];

if (hasRole(ROLE_COMPLAINANT)) {
    $whereConditions[] = "c.user_id = ?";
    $params[] = getCurrentUserId();
} elseif (hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER])) {
    // Show complaints assigned to user's department or assigned to user
    $user = getCurrentUser();
    if ($user['department_id']) {
        $whereConditions[] = "(c.assigned_department_id = ? OR c.assigned_user_id = ?)";
        $params[] = $user['department_id'];
        $params[] = getCurrentUserId();
    } else {
        $whereConditions[] = "c.assigned_user_id = ?";
        $params[] = getCurrentUserId();
    }
}

if ($statusFilter) {
    $whereConditions[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $whereConditions[] = "c.priority = ?";
    $params[] = $priorityFilter;
}

if ($categoryFilter) {
    $whereConditions[] = "c.category_id = ?";
    $params[] = $categoryFilter;
}

if ($search) {
    $whereConditions[] = "(c.title LIKE ? OR c.description LIKE ? OR c.tracking_number LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countQuery = "SELECT COUNT(*) FROM complaints c $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get complaints
$query = "SELECT c.*, 
    cat.name as category_name,
    d.name as department_name,
    u.name as user_name,
    (SELECT COUNT(*) FROM complaint_attachments WHERE complaint_id = c.id) as attachment_count
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.id
    LEFT JOIN departments d ON c.assigned_department_id = d.id
    LEFT JOIN users u ON c.user_id = u.id
    $whereClause
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = ITEMS_PER_PAGE;
$params[] = $offset;
$stmt = $db->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM complaint_categories WHERE status = 'active' ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-list-ul"></i> Complaints</h2>
                <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                    <a href="/modules/complaints/submit.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Submit New Complaint
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" placeholder="Title, description, or tracking number">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="<?php echo STATUS_NEW; ?>" <?php echo $statusFilter === STATUS_NEW ? 'selected' : ''; ?>>New</option>
                                <option value="<?php echo STATUS_IN_PROGRESS; ?>" <?php echo $statusFilter === STATUS_IN_PROGRESS ? 'selected' : ''; ?>>In Progress</option>
                                <option value="<?php echo STATUS_RESOLVED; ?>" <?php echo $statusFilter === STATUS_RESOLVED ? 'selected' : ''; ?>>Resolved</option>
                                <option value="<?php echo STATUS_CLOSED; ?>" <?php echo $statusFilter === STATUS_CLOSED ? 'selected' : ''; ?>>Closed</option>
                                <option value="<?php echo STATUS_ESCALATED; ?>" <?php echo $statusFilter === STATUS_ESCALATED ? 'selected' : ''; ?>>Escalated</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="">All Priorities</option>
                                <option value="<?php echo PRIORITY_LOW; ?>" <?php echo $priorityFilter === PRIORITY_LOW ? 'selected' : ''; ?>>Low</option>
                                <option value="<?php echo PRIORITY_MEDIUM; ?>" <?php echo $priorityFilter === PRIORITY_MEDIUM ? 'selected' : ''; ?>>Medium</option>
                                <option value="<?php echo PRIORITY_HIGH; ?>" <?php echo $priorityFilter === PRIORITY_HIGH ? 'selected' : ''; ?>>High</option>
                                <option value="<?php echo PRIORITY_CRITICAL; ?>" <?php echo $priorityFilter === PRIORITY_CRITICAL ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Complaints List -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($complaints)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">No complaints found.</p>
                            <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                                <a href="/modules/complaints/submit.php" class="btn btn-primary">Submit Your First Complaint</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tracking #</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Department</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $complaint): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($complaint['tracking_number']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($complaint['title']); ?>
                                                <?php if ($complaint['attachment_count'] > 0): ?>
                                                    <i class="bi bi-paperclip text-muted" title="<?php echo $complaint['attachment_count']; ?> attachment(s)"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($complaint['category_name']); ?></td>
                                            <td><?php echo getPriorityBadge($complaint['priority']); ?></td>
                                            <td><?php echo getStatusBadge($complaint['status']); ?></td>
                                            <td><?php echo htmlspecialchars($complaint['department_name'] ?? 'Unassigned'); ?></td>
                                            <td><?php echo formatDate($complaint['created_at'], 'Y-m-d H:i'); ?></td>
                                            <td>
                                                <a href="/modules/complaints/view.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&priority=<?php echo $priorityFilter; ?>&category=<?php echo $categoryFilter; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&priority=<?php echo $priorityFilter; ?>&category=<?php echo $categoryFilter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&priority=<?php echo $priorityFilter; ?>&category=<?php echo $categoryFilter; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
