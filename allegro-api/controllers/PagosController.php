<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class PagosController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $participante_id = Request::query('participante_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                p.id,
                p.participante_id,
                p.fecha_pago,
                p.importe,
                p.medio_pago,
                p.referencia,
                p.estado,
                p.observaciones,
                u.nombre,
                u.apellido
            FROM pagos p
            INNER JOIN participantes pa ON pa.id = p.participante_id
            INNER JOIN usuarios u ON u.id = pa.usuario_id
            WHERE 1=1
        ";

        $params = [];

        if ($participante_id) {
            $sql .= " AND p.participante_id = :participante_id";
            $params['participante_id'] = $participante_id;
        }

        if ($estado) {
            $sql .= " AND p.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY p.fecha_pago DESC, p.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de pagos');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                p.*,
                u.nombre,
                u.apellido
            FROM pagos p
            INNER JOIN participantes pa ON pa.id = p.participante_id
            INNER JOIN usuarios u ON u.id = pa.usuario_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $pago = $stmt->fetch();

        if (!$pago) {
            Response::error('Pago no encontrado', 404);
        }

        $stmtDet = $db->prepare("
            SELECT
                pd.id,
                pd.cargo_id,
                pd.importe_aplicado,
                c.concepto,
                c.periodo,
                c.fecha_vencimiento
            FROM pago_detalles pd
            INNER JOIN cargos c ON c.id = pd.cargo_id
            WHERE pd.pago_id = :pago_id
            ORDER BY pd.id
        ");
        $stmtDet->execute(['pago_id' => $id]);

        $pago['detalles'] = $stmtDet->fetchAll();

        Response::success($pago, 'Pago encontrado');
    }

    public static function store(): void
    {
        $user = requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $participante_id = (int)($data['participante_id'] ?? 0);
        $fecha_pago = trim($data['fecha_pago'] ?? '');
        $importe = (float)($data['importe'] ?? 0);
        $medio_pago = $data['medio_pago'] ?? 'transferencia';
        $referencia = trim($data['referencia'] ?? '');
        $estado = $data['estado'] ?? 'aprobado';
        $observaciones = $data['observaciones'] ?? null;
        $detalles = $data['detalles'] ?? [];

        if ($participante_id <= 0 || $fecha_pago === '' || $importe <= 0) {
            Response::error('participante_id, fecha_pago e importe son obligatorios', 422, $data);
        }

        try {
            $db->beginTransaction();

            $stmtPago = $db->prepare("
                INSERT INTO pagos (
                    participante_id, fecha_pago, importe, medio_pago,
                    referencia, estado, registrado_por, observaciones
                ) VALUES (
                    :participante_id, :fecha_pago, :importe, :medio_pago,
                    :referencia, :estado, :registrado_por, :observaciones
                )
            ");

            $stmtPago->execute([
                'participante_id' => $participante_id,
                'fecha_pago' => $fecha_pago,
                'importe' => $importe,
                'medio_pago' => $medio_pago,
                'referencia' => $referencia !== '' ? $referencia : null,
                'estado' => $estado,
                'registrado_por' => $user['id'],
                'observaciones' => $observaciones
            ]);

            $pagoId = (int)$db->lastInsertId();

            if (is_array($detalles) && count($detalles) > 0) {
                $stmtDet = $db->prepare("
                    INSERT INTO pago_detalles (pago_id, cargo_id, importe_aplicado)
                    VALUES (:pago_id, :cargo_id, :importe_aplicado)
                ");

                $stmtCargo = $db->prepare("
                    SELECT id, saldo
                    FROM cargos
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmtUpdCargo = $db->prepare("
                    UPDATE cargos
                    SET saldo = :saldo,
                        estado = :estado
                    WHERE id = :id
                ");

                foreach ($detalles as $item) {
                    $cargo_id = (int)($item['cargo_id'] ?? 0);
                    $importe_aplicado = (float)($item['importe_aplicado'] ?? 0);

                    if ($cargo_id <= 0 || $importe_aplicado <= 0) {
                        continue;
                    }

                    $stmtCargo->execute(['id' => $cargo_id]);
                    $cargo = $stmtCargo->fetch();

                    if (!$cargo) {
                        continue;
                    }

                    $nuevoSaldo = (float)$cargo['saldo'] - $importe_aplicado;
                    if ($nuevoSaldo < 0) {
                        $nuevoSaldo = 0;
                    }

                    $nuevoEstado = 'parcial';
                    if ($nuevoSaldo == 0.0) {
                        $nuevoEstado = 'pagado';
                    }

                    $stmtDet->execute([
                        'pago_id' => $pagoId,
                        'cargo_id' => $cargo_id,
                        'importe_aplicado' => $importe_aplicado
                    ]);

                    $stmtUpdCargo->execute([
                        'saldo' => $nuevoSaldo,
                        'estado' => $nuevoEstado,
                        'id' => $cargo_id
                    ]);
                }
            }

            $db->commit();

            Response::success(['id' => $pagoId], 'Pago registrado', 201);
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Response::error('Error al registrar pago', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM pagos WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Pago no encontrado', 404);
            }

            Response::success([], 'Pago eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar pago', 500, $e->getMessage());
        }
    }
}