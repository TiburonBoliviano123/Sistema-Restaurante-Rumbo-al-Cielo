<?php
require_once __DIR__ . '/../config/database.php';

class Configuracion
{
    private ?PDO $db;
    private ?array $cache = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    public function obtener(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $fila = $this->conexion()->query(
            'SELECT tarifa_pensionado, tarifa_esporadico, minimo_almuerzos_mes, hora_limite
             FROM configuracion_sistema WHERE id = 1'
        )->fetch();

        $this->cache = [
            'tarifa_pensionado' => (float) ($fila['tarifa_pensionado'] ?? TARIFA_PENSIONADO),
            'tarifa_esporadico' => (float) ($fila['tarifa_esporadico'] ?? TARIFA_NO_PENSIONADO),
            'minimo_almuerzos_mes' => (int) ($fila['minimo_almuerzos_mes'] ?? MINIMO_ALMUERZOS_MES),
            'hora_limite' => substr((string) ($fila['hora_limite'] ?? HORA_LIMITE_CANCELACION), 0, 5),
        ];
        return $this->cache;
    }

    public function guardar(array $configuracion): void
    {
        $stmt = $this->conexion()->prepare(
            'INSERT INTO configuracion_sistema
                (id, tarifa_pensionado, tarifa_esporadico, minimo_almuerzos_mes, hora_limite)
             VALUES (1, :tarifa_pensionado, :tarifa_esporadico, :minimo, :hora)
             ON DUPLICATE KEY UPDATE
                tarifa_pensionado = VALUES(tarifa_pensionado),
                tarifa_esporadico = VALUES(tarifa_esporadico),
                minimo_almuerzos_mes = VALUES(minimo_almuerzos_mes),
                hora_limite = VALUES(hora_limite)'
        );
        $stmt->execute([
            ':tarifa_pensionado' => $configuracion['tarifa_pensionado'],
            ':tarifa_esporadico' => $configuracion['tarifa_esporadico'],
            ':minimo' => $configuracion['minimo_almuerzos_mes'],
            ':hora' => $configuracion['hora_limite'] . ':00',
        ]);

        $this->cache = $configuracion;
    }

    private function conexion(): PDO
    {
        return $this->db ?? Database::getConnection();
    }
}
