<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Reporte.php';

class ReporteController {
    private Reporte $reporteModel;

    public function __construct() {
        $this->reporteModel = new Reporte();
    }

    private function parametro(string $clave, string $defecto): string {
        try { $db=Database::getConnection(); $q=$db->prepare('SELECT valor FROM configuracion_sistema WHERE clave=?'); $q->execute([$clave]); return (string)($q->fetchColumn() ?: $defecto); } catch (Throwable $e) { return $defecto; }
    }

    /**
     * Genera y persiste el reporte consolidado mensual.
     * Calcula tarifas, penalizaciones y monto total a descontar en planilla.
     *
     * Reglas:
     *  - PENSIONADO titular:   15 Bs x almuerzo, mínimo 10 → si < 10, se cobra igual 10×15
     *  - Familiar:             15 Bs x almuerzo, mínimo 10 → misma regla
     *  - NO_PENSIONADO:        18 Bs x almuerzo, sin mínimo
     *  - Todo cargo de familiares va al código del titular.
     */
    public function generarReporteMensual(int $anio, int $mes): array {
        $filas = [];

        // ── Pensionados ──────────────────────────────────────────────
        $pensionados = $this->reporteModel->datosParaReporteMensual($anio, $mes);

        foreach ($pensionados as $titular) {
            $almTitular   = (int) $titular['almuerzos_titular'];
            $minimo=(int)$this->parametro('minimo_pension',(string)MINIMO_ALMUERZOS_MES);
            $tarifaPension=(float)$this->parametro('tarifa_pensionado',(string)TARIFA_PENSIONADO);
            $tienePensionTitular = (int)($titular['almuerzos_pension'] ?? 0) > 0 || $this->reporteModel->tieneInscripcionPension((int)$titular['id'],0,$anio,$mes);
            $montoTitular = $tienePensionTitular
                ? max($minimo * $tarifaPension, (float)$titular['monto_reservas'])
                : (float)$titular['monto_reservas'];

            $detalleFamiliares   = [];
            $totalAlmFamiliares  = 0;
            $totalPenFamiliares  = 0.00;

            foreach ($titular['familiares'] as $fam) {
                $almFam   = (int) $fam['almuerzos'];
                $montoFam = (float)$fam['monto_reservas'];
                $tienePensionFamiliar = (int)($fam['almuerzos_pension'] ?? 0) > 0 || $this->reporteModel->tieneInscripcionPension((int)$titular['id'],(int)$fam['id'],$anio,$mes);
                $pen = $tienePensionFamiliar
                    ? max(0, ($minimo * $tarifaPension) - $montoFam)
                    : 0.00;

                $detalleFamiliares[] = [
                    'nombre'         => $fam['nombre'],
                    'almuerzos'      => $almFam,
                    'monto'          => $montoFam,
                    'penalizacion'   => $pen,
                    'total_familiar' => $montoFam + $pen,
                ];

                $totalAlmFamiliares += $almFam;
                $totalPenFamiliares += $pen;
            }

            $totalMontoFamiliares = array_sum(array_column($detalleFamiliares, 'monto'));
            $totalDescuento = $montoTitular + $totalMontoFamiliares + $totalPenFamiliares;

            $fila = [
                'anio'                    => $anio,
                'mes'                     => $mes,
                'codigo_empleado'         => $titular['codigo_empleado'] ?? 'SIN_CODIGO',
                'nombre_empleado'         => $titular['nombre'],
                'almuerzos_titular'       => $almTitular,
                'monto_titular'           => $montoTitular,
                'detalle_familiares'      => $detalleFamiliares,
                'almuerzos_familiares'    => $totalAlmFamiliares,
                'penalizacion_familiares' => $totalPenFamiliares,
                'total_descuento'         => $totalDescuento,
            ];

            $this->reporteModel->guardar($fila);
            $filas[] = $fila;
        }

        // ── No Pensionados (pago directo, sin mínimo obligatorio) ────
        $noPensionados = $this->reporteModel->datosNoPensionadosMensual($anio, $mes);

        foreach ($noPensionados as $np) {
            $alm   = (int) $np['almuerzos_titular'];
            $monto = (float)$np['monto_reservas'];

            $fila = [
                'anio'                    => $anio,
                'mes'                     => $mes,
                'codigo_empleado'         => $np['codigo_empleado'] ?? 'SIN_CODIGO',
                'nombre_empleado'         => $np['nombre'],
                'almuerzos_titular'       => $alm,
                'monto_titular'           => $monto,
                'detalle_familiares'      => [],
                'almuerzos_familiares'    => 0,
                'penalizacion_familiares' => 0.00,
                'total_descuento'         => $monto,
            ];

            $this->reporteModel->guardar($fila);
            $filas[] = $fila;
        }

        return [
            'success' => true,
            'message' => 'Reporte generado correctamente.',
            'total_empleados' => count($filas),
            'filas' => $filas,
        ];
    }

    /**
     * Obtiene el reporte ya generado de un mes.
     */
    public function obtenerReporte(int $anio, int $mes): array {
        return $this->reporteModel->listar($anio, $mes);
    }

    /**
     * Calcula el monto para un pensionado/familiar:
     * - Si consumió >= 10: consume × 15
     * - Si consumió < 10: IGUAL se cobra 10 × 15 = 150 (consumidos + faltantes)
     */
    private function calcularMontoPensionado(int $almuerzos): float {
        if ($almuerzos >= MINIMO_ALMUERZOS_MES) {
            return $almuerzos * TARIFA_PENSIONADO;
        }
        // Se cobra el mínimo obligatorio
        return MINIMO_ALMUERZOS_MES * TARIFA_PENSIONADO;
    }
}
