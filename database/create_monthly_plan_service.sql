ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS monthly_plan_id BIGINT UNSIGNED NULL AFTER recurring_group_id,
    ADD COLUMN IF NOT EXISTS monthly_plan_route_id BIGINT UNSIGNED NULL AFTER monthly_plan_id,
    ADD COLUMN IF NOT EXISTS driver_payout_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER monthly_plan_route_id;

CREATE TABLE IF NOT EXISTS monthly_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    billing_month DATE NOT NULL,
    destination_key VARCHAR(80) NULL,
    destination_address TEXT NULL,
    destination_latitude DECIMAL(10, 7) NULL,
    destination_longitude DECIMAL(10, 7) NULL,
    route_count INT UNSIGNED NOT NULL DEFAULT 0,
    child_count INT UNSIGNED NOT NULL DEFAULT 0,
    service_days INT UNSIGNED NOT NULL DEFAULT 0,
    gross_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    net_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    monthly_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    scheduled_time TIME NOT NULL DEFAULT '07:00:00',
    status ENUM('draft', 'active', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monthly_plans_parent_month (parent_id, billing_month),
    INDEX idx_monthly_plans_destination (parent_id, billing_month, destination_key),
    CONSTRAINT fk_monthly_plans_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
);

ALTER TABLE monthly_plans
    DROP INDEX IF EXISTS uniq_monthly_plans_parent_month,
    ADD COLUMN IF NOT EXISTS destination_key VARCHAR(80) NULL AFTER billing_month,
    ADD COLUMN IF NOT EXISTS destination_address TEXT NULL AFTER destination_key,
    ADD COLUMN IF NOT EXISTS destination_latitude DECIMAL(10, 7) NULL AFTER destination_address,
    ADD COLUMN IF NOT EXISTS destination_longitude DECIMAL(10, 7) NULL AFTER destination_latitude;

CREATE TABLE IF NOT EXISTS monthly_plan_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monthly_plan_id BIGINT UNSIGNED NOT NULL,
    destination_key VARCHAR(80) NOT NULL,
    destination_address TEXT NOT NULL,
    destination_latitude DECIMAL(10, 7) NULL,
    destination_longitude DECIMAL(10, 7) NULL,
    child_count INT UNSIGNED NOT NULL DEFAULT 0,
    gross_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    net_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_monthly_plan_routes_destination (monthly_plan_id, destination_key),
    CONSTRAINT fk_monthly_plan_routes_plan FOREIGN KEY (monthly_plan_id) REFERENCES monthly_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS monthly_plan_route_students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monthly_plan_route_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    pickup_address TEXT NOT NULL,
    pickup_latitude DECIMAL(10, 7) NULL,
    pickup_longitude DECIMAL(10, 7) NULL,
    distance_km DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    gross_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    net_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_monthly_plan_route_students (monthly_plan_route_id, student_id),
    CONSTRAINT fk_monthly_plan_route_students_route FOREIGN KEY (monthly_plan_route_id) REFERENCES monthly_plan_routes(id) ON DELETE CASCADE,
    CONSTRAINT fk_monthly_plan_route_students_student FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS driver_payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id BIGINT UNSIGNED NOT NULL,
    ride_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'paid',
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_driver_payouts_ride (ride_id),
    CONSTRAINT fk_driver_payouts_driver FOREIGN KEY (driver_id) REFERENCES drivers(id),
    CONSTRAINT fk_driver_payouts_ride FOREIGN KEY (ride_id) REFERENCES rides(id),
    CONSTRAINT fk_driver_payouts_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

CREATE TABLE IF NOT EXISTS monthly_plan_refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    monthly_plan_id BIGINT UNSIGNED NOT NULL,
    monthly_plan_route_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NULL,
    scheduled_date DATE NOT NULL,
    refund_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    reason TEXT NULL,
    reference_number VARCHAR(80) NULL,
    status ENUM('pending', 'approved', 'paid', 'cancelled') NOT NULL DEFAULT 'approved',
    refunded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_monthly_plan_refunds_parent (parent_id, scheduled_date),
    INDEX idx_monthly_plan_refunds_plan_day (monthly_plan_id, monthly_plan_route_id, scheduled_date),
    CONSTRAINT fk_monthly_plan_refunds_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
);
