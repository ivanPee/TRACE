<?php

session_start();

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function array_get($array, $key, $default = '')
{
    return isset($array[$key]) ? $array[$key] : $default;
}

function admin_config()
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(dirname(__DIR__)) . '/backend/config/config.php';
    }

    return $config;
}

function app_base_url()
{
    $configured = rtrim((string) array_get(admin_config(), 'base_url', ''), '/');

    if ($configured !== '') {
        return $configured;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminPos = strpos($scriptName, '/admin/');

    if ($adminPos !== false) {
        return substr($scriptName, 0, $adminPos);
    }

    return '';
}

function asset_url($path)
{
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $relativePath = ltrim($path, '/');

    if (strpos($relativePath, 'storage/uploads/') === 0) {
        $relativePath = 'backend/' . $relativePath;
    }

    return app_base_url() . '/' . $relativePath;
}

function render_document_link($path, $label = 'View document')
{
    $url = asset_url($path);

    if (!$url) {
        return '<span class="text-secondary">Not uploaded</span>';
    }

    return '<a class="btn btn-sm btn-outline-primary" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($label) . '</a>';
}

function db()
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = admin_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash()
{
    $flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);

    return $flash;
}

function redirect_to($path)
{
    header('Location: ' . $path);
    exit;
}

function current_admin()
{
    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT users.*, roles.code AS role_code
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.id = ? AND roles.code = "admin"
         LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['admin_user_id']]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function require_admin()
{
    $admin = current_admin();

    if (!$admin) {
        redirect_to('login.php');
    }

    return $admin;
}

function admin_log($action, $table, $recordId = null, $description = '')
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;

    if (!$adminId) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO admin_logs (admin_user_id, action, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $table, $recordId, $description]);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf()
{
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!hash_equals(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '', $token)) {
        flash('error', 'Security token expired. Please try again.');
        redirect_to(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php');
    }
}

function post_value($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function role_id($code)
{
    $stmt = db()->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        throw new RuntimeException('Role not found: ' . $code);
    }

    return (int) $id;
}

function create_user($roleCode, array $data)
{
    $password = trim((string) array_get($data, 'password', 'password'));
    $stmt = db()->prepare(
        'INSERT INTO users (role_id, first_name, middle_name, last_name, email, mobile_number, password_hash, status, is_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        role_id($roleCode),
        trim((string) $data['first_name']),
        trim((string) array_get($data, 'middle_name', '')) ?: null,
        trim((string) $data['last_name']),
        trim((string) $data['email']),
        trim((string) $data['mobile_number']),
        password_hash($password, PASSWORD_DEFAULT),
        array_get($data, 'status', 'active'),
        !empty($data['is_verified']) ? 1 : 0,
    ]);

    return (int) db()->lastInsertId();
}

function update_user($userId, array $data)
{
    $fields = [
        'role_id = ?',
        'first_name = ?',
        'middle_name = ?',
        'last_name = ?',
        'email = ?',
        'mobile_number = ?',
        'status = ?',
        'is_verified = ?',
    ];
    $params = [
        role_id((string) array_get($data, 'role_code')),
        trim((string) $data['first_name']),
        trim((string) array_get($data, 'middle_name', '')) ?: null,
        trim((string) $data['last_name']),
        trim((string) $data['email']),
        trim((string) $data['mobile_number']),
        array_get($data, 'status', 'active'),
        !empty($data['is_verified']) ? 1 : 0,
    ];

    if (!empty($data['password'])) {
        $fields[] = 'password_hash = ?';
        $params[] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
    }

    $params[] = $userId;
    $stmt = db()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);
}

function sync_vehicle_record_admin($driverId, array $data)
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE driver_id = ? LIMIT 1');
    $stmt->execute([(int) $driverId]);
    $vehicleId = $stmt->fetchColumn();

    $payload = [
        trim((string) array_get($data, 'vehicle_plate_number', array_get($data, 'plate_number'))),
        trim((string) array_get($data, 'vehicle_model', array_get($data, 'model'))),
        trim((string) array_get($data, 'vehicle_color', array_get($data, 'color', 'Unspecified'))),
        max(1, (int) array_get($data, 'capacity', 1)),
        array_get($data, 'registration_path') ?: null,
        array_get($data, 'vehicle_status', array_get($data, 'status', 'active')),
    ];

    if ($vehicleId) {
        $payload[] = (int) $vehicleId;
        $pdo->prepare(
            'UPDATE vehicles
             SET plate_number = ?, model = ?, color = ?, capacity = ?, registration_path = ?, status = ?
             WHERE id = ?'
        )->execute($payload);

        return (int) $vehicleId;
    }

    array_unshift($payload, (int) $driverId);
    $pdo->prepare(
        'INSERT INTO vehicles (driver_id, plate_number, model, color, capacity, registration_path, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute($payload);

    return (int) $pdo->lastInsertId();
}

function full_name(array $row)
{
    return trim(array_get($row, 'first_name', '') . ' ' . array_get($row, 'last_name', ''));
}

function text_excerpt($value, $limit = 70)
{
    if (strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, max(0, $limit - 3)) . '...';
}

function ensure_booking_recurring_group_column()
{
    $pdo = db();
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'recurring_group_id'");

    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN recurring_group_id VARCHAR(64) NULL AFTER assigned_driver_id');
    }
}

function booking_group_ids($bookingId)
{
    $pdo = db();
    ensure_booking_recurring_group_column();
    $stmt = $pdo->prepare('SELECT recurring_group_id FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $bookingId]);
    $groupId = (string) $stmt->fetchColumn();

    if ($groupId === '') {
        return [(int) $bookingId];
    }

    $group = $pdo->prepare('SELECT id FROM bookings WHERE recurring_group_id = ? ORDER BY scheduled_date ASC, scheduled_time ASC');
    $group->execute([$groupId]);
    $ids = array_map('intval', $group->fetchAll(PDO::FETCH_COLUMN));

    return $ids ?: [(int) $bookingId];
}

function admin_column_exists($table, $column)
{
    $stmt = db()->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);

    return (bool) $stmt->fetch();
}

function admin_index_exists($table, $index)
{
    $stmt = db()->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?');
    $stmt->execute([$index]);

    return (bool) $stmt->fetch();
}

function admin_ensure_index($table, $index, $columns)
{
    if (!admin_index_exists($table, $index)) {
        db()->exec('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (' . $columns . ')');
    }
}

function admin_drop_index_if_exists($table, $index)
{
    if (admin_index_exists($table, $index)) {
        db()->exec('ALTER TABLE ' . $table . ' DROP INDEX ' . $index);
    }
}

function admin_ensure_monthly_plan_schema()
{
    $pdo = db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS service_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NOT NULL,
            monthly_plan_id BIGINT UNSIGNED NULL,
            billing_month DATE NOT NULL,
            route_count INT UNSIGNED NOT NULL DEFAULT 0,
            service_days INT UNSIGNED NOT NULL DEFAULT 0,
            daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(50) NOT NULL DEFAULT "cash",
            reference_number VARCHAR(100) NULL,
            status ENUM("pending", "paid", "cancelled") NOT NULL DEFAULT "pending",
            route_snapshot TEXT NULL,
            paid_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_service_payments_parent_month (parent_id, billing_month),
            INDEX idx_service_payments_plan (monthly_plan_id),
            CONSTRAINT fk_service_payments_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
        )'
    );

    if (!admin_column_exists('service_payments', 'monthly_plan_id')) {
        $pdo->exec('ALTER TABLE service_payments ADD COLUMN monthly_plan_id BIGINT UNSIGNED NULL AFTER parent_id');
    }

    admin_ensure_index('service_payments', 'idx_service_payments_parent_month', 'parent_id, billing_month');
    admin_ensure_index('service_payments', 'idx_service_payments_plan', 'monthly_plan_id');
    admin_drop_index_if_exists('service_payments', 'uniq_service_payments_parent_month');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS monthly_plan_refunds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NOT NULL,
            monthly_plan_id BIGINT UNSIGNED NOT NULL,
            monthly_plan_route_id BIGINT UNSIGNED NOT NULL,
            booking_id BIGINT UNSIGNED NULL,
            scheduled_date DATE NOT NULL,
            refund_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            reason TEXT NULL,
            reference_number VARCHAR(80) NULL,
            status ENUM("pending", "approved", "paid", "cancelled") NOT NULL DEFAULT "approved",
            refunded_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_monthly_plan_refunds_parent (parent_id, scheduled_date),
            INDEX idx_monthly_plan_refunds_plan_day (monthly_plan_id, monthly_plan_route_id, scheduled_date),
            CONSTRAINT fk_monthly_plan_refunds_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS monthly_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NOT NULL,
            billing_month DATE NOT NULL,
            destination_key VARCHAR(80) NULL,
            destination_address TEXT NULL,
            destination_latitude DECIMAL(10, 7) NULL,
            destination_longitude DECIMAL(10, 7) NULL,
            trip_type VARCHAR(20) NOT NULL DEFAULT "one_way",
            route_count INT UNSIGNED NOT NULL DEFAULT 0,
            child_count INT UNSIGNED NOT NULL DEFAULT 0,
            service_days INT UNSIGNED NOT NULL DEFAULT 0,
            gross_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            net_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            monthly_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            scheduled_time TIME NOT NULL DEFAULT "07:00:00",
            return_time TIME NULL,
            status ENUM("draft", "active", "cancelled") NOT NULL DEFAULT "draft",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_monthly_plans_parent_month (parent_id, billing_month),
            INDEX idx_monthly_plans_destination (parent_id, billing_month, destination_key),
            CONSTRAINT fk_monthly_plans_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
        )'
    );

    foreach ([
        'destination_key' => 'ALTER TABLE monthly_plans ADD COLUMN destination_key VARCHAR(80) NULL AFTER billing_month',
        'destination_address' => 'ALTER TABLE monthly_plans ADD COLUMN destination_address TEXT NULL AFTER destination_key',
        'destination_latitude' => 'ALTER TABLE monthly_plans ADD COLUMN destination_latitude DECIMAL(10, 7) NULL AFTER destination_address',
        'destination_longitude' => 'ALTER TABLE monthly_plans ADD COLUMN destination_longitude DECIMAL(10, 7) NULL AFTER destination_latitude',
        'trip_type' => 'ALTER TABLE monthly_plans ADD COLUMN trip_type VARCHAR(20) NOT NULL DEFAULT "one_way" AFTER destination_longitude',
        'return_time' => 'ALTER TABLE monthly_plans ADD COLUMN return_time TIME NULL AFTER scheduled_time',
    ] as $column => $sql) {
        if (!admin_column_exists('monthly_plans', $column)) {
            $pdo->exec($sql);
        }
    }

    admin_ensure_index('monthly_plans', 'idx_monthly_plans_parent_month', 'parent_id, billing_month');
    admin_ensure_index('monthly_plans', 'idx_monthly_plans_destination', 'parent_id, billing_month, destination_key');
    admin_drop_index_if_exists('monthly_plans', 'uniq_monthly_plans_parent_month');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS monthly_plan_routes (
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
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS monthly_plan_route_students (
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
        )'
    );

    ensure_booking_recurring_group_column();
    foreach ([
        'monthly_plan_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_id BIGINT UNSIGNED NULL AFTER recurring_group_id',
        'monthly_plan_route_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_route_id BIGINT UNSIGNED NULL AFTER monthly_plan_id',
        'driver_payout_amount' => 'ALTER TABLE bookings ADD COLUMN driver_payout_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER monthly_plan_route_id',
        'pickup_time' => 'ALTER TABLE bookings ADD COLUMN pickup_time TIME NULL AFTER scheduled_time',
        'dropoff_time' => 'ALTER TABLE bookings ADD COLUMN dropoff_time TIME NULL AFTER pickup_time',
        'adjusted_pickup_time' => 'ALTER TABLE bookings ADD COLUMN adjusted_pickup_time TIME NULL AFTER dropoff_time',
    ] as $column => $sql) {
        if (!admin_column_exists('bookings', $column)) {
            $pdo->exec($sql);
        }
    }
}

function delete_booking_tree($bookingId)
{
    $pdo = db();
    $rideIds = $pdo->prepare('SELECT id FROM rides WHERE booking_id = ?');
    $rideIds->execute([(int) $bookingId]);
    $rideIds = array_map('intval', $rideIds->fetchAll(PDO::FETCH_COLUMN));

    foreach ($rideIds as $rideId) {
        $pdo->prepare('DELETE FROM ride_locations WHERE ride_id = ?')->execute([$rideId]);
        $pdo->prepare('DELETE FROM messages WHERE ride_id = ?')->execute([$rideId]);
    }

    $pdo->prepare('DELETE FROM rides WHERE booking_id = ?')->execute([(int) $bookingId]);
    $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([(int) $bookingId]);
}

function delete_student_tree($studentId, $deleteUser = true)
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $studentId]);
    $userId = (int) $stmt->fetchColumn();

    $bookingIds = $pdo->prepare('SELECT id FROM bookings WHERE student_id = ?');
    $bookingIds->execute([(int) $studentId]);

    foreach ($bookingIds->fetchAll(PDO::FETCH_COLUMN) as $bookingId) {
        delete_booking_tree((int) $bookingId);
    }

    $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([(int) $studentId]);

    if ($deleteUser && $userId) {
        delete_user_account_only($userId);
    }
}

function delete_parent_tree($parentId, $deleteUser = true)
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM parents WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $parentId]);
    $userId = (int) $stmt->fetchColumn();

    $studentIds = $pdo->prepare('SELECT id FROM students WHERE parent_id = ?');
    $studentIds->execute([(int) $parentId]);

    foreach ($studentIds->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
        delete_student_tree((int) $studentId, true);
    }

    $bookingIds = $pdo->prepare('SELECT id FROM bookings WHERE parent_id = ?');
    $bookingIds->execute([(int) $parentId]);

    foreach ($bookingIds->fetchAll(PDO::FETCH_COLUMN) as $bookingId) {
        delete_booking_tree((int) $bookingId);
    }

    $pdo->prepare('DELETE FROM parents WHERE id = ?')->execute([(int) $parentId]);

    if ($deleteUser && $userId) {
        delete_user_account_only($userId);
    }
}

function delete_driver_tree($driverId, $deleteUser = true)
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM drivers WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $driverId]);
    $userId = (int) $stmt->fetchColumn();

    $rideIds = $pdo->prepare('SELECT id FROM rides WHERE driver_id = ?');
    $rideIds->execute([(int) $driverId]);

    foreach ($rideIds->fetchAll(PDO::FETCH_COLUMN) as $rideId) {
        $pdo->prepare('DELETE FROM ride_locations WHERE ride_id = ?')->execute([(int) $rideId]);
        $pdo->prepare('DELETE FROM messages WHERE ride_id = ?')->execute([(int) $rideId]);
    }

    $pdo->prepare('DELETE FROM rides WHERE driver_id = ?')->execute([(int) $driverId]);
    $pdo->prepare('UPDATE bookings SET assigned_driver_id = NULL WHERE assigned_driver_id = ?')->execute([(int) $driverId]);
    $pdo->prepare('DELETE FROM vehicles WHERE driver_id = ?')->execute([(int) $driverId]);
    $pdo->prepare('DELETE FROM drivers WHERE id = ?')->execute([(int) $driverId]);

    if ($deleteUser && $userId) {
        delete_user_account_only($userId);
    }
}

function delete_user_account_only($userId)
{
    $pdo = db();
    $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM messages WHERE sender_user_id = ? OR receiver_user_id = ?')->execute([$userId, $userId]);
    $pdo->prepare('DELETE FROM admin_logs WHERE admin_user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}

function delete_user_tree($userId)
{
    $pdo = db();
    $userId = (int) $userId;

    $stmt = $pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $parentId = (int) $stmt->fetchColumn();

    if ($parentId) {
        delete_parent_tree($parentId, false);
    }

    $stmt = $pdo->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $studentId = (int) $stmt->fetchColumn();

    if ($studentId) {
        delete_student_tree($studentId, false);
    }

    $stmt = $pdo->prepare('SELECT id FROM drivers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $driverId = (int) $stmt->fetchColumn();

    if ($driverId) {
        delete_driver_tree($driverId, false);
    }

    delete_user_account_only($userId);
}
