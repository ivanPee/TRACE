<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class RideController extends ApiController
{
    public function track(array $params = []): void
    {
        $user = $this->requireUser();
        $this->ensureRideLocationGpsColumns();
        $this->ensureBookingScheduleColumns();
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                bookings.dropoff_address, bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time, bookings.notes,
                parents.user_id AS parent_user_id, students.user_id AS student_user_id, drivers.user_id AS driver_user_id,
                drivers.current_latitude, drivers.current_longitude, latest_location.latitude AS latest_latitude,
                latest_location.longitude AS latest_longitude, latest_location.accuracy AS latest_accuracy,
                latest_location.recorded_at AS latest_recorded_at,
                users.first_name, users.last_name
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN students ON students.id = bookings.student_id
             JOIN drivers ON drivers.id = rides.driver_id
             LEFT JOIN ride_locations latest_location ON latest_location.id = (
                SELECT ride_locations.id
                FROM ride_locations
                WHERE ride_locations.ride_id = rides.id
                ORDER BY ride_locations.recorded_at DESC, ride_locations.id DESC
                LIMIT 1
             )
             JOIN users ON users.id = drivers.user_id
             WHERE rides.id = ?
             LIMIT 1'
        );
        $stmt->execute([(int) ($params['id'] ?? 0)]);
        $ride = $stmt->fetch();

        if (!$ride) {
            Response::json(['success' => false, 'message' => 'Ride not found.'], 404);
        }

        if (!in_array((int) $user['id'], [(int) $ride['parent_user_id'], (int) $ride['student_user_id'], (int) $ride['driver_user_id']], true)) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $locationHistory = $this->latestLocationHistory((int) $ride['id']);
        $latestPoint = $locationHistory[0] ?? null;
        $previousPoint = $locationHistory[1] ?? null;
        $driverLatRaw = $ride['latest_latitude'] ?: ($ride['current_latitude'] ?: null);
        $driverLngRaw = $ride['latest_longitude'] ?: ($ride['current_longitude'] ?: null);
        $hasDriverLocation = $driverLatRaw !== null && $driverLngRaw !== null;
        $driverLat = $hasDriverLocation ? (float) $driverLatRaw : null;
        $driverLng = $hasDriverLocation ? (float) $driverLngRaw : null;
        $pickupLat = (float) ($ride['pickup_latitude'] ?: 10.676344);
        $pickupLng = (float) ($ride['pickup_longitude'] ?: 122.953221);
        $dropoffLat = (float) ($ride['dropoff_latitude'] ?: 10.668364);
        $dropoffLng = (float) ($ride['dropoff_longitude'] ?: 123.019768);
        $carpoolStops = $this->carpoolStopsForRide($ride);
        $orderedStops = $this->orderedStopsForRide($ride, $carpoolStops, $driverLat, $driverLng);
        $rideStatus = strtolower((string) $ride['ride_status']);
        $isPickedUp = in_array($rideStatus, ['picked_up', 'in_transit', 'dropped_off', 'completed'], true);
        $distanceToPickup = $hasDriverLocation ? $this->distanceKm($driverLat, $driverLng, $pickupLat, $pickupLng) : null;
        $distanceToDropoff = $hasDriverLocation ? $this->distanceKm($driverLat, $driverLng, $dropoffLat, $dropoffLng) : null;
        $targetLat = $isPickedUp ? $dropoffLat : $pickupLat;
        $targetLng = $isPickedUp ? $dropoffLng : $pickupLng;
        $remainingDistance = $isPickedUp ? $distanceToDropoff : $distanceToPickup;
        $dropoffRouteDistance = $this->distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $progress = $rideStatus === 'completed'
            ? 1
            : ($hasDriverLocation && $isPickedUp && $dropoffRouteDistance > 0
                ? max(0, min(1, 1 - ($distanceToDropoff / $dropoffRouteDistance)))
                : 0);
        $etaMinutes = $rideStatus === 'completed' ? 0 : ($remainingDistance !== null ? max(1, (int) ceil(($remainingDistance / 25) * 60)) : 0);
        $speed = $this->resolveSpeedKph($latestPoint, $previousPoint);
        $heading = $this->resolveHeading($latestPoint, $previousPoint);
        $isMoving = $speed >= 3;
        $accuracy = $ride['latest_accuracy'] !== null && $ride['latest_accuracy'] !== '' ? (float) $ride['latest_accuracy'] : null;
        $locationAgeSeconds = $ride['latest_recorded_at'] ? max(0, time() - strtotime((string) $ride['latest_recorded_at'])) : null;
        $locationQuality = $this->locationQuality($hasDriverLocation, $accuracy, $locationAgeSeconds);
        $studentLat = $isPickedUp ? $driverLat : $pickupLat;
        $studentLng = $isPickedUp ? $driverLng : $pickupLng;
        $pickupStops = array_map(fn ($stop) => $stop['pickupLocation'], $carpoolStops);

        if (!$pickupStops) {
            $pickupStops[] = ['latitude' => $pickupLat, 'longitude' => $pickupLng];
        }

        $routePoints = array_values(array_filter([
            $hasDriverLocation ? ['latitude' => $driverLat, 'longitude' => $driverLng] : null,
            ...(!$isPickedUp ? $pickupStops : []),
            ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
        ]));

        Response::json([
            'success' => true,
            'data' => [
                'id' => (int) $ride['id'],
                'rideId' => (int) $ride['id'],
                'status' => ucwords(str_replace('_', ' ', $rideStatus)),
                'etaMinutes' => $etaMinutes,
                'distanceKm' => $remainingDistance !== null ? (float) number_format($remainingDistance, 1, '.', '') : null,
                'distanceToPickupKm' => $distanceToPickup !== null ? (float) number_format($distanceToPickup, 1, '.', '') : null,
                'distanceToDropoffKm' => $distanceToDropoff !== null ? (float) number_format($distanceToDropoff, 1, '.', '') : null,
                'progress' => $progress,
                'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
                'lastLocationAt' => $ride['latest_recorded_at'],
                'locationAgeSeconds' => $locationAgeSeconds,
                'locationAccuracyMeters' => $accuracy,
                'locationQuality' => $locationQuality,
                'isPickedUp' => $isPickedUp,
                'nextStopLabel' => $isPickedUp ? 'Drop-off' : 'Pickup',
                'dropoffPhotoUrl' => $this->fileUrl($ride['dropoff_photo_path'] ?? null),
                'routeDirection' => str_contains(strtolower((string) ($ride['notes'] ?? '')), 'return') ? 'return_home' : 'to_school',
                'targetLocation' => ['latitude' => $targetLat, 'longitude' => $targetLng],
                'hasDriverLocation' => $hasDriverLocation,
                'location' => $hasDriverLocation ? ['latitude' => $driverLat, 'longitude' => $driverLng, 'accuracy' => $accuracy, 'latitudeDelta' => 0.03, 'longitudeDelta' => 0.03] : null,
                'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
                'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
                'studentLocation' => $studentLat !== null && $studentLng !== null ? ['latitude' => $studentLat, 'longitude' => $studentLng] : null,
                'carpoolStops' => $carpoolStops,
                'orderedStops' => $orderedStops,
                'routePoints' => $routePoints,
                'driver' => [
                    'name' => trim($ride['first_name'] . ' ' . $ride['last_name']),
                    'latitude' => $driverLat,
                    'longitude' => $driverLng,
                    'accuracyMeters' => $accuracy,
                    'speedKph' => $speed,
                    'heading' => $heading,
                    'isMoving' => $isMoving,
                ],
                'movement' => [
                    'isMoving' => $isMoving,
                    'speedKph' => $speed,
                    'heading' => $heading,
                ],
            ],
        ]);
    }

    private function latestLocationHistory(int $rideId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT latitude, longitude, speed, heading, accuracy, recorded_at
             FROM ride_locations
             WHERE ride_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 2'
        );
        $stmt->execute([$rideId]);

        return $stmt->fetchAll();
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

    private function ensureBookingScheduleColumns(): void
    {
        $columns = [
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
            $dropoffLocation = $stop['dropoffLocation'] ?? ['latitude' => (float) ($ride['dropoff_latitude'] ?: 10.668364), 'longitude' => (float) ($ride['dropoff_longitude'] ?: 123.019768)];
            $stops[] = [
                'id' => 'dropoff-' . $stop['rideId'],
                'type' => 'dropoff',
                'rideId' => $stop['rideId'],
                'bookingId' => $stop['bookingId'],
                'studentName' => $stop['studentName'],
                'parentName' => $stop['parentName'],
                'address' => $stop['dropoffAddress'] ?? ($ride['dropoff_address'] ?? ''),
                'location' => $dropoffLocation,
                'expectedTime' => !empty($stop['plannedDropoffTime']) ? $stop['plannedDropoffTime'] : $this->timeWithOffset($scheduledTime, (count($pickupStops) * 4) + 12 + ($index * 2)),
                'etaMinutes' => $this->stopEtaMinutes($driverLat, $driverLng, $dropoffLocation, (count($pickupStops) * 4) + 12 + ($index * 2)),
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
        $timestamp = strtotime('1970-01-01 ' . $time) ?: strtotime('1970-01-01 07:00:00');

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

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function resolveSpeedKph(?array $latestPoint, ?array $previousPoint): float
    {
        if ($latestPoint && $latestPoint['speed'] !== null && $latestPoint['speed'] !== '') {
            return max(0, (float) $latestPoint['speed']);
        }

        if (!$latestPoint || !$previousPoint) {
            return 0;
        }

        $seconds = max(
            1,
            strtotime((string) $latestPoint['recorded_at']) - strtotime((string) $previousPoint['recorded_at'])
        );
        $distanceKm = $this->distanceKm(
            (float) $previousPoint['latitude'],
            (float) $previousPoint['longitude'],
            (float) $latestPoint['latitude'],
            (float) $latestPoint['longitude']
        );

        return round(($distanceKm / $seconds) * 3600, 1);
    }

    private function resolveHeading(?array $latestPoint, ?array $previousPoint): ?float
    {
        if ($latestPoint && $latestPoint['heading'] !== null && $latestPoint['heading'] !== '') {
            return round((float) $latestPoint['heading'], 1);
        }

        if (!$latestPoint || !$previousPoint) {
            return null;
        }

        $fromLat = deg2rad((float) $previousPoint['latitude']);
        $fromLng = deg2rad((float) $previousPoint['longitude']);
        $toLat = deg2rad((float) $latestPoint['latitude']);
        $toLng = deg2rad((float) $latestPoint['longitude']);
        $y = sin($toLng - $fromLng) * cos($toLat);
        $x = cos($fromLat) * sin($toLat) - sin($fromLat) * cos($toLat) * cos($toLng - $fromLng);
        $bearing = rad2deg(atan2($y, $x));

        return round(fmod(($bearing + 360), 360), 1);
    }

    private function locationQuality(bool $hasDriverLocation, ?float $accuracy, ?int $ageSeconds): string
    {
        if (!$hasDriverLocation) {
            return 'waiting';
        }

        if ($ageSeconds !== null && $ageSeconds > 90) {
            return 'stale';
        }

        if ($accuracy !== null && $accuracy > 50) {
            return 'weak';
        }

        return 'good';
    }
}

