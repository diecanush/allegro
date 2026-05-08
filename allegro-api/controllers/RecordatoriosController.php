<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class RecordatoriosController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $participante_id = Request::query('participante_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                r.id,
                r.participante_id,
                r.cargo_id,
                r.canal,
                r.tipo,
                r.asunto,
                r.mensaje,
                r.estado,
                r.fecha_programada,
                r.fecha_envio,
                r.respuesta,
                r.fecha_alta,
                u.nombre,
                u.apellido
            FROM recordatorios r
            INNER JOIN participantes p ON p.id = r.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE 1=1
        ";

        $params = [];

        if ($participante_id) {
            $sql .= " AND r.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        if ($estado) {
            $sql .= " AND r.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY r.fecha_programada DESC, r.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de recordatorios');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $cargo_id = isset($data['cargo_id']) && $data['cargo_id'] !== '' ? (int)$data['cargo_id'] : null;
        $canal = $data['canal'] ?? 'manual';
        $tipo = $data['tipo'] ?? 'deuda';
        $asunto = trim($data['asunto'] ?? '');
        $mensaje = trim($data['mensaje'] ?? '');
        $estado = $data['estado'] ?? 'pendiente';
        $fecha_programada = trim($data['fecha_programada'] ?? '');
        $respuesta = $data['respuesta'] ?? null;

        if ($participante_id <= 0 || $mensaje === '') {
            Response::error('participante_id y mensaje son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO recordatorios (
                    participante_id, cargo_id, canal, tipo,
                    asunto, mensaje, estado, fecha_programada, respuesta
                ) VALUES (
                    :participante_id, :cargo_id, :canal, :tipo,
                    :asunto, :mensaje, :estado, :fecha_programada, :respuesta
                )
            ");
            $stmt->execute([
                'participante_id' => $participante_id,
                'cargo_id' => $cargo_id,
                'canal' => $canal,
                'tipo' => $tipo,
                'asunto' => $asunto !== '' ? $asunto : null,
                'mensaje' => $mensaje,
                'estado' => $estado,
                'fecha_programada' => $fecha_programada !== '' ? $fecha_programada : null,
                'respuesta' => $respuesta
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Recordatorio creado', 201);
        } catch (PDOException $e) {
            Response::error('Error al crear recordatorio', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $canal = $data['canal'] ?? 'manual';
        $tipo = $data['tipo'] ?? 'deuda';
        $asunto = trim($data['asunto'] ?? '');
        $mensaje = trim($data['mensaje'] ?? '');
        $estado = $data['estado'] ?? 'pendiente';
        $fecha_programada = trim($data['fecha_programada'] ?? '');
        $fecha_envio = trim($data['fecha_envio'] ?? '');
        $respuesta = $data['respuesta'] ?? null;

        if ($mensaje === '') {
            Response::error('mensaje es obligatorio', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                UPDATE recordatorios
                SET canal = :canal,
                    tipo = :tipo,
                    asunto = :asunto,
                    mensaje = :mensaje,
                    estado = :estado,
                    fecha_programada = :fecha_programada,
                    fecha_envio = :fecha_envio,
                    respuesta = :respuesta
                WHERE id = :id
            ");
            $stmt->execute([
                'canal' => $canal,
                'tipo' => $tipo,
                'asunto' => $asunto !== '' ? $asunto : null,
                'mensaje' => $mensaje,
                'estado' => $estado,
                'fecha_programada' => $fecha_programada !== '' ? $fecha_programada : null,
                'fecha_envio' => $fecha_envio !== '' ? $fecha_envio : null,
                'respuesta' => $respuesta,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Recordatorio no encontrado o sin cambios', 404);
            }

            Response::success([], 'Recordatorio actualizado');
        } catch (PDOException $e) {
            Response::error('Error al actualizar recordatorio', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM recordatorios WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Recordatorio no encontrado', 404);
            }

            Response::success([], 'Recordatorio eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar recordatorio', 500, $e->getMessage());
        }
    }
}