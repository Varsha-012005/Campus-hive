<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Redirect if not logged in (optional - you might want to handle this differently)
if (!$isLoggedIn) {
    header("Location: /university-system/login.html");
    exit();
}

// Get user info
$user_stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

$user_email = $user['email'] ?? '';
$user_fullname = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');

// Handle job application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job'])) {
    $job_id = $_POST['job_id'];
    $applicant_name = $user_fullname;
    $applicant_email = $user_email;

    // Validate phone number
    $applicant_phone = filter_var($_POST['applicant_phone'], FILTER_SANITIZE_STRING);
    if (!preg_match('/^[0-9\+\-\(\) ]+$/', $applicant_phone)) {
        $_SESSION['error'] = "Invalid phone number format";
        header("Location: recruitment.php");
        exit();
    }

    // Validate file type
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    if (isset($_FILES['resume']) && !in_array($_FILES['resume']['type'], $allowed_types)) {
        $_SESSION['error'] = "Only PDF and DOC/DOCX files are allowed";
        header("Location: recruitment.php");
        exit();
    }

    $applicant_address = $_POST['applicant_address'] ?? '';
    $cover_letter = $_POST['cover_letter'];

    // Handle file upload
    $resume_path = '';
    if (isset($_FILES['resume'])) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/university-system/uploads/resumes/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES['resume']['name']);
        $target_file = $upload_dir . $file_name;

        if ($_FILES['resume']['size'] > 5000000) {
            $_SESSION['error'] = "File is too large (max 5MB)";
            header("Location: recruitment.php");
            exit();
        }

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            $resume_path = $file_name;
        } else {
            $_SESSION['error'] = "Error uploading file";
            header("Location: recruitment.php");
            exit();
        }
    }

    // Insert application
    $stmt = $pdo->prepare("INSERT INTO job_applications 
                          (job_id, applicant_name, applicant_email, applicant_phone, 
                           application_date, cover_letter, resume_path, status, applicant_address) 
                          VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', ?)");
    $stmt->execute([
        $job_id,
        $applicant_name,
        $applicant_email,
        $applicant_phone,
        $cover_letter,
        $resume_path,
        $applicant_address
    ]);

    $_SESSION['message'] = "Application submitted successfully!";
    header("Location: recruitment.php");
    exit();
}

// Get filter parameters
$department_filter = $_GET['department_filter'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Get active job postings with filters
$query = "SELECT * FROM recruitment WHERE status = 'active'";
$params = [];

if ($department_filter != 'all') {
    $query .= " AND department = ?";
    $params[] = $department_filter;
}

if (!empty($search_term)) {
    $query .= " AND (position_title LIKE ? OR description LIKE ? OR requirements LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY posting_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get distinct departments for filter dropdown
$departments = $pdo->query("SELECT DISTINCT department FROM recruitment ORDER BY department")->fetchAll();

// Get single job for view
$current_job = null;
if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM recruitment WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $current_job = $stmt->fetch();
}

// Get user's applications
$applications = $pdo->prepare("
    SELECT ja.*, r.position_title, r.department 
    FROM job_applications ja
    JOIN recruitment r ON ja.job_id = r.id
    WHERE ja.applicant_email = ?
    ORDER BY ja.application_date DESC
");
$applications->execute([$user_email]);
$applications = $applications->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Opportunities</title>
    <style>
        :root {
            --primary: #f6b93b;
            --primary-dark: #e8a825;
            --secondary: #1e3799;
            --secondary-light: #4a69bd;
            --accent: #e55039;
            --background-light: #ffffff;
            --background-dark: #2d3436;
            --text-light: #2d3436;
            --text-dark: #ffffff;
            --text-muted: #6c757d;
            --success: #78e08f;
            --warning: #f39c12;
            --danger: #e55039;
            --info: #4a69bd;
            --border-radius: 12px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1e3799, rgb(34, 61, 167));
            color: white;
            position: fixed;
            height: 100vh;
            padding: 2rem 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            padding: 1.5rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dashboard-container {
            width: 100%;
            padding: 0;
        }

        /* Header Styles */
        .dashboard-header {
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: rgba(255, 255, 255, 0.98);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 100%;
            margin: 0 auto;
            gap: 1.5rem;
        }

        .header-content h1 {
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Button Styles */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-block;
            text-align: center;
            border: none;
        }

        .btn-primary {
            background-color: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-light);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        /* Content Area Styles */
        .dashboard-content {
            padding: 1.25rem 2rem;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Tab Styles */
        .tab-container {
            margin-top: 1rem;
        }

        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 0.5rem 1rem;
            background-color: #e0e0e0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-btn:hover {
            background-color: #d0d0d0;
        }

        .tab-btn.active {
            background-color: var(--secondary);
            color: white;
        }

        .tab-content {
            display: none;
            padding: 1rem;
            margin: 1rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .tab-content.active {
            display: block;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        /* Job Card Styles */
        .job-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .job-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .job-card h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .job-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .job-department {
            background-color: #e0e0e0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .job-description {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .job-actions {
            display: flex;
            justify-content: flex-end;
        }

        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-interview {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-hired {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Filter Styles */
        .filter-section {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group textarea {
            min-height: 100px;
        }

        /* Alert Styles */
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .dashboard-header {
                padding: 1rem;
            }

            .dashboard-content {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .tab-buttons {
                flex-direction: column;
            }

            .filter-row {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-university"></i> University</h2>
        </div>
        <div class="sidebar-menu">
            <!-- Common items for all users -->
            <a href="/university-system/index.html" class="menu-item">
                <i class="fas fa-home"></i> Home
            </a>

            <?php if ($isLoggedIn): ?>
                <!-- Dashboard link based on user role -->
                <a href="/university-system/php/<?= htmlspecialchars($_SESSION['role']) ?>/dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <!-- Common services for all authenticated users -->
                <a href="/university-system/php/public/canteen.php" class="menu-item">
                    <i class="fas fa-concierge-bell"></i> Canteen
                </a>
                <a href="/university-system/php/public/library.php" class="menu-item">
                    <i class="fas fa-book"></i> Library
                </a>
                <a href="/university-system/php/public/medical.php" class="menu-item">
                    <i class="fas fa-heartbeat"></i> Medical Center
                </a>
                <a href="/university-system/php/public/transport.php" class="menu-item">
                    <i class="fas fa-bus"></i> Transport
                </a>

                <a href="/university-system/php/public/hostel.php" class="menu-item">
                    <i class="fas fa-bus"></i> Hostel
                </a>

                <a href="/university-system/php/public/recruitment.php" class="menu-item active">
                    <i class="fas fa-bus"></i> Recruitment
                </a>

            <?php else: ?>
                <!-- Items for non-logged in users -->
                <a href="/university-system/login.html" class="menu-item">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="/university-system/register.html" class="menu-item">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-container">
            <header class="dashboard-header">
                <div class="header-content">
                    <h1>Job Opportunities</h1>
                    <div class="user-info">
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <a href="/university-system/php/auth/logout.php" class="btn btn-primary">Logout</a>
                    </div>
                </div>
            </header>

            <main class="dashboard-content">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert success"><?= $_SESSION['message'] ?></div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="openTab('jobListings')">Job Listings</button>
                        <button class="tab-btn" onclick="openTab('myApplications')">My Applications</button>
                    </div>

                    <div id="jobListings" class="tab-content active">
                        <div class="filter-section">
                            <form method="get" action="recruitment.php">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="department_filter">Department</label>
                                        <select name="department_filter" id="department_filter">
                                            <option value="all">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['department']) ?>"
                                                    <?= $department_filter == $dept['department'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dept['department']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" id="search"
                                            placeholder="Job title or keywords..."
                                            value="<?= htmlspecialchars($search_term) ?>">
                                    </div>
                                    <div class="filter-group" style="align-self: flex-end;">
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                        <a href="recruitment.php" class="btn">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <?php if (empty($jobs)): ?>
                            <p>No job openings currently available.</p>
                        <?php else: ?>
                            <?php foreach ($jobs as $job): ?>
                                <div class="job-card">
                                    <h3><?= htmlspecialchars($job['position_title']) ?></h3>
                                    <div class="job-meta">
                                        <span><?= date('M j, Y', strtotime($job['posting_date'])) ?> -
                                            <?= date('M j, Y', strtotime($job['closing_date'])) ?></span>
                                        <span class="job-department"><?= htmlspecialchars($job['department']) ?></span>
                                    </div>
                                    <div class="job-description">
                                        <?= nl2br(htmlspecialchars(substr($job['description'], 0, 200))) ?>
                                        <?= strlen($job['description']) > 200 ? '...' : '' ?>
                                    </div>
                                    <div class="job-actions">
                                        <a href="recruitment.php?action=view&id=<?= $job['id'] ?>" class="btn btn-primary">View
                                            Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="myApplications" class="tab-content">
                        <h2>My Applications</h2>
                        <?php if (empty($applications)): ?>
                            <p>You haven't applied to any jobs yet.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($app['position_title']) ?></td>
                                            <td><?= htmlspecialchars($app['department']) ?></td>
                                            <td><?= date('M j, Y', strtotime($app['application_date'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $app['status'] ?>">
                                                    <?= ucfirst($app['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="recruitment.php?action=view&id=<?= $app['job_id'] ?>"
                                                    class="btn btn-primary">View Job</a>
                                            </td>
                                        </tr>
                                        <?php if ($app['status'] == 'interview' && !empty($app['interview_date'])): ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div
                                                        style="background-color: #f0f8ff; padding: 10px; border-radius: 4px; margin: 5px 0;">
                                                        <strong>Scheduled Interview:</strong><br>
                                                        Date: <?= date('M j, Y g:i A', strtotime($app['interview_date'])) ?><br>
                                                        <?php if (!empty($app['interview_location'])): ?>
                                                            Location: <?= htmlspecialchars($app['interview_location']) ?><br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($app['interview_notes'])): ?>
                                                            Notes: <?= nl2br(htmlspecialchars($app['interview_notes'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && $current_job): ?>
                    <!-- Job Details Modal -->
                    <div class="modal" id="jobDetailsModal" style="display: block;">
                        <div class="modal-content">
                            <span class="close" onclick="window.location.href='recruitment.php'">&times;</span>
                            <h2><?= htmlspecialchars($current_job['position_title']) ?></h2>
                            <p><strong>Department:</strong> <?= htmlspecialchars($current_job['department']) ?></p>
                            <p><strong>Posted:</strong> <?= date('M j, Y', strtotime($current_job['posting_date'])) ?></p>
                            <p><strong>Closes:</strong> <?= date('M j, Y', strtotime($current_job['closing_date'])) ?></p>

                            <h3>Job Description</h3>
                            <p><?= nl2br(htmlspecialchars($current_job['description'])) ?></p>

                            <h3>Requirements</h3>
                            <p><?= nl2br(htmlspecialchars($current_job['requirements'])) ?></p>

                            <h3>Apply for this Position</h3>
                            <form method="post" action="recruitment.php" enctype="multipart/form-data">
                                <input type="hidden" name="job_id" value="<?= $current_job['id'] ?>">
                                <div class="form-group">
                                    <label for="applicant_name">Full Name</label>
                                    <input type="text" name="applicant_name" id="applicant_name" required
                                        value="<?= htmlspecialchars($user_fullname) ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="applicant_email">Email</label>
                                    <input type="email" name="applicant_email" id="applicant_email" required
                                        value="<?= htmlspecialchars($user_email) ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="applicant_phone">Phone</label>
                                    <input type="tel" name="applicant_phone" id="applicant_phone" required
                                        pattern="[0-9\+\-\(\) ]+">
                                </div>
                                <div class="form-group">
                                    <label for="applicant_address">Address</label>
                                    <textarea name="applicant_address" id="applicant_address" rows="3" required
                                        maxlength="500"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="cover_letter">Cover Letter</label>
                                    <textarea name="cover_letter" id="cover_letter" rows="5" required
                                        maxlength="2000"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="resume">Resume (PDF or DOCX, max 5MB)</label>
                                    <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
                                </div>
                                <button type="submit" name="apply_job" class="btn btn-primary">Submit Application</button>
                                <a href="recruitment.php" class="btn btn-danger">Cancel</a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }

            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function openInterviewModal(applicantId, jobId) {
            document.getElementById('modal_applicant_id').value = applicantId;
            document.getElementById('modal_job_id').value = jobId;

            // Set default date/time (next business day at 10am)
            const nextDay = new Date();
            nextDay.setDate(nextDay.getDate() + 1);
            if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1); // Skip Sunday
            if (nextDay.getDay() === 6) nextDay.setDate(nextDay.getDate() + 2); // Skip Saturday

            nextDay.setHours(10, 0, 0, 0); // Set to 10am

            const formattedDate = nextDay.toISOString().slice(0, 16);
            document.getElementById('interview_date').value = formattedDate;

            showModal('scheduleInterviewModal');
        }

        // Modal control functions
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            const modal = document.getElementById('jobDetailsModal');
            if (event.target == modal) {
                window.location.href = 'recruitment.php';
            }
        }
    </script>
</body>

</html>