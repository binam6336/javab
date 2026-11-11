<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable|array}> */
    private $routes = [];

    /** @param callable|array{0:class-string,1:string} $handler */
    public function get(string $pattern, $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    /** @param callable|array{0:class-string,1:string} $handler */
    public function post(string $pattern, $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$m, $pattern, $handler]) {
            $regex = '#^' . preg_replace(
                ['/\{id\}/', '/\{conv\}/'],
                ['(\d+)', '([a-f0-9]{32})'],
                $pattern
            ) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $allowed = true;
            if ($m !== $method) {
                continue;
            }
            array_shift($matches);
            if ($method === 'POST') {
                Csrf::verify();
            }
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...$matches);
            } else {
                $handler(...$matches);
            }
            return;
        }

        if ($allowed) {
            Response::error('این متد برای این مسیر مجاز نیست.', 405);
        }
        Response::error('صفحه‌ای که دنبالش هستید پیدا نشد.', 404);
    }
}
