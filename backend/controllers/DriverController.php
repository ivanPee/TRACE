<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class DriverController extends ApiController
{
    public function dashboard(): void
    {
        $user = $this->requireUser('driver');
        $driver = $this->driverByUser((int) $user['id']);
        $this->pdo->prepare('UPDATE drivers SET is_online = 1 WHERE id = ?')->execute([(int) $driver['id']]);
        Response::json(['success' => true, 'data' => $this->driverData($user)]);
    }

    public function availability(): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $isOnline = !empty($input['isOnline']) || !empty($input['is_online']) ? 1 : 0;

        $this->pdo->prepare('UPDATE drivers SET is_online = ? WHERE id = ?')->execute([$isOnline, (int) $driver['id']]);

        Response::json(['success' => true, 'message' => 'Driver availability updated.', 'data' => $this->driverData($user)]);
    }

    public function approveBooking(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $driver = $this->driverByUser((int) $user['id']);
        $bookingId = (int) ($params['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM bookings WHERE id = ? AND assigned_driver_id = ? AND booking_status = "pending" LIMIT 1'
        );
        $stmt->execute([$bookingId, (int) $driver['id']]);

        if (!$stmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Booking request is not available for approval.'], 404);
        }

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('UPDATE bookings SET booking_status = "assigned" WHERE id = ?')->execute([$bookingId]);
            $ride = $this->pdo->prepare(
                'INSERT INTO rides (booking_id, driver_id, ride_status)
                 VALUES (?, ?, "assigned")
                 ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), ride_status = "assigned"'
            );
            $ride->execute([$bookingId, (int) $driver['id']]);
            $this->pdo->prepare('UPDATE drivers SET is_online = 1 WHERE id = ?')->execute([(int) $driver['id']]);
            $parentUserId = $this->parentUserIdForBooking($bookingId);

            if ($parentUserId) {
                $this->notifyUser($parentUserId, 'Booking approved', 'Your selected driver approved the booking.', 'booking', $bookingId);
            }

            $this->notifyUser((int) $user['id'], 'Booking approved', 'The booking is now assigned to you.', 'booking', $bookingId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Booking approved.', 'data' => $this->driverData($user)]);
    }

    public function rejectBooking(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $driver = $this->driverByUser((int) $user['id']);
        $bookingId = (int) ($params['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'UPDATE bookings
             SET booking_status = "cancelled", assigned_driver_id = NULL
             WHERE id = ? AND assigned_driver_id = ? AND booking_status = "pending"'
        );
        $stmt->execute([$bookingId, (int) $driver['id']]);

        if ($stmt->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Booking request is not available for rejection.'], 404);
        }

        $parentUserId = $this->parentUserIdForBooking($bookingId);

        if ($parentUserId) {
            $this->notifyUser($parentUserId, 'Booking rejected', 'A driver rejected your booking request. Please select another driver.', 'booking', $bookingId);
        }

        Response::json(['success' => true, 'message' => 'Booking rejected.', 'data' => $this->driverData($user)]);
    }

    public function updateRideStatus(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $status = strtolower(str_replace(' ', '_', (string) ($input['status'] ?? '')));
        $allowed = ['assigned', 'driver_arriving', 'arrived', 'picked_up', 'in_transit', 'dropped_off', 'completed', 'cancelled'];

        if (!in_array($status, $allowed, true)) {
            Response::json(['success' => false, 'message' => 'Invalid ride status.'], 422);
        }

        $fieldMap = [
            'driver_arriving' => 'started_at',
            'arrived' => 'arrived_pickup_at',
            'picked_up' => 'picked_up_at',
            'dropped_off' => 'dropped_off_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
        ];
        $timestampSql = isset($fieldMap[$status]) ? ', ' . $fieldMap[$status] . ' = COALESCE(' . $fieldMap[$status] . ', NOW())' : '';
        $stmt = $this->pdo->prepare('UPDATE rides SET ride_status = ?' . $timestampSql . ' WHERE id = ?');
        $stmt->execute([$status, (int) ($params['id'] ?? 0)]);
        $this->pdo->prepare(
            'UPDATE bookings
             JOIN rides ON rides.booking_id = bookings.id
             SET bookings.booking_status = ?
             WHERE rides.id = ?'
        )->execute([$status === 'arrived' ? 'driver_arriving' : $status, (int) ($params['id'] ?? 0)]);
        $parentUserId = $this->parentUserIdForRide((int) ($params['id'] ?? 0));

        if ($parentUserId) {
            $this->notifyUser($parentUserId, 'Ride status updated', 'Ride status is now ' . ucwords(str_replace('_', ' ', $status)) . '.', 'ride', (int) ($params['id'] ?? 0));
        }

        Response::json(['success' => true, 'message' => 'Ride status updated.', 'data' => $this->driverData($user)]);
    }

    public function pushLocation(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ride_locations (ride_id, driver_id, latitude, longitude, speed, heading, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) ($params['id'] ?? 0),
            (int) $driver['id'],
            (float) ($input['latitude'] ?? 0),
            (float) ($input['longitude'] ?? 0),
            $input['speed'] ?? null,
            $input['heading'] ?? null,
            $input['recorded_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $this->pdo->prepare('UPDATE drivers SET current_latitude = ?, current_longitude = ?, is_online = 1 WHERE id = ?')
            ->execute([(float) ($input['latitude'] ?? 0), (float) ($input['longitude'] ?? 0), (int) $driver['id']]);

        Response::json(['success' => true, 'message' => 'Location recorded.', 'data' => $this->driverData($user)]);
    }

    public function transfer(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $rideId = (int) ($params['id'] ?? 0);
        $newDriverId = (int) ($input['driver_id'] ?? $input['driverId'] ?? 0);

        if (!$newDriverId || $newDriverId === (int) $driver['id']) {
            Response::json(['success' => false, 'message' => 'Select another driver for transfer.'], 422);
        }

        $rideStmt = $this->pdo->prepare('SELECT booking_id FROM rides WHERE id = ? AND driver_id = ? LIMIT 1');
        $rideStmt->execute([$rideId, (int) $driver['id']]);
        $bookingId = (int) $rideStmt->fetchColumn();

        if (!$bookingId) {
            Response::json(['success' => false, 'message' => 'Ride is not assigned to this driver.'], 404);
        }

        $newDriverStmt = $this->pdo->prepare(
            'SELECT drivers.id, drivers.user_id, users.first_name, users.last_name
             FROM drivers
             JOIN users ON users.id = drivers.user_id
             WHERE drivers.id = ? AND drivers.approval_status = "approved" AND users.status = "active"
             LIMIT 1'
        );
        $newDriverStmt->execute([$newDriverId]);
        $newDriver = $newDriverStmt->fetch();

        if (!$newDriver) {
            Response::json(['success' => false, 'message' => 'Target driver is not available.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('UPDATE rides SET driver_id = ? WHERE id = ?')->execute([$newDriverId, $rideId]);
            $this->pdo->prepare(
                'UPDATE bookings SET assigned_driver_id = ? WHERE id = ?'
            )->execute([$newDriverId, $bookingId]);

            $transferText = 'Ride transfer completed. You can coordinate here if handoff details are needed.';
            $this->pdo->prepare(
                'INSERT INTO messages (sender_user_id, receiver_user_id, ride_id, message_text, message_type)
                 VALUES (?, ?, ?, ?, "system")'
            )->execute([(int) $user['id'], (int) $newDriver['user_id'], $rideId, $transferText]);
            $this->notifyUser((int) $newDriver['user_id'], 'Booking transferred', 'A booking was transferred to you.', 'booking', $bookingId);
            $this->notifyUser((int) $user['id'], 'Booking transferred', 'The booking was transferred to ' . trim($newDriver['first_name'] . ' ' . $newDriver['last_name']) . '.', 'booking', $bookingId);
            $parentUserId = $this->parentUserIdForBooking($bookingId);

            if ($parentUserId) {
                $this->notifyUser($parentUserId, 'Driver changed', 'Your booking was transferred to another approved driver.', 'booking', $bookingId);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Driver transfer completed.', 'data' => $this->driverData($user)]);
    }

    private function driverData(array $user): array
    {
        $driver = $this->driverByUser((int) $user['id']);
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.id AS student_user_id, su.first_name AS student_first_name, su.last_name AS student_last_name,
                bookings.scheduled_date, bookings.scheduled_time, bookings.trip_type, bookings.booking_status,
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
             WHERE rides.driver_id = ?
             ORDER BY rides.updated_at DESC'
        );
        $stmt->execute([(int) $driver['id']]);
        $rides = array_map([$this, 'rideResource'], $stmt->fetchAll());

        $pending = $this->pendingBookingsForDriver((int) $driver['id']);

        return [
            'rides' => $rides,
            'bookings' => array_merge($pending, array_map(fn ($ride) => [
                'id' => $ride['bookingId'],
                'rideId' => $ride['id'],
                'studentName' => $ride['studentName'],
                'pickupAddress' => $ride['pickupAddress'],
                'dropoffAddress' => $ride['dropoffAddress'],
                'scheduledDate' => $ride['scheduledDate'],
                'scheduledTime' => $ride['scheduledTime'],
                'tripType' => $ride['tripType'],
                'status' => $ride['status'],
                'driverName' => $ride['parentName'],
                'parentName' => $ride['parentName'],
                'canApprove' => false,
            ], $rides)),
            'students' => array_map(fn ($ride) => ['id' => $ride['bookingId'], 'name' => $ride['studentName'], 'schoolName' => '', 'gradeLevel' => '', 'pickupAddress' => '', 'dropoffAddress' => '', 'emergencyContact' => $ride['parentName']], $rides),
            'messages' => $this->messagesForUser((int) $user['id']),
            'notifications' => $this->notificationsForUser((int) $user['id'], $user['role_code']),
        ];
    }

    private function driverByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM drivers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $driver = $stmt->fetch();

        if (!$driver) {
            Response::json(['success' => false, 'message' => 'Driver profile not found.'], 404);
        }

        return $driver;
    }

    private function pendingBookingsForDriver(int $driverId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bookings.*, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM bookings
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE bookings.assigned_driver_id = ? AND bookings.booking_status = "pending"
             ORDER BY bookings.created_at DESC'
        );
        $stmt->execute([$driverId]);

        return array_map(fn ($booking) => [
            'id' => (int) $booking['id'],
            'studentId' => (int) $booking['student_id'],
            'studentName' => trim($booking['student_first_name'] . ' ' . $booking['student_last_name']),
            'parentName' => trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']),
            'driverName' => trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']),
            'pickupAddress' => $booking['pickup_address'],
            'dropoffAddress' => $booking['dropoff_address'],
            'scheduledDate' => $booking['scheduled_date'],
            'scheduledTime' => substr((string) $booking['scheduled_time'], 0, 5),
            'tripType' => $booking['trip_type'],
            'status' => $booking['booking_status'],
            'canApprove' => true,
        ], $stmt->fetchAll());
    }

    private function parentUserIdForBooking(int $bookingId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT parents.user_id
             FROM bookings
             JOIN parents ON parents.id = bookings.parent_id
             WHERE bookings.id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookingId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function parentUserIdForRide(int $rideId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT parents.user_id
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             WHERE rides.id = ?
             LIMIT 1'
        );
        $stmt->execute([$rideId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
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
            'scheduledDate' => $ride['scheduled_date'],
            'scheduledTime' => substr((string) $ride['scheduled_time'], 0, 5),
            'tripType' => $ride['trip_type'],
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

