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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['place_order'])) {
        try {
            $pdo->beginTransaction();

            $user_id = $_SESSION['user_id'];
            $special_instructions = $_POST['special_instructions'] ?? '';
            $total_amount = 0;

            // Calculate total amount
            foreach ($_POST['items'] as $item_id => $quantity) {
                $item = $pdo->query("SELECT price FROM canteen_menu WHERE id = $item_id AND status = 'available'")->fetch();
                if ($item && $quantity > 0) {
                    $total_amount += $item['price'] * $quantity;
                }
            }

            // Create order
            $stmt = $pdo->prepare("INSERT INTO canteen_orders (user_id, total_amount, special_instructions, status, payment_status) 
                                  VALUES (?, ?, ?, 'pending', 'pending')");
            $stmt->execute([$user_id, $total_amount, $special_instructions]);
            $order_id = $pdo->lastInsertId();

            // Add order items
            foreach ($_POST['items'] as $item_id => $quantity) {
                if ($quantity > 0) {
                    $item = $pdo->query("SELECT * FROM canteen_menu WHERE id = $item_id AND status = 'available'")->fetch();
                    if ($item) {
                        $stmt = $pdo->prepare("INSERT INTO canteen_order_items 
                                            (order_id, menu_item_id, quantity, price_at_order, special_instructions) 
                                            VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$order_id, $item_id, $quantity, $item['price'], '']);

                        // Update inventory
                        $stmt = $pdo->prepare("UPDATE canteen_menu SET available_quantity = available_quantity - ? WHERE id = ?");
                        $stmt->execute([$quantity, $item_id]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = "Order placed successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error placing order: " . $e->getMessage();
        }

        header("Location: canteen.php");
        exit();
    }

    if (isset($_POST['toggle_favorite'])) {
        $item_id = (int) $_POST['item_id'];
        $user_id = (int) $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // Check if already favorite
            $stmt = $pdo->prepare("SELECT id FROM canteen_favorites WHERE user_id = ? AND menu_item_id = ?");
            $stmt->execute([$user_id, $item_id]);

            if ($stmt->fetch()) {
                // Remove favorite
                $stmt = $pdo->prepare("DELETE FROM canteen_favorites WHERE user_id = ? AND menu_item_id = ?");
                $stmt->execute([$user_id, $item_id]);
                $_SESSION['message'] = "Removed from favorites";
            } else {
                // Add favorite
                $stmt = $pdo->prepare("INSERT INTO canteen_favorites (user_id, menu_item_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $item_id]);
                $_SESSION['message'] = "Added to favorites";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating favorites: " . $e->getMessage();
        }

        header("Location: canteen.php");
        exit();
    }

    if (isset($_POST['cancel_order'])) {
        $order_id = $_POST['order_id'];

        // Verify order belongs to user
        $stmt = $pdo->prepare("SELECT id FROM canteen_orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        if ($stmt->fetch()) {
            // Only allow cancellation if order is still pending
            $stmt = $pdo->prepare("UPDATE canteen_orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$order_id]);

            // Restore inventory
            $items = $pdo->query("SELECT menu_item_id, quantity FROM canteen_order_items WHERE order_id = $order_id")->fetchAll();
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE canteen_menu SET available_quantity = available_quantity + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['menu_item_id']]);
            }

            $_SESSION['message'] = "Order cancelled successfully";
        } else {
            $_SESSION['error'] = "You can only cancel your own pending orders";
        }

        header("Location: canteen.php");
        exit();
    }
}

// Get available menu items
$menu_items = $pdo->query("
    SELECT cm.*, 
           CASE WHEN cf.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
    FROM canteen_menu cm
    LEFT JOIN canteen_favorites cf ON cm.id = cf.menu_item_id AND cf.user_id = " . $_SESSION['user_id'] . "
    WHERE cm.status = 'available' AND cm.available_quantity > 0
    ORDER BY is_favorite DESC, cm.category, cm.item_name
")->fetchAll();

// Get user's orders
$orders = $pdo->query("
    SELECT co.* 
    FROM canteen_orders co
    WHERE co.user_id = " . $_SESSION['user_id'] . "
    ORDER BY co.order_date DESC
    LIMIT 10
")->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM canteen_menu WHERE status = 'available' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canteen</title>
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
            --success: #78e08f;
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

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-btn {
            background-color: darkcyan;
            color: white;
            border: none;
            border-radius: 4px;
            width: 25px;
            height: 25px;
            cursor: pointer;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background-color: #008b8b;
            transform: scale(1.05);
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

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        /* Content Area Styles */
        .dashboard-content {
            padding: 1.25rem 2rem;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Tab Styles */
        .tab-container {
            margin-top: 1rem;
        }

        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 0.5rem 1rem;
            background-color: #e0e0e0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-btn:hover {
            background-color: #d0d0d0;
        }

        .tab-btn.active {
            background-color: var(--secondary);
            color: white;
        }

        .tab-content {
            display: none;
            padding: 1rem;
            margin: 1rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .tab-content.active {
            display: block;
        }

        /* Menu Item Styles */
        .menu-items-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .menu-item-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            transition: var(--transition);
            position: relative;
        }

        .menu-item-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }

        .menu-item-card.favorite {
            border-left: 4px solid var(--primary);
        }

        .menu-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }

        .menu-item-category {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .menu-item-description {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .menu-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-item-price {
            font-weight: 600;
            color: var(--primary-dark);
        }

        .menu-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .menu-item-quantity input {
            width: 50px;
            padding: 0.25rem;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .favorite-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            padding: 0;
            outline: none;
        }

        .favorite-btn i {
            transition: all 0.3s ease;
        }

        .favorite-btn:hover i {
            color: #e55039 !important;
            transform: scale(1.1);
        }

        /* Cart Styles */
        .cart-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }

        .cart-btn {
            background-color: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            transition: var(--transition);
        }

        .cart-btn:hover {
            background-color: #008b8b;
            transform: scale(1.05);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .cart-modal {
            display: none;
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            max-height: 500px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 101;
            overflow: hidden;
        }

        .cart-modal.active {
            display: block;
        }

        .cart-header {
            padding: 1rem;
            background-color: var(--secondary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-body {
            padding: 1rem;
            max-height: 350px;
            overflow-y: auto;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .cart-item-name {
            flex: 1;
        }

        .cart-item-quantity {
            width: 50px;
            text-align: center;
        }

        .cart-item-price {
            width: 80px;
            text-align: right;
        }

        .cart-footer {
            padding: 1rem;
            border-top: 1px solid #eee;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        /* Order History Styles */
        .order-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .order-id {
            font-weight: 600;
        }

        .order-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .order-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-preparing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-ready {
            background-color: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .order-items {
            margin-top: 0.5rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
        }

        .order-item-name {
            flex: 1;
        }

        .order-item-quantity {
            width: 50px;
            text-align: center;
        }

        .order-item-price {
            width: 80px;
            text-align: right;
        }

        .order-total {
            text-align: right;
            margin-top: 0.5rem;
            font-weight: 600;
        }

        /* Filter Styles */
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

        .filter-container select {
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

        /* Alert Styles */
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive Styles */
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

            .dashboard-header {
                padding: 1rem;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .menu-items-container {
                grid-template-columns: 1fr;
            }

            .cart-modal {
                width: calc(100% - 40px);
                right: 20px;
                left: 20px;
            }

            .favorite-btn {
                background: none;
                border: none;
                cursor: pointer;
                color: #ccc;
                font-size: 1.2rem;
                transition: var(--transition);
            }

            .favorite-btn.active {
                color: var(--danger);
            }

            .favorite-btn:hover {
                color: var(--danger);
                transform: scale(1.1);
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <a href="/university-system/php/public/canteen.php" class="menu-item active">
                    <i class="fas fa-concierge-bell"></i> Canteen
                </a>
                <a href="/university-system/php/public/library.php" class="menu-item">
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
                    <h1>Canteen</h1>
                    <div class="user-info">
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <a href="/university-system/php/auth/logout.php" class="btn btn-primary">Logout</a>
                    </div>
                </div>
            </header>

            <main class="dashboard-content">
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
                        <button class="tab-btn active" onclick="openTab('menu')">Menu</button>
                        <button class="tab-btn" onclick="openTab('orders')">My Orders</button>
                        <button class="tab-btn" onclick="openTab('favorites')">Favorites</button>
                    </div>

                    <div id="menu" class="tab-content active">
                        <h2>Menu</h2>

                        <!-- Menu Filters -->
                        <div class="filter-container">
                            <form id="menuFilterForm">
                                <div>
                                    <label for="category_filter">Category:</label>
                                    <select name="category_filter" id="category_filter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="search_term">Search:</label>
                                    <input type="text" name="search_term" id="search_term"
                                        placeholder="Search items...">
                                </div>
                                <button type="button" onclick="filterMenu()">Apply Filters</button>
                                <button type="button" onclick="resetFilters()" class="btn btn-danger">Reset</button>
                            </form>
                        </div>

                        <div class="menu-items-container" id="menuItemsContainer">
                            <?php foreach ($menu_items as $item): ?>
                                <div class="menu-item-card <?= $item['is_favorite'] ? 'favorite' : '' ?>"
                                    data-category="<?= htmlspecialchars($item['category']) ?>">
                                    <div class="menu-item-name">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                        <button class="favorite-btn <?= $item['is_favorite'] ? 'active' : '' ?>"
                                            onclick="toggleFavorite(<?= $item['id'] ?>, event)">
                                            <i class="fas fa-heart"
                                                style="<?= $item['is_favorite'] ? 'color: #e55039' : 'color: #ccc' ?>"></i>
                                        </button>
                                    </div>
                                    <div class="menu-item-category"><?= htmlspecialchars($item['category']) ?></div>
                                    <div class="menu-item-description">
                                        <?= htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : '') ?>
                                    </div>
                                    <div class="menu-item-footer">
                                        <div class="menu-item-price">$<?= number_format($item['price'], 2) ?></div>
                                        <div class="menu-item-quantity">
                                            <button class="quantity-btn"
                                                onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                                            <input type="number" id="quantity_<?= $item['id'] ?>" min="0"
                                                max="<?= $item['available_quantity'] ?>" value="0">
                                            <button class="quantity-btn"
                                                onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="orders" class="tab-content">
                        <h2>My Orders</h2>
                        <?php if (empty($orders)): ?>
                            <p>You have no orders yet.</p>
                        <?php else: ?>
                            <div class="order-list">
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div class="order-id">Order #<?= $order['id'] ?></div>
                                            <div class="order-date"><?= date('M j, Y H:i', strtotime($order['order_date'])) ?>
                                            </div>
                                        </div>
                                        <div class="order-status status-<?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </div>
                                        <?php if (!empty($order['special_instructions'])): ?>
                                            <div class="order-special-instructions">
                                                <strong>Note:</strong> <?= htmlspecialchars($order['special_instructions']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        $order_items = $pdo->query("
                                            SELECT coi.*, cm.item_name 
                                            FROM canteen_order_items coi
                                            JOIN canteen_menu cm ON coi.menu_item_id = cm.id
                                            WHERE coi.order_id = " . $order['id'] . "
                                        ")->fetchAll();
                                        ?>

                                        <div class="order-items">
                                            <?php foreach ($order_items as $item): ?>
                                                <div class="order-item">
                                                    <div class="order-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                                    <div class="order-item-quantity">x<?= $item['quantity'] ?></div>
                                                    <div class="order-item-price">
                                                        $<?= number_format($item['price_at_order'] * $item['quantity'], 2) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="order-total">
                                            Total: $<?= number_format($order['total_amount'], 2) ?>
                                        </div>

                                        <?php if ($order['status'] == 'pending'): ?>
                                            <form method="post" action="canteen.php" style="margin-top: 1rem;">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" name="cancel_order" class="btn btn-danger">Cancel
                                                    Order</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="favorites" class="tab-content">
                        <h2>My Favorite Items</h2>
                        <div class="menu-items-container" id="favoritesContainer">
                            <?php
                            $favorites = $pdo->query("
                                SELECT cm.* 
                                FROM canteen_menu cm
                                JOIN canteen_favorites cf ON cm.id = cf.menu_item_id
                                WHERE cf.user_id = " . $_SESSION['user_id'] . " 
                                AND cm.status = 'available'
                                ORDER BY cm.item_name
                            ")->fetchAll();

                            if (empty($favorites)): ?>
                                <p>You have no favorite items yet. Click the heart icon on menu items to add them to
                                    favorites.</p>
                            <?php else: ?>
                                <?php foreach ($favorites as $item): ?>
                                    <div class="menu-item-card favorite"
                                        data-category="<?= htmlspecialchars($item['category']) ?>">
                                        <div class="menu-item-name">
                                            <?= htmlspecialchars($item['item_name']) ?>
                                            <button class="favorite-btn active" onclick="toggleFavorite(<?= $item['id'] ?>)">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </div>
                                        <div class="menu-item-category"><?= htmlspecialchars($item['category']) ?></div>
                                        <div class="menu-item-description">
                                            <?= htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : '') ?>
                                        </div>
                                        <div class="menu-item-footer">
                                            <div class="menu-item-price">$<?= number_format($item['price'], 2) ?></div>
                                            <div class="menu-item-quantity">
                                                <button onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                                                <input type="number" id="quantity_<?= $item['id'] ?>" min="0"
                                                    max="<?= $item['available_quantity'] ?>" value="0">
                                                <button onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Cart Button -->
    <div class="cart-container">
        <button class="cart-btn " onclick="toggleCart()">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count" id="cartCount">0</span>
        </button>
    </div>

    <!-- Cart Modal -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-header">
            <h3>Your Order</h3>
            <button onclick="toggleCart()" style="background: none; border: none; color: white; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cart-body" id="cartItems">
            <!-- Cart items will be added here dynamically -->
            <p>Your cart is empty</p>
        </div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">$0.00</span>
            </div>
            <form method="post" action="canteen.php" id="orderForm">
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea name="special_instructions" id="special_instructions" rows="3"></textarea>
                </div>
                <button type="submit" name="place_order" class="btn btn-primary" style="width: 100%;">Place
                    Order</button>
            </form>
        </div>
    </div>

    <script>
        // Cart functionality
        let cart = {};

        function updateCartDisplay() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartCountElement = document.getElementById('cartCount');
            const cartTotalElement = document.getElementById('cartTotal');

            // Get all menu items data
            const menuItems = <?= json_encode($menu_items) ?>;

            // Calculate total items and amount
            let totalItems = 0;
            let totalAmount = 0;

            // Clear cart items display
            cartItemsContainer.innerHTML = '';

            if (Object.keys(cart).length === 0) {
                cartItemsContainer.innerHTML = '<p>Your cart is empty</p>';
            } else {
                // Create cart items list
                Object.keys(cart).forEach(itemId => {
                    const quantity = cart[itemId];
                    if (quantity > 0) {
                        const item = menuItems.find(i => i.id == itemId);
                        if (item) {
                            totalItems += quantity;
                            totalAmount += quantity * item.price;

                            const cartItemElement = document.createElement('div');
                            cartItemElement.className = 'cart-item';
                            cartItemElement.innerHTML = `
                                <div class="cart-item-name">${item.item_name}</div>
                                <div class="cart-item-quantity">x${quantity}</div>
                                <div class="cart-item-price">$${(quantity * item.price).toFixed(2)}</div>
                            `;
                            cartItemsContainer.appendChild(cartItemElement);
                        }
                    }
                });
            }

            // Update cart count and total
            cartCountElement.textContent = totalItems;
            cartTotalElement.textContent = `$${totalAmount.toFixed(2)}`;

            // Update hidden form fields for submission
            const orderForm = document.getElementById('orderForm');
            orderForm.innerHTML = ''; // Clear existing inputs

            // Add special instructions textarea
            orderForm.innerHTML = `
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea name="special_instructions" id="special_instructions" rows="3"></textarea>
                </div>
                <button type="submit" name="place_order" class="btn btn-primary" style="width: 100%;">Place Order</button>
            `;

            // Add hidden inputs for each item in cart
            Object.keys(cart).forEach(itemId => {
                if (cart[itemId] > 0) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${itemId}]`;
                    input.value = cart[itemId];
                    orderForm.insertBefore(input, orderForm.firstChild);
                }
            });
        }

        function toggleCart() {
            const cartModal = document.getElementById('cartModal');
            cartModal.classList.toggle('active');
        }

        function updateQuantity(itemId, change) {
            const inputElement = document.getElementById(`quantity_${itemId}`);
            let newValue = parseInt(inputElement.value) + change;

            // Get max quantity from data attribute
            const maxQuantity = parseInt(inputElement.max);

            // Validate new value
            newValue = Math.max(0, Math.min(newValue, maxQuantity));
            inputElement.value = newValue;

            // Update cart
            cart[itemId] = newValue;
            updateCartDisplay();
        }

        function toggleFavorite(itemId, event) {
            event.preventDefault();
            event.stopPropagation();

            const btn = event.currentTarget;
            btn.classList.toggle('active');

            // AJAX implementation for smoother experience
            fetch('canteen.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `toggle_favorite=1&item_id=${itemId}`
            })
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error('Network response was not ok');
                })
                .then(() => {
                    // Success - no need to refresh the page
                    // Update the heart icon color immediately
                    btn.querySelector('i').style.color = btn.classList.contains('active') ? '#e55039' : '#ccc';
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert the visual change if there was an error
                    btn.classList.toggle('active');
                });
        }

        function openTab(tabName) {
            // If clicking the favorites tab, reload the page with a hash
            if (tabName === 'favorites') {
                // Store current cart state in localStorage to preserve it
                localStorage.setItem('tempCart', JSON.stringify(cart));

                // Reload the page with #favorites hash
                window.location.href = window.location.pathname + '#favorites';
                window.location.reload();
                return;
            }

            // Normal tab switching for other tabs
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

        // Add this to your existing DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function () {
            // Restore cart from localStorage if available
            const savedCart = localStorage.getItem('tempCart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                localStorage.removeItem('tempCart');
                updateCartDisplay();
            }

            // Check for hash in URL and open corresponding tab
            if (window.location.hash === '#favorites') {
                // Switch to favorites tab
                const tabContents = document.getElementsByClassName('tab-content');
                for (let i = 0; i < tabContents.length; i++) {
                    tabContents[i].classList.remove('active');
                }

                const tabButtons = document.getElementsByClassName('tab-btn');
                for (let i = 0; i < tabButtons.length; i++) {
                    tabButtons[i].classList.remove('active');
                }

                document.getElementById('favorites').classList.add('active');
                // Find the favorites tab button and activate it
                const favTabBtn = document.querySelector('.tab-btn[onclick*="favorites"]');
                if (favTabBtn) favTabBtn.classList.add('active');
            }
        });

        function filterMenu() {
            const categoryFilter = document.getElementById('category_filter').value.toLowerCase();
            const searchTerm = document.getElementById('search_term').value.toLowerCase();
            const menuItems = document.querySelectorAll('.menu-item-card');

            menuItems.forEach(item => {
                const category = item.dataset.category.toLowerCase();
                const itemName = item.querySelector('.menu-item-name').textContent.toLowerCase();
                const itemDescription = item.querySelector('.menu-item-description').textContent.toLowerCase();

                const categoryMatch = !categoryFilter || category.includes(categoryFilter);
                const searchMatch = !searchTerm ||
                    itemName.includes(searchTerm) ||
                    itemDescription.includes(searchTerm) ||
                    category.includes(searchTerm);

                if (categoryMatch && searchMatch) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function resetFilters() {
            document.getElementById('category_filter').value = '';
            document.getElementById('search_term').value = '';
            filterMenu();
        }

        // Initialize cart
        document.addEventListener('DOMContentLoaded', function () {
            updateCartDisplay();

            // Close cart when clicking outside
            document.addEventListener('click', function (event) {
                const cartModal = document.getElementById('cartModal');
                const cartBtn = document.querySelector('.cart-btn');

                if (cartModal.classList.contains('active') &&
                    !cartModal.contains(event.target) &&
                    !cartBtn.contains(event.target)) {
                    cartModal.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>