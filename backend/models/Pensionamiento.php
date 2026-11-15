<?php
require_once __DIR__ . '/../config/database.php';

class Pensionamiento {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** Registers one beneficiary for a month and creates the weekday reservations atomically. */
    public function registrar(
        int $titularId,
        ?int $familiarId,
        int $anio,
        int $mes,
        int $registradoPorId
    ): array {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        } else {
            $this->db->exec('SAVEPOINT pensionamiento_registro');
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO pensionamientos_mensuales
                    (usuario_id, familiar_id, anio, mes, registrado_por_id)
                 VALUES (:usuario_id, :familiar_id, :anio, :mes, :registrado_por_id)'
            );
            $stmt->execute([
                ':usuario_id' => $titularId,
                ':familiar_id' => $familiarId,
                ':anio' => $anio,
                ':mes' => $mes,
                ':registrado_por_id' => $registradoPorId,
            ]);

            $reservasCreadas = $this->crearReservasDelMes($titularId, $familiarId, $anio, $mes);
            if ($ownsTransaction) {
                $this->db->commit();
            } else {
                $this->db->exec('RELEASE SAVEPOINT pensionamiento_registro');
            }

            return [
                'success' => true,
                'reservas_creadas' => $reservasCreadas,
                'message' => 'Pensionamiento registrado y reservas del mes confirmadas.',
            ];
        } catch (PDOException $exception) {
            $this->rollback($ownsTransaction);
            if ($this->esDuplicado($exception)) {
                return ['success' => false, 'message' => 'Este beneficiario ya está pensionado para ese mes.'];
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->rollback($ownsTransaction);
            throw $exception;
        }
    }

    public function estaRegistrado(int $titularId, ?int $familiarId, int $anio, int $mes): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM pensionamientos_mensuales
             WHERE usuario_id = :usuario_id AND familiar_id <=> :familiar_id
               AND anio = :anio AND mes = :mes'
        );
        $stmt->execute([
            ':usuario_id' => $titularId,
            ':familiar_id' => $familiarId,
            ':anio' => $anio,
            ':mes' => $mes,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarPorTitular(int $titularId, int $anio, int $mes): array {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.familiar_id, p.anio, p.mes, p.created_at,
                    IF(p.familiar_id IS NULL, 'TITULAR', 'FAMILIAR') AS tipo_beneficiario,
                    IF(p.familiar_id IS NULL,
                       CONCAT_WS(' ', NULLIF(u.nombre, ''), NULLIF(u.apellido, '')),
                       f.nombre) AS nombre_beneficiario
             FROM pensionamientos_mensuales p
             JOIN usuarios u ON u.id = p.usuario_id
             LEFT JOIN familiares f ON f.id = p.familiar_id
             WHERE p.usuario_id = :titular_id AND p.anio = :anio AND p.mes = :mes
             ORDER BY tipo_beneficiario, nombre_beneficiario"
        );
        $stmt->execute([':titular_id' => $titularId, ':anio' => $anio, ':mes' => $mes]);
        return $stmt->fetchAll();
    }

    private function crearReservasDelMes(int $titularId, ?int $familiarId, int $anio, int $mes): int {
        $inicio = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $fin = $inicio->modify('last day of this month');
        $stmtFeriados = $this->db->prepare(
            'SELECT fecha FROM calendario_operativo
             WHERE fecha BETWEEN :inicio AND :fin AND es_feriado = 1'
        );
        $stmtFeriados->execute([':inicio' => $inicio->format('Y-m-d'), ':fin' => $fin->format('Y-m-d')]);
        $feriados = array_fill_keys($stmtFeriados->fetchAll(PDO::FETCH_COLUMN), true);

        $crearTitular = $this->db->prepare(
            "INSERT IGNORE INTO reservas
                (usuario_id, familiar_id, tipo_beneficiario, fecha_reserva, estado, modalidad)
             VALUES (:usuario_id, NULL, 'TITULAR', :fecha, 'RESERVADO', 'PENSIONADO')"
        );
        $crearFamiliar = $this->db->prepare(
            "INSERT IGNORE INTO reservas
                (usuario_id, familiar_id, tipo_beneficiario, fecha_reserva, estado, modalidad)
             VALUES (:usuario_id, :familiar_id, 'FAMILIAR', :fecha, 'RESERVADO', 'PENSIONADO')"
        );
        $reservasCreadas = 0;

        for ($dia = $inicio; $dia <= $fin; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            if ((int) $dia->format('N') > 4 || isset($feriados[$fecha])) {
                continue;
            }

            if ($familiarId === null) {
                $crearTitular->execute([':usuario_id' => $titularId, ':fecha' => $fecha]);
            } else {
                $crearFamiliar->execute([
                    ':usuario_id' => $titularId,
                    ':familiar_id' => $familiarId,
                    ':fecha' => $fecha,
                ]);
            }
            $reservasCreadas += ($familiarId === null ? $crearTitular : $crearFamiliar)->rowCount();
        }

        $params = [
            ':inicio' => $inicio->format('Y-m-d'),
            ':fin' => $fin->format('Y-m-d'),
        ];
        $beneficiario = "usuario_id = :usuario_id AND tipo_beneficiario = 'TITULAR'";
        if ($familiarId !== null) {
            $beneficiario = 'familiar_id = :familiar_id';
            $params[':familiar_id'] = $familiarId;
        } else {
            $params[':usuario_id'] = $titularId;
        }
        $actualizar = $this->db->prepare(
            "UPDATE reservas SET modalidad = 'PENSIONADO'
             WHERE $beneficiario AND fecha_reserva BETWEEN :inicio AND :fin AND estado = 'RESERVADO'"
        );
        $actualizar->execute($params);

        return $reservasCreadas;
    }

    private function rollback(bool $ownsTransaction): void {
        if ($ownsTransaction && $this->db->inTransaction()) {
            $this->db->rollBack();
        } elseif (!$ownsTransaction) {
            $this->db->exec('ROLLBACK TO SAVEPOINT pensionamiento_registro');
            $this->db->exec('RELEASE SAVEPOINT pensionamiento_registro');
        }
    }

    private function esDuplicado(PDOException $exception): bool {
        return $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
