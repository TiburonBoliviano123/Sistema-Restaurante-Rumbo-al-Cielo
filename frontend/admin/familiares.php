<?php
$pagina = 'Gestión de Familiares';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../../backend/config/database.php';

// Traer solo pensionados para el selector
$db = Database::getConnection();
$pensionados = $db->query(
    "SELECT id, nombre, apellido, CONCAT_WS(' ', NULLIF(nombre, ''), NULLIF(apellido, '')) AS nombre_completo, codigo_empleado FROM usuarios WHERE tipo_cuenta='PENSIONADO' AND activo=1 ORDER BY nombre ASC, apellido ASC"
)->fetchAll();
?>

<div style="display:grid;grid-template-columns:1fr 1.8fr;gap:1.5rem;">

  <!-- Panel izquierdo: selector titular -->
  <div class="card">
    <div class="card-header"><h3>Seleccionar Titular</h3></div>
    <div class="card-body" style="padding:0;max-height:70vh;overflow-y:auto;">
      <?php if ($pensionados): ?>
        <?php foreach ($pensionados as $p): ?>
          <button class="nav-link w-100" style="text-align:left;border:none;background:none;padding:.75rem 1rem;cursor:pointer;"
                  onclick="seleccionarTitular(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nombre_completo'])) ?>')">
            <strong><?= htmlspecialchars($p['nombre_completo']) ?></strong>
            <br><small class="text-muted"><?= htmlspecialchars($p['codigo_empleado'] ?? '—') ?></small>
          </button>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">👥</div>
          <h4>Sin empleados pensionados</h4>
          <p>Registra empleados pensionados primero.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Panel derecho: familiares del titular -->
  <div class="card">
    <div class="card-header">
      <h3 id="titularNombre">Selecciona un titular</h3>
      <button class="btn btn-primary btn-sm" id="btnAgregar" style="display:none;" onclick="nuevoFamiliar()">
        + Agregar Familiar
      </button>
    </div>
    <div class="card-body" style="padding:0;">
      <div id="familiaresPanelInner">
        <div class="empty-state">
          <div class="empty-icon">👈</div>
          <h4>Selecciona un titular de la lista</h4>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Modal agregar familiar -->
<div class="modal-overlay" id="modalAgregarFam">
  <div class="modal">
    <div class="modal-header">
      <h4>Agregar Familiar</h4>
      <button class="btn-close" onclick="closeModal('modalAgregarFam')">×</button>
    </div>
    <div class="modal-body">
      <div id="alertAgregarFam"></div>
      <input type="hidden" id="famTitularId">
      <input type="hidden" id="famId">
      <div class="form-group">
        <label class="form-label" for="famNombre">Nombre completo *</label>
        <input type="text" id="famNombre" class="form-control" maxlength="150" placeholder="Nombre y apellido" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="famCi">Cédula de identidad (CI) *</label>
        <input type="text" id="famCi" class="form-control" maxlength="20" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="famParentesco">Parentesco *</label>
        <select id="famParentesco" class="form-control" required>
          <option value="">Selecciona parentesco</option>
          <option value="PADRE">Padre</option><option value="MADRE">Madre</option>
          <option value="HIJO">Hijo</option><option value="HIJA">Hija</option>
          <option value="CONYUGE">Cónyuge</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modalAgregarFam')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarFamiliar()">Guardar</button>
    </div>
  </div>
</div>

<script>
let titularActualId = null;

function seleccionarTitular(id, nombre) {
  titularActualId = id;
  document.getElementById('titularNombre').textContent = nombre;
  document.getElementById('btnAgregar').style.display  = '';
  document.getElementById('famTitularId').value        = id;
  cargarFamiliares(id);
}

function nuevoFamiliar() {
  limpiarFormularioFamiliar();
  openModal('modalAgregarFam');
}

async function cargarFamiliares(titularId) {
  const panel = document.getElementById('familiaresPanelInner');
  panel.innerHTML = '<p class="text-muted text-center" style="padding:1.5rem;">Cargando...</p>';

  const res = await apiGet('/backend/api/familiares.php', { action: 'listar', titular_id: titularId });
  const fams = res.data || [];

  if (!fams.length) {
    panel.innerHTML = `<div class="empty-state">
      <div class="empty-icon">➕</div>
      <h4>Sin familiares registrados</h4>
      <p>No hay familiares registrados para este titular.</p>
    </div>`;
    return;
  }

  const activos = fams.filter(f => f.estado === 'ACTIVO').length;

  panel.innerHTML = `
    <div style="padding:.75rem 1rem;background:#f8fafc;border-bottom:1px solid var(--border);font-size:.85rem;">
      <strong>${activos}</strong> familiares activos registrados
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>#</th><th>Nombre</th><th>CI</th><th>Parentesco</th><th>Estado</th><th>Registrado</th><th>Acciones</th></tr></thead>
        <tbody>
          ${fams.map((f,i) => `
            <tr>
              <td>${i+1}</td>
              <td><strong>${escHtml(f.nombre)}</strong></td>
              <td>${escHtml(f.ci || 'Pendiente')}</td>
              <td>${escHtml(etiquetaParentesco(f.parentesco))}</td>
              <td>${f.estado === 'ACTIVO'
                ? '<span class="badge badge-success">Activo</span>'
                : '<span class="badge badge-danger">Inactivo</span>'}</td>
              <td style="font-size:.8rem;color:var(--muted);">${formatDateShort(f.created_at?.split(' ')[0])}</td>
              <td>
                <button class="btn btn-secondary btn-sm" onclick="editarFamiliar(${f.id})">Editar</button>
                ${f.estado === 'ACTIVO'
                  ? `<button class="btn btn-danger btn-sm" onclick="desactivar(${f.id})">Desactivar</button>`
                  : '<span class="text-muted" style="font-size:.8rem;">Inactivo</span>'}
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

async function guardarFamiliar() {
  const familiarId = document.getElementById('famId').value;
  const titularId = document.getElementById('famTitularId').value;
  const alertEl = document.getElementById('alertAgregarFam');
  alertEl.innerHTML = '';
  const payload = {
    action: familiarId ? 'actualizar' : 'registrar',
    titular_id: titularId,
    familiar_id: familiarId,
    nombre: document.getElementById('famNombre').value.trim(),
    ci: document.getElementById('famCi').value.trim(),
    parentesco: document.getElementById('famParentesco').value,
  };
  const res = await apiPost('/backend/api/familiares.php', payload);

  if (res.success) {
    Toast.success(res.message);
    closeModal('modalAgregarFam');
    limpiarFormularioFamiliar();
    cargarFamiliares(titularId);
  } else {
    alertEl.textContent = res.message;
    alertEl.className = 'alert alert-danger';
  }
}

function editarFamiliar(id) {
  apiGet('/backend/api/familiares.php', { action: 'listar', titular_id: titularActualId }).then(res => {
    const familiar = (res.data || []).find(item => Number(item.id) === Number(id));
    if (!familiar) return Toast.error('No se encontró el familiar.');
    document.getElementById('famId').value = familiar.id;
    document.getElementById('famNombre').value = familiar.nombre || '';
    document.getElementById('famCi').value = familiar.ci || '';
    document.getElementById('famParentesco').value = familiar.parentesco || '';
    document.querySelector('#modalAgregarFam .modal-header h4').textContent = 'Editar Familiar';
    openModal('modalAgregarFam');
  });
}

function limpiarFormularioFamiliar() {
  document.getElementById('famId').value = '';
  document.getElementById('famNombre').value = '';
  document.getElementById('famCi').value = '';
  document.getElementById('famParentesco').value = '';
  document.getElementById('alertAgregarFam').className = '';
  document.getElementById('alertAgregarFam').textContent = '';
  document.querySelector('#modalAgregarFam .modal-header h4').textContent = 'Agregar Familiar';
}

function etiquetaParentesco(valor) {
  return ({ PADRE: 'Padre', MADRE: 'Madre', HIJO: 'Hijo', HIJA: 'Hija', CONYUGE: 'Cónyuge' })[valor] || 'Pendiente';
}

async function desactivar(familiarId) {
  if (!confirmar('¿Desactivar este familiar?')) return;
  const res = await apiPost('/backend/api/familiares.php', {
    action: 'desactivar', familiar_id: familiarId, titular_id: titularActualId
  });
  if (res.success) { Toast.success(res.message); cargarFamiliares(titularActualId); }
  else Toast.error(res.message);
}

</script>

<?php require_once __DIR__ . '/layout_end.php'; ?>
