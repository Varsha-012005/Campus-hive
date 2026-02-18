<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: /university-system/login.html");
    exit();
}

$student_id = $_SESSION['user_id'];
$current_semester = 'Fall'; // Should be dynamically determined
$current_year = date('Y');

// Initialize messages
$success = '';
$error = '';

// Handle course registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_course'])) {
    $class_id = filter_input(INPUT_POST, 'class_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        // Check if already registered
        $check = $pdo->prepare("SELECT * FROM enrollment WHERE student_id = ? AND class_id = ?");
        $check->execute([$student_id, $class_id]);

        if ($check->rowCount() == 0) {
            // Check course capacity
            $capacity_check = $pdo->prepare("SELECT capacity, enrolled FROM classes WHERE id = ?");
            $capacity_check->execute([$class_id]);
            $class = $capacity_check->fetch();

            if ($class['enrolled'] < $class['capacity']) {
                $pdo->beginTransaction();

                // Register student
                $insert = $pdo->prepare("INSERT INTO enrollment (student_id, class_id) VALUES (?, ?)");
                $insert->execute([$student_id, $class_id]);

                // Update enrolled count
                $update = $pdo->prepare("UPDATE classes SET enrolled = enrolled + 1 WHERE id = ?");
                $update->execute([$class_id]);

                $pdo->commit();
                $success = "Course registered successfully!";
            } else {
                $error = "This course has reached maximum capacity.";
            }
        } else {
            $error = "You are already registered for this course.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        $error = "An error occurred during registration. Please try again.";
    }
}

// Handle course drop
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['drop_course'])) {
    $class_id = filter_input(INPUT_POST, 'class_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        $pdo->beginTransaction();

        // Drop course
        $delete = $pdo->prepare("DELETE FROM enrollment WHERE student_id = ? AND class_id = ?");
        $delete->execute([$student_id, $class_id]);

        // Update enrolled count
        $update = $pdo->prepare("UPDATE classes SET enrolled = enrolled - 1 WHERE id = ?");
        $update->execute([$class_id]);

        $pdo->commit();
        $success = "Course dropped successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Drop error: " . $e->getMessage());
        $error = "An error occurred while dropping the course. Please try again.";
    }
}

// Get available courses with capacity information
try {
    $available_courses = $pdo->prepare("SELECT c.id as class_id, co.code, co.title, co.credits, 
                                      u.first_name, u.last_name, c.schedule, c.room, 
                                      c.capacity, c.enrolled, (c.capacity - c.enrolled) as seats_available
                                      FROM classes c
                                      JOIN courses co ON c.course_id = co.id
                                      JOIN faculty f ON c.faculty_id = f.faculty_id
                                      JOIN users u ON f.user_id = u.id
                                      WHERE c.semester = ? 
                                      AND c.year = ?
                                      AND co.status = 'active'
                                      AND (c.capacity - c.enrolled) > 0");
    $available_courses->execute([$current_semester, $current_year]);
    $available_courses = $available_courses->fetchAll();
} catch (PDOException $e) {
    error_log("Available courses error: " . $e->getMessage());
    $available_courses = [];
    $error = "Unable to load available courses. Please try again later.";
}

// Get registered courses
try {
    $registered_courses = $pdo->prepare("SELECT c.id as class_id, co.code, co.title, co.credits, 
                                       u.first_name, u.last_name, c.schedule, c.room, e.grade,
                                       c.capacity, c.enrolled
                                       FROM enrollment e
                                       JOIN classes c ON e.class_id = c.id
                                       JOIN courses co ON c.course_id = co.id
                                       JOIN faculty f ON c.faculty_id = f.faculty_id
                                       JOIN users u ON f.user_id = u.id
                                       WHERE e.student_id = ?
                                       AND c.semester = ?
                                       AND c.year = ?");
    $registered_courses->execute([$student_id, $current_semester, $current_year]);
    $registered_courses = $registered_courses->fetchAll();
} catch (PDOException $e) {
    error_log("Registered courses error: " . $e->getMessage());
    $registered_courses = [];
    $error = "Unable to load your registered courses. Please try again later.";
}

// Get class schedule
try {
    $schedule = $pdo->prepare("SELECT co.code, co.title, c.schedule, c.room, 
                              u.first_name, u.last_name
                              FROM enrollment e
                              JOIN classes c ON e.class_id = c.id
                              JOIN courses co ON c.course_id = co.id
                              JOIN faculty f ON c.faculty_id = f.faculty_id
                              JOIN users u ON f.user_id = u.id
                              WHERE e.student_id = ? 
                              AND e.status = 'registered'
                              AND c.semester = ?
                              AND c.year = ?
                              ORDER BY 
                              CASE 
                                  WHEN c.schedule LIKE 'Monday%' THEN 1
                                  WHEN c.schedule LIKE 'Tuesday%' THEN 2
                                  WHEN c.schedule LIKE 'Wednesday%' THEN 3
                                  WHEN c.schedule LIKE 'Thursday%' THEN 4
                                  WHEN c.schedule LIKE 'Friday%' THEN 5
                                  ELSE 6
                              END");
    $schedule->execute([$student_id, $current_semester, $current_year]);
    $schedule = $schedule->fetchAll();
} catch (PDOException $e) {
    error_log("Schedule error: " . $e->getMessage());
    $schedule = [];
}

// Get attendance
try {
    $attendance = $pdo->prepare("SELECT a.date, co.title, a.status 
                               FROM attendance a
                               JOIN classes c ON a.class_id = c.id
                               JOIN courses co ON c.course_id = co.id
                               WHERE a.student_id = ?
                               ORDER BY a.date DESC LIMIT 10");
    $attendance->execute([$student_id]);
    $attendance = $attendance->fetchAll();
} catch (PDOException $e) {
    error_log("Attendance error: " . $e->getMessage());
    $attendance = [];
}

// Get grades
try {
    $grades = $pdo->prepare("SELECT co.code, co.title, co.credits, e.grade
                            FROM enrollment e
                            JOIN classes c ON e.class_id = c.id
                            JOIN courses co ON c.course_id = co.id
                            WHERE e.student_id = ? 
                            AND e.grade IS NOT NULL
                            ORDER BY c.year DESC, c.semester DESC");
    $grades->execute([$student_id]);
    $grades = $grades->fetchAll();
} catch (PDOException $e) {
    error_log("Grades error: " . $e->getMessage());
    $grades = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Information</title>
    <link href="/university-system/css/student.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <!-- Header (same as dashboard.php) -->
        <header class="dashboard-header">
            <h1>Academic Information</h1>
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
                        <li class="active"><a href="academics.php">Academics</a></li>
                        <li><a href="finances.php">Finances</a></li>
                        <li><a href="resources.php">Resources</a></li>
                        <li><a href="requests.php">Requests</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <!-- Course Registration -->
                <section class="course-registration">
                    <h2>Course Registration</h2>

                    <?php if (isset($success)): ?>
                        <div class="alert success"><?= $success ?></div>
                    <?php elseif (isset($error)): ?>
                        <div class="alert error"><?= $error ?></div>
                    <?php endif; ?>

                    <h3>Available Courses</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Credits</th>
                                <th>Instructor</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['code']) ?></td>
                                    <td><?= htmlspecialchars($course['title']) ?></td>
                                    <td><?= htmlspecialchars($course['credits']) ?></td>
                                    <td><?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></td>
                                    <td><?= htmlspecialchars($course['schedule']) ?></td>
                                    <td><?= htmlspecialchars($course['room']) ?></td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="class_id" value="<?= $course['class_id'] ?>">
                                            <button type="submit" name="register_course"
                                                class="btn-primary">Register</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h3>Registered Courses</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Credits</th>
                                <th>Instructor</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registered_courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['code']) ?></td>
                                    <td><?= htmlspecialchars($course['title']) ?></td>
                                    <td><?= htmlspecialchars($course['credits']) ?></td>
                                    <td><?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?></td>
                                    <td><?= htmlspecialchars($course['schedule']) ?></td>
                                    <td><?= htmlspecialchars($course['room']) ?></td>
                                    <td><?= htmlspecialchars($course['grade'] ?? 'In Progress') ?></td>
                                    <td>
                                        <?php if (empty($course['grade'])): ?>
                                            <form method="post">
                                                <input type="hidden" name="class_id" value="<?= $course['class_id'] ?>">
                                                <button type="submit" name="drop_course" class="btn-danger">Drop</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Class Schedule -->
                <section class="class-schedule">
                    <h2>Class Schedule</h2>
                    <?php if (!empty($schedule)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Day/Time</th>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedule as $class): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($class['schedule']) ?></td>
                                        <td><?= htmlspecialchars($class['code'] . ' - ' . $class['title']) ?></td>
                                        <td><?= htmlspecialchars($class['first_name'] . ' ' . $class['last_name']) ?></td>
                                        <td><?= htmlspecialchars($class['room']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No classes scheduled.</p>
                    <?php endif; ?>
                </section>

                <!-- Attendance -->
                <section class="attendance">
                    <h2>Attendance Record</h2>
                    <?php if (!empty($attendance)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                        <td><?= htmlspecialchars($record['title']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($record['status'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No attendance records found.</p>
                    <?php endif; ?>
                </section>

                <!-- Grades -->
                <section class="grades">
                    <h2>Grades</h2>
                    <?php if (!empty($grades)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Title</th>
                                    <th>Credits</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($grade['code']) ?></td>
                                        <td><?= htmlspecialchars($grade['title']) ?></td>
                                        <td><?= htmlspecialchars($grade['credits']) ?></td>
                                        <td><?= htmlspecialchars($grade['grade']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="actions">
                            <button id="downloadTranscript" class="btn-primary">Download Transcript</button>
                        </div>
                    <?php else: ?>
                        <p>No grades available yet.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/student.js"></script>
</body>

</html>