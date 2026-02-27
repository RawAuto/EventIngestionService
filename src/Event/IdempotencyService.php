<?php

declare(strict_types=1);

namespace EventIngestion\Event;

use EventIngestion\Database;
use PDO;

/**
 * Service for handling idempotency key lookups and storage.
 * Uses SHA-256 hashing of source + key for consistent lookups.
 */
final class IdempotencyService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Generate hash for idempotency key lookup.
     */
    public function hashKey(string $source, string $key): string
    {
        return hash('sha256', $source . ':' . $key);
    }

    /**
     * Check if an idempotency key already exists.
     * Returns the existing event ID if found, null otherwise.
     */
    public function findExistingEventId(string $source, string $key): ?string
    {
        $hash = $this->hashKey($source, $key);

        $stmt = $this->pdo->prepare("
            SELECT event_id FROM idempotency_keys WHERE key_hash = :key_hash
        ");
        $stmt->execute(['key_hash' => $hash]);

        $row = $stmt->fetch();

        return $row !== false ? $row['event_id'] : null;
    }

    /**
     * Store an idempotency key mapping.
     */
    public function store(string $source, string $key, string $eventId): void
    {
        $hash = $this->hashKey($source, $key);
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $this->pdo->prepare("
            INSERT INTO idempotency_keys (key_hash, event_id, created_at)
            VALUES (:key_hash, :event_id, :created_at)
        ");

        $stmt->execute([
            'key_hash' => $hash,
            'event_id' => $eventId,
            'created_at' => $now,
        ]);
    }
}

