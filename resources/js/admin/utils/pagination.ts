export type PageItem = number | 'gap';

/**
 * Page numbers for a pagination bar: always the first and the last page, the current one with
 * `around` neighbours on each side, and a gap where pages are skipped. A gap never stands in for
 * a single page; that page is shown instead.
 */
export function pageRange(current: number, last: number, around = 1): PageItem[] {
    if (last <= 1) {
        return [1];
    }

    const page = Math.min(Math.max(current, 1), last);
    const pages = new Set<number>([1, last]);
    for (let p = page - around; p <= page + around; p++) {
        if (p >= 1 && p <= last) {
            pages.add(p);
        }
    }

    const items: PageItem[] = [];
    let previous = 0;
    for (const p of [...pages].sort((a, b) => a - b)) {
        if (p - previous === 2) {
            items.push(previous + 1);
        } else if (p - previous > 2) {
            items.push('gap');
        }
        items.push(p);
        previous = p;
    }

    return items;
}
