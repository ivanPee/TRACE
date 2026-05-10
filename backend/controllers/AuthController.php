<?php

namespace Controllers;

use Core\Auth;
use Core\ApiController;
use Core\Response;

class AuthController extends ApiController
{
    public function registerParent(): void
    {
        $input = $this->input();

        try {
            $this->pdo->beginTransaction();
            $userId = $this->createUser('parent', $input, 'active');
            $stmt = $this->pdo->prepare(
                'INSERT INTO parents (user_id, address, valid_id_path, emergency_contact_name, emergency_contact_number)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $input['address'] ?? '',
                $input['valid_id_path'] ?? null,
                $input['emergency_contact_name'] ?? $input['emergencyContactName'] ?? 'Emergency contact',
                $input['emergency_contact_number'] ?? $input['emergencyContactNumber'] ?? ($input['mobile_number'] ?? $input['mobileNumber'] ?? ''),
            ]);
            $this->pdo->commit();
            $user = $this->findUser($userId);

            Response::json([
                'success' => true,
                'message' => 'Parent registered.',
                'data' => ['user' => $this->userResource($user), 'token' => Auth::makeToken($user)],
            ], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function registerDriver(): void
    {
        $input = $this->input();

        try {
            $this->pdo->beginTransaction();
            $userId = $this->createUser('driver', $input, 'pending');
            $stmt = $this->pdo->prepare(
                'INSERT INTO drivers (user_id, license_number, license_expiry, license_photo_path, vehicle_type, vehicle_plate_number, vehicle_model, vehicle_color, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $input['license_number'] ?? $input['licenseNumber'] ?? '',
                $input['license_expiry'] ?? $input['licenseExpiry'] ?? date('Y-m-d', strtotime('+1 year')),
                $input['license_photo_path'] ?? 'pending-upload',
                $input['vehicle_type'] ?? $input['vehicleType'] ?? 'School Service',
                $input['vehicle_plate_number'] ?? $input['vehiclePlateNumber'] ?? '',
                $input['vehicle_model'] ?? $input['vehicleModel'] ?? '',
                $input['vehicle_color'] ?? $input['vehicleColor'] ?? 'Unspecified',
                'pending',
            ]);
            $this->pdo->commit();
            $user = $this->findUser($userId);

            Response::json([
                'success' => true,
                'message' => 'Driver registered for admin approval.',
                'data' => ['user' => $this->userResource($user), 'token' => Auth::makeToken($user)],
            ], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function login(): void
    {
        $input = $this->input();
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.code AS role_code
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.email = ?
             LIMIT 1'
        );
        $stmt->execute([trim((string) ($input['email'] ?? ''))]);
        $user = $stmt->fetch();

        if (!$user || !Auth::verifyPassword((string) ($input['password'] ?? ''), $user['password_hash'])) {
            Response::json(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        if (!in_array($user['status'], ['active', 'pending'], true)) {
            Response::json(['success' => false, 'message' => 'Account is not allowed to login.'], 403);
        }

        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);

        Response::json([
            'success' => true,
            'message' => 'Logged in.',
            'data' => ['user' => $this->userResource($user), 'token' => Auth::makeToken($user)],
        ]);
    }

    public function me(): void
    {
        $user = $this->requireUser();
        Response::json(['success' => true, 'data' => ['user' => $this->userResource($user)]]);
    }
}

