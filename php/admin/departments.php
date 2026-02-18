<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle department creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_department'])) {
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['description'];

    try {
        $stmt = $pdo->prepare("INSERT INTO departments (name, code, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $code, $description]);
        $success = "Department created successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'create_department', "Created department: $code - $name"]);
    } catch (PDOException $e) {
        $error = "Error creating department: " . $e->getMessage();
    }
}

// Handle department update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_department'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['description'];

    try {
        $stmt = $pdo->prepare("UPDATE departments SET name = ?, code = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $code, $description, $id]);
        $success = "Department updated successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'update_department', "Updated department: $code - $name"]);
    } catch (PDOException $e) {
        $error = "Error updating department: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if (!empty($_POST['selected_departments'])) {
        $selected_departments = $_POST['selected_departments'];
        $placeholders = implode(',', array_fill(0, count($selected_departments), '?'));

        try {
            if ($_POST['bulk_action'] == 'delete') {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
                $stmt->execute($selected_departments);
                $success = count($selected_departments) . " departments deleted successfully!";
            }

            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'bulk_department_action', "Performed " . $_POST['bulk_action'] . " on " . count($selected_departments) . " departments"]);
        } catch (PDOException $e) {
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error = "No departments selected for bulk action";
    }
}

// Get department data for editing if requested
$edit_department = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_department = $stmt->fetch();
}

// Get all departments with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Main query with placeholders for search terms
$query = "SELECT * FROM departments WHERE 1=1";
$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR code LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term]);
}

// Add pagination - note these are integers, not part of the parameters array
$query .= " ORDER BY name LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll();

// Count query for pagination
$count_query = "SELECT COUNT(*) FROM departments WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (name LIKE ? OR code LIKE ? OR description LIKE ?)";
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_departments = $count_stmt->fetchColumn();
$total_pages = ceil($total_departments / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
    <style>
        .modal-content {
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 100px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Department Management</h1>
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
                        <li class="active"><a href="departments.php">Departments</a></li>
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
                        <form method="get" action="departments.php">
                            <input type="text" name="search" placeholder="Search departments..."
                                value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="logout-btn">Search</button>
                            <a href="departments.php" class="btn-warning">Reset</a>
                        </form>
                    </div>
                    <button class="logout-btn" data-modal-target="#createDepartmentModal">Add New Department</button>
                </div>

                <form method="post" action="departments.php">
                    <div class="bulk-actions">
                        <select name="bulk_action" required>
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="logout-btn">Apply</button>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="bulk-select-all"></th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_departments[]" value="<?= $dept['id'] ?>"
                                            class="bulk-select-item"></td>
                                    <td><?= htmlspecialchars($dept['code']) ?></td>
                                    <td><?= htmlspecialchars($dept['name']) ?></td>
                                    <td><?= htmlspecialchars($dept['description']) ?></td>
                                    <td>
                                        <a href="departments.php?edit_id=<?= $dept['id'] ?>" class="logout-btn">Edit</a>
                                        <a href="departments.php?delete_id=<?= $dept['id'] ?>" class="logout-btn"
                                            onclick="return confirm('Are you sure you want to delete this department?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="departments.php?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                                class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create Department Modal -->
    <div class="modal" id="createDepartmentModal">
        <div class="modal-content">
            <span class="close" data-close-modal>&times;</span>
            <h2>Create New Department</h2>
            <form method="post" action="departments.php">
                <div class="form-group">
                    <label for="code">Department Code</label>
                    <input type="text" id="code" name="code" required>
                </div>
                <div class="form-group">
                    <label for="name">Department Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <button type="submit" name="create_department" class="logout-btn">Create Department</button>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <?php if ($edit_department): ?>
        <div class="modal" id="editDepartmentModal" style="display: flex">
            <div class="modal-content">
                <span class="close" onclick="window.location.href='departments.php'">&times;</span>
                <h2>Edit Department</h2>
                <form method="post" action="departments.php">
                    <input type="hidden" name="id" value="<?= $edit_department['id'] ?>">
                    <div class="form-group">
                        <label for="edit_code">Department Code</label>
                        <input type="text" id="edit_code" name="code"
                            value="<?= htmlspecialchars($edit_department['code']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_name">Department Name</label>
                        <input type="text" id="edit_name" name="name"
                            value="<?= htmlspecialchars($edit_department['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description"
                            rows="3"><?= htmlspecialchars($edit_department['description']) ?></textarea>
                    </div>
                    <button type="submit" name="update_department" class="logout-btn">Update Department</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script src="/university-system/js/admin.js"></script>
    <script>
        // Handle bulk select all checkbox
        document.getElementById('bulk-select-all').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.bulk-select-item');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal')) {
                window.location.href = 'departments.php';
            }
        });

        // Handle delete action
        <?php if (isset($_GET['delete_id'])): ?>
            if (confirm('Are you sure you want to delete this department?')) {
                window.location.href = 'departments.php?confirm_delete=<?= $_GET['delete_id'] ?>';
            } else {
                window.location.href = 'departments.php';
            }
        <?php endif; ?>

        // Handle confirmed delete
        <?php if (isset($_GET['confirm_delete'])): ?>
            <?php
            try {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$_GET['confirm_delete']]);
                $success = "Department deleted successfully!";

                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], 'delete_department', "Deleted department ID: " . $_GET['confirm_delete']]);

                header("Location: departments.php?success=" . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = "Error deleting department: " . $e->getMessage();
                header("Location: departments.php?error=" . urlencode($error));
                exit();
            }
            ?>
<?php endif; ?>
    </script>
</body>

</html>