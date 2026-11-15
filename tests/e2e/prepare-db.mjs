import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const phpCli = process.env.PHP_BINARY_PATH || 'php';
const testDbName = process.env.TEST_DB_NAME || 'restaurante_rumbo_al_cielo_test';

if (!testDbName.endsWith('_test')) {
  console.error('Seguridad: TEST_DB_NAME debe terminar en _test; se canceló la preparación E2E.');
  process.exit(1);
}

const env = {
  ...process.env,
  APP_ENV: 'test',
  TEST_DB_NAME: testDbName,
};

for (const script of ['tests/setup-test-database.php', 'tests/e2e/seed-users.php']) {
  const result = spawnSync(phpCli, [script], { cwd: projectRoot, env, stdio: 'inherit' });
  if (result.error) {
    console.error(`No se pudo ejecutar PHP (${phpCli}): ${result.error.message}`);
    process.exit(1);
  }
  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}
