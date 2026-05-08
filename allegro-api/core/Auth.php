<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';

class Auth
{
    public static function login(string $email, string $password): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, nombre, apellido, email, password_hash, rol, estado
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Credenciales inválidas');
        }

        if ($user['estado'] !== 'activo') {
            throw new Exception('Usuario inactivo o bloqueado');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Credenciales inválidas');
        }

        $token = generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $insert = $db->prepare("
            INSERT INTO usuarios_tokens (usuario_id, token, fecha_expiracion, ip, user_agent, estado)
            VALUES (:usuario_id, :token, :fecha_expiracion, :ip, :user_agent, 'activo')
        ");
        $insert->execute([
            'usuario_id' => $user['id'],
            'token' => $token,
            'fecha_expiracion' => $expiresAt,
            'ip' => $ip,
            'user_agent' => $userAgent
        ]);

        $upd = $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
        $upd->execute(['id' => $user['id']]);

        unset($user['password_hash']);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => $user
        ];
    }

    public static function validateToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT 
                ut.id AS token_id,
                ut.usuario_id,
                ut.token,
                ut.fecha_expiracion,
                ut.estado AS token_estado,
                u.id,
                u.nombre,
                u.apellido,
                u.email,
                u.rol,
                u.estado
            FROM usuarios_tokens ut
            INNER JOIN usuarios u ON u.id = ut.usuario_id
            WHERE ut.token = :token
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ($row['token_estado'] !== 'activo') {
            return null;
        }

        if ($row['estado'] !== 'activo') {
            return null;
        }

        if (strtotime($row['fecha_expiracion']) < time()) {
            $upd = $db->prepare("UPDATE usuarios_tokens SET estado = 'expirado' WHERE id = :id");
            $upd->execute(['id' => $row['token_id']]);
            return null;
        }

        return [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'email' => $row['email'],
            'rol' => $row['rol'],
            'token_id' => $row['token_id'],
        ];
    }

    public static function logout(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $db = DB::get();
        $stmt = $db->prepare("
            UPDATE usuarios_tokens
            SET estado = 'revocado'
            WHERE token = :token AND estado = 'activo'
        ");
        $stmt->execute(['token' => $token]);

        return $stmt->rowCount() > 0;
    }
}