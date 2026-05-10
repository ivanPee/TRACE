CREATE DATABASE IF NOT EXISTS trace_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trace_db;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (code, name) VALUES
('admin', 'Administrator'),
('driver', 'Driver'),
('parent', 'Parent'),
('student', 'Student');

INSERT INTO users (role_id, first_name, last_name, email, mobile_number, password_hash, status, is_verified)
SELECT id, 'System', 'Admin', 'admin@trace.test', '09000000000', '$2y$10$TBaiMFxt3f7QqGsSc6g77.nJnilxgjSf4CagE8E0l3J6i3/SiqKR2', 'active', 1
FROM roles
WHERE code = 'admin';

-- password

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mobile_number VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) NULL,
    status ENUM('pending', 'active', 'suspended', 'rejected') NOT NULL DEFAULT 'pending',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE parents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    address TEXT NOT NULL,
    valid_id_path VARCHAR(255) NULL,
    emergency_contact_name VARCHAR(150) NOT NULL,
    emergency_contact_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_parents_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    parent_id BIGINT UNSIGNED NOT NULL,
    lrn VARCHAR(30) NOT NULL UNIQUE,
    school_name VARCHAR(150) NOT NULL,
    grade_level VARCHAR(50) NOT NULL,
    pickup_address TEXT NOT NULL,
    dropoff_address TEXT NOT NULL,
    medical_notes TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
);

CREATE TABLE drivers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    license_number VARCHAR(50) NOT NULL UNIQUE,
    license_expiry DATE NOT NULL,
    license_photo_path VARCHAR(255) NOT NULL,
    license_verified TINYINT(1) NOT NULL DEFAULT 0,
    vehicle_type VARCHAR(50) NOT NULL,
    vehicle_plate_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_model VARCHAR(100) NOT NULL,
    vehicle_color VARCHAR(50) NOT NULL,
    vehicle_photo_path VARCHAR(255) NULL,
    approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    current_latitude DECIMAL(10, 7) NULL,
    current_longitude DECIMAL(10, 7) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drivers_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id BIGINT UNSIGNED NOT NULL,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    color VARCHAR(50) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 1,
    registration_path VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicles_driver FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

CREATE TABLE bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    pickup_address TEXT NOT NULL,
    dropoff_address TEXT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    trip_type ENUM('one_way', 'round_trip', 'recurring') NOT NULL DEFAULT 'one_way',
    notes TEXT NULL,
    booking_status ENUM(
        'pending',
        'approved',
        'assigned',
        'driver_arriving',
        'picked_up',
        'in_transit',
        'dropped_off',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    assigned_driver_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_parent FOREIGN KEY (parent_id) REFERENCES parents(id),
    CONSTRAINT fk_bookings_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_bookings_driver FOREIGN KEY (assigned_driver_id) REFERENCES drivers(id)
);

CREATE TABLE rides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL UNIQUE,
    driver_id BIGINT UNSIGNED NOT NULL,
    ride_status ENUM(
        'assigned',
        'driver_arriving',
        'arrived',
        'picked_up',
        'in_transit',
        'dropped_off',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'assigned',
    started_at DATETIME NULL,
    arrived_pickup_at DATETIME NULL,
    picked_up_at DATETIME NULL,
    dropped_off_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rides_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
    CONSTRAINT fk_rides_driver FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

CREATE TABLE ride_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ride_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    speed DECIMAL(6, 2) NULL,
    heading DECIMAL(6, 2) NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ride_locations_ride_recorded (ride_id, recorded_at),
    CONSTRAINT fk_ride_locations_ride FOREIGN KEY (ride_id) REFERENCES rides(id),
    CONSTRAINT fk_ride_locations_driver FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    receiver_user_id BIGINT UNSIGNED NOT NULL,
    ride_id BIGINT UNSIGNED NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('text', 'system', 'alert') NOT NULL DEFAULT 'text',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id),
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id),
    CONSTRAINT fk_messages_ride FOREIGN KEY (ride_id) REFERENCES rides(id)
);

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    reference_id BIGINT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE admin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_user_id) REFERENCES users(id)
);

