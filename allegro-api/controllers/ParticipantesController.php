<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ParticipantesController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesora']);

        $db = DB::get();

        $stmt = $db->query("
            SELECT 
                p.id,
                p.usuario_id,
                p.dni,
                p.fecha_nacimiento,
                p.direccion,
                p.contacto_emergencia_nombre,
                p.contacto_emergencia_telefono,
                p.apto_fisico_vencimiento,
                p.estado,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono
            FROM participantes p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            ORDER BY u.apellido, u.nombre
        ");

        Response::success($stmt->fetchAll(), 'Listado de participantes');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesora']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT 
                p.*,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                u.rol
            FROM participantes p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Participante no encontrado', 404);
        }

        Response::success($row, 'Participante encontrado');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesora']);

        $db = DB::get();
        $data = Request::body();

        $nombre = trim($data['nombre'] ?? '');
        $apellido = trim($data['apellido'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $password = $data['password'] ?? '';
        $dni = trim($data['dni'] ?? '');
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $direccion = trim($data['direccion'] ?? '');
        $contacto_emergencia_nombre = trim($data['contacto_emergencia_nombre'] ?? '');
        $contacto_emergencia_telefono = trim($data['contacto_emergencia_telefono'] ?? '');
        $apto_fisico_vencimiento = $data['apto_fisico_vencimiento'] ?? null;
        $observaciones = $data['observaciones'] ?? null;

        if ($nombre === '' || $apellido === '' || $email === '' || $password === '') {
            Response::error('Nombre, apellido, email y password son obligatorios', 422);
        }

        try {
            $db->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("
                INSERT INTO usuarios (nombre, apellido, email, telefono, password_hash, rol, estado)
                VALUES (:nombre, :apellido, :email, :telefono, :password_hash, 'participante', 'activo')
            ");
            $stmtUser->execute([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'telefono' => $telefono ?: null,
                'password_hash' => $hash
            ]);

            $usuarioId = $db->lastInsertId();

            $stmtPart = $db->prepare("
                INSERT INTO participantes (
                    usuario_id, dni, fecha_nacimiento, direccion,
                    contacto_emergencia_nombre, contacto_emergencia_telefono,
                    apto_fisico_vencimiento, observaciones, estado
                )
                VALUES (
                    :usuario_id, :dni, :fecha_nacimiento, :direccion,
                    :contacto_emergencia_nombre, :contacto_emergencia_telefono,
                    :apto_fisico_vencimiento, :observaciones, 'activo'
                )
            ");
            $stmtPart->execute([
                'usuario_id' => $usuarioId,
                'dni' => $dni ?: null,
                'fecha_nacimiento' => $fecha_nacimiento ?: null,
                'direccion' => $direccion ?: null,
                'contacto_emergencia_nombre' => $contacto_emergencia_nombre ?: null,
                'contacto_emergencia_telefono' => $contacto_emergencia_telefono ?: null,
                'apto_fisico_vencimiento' => $apto_fisico_vencimiento ?: null,
                'observaciones' => $observaciones
            ]);

            $participanteId = $db->lastInsertId();

            $db->commit();

            Response::success([
                'id' => (int)$participanteId,
                'usuario_id' => (int)$usuarioId
            ], 'Participante creado', 201);

        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al crear participante', 500, $e->getMessage());
        }
    }
    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);
    
        $db = DB::get();
        $data = Request::body();
    
        $stmt = $db->prepare("
            SELECT p.id, p.usuario_id
            FROM participantes p
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $participante = $stmt->fetch();
    
        if (!$participante) {
            Response::error('Participante no encontrado', 404);
        }
    
        $nombre = trim($data['nombre'] ?? '');
        $apellido = trim($data['apellido'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $dni = trim($data['dni'] ?? '');
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $direccion = trim($data['direccion'] ?? '');
        $contacto_emergencia_nombre = trim($data['contacto_emergencia_nombre'] ?? '');
        $contacto_emergencia_telefono = trim($data['contacto_emergencia_telefono'] ?? '');
        $apto_fisico_vencimiento = $data['apto_fisico_vencimiento'] ?? null;
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
                    'id' => $participante['usuario_id']
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
                    'id' => $participante['usuario_id']
                ]);
            }
    
            $stmtPart = $db->prepare("
                UPDATE participantes
                SET dni = :dni,
                    fecha_nacimiento = :fecha_nacimiento,
                    direccion = :direccion,
                    contacto_emergencia_nombre = :contacto_emergencia_nombre,
                    contacto_emergencia_telefono = :contacto_emergencia_telefono,
                    apto_fisico_vencimiento = :apto_fisico_vencimiento,
                    observaciones = :observaciones,
                    estado = :estado
                WHERE id = :id
            ");
            $stmtPart->execute([
                'dni' => $dni ?: null,
                'fecha_nacimiento' => $fecha_nacimiento ?: null,
                'direccion' => $direccion ?: null,
                'contacto_emergencia_nombre' => $contacto_emergencia_nombre ?: null,
                'contacto_emergencia_telefono' => $contacto_emergencia_telefono ?: null,
                'apto_fisico_vencimiento' => $apto_fisico_vencimiento ?: null,
                'observaciones' => $observaciones,
                'estado' => $estado,
                'id' => $id
            ]);
    
            $db->commit();
    
            Response::success([], 'Participante actualizado');
        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al actualizar participante', 500, $e->getMessage());
        }
    }
    
    public static function delete($id): void
    {
        requireAuth(['admin']);
    
        $db = DB::get();
    
        $stmt = $db->prepare("
            SELECT usuario_id
            FROM participantes
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $participante = $stmt->fetch();
    
        if (!$participante) {
            Response::error('Participante no encontrado', 404);
        }
    
        try {
            $db->beginTransaction();
    
            $del = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $del->execute(['id' => $participante['usuario_id']]);
    
            $db->commit();
    
            Response::success([], 'Participante eliminado');
        } catch (PDOException $e) {
            $db->rollBack();
            Response::error('Error al eliminar participante', 500, $e->getMessage());
        }
    }
}