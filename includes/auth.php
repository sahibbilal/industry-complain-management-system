<?php
/**
 * Authentication and Authorization Functions
 * ICMS - Industry Complaint Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
        logoutUser();
        redirect('/modules/auth/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
} elseif (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch();
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return getCurrentUserRole() === $role;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    $userRole = getCurrentUserRole();
    return in_array($userRole, $roles);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/modules/auth/login.php');
    }
}

/**
 * Require specific role - redirect if user doesn't have role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        setFlashMessage('danger', 'You do not have permission to access this page.');
        redirect('/index.php');
    }
}

/**
 * Require any of the specified roles
 */
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        setFlashMessage('danger', 'You do not have permission to access this page.');
        redirect('/index.php');
    }
}

/**
 * Login user
 */
function loginUser($email, $password) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['last_activity'] = time();
        
        // Log login activity
        logActivity($user['id'], 'login', 'User logged in');
        
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    if (isLoggedIn()) {
        logActivity(getCurrentUserId(), 'logout', 'User logged out');
    }
    
    session_unset();
    session_destroy();
}

/**
 * Register new user
 */
function registerUser($name, $email, $password, $role = ROLE_COMPLAINANT) {
    $db = getDBConnection();
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    
    try {
        $stmt->execute([$name, $email, $hashedPassword, $role]);
        $userId = $db->lastInsertId();
        
        // Log registration
        logActivity($userId, 'register', 'New user registered');
        
        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

/**
 * Log activity to audit log
 */
function logActivity($userId, $action, $description, $complaintId = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description, complaint_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->execute([$userId, $action, $description, $complaintId, $ipAddress]);
}
?>
