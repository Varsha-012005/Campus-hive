<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: /university-system/login.html");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$current_semester = 'Fall';
$current_year = date('Y');

// Get current courses
$current_courses = $pdo->prepare("SELECT c.id as class_id, co.code, co.title, co.credits, 
                                 c.schedule, c.room
                                 FROM classes c
                                 JOIN courses co ON c.course_id = co.id
                                 WHERE c.faculty_id = ?
                                 AND c.semester = ?
                                 AND c.year = ?");
$current_courses->execute([$faculty_id, $current_semester, $current_year]);
$current_courses = $current_courses->fetchAll();

// Get all courses taught
$all_courses = $pdo->prepare("SELECT c.id as class_id, co.code, co.title, co.credits, 
                             c.schedule, c.room, c.semester, c.year
                             FROM classes c
                             JOIN courses co ON c.course_id = co.id
                             WHERE c.faculty_id = ?
                             ORDER BY c.year DESC, c.semester DESC");
$all_courses->execute([$faculty_id]);
$all_courses = $all_courses->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Courses</title>
    <link href="/university-system/css/faculty.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Course Management</h1>
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
                        <li class="active"><a href="courses.php">Courses</a></li>
                        <li><a href="students.php">Students</a></li>
                        <li><a href="grading.php">Grading</a></li>
                        <li><a href="resources.php">Resources</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="current-courses">
                    <h2>Current Semester Courses</h2>
                    <?php if (!empty($current_courses)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Credits</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_courses as $course): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['code']) ?></td>
                                        <td><?= htmlspecialchars($course['title']) ?></td>
                                        <td><?= htmlspecialchars($course['credits']) ?></td>
                                        <td><?= htmlspecialchars($course['schedule']) ?></td>
                                        <td><?= htmlspecialchars($course['room']) ?></td>
                                        <td>
                                            <a href="course.php?id=<?= $course['class_id'] ?>" class="btn-primary">Manage</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No courses assigned for current semester.</p>
                    <?php endif; ?>
                </section>

                <section class="all-courses">
                    <h2>All Courses Taught</h2>
                    <?php if (!empty($all_courses)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Credits</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_courses as $course): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['semester'] . ' ' . $course['year']) ?></td>
                                        <td><?= htmlspecialchars($course['code']) ?></td>
                                        <td><?= htmlspecialchars($course['title']) ?></td>
                                        <td><?= htmlspecialchars($course['credits']) ?></td>
                                        <td><?= htmlspecialchars($course['schedule']) ?></td>
                                        <td><?= htmlspecialchars($course['room']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No courses found.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/faculty.js"></script>
</body>

</html>