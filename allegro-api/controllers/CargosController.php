<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class CargosController
{
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