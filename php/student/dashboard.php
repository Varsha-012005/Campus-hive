<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: /university-system/login.html");
    exit();
}

// Get student data
$student_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name, u.email 
                      FROM students s 
                      JOIN users u ON s.user_id = u.id 
                      WHERE s.user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get announcements
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get upcoming classes
$upcoming_classes = $pdo->prepare("SELECT c.title, cl.schedule, cl.room 
                                  FROM enrollment e
                                  JOIN classes cl ON e.class_id = cl.id
                                  JOIN courses c ON cl.course_id = c.id
                                  WHERE e.student_id = ? AND cl.schedule > NOW()
                                  ORDER BY cl.schedule ASC LIMIT 3");
$upcoming_classes->execute([$student_id]);
$upcoming_classes = $upcoming_classes->fetchAll();

// Get recent grades
$recent_grades = $pdo->prepare("SELECT c.title, e.grade 
                               FROM enrollment e
                               JOIN classes cl ON e.class_id = cl.id
                               JOIN courses c ON cl.course_id = c.id
                               WHERE e.student_id = ? AND e.grade IS NOT NULL
                               ORDER BY cl.year DESC, cl.semester DESC LIMIT 5");
$recent_grades->execute([$student_id]);
$recent_grades = $recent_grades->fetchAll();

// Calculate GPA
$gpa_result = $pdo->prepare("SELECT AVG(CASE 
                                       WHEN grade = 'A' THEN 4.0
                                       WHEN grade = 'B' THEN 3.0
                                       WHEN grade = 'C' THEN 2.0
                                       WHEN grade = 'D' THEN 1.0
                                       ELSE 0 END) as gpa
                            FROM enrollment
                            WHERE student_id = ? AND grade IS NOT NULL");
$gpa_result->execute([$student_id]);
$gpa = $gpa_result->fetchColumn();
$gpa = number_format($gpa, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="/university-system/css/student.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <h1>Welcome, <?= htmlspecialchars($student['first_name']) ?></h1>
            <div class="user-info">
                <span><?= htmlspecialchars($student['student_id']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="dashboard-content">
            <!-- Sidebar -->
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active"><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="academics.php">Academics</a></li>
                        <li><a href="finances.php">Finances</a></li>
                        <li><a href="resources.php">Resources</a></li>
                        <li><a href="requests.php">Requests</a></li>
                    </ul>
                </nav>
            </aside>

            <!-- Main Panel -->
            <main class="main-panel">
                <!-- Stats Overview -->
                <section class="stats-overview">
                    <div class="stat-card">
                        <h3>Current GPA</h3>
                        <div class="stat-value"><?= $gpa ?: 'N/A' ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Registered Courses</h3>
                        <?php
                        $course_count = $pdo->prepare("SELECT COUNT(*) FROM enrollment WHERE student_id = ? AND status = 'registered'");
                        $course_count->execute([$student_id]);
                        ?>
                        <div class="stat-value"><?= $course_count->fetchColumn() ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Attendance %</h3>
                        <?php
                        $attendance = $pdo->prepare("SELECT 
                            (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as percentage
                            FROM attendance WHERE student_id = ?");
                        $attendance->execute([$student_id]);
                        $attendance_percent = $attendance->fetchColumn();
                        ?>
                        <div class="stat-value"><?= $attendance_percent ? number_format($attendance_percent, 1) . '%' : 'N/A' ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Balance Due</h3>
                        <?php
                        $balance = $pdo->prepare("SELECT SUM(amount) FROM financial_transactions 
                                                WHERE student_id = ? AND status = 'pending'");
                        $balance->execute([$student_id]);
                        $balance_due = $balance->fetchColumn();
                        ?>
                        <div class="stat-value">$<?= $balance_due ? number_format($balance_due, 2) : '0.00' ?></div>
                    </div>
                </section>

                <!-- Announcements -->
                <section class="announcements">
                    <h2>Announcements</h2>
                    <div class="announcement-list">
                        <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                            <p><?= htmlspecialchars($announcement['content']) ?></p>
                            <small><?= date('M j, Y', strtotime($announcement['created_at'])) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Upcoming Classes -->
                <section class="upcoming-classes">
                    <h2>Upcoming Classes</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Time</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_classes as $class): ?>
                            <tr>
                                <td><?= htmlspecialchars($class['title']) ?></td>
                                <td><?= htmlspecialchars($class['schedule']) ?></td>
                                <td><?= htmlspecialchars($class['room']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Recent Grades -->
                <section class="recent-grades">
                    <h2>Recent Grades</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_grades as $grade): ?>
                            <tr>
                                <td><?= htmlspecialchars($grade['title']) ?></td>
                                <td><?= htmlspecialchars($grade['grade']) ?></td>
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