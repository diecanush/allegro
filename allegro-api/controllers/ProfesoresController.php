<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ProfesoresController
{
    public static function index(): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        $stmt = $db->query("
            SELECT
                p.id,
                p.usuario_id,
                p.especialidad,
                p.observaciones,
                p.estado,
                p.fecha_alta,
                p.fecha_actualizacion,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                u.rol
            FROM profesores p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            ORDER BY u.apellido, u.nombre
        ");

        Response::success($stmt->fetchAll(), 'Listado de profesores');
    }

    public static function show($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                p.*,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                u.rol,
                u.estado AS usuario_estado
            FROM profesores p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Profesor no encontrado', 404);
        }

        Response::success($row, 'Profesor encontrado');
    }

    public static function store(): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $nombre = trim($data['nombre'] ?? '');
        $apellido = trim($data['apellido'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $password = $data['password'] ?? '';
        $especialidad = trim($data['especialidad'] ?? '');
        $observaciones = $data['observaciones'] ?? null;
        $estado = $data['estado'] ?? 'activo';

        if ($nombre === '' || $apellido === '' || $email === '' || $password === '') {
            Response::error('Nombre, apellido, email y password son obligatorios', 422);
        }

        try {
            $db->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("
                INSERT INTO usuarios (nombre, apellido, email, telefono, password_hash, rol, estado)
                VALUES (:nombre, :apellido, :email, :telefono, :password_hash, 'profesor', :estado)
            ");
            $stmtUser->execute([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'telefono' => $telefono ?: null,
                'password_hash' => $hash,
                'estado' => $estado
            ]);

            $usuarioId = $db->lastInsertId();

            $stmtProf = $db->prepare("
                INSERT INTO profesores (usuario_id, especialidad, observaciones, estado)
                VALUES (:usuario_id, :especialidad, :observaciones, :estado)
            ");
            $stmtProf->execute([
                'usuario_id' => $usuarioId,
                'especialidad' => $especialidad ?: null,
                'observaciones' => $observaciones,
                'estado' => $estado
            ]);

            $profesorId = $db->lastInsertId();

            $db->commit();

            Response::success([
                'id' => (int)$profesorId,
                'usuario_id' => (int)$usuarioId
            ], 'Profesor creado', 201);

        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al crear profesor', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $stmt = $db->prepare("
            SELECT p.id, p.usuario_id
            FROM profesores p
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $profesor = $stmt->fetch();

        if (!$profesor) {
            Response::error('Profesor no encontrado', 404);
        }

        $nombre = trim($data['nombre'] ?? '');
        $apellido = trim($data['apellido'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $especialidad = trim($data['especialidad'] ?? '');
        $observaciones = $data['observaciones'] ?? null;
        $estado = $data['estado'] ?? 'activo';
        $password = $data['password'] ?? '';

        if ($nombre === '' || $apellido === '' || $email === '') {
            Response::error('Nombre, apellido y email son obligatorios', 422);
        }

        try {
            $db->beginTransaction();

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmtUser = $db->prepare("
                    UPDATE usuarios
                    SET nombre = :nombre,
                        apellido = :apellido,
                        email = :email,
                        telefono = :telefono,
                        password_hash = :password_hash,
                        estado = :estado
                    WHERE id = :id
                ");
                $stmtUser->execute([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'email' => $email,
                    'telefono' => $telefono ?: null,
                    'password_hash' => $hash,
                    'estado' => $estado,
                    'id' => $profesor['usuario_id']
                ]);
            } else {
                $stmtUser = $db->prepare("
                    UPDATE usuarios
                    SET nombre = :nombre,
                        apellido = :apellido,
                        email = :email,
                        telefono = :telefono,
                        estado = :estado
                    WHERE id = :id
                ");
                $stmtUser->execute([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'email' => $email,
                    'telefono' => $telefono ?: null,
                    'estado' => $estado,
                    'id' => $profesor['usuario_id']
                ]);
            }

            $stmtProf = $db->prepare("
                UPDATE profesores
                SET especialidad = :especialidad,
                    observaciones = :observaciones,
                    estado = :estado
                WHERE id = :id
            ");
            $stmtProf->execute([
                'especialidad' => $especialidad ?: null,
                'observaciones' => $observaciones,
                'estado' => $estado,
                'id' => $id
            ]);

            $db->commit();

            Response::success([], 'Profesor actualizado');
        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al actualizar profesor', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT usuario_id
            FROM profesores
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $profesor = $stmt->fetch();

        if (!$profesor) {
            Response::error('Profesor no encontrado', 404);
        }

        try {
            $db->beginTransaction();

            $del = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $del->execute(['id' => $profesor['usuario_id']]);

            $db->commit();

            Response::success([], 'Profesor eliminado');
        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al eliminar profesor', 500, $e->getMessage());
        }
    }
}