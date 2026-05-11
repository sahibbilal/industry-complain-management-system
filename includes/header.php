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
<body class="<?php echo isLoggedIn() ? 'app-authenticated' : 'app-public'; ?>">
    <?php if (isLoggedIn()): ?>
    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="sidebar-top d-flex align-items-center">
                <a class="sidebar-brand" href="/index.php">
                    <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?>
                </a>
                <button class="navbar-toggler sidebar-toggler d-lg-none ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse sidebar-collapse" id="navbarNav">
                <ul class="navbar-nav sidebar-nav me-auto w-100">
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
                    <?php if (hasAnyRole([ROLE_ADMIN])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/admin/departments.php"><i class="bi bi-building"></i> Departments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/admin/users.php"><i class="bi bi-people"></i> Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/admin/categories.php"><i class="bi bi-tags"></i> Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/admin/settings.php"><i class="bi bi-sliders"></i> Settings</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/modules/admin/audit_logs.php"><i class="bi bi-journal-text"></i> Audit Logs</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="sidebar-divider"></div>

                <div class="sidebar-user">
                    <span class="sidebar-user-label">Signed in as</span>
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                </div>

                <ul class="navbar-nav sidebar-nav sidebar-account-links w-100 mt-2">
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/auth/profile.php"><i class="bi bi-person-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </aside>

        <div class="app-content-wrapper">
    <?php endif; ?>

    <main class="container-fluid mt-4 app-content">
        <?php
        $flash = getFlashMessage();
        if ($flash):
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
