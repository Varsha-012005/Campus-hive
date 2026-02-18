<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hr') {
    header("Location: /university-system/login.html");
    exit();
}

// Get total staff count (all roles)
$total_staff = $pdo->query("
    SELECT COUNT(*) FROM users 
    WHERE role IN ('hr', 'faculty', 'admin', 'finance', 'campus')
")->fetchColumn();

// Get active recruitments
$active_recruitments = $pdo->query("SELECT COUNT(*) FROM recruitment WHERE status = 'active'")->fetchColumn();

// Get pending approvals (leave requests from all staff types)
$pending_approvals = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE status = 'pending'
")->fetchColumn();

// Recent activities (from all HR staff)
$recent_activities = $pdo->query("
    SELECT al.*, u.first_name, u.last_name 
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    WHERE u.role IN ('hr', 'faculty', 'admin', 'finance', 'campus')
    ORDER BY al.timestamp DESC 
    LIMIT 5
")->fetchAll();

// Get staff counts by department
$staff_counts = $pdo->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM users
    WHERE role IN ('hr', 'faculty', 'admin', 'finance', 'campus')
    GROUP BY role
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>HR Dashboard</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active"><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="employee_profiles.php">Staff Profiles</a></li>
                        <li><a href="recruitment.php">Recruitment</a></li>
                        <li><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li><a href="performance.php">Performance</a></li>
                        <li><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="stats-overview">
                    <div class="stat-card">
                        <h3>Total Staff</h3>
                        <div class="stat-value"><?= $total_staff ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Recruitments</h3>
                        <div class="stat-value"><?= $active_recruitments ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Approvals</h3>
                        <div class="stat-value"><?= $pending_approvals ?></div>
                    </div>
                </section>

                <section class="stats-overview">
                    <?php foreach ($staff_counts as $role => $count): ?>
                        <div class="stat-card">
                            <h3><?= ucfirst($role) ?> Staff</h3>
                            <div class="stat-value"><?= $count ?></div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="hr-card">
                    <h2>Recent Activities</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Staff</th>
                                <th>Activity</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($activity['timestamp'])) ?></td>
                                    <td><?= htmlspecialchars($activity['last_name'] . ', ' . $activity['first_name']) ?></td>
                                    <td><?= htmlspecialchars($activity['action']) ?></td>
                                    <td><?= htmlspecialchars($activity['details']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="quick-actions">
                    <button onclick="openModal('addStaffModal')" class="logout-btn">Add Staff</button>
                    <button onclick="openModal('createJobModal')" class="logout-btn">Create Job Posting</button>
                    <button onclick="openModal('runPayrollModal')" class="logout-btn">Run Payroll</button>
                    <button onclick="openModal('scheduleAppraisalModal')" class="logout-btn">Schedule Appraisals</button>
                </section>
            </main>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addStaffModal')">&times;</span>
            <h2>Add New Staff</h2>
            <form id="addStaffForm">
                <div class="form-group">
                    <label for="staffType">Staff Type:</label>
                    <select id="staffType" name="staffType" required>
                        <option value="">Select Staff Type</option>
                        <option value="faculty">Faculty</option>
                        <option value="hr">HR Staff</option>
                        <option value="finance">Finance Staff</option>
                        <option value="campus">Campus Staff</option>
                        <option value="admin">Admin Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="firstName">First Name:</label>
                    <input type="text" id="firstName" name="firstName" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name:</label>
                    <input type="text" id="lastName" name="lastName" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="logout-btn">Submit</button>
                    <button type="button" class="logout-btn" onclick="closeModal('addStaffModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Job Posting Modal -->
    <div id="createJobModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createJobModal')">&times;</span>
            <h2>Create Job Posting</h2>
            <form id="createJobForm">
                <div class="form-group">
                    <label for="jobTitle">Job Title:</label>
                    <input type="text" id="jobTitle" name="jobTitle" required>
                </div>
                <div class="form-group">
                    <label for="jobDepartment">Department:</label>
                    <input type="text" id="jobDepartment" name="jobDepartment" required>
                </div>
                <div class="form-group">
                    <label for="jobDescription">Description:</label>
                    <textarea id="jobDescription" name="jobDescription" rows="4" style="width:100%" required></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="logout-btn">Create</button>
                    <button type="button" class="logout-btn" onclick="closeModal('createJobModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Run Payroll Modal -->
    <div id="runPayrollModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('runPayrollModal')">&times;</span>
            <h2>Run Payroll</h2>
            <form id="runPayrollForm">
                <div class="form-group">
                    <label for="payrollPeriod">Payroll Period:</label>
                    <select id="payrollPeriod" name="payrollPeriod" required>
                        <option value="monthly">Monthly</option>
                        <option value="biweekly">Bi-weekly</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payrollDate">Payroll Date:</label>
                    <input type="date" id="payrollDate" name="payrollDate" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="logout-btn">Run Payroll</button>
                    <button type="button" class="logout-btn" onclick="closeModal('runPayrollModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Appraisal Modal -->
    <div id="scheduleAppraisalModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('scheduleAppraisalModal')">&times;</span>
            <h2>Schedule Performance Appraisals</h2>
            <form id="scheduleAppraisalForm">
                <div class="form-group">
                    <label for="appraisalType">Appraisal Type:</label>
                    <select id="appraisalType" name="appraisalType" required>
                        <option value="annual">Annual</option>
                        <option value="probation">Probation</option>
                        <option value="promotion">Promotion</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="appraisalDate">Appraisal Date:</label>
                    <input type="date" id="appraisalDate" name="appraisalDate" required>
                </div>
                <div class="form-group">
                    <label for="appraisalStaff">Staff Group:</label>
                    <select id="appraisalStaff" name="appraisalStaff" required>
                        <option value="all">All Staff</option>
                        <option value="faculty">Faculty Only</option>
                        <option value="hr">HR Staff Only</option>
                        <option value="finance">Finance Staff Only</option>
                        <option value="campus">Campus Staff Only</option>
                    </select>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="logout-btn">Schedule</button>
                    <button type="button" class="logout-btn" onclick="closeModal('scheduleAppraisalModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/university-system/js/hr.js"></script>
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

        // Form submissions
        document.getElementById('addStaffForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Staff added successfully!');
            closeModal('addStaffModal');
            // In a real application, you would send this data to the server via AJAX
        });

        document.getElementById('createJobForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Job posting created successfully!');
            closeModal('createJobModal');
            // In a real application, you would send this data to the server via AJAX
        });

        document.getElementById('runPayrollForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Payroll processed successfully!');
            closeModal('runPayrollModal');
            // In a real application, you would send this data to the server via AJAX
        });

        document.getElementById('scheduleAppraisalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Performance appraisals scheduled successfully!');
            closeModal('scheduleAppraisalModal');
            // In a real application, you would send this data to the server via AJAX
        });
    </script>
</body>

</html>