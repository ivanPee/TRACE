<?php

declare(strict_types=1);

session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/backend/config/config.php';
    }

    return $config;
}

function db(): PDO
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

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Security token expired. Please try again.');
        redirect_to($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

function post_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function role_id(string $code): int
{
    $stmt = db()->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        throw new RuntimeException('Role not found: ' . $code);
    }

    return (int) $id;
}

function create_user(string $roleCode, array $data): int
{
    $password = trim((string) ($data['password'] ?? 'password'));
    $stmt = db()->prepare(
        'INSERT INTO users (role_id, first_name, middle_name, last_name, email, mobile_number, password_hash, status, is_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        role_id($roleCode),
        trim((string) $data['first_name']),
        trim((string) ($data['middle_name'] ?? '')) ?: null,
        trim((string) $data['last_name']),
        trim((string) $data['email']),
        trim((string) $data['mobile_number']),
        password_hash($password, PASSWORD_DEFAULT),
        $data['status'] ?? 'active',
        !empty($data['is_verified']) ? 1 : 0,
    ]);

    return (int) db()->lastInsertId();
}

function update_user(int $userId, array $data): void
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
        role_id((string) $data['role_code']),
        trim((string) $data['first_name']),
        trim((string) ($data['middle_name'] ?? '')) ?: null,
        trim((string) $data['last_name']),
        trim((string) $data['email']),
        trim((string) $data['mobile_number']),
        $data['status'] ?? 'active',
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

function full_name(array $row): string
{
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function delete_user_tree(int $userId): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM messages WHERE sender_user_id = ? OR receiver_user_id = ?')->execute([$userId, $userId]);
    $pdo->prepare('DELETE FROM admin_logs WHERE admin_user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}

