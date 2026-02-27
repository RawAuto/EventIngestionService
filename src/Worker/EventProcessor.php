<?php

declare(strict_types=1);

namespace EventIngestion\Worker;

use EventIngestion\Event\Event;
use EventIngestion\Logger;
use Exception;

/**
 * Processes events by simulating delivery to a downstream system.
 * 
 * In a real system, this would:
 * - Make HTTP calls to downstream services
 * - Transform payloads as needed
 * - Handle various error conditions
 * 
 * For this demo, we simulate success/failure based on payload content.
 */
final class EventProcessor
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('processor');
    }

    /**
     * Process an event.
     * 
     * @throws Exception if processing fails
     */
    public function process(Event $event): void
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing event', [
            'event_id' => $event->id,
            'source' => $event->source,
            'attempt' => $event->attempts + 1,
        ]);

        try {
            // Simulate processing based on payload
            $this->simulateProcessing($event);
            
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            $this->logger->info('Event processed successfully', [
                'event_id' => $event->id,
                'source' => $event->source,
                'attempt' => $event->attempts + 1,
                'duration_ms' => $durationMs,
            ]);
        } catch (Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            $this->logger->warning('Event processing failed', [
                'event_id' => $event->id,
                'source' => $event->source,
                'attempt' => $event->attempts + 1,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Simulate processing with configurable behavior.
     * 
     * The payload can control behavior for testing:
     * - { "simulate_failure": true } - Always fails
     * - { "simulate_delay_ms": 500 } - Adds processing delay
     * - { "simulate_transient": true } - Fails first 2 attempts
     */
    private function simulateProcessing(Event $event): void
    {
        $payload = $event->payload;

        // Simulate processing delay
        if (isset($payload['simulate_delay_ms'])) {
            $delayMs = (int) $payload['simulate_delay_ms'];
            usleep($delayMs * 1000);
        }

        // Simulate permanent failure
        if (isset($payload['simulate_failure']) && $payload['simulate_failure'] === true) {
            throw new Exception('Simulated permanent failure');
        }

        // Simulate transient failure (fails first 2 attempts)
        if (isset($payload['simulate_transient']) && $payload['simulate_transient'] === true) {
            if ($event->attempts < 2) {
                throw new Exception('Simulated transient failure');
            }
        }

        // Default: small delay to simulate real work
        usleep(50000); // 50ms
    }
}

