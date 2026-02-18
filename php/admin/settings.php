<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Get current system settings
$default_settings = [
    'current_semester' => '',
    'next_semester' => '',
    'registration_start' => '',
    'registration_end' => '',
    'maintenance_mode' => '0',
    'last_backup' => '1970-01-01 00:00:00'
];

// Merge with database settings
$db_settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = array_merge($default_settings, $db_settings);

// Format dates for HTML input fields
$registration_start = date('Y-m-d', strtotime($settings['registration_start']));
$registration_end = date('Y-m-d', strtotime($settings['registration_end']));

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();

        // Update semester settings
        $current_semester = $_POST['current_semester'];
        $next_semester = $_POST['next_semester'];

        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'current_semester'");
        $stmt->execute([$current_semester]);

        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'next_semester'");
        $stmt->execute([$next_semester]);

        // Update registration dates
        $registration_start = $_POST['registration_start'];
        $registration_end = $_POST['registration_end'];

        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'registration_start'");
        $stmt->execute([$registration_start]);

        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'registration_end'");
        $stmt->execute([$registration_end]);

        // Update system maintenance mode
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'maintenance_mode'");
        $stmt->execute([$maintenance_mode]);

        $pdo->commit();
        $success = "System settings updated successfully!";

        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'update_settings', "Updated system settings"]);

        // Refresh settings
        $settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_type'])) {
    // Check if file was uploaded
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/university-system/uploads/imports/";

        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate unique filename
        $file_name = uniqid() . '_' . basename($_FILES["csv_file"]["name"]);
        $target_file = $target_dir . $file_name;
        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($_FILES['csv_file']['type'], $allowed_types) || $fileType != "csv") {
            $error = "Invalid file type. Only CSV files are allowed.";
        } elseif ($_FILES['csv_file']['size'] > 5000000) { // 5MB limit
            $error = "File is too large. Maximum size is 5MB.";
        } else {
            // Move uploaded file
            if (move_uploaded_file($_FILES["csv_file"]["tmp_name"], $target_file)) {
                // Process CSV file
                try {
                    $import_type = $_POST['import_type'];
                    $csv_file = fopen($target_file, "r");

                    if ($import_type == 'students') {
                        // Process student import
                        $pdo->beginTransaction();
                        $stmt_user = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) 
                                                  VALUES (?, ?, ?, ?, 'student', 'active')");
                        $stmt_student = $pdo->prepare("INSERT INTO students (user_id, student_id) 
                                                     VALUES (?, ?)");

                        $row = 0;
                        while (($data = fgetcsv($csv_file, 1000, ",")) !== FALSE) {
                            if ($row > 0) { // Skip header row
                                $first_name = $data[0];
                                $last_name = $data[1];
                                $email = $data[2];
                                $student_id = $data[3];
                                $password = password_hash('default123', PASSWORD_DEFAULT);

                                $stmt_user->execute([$first_name, $last_name, $email, $password]);
                                $user_id = $pdo->lastInsertId();
                                $stmt_student->execute([$user_id, $student_id]);
                            }
                            $row++;
                        }

                        $pdo->commit();
                        $success = "Imported " . ($row - 1) . " students successfully!";
                    } elseif ($import_type == 'faculty') {
                        // Process faculty import
                        $pdo->beginTransaction();
                        $stmt_user = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) 
                                                  VALUES (?, ?, ?, ?, 'faculty', 'active')");
                        $stmt_faculty = $pdo->prepare("INSERT INTO faculty (user_id, faculty_id, department_id) 
                                                      VALUES (?, ?, ?)");

                        $row = 0;
                        while (($data = fgetcsv($csv_file, 1000, ",")) !== FALSE) {
                            if ($row > 0) { // Skip header row
                                $first_name = $data[0];
                                $last_name = $data[1];
                                $email = $data[2];
                                $faculty_id = $data[3];
                                $department_id = $data[4];
                                $password = password_hash('default123', PASSWORD_DEFAULT);

                                $stmt_user->execute([$first_name, $last_name, $email, $password]);
                                $user_id = $pdo->lastInsertId();
                                $stmt_faculty->execute([$user_id, $faculty_id, $department_id]);
                            }
                            $row++;
                        }

                        $pdo->commit();
                        $success = "Imported " . ($row - 1) . " faculty members successfully!";
                    }

                    fclose($csv_file);

                    // Log activity
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                                          VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], 'csv_import', "Imported $import_type from CSV"]);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error importing data: " . $e->getMessage();

                    // Delete the uploaded file if there was an error
                    if (file_exists($target_file)) {
                        unlink($target_file);
                    }
                }
            } else {
                $error = "Error uploading file.";
            }
        }
    } else {
        $error = "Please select a valid CSV file to upload.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>System Settings</h1>
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
                        <li><a href="departments.php">Departments</a></li>
                        <li class="active"><a href="settings.php">System Settings</a></li>
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

                <section class="semester-settings">
                    <h2>Semester Configuration</h2>
                    <form method="post" action="settings.php">
                        <div class="form-group">
                            <label for="current_semester">Current Semester</label>
                            <input type="text" id="current_semester" name="current_semester"
                                value="<?= htmlspecialchars($settings['current_semester']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="next_semester">Next Semester</label>
                            <input type="text" id="next_semester" name="next_semester"
                                value="<?= htmlspecialchars($settings['next_semester']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="registration_start">Registration Start Date</label>
                            <input type="date" id="registration_start" name="registration_start"
                                value="<?= htmlspecialchars($registration_start) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="registration_end">Registration End Date</label>
                            <input type="date" id="registration_end" name="registration_end"
                                value="<?= htmlspecialchars($registration_end) ?>" required>
                        </div>
                        <div class="form-group inline">
                            Maintenance Mode
                            <label for="maintenance_mode">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                    <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                            </label>
                        </div>
                        <button type="submit" name="update_settings" class="logout-btn">Update Settings</button>
                    </form>
                </section>

                <section class="data-import">
                    <h2>Data Import</h2>
                    <form method="post" action="settings.php" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="import_type">Import Type</label>
                            <select id="import_type" name="import_type" required>
                                <option value="students">Students</option>
                                <option value="faculty">Faculty</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="csv_file">CSV File</label>
                            <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                            <small>CSV format: First Name, Last Name, Email, ID, [Department ID for faculty]</small>
                        </div>
                        <button type="submit" class="logout-btn">Import Data</button>
                    </form>
                </section>

                <section class="system-info">
                    <h2>System Information</h2>
                    <table>
                        <tr>
                            <th>PHP Version</th>
                            <td><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <th>Database Server</th>
                            <td><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></td>
                        </tr>
                        <tr>
                            <th>Last Backup</th>
                            <td><?= date('Y-m-d H:i:s', strtotime($settings['last_backup'])) ?></td>
                        </tr>
                    </table>
                    <div class="action-buttons" style="margin-top: 1rem;">
                        <a href="backup.php" class="logout-btn">Create Backup</a>
                        <a href="logs.php" class="btn-warning">View System Logs</a>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/admin.js"></script>
</body>

</html>