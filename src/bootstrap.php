<?php

declare(strict_types=1);

/**
 * Application bootstrap file.
 * Configures autoloading and shared initialisation.
 */

// Simple PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'EventIngestion\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Ensure data directory exists
$dataDir = '/app/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

