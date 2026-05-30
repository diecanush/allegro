<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class CargosController
{
    private static array $monthNames = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];

    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $participante_id = Request::query('participante_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                c.id,
                c.participante_id,
                c.participante_plan_id,
                c.concepto,
                c.periodo,
                c.fecha_generacion,
                c.fecha_vencimiento,
                c.importe,
                c.recargo,
                c.bonificacion,
                c.saldo,
                c.estado,
                c.observaciones,
                u.nombre,
                u.apellido
            FROM cargos c
            INNER JOIN participantes p ON p.id = c.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE 1=1
        ";

        $params = [];

        if ($participante_id) {
            $sql .= " AND c.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        if ($estado) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY c.fecha_vencimiento DESC, c.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de cargos');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $participante_plan_id = isset($data['participante_plan_id']) && $data['participante_plan_id'] !== '' ? (int)$data['participante_plan_id'] : null;
        $concepto = trim($data['concepto'] ?? '');
        $periodo = trim($data['periodo'] ?? '');
        $fecha_generacion = trim($data['fecha_generacion'] ?? '');
        $fecha_vencimiento = trim($data['fecha_vencimiento'] ?? '');
        $importe = (float)($data['importe'] ?? 0);
        $recargo = (float)($data['recargo'] ?? 0);
        $bonificacion = (float)($data['bonificacion'] ?? 0);
        $estado = $data['estado'] ?? 'pendiente';
        $observaciones = $data['observaciones'] ?? null;

        $saldo = $importe + $recargo - $bonificacion;

        if ($participante_id <= 0 || $concepto === '' || $fecha_generacion === '' || $fecha_vencimiento === '') {
            Response::error('participante_id, concepto, fecha_generacion y fecha_vencimiento son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO cargos (
                    participante_id, participante_plan_id, concepto, periodo,
                    fecha_generacion, fecha_vencimiento, importe, recargo,
                    bonificacion, saldo, estado, observaciones
                ) VALUES (
                    :participante_id, :participante_plan_id, :concepto, :periodo,
                    :fecha_generacion, :fecha_vencimiento, :importe, :recargo,
                    :bonificacion, :saldo, :estado, :observaciones
                )
            ");

            $stmt->execute([
                'participante_id' => $participante_id,
                'participante_plan_id' => $participante_plan_id,
                'concepto' => $concepto,
                'periodo' => $periodo !== '' ? $periodo : null,
                'fecha_generacion' => $fecha_generacion,
                'fecha_vencimiento' => $fecha_vencimiento,
                'importe' => $importe,
                'recargo' => $recargo,
                'bonificacion' => $bonificacion,
                'saldo' => $saldo,
                'estado' => $estado,
                'observaciones' => $observaciones
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Cargo creado', 201);
        } catch (PDOException $e) {
            Response::error('Error al crear cargo', 500, $e->getMessage());
        }
    }

    public static function generarMensuales(): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $periodo = trim($data['periodo'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            Response::error('El periodo debe tener formato YYYY-MM', 422, $data);
        }

        try {
            $inicio = new DateTimeImmutable($periodo . '-01');
        } catch (Exception $e) {
            Response::error('Periodo invalido', 422, $data);
        }

        $fin = $inicio->modify('last day of this month');
        $fechaGeneracion = $inicio->format('Y-m-d');
        $fechaVencimiento = $inicio->setDate((int)$inicio->format('Y'), (int)$inicio->format('m'), 10)->format('Y-m-d');
        $mesNombre = self::$monthNames[(int)$inicio->format('n')] ?? $inicio->format('m');
        $anio = $inicio->format('Y');

        try {
            $stmt = $db->prepare("
                SELECT
                    pp.id AS participante_plan_id,
                    pp.participante_id,
                    pp.precio_acordado,
                    p2.nombre AS plan_nombre,
                    u.nombre,
                    u.apellido,
                    GROUP_CONCAT(DISTINCT g.nombre ORDER BY g.nombre SEPARATOR ', ') AS grupos
                FROM participante_planes pp
                INNER JOIN planes p2 ON p2.id = pp.plan_id
                INNER JOIN participantes p ON p.id = pp.participante_id
                INNER JOIN usuarios u ON u.id = p.usuario_id
                INNER JOIN inscripciones i ON i.participante_id = pp.participante_id
                INNER JOIN grupos g ON g.id = i.grupo_id
                WHERE pp.estado = 'activo'
                  AND p.estado = 'activo'
                  AND p2.estado = 'activo'
                  AND i.estado = 'activa'
                  AND g.estado = 'activo'
                  AND pp.fecha_inicio <= :fin_plan
                  AND (pp.fecha_fin IS NULL OR pp.fecha_fin >= :inicio_plan)
                  AND i.fecha_inscripcion <= :fin_inscripcion
                  AND (i.fecha_baja IS NULL OR i.fecha_baja >= :inicio_inscripcion)
                GROUP BY
                    pp.id,
                    pp.participante_id,
                    pp.precio_acordado,
                    p2.nombre,
                    u.nombre,
                    u.apellido
                ORDER BY u.apellido, u.nombre
            ");
            $stmt->execute([
                'inicio_plan' => $inicio->format('Y-m-d'),
                'fin_plan' => $fin->format('Y-m-d'),
                'inicio_inscripcion' => $inicio->format('Y-m-d'),
                'fin_inscripcion' => $fin->format('Y-m-d')
            ]);

            $candidatos = $stmt->fetchAll();

            $stmtExiste = $db->prepare("
                SELECT id
                FROM cargos
                WHERE participante_id = :participante_id
                  AND participante_plan_id = :participante_plan_id
                  AND periodo = :periodo
                LIMIT 1
            ");

            $stmtInsert = $db->prepare("
                INSERT INTO cargos (
                    participante_id, participante_plan_id, concepto, periodo,
                    fecha_generacion, fecha_vencimiento, importe, recargo,
                    bonificacion, saldo, estado, observaciones
                ) VALUES (
                    :participante_id, :participante_plan_id, :concepto, :periodo,
                    :fecha_generacion, :fecha_vencimiento, :importe, 0,
                    0, :saldo, 'pendiente', :observaciones
                )
            ");

            $generados = [];
            $omitidos = [];

            $db->beginTransaction();

            foreach ($candidatos as $row) {
                $stmtExiste->execute([
                    'participante_id' => $row['participante_id'],
                    'participante_plan_id' => $row['participante_plan_id'],
                    'periodo' => $periodo
                ]);

                $existente = $stmtExiste->fetch();

                if ($existente) {
                    $omitidos[] = [
                        'participante_id' => (int)$row['participante_id'],
                        'participante_plan_id' => (int)$row['participante_plan_id'],
                        'cargo_id' => (int)$existente['id'],
                        'motivo' => 'Cargo ya existente para el periodo'
                    ];
                    continue;
                }

                $importe = (float)$row['precio_acordado'];
                $concepto = 'Cuota ' . $mesNombre . ' ' . $anio . ' - ' . $row['plan_nombre'];
                $observaciones = 'Generado automaticamente por inscripciones activas. Grupos: ' . ($row['grupos'] ?: '-');

                $stmtInsert->execute([
                    'participante_id' => $row['participante_id'],
                    'participante_plan_id' => $row['participante_plan_id'],
                    'concepto' => $concepto,
                    'periodo' => $periodo,
                    'fecha_generacion' => $fechaGeneracion,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'importe' => $importe,
                    'saldo' => $importe,
                    'observaciones' => $observaciones
                ]);

                $generados[] = [
                    'id' => (int)$db->lastInsertId(),
                    'participante_id' => (int)$row['participante_id'],
                    'participante' => trim($row['apellido'] . ', ' . $row['nombre']),
                    'participante_plan_id' => (int)$row['participante_plan_id'],
                    'importe' => $importe,
                    'grupos' => $row['grupos']
                ];
            }

            $db->commit();

            Response::success([
                'periodo' => $periodo,
                'fecha_generacion' => $fechaGeneracion,
                'fecha_vencimiento' => $fechaVencimiento,
                'candidatos' => count($candidatos),
                'generados' => $generados,
                'omitidos' => $omitidos
            ], 'Generacion de cargos finalizada');
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::error('Error al generar cargos', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $participante_plan_id = isset($data['participante_plan_id']) && $data['participante_plan_id'] !== '' ? (int)$data['participante_plan_id'] : null;
        $concepto = trim($data['concepto'] ?? '');
        $periodo = trim($data['periodo'] ?? '');
        $fecha_generacion = trim($data['fecha_generacion'] ?? '');
        $fecha_vencimiento = trim($data['fecha_vencimiento'] ?? '');
        $importe = (float)($data['importe'] ?? 0);
        $recargo = (float)($data['recargo'] ?? 0);
        $bonificacion = (float)($data['bonificacion'] ?? 0);
        $saldo = (float)($data['saldo'] ?? ($importe + $recargo - $bonificacion));
        $estado = $data['estado'] ?? 'pendiente';
        $observaciones = $data['observaciones'] ?? null;

        if ($participante_id <= 0 || $concepto === '' || $fecha_generacion === '' || $fecha_vencimiento === '') {
            Response::error('participante_id, concepto, fecha_generacion y fecha_vencimiento son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                UPDATE cargos
                SET participante_id = :participante_id,
                    participante_plan_id = :participante_plan_id,
                    concepto = :concepto,
                    periodo = :periodo,
                    fecha_generacion = :fecha_generacion,
                    fecha_vencimiento = :fecha_vencimiento,
                    importe = :importe,
                    recargo = :recargo,
                    bonificacion = :bonificacion,
                    saldo = :saldo,
                    estado = :estado,
                    observaciones = :observaciones
                WHERE id = :id
            ");

            $stmt->execute([
                'participante_id' => $participante_id,
                'participante_plan_id' => $participante_plan_id,
                'concepto' => $concepto,
                'periodo' => $periodo !== '' ? $periodo : null,
                'fecha_generacion' => $fecha_generacion,
                'fecha_vencimiento' => $fecha_vencimiento,
                'importe' => $importe,
                'recargo' => $recargo,
                'bonificacion' => $bonificacion,
                'saldo' => $saldo,
                'estado' => $estado,
                'observaciones' => $observaciones,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Cargo no encontrado o sin cambios', 404);
            }

            Response::success([], 'Cargo actualizado');
        } catch (PDOException $e) {
            Response::error('Error al actualizar cargo', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM cargos WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Cargo no encontrado', 404);
            }

            Response::success([], 'Cargo eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar cargo', 500, $e->getMessage());
        }
    }
}
