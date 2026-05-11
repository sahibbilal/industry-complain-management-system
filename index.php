<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$db = getDBConnection();

// Get statistics for public view
$stats = null;
if (!isLoggedIn()) {
    try {
        $stats = $db->query("SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            COUNT(DISTINCT user_id) as total_users
            FROM complaints")->fetch();
    } catch (Exception $e) {
        $stats = null;
    }
}
?>

<!-- Hero Section -->
<?php if (!isLoggedIn()): ?>
<div class="hero-section bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="display-3 fw-bold mb-4">
                        <i class="bi bi-shield-check"></i> ICMS
                    </h1>
                    <h2 class="h3 mb-4">Industry Complaint Management System</h2>
                    <p class="lead mb-4">
                        Streamline your complaint management process with our comprehensive, 
                        user-friendly platform. Track, manage, and resolve complaints efficiently 
                        with real-time updates and automated workflows.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="/modules/auth/login.php" class="btn btn-light btn-lg px-4">
                            <i class="bi bi-box-arrow-in-right"></i> Login to Your Account
                        </a>
                        <a href="/modules/auth/register.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-person-plus"></i> Create New Account
                        </a>
                    </div>
                    <div class="d-flex gap-4 text-white-50">
                        <div>
                            <i class="bi bi-check-circle-fill text-success"></i> Free Registration
                        </div>
                        <div>
                            <i class="bi bi-check-circle-fill text-success"></i> 24/7 Support
                        </div>
                        <div>
                            <i class="bi bi-check-circle-fill text-success"></i> Secure Platform
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="hero-image mt-4 mt-lg-0">
                    <i class="bi bi-shield-check" style="font-size: 15rem; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<?php if ($stats): ?>
<div class="container mb-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-4">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-text text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="display-4 fw-bold text-primary mb-2"><?php echo number_format((float) ($stats['total_complaints'] ?? 0)); ?></h3>
                    <p class="text-muted mb-0">Total Complaints Processed</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-4">
                    <div class="mb-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="display-4 fw-bold text-success mb-2"><?php echo number_format((float) ($stats['resolved'] ?? 0)); ?></h3>
                    <p class="text-muted mb-0">Successfully Resolved</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-4">
                    <div class="mb-3">
                        <i class="bi bi-people text-info" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="display-4 fw-bold text-info mb-2"><?php echo number_format((float) ($stats['total_users'] ?? 0)); ?></h3>
                    <p class="text-muted mb-0">Registered Users</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Features Section -->
<div class="container mb-5">
    <div class="text-center mb-5">
        <h2 class="display-5 fw-bold mb-3">Why Choose ICMS?</h2>
        <p class="lead text-muted">Comprehensive features designed to streamline your complaint management</p>
    </div>
    
    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-lightning-charge-fill text-warning" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Fast & Efficient</h4>
                    <p class="text-muted mb-0">
                        Submit complaints in minutes with our intuitive interface. 
                        Automatic routing ensures your complaint reaches the right department instantly.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-graph-up-arrow text-success" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Real-Time Tracking</h4>
                    <p class="text-muted mb-0">
                        Monitor your complaint status in real-time. Get instant notifications 
                        on updates and track the complete history of your complaint.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-shield-lock-fill text-primary" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Secure & Private</h4>
                    <p class="text-muted mb-0">
                        Your data is protected with enterprise-grade security. 
                        Role-based access ensures only authorized personnel can view your complaints.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-clock-history text-info" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">SLA Management</h4>
                    <p class="text-muted mb-0">
                        Automatic escalation based on Service Level Agreements ensures 
                        timely resolution. Never miss a deadline with our smart reminders.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">File Attachments</h4>
                    <p class="text-muted mb-0">
                        Attach supporting documents, images, or files with your complaint. 
                        All attachments are securely stored and easily accessible.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 feature-card">
                <div class="card-body p-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-bar-chart-fill text-warning" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Analytics & Reports</h4>
                    <p class="text-muted mb-0">
                        Comprehensive dashboards and reports help you understand trends, 
                        performance metrics, and areas for improvement.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="bg-light py-5 mb-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">How It Works</h2>
            <p class="lead text-muted">Simple steps to get your complaint resolved</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-3 text-center">
                <div class="step-number bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                    1
                </div>
                <h5 class="fw-bold mb-3">Register & Login</h5>
                <p class="text-muted">
                    Create your free account in seconds. Just provide your basic information 
                    and you're ready to go.
                </p>
            </div>
            
            <div class="col-md-3 text-center">
                <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                    2
                </div>
                <h5 class="fw-bold mb-3">Submit Complaint</h5>
                <p class="text-muted">
                    Fill out the complaint form with details, select category, 
                    and attach any relevant documents.
                </p>
            </div>
            
            <div class="col-md-3 text-center">
                <div class="step-number bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                    3
                </div>
                <h5 class="fw-bold mb-3">Track Progress</h5>
                <p class="text-muted">
                    Monitor your complaint status in real-time. Receive updates 
                    as your complaint moves through the resolution process.
                </p>
            </div>
            
            <div class="col-md-3 text-center">
                <div class="step-number bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                    4
                </div>
                <h5 class="fw-bold mb-3">Get Resolved</h5>
                <p class="text-muted">
                    Receive resolution details and provide feedback. 
                    Help us improve our service quality.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="container mb-5">
    <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 48%, #0f766e 120%);">
        <div class="card-body text-center text-white p-5">
            <h2 class="display-5 fw-bold mb-3">Ready to Get Started?</h2>
            <p class="lead mb-4">
                Join thousands of users who trust ICMS for their complaint management needs.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="/modules/auth/register.php" class="btn btn-light btn-lg px-5">
                    <i class="bi bi-person-plus"></i> Create Free Account
                </a>
                <a href="/modules/complaints/track.php" class="btn btn-outline-light btn-lg px-5">
                    <i class="bi bi-search"></i> Track Existing Complaint
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Logged In User Dashboard -->
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!</h2>
                    <p class="text-muted mb-0">Manage your complaints efficiently</p>
                </div>
                <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                    <a href="/modules/complaints/submit.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Submit New Complaint
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if (hasRole(ROLE_COMPLAINANT)): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-quick-action">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-plus-circle-fill text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Submit Complaint</h4>
                    <p class="text-muted mb-4">
                        File a new complaint with detailed information and attachments
                    </p>
                    <a href="/modules/complaints/submit.php" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-plus-circle"></i> Submit Now
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-quick-action">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-search text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Track Complaint</h4>
                    <p class="text-muted mb-4">
                        Check the status of your complaints using tracking number
                    </p>
                    <a href="/modules/complaints/track.php" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-search"></i> Track Now
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-quick-action">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-list-ul text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-3">My Complaints</h4>
                    <p class="text-muted mb-4">
                        View and manage all your submitted complaints
                    </p>
                    <a href="/modules/complaints/list.php" class="btn btn-warning btn-lg w-100">
                        <i class="bi bi-list-ul"></i> View All
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (hasAnyRole([ROLE_SUPPORT_STAFF, ROLE_MANAGER, ROLE_ADMIN])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-speedometer2 text-primary"></i> Quick Access
                    </h5>
                    <div class="row g-3">
                        <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                        <div class="col-md-3">
                            <a href="/modules/dashboard/index.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <a href="/modules/complaints/list.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-list-ul"></i> All Complaints
                            </a>
                        </div>
                        <?php if (hasAnyRole([ROLE_ADMIN, ROLE_SENIOR_MANAGEMENT])): ?>
                        <div class="col-md-3">
                            <a href="/modules/admin/departments.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-gear"></i> Admin Panel
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (hasRole(ROLE_COMPLAINANT)): ?>
                        <div class="col-md-3">
                            <a href="/modules/feedback/view.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-star"></i> Feedback
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
