<?php

declare(strict_types=1);

namespace EventIngestion\Event;

/**
 * Event entity representing a webhook event.
 */
final class Event
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $idempotencyKey,
        public readonly array $payload,
        public EventStatus $status,
        public int $attempts,
        public readonly int $maxAttempts,
        public ?string $lastError,
        public ?string $nextRetryAt,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * Create a new event with generated ID.
     */
    public static function create(
        string $source,
        string $idempotencyKey,
        array $payload,
        int $maxAttempts = 5
    ): self {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        return new self(
            id: self::generateId(),
            source: $source,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            status: EventStatus::QUEUED,
            attempts: 0,
            maxAttempts: $maxAttempts,
            lastError: null,
            nextRetryAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * Generate a UUID v4 event ID with prefix.
     */
    private static function generateId(): string
    {
        $data = random_bytes(16);
        
        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant to RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        
        return 'evt_' . $uuid;
    }

    /**
     * Create an Event from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: $row['id'],
            source: $row['source'],
            idempotencyKey: $row['idempotency_key'],
            payload: json_decode($row['payload'], true) ?? [],
            status: EventStatus::from($row['status']),
            attempts: (int) $row['attempts'],
            maxAttempts: (int) $row['max_attempts'],
            lastError: $row['last_error'],
            nextRetryAt: $row['next_retry_at'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    /**
     * Check if retry attempts remain.
     */
    public function hasAttemptsRemaining(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'last_error' => $this->lastError,
            'next_retry_at' => $this->nextRetryAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Convert to detailed array including payload.
     */
    public function toDetailedArray(): array
    {
        $data = $this->toArray();
        $data['payload'] = $this->payload;
        $data['status_description'] = $this->status->description();
        return $data;
    }
}

