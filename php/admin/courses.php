<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_course'])) {
    $code = $_POST['code'];
    $title = $_POST['title'];
    $credits = $_POST['credits'];
    $description = $_POST['description'];
    $department = $_POST['department'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("INSERT INTO courses (code, title, credits, description, department, status) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $title, $credits, $description, $department, $status]);
        $success = "Course created successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'create_course', "Created course: $code - $title"]);
    } catch (PDOException $e) {
        $error = "Error creating course: " . $e->getMessage();
    }
}

// Handle course update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
    $id = $_POST['id'];
    $code = $_POST['code'];
    $title = $_POST['title'];
    $credits = $_POST['credits'];
    $description = $_POST['description'];
    $department = $_POST['department'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE courses SET code = ?, title = ?, credits = ?, description = ?, department = ?, status = ? WHERE id = ?");
        $stmt->execute([$code, $title, $credits, $description, $department, $status, $id]);
        $success = "Course updated successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'update_course', "Updated course: $code - $title"]);
    } catch (PDOException $e) {
        $error = "Error updating course: " . $e->getMessage();
    }
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_course'])) {
    $id = $_POST['id'];

    try {
        // Get course info for logging before deleting
        $stmt = $pdo->prepare("SELECT code, title FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Course deleted successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'delete_course', "Deleted course: {$course['code']} - {$course['title']}"]);
    } catch (PDOException $e) {
        $error = "Error deleting course: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if (!empty($_POST['selected_courses'])) {
        $selected_courses = $_POST['selected_courses'];
        $placeholders = implode(',', array_fill(0, count($selected_courses), '?'));

        try {
            if ($_POST['bulk_action'] == 'activate') {
                $stmt = $pdo->prepare("UPDATE courses SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($selected_courses);
                $success = count($selected_courses) . " courses activated successfully!";
            } elseif ($_POST['bulk_action'] == 'deactivate') {
                $stmt = $pdo->prepare("UPDATE courses SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($selected_courses);
                $success = count($selected_courses) . " courses deactivated successfully!";
            } elseif ($_POST['bulk_action'] == 'delete') {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id IN ($placeholders)");
                $stmt->execute($selected_courses);
                $success = count($selected_courses) . " courses deleted successfully!";
            }

            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                                  VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'bulk_course_action', "Performed " . $_POST['bulk_action'] . " on " . count($selected_courses) . " courses"]);
        } catch (PDOException $e) {
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error = "No courses selected for bulk action";
    }
}

// Get all courses with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

$query = "SELECT c.*, d.name as department_name 
          FROM courses c
          LEFT JOIN departments d ON c.department = d.id
          WHERE 1=1";

$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (c.code LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

if (!empty($department_filter)) {
    $query .= " AND c.department = ?";
    $params[] = $department_filter;
    $count_params[] = $department_filter;
}

$query .= " ORDER BY c.code LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);

// Bind parameters with types
foreach ($params as $key => $value) {
    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key + 1, $value, $paramType);
}

$stmt->execute();
$courses = $stmt->fetchAll();

// Get departments for filter dropdown
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM courses c WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (c.code LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
}

if (!empty($status_filter)) {
    $count_query .= " AND c.status = ?";
}

if (!empty($department_filter)) {
    $count_query .= " AND c.department = ?";
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_courses = $count_stmt->fetchColumn();
$total_pages = ceil($total_courses / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
    <style>
        /* Modal styling fixes */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        /* Edit modal specific */
        .edit-form {
            display: none;
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
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
                        <li><a href="users.php">User Management</a></li>
                        <li class="active"><a href="courses.php">Course Management</a></li>
                        <li><a href="departments.php">Departments</a></li>
                        <li><a href="settings.php">System Settings</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($success)): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert error"><?= $error ?></div>
                <?php endif; ?>

                <div class="action-bar">
                    <div class="search-bar">
                        <form method="get" action="courses.php">
                            <input type="text" name="search" placeholder="Search courses..."
                                value="<?= htmlspecialchars($search) ?>">
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive
                                </option>
                            </select>
                            <select name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $department_filter == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="logout-btn">Filter</button>
                            <a href="courses.php" class="btn-warning">Reset</a>
                        </form>
                    </div>
                    <button class="logout-btn" onclick="openModal('createCourseModal')">Add New Course</button>
                </div>

                <form method="post" action="courses.php">
                    <div class="bulk-actions">
                        <select name="bulk_action" required>
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="logout-btn">Apply</button>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="bulk-select-all"></th>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Credits</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_courses[]" value="<?= $course['id'] ?>"
                                            class="bulk-select-item"></td>
                                    <td><?= htmlspecialchars($course['code']) ?></td>
                                    <td><?= htmlspecialchars($course['title']) ?></td>
                                    <td><?= htmlspecialchars($course['credits']) ?></td>
                                    <td><?= htmlspecialchars($course['department_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span
                                            class="status-badge <?= $course['status'] ?>"><?= ucfirst($course['status']) ?></span>
                                    </td>
                                    <td>
                                        <button class="logout-btn" onclick="openEditModal(<?= $course['id'] ?>)">Edit</button>
                                        <form method="post" action="courses.php" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $course['id'] ?>">
                                            <button type="submit" name="delete_course" class="logout-btn" onclick="return confirm('Are you sure you want to delete this course?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="courses.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&department=<?= $department_filter ?>"
                                class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal" id="createCourseModal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createCourseModal')">&times;</span>
            <h2>Create New Course</h2>
            <div class="modal-body">
                <form method="post" action="courses.php">
                    <div class="form-group">
                        <label for="code">Course Code</label>
                        <input type="text" id="code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="title">Course Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="credits">Credits</label>
                        <input type="number" id="credits" name="credits" min="1" max="6" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="create_course" class="logout-btn">Create Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal" id="editCourseModal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editCourseModal')">&times;</span>
            <h2>Edit Course</h2>
            <div class="modal-body">
                <form method="post" action="courses.php">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_code">Course Code</label>
                        <input type="text" id="edit_code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_title">Course Title</label>
                        <input type="text" id="edit_title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_credits">Credits</label>
                        <input type="number" id="edit_credits" name="credits" min="1" max="6" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <select id="edit_department" name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_course" class="logout-btn">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Bulk select all checkbox
        document.getElementById('bulk-select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.bulk-select-item');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Function to open edit modal with course data
        function openEditModal(courseId) {
            // In a real application, you would fetch the course data via AJAX
            // For this example, we'll use the data from the table row
            
            const row = document.querySelector(`tr input[value="${courseId}"]`).closest('tr');
            const cells = row.cells;
            
            document.getElementById('edit_id').value = courseId;
            document.getElementById('edit_code').value = cells[1].textContent;
            document.getElementById('edit_title').value = cells[2].textContent;
            document.getElementById('edit_credits').value = cells[3].textContent;
            
            // For department, we need to find the matching option
            const deptName = cells[4].textContent;
            const deptSelect = document.getElementById('edit_department');
            for (let i = 0; i < deptSelect.options.length; i++) {
                if (deptSelect.options[i].text === deptName) {
                    deptSelect.selectedIndex = i;
                    break;
                }
            }
            
            // For status
            const statusBadge = cells[5].querySelector('.status-badge');
            const status = statusBadge ? statusBadge.textContent.toLowerCase() : 'active';
            document.getElementById('edit_status').value = status;
            
            // Open the modal
            openModal('editCourseModal');
        }
    </script>
</body>

</html>