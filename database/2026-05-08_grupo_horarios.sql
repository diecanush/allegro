CREATE TABLE IF NOT EXISTS grupo_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    dia_semana ENUM('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_grupo_horarios_grupo
        FOREIGN KEY (grupo_id) REFERENCES grupos(id)
        ON DELETE CASCADE,
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
