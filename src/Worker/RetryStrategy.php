<?php

declare(strict_types=1);

namespace EventIngestion\Worker;

/**
 * Retry strategy with exponential backoff and jitter.
 * 
 * Formula: delay = base * (multiplier ^ attempt) * (0.8 + random * 0.4)
 * 
 * Default configuration:
 * - Attempt 1: ~10s (8-12s with jitter)
 * - Attempt 2: ~30s (24-36s with jitter)
 * - Attempt 3: ~90s (72-108s with jitter)
 * - Attempt 4: ~270s (216-324s with jitter)
 */
final class RetryStrategy
{
    private int $baseDelaySeconds;
    private float $multiplier;
    private float $jitterFactor;

    public function __construct(
        int $baseDelaySeconds = 10,
        float $multiplier = 3.0,
        float $jitterFactor = 0.2
    ) {
        $this->baseDelaySeconds = $baseDelaySeconds;
        $this->multiplier = $multiplier;
        $this->jitterFactor = $jitterFactor;
    }

    /**
     * Calculate the next retry timestamp for a given attempt number.
     * 
     * @param int $attempt Current attempt number (1-based, after failure)
     * @return string ISO 8601 timestamp for next retry
     */
    public function calculateNextRetryAt(int $attempt): string
    {
        $delaySeconds = $this->calculateDelaySeconds($attempt);
        $nextRetryTime = time() + $delaySeconds;
        
        return gmdate('Y-m-d\TH:i:s\Z', $nextRetryTime);
    }

    /**
     * Calculate delay in seconds with jitter.
     */
    public function calculateDelaySeconds(int $attempt): int
    {
        // Base exponential delay
        $baseDelay = $this->baseDelaySeconds * pow($this->multiplier, $attempt - 1);
        
        // Apply jitter: multiply by random factor between (1 - jitter) and (1 + jitter)
        $jitterMin = 1 - $this->jitterFactor;
        $jitterMax = 1 + $this->jitterFactor;
        $jitterMultiplier = $jitterMin + (mt_rand() / mt_getrandmax()) * ($jitterMax - $jitterMin);
        
        $delayWithJitter = $baseDelay * $jitterMultiplier;
        
        return (int) round($delayWithJitter);
    }

    /**
     * Get human-readable description of retry schedule.
     */
    public function describeSchedule(int $maxAttempts): array
    {
        $schedule = [];
        
        for ($attempt = 1; $attempt < $maxAttempts; $attempt++) {
            $baseDelay = $this->baseDelaySeconds * pow($this->multiplier, $attempt - 1);
            $minDelay = $baseDelay * (1 - $this->jitterFactor);
            $maxDelay = $baseDelay * (1 + $this->jitterFactor);
            
            $schedule[] = [
                'attempt' => $attempt,
                'base_delay_seconds' => (int) $baseDelay,
                'range_seconds' => [
                    'min' => (int) round($minDelay),
                    'max' => (int) round($maxDelay),
                ],
            ];
        }
        
        return $schedule;
    }
}

