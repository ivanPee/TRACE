<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;

class AuthController
{
    public function registerParent(): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Parent registration endpoint scaffolded.',
            'data' => [
                'full_name' => trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? '')),
                'password_hash_sample' => isset($input['password']) ? Auth::hashPassword($input['password']) : null,
            ],
        ], 201);
    }

    public function registerDriver(): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Driver registration endpoint scaffolded.',
            'data' => [
                'license_number' => $input['license_number'] ?? null,
                'vehicle_plate_number' => $input['vehicle_plate_number'] ?? null,
            ],
        ], 201);
    }

    public function login(): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Login endpoint scaffolded.',
            'data' => [
                'email' => $input['email'] ?? null,
                'token' => 'replace-with-jwt-token',
            ],
        ]);
    }
}

