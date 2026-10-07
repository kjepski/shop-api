/** Sort values follow the API: `name` is ascending, `-name` descending. */

export type SortDirection = 'ascending' | 'descending' | 'none';

/** How the list is sorted by `key`, in the words of the `aria-sort` attribute. */
export function sortDirection(sort: string | undefined, key: string): SortDirection {
    if (sort === key) {
        return 'ascending';
    }
    if (sort === `-${key}`) {
        return 'descending';
    }

    return 'none';
}

/** Clicking a column header: the same column flips direction, another one starts ascending. */
export function nextSort(sort: string | undefined, key: string): string {
    return sort === key ? `-${key}` : key;
}
