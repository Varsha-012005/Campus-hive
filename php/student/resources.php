<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: /university-system/login.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['assignment_file'])) {
    $assignment_id = $_POST['assignment_id'];
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/university-system/uploads/assignments/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = basename($_FILES['assignment_file']['name']);
    $file_path = $upload_dir . $file_name;
    $file_type = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Check if file is valid
    if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $file_path)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO assignment_submissions 
                                 (assignment_id, student_id, file_path, submission_date) 
                                 VALUES (?, ?, ?, NOW())");
            $stmt->execute([$assignment_id, $student_id, $file_path]);
            $success = "Assignment submitted successfully!";
        } catch (PDOException $e) {
            $error = "Error submitting assignment: " . $e->getMessage();
        }
    } else {
        $error = "Sorry, there was an error uploading your file.";
    }
}

// Get available assignments
$assignments = $pdo->prepare("SELECT a.*, c.title as course_title 
                            FROM assignments a
                            JOIN classes cl ON a.class_id = cl.id
                            JOIN courses c ON cl.course_id = c.id
                            JOIN enrollment e ON cl.id = e.class_id
                            WHERE e.student_id = ?
                            AND a.due_date > NOW()
                            ORDER BY a.due_date ASC");
$assignments->execute([$student_id]);
$assignments = $assignments->fetchAll();

// Get submitted assignments
$submissions = $pdo->prepare("SELECT s.*, a.title as assignment_title, a.due_date, c.title as course_title
                             FROM assignment_submissions s
                             JOIN assignments a ON s.assignment_id = a.id
                             JOIN classes cl ON a.class_id = cl.id
                             JOIN courses c ON cl.course_id = c.id
                             WHERE s.student_id = ?
                             ORDER BY s.submission_date DESC");
$submissions->execute([$student_id]);
$submissions = $submissions->fetchAll();

// Get study materials
$study_materials = $pdo->prepare("SELECT sm.*, c.title as course_title 
                                 FROM study_materials sm
                                 JOIN classes cl ON sm.class_id = cl.id
                                 JOIN courses c ON cl.course_id = c.id
                                 JOIN enrollment e ON cl.id = e.class_id
                                 WHERE e.student_id = ?
                                 ORDER BY c.title, sm.title");
$study_materials->execute([$student_id]);
$study_materials = $study_materials->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Resources</title>
    <link href="/university-system/css/student.css" rel="stylesheet">
    <style>
        .resource-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body[data-theme="dark"] .resource-section {
            background: rgba(30, 30, 50, 0.7);
        }
        
        .resource-section h2 {
            margin-top: 0;
            color: var(--secondary);
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        body[data-theme="dark"] .resource-section h2 {
            color: var(--primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .resource-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px dashed #eee;
        }
        
        body[data-theme="dark"] .resource-item {
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
        }
        
        .resource-link {
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        body[data-theme="dark"] .resource-link {
            color: var(--primary);
        }
        
        .resource-link:hover {
            color: #f6b93b;
            text-decoration: underline;
        }
        
        .due-date {
            color: #e74c3c;
            font-weight: 500;
        }
        
        .submission-status {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .submitted {
            background-color: #2ecc71;
            color: white;
        }
        
        .pending {
            background-color: #f39c12;
            color: white;
        }
        
        .late {
            background-color: #e74c3c;
            color: white;
        }
        
        .file-upload-form {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.03);
            border-radius: 5px;
        }
        
        body[data-theme="dark"] .file-upload-form {
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <h1>Academic Resources</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="academics.php">Academics</a></li>
                    <li><a href="finances.php">Finances</a></li>
                    <li class="active"><a href="resources.php">Resources</a></li>
                    <li><a href="requests.php">Requests</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="dashboard-content">
            <main class="main-panel">
                <?php if (isset($success)): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert error"><?= $error ?></div>
                <?php endif; ?>

                <!-- Assignments Section -->
                <section class="resource-section">
                    <h2>Current Assignments</h2>
                    <?php if (!empty($assignments)): ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="resource-item">
                                <div>
                                    <h3><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <p><?= htmlspecialchars($assignment['description']) ?></p>
                                    <div class="resource-meta">
                                        Course: <?= htmlspecialchars($assignment['course_title']) ?> | 
                                        Due: <span class="due-date"><?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?></span>
                                    </div>
                                </div>
                                <div>
                                    <form method="post" enctype="multipart/form-data" class="file-upload-form">
                                        <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                        <input type="file" name="assignment_file" required>
                                        <button type="submit" class="btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No current assignments available.</p>
                    <?php endif; ?>
                </section>

                <!-- Submitted Assignments -->
                <section class="resource-section">
                    <h2>Submitted Assignments</h2>
                    <?php if (!empty($submissions)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Submission Date</th>
                                    <th>Status</th>
                                    <th>File</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($submission['assignment_title']) ?></td>
                                        <td><?= htmlspecialchars($submission['course_title']) ?></td>
                                        <td><?= date('M j, Y', strtotime($submission['submission_date'])) ?></td>
                                        <td>
                                            <?php 
                                            $due_date = new DateTime($submission['due_date']);
                                            $sub_date = new DateTime($submission['submission_date']);
                                            if ($sub_date > $due_date) {
                                                echo '<span class="submission-status late">Late</span>';
                                            } else {
                                                echo '<span class="submission-status submitted">Submitted</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($submission['file_path']) ?>" class="resource-link" download>
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No assignments submitted yet.</p>
                    <?php endif; ?>
                </section>

                <!-- Study Materials -->
                <section class="resource-section">
                    <h2>Study Materials</h2>
                    <?php if (!empty($study_materials)): ?>
                        <?php 
                        $current_course = '';
                        foreach ($study_materials as $material): 
                            if ($material['course_title'] != $current_course):
                                $current_course = $material['course_title'];
                        ?>
                                <h3><?= htmlspecialchars($current_course) ?></h3>
                            <?php endif; ?>
                            <div class="resource-item">
                                <div>
                                    <a href="<?= htmlspecialchars($material['file_path']) ?>" class="resource-link" target="_blank">
                                        <?= htmlspecialchars($material['title']) ?>
                                    </a>
                                    <p><?= htmlspecialchars($material['description']) ?></p>
                                </div>
                                <div>
                                    <?= date('M j, Y', strtotime($material['upload_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No study materials available for your courses.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/student.js"></script>
</body>
</html>