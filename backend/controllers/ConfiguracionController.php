<?php
require_once __DIR__ . '/../models/Configuracion.php';

class ConfiguracionController
{
    private Configuracion $configuracionModel;

    public function __construct(?Configuracion $configuracionModel = null)
    {
        $this->configuracionModel = $configuracionModel ?? new Configuracion();
    }

    public function obtener(): array
    {
        return ['success' => true, 'data' => $this->configuracionModel->obtener()];
    }

    public function actualizar(array $datos): array
    {
        $tarifaPensionado = $this->validarTarifa($datos['tarifa_pensionado'] ?? null);
        $tarifaEsporadico = $this->validarTarifa($datos['tarifa_esporadico'] ?? null);
        $minimo = filter_var($datos['minimo_almuerzos_mes'] ?? null, FILTER_VALIDATE_INT);
        $hora = $datos['hora_limite'] ?? '';

        if ($tarifaPensionado === null || $tarifaEsporadico === null) {
            return ['success' => false, 'message' => 'Las tarifas deben ser números entre Bs 0 y Bs 999999,99.'];
        }
        if ($minimo === false || $minimo < 0 || $minimo > 31) {
            return ['success' => false, 'message' => 'El mínimo mensual debe estar entre 0 y 31 almuerzos.'];
        }
        if (!is_string($hora) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            return ['success' => false, 'message' => 'La hora límite debe tener un formato válido de 24 horas.'];
        }

        $this->configuracionModel->guardar([
            'tarifa_pensionado' => $tarifaPensionado,
            'tarifa_esporadico' => $tarifaEsporadico,
            'minimo_almuerzos_mes' => $minimo,
            'hora_limite' => $hora,
        ]);

        return ['success' => true, 'message' => 'Parámetros actualizados y aplicados al sistema.'];
    }

    private function validarTarifa(mixed $valor): ?float
    {
        if (!is_numeric($valor)) {
            return null;
        }
        $tarifa = round((float) $valor, 2);
        return $tarifa >= 0 && $tarifa <= 999999.99 ? $tarifa : null;
    }
}
