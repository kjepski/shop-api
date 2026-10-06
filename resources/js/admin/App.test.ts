import { mount, flushPromises } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import App from './App.vue';
import { createAdminRouter } from './router';

async function mountAt(path: string) {
    const router = createAdminRouter({ history: createMemoryHistory() });
    await router.push(path);
    await router.isReady();

    const wrapper = mount(App, { global: { plugins: [createPinia(), router] } });
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
        expect(wrapper.find('a').attributes('href')).toBe('/');
    });
});
