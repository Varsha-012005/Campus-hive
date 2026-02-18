<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hr') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_attendance'])) {
    $attendance_id = $_POST['attendance_id'];
    $status = $_POST['status'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $notes = $_POST['notes'];

    $stmt = $pdo->prepare("
        UPDATE staff_attendance 
        SET status = :status, 
            check_in = :check_in, 
            check_out = :check_out,
            notes = :notes,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':status' => $status,
        ':check_in' => $check_in,
        ':check_out' => $check_out,
        ':notes' => $notes,
        ':id' => $attendance_id
    ]);

    $_SESSION['message'] = "Attendance record updated successfully";
    header("Location: attendance_payroll.php");
    exit();
}

// Get filter parameters for attendance
$attendance_order_by = $_GET['attendance_order'] ?? 'sa.date DESC';
$attendance_status_filter = $_GET['attendance_status'] ?? '';
$attendance_role_filter = $_GET['attendance_role'] ?? '';
$attendance_date_from = $_GET['date_from'] ?? '';
$attendance_date_to = $_GET['date_to'] ?? '';

// Build query conditions for attendance
$attendance_conditions = [];
$attendance_params = [];

if ($attendance_status_filter) {
    $attendance_conditions[] = "sa.status = :attendance_status";
    $attendance_params[':attendance_status'] = $attendance_status_filter;
}

if ($attendance_role_filter) {
    $attendance_conditions[] = "u.role = :attendance_role";
    $attendance_params[':attendance_role'] = $attendance_role_filter;
}

if ($attendance_date_from) {
    $attendance_conditions[] = "sa.date >= :date_from";
    $attendance_params[':date_from'] = $attendance_date_from;
}

if ($attendance_date_to) {
    $attendance_conditions[] = "sa.date <= :date_to";
    $attendance_params[':date_to'] = $attendance_date_to;
}

$attendance_where_clause = $attendance_conditions ? "WHERE " . implode(" AND ", $attendance_conditions) : "";

// Get attendance data with filters
$attendance_query = "
    SELECT sa.*, u.first_name, u.last_name, u.role 
    FROM staff_attendance sa
    JOIN users u ON sa.user_id = u.id
    $attendance_where_clause
    ORDER BY $attendance_order_by
    LIMIT 100
";

$attendance_stmt = $pdo->prepare($attendance_query);
$attendance_stmt->execute($attendance_params);
$attendance = $attendance_stmt->fetchAll();

// Process payroll
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $period_start = $_POST['period_start'];
    $period_end = $_POST['period_end'];

    // First, check if payroll already exists for this period
    $check = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE pay_period_start = ? AND pay_period_end = ?");
    $check->execute([$period_start, $period_end]);

    if ($check->fetchColumn() > 0) {
        $_SESSION['error'] = "Payroll already processed for this period";
        header("Location: attendance_payroll.php");
        exit();
    }

    // Get all active staff members with their salary information
    $staff = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.role, 
               f.salary, f.contract_type,
               cs.position as campus_position,
               fs.position as finance_position
        FROM users u
        LEFT JOIN faculty f ON u.id = f.user_id
        LEFT JOIN campus_staff cs ON u.id = cs.user_id
        LEFT JOIN finance_staff fs ON u.id = fs.user_id
        WHERE u.role IN ('hr', 'faculty', 'admin', 'finance', 'campus')
        AND u.status = 'active'
    ")->fetchAll();

    $processed_employees = [];

    foreach ($staff as $employee) {
        // Get attendance records for the period
        $attendance = $pdo->prepare("
            SELECT COUNT(*) as present_days,
                   SUM(TIMESTAMPDIFF(HOUR, check_in, check_out)) as worked_hours
            FROM staff_attendance
            WHERE user_id = :user_id
            AND date BETWEEN :start AND :end
            AND status IN ('present', 'late', 'half-day')
        ");
        $attendance->execute([
            ':user_id' => $employee['id'],
            ':start' => $period_start,
            ':end' => $period_end
        ]);
        $attendance_data = $attendance->fetch();
        $present_days = $attendance_data['present_days'];
        $worked_hours = $attendance_data['worked_hours'];

        // Calculate salary based on role and contract type
        $gross_amount = 0;

        if ($employee['role'] == 'faculty') {
            // Faculty salary calculation
            if ($employee['contract_type'] == 'full-time') {
                $gross_amount = $employee['salary'] * ($present_days / 22); // Monthly salary prorated
            } else {
                // Part-time or visiting faculty - hourly rate
                $hourly_rate = $employee['salary'] / 160; // Assuming 160 hours/month for full-time
                $gross_amount = $hourly_rate * ($worked_hours ?: 0);
            }
        } else {
            // Non-faculty staff - use position-based salaries
            switch ($employee['role']) {
                case 'hr':
                    $gross_amount = 2500 * ($present_days / 22); // Example HR salary
                    break;
                case 'finance':
                    $gross_amount = 2300 * ($present_days / 22); // Example Finance salary
                    break;
                case 'campus':
                    $gross_amount = 2000 * ($present_days / 22); // Example Campus staff salary
                    break;
                case 'admin':
                    $gross_amount = 2700 * ($present_days / 22); // Example Admin salary
                    break;
            }
        }

        // Calculate deductions (tax, insurance, etc.)
        $deductions = calculateDeductions($gross_amount, $employee['role']);
        $net_amount = $gross_amount - $deductions;

        // Insert payroll record
        $stmt = $pdo->prepare("
            INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, 
                              gross_amount, deductions, net_amount, payment_date, status)
            VALUES (:employee_id, :start, :end, :gross, :deductions, :net, CURDATE(), 'processed')
        ");
        $stmt->execute([
            ':employee_id' => $employee['id'],
            ':start' => $period_start,
            ':end' => $period_end,
            ':gross' => $gross_amount,
            ':deductions' => $deductions,
            ':net' => $net_amount
        ]);

        $processed_employees[] = [
            'name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'role' => $employee['role'],
            'gross' => $gross_amount,
            'net' => $net_amount
        ];
    }

    $_SESSION['current_payroll'] = [
        'period_start' => $period_start,
        'period_end' => $period_end,
        'employees' => $processed_employees
    ];

    $_SESSION['message'] = "Payroll processed for period " . date('M j, Y', strtotime($period_start)) . " to " . date('M j, Y', strtotime($period_end));
    header("Location: attendance_payroll.php");
    exit();
}

// Helper function to calculate deductions
function calculateDeductions($gross_amount, $role)
{
    // Basic tax calculation (simplified)
    $tax = $gross_amount * 0.15; // 15% tax

    // Role-specific deductions
    $insurance = 0;
    if ($role == 'faculty') {
        $insurance = $gross_amount * 0.05; // 5% for faculty
    } else {
        $insurance = $gross_amount * 0.03; // 3% for other staff
    }

    return $tax + $insurance;
}

// Get filter parameters
$order_by = $_GET['order_by'] ?? 'pay_period_start DESC';
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($status_filter) {
    $conditions[] = "p.status = :status";
    $params[':status'] = $status_filter;
}

if ($role_filter) {
    $conditions[] = "u.role = :role";
    $params[':role'] = $role_filter;
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// Get payroll history
$payroll_query = "
    SELECT p.*, u.first_name, u.last_name, u.role
    FROM payroll p
    JOIN users u ON p.employee_id = u.id
    $where_clause
    ORDER BY $order_by
    LIMIT 20
";

$stmt = $pdo->prepare($payroll_query);
$stmt->execute($params);
$payrolls = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance & Payroll</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
    <style>
        .tab-container {
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: #f1f1f1;
            border: none;
            cursor: pointer;
            margin-right: 5px;
        }

        .tab-btn.active {
            background: #4CAF50;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-badge.present {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-badge.late {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.leave {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-badge.half-day {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-badge.processed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .filter-container select,
        .filter-container button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .current-payroll {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }

        .current-payroll h3 {
            margin-top: 0;
            color: #4CAF50;
        }

        .current-payroll table {
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Attendance & Payroll</h1>
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
                        <li><a href="employee_profiles.php">Staff Profiles</a></li>
                        <li><a href="recruitment.php">Recruitment</a></li>
                        <li class="active"><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li><a href="performance.php">Performance</a></li>
                        <li><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['message'] ?>
                        <?php unset($_SESSION['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-btn active logout-btn" onclick="openTab('attendance')">Attendance</button>
                        <button class="tab-btn logout-btn" onclick="openTab('payroll')">Payroll</button>
                    </div>

                    <div id="attendance" class="tab-content active">
                        <div class="action-bar">
                            <h2>Recent Attendance</h2>
                            <button class="logout-btn" onclick="showModal('payrollModal')">Process Payroll</button>
                        </div>

                        <div class="filter-container">
                            <select id="attendanceOrder" onchange="updateAttendanceFilters()">
                                <option value="sa.date DESC" <?= $attendance_order_by == 'sa.date DESC' ? 'selected' : '' ?>>Newest First</option>
                                <option value="sa.date ASC" <?= $attendance_order_by == 'sa.date ASC' ? 'selected' : '' ?>>
                                    Oldest First</option>
                                <option value="u.last_name ASC" <?= $attendance_order_by == 'u.last_name ASC' ? 'selected' : '' ?>>By Name (A-Z)</option>
                                <option value="u.last_name DESC" <?= $attendance_order_by == 'u.last_name DESC' ? 'selected' : '' ?>>By Name (Z-A)</option>
                            </select>

                            <select id="attendanceStatus" onchange="updateAttendanceFilters()">
                                <option value="">All Statuses</option>
                                <option value="present" <?= $attendance_status_filter == 'present' ? 'selected' : '' ?>>
                                    Present</option>
                                <option value="absent" <?= $attendance_status_filter == 'absent' ? 'selected' : '' ?>>
                                    Absent</option>
                                <option value="late" <?= $attendance_status_filter == 'late' ? 'selected' : '' ?>>Late
                                </option>
                                <option value="leave" <?= $attendance_status_filter == 'leave' ? 'selected' : '' ?>>Leave
                                </option>
                                <option value="half-day" <?= $attendance_status_filter == 'half-day' ? 'selected' : '' ?>>
                                    Half Day</option>
                            </select>

                            <select id="attendanceRole" onchange="updateAttendanceFilters()">
                                <option value="">All Roles</option>
                                <option value="faculty" <?= $attendance_role_filter == 'faculty' ? 'selected' : '' ?>>
                                    Faculty</option>
                                <option value="hr" <?= $attendance_role_filter == 'hr' ? 'selected' : '' ?>>HR</option>
                                <option value="admin" <?= $attendance_role_filter == 'admin' ? 'selected' : '' ?>>Admin
                                </option>
                                <option value="finance" <?= $attendance_role_filter == 'finance' ? 'selected' : '' ?>>
                                    Finance</option>
                                <option value="campus" <?= $attendance_role_filter == 'campus' ? 'selected' : '' ?>>Campus
                                </option>
                            </select>

                            <input type="date" id="dateFrom" value="<?= $attendance_date_from ?>"
                                onchange="updateAttendanceFilters()" placeholder="From Date">
                                <p>To</p>
                            <input type="date" id="dateTo" value="<?= $attendance_date_to ?>"
                                onchange="updateAttendanceFilters()" placeholder="To Date">

                            <button class="logout-btn" onclick="resetAttendanceFilters()">Reset Filters</button>
                        </div>

                        <div class="hr-card">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Staff</th>
                                        <th>Role</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $record): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                            <td><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']) ?>
                                            </td>
                                            <td><?= ucfirst($record['role']) ?></td>
                                            <td><?= $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '--' ?>
                                            </td>
                                            <td><?= $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '--' ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $record['status'] ?>">
                                                    <?= ucfirst($record['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="logout-btn edit-btn" onclick="showEditModal(
                                    '<?= $record['id'] ?>',
                                    '<?= $record['date'] ?>',
                                    '<?= $record['first_name'] ?> <?= $record['last_name'] ?>',
                                    '<?= $record['check_in'] ?>',
                                    '<?= $record['check_out'] ?>',
                                    '<?= $record['status'] ?>',
                                    '<?= htmlspecialchars($record['notes'] ?? '') ?>'
                                )">Edit</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="payroll" class="tab-content">
                        <?php if (isset($_SESSION['current_payroll'])): ?>
                            <div class="current-payroll">
                                <h3>Current Payroll
                                    (<?= date('M j, Y', strtotime($_SESSION['current_payroll']['period_start'])) ?> to
                                    <?= date('M j, Y', strtotime($_SESSION['current_payroll']['period_end'])) ?>)
                                </h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Role</th>
                                            <th>Gross Pay</th>
                                            <th>Net Pay</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($_SESSION['current_payroll']['employees'] as $employee): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($employee['name']) ?></td>
                                                <td><?= ucfirst($employee['role']) ?></td>
                                                <td>$<?= number_format($employee['gross'], 2) ?></td>
                                                <td>$<?= number_format($employee['net'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php unset($_SESSION['current_payroll']); ?>
                        <?php endif; ?>

                        <div class="action-bar">
                            <h2>Payroll History</h2>
                            <div class="filter-container">
                                <select id="orderBy" onchange="updateFilters()">
                                    <option value="pay_period_start DESC" <?= $order_by == 'pay_period_start DESC' ? 'selected' : '' ?>>Newest First</option>
                                    <option value="pay_period_start ASC" <?= $order_by == 'pay_period_start ASC' ? 'selected' : '' ?>>Oldest First</option>
                                </select>

                                <select id="statusFilter" onchange="updateFilters()">
                                    <option value="">All Statuses</option>
                                    <option value="processed" <?= $status_filter == 'processed' ? 'selected' : '' ?>>
                                        Processed</option>
                                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending
                                    </option>
                                </select>

                                <select id="roleFilter" onchange="updateFilters()">
                                    <option value="">All Roles</option>
                                    <option value="faculty" <?= $role_filter == 'faculty' ? 'selected' : '' ?>>Faculty
                                    </option>
                                    <option value="hr" <?= $role_filter == 'hr' ? 'selected' : '' ?>>HR</option>
                                    <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="finance" <?= $role_filter == 'finance' ? 'selected' : '' ?>>Finance
                                    </option>
                                    <option value="campus" <?= $role_filter == 'campus' ? 'selected' : '' ?>>Campus
                                    </option>
                                </select>

                                <button class="logout-btn" onclick="resetFilters()">Reset Filters</button>
                            </div>
                        </div>

                        <div class="hr-card">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Employee</th>
                                        <th>Role</th>
                                        <th>Gross</th>
                                        <th>Deductions</th>
                                        <th>Net</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payrolls as $payroll): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($payroll['pay_period_start'])) ?> to
                                                <?= date('M j, Y', strtotime($payroll['pay_period_end'])) ?>
                                            </td>
                                            <td><?= htmlspecialchars($payroll['last_name'] . ', ' . $payroll['first_name']) ?>
                                            </td>
                                            <td><?= ucfirst($payroll['role']) ?></td>
                                            <td>$<?= number_format($payroll['gross_amount'], 2) ?></td>
                                            <td>$<?= number_format($payroll['deductions'], 2) ?></td>
                                            <td>$<?= number_format($payroll['net_amount'], 2) ?></td>
                                            <td>
                                                <span class="status-badge <?= $payroll['status'] ?>">
                                                    <?= ucfirst($payroll['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payroll Modal -->
    <div class="modal" id="payrollModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('payrollModal')">&times;</span>
            <h2>Process Payroll</h2>
            <form method="post" action="attendance_payroll.php">
                <div class="form-group">
                    <label for="period_start">Period Start</label>
                    <input type="date" name="period_start" id="period_start" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="period_end">Period End</label>
                    <input type="date" name="period_end" id="period_end" required class="form-control">
                </div>
                <button type="submit" name="process_payroll" class="logout-btn">Process Payroll</button>
            </form>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal" id="editAttendanceModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('editAttendanceModal')">&times;</span>
            <h2>Edit Attendance Record</h2>
            <form method="post" action="attendance_payroll.php">
                <input type="hidden" name="attendance_id" id="edit_attendance_id">

                <div class="form-group">
                    <label>Date</label>
                    <p id="edit_date_display"></p>
                </div>

                <div class="form-group">
                    <label>Staff Member</label>
                    <p id="edit_staff_display"></p>
                </div>

                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="leave">Leave</option>
                        <option value="half-day">Half Day</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_check_in">Check In</label>
                    <input type="time" name="check_in" id="edit_check_in" class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_check_out">Check Out</label>
                    <input type="time" name="check_out" id="edit_check_out" class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" name="update_attendance" class="logout-btn">Update Attendance</button>
            </form>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }

            // Remove active class from all tab buttons
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }

            // Show the selected tab and mark its button as active
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Close modal if clicked outside
        window.onclick = function (event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        function showEditModal(id, date, staff, checkIn, checkOut, status, notes) {
            document.getElementById('edit_attendance_id').value = id;
            document.getElementById('edit_date_display').textContent = new Date(date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric'
            });
            document.getElementById('edit_staff_display').textContent = staff;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_check_in').value = checkIn ? checkIn.substring(0, 5) : '';
            document.getElementById('edit_check_out').value = checkOut ? checkOut.substring(0, 5) : '';
            document.getElementById('edit_notes').value = notes;

            document.getElementById('editAttendanceModal').style.display = 'block';
        }

        function updateFilters() {
            const orderBy = document.getElementById('orderBy').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const roleFilter = document.getElementById('roleFilter').value;

            let url = 'attendance_payroll.php?tab=payroll';

            if (orderBy) {
                url += `&order_by=${encodeURIComponent(orderBy)}`;
            }

            if (statusFilter) {
                url += `&status=${encodeURIComponent(statusFilter)}`;
            }

            if (roleFilter) {
                url += `&role=${encodeURIComponent(roleFilter)}`;
            }

            window.location.href = url;
        }

        function updateAttendanceFilters() {
            const orderBy = document.getElementById('attendanceOrder').value;
            const statusFilter = document.getElementById('attendanceStatus').value;
            const roleFilter = document.getElementById('attendanceRole').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            let url = 'attendance_payroll.php?tab=attendance';

            if (orderBy) {
                url += `&attendance_order=${encodeURIComponent(orderBy)}`;
            }

            if (statusFilter) {
                url += `&attendance_status=${encodeURIComponent(statusFilter)}`;
            }

            if (roleFilter) {
                url += `&attendance_role=${encodeURIComponent(roleFilter)}`;
            }

            if (dateFrom) {
                url += `&date_from=${encodeURIComponent(dateFrom)}`;
            }

            if (dateTo) {
                url += `&date_to=${encodeURIComponent(dateTo)}`;
            }

            window.location.href = url;
        }

        function resetAttendanceFilters() {
            window.location.href = 'attendance_payroll.php?tab=attendance';
        }

        function resetFilters() {
            window.location.href = 'attendance_payroll.php?tab=payroll';
        }

        // Set active tab based on URL parameter
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');

            if (tabParam) {
                openTab(tabParam);
            }
        });
    </script>
</body>

</html>