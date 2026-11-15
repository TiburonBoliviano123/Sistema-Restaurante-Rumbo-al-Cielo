<?php

putenv('APP_ENV=test');
require_once __DIR__ . '/../backend/config/database.php';

if (!str_ends_with(DB_NAME, '_test')) {
    fwrite(STDERR, "Seguridad: TEST_DB_NAME debe terminar en _test. No se creó ninguna base.\n");
    exit(1);
}

try {
    $server = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quotedDatabase = '`' . str_replace('`', '``', DB_NAME) . '`';
    $server->exec('CREATE DATABASE IF NOT EXISTS ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $db = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No se pudo leer database/schema.sql');
    }

    $schema = preg_replace('/^\s*--.*$/m', '', $schema);
    $schema = preg_replace('/\A\s*CREATE DATABASE IF NOT EXISTS[^;]+;\s*USE\s+[^;]+;\s*/i', '', $schema);
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $db->exec($statement);
    }

    $columns = $db->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN, 0);
    $missingColumns = [
        'apellido' => 'VARCHAR(100) NULL DEFAULT NULL AFTER nombre',
        'ci' => 'VARCHAR(20) NULL DEFAULT NULL AFTER apellido',
        'departamento' => 'VARCHAR(100) NULL DEFAULT NULL AFTER ci',
    ];
    foreach ($missingColumns as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $db->exec('ALTER TABLE usuarios ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $indexes = $db->query('SHOW INDEX FROM usuarios')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('uq_usuarios_ci', $indexes, true)) {
        $db->exec('ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_ci (ci)');
    }

    $familyColumns = $db->query('SHOW COLUMNS FROM familiares')->fetchAll(PDO::FETCH_COLUMN, 0);
    $missingFamilyColumns = [
        'ci' => 'VARCHAR(20) NULL DEFAULT NULL AFTER nombre',
        'parentesco' => "ENUM('PADRE','MADRE','HIJO','HIJA','CONYUGE') NULL DEFAULT NULL AFTER ci",
    ];
    foreach ($missingFamilyColumns as $column => $definition) {
        if (!in_array($column, $familyColumns, true)) {
            $db->exec('ALTER TABLE familiares ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $menuColumns = $db->query('SHOW COLUMNS FROM menus')->fetchAll(PDO::FETCH_COLUMN, 0);
    $missingMenuColumns = [
        'sopa' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER fecha",
        'plato_principal' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER sopa",
        'postre' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER plato_principal",
    ];
    foreach ($missingMenuColumns as $column => $definition) {
        if (!in_array($column, $menuColumns, true)) {
            $db->exec('ALTER TABLE menus ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $familyIndexes = $db->query('SHOW INDEX FROM familiares')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('uq_familiares_ci', $familyIndexes, true)) {
        $db->exec('ALTER TABLE familiares ADD UNIQUE KEY uq_familiares_ci (ci)');
    }

    $reservationColumns = $db->query('SHOW COLUMNS FROM reservas')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('modalidad', $reservationColumns, true)) {
        $db->exec("ALTER TABLE reservas ADD COLUMN modalidad ENUM('PENSIONADO','ESPORADICO') NOT NULL DEFAULT 'ESPORADICO' AFTER estado");
        $db->exec("UPDATE reservas r JOIN usuarios u ON u.id = r.usuario_id
                   SET r.modalidad = IF(u.tipo_cuenta = 'PENSIONADO', 'PENSIONADO', 'ESPORADICO')");
    }

    $db->exec("ALTER TABLE reservas MODIFY COLUMN estado
        ENUM('RESERVADO','CANCELADO','PENDIENTE_ENTREGA','ENTREGADO','COBRADO_INASISTENCIA')
        NOT NULL DEFAULT 'RESERVADO'");

    echo "Base de pruebas lista: " . DB_NAME . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo preparar la base de pruebas: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
