<?php

declare(strict_types=1);

namespace EventIngestion;

/**
 * Structured JSON logger for consistent log output.
 * All logs include timestamp, level, message, and optional context.
 */
final class Logger
{
    private string $component;

    public function __construct(string $component = 'app')
    {
        $this->component = $component;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'component' => $this->component,
            'message' => $message,
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Write to stderr for error/warning, stdout otherwise
        if (in_array($level, ['error', 'warning'])) {
            file_put_contents('php://stderr', $json . PHP_EOL);
        } else {
            file_put_contents('php://stdout', $json . PHP_EOL);
        }
    }
}

