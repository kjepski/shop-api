import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createWebHistory, type RouterHistory } from 'vue-router';

import App from '@admin/App.vue';
import * as auth from '@admin/api/auth';
import { ApiError } from '@admin/api/client';
import { BASE_PATH, createAdminRouter } from '@admin/router';
import type { User } from '@admin/types/user';

vi.mock('@admin/api/auth');

enableAutoUnmount(afterEach);

const user: User = { id: 2, name: 'Jan', email: 'jan@example.com', is_admin: false, created_at: null };

beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(auth.fetchMe).mockRejectedValue(new ApiError(401, 'Unauthenticated.'));
    document.body.innerHTML = '';
});

async function openLogin(path = '/login', history: RouterHistory = createMemoryHistory(BASE_PATH)) {
    const pinia = createPinia();
    const router = createAdminRouter({ history });
    const wrapper = mount(App, { global: { plugins: [pinia, router] }, attachTo: document.body });
    await router.push(path);
    await flushPromises();

    return { router, wrapper };
}

async function fillAndSubmit(wrapper: Awaited<ReturnType<typeof openLogin>>['wrapper']) {
    await wrapper.find('input[type="email"]').setValue('jan@example.com');
    await wrapper.find('input[type="password"]').setValue('password');
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

describe('login page', () => {
    it('labels both fields', async () => {
        const { wrapper } = await openLogin();

        const email = wrapper.find('input[type="email"]');
        const password = wrapper.find('input[type="password"]');

        expect(wrapper.find(`label[for="${email.attributes('id')}"]`).text()).toBe('E-mail');
        expect(wrapper.find(`label[for="${password.attributes('id')}"]`).text()).toBe('Hasło');
        expect(wrapper.find('header').exists()).toBe(false);
    });

    it('logs in and goes to the page the user came for', async () => {
        vi.mocked(auth.login).mockResolvedValue(user);
        // Browser history (jsdom), which records the previous entry for "back".
        const { router, wrapper } = await openLogin('/login?redirect=/missing%3Fa%3D1', createWebHistory(BASE_PATH));
        const backBeforeLogin = router.options.history.state.back;

        await fillAndSubmit(wrapper);

        expect(auth.login).toHaveBeenCalledWith('jan@example.com', 'password');
        // The target screen is loaded lazily, so the navigation needs a moment.
        await vi.waitFor(() => expect(router.currentRoute.value.fullPath).toBe('/missing?a=1'));
        expect(wrapper.find('header').text()).toContain('Jan');
        // The target replaced the login form in history, so "back" does not lead to the form again.
        expect(router.options.history.state.back).toBe(backBeforeLogin);
    });

    it('goes home when the redirect points outside the panel', async () => {
        vi.mocked(auth.login).mockResolvedValue(user);
        const { router, wrapper } = await openLogin('/login?redirect=//evil.example.com');

        await fillAndSubmit(wrapper);

        await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('home'));
    });

    it('shows the field error from 422 next to the field and focuses it', async () => {
        vi.mocked(auth.login).mockRejectedValue(
            new ApiError(422, 'These credentials do not match our records.', {
                email: ['These credentials do not match our records.'],
            }),
        );
        const { router, wrapper } = await openLogin();

        await fillAndSubmit(wrapper);

        const email = wrapper.find('input[type="email"]');
        const describedBy = email.attributes('aria-describedby');
        expect(email.attributes('aria-invalid')).toBe('true');
        expect(describedBy).toBeTruthy();
        expect(wrapper.find(`#${describedBy}`).text()).toBe('These credentials do not match our records.');
        expect(document.activeElement).toBe(email.element);
        expect(router.currentRoute.value.name).toBe('login');
    });

    it('tells how long to wait after too many attempts', async () => {
        vi.mocked(auth.login).mockRejectedValue(new ApiError(429, 'Too Many Attempts.', {}, 45));
        const { wrapper } = await openLogin();

        await fillAndSubmit(wrapper);

        expect(wrapper.find('[role="alert"]').text()).toBe('Za dużo prób. Spróbuj ponownie za 45 s.');
    });

    it('disables the button while logging in', async () => {
        let finish!: (value: User) => void;
        vi.mocked(auth.login).mockReturnValue(new Promise((resolve) => (finish = resolve)));
        const { wrapper } = await openLogin();

        await wrapper.find('form').trigger('submit');

        const button = wrapper.find('button[type="submit"]');
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('aria-busy')).toBe('true');
        expect(button.text()).toBe('Logowanie…');

        finish(user);
        await flushPromises();
    });
});
