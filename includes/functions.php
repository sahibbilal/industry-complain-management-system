<?php
/**
 * Utility Functions
 * ICMS - Industry Complaint Management System
 */

require_once __DIR__ . '/../config/constants.php';

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        STATUS_NEW => '<span class="badge bg-primary">New</span>',
        STATUS_IN_PROGRESS => '<span class="badge bg-warning">In Progress</span>',
        STATUS_RESOLVED => '<span class="badge bg-success">Resolved</span>',
        STATUS_CLOSED => '<span class="badge bg-secondary">Closed</span>',
        STATUS_ESCALATED => '<span class="badge bg-danger">Escalated</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Get priority badge HTML
 */
function getPriorityBadge($priority) {
    $badges = [
        PRIORITY_LOW => '<span class="badge bg-info">Low</span>',
        PRIORITY_MEDIUM => '<span class="badge bg-warning">Medium</span>',
        PRIORITY_HIGH => '<span class="badge bg-danger">High</span>',
        PRIORITY_CRITICAL => '<span class="badge bg-dark">Critical</span>'
    ];
    
    return $badges[$priority] ?? '<span class="badge bg-secondary">' . ucfirst($priority) . '</span>';
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_FILE_TYPES);
}

/**
 * Calculate text similarity percentage
 */
function calculateSimilarity($text1, $text2) {
    similar_text(strtolower($text1), strtolower($text2), $percent);
    return round($percent, 2);
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Show flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
