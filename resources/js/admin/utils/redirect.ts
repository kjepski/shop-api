import type { RouteLocationRaw } from 'vue-router';

/**
 * The ?redirect= target after login, only when it is a path inside the panel. Anything else
 * (another site, `//host`, `/\host`, arrays) returns null, so a crafted link cannot send the
 * user away after they log in.
 */
export function safeRedirect(value: unknown): string | null {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) {
        return null;
    }

    return value;
}

/** Where to go after logging in: the safe ?redirect= target or the start page. */
export function afterLoginTarget(redirect: unknown): RouteLocationRaw {
    return safeRedirect(redirect) ?? { name: 'home' };
}
