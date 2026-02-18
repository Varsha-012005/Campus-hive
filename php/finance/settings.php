<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: /university-system/login.html");
    exit();
}

// Feature 19: Update system settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $current_semester = $_POST['current_semester'];
    $late_fee_percentage = $_POST['late_fee_percentage'];
    $payment_due_days = $_POST['payment_due_days'];

    try {
        $pdo->beginTransaction();

        // Update current semester
        $stmt = $pdo->prepare("
            UPDATE system_settings 
            SET setting_value = ? 
            WHERE setting_name = 'current_semester'
        ");
        $stmt->execute([$current_semester]);

        // Update late fee percentage
        $stmt = $pdo->prepare("
            UPDATE system_settings 
            SET setting_value = ? 
            WHERE setting_name = 'late_fee_percentage'
        ");
        $stmt->execute([$late_fee_percentage]);

        // Update payment due days
        $stmt = $pdo->prepare("
            UPDATE system_settings 
            SET setting_value = ? 
            WHERE setting_name = 'payment_due_days'
        ");
        $stmt->execute([$payment_due_days]);

        $pdo->commit();
        $_SESSION['success'] = "Settings updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
    }
    header("Location: settings.php");
    exit();
}

// Feature 20: Get current settings
$settings = $pdo->query("
    SELECT setting_name, setting_value 
    FROM system_settings 
    WHERE setting_name IN ('current_semester', 'late_fee_percentage', 'payment_due_days')
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="/university-system/css/finance.css" rel="stylesheet">
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
                        <li><a href="transactions.php">Transactions</a></li>
                        <li><a href="fees.php">Fee Management</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li class="active"><a href="settings.php">Settings</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert success"><?= $_SESSION['success'] ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php elseif (isset($_SESSION['error'])): ?>
                    <div class="alert error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="settings-card">
                    <h2>Financial System Settings</h2>
                    <form method="post" action="settings.php">
                        <div class="form-group">
                            <label for="current_semester">Current Semester</label>
                            <input type="text" id="current_semester" name="current_semester"
                                value="<?= htmlspecialchars($settings['current_semester'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="late_fee_percentage">Late Fee Percentage (%)</label>
                            <input type="number" id="late_fee_percentage" name="late_fee_percentage"
                                value="<?= htmlspecialchars($settings['late_fee_percentage'] ?? '') ?>" step="0.01"
                                min="0" max="100" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_due_days">Payment Due Days</label>
                            <input type="number" id="payment_due_days" name="payment_due_days"
                                value="<?= htmlspecialchars($settings['payment_due_days'] ?? '') ?>" min="1" required>
                        </div>
                        <button type="submit" name="update_settings" class="logout-btn">Save Settings</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h2>System Information</h2>
                    <div class="system-info">
                        <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
                        <p><strong>Database:</strong> MySQL</p>
                        <p><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
                        <p><strong>Last Updated:</strong> <?= date('F j, Y', filemtime(__FILE__)) ?></p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="finance-data"
        data-report='<?= isset($_SESSION['report_data']) ? json_encode($_SESSION['report_data']) : '[]' ?>'></div>
    <script src="js/finance.js"></script>
</body>

</html>