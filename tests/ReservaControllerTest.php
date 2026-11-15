<?php

use PHPUnit\Framework\TestCase;

final class ReservaEnMemoria extends Reserva
{
    private bool $titularOcupado = false;
    public int $reservasGuardadas = 0;

    public function __construct()
    {
    }

    public function existeParaTitular(int $usuarioId, string $fecha): bool
    {
        return $this->titularOcupado;
    }

    public function crearParaTitular(int $usuarioId, string $fecha, string $modalidad = 'ESPORADICO'): int
    {
        $this->titularOcupado = true;
        $this->reservasGuardadas++;
        return $this->reservasGuardadas;
    }
}

final class ReservaControllerTest extends TestCase
{
    private const AYER = '2026-10-31';
    private const HOY = '2026-11-01';
    private const MANANA = '2026-11-02';

    public function testRechazaUnaReservaSiElDiaNoEsOperativo(): void
    {
        $calendario = $this->createMock(Calendario::class);
        $calendario->expects(self::once())
            ->method('esDiaOperativo')
            ->with(self::MANANA)
            ->willReturn(false);

        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(reserva: $reservas, calendario: $calendario)
            ->reservarTitular(7, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('no es operativo', $resultado['message']);
    }

    public function testRechazaUnaReservaSinMenuPublicado(): void
    {
        $menu = $this->createStub(Menu::class);
        $menu->method('findByFecha')->willReturn(false);

        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(reserva: $reservas, menu: $menu)
            ->reservarTitular(7, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('No hay menú publicado', $resultado['message']);
    }

    public function testRechazaUnaReservaDuplicadaSinGuardarOtra(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaTitular')->willReturn(true);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(reserva: $reservas)
            ->reservarTitular(7, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('Ya tienes una reserva activa', $resultado['message']);
    }

    public function testNoPermiteReservasAUnaCuentaAdmin(): void
    {
        $usuario = $this->createStub(Usuario::class);
        $usuario->method('findById')->willReturn(['tipo_cuenta' => 'ADMIN']);

        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaTitular')->willReturn(false);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(reserva: $reservas, usuario: $usuario)
            ->reservarTitular(7, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('administradores', $resultado['message']);
    }

    public function testRechazaFechasPasadasAntesDeBuscarMenu(): void
    {
        $menu = $this->createMock(Menu::class);
        $menu->expects(self::never())->method('findByFecha');
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(reserva: $reservas, menu: $menu)
            ->reservarTitular(7, self::AYER);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('fecha pasada', $resultado['message']);
    }

    public function testReservaExitosaGuardaUnaVezYDevuelveElId(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaTitular')->willReturn(false);
        $reservas->expects(self::once())
            ->method('crearParaTitular')
            ->with(7, self::MANANA, 'ESPORADICO')
            ->willReturn(42);

        $resultado = $this->controller(reserva: $reservas)
            ->reservarTitular(7, self::MANANA);

        self::assertTrue($resultado['success']);
        self::assertSame(42, $resultado['reserva_id']);
    }

    public function testRechazaReservaDeHoyDesdeLasNueve(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaTitular');

        $resultado = $this->controller(
            reserva: $reservas,
            ahora: new DateTimeImmutable(self::HOY . ' 09:00:00')
        )->reservarTitular(7, self::HOY);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('venció a las 09:00', $resultado['message']);
    }

    public function testPermiteReservaDeHoyAntesDeLasNueve(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaTitular')->willReturn(false);
        $reservas->expects(self::once())->method('crearParaTitular')->with(7, self::HOY, 'ESPORADICO')->willReturn(43);

        $resultado = $this->controller(
            reserva: $reservas,
            ahora: new DateTimeImmutable(self::HOY . ' 08:59:00')
        )->reservarTitular(7, self::HOY);

        self::assertTrue($resultado['success']);
        self::assertSame(43, $resultado['reserva_id']);
    }

    public function testRechazaReservaDeFamiliarDeHoyDesdeLasNueve(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaFamiliar');

        $resultado = $this->controller(
            reserva: $reservas,
            familiar: $this->familiarActivoDe(7),
            ahora: new DateTimeImmutable(self::HOY . ' 09:00:00')
        )->reservarFamiliar(7, 12, self::HOY);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('venció a las 09:00', $resultado['message']);
    }

    public function testFakeEnMemoriaRechazaLaSegundaReservaDelMismoTitular(): void
    {
        $reservas = new ReservaEnMemoria();
        $controller = $this->controller(reserva: $reservas);

        $primera = $controller->reservarTitular(7, self::MANANA);
        $segunda = $controller->reservarTitular(7, self::MANANA);

        self::assertTrue($primera['success']);
        self::assertFalse($segunda['success']);
        self::assertSame(1, $reservas->reservasGuardadas);
    }

    public function testFamiliarDeOtroTitularNoPuedeSerReservado(): void
    {
        $familiar = $this->createStub(Familiar::class);
        $familiar->method('findById')->willReturn([
            'empleado_titular_id' => 88,
            'estado' => 'ACTIVO',
        ]);

        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaFamiliar');

        $resultado = $this->controller(reserva: $reservas, familiar: $familiar)
            ->reservarFamiliar(7, 12, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('no pertenece', $resultado['message']);
    }

    public function testFamiliarInactivoNoPuedeReservar(): void
    {
        $familiar = $this->createStub(Familiar::class);
        $familiar->method('findById')->willReturn([
            'empleado_titular_id' => 7,
            'estado' => 'INACTIVO',
        ]);
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaFamiliar');

        $resultado = $this->controller(reserva: $reservas, familiar: $familiar)
            ->reservarFamiliar(7, 12, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('está inactivo', $resultado['message']);
    }

    public function testFamiliarSoloPuedeReservarSiElTitularEsPensionado(): void
    {
        $familiar = $this->familiarActivoDe(7);
        $usuario = $this->usuarioDeTipo('NO_PENSIONADO');
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('crearParaFamiliar');

        $resultado = $this->controller(
            reserva: $reservas,
            familiar: $familiar,
            usuario: $usuario
        )->reservarFamiliar(7, 12, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('Solo empleados pensionados', $resultado['message']);
    }

    public function testRechazaLaReservaDuplicadaDeUnFamiliar(): void
    {
        $familiar = $this->familiarActivoDe(7);
        $usuario = $this->usuarioDeTipo('PENSIONADO');
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaFamiliar')->willReturn(true);
        $reservas->expects(self::never())->method('crearParaFamiliar');

        $resultado = $this->controller(
            reserva: $reservas,
            familiar: $familiar,
            usuario: $usuario,
        )->reservarFamiliar(7, 12, self::MANANA);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('ya tiene una reserva activa', $resultado['message']);
    }

    public function testReservaFamiliarExitosaInvocaElModeloUnaVez(): void
    {
        $familiar = $this->familiarActivoDe(7);
        $usuario = $this->usuarioDeTipo('PENSIONADO');
        $pensionamiento = $this->createStub(Pensionamiento::class);
        $pensionamiento->method('estaRegistrado')->willReturn(true);
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('existeParaFamiliar')->willReturn(false);
        $reservas->expects(self::once())
            ->method('crearParaFamiliar')
            ->with(7, 12, self::MANANA, 'PENSIONADO')
            ->willReturn(91);

        $resultado = $this->controller(
            reserva: $reservas,
            familiar: $familiar,
            usuario: $usuario,
            pensionamiento: $pensionamiento
        )->reservarFamiliar(7, 12, self::MANANA);

        self::assertTrue($resultado['success']);
        self::assertSame(91, $resultado['reserva_id']);
    }

    public function testNoPermiteCancelarLaReservaDeHoyDesdeLasNueve(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'fecha_reserva' => self::HOY,
        ]);
        $reservas->expects(self::never())->method('cancelar');

        $resultado = $this->controller(
            reserva: $reservas,
            ahora: new DateTimeImmutable(self::HOY . ' 09:00:00')
        )->cancelar(21, 7);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('09:00', $resultado['message']);
    }

    public function testPermiteCancelarLaReservaDeHoyAntesDeLasNueve(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'fecha_reserva' => self::HOY,
        ]);
        $reservas->expects(self::once())->method('cancelar')->with(21)->willReturn(true);

        $resultado = $this->controller(
            reserva: $reservas,
            ahora: new DateTimeImmutable(self::HOY . ' 08:59:59')
        )->cancelar(21, 7);

        self::assertTrue($resultado['success']);
    }

    public function testNoPermiteCancelarUnaReservaDeOtroTitular(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 88,
            'fecha_reserva' => self::MANANA,
        ]);
        $reservas->expects(self::never())->method('cancelar');

        $resultado = $this->controller(reserva: $reservas)->cancelar(21, 7);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('No tienes permiso', $resultado['message']);
    }

    public function testNoPermiteCancelarUnaReservaDeFechaPasada(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'fecha_reserva' => self::AYER,
        ]);
        $reservas->expects(self::never())->method('cancelar');

        $resultado = $this->controller(reserva: $reservas)->cancelar(21, 7);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('fechas pasadas', $resultado['message']);
    }

    public function testPensionadoNoPuedeCancelarSiQuedariaDebajoDelMinimoMensual(): void
    {
        $pensionamiento = $this->createStub(Pensionamiento::class);
        $pensionamiento->method('estaRegistrado')->willReturn(true);
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'tipo_beneficiario' => 'TITULAR',
            'fecha_reserva' => self::MANANA,
        ]);
        $reservas->expects(self::once())->method('contarConsumosTitular')->with(7, 2026, 11)->willReturn(10);
        $reservas->expects(self::never())->method('cancelar');

        $resultado = $this->controller(
            reserva: $reservas,
            usuario: $this->usuarioDeTipo('PENSIONADO'),
            pensionamiento: $pensionamiento
        )->cancelar(21, 7);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('mínimo mensual de 10', $resultado['message']);
    }

    public function testPensionadoPuedeCancelarSiMantieneDiezAlmuerzos(): void
    {
        $pensionamiento = $this->createStub(Pensionamiento::class);
        $pensionamiento->method('estaRegistrado')->willReturn(true);
        $reservas = $this->createMock(Reserva::class);
        $reservas->method('findById')->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'tipo_beneficiario' => 'TITULAR',
            'fecha_reserva' => self::MANANA,
        ]);
        $reservas->method('contarConsumosTitular')->willReturn(11);
        $reservas->expects(self::once())->method('cancelar')->with(21)->willReturn(true);

        $resultado = $this->controller(
            reserva: $reservas,
            usuario: $this->usuarioDeTipo('PENSIONADO'),
            pensionamiento: $pensionamiento
        )->cancelar(21, 7);

        self::assertTrue($resultado['success']);
    }

    public function testAdministradorPuedeRegistrarLaEntregaDespuesDelCorte(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::once())
            ->method('actualizarEstadosAutomaticos')
            ->with(self::HOY, '10:00', '09:00');
        $reservas->expects(self::once())
            ->method('marcarEntregada')
            ->with(42, self::HOY)
            ->willReturn(true);

        $resultado = $this->controller(reserva: $reservas)->marcarEntregada(42);

        self::assertTrue($resultado['success']);
    }

    public function testNoPermiteRegistrarEntregaAntesDelCorte(): void
    {
        $reservas = $this->createMock(Reserva::class);
        $reservas->expects(self::never())->method('actualizarEstadosAutomaticos');
        $reservas->expects(self::never())->method('marcarEntregada');

        $resultado = $this->controller(
            reserva: $reservas,
            ahora: new DateTimeImmutable(self::HOY . ' 08:59:00')
        )->marcarEntregada(42);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('después del corte', $resultado['message']);
    }

    public function testLaHoraDeCancelacionSeTomaDeLaConfiguracionVigente(): void
    {
        $reserva = $this->createMock(Reserva::class);
        $reserva->expects(self::once())
            ->method('actualizarEstadosAutomaticos')
            ->with(self::HOY, '08:45', '08:30');
        $reserva->method('findById')->with(21)->willReturn([
            'estado' => 'RESERVADO',
            'usuario_id' => 7,
            'tipo_beneficiario' => 'TITULAR',
            'fecha_reserva' => self::HOY,
        ]);
        $reserva->expects(self::never())->method('cancelar');

        $configuracion = $this->createStub(Configuracion::class);
        $configuracion->method('obtener')->willReturn([
            'tarifa_pensionado' => 15.0,
            'tarifa_esporadico' => 18.0,
            'minimo_almuerzos_mes' => 10,
            'hora_limite' => '08:30',
        ]);

        $resultado = $this->controller(
            reserva: $reserva,
            ahora: new DateTimeImmutable(self::HOY . ' 08:45:00'),
            configuracion: $configuracion
        )->cancelar(21, 7);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('08:30', $resultado['message']);
    }

    private function controller(
        ?Reserva $reserva = null,
        ?Familiar $familiar = null,
        ?Calendario $calendario = null,
        ?Usuario $usuario = null,
        ?Menu $menu = null,
        ?DateTimeImmutable $ahora = null,
        ?Pensionamiento $pensionamiento = null,
        ?Configuracion $configuracion = null
    ): ReservaController {
        if ($calendario === null) {
            $calendario = $this->createStub(Calendario::class);
            $calendario->method('esDiaOperativo')->willReturn(true);
        }

        if ($menu === null) {
            $menu = $this->createStub(Menu::class);
            $menu->method('findByFecha')->willReturn(['id' => 1]);
        }

        if ($reserva === null) {
            $reserva = $this->createStub(Reserva::class);
            $reserva->method('existeParaTitular')->willReturn(false);
        }

        if ($usuario === null) {
            $usuario = $this->createStub(Usuario::class);
            $usuario->method('findById')->willReturn(['tipo_cuenta' => 'NO_PENSIONADO']);
        }

        $familiar ??= $this->createStub(Familiar::class);
        $ahora ??= new DateTimeImmutable(self::HOY . ' 10:00:00');
        $pensionamiento ??= $this->createStub(Pensionamiento::class);
        $pensionamiento->method('estaRegistrado')->willReturn(false);
        if ($configuracion === null) {
            $configuracion = $this->createStub(Configuracion::class);
            $configuracion->method('obtener')->willReturn([
                'tarifa_pensionado' => 15.0,
                'tarifa_esporadico' => 18.0,
                'minimo_almuerzos_mes' => 10,
                'hora_limite' => '09:00',
            ]);
        }
        $nowProvider = static fn (): DateTimeImmutable => $ahora;

        return new ReservaController($reserva, $familiar, $calendario, $usuario, $menu, $nowProvider, $pensionamiento, $configuracion);
    }

    private function familiarActivoDe(int $titularId): Familiar
    {
        $familiar = $this->createStub(Familiar::class);
        $familiar->method('findById')->willReturn([
            'empleado_titular_id' => $titularId,
            'estado' => 'ACTIVO',
        ]);
        return $familiar;
    }

    private function usuarioDeTipo(string $tipo): Usuario
    {
        $usuario = $this->createStub(Usuario::class);
        $usuario->method('findById')->willReturn(['tipo_cuenta' => $tipo]);
        return $usuario;
    }
}
