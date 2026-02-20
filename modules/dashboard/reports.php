<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_MANAGER, ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'Reports';

$db = getDBConnection();

// Get filter parameters
$reportType = $_GET['type'] ?? 'summary';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$departmentId = intval($_GET['department'] ?? 0);

// Generate report data based on type
$reportData = [];

switch ($reportType) {
    case 'summary':
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_resolution_hours
            FROM complaints
            WHERE DATE(created_at) BETWEEN ? AND ?
            " . ($departmentId ? "AND assigned_department_id = ?" : ""));
        $params = [$dateFrom, $dateTo];
        if ($departmentId) $params[] = $departmentId;
        $stmt->execute($params);
        $reportData = $stmt->fetch();
        break;
        
    case 'department':
        $stmt = $db->prepare("SELECT 
            d.name as department_name,
            COUNT(c.id) as total,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END) as closed,
            AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) ELSE NULL END) as avg_hours
            FROM complaints c
            LEFT JOIN departments d ON c.assigned_department_id = d.id
            WHERE DATE(c.created_at) BETWEEN ? AND ?
            GROUP BY d.id, d.name
            ORDER BY total DESC");
        $stmt->execute([$dateFrom, $dateTo]);
        $reportData = $stmt->fetchAll();
        break;
        
    case 'category':
        $stmt = $db->prepare("SELECT 
            cat.name as category_name,
            COUNT(c.id) as total,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM complaints c
            LEFT JOIN complaint_categories cat ON c.category_id = cat.id
            WHERE DATE(c.created_at) BETWEEN ? AND ?
            GROUP BY cat.id, cat.name
            ORDER BY total DESC");
        $stmt->execute([$dateFrom, $dateTo]);
        $reportData = $stmt->fetchAll();
        break;
}

// Get departments for filter
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-file-earmark-text"></i> Reports</h2>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn btn-danger">
                    <i class="bi bi-file-pdf"></i> Export PDF
                </a>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Report Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="department" <?php echo $reportType === 'department' ? 'selected' : ''; ?>>Department Performance</option>
                                <option value="category" <?php echo $reportType === 'category' ? 'selected' : ''; ?>>Category Analysis</option>
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
                        
                        <div class="col-md-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="0">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $departmentId == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Content -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php 
                        $titles = [
                            'summary' => 'Summary Report',
                            'department' => 'Department Performance Report',
                            'category' => 'Category Analysis Report'
                        ];
                        echo $titles[$reportType] ?? 'Report';
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($reportType === 'summary'): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $reportData['total']; ?></h3>
                                        <p class="mb-0">Total Complaints</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $reportData['resolved']; ?></h3>
                                        <p class="mb-0">Resolved</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $reportData['closed']; ?></h3>
                                        <p class="mb-0">Closed</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $reportData['avg_resolution_hours'] ? number_format($reportData['avg_resolution_hours'], 1) : 'N/A'; ?></h3>
                                        <p class="mb-0">Avg Resolution (Hours)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($reportType === 'department'): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Total Complaints</th>
                                        <th>Resolved</th>
                                        <th>Closed</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg Resolution Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['department_name'] ?? 'Unassigned'); ?></strong></td>
                                            <td><?php echo $row['total']; ?></td>
                                            <td><?php echo $row['resolved']; ?></td>
                                            <td><?php echo $row['closed']; ?></td>
                                            <td>
                                                <?php 
                                                $rate = $row['total'] > 0 ? (($row['resolved'] + $row['closed']) / $row['total'] * 100) : 0;
                                                echo number_format($rate, 1); ?>%
                                            </td>
                                            <td><?php echo $row['avg_hours'] ? number_format($row['avg_hours'], 1) . ' hours' : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType === 'category'): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total Complaints</th>
                                        <th>Resolved</th>
                                        <th>Resolution Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['category_name'] ?? 'Unknown'); ?></strong></td>
                                            <td><?php echo $row['total']; ?></td>
                                            <td><?php echo $row['resolved']; ?></td>
                                            <td>
                                                <?php 
                                                $rate = $row['total'] > 0 ? ($row['resolved'] / $row['total'] * 100) : 0;
                                                echo number_format($rate, 1); ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
