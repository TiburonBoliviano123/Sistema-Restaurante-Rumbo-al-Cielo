const { test, expect } = require('@playwright/test');

const adminEmail = 'e2e-admin@example.test';
const employeeEmail = 'e2e-empleado@example.test';
const pensionerEmail = 'e2e-pensionado@example.test';
const testPassword = 'Prueba-E2E-739!';

test('muestra un error al enviar credenciales incorrectas', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill('no-existe@example.test');
  await page.locator('#contrasena').fill('incorrecta');
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();

  await expect(page.locator('#loginAlert .alert-danger')).toContainText('Credenciales incorrectas');
  await expect(page).toHaveURL(/\/$/);
});

test('informa que la cuenta está suspendida cuando el empleado está inactivo', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill('e2e-inactivo@example.test');
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();

  await expect(page.locator('#loginAlert .alert-danger')).toContainText('suspendida o inactiva');
  await expect(page).toHaveURL(/\/$/);
});

test('permite iniciar sesión como administrador y cerrar sesión', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();

  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await expect(page.getByText('Resumen de operaciones')).toBeVisible();
  page.once('dialog', (dialog) => dialog.accept());
  await page.getByRole('button', { name: /Cerrar sesión/ }).click();
  await expect(page).toHaveURL(/index\.php$/);

  await page.goto('/frontend/admin/dashboard.php');
  await expect(page).toHaveURL(/index\.php$/);
});

test('un empleado inicia sesión y no puede abrir el panel admin', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(employeeEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();

  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
  await expect(page.locator('.employee-welcome')).toBeVisible();

  await page.goto('/frontend/admin/dashboard.php');
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
  await expect(page.locator('.employee-welcome')).toBeVisible();
  await expect(page.getByText('PANEL DE ADMINISTRACIÓN')).toHaveCount(0);
});

test('el empleado corrige su CI duplicado y actualiza su propio perfil', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(employeeEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);

  await page.goto('/frontend/empleado/perfil.php');
  await expect(page.getByLabel('Apellido(s)')).toHaveValue('E2E');
  await page.getByLabel('Cédula de identidad (CI) *').fill('70000001');
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(page.locator('#perfilAlert .alert-danger')).toContainText('CI ya está registrado');

  await page.getByLabel('Cédula de identidad (CI) *').fill('70000123');
  await page.getByLabel('Departamento *').fill('Tecnología');
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(page.getByLabel('Cédula de identidad (CI) *')).toHaveValue('70000123');
  await expect(page.getByLabel('Departamento *')).toHaveValue('Tecnología');

  await page.goto('/frontend/admin/dashboard.php');
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
});

test('el administrador registra empleados con CI único y datos completos', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);

  await page.goto('/frontend/admin/empleados.php');
  await page.getByRole('button', { name: /Nuevo Empleado/ }).click();
  const form = page.locator('#formNuevoEmpleado');
  await form.locator('[name="nombre"]').fill('Carlos');
  await form.locator('[name="apellido"]').fill('Rojas');
  await form.locator('[name="ci"]').fill('70000001');
  await form.locator('[name="departamento"]').fill('Finanzas');
  await form.locator('[name="codigo_empleado"]').fill('E2E-REG-001');
  await form.locator('[name="correo"]').fill(`e2e-registro-${Date.now()}@example.test`);
  await form.locator('[name="celular"]').fill('70000100');
  await form.locator('[name="tipo_cuenta"]').selectOption('NO_PENSIONADO');
  await form.locator('[name="contrasena"]').fill(testPassword);
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.locator('#alertNuevo .alert-danger')).toContainText('CI ya está registrado');

  await form.locator('[name="ci"]').fill('70000200');
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.locator('#tablaEmpleados')).toContainText('Carlos Rojas');
  await expect(page.locator('#tablaEmpleados')).toContainText('70000200');
  await expect(page.locator('#tablaEmpleados')).toContainText('Finanzas');
});

test('el pensionado afilia un familiar, corrige CI duplicado y gestiona sus datos', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(pensionerEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
  await page.goto('/frontend/empleado/familiares.php');

  await page.getByRole('button', { name: /Afiliar familiar/ }).click();
  await page.locator('#familiarNombre').fill('Familiar E2E');
  await page.locator('#familiarCi').fill('70000001');
  await page.locator('#familiarParentesco').selectOption('CONYUGE');
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.locator('#alertFamiliar')).toContainText('CI ya está asociado');

  await page.locator('#familiarCi').fill('70000005');
  await page.getByRole('button', { name: 'Guardar', exact: true }).click();
  await expect(page.locator('#listaFamiliares')).toContainText('Familiar E2E');
  await expect(page.locator('#listaFamiliares')).toContainText('70000005');
  await expect(page.locator('#listaFamiliares')).toContainText('Cónyuge');

  await page.getByRole('row', { name: /70000005/ }).getByRole('button', { name: 'Editar' }).click();
  await page.locator('#familiarParentesco').selectOption('MADRE');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.locator('#listaFamiliares')).toContainText('Madre');

  await page.goto('/frontend/empleado/pensionamiento.php');
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Pensionar para el mes' }).click();
  await expect(page.locator('.toast')).toContainText('Pensionamiento del familiar confirmado', { timeout: 5000 });
  await expect(page.locator('#pensionamientoFamiliares')).toContainText('Pensionado');
});

test('el administrador registra y edita familiares con CI y parentesco', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await page.goto('/frontend/admin/familiares.php');
  await page.getByRole('button', { name: /Titular E2E/ }).click();

  await page.getByRole('button', { name: /Agregar Familiar/ }).click();
  await page.locator('#famNombre').fill('Padre E2E');
  await page.locator('#famCi').fill('70000006');
  await page.locator('#famParentesco').selectOption('PADRE');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.locator('#familiaresPanelInner')).toContainText('Padre E2E');
  await expect(page.locator('#familiaresPanelInner')).toContainText('70000006');

  await page.getByRole('row', { name: /70000006/ }).getByRole('button', { name: 'Editar' }).click();
  await page.locator('#famParentesco').selectOption('HIJO');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.locator('#familiaresPanelInner')).toContainText('Hijo');
});

test('el empleado solicita la pensión del próximo mes y recibe reservas automáticas', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(employeeEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);

  await page.goto('/frontend/empleado/pensionamiento.php');
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Solicitar para mí' }).click();
  await expect(page.getByRole('button', { name: 'Ya estás pensionado este mes' })).toBeDisabled();
  await expect(page.getByText(/reservas nuevas/)).toBeVisible();
});

test('el administrador registra el pensionamiento mensual del titular', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await page.goto('/frontend/admin/pensionamientos.php');

  const titularOption = page.locator('#selectTitularPension option').filter({ hasText: 'Titular E2E' });
  await page.locator('#selectTitularPension').selectOption(await titularOption.getAttribute('value'));
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Registrar titular' }).click();
  await expect(page.getByRole('button', { name: 'Titular ya registrado este mes' })).toBeDisabled();
});

test('el administrador registra una entrega después del corte de reservas', async ({ page }) => {
  const ahora = new Date();
  test.skip(ahora.getDay() === 0 || ahora.getDay() >= 5 || ahora.getHours() < 9,
    'Requiere un día operativo después de las 09:00 para probar la lista real de cocina.');

  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await page.goto('/frontend/admin/reservas.php');

  const fila = page.getByRole('row', { name: /Empleado E2E/ });
  await expect(page.locator('#consolidadoCocina')).toContainText('Raciones a preparar: 1');
  await expect(fila).toContainText('Confirmado al corte');
  await fila.getByRole('button', { name: 'Marcar entregado' }).click();
  await expect(fila).toContainText('Entregado (cobrable)');
});

test('el administrador publica los tres platos y el empleado los consulta', async ({ page }) => {
  const hoy = new Date();
  test.skip(hoy.getDay() === 0 || hoy.getDay() >= 5, 'Requiere un día operativo de lunes a jueves.');
  const fecha = [hoy.getFullYear(), String(hoy.getMonth() + 1).padStart(2, '0'), String(hoy.getDate()).padStart(2, '0')].join('-');

  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await page.goto(`/backend/api/guardar_menus.php?semana=${fecha}`);
  await page.locator(`#sopa-${fecha}`).fill('Sopa E2E');
  await page.locator(`#principal-${fecha}`).fill('Pollo E2E');
  await page.locator(`#postre-${fecha}`).fill('Fruta E2E');
  await page.getByRole('button', { name: 'Guardar Semana' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Sopa E2E');

  await page.goto('/frontend/admin/dashboard.php');
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: /Cerrar sesión/ }).click();
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(employeeEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
  await page.goto('/frontend/empleado/menu.php');
  const diaMenu = page.locator('.menu-week-card').filter({ hasText: 'Pollo E2E' });
  await expect(diaMenu).toContainText('Sopa E2E');
  await expect(diaMenu).toContainText('Fruta E2E');
});

test('el administrador actualiza parámetros y la tarifa nueva aparece al empleado', async ({ page }) => {
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(adminEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/admin\/dashboard\.php$/);
  await page.goto('/frontend/admin/configuracion.php');
  await expect(page.getByRole('button', { name: 'Guardar parámetros' })).toBeEnabled();

  await page.locator('#tarifaPensionado').fill('16.50');
  await page.locator('#tarifaEsporadico').fill('19.50');
  await page.locator('#minimoMensual').fill('8');
  await page.locator('#horaLimite').fill('08:30');
  await page.getByRole('button', { name: 'Guardar parámetros' }).click();
  await expect(page.locator('.toast')).toContainText('Parámetros actualizados');

  await page.goto('/frontend/admin/reportes.php');
  await expect(page.locator('#cardReporte')).toContainText('Bs 16.50/almuerzo');
  await expect(page.locator('#cardReporte')).toContainText('Mínimo 8 almuerzos/mes');
  await expect(page.locator('#cardReporte')).toContainText('Bs 19.50/almuerzo');

  await page.goto('/frontend/admin/dashboard.php');
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: /Cerrar sesión/ }).click();
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(employeeEmail);
  await page.locator('#contrasena').fill(testPassword);
  await page.getByRole('button', { name: /Ingresar al sistema/ }).click();
  await expect(page).toHaveURL(/frontend\/empleado\/dashboard\.php$/);
  await page.goto('/frontend/empleado/menu.php');
  await expect(page.locator('.menu-price').first()).toContainText('Bs 19.50');
});
