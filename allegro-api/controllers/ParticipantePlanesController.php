<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ParticipantePlanesController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $participante_id = Request::query('participante_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                pp.id,
                pp.participante_id,
                pp.plan_id,
                pp.fecha_inicio,
                pp.fecha_fin,
                pp.precio_acordado,
                pp.estado,
                pp.observaciones,
                u.nombre,
                u.apellido,
                p2.nombre AS plan_nombre,
                p2.periodo,
                p2.importe AS plan_importe
            FROM participante_planes pp
            INNER JOIN participantes p ON p.id = pp.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            INNER JOIN planes p2 ON p2.id = pp.plan_id
            WHERE 1=1
        ";

        $params = [];

        if ($participante_id) {
            $sql .= " AND pp.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        if ($estado) {
            $sql .= " AND pp.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY u.apellido, u.nombre, pp.fecha_inicio DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de planes asignados');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $plan_id = (int)($data['plan_id'] ?? 0);
        $fecha_inicio = trim($data['fecha_inicio'] ?? '');
        $fecha_fin = trim($data['fecha_fin'] ?? '');
        $precio_acordado = (float)($data['precio_acordado'] ?? 0);
        $estado = $data['estado'] ?? 'activo';
        $observaciones = $data['observaciones'] ?? null;

        if ($participante_id <= 0 || $plan_id <= 0 || $fecha_inicio === '') {
            Response::error('participante_id, plan_id y fecha_inicio son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO participante_planes (
                    participante_id, plan_id, fecha_inicio, fecha_fin,
                    precio_acordado, estado, observaciones
                ) VALUES (
                    :participante_id, :plan_id, :fecha_inicio, :fecha_fin,
                    :precio_acordado, :estado, :observaciones
                )
            ");

            $stmt->execute([
                'participante_id' => $participante_id,
                'plan_id' => $plan_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin !== '' ? $fecha_fin : null,
                'precio_acordado' => $precio_acordado,
                'estado' => $estado,
                'observaciones' => $observaciones
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Plan asignado', 201);
        } catch (PDOException $e) {
            Response::error('Error al asignar plan', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $plan_id = (int)($data['plan_id'] ?? 0);
        $fecha_inicio = trim($data['fecha_inicio'] ?? '');
        $fecha_fin = trim($data['fecha_fin'] ?? '');
        $precio_acordado = (float)($data['precio_acordado'] ?? 0);
        $estado = $data['estado'] ?? 'activo';
        $observaciones = $data['observaciones'] ?? null;

        if ($participante_id <= 0 || $plan_id <= 0 || $fecha_inicio === '') {
            Response::error('participante_id, plan_id y fecha_inicio son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                UPDATE participante_planes
                SET participante_id = :participante_id,
                    plan_id = :plan_id,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    precio_acordado = :precio_acordado,
                    estado = :estado,
                    observaciones = :observaciones
                WHERE id = :id
            ");

            $stmt->execute([
                'participante_id' => $participante_id,
                'plan_id' => $plan_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin !== '' ? $fecha_fin : null,
                'precio_acordado' => $precio_acordado,
                'estado' => $estado,
                'observaciones' => $observaciones,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Asignación no encontrada o sin cambios', 404);
            }

            Response::success([], 'Plan asignado actualizado');
        } catch (PDOException $e) {
            Response::error('Error al actualizar plan asignado', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM participante_planes WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Asignación no encontrada', 404);
            }

            Response::success([], 'Asignación eliminada');
        } catch (PDOException $e) {
            Response::error('Error al eliminar asignación', 500, $e->getMessage());
        }
    }
}