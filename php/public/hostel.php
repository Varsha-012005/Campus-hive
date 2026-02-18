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
    if (isset($_POST['request_room'])) {
        $user_id = $_SESSION['user_id'];
        $room_id = $_POST['room_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $purpose = $_POST['purpose'];

        // Check if user already has an active request
        $check = $pdo->prepare("SELECT COUNT(*) FROM hostel_allocations 
                              WHERE user_id = ? AND request_status IN ('pending', 'approved')");
        $check->execute([$user_id]);
        $count = $check->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "You already have an active room request or allocation";
            header("Location: hostel.php");
            exit();
        }

        // Create room request
        $stmt = $pdo->prepare("INSERT INTO hostel_allocations 
                              (user_id, room_id, start_date, end_date, purpose, request_status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $room_id, $start_date, $end_date, $purpose]);

        $_SESSION['message'] = "Room request submitted successfully. Waiting for approval.";
        header("Location: hostel.php");
        exit();
    }

    if (isset($_POST['cancel_request'])) {
        $request_id = $_POST['request_id'];
        $user_id = $_SESSION['user_id'];

        // Verify the request belongs to the user
        $check = $pdo->prepare("SELECT id FROM hostel_allocations 
                               WHERE id = ? AND user_id = ? AND request_status = 'pending'");
        $check->execute([$request_id, $user_id]);

        if ($check->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM hostel_allocations WHERE id = ?");
            $stmt->execute([$request_id]);
            $_SESSION['message'] = "Room request cancelled successfully";
        } else {
            $_SESSION['error'] = "You can only cancel pending requests";
        }

        header("Location: hostel.php");
        exit();
    }

    if (isset($_POST['terminate_allocation'])) {
        $allocation_id = $_POST['allocation_id'];
        $user_id = $_SESSION['user_id'];

        // Verify the allocation belongs to the user and is active
        $check = $pdo->prepare("SELECT id, room_id FROM hostel_allocations 
                           WHERE id = ? AND user_id = ? AND status = 'active'");
        $check->execute([$allocation_id, $user_id]);
        $allocation = $check->fetch();

        if ($allocation) {
            // Start transaction for atomic operations
            $pdo->beginTransaction();

            try {
                // Update allocation status to terminated
                $stmt = $pdo->prepare("UPDATE hostel_allocations 
                                  SET status = 'terminated', 
                                      end_date = CURDATE() 
                                  WHERE id = ?");
                $stmt->execute([$allocation_id]);

                // Update room status to available
                $pdo->prepare("UPDATE hostel_rooms 
                          SET status = 'available' 
                          WHERE id = ?")
                    ->execute([$allocation['room_id']]);

                $pdo->commit();
                $_SESSION['message'] = "Room allocation terminated successfully";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error terminating allocation: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Invalid request - allocation not found or not active";
        }

        header("Location: hostel.php");
        exit();
    }
}

// Get available rooms
$room_query = "SELECT * FROM hostel_rooms WHERE status = 'available'";
$rooms = $pdo->query($room_query)->fetchAll();

// Get user's allocations and requests
$allocations = $pdo->query("
    SELECT ha.*, hr.room_number, hr.building, hr.type, hr.capacity
    FROM hostel_allocations ha
    JOIN hostel_rooms hr ON ha.room_id = hr.id
    WHERE ha.user_id = " . $_SESSION['user_id'] . "
    ORDER BY ha.start_date DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Accommodation</title>
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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

        /* Room Card */
        .room-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .room-card h3 {
            margin-top: 0;
            color: var(--secondary);
        }

        .room-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .room-detail {
            flex: 1;
            min-width: 150px;
        }

        .room-detail label {
            display: block;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .room-detail span {
            font-size: 1.1rem;
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
        }

        @media (max-width: 576px) {
            .tab-buttons {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
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
                <a href="/university-system/php/public/transport.php" class="menu-item">
                    <i class="fas fa-bus"></i> Transport
                </a>

                <a href="/university-system/php/public/hostel.php" class="menu-item active">
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
                    <h1>Hostel Accommodation</h1>
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
                        <button class="tab-btn active" onclick="openTab('availableRooms')">Available Rooms</button>
                        <button class="tab-btn" onclick="openTab('myRequests')">My Requests</button>
                        <button class="tab-btn" onclick="openTab('requestRoom')">Request Room</button>
                    </div>

                    <div id="availableRooms" class="tab-content active">
                        <h2>Available Rooms</h2>

                        <?php if (empty($rooms)): ?>
                            <p>No rooms currently available. Please check back later.</p>
                        <?php else: ?>
                            <div class="rooms-grid">
                                <?php foreach ($rooms as $room): ?>
                                    <div class="room-card">
                                        <h3><?= htmlspecialchars($room['building']) ?> - Room
                                            <?= htmlspecialchars($room['room_number']) ?></h3>
                                        <div class="room-details">
                                            <div class="room-detail">
                                                <label>Type</label>
                                                <span><?= ucfirst($room['type']) ?></span>
                                            </div>
                                            <div class="room-detail">
                                                <label>Capacity</label>
                                                <span><?= $room['capacity'] ?></span>
                                            </div>
                                            <div class="room-detail">
                                                <label>Status</label>
                                                <span class="status-badge status-<?= $room['status'] ?>">
                                                    <?= ucfirst($room['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <button onclick="requestRoomModal(<?= $room['id'] ?>)" class="btn btn-primary">
                                            Request This Room
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="myRequests" class="tab-content">
                        <h2>My Room Requests and Allocations</h2>

                        <?php if (empty($allocations)): ?>
                            <p>You have no room requests or allocations.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
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
                                            <td><?= htmlspecialchars($alloc['room_number']) ?></td>
                                            <td><?= htmlspecialchars($alloc['building']) ?></td>
                                            <td><?= ucfirst($alloc['type']) ?></td>
                                            <td><?= date('M j, Y', strtotime($alloc['start_date'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($alloc['end_date'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $alloc['request_status'] ?>">
                                                    <?= ucfirst($alloc['request_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($alloc['request_status'] == 'pending'): ?>
                                                        <form method="post" action="hostel.php" style="display: inline;">
                                                            <input type="hidden" name="request_id" value="<?= $alloc['id'] ?>">
                                                            <button type="submit" name="cancel_request" class="btn btn-danger">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    <?php elseif ($alloc['request_status'] == 'approved'): ?>
                                                        <form method="post" action="hostel.php" style="display: inline;">
                                                            <input type="hidden" name="allocation_id" value="<?= $alloc['id'] ?>">
                                                            <button type="submit" name="terminate_allocation"
                                                                class="btn btn-danger">
                                                                Terminate
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div id="requestRoom" class="tab-content">
                        <h2>Request a Room</h2>
                        <form method="post" action="hostel.php">
                            <div class="form-group">
                                <label for="room_id">Select Room</label>
                                <select name="room_id" id="room_id" required>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?= $room['id'] ?>">
                                            <?= htmlspecialchars($room['building'] . ' - Room ' . $room['room_number'] . ' (' . $room['type'] . ', Capacity: ' . $room['capacity'] . ')') ?>
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
                                <label for="purpose">Purpose of Stay</label>
                                <textarea name="purpose" id="purpose" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="request_room" class="btn btn-primary">Submit Request</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Request Room Modal -->
    <div class="modal" id="requestRoomModal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="hideModal('requestRoomModal')">&times;</span>
            <h2>Request Room</h2>
            <form method="post" action="hostel.php" id="roomRequestForm">
                <input type="hidden" name="room_id" id="modal_room_id">

                <div class="form-group">
                    <label for="modal_start_date">Start Date</label>
                    <input type="date" name="start_date" id="modal_start_date" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="modal_end_date">End Date</label>
                    <input type="date" name="end_date" id="modal_end_date" required>
                </div>
                <div class="form-group">
                    <label for="modal_purpose">Purpose of Stay</label>
                    <textarea name="purpose" id="modal_purpose" rows="3" required></textarea>
                </div>
                <button type="submit" name="request_room" class="btn btn-primary">Submit Request</button>
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

        function requestRoomModal(roomId) {
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('requestRoomModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            const nextYear = new Date();
            nextYear.setFullYear(today.getFullYear() + 1);

            document.getElementById('start_date').valueAsDate = today;
            document.getElementById('end_date').valueAsDate = nextYear;
            document.getElementById('modal_start_date').valueAsDate = today;
            document.getElementById('modal_end_date').valueAsDate = nextYear;

            // Close modal when clicking outside
            window.onclick = function (event) {
                if (event.target.className === 'modal') {
                    event.target.style.display = 'none';
                }
            }
        });
    </script>
</body>

</html>