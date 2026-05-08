<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class PlanesController
{
    public static function index(): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $stmt = $db->query("
            SELECT
                id, nombre, descripcion, importe, periodo,
                cantidad_clases, mora_diaria, estado,
                fecha_alta, fecha_actualizacion
            FROM planes
            ORDER BY nombre
        ");

        Response::success($stmt->fetchAll(), 'Listado de planes');
    }

    public static function show($id): void
    {
        requireAuth(['admin', 'profesor']);

        $db = DB::get();
        $stmt = $db->prepare("
            SELECT
                id, nombre, descripcion, importe, periodo,
                cantidad_clases, mora_diaria, estado,
                fecha_alta, fecha_actualizacion
            FROM planes
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Plan no encontrado', 404);
        }

        Response::success($row, 'Plan encontrado');
    }

    public static function store(): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $importe = (float)($data['importe'] ?? 0);
        $periodo = $data['periodo'] ?? 'mensual';
        $cantidad_clases = isset($data['cantidad_clases']) && $data['cantidad_clases'] !== '' ? (int)$data['cantidad_clases'] : null;
        $mora_diaria = (float)($data['mora_diaria'] ?? 0);
        $estado = $data['estado'] ?? 'activo';

        if ($nombre === '' || $importe < 0) {
            Response::error('Nombre e importe válidos son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO planes (
                    nombre, descripcion, importe, periodo,
                    cantidad_clases, mora_diaria, estado
                ) VALUES (
                    :nombre, :descripcion, :importe, :periodo,
                    :cantidad_clases, :mora_diaria, :estado
                )
            ");

            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'importe' => $importe,
                'periodo' => $periodo,
                'cantidad_clases' => $cantidad_clases,
                'mora_diaria' => $mora_diaria,
                'estado' => $estado
            ]);

            Response::success(['id' => (int)$db->lastInsertId()], 'Plan creado', 201);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                Response::error('Ya existe un plan con ese nombre', 422, $e->getMessage());
            }
            Response::error('Error al crear plan', 500, $e->getMessage());
        }
    }

    public static function update($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();
        $data = Request::body();

        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $importe = (float)($data['importe'] ?? 0);
        $periodo = $data['periodo'] ?? 'mensual';
        $cantidad_clases = isset($data['cantidad_clases']) && $data['cantidad_clases'] !== '' ? (int)$data['cantidad_clases'] : null;
        $mora_diaria = (float)($data['mora_diaria'] ?? 0);
        $estado = $data['estado'] ?? 'activo';

        if ($nombre === '' || $importe < 0) {
            Response::error('Nombre e importe válidos son obligatorios', 422, $data);
        }

        try {
            $stmt = $db->prepare("
                UPDATE planes
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    importe = :importe,
                    periodo = :periodo,
                    cantidad_clases = :cantidad_clases,
                    mora_diaria = :mora_diaria,
                    estado = :estado
                WHERE id = :id
            ");

            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'importe' => $importe,
                'periodo' => $periodo,
                'cantidad_clases' => $cantidad_clases,
                'mora_diaria' => $mora_diaria,
                'estado' => $estado,
                'id' => $id
            ]);

            if ($stmt->rowCount() === 0) {
                Response::error('Plan no encontrado o sin cambios', 404);
            }

            Response::success([], 'Plan actualizado');
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                Response::error('Ya existe un plan con ese nombre', 422, $e->getMessage());
            }
            Response::error('Error al actualizar plan', 500, $e->getMessage());
        }
    }

    public static function delete($id): void
    {
        requireAuth(['admin']);

        $db = DB::get();

        try {
            $stmt = $db->prepare("DELETE FROM planes WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Plan no encontrado', 404);
            }

            Response::success([], 'Plan eliminado');
        } catch (PDOException $e) {
            Response::error('Error al eliminar plan', 500, $e->getMessage());
        }
    }
}