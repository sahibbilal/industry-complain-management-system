<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">
                <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php"><i class="bi bi-house"></i> Home</a>
                    </li>
                    <?php if (hasAnyRole([ROLE_COMPLAINANT, ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/complaints/list.php"><i class="bi bi-list-ul"></i> Complaints</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/complaints/submit.php"><i class="bi bi-plus-circle"></i> Submit Complaint</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/modules/admin/departments.php">Departments</a></li>
                            <li><a class="dropdown-item" href="/modules/admin/users.php">Users</a></li>
                            <li><a class="dropdown-item" href="/modules/admin/categories.php">Categories</a></li>
                            <li><a class="dropdown-item" href="/modules/admin/settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/modules/admin/audit_logs.php">Audit Logs</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/modules/auth/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/modules/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="container-fluid mt-4">
        <?php
        $flash = getFlashMessage();
        if ($flash):
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
