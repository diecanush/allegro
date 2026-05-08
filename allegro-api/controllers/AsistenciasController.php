<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class AsistenciasController
{
    public static function matrix(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $grupoId = (int)(Request::query('grupo_id') ?? 0);
        $mes = trim((string)(Request::query('mes') ?? ''));

        if ($grupoId <= 0) {
            Response::error('grupo_id es obligatorio', 422);
        }

        [$mesNormalizado, $fechaInicio, $fechaFin] = self::resolverRangoMes($mes);
        $grupo = self::obtenerGrupo($db, $grupoId);
        $clases = self::obtenerClasesDelMes($db, $grupoId, $fechaInicio, $fechaFin);
        $fechasProgramadas = self::generarFechasProgramadas($grupo['horarios'] ?? [], $fechaInicio, $fechaFin);
        $columnas = self::combinarClasesYFechasProgramadas($clases, $fechasProgramadas, $grupo);
        $participantes = self::obtenerParticipantesDelGrupoParaMes($db, $grupoId, $fechaInicio, $fechaFin);
        $asistencias = self::obtenerAsistenciasDelMes($db, $grupoId, $fechaInicio, $fechaFin);

        $asistenciasPorParticipanteYFecha = [];
        foreach ($asistencias as $asistencia) {
            $asistenciasPorParticipanteYFecha[(int)$asistencia['participante_id']][$asistencia['clase_fecha']] = [
                'id' => (int)$asistencia['id'],
                'clase_id' => (int)$asistencia['clase_id'],
                'estado' => $asistencia['estado'],
                'observaciones' => $asistencia['observaciones'],
                'hora_registro' => $asistencia['hora_registro']
            ];
        }

        $filas = [];
        foreach ($participantes as $participante) {
            $participanteId = (int)$participante['participante_id'];
            $filaAsistencias = [];

            foreach ($columnas as $columna) {
                $filaAsistencias[$columna['fecha']] = $asistenciasPorParticipanteYFecha[$participanteId][$columna['fecha']] ?? null;
            }

            $filas[] = [
                'participante_id' => $participanteId,
                'nombre' => $participante['nombre'],
                'apellido' => $participante['apellido'],
                'email' => $participante['email'],
                'telefono' => $participante['telefono'],
                'inscripcion_estado' => $participante['inscripcion_estado'],
                'fecha_inscripcion' => $participante['fecha_inscripcion'],
                'fecha_baja' => $participante['fecha_baja'],
                'asistencias' => $filaAsistencias
            ];
        }

        Response::success([
            'grupo' => [
                'id' => (int)$grupo['id'],
                'nombre' => $grupo['nombre'],
                'dia_semana' => $grupo['dia_semana'],
                'hora_inicio' => $grupo['hora_inicio'],
                'hora_fin' => $grupo['hora_fin'],
                'horarios' => $grupo['horarios'],
                'horarios_texto' => $grupo['horarios_texto'],
                'actividad_nombre' => $grupo['actividad_nombre'],
                'profesor_nombre' => $grupo['profesor_nombre']
            ],
            'mes' => $mesNormalizado,
            'clases' => $columnas,
            'participantes' => $filas
        ], 'Matriz de asistencias');
    }

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

            $stmtLookup = $db->prepare("
                SELECT id, hora_registro
                FROM asistencias
                WHERE clase_id = :clase_id
                  AND participante_id = :participante_id
                LIMIT 1
            ");
            $stmtLookup->execute([
                'clase_id' => $clase_id,
                'participante_id' => $participante_id
            ]);
            $asistencia = $stmtLookup->fetch();

            Response::success([
                'id' => isset($asistencia['id']) ? (int)$asistencia['id'] : null,
                'hora_registro' => $asistencia['hora_registro'] ?? null
            ], 'Asistencia registrada');
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

    private static function resolverRangoMes(string $mes): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        $fechaInicio = $mes . '-01';
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        return [$mes, $fechaInicio, $fechaFin];
    }

    private static function obtenerGrupo(PDO $db, int $grupoId): array
    {
        $stmt = $db->prepare("
            SELECT
                g.id,
                g.nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,
                a.nombre AS actividad_nombre,
                CONCAT(u.nombre, ' ', u.apellido) AS profesor_nombre
            FROM grupos g
            INNER JOIN actividades a ON a.id = g.actividad_id
            INNER JOIN profesores p ON p.id = g.profesor_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE g.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $grupoId]);
        $grupo = $stmt->fetch();

        if (!$grupo) {
            Response::error('Grupo no encontrado', 404);
        }

        $grupos = [$grupo];
        GruposController::adjuntarHorarios($db, $grupos);

        return $grupos[0];
    }

    private static function obtenerClasesDelMes(PDO $db, int $grupoId, string $fechaInicio, string $fechaFin): array
    {
        $stmt = $db->prepare("
            SELECT
                id,
                fecha,
                hora_inicio,
                hora_fin,
                estado,
                observaciones
            FROM clases
            WHERE grupo_id = :grupo_id
              AND fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY fecha ASC, hora_inicio ASC, id ASC
        ");
        $stmt->execute([
            'grupo_id' => $grupoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);

        $clasesPorFecha = [];
        foreach ($stmt->fetchAll() as $clase) {
            if (!isset($clasesPorFecha[$clase['fecha']])) {
                $clasesPorFecha[$clase['fecha']] = [
                    'fecha' => $clase['fecha'],
                    'clase_id' => (int)$clase['id'],
                    'hora_inicio' => $clase['hora_inicio'],
                    'hora_fin' => $clase['hora_fin'],
                    'estado' => $clase['estado'],
                    'observaciones' => $clase['observaciones'],
                    'origen' => 'real'
                ];
            }
        }

        return $clasesPorFecha;
    }

    private static function generarFechasProgramadas(array $horarios, string $fechaInicio, string $fechaFin): array
    {
        $fechas = [];
        foreach ($horarios as $horario) {
            $numeroDiaSemana = self::mapearDiaSemanaANumero($horario['dia_semana'] ?? null);
            if ($numeroDiaSemana === null) {
                continue;
            }

            $cursor = new DateTimeImmutable($fechaInicio);
            $fin = new DateTimeImmutable($fechaFin);

            while ($cursor <= $fin) {
                if ((int)$cursor->format('N') === $numeroDiaSemana) {
                    $fechas[] = [
                        'fecha' => $cursor->format('Y-m-d'),
                        'hora_inicio' => $horario['hora_inicio'] ?? null,
                        'hora_fin' => $horario['hora_fin'] ?? null
                    ];
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        return $fechas;
    }

    private static function combinarClasesYFechasProgramadas(array $clases, array $fechasProgramadas, array $grupo): array
    {
        $resultado = $clases;

        foreach ($fechasProgramadas as $programada) {
            $fecha = $programada['fecha'];

            if (!isset($resultado[$fecha])) {
                $resultado[$fecha] = [
                    'fecha' => $fecha,
                    'clase_id' => null,
                    'hora_inicio' => $programada['hora_inicio'] ?? $grupo['hora_inicio'],
                    'hora_fin' => $programada['hora_fin'] ?? $grupo['hora_fin'],
                    'estado' => 'programada',
                    'observaciones' => null,
                    'origen' => 'programada'
                ];
            }
        }

        ksort($resultado);

        return array_values($resultado);
    }

    private static function obtenerParticipantesDelGrupoParaMes(PDO $db, int $grupoId, string $fechaInicio, string $fechaFin): array
    {
        $stmt = $db->prepare("
            SELECT
                p.id AS participante_id,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                i.estado AS inscripcion_estado,
                i.fecha_inscripcion,
                i.fecha_baja
            FROM inscripciones i
            INNER JOIN participantes p ON p.id = i.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE i.grupo_id = :grupo_id
              AND i.estado IN ('activa', 'pausada')
              AND i.fecha_inscripcion <= :fecha_fin
              AND (i.fecha_baja IS NULL OR i.fecha_baja = '' OR i.fecha_baja >= :fecha_inicio)
            ORDER BY u.apellido ASC, u.nombre ASC
        ");
        $stmt->execute([
            'grupo_id' => $grupoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);

        return $stmt->fetchAll();
    }

    private static function obtenerAsistenciasDelMes(PDO $db, int $grupoId, string $fechaInicio, string $fechaFin): array
    {
        $stmt = $db->prepare("
            SELECT
                a.id,
                a.clase_id,
                a.participante_id,
                a.estado,
                a.hora_registro,
                a.observaciones,
                c.fecha AS clase_fecha
            FROM asistencias a
            INNER JOIN clases c ON c.id = a.clase_id
            WHERE c.grupo_id = :grupo_id
              AND c.fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY c.fecha ASC, a.participante_id ASC
        ");
        $stmt->execute([
            'grupo_id' => $grupoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);

        return $stmt->fetchAll();
    }

    private static function mapearDiaSemanaANumero(?string $diaSemana): ?int
    {
        if (!$diaSemana) {
            return null;
        }

        $mapa = [
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'miércoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'sábado' => 6,
            'domingo' => 7
        ];

        $valor = trim($diaSemana);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($valor, 'UTF-8')
            : strtolower($valor);

        return $mapa[$clave] ?? null;
    }
}
