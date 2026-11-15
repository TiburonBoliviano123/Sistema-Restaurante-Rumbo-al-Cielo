<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Reporte.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../models/Configuracion.php';

class ReporteController {
    private Reporte $reporteModel;
    private Reserva $reservaModel;
    private \Closure $nowProvider;
    private Configuracion $configuracionModel;

    public function __construct(
        ?Reporte $reporteModel = null,
        ?Reserva $reservaModel = null,
        ?\Closure $nowProvider = null,
        ?Configuracion $configuracionModel = null
    ) {
        $this->reporteModel = $reporteModel ?? new Reporte();
        $this->reservaModel = $reservaModel ?? new Reserva();
        $this->nowProvider = $nowProvider ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now');
        $this->configuracionModel = $configuracionModel ?? new Configuracion();
    }

    /**
     * Genera y persiste el reporte consolidado mensual.
     * Calcula tarifas, penalizaciones y monto total a descontar en planilla.
     *
     * Reglas:
     *  - Aplica la tarifa pensionada y el mínimo configurados para el sistema.
     *  - La tarifa esporádica se aplica a consumos fuera de la afiliación mensual.
     *  - Todo cargo de familiares va al código del titular.
     */
    public function generarReporteMensual(int $anio, int $mes): array {
        $ahora = ($this->nowProvider)();
        $reglas = $this->configuracionModel->obtener();
        $this->reservaModel->actualizarEstadosAutomaticos(
            $ahora->format('Y-m-d'),
            $ahora->format('H:i'),
            $reglas['hora_limite']
        );
        $filas = [];

        foreach ($this->reporteModel->datosParaReporteMensual($anio, $mes) as $titular) {
            $almTitular = (int) $titular['almuerzos_titular'];
            $almTitularPension = (int) $titular['almuerzos_pensionados'];
            $almTitularEsporadico = (int) $titular['almuerzos_esporadicos'];
            $montoTitular = (!empty($titular['es_pensionado'])
                ? $this->calcularMontoPensionado($almTitularPension, $reglas)
                : $almTitularPension * $reglas['tarifa_pensionado'])
                + $almTitularEsporadico * $reglas['tarifa_esporadico'];

            $detalleFamiliares = [];
            $totalAlmFamiliares = 0;
            $totalPenFamiliares = 0.00;
            foreach ($titular['familiares'] as $fam) {
                $almFam = (int) $fam['almuerzos'];
                $almFamPension = (int) $fam['almuerzos_pensionados'];
                $almFamEsporadico = (int) $fam['almuerzos_esporadicos'];
                $montoFam = $almFamPension * $reglas['tarifa_pensionado']
                    + $almFamEsporadico * $reglas['tarifa_esporadico'];
                $pen = !empty($fam['es_pensionado'])
                    ? max(0, $reglas['minimo_almuerzos_mes'] - $almFamPension) * $reglas['tarifa_pensionado']
                    : 0.00;

                $detalleFamiliares[] = [
                    'nombre' => $fam['nombre'],
                    'almuerzos' => $almFam,
                    'monto' => $montoFam,
                    'penalizacion' => $pen,
                    'total_familiar' => $montoFam + $pen,
                ];
                $totalAlmFamiliares += $almFam;
                $totalPenFamiliares += $pen;
            }

            $totalDescuento = $montoTitular
                + array_sum(array_column($detalleFamiliares, 'monto'))
                + $totalPenFamiliares;
            $fila = [
                'anio' => $anio,
                'mes' => $mes,
                'codigo_empleado' => $titular['codigo_empleado'] ?? 'SIN_CODIGO',
                'nombre_empleado' => $titular['nombre'],
                'almuerzos_titular' => $almTitular,
                'monto_titular' => $montoTitular,
                'detalle_familiares' => $detalleFamiliares,
                'almuerzos_familiares' => $totalAlmFamiliares,
                'penalizacion_familiares' => $totalPenFamiliares,
                'total_descuento' => $totalDescuento,
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
    private function calcularMontoPensionado(int $almuerzos, array $reglas): float {
        if ($almuerzos >= $reglas['minimo_almuerzos_mes']) {
            return $almuerzos * $reglas['tarifa_pensionado'];
        }
        return $reglas['minimo_almuerzos_mes'] * $reglas['tarifa_pensionado'];
    }
}
