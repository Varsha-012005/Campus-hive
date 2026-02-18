<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Get report data
$enrollment_stats = $pdo->query("
    SELECT c.code, c.title, COUNT(e.student_id) as enrollment_count
    FROM enrollment e
    JOIN classes cl ON e.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    WHERE cl.semester = (SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester')
    GROUP BY c.id
    ORDER BY enrollment_count DESC
")->fetchAll();

$faculty_workload = $pdo->query("
    SELECT f.faculty_id, u.first_name, u.last_name, COUNT(cl.id) as course_count
    FROM faculty f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN classes cl ON f.user_id = cl.faculty_id
    WHERE cl.semester = (SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester')
    GROUP BY f.user_id
    ORDER BY course_count DESC
")->fetchAll();

$student_performance = $pdo->query("
    SELECT s.student_id, u.first_name, u.last_name, 
           COUNT(e.student_id) as courses_taken,
           AVG(CASE WHEN e.grade = 'A' THEN 4
                    WHEN e.grade = 'B' THEN 3
                    WHEN e.grade = 'C' THEN 2
                    WHEN e.grade = 'D' THEN 1
                    ELSE 0 END) as gpa
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN enrollment e ON s.student_id = e.student_id
    GROUP BY s.student_id
    HAVING COUNT(e.student_id) > 0
    ORDER BY gpa DESC
    LIMIT 50
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Reports & Analytics</h1>
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
                        <li><a href="users.php">User Management</a></li>
                        <li><a href="courses.php">Course Management</a></li>
                        <li><a href="departments.php">Departments</a></li>
                        <li><a href="settings.php">System Settings</a></li>
                        <li class="active"><a href="reports.php">Reports</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="enrollment-report">
                    <h2>Course Enrollment Statistics</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Enrollment Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollment_stats as $course): ?>
                            <tr>
                                <td><?= htmlspecialchars($course['code']) ?></td>
                                <td><?= htmlspecialchars($course['title']) ?></td>
                                <td><?= $course['enrollment_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="faculty-workload">
                    <h2>Faculty Workload</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty ID</th>
                                <th>Name</th>
                                <th>Courses Teaching</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faculty_workload as $faculty): ?>
                            <tr>
                                <td><?= htmlspecialchars($faculty['faculty_id']) ?></td>
                                <td><?= htmlspecialchars($faculty['last_name'] . ', ' . $faculty['first_name']) ?></td>
                                <td><?= $faculty['course_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="student-performance">
                    <h2>Top Performing Students</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Courses Taken</th>
                                <th>GPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_performance as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                <td><?= $student['courses_taken'] ?></td>
                                <td><?= number_format($student['gpa'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="report-actions">
                    <h2>Generate Reports</h2>
                    <div class="action-buttons">
                        <a href="generate_report.php?type=enrollment" class="logout-btn">Export Enrollment Report</a>
                        <a href="generate_report.php?type=grades" class="logout-btn">Export Grade Report</a>
                        <a href="generate_report.php?type=attendance" class="logout-btn">Export Attendance Report</a>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/admin.js"></script>
</body>
</html>