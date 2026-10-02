<?php
session_start();
require_once 'db.php';

// Redirect to login if not authenticated as admin
if (!isset($_SESSION['admin_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Simple admin authentication (in production, use more secure method)
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_name'] = 'Administrator';
        } else {
            $login_error = "Invalid admin credentials";
        }
    }
    
    // If still not logged in, show login form
    if (!isset($_SESSION['admin_id'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - EV Charge</title>
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                :root {
                    --primary: #4361ee;
                    --secondary: #3a0ca3;
                    --accent: #4cc9f0;
                    --light: #f8f9fa;
                    --dark: #212529;
                    --glass: rgba(255, 255, 255, 0.1);
                    --glass-border: rgba(255, 255, 255, 0.2);
                    --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Poppins', sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: var(--light);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .login-container {
                    width: 100%;
                    max-width: 400px;
                    padding: 0 20px;
                }
                
                .login-card {
                    background: var(--glass);
                    backdrop-filter: blur(15px);
                    border-radius: 20px;
                    padding: 40px;
                    box-shadow: var(--shadow);
                    border: 1px solid var(--glass-border);
                }
                
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                    font-size: 2rem;
                    font-weight: 700;
                }
                
                .form-group {
                    margin-bottom: 20px;
                }
                
                label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                }
                
                input {
                    width: 100%;
                    padding: 12px 16px;
                    border-radius: 10px;
                    border: 1px solid var(--glass-border);
                    background: rgba(255, 255, 255, 0.1);
                    color: white;
                    font-family: 'Poppins', sans-serif;
                }
                
                input::placeholder {
                    color: rgba(255, 255, 255, 0.7);
                }
                
                .btn {
                    width: 100%;
                    padding: 12px;
                    background: var(--primary);
                    color: white;
                    border: none;
                    border-radius: 10px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-family: 'Poppins', sans-serif;
                }
                
                .btn:hover {
                    background: var(--secondary);
                }
                
                .alert {
                    padding: 12px 16px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    background: rgba(220, 53, 69, 0.2);
                    border: 1px solid rgba(220, 53, 69, 0.5);
                }
                
                .note {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 0.9rem;
                    opacity: 0.7;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-card">
                    <div class="logo">
                        <i>⚡</i> EV Charge Admin
                    </div>
                    
                    <?php if (isset($login_error)): ?>
                        <div class="alert"><?= $login_error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn">Login</button>
                    </form>
                    
                    <div class="note">
                        Default credentials: admin / admin123
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_station':
                $name = trim($_POST['name']);
                $address = trim($_POST['address']);
                $total_slots = (int)$_POST['total_slots'];
                $price_per_hour = (float)$_POST['price_per_hour'];
                $charger_types = $_POST['charger_types'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO stations (name, address, total_slots, price_per_hour, charger_types, available) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                
                if ($stmt->execute([$name, $address, $total_slots, $price_per_hour, $charger_types])) {
                    $action_success = "Station added successfully";
                } else {
                    $action_error = "Failed to add station";
                }
                break;
                
            case 'edit_station':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $address = trim($_POST['address']);
                $total_slots = (int)$_POST['total_slots'];
                $price_per_hour = (float)$_POST['price_per_hour'];
                $charger_types = $_POST['charger_types'];
                $available = isset($_POST['available']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE stations 
                    SET name = ?, address = ?, total_slots = ?, price_per_hour = ?, charger_types = ?, available = ? 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$name, $address, $total_slots, $price_per_hour, $charger_types, $available, $id])) {
                    $action_success = "Station updated successfully";
                } else {
                    $action_error = "Failed to update station";
                }
                break;
                
            case 'delete_station':
                $id = (int)$_POST['id'];
                
                // Check if station has bookings
                $stmt = $pdo->prepare("SELECT COUNT(*) as booking_count FROM bookings WHERE station_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch();
                
                if ($result['booking_count'] > 0) {
                    $action_error = "Cannot delete station with existing bookings";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM stations WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $action_success = "Station deleted successfully";
                    } else {
                        $action_error = "Failed to delete station";
                    }
                }
                break;
                
            case 'update_booking_status':
                $id = (int)$_POST['id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $id])) {
                    $action_success = "Booking status updated successfully";
                } else {
                    $action_error = "Failed to update booking status";
                }
                break;
        }
    }
}

// Get data for dashboard
// Stations
$stations = $pdo->query("SELECT * FROM stations ORDER BY id DESC")->fetchAll();

// Bookings
$bookings = $pdo->query("
    SELECT b.*, u.name as user_name, u.email, s.name as station_name 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN stations s ON b.station_id = s.id 
    ORDER BY b.created_at DESC
")->fetchAll();

// Users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Analytics
$total_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$total_bookings = $pdo->query("SELECT COUNT(*) as count FROM bookings")->fetch()['count'];
$total_revenue = $pdo->query("SELECT SUM(total_amount) as revenue FROM bookings WHERE payment_status = 'paid'")->fetch()['revenue'] ?? 0;

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EV Charge</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4bb543;
            --warning: #ffcc00;
            --danger: #dc3545;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--light);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        header {
            padding: 20px 0;
            backdrop-filter: blur(10px);
            background: var(--glass);
            border-bottom: 1px solid var(--glass-border);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            color: var(--accent);
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-link {
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 50px;
            transition: var(--transition);
        }
        
        .nav-link:hover {
            background: var(--glass);
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Dashboard */
        .dashboard {
            padding: 40px 0;
        }
        
        .welcome-section {
            margin-bottom: 40px;
        }
        
        .welcome-section h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            opacity: 0.8;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: var(--glass);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .tab.active {
            background: var(--primary);
            border-color: var(--accent);
        }
        
        .tab:hover:not(.active) {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Cards */
        .card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .data-table th {
            font-weight: 600;
            opacity: 0.8;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(255, 204, 0, 0.2);
            color: var(--warning);
        }
        
        .status-approved {
            background: rgba(75, 181, 67, 0.2);
            color: var(--success);
        }
        
        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: var(--danger);
        }
        
        .status-completed {
            background: rgba(67, 97, 238, 0.2);
            color: var(--primary);
        }
        
        .payment-pending {
            background: rgba(255, 204, 0, 0.2);
            color: var(--warning);
        }
        
        .payment-paid {
            background: rgba(75, 181, 67, 0.2);
            color: var(--success);
        }
        
        .available-true {
            background: rgba(75, 181, 67, 0.2);
            color: var(--success);
        }
        
        .available-false {
            background: rgba(220, 53, 69, 0.2);
            color: var(--danger);
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-success:hover {
            background: #3d8b40;
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background: #e6b800;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        
        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
        }
        
        .alert-success {
            background: rgba(75, 181, 67, 0.2);
            border: 1px solid rgba(75, 181, 67, 0.5);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
            animation: modalFade 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @keyframes modalFade {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="admin.php" class="logo">
                    <i>⚡</i> EV Charge Admin
                </a>
                <div class="nav-links">
                    <a href="index.php" class="nav-link">View Site</a>
                    <div class="admin-info">
                        <div class="avatar">
                            A
                        </div>
                        <span>Welcome, <?= $_SESSION['admin_name'] ?></span>
                    </div>
                    <a href="?logout=1" class="nav-link">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Dashboard -->
    <section class="dashboard">
        <div class="container">
            <div class="welcome-section">
                <h1>Admin Dashboard</h1>
                <p>Manage stations, bookings, users, and view analytics</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($action_success)): ?>
                <div class="alert alert-success"><?= $action_success ?></div>
            <?php endif; ?>
            
            <?php if (isset($action_error)): ?>
                <div class="alert alert-error"><?= $action_error ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab <?= $active_tab === 'dashboard' ? 'active' : '' ?>" onclick="switchTab('dashboard')">Dashboard</div>
                <div class="tab <?= $active_tab === 'stations' ? 'active' : '' ?>" onclick="switchTab('stations')">Stations</div>
                <div class="tab <?= $active_tab === 'bookings' ? 'active' : '' ?>" onclick="switchTab('bookings')">Bookings</div>
                <div class="tab <?= $active_tab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')">Users</div>
            </div>

            <!-- Dashboard Tab -->
            <div id="dashboard-tab" class="tab-content <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_users ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_bookings ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?= number_format($total_revenue, 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($stations) ?></div>
                        <div class="stat-label">Charging Stations</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Bookings</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Slot #</th>
                                    <th>User</th>
                                    <th>Station</th>
                                    <th>Date & Time</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($bookings, 0, 5) as $booking): ?>
                                    <tr>
                                        <td><?= $booking['slot_number'] ?></td>
                                        <td>
                                            <div><?= htmlspecialchars($booking['user_name']) ?></div>
                                            <div style="font-size: 0.8rem; opacity: 0.7;"><?= $booking['email'] ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($booking['station_name']) ?></td>
                                        <td>
                                            <div><?= date('M j, Y', strtotime($booking['date'])) ?></div>
                                            <div style="font-size: 0.8rem; opacity: 0.7;"><?= date('g:i A', strtotime($booking['start_time'])) ?></div>
                                        </td>
                                        <td>$<?= number_format($booking['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $booking['status'] ?>">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge payment-<?= $booking['payment_status'] ?>">
                                                <?= ucfirst($booking['payment_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Stations Tab -->
            <div id="stations-tab" class="tab-content <?= $active_tab === 'stations' ? 'active' : '' ?>">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Charging Stations</h2>
                        <button class="btn" onclick="openStationModal()">Add New Station</button>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Slots</th>
                                    <th>Price/Hour</th>
                                    <th>Charger Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stations as $station): ?>
                                    <tr>
                                        <td><?= $station['id'] ?></td>
                                        <td><?= htmlspecialchars($station['name']) ?></td>
                                        <td><?= htmlspecialchars($station['address']) ?></td>
                                        <td><?= $station['total_slots'] ?></td>
                                        <td>$<?= $station['price_per_hour'] ?></td>
                                        <td><?= ucfirst($station['charger_types']) ?></td>
                                        <td>
                                            <span class="status-badge available-<?= $station['available'] ? 'true' : 'false' ?>">
                                                <?= $station['available'] ? 'Available' : 'Unavailable' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button class="btn btn-small" onclick="editStation(<?= $station['id'] ?>, '<?= htmlspecialchars($station['name']) ?>', '<?= htmlspecialchars($station['address']) ?>', <?= $station['total_slots'] ?>, <?= $station['price_per_hour'] ?>, '<?= $station['charger_types'] ?>', <?= $station['available'] ?>)">Edit</button>
                                                <button class="btn btn-danger btn-small" onclick="deleteStation(<?= $station['id'] ?>, '<?= htmlspecialchars($station['name']) ?>')">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bookings Tab -->
            <div id="bookings-tab" class="tab-content <?= $active_tab === 'bookings' ? 'active' : '' ?>">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">All Bookings</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Slot #</th>
                                    <th>User</th>
                                    <th>Station</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><?= $booking['id'] ?></td>
                                        <td><?= $booking['slot_number'] ?></td>
                                        <td>
                                            <div><?= htmlspecialchars($booking['user_name']) ?></div>
                                            <div style="font-size: 0.8rem; opacity: 0.7;"><?= $booking['email'] ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($booking['station_name']) ?></td>
                                        <td>
                                            <div><?= date('M j, Y', strtotime($booking['date'])) ?></div>
                                            <div style="font-size: 0.8rem; opacity: 0.7;"><?= date('g:i A', strtotime($booking['start_time'])) ?></div>
                                        </td>
                                        <td><?= $booking['duration_hours'] ?> hour<?= $booking['duration_hours'] > 1 ? 's' : '' ?></td>
                                        <td>$<?= number_format($booking['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $booking['status'] ?>">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge payment-<?= $booking['payment_status'] ?>">
                                                <?= ucfirst($booking['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                                <select onchange="updateBookingStatus(<?= $booking['id'] ?>, this.value)" style="padding: 6px; border-radius: 5px; background: rgba(255,255,255,0.1); color: white; border: 1px solid var(--glass-border);">
                                                    <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="approved" <?= $booking['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                    <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; opacity: 0.7;"><?= ucfirst($booking['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content <?= $active_tab === 'users' ? 'active' : '' ?>">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">All Users</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    // Get user's booking count
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as booking_count FROM bookings WHERE user_id = ?");
                                    $stmt->execute([$user['id']]);
                                    $booking_count = $stmt->fetch()['booking_count'];
                                    ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= $user['email'] ?></td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td><?= $booking_count ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Station Modal -->
    <div id="stationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="stationModalTitle">Add New Station</h3>
                <button class="close-modal" onclick="closeModal('stationModal')">&times;</button>
            </div>
            <form id="stationForm" method="POST">
                <input type="hidden" name="action" id="station_action" value="add_station">
                <input type="hidden" name="id" id="station_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="station_name">Station Name</label>
                        <input type="text" id="station_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="station_slots">Total Slots</label>
                        <input type="number" id="station_slots" name="total_slots" min="1" max="50" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="station_address">Address</label>
                    <textarea id="station_address" name="address" rows="3" required></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="station_price">Price per Hour ($)</label>
                        <input type="number" id="station_price" name="price_per_hour" step="0.01" min="0.5" max="50" required>
                    </div>
                    <div class="form-group">
                        <label for="station_charger_type">Charger Type</label>
                        <select id="station_charger_type" name="charger_types" required>
                            <option value="fast">Fast Charger</option>
                            <option value="slow">Slow Charger</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="station_available" name="available" checked>
                    <label for="station_available">Station Available for Booking</label>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal('stationModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Station</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Station Modal -->
    <div id="deleteStationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Station</h3>
                <button class="close-modal" onclick="closeModal('deleteStationModal')">&times;</button>
            </div>
            <p>Are you sure you want to delete station: <strong id="delete_station_name"></strong>?</p>
            <form id="deleteStationForm" method="POST">
                <input type="hidden" name="action" value="delete_station">
                <input type="hidden" name="id" id="delete_station_id">
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal('deleteStationModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Station</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update URL without reloading page
            history.pushState(null, null, '?tab=' + tabName);
        }
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Station management
        function openStationModal() {
            document.getElementById('stationModalTitle').textContent = 'Add New Station';
            document.getElementById('station_action').value = 'add_station';
            document.getElementById('station_id').value = '';
            document.getElementById('stationForm').reset();
            document.getElementById('station_available').checked = true;
            openModal('stationModal');
        }
        
        function editStation(id, name, address, slots, price, chargerType, available) {
            document.getElementById('stationModalTitle').textContent = 'Edit Station';
            document.getElementById('station_action').value = 'edit_station';
            document.getElementById('station_id').value = id;
            document.getElementById('station_name').value = name;
            document.getElementById('station_address').value = address;
            document.getElementById('station_slots').value = slots;
            document.getElementById('station_price').value = price;
            document.getElementById('station_charger_type').value = chargerType;
            document.getElementById('station_available').checked = available;
            openModal('stationModal');
        }
        
        function deleteStation(id, name) {
            document.getElementById('delete_station_id').value = id;
            document.getElementById('delete_station_name').textContent = name;
            openModal('deleteStationModal');
        }
        
        // Booking management
        function updateBookingStatus(bookingId, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'update_booking_status';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.name = 'id';
            idInput.value = bookingId;
            form.appendChild(idInput);
            
            const statusInput = document.createElement('input');
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>