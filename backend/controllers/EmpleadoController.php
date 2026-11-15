<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';

class EmpleadoController {
    private Usuario $usuarioModel;

    public function __construct(?Usuario $usuarioModel = null) {
        $this->usuarioModel = $usuarioModel ?? new Usuario();
    }

    /** Registra un empleado desde el panel de administración. */
    public function registrar(array $data): array {
        $data = $this->normalizarDatosPersonales($data);
        $data['contrasena'] = (string) ($data['contrasena'] ?? '');
        $data['tipo_cuenta'] = (string) ($data['tipo_cuenta'] ?? '');
        $data['codigo_empleado'] = trim((string) ($data['codigo_empleado'] ?? ''));
        $data['celular'] = trim((string) ($data['celular'] ?? ''));

        $error = $this->validarDatosPersonales($data);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }
        if (strlen($data['contrasena']) < 6) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if (!in_array($data['tipo_cuenta'], ['PENSIONADO', 'NO_PENSIONADO'], true)) {
            return ['success' => false, 'message' => 'Tipo de cuenta inválido.'];
        }
        if ($data['codigo_empleado'] === '') {
            return ['success' => false, 'message' => 'El código de empleado es requerido.'];
        }
        if ($this->usuarioModel->codigoExiste($data['codigo_empleado'])) {
            return ['success' => false, 'message' => 'El código de empleado ya está en uso.'];
        }

        try {
            $id = $this->usuarioModel->crear($data);
        } catch (PDOException $error) {
            if ($this->esErrorDeUnicidad($error)) {
                return ['success' => false, 'message' => 'El correo, CI o código de empleado ya está registrado.'];
            }
            throw $error;
        }

        return ['success' => true, 'message' => 'Empleado registrado correctamente.', 'usuario_id' => $id];
    }

    /** Actualiza datos de un empleado desde el panel de administración. */
    public function actualizar(int $id, array $data): array {
        $data = $this->normalizarDatosPersonales($data);
        $error = $this->validarDatosPersonales($data, $id);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $data['codigo_empleado'] = trim((string) ($data['codigo_empleado'] ?? ''));
        if ($data['codigo_empleado'] === '') {
            return ['success' => false, 'message' => 'El código de empleado es requerido.'];
        }
        if ($this->usuarioModel->codigoExiste($data['codigo_empleado'], $id)) {
            return ['success' => false, 'message' => 'El código de empleado ya está en uso.'];
        }
        if (!in_array($data['tipo_cuenta'] ?? '', ['PENSIONADO', 'NO_PENSIONADO'], true)) {
            return ['success' => false, 'message' => 'Tipo de cuenta inválido.'];
        }

        try {
            $this->usuarioModel->actualizar($id, $data);
        } catch (PDOException $error) {
            if ($this->esErrorDeUnicidad($error)) {
                return ['success' => false, 'message' => 'El correo, CI o código de empleado ya está registrado.'];
            }
            throw $error;
        }

        return ['success' => true, 'message' => 'Empleado actualizado correctamente.'];
    }

    /** Permite al empleado cambiar solo los datos personales de su propia cuenta. */
    public function actualizarPerfil(int $usuarioId, array $data): array {
        $permitidos = ['nombre', 'apellido', 'ci', 'departamento', 'correo', 'celular'];
        $data = $this->normalizarDatosPersonales(array_intersect_key($data, array_flip($permitidos)));
        $error = $this->validarDatosPersonales($data, $usuarioId);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        try {
            $this->usuarioModel->actualizar($usuarioId, array_intersect_key($data, array_flip($permitidos)));
        } catch (PDOException $error) {
            if ($this->esErrorDeUnicidad($error)) {
                return ['success' => false, 'message' => 'El correo o CI ya está asociado a otra cuenta.'];
            }
            throw $error;
        }

        $perfil = $this->usuarioModel->findById($usuarioId);
        if (!$perfil) {
            return ['success' => false, 'message' => 'No se encontró la cuenta del empleado.'];
        }

        return [
            'success' => true,
            'message' => 'Tus datos personales fueron actualizados.',
            'data' => $perfil,
        ];
    }

    /** Activa o desactiva un empleado. */
    public function cambiarEstado(int $id, int $activo): array {
        if (!in_array($activo, [0, 1], true)) {
            return ['success' => false, 'message' => 'Estado de cuenta inválido.'];
        }

        $this->usuarioModel->cambiarEstado($id, $activo);
        $msg = $activo ? 'Empleado activado.' : 'Empleado desactivado.';
        return ['success' => true, 'message' => $msg];
    }

    /** Lista todos los empleados. */
    public function listar(): array {
        return $this->usuarioModel->listarEmpleados();
    }

    /** Obtiene un empleado por ID. */
    public function obtener(int $id): array|false {
        return $this->usuarioModel->findById($id);
    }

    private function normalizarDatosPersonales(array $data): array {
        $data['nombre'] = trim((string) ($data['nombre'] ?? ''));
        $data['apellido'] = trim((string) ($data['apellido'] ?? ''));
        $data['correo'] = strtolower(trim((string) ($data['correo'] ?? '')));
        $data['ci'] = strtoupper((string) preg_replace('/\s+/u', '', trim((string) ($data['ci'] ?? ''))));
        $data['departamento'] = trim((string) ($data['departamento'] ?? ''));
        $data['celular'] = trim((string) ($data['celular'] ?? ''));

        return $data;
    }

    private function validarDatosPersonales(array $data, int $excluirId = 0): ?string {
        foreach (['nombre' => 'nombre', 'apellido' => 'apellido', 'ci' => 'CI', 'departamento' => 'departamento'] as $campo => $etiqueta) {
            if (($data[$campo] ?? '') === '') {
                return 'El ' . $etiqueta . ' es requerido.';
            }
        }
        if (
            strlen($data['nombre']) > 150 ||
            strlen($data['apellido']) > 100 ||
            strlen($data['nombre'] . ' ' . $data['apellido']) > 150 ||
            strlen($data['departamento']) > 100
        ) {
            return 'Revisa la longitud del nombre, apellido o departamento.';
        }
        if (strlen($data['ci']) < 3 || strlen($data['ci']) > 20) {
            return 'El CI debe tener entre 3 y 20 caracteres.';
        }
        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL) || strlen($data['correo']) > 150) {
            return 'Correo electrónico inválido.';
        }
        if ($this->usuarioModel->correoExiste($data['correo'], $excluirId)) {
            return 'El correo ya está registrado.';
        }
        if ($this->usuarioModel->ciExiste($data['ci'], $excluirId)) {
            return 'El CI ya está registrado.';
        }

        return null;
    }

    private function esErrorDeUnicidad(PDOException $error): bool {
        return (string) $error->getCode() === '23000';
    }
}
