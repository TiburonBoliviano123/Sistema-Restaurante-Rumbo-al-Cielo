# Restaurante Institucional "Rumbo al Cielo"
## Sistema de Reservas de Almuerzos — Unión Boliviana

---

### Requisitos

| Componente | Versión mínima |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 8.0 / 10.5 |
| Apache | 2.4 (con mod_rewrite) |
| phpMyAdmin | Cualquier versión reciente |

XAMPP / WAMP / Laragon son válidos para entorno local.

---

### Instalación Rápida

1. **Copiar el proyecto** en `htdocs/` (XAMPP) o `www/` (WAMP).
2. **Crear la base de datos** en phpMyAdmin:
   - Importar `database/schema.sql`
   - Si actualizas una base existente, aplica las migraciones pendientes de `database/migrations/` en orden de fecha antes de usar el sistema.
   - La BD se llama `restaurante_rumbo_al_cielo`
3. **Configurar credenciales** en `backend/config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');       // Cambia si tu MySQL tiene contraseña
   ```
4. **Crear el primer administrador** desde PowerShell, en la carpeta del proyecto:
   ```powershell
   $env:BOOTSTRAP_ADMIN_NAME = Read-Host 'Nombre del administrador'
   $env:BOOTSTRAP_ADMIN_EMAIL = Read-Host 'Correo del administrador'
   $secure = Read-Host 'Contraseña nueva (mínimo 12 caracteres)' -AsSecureString
   $env:BOOTSTRAP_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
   php backend/scripts/bootstrap_admin.php
   Remove-Item Env:\BOOTSTRAP_ADMIN_NAME, Env:\BOOTSTRAP_ADMIN_EMAIL, Env:\BOOTSTRAP_ADMIN_PASSWORD
   ```
5. **Acceder** en el navegador: `http://localhost/<carpeta-del-proyecto>/`

No se incluye una cuenta ni contraseña predeterminada. No subas credenciales reales a GitHub.

---

### Estructura del Proyecto

```
/
├── index.php                  ← Login / entry point
├── .htaccess                  ← Seguridad Apache
├── database/
│   └── schema.sql             ← Script SQL completo
├── backend/
│   ├── scripts/
│   │   └── bootstrap_admin.php ← Creación local del primer administrador
│   ├── config/
│   │   ├── database.php       ← Conexión PDO + constantes
│   │   └── session.php        ← Gestión de sesiones y roles
│   ├── models/                ← Capa de datos (PDO queries)
│   │   ├── Usuario.php
│   │   ├── Familiar.php
│   │   ├── Calendario.php
│   │   ├── Menu.php
│   │   ├── Reserva.php
│   │   └── Reporte.php
│   ├── controllers/           ← Lógica de negocio
│   │   ├── AuthController.php
│   │   ├── EmpleadoController.php
│   │   ├── FamiliarController.php
│   │   ├── MenuController.php
│   │   ├── CalendarioController.php
│   │   ├── ReservaController.php
│   │   └── ReporteController.php
│   └── api/                   ← Endpoints JSON (AJAX)
│       ├── auth.php
│       ├── empleados.php
│       ├── familiares.php
│       ├── menus.php
│       ├── calendario.php
│       ├── reservas.php
│       └── reportes.php
└── frontend/
    ├── assets/
    │   ├── css/main.css       ← Estilos globales
    │   └── js/app.js          ← Utilidades JS (fetch, toast, modales)
    ├── admin/                 ← Panel Administrador
    │   ├── layout.php / layout_end.php
    │   ├── dashboard.php
    │   ├── empleados.php
    │   ├── familiares.php
    │   ├── menus.php
    │   ├── calendario.php
    │   ├── reservas.php
    │   └── reportes.php
    └── empleado/              ← Panel Empleado (titular)
        ├── layout.php / layout_end.php
        ├── dashboard.php
        ├── menu.php
        ├── reservas.php
        └── familiares.php
```

---

### Reglas de Negocio Implementadas

| Regla | Implementación |
|---|---|
| Días operativos: L-J | `Calendario::esDiaOperativo()` — verifica `date('N')` 1..4 |
| Bloqueo cancelación 09:00 | `ReservaController::cancelar()` compara hora del servidor |
| Máx. 3 familiares activos | `FamiliarController::registrar()` cuenta antes de crear |
| Pensionado: 15 Bs, mín. 10 almuerzos | `ReporteController::calcularMontoPensionado()` |
| No pensionado: 18 Bs, sin mínimo | `ReporteController::generarReporteMensual()` |
| Cobro familiar al código del titular | Tabla `reportes_mensuales` consolida por `codigo_empleado` |
| Feriados solo por admin | `CalendarioController::marcarFeriado()` + `requireRole('ADMIN')` |
| Familiares sin acceso independiente | Familiares no tienen usuario; solo el titular los gestiona |

---

### Tarifas de Referencia

- **No Pensionado:** Bs 18 por almuerzo consumido (sin mínimo)
- **Pensionado / Familiar:** Bs 15 por almuerzo
  - Mínimo 10 almuerzos/mes
  - Si consume < 10: se cobra igual `10 × 15 = Bs 150`
  - Ejemplo: 7 consumidos → `7×15 + 3×15 = Bs 150`

---

### Reporte Mensual para RRHH

1. Ir a **Admin → Reporte Mensual**
2. Seleccionar mes y año
3. Clic en **"Calcular y Generar"**
4. Descargar como **CSV** o **Imprimir**

El reporte incluye por cada código de empleado:
- Almuerzos consumidos (titular + familiares)
- Monto base + penalización por incumplimiento
- **Total a descontar en planilla**
# Pruebas automatizadas

Las pruebas unitarias de reservas corren sin MySQL. Las pruebas de integración
ejercitan modelos reales contra una base aislada llamada
`restaurante_rumbo_al_cielo_test`; la configuración impide ejecutarlas contra una
base cuyo nombre no termine en `_test`. Esa base de pruebas se conserva entre
ejecuciones y cada caso revierte sus datos al terminar.

Se necesita PHP 8.2 y Composer. En XAMPP, si Composer avisa que no puede
descomprimir paquetes, habilita la extensión `zip` en el `php.ini` que usa la
terminal (el archivo `php_zip.dll` viene en XAMPP).

```bash
composer install
# Unitarias; las de MySQL quedan marcadas como omitidas
composer test
# Crear/actualizar el esquema aislado de pruebas (MySQL debe estar iniciado)
composer test:db:setup
# Integración contra MySQL
composer test:integration
```

Para cambiar las credenciales de esa base, configura `TEST_DB_HOST`,
`TEST_DB_PORT`, `TEST_DB_NAME` (siempre con sufijo `_test`), `TEST_DB_USER` y
`TEST_DB_PASS` en el entorno antes de ejecutar los comandos.

La lista de comprobación manual de los flujos desde el navegador está en
[`docs/pruebas-manuales.md`](docs/pruebas-manuales.md). Ejecuta `composer test`
después de cada cambio de lógica; si toca SQL o modelos, ejecuta también las
pruebas de integración. Esto funciona como regresión: vuelve a verificar las
reglas cubiertas previamente.

### Pruebas de navegador (Node.js + Playwright)

Node.js se usa aquí para automatizar el navegador; el backend y sus reglas siguen
siendo PHP. Las pruebas E2E levantan un servidor PHP local en el puerto 4173,
preparan usuarios ficticios en la base `_test` y comprueban login, cierre de
sesión y permisos. No usan ni necesitan cuentas reales.

```bash
npm ci
npx playwright install chromium
npm run test:e2e
```

Con XAMPP, si `php` no está en el `PATH`, en PowerShell indica dónde está PHP
antes de ejecutar la suite:

```powershell
$env:PHP_BINARY_PATH = 'C:\xampp\php\php.exe'
npm run test:e2e
```

Playwright usa Chromium descargado por defecto. También puede usar Microsoft Edge
instalado configurando `E2E_BROWSER_CHANNEL=msedge`. Para cambiar la conexión de
pruebas se admiten `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_NAME` (debe terminar
en `_test`), `TEST_DB_USER` y `TEST_DB_PASS`.

### Actualizar una base existente con los campos de empleado

En bases instaladas antes de agregar CI, apellido y departamento, ejecuta una sola
vez [`database/migrations/20261102_add_employee_identity_fields.sql`](database/migrations/20261102_add_employee_identity_fields.sql)
desde phpMyAdmin o la consola MySQL. La migración agrega columnas opcionales para
no borrar datos existentes y una restricción única para CI; desde los formularios,
los tres campos son obligatorios para nuevas altas y actualizaciones.

En bases instaladas antes de incorporar CI y parentesco de familiares, ejecuta una
sola vez [`database/migrations/20261102_add_family_identity_fields.sql`](database/migrations/20261102_add_family_identity_fields.sql).
Los registros existentes conservarán sus datos y tendrán CI/parentesco pendientes
hasta que se completen desde Administración o Mi Familiares.

En bases instaladas antes del registro mensual de pensión, ejecuta una sola vez
[`database/migrations/20261102_add_monthly_pensioning.sql`](database/migrations/20261102_add_monthly_pensioning.sql).
La afiliación se solicita desde Pensión mensual antes de que inicie el mes siguiente;
el sistema crea reservas de lunes a jueves y omite los feriados ya registrados.
