<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hr') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_upload'])) {
    if (isset($_FILES['bulk_file']) && $_FILES['bulk_file']['error'] == UPLOAD_ERR_OK) {
        $file_type = pathinfo($_FILES['bulk_file']['name'], PATHINFO_EXTENSION);
        
        if ($file_type == 'csv') {
            // Process CSV file
            $file = fopen($_FILES['bulk_file']['tmp_name'], 'r');
            $header = fgetcsv($file); // Skip header row
            
            $pdo->beginTransaction();
            $success_count = 0;
            $error_count = 0;
            $error_messages = [];
            
            try {
                while (($row = fgetcsv($file)) !== false) {
                    try {
                        $role = $row[0];
                        $username = $row[1];
                        $password = password_hash($row[2], PASSWORD_DEFAULT);
                        $email = $row[3];
                        $first_name = $row[4];
                        $last_name = $row[5];
                        $status = $row[6];
                        
                        // Check if username already exists
                        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $check_stmt->execute([$username]);
                        if ($check_stmt->fetch()) {
                            throw new Exception("Username '$username' already exists");
                        }
                        
                        // Insert into users table
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, first_name, last_name, status, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$username, $password, $role, $email, $first_name, $last_name, $status]);
                        $user_id = $pdo->lastInsertId();
                        
                        // Insert into respective role table
                        switch ($role) {
                            case 'faculty':
                                $stmt = $pdo->prepare("INSERT INTO faculty (user_id, faculty_id, department, position, hire_date, salary, contract_type) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$user_id, $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]]);
                                break;
                            case 'hr':
                                $stmt = $pdo->prepare("INSERT INTO hr_staff (user_id, hr_id, department, position, hire_date) 
                                                      VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$user_id, $row[7], $row[8], $row[9], $row[10]]);
                                break;
                            case 'finance':
                                $stmt = $pdo->prepare("INSERT INTO finance_staff (user_id, finance_id, department, position, hire_date) 
                                                      VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$user_id, $row[7], $row[8], $row[9], $row[10]]);
                                break;
                            case 'campus':
                                $stmt = $pdo->prepare("INSERT INTO campus_staff (user_id, campus_id, department, position, hire_date) 
                                                      VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$user_id, $row[7], $row[8], $row[9], $row[10]]);
                                break;
                            case 'admin':
                                // Admins might not need additional info beyond users table
                                break;
                            default:
                                throw new Exception("Invalid role '$role'");
                        }
                        $success_count++;
                    } catch (Exception $e) {
                        $error_count++;
                        $error_messages[] = "Row " . ($success_count + $error_count) . ": " . $e->getMessage();
                        continue;
                    }
                }
                $pdo->commit();
                $_SESSION['message'] = "Bulk upload completed with $success_count records processed successfully!";
                if ($error_count > 0) {
                    $_SESSION['message'] .= " $error_count records failed.";
                    $_SESSION['error_details'] = $error_messages;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error during bulk upload: " . $e->getMessage();
            }
            fclose($file);
        } else {
            $_SESSION['error'] = "Only CSV files are supported for bulk upload.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file. Error code: " . $_FILES['bulk_file']['error'];
    }
    header("Location: admin_utilities.php");
    exit();
}

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        $payroll_day = intval($_POST['payroll_day']);
        $default_password = $_POST['default_password'];
        
        // Validate inputs
        if ($payroll_day < 1 || $payroll_day > 28) {
            throw new Exception("Payroll day must be between 1 and 28");
        }
        
        if (empty($default_password)) {
            throw new Exception("Default password cannot be empty");
        }
        
        // In a real system, you would save these to a settings table
        $_SESSION['message'] = "System settings updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
    }
    header("Location: admin_utilities.php");
    exit();
}

// Handle data export
if (isset($_GET['action']) && $_GET['action'] == 'data_export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="staff_data_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, ['Role', 'Username', 'Email', 'First Name', 'Last Name', 'Status', 
                     'Staff ID', 'Department', 'Position', 'Hire Date', 'Salary', 'Contract Type']);
    
    // Get all staff data
    $roles = ['faculty', 'hr', 'finance', 'campus', 'admin'];
    
    foreach ($roles as $role) {
        $query = "SELECT u.role, u.username, u.email, u.first_name, u.last_name, u.status, ";
        
        switch ($role) {
            case 'faculty':
                $query .= "f.faculty_id as staff_id, f.department, f.position, f.hire_date, f.salary, f.contract_type 
                          FROM users u JOIN faculty f ON u.id = f.user_id";
                break;
            case 'hr':
                $query .= "h.hr_id as staff_id, h.department, h.position, h.hire_date, '' as salary, '' as contract_type 
                          FROM users u JOIN hr_staff h ON u.id = h.user_id";
                break;
            case 'finance':
                $query .= "fs.finance_id as staff_id, fs.department, fs.position, fs.hire_date, '' as salary, '' as contract_type 
                          FROM users u JOIN finance_staff fs ON u.id = fs.user_id";
                break;
            case 'campus':
                $query .= "cs.campus_id as staff_id, cs.department, cs.position, cs.hire_date, '' as salary, '' as contract_type 
                          FROM users u JOIN campus_staff cs ON u.id = cs.user_id";
                break;
            case 'admin':
                $query .= "'ADMIN' as staff_id, '' as department, '' as position, '' as hire_date, '' as salary, '' as contract_type 
                          FROM users u WHERE u.role = 'admin'";
                break;
        }
        
        $stmt = $pdo->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

// Handle reports generation
if (isset($_GET['action']) && $_GET['action'] == 'reports') {
    // In a real system, you would generate various reports here
    $_SESSION['message'] = "Report generation initiated. Check your email for the report.";
    header("Location: admin_utilities.php");
    exit();
}

// Handle user account reset
if (isset($_GET['action']) && $_GET['action'] == 'reset_account' && isset($_GET['user_id'])) {
    try {
        $user_id = intval($_GET['user_id']);
        $default_password = "TempPassword123"; // In real system, use a setting or random generator
        
        // Reset password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($default_password, PASSWORD_DEFAULT), $user_id]);
        
        $_SESSION['message'] = "User account reset successfully. Temporary password set.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error resetting account: " . $e->getMessage();
    }
    header("Location: employee_profiles.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Admin Tools</title>
    <link href="/university-system/css/hr.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>HR Admin Tools</h1>
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
                        <li><a href="attendance_payroll.php">Attendance & Payroll</a></li>
                        <li><a href="performance.php">Performance</a></li>
                        <li class="active"><a href="admin_utilities.php">Admin Tools</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert success"><?= $_SESSION['message'] ?></div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_details'])): ?>
                    <div class="error-details">
                        <h4>Error Details:</h4>
                        <ul>
                            <?php foreach ($_SESSION['error_details'] as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php unset($_SESSION['error_details']); ?>
                <?php endif; ?>
                
                <div class="hr-card">
                    <h2>HR Admin Tools</h2>
                    
                    <div class="admin-tools-grid">
                        <div class="tool-card" onclick="showModal('bulkUploadModal')">
                            <h3>Bulk Upload</h3>
                            <p>Upload staff data in bulk (CSV format)</p>
                            <button class="btn btn-primary">Upload Data</button>
                        </div>
                        
                        <div class="tool-card" onclick="location.href='admin_utilities.php?action=data_export'">
                            <h3>Data Export</h3>
                            <p>Export all staff data to CSV</p>
                            <button class="btn btn-primary">Export Now</button>
                        </div>
                        
                        <div class="tool-card" onclick="showModal('systemSettingsModal')">
                            <h3>System Settings</h3>
                            <p>Configure system parameters</p>
                            <button class="btn btn-primary">Configure</button>
                        </div>
                        
                        <div class="tool-card" onclick="location.href='admin_utilities.php?action=reports'">
                            <h3>Advanced Reports</h3>
                            <p>Generate custom reports</p>
                            <button class="btn btn-primary">Generate</button>
                        </div>
                        
                        <div class="tool-card" onclick="showModal('userManagementModal')">
                            <h3>User Management</h3>
                            <p>Reset passwords, manage access</p>
                            <button class="btn btn-primary">Manage Users</button>
                        </div>
                        
                        <div class="tool-card" onclick="showModal('databaseBackupModal')">
                            <h3>Database Backup</h3>
                            <p>Create system backups</p>
                            <button class="btn btn-primary">Backup Now</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div class="modal" id="bulkUploadModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('bulkUploadModal')">&times;</span>
            <h2>Bulk Data Upload</h2>
            <p>Upload a CSV file with staff data. The file should have the following columns:</p>
            <ul>
                <li>Role (faculty, hr, finance, campus, admin)</li>
                <li>Username</li>
                <li>Password (will be hashed)</li>
                <li>Email</li>
                <li>First Name</li>
                <li>Last Name</li>
                <li>Status (active/inactive)</li>
                <li>Staff ID (faculty_id, hr_id, etc.)</li>
                <li>Department</li>
                <li>Position</li>
                <li>Hire Date (YYYY-MM-DD)</li>
                <li>Salary (for faculty only)</li>
                <li>Contract Type (for faculty only - full-time/part-time/visiting)</li>
            </ul>
            <form method="post" action="admin_utilities.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="bulk_file">CSV File:</label>
                    <input type="file" name="bulk_file" id="bulk_file" accept=".csv" required>
                </div>
                <button type="submit" name="bulk_upload" class="btn btn-primary">Upload Data</button>
            </form>
        </div>
    </div>

    <!-- System Settings Modal -->
    <div class="modal" id="systemSettingsModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('systemSettingsModal')">&times;</span>
            <h2>System Settings</h2>
            <form method="post" action="admin_utilities.php">
                <div class="form-group">
                    <label for="payroll_day">Payroll Processing Day</label>
                    <select name="payroll_day" id="payroll_day">
                        <?php for ($i = 1; $i <= 28; $i++): ?>
                            <option value="<?= $i ?>" <?= ($i == 15) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="default_password">Default Password for New Users</label>
                    <input type="text" name="default_password" id="default_password" value="TempPassword123">
                </div>
                <div class="form-group">
                    <label for="login_attempts">Max Login Attempts Before Lockout</label>
                    <input type="number" name="login_attempts" id="login_attempts" value="5" min="1" max="10">
                </div>
                <div class="form-group">
                    <label for="session_timeout">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout" id="session_timeout" value="30" min="5" max="240">
                </div>
                <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- User Management Modal -->
    <div class="modal" id="userManagementModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('userManagementModal')">&times;</span>
            <h2>User Management</h2>
            <div class="form-group">
                <label for="search_user">Search User:</label>
                <div class="search-bar">
                    <input type="text" id="search_user" placeholder="Enter username or email">
                    <button class="btn btn-primary" onclick="searchUser()">Search</button>
                </div>
            </div>
            <div id="userResults" class="form-group">
                <!-- Search results will appear here -->
            </div>
        </div>
    </div>

    <!-- Database Backup Modal -->
    <div class="modal" id="databaseBackupModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('databaseBackupModal')">&times;</span>
            <h2>Database Backup</h2>
            <p>Create a complete backup of the system database.</p>
            <div class="form-group">
                <label for="backup_type">Backup Type:</label>
                <select name="backup_type" id="backup_type">
                    <option value="full">Full Backup</option>
                    <option value="structure">Structure Only</option>
                    <option value="data">Data Only</option>
                </select>
            </div>
            <div class="form-group">
                <label for="backup_compress">Compression:</label>
                <select name="backup_compress" id="backup_compress">
                    <option value="zip">ZIP</option>
                    <option value="gzip">GZIP</option>
                    <option value="none">None</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="createBackup()">Create Backup</button>
            <div id="backupStatus" class="form-group"></div>
        </div>
    </div>

    <script>
        // Show modal function
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        // Hide modal function
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // User search function
        function searchUser() {
            const query = document.getElementById('search_user').value;
            if (query.length < 3) {
                alert('Please enter at least 3 characters');
                return;
            }
            
            // This would be replaced with actual AJAX call in production
            document.getElementById('userResults').innerHTML = `
                <div class="stat-card">
                    <h4>Search Results for "${query}"</h4>
                    <p>In a production system, this would show actual user search results.</p>
                </div>
            `;
        }
        
        // Create backup function
        function createBackup() {
            const backupType = document.getElementById('backup_type').value;
            const compressType = document.getElementById('backup_compress').value;
            
            document.getElementById('backupStatus').innerHTML = `
                <div class="alert success">
                    Backup process initiated (${backupType} with ${compressType} compression).
                    <p>In a production system, this would actually create a database backup.</p>
                </div>
            `;
        }
    </script>
</body>
</html>