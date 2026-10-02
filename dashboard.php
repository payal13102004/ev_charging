<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'book') {
        $station_id = $_POST['station_id'];
        $user_id = $_SESSION['user_id'];
        $date = $_POST['date'];
        $start_time = $_POST['start_time'];
        $duration_hours = $_POST['duration_hours'];
        
        // Check availability first
        $available_slots = checkAvailability($station_id, $date, $start_time, $duration_hours);
        
        if ($available_slots > 0) {
            // Generate a unique slot number
            $slot_number = uniqid('SL');
            
            // Calculate total amount
            $stmt = $pdo->prepare("SELECT price_per_hour FROM stations WHERE id = ?");
            $stmt->execute([$station_id]);
            $station = $stmt->fetch();
            $total_amount = $station['price_per_hour'] * $duration_hours;
            
            // Insert booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, station_id, slot_number, date, start_time, duration_hours, total_amount, status, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            
            if ($stmt->execute([$user_id, $station_id, $slot_number, $date, $start_time, $duration_hours, $total_amount])) {
                $booking_success = "Booking successful! Your slot number is: " . $slot_number;
            } else {
                $booking_error = "Booking failed. Please try again.";
            }
        } else {
            $booking_error = "No available slots for the selected time. Please choose a different time.";
        }
    }
    
    if ($_POST['action'] === 'cancel_booking') {
        $booking_id = $_POST['booking_id'];
        
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$booking_id, $_SESSION['user_id']])) {
            $booking_success = "Booking cancelled successfully.";
        } else {
            $booking_error = "Failed to cancel booking.";
        }
    }
    
    if ($_POST['action'] === 'pay_booking') {
        $booking_id = $_POST['booking_id'];
        
        $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$booking_id, $_SESSION['user_id']])) {
            $booking_success = "Payment successful! Your booking is now confirmed.";
        } else {
            $booking_error = "Payment failed. Please try again.";
        }
    }
}

// Handle AJAX availability check
if (isset($_GET['action']) && $_GET['action'] === 'check_availability') {
    $station_id = $_POST['station_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $duration_hours = $_POST['duration_hours'];
    
    $available_slots = checkAvailability($station_id, $date, $start_time, $duration_hours);
    echo json_encode(['available' => $available_slots]);
    exit();
}

// Get user bookings
$stmt = $pdo->prepare("
    SELECT b.*, s.name as station_name, s.address 
    FROM bookings b 
    JOIN stations s ON b.station_id = s.id 
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EV Charge</title>
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
            max-width: 1200px;
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
        
        .user-info {
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
            text-align: center;
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
        
        /* Bookings Table */
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .bookings-table th {
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
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
            max-width: 500px;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
            animation: modalFade 0.3s;
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
            
            .bookings-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-grid {
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
                <a href="index.php" class="logo">
                    <i>⚡</i> EV Charge
                </a>
                <div class="nav-links">
                    <a href="index.php" class="nav-link">Find Stations</a>
                    <div class="user-info">
                        <div class="avatar">
                            <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                        </div>
                        <span>Hello, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
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
                <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
                <p>Manage your EV charging bookings and profile</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($booking_success)): ?>
                <div class="alert alert-success"><?= $booking_success ?></div>
            <?php endif; ?>
            
            <?php if (isset($booking_error)): ?>
                <div class="alert alert-error"><?= $booking_error ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab <?= $active_tab === 'bookings' ? 'active' : '' ?>" onclick="switchTab('bookings')">My Bookings</div>
                <div class="tab <?= $active_tab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">Profile</div>
            </div>

            <!-- Bookings Tab -->
            <div id="bookings-tab" class="tab-content <?= $active_tab === 'bookings' ? 'active' : '' ?>">
                <?php if (count($bookings) > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">My Bookings</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="bookings-table">
                                <thead>
                                    <tr>
                                        <th>Slot #</th>
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
                                            <td><?= $booking['slot_number'] ?></td>
                                            <td>
                                                <div><strong><?= htmlspecialchars($booking['station_name']) ?></strong></div>
                                                <div style="font-size: 0.8rem; opacity: 0.7;"><?= htmlspecialchars($booking['address']) ?></div>
                                            </td>
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
                                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <?php if ($booking['status'] === 'pending' && $booking['payment_status'] === 'pending'): ?>
                                                        <button class="btn btn-success btn-small" onclick="openPaymentModal(<?= $booking['id'] ?>, <?= $booking['total_amount'] ?>)">Pay</button>
                                                        <button class="btn btn-danger btn-small" onclick="openCancelModal(<?= $booking['id'] ?>)">Cancel</button>
                                                    <?php elseif ($booking['status'] === 'pending' && $booking['payment_status'] === 'paid'): ?>
                                                        <span style="font-size: 0.8rem; opacity: 0.7;">Waiting approval</span>
                                                    <?php elseif ($booking['status'] === 'approved'): ?>
                                                        <span style="font-size: 0.8rem; opacity: 0.7;">Confirmed</span>
                                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                                        <span style="font-size: 0.8rem; opacity: 0.7;">Cancelled</span>
                                                    <?php elseif ($booking['status'] === 'completed'): ?>
                                                        <span style="font-size: 0.8rem; opacity: 0.7;">Completed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 40px;">
                        <h3>No bookings yet</h3>
                        <p>You haven't made any bookings yet. Find a station and book your first charging slot!</p>
                        <a href="index.php" class="btn" style="margin-top: 20px;">Find Stations</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-content <?= $active_tab === 'profile' ? 'active' : '' ?>">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profile Information</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h3 style="margin-bottom: 15px;">Personal Details</h3>
                            <p><strong>Name:</strong> <?= htmlspecialchars($_SESSION['user_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['user_email']) ?></p>
                            <p><strong>Member since:</strong> 
                                <?php
                                $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $user = $stmt->fetch();
                                echo date('M j, Y', strtotime($user['created_at']));
                                ?>
                            </p>
                        </div>
                        <div>
                            <h3 style="margin-bottom: 15px;">Booking Statistics</h3>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT 
                                    COUNT(*) as total_bookings,
                                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_spent
                                FROM bookings 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $stats = $stmt->fetch();
                            ?>
                            <p><strong>Total Bookings:</strong> <?= $stats['total_bookings'] ?></p>
                            <p><strong>Completed Bookings:</strong> <?= $stats['completed_bookings'] ?></p>
                            <p><strong>Total Spent:</strong> $<?= number_format($stats['total_spent'] ?? 0, 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cancel Booking Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <button class="close-modal" onclick="closeModal('cancelModal')">&times;</button>
            </div>
            <p>Are you sure you want to cancel this booking?</p>
            <form id="cancelForm" method="POST">
                <input type="hidden" name="action" value="cancel_booking">
                <input type="hidden" name="booking_id" id="cancel_booking_id">
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal('cancelModal')">Keep Booking</button>
                    <button type="submit" class="btn btn-danger">Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Complete Payment</h3>
                <button class="close-modal" onclick="closeModal('paymentModal')">&times;</button>
            </div>
            <p>Complete payment for your booking:</p>
            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin: 15px 0;">
                <p><strong>Amount to pay:</strong> $<span id="payment_amount">0.00</span></p>
            </div>
            <p style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 20px;">
                This is a simulated payment. In a real application, you would be redirected to a payment gateway.
            </p>
            <form id="paymentForm" method="POST">
                <input type="hidden" name="action" value="pay_booking">
                <input type="hidden" name="booking_id" id="payment_booking_id">
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal('paymentModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Payment</button>
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
        
        // Cancel booking modal
        function openCancelModal(bookingId) {
            document.getElementById('cancel_booking_id').value = bookingId;
            openModal('cancelModal');
        }
        
        // Payment modal
        function openPaymentModal(bookingId, amount) {
            document.getElementById('payment_booking_id').value = bookingId;
            document.getElementById('payment_amount').textContent = amount.toFixed(2);
            openModal('paymentModal');
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