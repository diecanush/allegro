<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class EstadoCuentaController
{
    public static function participante($participanteId): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();

        $stmtPart = $db->prepare("
            SELECT
                p.id,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                p.dni,
                p.estado
            FROM participantes p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmtPart->execute(['id' => $participanteId]);
        $participante = $stmtPart->fetch();

        if (!$participante) {
            Response::error('Participante no encontrado', 404);
        }

        $stmtCargos = $db->prepare("
            SELECT
                id, concepto, periodo, fecha_generacion, fecha_vencimiento,
                importe, recargo, bonificacion, saldo, estado, observaciones
            FROM cargos
            WHERE participante_id = :participante_id
            ORDER BY fecha_vencimiento DESC, id DESC
        ");
        $stmtCargos->execute(['participante_id' => $participanteId]);
        $cargos = $stmtCargos->fetchAll();

        $stmtPagos = $db->prepare("
            SELECT
                id, fecha_pago, importe, medio_pago, referencia, estado, observaciones
            FROM pagos
            WHERE participante_id = :participante_id
            ORDER BY fecha_pago DESC, id DESC
        ");
        $stmtPagos->execute(['participante_id' => $participanteId]);
        $pagos = $stmtPagos->fetchAll();

        $stmtTot = $db->prepare("
            SELECT
                COALESCE(SUM(importe + recargo - bonificacion),0) AS total_cargado,
                COALESCE(SUM(saldo),0) AS saldo_pendiente
            FROM cargos
            WHERE participante_id = :participante_id
        ");
        $stmtTot->execute(['participante_id' => $participanteId]);
        $totales = $stmtTot->fetch();

        $stmtPagTot = $db->prepare("
            SELECT COALESCE(SUM(importe),0) AS total_pagado
            FROM pagos
            WHERE participante_id = :participante_id
              AND estado IN ('aprobado','pendiente_validacion')
        ");
        $stmtPagTot->execute(['participante_id' => $participanteId]);
        $pagosTot = $stmtPagTot->fetch();

        Response::success([
            'participante' => $participante,
            'totales' => [
                'total_cargado' => (float)$totales['total_cargado'],
                'saldo_pendiente' => (float)$totales['saldo_pendiente'],
                'total_pagado' => (float)$pagosTot['total_pagado']
            ],
            'cargos' => $cargos,
            'pagos' => $pagos
        ], 'Estado de cuenta');
    }
}