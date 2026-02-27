<?php

declare(strict_types=1);

namespace EventIngestion\Worker;

use EventIngestion\Event\EventRepository;
use EventIngestion\Logger;
use Exception;

/**
 * Background worker that polls for and processes events.
 * 
 * The worker runs in a continuous loop:
 * 1. Poll for claimable events (queued or ready for retry)
 * 2. Claim an event atomically
 * 3. Process the event
 * 4. Update status based on result (delivered, retrying, dead_lettered)
 * 5. Sleep briefly if no work available
 */
final class Worker
{
    private EventRepository $repository;
    private EventProcessor $processor;
    private RetryStrategy $retryStrategy;
    private Logger $logger;
    private bool $running = true;
    private int $pollIntervalMs;
    private int $idleIntervalMs;

    public function __construct(
        int $pollIntervalMs = 100,
        int $idleIntervalMs = 1000
    ) {
        $this->repository = new EventRepository();
        $this->processor = new EventProcessor();
        $this->retryStrategy = new RetryStrategy();
        $this->logger = new Logger('worker');
        $this->pollIntervalMs = $pollIntervalMs;
        $this->idleIntervalMs = $idleIntervalMs;
    }

    /**
     * Start the worker loop.
     */
    public function run(): void
    {
        $this->logger->info('Worker started', [
            'poll_interval_ms' => $this->pollIntervalMs,
            'idle_interval_ms' => $this->idleIntervalMs,
        ]);

        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_signal(SIGINT, fn() => $this->stop());
        }

        while ($this->running) {
            // Check for signals if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                $processed = $this->processNextEvent();

                if ($processed) {
                    // Brief pause between processing events
                    usleep($this->pollIntervalMs * 1000);
                } else {
                    // Longer pause when idle
                    usleep($this->idleIntervalMs * 1000);
                }
            } catch (Exception $e) {
                $this->logger->error('Worker loop error', [
                    'error' => $e->getMessage(),
                ]);
                
                // Back off on errors
                sleep(5);
            }
        }

        $this->logger->info('Worker stopped');
    }

    /**
     * Stop the worker gracefully.
     */
    public function stop(): void
    {
        $this->logger->info('Worker shutdown requested');
        $this->running = false;
    }

    /**
     * Process the next available event.
     * 
     * @return bool True if an event was processed, false if queue was empty
     */
    private function processNextEvent(): bool
    {
        $event = $this->repository->claimNextEvent();

        if ($event === null) {
            return false;
        }

        $this->logger->debug('Event claimed', [
            'event_id' => $event->id,
            'source' => $event->source,
            'attempts' => $event->attempts,
        ]);

        try {
            $this->processor->process($event);
            $this->repository->markDelivered($event);

            $this->logger->info('Event delivered', [
                'event_id' => $event->id,
                'source' => $event->source,
                'total_attempts' => $event->attempts,
            ]);
        } catch (Exception $e) {
            $this->handleProcessingFailure($event, $e);
        }

        return true;
    }

    /**
     * Handle a processing failure by scheduling retry or dead-lettering.
     */
    private function handleProcessingFailure(\EventIngestion\Event\Event $event, Exception $error): void
    {
        $errorMessage = $error->getMessage();
        $nextAttempt = $event->attempts + 1;

        if ($nextAttempt < $event->maxAttempts) {
            // Schedule for retry
            $nextRetryAt = $this->retryStrategy->calculateNextRetryAt($nextAttempt);
            $this->repository->markForRetry($event, $nextRetryAt, $errorMessage);

            $this->logger->info('Event scheduled for retry', [
                'event_id' => $event->id,
                'source' => $event->source,
                'attempt' => $nextAttempt,
                'next_retry_at' => $nextRetryAt,
                'error' => $errorMessage,
            ]);
        } else {
            // Max attempts exceeded, dead-letter the event
            $this->repository->markDeadLettered($event, $errorMessage);

            $this->logger->warning('Event dead-lettered', [
                'event_id' => $event->id,
                'source' => $event->source,
                'total_attempts' => $nextAttempt,
                'error' => $errorMessage,
            ]);
        }
    }
}

