<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/financial_functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: /university-system/login.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get financial data
$balance_due = getStudentBalance($student_id);
$transactions = getStudentTransactions($student_id);
$financial_aid = getStudentFinancialAid($student_id);

// Get current balance due (sum of all pending tuition minus payments/scholarships)
$balance_query = $pdo->prepare("
    SELECT 
        SUM(CASE 
            WHEN type IN ('tuition', 'other') THEN amount
            WHEN type IN ('payment', 'scholarship', 'financial_aid', 'refund') THEN -amount
            ELSE 0
        END) as balance_due
    FROM financial_transactions
    WHERE student_id = ? AND status = 'pending'
");
$balance_query->execute([$student_id]);
$balance_data = $balance_query->fetch();
$balance_due = $balance_data['balance_due'] ?? 0;

// Get all transaction history
$transactions_query = $pdo->prepare("
    SELECT *, 
        CASE 
            WHEN type IN ('payment', 'scholarship', 'financial_aid', 'refund') THEN -amount
            ELSE amount
        END as display_amount
    FROM financial_transactions
    WHERE student_id = ?
    ORDER BY transaction_date DESC
    LIMIT 10
");
$transactions_query->execute([$student_id]);
$transactions = $transactions_query->fetchAll();

// Get financial aid/scholarships specifically
$financial_aid_query = $pdo->prepare("
    SELECT *
    FROM financial_transactions
    WHERE student_id = ? AND type IN ('scholarship', 'financial_aid')
    ORDER BY transaction_date DESC
    LIMIT 5
");
$financial_aid_query->execute([$student_id]);
$financial_aid = $financial_aid_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Information</title>
    <link href="/university-system/css/student.css" rel="stylesheet">
    <style>
        .text-danger { color: red; }
        .text-success { color: green; }
        /* Add other necessary styles here */
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Financial Information</h1>
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
                        <li><a href="academics.php">Academics</a></li>
                        <li class="active"><a href="finances.php">Finances</a></li>
                        <li><a href="resources.php">Resources</a></li>
                        <li><a href="requests.php">Requests</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <!-- Balance Overview -->
                <section class="balance-overview">
                    <h2>Account Balance</h2>
                    <div class="balance-card">
                        <h3>Current Balance Due</h3>
                        <div class="balance-amount">$<?= number_format($balance_due, 2) ?></div>
                        <button id="makePaymentBtn" class="btn-primary">Make Payment</button>
                    </div>
                </section>

                <!-- Payment History -->
                <section class="payment-history">
                    <h2>Transaction History</h2>
                    <?php if (empty($transactions)): ?>
                        <p>No transaction history found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $transaction['type']))) ?></td>
                                    <td><?= htmlspecialchars($transaction['description']) ?></td>
                                    <td class="<?= $transaction['display_amount'] < 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $transaction['display_amount'] < 0 ? '-' : '' ?>$<?= number_format(abs($transaction['display_amount']), 2) ?>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst($transaction['status'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <!-- Financial Aid -->
                <section class="financial-aid">
                    <h2>Financial Aid & Scholarships</h2>
                    <?php if (empty($financial_aid)): ?>
                        <p>No financial aid or scholarships found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($financial_aid as $aid): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($aid['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $aid['type']))) ?></td>
                                    <td><?= htmlspecialchars($aid['description']) ?></td>
                                    <td class="text-success">$<?= number_format($aid['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($aid['status'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <!-- Payment Modal -->
                <div id="paymentModal" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h2>Make Payment</h2>
                        <form id="paymentForm" action="/university-system/php/process_payment.php" method="POST">
                            <div class="form-group">
                                <label for="paymentAmount">Amount</label>
                                <input type="number" name="amount" id="paymentAmount" min="0.01" step="0.01" max="<?= $balance_due ?>" value="<?= $balance_due ?>">
                            </div>
                            <div class="form-group">
                                <label for="paymentMethod">Payment Method</label>
                                <select id="paymentMethod" name="payment_method">
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cardNumber">Card Number</label>
                                <input type="text" id="cardNumber" name="card_number" placeholder="1234 5678 9012 3456">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiryDate">Expiry Date</label>
                                    <input type="text" id="expiryDate" name="expiry_date" placeholder="MM/YY">
                                </div>
                                <div class="form-group">
                                    <label for="cvv">CVV</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123">
                                </div>
                            </div>
                            <input type="hidden" name="student_id" value="<?= $student_id ?>">
                            <button type="submit" class="btn-primary">Submit Payment</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById("paymentModal");
        const btn = document.getElementById("makePaymentBtn");
        const span = document.getElementsByClassName("close")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>