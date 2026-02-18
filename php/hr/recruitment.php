<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hr') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle new job posting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_job'])) {
    $position_title = $_POST['position_title'];
    $department = $_POST['department'];
    $description = $_POST['description'];
    $requirements = $_POST['requirements'];
    $posting_date = $_POST['posting_date'];
    $closing_date = $_POST['closing_date'];

    $stmt = $pdo->prepare("INSERT INTO recruitment (position_title, department, description, requirements, posting_date, closing_date, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$position_title, $department, $description, $requirements, $posting_date, $closing_date]);

    $_SESSION['message'] = "Job posting added successfully";
    header("Location: recruitment.php");
    exit();
}

// Handle job update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_job'])) {
    $id = $_POST['id'];
    $position_title = $_POST['position_title'];
    $department = $_POST['department'];
    $description = $_POST['description'];
    $requirements = $_POST['requirements'];
    $posting_date = $_POST['posting_date'];
    $closing_date = $_POST['closing_date'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE recruitment SET 
                          position_title = ?, 
                          department = ?, 
                          description = ?, 
                          requirements = ?, 
                          posting_date = ?, 
                          closing_date = ?,
                          status = ?
                          WHERE id = ?");
    $stmt->execute([$position_title, $department, $description, $requirements, $posting_date, $closing_date, $status, $id]);

    $_SESSION['message'] = "Job posting updated successfully";
    header("Location: recruitment.php");
    exit();
}

// Handle job deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete_job' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // First delete all applications for this job
    $stmt = $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?");
    $stmt->execute([$id]);

    // Then delete the job
    $stmt = $pdo->prepare("DELETE FROM recruitment WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['message'] = "Job posting and all associated applications deleted successfully";
    header("Location: recruitment.php");
    exit();
}

// Handle interview scheduling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_interview'])) {
    $applicant_id = $_POST['applicant_id'];
    $job_id = $_POST['job_id'];
    $interview_date = $_POST['interview_date'];
    $interview_location = $_POST['interview_location'];
    $interview_notes = $_POST['interview_notes'];

    $stmt = $pdo->prepare("UPDATE job_applications SET 
                      status = 'interview',
                      interview_date = ?,
                      interview_location = ?,
                      interview_notes = ?
                      WHERE id = ?");
    if (!$stmt->execute([$interview_date, $interview_location, $interview_notes, $applicant_id])) {
    error_log("Interview scheduling failed: " . implode(" ", $stmt->errorInfo()));
};

    $_SESSION['message'] = "Interview scheduled successfully";
    header("Location: recruitment.php?action=view&id=" . $job_id);
    exit();
}

// Handle status change
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT status FROM recruitment WHERE id = ?");
    $stmt->execute([$id]);
    $job = $stmt->fetch();

    $new_status = ($job['status'] == 'active') ? 'closed' : 'active';

    $stmt = $pdo->prepare("UPDATE recruitment SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);

    $_SESSION['message'] = "Job status updated successfully";
    header("Location: recruitment.php");
    exit();
}

// Handle applicant deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete_applicant' && isset($_GET['applicant_id'])) {
    $applicant_id = $_GET['applicant_id'];
    $job_id = $_GET['job_id'];

    // Get resume path to delete the file
    $stmt = $pdo->prepare("SELECT resume_path FROM job_applications WHERE id = ?");
    $stmt->execute([$applicant_id]);
    $application = $stmt->fetch();

    if ($application && !empty($application['resume_path'])) {
        $resume_path = $_SERVER['DOCUMENT_ROOT'] . '/university-system/uploads/' . $application['resume_path'];
        if (file_exists($resume_path)) {
            unlink($resume_path);
        }
    }

    // Delete the application
    $stmt = $pdo->prepare("DELETE FROM job_applications WHERE id = ?");
    $stmt->execute([$applicant_id]);

    $_SESSION['message'] = "Applicant deleted successfully";
    header("Location: recruitment.php?action=view&id=" . $job_id);
    exit();
}

// Handle applicant status change
if (isset($_GET['action']) && $_GET['action'] == 'update_applicant' && isset($_GET['applicant_id']) && isset($_GET['status'])) {
    $applicant_id = $_GET['applicant_id'];
    $status = $_GET['status'];
    $job_id = $_GET['job_id'];

    $stmt = $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $applicant_id]);

    $_SESSION['message'] = "Applicant status updated successfully";
    header("Location: recruitment.php?action=view&id=" . $job_id);
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$department_filter = isset($_GET['department_filter']) ? $_GET['department_filter'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build base query
$query = "
    SELECT r.*, COUNT(a.id) as applicant_count
    FROM recruitment r
    LEFT JOIN job_applications a ON r.id = a.job_id
";

// Add WHERE conditions based on filters
$where_conditions = [];
$params = [];

if ($status_filter != 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($department_filter != 'all') {
    $where_conditions[] = "r.department = ?";
    $params[] = $department_filter;
}

if (!empty($search_term)) {
    $where_conditions[] = "(r.position_title LIKE ? OR r.description LIKE ? OR r.requirements LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Complete the query
$query .= " GROUP BY r.id ORDER BY r.posting_date DESC";

// Get all job postings with filters
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get distinct departments for filter dropdown
$departments = $pdo->query("SELECT DISTINCT department FROM recruitment ORDER BY department")->fetchAll();

// Get single job for view/edit
$current_job = null;
if (isset($_GET['action']) && ($_GET['action'] == 'view' || $_GET['action'] == 'edit') && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM recruitment WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $current_job = $stmt->fetch();

    if ($_GET['action'] == 'view') {
        $stmt = $pdo->prepare("SELECT * FROM job_applications WHERE job_id = ?");
        $stmt->execute([$_GET['id']]);
        $applications = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Management</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
            white-space: nowrap;
            margin: 2px 0;
        }

        .applicant-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .applicant-card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            position: relative;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.closed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-badge.pending {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-badge.interview {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-badge.hired {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .filter-section {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .filter-form {
            width: 100%;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-self: flex-end;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-actions {
                align-self: flex-start;
            }
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .delete-confirm {
            background-color: #f8d7da;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .interview-details {
            background-color: #f0f8ff;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 3px solid #4a69bd;
        }

        .interview-details p {
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Recruitment Management</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert-message">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="employee_profiles.php">Staff Profiles</a></li>
                        <li class="active"><a href="recruitment.php">Recruitment</a></li>
                        <li><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li><a href="performance.php">Performance</a></li>
                        <li><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && $current_job): ?>
                    <div class="action-bar">
                        <h2>Job Details: <?= htmlspecialchars($current_job['position_title']) ?></h2>
                        <div class="action-buttons">
                            <a href="recruitment.php" class="logout-btn">Back to List</a>
                            <a href="recruitment.php?action=edit&id=<?= $current_job['id'] ?>" class="logout-btn">Edit</a>
                            <a href="recruitment.php?action=toggle_status&id=<?= $current_job['id'] ?>" class="logout-btn">
                                <?= $current_job['status'] == 'active' ? 'Close Job' : 'Reopen Job' ?>
                            </a>
                            <a href="recruitment.php?action=delete_job&id=<?= $current_job['id'] ?>"
                                class="logout-btn btn-danger"
                                onclick="return confirm('Are you sure you want to delete this job and all its applications?')">Delete
                                Job</a>
                        </div>
                    </div>

                    <div class="hr-card">
                        <div class="job-details">
                            <h3>Department: <?= htmlspecialchars($current_job['department']) ?></h3>
                            <p><strong>Posted:</strong> <?= date('M j, Y', strtotime($current_job['posting_date'])) ?></p>
                            <p><strong>Closes:</strong> <?= date('M j, Y', strtotime($current_job['closing_date'])) ?></p>
                            <p><strong>Status:</strong> <span
                                    class="status-badge <?= $current_job['status'] ?>"><?= ucfirst($current_job['status']) ?></span>
                            </p>

                            <h4>Job Description</h4>
                            <p><?= nl2br(htmlspecialchars($current_job['description'])) ?></p>

                            <h4>Requirements</h4>
                            <p><?= nl2br(htmlspecialchars($current_job['requirements'])) ?></p>
                        </div>

                        <h3>Applicants (<?= count($applications) ?>)</h3>
                        <?php if (!empty($applications)): ?>
                            <?php foreach ($applications as $application): ?>
                                <div class="applicant-card">
                                    <h4><?= htmlspecialchars($application['applicant_name']) ?></h4>
                                    <p>Email: <?= htmlspecialchars($application['applicant_email']) ?></p>
                                    <p>Applied on: <?= date('M j, Y', strtotime($application['application_date'])) ?></p>
                                    <p>Status: <span
                                            class="status-badge <?= $application['status'] ?>"><?= ucfirst($application['status']) ?></span>
                                    </p>

                                    <?php if ($application['status'] == 'interview' && !empty($application['interview_date'])): ?>
                                        <div class="interview-details">
                                            <p><strong>Scheduled Interview:</strong></p>
                                            <p>Date: <?= date('M j, Y g:i A', strtotime($application['interview_date'])) ?></p>
                                            <p>Location: <?= htmlspecialchars($application['interview_location']) ?></p>
                                            <?php if (!empty($application['interview_notes'])): ?>
                                                <p>Notes: <?= nl2br(htmlspecialchars($application['interview_notes'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <a href="/university-system/uploads/resumes/<?= $application['resume_path'] ?>"
                                        class="logout-btn btn-small" download>Download Resume</a>
                                    <div class="applicant-actions">
                                        <a href="recruitment.php?action=update_applicant&applicant_id=<?= $application['id'] ?>&status=interview&job_id=<?= $current_job['id'] ?>"
                                            class="logout-btn btn-small">Schedule Interview</a>
                                        <a href="recruitment.php?action=update_applicant&applicant_id=<?= $application['id'] ?>&status=rejected&job_id=<?= $current_job['id'] ?>"
                                            class="logout-btn btn-small">Reject</a>
                                        <a href="recruitment.php?action=update_applicant&applicant_id=<?= $application['id'] ?>&status=hired&job_id=<?= $current_job['id'] ?>"
                                            class="logout-btn btn-small">Hire</a>
                                        <a href="recruitment.php?action=delete_applicant&applicant_id=<?= $application['id'] ?>&job_id=<?= $current_job['id'] ?>"
                                            class="logout-btn btn-small btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this applicant?')">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No applicants yet.</p>
                        <?php endif; ?>
                    </div>

                <?php elseif (isset($_GET['action']) && $_GET['action'] == 'edit' && $current_job): ?>
                    <div class="action-bar">
                        <h2>Edit Job: <?= htmlspecialchars($current_job['position_title']) ?></h2>
                        <a href="recruitment.php?action=view&id=<?= $current_job['id'] ?>" class="logout-btn">Cancel</a>
                    </div>

                    <div class="hr-card">
                        <form method="post" action="recruitment.php">
                            <input type="hidden" name="id" value="<?= $current_job['id'] ?>">
                            <div class="form-group">
                                <label for="position_title">Position Title</label>
                                <input type="text" name="position_title" id="position_title"
                                    value="<?= htmlspecialchars($current_job['position_title']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" name="department" id="department"
                                    value="<?= htmlspecialchars($current_job['department']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Job Description</label>
                                <textarea name="description" id="description" rows="4"
                                    required><?= htmlspecialchars($current_job['description']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="requirements">Requirements</label>
                                <textarea name="requirements" id="requirements" rows="4"
                                    required><?= htmlspecialchars($current_job['requirements']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="posting_date">Posting Date</label>
                                <input type="date" name="posting_date" id="posting_date"
                                    value="<?= $current_job['posting_date'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="closing_date">Closing Date</label>
                                <input type="date" name="closing_date" id="closing_date"
                                    value="<?= $current_job['closing_date'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="active" <?= $current_job['status'] == 'active' ? 'selected' : '' ?>>Active
                                    </option>
                                    <option value="closed" <?= $current_job['status'] == 'closed' ? 'selected' : '' ?>>Closed
                                    </option>
                                </select>
                            </div>
                            <button type="submit" name="update_job" class="logout-btn">Update Job</button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="action-bar">
                        <h2>Job Postings</h2>
                        <button class="logout-btn" onclick="showModal('addJobModal')">Post New Job</button>
                    </div>

                    <div class="hr-card">
                        <div class="filter-section">
                            <form method="get" action="recruitment.php" class="filter-form">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="status_filter">Status</label>
                                        <select name="status_filter" id="status_filter">
                                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses
                                            </option>
                                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active
                                            </option>
                                            <option value="closed" <?= $status_filter == 'closed' ? 'selected' : '' ?>>Closed
                                            </option>
                                        </select>
                                    </div>

                                    <div class="filter-group">
                                        <label for="department_filter">Department</label>
                                        <select name="department_filter" id="department_filter">
                                            <option value="all" <?= $department_filter == 'all' ? 'selected' : '' ?>>All
                                                Departments</option>
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
                                        <input type="text" name="search" id="search" placeholder="Position or keywords..."
                                            value="<?= htmlspecialchars($search_term) ?>">
                                    </div>

                                    <div class="filter-actions">
                                        <button type="submit" class="logout-btn">Apply</button>
                                        <a href="recruitment.php" class="logout-btn">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Posted</th>
                                <th>Closes</th>
                                <th>Status</th>
                                <th>Applicants</th>
                                <th style="min-width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?= htmlspecialchars($job['position_title']) ?></td>
                                    <td><?= htmlspecialchars($job['department']) ?></td>
                                    <td><?= date('M j, Y', strtotime($job['posting_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($job['closing_date'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= $job['status'] ?>">
                                            <?= ucfirst($job['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $job['applicant_count'] ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="recruitment.php?action=view&id=<?= $job['id'] ?>"
                                                class="logout-btn btn-small">View</a>
                                            <a href="recruitment.php?action=edit&id=<?= $job['id'] ?>"
                                                class="logout-btn btn-small">Edit</a>
                                            <a href="recruitment.php?action=toggle_status&id=<?= $job['id'] ?>"
                                                class="logout-btn btn-small">
                                                <?= $job['status'] == 'active' ? 'Close' : 'Reopen' ?>
                                            </a>
                                            <a href="recruitment.php?action=delete_job&id=<?= $job['id'] ?>"
                                                class="logout-btn btn-small btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this job and all its applications?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        <?php endif; ?>
        </main>
    </div>
    </div>

    <!-- Add Job Modal -->
    <div class="modal" id="addJobModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('addJobModal')">&times;</span>
            <h2>Create New Job Posting</h2>
            <form method="post" action="recruitment.php">
                <div class="form-group">
                    <label for="position_title">Position Title</label>
                    <input type="text" name="position_title" id="position_title" required>
                </div>
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" name="department" id="department" required>
                </div>
                <div class="form-group">
                    <label for="description">Job Description</label>
                    <textarea name="description" id="description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="requirements">Requirements</label>
                    <textarea name="requirements" id="requirements" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="posting_date">Posting Date</label>
                    <input type="date" name="posting_date" id="posting_date" required>
                </div>
                <div class="form-group">
                    <label for="closing_date">Closing Date</label>
                    <input type="date" name="closing_date" id="closing_date" required>
                </div>
                <button type="submit" name="add_job" class="logout-btn">Post Job</button>
            </form>
        </div>
    </div>

    <!-- Interview Scheduling Modal -->
    <div class="modal" id="scheduleInterviewModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('scheduleInterviewModal')">&times;</span>
            <h2>Schedule Interview</h2>
            <form id="interviewForm" method="post" action="recruitment.php">
                <input type="hidden" name="applicant_id" id="modal_applicant_id">
                <input type="hidden" name="job_id" id="modal_job_id">

                <div class="form-group">
                    <label for="interview_date">Date & Time</label>
                    <input type="datetime-local" name="interview_date" id="interview_date" required>
                </div>

                <div class="form-group">
                    <label for="interview_location">Location</label>
                    <input type="text" name="interview_location" id="interview_location" required>
                </div>

                <div class="form-group">
                    <label for="interview_notes">Notes</label>
                    <textarea name="interview_notes" id="interview_notes" rows="4"></textarea>
                </div>

                <button type="submit" name="schedule_interview" class="logout-btn">Schedule Interview</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Function to open interview modal with applicant data
        function openInterviewModal(applicantId, jobId) {
            document.getElementById('modal_applicant_id').value = applicantId;
            document.getElementById('modal_job_id').value = jobId;

            // Set default date/time (next business day at 10am)
            const nextDay = new Date();
            nextDay.setDate(nextDay.getDate() + 1);
            if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1); // Skip Sunday
            if (nextDay.getDay() === 6) nextDay.setDate(nextDay.getDate() + 2); // Skip Saturday

            const formattedDate = nextDay.toISOString().slice(0, 16);
            document.getElementById('interview_date').value = formattedDate;

            showModal('scheduleInterviewModal');
        }

        // Update the applicant action links to use the modal
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.applicant-actions a').forEach(link => {
                if (link.textContent.includes('Schedule Interview')) {
                    const href = link.getAttribute('href');
                    if (href.includes('status=interview')) {
                        link.setAttribute('onclick', 'event.preventDefault(); ' +
                            'const params = new URLSearchParams(href.split("?")[1]); ' +
                            'openInterviewModal(params.get("applicant_id"), params.get("job_id"));');
                    }
                }
            });
        });

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('posting_date').valueAsDate = new Date();

            const nextMonth = new Date();
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            document.getElementById('closing_date').valueAsDate = nextMonth;
        });
    </script>
</body>

</html>