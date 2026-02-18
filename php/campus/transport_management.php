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
    if (isset($_POST['add_vehicle'])) {
        $vehicle_number = $_POST['vehicle_number'];
        $type = $_POST['type'];
        $capacity = $_POST['capacity'];
        $status = $_POST['status'];
        $driver_id = $_POST['driver_id'] ?: null;
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO transport_vehicles (vehicle_number, type, capacity, status, driver_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vehicle_number, $type, $capacity, $status, $driver_id, $notes]);

        $_SESSION['message'] = "Vehicle added successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['add_route'])) {
        $route_name = $_POST['route_name'];
        $start_point = $_POST['start_point'];
        $end_point = $_POST['end_point'];
        $stops = $_POST['stops'];
        $schedule = $_POST['schedule'];
        $vehicle_id = $_POST['vehicle_id'] ?: null;
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO transport_routes (route_name, start_point, end_point, stops, schedule, vehicle_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$route_name, $start_point, $end_point, $stops, $schedule, $vehicle_id, $status]);

        $_SESSION['message'] = "Route added successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['allocate_route'])) {
        $route_id = $_POST['route_id'];
        $student_id = $_POST['student_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        $stmt = $pdo->prepare("INSERT INTO transport_allocations (route_id, student_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$route_id, $student_id, $start_date, $end_date]);

        $_SESSION['message'] = "Transport allocated successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['update_vehicle'])) {
        $vehicle_id = $_POST['vehicle_id'];
        $status = $_POST['status'];
        $driver_id = $_POST['driver_id'] ?: null;
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE transport_vehicles SET status = ?, driver_id = ?, notes = ? WHERE id = ?");
        $stmt->execute([$status, $driver_id, $notes, $vehicle_id]);

        $_SESSION['message'] = "Vehicle updated successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['update_route_status'])) {
        $route_id = $_POST['route_id'];
        $status = $_POST['status'];
        $vehicle_id = $_POST['vehicle_id'] ?: null;

        $stmt = $pdo->prepare("UPDATE transport_routes SET status = ?, vehicle_id = ? WHERE id = ?");
        $stmt->execute([$status, $vehicle_id, $route_id]);

        $_SESSION['message'] = "Route updated successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['delete_vehicle'])) {
        $vehicle_id = $_POST['vehicle_id'];

        // First check if vehicle is assigned to any routes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transport_routes WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete vehicle assigned to routes";
        } else {
            $stmt = $pdo->prepare("DELETE FROM transport_vehicles WHERE id = ?");
            $stmt->execute([$vehicle_id]);
            $_SESSION['message'] = "Vehicle deleted successfully";
        }
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['delete_route'])) {
        $route_id = $_POST['route_id'];

        // First check if route has any allocations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transport_allocations WHERE route_id = ?");
        $stmt->execute([$route_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete route with active allocations";
        } else {
            $stmt = $pdo->prepare("DELETE FROM transport_routes WHERE id = ?");
            $stmt->execute([$route_id]);
            $_SESSION['message'] = "Route deleted successfully";
        }
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['update_allocation'])) {
        $allocation_id = $_POST['allocation_id'];
        $status = $_POST['status'];
        $end_date = $_POST['end_date'];

        $stmt = $pdo->prepare("UPDATE transport_allocations SET status = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$status, $end_date, $allocation_id]);

        $_SESSION['message'] = "Allocation updated successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['delete_allocation'])) {
        $allocation_id = $_POST['allocation_id'];

        $stmt = $pdo->prepare("DELETE FROM transport_allocations WHERE id = ?");
        $stmt->execute([$allocation_id]);

        $_SESSION['message'] = "Allocation deleted successfully";
        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['approve_allocation_request'])) {
        $request_id = $_POST['request_id'];

        // Get the request details
        $stmt = $pdo->prepare("SELECT * FROM transport_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if ($request) {
            try {
                $pdo->beginTransaction();

                // Create the allocation
                $stmt = $pdo->prepare("INSERT INTO transport_allocations 
                (route_id, student_id, start_date, end_date, status) 
                VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $request['route_id'],
                    $request['student_id'],
                    $request['start_date'],
                    $request['end_date']
                ]);

                // Update the request status
                $stmt = $pdo->prepare("UPDATE transport_requests SET status = 'approved' WHERE id = ?");
                $stmt->execute([$request_id]);

                $pdo->commit();
                $_SESSION['message'] = "Transport request approved successfully";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error approving transport request: " . $e->getMessage();
            }
        }

        header("Location: transport_management.php");
        exit();
    }

    if (isset($_POST['reject_allocation_request'])) {
        $request_id = $_POST['request_id'];

        $stmt = $pdo->prepare("UPDATE transport_requests SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$request_id]);

        $_SESSION['message'] = "Transport request rejected";
        header("Location: transport_management.php");
        exit();
    }
}

// Build base queries
$vehicle_query = "
    SELECT tv.*, u.first_name, u.last_name 
    FROM transport_vehicles tv
    LEFT JOIN users u ON tv.driver_id = u.id
";

$route_query = "
    SELECT tr.*, tv.vehicle_number, tv.type as vehicle_type, 
           CONCAT(u.first_name, ' ', u.last_name) as driver_name
    FROM transport_routes tr
    LEFT JOIN transport_vehicles tv ON tr.vehicle_id = tv.id
    LEFT JOIN users u ON tv.driver_id = u.id
";

$allocation_query = "
    SELECT ta.id, ta.route_id, ta.student_id, ta.start_date, ta.end_date, ta.status,
           tr.route_name, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           u.email as student_email
    FROM transport_allocations ta
    JOIN transport_routes tr ON ta.route_id = tr.id
    JOIN users u ON ta.student_id = u.id
";

// Apply filters if they exist
$vehicle_filters = [];
if (isset($_GET['vehicle_status_filter'])) {
    $vehicle_filters[] = "tv.status = '" . $_GET['vehicle_status_filter'] . "'";
}
if (isset($_GET['vehicle_type_filter'])) {
    $vehicle_filters[] = "tv.type = '" . $_GET['vehicle_type_filter'] . "'";
}
if (!empty($vehicle_filters)) {
    $vehicle_query .= " WHERE " . implode(" AND ", $vehicle_filters);
}
$vehicle_query .= " ORDER BY tv.status, tv.type";
$vehicles = $pdo->query($vehicle_query)->fetchAll();

$route_filters = [];
if (isset($_GET['route_status_filter'])) {
    $route_filters[] = "tr.status = '" . $_GET['route_status_filter'] . "'";
}
if (!empty($route_filters)) {
    $route_query .= " WHERE " . implode(" AND ", $route_filters);
}
$route_query .= " ORDER BY tr.status, tr.route_name";
$routes = $pdo->query($route_query)->fetchAll();

$allocation_filters = [];
if (isset($_GET['allocation_status_filter'])) {
    $allocation_filters[] = "ta.status = '" . $_GET['allocation_status_filter'] . "'";
}
if (!empty($allocation_filters)) {
    $allocation_query .= " WHERE " . implode(" AND ", $allocation_filters);
}
$allocation_query .= " ORDER BY ta.status, ta.end_date DESC";
$allocations = $pdo->query($allocation_query)->fetchAll();

// Get available students
$students = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.role = 'student' AND u.status = 'active'
")->fetchAll();

// Get available drivers
$drivers = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.role = 'driver' AND u.status = 'active'
")->fetchAll();

// Get pending transport requests
$transport_requests = $pdo->query("
    SELECT tr.id, tr.route_id, tr.student_id, tr.start_date, tr.end_date, tr.status, tr.request_date, tr.reason,
           r.route_name, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           u.email as student_email
    FROM transport_requests tr
    JOIN transport_routes r ON tr.route_id = r.id
    JOIN users u ON tr.student_id = u.id
    WHERE tr.status = 'pending'
    ORDER BY tr.request_date DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Management</title>
    <link href="/university-system/css/campus.css" rel="stylesheet">
    <style>
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

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-expired {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
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
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Transport Management</h1>
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
                        <li class="active"><a href="transport_management.php">Transport Management</a></li>
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
                        <button class="tab-btn active" onclick="openTab('vehicles')">Vehicles</button>
                        <button class="tab-btn" onclick="openTab('routes')">Routes</button>
                        <button class="tab-btn" onclick="openTab('requests')">Transport Requests</button>
                        <button class="tab-btn" onclick="openTab('allocations')">Allocations</button>
                        <button class="tab-btn" onclick="openTab('addVehicle')">Add Vehicle</button>
                        <button class="tab-btn" onclick="openTab('addRoute')">Add Route</button>
                        <button class="tab-btn" onclick="openTab('allocate')">Allocate Route</button>
                    </div>

                    <div id="vehicles" class="tab-content active">
                        <h2>Transport Vehicles</h2>

                        <!-- Vehicle Filters -->
                        <div class="filter-container">
                            <form method="get" action="transport_management.php">
                                <input type="hidden" name="tab" value="vehicles">
                                <div>
                                    <label for="vehicle_status_filter">Status:</label>
                                    <select name="vehicle_status_filter" id="vehicle_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['vehicle_status_filter']) && $_GET['vehicle_status_filter'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="maintenance" <?= isset($_GET['vehicle_status_filter']) && $_GET['vehicle_status_filter'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        <option value="inactive" <?= isset($_GET['vehicle_status_filter']) && $_GET['vehicle_status_filter'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="vehicle_type_filter">Type:</label>
                                    <select name="vehicle_type_filter" id="vehicle_type_filter">
                                        <option value="">All Types</option>
                                        <option value="bus" <?= isset($_GET['vehicle_type_filter']) && $_GET['vehicle_type_filter'] == 'bus' ? 'selected' : '' ?>>Bus</option>
                                        <option value="van" <?= isset($_GET['vehicle_type_filter']) && $_GET['vehicle_type_filter'] == 'van' ? 'selected' : '' ?>>Van</option>
                                        <option value="shuttle" <?= isset($_GET['vehicle_type_filter']) && $_GET['vehicle_type_filter'] == 'shuttle' ? 'selected' : '' ?>>Shuttle</option>
                                        <option value="car" <?= isset($_GET['vehicle_type_filter']) && $_GET['vehicle_type_filter'] == 'car' ? 'selected' : '' ?>>Car</option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetVehicleFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Vehicle Number</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Driver</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($vehicle['vehicle_number']) ?></td>
                                        <td><?= ucfirst($vehicle['type']) ?></td>
                                        <td><?= $vehicle['capacity'] ?></td>
                                        <td><?= $vehicle['first_name'] ? htmlspecialchars($vehicle['first_name'] . ' ' . $vehicle['last_name']) : 'Not assigned' ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $vehicle['status'] ?>">
                                                <?= ucfirst($vehicle['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showVehicleModal(<?= $vehicle['id'] ?>)" class="logout-btn">Edit</button>
                                                <button onclick="showDeleteVehicleModal(<?= $vehicle['id'] ?>)" class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="routes" class="tab-content">
                        <h2>Transport Routes</h2>

                        <!-- Route Filters -->
                        <div class="filter-container">
                            <form method="get" action="transport_management.php">
                                <input type="hidden" name="tab" value="routes">
                                <div>
                                    <label for="route_status_filter">Status:</label>
                                    <select name="route_status_filter" id="route_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['route_status_filter']) && $_GET['route_status_filter'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= isset($_GET['route_status_filter']) && $_GET['route_status_filter'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetRouteFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Route Name</th>
                                    <th>Start Point</th>
                                    <th>End Point</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routes as $route): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($route['route_name']) ?></td>
                                        <td><?= htmlspecialchars($route['start_point']) ?></td>
                                        <td><?= htmlspecialchars($route['end_point']) ?></td>
                                        <td>
                                            <?= $route['vehicle_number'] ? htmlspecialchars($route['vehicle_number'] . ' (' . $route['vehicle_type'] . ')') : 'Not assigned' ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $route['status'] ?>">
                                                <?= ucfirst($route['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showRouteModal(<?= $route['id'] ?>)" class="logout-btn">Edit</button>
                                                <button class="logout-btn-warning" onclick="showDeleteRouteModal(<?= $route['id'] ?>)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="requests" class="tab-content">
                        <h2>Transport Requests</h2>
                        <?php if (empty($transport_requests)): ?>
                            <p>No pending transport requests.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Route</th>
                                        <th>Request Date</th>
                                        <th>Period</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transport_requests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['student_email']) ?>)</td>
                                            <td><?= htmlspecialchars($request['route_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                            <td>
                                                <?= date('M j, Y', strtotime($request['start_date'])) ?> - 
                                                <?= date('M j, Y', strtotime($request['end_date'])) ?>
                                            </td>
                                            <td><?= isset($request['reason']) ? htmlspecialchars(substr($request['reason'], 0, 30)) . (strlen($request['reason']) > 30 ? '...' : '') : '' ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <form method="post" action="transport_management.php">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="submit" name="approve_allocation_request" class="logout-btn">Approve</button>
                                                        <button type="submit" name="reject_allocation_request" class="logout-btn-warning">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div id="allocations" class="tab-content">
                        <h2>Transport Allocations</h2>
                        
                        <!-- Allocation Filters -->
                        <div class="filter-container">
                            <form method="get" action="transport_management.php">
                                <input type="hidden" name="tab" value="allocations">
                                <div>
                                    <label for="allocation_status_filter">Status:</label>
                                    <select name="allocation_status_filter" id="allocation_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?= isset($_GET['allocation_status_filter']) && $_GET['allocation_status_filter'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="expired" <?= isset($_GET['allocation_status_filter']) && $_GET['allocation_status_filter'] == 'expired' ? 'selected' : '' ?>>Expired</option>
                                        <option value="cancelled" <?= isset($_GET['allocation_status_filter']) && $_GET['allocation_status_filter'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetAllocationFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Route</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allocations as $allocation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($allocation['student_name']) ?> (<?= htmlspecialchars($allocation['student_email']) ?>)</td>
                                        <td><?= htmlspecialchars($allocation['route_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($allocation['start_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($allocation['end_date'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $allocation['status'] ?>">
                                                <?= ucfirst($allocation['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showAllocationModal(<?= $allocation['id'] ?>)" class="logout-btn">Edit</button>
                                                <button onclick="showDeleteAllocationModal(<?= $allocation['id'] ?>)" class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="addVehicle" class="tab-content">
                        <h2>Add New Vehicle</h2>
                        <form method="post" action="transport_management.php">
                            <div class="form-group">
                                <label for="vehicle_number">Vehicle Number</label>
                                <input type="text" name="vehicle_number" id="vehicle_number" required>
                            </div>
                            <div class="form-group">
                                <label for="type">Vehicle Type</label>
                                <select name="type" id="type" required>
                                    <option value="bus">Bus</option>
                                    <option value="van">Van</option>
                                    <option value="shuttle">Shuttle</option>
                                    <option value="car">Car</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="capacity">Capacity</label>
                                <input type="number" name="capacity" id="capacity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="driver_id">Driver (Optional)</label>
                                <select name="driver_id" id="driver_id">
                                    <option value="">Select Driver</option>
                                    <?php foreach ($drivers as $driver): ?>
                                        <option value="<?= $driver['id'] ?>">
                                            <?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="active">Active</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" name="add_vehicle" class="logout-btn">Add Vehicle</button>
                        </form>
                    </div>

                    <div id="addRoute" class="tab-content">
                        <h2>Add New Route</h2>
                        <form method="post" action="transport_management.php">
                            <div class="form-group">
                                <label for="route_name">Route Name</label>
                                <input type="text" name="route_name" id="route_name" required>
                            </div>
                            <div class="form-group">
                                <label for="start_point">Start Point</label>
                                <input type="text" name="start_point" id="start_point" required>
                            </div>
                            <div class="form-group">
                                <label for="end_point">End Point</label>
                                <input type="text" name="end_point" id="end_point" required>
                            </div>
                            <div class="form-group">
                                <label for="stops">Stops (comma separated)</label>
                                <textarea name="stops" id="stops" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="schedule">Schedule (e.g., Mon-Fri 7:30AM, 4:30PM)</label>
                                <textarea name="schedule" id="schedule" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="vehicle_id">Assigned Vehicle (Optional)</label>
                                <select name="vehicle_id" id="vehicle_id">
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?= $vehicle['id'] ?>">
                                            <?= htmlspecialchars($vehicle['vehicle_number'] . ' (' . ucfirst($vehicle['type']) . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <button type="submit" name="add_route" class="logout-btn">Add Route</button>
                        </form>
                    </div>

                    <div id="allocate" class="tab-content">
                        <h2>Allocate Transport Route</h2>
                        <form method="post" action="transport_management.php">
                            <div class="form-group">
                                <label for="student_id">Student</label>
                                <select name="student_id" id="student_id" required>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="route_id">Route</label>
                                <select name="route_id" id="route_id" required>
                                    <?php foreach ($routes as $route): ?>
                                        <?php if ($route['status'] == 'active'): ?>
                                            <option value="<?= $route['id'] ?>">
                                                <?= htmlspecialchars($route['route_name'] . ' (' . $route['start_point'] . ' to ' . $route['end_point'] . ')') ?>
                                            </option>
                                        <?php endif; ?>
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
                            <button type="submit" name="allocate_route" class="logout-btn">Allocate Route</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Vehicle Management Modal -->
    <div class="modal" id="vehicleModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('vehicleModal')">&times;</span>
            <h2>Manage Vehicle</h2>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="vehicle_id" id="modal_vehicle_id">

                <div class="form-group">
                    <label for="modal_vehicle_status">Status</label>
                    <select name="status" id="modal_vehicle_status" required>
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_vehicle_driver">Driver</label>
                    <select name="driver_id" id="modal_vehicle_driver">
                        <option value="">Select Driver</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['id'] ?>">
                                <?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_vehicle_notes">Notes</label>
                    <textarea name="notes" id="modal_vehicle_notes" rows="3"></textarea>
                </div>

                <button type="submit" name="update_vehicle" class="logout-btn">Update Vehicle</button>
            </form>
        </div>
    </div>

    <!-- Delete Vehicle Confirmation Modal -->
    <div class="modal" id="deleteVehicleModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteVehicleModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this vehicle?</p>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
                <div class="form-group">
                    <button type="submit" name="delete_vehicle" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteVehicleModal')" class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Route Management Modal -->
    <div class="modal" id="routeModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('routeModal')">&times;</span>
            <h2>Manage Route</h2>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="route_id" id="modal_route_id">

                <div class="form-group">
                    <label for="modal_route_status">Status</label>
                    <select name="status" id="modal_route_status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_route_vehicle">Assigned Vehicle</label>
                    <select name="vehicle_id" id="modal_route_vehicle">
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>">
                                <?= htmlspecialchars($vehicle['vehicle_number'] . ' (' . ucfirst($vehicle['type']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="update_route_status" class="logout-btn">Update Route</button>
            </form>
        </div>
    </div>

    <!-- Delete Route Confirmation Modal -->
    <div class="modal" id="deleteRouteModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteRouteModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this route?</p>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="route_id" id="delete_route_id">
                <div class="form-group">
                    <button type="submit" name="delete_route" class="logout-btn delete-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteRouteModal')" class="logout-btn-warning">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Allocation Management Modal -->
    <div class="modal" id="allocationModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('allocationModal')">&times;</span>
            <h2>Manage Allocation</h2>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="allocation_id" id="modal_allocation_id">

                <div class="form-group">
                    <label for="modal_allocation_status">Status</label>
                    <select name="status" id="modal_allocation_status" required>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_allocation_end_date">End Date</label>
                    <input type="date" name="end_date" id="modal_allocation_end_date" required>
                </div>

                <button type="submit" name="update_allocation" class="logout-btn">Update Allocation</button>
            </form>
        </div>
    </div>

    <!-- Delete Allocation Confirmation Modal -->
    <div class="modal" id="deleteAllocationModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteAllocationModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this allocation?</p>
            <form method="post" action="transport_management.php">
                <input type="hidden" name="allocation_id" id="delete_allocation_id">
                <div class="form-group">
                    <button type="submit" name="delete_allocation" class="logout-btn">Delete</button>
                    <button type="button" onclick="hideModal('deleteAllocationModal')" class="logout-btn-warning">Cancel</button>
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

        function showVehicleModal(vehicleId) {
            // Find the vehicle in our existing data
            const vehicles = <?= json_encode($vehicles) ?>;
            const vehicle = vehicles.find(v => v.id == vehicleId);

            if (vehicle) {
                document.getElementById('modal_vehicle_id').value = vehicle.id;
                document.getElementById('modal_vehicle_status').value = vehicle.status;
                document.getElementById('modal_vehicle_driver').value = vehicle.driver_id || '';
                document.getElementById('modal_vehicle_notes').value = vehicle.notes || '';
                showModal('vehicleModal');
            }
        }

        function showDeleteVehicleModal(vehicleId) {
            document.getElementById('delete_vehicle_id').value = vehicleId;
            showModal('deleteVehicleModal');
        }

        function showRouteModal(routeId) {
            // Find the route in our existing data
            const routes = <?= json_encode($routes) ?>;
            const route = routes.find(r => r.id == routeId);

            if (route) {
                document.getElementById('modal_route_id').value = route.id;
                document.getElementById('modal_route_status').value = route.status;
                document.getElementById('modal_route_vehicle').value = route.vehicle_id || '';
                showModal('routeModal');
            }
        }

        function showDeleteRouteModal(routeId) {
            document.getElementById('delete_route_id').value = routeId;
            showModal('deleteRouteModal');
        }

        function showAllocationModal(allocationId) {
            // Find the allocation in our existing data
            const allocations = <?= json_encode($allocations) ?>;
            const alloc = allocations.find(a => a.id == allocationId);

            if (alloc) {
                document.getElementById('modal_allocation_id').value = alloc.id;
                document.getElementById('modal_allocation_status').value = alloc.status;
                document.getElementById('modal_allocation_end_date').value = alloc.end_date.split(' ')[0];
                showModal('allocationModal');
            }
        }

        function showDeleteAllocationModal(allocationId) {
            document.getElementById('delete_allocation_id').value = allocationId;
            showModal('deleteAllocationModal');
        }

        // Reset filter functions
        function resetVehicleFilters() {
            window.location.href = 'transport_management.php?tab=vehicles';
        }

        function resetRouteFilters() {
            window.location.href = 'transport_management.php?tab=routes';
        }

        function resetAllocationFilters() {
            window.location.href = 'transport_management.php?tab=allocations';
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const nextYear = new Date();
            nextYear.setFullYear(today.getFullYear() + 1);

            document.getElementById('start_date').valueAsDate = today;
            document.getElementById('end_date').valueAsDate = nextYear;

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