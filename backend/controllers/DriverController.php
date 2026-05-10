<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class DriverController extends ApiController
{
    public function dashboard(): void
    {
        $user = $this->requireUser('driver');
        Response::json(['success' => true, 'data' => $this->driverData($user)]);
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
        $newDriverId = (int) ($input['driver_id'] ?? $input['driverId'] ?? 0);

        $this->pdo->prepare('UPDATE rides SET driver_id = ? WHERE id = ?')->execute([$newDriverId, (int) ($params['id'] ?? 0)]);
        $this->pdo->prepare(
            'UPDATE bookings JOIN rides ON rides.booking_id = bookings.id SET bookings.assigned_driver_id = ? WHERE rides.id = ?'
        )->execute([$newDriverId, (int) ($params['id'] ?? 0)]);

        Response::json(['success' => true, 'message' => 'Driver transfer completed.', 'data' => $this->driverData($user)]);
    }

    private function driverData(array $user): array
    {
        $driver = $this->driverByUser((int) $user['id']);
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
             WHERE rides.driver_id = ?
             ORDER BY rides.updated_at DESC'
        );
        $stmt->execute([(int) $driver['id']]);
        $rides = array_map(fn ($ride) => [
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
        ], $stmt->fetchAll());

        return [
            'rides' => $rides,
            'bookings' => array_map(fn ($ride) => [
                'id' => $ride['bookingId'],
                'studentName' => $ride['studentName'],
                'pickupAddress' => '',
                'dropoffAddress' => '',
                'scheduledDate' => '',
                'scheduledTime' => '',
                'tripType' => '',
                'status' => $ride['status'],
                'driverName' => $ride['parentName'],
            ], $rides),
            'students' => array_map(fn ($ride) => ['id' => $ride['bookingId'], 'name' => $ride['studentName'], 'schoolName' => '', 'gradeLevel' => '', 'pickupAddress' => '', 'dropoffAddress' => '', 'emergencyContact' => $ride['parentName']], $rides),
            'messages' => [],
            'notifications' => [],
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
}

