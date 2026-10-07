import { expect, type Page } from '@playwright/test';

/** Accounts from DatabaseSeeder; the password comes from UserFactory. */
export const ADMIN = { name: 'Admin', email: 'admin@example.com', password: 'password' };
export const USER = { name: 'Test User', email: 'test@example.com', password: 'password' };

/**
 * Fills the login form of either panel (both use the same labels) and waits for the API's answer.
 * The login limit is 5 attempts a minute per email, so a 429 gets a clear message instead of a
 * confusing failure further on.
 */
export async function logIn(page: Page, account: { email: string; password: string }): Promise<void> {
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Hasło').fill(account.password);

    const answer = page.waitForResponse(
        (response) =>
            /\/api\/(session|login)$/.test(new URL(response.url()).pathname) && response.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Zaloguj' }).click();

    if ((await answer).status() === 429) {
        throw new Error('Login limit reached: restart the e2e service (see AGENTS.md) or wait a minute.');
    }
}

export async function expectLoginPage(page: Page): Promise<void> {
    await expect(page.getByRole('heading', { level: 1, name: 'Logowanie' })).toBeVisible();
}
