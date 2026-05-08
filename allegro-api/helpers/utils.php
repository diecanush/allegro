<?php

function getRawInput(): string
{
    static $rawInput = null;

    if ($rawInput === null) {
        $rawInput = file_get_contents('php://input');
        if ($rawInput === false) {
            $rawInput = '';
        }
    }

    return $rawInput;
}

function getJsonInput(): array
{
    static $jsonData = null;

    if ($jsonData === null) {
        $input = getRawInput();

        if ($input === '') {
            $jsonData = [];
        } else {
            $data = json_decode($input, true);
            $jsonData = is_array($data) ? $data : [];
        }
    }

    return $jsonData;
}

function getBearerToken(): ?string
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

    if (!$authHeader) {
        return null;
    }

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function generateToken(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}