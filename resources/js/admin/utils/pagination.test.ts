import { describe, expect, it } from 'vitest';

import { pageRange } from '@admin/utils/pagination';

describe('pageRange', () => {
    it.each([
        ['a single page', 1, 1, [1]],
        ['an empty list (Laravel reports last_page 1)', 1, 0, [1]],
        ['few pages, all shown', 2, 4, [1, 2, 3, 4]],
        ['gap on the right', 1, 10, [1, 2, 'gap', 10]],
        ['gap on the left', 10, 10, [1, 'gap', 9, 10]],
        ['gaps on both sides', 5, 10, [1, 'gap', 4, 5, 6, 'gap', 10]],
        ['a page instead of a gap hiding just one page', 4, 10, [1, 2, 3, 4, 5, 'gap', 10]],
        ['a page instead of a gap on the right', 7, 10, [1, 'gap', 6, 7, 8, 9, 10]],
        ['a current page past the end, like the last page', 99, 5, [1, 'gap', 4, 5]],
        ['a current page below 1', 0, 10, [1, 2, 'gap', 10]],
    ])('handles %s', (_label, current, last, expected) => {
        expect(pageRange(current, last)).toEqual(expected);
    });

    it('shows more neighbours when asked', () => {
        expect(pageRange(10, 20, 2)).toEqual([1, 'gap', 8, 9, 10, 11, 12, 'gap', 20]);
    });
});
