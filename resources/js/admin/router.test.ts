import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import App from '@admin/App.vue';
import * as auth from '@admin/api/auth';
import { ApiError, request, setUnauthorizedHandler } from '@admin/api/client';
import { APP_TITLE, BASE_PATH, createAdminRouter } from '@admin/router';
import { useFlashStore } from '@admin/stores/flash';
import type { User } from '@admin/types/user';

vi.mock('@admin/api/auth');

enableAutoUnmount(afterEach);

const admin: User = { id: 1, name: 'Admin', email: 'admin@example.com', is_admin: true, created_at: null };

beforeEach(() => {
    sessionStorage.clear();
    document.body.innerHTML = '';
    vi.resetAllMocks();
    vi.mocked(auth.fetchMe).mockResolvedValue(admin);
    // The session guard needs a store before any component is mounted.
    setActivePinia(createPinia());
});

afterEach(() => {
    setUnauthorizedHandler(null);
    vi.unstubAllGlobals();
});

function loggedOut(): void {
    vi.mocked(auth.fetchMe).mockRejectedValue(new ApiError(401, 'Unauthenticated.'));
}

async function mountApp() {
    const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
    await router.push('/');
    await router.isReady();

    const wrapper = mount(App, { global: { plugins: [router] }, attachTo: document.body });
    await flushPromises();

    return { router, wrapper };
}

function brokenChunk(): Promise<never> {
    return Promise.reject(new TypeError('Failed to fetch dynamically imported module: /build/assets/Old-1a2b.js'));
}

describe('admin router', () => {
    it('builds every link under /admin-next/', () => {
        const router = createAdminRouter();

        expect(router.resolve({ name: 'home' }).href).toBe('/admin-next/');
        expect(router.resolve('/products/new').href).toBe('/admin-next/products/new');
        expect(router.resolve('/products/12/edit').href).toBe('/admin-next/products/12/edit');
    });

    it('names the browser tab after the current screen', async () => {
        const { router } = await mountApp();
        expect(document.title).toBe(`Start · ${APP_TITLE}`);

        await router.push('/missing');
        await flushPromises();

        expect(document.title).toBe(`Nie znaleziono · ${APP_TITLE}`);
    });

    it('moves focus to the new screen heading after navigation', async () => {
        const { router } = await mountApp();

        await router.push('/missing');
        await flushPromises();

        expect(document.activeElement?.textContent).toBe('Nie znaleziono');
    });

    it('leaves focus alone on the first screen', async () => {
        // Like main.ts: the router starts its first navigation while the app mounts.
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
        mount(App, { global: { plugins: [router] }, attachTo: document.body });
        await router.isReady();
        await flushPromises();

        expect(document.querySelector('h1')?.textContent).toBe('Panel sklepu');
        expect(document.activeElement).toBe(document.body);
    });

    it('starts a new screen at the top and restores the position on back', async () => {
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
        await router.push('/');
        const scrollBehavior = router.options.scrollBehavior!;
        const to = router.currentRoute.value;

        expect(scrollBehavior(to, to, null)).toEqual({ top: 0 });
        expect(scrollBehavior(to, to, { left: 0, top: 480 })).toEqual({ left: 0, top: 480 });
    });

    it('reloads the target page once when a page chunk is gone after a deploy', async () => {
        const reload = vi.fn();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH), reload });
        router.addRoute({ path: '/broken', component: brokenChunk, meta: { title: 'Broken' } });

        await router.push('/broken').catch(() => undefined);
        await router.push('/broken').catch(() => undefined);

        expect(reload).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledWith('/admin-next/broken');
    });

    it('sends a guest to the login page and remembers where they were going', async () => {
        loggedOut();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });

        await router.push('/products/12?page=2');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/products/12?page=2');
    });

    it('sends a guest from the start page to a plain login page', async () => {
        loggedOut();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });

        await router.push('/');

        expect(router.currentRoute.value.fullPath).toBe('/login');
    });

    it('lets a guest see the login page', async () => {
        loggedOut();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });

        await router.push('/login');

        expect(router.currentRoute.value.name).toBe('login');
    });

    it('sends a logged-in user away from the login page', async () => {
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });

        await router.push('/login');
        expect(router.currentRoute.value.name).toBe('home');

        await router.push('/login?redirect=/missing');
        expect(router.currentRoute.value.fullPath).toBe('/missing');

        await router.push('/login?redirect=https://evil.example.com');
        expect(router.currentRoute.value.name).toBe('home');
    });

    it('goes to the login page when the session expires mid-work', async () => {
        const { router } = await mountApp();
        await router.push('/missing?a=1');
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(new Response('{}', { status: 401 }))),
        );

        await request('GET', '/products').catch(() => undefined);
        await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('login'));

        expect(router.currentRoute.value.query.redirect).toBe('/missing?a=1');
        expect(useFlashStore().message?.text).toBe('Sesja wygasła, zaloguj się ponownie.');
    });

    it('ignores 401 before anyone has logged in', async () => {
        loggedOut();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
        await router.push('/login');
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(new Response('{}', { status: 401 }))),
        );

        await request('GET', '/me').catch(() => undefined);
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/login');
        expect(useFlashStore().message).toBeNull();
    });

    it('does not reload for other navigation errors', async () => {
        const reload = vi.fn();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH), reload });
        router.addRoute({ path: '/failing', component: () => Promise.reject(new Error('boom')), meta: { title: 'X' } });

        await router.push('/failing').catch(() => undefined);

        expect(reload).not.toHaveBeenCalled();
    });
});
