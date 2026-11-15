<?php
require_once __DIR__ . '/../models/Pensionamiento.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Familiar.php';

class PensionamientoController {
    private Pensionamiento $pensionamientoModel;
    private Usuario $usuarioModel;
    private Familiar $familiarModel;
    private Closure $nowProvider;

    public function __construct(
        ?Pensionamiento $pensionamientoModel = null,
        ?Usuario $usuarioModel = null,
        ?Familiar $familiarModel = null,
        ?Closure $nowProvider = null
    ) {
        $this->pensionamientoModel = $pensionamientoModel ?? new Pensionamiento();
        $this->usuarioModel = $usuarioModel ?? new Usuario();
        $this->familiarModel = $familiarModel ?? new Familiar();
        $this->nowProvider = $nowProvider ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public function registrarTitular(int $titularId, int $registradoPorId, int $anio, int $mes): array {
        $validacion = $this->validarPeriodo($anio, $mes);
        if ($validacion !== null) {
            return ['success' => false, 'message' => $validacion];
        }

        $titular = $this->usuarioModel->findById($titularId);
        if (!$titular || !(bool) $titular['activo'] || !in_array($titular['tipo_cuenta'], ['PENSIONADO', 'NO_PENSIONADO'], true)) {
            return ['success' => false, 'message' => 'El empleado no está disponible para pensionamiento.'];
        }

        $resultado = $this->pensionamientoModel->registrar($titularId, null, $anio, $mes, $registradoPorId);
        if ($resultado['success']) {
            $resultado['message'] = 'Pensionamiento del titular confirmado con reservas automáticas de lunes a jueves.';
        }
        return $resultado;
    }

    public function registrarFamiliar(int $titularId, int $familiarId, int $registradoPorId, int $anio, int $mes): array {
        $validacion = $this->validarPeriodo($anio, $mes);
        if ($validacion !== null) {
            return ['success' => false, 'message' => $validacion];
        }

        $titular = $this->usuarioModel->findById($titularId);
        $familiar = $this->familiarModel->findById($familiarId);
        if (!$titular || !(bool) $titular['activo'] || $titular['tipo_cuenta'] !== 'PENSIONADO') {
            return ['success' => false, 'message' => 'Solo un titular pensionado puede inscribir familiares.'];
        }
        if (!$familiar || (int) $familiar['empleado_titular_id'] !== $titularId || $familiar['estado'] !== 'ACTIVO') {
            return ['success' => false, 'message' => 'El familiar debe estar activo y vinculado al titular.'];
        }

        $resultado = $this->pensionamientoModel->registrar($titularId, $familiarId, $anio, $mes, $registradoPorId);
        if ($resultado['success']) {
            $resultado['message'] = 'Pensionamiento del familiar confirmado con reservas automáticas de lunes a jueves.';
        }
        return $resultado;
    }

    public function listar(int $titularId, int $anio, int $mes): array {
        $validacion = $this->validarPeriodo($anio, $mes);
        if ($validacion !== null) {
            return ['success' => false, 'message' => $validacion];
        }
        return [
            'success' => true,
            'data' => $this->pensionamientoModel->listarPorTitular($titularId, $anio, $mes),
        ];
    }

    private function validarPeriodo(int $anio, int $mes): ?string {
        if ($mes < 1 || $mes > 12) {
            return 'El mes seleccionado no es válido.';
        }
        $proximoMes = ($this->nowProvider)()->modify('first day of next month');
        if ($anio !== (int) $proximoMes->format('Y') || $mes !== (int) $proximoMes->format('n')) {
            return 'El pensionamiento solo puede solicitarse antes de iniciar el mes siguiente.';
        }
        return null;
    }
}
