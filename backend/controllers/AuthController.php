<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../models/Usuario.php';

class AuthController {
    private Usuario $usuarioModel;

    public function __construct(?Usuario $usuarioModel = null) {
        $this->usuarioModel = $usuarioModel ?? new Usuario();
    }

    /**
     * Autentica al usuario con correo y contraseña.
     * @return array ['success'=>bool, 'message'=>string, 'redirect'=>string]
     */
    public function login(string $correo, string $contrasena): array {
        $correo = trim(strtolower($correo));

        if (empty($correo) || empty($contrasena)) {
            return ['success' => false, 'message' => 'Completa todos los campos.'];
        }

        $usuario = $this->usuarioModel->findByEmailForAuthentication($correo);

        if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
            return ['success' => false, 'message' => 'Credenciales incorrectas.'];
        }

        if (!(bool) $usuario['activo']) {
            return ['success' => false, 'message' => 'Tu cuenta está suspendida o inactiva. Contacta al administrador.'];
        }

        // Establecer sesión
        $_SESSION['usuario_id']      = $usuario['id'];
        $nombreCompleto = $usuario['nombre_completo'] ?? $usuario['nombre'];
        $_SESSION['nombre']          = $nombreCompleto;
        $_SESSION['tipo_cuenta']     = $usuario['tipo_cuenta'];
        $_SESSION['codigo_empleado'] = $usuario['codigo_empleado'];

        $redirect = match ($usuario['tipo_cuenta']) {
            'ADMIN'  => BASE_URL . '/frontend/admin/dashboard.php',
            default  => BASE_URL . '/frontend/empleado/dashboard.php',
        };

        return ['success' => true, 'message' => 'Bienvenido/a, ' . $nombreCompleto, 'redirect' => $redirect];
    }

    /** Cierra la sesión activa */
    public function logout(): void {
        $_SESSION = [];
        session_destroy();
    }
}
