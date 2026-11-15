<?php
$pagina = 'Reservas del Día';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../../backend/models/Configuracion.php';
$reglas = (new Configuracion())->obtener();
?>

<div class="card">
  <div class="card-header">
    <h3>📅 Reservas por Fecha</h3>
    <div style="display:flex;gap:.5rem;align-items:center;">
      <input type="date" id="fechaConsulta" class="form-control" style="width:180px;" value="<?= date('Y-m-d') ?>">
      <button class="btn btn-primary" onclick="cargarReservas()">Consultar</button>
      <button class="btn btn-secondary" onclick="exportarCSV()">⬇ CSV</button>
    </div>
  </div>
  <div class="card-body" style="padding:0;">
    <div id="resumenReservas" style="padding:1rem;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;gap:2rem;font-size:.88rem;"></div>
    <div id="consolidadoCocina" style="padding:0 1rem;"></div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Titular</th>
            <th>Código</th>
            <th>Beneficiario</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Registro de entrega</th>
            <th>Hora Reserva</th>
          </tr>
        </thead>
        <tbody id="tbodyReservas">
          <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">Selecciona una fecha para ver las reservas.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let reservasData = [];

async function cargarReservas() {
  const fecha = document.getElementById('fechaConsulta').value;
  if (!fecha) { Toast.warning('Selecciona una fecha.'); return; }

  const tbody = document.getElementById('tbodyReservas');
  tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:1.5rem;"><div class="spinner" style="margin:0 auto .4rem;"></div></td></tr>';

  const res = await apiGet('/backend/api/reservas.php', { action: 'listar_por_fecha', fecha });
  reservasData = res.data || [];
  renderConsolidadoCocina(fecha, Boolean(res.consolidado_disponible));

  if (!reservasData.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">Sin reservas para el ${formatDateShort(fecha)}.</td></tr>`;
    document.getElementById('resumenReservas').innerHTML = '';
    return;
  }

  const activas = reservasData.filter(r => ['RESERVADO', 'PENDIENTE_ENTREGA'].includes(r.estado)).length;
  const entregadas = reservasData.filter(r => r.estado === 'ENTREGADO').length;
  const inasistencias = reservasData.filter(r => r.estado === 'COBRADO_INASISTENCIA').length;
  const canceladas = reservasData.filter(r => r.estado === 'CANCELADO').length;
  document.getElementById('resumenReservas').innerHTML = `
    <span>📅 Fecha: <strong>${formatDate(fecha)}</strong></span>
    <span>🍽️ Confirmadas: <strong style="color:var(--success);">${activas}</strong></span>
    <span>✅ Entregadas: <strong>${entregadas}</strong></span>
    <span>⚠ Inasistencias cobradas: <strong>${inasistencias}</strong></span>
    <span>❌ Canceladas: <strong style="color:var(--danger);">${canceladas}</strong></span>
    <span>Total: <strong>${reservasData.length}</strong></span>
  `;

  tbody.innerHTML = reservasData.map((r, i) => `
    <tr>
      <td>${i+1}</td>
      <td><strong>${escHtml(r.nombre_titular)}</strong></td>
      <td><span class="badge badge-muted">${escHtml(r.codigo_empleado || '—')}</span></td>
      <td>${r.tipo_beneficiario === 'FAMILIAR' ? escHtml(r.nombre_familiar || 'Familiar') : escHtml(r.nombre_titular)}</td>
      <td>${r.tipo_beneficiario === 'FAMILIAR'
        ? '<span class="badge badge-info">Familiar</span>'
        : '<span class="badge badge-primary">Titular</span>'}</td>
      <td>${badgeEstadoReserva(r.estado)}</td>
      <td>${r.estado === 'PENDIENTE_ENTREGA'
        ? `<button class="btn btn-success btn-sm" onclick="marcarEntregada(${Number(r.id)})">Marcar entregado</button>`
        : r.estado === 'ENTREGADO' ? '<span class="text-muted">Consumo confirmado</span>'
          : r.estado === 'COBRADO_INASISTENCIA' ? '<span class="text-muted">Sin entrega</span>' : '—'}</td>
      <td style="font-size:.8rem;color:var(--muted);">${r.fecha_creacion ? r.fecha_creacion.split(' ')[1]?.slice(0,5) : '—'}</td>
    </tr>
  `).join('');
}

function renderConsolidadoCocina(fecha, disponible) {
  const panel = document.getElementById('consolidadoCocina');
  if (!disponible) {
    panel.innerHTML = `<div class="alert alert-info" style="margin:1rem 0;">El consolidado final estará disponible desde las <?= htmlspecialchars($reglas['hora_limite']) ?> del día de servicio.</div>`;
    return;
  }

  const cobrables = reservasData.filter(r =>
    ['PENDIENTE_ENTREGA', 'ENTREGADO', 'COBRADO_INASISTENCIA'].includes(r.estado)
  );
  const pensionados = cobrables.filter(r => r.modalidad === 'PENSIONADO').length;
  const esporadicos = cobrables.filter(r => r.modalidad === 'ESPORADICO').length;
  panel.innerHTML = `
    <section class="alert alert-success" style="margin:1rem 0;display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
      <strong>Consolidado de cocina — ${formatDate(fecha)}</strong>
      <span>Raciones a preparar: <strong>${cobrables.length}</strong></span>
      <span>Pensionados: <strong>${pensionados}</strong></span>
      <span>Esporádicos: <strong>${esporadicos}</strong></span>
    </section>`;
}

async function marcarEntregada(reservaId) {
  const res = await apiPost('/backend/api/reservas.php', { action: 'marcar_entregada', reserva_id: reservaId });
  if (!res.success) return Toast.error(res.message || 'No se pudo registrar la entrega.');
  Toast.success(res.message);
  await cargarReservas();
}

function exportarCSV() {
  if (!reservasData.length) { Toast.warning('No hay datos para exportar.'); return; }
  const fecha = document.getElementById('fechaConsulta').value;
  const headers = ['#','Titular','Codigo','Beneficiario','Tipo','Estado','Hora'];
  const rows = reservasData.map((r, i) => [
    i+1,
    r.nombre_titular,
    r.codigo_empleado || '',
    r.tipo_beneficiario === 'FAMILIAR' ? r.nombre_familiar || 'Familiar' : r.nombre_titular,
    r.tipo_beneficiario,
    r.estado,
    r.fecha_creacion ? r.fecha_creacion.split(' ')[1]?.slice(0,5) : '',
  ]);
  const csv = [headers, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `reservas_${fecha}.csv`;
  a.click();
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Cargar reservas de hoy al entrar
cargarReservas();
</script>

<?php require_once __DIR__ . '/layout_end.php'; ?>
