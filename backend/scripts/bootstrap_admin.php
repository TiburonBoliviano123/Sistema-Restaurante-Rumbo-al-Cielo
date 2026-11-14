<?php
/** Create the first administrator from local environment variables. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$nombre = trim((string) getenv('BOOTSTRAP_ADMIN_NAME'));
$correo = trim((string) getenv('BOOTSTRAP_ADMIN_EMAIL'));
$password = (string) getenv('BOOTSTRAP_ADMIN_PASSWORD');

if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Define BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_EMAIL y una BOOTSTRAP_ADMIN_PASSWORD de al menos 12 caracteres.\n");
    exit(1);
}

try {
    $db = Database::getConnection();
    $admins = (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo_cuenta = 'ADMIN'")->fetchColumn();
    if ($admins > 0) {
        fwrite(STDERR, "Ya existe un administrador; no se creó otra cuenta.\n");
        exit(1);
    }

    $stmt = $db->prepare(
        "INSERT INTO usuarios (nombre, correo, contrasena, tipo_cuenta, activo)
         VALUES (:nombre, :correo, :contrasena, 'ADMIN', 1)"
    );
    $stmt->execute([
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':contrasena' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    fwrite(STDOUT, "Administrador creado correctamente.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear el administrador: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    unset($password);
}
