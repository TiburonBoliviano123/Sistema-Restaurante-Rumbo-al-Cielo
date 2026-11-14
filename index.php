<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/config/session.php';

$base = rtrim(BASE_URL, '/');

// Si ya está logueado, redirigir al panel correspondiente
if (isLoggedIn()) {
    $rol = rolActual();
    if ($rol === 'ADMIN') {
        header('Location: ' . $base . '/frontend/admin/dashboard.php');
    } else {
        header('Location: ' . $base . '/frontend/empleado/dashboard.php');
    }
    exit;
}

$error = htmlspecialchars($_GET['error'] ?? '', ENT_QUOTES, 'UTF-8');
$errorMsg = match ($error) {
    'acceso_denegado' => 'No tienes permiso para acceder a esa sección.',
    default => '',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#102b46">
  <meta name="description" content="Sistema de reservas de almuerzos de Rumbo al Cielo.">
  <title>Iniciar Sesión — Restaurante Rumbo al Cielo</title>
  <link rel="stylesheet" href="<?= $base ?>/frontend/assets/css/main.css">
</head>
<body>
<div class="login-page">
  <main class="login-shell">
    <section class="login-story" aria-label="Información del restaurante">
      <a class="login-brand" href="<?= $base ?>/index.php" aria-label="Rumbo al Cielo, inicio">
        <img class="uab-brand-mark" src="<?= $base ?>/frontend/assets/img/uab-favicon.png" alt="">
        <span><strong>Rumbo al Cielo</strong><small>RESTAURANTE · UNIVERSIDAD ADVENTISTA DE BOLIVIA</small></span>
      </a>

      <div class="login-story-copy">
        <span class="story-eyebrow"><i></i> SERVICIO DE ALMUERZOS</span>
        <h1>Tu almuerzo,<br><em>bien organizado.</em></h1>
        <p>Consulta el menú, reserva con tiempo y lleva el control de tus almuerzos desde un solo lugar.</p>
      </div>

      <div class="story-visual" aria-hidden="true">
        <div class="visual-glow"></div>
        <div class="plate plate-back"></div>
        <div class="plate plate-main"><span class="plate-ring"></span><span class="plate-food food-one"></span><span class="plate-food food-two"></span><span class="plate-food food-three"></span><span class="plate-leaf">✦</span></div>
        <div class="visual-label"><span class="visual-label-icon">✦</span><span><strong>Menú semanal</strong><small>De lunes a jueves</small></span></div>
        <span class="visual-spark spark-one">✳</span><span class="visual-spark spark-two">✦</span>
      </div>

      <div class="story-footer"><span>Universidad Adventista de Bolivia · Vinto, Cochabamba</span><span>01 — 04 <i></i> LUN — JUE</span></div>
    </section>

    <section class="login-content" aria-labelledby="loginTitle">
      <div class="login-card">
        <div class="login-mobile-brand"><img class="uab-brand-mark" src="<?= $base ?>/frontend/assets/img/uab-favicon.png" alt=""><strong>Rumbo al Cielo <small>Restaurante UAB</small></strong></div>
        <div class="form-heading">
          <span class="form-kicker">ACCESO AL SISTEMA</span>
          <h2 id="loginTitle">Bienvenido/a</h2>
          <p>Ingresa tus datos para continuar.</p>
        </div>

        <?php if ($errorMsg): ?>
          <div class="alert alert-danger" role="alert"><?= $errorMsg ?></div>
        <?php endif; ?>

        <div id="loginAlert" aria-live="polite"></div>

        <form id="loginForm">
          <div class="form-group">
            <label class="form-label" for="correo">Correo electrónico</label>
            <div class="login-input-wrap"><span class="input-prefix" aria-hidden="true">✉</span>
              <input type="email" id="correo" name="correo" class="form-control"
                     placeholder="nombre@correo.com" required autocomplete="email">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="contrasena">Contraseña</label>
            <div class="login-input-wrap"><span class="input-prefix" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 5v2"/></svg></span>
              <input type="password" id="contrasena" name="contrasena" class="form-control"
                     placeholder="Escribe tu contraseña" required autocomplete="current-password">
              <button type="button" id="togglePwd" class="password-toggle" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
            </div>
          </div>
          <button type="submit" id="btnLogin" class="btn btn-primary w-100 btn-lg login-submit">
            <span>Ingresar al sistema</span><span class="submit-arrow" aria-hidden="true">→</span>
          </button>
        </form>

        <div class="login-card-footer"><span class="secure-mark">✓</span><span>Acceso seguro para personal autorizado</span></div>
      </div>
      <p class="login-copyright">Sistema de Reservas de Almuerzos <span>·</span> v1.0</p>
    </section>
  </main>
</div>

<script>
  // BASE_URL disponible para app.js antes de que cargue
  const BASE_URL = <?= json_encode($base) ?>;
</script>
<script src="<?= $base ?>/frontend/assets/js/app.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function() {
  const input = document.getElementById('contrasena');
  input.type  = input.type === 'password' ? 'text' : 'password';
  this.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
  this.setAttribute('aria-label', input.type === 'text' ? 'Ocultar contraseña' : 'Mostrar contraseña');
});

document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn     = document.getElementById('btnLogin');
  const alertEl = document.getElementById('loginAlert');
  alertEl.innerHTML = '';
  btnLoading(btn, true);

  const res = await apiPost('/backend/api/auth.php', {
    action:     'login',
    correo:     document.getElementById('correo').value.trim(),
    contrasena: document.getElementById('contrasena').value,
  });

  if (res.success) {
    alertEl.innerHTML = `<div class="alert alert-success">✓ ${res.message}</div>`;
    setTimeout(() => { window.location.href = res.redirect; }, 700);
  } else {
    alertEl.innerHTML = `<div class="alert alert-danger">⚠ ${res.message}</div>`;
    btnLoading(btn, false);
  }
});
</script>
</body>
</html>
