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
    if (isset($_POST['add_menu_item'])) {
        $item_name = $_POST['item_name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $available_quantity = $_POST['available_quantity'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO canteen_menu (item_name, description, category, price, available_quantity, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_name, $description, $category, $price, $available_quantity, $status]);

        $_SESSION['message'] = "Menu item added successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['update_menu_item'])) {
        $item_id = $_POST['item_id'];
        $item_name = $_POST['item_name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $available_quantity = $_POST['available_quantity'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE canteen_menu SET item_name = ?, description = ?, category = ?, price = ?, available_quantity = ?, status = ? WHERE id = ?");
        $stmt->execute([$item_name, $description, $category, $price, $available_quantity, $status, $item_id]);

        $_SESSION['message'] = "Menu item updated successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['delete_menu_item'])) {
        $item_id = $_POST['item_id'];

        // Check if item is in any orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM canteen_order_items WHERE menu_item_id = ?");
        $stmt->execute([$item_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete menu item that has been ordered";
        } else {
            $stmt = $pdo->prepare("DELETE FROM canteen_menu WHERE id = ?");
            $stmt->execute([$item_id]);
            $_SESSION['message'] = "Menu item deleted successfully";
        }
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['add_staff'])) {
        $user_id = $_POST['user_id'];
        $position = $_POST['position'];
        $shift = $_POST['shift'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO canteen_staff (user_id, position, shift, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $position, $shift, $status]);

        $_SESSION['message'] = "Staff member added successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['update_staff'])) {
        $staff_id = $_POST['staff_id'];
        $position = $_POST['position'];
        $shift = $_POST['shift'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE canteen_staff SET position = ?, shift = ?, status = ? WHERE id = ?");
        $stmt->execute([$position, $shift, $status, $staff_id]);

        $_SESSION['message'] = "Staff member updated successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['delete_staff'])) {
        $staff_id = $_POST['staff_id'];

        $stmt = $pdo->prepare("DELETE FROM canteen_staff WHERE id = ?");
        $stmt->execute([$staff_id]);

        $_SESSION['message'] = "Staff member deleted successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['update_order_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE canteen_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        $_SESSION['message'] = "Order status updated successfully";
        header("Location: canteen_management.php");
        exit();
    }

    if (isset($_POST['update_payment_status'])) {
        $order_id = $_POST['order_id'];
        $payment_status = $_POST['payment_status'];

        $stmt = $pdo->prepare("UPDATE canteen_orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $order_id]);

        $_SESSION['message'] = "Payment status updated successfully";
        header("Location: canteen_management.php");
        exit();
    }
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'menu';

// Build base queries
$menu_query = "SELECT * FROM canteen_menu";
$orders_query = "
    SELECT co.*, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name,
           u.email as user_email
    FROM canteen_orders co
    JOIN users u ON co.user_id = u.id
";
$staff_query = "
    SELECT cs.*, 
           u.first_name, u.last_name, u.email
    FROM canteen_staff cs
    JOIN users u ON cs.user_id = u.id
";

// Apply filters if they exist
$menu_filters = [];
if (isset($_GET['menu_status_filter']) && $_GET['menu_status_filter'] != '') {
    $menu_filters[] = "status = '" . $_GET['menu_status_filter'] . "'";
}
if (isset($_GET['menu_category_filter']) && $_GET['menu_category_filter'] != '') {
    $menu_filters[] = "category = '" . $_GET['menu_category_filter'] . "'";
}
if (!empty($menu_filters)) {
    $menu_query .= " WHERE " . implode(" AND ", $menu_filters);
}
$menu_query .= " ORDER BY category, item_name";
$menu_items = $pdo->query($menu_query)->fetchAll();

$orders_filters = [];
if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
    $orders_filters[] = "co.status = '" . $_GET['order_status_filter'] . "'";
}
if (isset($_GET['payment_status_filter']) && $_GET['payment_status_filter'] != '') {
    $orders_filters[] = "co.payment_status = '" . $_GET['payment_status_filter'] . "'";
}
if (!empty($orders_filters)) {
    $orders_query .= " WHERE " . implode(" AND ", $orders_filters);
}
$orders_query .= " ORDER BY co.order_date DESC";
$orders = $pdo->query($orders_query)->fetchAll();

$staff_filters = [];
if (isset($_GET['staff_status_filter'])) {
    $staff_filters[] = "cs.status = '" . $_GET['staff_status_filter'] . "'";
}
if (isset($_GET['staff_position_filter'])) {
    $staff_filters[] = "cs.position LIKE '%" . $_GET['staff_position_filter'] . "%'";
}
if (!empty($staff_filters)) {
    $staff_query .= " WHERE " . implode(" AND ", $staff_filters);
}
$staff_query .= " ORDER BY cs.position, u.last_name";
$staff_members = $pdo->query($staff_query)->fetchAll();

// Get available staff (campus role users not already in canteen_staff)
$available_staff = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    LEFT JOIN canteen_staff cs ON u.id = cs.user_id
    WHERE u.role = 'campus' AND cs.id IS NULL
")->fetchAll();

// Get unique categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM canteen_menu ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get order details for view modal
if (isset($_GET['view_order_id'])) {
    $order_id = $_GET['view_order_id'];
    $order_details = $pdo->query("
        SELECT coi.*, cm.item_name, cm.description
        FROM canteen_order_items coi
        JOIN canteen_menu cm ON coi.menu_item_id = cm.id
        WHERE coi.order_id = $order_id
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canteen Management</title>
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

        .status-out_of_stock {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-discontinued {
            background-color: #f8d7da;
            color: #721c24;
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

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-refunded {
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

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .order-items-table th,
        .order-items-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .order-items-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Canteen Management</h1>
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
                        <li><a href="library_management.php">Library Management</a></li>
                        <li><a href="medical_management.php">Medical Center</a></li>
                        <li class="active"><a href="canteen_management.php">Canteen Management</a></li>
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
                        <button class="tab-btn <?= $current_tab == 'menu' ? 'active' : '' ?>"
                            onclick="openTab('menu')">Menu Items</button>
                        <button class="tab-btn <?= $current_tab == 'orders' ? 'active' : '' ?>"
                            onclick="openTab('orders')">Orders</button>
                        <button class="tab-btn <?= $current_tab == 'staff' ? 'active' : '' ?>"
                            onclick="openTab('staff')">Staff</button>
                        <button class="tab-btn <?= $current_tab == 'addMenuItem' ? 'active' : '' ?>"
                            onclick="openTab('addMenuItem')">Add Menu Item</button>
                        <button class="tab-btn <?= $current_tab == 'addStaff' ? 'active' : '' ?>"
                            onclick="openTab('addStaff')">Add Staff</button>
                    </div>

                    <div id="menu" class="tab-content <?= $current_tab == 'menu' ? 'active' : '' ?>">
                        <h2>Menu Items</h2>

                        <!-- Menu Filters -->
                        <div class="filter-container">
                            <form method="get" action="canteen_management.php">
                                <input type="hidden" name="tab" value="menu">
                                <div>
                                    <label for="menu_status_filter">Status:</label>
                                    <select name="menu_status_filter" id="menu_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="available" <?= isset($_GET['menu_status_filter']) && $_GET['menu_status_filter'] == 'available' ? 'selected' : '' ?>>Available
                                        </option>
                                        <option value="out_of_stock" <?= isset($_GET['menu_status_filter']) && $_GET['menu_status_filter'] == 'out_of_stock' ? 'selected' : '' ?>>Out of
                                            Stock</option>
                                        <option value="discontinued" <?= isset($_GET['menu_status_filter']) && $_GET['menu_status_filter'] == 'discontinued' ? 'selected' : '' ?>>
                                            Discontinued</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="menu_category_filter">Category:</label>
                                    <select name="menu_category_filter" id="menu_category_filter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>"
                                                <?= isset($_GET['menu_category_filter']) && $_GET['menu_category_filter'] == $category ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetMenuFilters()">Reset</button>
                            </form>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category']) ?></td>
                                        <td><?= htmlspecialchars(substr($item['description'], 0, 50)) . (strlen($item['description']) > 50 ? '...' : '') ?>
                                        </td>
                                        <td><?= number_format($item['price'], 2) ?></td>
                                        <td><?= $item['available_quantity'] ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $item['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showMenuItemModal(<?= $item['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteMenuItemModal(<?= $item['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="orders" class="tab-content <?= $current_tab == 'orders' ? 'active' : '' ?>">
                        <h2>Orders</h2>

                        <!-- Order Filters -->
                        <div class="filter-container">
                            <form method="get" action="canteen_management.php">
                                <input type="hidden" name="tab" value="orders">
                                <div>
                                    <label for="order_status_filter">Order Status:</label>
                                    <select name="order_status_filter" id="order_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?= isset($_GET['order_status_filter']) && $_GET['order_status_filter'] == 'pending' ? 'selected' : '' ?>>Pending
                                        </option>
                                        <option value="preparing" <?= isset($_GET['order_status_filter']) && $_GET['order_status_filter'] == 'preparing' ? 'selected' : '' ?>>Preparing
                                        </option>
                                        <option value="ready" <?= isset($_GET['order_status_filter']) && $_GET['order_status_filter'] == 'ready' ? 'selected' : '' ?>>Ready</option>
                                        <option value="delivered" <?= isset($_GET['order_status_filter']) && $_GET['order_status_filter'] == 'delivered' ? 'selected' : '' ?>>Delivered
                                        </option>
                                        <option value="cancelled" <?= isset($_GET['order_status_filter']) && $_GET['order_status_filter'] == 'cancelled' ? 'selected' : '' ?>>Cancelled
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_status_filter">Payment Status:</label>
                                    <select name="payment_status_filter" id="payment_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?= isset($_GET['payment_status_filter']) && $_GET['payment_status_filter'] == 'pending' ? 'selected' : '' ?>>Pending
                                        </option>
                                        <option value="paid" <?= isset($_GET['payment_status_filter']) && $_GET['payment_status_filter'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="refunded" <?= isset($_GET['payment_status_filter']) && $_GET['payment_status_filter'] == 'refunded' ? 'selected' : '' ?>>Refunded
                                        </option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetOrderFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Order Status</th>
                                    <th>Payment Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['user_name']) ?></td>
                                        <td><?= date('M j, Y H:i', strtotime($order['order_date'])) ?></td>
                                        <td><?= number_format($order['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $order['status'] ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $order['payment_status'] ?>">
                                                <?= ucfirst($order['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button
                                                    onclick="location.href='canteen_management.php?tab=orders&view_order_id=<?= $order['id'] ?>'"
                                                    class="logout-btn">View</button>
                                                <button onclick="showOrderStatusModal(<?= $order['id'] ?>)"
                                                    class="logout-btn">Update Status</button>
                                                <button onclick="showPaymentStatusModal(<?= $order['id'] ?>)"
                                                    class="logout-btn">Payment</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (isset($_GET['view_order_id']) && isset($order_details)): ?>
                            <!-- Order Details Modal -->
                            <div class="modal" id="orderDetailsModal" style="display: block;">
                                <div class="modal-content">
                                    <span class="close" onclick="hideModal('orderDetailsModal')">&times;</span>
                                    <h2>Order #<?= $_GET['view_order_id'] ?> Details</h2>

                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Description</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_details as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                                    <td><?= $item['quantity'] ?></td>
                                                    <td><?= number_format($item['price_at_order'], 2) ?></td>
                                                    <td><?= number_format($item['quantity'] * $item['price_at_order'], 2) ?>
                                                    </td>
                                                </tr>

                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <div style="margin-top: 20px; text-align: right;">
                                        <strong>Total Amount: <?= number_format(array_reduce($order_details, function ($carry, $item) {
                                            return $carry + ($item['quantity'] * $item['price_at_order']);
                                        }, 0), 2) ?></strong>
                                    </div>

                                    <div class="action-buttons" style="margin-top: 20px; justify-content: center;">
                                        <button onclick="hideModal('orderDetailsModal')" class="logout-btn">Close</button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="staff" class="tab-content">
                        <h2>Canteen Staff</h2>

                        <!-- Staff Filters -->
                        <div class="filter-container">
                            <form method="get" action="canteen_management.php">
                                <input type="hidden" name="tab" value="staff">
                                <div>
                                    <label for="staff_status_filter">Status:</label>
                                    <select name="staff_status_filter" id="staff_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['staff_status_filter']) && $_GET['staff_status_filter'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= isset($_GET['staff_status_filter']) && $_GET['staff_status_filter'] == 'inactive' ? 'selected' : '' ?>>Inactive
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="staff_position_filter">Position:</label>
                                    <input type="text" name="staff_position_filter" id="staff_position_filter"
                                        value="<?= isset($_GET['staff_position_filter']) ? htmlspecialchars($_GET['staff_position_filter']) : '' ?>"
                                        placeholder="Filter by position">
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetStaffFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Position</th>
                                    <th>Shift</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_members as $staff): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></td>
                                        <td><?= htmlspecialchars($staff['email']) ?></td>
                                        <td><?= htmlspecialchars($staff['position']) ?></td>
                                        <td><?= htmlspecialchars($staff['shift']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $staff['status'] ?>">
                                                <?= ucfirst($staff['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showStaffModal(<?= $staff['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteStaffModal(<?= $staff['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="addMenuItem" class="tab-content">
                        <h2>Add New Menu Item</h2>
                        <form method="post" action="canteen_management.php">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" name="item_name" id="item_name" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" name="category" id="category" required>
                            </div>
                            <div class="form-group">
                                <label for="price">Price</label>
                                <input type="number" name="price" id="price" min="0" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="available_quantity">Available Quantity</label>
                                <input type="number" name="available_quantity" id="available_quantity" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="available">Available</option>
                                    <option value="out_of_stock">Out of Stock</option>
                                    <option value="discontinued">Discontinued</option>
                                </select>
                            </div>
                            <button type="submit" name="add_menu_item" class="logout-btn">Add Menu Item</button>
                        </form>
                    </div>

                    <div id="addStaff" class="tab-content">
                        <h2>Add Canteen Staff</h2>
                        <form method="post" action="canteen_management.php">
                            <div class="form-group">
                                <label for="user_id">Staff Member</label>
                                <select name="user_id" id="user_id" required>
                                    <?php foreach ($available_staff as $staff): ?>
                                        <option value="<?= $staff['id'] ?>">
                                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . $staff['email'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="position">Position</label>
                                <input type="text" name="position" id="position" required>
                            </div>
                            <div class="form-group">
                                <label for="shift">Shift</label>
                                <select name="shift" id="shift" required>
                                    <option value="morning">Morning</option>
                                    <option value="afternoon">Afternoon</option>
                                    <option value="evening">Evening</option>
                                    <option value="full-day">Full Day</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <button type="submit" name="add_staff" class="logout-btn">Add Staff</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Menu Item Management Modal -->
    <div class="modal" id="menuItemModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('menuItemModal')">&times;</span>
            <h2>Edit Menu Item</h2>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="item_id" id="modal_item_id">

                <div class="form-group">
                    <label for="modal_item_name">Item Name</label>
                    <input type="text" name="item_name" id="modal_item_name" required>
                </div>

                <div class="form-group">
                    <label for="modal_item_description">Description</label>
                    <textarea name="description" id="modal_item_description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="modal_item_category">Category</label>
                    <input type="text" name="category" id="modal_item_category" required>
                </div>

                <div class="form-group">
                    <label for="modal_item_price">Price</label>
                    <input type="number" name="price" id="modal_item_price" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="modal_item_quantity">Available Quantity</label>
                    <input type="number" name="available_quantity" id="modal_item_quantity" min="0" required>
                </div>

                <div class="form-group">
                    <label for="modal_item_status">Status</label>
                    <select name="status" id="modal_item_status" required>
                        <option value="available">Available</option>
                        <option value="out_of_stock">Out of Stock</option>
                        <option value="discontinued">Discontinued</option>
                    </select>
                </div>

                <button type="submit" name="update_menu_item" class="logout-btn">Update Menu Item</button>
            </form>
        </div>
    </div>

    <!-- Delete Menu Item Confirmation Modal -->
    <div class="modal" id="deleteMenuItemModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteMenuItemModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this menu item?</p>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="item_id" id="delete_menu_item_id">
                <button type="submit" name="delete_menu_item" class="logout-btn">Delete</button>
                <button type="button" onclick="hideModal('deleteMenuItemModal')"
                    class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Order Status Management Modal -->
    <div class="modal" id="orderStatusModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('orderStatusModal')">&times;</span>
            <h2>Update Order Status</h2>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="order_id" id="modal_order_id">

                <div class="form-group">
                    <label for="modal_order_status">Status</label>
                    <select name="status" id="modal_order_status" required>
                        <option value="pending">Pending</option>
                        <option value="preparing">Preparing</option>
                        <option value="ready">Ready</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <button type="submit" name="update_order_status" class="logout-btn">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Payment Status Management Modal -->
    <div class="modal" id="paymentStatusModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('paymentStatusModal')">&times;</span>
            <h2>Update Payment Status</h2>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="order_id" id="modal_payment_order_id">

                <div class="form-group">
                    <label for="modal_payment_status">Status</label>
                    <select name="payment_status" id="modal_payment_status" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>

                <button type="submit" name="update_payment_status" class="logout-btn">Update Payment Status</button>
            </form>
        </div>
    </div>

    <!-- Staff Management Modal -->
    <div class="modal" id="staffModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('staffModal')">&times;</span>
            <h2>Edit Staff Member</h2>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="staff_id" id="modal_staff_id">

                <div class="form-group">
                    <label for="modal_staff_position">Position</label>
                    <input type="text" name="position" id="modal_staff_position" required>
                </div>

                <div class="form-group">
                    <label for="modal_staff_shift">Shift</label>
                    <select name="shift" id="modal_staff_shift" required>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                        <option value="evening">Evening</option>
                        <option value="full-day">Full Day</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_staff_status">Status</label>
                    <select name="status" id="modal_staff_status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" name="update_staff" class="logout-btn">Update Staff</button>
            </form>
        </div>
    </div>

    <!-- Delete Staff Confirmation Modal -->
    <div class="modal" id="deleteStaffModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteStaffModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this staff member?</p>
            <form method="post" action="canteen_management.php">
                <input type="hidden" name="staff_id" id="delete_staff_id">
                <button type="submit" name="delete_staff" class="logout-btn">Delete</button>
                <button type="button" onclick="hideModal('deleteStaffModal')" class="logout-btn-warning">Cancel</button>
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
            
            // Update URL with tab parameter
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showMenuItemModal(itemId) {
            // Find the item in our existing data
            const menuItems = <?= json_encode($menu_items) ?>;
            const item = menuItems.find(i => i.id == itemId);

            if (item) {
                document.getElementById('modal_item_id').value = item.id;
                document.getElementById('modal_item_name').value = item.item_name;
                document.getElementById('modal_item_description').value = item.description;
                document.getElementById('modal_item_category').value = item.category;
                document.getElementById('modal_item_price').value = item.price;
                document.getElementById('modal_item_quantity').value = item.available_quantity;
                document.getElementById('modal_item_status').value = item.status;
                showModal('menuItemModal');
            }
        }

        function showDeleteMenuItemModal(itemId) {
            document.getElementById('delete_menu_item_id').value = itemId;
            showModal('deleteMenuItemModal');
        }

        function showOrderStatusModal(orderId) {
            // Find the order in our existing data
            const orders = <?= json_encode($orders) ?>;
            const order = orders.find(o => o.id == orderId);

            if (order) {
                document.getElementById('modal_order_id').value = order.id;
                document.getElementById('modal_order_status').value = order.status;
                showModal('orderStatusModal');
            }
        }

        function showPaymentStatusModal(orderId) {
            // Find the order in our existing data
            const orders = <?= json_encode($orders) ?>;
            const order = orders.find(o => o.id == orderId);

            if (order) {
                document.getElementById('modal_payment_order_id').value = order.id;
                document.getElementById('modal_payment_status').value = order.payment_status;
                showModal('paymentStatusModal');
            }
        }

        function showStaffModal(staffId) {
            // Find the staff in our existing data
            const staffMembers = <?= json_encode($staff_members) ?>;
            const staff = staffMembers.find(s => s.id == staffId);

            if (staff) {
                document.getElementById('modal_staff_id').value = staff.id;
                document.getElementById('modal_staff_position').value = staff.position;
                document.getElementById('modal_staff_shift').value = staff.shift;
                document.getElementById('modal_staff_status').value = staff.status;
                showModal('staffModal');
            }
        }

        function showDeleteStaffModal(staffId) {
            document.getElementById('delete_staff_id').value = staffId;
            showModal('deleteStaffModal');
        }

        // Reset filter functions
        function resetMenuFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('menu_status_filter');
            url.searchParams.delete('menu_category_filter');
            url.searchParams.set('tab', 'menu');
            window.location.href = url.toString();
        }

        function resetOrderFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('order_status_filter');
            url.searchParams.delete('payment_status_filter');
            url.searchParams.set('tab', 'orders');
            window.location.href = url.toString();
        }

        function resetStaffFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('staff_status_filter');
            url.searchParams.delete('staff_position_filter');
            url.searchParams.set('tab', 'staff');
            window.location.href = url.toString();
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            window.onclick = function(event) {
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
                // Find the tab button and click it to activate
                const tabButtons = document.getElementsByClassName('tab-btn');
                for (let i = 0; i < tabButtons.length; i++) {
                    if (tabButtons[i].getAttribute('onclick').includes(tabParam)) {
                        tabButtons[i].click();
                        break;
                    }
                }
            }
        });
    </script>
</body>

</html>