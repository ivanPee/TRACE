<?php

use Controllers\AuthController;
use Controllers\DriverController;
use Controllers\MessageController;
use Controllers\ParentController;
use Controllers\RideController;
use Core\Request;
use Core\Response;

$routes = [
    ['POST', '/api/register/parent', [AuthController::class, 'registerParent']],
    ['POST', '/api/register/driver', [AuthController::class, 'registerDriver']],
    ['POST', '/api/login', [AuthController::class, 'login']],
    ['GET', '/api/me', [AuthController::class, 'me']],
    ['POST', '/api/profile', [AuthController::class, 'updateProfile']],
    ['GET', '/api/parent/dashboard', [ParentController::class, 'dashboard']],
    ['GET', '/api/drivers', [ParentController::class, 'drivers']],
    ['POST', '/api/parents/students', [ParentController::class, 'createStudent']],
    ['POST', '/api/parents/students/{id}', [ParentController::class, 'updateStudent']],
    ['POST', '/api/bookings', [ParentController::class, 'createBooking']],
    ['GET', '/api/driver/dashboard', [DriverController::class, 'dashboard']],
    ['POST', '/api/driver/availability', [DriverController::class, 'availability']],
    ['POST', '/api/driver/bookings/{id}/approve', [DriverController::class, 'approveBooking']],
    ['POST', '/api/driver/bookings/{id}/reject', [DriverController::class, 'rejectBooking']],
    ['POST', '/api/driver/rides/{id}/status', [DriverController::class, 'updateRideStatus']],
    ['POST', '/api/driver/rides/{id}/location', [DriverController::class, 'pushLocation']],
    ['POST', '/api/driver/rides/{id}/transfer', [DriverController::class, 'transfer']],
    ['POST', '/api/messages', [MessageController::class, 'send']],
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

