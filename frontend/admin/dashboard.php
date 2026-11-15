<?php
$pagina = 'Dashboard';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../../backend/config/database.php';

// Stats rápidas
$db = Database::getConnection();

$hoy = date('Y-m-d');
$diasSemana = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
$fechaLegible = ucfirst($diasSemana[(int) date('N')] . ' ' . date('d/m/Y'));
$mes = date('m');
$anio = date('Y');

// Empleados activos
$totalEmpleados = $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo_cuenta != 'ADMIN' AND activo=1")->fetchColumn();
// Familiares activos
$totalFamiliares = $db->query("SELECT COUNT(*) FROM familiares WHERE estado='ACTIVO'")->fetchColumn();
// Reservas de hoy
$stmt = $db->prepare("SELECT COUNT(*) FROM reservas WHERE fecha_reserva=:h AND estado <> 'CANCELADO'");
$stmt->execute([':h' => $hoy]);
$reservasHoy = $stmt->fetchColumn();
// Reservas del mes
$stmt2 = $db->prepare("SELECT COUNT(*) FROM reservas WHERE MONTH(fecha_reserva)=:m AND YEAR(fecha_reserva)=:a AND estado <> 'CANCELADO'");
$stmt2->execute([':m' => $mes, ':a' => $anio]);
$reservasMes = $stmt2->fetchColumn();
// Menú de hoy
$stmtM = $db->prepare("SELECT sopa, plato_principal, postre, descripcion_menu FROM menus WHERE fecha=:h AND publicado=1");
$stmtM->execute([':h' => $hoy]);
$menuHoy = $stmtM->fetch();
// Feriados próximos
$stmtF = $db->prepare("SELECT fecha, descripcion_feriado FROM calendario_operativo WHERE es_feriado=1 AND fecha >= :h ORDER BY fecha ASC LIMIT 3");
$stmtF->execute([':h' => $hoy]);
$feriadosProximos = $stmtF->fetchAll();
// Últimas reservas
$ultimasReservas = $db->prepare("SELECT r.fecha_reserva, r.tipo_beneficiario, r.estado, CONCAT_WS(' ', NULLIF(u.nombre, ''), NULLIF(u.apellido, '')) AS titular, f.nombre AS familiar FROM reservas r JOIN usuarios u ON u.id=r.usuario_id LEFT JOIN familiares f ON f.id=r.familiar_id ORDER BY r.fecha_creacion DESC LIMIT 8");
$ultimasReservas->execute();
$ultimasReservas = $ultimasReservas->fetchAll();
?>

<section class="dashboard-welcome">
  <div>
    <span class="dashboard-kicker">PANEL DE ADMINISTRACIÓN</span>
    <h2>Resumen de operaciones</h2>
    <p>Controla reservas, menús y actividad del restaurante desde aquí.</p>
  </div>
  <div class="welcome-date"><span>HOY</span><strong><?= date('d') ?></strong><small><?= date('m/Y') ?></small></div>
</section>

<!-- Indicadores principales -->
<div class="stats-grid">
  <article class="stat-card">
    <div class="stat-icon icon-blue"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.8a4 4 0 0 1 0 7.6m2 4.2a4 4 0 0 1 2 3.5V21"/></svg></div>
    <div class="stat-info">
      <h4><?= $totalEmpleados ?></h4>
      <p>Empleados Activos</p>
    </div>
  </article>
  <article class="stat-card">
    <div class="stat-icon icon-purple"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-9a3 3 0 1 0-2-5.2M17 15a5 5 0 0 1 4 4.8V20"/></svg></div>
    <div class="stat-info">
      <h4><?= $totalFamiliares ?></h4>
      <p>Familiares Registrados</p>
    </div>
  </article>
  <article class="stat-card">
    <div class="stat-icon icon-green"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 4h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></div>
    <div class="stat-info">
      <h4><?= $reservasHoy ?></h4>
      <p>Reservas Hoy</p>
    </div>
  </article>
  <article class="stat-card">
    <div class="stat-icon icon-orange"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6"/></svg></div>
    <div class="stat-info">
      <h4><?= $reservasMes ?></h4>
      <p>Reservas Este Mes</p>
    </div>
  </article>
</div>

<!-- Fila principal -->
<div class="dashboard-grid dashboard-grid-admin">

  <!-- Menú del día + Reservas recientes -->
  <div>
    <!-- Menú de hoy -->
    <div class="menu-day-card mb-2">
      <div class="fecha-label"><span class="label-dot"></span> Menú de hoy <i><?= htmlspecialchars($fechaLegible) ?></i></div>
      <?php if ($menuHoy): ?>
        <h2>Almuerzo del día</h2>
        <p class="menu-desc">
          <?php if ($menuHoy['sopa'] !== '' || $menuHoy['plato_principal'] !== '' || $menuHoy['postre'] !== ''): ?>
            Sopa: <?= htmlspecialchars($menuHoy['sopa']) ?><br>
            Plato principal: <?= htmlspecialchars($menuHoy['plato_principal']) ?><br>
            Postre: <?= htmlspecialchars($menuHoy['postre']) ?>
          <?php else: ?>
            <?= nl2br(htmlspecialchars($menuHoy['descripcion_menu'])) ?>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <h2>Sin menú publicado</h2>
        <p class="menu-desc" style="opacity:.7;">
          <a href="<?= $base ?>/frontend/admin/menus.php" style="color:#fff;text-decoration:underline;">Publicar menú de hoy →</a>
        </p>
      <?php endif; ?>
    </div>

    <!-- Últimas reservas -->
    <div class="card">
      <div class="card-header">
        <h3>Últimas Reservas</h3>
        <a href="<?= $base ?>/frontend/admin/reservas.php" class="btn btn-secondary btn-sm">Ver todas</a>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if ($ultimasReservas): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Titular</th>
                  <th>Para</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ultimasReservas as $r): ?>
                  <tr>
                    <td><?= date('d/m/Y', strtotime($r['fecha_reserva'])) ?></td>
                    <td><?= htmlspecialchars($r['titular']) ?></td>
                    <td>
                      <?php if ($r['tipo_beneficiario'] === 'FAMILIAR'): ?>
                        <span class="badge badge-info"><?= htmlspecialchars($r['familiar'] ?? 'Familiar') ?></span>
                      <?php else: ?>
                        <span class="badge badge-primary">Titular</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                      $estadoReserva = [
                          'RESERVADO' => ['success', 'Reservado'],
                          'CANCELADO' => ['danger', 'Cancelado (sin cobro)'],
                          'PENDIENTE_ENTREGA' => ['info', 'Confirmado al corte'],
                          'ENTREGADO' => ['success', 'Entregado (cobrable)'],
                          'COBRADO_INASISTENCIA' => ['warning', 'Inasistencia cobrada'],
                      ][$r['estado']] ?? ['muted', 'Estado desconocido'];
                      ?>
                      <span class="badge badge-<?= $estadoReserva[0] ?>"><?= htmlspecialchars($estadoReserva[1]) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 14h5l1.5 2h5L16 14h5"/><path d="M7 9h.01M12 9h.01M17 9h.01"/></svg></div>
            <h4>Sin reservas aún</h4>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Panel lateral -->
  <div>
    <!-- Accesos rápidos -->
    <div class="card mb-2">
      <div class="card-header"><h3>Acciones Rápidas</h3></div>
      <div class="card-body quick-actions">
        <a href="<?= $base ?>/frontend/admin/menus.php" class="quick-action"><span class="quick-action-icon"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></span><span><strong>Publicar menú</strong><small>Preparar la semana</small></span><b>→</b></a>
        <a href="<?= $base ?>/frontend/admin/empleados.php" class="quick-action"><span class="quick-action-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-3v6m-3-3h6"/></svg></span><span><strong>Registrar empleado</strong><small>Crear una cuenta</small></span><b>→</b></a>
        <a href="<?= $base ?>/frontend/admin/calendario.php" class="quick-action"><span class="quick-action-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3v3H8z"/></svg></span><span><strong>Gestionar feriados</strong><small>Actualizar calendario</small></span><b>→</b></a>
        <a href="<?= $base ?>/frontend/admin/reportes.php" class="quick-action"><span class="quick-action-icon"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6"/></svg></span><span><strong>Generar reporte</strong><small>Consultar el mes</small></span><b>→</b></a>
      </div>
    </div>

    <!-- Feriados próximos -->
    <div class="card">
      <div class="card-header"><h3>Feriados Próximos</h3></div>
      <div class="card-body" style="padding:0;">
        <?php if ($feriadosProximos): ?>
          <?php foreach ($feriadosProximos as $f): ?>
            <div class="reserva-item">
              <div>
                <div class="fecha"><?= date('d/m/Y', strtotime($f['fecha'])) ?></div>
                <div class="tipo"><?= htmlspecialchars($f['descripcion_feriado'] ?? 'Feriado') ?></div>
              </div>
              <span class="badge badge-warning">Feriado</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted text-center" style="padding:1rem;font-size:.85rem;">Sin feriados próximos</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/layout_end.php'; ?>
