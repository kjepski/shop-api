import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import App from './App.vue';
import { APP_TITLE, BASE_PATH, createAdminRouter } from './router';

enableAutoUnmount(afterEach);

beforeEach(() => {
    sessionStorage.clear();
    document.body.innerHTML = '';
});

async function mountApp() {
    const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
    await router.push('/');
    await router.isReady();

    const wrapper = mount(App, { global: { plugins: [createPinia(), router] }, attachTo: document.body });
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
        mount(App, { global: { plugins: [createPinia(), router] }, attachTo: document.body });
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

    it('does not reload for other navigation errors', async () => {
        const reload = vi.fn();
        const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH), reload });
        router.addRoute({ path: '/failing', component: () => Promise.reject(new Error('boom')), meta: { title: 'X' } });

        await router.push('/failing').catch(() => undefined);

        expect(reload).not.toHaveBeenCalled();
    });
});
