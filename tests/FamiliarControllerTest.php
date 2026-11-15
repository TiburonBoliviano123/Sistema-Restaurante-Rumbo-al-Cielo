<?php

use PHPUnit\Framework\TestCase;

final class FamiliarControllerTest extends TestCase
{
    private function controller(?Familiar $familiar = null, ?Usuario $usuario = null): FamiliarController
    {
        $familiar ??= $this->createMock(Familiar::class);
        $usuario ??= $this->createMock(Usuario::class);
        return new FamiliarController($familiar, $usuario);
    }

    private function titular(string $tipo = 'PENSIONADO'): array
    {
        return ['id' => 7, 'tipo_cuenta' => $tipo];
    }

    private function datos(array $sobre = []): array
    {
        return array_merge([
            'nombre' => ' Ana Pérez ',
            'ci' => ' 123 456 LP ',
            'parentesco' => 'madre',
        ], $sobre);
    }

    public function testRegistraFamiliarNormalizandoCiYParentesco(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('findById')->willReturn($this->titular());
        $usuario->method('ciExiste')->with('123456LP')->willReturn(false);
        $familiar->method('ciExiste')->with('123456LP', 0)->willReturn(false);
        $familiar->method('contarActivosPorTitular')->with(7)->willReturn(0);
        $familiar->expects(self::once())
            ->method('crear')
            ->with(7, 'Ana Pérez', '123456LP', 'MADRE')
            ->willReturn(12);

        $resultado = $this->controller($familiar, $usuario)->registrar(7, $this->datos());

        self::assertTrue($resultado['success']);
        self::assertSame(12, $resultado['familiar_id']);
    }

    public function testRechazaParentescoFueraDeLaListaPermitida(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('findById')->willReturn($this->titular());
        $familiar->expects(self::never())->method('crear');

        $resultado = $this->controller($familiar, $usuario)->registrar(7, $this->datos(['parentesco' => 'HERMANO']));

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('Parentesco inválido', $resultado['message']);
    }

    public function testRechazaCiYaRegistradoComoUsuario(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $familiar->method('ciExiste')->willReturn(false);
        $familiar->expects(self::never())->method('crear');
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('findById')->willReturn($this->titular());
        $usuario->method('ciExiste')->willReturn(true);

        $resultado = $this->controller($familiar, $usuario)->registrar(7, $this->datos());

        self::assertFalse($resultado['success']);
        self::assertSame('El CI ya está asociado a otra persona.', $resultado['message']);
    }

    public function testRechazaCiDuplicadoEntreFamiliares(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $familiar->method('ciExiste')->willReturn(true);
        $familiar->expects(self::never())->method('crear');
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('findById')->willReturn($this->titular());

        $resultado = $this->controller($familiar, $usuario)->registrar(7, $this->datos());

        self::assertFalse($resultado['success']);
        self::assertSame('El CI ya está asociado a otra persona.', $resultado['message']);
    }

    public function testSoloPensionadosPuedenRegistrarFamiliares(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('findById')->willReturn($this->titular('NO_PENSIONADO'));
        $familiar->expects(self::never())->method('crear');

        $resultado = $this->controller($familiar, $usuario)->registrar(7, $this->datos());

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('pensionados', $resultado['message']);
    }

    public function testNoPermiteActualizarFamiliarDeOtroTitular(): void
    {
        $familiar = $this->createMock(Familiar::class);
        $familiar->method('findById')->willReturn(['id' => 12, 'empleado_titular_id' => 8]);
        $familiar->expects(self::never())->method('actualizar');

        $resultado = $this->controller($familiar)->actualizar(7, 12, $this->datos());

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('no pertenece', $resultado['message']);
    }

}
