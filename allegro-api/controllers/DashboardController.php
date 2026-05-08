<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class DashboardController
{
    public static function resumen(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01');
        $finMes = date('Y-m-t');

        $participantesActivos = self::scalar($db, "
            SELECT COUNT(*) 
            FROM participantes
            WHERE estado = 'activo'
        ");

        $profesoresActivos = self::scalar($db, "
            SELECT COUNT(*)
            FROM profesores
            WHERE estado = 'activo'
        ");

        $gruposActivos = self::scalar($db, "
            SELECT COUNT(*)
            FROM grupos
            WHERE estado = 'activo'
        ");

        $clasesHoy = self::scalar($db, "
            SELECT COUNT(*)
            FROM clases
            WHERE fecha = :hoy
              AND estado IN ('programada','dictada')
        ", ['hoy' => $hoy]);

        $asistenciasHoy = self::scalar($db, "
            SELECT COUNT(*)
            FROM asistencias a
            INNER JOIN clases c ON c.id = a.clase_id
            WHERE c.fecha = :hoy
        ", ['hoy' => $hoy]);

        $cargosPendientes = self::scalar($db, "
            SELECT COUNT(*)
            FROM cargos
            WHERE estado IN ('pendiente','parcial','vencido')
              AND saldo > 0
        ");

        $saldoPendiente = self::scalar($db, "
            SELECT COALESCE(SUM(saldo),0)
            FROM cargos
            WHERE estado IN ('pendiente','parcial','vencido')
              AND saldo > 0
        ");

        $pagosMes = self::scalar($db, "
            SELECT COALESCE(SUM(importe),0)
            FROM pagos
            WHERE fecha_pago >= :inicio_mes
              AND fecha_pago < DATE_ADD(:fin_mes, INTERVAL 1 DAY)
              AND estado IN ('aprobado','pendiente_validacion')
        ", [
            'inicio_mes' => $inicioMes,
            'fin_mes' => $finMes
        ]);

        $comprobantesPendientes = self::scalar($db, "
            SELECT COUNT(*)
            FROM comprobantes_pago
            WHERE estado = 'subido'
        ");

        $recordatoriosPendientes = self::scalar($db, "
            SELECT COUNT(*)
            FROM recordatorios
            WHERE estado = 'pendiente'
        ");

        $vencimientos = self::fetchAll($db, "
            SELECT
                c.id,
                c.participante_id,
                c.concepto,
                c.periodo,
                c.fecha_vencimiento,
                c.saldo,
                c.estado,
                u.nombre,
                u.apellido
            FROM cargos c
            INNER JOIN participantes p ON p.id = c.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE c.saldo > 0
              AND c.estado IN ('pendiente','parcial','vencido')
            ORDER BY c.fecha_vencimiento ASC, c.id ASC
            LIMIT 10
        ");

        $ultimosPagos = self::fetchAll($db, "
            SELECT
                p.id,
                p.participante_id,
                p.fecha_pago,
                p.importe,
                p.medio_pago,
                p.estado,
                u.nombre,
                u.apellido
            FROM pagos p
            INNER JOIN participantes pa ON pa.id = p.participante_id
            INNER JOIN usuarios u ON u.id = pa.usuario_id
            ORDER BY p.fecha_pago DESC, p.id DESC
            LIMIT 10
        ");

        $pendientesValidacion = self::fetchAll($db, "
            SELECT
                cp.id,
                cp.pago_id,
                cp.archivo_nombre,
                cp.archivo_ruta,
                cp.estado,
                cp.fecha_alta,
                p.id AS participante_id,
                u.nombre,
                u.apellido
            FROM comprobantes_pago cp
            INNER JOIN pagos pg ON pg.id = cp.pago_id
            INNER JOIN participantes p ON p.id = pg.participante_id
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE cp.estado = 'subido'
            ORDER BY cp.fecha_alta DESC, cp.id DESC
            LIMIT 10
        ");

        Response::success([
            'fecha' => $hoy,
            'kpis' => [
                'participantes_activos' => (int)$participantesActivos,
                'profesores_activos' => (int)$profesoresActivos,
                'grupos_activos' => (int)$gruposActivos,
                'clases_hoy' => (int)$clasesHoy,
                'asistencias_hoy' => (int)$asistenciasHoy,
                'cargos_pendientes' => (int)$cargosPendientes,
                'saldo_pendiente' => (float)$saldoPendiente,
                'pagos_mes' => (float)$pagosMes,
                'comprobantes_pendientes' => (int)$comprobantesPendientes,
                'recordatorios_pendientes' => (int)$recordatoriosPendientes
            ],
            'proximos_vencimientos' => $vencimientos,
            'ultimos_pagos' => $ultimosPagos,
            'comprobantes_pendientes' => $pendientesValidacion
        ], 'Dashboard');
    }

    private static function scalar(PDO $db, string $sql, array $params = [])
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private static function fetchAll(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}