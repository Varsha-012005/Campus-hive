<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: /university-system/login.html");
    exit();
}

// Feature 17: Generate revenue report
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $semester = $_POST['semester'];

    try {
        $query = "
            SELECT 
                t.type,
                SUM(t.amount) as total,
                COUNT(*) as count,
                s.name as program_name
            FROM financial_transactions t
            LEFT JOIN students st ON t.student_id = st.student_id
            LEFT JOIN programs s ON st.program_id = s.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($start_date)) {
            $query .= " AND t.transaction_date >= ?";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND t.transaction_date <= ?";
            $params[] = $end_date;
        }

        if (!empty($semester)) {
            $query .= " AND t.semester = ?";
            $params[] = $semester;
        }

        if ($report_type == 'revenue') {
            $query .= " AND t.type IN ('tuition', 'other')";
        } elseif ($report_type == 'financial_aid') {
            $query .= " AND t.type IN ('scholarship', 'financial_aid')";
        } elseif ($report_type == 'all') {
            // Include all types
        }

        $query .= " GROUP BY t.type, s.name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();

        $_SESSION['report_data'] = $report_data;
        $_SESSION['report_type'] = $report_type;
        $_SESSION['report_range'] = [
            'start' => $start_date,
            'end' => $end_date,
            'semester' => $semester
        ];

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    }
    header("Location: reports.php");
    exit();
}

// Feature 18: Get available semesters
$semesters = $pdo->query("SELECT DISTINCT semester FROM financial_transactions ORDER BY semester DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports</title>
    <link href="/university-system/css/finance.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Financial Reports</h1>
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
                        <li class="active"><a href="reports.php">Reports</a></li>
                        <li><a href="settings.php">Settings</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="report-card">
                    <h2>Generate Report</h2>
                    <form method="post" action="reports.php">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" required>
                                <option value="revenue">Revenue Report</option>
                                <option value="financial_aid">Financial Aid Report</option>
                                <option value="all">Comprehensive Report</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date">
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem) ?>"><?= htmlspecialchars($sem) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="generate_report" class="logout-btn">Generate Report</button>
                    </form>
                </div>

                <?php if (isset($_SESSION['report_data'])): ?>
                    <div class="report-results">
                        <h2>Report Results: <?= ucfirst($_SESSION['report_type']) ?> Report</h2>
                        <?php if ($_SESSION['report_range']['start'] || $_SESSION['report_range']['end']): ?>
                            <p>Date Range:
                                <?= $_SESSION['report_range']['start'] ? date('M j, Y', strtotime($_SESSION['report_range']['start'])) : 'Start' ?>
                                to
                                <?= $_SESSION['report_range']['end'] ? date('M j, Y', strtotime($_SESSION['report_range']['end'])) : 'End' ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($_SESSION['report_range']['semester']): ?>
                            <p>Semester: <?= htmlspecialchars($_SESSION['report_range']['semester']) ?></p>
                        <?php endif; ?>

                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Program</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['report_data'] as $row): ?>
                                    <tr>
                                        <td><?= ucfirst($row['type']) ?></td>
                                        <td><?= $row['program_name'] ? htmlspecialchars($row['program_name']) : 'N/A' ?></td>
                                        <td><?= $row['count'] ?></td>
                                        <td>$<?= number_format($row['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="report-actions">
                            <button class="logout-btn" onclick="window.print()">Print Report</button>
                            <button class="logout-btn" id="exportCsv">Export to CSV</button>
                        </div>
                    </div>
                    <?php unset($_SESSION['report_data']); ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div id="finance-data"
        data-report='<?= isset($_SESSION['report_data']) ? json_encode($_SESSION['report_data']) : '[]' ?>'></div>
    <script src="js/finance.js"></script>
</body>

</html>