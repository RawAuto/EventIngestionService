<?php

declare(strict_types=1);

namespace EventIngestion\Http;

/**
 * Simple path-based router.
 * Supports route parameters like {id} and {source}.
 */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        $this->addRoute('GET', $pattern, $handler);
        return $this;
    }

    public function post(string $pattern, callable $handler): self
    {
        $this->addRoute('POST', $pattern, $handler);
        return $this;
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[$method][$pattern] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if (!isset($this->routes[$method])) {
            return Response::notFound('Method not allowed');
        }

        foreach ($this->routes[$method] as $pattern => $handler) {
            $params = $this->matchRoute($pattern, $path);
            
            if ($params !== null) {
                $request->setPathParams($params);
                return $handler($request);
            }
        }

        return Response::notFound('Route not found');
    }

    /**
     * Match a route pattern against a path.
     * Returns path parameters if matched, null otherwise.
     */
    private function matchRoute(string $pattern, string $path): ?array
    {
        // Convert route pattern to regex
        // {param} becomes a named capture group
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Filter out numeric keys, keep only named captures
            return array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }
}

