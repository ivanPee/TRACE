<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class ParentController extends ApiController
{
    public function dashboard(): void
    {
        $user = $this->requireUser();
        $data = $this->dashboardData($user);
        Response::json(['success' => true, 'data' => $data]);
    }

    public function createStudent(): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $stmt = $this->pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $parentId = (int) $stmt->fetchColumn();

        try {
            $this->pdo->beginTransaction();
            $name = trim((string) ($input['student_name'] ?? $input['studentName'] ?? ''));
            [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');
            $userId = $this->createUser('student', [
                'first_name' => $firstName ?: 'Student',
                'last_name' => $lastName ?: $user['last_name'],
                'email' => $input['email'] ?? 'student-' . time() . '@trace.local',
                'mobile_number' => $input['mobile_number'] ?? $input['mobileNumber'] ?? 'student-' . time(),
                'password' => $input['password'] ?? 'password',
            ], 'active');

            $stmt = $this->pdo->prepare(
                'INSERT INTO students (user_id, parent_id, lrn, school_name, grade_level, pickup_address, dropoff_address, medical_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $parentId,
                $input['lrn'] ?? '',
                $input['school_name'] ?? $input['schoolName'] ?? '',
                $input['grade_level'] ?? $input['gradeLevel'] ?? '',
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $input['medical_notes'] ?? $input['notes'] ?? null,
            ]);
            $this->notifyUser((int) $user['id'], 'Student added', 'A student account was linked to your profile.', 'student', $userId);
            $this->pdo->commit();

            Response::json(['success' => true, 'message' => 'Student registered.', 'data' => $this->dashboardData($user)], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateStudent(array $params = []): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $studentId = (int) ($params['id'] ?? 0);
        $stmt = $this->pdo->prepare(
            'SELECT students.*, users.id AS student_user_id
             FROM students
             JOIN parents ON parents.id = students.parent_id
             JOIN users parent_user ON parent_user.id = parents.user_id
             JOIN users ON users.id = students.user_id
             WHERE students.id = ? AND parent_user.id = ?
             LIMIT 1'
        );
        $stmt->execute([$studentId, (int) $user['id']]);
        $student = $stmt->fetch();

        if (!$student) {
            Response::json(['success' => false, 'message' => 'Student not found.'], 404);
        }

        $name = trim((string) ($input['student_name'] ?? $input['studentName'] ?? ''));
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

        try {
            $this->pdo->beginTransaction();
            $userFields = ['first_name = ?', 'last_name = ?', 'email = ?', 'mobile_number = ?'];
            $userParams = [
                $firstName ?: 'Student',
                $lastName ?: $user['last_name'],
                $input['email'] ?? 'student-' . $studentId . '@trace.local',
                $input['mobile_number'] ?? $input['mobileNumber'] ?? 'student-' . $studentId,
            ];

            if (!empty($input['password'])) {
                $userFields[] = 'password_hash = ?';
                $userParams[] = \Core\Auth::hashPassword((string) $input['password']);
            }

            $userParams[] = (int) $student['student_user_id'];
            $this->pdo->prepare('UPDATE users SET ' . implode(', ', $userFields) . ' WHERE id = ?')->execute($userParams);
            $this->pdo->prepare(
                'UPDATE students SET lrn = ?, school_name = ?, grade_level = ?, pickup_address = ?, dropoff_address = ?, medical_notes = ? WHERE id = ?'
            )->execute([
                $input['lrn'] ?? '',
                $input['school_name'] ?? $input['schoolName'] ?? '',
                $input['grade_level'] ?? $input['gradeLevel'] ?? '',
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $input['medical_notes'] ?? $input['notes'] ?? null,
                $studentId,
            ]);
            $this->notifyUser((int) $user['id'], 'Student updated', 'Student profile details were updated.', 'student', $studentId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Student updated.', 'data' => $this->dashboardData($user)]);
    }

    public function createBooking(): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $stmt = $this->pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $parentId = (int) $stmt->fetchColumn();

        try {
            $driverId = $input['driver_id'] ?? $input['driverId'] ?? null;
            $coordinate = static fn ($value) => trim((string) $value) === '' ? null : (float) $value;
            $stmt = $this->pdo->prepare(
                'INSERT INTO bookings (parent_id, student_id, pickup_address, dropoff_address, pickup_latitude, pickup_longitude, dropoff_latitude, dropoff_longitude, scheduled_date, scheduled_time, trip_type, notes, booking_status, assigned_driver_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $parentId,
                (int) ($input['student_id'] ?? $input['studentId'] ?? 0),
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $coordinate($input['pickup_latitude'] ?? $input['pickupLatitude'] ?? null),
                $coordinate($input['pickup_longitude'] ?? $input['pickupLongitude'] ?? null),
                $coordinate($input['dropoff_latitude'] ?? $input['dropoffLatitude'] ?? null),
                $coordinate($input['dropoff_longitude'] ?? $input['dropoffLongitude'] ?? null),
                $input['scheduled_date'] ?? $input['scheduledDate'] ?? date('Y-m-d'),
                $input['scheduled_time'] ?? $input['scheduledTime'] ?? date('H:i:s'),
                $input['trip_type'] ?? $input['tripType'] ?? 'one_way',
                $input['notes'] ?? null,
                'pending',
                $driverId ? (int) $driverId : null,
            ]);
            $bookingId = (int) $this->pdo->lastInsertId();

            if ($driverId) {
                $driverUser = $this->pdo->prepare('SELECT user_id FROM drivers WHERE id = ? LIMIT 1');
                $driverUser->execute([(int) $driverId]);
                $driverUserId = (int) $driverUser->fetchColumn();

                if ($driverUserId) {
                    $this->notifyUser($driverUserId, 'New booking request', 'A parent selected you for a student trip.', 'booking', $bookingId);
                }
            }

            $this->notifyUser((int) $user['id'], 'Booking submitted', 'Your booking is waiting for driver approval.', 'booking', $bookingId);

            Response::json(['success' => true, 'message' => 'Booking saved.', 'data' => $this->dashboardData($user)], 201);
        } catch (\Throwable $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function drivers(): void
    {
        $this->requireUser();
        $drivers = $this->pdo->query(
            'SELECT drivers.id, users.first_name, users.last_name, drivers.vehicle_plate_number, drivers.vehicle_model, drivers.vehicle_color, drivers.is_online
             FROM drivers
             JOIN users ON users.id = drivers.user_id
             WHERE drivers.approval_status = "approved" AND users.status = "active"
             ORDER BY drivers.is_online DESC, users.first_name'
        )->fetchAll();

        Response::json(['success' => true, 'data' => ['drivers' => array_map(fn ($driver) => [
            'id' => (int) $driver['id'],
            'name' => trim($driver['first_name'] . ' ' . $driver['last_name']),
            'vehicle' => trim($driver['vehicle_model'] . ' - ' . $driver['vehicle_plate_number']),
            'vehicleColor' => $driver['vehicle_color'],
            'isOnline' => (bool) $driver['is_online'],
        ], $drivers)]]);
    }

    private function dashboardData(array $user): array
    {
        $parent = $this->pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
        $parent->execute([(int) $user['id']]);
        $parentId = (int) $parent->fetchColumn();

        return [
            'students' => $this->studentsForParent($parentId),
            'bookings' => $this->bookingsForParent($parentId),
            'rides' => $this->ridesForParent($parentId),
            'notifications' => $this->notificationsForUser((int) $user['id'], $user['role_code']),
            'messages' => $this->messagesForUser((int) $user['id']),
        ];
    }

    private function studentsForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT students.*, users.first_name, users.last_name
             FROM students
             JOIN users ON users.id = students.user_id
             WHERE students.parent_id = ?
             ORDER BY students.created_at DESC'
        );
        $stmt->execute([$parentId]);

        return array_map(fn ($student) => [
            'id' => (int) $student['id'],
            'userId' => (int) $student['user_id'],
            'name' => trim($student['first_name'] . ' ' . $student['last_name']),
            'lrn' => $student['lrn'],
            'schoolName' => $student['school_name'],
            'gradeLevel' => $student['grade_level'],
            'pickupAddress' => $student['pickup_address'],
            'dropoffAddress' => $student['dropoff_address'],
            'emergencyContact' => 'Parent account',
            'notes' => $student['medical_notes'],
        ], $stmt->fetchAll());
    }

    private function bookingsForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bookings.*, su.first_name AS student_first_name, su.last_name AS student_last_name,
                du.first_name AS driver_first_name, du.last_name AS driver_last_name
             FROM bookings
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
             LEFT JOIN users du ON du.id = drivers.user_id
             WHERE bookings.parent_id = ?
             ORDER BY bookings.created_at DESC'
        );
        $stmt->execute([$parentId]);

        return array_map(fn ($booking) => [
            'id' => (int) $booking['id'],
            'studentId' => (int) $booking['student_id'],
            'studentName' => trim($booking['student_first_name'] . ' ' . $booking['student_last_name']),
            'pickupAddress' => $booking['pickup_address'],
            'dropoffAddress' => $booking['dropoff_address'],
            'scheduledDate' => $booking['scheduled_date'],
            'scheduledTime' => substr((string) $booking['scheduled_time'], 0, 5),
            'tripType' => $booking['trip_type'],
            'status' => $booking['booking_status'],
            'driverName' => trim(($booking['driver_first_name'] ?? '') . ' ' . ($booking['driver_last_name'] ?? '')) ?: 'To be assigned',
        ], $stmt->fetchAll());
    }

    private function ridesForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.id AS student_user_id, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.id AS parent_user_id, pu.first_name AS parent_first_name, pu.last_name AS parent_last_name,
                du.id AS driver_user_id, du.first_name AS driver_first_name, du.last_name AS driver_last_name,
                drivers.vehicle_model, drivers.vehicle_plate_number, drivers.current_latitude, drivers.current_longitude
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN drivers ON drivers.id = rides.driver_id
             JOIN users du ON du.id = drivers.user_id
             WHERE bookings.parent_id = ?
             ORDER BY rides.updated_at DESC'
        );
        $stmt->execute([$parentId]);

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
        return array_map(fn ($message) => [
            'id' => (int) $message['id'],
            'rideId' => $message['ride_id'] ? (int) $message['ride_id'] : null,
            'senderUserId' => (int) $message['sender_user_id'],
            'senderRole' => $message['sender_role'],
            'senderName' => trim($message['first_name'] . ' ' . $message['last_name']),
            'receiverUserId' => (int) $message['receiver_user_id'],
            'receiverRole' => $message['receiver_role'],
            'receiverName' => trim($message['receiver_first_name'] . ' ' . $message['receiver_last_name']),
            'text' => $message['message_text'],
            'time' => $message['created_at'],
        ], $stmt->fetchAll());
    }

    private function rideResource(array $ride): array
    {
        $driverLat = (float) ($ride['current_latitude'] ?: $ride['pickup_latitude'] ?: 10.6765);
        $driverLng = (float) ($ride['current_longitude'] ?: $ride['pickup_longitude'] ?: 122.9509);
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
            'status' => ucwords(str_replace('_', ' ', $ride['ride_status'])),
            'etaMinutes' => 0,
            'distanceKm' => 0,
            'pickupTime' => $ride['started_at'] ?: '',
            'dropoffTime' => $ride['dropped_off_at'] ?: '',
            'progress' => $ride['ride_status'] === 'completed' ? 1 : 0,
            'currentPointIndex' => 0,
            'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
            'location' => [
                'latitude' => $driverLat,
                'longitude' => $driverLng,
                'latitudeDelta' => 0.03,
                'longitudeDelta' => 0.03,
            ],
            'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
            'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            'routePoints' => [
                ['latitude' => $pickupLat, 'longitude' => $pickupLng],
                ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            ],
        ];
    }
}

