import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import App from '@admin/App.vue';
import * as auth from '@admin/api/auth';
import { ApiError } from '@admin/api/client';
import { BASE_PATH, createAdminRouter } from '@admin/router';
import type { User } from '@admin/types/user';

vi.mock('@admin/api/auth');

enableAutoUnmount(afterEach);

const admin: User = { id: 1, name: 'Admin', email: 'admin@example.com', is_admin: true, created_at: null };

beforeEach(() => {
    vi.resetAllMocks();
});

async function mountLoggedIn(user: User = admin) {
    vi.mocked(auth.fetchMe).mockResolvedValue(user);
    const router = createAdminRouter({ history: createMemoryHistory(BASE_PATH) });
    const wrapper = mount(App, { global: { plugins: [createPinia(), router] } });
    await router.push('/');
    await flushPromises();

    return { router, wrapper };
}

function logoutButton(wrapper: Awaited<ReturnType<typeof mountLoggedIn>>['wrapper']) {
    const button = wrapper.findAll('header button').find((b) => b.text() === 'Wyloguj');
    if (!button) {
        throw new Error('No logout button');
    }

    return button;
}

describe('app header', () => {
    it('shows who is logged in', async () => {
        const { wrapper } = await mountLoggedIn();

        expect(wrapper.find('header').text()).toContain('Admin');
        expect(wrapper.find('header').text()).toContain('(administrator)');
        expect(wrapper.find('nav').attributes('aria-label')).toBe('Główna nawigacja');
    });

    it('does not call a regular user an administrator', async () => {
        const { wrapper } = await mountLoggedIn({ ...admin, name: 'Jan', is_admin: false });

        expect(wrapper.find('header').text()).not.toContain('administrator');
    });

    it('logs out to the login page', async () => {
        vi.mocked(auth.logout).mockResolvedValue();
        const { router, wrapper } = await mountLoggedIn();

        await logoutButton(wrapper).trigger('click');

        // The login screen is loaded lazily, so the navigation needs a moment.
        await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('login'));
        expect(wrapper.find('header').exists()).toBe(false);
        expect(wrapper.text()).toContain('Wylogowano.');
    });

    it('stays logged in and says so when logout fails', async () => {
        vi.mocked(auth.logout).mockRejectedValue(new ApiError(0, 'x'));
        const { router, wrapper } = await mountLoggedIn();

        await logoutButton(wrapper).trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('home');
        expect(wrapper.text()).toContain('Nie udało się wylogować. Brak połączenia z serwerem.');
    });
});
