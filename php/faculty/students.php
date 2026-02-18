<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: /university-system/login.html");
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Get all students in faculty's courses
$students = $pdo->prepare("SELECT DISTINCT s.student_id, u.first_name, u.last_name, u.email, 
                          GROUP_CONCAT(co.title SEPARATOR ', ') as courses
                          FROM enrollment e
                          JOIN classes cl ON e.class_id = cl.id
                          JOIN courses co ON cl.course_id = co.id
                          JOIN students s ON e.student_id = s.student_id
                          JOIN users u ON s.user_id = u.id
                          WHERE cl.faculty_id = ?
                          GROUP BY s.student_id
                          ORDER BY u.last_name, u.first_name");
$students->execute([$faculty_id]);
$students = $students->fetchAll();

// Get advisees (already in faculty's dashboard, but including here for completeness)
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
    <title>Student Management</title>
    <link href="/university-system/css/faculty.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Student Management</h1>
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
                        <li><a href="courses.php">Courses</a></li>
                        <li class="active"><a href="students.php">Students</a></li>
                        <li><a href="grading.php">Grading</a></li>
                        <li><a href="resources.php">Resources</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="student-list">
                    <h2>Students in Your Courses</h2>
                    <?php if (!empty($students)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['student_id']) ?></td>
                                    <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td><?= htmlspecialchars($student['courses']) ?></td>
                                    <td>
                                        <a href="student.php?id=<?= $student['student_id'] ?>" class="btn-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No students found in your courses.</p>
                    <?php endif; ?>
                </section>

                <section class="advisees-list">
                    <h2>Your Advisees</h2>
                    <?php if (!empty($advisees)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advisees as $advisee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($advisee['student_id']) ?></td>
                                    <td><?= htmlspecialchars($advisee['last_name'] . ', ' . $advisee['first_name']) ?></td>
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