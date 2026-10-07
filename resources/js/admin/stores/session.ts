import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

import * as auth from '@admin/api/auth';
import { ApiError } from '@admin/api/client';
import type { User } from '@admin/types/user';
import { errorMessage } from '@admin/utils/errors';

import { useFlashStore } from './flash';

/** `unknown` until the first /api/me answers. */
export type SessionStatus = 'unknown' | 'guest' | 'authenticated';

function isUnauthorized(error: unknown): boolean {
    return error instanceof ApiError && error.status === 401;
}

export const useSessionStore = defineStore('session', () => {
    const flash = useFlashStore();

    const user = ref<User | null>(null);
    const status = ref<SessionStatus>('unknown');
    const isAdmin = computed(() => user.value?.is_admin === true);

    let loading: Promise<void> | null = null;
    let loggingOut = false;

    function setUser(value: User | null): void {
        user.value = value;
        status.value = value ? 'authenticated' : 'guest';
    }

    /** Asks /api/me once; navigations that start meanwhile wait for the same request. */
    function ensureLoaded(): Promise<void> {
        if (status.value !== 'unknown') {
            return Promise.resolve();
        }

        loading ??= auth
            .fetchMe()
            .then(setUser)
            .catch((error: unknown) => {
                setUser(null);
                // 401 just means "not logged in yet"; anything else is worth telling.
                if (!isUnauthorized(error)) {
                    flash.show('error', `Nie udało się sprawdzić sesji. ${errorMessage(error)}`);
                }
            })
            .finally(() => {
                loading = null;
            });

        return loading;
    }

    async function login(email: string, password: string): Promise<void> {
        setUser(await auth.login(email, password));
    }

    /** Rejects (and keeps the user logged in) when the server could not end the session. */
    async function logout(): Promise<void> {
        loggingOut = true;
        try {
            await auth.logout();
        } catch (error) {
            // 401: the session had already ended on the server, which is what we wanted.
            if (!isUnauthorized(error)) {
                throw error;
            }
        } finally {
            loggingOut = false;
        }

        setUser(null);
        flash.show('info', 'Wylogowano.');
    }

    /** A request got 401 mid-session. Returns true when the user was logged in until now. */
    function expire(): boolean {
        if (status.value !== 'authenticated' || loggingOut) {
            return false;
        }

        setUser(null);
        flash.show('info', 'Sesja wygasła, zaloguj się ponownie.');

        return true;
    }

    return { user, status, isAdmin, ensureLoaded, login, logout, expire };
});
