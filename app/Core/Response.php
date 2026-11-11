<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }

    public static function error(string $message, int $status = 500): void
    {
        if (Request::wantsJson()) {
            self::json(['ok' => false, 'message' => $message], $status);
        }
        http_response_code($status);
        echo View::render('errors/error', ['title' => 'خطا', 'message' => $message, 'status' => $status], null);
        exit;
    }
}
