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
 * Route a complaint to the most relevant department.
 *
 * Priority order:
 * 1. The selected category's default department.
 * 2. Keyword matches from active categories.
 */
function routeComplaintDepartment($categoryId, $title, $description, $db) {
    if (!empty($categoryId)) {
        $stmt = $db->prepare("SELECT c.default_department_id FROM complaint_categories c LEFT JOIN departments d ON c.default_department_id = d.id WHERE c.id = ? AND c.status = 'active' AND c.default_department_id IS NOT NULL AND d.status = 'active' LIMIT 1");
        $stmt->execute([$categoryId]);
        $departmentId = $stmt->fetchColumn();

        if (!empty($departmentId)) {
            return (int) $departmentId;
        }
    }

    $searchText = strtolower(trim($title . ' ' . $description));
    $categories = $db->query("SELECT c.name, c.default_department_id, c.keywords FROM complaint_categories c INNER JOIN departments d ON c.default_department_id = d.id WHERE c.status = 'active' AND c.default_department_id IS NOT NULL AND d.status = 'active' AND c.keywords IS NOT NULL AND c.keywords != ''")->fetchAll();

    foreach ($categories as $category) {
        $keywords = array_filter(array_map('trim', explode(',', strtolower((string) $category['keywords']))));
        $categoryTerms = array_filter(array_map('trim', preg_split('/\s+/', strtolower((string) $category['name']))));
        $terms = array_unique(array_merge($keywords, $categoryTerms));

        foreach ($terms as $term) {
            if ($term !== '' && stripos($searchText, $term) !== false) {
                return (int) $category['default_department_id'];
            }
        }
    }

    return null;
}

/**
 * Select an active department user to handle a complaint.
 */
function getDepartmentComplaintAssignee($departmentId, $db) {
    if (empty($departmentId)) {
        return null;
    }

    $stmt = $db->prepare("SELECT u.id
        FROM users u
        WHERE u.department_id = ?
          AND u.status = 'active'
          AND u.role IN (?, ?, ?)
        ORDER BY FIELD(u.role, ?, ?, ?), u.id ASC
        LIMIT 1");

    $stmt->execute([
        (int) $departmentId,
        ROLE_SUPPORT_STAFF,
        ROLE_MANAGER,
        ROLE_QA_OFFICER,
        ROLE_SUPPORT_STAFF,
        ROLE_MANAGER,
        ROLE_QA_OFFICER
    ]);

    $assigneeId = $stmt->fetchColumn();
    return !empty($assigneeId) ? (int) $assigneeId : null;
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
