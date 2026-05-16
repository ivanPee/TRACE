<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class RideController extends ApiController
{
    public function track(array $params = []): void
    {
        $user = $this->requireUser();
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                parents.user_id AS parent_user_id, students.user_id AS student_user_id, drivers.user_id AS driver_user_id,
                drivers.current_latitude, drivers.current_longitude, latest_location.latitude AS latest_latitude,
                latest_location.longitude AS latest_longitude, latest_location.recorded_at AS latest_recorded_at,
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

        $driverLat = (float) ($ride['latest_latitude'] ?: $ride['current_latitude'] ?: $ride['pickup_latitude'] ?: 10.6765);
        $driverLng = (float) ($ride['latest_longitude'] ?: $ride['current_longitude'] ?: $ride['pickup_longitude'] ?: 122.9509);
        $pickupLat = (float) ($ride['pickup_latitude'] ?: 10.676344);
        $pickupLng = (float) ($ride['pickup_longitude'] ?: 122.953221);
        $dropoffLat = (float) ($ride['dropoff_latitude'] ?: 10.668364);
        $dropoffLng = (float) ($ride['dropoff_longitude'] ?: 123.019768);
        $totalDistance = $this->distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $remainingDistance = $this->distanceKm($driverLat, $driverLng, $dropoffLat, $dropoffLng);
        $progress = $totalDistance > 0 ? max(0, min(1, 1 - ($remainingDistance / $totalDistance))) : 0;
        $etaMinutes = $ride['ride_status'] === 'completed' ? 0 : max(1, (int) ceil(($remainingDistance / 25) * 60));

        Response::json([
            'success' => true,
            'data' => [
                'id' => (int) $ride['id'],
                'rideId' => (int) $ride['id'],
                'status' => ucwords(str_replace('_', ' ', $ride['ride_status'])),
                'etaMinutes' => $etaMinutes,
                'distanceKm' => (float) number_format($remainingDistance, 1, '.', ''),
                'progress' => $ride['ride_status'] === 'completed' ? 1 : $progress,
                'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
                'lastLocationAt' => $ride['latest_recorded_at'],
                'location' => ['latitude' => $driverLat, 'longitude' => $driverLng, 'latitudeDelta' => 0.03, 'longitudeDelta' => 0.03],
                'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
                'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
                'routePoints' => [
                    ['latitude' => $pickupLat, 'longitude' => $pickupLng],
                    ['latitude' => $driverLat, 'longitude' => $driverLng],
                    ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
                ],
                'driver' => [
                    'name' => trim($ride['first_name'] . ' ' . $ride['last_name']),
                    'latitude' => $driverLat,
                    'longitude' => $driverLng,
                ],
            ],
        ]);
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

