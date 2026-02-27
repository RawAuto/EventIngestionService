<?php

declare(strict_types=1);

/**
 * API entry point.
 * Routes incoming HTTP requests to appropriate handlers.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use EventIngestion\Handler\EventHandler;
use EventIngestion\Handler\HealthHandler;
use EventIngestion\Handler\WebhookHandler;
use EventIngestion\Http\Request;
use EventIngestion\Http\Response;
use EventIngestion\Http\Router;
use EventIngestion\Logger;

$logger = new Logger('api');

try {
    $request = new Request();
    
    $logger->debug('Incoming request', [
        'method' => $request->getMethod(),
        'path' => $request->getPath(),
    ]);

    // Set up routes
    $router = new Router();

    // Health endpoints
    $healthHandler = new HealthHandler();
    $router->get('/health', fn(Request $r) => $healthHandler->health($r));
    $router->get('/ready', fn(Request $r) => $healthHandler->ready($r));

    // Webhook endpoint
    $webhookHandler = new WebhookHandler();
    $router->post('/webhooks/{source}', fn(Request $r) => $webhookHandler->handle($r));

    // Event query endpoint
    $eventHandler = new EventHandler();
    $router->get('/events/{id}', fn(Request $r) => $eventHandler->handle($r));

    // Dispatch request
    $response = $router->dispatch($request);
    $response->send();

} catch (Throwable $e) {
    $logger->error('Unhandled exception', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    Response::serverError('Internal server error')->send();
}

