<?php
/**
 * System Constants
 * ICMS - Industry Complaint Management System
 */

// User Roles
define('ROLE_COMPLAINANT', 'complainant');
define('ROLE_SUPPORT_STAFF', 'support_staff');
define('ROLE_MANAGER', 'manager');
define('ROLE_QA_OFFICER', 'qa_officer');
define('ROLE_ADMIN', 'admin');
define('ROLE_SENIOR_MANAGEMENT', 'senior_management');

// Complaint Status
define('STATUS_NEW', 'new');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_RESOLVED', 'resolved');
define('STATUS_CLOSED', 'closed');
define('STATUS_ESCALATED', 'escalated');

// Priority Levels
define('PRIORITY_LOW', 'low');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_HIGH', 'high');
define('PRIORITY_CRITICAL', 'critical');

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// System Settings
define('SITE_NAME', 'ICMS - Industry Complaint Management System');
define('SITE_URL', 'http://localhost');

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour

// Email Settings
define('EMAIL_FROM', 'noreply@icms.local');
define('EMAIL_FROM_NAME', 'ICMS System');

// Duplicate Detection
define('DUPLICATE_SIMILARITY_THRESHOLD', 80); // Percentage
?>
