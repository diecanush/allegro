<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class MiCuentaController
{
    public static function perfil(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.usuario_id,
                p.dni,
                p.fecha_nacimiento,
                p.direccion,
                p.contacto_emergencia_nombre,
                p.contacto_emergencia_telefono,
                p.observaciones_medicas,
                p.apto_fisico_vencimiento,
                p.observaciones,
                p.estado,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono
            FROM participantes p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $participante['id']]);
        $row = $stmt->fetch();

        Response::success($row, 'Mi perfil');
    }

    public static function misGrupos(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);

        $stmt = $db->prepare("
            SELECT
                i.id AS inscripcion_id,
                i.fecha_inscripcion,
                i.fecha_baja,
                i.estado AS inscripcion_estado,
                g.id AS grupo_id,
                g.nombre AS grupo_nombre,
                g.dia_semana,
                g.hora_inicio,
                g.hora_fin,
                g.estado AS grupo_estado,
                a.id AS actividad_id,
                a.nombre AS actividad_nombre,
                s.id AS sede_id,
                s.nombre AS sede_nombre,
                s.direccion AS sede_direccion,
                pr.id AS profesor_id,
                CONCAT(u2.nombre, ' ', u2.apellido) AS profesor_nombre
            FROM inscripciones i
            INNER JOIN grupos g ON g.id = i.grupo_id
            INNER JOIN actividades a ON a.id = g.actividad_id
            LEFT JOIN sedes s ON s.id = g.sede_id
            INNER JOIN profesores pr ON pr.id = g.profesor_id
            INNER JOIN usuarios u2 ON u2.id = pr.usuario_id
            WHERE i.participante_id = :participante_id
            ORDER BY FIELD(g.dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), g.hora_inicio
        ");
        $stmt->execute(['participante_id' => $participante['id']]);

        Response::success($stmt->fetchAll(), 'Mis grupos');
    }

    public static function misAsistencias(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);
        $grupo_id = Request::query('grupo_id');

        $sql = "
            SELECT
                a.id,
                a.estado,
                a.hora_registro,
                a.observaciones,
                c.id AS clase_id,
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.estado AS clase_estado,
                g.id AS grupo_id,
                g.nombre AS grupo_nombre,
                act.nombre AS actividad_nombre
            FROM asistencias a
            INNER JOIN clases c ON c.id = a.clase_id
            INNER JOIN grupos g ON g.id = c.grupo_id
            INNER JOIN actividades act ON act.id = g.actividad_id
            WHERE a.participante_id = :participante_id
        ";

        $params = ['participante_id' => $participante['id']];

        if ($grupo_id) {
            $sql .= " AND g.id = :grupo_id";
            $params['grupo_id'] = $grupo_id;
        }

        $sql .= " ORDER BY c.fecha DESC, c.hora_inicio DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Mis asistencias');
    }

    public static function miEstadoCuenta(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);

        $stmtCargos = $db->prepare("
            SELECT
                id, concepto, periodo, fecha_generacion, fecha_vencimiento,
                importe, recargo, bonificacion, saldo, estado, observaciones
            FROM cargos
            WHERE participante_id = :participante_id
            ORDER BY fecha_vencimiento DESC, id DESC
        ");
        $stmtCargos->execute(['participante_id' => $participante['id']]);
        $cargos = $stmtCargos->fetchAll();

        $stmtPagos = $db->prepare("
            SELECT
                id, fecha_pago, importe, medio_pago, referencia, estado, observaciones
            FROM pagos
            WHERE participante_id = :participante_id
            ORDER BY fecha_pago DESC, id DESC
        ");
        $stmtPagos->execute(['participante_id' => $participante['id']]);
        $pagos = $stmtPagos->fetchAll();

        $stmtTot = $db->prepare("
            SELECT
                COALESCE(SUM(importe + recargo - bonificacion),0) AS total_cargado,
                COALESCE(SUM(saldo),0) AS saldo_pendiente
            FROM cargos
            WHERE participante_id = :participante_id
        ");
        $stmtTot->execute(['participante_id' => $participante['id']]);
        $totales = $stmtTot->fetch();

        $stmtPagTot = $db->prepare("
            SELECT COALESCE(SUM(importe),0) AS total_pagado
            FROM pagos
            WHERE participante_id = :participante_id
              AND estado IN ('aprobado','pendiente_validacion')
        ");
        $stmtPagTot->execute(['participante_id' => $participante['id']]);
        $pagosTot = $stmtPagTot->fetch();

        Response::success([
            'totales' => [
                'total_cargado' => (float)$totales['total_cargado'],
                'saldo_pendiente' => (float)$totales['saldo_pendiente'],
                'total_pagado' => (float)$pagosTot['total_pagado']
            ],
            'cargos' => $cargos,
            'pagos' => $pagos
        ], 'Mi estado de cuenta');
    }

    public static function misPagos(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.fecha_pago,
                p.importe,
                p.medio_pago,
                p.referencia,
                p.estado,
                p.observaciones
            FROM pagos p
            WHERE p.participante_id = :participante_id
            ORDER BY p.fecha_pago DESC, p.id DESC
        ");
        $stmt->execute(['participante_id' => $participante['id']]);
        $pagos = $stmt->fetchAll();

        Response::success($pagos, 'Mis pagos');
    }

    public static function misComprobantes(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();

        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);

        $stmt = $db->prepare("
            SELECT
                cp.id,
                cp.pago_id,
                cp.tipo_archivo,
                cp.archivo_nombre,
                cp.archivo_ruta,
                cp.archivo_mime,
                cp.archivo_tamano,
                cp.estado,
                cp.fecha_validacion,
                cp.observaciones,
                cp.fecha_alta,
                p.fecha_pago,
                p.importe
            FROM comprobantes_pago cp
            INNER JOIN pagos p ON p.id = cp.pago_id
            WHERE p.participante_id = :participante_id
            ORDER BY cp.fecha_alta DESC, cp.id DESC
        ");
        $stmt->execute(['participante_id' => $participante['id']]);

        Response::success($stmt->fetchAll(), 'Mis comprobantes');
    }

    public static function subirComprobante(): void
    {
        $user = requireAuth(['participante']);
        $db = DB::get();
        $participante = self::getParticipanteByUsuarioId($db, (int)$user['id']);
        $data = Request::body();

        $pago_id = (int)($data['pago_id'] ?? 0);
        $tipo_archivo = $data['tipo_archivo'] ?? 'imagen';
        $archivo_nombre = trim($data['archivo_nombre'] ?? '');
        $archivo_ruta = trim($data['archivo_ruta'] ?? '');
        $archivo_mime = trim($data['archivo_mime'] ?? '');
        $archivo_tamano = isset($data['archivo_tamano']) && $data['archivo_tamano'] !== '' ? (int)$data['archivo_tamano'] : null;
        $observaciones = $data['observaciones'] ?? null;

        if ($pago_id <= 0 || $archivo_nombre === '' || $archivo_ruta === '') {
            Response::error('pago_id, archivo_nombre y archivo_ruta son obligatorios', 422, $data);
        }

        $stmtPago = $db->prepare("
            SELECT id
            FROM pagos
            WHERE id = :pago_id
              AND participante_id = :participante_id
            LIMIT 1
        ");
        $stmtPago->execute([
            'pago_id' => $pago_id,
            'participante_id' => $participante['id']
        ]);
        $pago = $stmtPago->fetch();

        if (!$pago) {
            Response::error('No podés adjuntar comprobantes a ese pago', 403);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO comprobantes_pago (
                    pago_id, tipo_archivo, archivo_nombre, archivo_ruta,
                    archivo_mime, archivo_tamano, estado, observaciones
                ) VALUES (
                    :pago_id, :tipo_archivo, :archivo_nombre, :archivo_ruta,
                    :archivo_mime, :archivo_tamano, 'subido', :observaciones
                )
            ");
            $stmt->execute([
                'pago_id' => $pago_id,
                'tipo_archivo' => $tipo_archivo,
                'archivo_nombre' => $archivo_nombre,
                'archivo_ruta' => $archivo_ruta,
                'archivo_mime' => $archivo_mime !== '' ? $archivo_mime : null,
                'archivo_tamano' => $archivo_tamano,
                'observaciones' => $observaciones
            ]);

            Response::success([
                'id' => (int)$db->lastInsertId()
            ], 'Comprobante enviado', 201);
        } catch (PDOException $e) {
            Response::error('Error al enviar comprobante', 500, $e->getMessage());
        }
    }

    private static function getParticipanteByUsuarioId(PDO $db, int $usuarioId): array
    {
        $stmt = $db->prepare("
            SELECT id, usuario_id, estado
            FROM participantes
            WHERE usuario_id = :usuario_id
            LIMIT 1
        ");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $participante = $stmt->fetch();

        if (!$participante) {
            Response::error('No existe un perfil de participante asociado a este usuario', 404);
        }

        return $participante;
    }
}