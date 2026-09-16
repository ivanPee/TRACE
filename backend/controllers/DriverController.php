<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class DriverController extends ApiController
{
    private const CARPOOL_SPEED_KPH = 50.0;

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
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $bookingId = (int) ($params['id'] ?? 0);
        $includeCarpool = !empty($input['includeCarpool']) || !empty($input['include_carpool']);
        $this->ensureRecurringGroupColumn();
        $this->ensureBookingMonthlyPlanColumns();

        $stmt = $this->pdo->prepare(
            'SELECT id, recurring_group_id, monthly_plan_route_id, scheduled_date, scheduled_time, pickup_time, dropoff_time, adjusted_pickup_time,
                pickup_address, pickup_latitude, pickup_longitude, dropoff_address, dropoff_latitude, dropoff_longitude, notes
             FROM bookings
             WHERE id = ?
               AND (assigned_driver_id IS NULL OR assigned_driver_id = ?)
               AND booking_status = "pending"
               AND scheduled_date = CURDATE()
             LIMIT 1'
        );
        $stmt->execute([$bookingId, (int) $driver['id']]);
        $booking = $stmt->fetch();

        if (!$booking) {
            Response::json(['success' => false, 'message' => 'Route is no longer available.'], 404);
        }

        try {
            $this->pdo->beginTransaction();
            $bookingIds = [(int) $booking['id']];

            if ($includeCarpool) {
                $bookingIds = $this->currentDayCarpoolBookingIds($booking, (int) $driver['id']);
            } elseif (!empty($booking['monthly_plan_route_id'])) {
                $groupStmt = $this->pdo->prepare(
                    'SELECT id
                     FROM bookings
                     WHERE monthly_plan_route_id = ?
                       AND scheduled_date = ?
                       AND scheduled_time = ?
                       AND booking_status = "pending"
                       AND assigned_driver_id IS NULL'
                );
                $groupStmt->execute([$booking['monthly_plan_route_id'], $booking['scheduled_date'], $booking['scheduled_time']]);
                $bookingIds = array_map('intval', $groupStmt->fetchAll(\PDO::FETCH_COLUMN));
            } elseif (!empty($booking['recurring_group_id'])) {
                $groupStmt = $this->pdo->prepare(
                    'SELECT id
                     FROM bookings
                     WHERE recurring_group_id = ? AND assigned_driver_id = ? AND booking_status = "pending"'
                );
                $groupStmt->execute([$booking['recurring_group_id'], (int) $driver['id']]);
                $bookingIds = array_map('intval', $groupStmt->fetchAll(\PDO::FETCH_COLUMN));
            }

            if (!$bookingIds) {
                $bookingIds = [(int) $booking['id']];
            }

            $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
            $vehicleCapacity = $this->vehicleCapacityForDriver((int) $driver['id']);
            $routeSeatCount = $this->routeSeatCountAfterClaim((int) $driver['id'], $booking['scheduled_date'], $booking['pickup_time'] ?: $booking['scheduled_time'], $bookingIds);

            if ($routeSeatCount > $vehicleCapacity) {
                $this->pdo->rollBack();
                Response::json([
                    'success' => false,
                    'message' => 'Vehicle capacity is full. This route needs ' . $routeSeatCount . ' seat' . ($routeSeatCount === 1 ? '' : 's') . ', but your vehicle capacity is ' . $vehicleCapacity . '.',
                ], 409);
            }

            $schedule = $this->routeScheduleForClaim((int) $driver['id'], $booking, $bookingIds);

            if (!$schedule['feasible']) {
                $this->pdo->rollBack();
                Response::json(['success' => false, 'message' => $schedule['message']], 409);
            }

            $this->pdo->prepare('UPDATE bookings SET booking_status = "assigned", assigned_driver_id = ? WHERE id IN (' . $placeholders . ')')->execute(array_merge([(int) $driver['id']], $bookingIds));
            $this->applyPickupSchedule($schedule['schedule'], trim($user['first_name'] . ' ' . $user['last_name']));
            $ride = $this->pdo->prepare(
                'INSERT INTO rides (booking_id, driver_id, ride_status)
                 VALUES (?, ?, "assigned")
                 ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), ride_status = "assigned"'
            );

            foreach ($bookingIds as $approvedBookingId) {
                $ride->execute([$approvedBookingId, (int) $driver['id']]);
            }

            $this->pdo->prepare('UPDATE drivers SET is_online = 1 WHERE id = ?')->execute([(int) $driver['id']]);
            $approvedText = count($bookingIds) > 1
                ? count($bookingIds) . ' children were assigned to a driver for today\'s ' . substr((string) ($booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5) . ' carpool route. Pickup times may be adjusted by route order.'
                : 'A driver claimed today\'s service route.';

            foreach ($this->parentUserIdsForBookings($bookingIds) as $parentUserId) {
                $this->notifyUser($parentUserId, 'Route claimed', $approvedText, 'booking', $bookingId);
            }

            $this->notifyUser((int) $user['id'], 'Route claimed', count($bookingIds) > 1 ? 'The carpool route is now assigned to you.' : 'The service stop is now assigned to you.', 'booking', $bookingId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Route claimed.', 'data' => $this->driverData($user)]);
    }

    public function rejectBooking(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $driver = $this->driverByUser((int) $user['id']);
        $bookingId = (int) ($params['id'] ?? 0);
        $this->ensureRecurringGroupColumn();
        $this->ensureBookingMonthlyPlanColumns();

        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM bookings
             WHERE id = ?
               AND (assigned_driver_id IS NULL OR assigned_driver_id = ?)
               AND booking_status = "pending"
               AND scheduled_date = CURDATE()
             LIMIT 1'
        );
        $stmt->execute([$bookingId, (int) $driver['id']]);

        if (!$stmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Route is no longer available.'], 404);
        }

        Response::json(['success' => true, 'message' => 'Route left on the transport board.', 'data' => $this->driverData($user)]);
    }

    public function updateRideStatus(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $this->ensureDriverPayoutsTable();
        $this->ensureRideDropoffProofColumns();
        $rideId = (int) ($params['id'] ?? 0);
        $status = strtolower(str_replace(' ', '_', (string) ($input['status'] ?? '')));
        $allowed = ['assigned', 'driver_arriving', 'arrived', 'picked_up', 'in_transit', 'dropped_off', 'completed', 'cancelled'];

        if (!in_array($status, $allowed, true)) {
            Response::json(['success' => false, 'message' => 'Invalid ride status.'], 422);
        }

        $rideStmt = $this->pdo->prepare(
            'SELECT rides.id, rides.ride_status, rides.dropoff_photo_path, bookings.scheduled_date,
                bookings.scheduled_date = CURDATE() AS is_current_day
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             WHERE rides.id = ? AND rides.driver_id = ?
             LIMIT 1'
        );
        $rideStmt->execute([$rideId, (int) $driver['id']]);
        $ride = $rideStmt->fetch();

        if (!$ride) {
            Response::json(['success' => false, 'message' => 'Ride is not assigned to this driver.'], 404);
        }

        if (empty($ride['is_current_day'])) {
            Response::json(['success' => false, 'message' => 'Only today\'s transport stops can be updated.'], 409);
        }

        if (in_array((string) $ride['ride_status'], ['completed', 'cancelled'], true) && $status !== (string) $ride['ride_status']) {
            Response::json(['success' => false, 'message' => 'Done or cancelled stops cannot be changed.'], 409);
        }

        if ($status === 'completed' && !in_array((string) $ride['ride_status'], ['picked_up', 'in_transit', 'dropped_off', 'completed'], true)) {
            Response::json(['success' => false, 'message' => 'Mark the child boarded before completing the drop-off.'], 409);
        }

        $dropoffPhotoPath = null;

        if ($status === 'completed') {
            $dropoffPhotoPath = $this->storeUpload('dropoff_photo', 'dropoffs')
                ?: $this->storeBase64Upload((string) ($input['dropoff_photo_base64'] ?? $input['dropoffPhotoBase64'] ?? ''), 'dropoffs', 'dropoff-photo.jpg');

            if (!$dropoffPhotoPath && empty($ride['dropoff_photo_path'])) {
                Response::json(['success' => false, 'message' => 'Take a drop-off photo before completing the ride.'], 422);
            }
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

        if ($status === 'completed') {
            $timestampSql .= ', dropped_off_at = COALESCE(dropped_off_at, NOW())';
        }
        $dropoffPhotoSql = $dropoffPhotoPath ? ', dropoff_photo_path = ?' : '';
        $stmt = $this->pdo->prepare('UPDATE rides SET ride_status = ?' . $timestampSql . $dropoffPhotoSql . ' WHERE id = ?');
        $stmt->execute($dropoffPhotoPath ? [$status, $dropoffPhotoPath, $rideId] : [$status, $rideId]);
        $this->pdo->prepare(
            'UPDATE bookings
             JOIN rides ON rides.booking_id = bookings.id
             SET bookings.booking_status = ?
             WHERE rides.id = ?'
        )->execute([$status === 'arrived' ? 'driver_arriving' : $status, $rideId]);
        $tripUserIds = $this->tripUserIdsForRide($rideId);
        $statusText = ucwords(str_replace('_', ' ', $status));

        if ($tripUserIds['parentUserId']) {
            $this->notifyUser($tripUserIds['parentUserId'], 'Ride status updated', 'Ride status is now ' . $statusText . '.', 'ride', $rideId);
        }

        if ($status === 'completed' && $dropoffPhotoPath && $tripUserIds['parentUserId']) {
            $photoUrl = $this->fileUrl($dropoffPhotoPath);
            $this->notifyUser($tripUserIds['parentUserId'], 'Drop-off photo received', 'The driver uploaded a child drop-off photo.', 'ride', $rideId);
            $this->pdo->prepare(
                'INSERT INTO messages (sender_user_id, receiver_user_id, ride_id, message_text, message_type)
                 VALUES (?, ?, ?, ?, "alert")'
            )->execute([(int) $user['id'], $tripUserIds['parentUserId'], $rideId, $photoUrl ?: $dropoffPhotoPath]);
        }

        if ($tripUserIds['studentUserId']) {
            $this->notifyUser($tripUserIds['studentUserId'], 'Trip status updated', 'Your ride status is now ' . $statusText . '.', 'ride', $rideId);
        }

        if ($status === 'completed') {
            $this->ensureDriverPayoutsTable();
            $this->recordDriverPayout($rideId, (int) $driver['id']);
        }

        Response::json(['success' => true, 'message' => 'Ride status updated.', 'data' => $this->driverData($user)]);
    }

    public function pushLocation(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $rideId = (int) ($params['id'] ?? 0);
        $this->ensureRideLocationGpsColumns();

        $ride = $this->pdo->prepare('SELECT id, ride_status FROM rides WHERE id = ? AND driver_id = ? LIMIT 1');
        $ride->execute([$rideId, (int) $driver['id']]);
        $rideRow = $ride->fetch();

        if (!$rideRow) {
            Response::json(['success' => false, 'message' => 'Ride is not assigned to this driver.'], 404);
        }

        $latitude = isset($input['latitude']) ? (float) $input['latitude'] : null;
        $longitude = isset($input['longitude']) ? (float) $input['longitude'] : null;
        $accuracy = isset($input['accuracy']) && $input['accuracy'] !== '' ? (float) $input['accuracy'] : null;

        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            Response::json(['success' => false, 'message' => 'Valid latitude and longitude are required.'], 422);
        }

        if ($accuracy !== null && ($accuracy < 0 || $accuracy > 100)) {
            Response::json(['success' => false, 'message' => 'GPS accuracy is too low. Move to an open area and try again.'], 422);
        }

        $previous = $this->latestLocationForRide($rideId);
        if ($previous) {
            $seconds = max(1, time() - strtotime((string) $previous['recorded_at']));
            $distanceKm = $this->distanceKm((float) $previous['latitude'], (float) $previous['longitude'], $latitude, $longitude);
            $speedKph = ($distanceKm / $seconds) * 3600;

            if ($seconds < 90 && $speedKph > 140) {
                Response::json(['success' => false, 'message' => 'GPS jump detected. Waiting for a more accurate location fix.'], 422);
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ride_locations (ride_id, driver_id, latitude, longitude, speed, heading, accuracy, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $rideId,
            (int) $driver['id'],
            $latitude,
            $longitude,
            isset($input['speed']) && $input['speed'] !== '' ? (float) $input['speed'] : null,
            isset($input['heading']) && $input['heading'] !== '' ? (float) $input['heading'] : null,
            $accuracy,
        ]);
        $this->pdo->prepare('UPDATE drivers SET current_latitude = ?, current_longitude = ?, is_online = 1 WHERE id = ?')
            ->execute([$latitude, $longitude, (int) $driver['id']]);

        if (in_array($rideRow['ride_status'], ['assigned', 'driver_arriving'], true)) {
            $this->pdo->prepare('UPDATE rides SET ride_status = "driver_arriving", started_at = COALESCE(started_at, NOW()) WHERE id = ?')->execute([$rideId]);
            $this->pdo->prepare(
                'UPDATE bookings
                 JOIN rides ON rides.booking_id = bookings.id
                 SET bookings.booking_status = "driver_arriving"
                 WHERE rides.id = ?'
            )->execute([$rideId]);
        }

        Response::json(['success' => true, 'message' => 'Location recorded.', 'data' => $this->driverData($user)]);
    }

    private function latestLocationForRide(int $rideId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT latitude, longitude, recorded_at
             FROM ride_locations
             WHERE ride_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$rideId]);
        $location = $stmt->fetch();

        return $location ?: null;
    }

    private function ensureRideLocationGpsColumns(): void
    {
        $columns = [
            'speed' => 'ALTER TABLE ride_locations ADD COLUMN speed DECIMAL(6, 2) NULL AFTER longitude',
            'heading' => 'ALTER TABLE ride_locations ADD COLUMN heading DECIMAL(6, 2) NULL AFTER speed',
            'accuracy' => 'ALTER TABLE ride_locations ADD COLUMN accuracy DECIMAL(7, 2) NULL AFTER heading',
        ];

        foreach ($columns as $column => $sql) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM ride_locations LIKE '" . $column . "'");

            if (!$stmt->fetch()) {
                $this->pdo->exec($sql);
            }
        }
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function transfer(array $params = []): void
    {
        $user = $this->requireUser('driver');
        $input = $this->input();
        $driver = $this->driverByUser((int) $user['id']);
        $rideId = (int) ($params['id'] ?? 0);
        $newDriverId = (int) ($input['driver_id'] ?? $input['driverId'] ?? 0);
        $handoffNote = trim((string) ($input['handoff_note'] ?? $input['handoffNote'] ?? ''));
        $includeRoute = !array_key_exists('includeRoute', $input) && !array_key_exists('include_route', $input)
            ? true
            : (!empty($input['includeRoute']) || !empty($input['include_route']));

        if (!$newDriverId || $newDriverId === (int) $driver['id']) {
            Response::json(['success' => false, 'message' => 'Select another driver for transfer.'], 422);
        }

        $rideStmt = $this->pdo->prepare(
            'SELECT rides.id, rides.booking_id, bookings.scheduled_date, bookings.scheduled_time,
                bookings.dropoff_address, bookings.dropoff_latitude, bookings.dropoff_longitude, bookings.notes
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             WHERE rides.id = ? AND rides.driver_id = ?
             LIMIT 1'
        );
        $rideStmt->execute([$rideId, (int) $driver['id']]);
        $ride = $rideStmt->fetch();

        if (!$ride) {
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
            $bookingIds = $includeRoute ? $this->transferRouteBookingIds($ride, (int) $driver['id']) : [(int) $ride['booking_id']];

            if (!$bookingIds) {
                $bookingIds = [(int) $ride['booking_id']];
            }

            $vehicleCapacity = $this->vehicleCapacityForDriver($newDriverId);
            $routeSeatCount = $this->routeSeatCountAfterClaim($newDriverId, $ride['scheduled_date'], $ride['scheduled_time'], $bookingIds);

            if ($routeSeatCount > $vehicleCapacity) {
                $this->pdo->rollBack();
                Response::json([
                    'success' => false,
                    'message' => 'Target driver capacity is full for this schedule. This handoff needs ' . $routeSeatCount . ' seat' . ($routeSeatCount === 1 ? '' : 's') . ', but the vehicle capacity is ' . $vehicleCapacity . '.',
                ], 409);
            }

            $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
            $conflictStmt = $this->pdo->prepare(
                'SELECT id
                 FROM bookings
                 WHERE assigned_driver_id = ?
                   AND scheduled_date = ?
                   AND scheduled_time = ?
                   AND booking_status NOT IN ("pending", "cancelled", "completed")
                   AND id NOT IN (' . $placeholders . ')
                 LIMIT 1'
            );
            $conflictStmt->execute(array_merge([$newDriverId, $ride['scheduled_date'], $ride['scheduled_time']], $bookingIds));

            if ($conflictStmt->fetchColumn()) {
                $this->pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Target driver already has transport at ' . substr((string) $ride['scheduled_time'], 0, 5) . ' today.'], 409);
            }

            $this->pdo->prepare('UPDATE rides SET driver_id = ? WHERE booking_id IN (' . $placeholders . ') AND driver_id = ?')
                ->execute(array_merge([$newDriverId], $bookingIds, [(int) $driver['id']]));
            $this->pdo->prepare(
                'UPDATE bookings SET assigned_driver_id = ? WHERE id IN (' . $placeholders . ')'
            )->execute(array_merge([$newDriverId], $bookingIds));

            $transferredStops = $this->transferredStopsForBookings($bookingIds);
            $childNames = array_values(array_unique(array_map(fn ($stop) => $stop['studentName'], $transferredStops)));
            $currentDriverName = trim($user['first_name'] . ' ' . $user['last_name']);
            $newDriverName = trim($newDriver['first_name'] . ' ' . $newDriver['last_name']);
            $childrenText = $childNames ? implode(', ', $childNames) : 'the assigned children';
            $noteText = $handoffNote !== '' ? ' Handoff note: ' . $handoffNote : '';
            $transferText = 'Emergency route handoff from ' . $currentDriverName . ' for ' . $childrenText . '.' . $noteText;

            foreach ($transferredStops as $stop) {
                $this->pdo->prepare(
                    'INSERT INTO messages (sender_user_id, receiver_user_id, ride_id, message_text, message_type)
                     VALUES (?, ?, ?, ?, "system")'
                )->execute([(int) $user['id'], (int) $newDriver['user_id'], (int) $stop['rideId'], $transferText]);

                if (!empty($stop['parentUserId'])) {
                    $this->notifyUser(
                        (int) $stop['parentUserId'],
                        'Driver transfer alert',
                        $currentDriverName . ' transferred ' . $stop['studentName'] . '\'s route to ' . $newDriverName . ' because of a driver emergency.' . $noteText,
                        'booking',
                        (int) $stop['bookingId']
                    );
                }
            }

            $this->notifyUser((int) $newDriver['user_id'], 'Emergency route handoff', 'A route for ' . $childrenText . ' was transferred to you.' . $noteText, 'booking', (int) $ride['booking_id']);
            $this->notifyUser((int) $user['id'], 'Transport transferred', 'The route for ' . $childrenText . ' was transferred to ' . $newDriverName . '.', 'booking', (int) $ride['booking_id']);

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
        $this->ensureRecurringGroupColumn();
        $this->ensureBookingMonthlyPlanColumns();
        $this->ensureDriverPayoutsTable();
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.id AS student_user_id, su.first_name AS student_first_name, su.last_name AS student_last_name,
                students.id AS student_id, students.lrn AS student_lrn, students.school_name AS student_school_name, students.grade_level AS student_grade_level,
                bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time, bookings.trip_type, bookings.notes, bookings.recurring_group_id, bookings.monthly_plan_id, bookings.monthly_plan_route_id, bookings.driver_payout_amount, bookings.booking_status,
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
              WHERE rides.driver_id = ?
                AND bookings.scheduled_date = CURDATE()
                AND rides.ride_status NOT IN ("completed", "cancelled")
                AND bookings.booking_status NOT IN ("completed", "cancelled")
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
                'pickupTime' => $ride['scheduledTime'],
                'requestedPickupTime' => $ride['requestedPickupTime'],
                'dropoffTime' => $ride['plannedDropoffTime'],
                'adjustedPickupTime' => $ride['adjustedPickupTime'],
                'tripType' => $ride['tripType'],
                'routeDirection' => $ride['routeDirection'],
                'recurringGroupId' => $ride['recurringGroupId'],
                'monthlyPlanId' => $ride['monthlyPlanId'],
                'monthlyPlanRouteId' => $ride['monthlyPlanRouteId'],
                'driverPayoutAmount' => $ride['driverPayoutAmount'],
                'status' => $ride['status'],
                'driverName' => $ride['parentName'],
                'parentName' => $ride['parentName'],
                'pickupLatitude' => $ride['pickupLocation']['latitude'],
                'pickupLongitude' => $ride['pickupLocation']['longitude'],
                'dropoffLatitude' => $ride['dropoffLocation']['latitude'],
                'dropoffLongitude' => $ride['dropoffLocation']['longitude'],
                'pickupLocation' => $ride['pickupLocation'],
                'dropoffLocation' => $ride['dropoffLocation'],
                'carpoolStops' => $ride['carpoolStops'],
                'orderedStops' => $ride['orderedStops'],
                'routePoints' => $ride['routePoints'],
                'canApprove' => false,
            ], $rides)),
            'students' => $this->driverStudentsFromRides($rides),
            'transactions' => $this->transactionsForDriver((int) $driver['id']),
            'messages' => $this->messagesForUser((int) $user['id']),
            'notifications' => $this->notificationsForUser((int) $user['id'], $user['role_code']),
        ];
    }

    private function transactionsForDriver(int $driverId): array
    {
        $this->ensureDriverPayoutsTable();
        $stmt = $this->pdo->prepare(
            'SELECT driver_payouts.*, rides.ride_status, rides.completed_at,
                bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time,
                su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM driver_payouts
             JOIN rides ON rides.id = driver_payouts.ride_id
             JOIN bookings ON bookings.id = driver_payouts.booking_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE driver_payouts.driver_id = ?
             ORDER BY COALESCE(driver_payouts.paid_at, driver_payouts.created_at) DESC, driver_payouts.id DESC
             LIMIT 100'
        );
        $stmt->execute([$driverId]);

        return array_map(fn ($payout) => [
            'id' => 'payout-' . (int) $payout['id'],
            'type' => 'payout',
            'role' => 'driver',
            'title' => 'Completed transport payout',
            'description' => trim($payout['student_first_name'] . ' ' . $payout['student_last_name']) . ' / Parent: ' . trim($payout['parent_first_name'] . ' ' . $payout['parent_last_name']),
            'amount' => (float) $payout['amount'],
            'status' => $payout['status'],
            'method' => 'driver payout',
            'reference' => 'Ride #' . (int) $payout['ride_id'],
            'date' => $payout['paid_at'] ?: ($payout['completed_at'] ?: $payout['created_at']),
            'scheduledDate' => $payout['scheduled_date'],
            'scheduledTime' => substr((string) ($payout['pickup_time'] ?: $payout['scheduled_time']), 0, 5),
            'rideId' => (int) $payout['ride_id'],
            'bookingId' => (int) $payout['booking_id'],
        ], $stmt->fetchAll());
    }

    private function driverStudentsFromRides(array $rides): array
    {
        $students = [];

        foreach ($rides as $ride) {
            $key = !empty($ride['studentId']) ? 'student-' . $ride['studentId'] : strtolower($ride['studentName'] . '|' . $ride['parentName']);

            if (isset($students[$key])) {
                $students[$key]['assignmentCount']++;
                continue;
            }

            $students[$key] = [
                'id' => $ride['studentId'] ?: $ride['bookingId'],
                'studentUserId' => $ride['studentUserId'],
                'name' => $ride['studentName'],
                'lrn' => $ride['lrn'],
                'schoolName' => $ride['schoolName'],
                'gradeLevel' => $ride['gradeLevel'],
                'pickupAddress' => $ride['pickupAddress'],
                'dropoffAddress' => $ride['dropoffAddress'],
                'emergencyContact' => $ride['parentName'],
                'assignmentCount' => 1,
            ];
        }

        return array_values($students);
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

    private function ensureRecurringGroupColumn(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM bookings LIKE 'recurring_group_id'");

        if (!$stmt->fetch()) {
            $this->pdo->exec('ALTER TABLE bookings ADD COLUMN recurring_group_id VARCHAR(64) NULL AFTER assigned_driver_id');
        }
    }

    private function ensureBookingMonthlyPlanColumns(): void
    {
        $columns = [
            'monthly_plan_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_id BIGINT UNSIGNED NULL AFTER recurring_group_id',
            'monthly_plan_route_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_route_id BIGINT UNSIGNED NULL AFTER monthly_plan_id',
            'driver_payout_amount' => 'ALTER TABLE bookings ADD COLUMN driver_payout_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER monthly_plan_route_id',
            'pickup_time' => 'ALTER TABLE bookings ADD COLUMN pickup_time TIME NULL AFTER scheduled_time',
            'dropoff_time' => 'ALTER TABLE bookings ADD COLUMN dropoff_time TIME NULL AFTER pickup_time',
            'adjusted_pickup_time' => 'ALTER TABLE bookings ADD COLUMN adjusted_pickup_time TIME NULL AFTER dropoff_time',
        ];

        foreach ($columns as $column => $sql) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM bookings LIKE '" . $column . "'");

            if (!$stmt->fetch()) {
                $this->pdo->exec($sql);
            }
        }
    }

    private function ensureDriverPayoutsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS driver_payouts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                driver_id BIGINT UNSIGNED NOT NULL,
                ride_id BIGINT UNSIGNED NOT NULL,
                booking_id BIGINT UNSIGNED NOT NULL,
                amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                status ENUM("pending", "paid", "cancelled") NOT NULL DEFAULT "paid",
                paid_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_driver_payouts_ride (ride_id),
                CONSTRAINT fk_driver_payouts_driver FOREIGN KEY (driver_id) REFERENCES drivers(id),
                CONSTRAINT fk_driver_payouts_ride FOREIGN KEY (ride_id) REFERENCES rides(id),
                CONSTRAINT fk_driver_payouts_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
            )'
        );
    }

    private function ensureRideDropoffProofColumns(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM rides LIKE 'dropoff_photo_path'");

        if (!$stmt->fetch()) {
            $this->pdo->exec('ALTER TABLE rides ADD COLUMN dropoff_photo_path VARCHAR(255) NULL AFTER dropped_off_at');
        }
    }

    private function recordDriverPayout(int $rideId, int $driverId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT rides.booking_id, bookings.driver_payout_amount
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             WHERE rides.id = ? AND rides.driver_id = ?
             LIMIT 1'
        );
        $stmt->execute([$rideId, $driverId]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO driver_payouts (driver_id, ride_id, booking_id, amount, status, paid_at)
             VALUES (?, ?, ?, ?, "paid", NOW())
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = "paid", paid_at = NOW()'
        )->execute([$driverId, $rideId, (int) $row['booking_id'], (float) $row['driver_payout_amount']]);
    }

    private function pendingBookingsForDriver(int $driverId): array
    {
        $this->ensureBookingMonthlyPlanColumns();
        $vehicleCapacity = $this->vehicleCapacityForDriver($driverId);
        $stmt = $this->pdo->prepare(
            'SELECT bookings.*, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM bookings
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE (bookings.assigned_driver_id = ? OR bookings.assigned_driver_id IS NULL)
               AND bookings.booking_status = "pending"
               AND bookings.scheduled_date = CURDATE()
             ORDER BY bookings.scheduled_date ASC, bookings.scheduled_time ASC, bookings.created_at DESC'
        );
        $stmt->execute([$driverId]);

        return array_map(function ($booking) use ($driverId, $vehicleCapacity) {
            $candidateIds = $this->capacityCandidateBookingIds($booking, $driverId);
            $routeSeatCount = $this->routeSeatCountAfterClaim($driverId, $booking['scheduled_date'], $booking['pickup_time'] ?: $booking['scheduled_time'], $candidateIds);
            $schedule = $this->routeScheduleForClaim($driverId, $booking, $candidateIds);
            $hasCapacity = $routeSeatCount <= $vehicleCapacity;
            $canApprove = $hasCapacity && $schedule['feasible'];

            return [
                'id' => (int) $booking['id'],
                'studentId' => (int) $booking['student_id'],
                'studentName' => trim($booking['student_first_name'] . ' ' . $booking['student_last_name']),
                'parentName' => trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']),
                'driverName' => trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']),
                'pickupAddress' => $booking['pickup_address'],
                'dropoffAddress' => $booking['dropoff_address'],
                'pickupLatitude' => $booking['pickup_latitude'],
                'pickupLongitude' => $booking['pickup_longitude'],
                'dropoffLatitude' => $booking['dropoff_latitude'],
                'dropoffLongitude' => $booking['dropoff_longitude'],
                'scheduledDate' => $booking['scheduled_date'],
                'scheduledTime' => substr((string) ($booking['adjusted_pickup_time'] ?: $booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
                'pickupTime' => substr((string) ($booking['adjusted_pickup_time'] ?: $booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
                'requestedPickupTime' => substr((string) ($booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
                'dropoffTime' => !empty($booking['dropoff_time']) ? substr((string) $booking['dropoff_time'], 0, 5) : '',
                'adjustedPickupTime' => !empty($booking['adjusted_pickup_time']) ? substr((string) $booking['adjusted_pickup_time'], 0, 5) : null,
                'tripType' => $booking['trip_type'],
                'routeDirection' => str_contains(strtolower((string) ($booking['notes'] ?? '')), 'return') ? 'return_home' : 'to_school',
                'recurringGroupId' => $booking['recurring_group_id'] ?? null,
                'monthlyPlanId' => $booking['monthly_plan_id'] ? (int) $booking['monthly_plan_id'] : null,
                'monthlyPlanRouteId' => $booking['monthly_plan_route_id'] ? (int) $booking['monthly_plan_route_id'] : null,
                'driverPayoutAmount' => (float) $booking['driver_payout_amount'],
                'status' => $booking['booking_status'],
                'vehicleCapacity' => $vehicleCapacity,
                'routeSeatCount' => $routeSeatCount,
                'capacityRemaining' => max(0, $vehicleCapacity - $routeSeatCount),
                'capacityMessage' => $hasCapacity ? ($schedule['feasible'] ? null : $schedule['message']) : 'Vehicle capacity is full for this schedule.',
                'scheduleMessage' => $schedule['feasible'] ? null : $schedule['message'],
                'canApprove' => $canApprove,
            ];
        }, $stmt->fetchAll());
    }

    private function vehicleCapacityForDriver(int $driverId): int
    {
        $vehicle = $this->vehicleByDriverId($driverId);

        return max(1, (int) ($vehicle['capacity'] ?? 1));
    }

    private function routeSeatCountAfterClaim(int $driverId, string $scheduledDate, string $scheduledTime, array $candidateBookingIds): int
    {
        $seatIds = [];

        foreach ($candidateBookingIds as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $seatIds[$id] = true;
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM bookings
             WHERE assigned_driver_id = ?
               AND scheduled_date = ?
               AND COALESCE(pickup_time, scheduled_time) = ?
               AND booking_status NOT IN ("pending", "cancelled", "completed")'
        );
        $stmt->execute([$driverId, $scheduledDate, $scheduledTime]);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $seatIds[(int) $id] = true;
        }

        return count($seatIds);
    }

    private function routeScheduleForClaim(int $driverId, array $sourceBooking, array $candidateBookingIds): array
    {
        $candidateBookingIds = array_values(array_unique(array_filter(array_map('intval', $candidateBookingIds), fn ($id) => $id > 0)));

        if (!$candidateBookingIds) {
            $candidateBookingIds = [(int) $sourceBooking['id']];
        }

        $pickupTime = (string) ($sourceBooking['pickup_time'] ?: $sourceBooking['scheduled_time']);
        $direction = str_contains(strtolower((string) ($sourceBooking['notes'] ?? '')), 'return') ? 'return_home' : 'to_school';
        $placeholders = implode(', ', array_fill(0, count($candidateBookingIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT bookings.id, bookings.parent_id, parents.user_id AS parent_user_id,
                bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time,
                bookings.notes, su.first_name AS student_first_name, su.last_name AS student_last_name
             FROM bookings
             JOIN parents ON parents.id = bookings.parent_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             WHERE bookings.scheduled_date = ?
               AND (
                    (
                        bookings.assigned_driver_id = ?
                        AND COALESCE(bookings.pickup_time, bookings.scheduled_time) = ?
                        AND bookings.booking_status NOT IN ("pending", "cancelled", "completed")
                    )
                    OR bookings.id IN (' . $placeholders . ')
               )'
        );
        $stmt->execute([
            $sourceBooking['scheduled_date'],
            $driverId,
            $pickupTime,
            ...$candidateBookingIds,
        ]);
        $bookings = array_values(array_filter($stmt->fetchAll(), function ($booking) use ($direction) {
            $bookingDirection = str_contains(strtolower((string) ($booking['notes'] ?? '')), 'return') ? 'return_home' : 'to_school';

            return $bookingDirection === $direction;
        }));

        usort($bookings, fn ($left, $right) => [
            $this->minutesFromTime((string) ($left['pickup_time'] ?: $left['scheduled_time'])),
            (int) $left['id'],
        ] <=> [
            $this->minutesFromTime((string) ($right['pickup_time'] ?: $right['scheduled_time'])),
            (int) $right['id'],
        ]);

        $schedule = [];
        $currentMinutes = null;
        $currentLat = null;
        $currentLng = null;

        foreach ($bookings as $booking) {
            $requestedPickup = $this->minutesFromTime((string) ($booking['pickup_time'] ?: $booking['scheduled_time']));
            $pickupLat = $this->bookingCoordinate($booking['pickup_latitude'], 10.676344);
            $pickupLng = $this->bookingCoordinate($booking['pickup_longitude'], 122.953221);
            $travelMinutes = $currentMinutes === null ? 0 : $this->travelMinutes($currentLat, $currentLng, $pickupLat, $pickupLng);
            $actualPickup = max($requestedPickup, ($currentMinutes ?? $requestedPickup) + $travelMinutes);

            $schedule[] = [
                'booking' => $booking,
                'requestedPickupMinutes' => $requestedPickup,
                'actualPickupMinutes' => $actualPickup,
            ];
            $currentMinutes = $actualPickup;
            $currentLat = $pickupLat;
            $currentLng = $pickupLng;
        }

        foreach ($schedule as $index => $item) {
            $booking = $item['booking'];
            $dropoffLat = $this->bookingCoordinate($booking['dropoff_latitude'], 10.668364);
            $dropoffLng = $this->bookingCoordinate($booking['dropoff_longitude'], 123.019768);
            $currentMinutes += $this->travelMinutes($currentLat, $currentLng, $dropoffLat, $dropoffLng);
            $deadline = !empty($booking['dropoff_time'])
                ? $this->minutesFromTime((string) $booking['dropoff_time'])
                : $item['requestedPickupMinutes'] + $this->travelMinutes(
                    $this->bookingCoordinate($booking['pickup_latitude'], 10.676344),
                    $this->bookingCoordinate($booking['pickup_longitude'], 122.953221),
                    $dropoffLat,
                    $dropoffLng
                ) + 15;

            $schedule[$index]['actualDropoffMinutes'] = $currentMinutes;
            $schedule[$index]['dropoffDeadlineMinutes'] = $deadline;

            if ($currentMinutes > $deadline) {
                $studentName = trim($booking['student_first_name'] . ' ' . $booking['student_last_name']);

                return [
                    'feasible' => false,
                    'message' => 'Cannot add ' . $studentName . ': the 50 km/h route estimate reaches drop-off at ' . $this->formatMinutesAsTime($currentMinutes) . ', after the ' . $this->formatMinutesAsTime($deadline) . ' deadline.',
                    'schedule' => $schedule,
                ];
            }

            $currentLat = $dropoffLat;
            $currentLng = $dropoffLng;
        }

        return ['feasible' => true, 'message' => null, 'schedule' => $schedule];
    }

    private function applyPickupSchedule(array $schedule, string $driverName): void
    {
        $stmt = $this->pdo->prepare('UPDATE bookings SET adjusted_pickup_time = ? WHERE id = ?');

        foreach ($schedule as $item) {
            $booking = $item['booking'];
            $newTime = $this->formatMinutesAsSqlTime((int) $item['actualPickupMinutes']);
            $oldTime = (string) ($booking['adjusted_pickup_time'] ?: $booking['pickup_time'] ?: $booking['scheduled_time']);
            $stmt->execute([$newTime, (int) $booking['id']]);

            if (substr($oldTime, 0, 5) !== substr($newTime, 0, 5)) {
                $studentName = trim($booking['student_first_name'] . ' ' . $booking['student_last_name']);
                $this->notifyUser(
                    (int) $booking['parent_user_id'],
                    'Pickup time adjusted',
                    $driverName . '\'s carpool route moved ' . $studentName . '\'s pickup to ' . substr($newTime, 0, 5) . ' to keep the route feasible.',
                    'booking',
                    (int) $booking['id']
                );
            }
        }
    }

    private function travelMinutes(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        return max(1, (int) ceil(($this->distanceKm($fromLat, $fromLng, $toLat, $toLng) / self::CARPOOL_SPEED_KPH) * 60));
    }

    private function bookingCoordinate($value, float $fallback): float
    {
        return trim((string) $value) === '' ? $fallback : (float) $value;
    }

    private function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return ($hour * 60) + $minute;
    }

    private function formatMinutesAsTime(int $minutes): string
    {
        $minutes = max(0, min((24 * 60) - 1, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatMinutesAsSqlTime(int $minutes): string
    {
        return $this->formatMinutesAsTime($minutes) . ':00';
    }

    private function capacityCandidateBookingIds(array $booking, int $driverId): array
    {
        if (!empty($booking['monthly_plan_route_id'])) {
            $stmt = $this->pdo->prepare(
                'SELECT id
                 FROM bookings
                 WHERE monthly_plan_route_id = ?
                   AND scheduled_date = ?
                   AND scheduled_time = ?
                   AND booking_status = "pending"
                   AND assigned_driver_id IS NULL'
            );
            $stmt->execute([$booking['monthly_plan_route_id'], $booking['scheduled_date'], $booking['scheduled_time']]);
            $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            return $ids ?: [(int) $booking['id']];
        }

        if (!empty($booking['recurring_group_id'])) {
            $stmt = $this->pdo->prepare(
                'SELECT id
                 FROM bookings
                 WHERE recurring_group_id = ?
                   AND assigned_driver_id = ?
                   AND booking_status = "pending"'
            );
            $stmt->execute([$booking['recurring_group_id'], $driverId]);
            $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            return $ids ?: [(int) $booking['id']];
        }

        return [(int) $booking['id']];
    }

    private function currentDayCarpoolBookingIds(array $booking, int $driverId): array
    {
        $params = [
            $booking['pickup_time'] ?: $booking['scheduled_time'],
            $driverId,
        ];
        $routeDirectionSql = $this->routeDirectionMatchSql($booking, $params);
        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM bookings
             WHERE scheduled_date = CURDATE()
               AND COALESCE(pickup_time, scheduled_time) = ?
               AND (
                   (booking_status = "pending" AND assigned_driver_id IS NULL)
                   OR (assigned_driver_id = ? AND booking_status NOT IN ("cancelled", "completed"))
               )
               AND ' . $routeDirectionSql . '
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        return $ids ?: [(int) $booking['id']];
    }

    private function transferRouteBookingIds(array $ride, int $driverId): array
    {
        $params = [
            $driverId,
            $ride['scheduled_date'],
            $ride['scheduled_time'],
        ];
        $dropoffSql = $this->dropoffMatchSql($ride, $params);
        $routeDirectionSql = $this->routeDirectionMatchSql($ride, $params);
        $stmt = $this->pdo->prepare(
            'SELECT bookings.id
             FROM bookings
             JOIN rides ON rides.booking_id = bookings.id
             WHERE rides.driver_id = ?
               AND bookings.scheduled_date = ?
               AND bookings.scheduled_time = ?
               AND bookings.booking_status NOT IN ("cancelled", "completed")
               AND ' . $dropoffSql . '
               AND ' . $routeDirectionSql . '
             ORDER BY bookings.id ASC'
        );
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        return $ids ?: [(int) $ride['booking_id']];
    }

    private function transferredStopsForBookings(array $bookingIds): array
    {
        if (!$bookingIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT bookings.id AS booking_id, rides.id AS ride_id, parents.user_id AS parent_user_id,
                su.first_name AS student_first_name, su.last_name AS student_last_name
             FROM bookings
             JOIN rides ON rides.booking_id = bookings.id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             WHERE bookings.id IN (' . $placeholders . ')
             ORDER BY bookings.id ASC'
        );
        $stmt->execute($bookingIds);

        return array_map(fn ($stop) => [
            'bookingId' => (int) $stop['booking_id'],
            'rideId' => (int) $stop['ride_id'],
            'parentUserId' => (int) $stop['parent_user_id'],
            'studentName' => trim($stop['student_first_name'] . ' ' . $stop['student_last_name']),
        ], $stmt->fetchAll());
    }

    private function dropoffMatchSql(array $source, array &$params): string
    {
        $address = trim((string) ($source['dropoff_address'] ?? ''));
        $hasCoordinates = ($source['dropoff_latitude'] ?? null) !== null && ($source['dropoff_latitude'] ?? '') !== ''
            && ($source['dropoff_longitude'] ?? null) !== null && ($source['dropoff_longitude'] ?? '') !== '';

        if ($hasCoordinates) {
            $params[] = (float) $source['dropoff_latitude'];
            $params[] = (float) $source['dropoff_longitude'];
            $params[] = $address;

            return '(
                (
                    bookings.dropoff_latitude IS NOT NULL
                    AND bookings.dropoff_longitude IS NOT NULL
                    AND ABS(bookings.dropoff_latitude - ?) < 0.0001
                    AND ABS(bookings.dropoff_longitude - ?) < 0.0001
                )
                OR LOWER(TRIM(bookings.dropoff_address)) = LOWER(TRIM(?))
            )';
        }

        $params[] = $address;

        return 'LOWER(TRIM(bookings.dropoff_address)) = LOWER(TRIM(?))';
    }

    private function routeDirectionMatchSql(array $source, array &$params): string
    {
        $direction = str_contains(strtolower((string) ($source['notes'] ?? '')), 'return') ? 'return_home' : 'to_school';
        $params[] = $direction;
        $params[] = $direction;

        return '(
            (? = "return_home" AND LOWER(COALESCE(bookings.notes, "")) LIKE "%return%")
            OR (? = "to_school" AND LOWER(COALESCE(bookings.notes, "")) NOT LIKE "%return%")
        )';
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

    private function parentUserIdsForBookings(array $bookingIds): array
    {
        if (!$bookingIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT parents.user_id
             FROM bookings
             JOIN parents ON parents.id = bookings.parent_id
             WHERE bookings.id IN (' . $placeholders . ')'
        );
        $stmt->execute($bookingIds);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function tripUserIdsForRide(int $rideId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT parents.user_id AS parent_user_id, students.user_id AS student_user_id
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN students ON students.id = bookings.student_id
             WHERE rides.id = ?
             LIMIT 1'
        );
        $stmt->execute([$rideId]);
        $users = $stmt->fetch();

        return [
            'parentUserId' => !empty($users['parent_user_id']) ? (int) $users['parent_user_id'] : null,
            'studentUserId' => !empty($users['student_user_id']) ? (int) $users['student_user_id'] : null,
        ];
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
        $carpoolStops = $this->carpoolStopsForRide($ride);
        $routePoints = array_map(fn ($stop) => $stop['pickupLocation'], $carpoolStops);

        if (!$routePoints) {
            $routePoints[] = ['latitude' => $pickupLat, 'longitude' => $pickupLng];
        }

        $routePoints[] = ['latitude' => $dropoffLat, 'longitude' => $dropoffLng];
        $orderedStops = $this->orderedStopsForRide($ride, $carpoolStops, $driverLat, $driverLng);

        return [
            'id' => (int) $ride['id'],
            'bookingId' => (int) $ride['booking_id'],
            'studentId' => (int) $ride['student_id'],
            'studentUserId' => (int) $ride['student_user_id'],
            'parentUserId' => (int) $ride['parent_user_id'],
            'driverUserId' => (int) $ride['driver_user_id'],
            'studentName' => trim($ride['student_first_name'] . ' ' . $ride['student_last_name']),
            'lrn' => $ride['student_lrn'],
            'schoolName' => $ride['student_school_name'],
            'gradeLevel' => $ride['student_grade_level'],
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
            'scheduledDate' => $ride['scheduled_date'],
            'scheduledTime' => substr((string) ($ride['adjusted_pickup_time'] ?: $ride['pickup_time'] ?: $ride['scheduled_time']), 0, 5),
            'requestedPickupTime' => substr((string) ($ride['pickup_time'] ?: $ride['scheduled_time']), 0, 5),
            'adjustedPickupTime' => !empty($ride['adjusted_pickup_time']) ? substr((string) $ride['adjusted_pickup_time'], 0, 5) : null,
            'plannedDropoffTime' => !empty($ride['dropoff_time']) ? substr((string) $ride['dropoff_time'], 0, 5) : '',
            'tripType' => $ride['trip_type'],
            'routeDirection' => str_contains(strtolower((string) ($ride['notes'] ?? '')), 'return') ? 'return_home' : 'to_school',
            'recurringGroupId' => $ride['recurring_group_id'] ?? null,
            'monthlyPlanId' => $ride['monthly_plan_id'] ? (int) $ride['monthly_plan_id'] : null,
            'monthlyPlanRouteId' => $ride['monthly_plan_route_id'] ? (int) $ride['monthly_plan_route_id'] : null,
            'driverPayoutAmount' => (float) $ride['driver_payout_amount'],
            'etaMinutes' => 0,
            'distanceKm' => 0,
            'pickupTime' => $ride['started_at'] ?: '',
            'dropoffTime' => $ride['dropped_off_at'] ?: '',
            'dropoffPhotoUrl' => $this->fileUrl($ride['dropoff_photo_path'] ?? null),
            'progress' => $ride['ride_status'] === 'completed' ? 1 : 0,
            'currentPointIndex' => 0,
            'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
            'hasDriverLocation' => $hasDriverLocation,
            'location' => $hasDriverLocation ? ['latitude' => $driverLat, 'longitude' => $driverLng, 'latitudeDelta' => 0.03, 'longitudeDelta' => 0.03] : null,
            'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
            'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            'carpoolStops' => $carpoolStops,
            'orderedStops' => $orderedStops,
            'routePoints' => $routePoints,
        ];
    }

    private function carpoolStopsForRide(array $ride): array
    {
        if (empty($ride['driver_id']) || empty($ride['scheduled_date']) || empty($ride['scheduled_time'])) {
            return [];
        }

        $params = [
            (int) $ride['driver_id'],
            $ride['scheduled_date'],
            $ride['pickup_time'] ?: $ride['scheduled_time'],
        ];
        $routeDirectionSql = $this->routeDirectionMatchSql($ride, $params);
        $stmt = $this->pdo->prepare(
            'SELECT rides.id AS ride_id, rides.ride_status, rides.picked_up_at, rides.dropped_off_at, rides.completed_at,
                bookings.id AS booking_id, bookings.pickup_address, bookings.dropoff_address, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE rides.driver_id = ?
               AND bookings.scheduled_date = ?
               AND COALESCE(bookings.pickup_time, bookings.scheduled_time) = ?
               AND bookings.booking_status NOT IN ("cancelled")
               AND (rides.completed_at IS NULL OR rides.completed_at >= (NOW() - INTERVAL 1 DAY))
               AND ' . $routeDirectionSql . '
             ORDER BY bookings.scheduled_time ASC, bookings.id ASC'
        );
        $stmt->execute($params);

        $stops = [];

        foreach ($stmt->fetchAll() as $index => $stop) {
            $stops[] = [
                'rideId' => (int) $stop['ride_id'],
                'bookingId' => (int) $stop['booking_id'],
                'studentName' => trim($stop['student_first_name'] . ' ' . $stop['student_last_name']),
                'parentName' => trim($stop['parent_first_name'] . ' ' . $stop['parent_last_name']),
                'pickupAddress' => $stop['pickup_address'],
                'dropoffAddress' => $stop['dropoff_address'],
                'expectedPickupTime' => substr((string) ($stop['adjusted_pickup_time'] ?: $this->timeWithOffset((string) ($stop['pickup_time'] ?: $stop['scheduled_time']), $index * 4)), 0, 5),
                'status' => ucwords(str_replace('_', ' ', (string) $stop['ride_status'])),
                'rawStatus' => $stop['ride_status'],
                'pickupTime' => $stop['picked_up_at'] ?: '',
                'dropoffTime' => $stop['dropped_off_at'] ?: ($stop['completed_at'] ?: ''),
                'plannedDropoffTime' => !empty($stop['dropoff_time']) ? substr((string) $stop['dropoff_time'], 0, 5) : '',
                'pickupLocation' => [
                    'latitude' => (float) ($stop['pickup_latitude'] ?: 10.676344),
                    'longitude' => (float) ($stop['pickup_longitude'] ?: 122.953221),
                ],
                'dropoffLocation' => [
                    'latitude' => (float) ($stop['dropoff_latitude'] ?: 10.668364),
                    'longitude' => (float) ($stop['dropoff_longitude'] ?: 123.019768),
                ],
            ];
        }

        return $stops;
    }

    private function orderedStopsForRide(array $ride, array $pickupStops, ?float $driverLat, ?float $driverLng): array
    {
        $scheduledTime = (string) (($ride['pickup_time'] ?? null) ?: ($ride['scheduled_time'] ?? '07:00:00'));
        $stops = [];
        $passengers = 0;

        foreach ($pickupStops as $index => $stop) {
            $passengers++;
            $stops[] = [
                'id' => 'pickup-' . $stop['rideId'],
                'type' => 'pickup',
                'rideId' => $stop['rideId'],
                'bookingId' => $stop['bookingId'],
                'studentName' => $stop['studentName'],
                'parentName' => $stop['parentName'],
                'address' => $stop['pickupAddress'],
                'location' => $stop['pickupLocation'],
                'expectedTime' => $stop['expectedPickupTime'] ?? $this->timeWithOffset($scheduledTime, $index * 4),
                'etaMinutes' => $this->stopEtaMinutes($driverLat, $driverLng, $stop['pickupLocation'], $index * 4),
                'passengerCount' => $passengers,
                'status' => $this->pickupStopStatus((string) ($stop['rawStatus'] ?? 'assigned')),
                'rawStatus' => $stop['rawStatus'] ?? 'assigned',
            ];
        }

        foreach ($pickupStops as $index => $stop) {
            $passengers = max(0, $passengers - 1);
            $stops[] = [
                'id' => 'dropoff-' . $stop['rideId'],
                'type' => 'dropoff',
                'rideId' => $stop['rideId'],
                'bookingId' => $stop['bookingId'],
                'studentName' => $stop['studentName'],
                'parentName' => $stop['parentName'],
                'address' => $stop['dropoffAddress'] ?? ($ride['dropoff_address'] ?? ''),
                'location' => $stop['dropoffLocation'] ?? ['latitude' => (float) ($ride['dropoff_latitude'] ?: 10.668364), 'longitude' => (float) ($ride['dropoff_longitude'] ?: 123.019768)],
                'expectedTime' => !empty($stop['plannedDropoffTime']) ? $stop['plannedDropoffTime'] : $this->timeWithOffset($scheduledTime, (count($pickupStops) * 4) + 12 + ($index * 2)),
                'etaMinutes' => $this->stopEtaMinutes($driverLat, $driverLng, $stop['dropoffLocation'] ?? null, (count($pickupStops) * 4) + 12 + ($index * 2)),
                'passengerCount' => $passengers,
                'status' => $this->dropoffStopStatus((string) ($stop['rawStatus'] ?? 'assigned')),
                'rawStatus' => $stop['rawStatus'] ?? 'assigned',
            ];
        }

        return $stops;
    }

    private function stopEtaMinutes(?float $driverLat, ?float $driverLng, ?array $location, int $fallbackMinutes): int
    {
        if ($driverLat !== null && $driverLng !== null && $location) {
            return max(1, (int) ceil(($this->distanceKm($driverLat, $driverLng, (float) $location['latitude'], (float) $location['longitude']) / 25) * 60));
        }

        return max(1, $fallbackMinutes);
    }

    private function timeWithOffset(string $time, int $minutes): string
    {
        $timestamp = strtotime('1970-01-01 ' . $time);

        if (!$timestamp) {
            $timestamp = strtotime('1970-01-01 07:00:00');
        }

        return date('H:i', $timestamp + ($minutes * 60));
    }

    private function pickupStopStatus(string $status): string
    {
        return in_array($status, ['picked_up', 'in_transit', 'dropped_off', 'completed'], true) ? 'Boarded' : 'Waiting';
    }

    private function dropoffStopStatus(string $status): string
    {
        if (in_array($status, ['dropped_off', 'completed'], true)) {
            return 'Dropped Off';
        }

        return in_array($status, ['picked_up', 'in_transit'], true) ? 'On Board' : 'Pending Pickup';
    }
}

