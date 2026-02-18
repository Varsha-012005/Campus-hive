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
    if (isset($_POST['add_staff'])) {
        $user_id = $_POST['user_id'];
        $specialization = $_POST['specialization'];
        $license_number = $_POST['license_number'];

        $stmt = $pdo->prepare("INSERT INTO medical_staff (user_id, specialization, license_number) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $specialization, $license_number]);

        $_SESSION['message'] = "Medical staff added successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['add_appointment'])) {
        $patient_id = $_POST['patient_id'];
        $staff_id = $_POST['staff_id'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $reason = $_POST['reason'];

        $stmt = $pdo->prepare("INSERT INTO medical_appointments (patient_id, staff_id, appointment_date, appointment_time, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $staff_id, $appointment_date, $appointment_time, $reason]);

        $_SESSION['message'] = "Appointment scheduled successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['update_appointment'])) {
        $appointment_id = $_POST['appointment_id'];
        $status = $_POST['status'];
        $diagnosis = $_POST['diagnosis'];
        $prescription = $_POST['prescription'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE medical_appointments SET status = ?, diagnosis = ?, prescription = ?, notes = ? WHERE id = ?");
        $stmt->execute([$status, $diagnosis, $prescription, $notes, $appointment_id]);

        $_SESSION['message'] = "Appointment updated successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['add_inventory'])) {
        $item_name = $_POST['item_name'];
        $category = $_POST['category'];
        $quantity = $_POST['quantity'];
        $unit = $_POST['unit'];
        $expiry_date = $_POST['expiry_date'];
        $supplier = $_POST['supplier'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO medical_inventory (item_name, category, quantity, unit, expiry_date, supplier, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_name, $category, $quantity, $unit, $expiry_date, $supplier, $notes]);

        $_SESSION['message'] = "Inventory item added successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['update_inventory'])) {
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'];
        $expiry_date = $_POST['expiry_date'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE medical_inventory SET quantity = ?, expiry_date = ?, notes = ? WHERE id = ?");
        $stmt->execute([$quantity, $expiry_date, $notes, $item_id]);

        $_SESSION['message'] = "Inventory item updated successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['delete_staff'])) {
        $staff_id = $_POST['staff_id'];

        // First check if staff has any appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_appointments WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete staff member with assigned appointments";
        } else {
            $stmt = $pdo->prepare("DELETE FROM medical_staff WHERE id = ?");
            $stmt->execute([$staff_id]);
            $_SESSION['message'] = "Medical staff deleted successfully";
        }
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['delete_appointment'])) {
        $appointment_id = $_POST['appointment_id'];

        $stmt = $pdo->prepare("DELETE FROM medical_appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);

        $_SESSION['message'] = "Appointment deleted successfully";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['delete_inventory'])) {
        $item_id = $_POST['item_id'];

        $stmt = $pdo->prepare("DELETE FROM medical_inventory WHERE id = ?");
        $stmt->execute([$item_id]);

        $_SESSION['message'] = "Inventory item deleted successfully";
        header("Location: medical_management.php");
        exit();
    }

    // Add to the existing POST handlers
    if (isset($_POST['approve_medicine'])) {
        $request_id = $_POST['request_id'];
        $staff_id = $_SESSION['user_id'];

        $pdo->beginTransaction();
        try {
            // Get the request details
            $request = $pdo->query("SELECT * FROM medicine_requests WHERE id = $request_id")->fetch();

            // Check if enough quantity is available
            $medicine = $pdo->query("SELECT * FROM medical_inventory WHERE id = {$request['medicine_id']}")->fetch();

            if ($medicine['quantity'] >= $request['quantity_requested']) {
                // Update request status
                $stmt = $pdo->prepare("UPDATE medicine_requests SET 
                status = 'approved',
                approved_by = ?,
                approval_date = NOW()
                WHERE id = ?");
                $stmt->execute([$staff_id, $request_id]);

                // Deduct from inventory
                $stmt = $pdo->prepare("UPDATE medical_inventory 
                SET quantity = quantity - ? 
                WHERE id = ?");
                $stmt->execute([$request['quantity_requested'], $request['medicine_id']]);

                $pdo->commit();
                $_SESSION['message'] = "Medicine request approved successfully";
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = "Not enough quantity available to approve this request";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error approving request: " . $e->getMessage();
        }
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['reject_medicine'])) {
        $request_id = $_POST['request_id'];
        $rejection_reason = $_POST['rejection_reason'];

        $stmt = $pdo->prepare("UPDATE medicine_requests SET 
        status = 'rejected',
        rejection_reason = ?
        WHERE id = ?");
        $stmt->execute([$rejection_reason, $request_id]);

        $_SESSION['message'] = "Medicine request rejected";
        header("Location: medical_management.php");
        exit();
    }

    if (isset($_POST['mark_collected'])) {
        $request_id = $_POST['request_id'];

        $stmt = $pdo->prepare("UPDATE medicine_requests SET 
        status = 'collected'
        WHERE id = ?");
        $stmt->execute([$request_id]);

        $_SESSION['message'] = "Medicine marked as collected";
        header("Location: medical_management.php");
        exit();
    }
}

// Build base queries with filters
$appointment_query = "
    SELECT ma.*, 
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(s.first_name, ' ', s.last_name) as staff_name,
           ms.specialization
    FROM medical_appointments ma
    JOIN users p ON ma.patient_id = p.id
    JOIN medical_staff ms ON ma.staff_id = ms.id
    JOIN users s ON ms.user_id = s.id
";

$staff_query = "
    SELECT ms.*, u.first_name, u.last_name, u.email
    FROM medical_staff ms
    JOIN users u ON ms.user_id = u.id
";

$inventory_query = "SELECT * FROM medical_inventory";

// Apply filters if they exist
$appointment_filters = [];
if (isset($_GET['appointment_status_filter'])) {
    $appointment_filters[] = "ma.status = '" . $_GET['appointment_status_filter'] . "'";
}
if (!empty($appointment_filters)) {
    $appointment_query .= " WHERE " . implode(" AND ", $appointment_filters);
}
$appointment_query .= " ORDER BY ma.appointment_date DESC, ma.appointment_time DESC";
$appointments = $pdo->query($appointment_query)->fetchAll();

$staff_filters = [];
if (isset($_GET['staff_specialization_filter'])) {
    $staff_filters[] = "ms.specialization LIKE '%" . $_GET['staff_specialization_filter'] . "%'";
}
if (!empty($staff_filters)) {
    $staff_query .= " WHERE " . implode(" AND ", $staff_filters);
}
$staff_query .= " ORDER BY u.last_name, u.first_name";
$medical_staff = $pdo->query($staff_query)->fetchAll();

$inventory_filters = [];
if (isset($_GET['inventory_category_filter'])) {
    $inventory_filters[] = "category = '" . $_GET['inventory_category_filter'] . "'";
}
if (isset($_GET['inventory_expiry_filter'])) {
    if ($_GET['inventory_expiry_filter'] == 'expired') {
        $inventory_filters[] = "expiry_date IS NOT NULL AND expiry_date < CURDATE()";
    } elseif ($_GET['inventory_expiry_filter'] == 'expiring_soon') {
        $inventory_filters[] = "expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}
if (!empty($inventory_filters)) {
    $inventory_query .= " WHERE " . implode(" AND ", $inventory_filters);
}
$inventory_query .= " ORDER BY item_name";
$inventory = $pdo->query($inventory_query)->fetchAll();

// Get available staff (campus role users not already in medical_staff)
$available_staff = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    LEFT JOIN medical_staff ms ON u.id = ms.user_id
    WHERE u.role = 'campus' AND ms.id IS NULL
")->fetchAll();

// Get all students (for appointments)
$students = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'student' AND status = 'active'")->fetchAll();

// Get unique specializations for filter
$specializations = $pdo->query("SELECT DISTINCT specialization FROM medical_staff ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);

// Get unique categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM medical_inventory ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Add with the other queries
$medicine_requests_query = "
    SELECT mr.*, 
           CONCAT(u.first_name, ' ', u.last_name) as patient_name,
           mi.item_name,
           mi.unit
    FROM medicine_requests mr
    JOIN users u ON mr.patient_id = u.id
    JOIN medical_inventory mi ON mr.medicine_id = mi.id
    ORDER BY mr.request_date DESC
";
$medicine_requests = $pdo->query($medicine_requests_query)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Center Management</title>
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

        /* Add to the existing CSS */
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
            <h1>Medical Center Management</h1>
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
                        <li class="active"><a href="medical_management.php">Medical Center</a></li>
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
                        <button class="tab-btn active" onclick="openTab('appointments')">Appointments</button>
                        <button class="tab-btn" onclick="openTab('staff')">Medical Staff</button>
                        <button class="tab-btn" onclick="openTab('inventory')">Inventory</button>
                        <button class="tab-btn" onclick="openTab('medicineRequests')">Medicine Requests</button>
                        <button class="tab-btn" onclick="openTab('addStaff')">Add Staff</button>
                        <button class="tab-btn" onclick="openTab('addAppointment')">Add Appointment</button>
                        <button class="tab-btn" onclick="openTab('addInventory')">Add Inventory</button>
                    </div>

                    <div id="appointments" class="tab-content active">
                        <h2>Appointments</h2>

                        <!-- Appointment Filters -->
                        <div class="filter-container">
                            <form method="get" action="medical_management.php">
                                <input type="hidden" name="tab" value="appointments">
                                <div>
                                    <label for="appointment_status_filter">Status:</label>
                                    <select name="appointment_status_filter" id="appointment_status_filter">
                                        <option value="">All Statuses</option>
                                        <option value="scheduled" <?= isset($_GET['appointment_status_filter']) && $_GET['appointment_status_filter'] == 'scheduled' ? 'selected' : '' ?>>
                                            Scheduled</option>
                                        <option value="completed" <?= isset($_GET['appointment_status_filter']) && $_GET['appointment_status_filter'] == 'completed' ? 'selected' : '' ?>>
                                            Completed</option>
                                        <option value="cancelled" <?= isset($_GET['appointment_status_filter']) && $_GET['appointment_status_filter'] == 'cancelled' ? 'selected' : '' ?>>
                                            Cancelled</option>
                                        <option value="no_show" <?= isset($_GET['appointment_status_filter']) && $_GET['appointment_status_filter'] == 'no_show' ? 'selected' : '' ?>>No Show
                                        </option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn"
                                    onclick="resetAppointmentFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Staff</th>
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
                                        <td><?= htmlspecialchars($appt['patient_name']) ?></td>
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
                                            <div class="action-buttons">
                                                <button onclick="showAppointmentModal(<?= $appt['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteAppointmentModal(<?= $appt['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="staff" class="tab-content">
                        <h2>Medical Staff</h2>

                        <!-- Staff Filters -->
                        <div class="filter-container">
                            <form method="get" action="medical_management.php">
                                <input type="hidden" name="tab" value="staff">
                                <div>
                                    <label for="staff_specialization_filter">Specialization:</label>
                                    <input type="text" name="staff_specialization_filter"
                                        id="staff_specialization_filter"
                                        value="<?= isset($_GET['staff_specialization_filter']) ? htmlspecialchars($_GET['staff_specialization_filter']) : '' ?>"
                                        placeholder="Filter by specialization">
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
                                    <th>Specialization</th>
                                    <th>License Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medical_staff as $staff): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></td>
                                        <td><?= htmlspecialchars($staff['email']) ?></td>
                                        <td><?= htmlspecialchars($staff['specialization']) ?></td>
                                        <td><?= htmlspecialchars($staff['license_number']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showDeleteStaffModal(<?= $staff['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="inventory" class="tab-content">
                        <h2>Medical Inventory</h2>

                        <!-- Inventory Filters -->
                        <div class="filter-container">
                            <form method="get" action="medical_management.php">
                                <input type="hidden" name="tab" value="inventory">
                                <div>
                                    <label for="inventory_category_filter">Category:</label>
                                    <select name="inventory_category_filter" id="inventory_category_filter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>"
                                                <?= isset($_GET['inventory_category_filter']) && $_GET['inventory_category_filter'] == $category ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="inventory_expiry_filter">Expiry Status:</label>
                                    <select name="inventory_expiry_filter" id="inventory_expiry_filter">
                                        <option value="">All Items</option>
                                        <option value="expired" <?= isset($_GET['inventory_expiry_filter']) && $_GET['inventory_expiry_filter'] == 'expired' ? 'selected' : '' ?>>Expired
                                        </option>
                                        <option value="expiring_soon" <?= isset($_GET['inventory_expiry_filter']) && $_GET['inventory_expiry_filter'] == 'expiring_soon' ? 'selected' : '' ?>>
                                            Expiring Soon (30 days)</option>
                                    </select>
                                </div>
                                <button type="submit">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="resetInventoryFilters()">Reset</button>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Expiry Date</th>
                                    <th>Supplier</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category']) ?></td>
                                        <td><?= $item['quantity'] . ' ' ?></td>
                                        <td><?= $item['unit'] . ' ' ?></td>
                                        <td><?= $item['expiry_date'] ? date('M j, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['supplier']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="showInventoryModal(<?= $item['id'] ?>)"
                                                    class="logout-btn">Edit</button>
                                                <button onclick="showDeleteInventoryModal(<?= $item['id'] ?>)"
                                                    class="logout-btn-warning">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="medicineRequests" class="tab-content">
                        <h2>Medicine Requests</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicine_requests as $request): ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($request['request_date'])) ?></td>
                                        <td><?= htmlspecialchars($request['patient_name']) ?></td>
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
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($request['status'] == 'pending'): ?>
                                                    <button onclick="showApproveMedicineModal(<?= $request['id'] ?>)"
                                                        class="logout-btn">Approve</button>
                                                    <button onclick="showRejectMedicineModal(<?= $request['id'] ?>)"
                                                        class="logout-btn-warning">Reject</button>
                                                <?php elseif ($request['status'] == 'approved'): ?>
                                                    <button onclick="showCollectMedicineModal(<?= $request['id'] ?>)"
                                                        class="logout-btn">Mark Collected</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="addStaff" class="tab-content">
                        <h2>Add Medical Staff</h2>
                        <form method="post" action="medical_management.php">
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
                                <label for="specialization">Specialization</label>
                                <input type="text" name="specialization" id="specialization" required>
                            </div>
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" name="license_number" id="license_number">
                            </div>
                            <button type="submit" name="add_staff" class="logout-btn">Add Staff</button>
                        </form>
                    </div>

                    <div id="addAppointment" class="tab-content">
                        <h2>Schedule Appointment</h2>
                        <form method="post" action="medical_management.php">
                            <div class="form-group">
                                <label for="patient_id">Patient</label>
                                <select name="patient_id" id="patient_id" required>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="staff_id">Medical Staff</label>
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
                                <input type="date" name="appointment_date" id="appointment_date" required>
                            </div>
                            <div class="form-group">
                                <label for="appointment_time">Time</label>
                                <input type="time" name="appointment_time" id="appointment_time" required>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <textarea name="reason" id="reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="add_appointment" class="logout-btn">Schedule
                                Appointment</button>
                        </form>
                    </div>

                    <div id="addInventory" class="tab-content">
                        <h2>Add Inventory Item</h2>
                        <form method="post" action="medical_management.php">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" name="item_name" id="item_name" required>
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" name="category" id="category" required>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" name="quantity" id="quantity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <input type="text" name="unit" id="unit" required>
                            </div>
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date (if applicable)</label>
                                <input type="date" name="expiry_date" id="expiry_date">
                            </div>
                            <div class="form-group">
                                <label for="supplier">Supplier</label>
                                <input type="text" name="supplier" id="supplier">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" name="add_inventory" class="logout-btn">Add Item</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Appointment Management Modal -->
    <div class="modal" id="appointmentModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('appointmentModal')">&times;</span>
            <h2>Manage Appointment</h2>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">

                <div class="form-group">
                    <label for="modal_appointment_status">Status</label>
                    <select name="status" id="modal_appointment_status" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_appointment_diagnosis">Diagnosis</label>
                    <textarea name="diagnosis" id="modal_appointment_diagnosis" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="modal_appointment_prescription">Prescription</label>
                    <textarea name="prescription" id="modal_appointment_prescription" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="modal_appointment_notes">Notes</label>
                    <textarea name="notes" id="modal_appointment_notes" rows="3"></textarea>
                </div>

                <button type="submit" name="update_appointment" class="logout-btn">Update Appointment</button>
            </form>
        </div>
    </div>

    <!-- Delete Appointment Confirmation Modal -->
    <div class="modal" id="deleteAppointmentModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteAppointmentModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this appointment?</p>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="appointment_id" id="delete_appointment_id">
                <button type="submit" name="delete_appointment" class="logout-btn">Delete</button>
                <button type="button" onclick="hideModal('deleteAppointmentModal')"
                    class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Staff Confirmation Modal -->
    <div class="modal" id="deleteStaffModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteStaffModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this medical staff member?</p>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="staff_id" id="delete_staff_id">
                <button type="submit" name="delete_staff" class="logout-btn">Delete</button>
                <button type="button" onclick="hideModal('deleteStaffModal')" class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Inventory Management Modal -->
    <div class="modal" id="inventoryModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('inventoryModal')">&times;</span>
            <h2>Manage Inventory Item</h2>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="item_id" id="modal_item_id">

                <div class="form-group">
                    <label for="modal_item_quantity">Quantity</label>
                    <input type="number" name="quantity" id="modal_item_quantity" min="0" required>
                </div>

                <div class="form-group">
                    <label for="modal_item_expiry">Expiry Date</label>
                    <input type="date" name="expiry_date" id="modal_item_expiry">
                </div>

                <div class="form-group">
                    <label for="modal_item_notes">Notes</label>
                    <textarea name="notes" id="modal_item_notes" rows="3"></textarea>
                </div>

                <button type="submit" name="update_inventory" class="logout-btn">Update Item</button>
            </form>
        </div>
    </div>

    <!-- Delete Inventory Confirmation Modal -->
    <div class="modal" id="deleteInventoryModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('deleteInventoryModal')">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this inventory item?</p>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="item_id" id="delete_inventory_id">
                <button type="submit" name="delete_inventory" class="logout-btn">Delete</button>
                <button type="button" onclick="hideModal('deleteInventoryModal')"
                    class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Approve Medicine Modal -->
    <div class="modal" id="approveMedicineModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('approveMedicineModal')">&times;</span>
            <h2>Approve Medicine Request</h2>
            <p>Are you sure you want to approve this request?</p>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="request_id" id="approve_request_id">
                <button type="submit" name="approve_medicine" class="logout-btn">Approve</button>
                <button type="button" onclick="hideModal('approveMedicineModal')"
                    class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Reject Medicine Modal -->
    <div class="modal" id="rejectMedicineModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('rejectMedicineModal')">&times;</span>
            <h2>Reject Medicine Request</h2>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="request_id" id="reject_request_id">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="3" required></textarea>
                </div>
                <button type="submit" name="reject_medicine" class="logout-btn-warning">Reject</button>
                <button type="button" onclick="hideModal('rejectMedicineModal')" class="logout-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Collect Medicine Modal -->
    <div class="modal" id="collectMedicineModal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('collectMedicineModal')">&times;</span>
            <h2>Mark Medicine as Collected</h2>
            <p>Confirm the patient has collected the medicine?</p>
            <form method="post" action="medical_management.php">
                <input type="hidden" name="request_id" id="collect_request_id">
                <button type="submit" name="mark_collected" class="logout-btn">Confirm Collection</button>
                <button type="button" onclick="hideModal('collectMedicineModal')"
                    class="logout-btn-warning">Cancel</button>
            </form>
        </div>
    </div>

    <script src="/university-system/js/campus.js"></script>
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

        function showAppointmentModal(appointmentId) {
            // Find the appointment in our existing data
            const appointments = <?= json_encode($appointments) ?>;
            const appointment = appointments.find(a => a.id == appointmentId);

            if (appointment) {
                document.getElementById('modal_appointment_id').value = appointment.id;
                document.getElementById('modal_appointment_status').value = appointment.status;
                document.getElementById('modal_appointment_diagnosis').value = appointment.diagnosis || '';
                document.getElementById('modal_appointment_prescription').value = appointment.prescription || '';
                document.getElementById('modal_appointment_notes').value = appointment.notes || '';
                showModal('appointmentModal');
            }
        }

        function showDeleteAppointmentModal(appointmentId) {
            document.getElementById('delete_appointment_id').value = appointmentId;
            showModal('deleteAppointmentModal');
        }

        function showDeleteStaffModal(staffId) {
            document.getElementById('delete_staff_id').value = staffId;
            showModal('deleteStaffModal');
        }

        function showInventoryModal(itemId) {
            // Find the item in our existing data
            const inventory = <?= json_encode($inventory) ?>;
            const item = inventory.find(i => i.id == itemId);

            if (item) {
                document.getElementById('modal_item_id').value = item.id;
                document.getElementById('modal_item_quantity').value = item.quantity;
                document.getElementById('modal_item_expiry').value = item.expiry_date || '';
                document.getElementById('modal_item_notes').value = item.notes || '';
                showModal('inventoryModal');
            }
        }

        function showDeleteInventoryModal(itemId) {
            document.getElementById('delete_inventory_id').value = itemId;
            showModal('deleteInventoryModal');
        }

        // Reset filter functions
        function resetAppointmentFilters() {
            window.location.href = 'medical_management.php?tab=appointments';
        }

        function resetStaffFilters() {
            window.location.href = 'medical_management.php?tab=staff';
        }

        function resetInventoryFilters() {
            window.location.href = 'medical_management.php?tab=inventory';
        }

        // Add to the existing JavaScript
        function showApproveMedicineModal(requestId) {
            document.getElementById('approve_request_id').value = requestId;
            showModal('approveMedicineModal');
        }

        function showRejectMedicineModal(requestId) {
            document.getElementById('reject_request_id').value = requestId;
            showModal('rejectMedicineModal');
        }

        function showCollectMedicineModal(requestId) {
            document.getElementById('collect_request_id').value = requestId;
            showModal('collectMedicineModal');
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date();

            document.getElementById('appointment_date').valueAsDate = today;
            document.getElementById('appointment_time').value = '09:00';

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