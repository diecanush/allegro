<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ClasesController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $grupo_id = Request::query('grupo_id');
        $fecha = Request::query('fecha');

        $sql = "
            SELECT
                c.id,
                c.grupo_id,
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.observaciones,
                c.estado,
                g.nombre AS grupo_nombre,
                a.nombre AS actividad_nombre
            FROM clases c
            INNER JOIN grupos g ON g.id = c.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            WHERE 1=1
        ";

        $params = [];

        if ($grupo_id) {
            $sql .= " AND c.grupo_id = :grupo_id";
            $params['grupo_id'] = $grupo_id;
        }

        if ($fecha) {
            $sql .= " AND c.fecha = :fecha";
            $params['fecha'] = $fecha;
        }

        $sql .= " ORDER BY c.fecha DESC, c.hora_inicio ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de clases');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                c.*,
                g.nombre AS grupo_nombre,
                a.nombre AS actividad_nombre
            FROM clases c
            INNER JOIN grupos g ON g.id = c.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Clase no encontrada', 404);
        }

        Response::success($row, 'Clase encontrada');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $grupo_id = (int)($data['grupo_id'] ?? 0);
        $fecha = trim($data['fecha'] ?? '');
        $hora_inicio = trim($data['hora_inicio'] ?? '');
        $hora_fin = trim($data['hora_fin'] ?? '');
        $observaciones = $data['observaciones'] ?? null;
        $estado = $data['estado'] ?? 'programada';

        if ($grupo_id <= 0 || $fecha === '') {
            Response::error('grupo_id y fecha son obligatorios', 422);
        }

        $stmt = $db->prepare("
            INSERT INTO clases (grupo_id, fecha, hora_inicio, hora_fin, observaciones, estado)
            VALUES (:grupo_id, :fecha, :hora_inicio, :hora_fin, :observaciones, :estado)
        ");

        try {
            $stmt->execute([
                'grupo_id' => $grupo_id,
                'fecha' => $fecha,
                'hora_inicio' => $hora_inicio ?: null,
                'hora_fin' => $hora_fin ?: null,
                'observaciones' => $observaciones,
                'estado' => $estado
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Clase creada', 201);
        } catch (PDOException $e) {
            Response::error('Error al crear clase', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $grupo_id = (int)($data['grupo_id'] ?? 0);
        $fecha = trim($data['fecha'] ?? '');
        $hora_inicio = trim($data['hora_inicio'] ?? '');
        $hora_fin = trim($data['hora_fin'] ?? '');
        $observaciones = $data['observaciones'] ?? null;
        $estado = $data['estado'] ?? 'programada';

        if ($grupo_id <= 0 || $fecha === '') {
            Response::error('grupo_id y fecha son obligatorios', 422);
        }

        $stmt = $db->prepare("
            UPDATE clases
            SET grupo_id = :grupo_id,
                fecha = :fecha,
                hora_inicio = :hora_inicio,
                hora_fin = :hora_fin,
                observaciones = :observaciones,
                estado = :estado
            WHERE id = :id
        ");

        try {
            $stmt->execute([
                'grupo_id' => $grupo_id,
                'fecha' => $fecha,
                'hora_inicio' => $hora_inicio ?: null,
                'hora_fin' => $hora_fin ?: null,
                'observaciones' => $observaciones,
                'estado' => $estado,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Clase no encontrada o sin cambios', 404);
            }

            Response::success([], 'Clase actualizada');
        } catch (PDOException $e) {
            Response::error('Error al actualizar clase', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("DELETE FROM clases WHERE id = :id");

        try {
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Clase no encontrada', 404);
            }

            Response::success([], 'Clase eliminada');
        } catch (PDOException $e) {
            Response::error('Error al eliminar clase', 500, $e->getMessage());
        }
    }
}