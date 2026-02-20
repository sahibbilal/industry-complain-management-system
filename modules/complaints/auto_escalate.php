<?php
/**
 * Automatic Escalation Script
 * This should be run as a cron job to check for complaints that need escalation
 * Run: php modules/complaints/auto_escalate.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/email.php';

$db = getDBConnection();

// Get all active SLA rules
$slaRules = $db->query("SELECT * FROM sla_rules WHERE status = 'active'")->fetchAll();
$slaByPriority = [];
foreach ($slaRules as $rule) {
    $slaByPriority[$rule['priority']] = $rule;
}

// Get complaints that are not resolved/closed and past their escalation time
$complaints = $db->query("SELECT c.*, 
    u.email as user_email,
    d.name as department_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.assigned_department_id = d.id
    WHERE c.status NOT IN ('resolved', 'closed')
    AND c.status != 'escalated'
    AND c.sla_deadline IS NOT NULL
    AND NOW() > c.sla_deadline
    ORDER BY c.priority DESC, c.created_at ASC")->fetchAll();

$escalatedCount = 0;

foreach ($complaints as $complaint) {
    $priority = $complaint['priority'];
    $slaRule = $slaByPriority[$priority] ?? null;
    
    if (!$slaRule || !$slaRule['escalation_time_hours']) {
        continue;
    }
    
    // Calculate escalation deadline
    $escalationDeadline = date('Y-m-d H:i:s', strtotime($complaint['created_at'] . " +{$slaRule['escalation_time_hours']} hours"));
    
    // Check if past escalation time
    if (strtotime($escalationDeadline) < time()) {
        // Find a manager to escalate to
        $stmt = $db->prepare("SELECT * FROM users WHERE role IN ('manager', 'senior_management', 'admin') AND status = 'active' ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $manager = $stmt->fetch();
        
        if ($manager) {
            // Update complaint
            $stmt = $db->prepare("UPDATE complaints SET status = ?, assigned_user_id = ? WHERE id = ?");
            $stmt->execute([STATUS_ESCALATED, $manager['id'], $complaint['id']]);
            
            // Add status history
            $stmt = $db->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$complaint['id'], $complaint['status'], STATUS_ESCALATED, 1, 'Automatically escalated due to SLA deadline']);
            
            // Create escalation record
            $reason = "Automatic escalation: Complaint exceeded SLA deadline for {$priority} priority";
            $stmt = $db->prepare("INSERT INTO escalations (complaint_id, escalated_from, escalated_to, reason, priority_before, priority_after, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$complaint['id'], null, $manager['id'], $reason, $priority, $priority]);
            
            // Send escalation email
            sendEscalationEmail($manager['email'], $complaint['tracking_number'], $complaint['title'], $reason);
            
            $escalatedCount++;
        }
    }
}

echo "Auto-escalation completed. Escalated $escalatedCount complaint(s).\n";
?>
