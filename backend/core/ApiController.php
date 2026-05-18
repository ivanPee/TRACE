<?php

namespace Core;

use PDO;

class ApiController
{
    protected PDO $pdo;
    protected array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/config.php';
        $this->pdo = Database::connect($this->config['db']);
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
        $profilePhoto = $this->storeUpload('profile_photo', 'profiles')
            ?: $this->storeBase64Upload((string) ($input['profile_photo_base64'] ?? $input['profilePhotoBase64'] ?? ''), 'profiles', 'profile.jpg')
            ?: ($input['profile_photo'] ?? $input['profilePhoto'] ?? null);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (role_id, first_name, middle_name, last_name, email, mobile_number, password_hash, profile_photo, status, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->roleId($role),
            trim((string) ($input['first_name'] ?? $input['firstName'] ?? '')),
            trim((string) ($input['middle_name'] ?? $input['middleName'] ?? '')) ?: null,
            trim((string) ($input['last_name'] ?? $input['lastName'] ?? '')),
            trim((string) ($input['email'] ?? '')),
            trim((string) ($input['mobile_number'] ?? $input['mobileNumber'] ?? '')),
            Auth::hashPassword((string) ($input['password'] ?? 'password')),
            $profilePhoto,
            $status,
            $status === 'active' ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function userResource(array $user): array
    {
        $resource = [
            'id' => (int) $user['id'],
            'role' => $user['role_code'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'email' => $user['email'],
            'mobileNumber' => $user['mobile_number'],
            'profilePhoto' => $user['profile_photo'] ?? null,
            'profilePhotoUrl' => $this->fileUrl($user['profile_photo'] ?? null),
            'status' => $user['status'],
        ];

        if ($user['role_code'] === 'parent') {
            $stmt = $this->pdo->prepare('SELECT * FROM parents WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int) $user['id']]);
            $parent = $stmt->fetch();

            if ($parent) {
                $resource['address'] = $parent['address'];
                $resource['addressLatitude'] = $parent['address_latitude'] !== null ? (float) $parent['address_latitude'] : null;
                $resource['addressLongitude'] = $parent['address_longitude'] !== null ? (float) $parent['address_longitude'] : null;
                $resource['validIdPath'] = $parent['valid_id_path'];
                $resource['validIdUrl'] = $this->fileUrl($parent['valid_id_path']);
                $resource['emergencyContactName'] = $parent['emergency_contact_name'];
                $resource['emergencyContactNumber'] = $parent['emergency_contact_number'];
            }
        }

        if ($user['role_code'] === 'driver') {
            $stmt = $this->pdo->prepare('SELECT * FROM drivers WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int) $user['id']]);
            $driver = $stmt->fetch();

            if ($driver) {
                $vehicle = $this->vehicleByDriverId((int) $driver['id']);
                $legacyOrcrPath = !$vehicle ? ($driver['vehicle_photo_path'] ?? null) : null;
                $resource['driverId'] = (int) $driver['id'];
                $resource['licenseNumber'] = $driver['license_number'];
                $resource['licenseExpiry'] = $driver['license_expiry'];
                $resource['licensePhotoUrl'] = $this->fileUrl($driver['license_photo_path']);
                $resource['licensePhotoPath'] = $driver['license_photo_path'];
                $resource['vehicleType'] = $driver['vehicle_type'];
                $resource['vehiclePlateNumber'] = $driver['vehicle_plate_number'];
                $resource['vehicleModel'] = $driver['vehicle_model'];
                $resource['vehicleColor'] = $driver['vehicle_color'];
                $resource['vehiclePhotoPath'] = $vehicle ? $driver['vehicle_photo_path'] : null;
                $resource['vehiclePhotoUrl'] = $this->fileUrl($vehicle ? $driver['vehicle_photo_path'] : null);
                $resource['vehicleId'] = $vehicle ? (int) $vehicle['id'] : null;
                $resource['vehicleOrcrPath'] = $vehicle['registration_path'] ?? $legacyOrcrPath;
                $resource['vehicleOrcrUrl'] = $this->fileUrl($vehicle['registration_path'] ?? $legacyOrcrPath);
                $resource['vehicleCapacity'] = $vehicle ? (int) $vehicle['capacity'] : 1;
                $resource['vehicleStatus'] = $vehicle['status'] ?? 'active';
                $resource['approvalStatus'] = $driver['approval_status'];
                $resource['isOnline'] = (bool) $driver['is_online'];
            }
        }

        if ($user['role_code'] === 'student') {
            $stmt = $this->pdo->prepare('SELECT * FROM students WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int) $user['id']]);
            $student = $stmt->fetch();

            if ($student) {
                $resource['studentId'] = (int) $student['id'];
                $resource['parentId'] = (int) $student['parent_id'];
                $resource['lrn'] = $student['lrn'];
                $resource['schoolName'] = $student['school_name'];
                $resource['gradeLevel'] = $student['grade_level'];
                $resource['pickupAddress'] = $student['pickup_address'];
                $resource['dropoffAddress'] = $student['dropoff_address'];
            }
        }

        return $resource;
    }

    protected function fileUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
    }

    protected function vehicleByDriverId(int $driverId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE driver_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$driverId]);
        $vehicle = $stmt->fetch();

        return $vehicle ?: null;
    }

    protected function syncVehicleRecord(int $driverId, array $data, ?string $registrationPath = null): int
    {
        $vehicle = $this->vehicleByDriverId($driverId);
        $resolvedRegistrationPath = $registrationPath
            ?? ($data['vehicle_orcr_path'] ?? $data['vehicleOrcrPath'] ?? $vehicle['registration_path'] ?? null);
        $payload = [
            trim((string) ($data['vehicle_plate_number'] ?? $data['vehiclePlateNumber'] ?? '')),
            trim((string) ($data['vehicle_model'] ?? $data['vehicleModel'] ?? '')),
            trim((string) ($data['vehicle_color'] ?? $data['vehicleColor'] ?? 'Unspecified')),
            max(1, (int) ($data['vehicle_capacity'] ?? $data['vehicleCapacity'] ?? 1)),
            $resolvedRegistrationPath,
            $data['vehicle_status'] ?? $data['vehicleStatus'] ?? 'active',
        ];

        if ($vehicle) {
            $payload[] = (int) $vehicle['id'];
            $this->pdo->prepare(
                'UPDATE vehicles
                 SET plate_number = ?, model = ?, color = ?, capacity = ?, registration_path = ?, status = ?
                 WHERE id = ?'
            )->execute($payload);

            return (int) $vehicle['id'];
        }

        array_unshift($payload, $driverId);
        $this->pdo->prepare(
            'INSERT INTO vehicles (driver_id, plate_number, model, color, capacity, registration_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    protected function storeUpload(string $field, string $folder): ?string
    {
        if (empty($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $_FILES[$field]['name'], PATHINFO_EXTENSION)) ?: 'bin';
        $fileName = uniqid($field . '-', true) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
        $relativePath = 'storage/uploads/' . trim($folder, '/') . '/' . $fileName;
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $absolutePath)) {
            return null;
        }

        return $relativePath;
    }

    protected function storeBase64Upload(string $base64, string $folder, string $fallbackName): ?string
    {
        if (trim($base64) === '') {
            return null;
        }

        $extension = strtolower(pathinfo($fallbackName, PATHINFO_EXTENSION)) ?: 'jpg';
        $payload = preg_replace('#^data:[^;]+;base64,#', '', $base64);
        $bytes = base64_decode((string) $payload, true);

        if ($bytes === false) {
            return null;
        }

        $fileName = uniqid('upload-', true) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
        $relativePath = 'storage/uploads/' . trim($folder, '/') . '/' . $fileName;
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, $bytes);

        return $relativePath;
    }

    protected function notifyUser(int $userId, string $title, string $body, string $type, ?int $referenceId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO notifications (user_id, title, body, type, reference_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $body, $type, $referenceId]);
    }
}
