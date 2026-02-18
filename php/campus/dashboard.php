<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /university-system/login.html");
    exit();
}

// Check if user has campus staff role
if ($_SESSION['role'] != 'campus') {
    header("Location: /university-system/unauthorized.php");
    exit();
}

// Get campus staff details
$staff_id = $_SESSION['user_id'];
$staff_query = $pdo->prepare("
    SELECT cs.*, u.first_name, u.last_name, u.email 
    FROM campus_staff cs 
    JOIN users u ON cs.user_id = u.id 
    WHERE cs.user_id = ?
");
$staff_query->execute([$staff_id]);
$staff = $staff_query->fetch();

// Get service statistics
$stats = [
    'hostel_occupancy' => $pdo->query("SELECT COUNT(*) FROM hostel_allocations WHERE status = 'active'")->fetchColumn(),
    'transport_routes' => $pdo->query("SELECT COUNT(*) FROM transport_routes WHERE status = 'active'")->fetchColumn(),
    'library_books' => $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'available'")->fetchColumn(),
    'medical_appointments' => $pdo->query("SELECT COUNT(*) FROM medical_appointments WHERE appointment_date = CURDATE()")->fetchColumn(),
    'canteen_orders' => $pdo->query("SELECT COUNT(*) FROM canteen_orders WHERE DATE(order_date) = CURDATE()")->fetchColumn(),
    'canteen_menu_items' => $pdo->query("SELECT COUNT(*) FROM canteen_menu WHERE status = 'available'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Services Dashboard</title>
    <link href="/university-system/css/campus.css" rel="stylesheet">
    <style>
        .main-panel {
            flex: 1;
            padding: 2rem;
            background-color: #f5f5f5;
        }
        
        .welcome-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }
        
        .welcome-card h2 {
            margin-top: 0;
            color: var(--primary);
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:nth-child(1) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(2) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(3) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(4) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(5) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(6) {
            border-top: 4px solid var(--primary);
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--primary);
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-action-btn {
            background: white;
            color:darkslateblue;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100px;
        }
        
        .quick-action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .recent-activities {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .recent-activities h2 {
            margin-top: 0;
            color: var(--dark);
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .activity-list {
            margin-top: 1rem;
        }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-details h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
        }
        
        .activity-details p {
            margin: 0 0 0.5rem 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .activity-details small {
            color: #999;
            font-size: 0.8rem;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Campus Services Dashboard</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></span>
                <a href="/university-system/php/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <aside class="sidebar">
                <nav>
                    <ul>
                        <li class="active"><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="hostel_management.php">Hostel Management</a></li>
                        <li><a href="transport_management.php">Transport Management</a></li>
                        <li><a href="library_management.php">Library Management</a></li>
                        <li><a href="medical_management.php">Medical Center</a></li>
                        <li><a href="canteen_management.php">Canteen Management</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-panel">
                <div class="welcome-card">
                    <h2>Welcome, <?= htmlspecialchars($staff['first_name']) ?></h2>
                    <p>Department: <?= htmlspecialchars($staff['department']) ?></p>
                    <p>Position: <?= htmlspecialchars($staff['position']) ?></p>
                    <p>Email: <?= htmlspecialchars($staff['email']) ?></p>
                </div>

                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="location.href='hostel_management.php?action=new'">
                        <i class="fas fa-bed"></i>
                        New Hostel Allocation
                    </button>
                    <button class="quick-action-btn" onclick="location.href='transport_management.php?action=add'">
                        <i class="fas fa-bus"></i>
                        Add Transport Route
                    </button>
                    <button class="quick-action-btn" onclick="location.href='library_management.php?action=add'">
                        <i class="fas fa-book"></i>
                        Add New Book
                    </button>
                    <button class="quick-action-btn" onclick="location.href='medical_management.php?action=schedule'">
                        <i class="fas fa-calendar-plus"></i>
                        Schedule Appointment
                    </button>
                    <button class="quick-action-btn" onclick="location.href='canteen_management.php?action=add_menu'">
                        <i class="fas fa-utensils"></i>
                        Add Menu Item
                    </button>
                </div>

                <div class="stats-overview">
                    <div class="stat-card" onclick="location.href='hostel_management.php'">
                        <h3>Hostel Occupancy</h3>
                        <div class="stat-value"><?= $stats['hostel_occupancy'] ?></div>
                        <p>Current residents</p>
                    </div>
                    
                    <div class="stat-card" onclick="location.href='transport_management.php'">
                        <h3>Active Routes</h3>
                        <div class="stat-value"><?= $stats['transport_routes'] ?></div>
                        <p>Transport routes</p>
                    </div>
                    
                    <div class="stat-card" onclick="location.href='library_management.php'">
                        <h3>Available Books</h3>
                        <div class="stat-value"><?= $stats['library_books'] ?></div>
                        <p>In library</p>
                    </div>
                    
                    <div class="stat-card" onclick="location.href='medical_management.php'">
                        <h3>Today's Appointments</h3>
                        <div class="stat-value"><?= $stats['medical_appointments'] ?></div>
                        <p>Medical center</p>
                    </div>
                    
                    <div class="stat-card" onclick="location.href='canteen_management.php'">
                        <h3>Today's Orders</h3>
                        <div class="stat-value"><?= $stats['canteen_orders'] ?></div>
                        <p>Canteen orders</p>
                    </div>
                    
                    <div class="stat-card" onclick="location.href='canteen_management.php?tab=menu'">
                        <h3>Menu Items</h3>
                        <div class="stat-value"><?= $stats['canteen_menu_items'] ?></div>
                        <p>Available items</p>
                    </div>
                </div>

                <div class="recent-activities">
                    <h2>Recent Activities</h2>
                    <div class="activity-list">
                        <?php
                        $activities = $pdo->query("
                            SELECT * FROM campus_activities 
                            ORDER BY activity_date DESC 
                            LIMIT 5
                        ")->fetchAll();
                        
                        foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php switch($activity['service_type']) {
                                        case 'hostel': echo '🏠'; break;
                                        case 'transport': echo '🚌'; break;
                                        case 'library': echo '📚'; break;
                                        case 'medical': echo '🏥'; break;
                                        case 'canteen': echo '🍽️'; break;
                                        default: echo 'ℹ️';
                                    } ?>
                                </div>
                                <div class="activity-details">
                                    <h4><?= htmlspecialchars($activity['title']) ?></h4>
                                    <p><?= htmlspecialchars($activity['description']) ?></p>
                                    <small><?= date('M j, Y H:i', strtotime($activity['activity_date'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>