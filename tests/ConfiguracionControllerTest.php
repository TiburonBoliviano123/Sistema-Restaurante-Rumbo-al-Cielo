<?php

use PHPUnit\Framework\TestCase;

final class ConfiguracionControllerTest extends TestCase
{
    public function testActualizaTarifasMinimoYHoraLimite(): void
    {
        $modelo = $this->createMock(Configuracion::class);
        $modelo->expects(self::once())->method('guardar')->with([
            'tarifa_pensionado' => 16.5,
            'tarifa_esporadico' => 19.0,
            'minimo_almuerzos_mes' => 8,
            'hora_limite' => '08:30',
        ]);

        $resultado = (new ConfiguracionController($modelo))->actualizar([
            'tarifa_pensionado' => '16.50',
            'tarifa_esporadico' => '19.00',
            'minimo_almuerzos_mes' => '8',
            'hora_limite' => '08:30',
        ]);

        self::assertTrue($resultado['success']);
    }

    public function testRechazaHoraInvalidaSinGuardar(): void
    {
        $modelo = $this->createMock(Configuracion::class);
        $modelo->expects(self::never())->method('guardar');

        $resultado = (new ConfiguracionController($modelo))->actualizar([
            'tarifa_pensionado' => '15',
            'tarifa_esporadico' => '18',
            'minimo_almuerzos_mes' => '10',
            'hora_limite' => '25:90',
        ]);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('24 horas', $resultado['message']);
    }

    public function testRechazaMinimoFueraDeRangoSinGuardar(): void
    {
        $modelo = $this->createMock(Configuracion::class);
        $modelo->expects(self::never())->method('guardar');

        $resultado = (new ConfiguracionController($modelo))->actualizar([
            'tarifa_pensionado' => '15',
            'tarifa_esporadico' => '18',
            'minimo_almuerzos_mes' => '32',
            'hora_limite' => '09:00',
        ]);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('0 y 31', $resultado['message']);
    }
}
