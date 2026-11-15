<?php
require_once __DIR__ . '/../config/database.php';

class Reporte {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene todos los titulares pensionados con sus consumos
     * para el cálculo mensual.
     */
    public function datosParaReporteMensual(int $anio, int $mes): array {
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin    = (new DateTime($inicio))->format('Y-m-t');

        // Titulares pensionados
        $stmt = $this->db->prepare(
            "SELECT u.id, u.nombre, u.codigo_empleado,
                    COUNT(r.id) AS almuerzos_titular, COALESCE(SUM(r.tarifa),0) AS monto_reservas, SUM(r.modalidad='PENSIONADO') AS almuerzos_pension
             FROM usuarios u
             LEFT JOIN reservas r ON r.usuario_id = u.id
               AND r.tipo_beneficiario = 'TITULAR'
               AND r.fecha_reserva BETWEEN :ini AND :fin
               AND r.estado IN ('RESERVADO','ENTREGADO','INASISTENCIA')
             WHERE u.tipo_cuenta = 'PENSIONADO' AND u.activo = 1
             GROUP BY u.id"
        );
        $stmt->execute([':ini' => $inicio, ':fin' => $fin]);
        $titulares = $stmt->fetchAll();

        foreach ($titulares as &$titular) {
            // Familiares del titular
            $stmtF = $this->db->prepare(
                "SELECT f.id, f.nombre,
                        COUNT(r.id) AS almuerzos, COALESCE(SUM(r.tarifa),0) AS monto_reservas, SUM(r.modalidad='PENSIONADO') AS almuerzos_pension
                 FROM familiares f
                 LEFT JOIN reservas r ON r.familiar_id = f.id
                   AND r.fecha_reserva BETWEEN :ini AND :fin
                   AND r.estado IN ('RESERVADO','ENTREGADO','INASISTENCIA')
                 WHERE f.empleado_titular_id = :tid
                 GROUP BY f.id"
            );
            $stmtF->execute([':ini' => $inicio, ':fin' => $fin, ':tid' => $titular['id']]);
            $titular['familiares'] = $stmtF->fetchAll();
        }
        unset($titular);

        return $titulares;
    }

    /**
     * Datos de titulares NO PENSIONADO para reporte (pago directo, sin mínimo).
     */
    public function datosNoPensionadosMensual(int $anio, int $mes): array {
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin    = (new DateTime($inicio))->format('Y-m-t');

        $stmt = $this->db->prepare(
            "SELECT u.id, u.nombre, u.codigo_empleado,
                    COUNT(r.id) AS almuerzos_titular, COALESCE(SUM(r.tarifa),0) AS monto_reservas, SUM(r.modalidad='PENSIONADO') AS almuerzos_pension
             FROM usuarios u
             LEFT JOIN reservas r ON r.usuario_id = u.id
               AND r.tipo_beneficiario = 'TITULAR'
               AND r.fecha_reserva BETWEEN :ini AND :fin
               AND r.estado IN ('RESERVADO','ENTREGADO','INASISTENCIA')
             WHERE u.tipo_cuenta = 'NO_PENSIONADO' AND u.activo = 1
             GROUP BY u.id"
        );
        $stmt->execute([':ini' => $inicio, ':fin' => $fin]);
        return $stmt->fetchAll();
    }


    public function tieneInscripcionPension(int $usuarioId, int $beneficiarioId, int $anio, int $mes): bool {
        $q=$this->db->prepare("SELECT COUNT(*) FROM inscripciones_pension WHERE usuario_id=? AND beneficiario_id=? AND anio=? AND mes=? AND estado='ACTIVA'");
        $q->execute([$usuarioId,$beneficiarioId,$anio,$mes]); return (int)$q->fetchColumn()>0;
    }

    /** Guarda el reporte consolidado en la tabla reportes_mensuales */
    public function guardar(array $fila): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO reportes_mensuales
               (anio, mes, codigo_empleado, nombre_empleado,
                almuerzos_titular, monto_titular,
                detalle_familiares, almuerzos_familiares,
                penalizacion_familiares, total_descuento)
             VALUES
               (:anio, :mes, :codigo, :nombre,
                :alm_t, :monto_t,
                :det_f, :alm_f, :pen_f, :total)
             ON DUPLICATE KEY UPDATE
               almuerzos_titular       = VALUES(almuerzos_titular),
               monto_titular           = VALUES(monto_titular),
               detalle_familiares      = VALUES(detalle_familiares),
               almuerzos_familiares    = VALUES(almuerzos_familiares),
               penalizacion_familiares = VALUES(penalizacion_familiares),
               total_descuento         = VALUES(total_descuento),
               generado_en             = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':anio'   => $fila['anio'],
            ':mes'    => $fila['mes'],
            ':codigo' => $fila['codigo_empleado'],
            ':nombre' => $fila['nombre_empleado'],
            ':alm_t'  => $fila['almuerzos_titular'],
            ':monto_t'=> $fila['monto_titular'],
            ':det_f'  => json_encode($fila['detalle_familiares'] ?? [], JSON_UNESCAPED_UNICODE),
            ':alm_f'  => $fila['almuerzos_familiares'],
            ':pen_f'  => $fila['penalizacion_familiares'],
            ':total'  => $fila['total_descuento'],
        ]);
        return true;
    }

    /** Lista los reportes generados */
    public function listar(int $anio, int $mes): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM reportes_mensuales
             WHERE anio = :anio AND mes = :mes
             ORDER BY nombre_empleado ASC'
        );
        $stmt->execute([':anio' => $anio, ':mes' => $mes]);
        return $stmt->fetchAll();
    }
}
