<?php

declare(strict_types=1);

namespace EventIngestion\Event;

/**
 * Event status enum representing the lifecycle states.
 */
enum EventStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case RETRYING = 'retrying';
    case DELIVERED = 'delivered';
    case DEAD_LETTERED = 'dead_lettered';

    /**
     * Check if the event can be claimed for processing.
     */
    public function isClaimable(): bool
    {
        return $this === self::QUEUED || $this === self::RETRYING;
    }

    /**
     * Check if the event is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::DELIVERED || $this === self::DEAD_LETTERED;
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::QUEUED => 'Awaiting first processing attempt',
            self::PROCESSING => 'Currently being processed',
            self::RETRYING => 'Failed, scheduled for retry',
            self::DELIVERED => 'Successfully processed',
            self::DEAD_LETTERED => 'Permanently failed after max attempts',
        };
    }
}

