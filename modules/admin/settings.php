<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT]);
$pageTitle = 'System Settings';

$db = getDBConnection();
$error = '';
$success = '';

// Handle SLA rules update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sla'])) {
    $priorities = [PRIORITY_LOW, PRIORITY_MEDIUM, PRIORITY_HIGH, PRIORITY_CRITICAL];
    
    foreach ($priorities as $priority) {
        $responseTime = intval($_POST["response_time_{$priority}"] ?? 0);
        $resolutionTime = intval($_POST["resolution_time_{$priority}"] ?? 0);
        $escalationTime = intval($_POST["escalation_time_{$priority}"] ?? 0);
        
        $stmt = $db->prepare("UPDATE sla_rules SET response_time_hours = ?, resolution_time_hours = ?, escalation_time_hours = ? WHERE priority = ?");
        $stmt->execute([$responseTime, $resolutionTime, $escalationTime, $priority]);
    }
    
    logActivity(getCurrentUserId(), 'settings_update', 'Updated SLA rules');
    $success = 'SLA rules updated successfully.';
}

// Get SLA rules
$slaRules = $db->query("SELECT * FROM sla_rules ORDER BY 
    CASE priority 
        WHEN 'critical' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END")->fetchAll();

$slaData = [];
foreach ($slaRules as $rule) {
    $slaData[$rule['priority']] = $rule;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-gear"></i> System Settings</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> SLA (Service Level Agreement) Rules</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_sla" value="1">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Priority</th>
                                        <th>Response Time (Hours)</th>
                                        <th>Resolution Time (Hours)</th>
                                        <th>Escalation Time (Hours)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $priorities = [
                                        PRIORITY_CRITICAL => 'Critical',
                                        PRIORITY_HIGH => 'High',
                                        PRIORITY_MEDIUM => 'Medium',
                                        PRIORITY_LOW => 'Low'
                                    ];
                                    
                                    foreach ($priorities as $priority => $label): 
                                        $rule = $slaData[$priority] ?? [];
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $label; ?></strong></td>
                                            <td>
                                                <input type="number" class="form-control" 
                                                       name="response_time_<?php echo $priority; ?>" 
                                                       value="<?php echo $rule['response_time_hours'] ?? 0; ?>" 
                                                       min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" 
                                                       name="resolution_time_<?php echo $priority; ?>" 
                                                       value="<?php echo $rule['resolution_time_hours'] ?? 0; ?>" 
                                                       min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" 
                                                       name="escalation_time_<?php echo $priority; ?>" 
                                                       value="<?php echo $rule['escalation_time_hours'] ?? 0; ?>" 
                                                       min="0">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update SLA Rules
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">System Name</dt>
                        <dd class="col-sm-9"><?php echo SITE_NAME; ?></dd>
                        
                        <dt class="col-sm-3">PHP Version</dt>
                        <dd class="col-sm-9"><?php echo PHP_VERSION; ?></dd>
                        
                        <dt class="col-sm-3">Database</dt>
                        <dd class="col-sm-9">MySQL/MariaDB</dd>
                        
                        <dt class="col-sm-3">Total Users</dt>
                        <dd class="col-sm-9">
                            <?php 
                            $stmt = $db->query("SELECT COUNT(*) FROM users");
                            echo $stmt->fetchColumn(); 
                            ?>
                        </dd>
                        
                        <dt class="col-sm-3">Total Departments</dt>
                        <dd class="col-sm-9">
                            <?php 
                            $stmt = $db->query("SELECT COUNT(*) FROM departments");
                            echo $stmt->fetchColumn(); 
                            ?>
                        </dd>
                        
                        <dt class="col-sm-3">Total Complaints</dt>
                        <dd class="col-sm-9">
                            <?php 
                            $stmt = $db->query("SELECT COUNT(*) FROM complaints");
                            echo $stmt->fetchColumn(); 
                            ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
