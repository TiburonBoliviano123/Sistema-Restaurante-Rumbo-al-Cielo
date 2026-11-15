<?php

final class ModelsIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testReservaModelGuardaConsultaCuentaYCancela(): void
    {
        $usuarioId = $this->crearUsuario();
        $fecha = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $reservas = new Reserva();

        $reservaId = $reservas->crearParaTitular($usuarioId, $fecha);

        self::assertGreaterThan(0, $reservaId);
        self::assertTrue($reservas->existeParaTitular($usuarioId, $fecha));
        self::assertSame(1, $reservas->contarConsumosTitular(
            $usuarioId,
            (int) substr($fecha, 0, 4),
            (int) substr($fecha, 5, 2)
        ));
        self::assertTrue($reservas->cancelar($reservaId));
        self::assertFalse($reservas->existeParaTitular($usuarioId, $fecha));
    }

    public function testFamiliarModelSeRelacionaConTitularYReserva(): void
    {
        $titularId = $this->crearUsuario('PENSIONADO');
        $familiares = new Familiar();
        $familiarId = $familiares->crear($titularId, 'Familiar de prueba', 'FAM-' . strtoupper(bin2hex(random_bytes(4))), 'MADRE');

        $familiar = $familiares->findById($familiarId);
        self::assertIsArray($familiar);
        self::assertSame($titularId, (int) $familiar['empleado_titular_id']);
        self::assertSame('ACTIVO', $familiar['estado']);
        self::assertSame('MADRE', $familiar['parentesco']);
        self::assertNotEmpty($familiar['ci']);

        $fecha = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $reservas = new Reserva();
        $reservaId = $reservas->crearParaFamiliar($titularId, $familiarId, $fecha);

        self::assertGreaterThan(0, $reservaId);
        self::assertTrue($reservas->existeParaFamiliar($familiarId, $fecha));
        self::assertSame(1, $reservas->contarConsumosFamiliar(
            $familiarId,
            (int) substr($fecha, 0, 4),
            (int) substr($fecha, 5, 2)
        ));
    }

    public function testCorteEntregaEInasistenciaTienenEstadosYCargosDistintos(): void
    {
        $titularId = $this->crearUsuario('PENSIONADO');
        $modelo = new Reserva();
        $fechaEntrega = '2026-01-05';
        $fechaInasistencia = '2026-01-06';
        $entregaId = $modelo->crearParaTitular($titularId, $fechaEntrega, 'PENSIONADO');
        $inasistenciaId = $modelo->crearParaTitular($titularId, $fechaInasistencia, 'PENSIONADO');

        $modelo->actualizarEstadosAutomaticos($fechaEntrega, '09:00');
        self::assertSame('PENDIENTE_ENTREGA', $modelo->findById($entregaId)['estado']);
        self::assertTrue($modelo->marcarEntregada($entregaId, $fechaEntrega));
        self::assertSame('ENTREGADO', $modelo->findById($entregaId)['estado']);

        $modelo->actualizarEstadosAutomaticos('2026-01-07', '09:00');
        self::assertSame('ENTREGADO', $modelo->findById($entregaId)['estado']);
        self::assertSame('COBRADO_INASISTENCIA', $modelo->findById($inasistenciaId)['estado']);
        self::assertSame(2, $modelo->contarConsumosTitular($titularId, 2026, 1));
    }

    public function testFamiliarModelImponeCiUnico(): void
    {
        $titularId = $this->crearUsuario('PENSIONADO');
        $familiares = new Familiar();
        $ci = 'FAM-' . strtoupper(bin2hex(random_bytes(4)));
        $familiarId = $familiares->crear($titularId, 'Familiar único A', $ci, 'PADRE');

        self::assertTrue($familiares->ciExiste($ci));
        self::assertFalse($familiares->ciExiste($ci, $familiarId));
        $this->expectException(PDOException::class);
        $familiares->crear($titularId, 'Familiar único B', $ci, 'MADRE');
    }

    public function testPensionamientoGeneraReservasMensualesLunesAJuevesYOmiteFeriados(): void
    {
        $titularId = $this->crearUsuario('PENSIONADO');
        $periodo = new DateTimeImmutable('first day of next month');
        $anio = (int) $periodo->format('Y');
        $mes = (int) $periodo->format('n');
        $fechaFeriado = null;
        for ($dia = $periodo; $dia->format('n') === $periodo->format('n'); $dia = $dia->modify('+1 day')) {
            if ((int) $dia->format('N') <= 4) {
                $fechaFeriado = $dia->format('Y-m-d');
                break;
            }
        }
        self::assertNotNull($fechaFeriado);
        (new Calendario())->guardar($fechaFeriado, true, 'Feriado de prueba');

        $pensionamiento = new Pensionamiento();
        $resultado = $pensionamiento->registrar($titularId, null, $anio, $mes, $titularId);
        self::assertTrue($resultado['success']);
        self::assertGreaterThan(0, $resultado['reservas_creadas']);
        self::assertTrue($pensionamiento->estaRegistrado($titularId, null, $anio, $mes));

        $consultar = $this->db->prepare(
            "SELECT fecha_reserva, modalidad FROM reservas
             WHERE usuario_id = :usuario_id AND tipo_beneficiario = 'TITULAR'
               AND fecha_reserva BETWEEN :inicio AND :fin AND estado = 'RESERVADO'"
        );
        $consultar->execute([
            ':usuario_id' => $titularId,
            ':inicio' => $periodo->format('Y-m-d'),
            ':fin' => $periodo->format('Y-m-t'),
        ]);
        $reservas = $consultar->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount($resultado['reservas_creadas'], $reservas);
        foreach ($reservas as $reserva) {
            self::assertLessThanOrEqual(4, (int) (new DateTimeImmutable($reserva['fecha_reserva']))->format('N'));
            self::assertSame('PENSIONADO', $reserva['modalidad']);
            self::assertNotSame($fechaFeriado, $reserva['fecha_reserva']);
        }

        $duplicado = $pensionamiento->registrar($titularId, null, $anio, $mes, $titularId);
        self::assertFalse($duplicado['success']);
    }

    public function testCalendarioSoloAceptaLunesAJuevesYRespetaFeriados(): void
    {
        $lunes = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $sabado = (new DateTimeImmutable('next saturday'))->format('Y-m-d');
        $calendario = new Calendario();

        $calendario->guardar($lunes, false);
        self::assertTrue($calendario->esDiaOperativo($lunes));

        $calendario->guardar($lunes, true, 'Feriado de prueba');
        self::assertFalse($calendario->esDiaOperativo($lunes));
        self::assertFalse($calendario->esDiaOperativo($sabado));
        self::assertFalse($calendario->esDiaOperativo('fecha-invalida'));
    }

    public function testMenuSeGuardaYSeConsultaSoloSiEstaPublicado(): void
    {
        $adminId = $this->crearUsuario('ADMIN');
        $fecha = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $menu = new Menu();

        self::assertTrue($menu->guardar($fecha, 'Sopa de verduras', 'Pollo con arroz', 'Fruta', $adminId));
        $guardado = $menu->findByFecha($fecha);

        self::assertIsArray($guardado);
        self::assertSame('Sopa de verduras', $guardado['sopa']);
        self::assertSame('Pollo con arroz', $guardado['plato_principal']);
        self::assertSame('Fruta', $guardado['postre']);
        self::assertSame('Prueba', explode(' ', $guardado['publicado_por'])[0]);
    }

    public function testUsuarioModelAlmacenaUnaContrasenaVerificable(): void
    {
        $usuario = new Usuario();
        $correo = 'usuario-' . bin2hex(random_bytes(5)) . '@example.test';
        $usuarioId = $usuario->crear([
            'nombre' => 'Usuario integración',
            'apellido' => 'Apellido prueba',
            'ci' => 'CI-' . strtoupper(bin2hex(random_bytes(4))),
            'departamento' => 'Departamento de pruebas',
            'correo' => $correo,
            'contrasena' => 'Clave-Segura-123',
            'tipo_cuenta' => 'NO_PENSIONADO',
            'codigo_empleado' => 'USR-' . bin2hex(random_bytes(4)),
        ]);

        $encontrado = $usuario->findByEmail($correo);
        self::assertIsArray($encontrado);
        self::assertSame($usuarioId, (int) $encontrado['id']);
        self::assertTrue(password_verify('Clave-Segura-123', $encontrado['contrasena']));
        self::assertTrue($usuario->ciExiste($encontrado['ci']));
        self::assertFalse($usuario->ciExiste($encontrado['ci'], $usuarioId));
        self::assertSame('Apellido prueba', $encontrado['apellido']);
        self::assertSame('Departamento de pruebas', $encontrado['departamento']);
    }

    public function testReporteNoSumaDosVecesElMinimoDeUnFamiliarPensionado(): void
    {
        (new Configuracion())->guardar([
            'tarifa_pensionado' => 15.0,
            'tarifa_esporadico' => 18.0,
            'minimo_almuerzos_mes' => 10,
            'hora_limite' => '09:00',
        ]);
        $titularId = $this->crearUsuario('PENSIONADO');
        $familiarId = (new Familiar())->crear(
            $titularId,
            'Familiar para reporte',
            'FAM-' . strtoupper(bin2hex(random_bytes(4))),
            'HIJA'
        );
        $periodo = new DateTimeImmutable('first day of this month');
        $anio = (int) $periodo->format('Y');
        $mes = (int) $periodo->format('n');
        $inscribir = $this->db->prepare(
            'INSERT INTO pensionamientos_mensuales (usuario_id, familiar_id, anio, mes, registrado_por_id)
             VALUES (:usuario_titular, NULL, :anio_titular, :mes_titular, :registrado_titular),
                    (:usuario_familiar, :familiar_id, :anio_familiar, :mes_familiar, :registrado_familiar)'
        );
        $inscribir->execute([
            ':usuario_titular' => $titularId,
            ':anio_titular' => $anio,
            ':mes_titular' => $mes,
            ':registrado_titular' => $titularId,
            ':usuario_familiar' => $titularId,
            ':familiar_id' => $familiarId,
            ':anio_familiar' => $anio,
            ':mes_familiar' => $mes,
            ':registrado_familiar' => $titularId,
        ]);
        $inicio = $periodo->format('Y-m-');
        $reservas = new Reserva();

        for ($dia = 1; $dia <= 7; $dia++) {
            $fecha = $inicio . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
            $reservas->crearParaTitular($titularId, $fecha, 'PENSIONADO');
            $reservas->crearParaFamiliar($titularId, $familiarId, $fecha, 'PENSIONADO');
        }

        $marcarCobrables = $this->db->prepare(
            "UPDATE reservas SET estado = 'COBRADO_INASISTENCIA'
             WHERE fecha_reserva BETWEEN :inicio AND :fin AND usuario_id = :titular_id"
        );
        $marcarCobrables->execute([
            ':inicio' => $inicio . '01',
            ':fin' => $inicio . '07',
            ':titular_id' => $titularId,
        ]);

        $reporte = (new ReporteController())->generarReporteMensual($anio, $mes);
        $codigo = $this->db->query('SELECT codigo_empleado FROM usuarios WHERE id = ' . $titularId)->fetchColumn();
        $fila = array_values(array_filter(
            $reporte['filas'],
            static fn (array $item): bool => $item['codigo_empleado'] === $codigo
        ))[0] ?? null;

        self::assertIsArray($fila);
        self::assertSame(150.0, (float) $fila['monto_titular']);
        self::assertSame(300.0, (float) $fila['total_descuento']);
        self::assertSame(105.0, (float) $fila['detalle_familiares'][0]['monto']);
        self::assertSame(45.0, (float) $fila['detalle_familiares'][0]['penalizacion']);
        self::assertSame(150.0, (float) $fila['detalle_familiares'][0]['total_familiar']);
        self::assertSame(45.0, (float) $fila['penalizacion_familiares']);
    }
}
