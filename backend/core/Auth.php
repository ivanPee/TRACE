<?php

namespace Core;

class Auth
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function makeToken(array $user): string
    {
        $payload = [
            'user_id' => (int) $user['id'],
            'role' => $user['role_code'],
            'issued_at' => time(),
        ];

        return base64_encode(json_encode($payload));
    }

    public static function tokenPayload(): ?array
    {
        $header = self::authorizationHeader();

        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $json = base64_decode(trim(substr($header, 7)), true);
        $payload = json_decode((string) $json, true);

        return is_array($payload) && isset($payload['user_id']) ? $payload : null;
    }

    private static function authorizationHeader(): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
            if (!empty($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    return (string) $value;
                }
            }
        }

        return '';
    }
}

