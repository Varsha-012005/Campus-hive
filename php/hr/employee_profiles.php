<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hr') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle form submissions for editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_employee'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    
    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $user_id]);
    
    // If faculty, update salary if provided
    if (isset($_POST['salary'])) {
        $salary = $_POST['salary'];
        $stmt = $pdo->prepare("UPDATE faculty SET salary = ? WHERE user_id = ?");
        $stmt->execute([$salary, $user_id]);
    }
    
    $_SESSION['message'] = "Employee updated successfully";
    header("Location: employee_profiles.php?action=view&id=" . $user_id);
    exit();
}

// Get all employees with their user information and attendance data
$employees = $pdo->query("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.role, u.status,
        COALESCE(f.faculty_id, h.hr_id, fi.finance_id, c.campus_id) AS employee_id,
        COALESCE(f.hire_date, h.hire_date, fi.hire_date, c.hire_date) AS hire_date,
        f.salary,
        (
            SELECT COUNT(*) 
            FROM attendance 
            WHERE id = u.id AND status = 'present'
        ) AS present_days,
        (
            SELECT COUNT(*) 
            FROM attendance 
            WHERE id = u.id
        ) AS total_days
    FROM users u
    LEFT JOIN faculty f ON u.id = f.user_id AND u.role = 'faculty'
    LEFT JOIN hr_staff h ON u.id = h.user_id AND u.role = 'hr'
    LEFT JOIN finance_staff fi ON u.id = fi.user_id AND u.role = 'finance'
    LEFT JOIN campus_staff c ON u.id = c.user_id AND u.role = 'campus'
    WHERE u.role IN ('faculty', 'hr', 'finance', 'campus', 'admin')
    ORDER BY hire_date DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profiles</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
    <style>
        .profile-card {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .profile-card.active {
            display: block;
        }
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .stat-box {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            width: 30%;
            text-align: center;
        }
        .attendance-bar {
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            margin-top: 5px;
            overflow: hidden;
        }
        .attendance-progress {
            height: 100%;
            background: #4CAF50;
        }
        .edit-form {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Employee Profiles</h1>
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
                        <li class="active"><a href="employee_profiles.php">Employee Profiles</a></li>
                        <li><a href="recruitment.php">Recruitment</a></li>
                        <li><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li><a href="performance.php">Performance</a></li>
                        <li><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <div class="action-bar">
                    <h2>All Employees</h2>
                </div>

                <?php if (isset($_GET['action']) && isset($_GET['id'])): 
                    $employee_id = $_GET['id'];
                    $employee = array_filter($employees, function($e) use ($employee_id) {
                        return $e['id'] == $employee_id;
                    });
                    $employee = reset($employee);
                    if ($employee): 
                        $attendance_percentage = ($employee['total_days'] > 0) ? round(($employee['present_days'] / $employee['total_days']) * 100, 2) : 0;
                        ?>
                        <div class="profile-card active" id="profile-<?= $employee['id'] ?>">
                            <h3><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                            <p>Email: <?= htmlspecialchars($employee['email']) ?></p>
                            <p>Role: <?= ucfirst(htmlspecialchars($employee['role'])) ?></p>
                            <p>Employee ID: <?= htmlspecialchars($employee['employee_id']) ?></p>
                            <p>Hire Date: <?= date('M j, Y', strtotime($employee['hire_date'])) ?></p>
                            <p>Status: <span class="status-badge <?= $employee['status'] ?>">
                                <?= ucfirst($employee['status']) ?>
                            </span></p>
                            
                            <?php if ($employee['salary']): ?>
                                <p>Salary: $<?= number_format($employee['salary'], 2) ?></p>
                            <?php endif; ?>
                            
                            <p>Attendance: <?= $attendance_percentage ?>% (<?= $employee['present_days'] ?> present out of <?= $employee['total_days'] ?> days)</p>
                            <div class="attendance-bar">
                                <div class="attendance-progress" style="width: <?= $attendance_percentage ?>%"></div>
                            </div>
                            
                            <div class="stats-container">
                                <div class="stat-box">
                                    <h4>Years of Service</h4>
                                    <p><?= date_diff(date_create($employee['hire_date']), date_create('now'))->y ?></p>
                                </div>
                                <div class="stat-box">
                                    <h4>Status</h4>
                                    <p><?= ucfirst($employee['status']) ?></p>
                                </div>
                                <div class="stat-box">
                                    <h4>Role</h4>
                                    <p><?= ucfirst($employee['role']) ?></p>
                                </div>
                            </div>
                            
                            <?php if ($_GET['action'] == 'edit'): ?>
                                <div class="edit-form">
                                    <h3>Edit Employee</h3>
                                    <form method="post" action="employee_profiles.php">
                                        <input type="hidden" name="user_id" value="<?= $employee['id'] ?>">
                                        
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select name="status" id="status" required>
                                                <option value="active" <?= $employee['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= $employee['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                        
                                        <?php if ($employee['role'] == 'faculty'): ?>
                                            <div class="form-group">
                                                <label for="salary">Salary</label>
                                                <input type="number" name="salary" id="salary" step="0.01" 
                                                       value="<?= $employee['salary'] ?>" required>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <button type="submit" name="edit_employee" class="logout-btn">Save Changes</button>
                                        <a href="employee_profiles.php?action=view&id=<?= $employee['id'] ?>" class="logout-btn">Cancel</a>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="action-buttons">
                                    <a href="employee_profiles.php?action=edit&id=<?= $employee['id'] ?>" class="logout-btn">Edit</a>
                                    <a href="employee_profiles.php" class="logout-btn">Back to List</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="hr-card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Hire Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($employee['employee_id']) ?></td>
                                        <td><?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?></td>
                                        <td><?= htmlspecialchars($employee['email']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($employee['role'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($employee['hire_date'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= $employee['status'] ?>">
                                                <?= ucfirst($employee['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="employee_profiles.php?action=edit&id=<?= $employee['id'] ?>" class="logout-btn">Edit</a>
                                            <a href="employee_profiles.php?action=view&id=<?= $employee['id'] ?>" class="logout-btn">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="/university-system/js/hr.js"></script>
</body>
</html>