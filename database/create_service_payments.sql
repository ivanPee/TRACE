CREATE TABLE IF NOT EXISTS service_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    monthly_plan_id BIGINT UNSIGNED NULL,
    billing_month DATE NOT NULL,
    route_count INT UNSIGNED NOT NULL DEFAULT 0,
    service_days INT UNSIGNED NOT NULL DEFAULT 0,
    daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100) NULL,
    status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
    route_snapshot TEXT NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_payments_parent_month (parent_id, billing_month),
    INDEX idx_service_payments_plan (monthly_plan_id),
    CONSTRAINT fk_service_payments_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
);

ALTER TABLE service_payments
    DROP INDEX IF EXISTS uniq_service_payments_parent_month,
    ADD COLUMN IF NOT EXISTS monthly_plan_id BIGINT UNSIGNED NULL AFTER parent_id;
