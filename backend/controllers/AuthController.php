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
            $validIdPath = $this->storeUpload('valid_id', 'parents')
                ?: $this->storeBase64Upload((string) ($input['valid_id_base64'] ?? $input['validIdBase64'] ?? ''), 'parents', 'valid-id.jpg')
                ?: ($input['valid_id_path'] ?? $input['validIdPath'] ?? null);
            $coordinate = static fn ($value) => trim((string) $value) === '' ? null : (float) $value;
            $stmt = $this->pdo->prepare(
                'INSERT INTO parents (user_id, address, address_latitude, address_longitude, valid_id_path, emergency_contact_name, emergency_contact_number)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $input['address'] ?? '',
                $coordinate($input['address_latitude'] ?? $input['addressLatitude'] ?? null),
                $coordinate($input['address_longitude'] ?? $input['addressLongitude'] ?? null),
                $validIdPath,
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
            $licensePhotoPath = $this->storeUpload('license_photo', 'drivers')
                ?: $this->storeBase64Upload((string) ($input['license_photo_base64'] ?? $input['licensePhotoBase64'] ?? ''), 'drivers', 'license.jpg')
                ?: ($input['license_photo_path'] ?? $input['licensePhotoPath'] ?? 'pending-upload');
            $vehiclePhotoPath = $this->storeUpload('vehicle_photo', 'vehicles')
                ?: $this->storeBase64Upload((string) ($input['vehicle_photo_base64'] ?? $input['vehiclePhotoBase64'] ?? ''), 'vehicles', 'vehicle-photo.jpg')
                ?: ($input['vehicle_photo_path'] ?? $input['vehiclePhotoPath'] ?? null);
            $orcrPath = $this->storeUpload('vehicle_orcr', 'vehicles')
                ?: $this->storeBase64Upload((string) ($input['vehicle_orcr_base64'] ?? $input['vehicleOrcrBase64'] ?? ''), 'vehicles', 'orcr.jpg')
                ?: ($input['vehicle_orcr_path'] ?? $input['vehicleOrcrPath'] ?? null);
            $stmt = $this->pdo->prepare(
                'INSERT INTO drivers (user_id, license_number, license_expiry, license_photo_path, vehicle_type, vehicle_plate_number, vehicle_model, vehicle_color, vehicle_photo_path, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $input['license_number'] ?? $input['licenseNumber'] ?? '',
                $input['license_expiry'] ?? $input['licenseExpiry'] ?? date('Y-m-d', strtotime('+1 year')),
                $licensePhotoPath,
                $input['vehicle_type'] ?? $input['vehicleType'] ?? 'School Service',
                $input['vehicle_plate_number'] ?? $input['vehiclePlateNumber'] ?? '',
                $input['vehicle_model'] ?? $input['vehicleModel'] ?? '',
                $input['vehicle_color'] ?? $input['vehicleColor'] ?? 'Unspecified',
                $vehiclePhotoPath,
                'pending',
            ]);
            $driverId = (int) $this->pdo->lastInsertId();
            $this->syncVehicleRecord($driverId, $input, $orcrPath);
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

    public function updateProfile(): void
    {
        $user = $this->requireUser();
        $input = $this->input();
        $fields = [
            'first_name = ?',
            'last_name = ?',
            'email = ?',
            'mobile_number = ?',
        ];
        $params = [
            trim((string) ($input['first_name'] ?? $input['firstName'] ?? $user['first_name'])),
            trim((string) ($input['last_name'] ?? $input['lastName'] ?? $user['last_name'])),
            trim((string) ($input['email'] ?? $user['email'])),
            trim((string) ($input['mobile_number'] ?? $input['mobileNumber'] ?? $user['mobile_number'])),
        ];
        $profilePhoto = $this->storeUpload('profile_photo', 'profiles')
            ?: $this->storeBase64Upload((string) ($input['profile_photo_base64'] ?? $input['profilePhotoBase64'] ?? ''), 'profiles', 'profile.jpg');

        if ($profilePhoto) {
            $fields[] = 'profile_photo = ?';
            $params[] = $profilePhoto;
        }

        if (!empty($input['password'])) {
            $fields[] = 'password_hash = ?';
            $params[] = Auth::hashPassword((string) $input['password']);
        }

        try {
            $this->pdo->beginTransaction();
            $params[] = (int) $user['id'];
            $this->pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

            if ($user['role_code'] === 'parent') {
                $validIdPath = $this->storeUpload('valid_id', 'parents')
                    ?: $this->storeBase64Upload((string) ($input['valid_id_base64'] ?? $input['validIdBase64'] ?? ''), 'parents', 'valid-id.jpg');
                $coordinate = static fn ($value) => trim((string) $value) === '' ? null : (float) $value;
                $parentFields = ['address = ?', 'address_latitude = ?', 'address_longitude = ?', 'emergency_contact_name = ?', 'emergency_contact_number = ?'];
                $parentParams = [
                    $input['address'] ?? '',
                    $coordinate($input['address_latitude'] ?? $input['addressLatitude'] ?? null),
                    $coordinate($input['address_longitude'] ?? $input['addressLongitude'] ?? null),
                    $input['emergency_contact_name'] ?? $input['emergencyContactName'] ?? 'Emergency contact',
                    $input['emergency_contact_number'] ?? $input['emergencyContactNumber'] ?? ($input['mobile_number'] ?? $input['mobileNumber'] ?? ''),
                ];

                if ($validIdPath) {
                    $parentFields[] = 'valid_id_path = ?';
                    $parentParams[] = $validIdPath;
                }

                $parentParams[] = (int) $user['id'];
                $this->pdo->prepare('UPDATE parents SET ' . implode(', ', $parentFields) . ' WHERE user_id = ?')->execute($parentParams);
            }

            if ($user['role_code'] === 'driver') {
                $licensePhotoPath = $this->storeUpload('license_photo', 'drivers')
                    ?: $this->storeBase64Upload((string) ($input['license_photo_base64'] ?? $input['licensePhotoBase64'] ?? ''), 'drivers', 'license.jpg');
                $vehiclePhotoPath = $this->storeUpload('vehicle_photo', 'vehicles')
                    ?: $this->storeBase64Upload((string) ($input['vehicle_photo_base64'] ?? $input['vehiclePhotoBase64'] ?? ''), 'vehicles', 'vehicle-photo.jpg');
                $orcrPath = $this->storeUpload('vehicle_orcr', 'vehicles')
                    ?: $this->storeBase64Upload((string) ($input['vehicle_orcr_base64'] ?? $input['vehicleOrcrBase64'] ?? ''), 'vehicles', 'orcr.jpg');
                $driverFields = ['license_number = ?', 'license_expiry = ?', 'vehicle_type = ?', 'vehicle_plate_number = ?', 'vehicle_model = ?', 'vehicle_color = ?'];
                $driverParams = [
                    $input['license_number'] ?? $input['licenseNumber'] ?? '',
                    $input['license_expiry'] ?? $input['licenseExpiry'] ?? date('Y-m-d', strtotime('+1 year')),
                    $input['vehicle_type'] ?? $input['vehicleType'] ?? 'School Service',
                    $input['vehicle_plate_number'] ?? $input['vehiclePlateNumber'] ?? '',
                    $input['vehicle_model'] ?? $input['vehicleModel'] ?? '',
                    $input['vehicle_color'] ?? $input['vehicleColor'] ?? 'Unspecified',
                ];

                if ($licensePhotoPath) {
                    $driverFields[] = 'license_photo_path = ?';
                    $driverParams[] = $licensePhotoPath;
                }

                if ($vehiclePhotoPath) {
                    $driverFields[] = 'vehicle_photo_path = ?';
                    $driverParams[] = $vehiclePhotoPath;
                }

                $driverParams[] = (int) $user['id'];
                $this->pdo->prepare('UPDATE drivers SET ' . implode(', ', $driverFields) . ' WHERE user_id = ?')->execute($driverParams);

                $driver = $this->pdo->prepare('SELECT id FROM drivers WHERE user_id = ? LIMIT 1');
                $driver->execute([(int) $user['id']]);
                $driverId = (int) $driver->fetchColumn();

                if ($driverId > 0) {
                    $this->syncVehicleRecord($driverId, $input, $orcrPath);
                }
            }

            $this->notifyUser((int) $user['id'], 'Profile updated', 'Your account details were updated successfully.', 'profile', (int) $user['id']);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        $fresh = $this->findUser((int) $user['id']);
        Response::json(['success' => true, 'message' => 'Profile updated.', 'data' => ['user' => $this->userResource($fresh)]]);
    }
}

