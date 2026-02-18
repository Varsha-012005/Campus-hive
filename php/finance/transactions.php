<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: /university-system/login.html");
    exit();
}

// Feature 9: Create invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_invoice'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO financial_transactions 
            (student_id, type, amount, description, due_date, status, semester)
            VALUES (?, 'tuition', ?, ?, ?, 'pending', 
                   (SELECT setting_value FROM system_settings WHERE setting_name = 'current_semester'))
        ");
        $stmt->execute([$student_id, $amount, $description, $due_date]);

        $_SESSION['success'] = "Invoice created successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating invoice: " . $e->getMessage();
    }
    header("Location: transactions.php");
    exit();
}

// Feature 10: Record payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $method = $_POST['method'];

    try {
        $pdo->beginTransaction();

        // Record payment
        $stmt = $pdo->prepare("
            INSERT INTO financial_transactions 
            (student_id, type, amount, description, status)
            VALUES (?, 'payment', ?, ?, 'completed')
        ");
        $stmt->execute([$student_id, $amount, "Manual payment via $method"]);

        // Update any pending invoices
        $stmt = $pdo->prepare("
            UPDATE financial_transactions 
            SET status = 'paid' 
            WHERE student_id = ? AND status = 'pending' AND type = 'tuition'
            ORDER BY due_date ASC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);

        $pdo->commit();
        $_SESSION['success'] = "Payment recorded successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error recording payment: " . $e->getMessage();
    }
    header("Location: transactions.php");
    exit();
}

// Feature 11: Get all transactions with filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$student_filter = isset($_GET['student']) ? $_GET['student'] : '';

$query = "
    SELECT t.*, u.first_name, u.last_name, s.student_id as student_number
    FROM financial_transactions t
    JOIN students s ON t.student_id = s.student_id
    JOIN users u ON s.user_id = u.id
    WHERE 1=1
";

$params = [];
if (!empty($type_filter)) {
    $query .= " AND t.type = ?";
    $params[] = $type_filter;
}
if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}
if (!empty($student_filter)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_id LIKE ?)";
    $search_term = "%$student_filter%";
    array_push($params, $search_term, $search_term, $search_term);
}

$query .= " ORDER BY t.transaction_date DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM financial_transactions t
    JOIN students s ON t.student_id = s.student_id
    JOIN users u ON s.user_id = u.id
    WHERE 1=1
";

if (!empty($type_filter)) {
    $count_query .= " AND t.type = ?";
}
if (!empty($status_filter)) {
    $count_query .= " AND t.status = ?";
}
if (!empty($student_filter)) {
    $count_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_id LIKE ?)";
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_transactions = $count_stmt->fetchColumn();
$total_pages = ceil($total_transactions / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management</title>
    <link href="/university-system/css/finance.css" rel="stylesheet">
    <style>
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar {
            flex-grow: 1;
            margin-right: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .form-modal {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Transaction Management</h1>
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
                        <li class="active"><a href="transactions.php">Transactions</a></li>
                        <li><a href="fees.php">Fee Management</a></li>
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

                <div class="action-bar">
                    <div class="search-bar">
                        <form method="get" action="transactions.php">
                            <input type="text" name="student" placeholder="Search students..."
                                value="<?= htmlspecialchars($student_filter) ?>">
                            <select name="type">
                                <option value="">All Types</option>
                                <option value="tuition" <?= $type_filter == 'tuition' ? 'selected' : '' ?>>Tuition</option>
                                <option value="payment" <?= $type_filter == 'payment' ? 'selected' : '' ?>>Payment</option>
                                <option value="scholarship" <?= $type_filter == 'scholarship' ? 'selected' : '' ?>>
                                    Scholarship</option>
                            </select>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending
                                </option>
                                <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed
                                </option>
                                <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                            </select>
                            <button type="submit" class="logout-btn">Filter</button>
                            <a href="transactions.php" class="btn-warning">Reset</a>
                        </form>
                    </div>
                    <div class="action-buttons">
                        <button class="logout-btn" onclick="document.getElementById('createInvoiceModal').style.display='flex'">Create Invoice</button>
                        <button class="logout-btn" onclick="document.getElementById('recordPaymentModal').style.display='flex'">Record Payment</button>
                    </div>
                </div>

                <div class="transaction-card">
                    <h2>Financial Transactions</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($transaction['last_name'] . ', ' . $transaction['first_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['student_number']) ?></td>
                                    <td><?= ucfirst($transaction['type']) ?></td>
                                    <td>$<?= number_format($transaction['amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= $transaction['status'] ?>">
                                            <?= ucfirst($transaction['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="transactions.php?page=<?= $i ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>&student=<?= urlencode($student_filter) ?>"
                                    class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Invoice Modal -->
    <div class="modal" id="createInvoiceModal">
        <div class="modal-content form-modal">
            <span class="close" onclick="document.getElementById('createInvoiceModal').style.display='none'">&times;</span>
            <h2>Create New Invoice</h2>
            <form method="post" action="transactions.php">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" required>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" required>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>
                <button type="submit" name="create_invoice" class="logout-btn">Create Invoice</button>
            </form>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal" id="recordPaymentModal">
        <div class="modal-content form-modal">
            <span class="close" onclick="document.getElementById('recordPaymentModal').style.display='none'">&times;</span>
            <h2>Record Payment</h2>
            <form method="post" action="transactions.php">
                <div class="form-group">
                    <label for="payment_student_id">Student ID</label>
                    <input type="text" id="payment_student_id" name="student_id" required>
                </div>
                <div class="form-group">
                    <label for="payment_amount">Amount</label>
                    <input type="number" id="payment_amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="method">Payment Method</label>
                    <select id="method" name="method" required>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <button type="submit" name="record_payment" class="logout-btn">Record Payment</button>
            </form>
        </div>
    </div>

    <script>
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>

</html>