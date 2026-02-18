<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: /university-system/login.html");
    exit();
}

$student_id = $_SESSION['user_id']; // Using user_id consistently

// Handle transcript request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_transcript'])) {
    $type = 'transcript';
    $description = 'Official transcript request';
    
    $insert = $pdo->prepare("INSERT INTO requests (user_id, type, description) VALUES (?, ?, ?)");
    $insert->execute([$student_id, $type, $description]);
    $success = "Transcript request submitted successfully!";
}

// Handle advisor meeting request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_meeting'])) {
    $type = 'advisor_meeting';
    $description = $_POST['meeting_reason'];
    $preferred_date = $_POST['preferred_date'];
    
    $insert = $pdo->prepare("INSERT INTO requests (user_id, type, description) VALUES (?, ?, ?)");
    $insert->execute([$student_id, $type, $description . " (Preferred date: " . $preferred_date . ")"]);
    $success = "Advisor meeting request submitted successfully!";
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $type = 'feedback';
    $description = $_POST['feedback_content'];
    $category = $_POST['feedback_category'];
    
    $insert = $pdo->prepare("INSERT INTO requests (user_id, type, description) VALUES (?, ?, ?)");
    $insert->execute([$student_id, $type, $category . " feedback: " . $description]);
    $success = "Thank you for your feedback!";
}

try {
    // Get student data first - using $student_id (which is $_SESSION['user_id'])
    $student = $pdo->prepare("SELECT advisor_id FROM student WHERE user_id = ?");
    $student->execute([$student_id]);
    $student = $student->fetch();
    
    if ($student && !empty($student['advisor_id'])) {
        // Get advisor information
        $advisor = $pdo->prepare("SELECT u.first_name, u.last_name, u.email 
                                FROM faculty f
                                JOIN users u ON f.user_id = u.id
                                WHERE f.user_id = ?");
        $advisor->execute([$student['advisor_id']]);
        $advisor = $advisor->fetch();
    } else {
        $advisor = null;
        error_log("No advisor found for student: " . $student_id);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $advisor = null;
}

// Get student's requests
$requests = $pdo->prepare("SELECT * FROM requests 
                          WHERE user_id = ?
                          ORDER BY created_at DESC");
$requests->execute([$student_id]);
$requests = $requests->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests</title>
    <link href="/university-system/css/student.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header (same as dashboard.php) -->
        <header class="dashboard-header">
            <h1>Requests and Support</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Sidebar (same as dashboard.php) -->
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="academics.php">Academics</a></li>
                        <li><a href="finances.php">Finances</a></li>
                        <li><a href="resources.php">Resources</a></li>
                        <li class="active"><a href="requests.php">Requests</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($success)): ?>
                <div class="alert success"><?= $success ?></div>
                <?php endif; ?>

                <!-- Advisor Information -->
                <section class="advisor-info">
                    <h2>Your Academic Advisor</h2>
                    <?php if ($advisor): ?>
                    <div class="advisor-card">
                        <h3><?= htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']) ?></h3>
                        <p>Email: <?= htmlspecialchars($advisor['email']) ?></p>
                    </div>
                    <?php else: ?>
                    <p>No advisor assigned.</p>
                    <?php endif; ?>
                </section>

                <!-- Transcript Request -->
                <section class="transcript-request">
                    <h2>Request Official Transcript</h2>
                    <form method="post">
                        <p>Request an official copy of your academic transcript.</p>
                        <button type="submit" name="request_transcript" class="btn-primary">
                            Request Transcript
                        </button>
                    </form>
                </section>

                <!-- Advisor Meeting -->
                <section class="advisor-meeting">
                    <h2>Schedule Advisor Meeting</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="meeting_reason">Meeting Reason</label>
                            <textarea id="meeting_reason" name="meeting_reason" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="preferred_date">Preferred Date</label>
                            <input type="date" id="preferred_date" name="preferred_date" required>
                        </div>
                        <button type="submit" name="request_meeting" class="btn-primary">
                            Request Meeting
                        </button>
                    </form>
                </section>

                <!-- Feedback Form -->
                <section class="feedback-form">
                    <h2>Submit Feedback</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="feedback_category">Category</label>
                            <select id="feedback_category" name="feedback_category" required>
                                <option value="General">General</option>
                                <option value="Academic">Academic</option>
                                <option value="Facilities">Facilities</option>
                                <option value="Services">Services</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="feedback_content">Feedback</label>
                            <textarea id="feedback_content" name="feedback_content" required></textarea>
                        </div>
                        <button type="submit" name="submit_feedback" class="btn-primary">
                            Submit Feedback
                        </button>
                    </form>
                </section>

                <!-- Request History -->
                <section class="request-history">
                    <h2>Your Requests</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $request['type']))) ?></td>
                                <td><?= htmlspecialchars($request['description']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($request['status'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/student.js"></script>
</body>
</html>