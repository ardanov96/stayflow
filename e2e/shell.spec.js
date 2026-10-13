
// P0-05 acceptance: Playwright smoke test loads the shell in every
// supported locale and verifies the keyboard skip-to-main link.
// Run with: npm run test:e2e   (requires the dev server on $E2E_BASE_URL).

import { test, expect } from '@playwright/test';

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

/** Set the locale cookie the way LocaleController would after POST /locale. */
async function setLocaleCookie(context, locale) {
    await context.addCookies([
        { name: 'locale', value: locale, url: BASE_URL },
    ]);
}

test.describe('Shell renders the dashboard in every supported locale', () => {
    test('en (default): English copy and html lang=en', async ({ page }) => {
        await page.goto('/');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Operations dashboard' }),
        ).toBeVisible();
        await expect(page.getByText("Today's arrivals")).toBeVisible();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    });

    test('id: Indonesian copy and html lang=id', async ({ context, page }) => {
        await setLocaleCookie(context, 'id');
        await page.goto('/');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Dasbor operasional' }),
        ).toBeVisible();
        await expect(page.getByText('Kedatangan hari ini')).toBeVisible();
        await expect(page.locator('html')).toHaveAttribute('lang', 'id');
    });

    test('zh: Chinese copy and html lang=zh', async ({ context, page }) => {
        await setLocaleCookie(context, 'zh');
        await page.goto('/');
        await expect(
            page.getByRole('heading', { level: 1, name: '运营仪表板' }),
        ).toBeVisible();
        await expect(page.getByText('今日到达')).toBeVisible();
        await expect(page.locator('html')).toHaveAttribute('lang', 'zh');
    });
});

test.describe('Keyboard navigation on the shell', () => {
    test('the first Tab from <body> focuses the skip-to-main link', async ({ page }) => {
        await page.goto('/');
        // Make sure no input is auto-focused before we press Tab.
        await page.evaluate(() => {
            const active = document.activeElement;
            if (active instanceof HTMLElement) {
                active.blur();
            }
        });
        await page.keyboard.press('Tab');
        const focused = await page.evaluate(
            () => document.activeElement?.textContent?.trim().toLowerCase() ?? '',
        );
        expect(focused).toContain('skip to main content');
    });
});
