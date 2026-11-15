<?php

use PHPUnit\Framework\TestCase;

final class EmpleadoControllerTest extends TestCase
{
    public function testAltaNormalizaYGuardaCiApellidoYDepartamento(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('correoExiste')->willReturn(false);
        $usuario->method('ciExiste')->willReturn(false);
        $usuario->method('codigoExiste')->willReturn(false);
        $usuario->expects(self::once())
            ->method('crear')
            ->with(self::callback(static fn (array $data): bool =>
                $data['nombre'] === 'María' &&
                $data['apellido'] === 'Pérez' &&
                $data['ci'] === '1234567-LP' &&
                $data['departamento'] === 'Administración' &&
                $data['correo'] === 'maria@example.test'
            ))
            ->willReturn(42);

        $resultado = (new EmpleadoController($usuario))->registrar($this->datosEmpleado([
            'ci' => ' 1234567 -lp ',
            'correo' => ' MARIA@EXAMPLE.TEST ',
        ]));

        self::assertTrue($resultado['success']);
        self::assertSame(42, $resultado['usuario_id']);
    }

    public function testRechazaUnCiDuplicadoAntesDeCrearLaCuenta(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('correoExiste')->willReturn(false);
        $usuario->expects(self::once())->method('ciExiste')->with('1234567-LP', 0)->willReturn(true);
        $usuario->expects(self::never())->method('crear');

        $resultado = (new EmpleadoController($usuario))->registrar($this->datosEmpleado());

        self::assertFalse($resultado['success']);
        self::assertSame('El CI ya está registrado.', $resultado['message']);
    }

    public function testRequiereLosNuevosDatosPersonales(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->expects(self::never())->method('crear');

        $datos = $this->datosEmpleado();
        $datos['departamento'] = '';
        $resultado = (new EmpleadoController($usuario))->registrar($datos);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('departamento', $resultado['message']);
    }

    public function testEmpleadoSoloPuedeActualizarSusDatosPersonales(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('correoExiste')->willReturn(false);
        $usuario->method('ciExiste')->willReturn(false);
        $usuario->expects(self::once())
            ->method('actualizar')
            ->with(12, self::callback(static function (array $data): bool {
                self::assertSame('María', $data['nombre']);
                self::assertArrayNotHasKey('tipo_cuenta', $data);
                self::assertArrayNotHasKey('codigo_empleado', $data);
                self::assertArrayNotHasKey('activo', $data);
                return true;
            }))
            ->willReturn(true);
        $usuario->method('findById')->willReturn([
            'id' => 12,
            'nombre' => 'María',
            'apellido' => 'Pérez',
            'nombre_completo' => 'María Pérez',
            'tipo_cuenta' => 'NO_PENSIONADO',
        ]);

        $resultado = (new EmpleadoController($usuario))->actualizarPerfil(12, $this->datosEmpleado([
            'tipo_cuenta' => 'ADMIN',
            'codigo_empleado' => 'MANIPULADO',
            'activo' => '0',
        ]));

        self::assertTrue($resultado['success']);
        self::assertSame('María Pérez', $resultado['data']['nombre_completo']);
    }

    private function datosEmpleado(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'María',
            'apellido' => 'Pérez',
            'ci' => '1234567-LP',
            'departamento' => 'Administración',
            'correo' => 'maria@example.test',
            'celular' => '70000000',
            'contrasena' => 'Clave-123',
            'codigo_empleado' => 'EMP-001',
            'tipo_cuenta' => 'NO_PENSIONADO',
        ], $overrides);
    }
}
