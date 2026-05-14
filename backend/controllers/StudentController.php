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
                'dropoffAddress' => $student['dropoff_address'],
                'emergencyContact' => trim($student['parent_first_name'] . ' ' . $student['parent_last_name']),
                'notes' => $student['medical_notes'],
            ]],
            'bookings' => $this->bookingsForStudent((int) $student['id']),
            'rides' => $this->ridesForStudent((int) $student['id']),
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
            'SELECT bookings.*, du.first_name AS driver_first_name, du.last_name AS driver_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM bookings
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
             LEFT JOIN users du ON du.id = drivers.user_id
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
            'pickupAddress' => $booking['pickup_address'],
            'dropoffAddress' => $booking['dropoff_address'],
            'scheduledDate' => $booking['scheduled_date'],
            'scheduledTime' => substr((string) $booking['scheduled_time'], 0, 5),
            'tripType' => $booking['trip_type'],
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
             WHERE bookings.student_id = ?
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
            'pickupAddress' => $ride['pickup_address'],
            'dropoffAddress' => $ride['dropoff_address'],
            'etaMinutes' => 0,
            'distanceKm' => 0,
            'pickupTime' => $ride['started_at'] ?: '',
            'dropoffTime' => $ride['dropped_off_at'] ?: '',
            'progress' => $ride['ride_status'] === 'completed' ? 1 : 0,
            'currentPointIndex' => 0,
            'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
            'location' => ['latitude' => $driverLat, 'longitude' => $driverLng, 'latitudeDelta' => 0.03, 'longitudeDelta' => 0.03],
            'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
            'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            'routePoints' => [['latitude' => $pickupLat, 'longitude' => $pickupLng], ['latitude' => $dropoffLat, 'longitude' => $dropoffLng]],
        ];
    }
}
