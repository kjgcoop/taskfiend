import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Task Fiend E2E tests
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/e2e',

  /* Global setup - runs once before all tests */
  globalSetup: './tests/e2e/global-setup.js',

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Test timeout - 30 seconds per test */
  timeout: 30000,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Run with 8 workers for parallel execution */
  workers: process.env.CI ? 1 : 8,

  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [['html', { open: 'never' }]],

  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'http://localhost:8003',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',

    /* Screenshot on failure */
    screenshot: 'only-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Use Playwright's bundled Chromium (sandboxed in ~/.cache/ms-playwright/)
        // Avoids snap Firefox connection issues
      },
    },
  ],

  /* Run your local dev server before starting the tests */
  webServer: {
    command: 'php artisan serve --env=testing --port=8003',
    url: 'http://localhost:8003/login',
    reuseExistingServer: true,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});
