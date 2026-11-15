<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Familiar.php';
require_once __DIR__ . '/../models/Usuario.php';

class FamiliarController {
    private Familiar $familiarModel;
    private Usuario $usuarioModel;

    public function __construct(?Familiar $familiarModel = null, ?Usuario $usuarioModel = null) {
        $this->familiarModel = $familiarModel ?? new Familiar();
        $this->usuarioModel = $usuarioModel ?? new Usuario();
    }

    public function registrar(int $titularId, array $data): array {
        $titular = $this->usuarioModel->findById($titularId);
        if (!$titular) {
            return ['success' => false, 'message' => 'Titular no encontrado.'];
        }
        if ($titular['tipo_cuenta'] !== 'PENSIONADO') {
            return ['success' => false, 'message' => 'Solo los empleados pensionados pueden registrar familiares.'];
        }

        $datos = $this->normalizarYValidar($data);
        if (isset($datos['error'])) {
            return ['success' => false, 'message' => $datos['error']];
        }
        if ($this->ciDuplicado($datos['ci'])) {
            return ['success' => false, 'message' => 'El CI ya está asociado a otra persona.'];
        }
        try {
            $id = $this->familiarModel->crear($titularId, $datos['nombre'], $datos['ci'], $datos['parentesco']);
        } catch (PDOException $exception) {
            if ($this->esErrorDeUnicidad($exception)) {
                return ['success' => false, 'message' => 'El CI ya está asociado a otra persona.'];
            }
            throw $exception;
        }

        return ['success' => true, 'message' => 'Familiar registrado correctamente.', 'familiar_id' => $id];
    }

    public function actualizar(int $titularId, int $familiarId, array $data): array {
        $familiar = $this->familiarModel->findById($familiarId);
        if (!$familiar || (int) $familiar['empleado_titular_id'] !== $titularId) {
            return ['success' => false, 'message' => 'Familiar no encontrado o no pertenece a tu cuenta.'];
        }
        $datos = $this->normalizarYValidar($data);
        if (isset($datos['error'])) {
            return ['success' => false, 'message' => $datos['error']];
        }
        if ($this->ciDuplicado($datos['ci'], $familiarId)) {
            return ['success' => false, 'message' => 'El CI ya está asociado a otra persona.'];
        }

        try {
            $this->familiarModel->actualizar($familiarId, $datos['nombre'], $datos['ci'], $datos['parentesco']);
        } catch (PDOException $exception) {
            if ($this->esErrorDeUnicidad($exception)) {
                return ['success' => false, 'message' => 'El CI ya está asociado a otra persona.'];
            }
            throw $exception;
        }
        return ['success' => true, 'message' => 'Familiar actualizado correctamente.'];
    }

    public function desactivar(int $titularId, int $familiarId): array {
        $familiar = $this->familiarModel->findById($familiarId);
        if (!$familiar || (int) $familiar['empleado_titular_id'] !== $titularId) {
            return ['success' => false, 'message' => 'Familiar no encontrado o no pertenece a tu cuenta.'];
        }
        $this->familiarModel->cambiarEstado($familiarId, 'INACTIVO');
        return ['success' => true, 'message' => 'Familiar desactivado correctamente.'];
    }

    public function listar(int $titularId): array {
        return $this->familiarModel->listarPorTitular($titularId);
    }

    private function normalizarYValidar(array $data): array {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $ci = strtoupper((string) preg_replace('/\s+/', '', trim((string) ($data['ci'] ?? ''))));
        $parentesco = strtoupper(trim((string) ($data['parentesco'] ?? '')));

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            return ['error' => 'Ingresa el nombre completo del familiar (máximo 150 caracteres).'];
        }
        if (strlen($ci) < 3 || strlen($ci) > 20 || !preg_match('/^[A-Z0-9-]+$/', $ci)) {
            return ['error' => 'El CI debe tener entre 3 y 20 caracteres alfanuméricos.'];
        }
        if (!in_array($parentesco, ['PADRE', 'MADRE', 'HIJO', 'HIJA', 'CONYUGE'], true)) {
            return ['error' => 'Parentesco inválido. Elige padre, madre, hijo, hija o cónyuge.'];
        }
        return ['nombre' => $nombre, 'ci' => $ci, 'parentesco' => $parentesco];
    }

    private function ciDuplicado(string $ci, int $excluirFamiliarId = 0): bool {
        return $this->familiarModel->ciExiste($ci, $excluirFamiliarId)
            || $this->usuarioModel->ciExiste($ci);
    }

    private function esErrorDeUnicidad(PDOException $exception): bool {
        return $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
