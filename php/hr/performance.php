<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /university-system/login.html");
    exit();
}

// Check if user has HR or admin role
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: /university-system/unauthorized.php");
    exit();
}

// Handle messages
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Schedule appraisal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_appraisal'])) {
    $employee_id = $_POST['employee_id'];
    $review_date = $_POST['review_date'];
    $reviewer_id = $_POST['reviewer_id'];

    try {
        // Validate inputs
        if (empty($employee_id) || empty($review_date) || empty($reviewer_id)) {
            throw new Exception("All fields are required");
        }

        // Check if reviewer is valid HR staff
        $check_reviewer = $pdo->prepare("
            SELECT id FROM users 
            WHERE id = ? AND status = 'active' 
            AND (role = 'admin' OR EXISTS (SELECT 1 FROM hr_staff WHERE user_id = ?))
        ");

        $check_reviewer->execute([$reviewer_id, $reviewer_id]);
        if (!$check_reviewer->fetch()) {
            throw new Exception("Selected reviewer is not a valid HR staff member or admin");
        }

        // Check if the selected date is in the future
        $today = new DateTime();
        $selected_date = new DateTime($review_date);
        if ($selected_date <= $today) {
            throw new Exception("Review date must be in the future");
        }

        // Check if the employee already has a scheduled review
        $check_stmt = $pdo->prepare("SELECT id FROM performance_reviews 
                                    WHERE employee_id = ? AND review_date > CURDATE()");
        $check_stmt->execute([$employee_id]);
        if ($check_stmt->fetch()) {
            throw new Exception("This employee already has a scheduled review");
        }

        // Schedule the review
        $stmt = $pdo->prepare("INSERT INTO performance_reviews 
                              (employee_id, review_date, reviewer_id) 
                              VALUES (?, ?, ?)");

        if ($stmt->execute([$employee_id, $review_date, $reviewer_id])) {
            $_SESSION['message'] = "Appraisal scheduled successfully";
        } else {
            throw new Exception("Database error: " . implode(" ", $stmt->errorInfo()));
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error scheduling appraisal: " . $e->getMessage();
    }
    header("Location: performance.php");
    exit();
}

// Conduct appraisal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['conduct_appraisal'])) {
    $review_id = $_POST['review_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    try {
        // Validate inputs
        if (empty($review_id) || empty($rating) || empty($comments)) {
            throw new Exception("All fields are required");
        }

        if ($rating < 1 || $rating > 5) {
            throw new Exception("Rating must be between 1 and 5");
        }

        // Check if review exists and hasn't been completed yet
        $check_stmt = $pdo->prepare("SELECT id FROM performance_reviews 
                                    WHERE id = ? AND rating IS NULL");
        $check_stmt->execute([$review_id]);
        if (!$check_stmt->fetch()) {
            throw new Exception("Review not found or already completed");
        }

        // Complete the review
        $stmt = $pdo->prepare("UPDATE performance_reviews 
                              SET rating = ?, comments = ? 
                              WHERE id = ?");

        if ($stmt->execute([$rating, $comments, $review_id])) {
            $_SESSION['message'] = "Appraisal completed successfully";
        } else {
            throw new Exception("Database error: " . implode(" ", $stmt->errorInfo()));
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error completing appraisal: " . $e->getMessage();
    }
    header("Location: performance.php");
    exit();
}

// Get performance data for all staff types
$performance = $pdo->query("
    SELECT pr.*, 
           u.first_name, u.last_name, u.role,
           r.first_name as reviewer_first, r.last_name as reviewer_last,
           CASE 
               WHEN u.role = 'faculty' THEN f.department
               WHEN u.role = 'finance' THEN fs.department
               WHEN u.role = 'campus' THEN cs.department
               WHEN u.role = 'hr' THEN hs.department
               ELSE 'Administration'
           END as department,
           CASE
               WHEN pr.rating IS NOT NULL THEN 'completed'
               WHEN pr.review_date > CURDATE() THEN 'scheduled'
               ELSE 'pending'
           END as status
    FROM performance_reviews pr
    JOIN users u ON pr.employee_id = u.id
    JOIN users r ON pr.reviewer_id = r.id
    LEFT JOIN faculty f ON u.id = f.user_id AND u.role = 'faculty'
    LEFT JOIN finance_staff fs ON u.id = fs.user_id AND u.role = 'finance'
    LEFT JOIN campus_staff cs ON u.id = cs.user_id AND u.role = 'campus'
    LEFT JOIN hr_staff hs ON u.id = hs.user_id AND u.role = 'hr'
    WHERE pr.review_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ORDER BY 
        CASE 
            WHEN pr.rating IS NOT NULL THEN 2
            WHEN pr.review_date > CURDATE() THEN 1
            ELSE 0
        END,
        pr.review_date DESC
")->fetchAll();

// Get all active staff for dropdown
$staff = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.role,
           CASE 
               WHEN u.role = 'faculty' THEN f.department
               WHEN u.role = 'finance' THEN fs.department
               WHEN u.role = 'campus' THEN cs.department
               WHEN u.role = 'hr' THEN hs.department
               ELSE 'Administration'
           END as department
    FROM users u
    LEFT JOIN faculty f ON u.id = f.user_id AND u.role = 'faculty'
    LEFT JOIN finance_staff fs ON u.id = fs.user_id AND u.role = 'finance'
    LEFT JOIN campus_staff cs ON u.id = cs.user_id AND u.role = 'campus'
    LEFT JOIN hr_staff hs ON u.id = hs.user_id AND u.role = 'hr'
    WHERE u.status = 'active' AND u.role IN ('faculty', 'admin', 'hr', 'finance', 'campus')
    ORDER BY u.role, u.last_name, u.first_name
")->fetchAll();

// Get all eligible reviewers (HR staff + admins)
$reviewers = $pdo->query("
    SELECT u.id, u.first_name, u.last_name 
    FROM users u
    WHERE u.status = 'active' AND (u.role = 'admin' OR u.role = 'hr')
    ORDER BY u.last_name, u.first_name
")->fetchAll();

// Get review details if viewing or conducting
$current_review = null;
if (isset($_GET['action']) && in_array($_GET['action'], ['view', 'conduct']) && isset($_GET['id'])) {
    $review_id = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT pr.*, 
               u.first_name, u.last_name, u.role,
               r.first_name as reviewer_first, r.last_name as reviewer_last,
               CASE 
                   WHEN u.role = 'faculty' THEN f.department
                   WHEN u.role = 'finance' THEN fs.department
                   WHEN u.role = 'campus' THEN cs.department
                   WHEN u.role = 'hr' THEN hs.department
                   ELSE 'Administration'
               END as department,
               CASE
                   WHEN pr.rating IS NOT NULL THEN 'completed'
                   WHEN pr.review_date > CURDATE() THEN 'scheduled'
                   ELSE 'pending'
               END as status
        FROM performance_reviews pr
        JOIN users u ON pr.employee_id = u.id
        JOIN users r ON pr.reviewer_id = r.id
        LEFT JOIN faculty f ON u.id = f.user_id AND u.role = 'faculty'
        LEFT JOIN finance_staff fs ON u.id = fs.user_id AND u.role = 'finance'
        LEFT JOIN campus_staff cs ON u.id = cs.user_id AND u.role = 'campus'
        LEFT JOIN hr_staff hs ON u.id = hs.user_id AND u.role = 'hr'
        WHERE pr.id = ?
    ");
    $stmt->execute([$review_id]);
    $current_review = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Management</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-badge.scheduled {
            background-color: #FFF3CD;
            color: #856404;
        }

        .status-badge.completed {
            background-color: #D4EDDA;
            color: #155724;
        }

        .status-badge.pending {
            background-color: #F8D7DA;
            color: #721C24;
        }

        .rating-stars {
            color: #FFD700;
            font-size: 16px;
        }

        .rating-stars .far {
            color: #D3D3D3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 60%;
            max-width: 600px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .review-details .detail-row {
            margin-bottom: 10px;
        }

        .review-details .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        .rating-input i {
            cursor: pointer;
            font-size: 24px;
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Performance Management</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="employee_profiles.php">Staff Profiles</a></li>
                        <li><a href="recruitment.php">Recruitment</a></li>
                        <li><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li class="active"><a href="performance.php">Performance</a></li>
                        <li><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if ($message): ?>
                    <div class="alert <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="action-bar">
                    <h2>Performance Reviews</h2>
                    <div class="search-bar">
                        <button class="logout-btn" onclick="showModal('appraisalModal')">
                            <i class="fas fa-calendar-plus"></i> Schedule Appraisal
                        </button>
                    </div>
                </div>

                <div class="hr-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Review Date</th>
                                <th>Reviewer</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance as $review): ?>
                                <tr>
                                    <td><?= htmlspecialchars($review['last_name'] . ', ' . $review['first_name']) ?></td>
                                    <td><?= ucfirst(htmlspecialchars($review['role'])) ?></td>
                                    <td><?= htmlspecialchars($review['department']) ?></td>
                                    <td><?= date('M j, Y', strtotime($review['review_date'])) ?></td>
                                    <td><?= htmlspecialchars($review['reviewer_last'] . ', ' . $review['reviewer_first']) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $review['status'] ?>">
                                            <?= ucfirst($review['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($review['rating']): ?>
                                            <div class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'filled' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="logout-btn" onclick="showReviewDetails(<?= $review['id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($review['status'] == 'scheduled' && ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $review['reviewer_id'])): ?>
                                            <button class="btn-small" onclick="showConductForm(<?= $review['id'] ?>)">
                                                <i class="fas fa-clipboard-check"></i> Conduct
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Appraisal Modal -->
    <div class="modal" id="appraisalModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('appraisalModal')">&times;</span>
            <h2>Schedule Performance Appraisal</h2>
            <form method="post" action="performance.php">
                <div class="form-group">
                    <label for="employee_id">Staff Member</label>
                    <select name="employee_id" id="employee_id" required>
                        <option value="">Select Staff</option>
                        <?php foreach ($staff as $member): ?>
                            <option value="<?= $member['id'] ?>">
                                <?= htmlspecialchars($member['last_name'] . ', ' . $member['first_name'] . ' (' . ucfirst($member['role']) . ' - ' . $member['department'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="review_date">Review Date</label>
                    <input type="date" name="review_date" id="review_date" required>
                </div>
                <div class="form-group">
                    <label for="reviewer_id">Reviewer</label>
                    <select name="reviewer_id" id="reviewer_id" required>
                        <option value="">Select Reviewer</option>
                        <?php foreach ($reviewers as $reviewer): ?>
                            <option value="<?= $reviewer['id'] ?>">
                                <?= htmlspecialchars($reviewer['last_name'] . ', ' . $reviewer['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="schedule_appraisal" class="logout-btn">
                    <i class="fas fa-calendar-check"></i> Schedule
                </button>
            </form>
        </div>
    </div>

    <!-- Review Details Modal -->
    <div class="modal" id="reviewDetailsModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('reviewDetailsModal')">&times;</span>
            <h2>Performance Review Details</h2>
            <?php if ($current_review && isset($_GET['action']) && $_GET['action'] == 'view'): ?>
                <div class="review-details">
                    <div class="detail-row">
                        <span class="detail-label">Staff Member:</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($current_review['last_name'] . ', ' . $current_review['first_name']) ?>
                            (<?= ucfirst($current_review['role']) ?> - <?= $current_review['department'] ?>)
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Review Date:</span>
                        <span class="detail-value"><?= date('F j, Y', strtotime($current_review['review_date'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reviewer:</span>
                        <span
                            class="detail-value"><?= htmlspecialchars($current_review['reviewer_last'] . ', ' . $current_review['reviewer_first']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="status-badge <?= $current_review['status'] ?>">
                            <?= ucfirst($current_review['status']) ?>
                        </span>
                    </div>
                    <?php if ($current_review['rating']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Rating:</span>
                            <span class="detail-value">
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $current_review['rating'] ? 'filled' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Comments:</span>
                            <span class="detail-value"><?= nl2br(htmlspecialchars($current_review['comments'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conduct Appraisal Modal -->
    <div class="modal" id="conductAppraisalModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('conductAppraisalModal')">&times;</span>
            <h2>Conduct Performance Appraisal</h2>
            <?php if ($current_review && isset($_GET['action']) && $_GET['action'] == 'conduct'): ?>
                <form method="post" action="performance.php">
                    <input type="hidden" name="review_id" value="<?= $current_review['id'] ?>">

                    <div class="review-info">
                        <div class="info-item">
                            <span class="info-label">Staff:</span>
                            <span class="info-value">
                                <?= htmlspecialchars($current_review['last_name'] . ', ' . $current_review['first_name']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Department:</span>
                            <span class="info-value"><?= htmlspecialchars($current_review['department']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Review Date:</span>
                            <span class="info-value"><?= date('F j, Y', strtotime($current_review['review_date'])) ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rating">Overall Rating (1-5)</label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star" data-rating="<?= $i ?>" onclick="setRating(this)"></i>
                            <?php endfor; ?>
                            <input type="hidden" name="rating" id="rating" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comments">Comments</label>
                        <textarea name="comments" id="comments" rows="5" required></textarea>
                    </div>

                    <button type="submit" name="conduct_appraisal" class="logout-btn">
                        <i class="fas fa-check-circle"></i> Complete Appraisal
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize date picker with minimum date of tomorrow
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            const dd = String(tomorrow.getDate()).padStart(2, '0');
            const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
            const yyyy = tomorrow.getFullYear();

            document.getElementById('review_date').min = `${yyyy}-${mm}-${dd}`;
            document.getElementById('review_date').value = `${yyyy}-${mm}-${dd}`;
        });

        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            // Remove action parameters from URL
            if (window.location.search.includes('action=')) {
                history.replaceState(null, '', window.location.pathname);
            }
        }

        function showReviewDetails(reviewId) {
            window.location.href = 'performance.php?action=view&id=' + reviewId;
        }

        function showConductForm(reviewId) {
            window.location.href = 'performance.php?action=conduct&id=' + reviewId;
        }

        // Rating stars functionality
        function setRating(star) {
            const rating = parseInt(star.getAttribute('data-rating'));
            document.getElementById('rating').value = rating;

            const stars = document.querySelectorAll('.rating-input .fa-star');
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        }

        // Show appropriate modal based on URL parameters
        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            const id = urlParams.get('id');

            if (action === 'view' && id) {
                showModal('reviewDetailsModal');
            } else if (action === 'conduct' && id) {
                showModal('conductAppraisalModal');
            }
        };
    </script>
</body>

</html>