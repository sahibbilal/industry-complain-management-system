<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$pageTitle = 'Submit Feedback';

$db = getDBConnection();
$error = '';
$success = '';

$complaintId = intval($_GET['id'] ?? 0);

if (!$complaintId) {
    redirect('/modules/complaints/list.php');
}

// Get complaint details
$stmt = $db->prepare("SELECT c.*, 
    cat.name as category_name,
    u.name as user_name
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.id = ?");
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    setFlashMessage('danger', 'Complaint not found.');
    redirect('/modules/complaints/list.php');
}

// Check if user owns the complaint
if ($complaint['user_id'] != getCurrentUserId()) {
    setFlashMessage('danger', 'You can only provide feedback for your own complaints.');
    redirect('/modules/complaints/list.php');
}

// Check if complaint is resolved
if ($complaint['status'] != STATUS_RESOLVED && $complaint['status'] != STATUS_CLOSED) {
    setFlashMessage('warning', 'You can only provide feedback for resolved complaints.');
    redirect('/modules/complaints/view.php?id=' . $complaintId);
}

// Check if feedback already exists
$stmt = $db->prepare("SELECT * FROM feedback WHERE complaint_id = ? AND user_id = ?");
$stmt->execute([$complaintId, getCurrentUserId()]);
$existingFeedback = $stmt->fetch();

if ($existingFeedback) {
    setFlashMessage('info', 'You have already provided feedback for this complaint.');
    redirect('/modules/feedback/view.php?id=' . $complaintId);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = sanitizeInput($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } else {
        $stmt = $db->prepare("INSERT INTO feedback (complaint_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$complaintId, getCurrentUserId(), $rating, $comment])) {
            logActivity(getCurrentUserId(), 'feedback_submit', "Submitted feedback for complaint #{$complaint['tracking_number']}", $complaintId);
            $success = 'Thank you for your feedback!';
            
            // Redirect after 2 seconds
            header("refresh:2;url=/modules/complaints/view.php?id=" . $complaintId);
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-star"></i> Provide Feedback</h4>
                </div>
                <div class="card-body">
                    <!-- Complaint Info -->
                    <div class="alert alert-info mb-4">
                        <strong>Complaint:</strong> <?php echo htmlspecialchars($complaint['tracking_number']); ?><br>
                        <strong>Title:</strong> <?php echo htmlspecialchars($complaint['title']); ?><br>
                        <strong>Status:</strong> <?php echo getStatusBadge($complaint['status']); ?>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            <p class="mb-0 mt-2">Redirecting to complaint details...</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4 text-center">
                                <label class="form-label required-field"><strong>Rate Your Experience</strong></label>
                                <div class="rating-input mb-3">
                                    <input type="radio" id="rating5" name="rating" value="5" required>
                                    <label for="rating5" class="star-label">★</label>
                                    
                                    <input type="radio" id="rating4" name="rating" value="4">
                                    <label for="rating4" class="star-label">★</label>
                                    
                                    <input type="radio" id="rating3" name="rating" value="3">
                                    <label for="rating3" class="star-label">★</label>
                                    
                                    <input type="radio" id="rating2" name="rating" value="2">
                                    <label for="rating2" class="star-label">★</label>
                                    
                                    <input type="radio" id="rating1" name="rating" value="1">
                                    <label for="rating1" class="star-label">★</label>
                                </div>
                                <div id="rating-text" class="text-muted">Select a rating</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comment" class="form-label">Comments (Optional)</label>
                                <textarea class="form-control" id="comment" name="comment" rows="6" 
                                          placeholder="Please share your experience and any suggestions for improvement..."><?php echo htmlspecialchars($comment ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send"></i> Submit Feedback
                                </button>
                                <a href="/modules/complaints/view.php?id=<?php echo $complaintId; ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-input {
    display: flex;
    justify-content: center;
    gap: 5px;
    font-size: 2.5rem;
    direction: rtl;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input .star-label {
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input input[type="radio"]:checked ~ .star-label,
.rating-input .star-label:hover,
.rating-input .star-label:hover ~ .star-label {
    color: #ffc107;
}

.rating-input input[type="radio"]:checked ~ .star-label {
    color: #ffc107;
}
</style>

<script>
const ratingInputs = document.querySelectorAll('input[name="rating"]');
const ratingText = document.getElementById('rating-text');
const ratingTexts = {
    1: 'Poor - Very dissatisfied',
    2: 'Fair - Somewhat dissatisfied',
    3: 'Good - Satisfied',
    4: 'Very Good - Very satisfied',
    5: 'Excellent - Extremely satisfied'
};

ratingInputs.forEach(input => {
    input.addEventListener('change', function() {
        ratingText.textContent = ratingTexts[this.value] || 'Select a rating';
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
