const { defineConfig, devices } = require('@playwright/test');

const phpCli = process.env.PHP_BINARY_PATH || 'php';
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:4173';

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: 'list',
  use: {
    ...devices['Desktop Chrome'],
    ...(process.env.E2E_BROWSER_CHANNEL ? { channel: process.env.E2E_BROWSER_CHANNEL } : {}),
    baseURL,
    trace: 'retain-on-failure',
  },
  webServer: {
    command: `"${phpCli}" -S 127.0.0.1:4173 -t .`,
    url: baseURL,
    reuseExistingServer: false,
    timeout: 30_000,
    env: {
      APP_ENV: 'test',
      TEST_DB_HOST: process.env.TEST_DB_HOST || '127.0.0.1',
      TEST_DB_PORT: process.env.TEST_DB_PORT || '3306',
      TEST_DB_NAME: process.env.TEST_DB_NAME || 'restaurante_rumbo_al_cielo_test',
      TEST_DB_USER: process.env.TEST_DB_USER || 'root',
      TEST_DB_PASS: process.env.TEST_DB_PASS || '',
    },
  },
});
