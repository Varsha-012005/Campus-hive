<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: /university-system/login.html");
    exit();
}

$faculty_id = $_SESSION['user_id'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resource_file'])) {
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/university-system/uploads/resources/";
    $target_file = $target_dir . basename($_FILES["resource_file"]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if file already exists
    if (file_exists($target_file)) {
        $error = "Sorry, file already exists.";
        $uploadOk = 0;
    }

    // Check file size (5MB max)
    if ($_FILES["resource_file"]["size"] > 5000000) {
        $error = "Sorry, your file is too large.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'];
    if (!in_array($fileType, $allowedTypes)) {
        $error = "Sorry, only PDF, DOC, PPT, XLS, TXT & ZIP files are allowed.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["resource_file"]["tmp_name"], $target_file)) {
            // Save to database
            $stmt = $pdo->prepare("INSERT INTO resources (title, description, file_path, faculty_id, course_id, created_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                "/university-system/uploads/resources/" . basename($_FILES["resource_file"]["name"]),
                $faculty_id,
                $_POST['course_id']
            ]);
            $success = "The file " . htmlspecialchars(basename($_FILES["resource_file"]["name"])) . " has been uploaded.";
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    }
}

// Get faculty's courses for dropdown
$courses = $pdo->prepare("SELECT c.id, co.code, co.title 
                         FROM classes c
                         JOIN courses co ON c.course_id = co.id
                         WHERE c.faculty_id = ?
                         ORDER BY co.title");
$courses->execute([$faculty_id]);
$courses = $courses->fetchAll();

// Get uploaded resources
$resources = $pdo->prepare("SELECT r.*, co.code as course_code, co.title as course_title
                           FROM resources r
                           LEFT JOIN courses co ON r.course_id = co.id
                           WHERE r.faculty_id = ?
                           ORDER BY r.created_at DESC");
$resources->execute([$faculty_id]);
$resources = $resources->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Resources</title>
    <link href="/university-system/css/faculty.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Course Resources</h1>
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
                        <li><a href="grading.php">Grading</a></li>
                        <li class="active"><a href="resources.php">Resources</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($success)): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert error"><?= $error ?></div>
                <?php endif; ?>

                <section class="upload-resource">
                    <h2>Upload New Resource</h2>
                    <form method="post" enctype="multipart/form-data" class="material-upload-form">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="course_id">Course (optional)</label>
                            <select id="course_id" name="course_id">
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>">
                                        <?= htmlspecialchars($course['code'] . ' - ' . $course['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="resource_file">File</label>
                            <input type="file" id="resource_file" name="resource_file" required>
                        </div>
                        <button type="submit" class="btn-primary">Upload Resource</button>
                    </form>
                </section>

                <section class="resource-list">
                    <h2>Your Resources</h2>
                    <?php if (!empty($resources)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Course</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td><?= htmlspecialchars($resource['title']) ?></td>
                                    <td><?= htmlspecialchars($resource['description']) ?></td>
                                    <td>
                                        <?= $resource['course_code'] ? 
                                            htmlspecialchars($resource['course_code'] . ' - ' . $resource['course_title']) : 
                                            'General' ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($resource['created_at'])) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($resource['file_path']) ?>" 
                                           class="btn-primary" 
                                           download>Download</a>
                                        <a href="delete_resource.php?id=<?= $resource['id'] ?>" 
                                           class="btn-danger">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No resources uploaded yet.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/faculty.js"></script>
</body>
</html>