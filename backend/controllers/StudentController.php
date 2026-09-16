<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class StudentController extends ApiController
{
    public function dashboard(): void
    {
        $user = $this->requireUser('student');
        $student = $this->studentByUser((int) $user['id']);

        Response::json(['success' => true, 'data' => [
            'students' => [[
                'id' => (int) $student['id'],
                'userId' => (int) $student['user_id'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'lrn' => $student['lrn'],
                'schoolName' => $student['school_name'],
                'gradeLevel' => $student['grade_level'],
                'pickupAddress' => $student['pickup_address'],
                'pickupLatitude' => $student['pickup_latitude'],
                'pickupLongitude' => $student['pickup_longitude'],
                'dropoffAddress' => $student['dropoff_address'],
                'dropoffLatitude' => $student['dropoff_latitude'],
                'dropoffLongitude' => $student['dropoff_longitude'],
                'emergencyContact' => trim($student['parent_first_name'] . ' ' . $student['parent_last_name']),
                'notes' => $student['medical_notes'],
            ]],
            'bookings' => $this->bookingsForStudent((int) $student['id']),
            'rides' => $this->ridesForStudent((int) $student['id']),
            'transactions' => $this->transactionsForStudent((int) $student['id']),
            'notifications' => $this->notificationsForUser((int) $user['id'], $user['role_code']),
            'messages' => $this->messagesForUser((int) $user['id']),
        ]]);
    }

    private function studentByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT students.*, pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM students
             JOIN parents ON parents.id = students.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE students.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $student = $stmt->fetch();

        if (!$student) {
            Response::json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        return $student;
    }

    private function bookingsForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bookings.*, du.first_name AS driver_first_name, du.last_name AS driver_last_name, du.profile_photo AS driver_profile_photo,
                drivers.license_number AS driver_license_number, drivers.license_expiry AS driver_license_expiry,
                drivers.license_photo_path AS driver_license_photo_path, drivers.vehicle_model AS driver_vehicle_model,
                drivers.vehicle_plate_number AS driver_vehicle_plate_number, drivers.vehicle_color AS driver_vehicle_color,
                drivers.vehicle_photo_path AS driver_vehicle_photo_path, drivers.approval_status AS driver_approval_status,
                drivers.is_online AS driver_is_online, vehicles.capacity AS driver_vehicle_capacity, vehicles.registration_path AS driver_vehicle_orcr_path,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM bookings
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
             LEFT JOIN users du ON du.id = drivers.user_id
             LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
             WHERE bookings.student_id = ?
             ORDER BY bookings.created_at DESC'
        );
        $stmt->execute([$studentId]);

        return array_map(fn ($booking) => [
            'id' => (int) $booking['id'],
            'studentId' => (int) $booking['student_id'],
            'studentName' => 'You',
            'parentName' => trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']),
            'driverName' => trim(($booking['driver_first_name'] ?? '') . ' ' . ($booking['driver_last_name'] ?? '')) ?: 'To be assigned',
            'driver' => !empty($booking['assigned_driver_id']) ? [
                'id' => (int) $booking['assigned_driver_id'],
                'name' => trim(($booking['driver_first_name'] ?? '') . ' ' . ($booking['driver_last_name'] ?? '')) ?: 'Assigned driver',
                'profilePhotoUrl' => $this->fileUrl($booking['driver_profile_photo'] ?? null),
                'vehicle' => trim(($booking['driver_vehicle_model'] ?? '') . ' - ' . ($booking['driver_vehicle_plate_number'] ?? '')),
                'vehicleModel' => $booking['driver_vehicle_model'] ?? '',
                'vehiclePlateNumber' => $booking['driver_vehicle_plate_number'] ?? '',
                'vehicleColor' => $booking['driver_vehicle_color'] ?? '',
                'vehicleCapacity' => (int) ($booking['driver_vehicle_capacity'] ?? 1),
                'licenseNumber' => $booking['driver_license_number'] ?? '',
                'licenseExpiry' => $booking['driver_license_expiry'] ?? '',
                'licensePhotoUrl' => $this->fileUrl($booking['driver_license_photo_path'] ?? null),
                'vehiclePhotoUrl' => $this->fileUrl($booking['driver_vehicle_photo_path'] ?? null),
                'vehicleOrcrUrl' => $this->fileUrl($booking['driver_vehicle_orcr_path'] ?? null),
                'approvalStatus' => $booking['driver_approval_status'] ?? 'approved',
                'isOnline' => (bool) ($booking['driver_is_online'] ?? false),
            ] : null,
            'pickupAddress' => $booking['pickup_address'],
            'dropoffAddress' => $booking['dropoff_address'],
            'scheduledDate' => $booking['scheduled_date'],
            'scheduledTime' => substr((string) $booking['scheduled_time'], 0, 5),
            'tripType' => $booking['trip_type'],
            'recurringGroupId' => $booking['recurring_group_id'] ?? null,
            'status' => $booking['booking_status'],
        ], $stmt->fetchAll());
    }

    private function ridesForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.id AS student_user_id, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.id AS parent_user_id, pu.first_name AS parent_first_name, pu.last_name AS parent_last_name,
                du.id AS driver_user_id, du.first_name AS driver_first_name, du.last_name AS driver_last_name, du.profile_photo AS driver_profile_photo,
                drivers.license_number AS driver_license_number, drivers.license_expiry AS driver_license_expiry,
                drivers.license_photo_path AS driver_license_photo_path, drivers.vehicle_model, drivers.vehicle_plate_number,
                drivers.vehicle_color, drivers.vehicle_photo_path, drivers.approval_status, drivers.is_online,
                drivers.current_latitude, drivers.current_longitude, vehicles.capacity AS vehicle_capacity, vehicles.registration_path AS vehicle_orcr_path
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN drivers ON drivers.id = rides.driver_id
             JOIN users du ON du.id = drivers.user_id
             LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
              WHERE bookings.student_id = ?
                AND bookings.scheduled_date = CURDATE()
                AND rides.ride_status NOT IN ("completed", "cancelled")
                AND bookings.booking_status NOT IN ("completed", "cancelled")
              ORDER BY rides.updated_at DESC'
        );
        $stmt->execute([$studentId]);

        return array_map([$this, 'rideResource'], $stmt->fetchAll());
    }

    private function notificationsForUser(int $userId, string $role): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
        $stmt->execute([$userId]);

        return array_map(fn ($item) => [
            'id' => (int) $item['id'],
            'role' => $role,
            'title' => $item['title'],
            'body' => $item['body'],
            'time' => $item['created_at'],
        ], $stmt->fetchAll());
    }

    private function transactionsForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name,
                du.first_name AS driver_first_name, du.last_name AS driver_last_name
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             JOIN drivers ON drivers.id = rides.driver_id
             JOIN users du ON du.id = drivers.user_id
             WHERE bookings.student_id = ?
               AND rides.ride_status = "completed"
             ORDER BY COALESCE(rides.completed_at, rides.updated_at, rides.created_at) DESC, rides.id DESC
             LIMIT 100'
        );
        $stmt->execute([$studentId]);

        return array_map(fn ($ride) => [
            'id' => 'ride-' . (int) $ride['id'],
            'type' => 'ride',
            'role' => 'student',
            'title' => 'Completed transport',
            'description' => 'Driver: ' . trim($ride['driver_first_name'] . ' ' . $ride['driver_last_name']) . ' / Parent: ' . trim($ride['parent_first_name'] . ' ' . $ride['parent_last_name']),
            'amount' => null,
            'status' => 'completed',
            'method' => 'ride record',
            'reference' => 'Ride #' . (int) $ride['id'],
            'date' => $ride['completed_at'] ?: $ride['updated_at'],
            'scheduledDate' => $ride['scheduled_date'],
            'scheduledTime' => substr((string) ($ride['pickup_time'] ?: $ride['scheduled_time']), 0, 5),
            'rideId' => (int) $ride['id'],
            'bookingId' => (int) $ride['booking_id'],
        ], $stmt->fetchAll());
    }

    private function messagesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT messages.*, users.first_name, users.last_name, roles.code AS sender_role,
                receiver_users.first_name AS receiver_first_name, receiver_users.last_name AS receiver_last_name,
                receiver_roles.code AS receiver_role
             FROM messages
             JOIN users ON users.id = messages.sender_user_id
             JOIN roles ON roles.id = users.role_id
             JOIN users receiver_users ON receiver_users.id = messages.receiver_user_id
             JOIN roles receiver_roles ON receiver_roles.id = receiver_users.role_id
             WHERE messages.sender_user_id = ? OR messages.receiver_user_id = ?
             ORDER BY messages.created_at ASC'
        );
        $stmt->execute([$userId, $userId]);

        return array_map(fn ($message) => $this->messageResource($message), $stmt->fetchAll());
    }

    private function rideResource(array $ride): array
    {
        $hasDriverLocation = $ride['current_latitude'] !== null && $ride['current_latitude'] !== ''
            && $ride['current_longitude'] !== null && $ride['current_longitude'] !== '';
        $driverLat = $hasDriverLocation ? (float) $ride['current_latitude'] : null;
        $driverLng = $hasDriverLocation ? (float) $ride['current_longitude'] : null;
        $pickupLat = (float) ($ride['pickup_latitude'] ?: 10.676344);
        $pickupLng = (float) ($ride['pickup_longitude'] ?: 122.953221);
        $dropoffLat = (float) ($ride['dropoff_latitude'] ?: 10.668364);
        $dropoffLng = (float) ($ride['dropoff_longitude'] ?: 123.019768);

        return [
            'id' => (int) $ride['id'],
            'bookingId' => (int) $ride['booking_id'],
            'studentUserId' => (int) $ride['student_user_id'],
            'parentUserId' => (int) $ride['parent_user_id'],
            'driverUserId' => (int) $ride['driver_user_id'],
            'studentName' => trim($ride['student_first_name'] . ' ' . $ride['student_last_name']),
            'parentName' => trim($ride['parent_first_name'] . ' ' . $ride['parent_last_name']),
            'driverName' => trim($ride['driver_first_name'] . ' ' . $ride['driver_last_name']),
            'vehicle' => trim($ride['vehicle_model'] . ' - ' . $ride['vehicle_plate_number']),
            'vehicleModel' => $ride['vehicle_model'],
            'vehiclePlateNumber' => $ride['vehicle_plate_number'],
            'vehicleColor' => $ride['vehicle_color'] ?? '',
            'vehicleCapacity' => (int) ($ride['vehicle_capacity'] ?? 1),
            'driverProfilePhotoUrl' => $this->fileUrl($ride['driver_profile_photo'] ?? null),
            'driverLicenseNumber' => $ride['driver_license_number'] ?? '',
            'driverLicenseExpiry' => $ride['driver_license_expiry'] ?? '',
            'driverLicensePhotoUrl' => $this->fileUrl($ride['driver_license_photo_path'] ?? null),
            'driverApprovalStatus' => $ride['approval_status'] ?? 'approved',
            'driverIsOnline' => (bool) ($ride['is_online'] ?? false),
            'vehiclePhotoUrl' => $this->fileUrl($ride['vehicle_photo_path'] ?? null),
            'vehicleOrcrUrl' => $this->fileUrl($ride['vehicle_orcr_path'] ?? null),
            'driver' => [
                'id' => (int) ($ride['driver_id'] ?? 0),
                'name' => trim($ride['driver_first_name'] . ' ' . $ride['driver_last_name']),
                'profilePhotoUrl' => $this->fileUrl($ride['driver_profile_photo'] ?? null),
                'vehicle' => trim($ride['vehicle_model'] . ' - ' . $ride['vehicle_plate_number']),
                'vehicleModel' => $ride['vehicle_model'],
                'vehiclePlateNumber' => $ride['vehicle_plate_number'],
                'vehicleColor' => $ride['vehicle_color'] ?? '',
                'vehicleCapacity' => (int) ($ride['vehicle_capacity'] ?? 1),
                'licenseNumber' => $ride['driver_license_number'] ?? '',
                'licenseExpiry' => $ride['driver_license_expiry'] ?? '',
                'licensePhotoUrl' => $this->fileUrl($ride['driver_license_photo_path'] ?? null),
                'vehiclePhotoUrl' => $this->fileUrl($ride['vehicle_photo_path'] ?? null),
                'vehicleOrcrUrl' => $this->fileUrl($ride['vehicle_orcr_path'] ?? null),
                'approvalStatus' => $ride['approval_status'] ?? 'approved',
                'isOnline' => (bool) ($ride['is_online'] ?? false),
            ],
            'status' => ucwords(str_replace('_', ' ', $ride['ride_status'])),
            'pickupAddress' => $ride['pickup_address'],
            'dropoffAddress' => $ride['dropoff_address'],
            'etaMinutes' => 0,
            'distanceKm' => 0,
            'pickupTime' => $ride['started_at'] ?: '',
            'dropoffTime' => $ride['dropped_off_at'] ?: '',
            'progress' => $ride['ride_status'] === 'completed' ? 1 : 0,
            'currentPointIndex' => 0,
            'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
            'hasDriverLocation' => $hasDriverLocation,
            'location' => $hasDriverLocation ? ['latitude' => $driverLat, 'longitude' => $driverLng, 'latitudeDelta' => 0.03, 'longitudeDelta' => 0.03] : null,
            'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
            'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            'routePoints' => [['latitude' => $pickupLat, 'longitude' => $pickupLng], ['latitude' => $dropoffLat, 'longitude' => $dropoffLng]],
        ];
    }
}
