<?php
/**
 * Email Configuration
 * ICMS - Industry Complaint Management System
 */

require_once __DIR__ . '/constants.php';

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $altMessage Plain text alternative
 * @return bool Success status
 */
function sendEmail($to, $subject, $message, $altMessage = '') {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">" . "\r\n";
    
    if (empty($altMessage)) {
        $altMessage = strip_tags($message);
    }
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Send complaint submission confirmation email
 */
function sendComplaintConfirmationEmail($userEmail, $complaintId, $complaintTitle) {
    $subject = "Complaint Submitted - #" . $complaintId;
    $message = "
    <html>
    <body>
        <h2>Complaint Submitted Successfully</h2>
        <p>Your complaint has been received and assigned ID: <strong>#" . $complaintId . "</strong></p>
        <p><strong>Title:</strong> " . htmlspecialchars($complaintTitle) . "</p>
        <p>You can track your complaint status using the tracking ID: <strong>" . $complaintId . "</strong></p>
        <p>Thank you for using ICMS.</p>
    </body>
    </html>";
    
    return sendEmail($userEmail, $subject, $message);
}

/**
 * Send status update notification email
 */
function sendStatusUpdateEmail($userEmail, $complaintId, $complaintTitle, $newStatus) {
    $subject = "Complaint Status Updated - #" . $complaintId;
    $message = "
    <html>
    <body>
        <h2>Complaint Status Update</h2>
        <p>Your complaint #" . $complaintId . " status has been updated.</p>
        <p><strong>Title:</strong> " . htmlspecialchars($complaintTitle) . "</p>
        <p><strong>New Status:</strong> " . ucfirst(str_replace('_', ' ', $newStatus)) . "</p>
        <p>You can track your complaint using the tracking ID: <strong>" . $complaintId . "</strong></p>
    </body>
    </html>";
    
    return sendEmail($userEmail, $subject, $message);
}

/**
 * Send escalation notification email
 */
function sendEscalationEmail($managerEmail, $complaintId, $complaintTitle, $reason) {
    $subject = "Complaint Escalated - #" . $complaintId;
    $message = "
    <html>
    <body>
        <h2>Complaint Escalation Alert</h2>
        <p>A complaint has been escalated to your attention.</p>
        <p><strong>Complaint ID:</strong> #" . $complaintId . "</p>
        <p><strong>Title:</strong> " . htmlspecialchars($complaintTitle) . "</p>
        <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
        <p>Please review and take appropriate action.</p>
    </body>
    </html>";
    
    return sendEmail($managerEmail, $subject, $message);
}
?>
