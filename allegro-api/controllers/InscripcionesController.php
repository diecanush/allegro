<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class InscripcionesController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $grupo_id = Request::query('grupo_id');
        $participante_id = Request::query('participante_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                i.id,
                i.participante_id,
                i.grupo_id,
                i.fecha_inscripcion,
                i.fecha_baja,
                i.estado,
                i.observaciones,
                i.fecha_alta,
                i.fecha_actualizacion,

                u.nombre,
                u.apellido,
                u.email,
                u.telefono,

                g.nombre AS grupo_nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,

                a.id AS actividad_id,
                a.nombre AS actividad_nombre
            FROM inscripciones i
            INNER JOIN participantes p ON p.id = i.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            INNER JOIN grupos g ON g.id = i.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            WHERE 1=1
        ";

        $params = [];

        if ($grupo_id) {
            $sql .= " AND i.grupo_id = :grupo_id";
            $params['grupo_id'] = $grupo_id;
        }

        if ($participante_id) {
            $sql .= " AND i.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        if ($estado) {
            $sql .= " AND i.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY g.nombre, u.apellido, u.nombre";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de inscripciones');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                i.*,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                g.nombre AS grupo_nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,
                a.nombre AS actividad_nombre
            FROM inscripciones i
            INNER JOIN participantes p ON p.id = i.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            INNER JOIN grupos g ON g.id = i.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            WHERE i.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Inscripción no encontrada', 404);
        }

        Response::success($row, 'Inscripción encontrada');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);
    
        $db = DB::get();
    
        try {
            $data = Request::body();
    
            $participante_id = (int)($data['participante_id'] ?? 0);
            $grupo_id = (int)($data['grupo_id'] ?? 0);
            $fecha_inscripcion = trim($data['fecha_inscripcion'] ?? '');
            $fecha_baja = trim($data['fecha_baja'] ?? '');
            $estado = $data['estado'] ?? 'activa';
            $observaciones = $data['observaciones'] ?? null;
    
            if ($participante_id <= 0 || $grupo_id <= 0 || $fecha_inscripcion === '') {
                Response::error('participante_id, grupo_id y fecha_inscripcion son obligatorios', 422, $data);
            }
    
            $stmt = $db->prepare("
                INSERT INTO inscripciones (
                    participante_id,
                    grupo_id,
                    fecha_inscripcion,
                    fecha_baja,
                    estado,
                    observaciones
                ) VALUES (
                    :participante_id,
                    :grupo_id,
                    :fecha_inscripcion,
                    :fecha_baja,
                    :estado,
                    :observaciones
                )
            ");
    
            $stmt->execute([
                'participante_id' => $participante_id,
                'grupo_id' => $grupo_id,
                'fecha_inscripcion' => $fecha_inscripcion,
                'fecha_baja' => $fecha_baja !== '' ? $fecha_baja : null,
                'estado' => $estado,
                'observaciones' => $observaciones
            ]);
    
            Response::success([
                'id' => (int)$db->lastInsertId()
            ], 'Inscripción creada', 201);
    
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                Response::error('Participante o grupo inexistente, o inscripción duplicada', 422, $e->getMessage());
            }
    
            Response::error('Error al crear inscripción', 500, $e->getMessage());
        } catch (Throwable $e) {
            Response::error('Error inesperado', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::error('JSON inválido o body vacío', 422, [
                'raw' => $raw,
                'json_error' => json_last_error_msg()
            ]);
        }

        $participante_id = (int)($data['participante_id'] ?? 0);
        $grupo_id = (int)($data['grupo_id'] ?? 0);
        $fecha_inscripcion = trim($data['fecha_inscripcion'] ?? '');
        $fecha_baja = trim($data['fecha_baja'] ?? '');
        $estado = $data['estado'] ?? 'activa';
        $observaciones = $data['observaciones'] ?? null;

        if ($participante_id <= 0 || $grupo_id <= 0 || $fecha_inscripcion === '') {
            Response::error('participante_id, grupo_id y fecha_inscripcion son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                UPDATE inscripciones
                SET participante_id = :participante_id,
                    grupo_id = :grupo_id,
                    fecha_inscripcion = :fecha_inscripcion,
                    fecha_baja = :fecha_baja,
                    estado = :estado,
                    observaciones = :observaciones
                WHERE id = :id
            ");

            $stmt->execute([
                'participante_id' => $participante_id,
                'grupo_id' => $grupo_id,
                'fecha_inscripcion' => $fecha_inscripcion,
                'fecha_baja' => $fecha_baja !== '' ? $fecha_baja : null,
                'estado' => $estado,
                'observaciones' => $observaciones,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Inscripción no encontrada o sin cambios', 404);
            }

            Response::success([], 'Inscripción actualizada');

        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                Response::error('Participante o grupo inexistente, o inscripción duplicada', 422, $e->getMessage());
            }

            Response::error('Error al actualizar inscripción', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM inscripciones WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Inscripción no encontrada', 404);
            }

            Response::success([], 'Inscripción eliminada');
        } catch (PDOException $e) {
            Response::error('Error al eliminar inscripción', 500, $e->getMessage());
        }
    }

    public static function participantesPorGrupo($grupoId): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                i.id AS inscripcion_id,
                i.estado AS inscripcion_estado,
                i.fecha_inscripcion,
                i.fecha_baja,
                p.id AS participante_id,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                p.dni,
                p.apto_fisico_vencimiento
            FROM inscripciones i
            INNER JOIN participantes p ON p.id = i.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE i.grupo_id = :grupo_id
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute(['grupo_id' => $grupoId]);

        Response::success($stmt->fetchAll(), 'Participantes del grupo');
    }

    public static function gruposPorParticipante($participanteId): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                i.id AS inscripcion_id,
                i.estado AS inscripcion_estado,
                i.fecha_inscripcion,
                i.fecha_baja,
                g.id AS grupo_id,
                g.nombre AS grupo_nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,
                a.id AS actividad_id,
                a.nombre AS actividad_nombre
            FROM inscripciones i
            INNER JOIN grupos g ON g.id = i.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            WHERE i.participante_id = :participante_id
            ORDER BY g.nombre
        ");
        $stmt->execute(['participante_id' => $participanteId]);

        Response::success($stmt->fetchAll(), 'Grupos del participante');
    }
}