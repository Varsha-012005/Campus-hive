<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: /university-system/login.html");
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Get faculty data
$stmt = $pdo->prepare("SELECT f.*, u.first_name, u.last_name, u.email 
                      FROM faculty f 
                      JOIN users u ON f.user_id = u.id 
                      WHERE f.user_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

// Get announcements
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get upcoming classes
$upcoming_classes = $pdo->prepare("SELECT c.title, cl.schedule, cl.room 
                                 FROM classes cl
                                 JOIN courses c ON cl.course_id = c.id
                                 WHERE cl.faculty_id = ? AND cl.schedule > NOW()
                                 ORDER BY cl.schedule ASC LIMIT 3");
$upcoming_classes->execute([$faculty_id]);
$upcoming_classes = $upcoming_classes->fetchAll();

// Get advisees
$advisees = $pdo->prepare("SELECT s.student_id, u.first_name, u.last_name, u.email
                          FROM students s
                          JOIN users u ON s.user_id = u.id
                          WHERE s.advisor_id = ?
                          ORDER BY u.last_name ASC");
$advisees->execute([$faculty_id]);
$advisees = $advisees->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="/university-system/css/faculty.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Welcome, Dr. <?= htmlspecialchars($faculty['last_name']) ?></h1>
            <div class="user-info">
                <span><?= htmlspecialchars($faculty['department']) ?> Department</span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active"><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="courses.php">Courses</a></li>
                        <li><a href="students.php">Students</a></li>
                        <li><a href="grading.php">Grading</a></li>
                        <li><a href="resources.php">Resources</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="stats-overview">
                    <div class="stat-card">
                        <h3>Current Courses</h3>
                        <?php
                        $course_count = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE faculty_id = ?");
                        $course_count->execute([$faculty_id]);
                        ?>
                        <div class="stat-value"><?= $course_count->fetchColumn() ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Students</h3>
                        <?php
                        $student_count = $pdo->prepare("SELECT COUNT(*) FROM enrollment e JOIN classes c ON e.class_id = c.id WHERE c.faculty_id = ?");
                        $student_count->execute([$faculty_id]);
                        ?>
                        <div class="stat-value"><?= $student_count->fetchColumn() ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Advisees</h3>
                        <div class="stat-value"><?= count($advisees) ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Grading</h3>
                        <?php
                        $grading_count = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN classes c ON a.class_id = c.id WHERE c.faculty_id = ? AND a.due_date < NOW()");
                        $grading_count->execute([$faculty_id]);
                        ?>
                        <div class="stat-value"><?= $grading_count->fetchColumn() ?></div>
                    </div>
                </section>

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

                <section class="advisees">
                    <h2>Your Advisees</h2>
                    <?php if (!empty($advisees)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advisees as $advisee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($advisee['student_id']) ?></td>
                                    <td><?= htmlspecialchars($advisee['first_name'] . ' ' . $advisee['last_name']) ?></td>
                                    <td><?= htmlspecialchars($advisee['email']) ?></td>
                                    <td>
                                        <a href="student.php?id=<?= $advisee['student_id'] ?>" class="btn-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No advisees assigned.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/faculty.js"></script>
</body>
</html>