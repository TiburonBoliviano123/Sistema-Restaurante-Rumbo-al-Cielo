<?php
$pagina = 'Parámetros del Sistema';
require_once __DIR__ . '/layout.php';
?>

<section class="dashboard-welcome" style="margin-bottom:1.25rem;">
  <div>
    <span class="dashboard-kicker">REGLAS DEL RESTAURANTE</span>
    <h2>Tarifas y horarios</h2>
    <p>Los cambios se aplican a las validaciones y cálculos de los siguientes usos del sistema.</p>
  </div>
  <a class="btn btn-secondary" href="<?= $base ?>/frontend/admin/calendario.php">Administrar feriados</a>
</section>

<div class="card" style="max-width:760px;">
  <div class="card-header"><h3>Configuración vigente</h3></div>
  <div class="card-body">
    <div id="alertConfiguracion"></div>
    <form id="formConfiguracion">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        <div class="form-group">
          <label class="form-label" for="tarifaPensionado">Tarifa pensionado (Bs)</label>
          <input class="form-control" type="number" id="tarifaPensionado" name="tarifa_pensionado" min="0" max="999999.99" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="tarifaEsporadico">Tarifa esporádica (Bs)</label>
          <input class="form-control" type="number" id="tarifaEsporadico" name="tarifa_esporadico" min="0" max="999999.99" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="minimoMensual">Mínimo mensual de pensionados</label>
          <input class="form-control" type="number" id="minimoMensual" name="minimo_almuerzos_mes" min="0" max="31" step="1" required>
          <small class="text-muted">Usa 0 si quieres desactivar el mínimo mensual.</small>
        </div>
        <div class="form-group">
          <label class="form-label" for="horaLimite">Hora límite para reservar/cancelar</label>
          <input class="form-control" type="time" id="horaLimite" name="hora_limite" required>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.75rem;align-items:center;margin-top:.5rem;">
        <span id="estadoActualizacion" class="text-muted" aria-live="polite"></span>
        <button class="btn btn-primary" id="btnGuardarConfiguracion" type="submit" disabled>Guardar parámetros</button>
      </div>
    </form>
  </div>
</div>

<script>
const formConfiguracion = document.getElementById('formConfiguracion');

async function cargarConfiguracion() {
  const res = await apiGet('/backend/api/configuracion.php', { action: 'obtener' });
  if (!res.success) {
    document.getElementById('alertConfiguracion').innerHTML = `<div class="alert alert-danger">${escHtml(res.message || 'No se pudo cargar la configuración.')}</div>`;
    return;
  }
  const valores = res.data;
  formConfiguracion.elements.tarifa_pensionado.value = Number(valores.tarifa_pensionado).toFixed(2);
  formConfiguracion.elements.tarifa_esporadico.value = Number(valores.tarifa_esporadico).toFixed(2);
  formConfiguracion.elements.minimo_almuerzos_mes.value = valores.minimo_almuerzos_mes;
  formConfiguracion.elements.hora_limite.value = valores.hora_limite;
  document.getElementById('btnGuardarConfiguracion').disabled = false;
  document.getElementById('estadoActualizacion').textContent = 'Parámetros cargados';
}

formConfiguracion.addEventListener('submit', async event => {
  event.preventDefault();
  const button = document.getElementById('btnGuardarConfiguracion');
  btnLoading(button, true);
  document.getElementById('alertConfiguracion').innerHTML = '';
  const datos = Object.fromEntries(new FormData(formConfiguracion));
  const res = await apiPost('/backend/api/configuracion.php', { action: 'actualizar', ...datos });
  btnLoading(button, false);
  if (!res.success) {
    document.getElementById('alertConfiguracion').innerHTML = `<div class="alert alert-danger">${escHtml(res.message || 'No se pudieron guardar los parámetros.')}</div>`;
    return;
  }
  Toast.success(res.message);
  await cargarConfiguracion();
});

cargarConfiguracion();
</script>

<?php require_once __DIR__ . '/layout_end.php'; ?>
