import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError, request, setUnauthorizedHandler } from '@admin/api/client';

const fetchMock = vi.fn<typeof fetch>();

function setCsrfCookie(value: string | null): void {
    document.cookie =
        value === null
            ? 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
            : `XSRF-TOKEN=${encodeURIComponent(value)}; path=/`;
}

function json(status: number, body: unknown, headers: Record<string, string> = {}): Response {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
}

/** URL and init of the n-th fetch call. */
function call(n: number): { url: string; init: RequestInit } {
    const [url, init] = fetchMock.mock.calls[n] ?? [];
    if (typeof url !== 'string') {
        throw new Error(`fetch call ${n} has no string URL`);
    }

    return { url, init: init ?? {} };
}

function header(n: number, name: string): string | undefined {
    return (call(n).init.headers as Record<string, string>)[name];
}

beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock);
    setCsrfCookie('token=1');
});

afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    setUnauthorizedHandler(null);
    setCsrfCookie(null);
});

describe('api client', () => {
    it('sends cookies and echoes the decoded CSRF cookie in a header', async () => {
        fetchMock.mockResolvedValue(json(200, { data: { id: 1 } }));

        await expect(request('GET', '/me')).resolves.toEqual({ data: { id: 1 } });

        expect(call(0).url).toBe('/api/me');
        expect(call(0).init.credentials).toBe('same-origin');
        expect(header(0, 'Accept')).toBe('application/json');
        expect(header(0, 'X-XSRF-TOKEN')).toBe('token=1');
    });

    it('sends a JSON body', async () => {
        fetchMock.mockResolvedValue(json(200, {}));

        await request('POST', '/session', { body: { email: 'a@example.com' } });

        expect(call(0).init.method).toBe('POST');
        expect(call(0).init.body).toBe('{"email":"a@example.com"}');
        expect(header(0, 'Content-Type')).toBe('application/json');
    });

    it('resolves with null for 204', async () => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }));

        await expect(request('POST', '/logout')).resolves.toBeNull();
    });

    it('sends no Content-Type without a body', async () => {
        fetchMock.mockResolvedValue(json(200, {}));

        await request('GET', '/me');

        expect(header(0, 'Content-Type')).toBeUndefined();
        expect(call(0).init.body).toBeUndefined();
    });

    it.each(['POST', 'PUT', 'PATCH', 'DELETE'] as const)(
        'gets the CSRF cookie before %s when it is missing',
        async (method) => {
            setCsrfCookie(null);
            fetchMock.mockImplementation((url) => {
                if (url === '/sanctum/csrf-cookie') {
                    setCsrfCookie('fresh');
                    return Promise.resolve(new Response(null, { status: 204 }));
                }
                return Promise.resolve(json(200, {}));
            });

            await request(method, '/products/1', { body: {} });

            expect(call(0).url).toBe('/sanctum/csrf-cookie');
            expect(call(1).url).toBe('/api/products/1');
            expect(header(1, 'X-XSRF-TOKEN')).toBe('fresh');
        },
    );

    it('does not ask for the CSRF cookie before a read', async () => {
        setCsrfCookie(null);
        fetchMock.mockResolvedValue(json(200, {}));

        await request('GET', '/me');

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(call(0).url).toBe('/api/me');
    });

    it('refreshes the CSRF cookie and retries once after 419', async () => {
        fetchMock
            .mockResolvedValueOnce(json(419, { message: 'CSRF token mismatch.' }))
            .mockImplementationOnce(() => {
                setCsrfCookie('fresh');
                return Promise.resolve(new Response(null, { status: 204 }));
            })
            .mockResolvedValueOnce(json(200, { ok: true }));

        await expect(request('POST', '/logout')).resolves.toEqual({ ok: true });

        expect(call(1).url).toBe('/sanctum/csrf-cookie');
        expect(header(2, 'X-XSRF-TOKEN')).toBe('fresh');
    });

    it('gives up after one retry when 419 repeats', async () => {
        fetchMock.mockImplementation((url) =>
            Promise.resolve(
                url === '/sanctum/csrf-cookie'
                    ? new Response(null, { status: 204 })
                    : json(419, { message: 'CSRF token mismatch.' }),
            ),
        );

        await expect(request('POST', '/logout')).rejects.toMatchObject({ status: 419 });
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('rejects with field errors on 422', async () => {
        fetchMock.mockResolvedValue(
            json(422, { message: 'The email field is required.', errors: { email: ['The email field is required.'] } }),
        );

        const error = await request('POST', '/session', { body: {} }).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({
            status: 422,
            message: 'The email field is required.',
            errors: { email: ['The email field is required.'] },
        });
    });

    it('reads Retry-After seconds on 429', async () => {
        fetchMock.mockResolvedValue(json(429, { message: 'Too Many Attempts.' }, { 'Retry-After': '42' }));

        await expect(request('POST', '/session', { body: {} })).rejects.toMatchObject({ status: 429, retryAfter: 42 });
    });

    it.each([[null], [''], ['soon'], ['-1'], ['1.5']])('ignores an unusable Retry-After (%s)', async (value) => {
        const headers: Record<string, string> = value === null ? {} : { 'Retry-After': value };
        fetchMock.mockResolvedValue(json(429, { message: 'Too Many Attempts.' }, headers));

        await expect(request('GET', '/products')).rejects.toMatchObject({ status: 429, retryAfter: null });
    });

    it('falls back to the status when the error body is not JSON', async () => {
        fetchMock.mockResolvedValue(new Response('<html>', { status: 500 }));

        await expect(request('GET', '/me')).rejects.toMatchObject({ status: 500, message: 'Błąd HTTP 500' });
    });

    it('turns a network failure into status 0', async () => {
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        await expect(request('GET', '/me')).rejects.toMatchObject({ status: 0 });
    });

    it('passes an aborted request through untouched', async () => {
        const abort = new DOMException('Aborted', 'AbortError');
        fetchMock.mockRejectedValue(abort);

        await expect(request('GET', '/products', { signal: new AbortController().signal })).rejects.toBe(abort);
    });

    it('calls the unauthorized handler on 401 only', async () => {
        const handler = vi.fn();
        setUnauthorizedHandler(handler);

        fetchMock.mockResolvedValueOnce(json(403, { message: 'Forbidden' }));
        await request('GET', '/users').catch(() => undefined);
        expect(handler).not.toHaveBeenCalled();

        fetchMock.mockResolvedValueOnce(json(401, { message: 'Unauthenticated.' }));
        await expect(request('GET', '/me')).rejects.toMatchObject({ status: 401 });
        expect(handler).toHaveBeenCalledOnce();
    });
});
