<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

$expectedConfirm = 'grupo_horarios_20260508';
$confirm = $_GET['confirm'] ?? '';

if ($confirm !== $expectedConfirm) {
    http_response_code(400);
    echo "Migracion no ejecutada.\n";
    echo "Volve a abrir este archivo con ?confirm={$expectedConfirm}\n";
    exit;
}

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS grupo_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    dia_semana ENUM('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_grupo_horarios_grupo_id (grupo_id),
    CONSTRAINT uq_grupo_horario
        UNIQUE KEY (grupo_id, dia_semana, hora_inicio, hora_fin)
);

INSERT INTO grupo_horarios (grupo_id, dia_semana, hora_inicio, hora_fin)
SELECT g.id, g.dia_semana, g.hora_inicio, g.hora_fin
FROM grupos g
LEFT JOIN grupo_horarios gh
    ON gh.grupo_id = g.id
   AND gh.dia_semana = g.dia_semana
   AND gh.hora_inicio = g.hora_inicio
   AND gh.hora_fin = g.hora_fin
WHERE g.dia_semana IS NOT NULL
  AND g.hora_inicio IS NOT NULL
  AND g.hora_fin IS NOT NULL
  AND gh.id IS NULL;
SQL;

try {
    $db = DB::get();
    $db->exec($sql);

    $count = (int)$db->query("SELECT COUNT(*) AS total FROM grupo_horarios")->fetchColumn();

    echo "Migracion ejecutada correctamente.\n";
    echo "Registros en grupo_horarios: {$count}\n";
    echo "Elimina este archivo del hosting cuando termines.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error al ejecutar la migracion.\n";
    echo $e->getMessage() . "\n";
}
