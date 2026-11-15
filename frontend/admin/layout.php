<?php
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/config/session.php';
requireRole('ADMIN');

$pagina  = $pagina ?? 'Dashboard';
$nombre  = $_SESSION['nombre'] ?? 'Admin';
$inicial = strtoupper(mb_substr($nombre, 0, 1));
$base    = rtrim(BASE_URL, '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pagina) ?> — Admin · Rumbo al Cielo</title>
  <link rel="stylesheet" href="<?= $base ?>/frontend/assets/css/main.css">
  <!-- BASE_URL y app.js van en el <head> para que estén disponibles
       antes de que cualquier <script> inline de la página se ejecute -->
  <script>const BASE_URL = <?= json_encode($base) ?>;</script>
  <script src="<?= $base ?>/frontend/assets/js/app.js"></script>
</head>
<body>
<div class="app-wrapper">

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.15);
                  display:grid;place-items:center;font-size:1.8rem;margin:0 auto .5rem;">🍽️</div>
      <h2>Rumbo al Cielo</h2>
      <p>Panel Administrador</p>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-title">Principal</span>
      <a href="<?= $base ?>/frontend/admin/dashboard.php" class="nav-link">
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="<?= $base ?>/frontend/admin/empleados.php" class="nav-link">
        <span class="icon">👥</span> Empleados
      </a>
      <a href="<?= $base ?>/frontend/admin/familiares.php" class="nav-link">
        <span class="icon">👨‍👩‍👧</span> Familiares
      </a>

      <span class="nav-section-title">Operación</span>
      <a href="<?= $base ?>/frontend/admin/menus.php" class="nav-link">
        <span class="icon">📋</span> Menús
      </a>
      <a href="<?= $base ?>/frontend/admin/cocina.php" class="nav-link"><span class="icon">🍲</span> Control de Cocina</a>
      <a href="<?= $base ?>/frontend/admin/reservas.php" class="nav-link">
        <span class="icon">📅</span> Reservas del Día
      </a>
      <a href="<?= $base ?>/frontend/admin/configuracion.php" class="nav-link"><span class="icon">⚙️</span> Configuración</a>
      <a href="<?= $base ?>/frontend/admin/calendario.php" class="nav-link">
        <span class="icon">🗓️</span> Calendario / Feriados
      </a>

      <span class="nav-section-title">Reportes</span>
      <a href="<?= $base ?>/frontend/admin/reportes.php" class="nav-link">
        <span class="icon">📈</span> Reporte Mensual
      </a>
    </nav>

    <div class="sidebar-footer">
      <strong><?= htmlspecialchars($nombre) ?></strong><br>
      Administrador del sistema
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menú">☰</button>
        <h1><?= htmlspecialchars($pagina) ?></h1>
      </div>
      <div class="topbar-right">
        <div class="user-badge">
          <div class="avatar"><?= $inicial ?></div>
          <span><?= htmlspecialchars($nombre) ?></span>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="logout()">Salir</button>
      </div>
    </header>
    <main class="page-content">
