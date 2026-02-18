<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/financial_functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /university-system/login.html");
    exit();
}

if ($_SESSION['role'] != 'finance') {
    header("Location: /university-system/unauthorized.php");
    exit();
}

// Feature 1: Get total revenue for current semester
$revenue = $pdo->query("
    SELECT SUM(amount) as total 
    FROM financial_transactions 
    WHERE type IN ('tuition', 'other') 
    AND semester = (SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester')
")->fetchColumn();

// Feature 2: Get pending payments
$pending_payments = $pdo->query("
    SELECT COUNT(*) 
    FROM financial_transactions 
    WHERE status = 'pending' AND type IN ('tuition', 'other')
")->fetchColumn();

// Feature 3: Get recent transactions
$recent_transactions = $pdo->query("
    SELECT t.*, u.first_name, u.last_name 
    FROM financial_transactions t
    JOIN students s ON t.student_id = s.student_id
    JOIN users u ON s.user_id = u.id
    ORDER BY transaction_date DESC 
    LIMIT 5
")->fetchAll();

// Feature 4: Get financial aid distribution
$financial_aid = $pdo->query("
    SELECT type, SUM(amount) as total 
    FROM financial_transactions 
    WHERE type IN ('scholarship', 'financial_aid')
    GROUP BY type
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard</title>
    <link href="/university-system/css/finance.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Finance Dashboard</h1>
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
                        <li><a href="transactions.php">Transactions</a></li>
                        <li><a href="fees.php">Fee Management</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="settings.php">Settings</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <section class="stats-overview">
                    <div class="stat-card">
                        <h3>Total Revenue</h3>
                        <div class="stat-value">$<?= number_format($revenue, 2) ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Payments</h3>
                        <div class="stat-value"><?= $pending_payments ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Financial Aid</h3>
                        <div class="stat-value">
                            <?php foreach ($financial_aid as $aid): ?>
                                <?= ucfirst($aid['type']) ?>: $<?= number_format($aid['total'], 2) ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="finance-card">
                    <h2>Recent Transactions</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($transaction['last_name'] . ', ' . $transaction['first_name']) ?>
                                    </td>
                                    <td><?= ucfirst($transaction['type']) ?></td>
                                    <td>$<?= number_format($transaction['amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= $transaction['status'] ?>">
                                            <?= ucfirst($transaction['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="quick-actions">
                    <!-- Feature 5-8: Quick action buttons -->
                    <a href="transactions.php?action=create_invoice" class="logout-btn">Create Invoice</a>
                    <a href="transactions.php?action=record_payment" class="logout-btn">Record Payment</a>
                    <a href="fees.php?action=manage" class="logout-btn">Manage Fee Structure</a>
                    <a href="reports.php?type=revenue" class="logout-btn">Generate Revenue Report</a>
                </section>
            </main>
        </div>
    </div>

    <div id="finance-data"
        data-report='<?= isset($_SESSION['report_data']) ? json_encode($_SESSION['report_data']) : '[]' ?>'></div>
    <script src="js/finance.js"></script>
</body>

</html>