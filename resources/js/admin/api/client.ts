import type { ErrorBody, ValidationErrors } from '@admin/types/api';

/**
 * The only place in the panel that calls fetch. Authentication is the Sanctum SPA session:
 * an HttpOnly cookie the browser sends by itself, plus the XSRF-TOKEN cookie echoed back
 * in a header on every request.
 */

export type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
    body?: unknown;
    signal?: AbortSignal;
}

/** Failed request. Status 0 means the server could not be reached. */
export class ApiError extends Error {
    readonly status: number;
    /** Field errors of a 422 response. */
    readonly errors: ValidationErrors;
    /** Seconds until a rate limit (429) lets requests through again. */
    readonly retryAfter: number | null;

    constructor(status: number, message: string, errors: ValidationErrors = {}, retryAfter: number | null = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.retryAfter = retryAfter;
    }
}

const CSRF_COOKIE = 'XSRF-TOKEN';

let unauthorizedHandler: (() => void) | null = null;

/** Called on every 401, e.g. to send the user to the login page when the session expires. */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
    unauthorizedHandler = handler;
}

function readCsrfToken(): string | null {
    const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${CSRF_COOKIE}=`));

    return entry ? decodeURIComponent(entry.slice(CSRF_COOKIE.length + 1)) : null;
}

async function refreshCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
}

function send(method: Method, path: string, { body, signal }: RequestOptions): Promise<Response> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    const csrfToken = readCsrfToken();
    if (csrfToken !== null) {
        headers['X-XSRF-TOKEN'] = csrfToken;
    }

    return fetch(`/api${path}`, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
        signal,
    });
}

function parseRetryAfter(value: string | null): number | null {
    const seconds = Number(value);

    return value !== null && value.trim() !== '' && Number.isInteger(seconds) && seconds >= 0 ? seconds : null;
}

async function readResponse<T>(response: Response): Promise<T> {
    const body: unknown = response.status === 204 ? null : await response.json().catch(() => null);

    if (response.ok) {
        return body as T;
    }

    if (response.status === 401) {
        unauthorizedHandler?.();
    }

    const error = (body ?? {}) as ErrorBody;
    throw new ApiError(
        response.status,
        error.message ?? `Błąd HTTP ${response.status}`,
        error.errors ?? {},
        parseRetryAfter(response.headers.get('Retry-After')),
    );
}

/** Calls /api{path}; resolves with the JSON body (null for 204), rejects with ApiError. */
export async function request<T>(method: Method, path: string, options: RequestOptions = {}): Promise<T> {
    try {
        // The cookie expires with the session (e.g. after a long idle); without it every write gets 419.
        if (method !== 'GET' && readCsrfToken() === null) {
            await refreshCsrfCookie();
        }

        let response = await send(method, path, options);

        // 419: the CSRF token no longer matches the session (e.g. after logging in or out in another tab).
        // Get a fresh one and retry once.
        if (response.status === 419) {
            await refreshCsrfCookie();
            response = await send(method, path, options);
        }

        return await readResponse<T>(response);
    } catch (error) {
        // An aborted request is the caller's decision, not a failure to report.
        if (error instanceof ApiError || (error instanceof DOMException && error.name === 'AbortError')) {
            throw error;
        }

        throw new ApiError(0, 'Brak połączenia z serwerem.');
    }
}
