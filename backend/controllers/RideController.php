<?php

namespace Controllers;

use Core\Response;

class RideController
{
    public function track(array $params = []): void
    {
        Response::json([
            'success' => true,
            'message' => 'Ride tracking endpoint scaffolded.',
            'data' => [
                'ride_id' => $params['id'] ?? null,
                'driver' => [
                    'name' => 'Sample Driver',
                    'latitude' => 10.6765,
                    'longitude' => 122.9509,
                ],
                'student_status' => 'picked_up',
                'eta_minutes' => 12,
            ],
        ]);
    }
}

