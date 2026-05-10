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
            $this->pdo->commit();

            Response::json(['success' => true, 'message' => 'Student registered.', 'data' => $this->dashboardData($user)], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
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
            $stmt = $this->pdo->prepare(
                'INSERT INTO bookings (parent_id, student_id, pickup_address, dropoff_address, scheduled_date, scheduled_time, trip_type, notes, booking_status, assigned_driver_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $parentId,
                (int) ($input['student_id'] ?? $input['studentId'] ?? 0),
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $input['scheduled_date'] ?? $input['scheduledDate'] ?? date('Y-m-d'),
                $input['scheduled_time'] ?? $input['scheduledTime'] ?? date('H:i:s'),
                $input['trip_type'] ?? $input['tripType'] ?? 'one_way',
                $input['notes'] ?? null,
                $driverId ? 'assigned' : 'pending',
                $driverId ? (int) $driverId : null,
            ]);
            $bookingId = (int) $this->pdo->lastInsertId();

            if ($driverId) {
                $ride = $this->pdo->prepare('INSERT INTO rides (booking_id, driver_id, ride_status) VALUES (?, ?, ?)');
                $ride->execute([$bookingId, (int) $driverId, 'assigned']);
            }

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
            'notifications' => $this->notificationsForUser((int) $user['id']),
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
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name, du.first_name AS driver_first_name, du.last_name AS driver_last_name,
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

    private function notificationsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
        $stmt->execute([$userId]);
        return array_map(fn ($item) => [
            'id' => (int) $item['id'],
            'role' => 'parent',
            'title' => $item['title'],
            'body' => $item['body'],
            'time' => $item['created_at'],
        ], $stmt->fetchAll());
    }

    private function messagesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT messages.*, users.first_name, roles.code AS sender_role
             FROM messages
             JOIN users ON users.id = messages.sender_user_id
             JOIN roles ON roles.id = users.role_id
             WHERE messages.sender_user_id = ? OR messages.receiver_user_id = ?
             ORDER BY messages.created_at ASC'
        );
        $stmt->execute([$userId, $userId]);
        return array_map(fn ($message) => [
            'id' => (int) $message['id'],
            'senderRole' => $message['sender_role'],
            'senderName' => $message['first_name'],
            'receiverRole' => '',
            'text' => $message['message_text'],
            'time' => $message['created_at'],
        ], $stmt->fetchAll());
    }

    private function rideResource(array $ride): array
    {
        return [
            'id' => (int) $ride['id'],
            'bookingId' => (int) $ride['booking_id'],
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
                'latitude' => (float) ($ride['current_latitude'] ?: 10.6765),
                'longitude' => (float) ($ride['current_longitude'] ?: 122.9509),
                'latitudeDelta' => 0.03,
                'longitudeDelta' => 0.03,
            ],
            'routePoints' => [
                ['latitude' => (float) ($ride['current_latitude'] ?: 10.6765), 'longitude' => (float) ($ride['current_longitude'] ?: 122.9509)],
            ],
        ];
    }
}

