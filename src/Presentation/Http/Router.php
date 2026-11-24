<?php

declare(strict_types=1);

namespace QETG\Presentation\Http;

final class Router
{
    /** @var array<string, array<string, callable(): void>> */
    private array $routes = array();

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function dispatch(string $method, string $requestUri, string $basePath = '', ?callable $onMatchedGet = null): void
    {
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = \is_string($requestPath) ? $requestPath : '/';
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && ($requestPath === $basePath || \str_starts_with($requestPath, $basePath . '/'))) {
            $requestPath = \substr($requestPath, \strlen($basePath));
        }

        $path = $this->normalizePath($requestPath);
        $normalizedMethod = strtoupper($method);
        $handler = $this->routes[$normalizedMethod][$path] ?? null;

        if ($handler === null) {
            http_response_code(isset($this->routes['GET'][$path]) || isset($this->routes['POST'][$path]) ? 405 : 404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo isset($this->routes['GET'][$path]) || isset($this->routes['POST'][$path]) ? 'Méthode non autorisée' : 'Page introuvable';
            return;
        }

        if ($normalizedMethod === 'GET' && $onMatchedGet !== null) {
            $onMatchedGet($path);
        }
        $handler();
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$this->normalizePath($path)] = $handler;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}