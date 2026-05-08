<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

class ActividadesController
{
    public static function index(): void
    {
        requireAuth();

        $db = DB::get();
        $stmt = $db->query("
            SELECT id, nombre, descripcion, color, estado, fecha_alta, fecha_actualizacion
            FROM actividades
            ORDER BY nombre
        ");

        Response::success($stmt->fetchAll(), 'Listado de actividades');
    }

    public static function store(): void
    {
        requireAuth(['admin', 'profesora']);

        $db = DB::get();
        $data = Request::body();

        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $color = trim($data['color'] ?? '');
        $estado = $data['estado'] ?? 'activa';

        if ($nombre === '') {
            Response::error('El nombre es obligatorio', 422);
        }

        $stmt = $db->prepare("
            INSERT INTO actividades (nombre, descripcion, color, estado)
            VALUES (:nombre, :descripcion, :color, :estado)
        ");

        try {
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'color' => $color ?: null,
                'estado' => $estado
            ]);

            Response::success([
                'id' => (int)$db->lastInsertId()
            ], 'Actividad creada', 201);

        } catch (PDOException $e) {
            Response::error('Error al crear actividad', 500, $e->getMessage());
        }
    }
    public static function show($id): void
    {
        requireAuth();
    
        $db = DB::get();
    
        $stmt = $db->prepare("
            SELECT id, nombre, descripcion, color, estado, fecha_alta, fecha_actualizacion
            FROM actividades
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
    
        if (!$row) {
            Response::error('Actividad no encontrada', 404);
        }
    
        Response::success($row, 'Actividad encontrada');
    }
    
    public static function update($id): void
    {
        requireAuth(['admin', 'profesor']);
    
        $db = DB::get();
        $data = Request::body();
    
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = $data['descripcion'] ?? null;
        $color = trim($data['color'] ?? '');
        $estado = $data['estado'] ?? 'activa';
    
        if ($nombre === '') {
            Response::error('El nombre es obligatorio', 422);
        }
    
        $stmt = $db->prepare("
            UPDATE actividades
            SET nombre = :nombre,
                descripcion = :descripcion,
                color = :color,
                estado = :estado
            WHERE id = :id
        ");
    
        try {
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'color' => $color ?: null,
                'estado' => $estado,
                'id' => $id
            ]);
    
            if ($stmt->rowCount() === 0) {
                Response::error('Actividad no encontrada o sin cambios', 404);
            }
    
            Response::success([], 'Actividad actualizada');
        } catch (PDOException $e) {
            Response::error('Error al actualizar actividad', 500, $e->getMessage());
        }
    }
    
    public static function delete($id): void
    {
        requireAuth(['admin']);
    
        $db = DB::get();
    
        $stmt = $db->prepare("DELETE FROM actividades WHERE id = :id");
    
        try {
            $stmt->execute(['id' => $id]);
    
            if ($stmt->rowCount() === 0) {
                Response::error('Actividad no encontrada', 404);
            }
    
            Response::success([], 'Actividad eliminada');
        } catch (PDOException $e) {
            Response::error('Error al eliminar actividad', 500, $e->getMessage());
        }
    }
}