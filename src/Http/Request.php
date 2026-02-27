<?php

declare(strict_types=1);

namespace EventIngestion\Http;

/**
 * Simple HTTP request wrapper.
 * Provides access to method, path, headers, and body.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $headers;
    private string $body;
    private array $pathParams;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->parsePath();
        $this->headers = $this->parseHeaders();
        $this->body = file_get_contents('php://input') ?: '';
        $this->pathParams = [];
    }

    private function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?: '/';
    }

    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }

        // Include content-type and content-length if present
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getJsonBody(): ?array
    {
        if (empty($this->body)) {
            return null;
        }

        $decoded = json_decode($this->body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    public function setPathParams(array $params): void
    {
        $this->pathParams = $params;
    }

    public function getPathParam(string $name): ?string
    {
        return $this->pathParams[$name] ?? null;
    }
}

