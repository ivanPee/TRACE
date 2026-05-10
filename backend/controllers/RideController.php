<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class RideController extends ApiController
{
    public function track(array $params = []): void
    {
        $this->requireUser();
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, drivers.current_latitude, drivers.current_longitude, users.first_name, users.last_name
             FROM rides
             JOIN drivers ON drivers.id = rides.driver_id
             JOIN users ON users.id = drivers.user_id
             WHERE rides.id = ?
             LIMIT 1'
        );
        $stmt->execute([(int) ($params['id'] ?? 0)]);
        $ride = $stmt->fetch();

        if (!$ride) {
            Response::json(['success' => false, 'message' => 'Ride not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data' => [
                'ride_id' => (int) $ride['id'],
                'status' => $ride['ride_status'],
                'driver' => [
                    'name' => trim($ride['first_name'] . ' ' . $ride['last_name']),
                    'latitude' => (float) ($ride['current_latitude'] ?: 0),
                    'longitude' => (float) ($ride['current_longitude'] ?: 0),
                ],
            ],
        ]);
    }
}

