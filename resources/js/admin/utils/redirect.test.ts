import { describe, expect, it } from 'vitest';

import { afterLoginTarget, safeRedirect } from '@admin/utils/redirect';

describe('safeRedirect', () => {
    it.each(['/', '/products', '/products/12/edit?tab=1#top'])('keeps a panel path (%s)', (path) => {
        expect(safeRedirect(path)).toBe(path);
    });

    it.each([
        ['another site', 'https://evil.example.com'],
        ['protocol-relative', '//evil.example.com'],
        ['backslash trick', '/\\evil.example.com'],
        ['relative path', 'products'],
        ['javascript url', 'javascript:alert(1)'],
        ['empty', ''],
        ['array from repeated ?redirect=', ['/a', '/b']],
        ['missing', undefined],
        ['null', null],
    ])('rejects %s', (_label, value) => {
        expect(safeRedirect(value)).toBeNull();
    });
});

describe('afterLoginTarget', () => {
    it('goes to a safe redirect, otherwise to the start page', () => {
        expect(afterLoginTarget('/products?page=2')).toBe('/products?page=2');
        expect(afterLoginTarget('//evil.example.com')).toEqual({ name: 'home' });
        expect(afterLoginTarget(undefined)).toEqual({ name: 'home' });
    });
});
