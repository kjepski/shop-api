import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests of the admin panels in a real browser, against a running app with seeded data.
 * Locally: the `e2e` and `playwright` services in compose.yaml (see AGENTS.md); in CI: the e2e job.
 */
export default defineConfig({
    testDir: './tests/e2e',
    // One seeded database: tests that change data must not run at the same time as others.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    // No retries: a flaky test should fail loudly (a retry would also eat the login limit).
    retries: 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        // The e2e service on the Sail network; set E2E_BASE_URL anywhere else (CI does).
        baseURL: process.env.E2E_BASE_URL ?? 'http://e2e:8001',
        trace: 'retain-on-failure',
        locale: 'pl-PL',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
