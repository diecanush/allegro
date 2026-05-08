<?php

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = [], string $message = 'OK', int $status = 200): void
    {
        self::json([
            'ok' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    public static function error(string $message = 'Error', int $status = 400, $errors = null): void
    {
        self::json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}