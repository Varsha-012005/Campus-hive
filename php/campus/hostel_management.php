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
    if (isset($_POST['add_room'])) {
        $room_number = $_POST['room_number'];
        $building = $_POST['building'];
        $capacity = $_POST['capacity'];
        $type = $_POST['type'];
        $status = 'available';

        $stmt = $pdo->prepare("INSERT INTO hostel_rooms (room_number, building, capacity, type, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$room_number, $building, $capacity, $type, $status]);

        $_SESSION['message'] = "Room added successfully";
        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['update_room'])) {
        $room_id = $_POST['room_id'];
        $room_number = $_POST['room_number'];
        $building = $_POST['building'];
        $capacity = $_POST['capacity'];
        $type = $_POST['type'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE hostel_rooms SET room_number = ?, building = ?, capacity = ?, type = ?, status = ? WHERE id = ?");
        $stmt->execute([$room_number, $building, $capacity, $type, $status, $room_id]);

        $_SESSION['message'] = "Room updated successfully";
        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['delete_room'])) {
        $room_id = $_POST['room_id'];

        // Check if room has any allocations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_allocations WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete room with active allocations";
        } else {
            $stmt = $pdo->prepare("DELETE FROM hostel_rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            $_SESSION['message'] = "Room deleted successfully";
        }
        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['allocate_room'])) {
        $room_id = $_POST['room_id'];
        $user_id = $_POST['user_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = 'active';

        // Check if room is available
        $check = $pdo->prepare("SELECT status FROM hostel_rooms WHERE id = ?");
        $check->execute([$room_id]);
        $room_status = $check->fetchColumn();

        if ($room_status != 'available') {
            $_SESSION['error'] = "Selected room is not available";
            header("Location: hostel_management.php");
            exit();
        }

        // Check if user already has an active allocation
        $check = $pdo->prepare("SELECT COUNT(*) FROM hostel_allocations WHERE user_id = ? AND status = 'active'");
        $check->execute([$user_id]);
        $count = $check->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "User already has an active room allocation";
            header("Location: hostel_management.php");
            exit();
        }

        // Allocate room
        $stmt = $pdo->prepare("INSERT INTO hostel_allocations (room_id, user_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $user_id, $start_date, $end_date, $status]);

        // Update room status
        $pdo->prepare("UPDATE hostel_rooms SET status = 'occupied' WHERE id = ?")->execute([$room_id]);

        $_SESSION['message'] = "Room allocated successfully";
        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['update_allocation'])) {
        $allocation_id = $_POST['allocation_id'];
        $action = $_POST['action'];

        if ($action == 'terminate') {
            $stmt = $pdo->prepare("UPDATE hostel_allocations SET status = 'terminated', end_date = CURDATE() WHERE id = ?");
            $stmt->execute([$allocation_id]);

            // Get room ID to update status
            $room_id = $pdo->query("SELECT room_id FROM hostel_allocations WHERE id = $allocation_id")->fetchColumn();
            $pdo->query("UPDATE hostel_rooms SET status = 'available' WHERE id = $room_id");

            $_SESSION['message'] = "Allocation terminated successfully";
        } elseif ($action == 'extend') {
            $new_end_date = $_POST['new_end_date'];
            $stmt = $pdo->prepare("UPDATE hostel_allocations SET end_date = ? WHERE id = ?");
            $stmt->execute([$new_end_date, $allocation_id]);

            $_SESSION['message'] = "Allocation extended successfully";
        }

        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['delete_allocation'])) {
        $allocation_id = $_POST['allocation_id'];

        // Get room ID before deletion
        $room_id = $pdo->query("SELECT room_id FROM hostel_allocations WHERE id = $allocation_id")->fetchColumn();

        // Delete the allocation
        $stmt = $pdo->prepare("DELETE FROM hostel_allocations WHERE id = ?");
        $stmt->execute([$allocation_id]);

        // Update room status to available
        $pdo->prepare("UPDATE hostel_rooms SET status = 'available' WHERE id = ?")->execute([$room_id]);

        $_SESSION['message'] = "Allocation deleted successfully";
        header("Location: hostel_management.php");
        exit();
    }

    if (isset($_POST['process_request'])) {
        $allocation_id = $_POST['request_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';

        // Get the request details
        $request = $pdo->prepare("SELECT * FROM hostel_allocations WHERE id = ?");
        $request->execute([$allocation_id]);
        $request = $request->fetch();

        if (!$request) {
            $_SESSION['error'] = "Request not found";
            header("Location: hostel_management.php");
            exit();
        }

        if ($action == 'approve') {
            // Check if room is still available
            $room_status = $pdo->prepare("SELECT status FROM hostel_rooms WHERE id = ?");
            $room_status->execute([$request['room_id']]);
            $room_status = $room_status->fetchColumn();

            if ($room_status != 'available') {
                $_SESSION['error'] = "Room is no longer available";
                header("Location: hostel_management.php");
                exit();
            }

            // Check if user already has an active allocation
            $active_check = $pdo->prepare("SELECT COUNT(*) FROM hostel_allocations 
                                      WHERE user_id = ? AND status = 'active'");
            $active_check->execute([$request['user_id']]);
            $count = $active_check->fetchColumn();

            if ($count > 0) {
                $_SESSION['error'] = "User already has an active allocation";
                header("Location: hostel_management.php");
                exit();
            }

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Update allocation status
                $stmt = $pdo->prepare("UPDATE hostel_allocations 
                                  SET request_status = 'approved', 
                                      status = 'active',
                                      processed_by = ?,
                                      processed_at = NOW(),
                                      notes = ?
                                  WHERE id = ?");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $notes,
                    $allocation_id
                ]);

                // Update room status
                $pdo->prepare("UPDATE hostel_rooms 
                          SET status = 'occupied' 
                          WHERE id = ?")
                    ->execute([$request['room_id']]);

                $pdo->commit();
                $_SESSION['message'] = "Request approved and room allocated successfully";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error processing request: " . $e->getMessage();
            }

        } elseif ($action == 'decline') {
            try {
                $stmt = $pdo->prepare("UPDATE hostel_allocations 
                                  SET request_status = 'rejected', 
                                      processed_by = ?,
                                      processed_at = NOW(),
                                      notes = ? 
                                  WHERE id = ?");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $notes,
                    $allocation_id
                ]);

                $_SESSION['message'] = "Request declined successfully";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error declining request: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Invalid action";
        }

        header("Location: hostel_management.php");
        exit();
    }
}

// Build base queries
$room_query = "SELECT * FROM hostel_rooms";
$allocation_query = "
    SELECT ha.*, hr.room_number, hr.building, hr.type, 
           u.first_name, u.last_name, u.email
    FROM hostel_allocations ha
    JOIN hostel_rooms hr ON ha.room_id = hr.id
    JOIN users u ON ha.user_id = u.id
";

// Requests query
$requests_query = "
    SELECT ha.*, 
           u.first_name, u.last_name, u.email,
           hr.room_number, hr.building, hr.type,
           admin.first_name as processed_by_name, 
           admin.last_name as processed_by_last_name
    FROM hostel_allocations ha
    JOIN users u ON ha.user_id = u.id
    JOIN hostel_rooms hr ON ha.room_id = hr.id
    LEFT JOIN users admin ON ha.processed_by = admin.id
    WHERE ha.request_status IS NOT NULL
";

// Apply filters if they exist
$room_filters = [];
$params = [];

if (isset($_GET['room_status_filter']) && !empty($_GET['room_status_filter'])) {
    $room_filters[] = "status = :status";
    $params[':status'] = $_GET['room_status_filter'];
}

if (isset($_GET['room_type_filter']) && !empty($_GET['room_type_filter'])) {
    $room_filters[] = "type = :type";
    $params[':type'] = $_GET['room_type_filter'];
}

if (isset($_GET['building_filter']) && !empty($_GET['building_filter'])) {
    $room_filters[] = "building LIKE :building";
    $params[':building'] = '%' . $_GET['building_filter'] . '%';
}

if (!empty($room_filters)) {
    $room_query .= " WHERE " . implode(" AND ", $room_filters);
}
$room_query .= " ORDER BY building, room_number";

// Prepare and execute the query with parameters
$stmt = $pdo->prepare($room_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$rooms = $stmt->fetchAll();

$allocation_filters = [];
if (isset($_GET['allocation_status_filter'])) {
    $allocation_filters[] = "ha.status = '" . $_GET['allocation_status_filter'] . "'";
}
if (!empty($allocation_filters)) {
    $allocation_query .= " WHERE " . implode(" AND ", $allocation_filters);
}
$allocation_query .= " ORDER BY ha.status, ha.end_date DESC";
$allocations = $pdo->query($allocation_query)->fetchAll();


// Apply filters to requests
// Apply filters to requests
$request_filters = [];
if (isset($_GET['request_status_filter']) && $_GET['request_status_filter'] !== '') {
    $request_filters[] = "ha.request_status = '" . $_GET['request_status_filter'] . "'";
}

if (!empty($request_filters)) {
    $requests_query .= " AND " . implode(" AND ", $request_filters);
}
$requests_query .= " ORDER BY ha.created_at DESC";
$requests = $pdo->query($requests_query)->fetchAll();

// Get all users (for allocation form)
$users = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.role
    FROM users u
    LEFT JOIN hostel_allocations ha ON u.id = ha.user_id AND ha.status = 'active'
    WHERE ha.id IS NULL
    ORDER BY u.last_name, u.first_name
")->fetchAll();

// Get available rooms
$available_rooms = $pdo->query("SELECT * FROM hostel_rooms WHERE status = 'available'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Management</title>
    <link href="/university-system/css/campus.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-active {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-terminated {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-available {
            background-color: #d4edda;
            color: #155724;
        }

        .status-occupied {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-maintenance {
            background-color: #fff3cd;
            color: #856404;
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

        .view-room-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px auto;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .view-room-card h3 {
            margin-top: 0;
            color: #5d78ff;
        }

        .view-room-card p {
            margin: 10px 0;
        }

        .view-room-actions {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
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

        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .badge-primary {
            color: #fff;
            background-color: #007bff;
        }

        .badge-secondary {
            color: #fff;
            background-color: #6c757d;
        }

        .badge-success {
            color: #fff;
            background-color: #28a745;
        }

        .badge-danger {
            color: #fff;
            background-color: #dc3545;
        }

        .badge-warning {
            color: #212529;
            background-color: #ffc107;
        }

        .badge-info {
            color: #fff;
            background-color: #17a2b8;
        }

        .badge-light {
            color: #212529;
            background-color: #f8f9fa;
        }

        .badge-dark {
            color: #fff;
            background-color: #343a40;
        }

        .user-role-badge {
            font-size: 0.7rem;
            margin-left: 5px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Hostel Management</h1>
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
                        <li class="active"><a href="hostel_management.php">Hostel Management</a></li>
                        <li><a href="transport_management.php">Transport Management</a></li>
                        <li><a href="library_management.php">Library Management</a></li>
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
                        <button class="tab-btn active" onclick="openTab('rooms')">Rooms</button>
                        <button class="tab-btn" onclick="openTab('allocations')">Allocations</button>
                        <button class="tab-btn" onclick="openTab('requests')">Requests</button>
                        <button class="tab-btn" onclick="openTab('addRoom')">Add Room</button>
                        <button class="tab-btn" onclick="openTab('allocate')">Allocate Room</button>
                    </div>

                    <div id="rooms" class="tab-content active">
                        <h2>Hostel Rooms</h2>

                        <!-- Room Filters -->
                        <div class="filter-container">
                            <form method="get" action="hostel_management.php">
                                <input type="hidden" name="tab" value="rooms">
                                <div>
                                    <label for="room_status_filter">Status:</label>
                                    <select name="room_status_filter" id="room_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="available" <?= isset($_GET['room_status_filter']) && $_GET['room_status_filter'] == 'available' ? 'selected' : '' ?>>Available
                                        </option>
                                        <option value="occupied" <?= isset($_GET['room_status_filter']) && $_GET['room_status_filter'] == 'occupied' ? 'selected' : '' ?>>Occupied
                                        </option>
                                        <option value="maintenance" <?= isset($_GET['room_status_filter']) && $_GET['room_status_filter'] == 'maintenance' ? 'selected' : '' ?>>Maintenance
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label for="room_type_filter">Type:</label>
                                    <select name="room_type_filter" id="room_type_filter">
                                        <option value="">All Types</option>
                                        <option value="single" <?= isset($_GET['room_type_filter']) && $_GET['room_type_filter'] == 'single' ? 'selected' : '' ?>>Single</option>
                                        <option value="double" <?= isset($_GET['room_type_filter']) && $_GET['room_type_filter'] == 'double' ? 'selected' : '' ?>>Double</option>
                                        <option value="dormitory" <?= isset($_GET['room_type_filter']) && $_GET['room_type_filter'] == 'dormitory' ? 'selected' : '' ?>>Dormitory
                                        </option>
                                        <option value="suite" <?= isset($_GET['room_type_filter']) && $_GET['room_type_filter'] == 'suite' ? 'selected' : '' ?>>Suite</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="building_filter">Building:</label>
                                    <input type="text" name="building_filter" id="building_filter"
                                        value="<?= isset($_GET['building_filter']) ? htmlspecialchars($_GET['building_filter']) : '' ?>">
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetRoomFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Building</th>
                                    <th>Room Number</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($room['building']) ?></td>
                                        <td><?= htmlspecialchars($room['room_number']) ?></td>
                                        <td><?= ucfirst($room['type']) ?></td>
                                        <td><?= $room['capacity'] ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $room['status'] ?>">
                                                <?= ucfirst($room['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showViewRoomModal(<?= $room['id'] ?>)"
                                                    class="logout-btn">View</button>
                                                <button onclick="showEditRoomModal(<?= $room['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteRoomModal(<?= $room['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="allocations" class="tab-content">
                        <h2>Current Allocations</h2>

                        <!-- Allocation Filters -->
                        <div class="filter-container">
                            <form method="get" action="hostel_management.php">
                                <input type="hidden" name="tab" value="allocations">
                                <div>
                                    <label for="allocation_status_filter">Status:</label>
                                    <select name="allocation_status_filter" id="allocation_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['allocation_status_filter']) && $_GET['allocation_status_filter'] == 'active' ? 'selected' : '' ?>>Active
                                        </option>
                                        <option value="terminated" <?= isset($_GET['allocation_status_filter']) && $_GET['allocation_status_filter'] == 'terminated' ? 'selected' : '' ?>>
                                            Terminated</option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn"
                                    onclick="resetAllocationFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Room</th>
                                    <th>Building</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($alloc['first_name'] . ' ' . $alloc['last_name']) ?>
                                            <span
                                                class="badge badge-secondary user-role-badge"><?= $alloc['role'] ?? 'user' ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($alloc['room_number']) ?></td>
                                        <td><?= htmlspecialchars($alloc['building']) ?></td>
                                        <td><?= htmlspecialchars($alloc['type']) ?></td>
                                        <td><?= date('M j, Y', strtotime($alloc['start_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($alloc['end_date'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $alloc['status'] ?>">
                                                <?= ucfirst($alloc['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($alloc['status'] == 'active'): ?>
                                                    <button onclick="showAllocationModal(<?= $alloc['id'] ?>)"
                                                        class="logout-btn">Manage</button>
                                                <?php endif; ?>
                                                <button onclick="showDeleteAllocationModal(<?= $alloc['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="requests" class="tab-content">
                        <h2>Room Requests</h2>

                        <!-- Request Filters -->
                        <div class="filter-container">
                            <form method="get" action="hostel_management.php">
                                <input type="hidden" name="tab" value="requests">
                                <div>
                                    <label for="request_status_filter">Status:</label>
                                    <select name="request_status_filter" id="request_status_filter">
                                        <option value="" <?= !isset($_GET['request_status_filter']) ? 'selected' : '' ?>>
                                            All Requests</option>
                                        <option value="pending" <?= isset($_GET['request_status_filter']) && $_GET['request_status_filter'] == 'pending' ? 'selected' : '' ?>>Pending
                                        </option>
                                        <option value="approved" <?= isset($_GET['request_status_filter']) && $_GET['request_status_filter'] == 'approved' ? 'selected' : '' ?>>Approved
                                        </option>
                                        <option value="rejected" <?= isset($_GET['request_status_filter']) && $_GET['request_status_filter'] == 'rejected' ? 'selected' : '' ?>>Rejected
                                        </option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetRequestFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Room</th>
                                    <th>Building</th>
                                    <th>Request Date</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>User Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Processed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($request['room_number']) ?></td>
                                        <td><?= htmlspecialchars($request['building']) ?></td>
                                        <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($request['start_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($request['end_date'])) ?></td>
                                        <td><?= ucfirst($request['user_type'] ?? 'student') ?></td>
                                        <td><?= htmlspecialchars(substr($request['purpose'], 0, 30)) . (strlen($request['purpose']) > 30 ? '...' : '') ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $request['request_status'] ?>">
                                                <?= ucfirst($request['request_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($request['processed_by_name'])): ?>
                                                <?= htmlspecialchars($request['processed_by_name'] . ' ' . $request['processed_by_last_name']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not processed yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($request['request_status'] == 'pending'): ?>
                                                    <button onclick="showRequestModal(<?= $request['id'] ?>)"
                                                        class="logout-btn">Process</button>
                                                <?php else: ?>
                                                    <span class="text-muted">Completed</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="addRoom" class="tab-content">
                        <h2>Add New Room</h2>
                        <form method="post" action="hostel_management.php">
                            <div class="form-group">
                                <label for="building">Building</label>
                                <input type="text" name="building" id="building" required>
                            </div>
                            <div class="form-group">
                                <label for="room_number">Room Number</label>
                                <input type="text" name="room_number" id="room_number" required>
                            </div>
                            <div class="form-group">
                                <label for="capacity">Capacity</label>
                                <input type="number" name="capacity" id="capacity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="type">Room Type</label>
                                <select name="type" id="type" required>
                                    <option value="single">Single</option>
                                    <option value="double">Double</option>
                                    <option value="dormitory">Dormitory</option>
                                    <option value="suite">Suite</option>
                                </select>
                            </div>
                            <button type="submit" name="add_room" class="logout-btn">Add Room</button>
                        </form>
                    </div>

                    <div id="allocate" class="tab-content">
                        <h2>Allocate Room to User</h2>
                        <form method="post" action="hostel_management.php">
                            <div class="form-group">
                                <label for="user_id">User</label>
                                <select name="user_id" id="user_id" required>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                                            <span class="badge badge-secondary"><?= $user['role'] ?></span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="room_id">Available Room</label>
                                <select name="room_id" id="room_id" required>
                                    <?php foreach ($available_rooms as $room): ?>
                                        <option value="<?= $room['id'] ?>">
                                            <?= htmlspecialchars($room['building'] . ' - Room ' . $room['room_number'] . ' (' . $room['type'] . ', Capacity: ' . $room['capacity'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" required>
                            </div>
                            <button type="submit" name="allocate_room" class="logout-btn">Allocate Room</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Room View Modal -->
    <div class="modal" id="viewRoomModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('viewRoomModal')">&times;</span>
            <div class="view-room-card" id="viewRoomContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="view-room-actions">
                <button class="logout-btn" onclick="hideModal('viewRoomModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Room Edit Modal -->
    <div class="modal" id="editRoomModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('editRoomModal')">&times;</span>
            <h2>Edit Room</h2>
            <form method="post" action="hostel_management.php">
                <input type="hidden" name="room_id" id="edit_room_id">

                <div class="form-group">
                    <label for="edit_building">Building</label>
                    <input type="text" name="building" id="edit_building" required>
                </div>
                <div class="form-group">
                    <label for="edit_room_number">Room Number</label>
                    <input type="text" name="room_number" id="edit_room_number" required>
                </div>
                <div class="form-group">
                    <label for="edit_capacity">Capacity</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1" required>
                </div>
                <div class="form-group">
                    <label for="edit_type">Room Type</label>
                    <select name="type" id="edit_type" required>
                        <option value="single">Single</option>
                        <option value="double">Double</option>
                        <option value="dormitory">Dormitory</option>
                        <option value="suite">Suite</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <button type="submit" name="update_room" class="logout-btn">Update Room</button>
            </form>
        </div>
    </div>

    <!-- Delete Room Confirmation Modal -->
    <div class="modal" id="deleteRoomModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteRoomModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this room?</p>
            <form method="post" action="hostel_management.php">
                <input type="hidden" name="room_id" id="delete_room_id">
                <div class="form-group">
                    <button type="submit" name="delete_room" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteRoomModal')"
                        class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Allocation Management Modal -->
    <div class="modal" id="allocationModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('allocationModal')">&times;</span>
            <h2>Manage Allocation</h2>
            <form method="post" action="hostel_management.php">
                <input type="hidden" name="allocation_id" id="modal_allocation_id">

                <div class="form-group">
                    <label>Action</label>
                    <select name="action" id="action" onchange="toggleEndDate()" required>
                        <option value="terminate">Terminate Allocation</option>
                        <option value="extend">Extend Allocation</option>
                    </select>
                </div>

                <div class="form-group" id="endDateGroup" style="display: none;">
                    <label for="new_end_date">New End Date</label>
                    <input type="date" name="new_end_date" id="new_end_date">
                </div>

                <button type="submit" name="update_allocation" class="logout-btn">Submit</button>
            </form>
        </div>
    </div>

    <!-- Delete Allocation Confirmation Modal -->
    <div class="modal" id="deleteAllocationModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteAllocationModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this allocation?</p>
            <form method="post" action="hostel_management.php">
                <input type="hidden" name="allocation_id" id="delete_allocation_id">
                <div class="form-group">
                    <button type="submit" name="delete_allocation" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteAllocationModal')"
                        class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Processing Modal -->
    <div class="modal" id="requestModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('requestModal')">&times;</span>
            <h2>Process Room Request</h2>
            <form method="post" action="hostel_management.php">
                <input type="hidden" name="request_id" id="modal_request_id">

                <div class="form-group">
                    <label>Action</label>
                    <select name="action" id="request_action" required>
                        <option value="approve">Approve</option>
                        <option value="decline">Decline</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="request_notes">Notes (Optional)</label>
                    <textarea name="notes" id="request_notes" rows="3"></textarea>
                </div>

                <button type="submit" name="process_request" class="logout-btn">Submit</button>
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
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showViewRoomModal(roomId) {
            // Find the room in our existing data
            const rooms = <?= json_encode($rooms) ?>;
            const room = rooms.find(r => r.id == roomId);

            if (room) {
                const viewContent = document.getElementById('viewRoomContent');
                viewContent.innerHTML = `
                    <h3>${room.building} - Room ${room.room_number}</h3>
                    <p><strong>Type:</strong> ${room.type}</p>
                    <p><strong>Capacity:</strong> ${room.capacity}</p>
                    <p><strong>Status:</strong> <span class="status-badge status-${room.status}">${room.status.charAt(0).toUpperCase() + room.status.slice(1)}</span></p>
                    ${room.notes ? `<p><strong>Notes:</strong> ${room.notes}</p>` : ''}
                `;
                showModal('viewRoomModal');
            }
        }

        function showEditRoomModal(roomId) {
            // Find the room in our existing data
            const rooms = <?= json_encode($rooms) ?>;
            const room = rooms.find(r => r.id == roomId);

            if (room) {
                document.getElementById('edit_room_id').value = room.id;
                document.getElementById('edit_building').value = room.building;
                document.getElementById('edit_room_number').value = room.room_number;
                document.getElementById('edit_capacity').value = room.capacity;
                document.getElementById('edit_type').value = room.type;
                document.getElementById('edit_status').value = room.status;
                showModal('editRoomModal');
            }
        }

        function showDeleteRoomModal(roomId) {
            document.getElementById('delete_room_id').value = roomId;
            showModal('deleteRoomModal');
        }

        function showAllocationModal(allocationId) {
            // Find the allocation in our existing data
            const allocations = <?= json_encode($allocations) ?>;
            const alloc = allocations.find(a => a.id == allocationId);

            if (alloc) {
                document.getElementById('modal_allocation_id').value = alloc.id;
                // Set default end date to current end date
                const endDate = new Date(alloc.end_date);
                document.getElementById('new_end_date').value = endDate.toISOString().split('T')[0];
                showModal('allocationModal');
            }
        }

        function showDeleteAllocationModal(allocationId) {
            document.getElementById('delete_allocation_id').value = allocationId;
            showModal('deleteAllocationModal');
        }

        function showRequestModal(requestId) {
            document.getElementById('modal_request_id').value = requestId;
            showModal('requestModal');
        }

        function toggleEndDate() {
            const action = document.getElementById('action').value;
            const endDateGroup = document.getElementById('endDateGroup');

            if (action === 'extend') {
                endDateGroup.style.display = 'block';
            } else {
                endDateGroup.style.display = 'none';
            }
        }

        // Reset filter functions
        function resetRoomFilters() {
            window.location.href = 'hostel_management.php?tab=rooms';
        }

        function resetAllocationFilters() {
            window.location.href = 'hostel_management.php?tab=allocations';
        }

        function resetRequestFilters() {
            window.location.href = 'hostel_management.php?tab=requests';
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const nextYear = new Date();
            nextYear.setFullYear(today.getFullYear() + 1);

            document.getElementById('start_date').valueAsDate = today;
            document.getElementById('end_date').valueAsDate = nextYear;
            document.getElementById('new_end_date').valueAsDate = nextYear;

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

            // Initialize room usage statistics
            updateRoomUsageStats();
        });

        // Function to update room usage statistics
        function updateRoomUsageStats() {
            fetch('/university-system/php/get_room_usage.php')
                .then(response => response.json())
                .then(data => {
                    // Update UI with room usage statistics
                    console.log('Room usage data:', data);
                    // You can implement the UI update logic here
                })
                .catch(error => console.error('Error fetching room usage:', error));
        }
    </script>
</body>

</html>