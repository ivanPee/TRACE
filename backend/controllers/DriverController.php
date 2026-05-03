<?php

namespace Controllers;

use Core\Request;
use Core\Response;

class DriverController
{
    public function updateRideStatus(array $params = []): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Ride status endpoint scaffolded.',
            'data' => [
                'ride_id' => $params['id'] ?? null,
                'status' => $input['status'] ?? null,
            ],
        ]);
    }

    public function pushLocation(array $params = []): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Location update endpoint scaffolded.',
            'data' => [
                'ride_id' => $params['id'] ?? null,
                'latitude' => $input['latitude'] ?? null,
                'longitude' => $input['longitude'] ?? null,
                'recorded_at' => $input['recorded_at'] ?? null,
            ],
        ]);
    }
}

