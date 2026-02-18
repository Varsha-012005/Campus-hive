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
    if (isset($_POST['request_transport'])) {
        $student_id = $_SESSION['user_id'];
        $route_id = $_POST['route_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = $_POST['reason'];

        $stmt = $pdo->prepare("INSERT INTO transport_requests 
            (student_id, route_id, start_date, end_date, reason, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$student_id, $route_id, $start_date, $end_date, $reason]);

        $_SESSION['message'] = "Transport request submitted successfully";
        header("Location: transport.php");
        exit();
    }

    if (isset($_POST['cancel_allocation'])) {
        $allocation_id = $_POST['allocation_id'];

        // Check if the allocation belongs to the current user
        $check_stmt = $pdo->prepare("SELECT id FROM transport_allocations WHERE id = ? AND student_id = ?");
        $check_stmt->execute([$allocation_id, $_SESSION['user_id']]);

        if ($check_stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE transport_allocations SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$allocation_id]);
            $_SESSION['message'] = "Transport allocation cancelled successfully";
        } else {
            $_SESSION['error'] = "You can only cancel your own allocations";
        }

        header("Location: transport.php");
        exit();
    }
}

// Get student's transport allocations
$allocations = $pdo->query("
    SELECT ta.id, ta.route_id, ta.start_date, ta.end_date, ta.status,
           tr.route_name, tr.start_point, tr.end_point, tr.stops, tr.schedule,
           tv.vehicle_number, tv.type as vehicle_type, tv.capacity,
           CONCAT(u.first_name, ' ', u.last_name) as driver_name
    FROM transport_allocations ta
    JOIN transport_routes tr ON ta.route_id = tr.id
    LEFT JOIN transport_vehicles tv ON tr.vehicle_id = tv.id
    LEFT JOIN users u ON tv.driver_id = u.id
    WHERE ta.student_id = " . $_SESSION['user_id'] . "
    ORDER BY ta.status, ta.end_date DESC
")->fetchAll();

// Get student's transport requests
$transport_requests = $pdo->query("
    SELECT tr.id, tr.route_id, tr.start_date, tr.end_date, tr.status, tr.reason,
           r.route_name, r.start_point, r.end_point
    FROM transport_requests tr
    JOIN transport_routes r ON tr.route_id = r.id
    WHERE tr.student_id = " . $_SESSION['user_id'] . "
    ORDER BY tr.status, tr.request_date DESC
")->fetchAll();

// Get available routes
$available_routes = $pdo->query("
    SELECT * FROM transport_routes 
    WHERE status = 'active'
    ORDER BY route_name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Services</title>

    <style>
        /* Base Styles */
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

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        /* Form Styles */
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
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
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

        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
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

        /* Transport Specific Styles */
        .route-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }

        .route-details h4 {
            margin-top: 0;
            color: #1e3799;
            margin-bottom: 10px;
        }

        .stops-list {
            list-style-type: none;
            padding-left: 0;
            margin-top: 10px;
        }

        .stops-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .stops-list li:before {
            content: "•";
            color: var(--secondary);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }

        .stops-list li:last-child {
            border-bottom: none;
        }

        .vehicle-info {
            background-color: #e9f7ef;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid var(--secondary);
        }

        .vehicle-info p {
            margin: 5px 0;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .logout-btn {
            padding: 8px 15px;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: var(--secondary-light);
        }

        .logout-btn-warning {
            padding: 8px 15px;
            background-color: var(--danger);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn-warning:hover {
            background-color: #c0392b;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 1.5rem;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            position: relative;
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

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .tab-buttons {
                flex-direction: column;
            }

            .filter-container {
                flex-direction: column;
                align-items: flex-start;
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
                <a href="/university-system/php/public/library.php" class="menu-item">
                    <i class="fas fa-book"></i> Library
                </a>
                <a href="/university-system/php/public/medical.php" class="menu-item">
                    <i class="fas fa-heartbeat"></i> Medical Center
                </a>
                <a href="/university-system/php/public/transport.php" class="menu-item active">
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
                    <h1>Transport Services</h1>
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
                        <button class="tab-btn active" onclick="openTab('myTransport')">My Transport</button>
                        <button class="tab-btn" onclick="openTab('requestTransport')">Request Transport</button>
                        <button class="tab-btn" onclick="openTab('myRequests')">My Requests</button>
                    </div>

                    <div id="myTransport" class="tab-content active">
                        <h2>My Transport Allocations</h2>

                        <?php if (empty($allocations)): ?>
                            <p>You have no active transport allocations.</p>
                        <?php else: ?>
                            <?php foreach ($allocations as $alloc): ?>
                                <div class="route-details">
                                    <h4><?= htmlspecialchars($alloc['route_name']) ?></h4>
                                    <p><strong>Route:</strong> <?= htmlspecialchars($alloc['start_point']) ?> to
                                        <?= htmlspecialchars($alloc['end_point']) ?></p>
                                    <p><strong>Schedule:</strong> <?= htmlspecialchars($alloc['schedule']) ?></p>
                                    <p><strong>Status:</strong>
                                        <span class="status-badge status-<?= $alloc['status'] ?>">
                                            <?= ucfirst($alloc['status']) ?>
                                        </span>
                                    </p>
                                    <p><strong>Valid:</strong> <?= date('M j, Y', strtotime($alloc['start_date'])) ?> to
                                        <?= date('M j, Y', strtotime($alloc['end_date'])) ?></p>

                                    <?php if ($alloc['vehicle_number']): ?>
                                        <div class="vehicle-info">
                                            <p><strong>Assigned Vehicle:</strong> <?= htmlspecialchars($alloc['vehicle_number']) ?>
                                                (<?= ucfirst($alloc['vehicle_type']) ?>, Capacity: <?= $alloc['capacity'] ?>)</p>
                                            <?php if ($alloc['driver_name']): ?>
                                                <p><strong>Driver:</strong> <?= htmlspecialchars($alloc['driver_name']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <h5>Stops:</h5>
                                    <ul class="stops-list">
                                        <?php
                                        $stops = explode(',', $alloc['stops']);
                                        foreach ($stops as $stop):
                                            ?>
                                            <li><?= htmlspecialchars(trim($stop)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if ($alloc['status'] == 'active'): ?>
                                        <form method="post" action="transport.php" style="margin-top: 15px;">
                                            <input type="hidden" name="allocation_id" value="<?= $alloc['id'] ?>">
                                            <button type="submit" name="cancel_allocation" class="btn btn-danger">Cancel
                                                Allocation</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="requestTransport" class="tab-content">
                        <h2>Request Transport Allocation</h2>
                        <form method="post" action="transport.php">
                            <div class="form-group">
                                <label for="route_id">Route</label>
                                <select name="route_id" id="route_id" required>
                                    <?php foreach ($available_routes as $route): ?>
                                        <option value="<?= $route['id'] ?>">
                                            <?= htmlspecialchars($route['route_name'] . ' (' . $route['start_point'] . ' to ' . $route['end_point'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" required
                                    min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" required>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason for Request</label>
                                <textarea name="reason" id="reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="request_transport" class="btn btn-primary">Submit
                                Request</button>
                        </form>
                    </div>

                    <div id="myRequests" class="tab-content">
                        <h2>My Transport Requests</h2>

                        <?php if (empty($transport_requests)): ?>
                            <p>You have no transport requests.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th>Request Date</th>
                                        <th>Period</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transport_requests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['route_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($request['request_date'] ?? 'now')) ?></td>
                                            <td>
                                                <?= date('M j, Y', strtotime($request['start_date'])) ?> -
                                                <?= date('M j, Y', strtotime($request['end_date'])) ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $request['status'] ?>">
                                                    <?= ucfirst($request['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars(substr($request['reason'], 0, 30)) . (strlen($request['reason']) > 30 ? '...' : '') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const nextMonth = new Date();
            nextMonth.setMonth(today.getMonth() + 1);

            document.getElementById('start_date').valueAsDate = today;
            document.getElementById('end_date').valueAsDate = nextMonth;
        });
    </script>
</body>

</html>