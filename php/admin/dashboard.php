<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Get system statistics
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_faculty = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$active_semester = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester'")->fetchColumn();

// Get recent activity logs
$activity_logs = $pdo->query("SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT 5")->fetchAll();

// Get pending approvals
$pending_approvals = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active"><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="users.php">User Management</a></li>
                        <li><a href="courses.php">Course Management</a></li>
                        <li><a href="departments.php">Departments</a></li>
                        <li><a href="settings.php">System Settings</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="stats-overview">
                    <div class="stat-card">
                        <h3>Total Students</h3>
                        <div class="stat-value"><?= $total_students ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Faculty</h3>
                        <div class="stat-value"><?= $total_faculty ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Courses</h3>
                        <div class="stat-value"><?= $total_courses ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Approvals</h3>
                        <div class="stat-value"><?= $pending_approvals ?></div>
                    </div>
                </section>

                <section class="current-semester">
                    <h2>Current Semester: <?= htmlspecialchars($active_semester) ?></h2>
                </section>

                <section class="recent-activity">
                    <h2>Recent Activity</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_logs as $log): ?>
                            <tr>
                                <td><?= date('M j, Y H:i', strtotime($log['timestamp'])) ?></td>
                                <td><?= htmlspecialchars($log['user_id']) ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="users.php?action=create" class="logout-btn">Add New User</a>
                        <a href="courses.php?action=create" class="logout-btn">Add New Course</a>
                        <a href="settings.php" class="logout-btn">Configure Semester</a>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/admin.js"></script>
</body>
</html>