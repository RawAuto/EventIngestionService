<?php

declare(strict_types=1);

namespace EventIngestion\Handler;

use EventIngestion\Database;
use EventIngestion\Http\Request;
use EventIngestion\Http\Response;

/**
 * Handler for health check endpoints.
 * - GET /health - Liveness check (process is running)
 * - GET /ready - Readiness check (database connectivity)
 */
final class HealthHandler
{
    /**
     * Liveness check - confirms the process is running.
     */
    public function health(Request $request): Response
    {
        return Response::ok([
            'status' => 'healthy',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * Readiness check - confirms database connectivity.
     */
    public function ready(Request $request): Response
    {
        $dbConnected = Database::isConnected();

        if (!$dbConnected) {
            return Response::serviceUnavailable('Database connection failed');
        }

        return Response::ok([
            'status' => 'ready',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'checks' => [
                'database' => 'connected',
            ],
        ]);
    }
}

