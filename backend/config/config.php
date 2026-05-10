<?php

return [
    'app_name' => 'TRACE API',
    'base_url' => 'http://localhost/trace/TRACE/backend',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'trace_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'jwt_secret' => 'change-this-secret-key',
    'upload_path' => dirname(__DIR__) . '/storage/uploads',
];

