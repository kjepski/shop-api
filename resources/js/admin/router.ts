import { nextTick } from 'vue';
import {
    createRouter,
    createWebHistory,
    type RouteLocationNormalized,
    type RouteLocationRaw,
    type Router,
    type RouterHistory,
} from 'vue-router';

import { setUnauthorizedHandler } from '@admin/api/client';
import { useSessionStore } from '@admin/stores/session';
import { afterLoginTarget } from '@admin/utils/redirect';

declare module 'vue-router' {
    interface RouteMeta {
        /** Page name for the browser tab and screen readers; every route must set it. */
        title: string;
        /** Reachable without logging in; every other route requires a session. */
        public?: boolean;
    }
}

/**
 * Every screen has its own URL: lists, details (`/:id`), create (`/new`) and edit (`/:id/edit`).
 * Laravel serves the same shell for every /admin-next path, so any URL works on refresh.
 */
export const BASE_PATH = '/admin-next/';
export const APP_TITLE = 'Panel sklepu';

const RELOADED_FOR_KEY = 'admin-next:reloaded-for';

interface RouterOptions {
    history?: RouterHistory;
    /** Full page load of a URL; replaceable in tests, where jsdom cannot navigate. */
    reload?: (url: string) => void;
}

function readSession(key: string): string | null {
    try {
        return sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeSession(key: string, value: string): void {
    try {
        sessionStorage.setItem(key, value);
    } catch {
        // Blocked storage only means the loop guard is off; the reload itself still helps.
    }
}

export function createAdminRouter({
    history = createWebHistory(BASE_PATH),
    reload = (url) => window.location.assign(url),
}: RouterOptions = {}): Router {
    const router = createRouter({
        history,
        routes: [
            {
                path: '/',
                name: 'home',
                component: () => import('./features/home/HomePage.vue'),
                meta: { title: 'Start' },
            },
            {
                path: '/login',
                name: 'login',
                component: () => import('./features/auth/LoginPage.vue'),
                meta: { title: 'Logowanie', public: true },
            },
            {
                path: '/:pathMatch(.*)*',
                name: 'not-found',
                component: () => import('./features/errors/NotFoundPage.vue'),
                meta: { title: 'Nie znaleziono' },
            },
        ],
        // Back/forward restores the list position; a new screen starts at the top.
        scrollBehavior: (_to, _from, savedPosition) => savedPosition ?? { top: 0 },
    });

    // Requires an active Pinia: the app installs Pinia before the router starts navigating.
    router.beforeEach(async (to) => {
        const session = useSessionStore();
        await session.ensureLoaded();

        const loggedIn = session.status === 'authenticated';
        if (!to.meta.public && !loggedIn) {
            return loginFor(to);
        }
        if (to.name === 'login' && loggedIn) {
            return afterLoginTarget(to.query.redirect);
        }

        return true;
    });

    router.afterEach(async (to, from, failure) => {
        if (failure) {
            return;
        }

        document.title = `${to.meta.title} · ${APP_TITLE}`;

        // Screen readers announce the new screen; skipped on the first load to leave focus alone.
        if (from.matched.length > 0) {
            await nextTick();
            document.querySelector<HTMLElement>('main h1')?.focus();
        }
    });

    // After a deploy, an open tab may ask for page chunks that no longer exist. Load the target
    // URL fresh instead of failing silently; the stored path prevents a reload loop.
    router.onError((error: unknown, to) => {
        const isChunkError = error instanceof TypeError && /dynamically imported module/i.test(error.message);
        if (!isChunkError || readSession(RELOADED_FOR_KEY) === to.fullPath) {
            return;
        }

        writeSession(RELOADED_FOR_KEY, to.fullPath);
        reload(router.resolve(to).href);
    });

    redirectToLoginOnExpiredSession(router);

    return router;
}

/** The login page, coming back to `to` afterwards. */
function loginFor(to: RouteLocationNormalized): RouteLocationRaw {
    return to.name === 'home' ? { name: 'login' } : { name: 'login', query: { redirect: to.fullPath } };
}

/**
 * A 401 in the middle of the work (the session expired or was ended elsewhere) leads to the login
 * page, which then returns to the screen the user was on. One handler for the whole app: the most
 * recently created router owns it.
 */
function redirectToLoginOnExpiredSession(router: Router): void {
    setUnauthorizedHandler(() => {
        if (useSessionStore().expire()) {
            void router.replace(loginFor(router.currentRoute.value));
        }
    });
}
