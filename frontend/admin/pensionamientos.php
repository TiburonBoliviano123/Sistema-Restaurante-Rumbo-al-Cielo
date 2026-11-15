<?php
$pagina = 'Pensionamientos Mensuales';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../../backend/models/Configuracion.php';
$reglas = (new Configuracion())->obtener();
$periodo = new DateTimeImmutable('first day of next month');
$mes = (int) $periodo->format('n');
$anio = (int) $periodo->format('Y');
$meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
?>

<div class="alert alert-info">
  Inscribe al empleado antes de que comience <strong><?= ucfirst($meses[$mes]) ?> de <?= $anio ?></strong>.
  El sistema confirma automáticamente sus almuerzos de lunes a jueves, excepto feriados, a Bs <?= number_format($reglas['tarifa_pensionado'], 2, '.', '') ?> por día.
</div>

<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-header"><h3>Seleccionar empleado</h3><span class="badge badge-info"><?= ucfirst($meses[$mes]) ?> <?= $anio ?></span></div>
  <div class="card-body">
    <label class="form-label" for="selectTitularPension">Empleado titular</label>
    <select id="selectTitularPension" class="form-control" onchange="seleccionarTitular(this.value)">
      <option value="">Cargando empleados...</option>
    </select>
  </div>
</div>

<div id="pensionamientoTitularPanel" class="card" style="display:none;margin-bottom:1.25rem;">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <div><h3 id="nombreTitularPension">Titular</h3><p class="text-muted">El pensionamiento genera las reservas automáticas del mes seleccionado.</p></div>
    <button class="btn btn-primary" id="btnAdminPensionarTitular" onclick="pensionarTitular()">Registrar titular</button>
  </div>
</div>

<div id="pensionamientoFamiliaresPanel" class="card" style="display:none;">
  <div class="card-header"><h3>Familiares pensionables</h3></div>
  <div class="card-body" id="tablaFamiliaresPension"></div>
</div>

<script>
const periodoAdmin = { anio: <?= $anio ?>, mes: <?= $mes ?> };
let empleadosPension = [];
let titularPensionActual = null;
let familiaresPension = [];
let inscritosPension = [];

async function cargarEmpleadosPensionables() {
  const select = document.getElementById('selectTitularPension');
  const res = await apiGet('/backend/api/empleados.php', { action: 'listar' });
  if (!res.success) {
    select.innerHTML = '<option value="">No se pudieron cargar empleados</option>';
    return;
  }
  empleadosPension = res.data || [];
  select.innerHTML = '<option value="">Selecciona un empleado</option>' + empleadosPension.map(e =>
    `<option value="${Number(e.id)}">${escHtml(e.nombre_completo)} · ${escHtml(e.codigo_empleado || 'Sin código')} · ${e.tipo_cuenta === 'PENSIONADO' ? 'Pensionado' : 'No pensionado'}</option>`
  ).join('');
}

async function seleccionarTitular(id) {
  titularPensionActual = empleadosPension.find(e => Number(e.id) === Number(id)) || null;
  const titularPanel = document.getElementById('pensionamientoTitularPanel');
  const familiaPanel = document.getElementById('pensionamientoFamiliaresPanel');
  if (!titularPensionActual) {
    titularPanel.style.display = 'none';
    familiaPanel.style.display = 'none';
    return;
  }
  titularPanel.style.display = '';
  document.getElementById('nombreTitularPension').textContent = titularPensionActual.nombre_completo;
  await cargarEstadoPensionamiento();
  if (titularPensionActual.tipo_cuenta === 'PENSIONADO') {
    familiaPanel.style.display = '';
    await cargarFamiliaresPensionables();
  } else {
    familiaPanel.style.display = 'none';
  }
}

async function cargarEstadoPensionamiento() {
  const res = await apiGet('/backend/api/pensionamientos.php', {
    action: 'listar', titular_id: titularPensionActual.id,
    anio: periodoAdmin.anio, mes: periodoAdmin.mes
  });
  inscritosPension = res.data || [];
  const registrado = inscritosPension.some(item => item.tipo_beneficiario === 'TITULAR');
  const button = document.getElementById('btnAdminPensionarTitular');
  button.disabled = registrado;
  button.textContent = registrado ? 'Titular ya registrado este mes' : 'Registrar titular';
}

async function cargarFamiliaresPensionables() {
  const res = await apiGet('/backend/api/familiares.php', {
    action: 'listar', titular_id: titularPensionActual.id
  });
  familiaresPension = res.data || [];
  const panel = document.getElementById('tablaFamiliaresPension');
  const activos = familiaresPension.filter(f => f.estado === 'ACTIVO');
  if (!activos.length) {
    panel.innerHTML = '<p class="text-muted">No hay familiares activos para este titular.</p>';
    return;
  }
  panel.innerHTML = `<div class="table-responsive"><table class="table"><thead><tr><th>Familiar</th><th>CI</th><th>Parentesco</th><th>Estado mensual</th><th>Acción</th></tr></thead><tbody>
    ${activos.map(f => {
      const inscrito = inscritosPension.some(item => Number(item.familiar_id) === Number(f.id));
      return `<tr><td>${escHtml(f.nombre)}</td><td>${escHtml(f.ci || 'Pendiente')}</td><td>${escHtml(etiquetaParentesco(f.parentesco))}</td>
        <td>${inscrito ? '<span class="badge badge-success">Pensionado</span>' : 'No inscrito'}</td>
        <td>${inscrito ? '<span class="text-muted">Confirmado</span>' : `<button class="btn btn-primary btn-sm" onclick="pensionarFamiliar(${Number(f.id)})">Registrar para el mes</button>`}</td></tr>`;
    }).join('')}
    </tbody></table></div>`;
}

async function pensionarTitular() {
  if (!titularPensionActual || !confirmar(`¿Registrar a ${titularPensionActual.nombre_completo} y crear sus reservas automáticas?`)) return;
  const button = document.getElementById('btnAdminPensionarTitular');
  btnLoading(button, true);
  const res = await apiPost('/backend/api/pensionamientos.php', {
    action: 'registrar_titular', titular_id: titularPensionActual.id,
    anio: periodoAdmin.anio, mes: periodoAdmin.mes,
  });
  btnLoading(button, false);
  if (!res.success) return Toast.error(res.message || 'No se pudo registrar.');
  Toast.success(`${res.message} (${res.reservas_creadas} reservas nuevas).`);
  await seleccionarTitular(titularPensionActual.id);
}

async function pensionarFamiliar(id) {
  const familiar = familiaresPension.find(f => Number(f.id) === Number(id));
  if (!familiar || !confirmar(`¿Pensionar a ${familiar.nombre} para el próximo mes y crear sus reservas automáticas?`)) return;
  const res = await apiPost('/backend/api/pensionamientos.php', {
    action: 'registrar_familiar', titular_id: titularPensionActual.id,
    familiar_id: id, anio: periodoAdmin.anio, mes: periodoAdmin.mes,
  });
  if (!res.success) return Toast.error(res.message || 'No se pudo registrar.');
  Toast.success(`${res.message} (${res.reservas_creadas} reservas nuevas).`);
  await seleccionarTitular(titularPensionActual.id);
}

function etiquetaParentesco(valor) {
  return ({ PADRE: 'Padre', MADRE: 'Madre', HIJO: 'Hijo', HIJA: 'Hija', CONYUGE: 'Cónyuge' })[valor] || '—';
}

cargarEmpleadosPensionables();
</script>

<?php require_once __DIR__ . '/layout_end.php'; ?>
