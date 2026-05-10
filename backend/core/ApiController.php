<?php

namespace Core;

use PDO;

class ApiController
{
    protected PDO $pdo;

    public function __construct()
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        $this->pdo = Database::connect($config['db']);
    }

    protected function input(): array
    {
        return Request::input();
    }

    protected function requireUser(?string $role = null): array
    {
        $payload = Auth::tokenPayload();

        if (!$payload) {
            Response::json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $user = $this->findUser((int) $payload['user_id']);

        if (!$user || ($role !== null && $user['role_code'] !== $role)) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        return $user;
    }

    protected function findUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.code AS role_code
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    protected function roleId(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            Response::json(['success' => false, 'message' => 'Role is not configured: ' . $code], 500);
        }

        return (int) $id;
    }

    protected function createUser(string $role, array $input, string $status = 'active'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (role_id, first_name, middle_name, last_name, email, mobile_number, password_hash, status, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->roleId($role),
            trim((string) ($input['first_name'] ?? $input['firstName'] ?? '')),
            trim((string) ($input['middle_name'] ?? $input['middleName'] ?? '')) ?: null,
            trim((string) ($input['last_name'] ?? $input['lastName'] ?? '')),
            trim((string) ($input['email'] ?? '')),
            trim((string) ($input['mobile_number'] ?? $input['mobileNumber'] ?? '')),
            Auth::hashPassword((string) ($input['password'] ?? 'password')),
            $status,
            $status === 'active' ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function userResource(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'role' => $user['role_code'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'email' => $user['email'],
            'mobileNumber' => $user['mobile_number'],
            'status' => $user['status'],
        ];
    }
}
