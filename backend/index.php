<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefixMap = [
        'Core\\' => __DIR__ . '/core/',
        'Controllers\\' => __DIR__ . '/controllers/',
    ];

    foreach ($prefixMap as $prefix => $directory) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});

$config = require __DIR__ . '/config/config.php';

if (!is_dir($config['upload_path'])) {
    mkdir($config['upload_path'], 0777, true);
}

require __DIR__ . '/routes/api.php';

