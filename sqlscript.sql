-- EV Charging Booking System Database Setup
-- Run this script in your MySQL database to create the necessary tables and sample data

CREATE DATABASE IF NOT EXISTS ev_charging;
USE ev_charging;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Charging stations table
CREATE TABLE IF NOT EXISTS stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    total_slots INT NOT NULL,
    price_per_hour DECIMAL(10,2) NOT NULL,
    charger_types ENUM('fast', 'slow') NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    station_id INT NOT NULL,
    slot_number VARCHAR(20) UNIQUE NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_hours INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- Insert sample users
INSERT INTO users (name, email, password) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password
('Mike Johnson', 'mike@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password

-- Insert sample stations
INSERT INTO stations (name, address, total_slots, price_per_hour, charger_types) VALUES
('Downtown Charging Hub', '123 Main Street, Downtown, City Center', 10, 12.50, 'fast'),
('Mall EV Station', '456 Shopping Mall, North District', 8, 8.75, 'slow'),
('Highway Rest Stop Charger', 'Highway 101, Mile Marker 45', 12, 15.00, 'fast'),
('University Campus Station', '789 University Avenue, West Campus', 6, 7.50, 'slow');

-- Insert sample bookings
INSERT INTO bookings (user_id, station_id, slot_number, date, start_time, duration_hours, total_amount, status, payment_status) VALUES
(1, 1, 'SLOT001', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 2, 25.00, 'approved', 'paid'),
(2, 2, 'SLOT002', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:30:00', 3, 26.25, 'pending', 'pending'),
(3, 1, 'SLOT003', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', 1, 12.50, 'completed', 'paid'),
(1, 3, 'SLOT004', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '16:00:00', 2, 30.00, 'approved', 'paid');