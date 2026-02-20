<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'Dashboard';

// Load Chart.js in header for dashboard
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

$db = getDBConnection();

// Get date range filter - default to show all complaints
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// If no date filter is set, show all complaints (use a very early date)
if ($dateFrom === null) {
    $dateFrom = '2020-01-01'; // Start from a date that will include all complaints
}
if ($dateTo === null) {
    $dateTo = date('Y-m-d', strtotime('+1 day')); // Include today
}

// Overall Statistics
$stats = $db->prepare("SELECT 
    COUNT(*) as total_complaints,
    COALESCE(SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END), 0) as new_count,
    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_count,
    COALESCE(SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END), 0) as resolved_count,
    COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) as closed_count,
    COALESCE(SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END), 0) as escalated_count,
    COALESCE(SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END), 0) as critical_count,
    COALESCE(SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END), 0) as high_count,
    COALESCE(SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END), 0) as medium_count,
    COALESCE(SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END), 0) as low_count
    FROM complaints 
    WHERE DATE(created_at) BETWEEN ? AND ?");
$stats->execute([$dateFrom, $dateTo]);
$overallStats = $stats->fetch();

// Complaints by Category
$categoryStats = $db->prepare("SELECT 
    cat.name as category_name,
    COUNT(c.id) as count
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.id
    WHERE DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY cat.id, cat.name
    ORDER BY count DESC
    LIMIT 10");
$categoryStats->execute([$dateFrom, $dateTo]);
$categoryData = $categoryStats->fetchAll();

// Complaints by Department
$deptStats = $db->prepare("SELECT 
    d.name as department_name,
    COUNT(c.id) as count,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM complaints c
    LEFT JOIN departments d ON c.assigned_department_id = d.id
    WHERE DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY d.id, d.name
    ORDER BY count DESC");
$deptStats->execute([$dateFrom, $dateTo]);
$deptData = $deptStats->fetchAll();

// Daily trend (last 30 days)
$trendData = $db->prepare("SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
    FROM complaints
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC");
$trendData->execute([$dateFrom, $dateTo]);
$trendDataResult = $trendData->fetchAll();

// SLA Compliance
$slaStats = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status IN ('resolved', 'closed') AND resolved_at <= sla_deadline THEN 1 ELSE 0 END) as on_time,
    SUM(CASE WHEN status IN ('resolved', 'closed') AND resolved_at > sla_deadline THEN 1 ELSE 0 END) as overdue,
    SUM(CASE WHEN status NOT IN ('resolved', 'closed') AND NOW() > sla_deadline THEN 1 ELSE 0 END) as pending_overdue
    FROM complaints
    WHERE sla_deadline IS NOT NULL
    AND DATE(created_at) BETWEEN ? AND ?");
$slaStats->execute([$dateFrom, $dateTo]);
$slaData = $slaStats->fetch();

// Average resolution time by priority
$resolutionTime = $db->prepare("SELECT 
    priority,
    AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
    FROM complaints
    WHERE status IN ('resolved', 'closed')
    AND resolved_at IS NOT NULL
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY priority");
$resolutionTime->execute([$dateFrom, $dateTo]);
$resolutionData = $resolutionTime->fetchAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')); ?>">
                    <input type="date" class="form-control" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <a href="/modules/dashboard/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card primary">
                <div class="card-body">
                    <h5 class="card-title">Total Complaints</h5>
                    <h2 class="mb-0"><?php echo $overallStats['total_complaints']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card warning">
                <div class="card-body">
                    <h5 class="card-title">In Progress</h5>
                    <h2 class="mb-0"><?php echo $overallStats['in_progress_count']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card success">
                <div class="card-body">
                    <h5 class="card-title">Resolved</h5>
                    <h2 class="mb-0"><?php echo $overallStats['resolved_count']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card danger">
                <div class="card-body">
                    <h5 class="card-title">Escalated</h5>
                    <h2 class="mb-0"><?php echo $overallStats['escalated_count']; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Status Distribution Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Complaints by Status</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Priority Distribution Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Complaints by Priority</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Category Distribution Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Categories</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trend Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Complaint Trends</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department Performance -->
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Department Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total Complaints</th>
                                    <th>Resolved</th>
                                    <th>Closed</th>
                                    <th>Resolution Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deptData as $dept): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_name'] ?? 'Unassigned'); ?></strong></td>
                                        <td><?php echo $dept['count']; ?></td>
                                        <td><?php echo $dept['resolved']; ?></td>
                                        <td><?php echo $dept['closed']; ?></td>
                                        <td>
                                            <?php 
                                            $rate = $dept['count'] > 0 ? (($dept['resolved'] + $dept['closed']) / $dept['count'] * 100) : 0;
                                            echo number_format($rate, 1); ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SLA Compliance -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">SLA Compliance</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="slaChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <p><strong>On Time:</strong> <?php echo $slaData['on_time'] ?? 0; ?></p>
                        <p><strong>Overdue:</strong> <?php echo $slaData['overdue'] ?? 0; ?></p>
                        <p><strong>Pending Overdue:</strong> <?php echo $slaData['pending_overdue'] ?? 0; ?></p>
                        <?php if (($slaData['on_time'] ?? 0) + ($slaData['overdue'] ?? 0) + ($slaData['pending_overdue'] ?? 0) == 0): ?>
                            <p class="text-muted"><small>No complaints with SLA deadlines in the selected period.</small></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Average Resolution Time -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Average Resolution Time (Hours)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="resolutionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for Chart.js to be available
function initCharts() {
    if (typeof Chart === 'undefined') {
        // Chart.js not loaded yet, wait a bit and try again
        setTimeout(initCharts, 100);
        return;
    }
    
    // Chart.js is available, initialize charts

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['New', 'In Progress', 'Resolved', 'Closed', 'Escalated'],
        datasets: [{
            data: [
                <?php echo $overallStats['new_count']; ?>,
                <?php echo $overallStats['in_progress_count']; ?>,
                <?php echo $overallStats['resolved_count']; ?>,
                <?php echo $overallStats['closed_count']; ?>,
                <?php echo $overallStats['escalated_count']; ?>
            ],
            backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#6c757d', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Priority Chart
const priorityCtx = document.getElementById('priorityChart').getContext('2d');
new Chart(priorityCtx, {
    type: 'bar',
    data: {
        labels: ['Critical', 'High', 'Medium', 'Low'],
        datasets: [{
            label: 'Complaints',
            data: [
                <?php echo $overallStats['critical_count']; ?>,
                <?php echo $overallStats['high_count']; ?>,
                <?php echo $overallStats['medium_count']; ?>,
                <?php echo $overallStats['low_count']; ?>
            ],
            backgroundColor: ['#212529', '#dc3545', '#ffc107', '#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
<?php 
$categoryLabels = !empty($categoryData) ? implode(',', array_map(function($item) { return "'" . addslashes($item['category_name'] ?? 'Unknown') . "'"; }, $categoryData)) : "'No Data'";
$categoryValues = !empty($categoryData) ? implode(',', array_column($categoryData, 'count')) : '0';
?>
new Chart(categoryCtx, {
    type: 'pie',
    data: {
        labels: [<?php echo $categoryLabels; ?>],
        datasets: [{
            data: [<?php echo $categoryValues; ?>],
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d', '#0dcaf0', '#fd7e14', '#6610f2', '#e83e8c', '#20c997']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
<?php 
$trendLabels = !empty($trendDataResult) ? implode(',', array_map(function($item) { return "'" . date('M d', strtotime($item['date'])) . "'"; }, $trendDataResult)) : "'" . date('M d') . "'";
$trendValues = !empty($trendDataResult) ? implode(',', array_column($trendDataResult, 'count')) : '0';
?>
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [<?php echo $trendLabels; ?>],
        datasets: [{
            label: 'Complaints',
            data: [<?php echo $trendValues; ?>],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// SLA Chart
const slaCtx = document.getElementById('slaChart').getContext('2d');
<?php 
$slaOnTime = $slaData['on_time'] ?? 0;
$slaOverdue = $slaData['overdue'] ?? 0;
$slaPending = $slaData['pending_overdue'] ?? 0;
$slaTotal = $slaOnTime + $slaOverdue + $slaPending;
?>
new Chart(slaCtx, {
    type: 'doughnut',
    data: {
        labels: ['On Time', 'Overdue', 'Pending Overdue'],
        datasets: [{
            data: [
                <?php echo $slaOnTime; ?>,
                <?php echo $slaOverdue; ?>,
                <?php echo $slaPending; ?>
            ],
            backgroundColor: ['#198754', '#dc3545', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Resolution Time Chart
const resolutionCtx = document.getElementById('resolutionChart').getContext('2d');
<?php 
if (empty($resolutionData)) {
    $resolutionLabels = "'Critical', 'High', 'Medium', 'Low'";
    $resolutionValues = "0, 0, 0, 0";
} else {
    $priorityOrder = ['critical', 'high', 'medium', 'low'];
    $resolutionByPriority = [];
    foreach ($resolutionData as $item) {
        $resolutionByPriority[$item['priority']] = round($item['avg_hours'], 1);
    }
    $resolutionLabels = implode(',', array_map(function($p) { return "'" . ucfirst($p) . "'"; }, $priorityOrder));
    $resolutionValues = implode(',', array_map(function($p) use ($resolutionByPriority) { return $resolutionByPriority[$p] ?? 0; }, $priorityOrder));
}
?>
new Chart(resolutionCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo $resolutionLabels; ?>],
        datasets: [{
            label: 'Average Hours',
            data: [<?php echo $resolutionValues; ?>],
            backgroundColor: ['#212529', '#dc3545', '#ffc107', '#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

} // End of initCharts function

// Start initialization when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
} else {
    // DOM is already ready
    initCharts();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
