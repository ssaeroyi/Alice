import { defineConfig, devices } from '@playwright/test';

// Browser checks for the Field Journal panels. Run with `npm run test:browser`.
// Reuses a server already on :8000; otherwise starts `php artisan serve`.
export default defineConfig({
    testDir: 'tests/browser',
    timeout: 60_000,
    fullyParallel: false,
    workers: 1,
    use: {
        baseURL: 'http://localhost:8000',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
