<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: /university-system/login.html");
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grade'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $grade = $_POST['grade'];

    try {
        $stmt = $pdo->prepare("UPDATE enrollment SET grade = ? WHERE id = ?");
        $stmt->execute([$grade, $enrollment_id]);
        $success = "Grade submitted successfully!";
    } catch (PDOException $e) {
        $error = "Error submitting grade: " . $e->getMessage();
    }
}

// Get assignments needing grading
$assignments = $pdo->prepare("SELECT a.id as assignment_id, a.title, a.due_date, 
                            c.title as course_title, COUNT(s.id) as submissions_count
                            FROM assignments a
                            JOIN classes cl ON a.class_id = cl.id
                            JOIN courses c ON cl.course_id = c.id
                            LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                            WHERE cl.faculty_id = ? AND a.due_date < NOW()
                            GROUP BY a.id
                            ORDER BY a.due_date ASC");
$assignments->execute([$faculty_id]);
$assignments = $assignments->fetchAll();

// Get students needing grades

$students_needing_grades = $pdo->prepare("SELECT 
    e.student_id, 
    u.first_name, 
    u.last_name, 
    c.title as course_title
FROM enrollment e
JOIN classes cl ON e.class_id = cl.id
JOIN courses c ON cl.course_id = c.id
JOIN students s ON e.student_id = s.student_id
JOIN users u ON s.user_id = u.id
WHERE cl.faculty_id = ? 
AND e.grade IS NULL
ORDER BY c.title, u.last_name");

$students_needing_grades->execute([$faculty_id]);
$students_needing_grades = $students_needing_grades->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading System</title>
    <link href="/university-system/css/faculty.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Grading System</h1>
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
                        <li><a href="students.php">Students</a></li>
                        <li class="active"><a href="grading.php">Grading</a></li>
                        <li><a href="resources.php">Resources</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($success)): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert error"><?= $error ?></div>
                <?php endif; ?>

                <section class="assignments-grading">
                    <h2>Assignments Needing Grading</h2>
                    <?php if (!empty($assignments)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Submissions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($assignment['title']) ?></td>
                                        <td><?= htmlspecialchars($assignment['course_title']) ?></td>
                                        <td><?= date('M j, Y', strtotime($assignment['due_date'])) ?></td>
                                        <td><?= htmlspecialchars($assignment['submissions_count']) ?></td>
                                        <td>
                                            <a href="assignment.php?id=<?= $assignment['assignment_id'] ?>"
                                                class="btn-primary">Grade</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No assignments currently need grading.</p>
                    <?php endif; ?>
                </section>

                <section class="final-grades">
                    <h2>Submit Final Grades</h2>
                    <?php if (!empty($students_needing_grades)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Current Grade</th>
                                    <th>Submit Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_needing_grades as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                        <td><?= htmlspecialchars($student['course_title']) ?></td>
                                        <td><?= htmlspecialchars($student['grade'] ?? 'Not submitted') ?></td>
                                        <td>
                                            <form method="post" class="grade-form">
                                                <input type="hidden" name="enrollment_id"
                                                    value="<?= $student['enrollment_id'] ?>">
                                                <select name="grade" required>
                                                    <option value="">Select Grade</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                    <option value="D">D</option>
                                                    <option value="F">F</option>
                                                </select>
                                                <button type="submit" name="submit_grade" class="btn-primary">Submit</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>All final grades have been submitted for current semester.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/faculty.js"></script>
</body>

</html>