<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ComprobantesPagoController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $pago_id = Request::query('pago_id');
        $estado = Request::query('estado');

        $sql = "
            SELECT
                c.id,
                c.pago_id,
                c.tipo_archivo,
                c.archivo_nombre,
                c.archivo_ruta,
                c.archivo_mime,
                c.archivo_tamano,
                c.estado,
                c.validado_por,
                c.fecha_validacion,
                c.observaciones,
                c.fecha_alta
            FROM comprobantes_pago c
            WHERE 1=1
        ";
        $params = [];

        if ($pago_id) {
            $sql .= " AND c.pago_id = :pago_id";
            $params['pago_id'] = $pago_id;
        }

        if ($estado) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY c.fecha_alta DESC, c.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success($stmt->fetchAll(), 'Listado de comprobantes');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $stmt = $db->prepare("
            SELECT *
            FROM comprobantes_pago
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Comprobante no encontrado', 404);
        }

        Response::success($row, 'Comprobante encontrado');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $pago_id = (int)($data['pago_id'] ?? 0);
        $tipo_archivo = $data['tipo_archivo'] ?? 'imagen';
        $archivo_nombre = trim($data['archivo_nombre'] ?? '');
        $archivo_ruta = trim($data['archivo_ruta'] ?? '');
        $archivo_mime = trim($data['archivo_mime'] ?? '');
        $archivo_tamano = isset($data['archivo_tamano']) && $data['archivo_tamano'] !== '' ? (int)$data['archivo_tamano'] : null;
        $estado = $data['estado'] ?? 'subido';
        $observaciones = $data['observaciones'] ?? null;

        if ($pago_id <= 0 || $archivo_nombre === '' || $archivo_ruta === '') {
            Response::error('pago_id, archivo_nombre y archivo_ruta son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO comprobantes_pago (
                    pago_id, tipo_archivo, archivo_nombre, archivo_ruta,
                    archivo_mime, archivo_tamano, estado, observaciones
                ) VALUES (
                    :pago_id, :tipo_archivo, :archivo_nombre, :archivo_ruta,
                    :archivo_mime, :archivo_tamano, :estado, :observaciones
                )
            ");
            $stmt->execute([
                'pago_id' => $pago_id,
                'tipo_archivo' => $tipo_archivo,
                'archivo_nombre' => $archivo_nombre,
                'archivo_ruta' => $archivo_ruta,
                'archivo_mime' => $archivo_mime !== '' ? $archivo_mime : null,
                'archivo_tamano' => $archivo_tamano,
                'estado' => $estado,
                'observaciones' => $observaciones
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Comprobante registrado', 201);
        } catch (PDOException $e) {
            Response::error('Error al registrar comprobante', 500, $e->getMessage());
        }
    }

    public static function validar($id): void
    {
        $user = requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $data = Request::body();

        $estado = $data['estado'] ?? 'validado';
        $observaciones = $data['observaciones'] ?? null;

        if (!in_array($estado, ['validado', 'rechazado'], true)) {
            Response::error('Estado inválido', 422);
        }

        try {
            $stmt = $db->prepare("
                UPDATE comprobantes_pago
                SET estado = :estado,
                    validado_por = :validado_por,
                    fecha_validacion = NOW(),
                    observaciones = :observaciones
                WHERE id = :id
            ");
            $stmt->execute([
                'estado' => $estado,
                'validado_por' => $user['id'],
                'observaciones' => $observaciones,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Comprobante no encontrado o sin cambios', 404);
            }

            Response::success([], 'Comprobante actualizado');
        } catch (PDOException $e) {
            Response::error('Error al validar comprobante', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM comprobantes_pago WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Comprobante no encontrado', 404);
            }

            Response::success([], 'Comprobante eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar comprobante', 500, $e->getMessage());
        }
    }
}