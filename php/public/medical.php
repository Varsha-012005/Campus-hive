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
    if (isset($_POST['book_appointment'])) {
        $patient_id = $_SESSION['user_id'];
        $staff_id = $_POST['staff_id'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $reason = $_POST['reason'];

        $stmt = $pdo->prepare("INSERT INTO medical_appointments (patient_id, staff_id, appointment_date, appointment_time, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $staff_id, $appointment_date, $appointment_time, $reason]);

        $_SESSION['message'] = "Appointment booked successfully";
        header("Location: medical.php");
        exit();
    }

    if (isset($_POST['cancel_appointment'])) {
        $appointment_id = $_POST['appointment_id'];

        // Check if the appointment belongs to the current user
        $check_stmt = $pdo->prepare("SELECT id FROM medical_appointments WHERE id = ? AND patient_id = ?");
        $check_stmt->execute([$appointment_id, $_SESSION['user_id']]);

        if ($check_stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE medical_appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $_SESSION['message'] = "Appointment cancelled successfully";
        } else {
            $_SESSION['error'] = "You can only cancel your own appointments";
        }

        header("Location: medical.php");
        exit();
    }
}

// Get user's appointments
$appointments = $pdo->query("
    SELECT ma.*, 
           CONCAT(s.first_name, ' ', s.last_name) as staff_name,
           ms.specialization
    FROM medical_appointments ma
    JOIN medical_staff ms ON ma.staff_id = ms.id
    JOIN users s ON ms.user_id = s.id
    WHERE ma.patient_id = " . $_SESSION['user_id'] . "
    ORDER BY ma.appointment_date DESC, ma.appointment_time DESC
")->fetchAll();

// Get available medical staff
$medical_staff = $pdo->query("
    SELECT ms.id, u.first_name, u.last_name, ms.specialization
    FROM medical_staff ms
    JOIN users u ON ms.user_id = u.id
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$available_medicines = $pdo->query("
    SELECT * FROM medical_inventory 
    WHERE category = 'medicine' AND quantity > 0
    ORDER BY item_name
")->fetchAll();

// Add this to the form submission handling
if (isset($_POST['request_medicine'])) {
    $patient_id = $_SESSION['user_id'];
    $medicine_id = $_POST['medicine_id'];
    $quantity_requested = $_POST['quantity_requested'];
    $medical_reason = $_POST['medical_reason'];

    // Check if enough quantity is available
    $medicine = $pdo->query("SELECT * FROM medical_inventory WHERE id = $medicine_id")->fetch();

    if ($medicine['quantity'] >= $quantity_requested) {
        try {
            $pdo->beginTransaction();

            // Create medicine request
            $stmt = $pdo->prepare("INSERT INTO medicine_requests 
                (patient_id, medicine_id, quantity_requested, medical_reason, status) 
                VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$patient_id, $medicine_id, $quantity_requested, $medical_reason]);

            // Update inventory (reserve the quantity)
            $stmt = $pdo->prepare("UPDATE medical_inventory 
                SET quantity = quantity - ? 
                WHERE id = ?");
            $stmt->execute([$quantity_requested, $medicine_id]);

            $pdo->commit();

            $_SESSION['message'] = "Medicine request submitted successfully";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing medicine request: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Not enough quantity available. Only " . $medicine['quantity'] . " " . $medicine['unit'] . " available.";
    }

    header("Location: medical.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Center</title>
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

        .status-scheduled {
            background-color: #d4edda;
            color: #155724;
        }

        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-no_show {
            background-color: #fff3cd;
            color: #856404;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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

        .status-collected {
            background-color: #cce5ff;
            color: #004085;
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
                <a href="/university-system/php/public/medical.php" class="menu-item active">
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
                    <h1>Medical Center</h1>
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
                        <button class="tab-btn active" onclick="openTab('myAppointments')">My Appointments</button>
                        <button class="tab-btn" onclick="openTab('bookAppointment')">Book Appointment</button>
                        <button class="tab-btn" onclick="openTab('bookMedicine')">Book Medicine</button>
                    </div>

                    <div id="myAppointments" class="tab-content active">
                        <h2>My Appointments</h2>

                        <?php if (empty($appointments)): ?>
                            <p>You have no appointments scheduled.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appt): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                            <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                            <td><?= htmlspecialchars($appt['staff_name']) ?></td>
                                            <td><?= htmlspecialchars($appt['specialization']) ?></td>
                                            <td><?= htmlspecialchars(substr($appt['reason'], 0, 30)) . (strlen($appt['reason']) > 30 ? '...' : '') ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $appt['status'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $appt['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($appt['status'] == 'scheduled'): ?>
                                                    <div class="action-buttons">
                                                        <form method="post" action="medical.php" style="display: inline;">
                                                            <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                            <button type="submit" name="cancel_appointment"
                                                                class="btn btn-danger">Cancel</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div id="myAppointment" class="tab-content active">
                        <h3>My Medicine Requests</h3>
                        <?php
                        $medicine_requests = $pdo->query("
    SELECT mr.*, mi.item_name, mi.unit
    FROM medicine_requests mr
    JOIN medical_inventory mi ON mr.medicine_id = mi.id
    WHERE mr.patient_id = " . $_SESSION['user_id'] . "
    ORDER BY mr.request_date DESC
")->fetchAll();

                        if (empty($medicine_requests)): ?>
                            <p>You have no medicine requests.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Medicine</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicine_requests as $request): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                            <td><?= htmlspecialchars($request['item_name']) ?></td>
                                            <td><?= $request['quantity_requested'] ?></td>
                                            <td><?= $request['unit'] ?></td>
                                            <td><?= htmlspecialchars(substr($request['medical_reason'], 0, 30)) . (strlen($request['medical_reason']) > 30 ? '...' : '') ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $request['status'] ?>">
                                                    <?= ucfirst($request['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div id="bookAppointment" class="tab-content">
                        <h2>Book New Appointment</h2>
                        <form method="post" action="medical.php">
                            <div class="form-group">
                                <label for="staff_id">Doctor</label>
                                <select name="staff_id" id="staff_id" required>
                                    <?php foreach ($medical_staff as $staff): ?>
                                        <option value="<?= $staff['id'] ?>">
                                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . $staff['specialization'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="appointment_date">Date</label>
                                <input type="date" name="appointment_date" id="appointment_date" required
                                    min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment_time">Time</label>
                                <input type="time" name="appointment_time" id="appointment_time" required>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason for Appointment</label>
                                <textarea name="reason" id="reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="book_appointment" class="btn btn-primary">Book
                                Appointment</button>
                        </form>
                    </div>

                    <div id="bookMedicine" class="tab-content">
                        <h2>Request Medicine</h2>
                        <form method="post" action="medical.php">
                            <div class="form-group">
                                <label for="medicine_id">Medicine</label>
                                <select name="medicine_id" id="medicine_id" required>
                                    <?php foreach ($available_medicines as $medicine): ?>
                                        <option value="<?= $medicine['id'] ?>" data-quantity="<?= $medicine['quantity'] ?>"
                                            data-unit="<?= $medicine['unit'] ?>">
                                            <?= htmlspecialchars($medicine['item_name'] . ' (' . $medicine['quantity'] . ' ' . $medicine['unit'] . ' available)') ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity_requested">Quantity Needed</label>
                                <input type="number" name="quantity_requested" id="quantity_requested" min="1" required>
                                <span id="unit_display"></span>
                            </div>
                            <div class="form-group">
                                <label for="medical_reason">Medical Reason</label>
                                <textarea name="medical_reason" id="medical_reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="request_medicine" class="btn btn-primary">Request
                                Medicine</button>
                        </form>
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

        // Add this to the JavaScript section
        document.getElementById('medicine_id').addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const unit = selectedOption.dataset.unit;
            document.getElementById('unit_display').textContent = unit;

            // Set max quantity based on available
            const maxQuantity = parseInt(selectedOption.dataset.quantity);
            document.getElementById('quantity_requested').max = maxQuantity;
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize medicine unit display
            const initialSelect = document.getElementById('medicine_id');
            if (initialSelect.options.length > 0) {
                const initialOption = initialSelect.options[initialSelect.selectedIndex];
                document.getElementById('unit_display').textContent = initialOption.dataset.unit;
                document.getElementById('quantity_requested').max = parseInt(initialOption.dataset.quantity);
            }
        });

        // Initialize date and time pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();
            document.getElementById('appointment_date').valueAsDate = today;
            document.getElementById('appointment_time').value = '09:00';
        });
    </script>
</body>

</html>