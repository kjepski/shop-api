import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import * as auth from '@admin/api/auth';
import { ApiError } from '@admin/api/client';
import { useFlashStore } from '@admin/stores/flash';
import { useSessionStore } from '@admin/stores/session';
import type { User } from '@admin/types/user';

vi.mock('@admin/api/auth');

const admin: User = { id: 1, name: 'Admin', email: 'admin@example.com', is_admin: true, created_at: null };

beforeEach(() => {
    setActivePinia(createPinia());
    vi.resetAllMocks();
});

describe('session store', () => {
    it('asks /api/me once for concurrent callers', async () => {
        vi.mocked(auth.fetchMe).mockResolvedValue(admin);
        const session = useSessionStore();

        await Promise.all([session.ensureLoaded(), session.ensureLoaded()]);
        await session.ensureLoaded();

        expect(auth.fetchMe).toHaveBeenCalledOnce();
        expect(session.status).toBe('authenticated');
        expect(session.user).toEqual(admin);
        expect(session.isAdmin).toBe(true);
    });

    it('treats 401 at start as a guest, without a message', async () => {
        vi.mocked(auth.fetchMe).mockRejectedValue(new ApiError(401, 'Unauthenticated.'));
        const session = useSessionStore();

        await session.ensureLoaded();

        expect(session.status).toBe('guest');
        expect(useFlashStore().message).toBeNull();
    });

    it('reports other failures at start and continues as a guest', async () => {
        vi.mocked(auth.fetchMe).mockRejectedValue(new ApiError(0, 'x'));
        const session = useSessionStore();

        await session.ensureLoaded();

        expect(session.status).toBe('guest');
        expect(useFlashStore().message).toMatchObject({ type: 'error' });
        expect(useFlashStore().message?.text).toContain('Brak połączenia');
    });

    it('logs in', async () => {
        vi.mocked(auth.login).mockResolvedValue({ ...admin, is_admin: false });
        const session = useSessionStore();

        await session.login('admin@example.com', 'password');

        expect(auth.login).toHaveBeenCalledWith('admin@example.com', 'password');
        expect(session.status).toBe('authenticated');
        expect(session.isAdmin).toBe(false);
    });

    it('is not logged in when login fails', async () => {
        vi.mocked(auth.login).mockRejectedValue(new ApiError(422, 'Wrong.', { email: ['Wrong.'] }));
        const session = useSessionStore();

        await expect(session.login('a@example.com', 'x')).rejects.toBeInstanceOf(ApiError);
        expect(session.status).toBe('unknown');
        expect(session.user).toBeNull();
    });

    async function loggedIn() {
        vi.mocked(auth.fetchMe).mockResolvedValue(admin);
        const session = useSessionStore();
        await session.ensureLoaded();

        return session;
    }

    it('logs out', async () => {
        const session = await loggedIn();
        vi.mocked(auth.logout).mockResolvedValue();

        await session.logout();

        expect(session.status).toBe('guest');
        expect(session.user).toBeNull();
        expect(useFlashStore().message?.text).toBe('Wylogowano.');
    });

    it('counts 401 on logout as logged out', async () => {
        const session = await loggedIn();
        let expiredDuringLogout: boolean | undefined;
        vi.mocked(auth.logout).mockImplementation(() => {
            // Like the API client: the 401 handler runs before the request rejects.
            expiredDuringLogout = session.expire();
            return Promise.reject(new ApiError(401, 'Unauthenticated.'));
        });

        await session.logout();

        // No "session expired" message or redirect on top of the logout itself.
        expect(expiredDuringLogout).toBe(false);

        expect(session.status).toBe('guest');
        expect(useFlashStore().message?.text).toBe('Wylogowano.');
    });

    it('stays logged in when the server could not end the session', async () => {
        const session = await loggedIn();
        vi.mocked(auth.logout).mockRejectedValue(new ApiError(0, 'x'));

        await expect(session.logout()).rejects.toMatchObject({ status: 0 });

        expect(session.status).toBe('authenticated');
        expect(session.user).toEqual(admin);
    });

    it('expires a live session once', async () => {
        const session = await loggedIn();

        expect(session.expire()).toBe(true);
        expect(session.status).toBe('guest');
        expect(useFlashStore().message?.text).toBe('Sesja wygasła, zaloguj się ponownie.');

        expect(session.expire()).toBe(false);
    });

    it('does not expire a session that never started', () => {
        expect(useSessionStore().expire()).toBe(false);
        expect(useFlashStore().message).toBeNull();
    });
});
