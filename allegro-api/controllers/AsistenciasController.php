<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class AsistenciasController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $clase_id = Request::query('clase_id');
        $participante_id = Request::query('participante_id');

        $sql = "
            SELECT
                a.id,
                a.clase_id,
                a.participante_id,
                a.estado,
                a.hora_registro,
                a.observaciones,
                c.fecha AS clase_fecha,
                u.nombre,
                u.apellido
            FROM asistencias a
            INNER JOIN clases c ON c.id = a.clase_id
            INNER JOIN participantes p ON p.id = a.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE 1=1
        ";

        $params = [];

        if ($clase_id) {
            $sql .= " AND a.clase_id = :clase_id";
            $params['clase_id'] = $clase_id;
        }

        if ($participante_id) {
            $sql .= " AND a.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        $sql .= " ORDER BY c.fecha DESC, u.apellido, u.nombre";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de asistencias');
    }

    public static function store(): void
    {
        $user = requireAuth(['admin', 'profesor']);

        $db = DB::get();

        try {
            $data = Request::body();

            $clase_id = (int)($data['clase_id'] ?? 0);
            $participante_id = (int)($data['participante_id'] ?? 0);
            $estado = $data['estado'] ?? 'presente';
            $observaciones = $data['observaciones'] ?? null;

            if ($clase_id <= 0 || $participante_id <= 0) {
                Response::error('clase_id y participante_id son obligatorios', 422, $data);
            }

            self::validarParticipanteInscriptoEnClase($db, $clase_id, $participante_id);

            $stmt = $db->prepare("
                INSERT INTO asistencias (
                    clase_id,
                    participante_id,
                    estado,
                    registrada_por,
                    observaciones
                ) VALUES (
                    :clase_id,
                    :participante_id,
                    :estado,
                    :registrada_por,
                    :observaciones
                )
                ON DUPLICATE KEY UPDATE
                    estado = VALUES(estado),
                    registrada_por = VALUES(registrada_por),
                    observaciones = VALUES(observaciones),
                    hora_registro = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                'clase_id' => $clase_id,
                'participante_id' => $participante_id,
                'estado' => $estado,
                'registrada_por' => $user['id'],
                'observaciones' => $observaciones
            ]);

            Response::success([], 'Asistencia registrada');
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                Response::error('Clase, participante o usuario inválido para registrar la asistencia', 422, $e->getMessage());
            }

            Response::error('Error al registrar asistencia', 500, $e->getMessage());
        } catch (Throwable $e) {
            Response::error('Error inesperado al registrar asistencia', 500, $e->getMessage());
        }
    }

    public static function storeBulk(): void
    {
        $user = requireAuth(['admin', 'profesor']);

        $db = DB::get();

        try {
            $data = Request::body();

            $clase_id = (int)($data['clase_id'] ?? 0);
            $asistencias = $data['asistencias'] ?? [];

            if ($clase_id <= 0 || !is_array($asistencias) || count($asistencias) === 0) {
                Response::error('clase_id y asistencias son obligatorios', 422, $data);
            }

            $stmtClase = $db->prepare("
                SELECT id, grupo_id
                FROM clases
                WHERE id = :id
                LIMIT 1
            ");
            $stmtClase->execute(['id' => $clase_id]);
            $clase = $stmtClase->fetch();

            if (!$clase) {
                Response::error('La clase indicada no existe', 422);
            }

            $stmt = $db->prepare("
                INSERT INTO asistencias (
                    clase_id,
                    participante_id,
                    estado,
                    registrada_por,
                    observaciones
                ) VALUES (
                    :clase_id,
                    :participante_id,
                    :estado,
                    :registrada_por,
                    :observaciones
                )
                ON DUPLICATE KEY UPDATE
                    estado = VALUES(estado),
                    registrada_por = VALUES(registrada_por),
                    observaciones = VALUES(observaciones),
                    hora_registro = CURRENT_TIMESTAMP
            ");

            $db->beginTransaction();

            $procesadas = 0;
            $omitidas = [];

            foreach ($asistencias as $item) {
                $participante_id = (int)($item['participante_id'] ?? 0);
                $estado = $item['estado'] ?? 'presente';
                $observaciones = $item['observaciones'] ?? null;

                if ($participante_id <= 0) {
                    $omitidas[] = [
                        'participante_id' => $participante_id,
                        'motivo' => 'participante_id inválido'
                    ];
                    continue;
                }

                if (!self::estaInscriptoEnGrupoDeClase($db, $clase_id, $participante_id)) {
                    $omitidas[] = [
                        'participante_id' => $participante_id,
                        'motivo' => 'No está inscripto en el grupo de la clase'
                    ];
                    continue;
                }

                $stmt->execute([
                    'clase_id' => $clase_id,
                    'participante_id' => $participante_id,
                    'estado' => $estado,
                    'registrada_por' => $user['id'],
                    'observaciones' => $observaciones
                ]);

                $procesadas++;
            }

            $db->commit();

            Response::success([
                'procesadas' => $procesadas,
                'omitidas' => $omitidas
            ], 'Asistencias registradas');
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ((string)$e->getCode() === '23000') {
                Response::error('Datos inválidos al registrar asistencias', 422, $e->getMessage());
            }

            Response::error('Error al registrar asistencias', 500, $e->getMessage());
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Response::error('Error inesperado al registrar asistencias', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM asistencias WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Asistencia no encontrada', 404);
            }

            Response::success([], 'Asistencia eliminada');
        } catch (PDOException $e) {
            Response::error('Error al eliminar asistencia', 500, $e->getMessage());
        }
    }

    private static function validarParticipanteInscriptoEnClase(PDO $db, int $clase_id, int $participante_id): void
    {
        $stmtClase = $db->prepare("
            SELECT id, grupo_id
            FROM clases
            WHERE id = :id
            LIMIT 1
        ");
        $stmtClase->execute(['id' => $clase_id]);
        $clase = $stmtClase->fetch();

        if (!$clase) {
            Response::error('La clase indicada no existe', 422);
        }

        $stmtParticipante = $db->prepare("
            SELECT id
            FROM participantes
            WHERE id = :id
            LIMIT 1
        ");
        $stmtParticipante->execute(['id' => $participante_id]);
        $participante = $stmtParticipante->fetch();

        if (!$participante) {
            Response::error('El participante indicado no existe', 422);
        }

        if (!self::estaInscriptoEnGrupoDeClase($db, $clase_id, $participante_id)) {
            Response::error('El participante no está inscripto en el grupo de esa clase', 422);
        }
    }

    private static function estaInscriptoEnGrupoDeClase(PDO $db, int $clase_id, int $participante_id): bool
    {
        $stmt = $db->prepare("
            SELECT c.id
            FROM clases c
            INNER JOIN inscripciones i ON i.grupo_id = c.grupo_id
            WHERE c.id = :clase_id
              AND i.participante_id = :participante_id
              AND i.estado IN ('activa', 'pausada')
              AND (i.fecha_baja IS NULL OR i.fecha_baja >= c.fecha)
            LIMIT 1
        ");

        $stmt->execute([
            'clase_id' => $clase_id,
            'participante_id' => $participante_id
        ]);

        return (bool)$stmt->fetch();
    }
}