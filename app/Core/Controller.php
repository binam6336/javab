<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo View::render($view, $data);
    }
}
