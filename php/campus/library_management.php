<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /university-system/login.html");
    exit();
}

if ($_SESSION['role'] != 'campus') {
    header("Location: /university-system/unauthorized.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_book'])) {
        $isbn = $_POST['isbn'];
        $title = $_POST['title'];
        $author = $_POST['author'];
        $publisher = $_POST['publisher'];
        $publication_year = $_POST['publication_year'];
        $category = $_POST['category'];
        $shelf_location = $_POST['shelf_location'];
        $quantity = $_POST['quantity'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO library_books (isbn, title, author, publisher, publication_year, category, shelf_location, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$isbn, $title, $author, $publisher, $publication_year, $category, $shelf_location, $quantity, $status]);

        $_SESSION['message'] = "Book added successfully";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['checkout_book'])) {
        $book_id = $_POST['book_id'];
        $user_id = $_POST['user_id'];
        $due_date = $_POST['due_date'];

        // Check book availability
        $book = $pdo->query("SELECT status, quantity FROM library_books WHERE id = $book_id")->fetch();

        if ($book['status'] != 'available' || $book['quantity'] < 1) {
            $_SESSION['error'] = "Book is not available for checkout";
            header("Location: library_management.php");
            exit();
        }

        // Create loan record
        $stmt = $pdo->prepare("INSERT INTO library_loans (book_id, user_id, checkout_date, due_date) VALUES (?, ?, CURDATE(), ?)");
        $stmt->execute([$book_id, $user_id, $due_date]);

        // Update book quantity
        $pdo->query("UPDATE library_books SET quantity = quantity - 1 WHERE id = $book_id");

        $_SESSION['message'] = "Book checked out successfully";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['return_book'])) {
        $loan_id = $_POST['loan_id'];

        // Get book ID from loan
        $book_id = $pdo->query("SELECT book_id FROM library_loans WHERE id = $loan_id")->fetchColumn();

        // Update loan record
        $stmt = $pdo->prepare("UPDATE library_loans SET return_date = CURDATE(), status = 'returned' WHERE id = ?");
        $stmt->execute([$loan_id]);

        // Update book quantity
        $pdo->query("UPDATE library_books SET quantity = quantity + 1 WHERE id = $book_id");

        $_SESSION['message'] = "Book returned successfully";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['delete_loan'])) {
        $loan_id = $_POST['loan_id'];

        // Get book ID from loan
        $book_id = $pdo->query("SELECT book_id FROM library_loans WHERE id = $loan_id")->fetchColumn();

        // Delete loan record
        $stmt = $pdo->prepare("DELETE FROM library_loans WHERE id = ?");
        $stmt->execute([$loan_id]);

        // Update book quantity if loan was active
        $pdo->query("UPDATE library_books SET quantity = quantity + 1 WHERE id = $book_id AND (SELECT status FROM library_loans WHERE id = $loan_id) = 'active'");

        $_SESSION['message'] = "Loan record deleted successfully";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['update_book'])) {
        $book_id = $_POST['book_id'];
        $isbn = $_POST['isbn'];
        $title = $_POST['title'];
        $author = $_POST['author'];
        $publisher = $_POST['publisher'];
        $publication_year = $_POST['publication_year'];
        $category = $_POST['category'];
        $shelf_location = $_POST['shelf_location'];
        $quantity = $_POST['quantity'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE library_books SET isbn = ?, title = ?, author = ?, publisher = ?, publication_year = ?, category = ?, shelf_location = ?, quantity = ?, status = ? WHERE id = ?");
        $stmt->execute([$isbn, $title, $author, $publisher, $publication_year, $category, $shelf_location, $quantity, $status, $book_id]);

        $_SESSION['message'] = "Book updated successfully";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['delete_book'])) {
        $book_id = $_POST['book_id'];

        // Check if book has active loans
        $active_loans = $pdo->query("SELECT COUNT(*) FROM library_loans WHERE book_id = $book_id AND status = 'active'")->fetchColumn();

        if ($active_loans > 0) {
            $_SESSION['error'] = "Cannot delete book with active loans";
            header("Location: library_management.php");
            exit();
        }

        // Delete book
        $stmt = $pdo->prepare("DELETE FROM library_books WHERE id = ?");
        $stmt->execute([$book_id]);

        $_SESSION['message'] = "Book deleted successfully";
        header("Location: library_management.php");
        exit();
    }

    // Handle request approvals
    if (isset($_POST['approve_request'])) {
        $request_id = $_POST['request_id'];
        $book_id = $_POST['book_id'];
        $user_id = $_POST['user_id'];

        // Check book availability
        $book = $pdo->query("SELECT status, quantity FROM library_books WHERE id = $book_id")->fetch();

        if ($book['status'] != 'available' || $book['quantity'] < 1) {
            $_SESSION['error'] = "Book is no longer available for checkout";
            header("Location: library_management.php");
            exit();
        }

        // Create loan record
        $due_date = date('Y-m-d', strtotime('+14 days'));
        $stmt = $pdo->prepare("INSERT INTO library_loans (book_id, user_id, checkout_date, due_date, status) VALUES (?, ?, CURDATE(), ?, 'active')");
        $stmt->execute([$book_id, $user_id, $due_date]);

        // Update book quantity
        $pdo->query("UPDATE library_books SET quantity = quantity - 1 WHERE id = $book_id");

        // Update request status
        $pdo->query("UPDATE book_requests SET status = 'approved' WHERE id = $request_id");

        $_SESSION['message'] = "Request approved and book checked out";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['reject_request'])) {
        $request_id = $_POST['request_id'];
        $pdo->query("UPDATE book_requests SET status = 'rejected' WHERE id = $request_id");

        $_SESSION['message'] = "Request rejected";
        header("Location: library_management.php");
        exit();
    }

    if (isset($_POST['process_return'])) {
        $return_id = $_POST['return_id'];
        $loan_id = $_POST['loan_id'];
        $book_id = $_POST['book_id'];

        // Update loan record
        $stmt = $pdo->prepare("UPDATE library_loans SET return_date = CURDATE(), status = 'returned' WHERE id = ?");
        $stmt->execute([$loan_id]);

        // Update book quantity
        $pdo->query("UPDATE library_books SET quantity = quantity + 1 WHERE id = $book_id");

        // Update return request status
        $pdo->query("UPDATE return_requests SET status = 'processed' WHERE id = $return_id");

        $_SESSION['message'] = "Return processed successfully";
        header("Location: library_management.php");
        exit();
    }
}

// Build base queries
$book_query = "SELECT * FROM library_books";

// Apply filters if they exist
$params = [];

$loan_query = "
    SELECT ll.*, lb.title, lb.author, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name,
           u.email as user_email
    FROM library_loans ll
    JOIN library_books lb ON ll.book_id = lb.id
    JOIN users u ON ll.user_id = u.id
";

// Apply filters if they exist
$book_filters = [];
if (isset($_GET['book_status_filter']) && !empty($_GET['book_status_filter'])) {
    $book_filters[] = "status = ?";
    $params[] = $_GET['book_status_filter'];
}

if (isset($_GET['book_category_filter']) && !empty($_GET['book_category_filter'])) {
    $book_filters[] = "category = ?";
    $params[] = $_GET['book_category_filter'];
}

if (!empty($book_filters)) {
    $book_query .= " WHERE " . implode(" AND ", $book_filters);
}

$book_query .= " ORDER BY title";
$stmt = $pdo->prepare($book_query);
$stmt->execute($params);
$books = $stmt->fetchAll();

$loan_filters = [];
if (isset($_GET['loan_status_filter'])) {
    $loan_filters[] = "ll.status = '" . $_GET['loan_status_filter'] . "'";
}
if (!empty($loan_filters)) {
    $loan_query .= " WHERE " . implode(" AND ", $loan_filters);
}
$loan_query .= " ORDER BY ll.due_date";
$loans = $pdo->query($loan_query)->fetchAll();

// Get all users
$users = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE status = 'active'")->fetchAll();

// Get unique categories for filter dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management</title>
    <link href="/university-system/css/campus.css" rel="stylesheet">
    <style>
    
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-available {
            background-color: #d4edda;
            color: #155724;
        }

        .status-checked_out {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-reserved {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-lost {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-maintenance {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-expired {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-returned {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .filter-container {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-container label {
            font-weight: bold;
            margin-right: 5px;
        }

        .filter-container select,
        .filter-container input {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .filter-container button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .filter-container button:hover {
            background-color: #45a049;
        }

        .filter-container .reset-btn {
            background-color: #f44336;
        }

        .filter-container .reset-btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Library Management</h1>
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
                        <li><a href="hostel_management.php">Hostel Management</a></li>
                        <li><a href="transport_management.php">Transport Management</a></li>
                        <li class="active"><a href="library_management.php">Library Management</a></li>
                        <li><a href="medical_management.php">Medical Center</a></li>
                        <li><a href="canteen_management.php">Canteen Management</a></li>
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

                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="openTab('books')">Books</button>
                        <button class="tab-btn" onclick="openTab('loans')">Loans</button>
                        <button class="tab-btn" onclick="openTab('requests')">Requests</button>
                        <button class="tab-btn" onclick="openTab('returns')">Returns</button>
                        <button class="tab-btn" onclick="openTab('addBook')">Add Book</button>
                        <button class="tab-btn" onclick="openTab('checkout')">Checkout Book</button>
                    </div>

                    <div id="books" class="tab-content active">
                        <h2>Library Books</h2>

                        <!-- Book Filters -->
                        <div class="filter-container">
                            <form method="get" action="library_management.php">
                                <input type="hidden" name="tab" value="books">
                                <div>
                                    <label for="book_status_filter">Status:</label>
                                    <select name="book_status_filter" id="book_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="available" <?= isset($_GET['book_status_filter']) && $_GET['book_status_filter'] === 'available' ? 'selected' : '' ?>>Available
                                        </option>
                                        <option value="checked_out" <?= isset($_GET['book_status_filter']) && $_GET['book_status_filter'] === 'checked_out' ? 'selected' : '' ?>>Checked Out
                                        </option>
                                        <option value="reserved" <?= isset($_GET['book_status_filter']) && $_GET['book_status_filter'] === 'reserved' ? 'selected' : '' ?>>Reserved
                                        </option>
                                        <option value="lost" <?= isset($_GET['book_status_filter']) && $_GET['book_status_filter'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                                        <option value="maintenance" <?= isset($_GET['book_status_filter']) && $_GET['book_status_filter'] === 'maintenance' ? 'selected' : '' ?>>Maintenance
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="book_category_filter">Category:</label>
                                    <select name="book_category_filter" id="book_category_filter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category['category']) ?>"
                                                <?= isset($_GET['book_category_filter']) && $_GET['book_category_filter'] == $category['category'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['category']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetBookFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>ISBN</th>
                                    <th>Category</th>
                                    <th>Shelf Location</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($book['title']) ?></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= htmlspecialchars($book['isbn']) ?></td>
                                        <td><?= htmlspecialchars($book['category']) ?></td>
                                        <td><?= htmlspecialchars($book['shelf_location']) ?></td>
                                        <td><?= $book['quantity'] ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $book['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $book['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showBookModal(<?= $book['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteBookModal(<?= $book['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="requests" class="tab-content">
                        <h2>Pending Book Requests</h2>
                        <?php
                        $requests = $pdo->query("
        SELECT br.*, lb.title, lb.author, lb.shelf_location,
               CONCAT(u.first_name, ' ', u.last_name) as user_name,
               u.email as user_email
        FROM book_requests br
        JOIN library_books lb ON br.book_id = lb.id
        JOIN users u ON br.user_id = u.id
        WHERE br.status = 'pending'
        ORDER BY br.request_date
    ")->fetchAll();
                        ?>

                        <?php if (count($requests) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Requested By</th>
                                        <th>Request Date</th>
                                        <th>Shelf Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['title']) ?></td>
                                            <td><?= htmlspecialchars($request['author']) ?></td>
                                            <td><?= htmlspecialchars($request['user_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                            <td><?= htmlspecialchars($request['shelf_location']) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <form method="post" action="library_management.php"
                                                        style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <input type="hidden" name="book_id" value="<?= $request['book_id'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $request['user_id'] ?>">
                                                        <button type="submit" name="approve_request"
                                                            class="logout-btn">Approve</button>
                                                    </form>
                                                    <form method="post" action="library_management.php"
                                                        style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="submit" name="reject_request"
                                                            class="logout-btn-warning">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No pending book requests.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Add this new tab content -->
                    <div id="returns" class="tab-content">
                        <h2>Pending Return Requests</h2>
                        <?php
                        $returns = $pdo->query("
        SELECT rr.*, ll.book_id, ll.user_id, ll.due_date,
               lb.title, lb.author,
               CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM return_requests rr
        JOIN library_loans ll ON rr.loan_id = ll.id
        JOIN library_books lb ON ll.book_id = lb.id
        JOIN users u ON ll.user_id = u.id
        WHERE rr.status = 'pending'
        ORDER BY rr.request_date
    ")->fetchAll();
                        ?>

                        <?php if (count($returns) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Returned By</th>
                                        <th>Due Date</th>
                                        <th>Request Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returns as $return): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($return['title']) ?></td>
                                            <td><?= htmlspecialchars($return['author']) ?></td>
                                            <td><?= htmlspecialchars($return['user_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($return['due_date'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($return['request_date'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <form method="post" action="library_management.php"
                                                        style="display: inline;">
                                                        <input type="hidden" name="return_id" value="<?= $return['id'] ?>">
                                                        <input type="hidden" name="loan_id" value="<?= $return['loan_id'] ?>">
                                                        <input type="hidden" name="book_id" value="<?= $return['book_id'] ?>">
                                                        <button type="submit" name="process_return" class="logout-btn">Process
                                                            Return</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No pending return requests.</p>
                        <?php endif; ?>
                    </div>

                    <div id="loans" class="tab-content">
                        <h2>Book Loans</h2>

                        <!-- Loan Filters -->
                        <div class="filter-container">
                            <form method="get" action="library_management.php">
                                <input type="hidden" name="tab" value="loans">
                                <div>
                                    <label for="loan_status_filter">Status:</label>
                                    <select name="loan_status_filter" id="loan_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['loan_status_filter']) && $_GET['loan_status_filter'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="expired" <?= isset($_GET['loan_status_filter']) && $_GET['loan_status_filter'] == 'expired' ? 'selected' : '' ?>>Expired</option>
                                        <option value="returned" <?= isset($_GET['loan_status_filter']) && $_GET['loan_status_filter'] == 'returned' ? 'selected' : '' ?>>Returned
                                        </option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetLoanFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Author</th>
                                    <th>Borrower</th>
                                    <th>Checkout Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($loan['title']) ?></td>
                                        <td><?= htmlspecialchars($loan['author']) ?></td>
                                        <td><?= htmlspecialchars($loan['user_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($loan['checkout_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($loan['due_date'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $loan['status'] ?>">
                                                <?= ucfirst($loan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($loan['status'] == 'active'): ?>
                                                    <button onclick="showReturnLoanModal(<?= $loan['id'] ?>)"
                                                        class="logout-btn">Return</button>
                                                <?php endif; ?>
                                                <button onclick="showDeleteLoanModal(<?= $loan['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="addBook" class="tab-content">
                        <h2>Add New Book</h2>
                        <form method="post" action="library_management.php">
                            <div class="form-group">
                                <label for="isbn">ISBN</label>
                                <input type="text" name="isbn" id="isbn">
                            </div>
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" name="title" id="title" required>
                            </div>
                            <div class="form-group">
                                <label for="author">Author</label>
                                <input type="text" name="author" id="author" required>
                            </div>
                            <div class="form-group">
                                <label for="publisher">Publisher</label>
                                <input type="text" name="publisher" id="publisher">
                            </div>
                            <div class="form-group">
                                <label for="publication_year">Publication Year</label>
                                <input type="number" name="publication_year" id="publication_year" min="1800"
                                    max="<?= date('Y') ?>">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" name="category" id="category">
                            </div>
                            <div class="form-group">
                                <label for="shelf_location">Shelf Location</label>
                                <input type="text" name="shelf_location" id="shelf_location" required>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" name="quantity" id="quantity" min="1" value="1" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="available">Available</option>
                                    <option value="checked_out">Checked Out</option>
                                    <option value="reserved">Reserved</option>
                                    <option value="lost">Lost</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                            <button type="submit" name="add_book" class="logout-btn">Add Book</button>
                        </form>
                    </div>

                    <div id="checkout" class="tab-content">
                        <h2>Checkout Book</h2>
                        <form method="post" action="library_management.php">
                            <div class="form-group">
                                <label for="user_id">User</label>
                                <select name="user_id" id="user_id" required>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="book_id">Book</label>
                                <select name="book_id" id="book_id" required>
                                    <?php
                                    $available_books = $pdo->query("SELECT * FROM library_books WHERE status = 'available' AND quantity > 0")->fetchAll();
                                    foreach ($available_books as $book): ?>
                                        <option value="<?= $book['id'] ?>">
                                            <?= htmlspecialchars($book['title'] . ' by ' . $book['author'] . ' (' . $book['shelf_location'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="due_date">Due Date</label>
                                <input type="date" name="due_date" id="due_date" required>
                            </div>
                            <button type="submit" name="checkout_book" class="logout-btn">Checkout Book</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Book Management Modal -->
    <div class="modal" id="bookModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('bookModal')">&times;</span>
            <h2>Manage Book</h2>
            <form method="post" action="library_management.php">
                <input type="hidden" name="book_id" id="modal_book_id">

                <div class="form-group">
                    <label for="modal_book_isbn">ISBN</label>
                    <input type="text" name="isbn" id="modal_book_isbn">
                </div>

                <div class="form-group">
                    <label for="modal_book_title">Title</label>
                    <input type="text" name="title" id="modal_book_title" required>
                </div>

                <div class="form-group">
                    <label for="modal_book_author">Author</label>
                    <input type="text" name="author" id="modal_book_author" required>
                </div>

                <div class="form-group">
                    <label for="modal_book_publisher">Publisher</label>
                    <input type="text" name="publisher" id="modal_book_publisher">
                </div>

                <div class="form-group">
                    <label for="modal_book_publication_year">Publication Year</label>
                    <input type="number" name="publication_year" id="modal_book_publication_year" min="1800"
                        max="<?= date('Y') ?>">
                </div>

                <div class="form-group">
                    <label for="modal_book_category">Category</label>
                    <input type="text" name="category" id="modal_book_category">
                </div>

                <div class="form-group">
                    <label for="modal_book_shelf_location">Shelf Location</label>
                    <input type="text" name="shelf_location" id="modal_book_shelf_location" required>
                </div>

                <div class="form-group">
                    <label for="modal_book_quantity">Quantity</label>
                    <input type="number" name="quantity" id="modal_book_quantity" min="1" required>
                </div>

                <div class="form-group">
                    <label for="modal_book_status">Status</label>
                    <select name="status" id="modal_book_status" required>
                        <option value="available">Available</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="reserved">Reserved</option>
                        <option value="lost">Lost</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>

                <button type="submit" name="update_book" class="logout-btn">Update Book</button>
            </form>
        </div>
    </div>

    <!-- Delete Book Confirmation Modal -->
    <div class="modal" id="deleteBookModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteBookModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this book?</p>
            <form method="post" action="library_management.php">
                <input type="hidden" name="book_id" id="delete_book_id">
                <div class="form-group">
                    <button type="submit" name="delete_book" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteBookModal')"
                        class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Loan Modal -->
    <div class="modal" id="returnLoanModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('returnLoanModal')">&times;</span>
            <h2>Confirm Return</h2>
            <p>Are you sure you want to mark this book as returned?</p>
            <form method="post" action="library_management.php">
                <input type="hidden" name="loan_id" id="return_loan_id">
                <div class="form-group">
                    <button type="submit" name="return_book" class="logout-btn">Return</button>
                    <button type="button" onclick="hideModal('returnLoanModal')"
                        class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Loan Confirmation Modal -->
    <div class="modal" id="deleteLoanModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteLoanModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this loan record?</p>
            <form method="post" action="library_management.php">
                <input type="hidden" name="loan_id" id="delete_loan_id">
                <div class="form-group">
                    <button type="submit" name="delete_loan" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteLoanModal')"
                        class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }

            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showBookModal(bookId) {
            // Find the book in the books array that was loaded on page load
            const books = <?= json_encode($books) ?>;
            const book = books.find(b => b.id == bookId);

            if (book) {
                document.getElementById('modal_book_id').value = book.id;
                document.getElementById('modal_book_isbn').value = book.isbn || '';
                document.getElementById('modal_book_title').value = book.title;
                document.getElementById('modal_book_author').value = book.author;
                document.getElementById('modal_book_publisher').value = book.publisher || '';
                document.getElementById('modal_book_publication_year').value = book.publication_year || '';
                document.getElementById('modal_book_category').value = book.category || '';
                document.getElementById('modal_book_shelf_location').value = book.shelf_location;
                document.getElementById('modal_book_quantity').value = book.quantity;
                document.getElementById('modal_book_status').value = book.status;

                showModal('bookModal');
            } else {
                alert('Book not found');
            }
        }

        function showDeleteBookModal(bookId) {
            document.getElementById('delete_book_id').value = bookId;
            showModal('deleteBookModal');
        }

        function showReturnLoanModal(loanId) {
            document.getElementById('return_loan_id').value = loanId;
            showModal('returnLoanModal');
        }

        function showDeleteLoanModal(loanId) {
            document.getElementById('delete_loan_id').value = loanId;
            showModal('deleteLoanModal');
        }

        // Reset filter functions
        function resetBookFilters() {
            window.location.href = 'library_management.php?tab=books';
        }

        function resetLoanFilters() {
            window.location.href = 'library_management.php?tab=loans';
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const dueDate = new Date();
            dueDate.setDate(today.getDate() + 14); // 2 weeks from today

            document.getElementById('due_date').valueAsDate = dueDate;

            // Close modal when clicking outside
            window.onclick = function (event) {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    if (event.target == modals[i]) {
                        modals[i].style.display = 'none';
                    }
                }
            }

            // Open the correct tab if coming from a filtered view
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                openTab(tabParam);
            }
        });
    </script>
</body>

</html>