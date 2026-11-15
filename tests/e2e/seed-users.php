<?php

putenv('APP_ENV=test');
require_once __DIR__ . '/../../backend/config/database.php';

if (!str_ends_with(DB_NAME, '_test')) {
    fwrite(STDERR, "Seguridad: solo se permiten usuarios E2E en una base terminada en _test.\n");
    exit(1);
}

$fixtures = [
    ['nombre' => 'Admin', 'apellido' => 'E2E', 'correo' => 'e2e-admin@example.test', 'tipo' => 'ADMIN', 'codigo' => 'E2E-ADMIN', 'ci' => '70000001', 'departamento' => 'Pruebas', 'activo' => 1],
    ['nombre' => 'Empleado', 'apellido' => 'E2E', 'correo' => 'e2e-empleado@example.test', 'tipo' => 'NO_PENSIONADO', 'codigo' => 'E2E-EMPLEADO', 'ci' => '70000002', 'departamento' => 'Pruebas', 'activo' => 1],
    ['nombre' => 'Cuenta inactiva', 'apellido' => 'E2E', 'correo' => 'e2e-inactivo@example.test', 'tipo' => 'NO_PENSIONADO', 'codigo' => 'E2E-INACTIVO', 'ci' => '70000003', 'departamento' => 'Pruebas', 'activo' => 0],
    ['nombre' => 'Titular', 'apellido' => 'E2E', 'correo' => 'e2e-pensionado@example.test', 'tipo' => 'PENSIONADO', 'codigo' => 'E2E-PENSIONADO', 'ci' => '70000004', 'departamento' => 'Pruebas', 'activo' => 1],
];
$passwordHash = password_hash('Prueba-E2E-739!', PASSWORD_BCRYPT);
$db = Database::getConnection();
$db->beginTransaction();

try {
    $db->exec("INSERT INTO configuracion_sistema
                 (id, tarifa_pensionado, tarifa_esporadico, minimo_almuerzos_mes, hora_limite)
               VALUES (1, 15.00, 18.00, 10, '09:00:00')
               ON DUPLICATE KEY UPDATE tarifa_pensionado=VALUES(tarifa_pensionado),
                 tarifa_esporadico=VALUES(tarifa_esporadico), minimo_almuerzos_mes=VALUES(minimo_almuerzos_mes),
                 hora_limite=VALUES(hora_limite)");

    foreach ($fixtures as $fixture) {
        $find = $db->prepare('SELECT id FROM usuarios WHERE correo = :correo LIMIT 1');
        $find->execute([':correo' => $fixture['correo']]);
        $id = $find->fetchColumn();

        if ($id !== false) {
            $update = $db->prepare(
                'UPDATE usuarios SET nombre=:nombre, apellido=:apellido, ci=:ci, departamento=:departamento,
                 contrasena=:contrasena, tipo_cuenta=:tipo, codigo_empleado=:codigo, activo=:activo WHERE id=:id'
            );
            $update->execute([
                ':nombre' => $fixture['nombre'], ':apellido' => $fixture['apellido'],
                ':ci' => $fixture['ci'], ':departamento' => $fixture['departamento'], ':contrasena' => $passwordHash,
                ':tipo' => $fixture['tipo'], ':codigo' => $fixture['codigo'], ':activo' => $fixture['activo'], ':id' => $id,
            ]);
        } else {
            $insert = $db->prepare(
                'INSERT INTO usuarios (nombre, apellido, ci, departamento, correo, contrasena, tipo_cuenta, codigo_empleado, activo)
                 VALUES (:nombre, :apellido, :ci, :departamento, :correo, :contrasena, :tipo, :codigo, :activo)'
            );
            $insert->execute([
                ':nombre' => $fixture['nombre'], ':apellido' => $fixture['apellido'],
                ':ci' => $fixture['ci'], ':departamento' => $fixture['departamento'], ':correo' => $fixture['correo'],
                ':contrasena' => $passwordHash, ':tipo' => $fixture['tipo'], ':codigo' => $fixture['codigo'],
                ':activo' => $fixture['activo'],
            ]);
        }
    }

    $emails = array_column($fixtures, 'correo');
    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $idsQuery = $db->prepare("SELECT id FROM usuarios WHERE correo IN ($placeholders)");
    $idsQuery->execute($emails);
    $fixtureIds = array_map('intval', $idsQuery->fetchAll(PDO::FETCH_COLUMN));
    if ($fixtureIds) {
        $idPlaceholders = implode(',', array_fill(0, count($fixtureIds), '?'));
        $db->prepare("DELETE FROM reservas WHERE usuario_id IN ($idPlaceholders)")->execute($fixtureIds);
        $db->prepare("DELETE FROM pensionamientos_mensuales
                      WHERE usuario_id IN ($idPlaceholders) OR registrado_por_id IN ($idPlaceholders)")
            ->execute([...$fixtureIds, ...$fixtureIds]);
        $db->prepare("DELETE FROM familiares WHERE empleado_titular_id IN ($idPlaceholders)")->execute($fixtureIds);
    }

    $titular = $db->prepare("SELECT id FROM usuarios WHERE correo = 'e2e-pensionado@example.test' LIMIT 1");
    $titular->execute();
    $titularId = $titular->fetchColumn();
    if ($titularId !== false) {
        $limpiarFamiliares = $db->prepare('DELETE FROM familiares WHERE empleado_titular_id = :titular_id');
        $limpiarFamiliares->execute([':titular_id' => $titularId]);
    }

    $hoy = new DateTimeImmutable('today');
    if ((int) $hoy->format('N') <= 4) {
        $empleado = $db->prepare("SELECT id FROM usuarios WHERE correo = 'e2e-empleado@example.test' LIMIT 1");
        $empleado->execute();
        $empleadoId = $empleado->fetchColumn();
        if ($empleadoId !== false) {
            $reservaHoy = $db->prepare(
                "INSERT INTO reservas (usuario_id, familiar_id, tipo_beneficiario, fecha_reserva, estado, modalidad)
                 VALUES (:usuario_id, NULL, 'TITULAR', :fecha, 'RESERVADO', 'ESPORADICO')"
            );
            $reservaHoy->execute([':usuario_id' => $empleadoId, ':fecha' => $hoy->format('Y-m-d')]);
        }
    }

    $db->commit();
    echo "Cuentas E2E listas en " . DB_NAME . ". Credenciales solo para pruebas: e2e-admin@example.test / Prueba-E2E-739! y e2e-empleado@example.test / Prueba-E2E-739!\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'No se pudieron preparar las cuentas E2E: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
