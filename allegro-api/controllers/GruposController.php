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
            ORDER BY g.nombre
        ";

        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll();

        self::adjuntarHorarios($db, $rows);

        usort($rows, function ($a, $b) {
            return strcmp(self::primerHorarioOrdenable($a), self::primerHorarioOrdenable($b));
        });

        Response::success($rows, 'Listado de grupos');
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

        $rows = [$row];
        self::adjuntarHorarios($db, $rows);

        Response::success($rows[0], 'Grupo encontrado');
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
        $cupo_maximo = isset($data['cupo_maximo']) && $data['cupo_maximo'] !== '' ? (int)$data['cupo_maximo'] : null;
        $estado = $data['estado'] ?? 'activo';
        $horarios = self::normalizarHorarios($data);

        if ($actividad_id <= 0 || $profesor_id <= 0 || $nombre === '' || count($horarios) === 0) {
            Response::error('Faltan campos obligatorios', 422);
        }

        $primerHorario = $horarios[0];

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
            $db->beginTransaction();

            $stmt->execute([
                'actividad_id' => $actividad_id,
                'profesor_id' => $profesor_id,
                'sede_id' => $sede_id,
                'nombre' => $nombre,
                'dia_semana' => $primerHorario['dia_semana'],
                'hora_inicio' => $primerHorario['hora_inicio'],
                'hora_fin' => $primerHorario['hora_fin'],
                'cupo_maximo' => $cupo_maximo,
                'estado' => $estado
            ]);

            $grupoId = (int)$db->lastInsertId();
            self::guardarHorarios($db, $grupoId, $horarios);

            $db->commit();

            Response::success(['id' => $grupoId], 'Grupo creado', 201);
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

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
        $cupo_maximo = isset($data['cupo_maximo']) && $data['cupo_maximo'] !== '' ? (int)$data['cupo_maximo'] : null;
        $estado = $data['estado'] ?? 'activo';
        $horarios = self::normalizarHorarios($data);

        if ($actividad_id <= 0 || $profesor_id <= 0 || $nombre === '' || count($horarios) === 0) {
            Response::error('Faltan campos obligatorios', 422);
        }

        $primerHorario = $horarios[0];

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
            $db->beginTransaction();

            $stmt->execute([
                'actividad_id' => $actividad_id,
                'profesor_id' => $profesor_id,
                'sede_id' => $sede_id,
                'nombre' => $nombre,
                'dia_semana' => $primerHorario['dia_semana'],
                'hora_inicio' => $primerHorario['hora_inicio'],
                'hora_fin' => $primerHorario['hora_fin'],
                'cupo_maximo' => $cupo_maximo,
                'estado' => $estado,
                'id' => $id
            ]);

            self::guardarHorarios($db, (int)$id, $horarios);
            $db->commit();

            Response::success([], 'Grupo actualizado');
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

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

    public static function horariosPorGrupo(PDO $db, array $grupoIds): array
    {
        $grupoIds = array_values(array_unique(array_map('intval', $grupoIds)));
        if (!$grupoIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($grupoIds), '?'));
        $stmt = $db->prepare("
            SELECT
                id,
                grupo_id,
                dia_semana,
                hora_inicio,
                hora_fin
            FROM grupo_horarios
            WHERE grupo_id IN ($placeholders)
            ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio, id
        ");
        $stmt->execute($grupoIds);

        $horariosPorGrupo = [];
        foreach ($stmt->fetchAll() as $horario) {
            $grupoId = (int)$horario['grupo_id'];
            $horariosPorGrupo[$grupoId][] = [
                'id' => (int)$horario['id'],
                'dia_semana' => $horario['dia_semana'],
                'hora_inicio' => $horario['hora_inicio'],
                'hora_fin' => $horario['hora_fin']
            ];
        }

        return $horariosPorGrupo;
    }

    public static function normalizarHorarios(array $data): array
    {
        $horarios = $data['horarios'] ?? null;
        $normalizados = [];

        if (is_array($horarios) && count($horarios) > 0) {
            foreach ($horarios as $horario) {
                if (!is_array($horario)) {
                    continue;
                }

                $diaSemana = trim((string)($horario['dia_semana'] ?? ''));
                $horaInicio = trim((string)($horario['hora_inicio'] ?? ''));
                $horaFin = trim((string)($horario['hora_fin'] ?? ''));

                if ($diaSemana === '' || $horaInicio === '' || $horaFin === '') {
                    continue;
                }

                $normalizados[] = [
                    'dia_semana' => $diaSemana,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin
                ];
            }
        }

        if (!$normalizados) {
            $diaSemana = trim((string)($data['dia_semana'] ?? ''));
            $horaInicio = trim((string)($data['hora_inicio'] ?? ''));
            $horaFin = trim((string)($data['hora_fin'] ?? ''));

            if ($diaSemana !== '' && $horaInicio !== '' && $horaFin !== '') {
                $normalizados[] = [
                    'dia_semana' => $diaSemana,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin
                ];
            }
        }

        usort($normalizados, function ($a, $b) {
            $left = self::diaSemanaOrden($a['dia_semana']) . '|' . $a['hora_inicio'];
            $right = self::diaSemanaOrden($b['dia_semana']) . '|' . $b['hora_inicio'];
            return strcmp($left, $right);
        });

        return array_values(array_unique($normalizados, SORT_REGULAR));
    }

    public static function adjuntarHorarios(PDO $db, array &$rows): void
    {
        $grupoIds = array_map(fn($row) => (int)$row['id'], $rows);
        $horariosPorGrupo = self::horariosPorGrupo($db, $grupoIds);

        foreach ($rows as &$row) {
            $grupoId = (int)$row['id'];
            $horarios = $horariosPorGrupo[$grupoId] ?? [];

            if (!$horarios && !empty($row['dia_semana']) && !empty($row['hora_inicio']) && !empty($row['hora_fin'])) {
                $horarios = [[
                    'id' => null,
                    'dia_semana' => $row['dia_semana'],
                    'hora_inicio' => $row['hora_inicio'],
                    'hora_fin' => $row['hora_fin']
                ]];
            }

            $row['horarios'] = $horarios;
            $row['horarios_texto'] = self::horariosComoTexto($horarios);

            if ($horarios) {
                $row['dia_semana'] = $horarios[0]['dia_semana'];
                $row['hora_inicio'] = $horarios[0]['hora_inicio'];
                $row['hora_fin'] = $horarios[0]['hora_fin'];
            }
        }
    }

    private static function guardarHorarios(PDO $db, int $grupoId, array $horarios): void
    {
        $db->prepare("DELETE FROM grupo_horarios WHERE grupo_id = :grupo_id")->execute([
            'grupo_id' => $grupoId
        ]);

        $stmt = $db->prepare("
            INSERT INTO grupo_horarios (grupo_id, dia_semana, hora_inicio, hora_fin)
            VALUES (:grupo_id, :dia_semana, :hora_inicio, :hora_fin)
        ");

        foreach ($horarios as $horario) {
            $stmt->execute([
                'grupo_id' => $grupoId,
                'dia_semana' => $horario['dia_semana'],
                'hora_inicio' => $horario['hora_inicio'],
                'hora_fin' => $horario['hora_fin']
            ]);
        }
    }

    private static function horariosComoTexto(array $horarios): string
    {
        return implode(', ', array_map(function ($horario) {
            return $horario['dia_semana'] . ' ' . substr($horario['hora_inicio'], 0, 5) . '-' . substr($horario['hora_fin'], 0, 5);
        }, $horarios));
    }

    private static function primerHorarioOrdenable(array $grupo): string
    {
        $dia = $grupo['dia_semana'] ?? '';
        $hora = $grupo['hora_inicio'] ?? '';
        return str_pad((string)self::diaSemanaOrden($dia), 2, '0', STR_PAD_LEFT) . '|' . $hora . '|' . ($grupo['nombre'] ?? '');
    }

    private static function diaSemanaOrden(string $diaSemana): int
    {
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

        return $mapa[strtolower(trim($diaSemana))] ?? 99;
    }
}
