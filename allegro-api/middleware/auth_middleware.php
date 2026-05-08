<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../helpers/utils.php';

function requireAuth(array $roles = []): array
{
    $token = getBearerToken();
    $user = Auth::validateToken($token);

    if (!$user) {
        Response::error('No autorizado', 401);
    }

    if (!empty($roles) && !in_array($user['rol'], $roles, true)) {
        Response::error('No tenés permisos para acceder a este recurso', 403);
    }

    return $user;
}