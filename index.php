<?php
session_start();
require_once 'db.php';

// Handle user registration
if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $register_error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $register_error = "Email already registered";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $register_success = "Registration successful! Please login.";
            } else {
                $register_error = "Registration failed. Please try again.";
            }
        }
    }
}

// Handle user login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = 'user';
        header("Location: dashboard.php");
        exit();
    } else {
        $login_error = "Invalid email or password";
    }
}

// Handle station search
$stations = [];
if (isset($_GET['search'])) {
    $location = trim($_GET['location']);
    $charger_type = $_GET['charger_type'];
    
    $query = "SELECT * FROM stations WHERE 1=1";
    $params = [];
    
    if (!empty($location)) {
        $query .= " AND (name LIKE ? OR address LIKE ?)";
        $search_term = "%$location%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($charger_type) && $charger_type !== 'all') {
        $query .= " AND charger_types LIKE ?";
        $params[] = "%$charger_type%";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stations = $stmt->fetchAll();
} else {
    // Show all stations by default
    $stmt = $pdo->query("SELECT * FROM stations");
    $stations = $stmt->fetchAll();
}

// Check availability for a specific station and time
function checkAvailability($station_id, $date, $start_time, $duration_hours) {
    global $pdo;
    
    // Calculate end time
    $start_datetime = "$date $start_time";
    $end_datetime = date('Y-m-d H:i:s', strtotime("$start_datetime + $duration_hours hours"));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as booked_slots 
        FROM bookings 
        WHERE station_id = ? 
        AND status NOT IN ('cancelled') 
        AND (
            (start_time <= ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) > ?) OR
            (start_time < ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) >= ?) OR
            (start_time >= ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) <= ?)
        )
    ");
    
    $stmt->execute([$station_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
    $result = $stmt->fetch();
    
    // Get total slots for the station
    $stmt = $pdo->prepare("SELECT total_slots FROM stations WHERE id = ?");
    $stmt->execute([$station_id]);
    $station = $stmt->fetch();
    
    return $station['total_slots'] - $result['booked_slots'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV Charge - Book Your Charging Slot</title>
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
        
        /* Header & Navigation */
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
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
        }
        
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Hero Section */
        .hero {
            padding: 100px 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 40px;
            opacity: 0.9;
        }
        
        /* Search Section */
        .search-section {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 50px;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select {
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
        
        /* Stations Grid */
        .stations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .station-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
            transition: var(--transition);
        }
        
        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        .station-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .station-name {
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .station-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .station-address {
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .station-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .detail-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .detail-label {
            font-size: 0.8rem;
            opacity: 0.7;
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
        
        /* Auth Forms */
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-footer {
            margin-top: 20px;
            text-align: center;
        }
        
        .form-footer a {
            color: var(--accent);
            text-decoration: none;
        }
        
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .stations-grid {
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="nav-link">Dashboard</a>
                        <a href="dashboard.php?logout=1" class="nav-link">Logout</a>
                    <?php else: ?>
                        <a href="#" class="nav-link" onclick="openModal('loginModal')">Login</a>
                        <a href="#" class="btn btn-outline" onclick="openModal('registerModal')">Sign Up</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Charge Your EV, Power Your Journey</h1>
            <p>Find and book available charging stations near you. Fast, reliable, and convenient electric vehicle charging solutions.</p>
            <a href="#search" class="btn">Find Stations</a>
        </div>
    </section>

    <!-- Search Section -->
    <section id="search" class="container">
        <div class="search-section">
            <h2 style="margin-bottom: 20px;">Find Charging Stations</h2>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="Enter city or address" value="<?= isset($_GET['location']) ? htmlspecialchars($_GET['location']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="charger_type">Charger Type</label>
                    <select id="charger_type" name="charger_type">
                        <option value="all">All Types</option>
                        <option value="fast" <?= (isset($_GET['charger_type']) && $_GET['charger_type'] == 'fast') ? 'selected' : '' ?>>Fast Charger</option>
                        <option value="slow" <?= (isset($_GET['charger_type']) && $_GET['charger_type'] == 'slow') ? 'selected' : '' ?>>Slow Charger</option>
                    </select>
                </div>
                <div class="form-group" style="justify-content: flex-end;">
                    <button type="submit" name="search" class="btn" style="margin-top: 24px;">Search</button>
                </div>
            </form>
        </div>

        <!-- Stations List -->
        <div class="stations-grid">
            <?php if (count($stations) > 0): ?>
                <?php foreach ($stations as $station): ?>
                    <div class="station-card">
                        <div class="station-header">
                            <h3 class="station-name"><?= htmlspecialchars($station['name']) ?></h3>
                            <div class="station-price">$<?= $station['price_per_hour'] ?>/hr</div>
                        </div>
                        <p class="station-address"><?= htmlspecialchars($station['address']) ?></p>
                        <div class="station-details">
                            <div class="detail-item">
                                <span class="detail-value"><?= $station['total_slots'] ?></span>
                                <span class="detail-label">Total Slots</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-value"><?= ucfirst($station['charger_types']) ?></span>
                                <span class="detail-label">Charger Type</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-value"><?= $station['available'] ? 'Available' : 'Busy' ?></span>
                                <span class="detail-label">Status</span>
                            </div>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="btn" onclick="openBookingModal(<?= $station['id'] ?>, '<?= htmlspecialchars($station['name']) ?>', <?= $station['price_per_hour'] ?>)">Book Now</button>
                        <?php else: ?>
                            <button class="btn" onclick="openModal('loginModal')">Login to Book</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <h3>No stations found matching your criteria</h3>
                    <p>Try adjusting your search filters</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Login to Your Account</h3>
                <button class="close-modal" onclick="closeModal('loginModal')">&times;</button>
            </div>
            <?php if (isset($login_error)): ?>
                <div class="alert alert-error"><?= $login_error ?></div>
            <?php endif; ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="login_email">Email</label>
                    <input type="email" id="login_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn">Login</button>
            </form>
            <div class="form-footer">
                <p>Don't have an account? <a href="#" onclick="closeModal('loginModal'); openModal('registerModal')">Sign up</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create an Account</h3>
                <button class="close-modal" onclick="closeModal('registerModal')">&times;</button>
            </div>
            <?php if (isset($register_error)): ?>
                <div class="alert alert-error"><?= $register_error ?></div>
            <?php endif; ?>
            <?php if (isset($register_success)): ?>
                <div class="alert alert-success"><?= $register_success ?></div>
            <?php endif; ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="register_name">Full Name</label>
                    <input type="text" id="register_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="register_email">Email</label>
                    <input type="email" id="register_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="register_password">Password</label>
                    <input type="password" id="register_password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" name="register" class="btn">Sign Up</button>
            </form>
            <div class="form-footer">
                <p>Already have an account? <a href="#" onclick="closeModal('registerModal'); openModal('loginModal')">Login</a></p>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Book Charging Slot</h3>
                <button class="close-modal" onclick="closeModal('bookingModal')">&times;</button>
            </div>
            <form id="bookingForm" method="POST" action="dashboard.php">
                <input type="hidden" name="station_id" id="booking_station_id">
                <input type="hidden" name="action" value="book">
                
                <div class="form-group">
                    <label for="station_name">Station</label>
                    <input type="text" id="station_name" readonly style="background: rgba(255,255,255,0.1);">
                </div>
                
                <div class="form-group">
                    <label for="booking_date">Date</label>
                    <input type="date" id="booking_date" name="date" min="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="duration_hours">Duration (hours)</label>
                    <select id="duration_hours" name="duration_hours" required>
                        <option value="1">1 hour</option>
                        <option value="2">2 hours</option>
                        <option value="3">3 hours</option>
                        <option value="4">4 hours</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Price Estimate</label>
                    <div id="price_estimate" style="padding: 10px; background: rgba(255,255,255,0.1); border-radius: 10px; text-align: center; font-weight: 600;">
                        $0.00
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Available Slots</label>
                    <div id="available_slots" style="padding: 10px; background: rgba(255,255,255,0.1); border-radius: 10px; text-align: center;">
                        Select date and time to check availability
                    </div>
                </div>
                
                <button type="submit" class="btn" id="book_btn" disabled>Book Now</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Booking modal functions
        function openBookingModal(stationId, stationName, pricePerHour) {
            document.getElementById('booking_station_id').value = stationId;
            document.getElementById('station_name').value = stationName;
            document.getElementById('bookingModal').style.display = 'flex';
            
            // Set up event listeners for availability check
            const dateInput = document.getElementById('booking_date');
            const timeInput = document.getElementById('start_time');
            const durationSelect = document.getElementById('duration_hours');
            const priceEstimate = document.getElementById('price_estimate');
            const availableSlots = document.getElementById('available_slots');
            const bookBtn = document.getElementById('book_btn');
            
            function checkAvailability() {
                const date = dateInput.value;
                const time = timeInput.value;
                const duration = durationSelect.value;
                
                if (date && time && duration) {
                    // Calculate price
                    const price = (pricePerHour * duration).toFixed(2);
                    priceEstimate.textContent = `$${price}`;
                    
                    // Check availability via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'dashboard.php?action=check_availability', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            availableSlots.textContent = `${response.available} slots available`;
                            
                            if (response.available > 0) {
                                availableSlots.style.background = 'rgba(75, 181, 67, 0.2)';
                                availableSlots.style.border = '1px solid rgba(75, 181, 67, 0.5)';
                                bookBtn.disabled = false;
                            } else {
                                availableSlots.style.background = 'rgba(220, 53, 69, 0.2)';
                                availableSlots.style.border = '1px solid rgba(220, 53, 69, 0.5)';
                                bookBtn.disabled = true;
                            }
                        }
                    };
                    xhr.send(`station_id=${stationId}&date=${date}&start_time=${time}&duration_hours=${duration}`);
                }
            }
            
            dateInput.onchange = checkAvailability;
            timeInput.onchange = checkAvailability;
            durationSelect.onchange = checkAvailability;
        }
        
        // Set minimum time to current time for today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('booking_date').min = today;
            
            // If today is selected, set min time to current time
            document.getElementById('booking_date').addEventListener('change', function() {
                const selectedDate = this.value;
                const now = new Date();
                const today = now.toISOString().split('T')[0];
                
                if (selectedDate === today) {
                    const currentHour = now.getHours().toString().padStart(2, '0');
                    const currentMinute = now.getMinutes().toString().padStart(2, '0');
                    document.getElementById('start_time').min = `${currentHour}:${currentMinute}`;
                } else {
                    document.getElementById('start_time').min = '00:00';
                }
            });
        });
    </script>
</body>
</html>