<?php

declare(strict_types=1);

namespace EventIngestion\Http;

/**
 * HTTP response builder.
 * Provides fluent interface for building JSON responses.
 */
final class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $body = null;

    public function __construct()
    {
        $this->headers['Content-Type'] = 'application/json';
    }

    public static function json(mixed $data, int $statusCode = 200): self
    {
        $response = new self();
        $response->statusCode = $statusCode;
        $response->body = $data;
        return $response;
    }

    public static function accepted(array $data): self
    {
        return self::json($data, 202);
    }

    public static function ok(array $data): self
    {
        return self::json($data, 200);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return self::json(['error' => $message], 404);
    }

    public static function badRequest(string $message): self
    {
        return self::json(['error' => $message], 400);
    }

    public static function conflict(string $message): self
    {
        return self::json(['error' => $message], 409);
    }

    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::json(['error' => $message], 500);
    }

    public static function serviceUnavailable(string $message = 'Service unavailable'): self
    {
        return self::json(['error' => $message], 503);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->body !== null) {
            echo json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
}

