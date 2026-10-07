import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import App from '@admin/App.vue';
import * as auth from '@admin/api/auth';
import { createAdminRouter } from '@admin/router';
import { useFlashStore } from '@admin/stores/flash';

vi.mock('@admin/api/auth');

enableAutoUnmount(afterEach);

beforeEach(() => {
    vi.mocked(auth.fetchMe).mockResolvedValue({
        id: 1,
        name: 'Admin',
        email: 'admin@example.com',
        is_admin: true,
        created_at: null,
    });
});

async function mountAt(path: string, attachTo?: HTMLElement) {
    const router = createAdminRouter({ history: createMemoryHistory() });
    const wrapper = mount(App, { global: { plugins: [createPinia(), router] }, attachTo });
    await router.push(path);
    await flushPromises();

    return wrapper;
}

describe('admin panel shell', () => {
    it('renders the home page at the root', async () => {
        const wrapper = await mountAt('/');

        expect(wrapper.find('h1').text()).toBe('Panel sklepu');
    });

    it('renders the not found page for unknown paths', async () => {
        const wrapper = await mountAt('/does/not/exist');

        expect(wrapper.find('h1').text()).toBe('Nie znaleziono');
        expect(wrapper.find('main a').attributes('href')).toBe('/');
    });

    it('keeps a live region in the page, so messages added later are announced', async () => {
        const wrapper = await mountAt('/');

        expect(wrapper.find('[aria-live="polite"]').exists()).toBe(true);
    });

    it('shows a message in the live region and closes it, keeping focus on the page', async () => {
        const wrapper = await mountAt('/', document.body);
        useFlashStore().show('error', 'Coś nie działa.');
        await flushPromises();

        expect(wrapper.find('[aria-live="polite"]').text()).toContain('Coś nie działa.');

        await wrapper.find('[aria-label="Zamknij komunikat"]').trigger('click');

        expect(wrapper.text()).not.toContain('Coś nie działa.');
        expect(document.activeElement).toBe(wrapper.find('main h1').element);
    });
});
