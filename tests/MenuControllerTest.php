<?php

use PHPUnit\Framework\TestCase;

final class MenuControllerTest extends TestCase
{
    public function testRequiereSopaPlatoPrincipalYPostre(): void
    {
        $calendario = $this->createMock(Calendario::class);
        $calendario->expects(self::never())->method('esDiaOperativo');
        $menu = $this->createMock(Menu::class);
        $menu->expects(self::never())->method('guardar');

        $resultado = (new MenuController($menu, $calendario))
            ->publicar('2026-11-02', 'Sopa', '', 'Fruta', 9);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('Completa la sopa', $resultado['message']);
    }

    public function testLimitaCadaPlatoAlTamañoDeLaBaseDeDatos(): void
    {
        $calendario = $this->createMock(Calendario::class);
        $calendario->expects(self::never())->method('esDiaOperativo');
        $menu = $this->createMock(Menu::class);
        $menu->expects(self::never())->method('guardar');

        $resultado = (new MenuController($menu, $calendario))
            ->publicar('2026-11-02', str_repeat('a', 151), 'Plato', 'Postre', 9);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('150 caracteres', $resultado['message']);
    }

    public function testNoPublicaMenuEnDiaNoOperativo(): void
    {
        $calendario = $this->createStub(Calendario::class);
        $calendario->method('esDiaOperativo')->willReturn(false);
        $menu = $this->createMock(Menu::class);
        $menu->expects(self::never())->method('guardar');

        $resultado = (new MenuController($menu, $calendario))
            ->publicar('2026-11-02', 'Sopa', 'Pollo', 'Fruta', 9);

        self::assertFalse($resultado['success']);
        self::assertStringContainsString('días operativos', $resultado['message']);
    }

    public function testPublicaLosTresComponentesDelMenu(): void
    {
        $fecha = '2026-11-02';
        $calendario = $this->createStub(Calendario::class);
        $calendario->method('esDiaOperativo')->willReturn(true);
        $menu = $this->createMock(Menu::class);
        $menu->expects(self::once())
            ->method('guardar')
            ->with($fecha, 'Sopa', 'Pollo', 'Fruta', 9, null)
            ->willReturn(true);

        $resultado = (new MenuController($menu, $calendario))
            ->publicar($fecha, ' Sopa ', ' Pollo ', ' Fruta ', 9);

        self::assertTrue($resultado['success']);
    }
}
