<?php

use PHPUnit\Framework\TestCase;

final class UsuarioParaAutenticacion extends Usuario
{
    public function __construct(private array|false $usuario) {}

    public function findByEmailForAuthentication(string $correo): array|false
    {
        return $this->usuario;
    }
}

final class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testUnaCuentaInactivaConClaveCorrectaRecibeMensajeDeCuentaSuspendida(): void
    {
        $controller = new AuthController($this->usuario([ 'activo' => 0 ]));

        $resultado = $controller->login('persona@uab.bo', 'Clave-de-prueba');

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('suspendida o inactiva', $resultado['message']);
        self::assertArrayNotHasKey('usuario_id', $_SESSION);
    }

    public function testCuentaInactivaConClaveIncorrectaNoRevelaElEstado(): void
    {
        $controller = new AuthController($this->usuario([ 'activo' => 0 ]));

        $resultado = $controller->login('persona@uab.bo', 'clave-incorrecta');

        self::assertFalse($resultado['success']);
        self::assertSame('Credenciales incorrectas.', $resultado['message']);
    }

    public function testCuentaActivaIniciaSesionYRedirigeSegunElRol(): void
    {
        $controller = new AuthController($this->usuario([
            'activo' => 1,
            'tipo_cuenta' => 'ADMIN',
            'id' => 19,
            'nombre' => 'Admin de prueba',
            'codigo_empleado' => null,
        ]));

        $resultado = $controller->login(' ADMIN@UAB.BO ', 'Clave-de-prueba');

        self::assertTrue($resultado['success']);
        self::assertSame(19, $_SESSION['usuario_id']);
        self::assertStringEndsWith('/frontend/admin/dashboard.php', $resultado['redirect']);
    }

    private function usuario(array $overrides = []): UsuarioParaAutenticacion
    {
        return new UsuarioParaAutenticacion(array_merge([
            'id' => 8,
            'nombre' => 'Usuario de prueba',
            'correo' => 'persona@uab.bo',
            'contrasena' => password_hash('Clave-de-prueba', PASSWORD_DEFAULT),
            'tipo_cuenta' => 'NO_PENSIONADO',
            'codigo_empleado' => 'TEST-8',
            'activo' => 1,
        ], $overrides));
    }
}
