<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Redirect if not logged in (optional - you might want to handle this differently)
if (!$isLoggedIn) {
    header("Location: /university-system/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's current loans
$loans = $pdo->prepare("
    SELECT ll.*, lb.title, lb.author, lb.shelf_location
    FROM library_loans ll
    JOIN library_books lb ON ll.book_id = lb.id
    WHERE ll.user_id = ? AND ll.status = 'active'
");
$loans->execute([$user_id]);
$user_loans = $loans->fetchAll();

// Get user's pending requests
$requests = $pdo->prepare("
    SELECT br.*, lb.title, lb.author
    FROM book_requests br
    JOIN library_books lb ON br.book_id = lb.id
    WHERE br.user_id = ? AND br.status = 'pending'
");
$requests->execute([$user_id]);
$pending_requests = $requests->fetchAll();

// Get available books
$book_query = "SELECT * FROM library_books WHERE status = 'available' AND quantity > 0";
$params = [];

// Apply filters
if (isset($_GET['category_filter']) && !empty($_GET['category_filter'])) {
    $book_query .= " AND category = ?";
    $params[] = $_GET['category_filter'];
}

if (isset($_GET['search_query']) && !empty($_GET['search_query'])) {
    $book_query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $search_term = '%' . $_GET['search_query'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$book_query .= " ORDER BY title";
$stmt = $pdo->prepare($book_query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get unique categories
$categories = $pdo->query("SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['request_book'])) {
        $book_id = $_POST['book_id'];

        // Check if book is available
        $book = $pdo->prepare("SELECT status, quantity FROM library_books WHERE id = ?");
        $book->execute([$book_id]);
        $book_data = $book->fetch();

        if ($book_data['status'] == 'available' && $book_data['quantity'] > 0) {
            // Create request
            $stmt = $pdo->prepare("INSERT INTO book_requests (book_id, user_id, request_date, status) VALUES (?, ?, NOW(), 'pending')");
            $stmt->execute([$book_id, $user_id]);

            $_SESSION['message'] = "Book request submitted! Staff will process it shortly.";
            header("Location: library.php");
            exit();
        } else {
            $_SESSION['error'] = "Book is no longer available";
            header("Location: library.php");
            exit();
        }
    }

    if (isset($_POST['return_book'])) {
        $loan_id = $_POST['loan_id'];

        // Create return request
        $stmt = $pdo->prepare("INSERT INTO return_requests (loan_id, request_date, status) VALUES (?, NOW(), 'pending')");
        $stmt->execute([$loan_id]);

        $_SESSION['message'] = "Return request submitted. Please return the book to the library.";
        header("Location: library.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Library</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #f6b93b;
            --primary-dark: #e8a825;
            --secondary: #1e3799;
            --secondary-light: #4a69bd;
            --accent: #e55039;
            --background-light: #ffffff;
            --background-dark: #2d3436;
            --text-light: #2d3436;
            --text-dark: #ffffff;
            --text-muted: #6c757d;
            --success: darkcyan;
            --warning: #f39c12;
            --danger: #e55039;
            --info: #4a69bd;
            --border-radius: 12px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            overflow-x: hidden;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1e3799, rgb(34, 61, 167));
            color: white;
            position: fixed;
            height: 100vh;
            padding: 2rem 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
            overflow-y: auto;
            top: 0;
            left: 0;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.12);
            bottom: -40px;
            right: -40px;
            border-radius: 50%;
            z-index: -1;
            animation: floatBlob 8s ease-in-out infinite alternate;
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h2 i {
            color: #ffeb3b;
        }

        .sidebar-menu {
            padding: 1.5rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dashboard-container {
            width: 100%;
            padding: 0;
        }

        /* Header Styles */
        .dashboard-header {
            width: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: rgba(255, 255, 255, 0.98);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 100%;
            margin: 0 auto;
            gap: 1.5rem;
        }

        .header-content h1 {
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .welcome-msg {
            font-weight: 500;
            color: #555;
            margin: 0;
            padding: 0;
            white-space: nowrap;
        }

        /* Button Styles */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--secondary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--secondary-light);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-outline:hover {
            background-color: rgba(30, 55, 153, 0.1);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
            border: none;
        }

        .btn-success:hover {
            background-color: #5cb85c;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
            border: none;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        /* Content Area Styles */
        .dashboard-content {
            padding: 1.25rem 2rem;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-light);
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .book-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .book-icon {
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .book-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }

        .book-author {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .book-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .book-details span {
            display: block;
            margin-bottom: 0.3rem;
        }

        .book-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        /* Loans Table */
        .loans-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-light);
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-available {
            background-color: #d4edda;
            color: #155724;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .user-actions {
                width: 100%;
                justify-content: space-between;
            }

            .dashboard-content {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .books-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }
        }

        /* Animations */
        @keyframes floatBlob {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            50% {
                transform: translate(20px, 20px) rotate(180deg);
            }

            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 5px;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
        }

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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Tab System */
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .tab-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .tab-btn.active {
            background-color: var(--secondary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Filter Container */
        .filter-container {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .filter-container label {
            font-weight: 500;
            margin-right: 0.5rem;
        }

        .filter-container select,
        .filter-container input {
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .filter-container button {
            padding: 0.5rem 1rem;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-container button:hover {
            background-color: var(--secondary-light);
        }

        .filter-container .btn-outline {
            background-color: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--secondary);
            color: white;
            font-weight: 500;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-available {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert.success {
            background-color: var(--success);
        }

        .alert.error {
            background-color: var(--danger);
        }

        /* Button Styles */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-block;
            text-align: center;
            border: none;
        }

        .btn-primary {
            background-color: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-light);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #5cb85c;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-outline:hover {
            background-color: rgba(30, 55, 153, 0.1);
        }

        /* Responsive Table */
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
            }

            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-container>div {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-university"></i> University</h2>
        </div>
        <div class="sidebar-menu">
            <!-- Common items for all users -->
            <a href="/university-system/index.html" class="menu-item">
                <i class="fas fa-home"></i> Home
            </a>

            <?php if ($isLoggedIn): ?>
                <!-- Dashboard link based on user role -->
                <a href="/university-system/php/<?= htmlspecialchars($_SESSION['role']) ?>/dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <!-- Common services for all authenticated users -->
                <a href="/university-system/php/public/canteen.php" class="menu-item">
                    <i class="fas fa-concierge-bell"></i> Canteen
                </a>
                <a href="/university-system/php/public/library.php" class="menu-item active">
                    <i class="fas fa-book"></i> Library
                </a>
                <a href="/university-system/php/public/medical.php" class="menu-item">
                    <i class="fas fa-heartbeat"></i> Medical Center
                </a>
                <a href="/university-system/php/public/transport.php" class="menu-item">
                    <i class="fas fa-bus"></i> Transport
                </a>

                <a href="/university-system/php/public/hostel.php" class="menu-item">
                    <i class="fas fa-bus"></i> Hostel
                </a>

                <a href="/university-system/php/public/recruitment.php" class="menu-item">
                    <i class="fas fa-bus"></i> Recruitment
                </a>

            <?php else: ?>
                <!-- Items for non-logged in users -->
                <a href="/university-system/login.html" class="menu-item">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="/university-system/register.html" class="menu-item">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-container">
            <header class="dashboard-header">
                <div class="header-content">
                    <h1>University Library</h1>
                    <div class="user-actions">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span class="welcome-msg">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                            <a href="/university-system/php/auth/logout.php" class="btn btn-primary">Logout</a>
                        <?php else: ?>
                            <a href="/university-system/login.html" class="btn btn-primary">Login</a>
                            <a href="/university-system/register.html" class="btn btn-outline">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="tab-buttons">
                <button class="tab-btn active" onclick="openTab('available')">Available Books</button>
                <button class="tab-btn" onclick="openTab('loans')">My Loans</button>
                <button class="tab-btn" onclick="openTab('requests')">My Requests</button>
            </div>

            <div id="available" class="tab-content active">
                <h2>Available Books</h2>
                <div class="filter-container">
                    <form method="get" action="library.php">
                        <input type="hidden" name="tab" value="available">
                        <div>
                            <label for="category_filter">Category:</label>
                            <select name="category_filter" id="category_filter">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['category']) ?>"
                                        <?= isset($_GET['category_filter']) && $_GET['category_filter'] == $category['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['category']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="search_query">Search:</label>
                            <input type="text" name="search_query" id="search_query"
                                value="<?= isset($_GET['search_query']) ? htmlspecialchars($_GET['search_query']) : '' ?>"
                                placeholder="Title, Author, or ISBN">
                        </div>
                        <button type="submit">Apply Filters</button>
                        <button type="button" onclick="window.location.href='library.php?tab=available'"
                            class="btn btn-outline">Reset</button>
                    </form>
                </div>

                <?php if (count($books) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Category</th>
                                <th>Shelf Location</th>
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
                                    <td>
                                        <span class="status-badge status-<?= $book['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $book['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="library.php" style="display: inline;">
                                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                            <button type="submit" name="request_book" class="btn btn-success">Request</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No books available matching your criteria.</p>
                <?php endif; ?>
            </div>

            <div id="loans" class="tab-content">
                <h2>Your Current Loans</h2>
                <?php if (count($user_loans) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Shelf Location</th>
                                <th>Checkout Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_loans as $loan): ?>
                                <tr>
                                    <td><?= htmlspecialchars($loan['title']) ?></td>
                                    <td><?= htmlspecialchars($loan['author']) ?></td>
                                    <td><?= htmlspecialchars($loan['shelf_location']) ?></td>
                                    <td><?= date('M j, Y', strtotime($loan['checkout_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($loan['due_date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $loan['status'] ?>">
                                            <?= ucfirst($loan['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="library.php" style="display: inline;">
                                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                            <button type="submit" name="return_book" class="btn btn-primary">Return</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You currently have no active book loans.</p>
                <?php endif; ?>
            </div>

            <div id="requests" class="tab-content">
                <h2>Your Pending Requests</h2>
                <?php if (count($pending_requests) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Request Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['title']) ?></td>
                                    <td><?= htmlspecialchars($request['author']) ?></td>
                                    <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You have no pending book requests.</p>
                <?php endif; ?>
            </div>

            <!-- Checkout Modal -->
            <div id="checkoutModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideModal('checkoutModal')">&times;</span>
                    <h2>Borrow Book</h2>
                    <form method="post" action="library.php">
                        <input type="hidden" name="book_id" id="checkout_book_id">
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" name="due_date" id="due_date" required>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="hideModal('checkoutModal')"
                                class="btn btn-outline">Cancel</button>
                            <button type="submit" name="checkout_book" class="btn btn-success">Confirm Borrow</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Return Modal -->
            <div id="returnModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideModal('returnModal')">&times;</span>
                    <h2>Return Book</h2>
                    <p>Are you sure you want to return this book?</p>
                    <form method="post" action="library.php">
                        <input type="hidden" name="loan_id" id="return_loan_id">
                        <div class="modal-actions">
                            <button type="button" onclick="hideModal('returnModal')"
                                class="btn btn-outline">Cancel</button>
                            <button type="submit" name="return_book" class="btn btn-success">Confirm Return</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Book Details Modal -->
            <div id="bookDetailsModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideModal('bookDetailsModal')">&times;</span>
                    <h2 id="book_details_title"></h2>
                    <div class="book-details-content" id="book_details_content">
                        <!-- Details will be loaded via AJAX -->
                    </div>
                    <div class="modal-actions">
                        <button onclick="hideModal('bookDetailsModal')" class="btn btn-primary">Close</button>
                    </div>
                </div>
            </div>

            <script>
                // Tab functionality
                function openTab(tabName) {
                    // Hide all tab contents
                    const tabContents = document.getElementsByClassName('tab-content');
                    for (let i = 0; i < tabContents.length; i++) {
                        tabContents[i].classList.remove('active');
                    }

                    // Deactivate all tab buttons
                    const tabButtons = document.getElementsByClassName('tab-btn');
                    for (let i = 0; i < tabButtons.length; i++) {
                        tabButtons[i].classList.remove('active');
                    }

                    // Activate the selected tab
                    document.getElementById(tabName).classList.add('active');
                    event.currentTarget.classList.add('active');

                    // Update URL without reloading
                    history.pushState(null, null, `?tab=${tabName}`);
                }

                // Initialize tab from URL
                document.addEventListener('DOMContentLoaded', function () {
                    const urlParams = new URLSearchParams(window.location.search);
                    const tabParam = urlParams.get('tab');

                    if (tabParam) {
                        // Find the tab button and click it
                        const tabButtons = document.getElementsByClassName('tab-btn');
                        for (let i = 0; i < tabButtons.length; i++) {
                            if (tabButtons[i].getAttribute('onclick').includes(tabParam)) {
                                tabButtons[i].click();
                                break;
                            }
                        }
                    }
                });
                // Show checkout modal
                function showCheckoutModal(bookId) {
                    document.getElementById('checkout_book_id').value = bookId;

                    // Set default due date (2 weeks from today)
                    const today = new Date();
                    const dueDate = new Date();
                    dueDate.setDate(today.getDate() + 14);

                    document.getElementById('due_date').valueAsDate = dueDate;
                    showModal('checkoutModal');
                }

                // Show return modal
                function showReturnModal(loanId) {
                    document.getElementById('return_loan_id').value = loanId;
                    showModal('returnModal');
                }

                // Show book details modal
                function showBookDetails(bookId) {
                    // Fetch book details via AJAX
                    fetch(`/university-system/php/api/get_book.php?id=${bookId}`)
                        .then(response => response.json())
                        .then(book => {
                            document.getElementById('book_details_title').textContent = book.title;

                            let detailsHtml = `
                        <p><strong>Author:</strong> ${book.author}</p>
                        <p><strong>ISBN:</strong> ${book.isbn || 'N/A'}</p>
                        <p><strong>Publisher:</strong> ${book.publisher || 'N/A'}</p>
                        <p><strong>Publication Year:</strong> ${book.publication_year || 'N/A'}</p>
                        <p><strong>Category:</strong> ${book.category || 'N/A'}</p>
                        <p><strong>Shelf Location:</strong> ${book.shelf_location}</p>
                        <p><strong>Available Copies:</strong> ${book.quantity}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${book.status}">
                            ${book.status.replace('_', ' ').toUpperCase()}
                        </span></p>
                    `;

                            if (book.description) {
                                detailsHtml += `<div class="book-description"><p>${book.description}</p></div>`;
                            }

                            document.getElementById('book_details_content').innerHTML = detailsHtml;
                            showModal('bookDetailsModal');
                        });
                }

                // Modal control functions
                function showModal(modalId) {
                    document.getElementById(modalId).style.display = 'block';
                }

                function hideModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                // Close modal when clicking outside
                window.onclick = function (event) {
                    const modals = document.getElementsByClassName('modal');
                    for (let i = 0; i < modals.length; i++) {
                        if (event.target == modals[i]) {
                            modals[i].style.display = 'none';
                        }
                    }
                }

                // Handle form submissions
                document.addEventListener('DOMContentLoaded', function () {
                    // Check for success/error messages in URL
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('success')) {
                        alert(urlParams.get('success'));
                    }
                    if (urlParams.has('error')) {
                        alert(urlParams.get('error'));
                    }
                });
            </script>
</body>

</html>