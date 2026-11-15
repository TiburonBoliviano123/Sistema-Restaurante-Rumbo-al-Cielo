<?php

use PHPUnit\Framework\TestCase;

final class PensionamientoControllerTest extends TestCase
{
    private const AHORA = '2026-11-02 10:00:00';

    public function testEmpleadoPuedeSolicitarElMesSiguiente(): void
    {
        $pensionamiento = $this->createMock(Pensionamiento::class);
        $pensionamiento->expects(self::once())
            ->method('registrar')
            ->with(7, null, 2026, 12, 7)
            ->willReturn(['success' => true, 'reservas_creadas' => 18, 'message' => 'ok']);
        $usuario = $this->usuario('NO_PENSIONADO');

        $resultado = $this->controller(pensionamiento: $pensionamiento, usuario: $usuario)
            ->registrarTitular(7, 7, 2026, 12);

        self::assertTrue($resultado['success']);
        self::assertSame(18, $resultado['reservas_creadas']);
        self::assertStringContainsString('lunes a jueves', $resultado['message']);
    }

    public function testNoPermiteRegistrarPensionamientoDelMesEnCurso(): void
    {
        $pensionamiento = $this->createMock(Pensionamiento::class);
        $pensionamiento->expects(self::never())->method('registrar');
        $usuario = $this->createMock(Usuario::class);
        $usuario->expects(self::never())->method('findById');

        $resultado = $this->controller(pensionamiento: $pensionamiento, usuario: $usuario)
            ->registrarTitular(7, 7, 2026, 11);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('antes de iniciar', $resultado['message']);
    }

    public function testAdminPuedeRegistrarUnFamiliarActivoDelTitular(): void
    {
        $pensionamiento = $this->createMock(Pensionamiento::class);
        $pensionamiento->expects(self::once())
            ->method('registrar')
            ->with(7, 12, 2026, 12, 99)
            ->willReturn(['success' => true, 'reservas_creadas' => 18, 'message' => 'ok']);
        $familiar = $this->createMock(Familiar::class);
        $familiar->method('findById')->with(12)->willReturn([
            'id' => 12, 'empleado_titular_id' => 7, 'estado' => 'ACTIVO',
        ]);

        $resultado = $this->controller(
            pensionamiento: $pensionamiento,
            usuario: $this->usuario('PENSIONADO'),
            familiar: $familiar
        )->registrarFamiliar(7, 12, 99, 2026, 12);

        self::assertTrue($resultado['success']);
    }

    public function testNoPermitePensionarUnFamiliarDeOtroTitular(): void
    {
        $pensionamiento = $this->createMock(Pensionamiento::class);
        $pensionamiento->expects(self::never())->method('registrar');
        $familiar = $this->createStub(Familiar::class);
        $familiar->method('findById')->willReturn([
            'id' => 12, 'empleado_titular_id' => 8, 'estado' => 'ACTIVO',
        ]);

        $resultado = $this->controller(
            pensionamiento: $pensionamiento,
            usuario: $this->usuario('PENSIONADO'),
            familiar: $familiar
        )->registrarFamiliar(7, 12, 99, 2026, 12);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('vinculado al titular', $resultado['message']);
    }

    public function testMesInvalidoEsRechazado(): void
    {
        $pensionamiento = $this->createMock(Pensionamiento::class);
        $pensionamiento->expects(self::never())->method('registrar');

        $resultado = $this->controller(pensionamiento: $pensionamiento)
            ->registrarTitular(7, 7, 2026, 13);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('no es válido', $resultado['message']);
    }

    private function controller(
        ?Pensionamiento $pensionamiento = null,
        ?Usuario $usuario = null,
        ?Familiar $familiar = null
    ): PensionamientoController {
        $now = static fn (): DateTimeImmutable => new DateTimeImmutable(self::AHORA);
        return new PensionamientoController($pensionamiento, $usuario, $familiar, $now);
    }

    private function usuario(string $tipo): Usuario
    {
        $usuario = $this->createStub(Usuario::class);
        $usuario->method('findById')->willReturn([
            'id' => 7, 'activo' => 1, 'tipo_cuenta' => $tipo,
        ]);
        return $usuario;
    }
}
