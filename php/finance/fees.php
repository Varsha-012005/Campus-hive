<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: /university-system/login.html");
    exit();
}

// Feature 12: Update fee structure
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fees'])) {
    $program = $_POST['program'];
    $tuition_fee = $_POST['tuition_fee'];
    $registration_fee = $_POST['registration_fee'];
    $technology_fee = $_POST['technology_fee'];
    $misc_fee = $_POST['misc_fee'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO fee_structure 
            (program, tuition_fee, registration_fee, technology_fee, misc_fee, academic_year)
            VALUES (?, ?, ?, ?, ?, 
                   (SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester'))
            ON DUPLICATE KEY UPDATE
            tuition_fee = VALUES(tuition_fee),
            registration_fee = VALUES(registration_fee),
            technology_fee = VALUES(technology_fee),
            misc_fee = VALUES(misc_fee)
        ");
        $stmt->execute([$program, $tuition_fee, $registration_fee, $technology_fee, $misc_fee]);

        $_SESSION['success'] = "Fee structure updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating fee structure: " . $e->getMessage();
    }
    header("Location: fees.php");
    exit();
}

/// Feature 13: Get current fee structure with error handling
try {
    // First check if the tables exist
    $tablesExist = $pdo->query("SHOW TABLES LIKE 'fee_structure'")->rowCount() > 0
        && $pdo->query("SHOW TABLES LIKE 'system_settings'")->rowCount() > 0;

    if (!$tablesExist) {
        throw new Exception("Required database tables are missing");
    }

    // Get current semester/year from settings
    $currentSemester = $pdo->query("
        SELECT setting_value FROM system_settings 
        WHERE setting_name = 'current_semester'
        LIMIT 1
    ")->fetchColumn();

    if (!$currentSemester) {
        throw new Exception("Current semester is not set in system settings");
    }

    // Get fee structure for current semester/year
    $stmt = $pdo->prepare("
        SELECT * FROM fee_structure 
        WHERE academic_year = :currentSemester
    ");
    $stmt->execute([':currentSemester' => $currentSemester]);
    $fee_structure = $stmt->fetchAll();

} catch (PDOException $e) {
    // Log error and return empty array or show friendly message
    error_log("Database error: " . $e->getMessage());
    $fee_structure = [];
} catch (Exception $e) {
    // Log other errors
    error_log($e->getMessage());
    $fee_structure = [];
}
// Feature 14: Get program list
// Get distinct course names instead of program names
try {
    $courses = $pdo->query("SELECT DISTINCT course_name FROM courses")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Error handling
    error_log("Database error: " . $e->getMessage());
    $courses = []; // Return empty array if error occurs
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management</title>
    <link href="/university-system/css/finance.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Fee Management</h1>
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
                        <li class="active"><a href="fees.php">Fee Management</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="settings.php">Settings</a></li>
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

                <div class="fee-card">
                    <h2>Current Fee Structure</h2>
                    <table class="fee-structure">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Tuition Fee</th>
                                <th>Registration Fee</th>
                                <th>Technology Fee</th>
                                <th>Miscellaneous Fee</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_structure as $fee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fee['program']) ?></td>
                                    <td>$<?= number_format($fee['tuition_fee'], 2) ?></td>
                                    <td>$<?= number_format($fee['registration_fee'], 2) ?></td>
                                    <td>$<?= number_format($fee['technology_fee'], 2) ?></td>
                                    <td>$<?= number_format($fee['misc_fee'], 2) ?></td>
                                    <td>$<?= number_format($fee['tuition_fee'] + $fee['registration_fee'] + $fee['technology_fee'] + $fee['misc_fee'], 2) ?>
                                    </td>
                                    <td>
                                        <button class="logout-btn" onclick="editFee('<?= $fee['program'] ?>')">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Feature 15: Add new fee structure -->
                <div class="fee-card">
                    <h2>Add/Edit Fee Structure</h2>
                    <form method="post" action="fees.php">
                        <div class="form-group">
                            <label for="program">Program</label>
                            <select id="program" name="program" required>
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tuition_fee">Tuition Fee</label>
                            <input type="number" id="tuition_fee" name="tuition_fee" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="registration_fee">Registration Fee</label>
                            <input type="number" id="registration_fee" name="registration_fee" step="0.01" min="0"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="technology_fee">Technology Fee</label>
                            <input type="number" id="technology_fee" name="technology_fee" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="misc_fee">Miscellaneous Fee</label>
                            <input type="number" id="misc_fee" name="misc_fee" step="0.01" min="0" required>
                        </div>
                        <button type="submit" name="update_fees" class="logout-btn">Save Fee Structure</button>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Feature 16: Edit fee structure - populate form
        function editFee(program) {
            const feeRow = document.querySelector(`tr:has(td:contains('${program}')`);
            if (feeRow) {
                document.getElementById('program').value = program;
                document.getElementById('tuition_fee').value = feeRow.cells[1].textContent.replace('$', '');
                document.getElementById('registration_fee').value = feeRow.cells[2].textContent.replace('$', '');
                document.getElementById('technology_fee').value = feeRow.cells[3].textContent.replace('$', '');
                document.getElementById('misc_fee').value = feeRow.cells[4].textContent.replace('$', '');

                // Scroll to form
                document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
            }
        }
    </script>
    <div id="finance-data"
        data-report='<?= isset($_SESSION['report_data']) ? json_encode($_SESSION['report_data']) : '[]' ?>'></div>
    <script src="js/finance.js"></script>
</body>

</html>