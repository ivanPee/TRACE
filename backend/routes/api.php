<?php

use Controllers\AuthController;
use Controllers\DriverController;
use Controllers\ParentController;
use Controllers\RideController;
use Core\Request;
use Core\Response;

$routes = [
    ['POST', '/api/register/parent', [AuthController::class, 'registerParent']],
    ['POST', '/api/register/driver', [AuthController::class, 'registerDriver']],
    ['POST', '/api/login', [AuthController::class, 'login']],
    ['POST', '/api/parents/students', [ParentController::class, 'createStudent']],
    ['POST', '/api/bookings', [ParentController::class, 'createBooking']],
    ['POST', '/api/driver/rides/{id}/status', [DriverController::class, 'updateRideStatus']],
    ['POST', '/api/driver/rides/{id}/location', [DriverController::class, 'pushLocation']],
    ['GET', '/api/rides/{id}/track', [RideController::class, 'track']],
];

$requestMethod = Request::method();
$requestPath = Request::path();

foreach ($routes as [$method, $pattern, $handler]) {
    $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

    if ($method !== $requestMethod) {
        continue;
    }

    if (!preg_match($regex, $requestPath, $matches)) {
        continue;
    }

    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    [$class, $action] = $handler;
    $controller = new $class();
    $controller->$action($params);
}

Response::json([
    'success' => false,
    'message' => 'Route not found.',
    'path' => $requestPath,
], 404);

