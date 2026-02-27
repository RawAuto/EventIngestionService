<?php

declare(strict_types=1);

/**
 * Worker entry point.
 * Runs the background event processing loop.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use EventIngestion\Logger;
use EventIngestion\Worker\Worker;

$logger = new Logger('worker-main');

$logger->info('Starting event processing worker');

try {
    $worker = new Worker();
    $worker->run();
} catch (Throwable $e) {
    $logger->error('Worker crashed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}

