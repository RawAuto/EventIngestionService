<?php

declare(strict_types=1);

/**
 * Database initialisation script.
 * Creates the required tables if they don't exist.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use EventIngestion\Database;
use EventIngestion\Logger;

$logger = new Logger('init-db');

$logger->info('Starting database initialization');

try {
    $pdo = Database::getConnection();

    // Create events table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id TEXT PRIMARY KEY,
            source TEXT NOT NULL,
            idempotency_key TEXT NOT NULL,
            payload TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'queued',
            attempts INTEGER NOT NULL DEFAULT 0,
            max_attempts INTEGER NOT NULL DEFAULT 5,
            last_error TEXT,
            next_retry_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            
            UNIQUE(source, idempotency_key)
        )
    ");

    $logger->info('Created events table');

    // Create index for efficient worker polling
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_events_status_retry 
        ON events(status, next_retry_at)
    ");

    $logger->info('Created events index');

    // Create idempotency keys table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS idempotency_keys (
            key_hash TEXT PRIMARY KEY,
            event_id TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
    ");

    $logger->info('Created idempotency_keys table');

    // Set permissions on database file so PHP-FPM (www-data) can write
    $dbPath = '/app/data/events.db';
    if (file_exists($dbPath)) {
        chmod($dbPath, 0666);
        $logger->info('Set database file permissions');
    }

    // Also set permissions on WAL files if they exist
    $walPath = $dbPath . '-wal';
    $shmPath = $dbPath . '-shm';
    if (file_exists($walPath)) {
        chmod($walPath, 0666);
    }
    if (file_exists($shmPath)) {
        chmod($shmPath, 0666);
    }

    $logger->info('Database initialization completed successfully');
    exit(0);
} catch (Exception $e) {
    $logger->error('Database initialization failed', [
        'error' => $e->getMessage(),
    ]);
    exit(1);
}

