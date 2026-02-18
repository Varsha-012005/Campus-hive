<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /university-system/login.html");
    exit();
}

if ($_SESSION['role'] != 'admin') {
    header("Location: /university-system/unauthorized.php");
    exit();
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // Create user record
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$first_name, $last_name, $email, $password, $role, $status]);
        $user_id = $pdo->lastInsertId();

        // Create role-specific record
        if ($role == 'student') {
            $student_id = 'S' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, advisor_id) 
                                  VALUES (?, ?, NULL)");
            $stmt->execute([$user_id, $student_id]);
        } elseif ($role == 'faculty') {
            $faculty_id = 'F' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO faculty (user_id, faculty_id, department_id) 
                                  VALUES (?, ?, NULL)");
            $stmt->execute([$user_id, $faculty_id]);
        }

        $pdo->commit();
        $success = "User created successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'create_user', "Created $role user: $email"]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error creating user: " . $e->getMessage();
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $email, $role, $status, $user_id]);
        $success = "User updated successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'update_user', "Updated user: $email"]);
    } catch (PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if (!empty($_POST['selected_users'])) {
        $selected_users = $_POST['selected_users'];
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));

        try {
            if ($_POST['bulk_action'] == 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($selected_users);
                $success = count($selected_users) . " users activated successfully!";
            } elseif ($_POST['bulk_action'] == 'deactivate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($selected_users);
                $success = count($selected_users) . " users deactivated successfully!";
            } elseif ($_POST['bulk_action'] == 'delete') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                $stmt->execute($selected_users);
                $success = count($selected_users) . " users deleted successfully!";
            }

            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                                  VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'bulk_user_action', "Performed " . $_POST['bulk_action'] . " on " . count($selected_users) . " users"]);
        } catch (PDOException $e) {
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error = "No users selected for bulk action";
    }
}

// Get user data for editing if requested
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_user = $stmt->fetch();
}

// Get all users with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT u.*, 
          s.student_id, f.faculty_id
          FROM users u
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN faculty f ON u.id = f.user_id
          WHERE 1=1";

$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ? OR f.faculty_id LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $count_params[] = $role_filter;
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

// For MySQL/MariaDB, we need to add LIMIT and OFFSET without parameters
$query .= " ORDER BY u.last_name, u.first_name LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN faculty f ON u.id = f.user_id
                WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ? OR f.faculty_id LIKE ?)";
}

if (!empty($role_filter)) {
    $count_query .= " AND u.role = ?";
}

if (!empty($status_filter)) {
    $count_query .= " AND u.status = ?";
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>User Management</h1>
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
                        <li class="active"><a href="users.php">User Management</a></li>
                        <li><a href="courses.php">Course Management</a></li>
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
                        <form method="get" action="users.php">
                            <input type="text" name="search" placeholder="Search users..."
                                value="<?= htmlspecialchars($search) ?>">
                            <select name="role">
                                <option value="">All Roles</option>
                                <option value="student" <?= $role_filter == 'student' ? 'selected' : '' ?>>Students</option>
                                <option value="faculty" <?= $role_filter == 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admins</option>
                                <option value="hr" <?= $role_filter == 'hr' ? 'selected' : '' ?>>HR</option>
                                <option value="finance" <?= $role_filter == 'finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="campus" <?= $role_filter == 'campus' ? 'selected' : '' ?>>Campus</option>
                            </select>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                            <button type="submit" class="logout-btn">Filter</button>
                            <a href="users.php" class="btn-warning">Reset</a>
                        </form>
                    </div>
                    <button class="logout-btn" data-modal-target="#createUserModal">Add New User</button>
                </div>

                <form method="post" action="users.php">
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
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>"
                                            class="bulk-select-item"></td>
                                    <td>
                                        <?= $user['role'] == 'student' ? htmlspecialchars($user['student_id']) :
                                            ($user['role'] == 'faculty' ? htmlspecialchars($user['faculty_id']) :
                                                'A' . str_pad($user['id'], 6, '0', STR_PAD_LEFT)) ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['last_name'] . ', ' . $user['first_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="role-badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="users.php?edit_id=<?= $user['id'] ?>" class="logout-btn">Edit</a>
                                        <a href="user_delete.php?id=<?= $user['id'] ?>" class="logout-btn"
                                            onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="users.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>"
                                class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal" id="createUserModal">
        <div class="modal-content">
            <span class="close" data-close-modal>&times;</span>
            <h2>Create New User</h2>
            <form method="post" action="users.php">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                        <option value="admin">Admin</option>
                        <option value="hr">HR</option>
                        <option value="finance">Finance</option>
                        <option value="campus">Campus</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <button type="submit" name="create_user" class="logout-btn">Create User</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <?php if ($edit_user): ?>
    <div class="modal" id="editUserModal" style="display: flex">
        <div class="modal-content">
            <span class="close" onclick="window.location.href='users.php'">&times;</span>
            <h2>Edit User</h2>
            <form method="post" action="users.php">
                <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                <div class="form-group">
                    <label for="edit_first_name">First Name</label>
                    <input type="text" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($edit_user['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_last_name">Last Name</label>
                    <input type="text" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($edit_user['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select id="edit_role" name="role" required>
                        <option value="student" <?= $edit_user['role'] == 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="faculty" <?= $edit_user['role'] == 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="admin" <?= $edit_user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="hr" <?= $edit_user['role'] == 'hr' ? 'selected' : '' ?>>HR</option>
                        <option value="finance" <?= $edit_user['role'] == 'finance' ? 'selected' : '' ?>>Finance</option>
                        <option value="campus" <?= $edit_user['role'] == 'campus' ? 'selected' : '' ?>>Campus</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="active" <?= $edit_user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $edit_user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="pending" <?= $edit_user['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <button type="submit" name="update_user" class="logout-btn">Update User</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="/university-system/js/admin.js"></script>
    <script>
        // Handle bulk select all checkbox
        document.getElementById('bulk-select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.bulk-select-item');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                window.location.href = 'users.php';
            }
        });
    </script>
</body>

</html>