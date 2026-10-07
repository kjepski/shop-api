import { expect, test, type APIRequestContext } from '@playwright/test';

import { USER, logIn } from './fixtures';

/** /api/me with a Bearer token only (no Referer, so no cookie session is involved). */
function meWithToken(request: APIRequestContext, token: string) {
    return request.get('/api/me', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
}

/**
 * The old Alpine panel (/admin) logs in with a Bearer token, but its requests come from the same
 * domain as the cookie-based panel, so the API checks CSRF on them too (PR #17). PHP tests skip
 * CSRF; this proves the old panel still sends the token.
 */
test('the old panel logs in and out with a token', async ({ page }) => {
    await page.goto('/admin');
    await logIn(page, USER);

    const header = page.getByRole('banner');
    await expect(header).toContainText('Test User');
    await expect(header).toContainText('tylko odczyt');

    const token = await page.evaluate(() => sessionStorage.getItem('shop-admin-token'));
    expect(token).toBeTruthy();

    // Baseline: the stored token really works, so a 401 later means it was revoked.
    const before = await meWithToken(page.request, token!);
    expect(before.status()).toBe(200);
    expect(((await before.json()) as { data: { email: string } }).data.email).toBe(USER.email);

    await header.getByRole('button', { name: 'Wyloguj' }).click();
    await expect(page.getByRole('button', { name: 'Zaloguj' })).toBeVisible();

    // Revoked on the server, not just forgotten by the page.
    expect((await meWithToken(page.request, token!)).status()).toBe(401);
    expect(await page.evaluate(() => sessionStorage.getItem('shop-admin-token'))).toBeNull();
});
