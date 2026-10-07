import { describe, expect, it } from 'vitest';

import { nextSort, sortDirection } from '@admin/utils/sort';

describe('sortDirection', () => {
    it.each([
        ['name', 'name', 'ascending'],
        ['-name', 'name', 'descending'],
        ['price', 'name', 'none'],
        ['-price', 'name', 'none'],
        [undefined, 'name', 'none'],
        // A key that only starts or ends with another key is a different column.
        ['name_full', 'name', 'none'],
        ['full_name', 'name', 'none'],
        ['-full_name', 'name', 'none'],
    ] as const)('sort %s on column %s is %s', (sort, key, expected) => {
        expect(sortDirection(sort, key)).toBe(expected);
    });
});

describe('nextSort', () => {
    it('flips the direction of the current column', () => {
        expect(nextSort('name', 'name')).toBe('-name');
        expect(nextSort('-name', 'name')).toBe('name');
    });

    it('starts another column ascending', () => {
        expect(nextSort('-name', 'price')).toBe('price');
        expect(nextSort(undefined, 'price')).toBe('price');
    });
});
