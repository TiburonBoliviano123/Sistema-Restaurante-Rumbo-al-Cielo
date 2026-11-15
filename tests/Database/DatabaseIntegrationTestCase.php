<?php

use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected PDO $db;

    public static function setUpBeforeClass(): void
    {
        if (getenv('RUN_DB_INTEGRATION') !== '1') {
            self::markTestSkipped('Activa RUN_DB_INTEGRATION=1 para ejecutar las pruebas MySQL.');
        }

        if (!str_ends_with(DB_NAME, '_test')) {
            self::fail('Seguridad: las pruebas de integración requieren una base cuyo nombre termine en _test.');
        }

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        parent::tearDown();
    }

    protected function crearUsuario(string $tipo = 'NO_PENSIONADO'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, correo, contrasena, tipo_cuenta, codigo_empleado)
             VALUES (:nombre, :correo, :contrasena, :tipo, :codigo)'
        );
        $token = bin2hex(random_bytes(6));
        $stmt->execute([
            ':nombre' => 'Prueba ' . $token,
            ':correo' => 'test-' . $token . '@example.test',
            ':contrasena' => password_hash('Clave-Test-123', PASSWORD_DEFAULT),
            ':tipo' => $tipo,
            ':codigo' => 'TEST-' . strtoupper($token),
        ]);

        return (int) $this->db->lastInsertId();
    }
}
