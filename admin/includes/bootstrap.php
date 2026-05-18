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
    return rtrim((string) array_get(admin_config(), 'base_url', ''), '/');
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

    return app_base_url() . '/' . ltrim($path, '/');
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

function delete_user_tree($userId)
{
    $pdo = db();
    $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM messages WHERE sender_user_id = ? OR receiver_user_id = ?')->execute([$userId, $userId]);
    $pdo->prepare('DELETE FROM admin_logs WHERE admin_user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
