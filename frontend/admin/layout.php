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
      <div class="sidebar-brand-lockup">
        <img class="brand-mark brand-mark-image" src="<?= $base ?>/frontend/assets/img/uab-favicon.png" alt="">
        <span><h2>Rumbo al Cielo</h2><p>Restaurante · Universidad Adventista de Bolivia</p></span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-title">Principal</span>
      <a href="<?= $base ?>/frontend/admin/dashboard.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg></span> Dashboard
      </a>
      <a href="<?= $base ?>/frontend/admin/empleados.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.8a4 4 0 0 1 0 7.6m2 4.2a4 4 0 0 1 2 3.5V21"/></svg></span> Empleados
      </a>
      <a href="<?= $base ?>/frontend/admin/familiares.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-9a3 3 0 1 0-2-5.2M17 15a5 5 0 0 1 4 4.8V20"/></svg></span> Familiares
      </a>

      <span class="nav-section-title">Operación</span>
      <a href="<?= $base ?>/frontend/admin/menus.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></span> Menús
      </a>
      <a href="<?= $base ?>/frontend/admin/reservas.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 4h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span> Reservas del Día
      </a>
      <a href="<?= $base ?>/frontend/admin/calendario.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3v3H8z"/></svg></span> Calendario / Feriados
      </a>
      <a href="<?= $base ?>/frontend/admin/configuracion.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1a1.7 1.7 0 1 1-2.4 2.4l-.1-.1a1.7 1.7 0 0 0-2.9 1.2v.3a1.7 1.7 0 1 1-3.4 0v-.2a1.7 1.7 0 0 0-2.9-1.2l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1.2-2.9h-.3a1.7 1.7 0 1 1 0-3.4h.2a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.7 1.7 0 0 0 2.9-1.2v-.3a1.7 1.7 0 1 1 3.4 0v.2a1.7 1.7 0 0 0 2.9 1.2l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.3a1.7 1.7 0 1 1 0 3.4h-.2a1.7 1.7 0 0 0-1.2 2.9Z"/></svg></span> Parámetros
      </a>
      <a href="<?= $base ?>/frontend/admin/pensionamientos.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h8M8 18h5"/></svg></span> Pensionamientos
      </a>

      <span class="nav-section-title">Reportes</span>
      <a href="<?= $base ?>/frontend/admin/reportes.php" class="nav-link">
        <span class="icon"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6"/></svg></span> Reporte Mensual
      </a>
    </nav>

    <div class="sidebar-footer">
      <span class="system-status"><i></i> Sistema operativo</span>
      <div class="sidebar-account"><span class="account-avatar"><?= $inicial ?></span><span><strong><?= htmlspecialchars($nombre) ?></strong><small>Administrador</small></span></div>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="topbar-heading"><span>RUMBO AL CIELO <i>/</i> ADMINISTRACIÓN</span><h1><?= htmlspecialchars($pagina) ?></h1></div>
      </div>
      <div class="topbar-right">
        <div class="user-badge">
          <div class="avatar"><?= $inicial ?></div>
          <span><?= htmlspecialchars($nombre) ?></span>
        </div>
        <button class="btn btn-secondary btn-sm btn-logout" onclick="logout()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg><span>Cerrar sesión</span></button>
      </div>
    </header>
    <main class="page-content">
