<?php

declare(strict_types=1);

namespace EventIngestion\Event;

use EventIngestion\Database;
use PDO;
use PDOException;

/**
 * Repository for Event persistence operations.
 */
final class EventRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Save a new event to the database.
     */
    public function save(Event $event): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO events (
                id, source, idempotency_key, payload, status,
                attempts, max_attempts, last_error, next_retry_at,
                created_at, updated_at
            ) VALUES (
                :id, :source, :idempotency_key, :payload, :status,
                :attempts, :max_attempts, :last_error, :next_retry_at,
                :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'id' => $event->id,
            'source' => $event->source,
            'idempotency_key' => $event->idempotencyKey,
            'payload' => json_encode($event->payload),
            'status' => $event->status->value,
            'attempts' => $event->attempts,
            'max_attempts' => $event->maxAttempts,
            'last_error' => $event->lastError,
            'next_retry_at' => $event->nextRetryAt,
            'created_at' => $event->createdAt,
            'updated_at' => $event->updatedAt,
        ]);
    }

    /**
     * Find an event by ID.
     */
    public function findById(string $id): ?Event
    {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch();
        
        if ($row === false) {
            return null;
        }

        return Event::fromRow($row);
    }

    /**
     * Claim the next available event for processing.
     * Uses atomic update to prevent double-claiming.
     * 
     * @return Event|null The claimed event, or null if none available
     */
    public function claimNextEvent(): ?Event
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        // Find a claimable event
        $stmt = $this->pdo->prepare("
            SELECT id FROM events
            WHERE (status = 'queued' OR (status = 'retrying' AND next_retry_at <= :now))
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['now' => $now]);
        
        $row = $stmt->fetch();
        
        if ($row === false) {
            return null;
        }

        $eventId = $row['id'];

        // Atomically claim the event
        $updateStmt = $this->pdo->prepare("
            UPDATE events
            SET status = 'processing', updated_at = :updated_at
            WHERE id = :id
            AND (status = 'queued' OR (status = 'retrying' AND next_retry_at <= :now))
        ");
        
        $updateStmt->execute([
            'id' => $eventId,
            'updated_at' => $now,
            'now' => $now,
        ]);

        // Check if we actually claimed it
        if ($updateStmt->rowCount() === 0) {
            return null;
        }

        return $this->findById($eventId);
    }

    /**
     * Mark event as delivered (success).
     */
    public function markDelivered(Event $event): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET status = 'delivered', 
                attempts = attempts + 1,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $event->id,
            'updated_at' => $now,
        ]);

        $event->status = EventStatus::DELIVERED;
        $event->attempts++;
        $event->updatedAt = $now;
    }

    /**
     * Mark event for retry with scheduled time.
     */
    public function markForRetry(Event $event, string $nextRetryAt, string $error): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET status = 'retrying',
                attempts = attempts + 1,
                last_error = :last_error,
                next_retry_at = :next_retry_at,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $event->id,
            'last_error' => $error,
            'next_retry_at' => $nextRetryAt,
            'updated_at' => $now,
        ]);

        $event->status = EventStatus::RETRYING;
        $event->attempts++;
        $event->lastError = $error;
        $event->nextRetryAt = $nextRetryAt;
        $event->updatedAt = $now;
    }

    /**
     * Mark event as dead-lettered (permanent failure).
     */
    public function markDeadLettered(Event $event, string $error): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET status = 'dead_lettered',
                attempts = attempts + 1,
                last_error = :last_error,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $event->id,
            'last_error' => $error,
            'updated_at' => $now,
        ]);

        $event->status = EventStatus::DEAD_LETTERED;
        $event->attempts++;
        $event->lastError = $error;
        $event->updatedAt = $now;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}

