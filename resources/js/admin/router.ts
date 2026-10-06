import { nextTick } from 'vue';
import { createRouter, createWebHistory, type Router, type RouterHistory } from 'vue-router';

declare module 'vue-router' {
    interface RouteMeta {
        /** Page name for the browser tab and screen readers; every route must set it. */
        title: string;
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
                path: '/:pathMatch(.*)*',
                name: 'not-found',
                component: () => import('./features/errors/NotFoundPage.vue'),
                meta: { title: 'Nie znaleziono' },
            },
        ],
        // Back/forward restores the list position; a new screen starts at the top.
        scrollBehavior: (_to, _from, savedPosition) => savedPosition ?? { top: 0 },
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

    return router;
}
