<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_QA_OFFICER]);
$pageTitle = 'Feedback & Ratings';

$db = getDBConnection();

// Get filter parameters
$ratingFilter = intval($_GET['rating'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$whereConditions = [];
$params = [];

if ($ratingFilter > 0) {
    $whereConditions[] = "f.rating = ?";
    $params[] = $ratingFilter;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countQuery = "SELECT COUNT(*) FROM feedback f $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get feedback
$query = "SELECT f.*, 
    c.tracking_number,
    c.title as complaint_title,
    u.name as user_name
    FROM feedback f
    LEFT JOIN complaints c ON f.complaint_id = c.id
    LEFT JOIN users u ON f.user_id = u.id
    $whereClause
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = ITEMS_PER_PAGE;
$params[] = $offset;
$stmt = $db->prepare($query);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Get statistics
$stats = $db->query("SELECT 
    COUNT(*) as total,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
    FROM feedback")->fetch();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-star"></i> Feedback & Ratings</h2>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card dashboard-stat-card primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Feedback</h5>
                            <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stat-card success">
                        <div class="card-body">
                            <h5 class="card-title">Average Rating</h5>
                            <h2 class="mb-0"><?php echo number_format((float)($stats['avg_rating'] ?? 0), 1); ?>/5</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stat-card warning">
                        <div class="card-body">
                            <h5 class="card-title">Positive (4-5)</h5>
                            <h2 class="mb-0"><?php echo ($stats['rating_4'] + $stats['rating_5']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stat-card danger">
                        <div class="card-body">
                            <h5 class="card-title">Negative (1-2)</h5>
                            <h2 class="mb-0"><?php echo ($stats['rating_1'] + $stats['rating_2']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="rating" class="form-label">Filter by Rating</label>
                            <select class="form-select" id="rating" name="rating">
                                <option value="0">All Ratings</option>
                                <option value="5" <?php echo $ratingFilter == 5 ? 'selected' : ''; ?>>5 Stars</option>
                                <option value="4" <?php echo $ratingFilter == 4 ? 'selected' : ''; ?>>4 Stars</option>
                                <option value="3" <?php echo $ratingFilter == 3 ? 'selected' : ''; ?>>3 Stars</option>
                                <option value="2" <?php echo $ratingFilter == 2 ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="1" <?php echo $ratingFilter == 1 ? 'selected' : ''; ?>>1 Star</option>
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
            
            <!-- Feedback List -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($feedbacks)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">No feedback found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="/modules/complaints/view.php?id=<?php echo $feedback['complaint_id']; ?>">
                                                    <?php echo htmlspecialchars($feedback['tracking_number']); ?>
                                                </a>
                                                - <?php echo htmlspecialchars($feedback['complaint_title']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                By <?php echo htmlspecialchars($feedback['user_name']); ?> 
                                                on <?php echo formatDate($feedback['created_at']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="h4 mb-0">
                                                <?php 
                                                $rating = intval($feedback['rating']);
                                                for ($i = 1; $i <= 5; $i++): 
                                                    echo $i <= $rating ? '★' : '☆';
                                                endfor; 
                                                ?>
                                            </div>
                                            <small class="text-muted"><?php echo $rating; ?>/5</small>
                                        </div>
                                    </div>
                                    <?php if ($feedback['comment']): ?>
                                        <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($feedback['comment'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&rating=<?php echo $ratingFilter; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&rating=<?php echo $ratingFilter; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&rating=<?php echo $ratingFilter; ?>">Next</a>
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
