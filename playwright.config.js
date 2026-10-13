// Playwright config for the P0-05 acceptance smoke test. CI starts
// `php artisan serve` on http://127.0.0.1:8000 and points this runner
// at it via E2E_BASE_URL.
//
// Only chromium is exercised for now; the booking widget and edge
// cases (Safari, mobile) land with the Booking Engine (P3).

import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI
        ? [['list'], ['github']]
        : 'list',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
