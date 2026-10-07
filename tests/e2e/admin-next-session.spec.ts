import { expect, test } from '@playwright/test';

import { ADMIN, USER, expectLoginPage, logIn } from './fixtures';

/**
 * Cookie session of the Vue panel (Sanctum SPA): what jsdom cannot show, such as real cookies,
 * CSRF, reloads, focus and browser history. Login attempts are limited to 5 a minute per email,
 * so the tests spread over the seeded accounts.
 */
test.describe('Vue panel session', () => {
    test('a guest logs in from the start page and the login form leaves no trace in history', async ({ page }) => {
        await page.goto('/admin-next/');

        await expect(page).toHaveURL('/admin-next/login');
        await expectLoginPage(page);
        await expect(page).toHaveTitle('Logowanie · Panel sklepu');

        await logIn(page, ADMIN);

        await expect(page).toHaveURL('/admin-next/');
        await expect(page.getByRole('banner')).toContainText('Admin (administrator)');
        await expect(page).toHaveTitle('Start · Panel sklepu');
        // Screen readers start reading the new screen from its heading.
        await expect(page.getByRole('heading', { level: 1, name: 'Panel sklepu' })).toBeFocused();

        // The login form replaced its history entry, so "back" leaves the panel instead of
        // returning to it.
        await page.goBack();
        await expect(page).toHaveURL('about:blank');

        // A logged-in user who opens the login page directly is sent to the start page.
        await page.goto('/admin-next/login');
        await expect(page).toHaveURL('/admin-next/');
    });

    test('a regular user is not called an administrator', async ({ page }) => {
        await page.goto('/admin-next/login');
        await logIn(page, USER);

        await expect(page.getByRole('banner')).toContainText('Test User');
        await expect(page.getByRole('banner')).not.toContainText('administrator');
    });

    test('failed credentials are described at the email field, which gets focus', async ({ page }) => {
        await page.goto('/admin-next/login');
        // An unknown email, so the seeded accounts keep their login budget.
        await logIn(page, { email: 'nobody@example.com', password: 'wrong-password' });

        const email = page.getByLabel('E-mail');
        await expect(email).toHaveAttribute('aria-invalid', 'true');
        await expect(email).toBeFocused();
        await expect(email).toHaveAccessibleDescription('These credentials do not match our records.');
        await expect(page).toHaveURL('/admin-next/login');
    });

    test('after logging in the user lands on the link they opened', async ({ page }) => {
        await page.goto('/admin-next/cokolwiek?x=1');
        await expectLoginPage(page);

        // The target lives in the URL, not in memory, so it survives a reload of the login page.
        await page.reload();
        await logIn(page, USER);

        await expect(page).toHaveURL('/admin-next/cokolwiek?x=1');
        await expect(page.getByRole('heading', { level: 1, name: 'Nie znaleziono' })).toBeVisible();
    });

    test('the session survives a reload and is invisible to JavaScript', async ({ page, context }) => {
        await page.goto('/admin-next/login');
        await logIn(page, ADMIN);
        await expect(page.getByRole('banner')).toBeVisible();

        await page.reload();
        await expect(page.getByRole('banner')).toContainText('Admin');

        // The browser holds an HttpOnly session cookie; page scripts see only the CSRF cookie.
        const cookies = await context.cookies();
        const session = cookies.filter((cookie) => cookie.name !== 'XSRF-TOKEN');
        expect(session).toHaveLength(1);
        expect(session[0]?.httpOnly).toBe(true);

        const visible = await page.evaluate(() => document.cookie.split('; ').map((entry) => entry.split('=')[0]));
        expect(visible).toEqual(['XSRF-TOKEN']);

        // No token kept in storage; the only key the panel may use is the reload guard of the router.
        const keys = await page.evaluate(() => [...Object.keys(localStorage), ...Object.keys(sessionStorage)]);
        expect(keys.filter((key) => key !== 'admin-next:reloaded-for')).toEqual([]);
    });

    test('logging out ends the session on the server, even with a stale CSRF token', async ({
        page,
        context,
        baseURL,
    }) => {
        await page.goto('/admin-next/login');
        await logIn(page, ADMIN);
        await expect(page.getByRole('banner')).toBeVisible();

        // E.g. the token changed in another tab: the client gets 419, fetches a new one and retries.
        await context.addCookies([{ name: 'XSRF-TOKEN', value: 'stale', url: baseURL! }]);

        await page.getByRole('button', { name: 'Wyloguj' }).click();

        // The message hides itself after 5 s, so check it first.
        await expect(page.getByText('Wylogowano.')).toBeVisible();
        await expectLoginPage(page);

        // A fresh page load asks the server again.
        await page.goto('/admin-next/');
        await expectLoginPage(page);
    });
});
