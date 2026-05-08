<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../helpers/utils.php';

class AuthController
{
    public static function login(): void
    {
        $data = Request::body();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            Response::error('Email y password son obligatorios', 422);
        }

        try {
            $result = Auth::login($email, $password);
            Response::success($result, 'Login correcto');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    public static function me(): void
    {
        $user = requireAuth();
        Response::success($user, 'Usuario autenticado');
    }

    public static function logout(): void
    {
        $token = getBearerToken();

        if (!$token) {
            Response::error('Token no enviado', 400);
        }

        $ok = Auth::logout($token);

        if (!$ok) {
            Response::error('No se pudo cerrar sesión o el token ya no estaba activo', 400);
        }

        Response::success([], 'Sesión cerrada');
    }
}