<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /university-system/login.html");
    exit();
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_backup'])) {
    $backup_file = 'university_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = $_SERVER['DOCUMENT_ROOT'] . '/university-system/backups/' . $backup_file;
    
    try {
        // Get database configuration
        $db_host = 'localhost';
        $db_user = 'root'; // Replace with your DB user
        $db_pass = '';     // Replace with your DB password
        $db_name = 'university_system';
        
        // Execute mysqldump command
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_path}";
        system($command, $output);
        
        if ($output === 0) {
            // Update last backup time
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = NOW() WHERE setting_name = 'last_backup'");
            $stmt->execute();
            
            $success = "Database backup created successfully!";
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) 
                                  VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'create_backup', "Created database backup: $backup_file"]);
        } else {
            $error = "Error creating database backup";
        }
    } catch (PDOException $e) {
        $error = "Error creating backup: " . $e->getMessage();
    }
}

// Get list of existing backups
$backup_dir = $_SERVER['DOCUMENT_ROOT'] . '/university-system/backups/';
$backups = glob($backup_dir . '*.sql');
rsort($backups); // Sort by newest first
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Backup</title>
    <link href="/university-system/css/admin.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>System Backup</h1>
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

                <section class="create-backup">
                    <h2>Create New Backup</h2>
                    <form method="post" action="backup.php">
                        <div class="form-group">
                            <label>Backup will include all database tables and records.</label>
                        </div>
                        <button type="submit" name="create_backup" class="logout-btn">Create Backup Now</button>
                    </form>
                </section>

                <section class="existing-backups">
                    <h2>Existing Backups</h2>
                    <?php if (!empty($backups)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Backup File</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): 
                                    $filename = basename($backup);
                                    $filesize = filesize($backup);
                                    $formatted_size = $filesize > 1024 * 1024 
                                        ? round($filesize / (1024 * 1024), 2) . ' MB' 
                                        : round($filesize / 1024, 2) . ' KB';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($filename) ?></td>
                                    <td><?= $formatted_size ?></td>
                                    <td>
                                        <a href="/university-system/backups/<?= $filename ?>" class="logout-btn" download>Download</a>
                                        <a href="backup_delete.php?file=<?= $filename ?>" class="logout-btn" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No backup files found.</p>
                    <?php endif; ?>
                </section>

                <section class="restore-backup">
                    <h2>Restore Backup</h2>
                    <form method="post" action="backup_restore.php" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="backup_file">Select Backup File</label>
                            <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn-warning">Restore Database</button>
                    </form>
                </section>
            </main>
        </div>
    </div>

    <script src="/university-system/js/admin.js"></script>
</body>
</html>