<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Fetch dynamic data from database
try {
    // Hostel availability
    $hostelStmt = $pdo->prepare("SELECT COUNT(*) as available_rooms FROM hostel_rooms WHERE status = 'available'");
    $hostelStmt->execute();
    $hostelData = $hostelStmt->fetch(PDO::FETCH_ASSOC);

    // Library book count
    $libraryStmt = $pdo->prepare("SELECT COUNT(*) as book_count FROM library_books");
    $libraryStmt->execute();
    $libraryData = $libraryStmt->fetch(PDO::FETCH_ASSOC);

    // Open positions
    $recruitmentStmt = $pdo->prepare("SELECT COUNT(*) as open_positions FROM recruitment WHERE status = 'open'");
    $recruitmentStmt->execute();
    $recruitmentData = $recruitmentStmt->fetch(PDO::FETCH_ASSOC);

    // Available doctors
    $medicalStmt = $pdo->prepare("SELECT COUNT(*) as available_doctors FROM medical_staff WHERE status = 'available'");
    $medicalStmt->execute();
    $medicalData = $medicalStmt->fetch(PDO::FETCH_ASSOC);

    // Active transport routes
    $transportStmt = $pdo->prepare("SELECT COUNT(*) as active_routes FROM transport_routes WHERE status = 'active'");
    $transportStmt->execute();
    $transportData = $transportStmt->fetch(PDO::FETCH_ASSOC);

    // Recent announcements
    $announcementStmt = $pdo->prepare("SELECT title, content, created_at FROM announcements ORDER BY created_at DESC LIMIT 2");
    $announcementStmt->execute();
    $announcements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error and set default values
    error_log("Database error: " . $e->getMessage());
    $hostelData = ['available_rooms' => 0];
    $libraryData = ['book_count' => 0];
    $recruitmentData = ['open_positions' => 0];
    $medicalData = ['available_doctors' => 0];
    $transportData = ['active_routes' => 0];
    $announcements = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Services Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="/university-system/css/style.css" rel="stylesheet">
    <<style>
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
        background-color: rgba(255, 255, 255, 0.98); /* Semi-transparent white */
        padding: 1rem 2rem;
        position: sticky;
        top: 0;
        z-index: 1000; /* Higher z-index to ensure it stays on top */
        backdrop-filter: blur(8px); /* Adds a subtle blur effect to content behind */
        -webkit-backdrop-filter: blur(8px); /* For Safari support */
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

        .theme-toggle {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0.5rem;
        margin-left: 0.5rem;
        line-height: 1;
        }

        /* Content Area Styles */
        .dashboard-content {
        padding: 1.25rem 2rem;
        max-width: 100%;
        box-sizing: border-box;
        }

        .quick-links {
        margin: 0.5rem 0 1.5rem 0;
        }

        .quick-links h2 {
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        }

        /* Services Grid */
        .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        }

        .service-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
        }

        .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .service-icon {
        font-size: 2rem;
        color: #3a7bd5;
        margin-bottom: 1rem;
        }

        .service-stats {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
        font-size: 0.9rem;
        color: #666;
        }

        /* Announcements */
        .announcements {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .announcement-card {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
        }

        .announcement-card:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
        }

        .announcement-date {
        font-size: 0.8rem;
        color: #888;
        margin-bottom: 0.5rem;
        }

        /* Quick Links Grid */
        .links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        }

        .link-card {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        text-decoration: none;
        color: #333;
        }

        .link-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .link-card i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #3a7bd5;
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

        /* Responsive Styles */
        @media (max-width: 992px) {
        .services-grid {
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
        .services-grid {
        grid-template-columns: 1fr;
        }

        .links-grid {
        grid-template-columns: 1fr 1fr;
        }

        .dashboard-header {
        padding: 1rem;
        }
        }
        </style>
</head>

<body>
    <!-- Sidebar -->
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-university"></i> University</h2>
        </div>
        <div class="sidebar-menu">
            <a href="/university-system/index.html" class="menu-item">
                <i class="fas fa-home"></i> Home
            </a>
            <?php if ($isLoggedIn): ?>
                <?php if (isset($_SESSION['role'])): ?>
                    <a href="/university-system/php/<?= htmlspecialchars($_SESSION['role']) ?>/dashboard.php"
                        class="menu-item active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="/university-system/php/dashboard.php" class="menu-item active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                <?php endif; ?>
                <a href="#quick-links" class="menu-item"
                    onclick="document.getElementById('quick-links').scrollIntoView({ behavior: 'smooth' }); return false;">
                    <i class="fas fa-rocket"></i> Quick Links
                </a>
                <a href="#announcements" class="menu-item"
                    onclick="document.getElementById('announcements').scrollIntoView({ behavior: 'smooth' }); return false;">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>

            <?php else: ?>
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
                    <h1>University Services</h1>
                    <div class="user-actions">
                        <?php if ($isLoggedIn): ?>
                            <span class="welcome-msg">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                            <a href="/university-system/php/auth/logout.php" class="btn btn-primary">Logout</a>
                        <?php else: ?>
                            <a href="/university-system/login.html" class="btn btn-primary">Login</a>
                            <a href="/university-system/register.html" class="btn btn-outline">Register</a>
                        <?php endif; ?>
                        <button class="theme-toggle" onclick="toggleMode()">
                            <span class="theme-icon">🌙</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="dashboard-content">
                <?php if ($isLoggedIn): ?>
                    <section class="quick-links" id="quick-links">
                        <h2><i class="fas fa-rocket"></i> Quick Links</h2>
                        <div class="links-grid">
                            <?php if (isset($_SESSION['role'])): ?>
                                <a href="/university-system/php/<?= htmlspecialchars($_SESSION['role']) ?>/dashboard.php"
                                    class="link-card">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>My Dashboard</span>
                                </a>

                            <?php endif; ?>
                            <a href="/university-system/php/public/canteen.php" class="link-card">
                                <i class="fas fa-concierge-bell"></i>
                                <span>Canteen</span>
                            </a>
                            <a href="/university-system/php/public/library.php" class="link-card">
                                <i class="fas fa-book"></i>
                                <span>Library</span>
                            </a>
                            <a href="/university-system/index.html" class="link-card">
                                <i class="fas fa-home"></i>
                                <span>Home</span>
                            </a>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="services-grid">
                    <!-- Hostel Service -->
                    <div class="service-card" data-target="hostel.php">
                        <div class="service-icon">
                            <i class="fas fa-bed"></i>
                        </div>
                        <h3>Hostel Booking</h3>
                        <p>Find and book accommodation in university hostels</p>
                        <div class="service-stats">
                            <span><i class="fas fa-home"></i> <?= $hostelData['available_rooms'] ?> Rooms
                                Available</span>
                        </div>
                    </div>

                    <!-- Library Service -->
                    <div class="service-card" data-target="library.php">
                        <div class="service-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>Library</h3>
                        <p>Browse, search and borrow books from our collection</p>
                        <div class="service-stats">
                            <span><i class="fas fa-book-open"></i> <?= $libraryData['book_count'] ?> Books</span>
                        </div>
                    </div>

                    <!-- Canteen Service -->
                    <div class="service-card" data-target="canteen.php">
                        <div class="service-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h3>Canteen</h3>
                        <p>Order food from university canteen</p>
                        <div class="service-stats">
                            <span><i class="fas fa-shopping-cart"></i> <span class="cart-count"
                                    style="display: none;">0</span> Items</span>
                        </div>
                    </div>

                    <!-- Recruitment Service -->
                    <div class="service-card" data-target="recruitment.php">
                        <div class="service-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3>Recruitment</h3>
                        <p>Find job opportunities within the university</p>
                        <div class="service-stats">
                            <span><i class="fas fa-list"></i> <?= $recruitmentData['open_positions'] ?> Open
                                Positions</span>
                        </div>
                    </div>

                    <!-- Medical Service -->
                    <div class="service-card" data-target="medical.php">
                        <div class="service-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h3>Medical Center</h3>
                        <p>Book appointments with university doctors</p>
                        <div class="service-stats">
                            <span><i class="fas fa-user-md"></i> <?= $medicalData['available_doctors'] ?> Doctors
                                Available</span>
                        </div>
                    </div>

                    <!-- Transport Service -->
                    <div class="service-card" data-target="transport.php">
                        <div class="service-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <h3>Transport</h3>
                        <p>Book university transport services</p>
                        <div class="service-stats">
                            <span><i class="fas fa-route"></i> <?= $transportData['active_routes'] ?> Active
                                Routes</span>
                        </div>
                    </div>
                </section>

                <section class="announcements" id="announcements">
                    <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <div class="announcement-date"><?= date('M j, Y', strtotime($announcement['created_at'])) ?>
                                </div>
                                <h4><?= htmlspecialchars($announcement['title']) ?></h4>
                                <p><?= htmlspecialchars($announcement['content']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="announcement-card">
                            <p>No recent announcements</p>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <!-- Bee Cursor Element -->
    <div id="bee-cursor">🐝</div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script src="/university-system/js/main.js"></script>
    <script>
        // Redirect service cards to their respective pages
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('click', function () {
                const target = this.getAttribute('data-target');
                window.location.href = '/university-system/php/' + target;
            });
        });
    </script>
</body>

</html>