<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class GruposController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $sql = "
            SELECT
                g.id,
                g.nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,
                g.cupo_maximo,
                g.estado,
                g.actividad_id,
                a.nombre AS actividad_nombre,
                g.profesor_id,
                CONCAT(u.nombre, ' ', u.apellido) AS profesor_nombre,
                g.sede_id,
                s.nombre AS sede_nombre
            FROM grupos g
            INNER JOIN actividades a ON a.id = g.actividad_id
            INNER JOIN profesores p ON p.id = g.profesor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN sedes s ON s.id = g.sede_id
            ORDER BY FIELD(g.dia_semana,
                'lunes','martes','miercoles','jueves','viernes','sabado','domingo'
            ), g.hora_inicio, g.nombre
        ";

        $stmt = $db->query($sql);
        Response::success($stmt->fetchAll(), 'Listado de grupos');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                g.*,
                a.nombre AS actividad_nombre,
                CONCAT(u.nombre, ' ', u.apellido) AS profesor_nombre,
                s.nombre AS sede_nombre
            FROM grupos g
            INNER JOIN actividades a ON a.id = g.actividad_id
            INNER JOIN profesores p ON p.id = g.profesor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN sedes s ON s.id = g.sede_id
            WHERE g.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Grupo no encontrado', 404);
        }

        Response::success($row, 'Grupo encontrado');
    }

    public static function store(): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $actividad_id = (int)($data['actividad_id'] ?? 0);
        $profesor_id = (int)($data['profesor_id'] ?? 0);
        $sede_id = isset($data['sede_id']) && $data['sede_id'] !== '' ? (int)$data['sede_id'] : null;
        $nombre = trim($data['nombre'] ?? '');
        $dia_semana = trim($data['dia_semana'] ?? '');
        $hora_inicio = trim($data['hora_inicio'] ?? '');
        $hora_fin = trim($data['hora_fin'] ?? '');
        $cupo_maximo = isset($data['cupo_maximo']) && $data['cupo_maximo'] !== '' ? (int)$data['cupo_maximo'] : null;
        $estado = $data['estado'] ?? 'activo';

        if ($actividad_id <= 0 || $profesor_id <= 0 || $nombre === '' || $dia_semana === '' || $hora_inicio === '' || $hora_fin === '') {
            Response::error('Faltan campos obligatorios', 422);
        }

        $stmt = $db->prepare("
            INSERT INTO grupos (
                actividad_id, profesor_id, sede_id, nombre, dia_semana,
                hora_inicio, hora_fin, cupo_maximo, estado
            ) VALUES (
                :actividad_id, :profesor_id, :sede_id, :nombre, :dia_semana,
                :hora_inicio, :hora_fin, :cupo_maximo, :estado
            )
        ");

        try {
            $stmt->execute([
                'actividad_id' => $actividad_id,
                'profesor_id' => $profesor_id,
                'sede_id' => $sede_id,
                'nombre' => $nombre,
                'dia_semana' => $dia_semana,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'cupo_maximo' => $cupo_maximo,
                'estado' => $estado
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Grupo creado', 201);
        } catch (PDOException $e) {
            Response::error('Error al crear grupo', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $actividad_id = (int)($data['actividad_id'] ?? 0);
        $profesor_id = (int)($data['profesor_id'] ?? 0);
        $sede_id = isset($data['sede_id']) && $data['sede_id'] !== '' ? (int)$data['sede_id'] : null;
        $nombre = trim($data['nombre'] ?? '');
        $dia_semana = trim($data['dia_semana'] ?? '');
        $hora_inicio = trim($data['hora_inicio'] ?? '');
        $hora_fin = trim($data['hora_fin'] ?? '');
        $cupo_maximo = isset($data['cupo_maximo']) && $data['cupo_maximo'] !== '' ? (int)$data['cupo_maximo'] : null;
        $estado = $data['estado'] ?? 'activo';

        if ($actividad_id <= 0 || $profesor_id <= 0 || $nombre === '' || $dia_semana === '' || $hora_inicio === '' || $hora_fin === '') {
            Response::error('Faltan campos obligatorios', 422);
        }

        $stmt = $db->prepare("
            UPDATE grupos
            SET actividad_id = :actividad_id,
                profesor_id = :profesor_id,
                sede_id = :sede_id,
                nombre = :nombre,
                dia_semana = :dia_semana,
                hora_inicio = :hora_inicio,
                hora_fin = :hora_fin,
                cupo_maximo = :cupo_maximo,
                estado = :estado
            WHERE id = :id
        ");

        try {
            $stmt->execute([
                'actividad_id' => $actividad_id,
                'profesor_id' => $profesor_id,
                'sede_id' => $sede_id,
                'nombre' => $nombre,
                'dia_semana' => $dia_semana,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'cupo_maximo' => $cupo_maximo,
                'estado' => $estado,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Grupo no encontrado o sin cambios', 404);
            }

            Response::success([], 'Grupo actualizado');
        } catch (PDOException $e) {
            Response::error('Error al actualizar grupo', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        $stmt = $db->prepare("DELETE FROM grupos WHERE id = :id");

        try {
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Grupo no encontrado', 404);
            }

            Response::success([], 'Grupo eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar grupo', 500, $e->getMessage());
        }
    }
}