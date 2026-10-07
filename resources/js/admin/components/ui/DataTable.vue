<script setup lang="ts" generic="T extends object">
import { computed } from 'vue';

import { nextSort, sortDirection } from '@admin/utils/sort';

import type { TableColumn } from './types';

const {
    columns,
    rows,
    rowKey,
    captionHidden = false,
    loading = false,
    emptyText = 'Brak wyników.',
    defaultSort = undefined,
} = defineProps<{
    columns: TableColumn[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** Name of the table for screen readers, e.g. "Produkty". */
    caption: string;
    /** Keep the caption for screen readers only, when a heading above already names the table. */
    captionHidden?: boolean;
    /** Rows stay visible while the next page or sort loads, so the table does not flash empty. */
    loading?: boolean;
    emptyText?: string;
    /** The API's sort when the request has none (e.g. `name`), so its column shows as sorted. */
    defaultSort?: string;
}>();

/** API sort value: `name` ascending, `-name` descending. */
const sort = defineModel<string>('sort');

const currentSort = computed(() => sort.value ?? defaultSort);

defineSlots<Record<`cell-${string}`, (props: { row: T }) => unknown>>();

/** Plain values only; objects (e.g. a related category) need a `cell-<key>` slot. */
function cellText(row: T, key: string): string {
    const value = (row as Record<string, unknown>)[key];

    if (typeof value === 'boolean') {
        return value ? 'Tak' : 'Nie';
    }

    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

/** Only the sorted column gets `aria-sort`, as the ARIA spec advises. */
function ariaSort(column: TableColumn): 'ascending' | 'descending' | undefined {
    const direction = column.sortable ? sortDirection(currentSort.value, column.key) : 'none';

    return direction === 'none' ? undefined : direction;
}

const ARROWS = { ascending: '▲', descending: '▼', none: '↕' } as const;
</script>

<template>
    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-sm" :aria-busy="loading || undefined">
            <caption :class="captionHidden ? 'sr-only' : 'px-4 py-3 text-left font-medium'">
                {{
                    caption
                }}
            </caption>
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-2 font-medium"
                        :class="{ 'text-right': column.align === 'right' }"
                        :aria-sort="ariaSort(column)"
                    >
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="inline-flex items-center gap-1 rounded hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            @click="sort = nextSort(currentSort, column.key)"
                        >
                            {{ column.label }}
                            <span aria-hidden="true" class="text-xs">{{
                                ARROWS[sortDirection(currentSort, column.key)]
                            }}</span>
                        </button>
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length" class="px-4 py-6 text-center text-gray-500">
                        {{ loading ? 'Ładowanie…' : emptyText }}
                    </td>
                </tr>
                <tr v-for="row in rows" :key="rowKey(row)">
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-2"
                        :class="{ 'text-right': column.align === 'right' }"
                    >
                        <slot :name="`cell-${column.key}`" :row="row">{{ cellText(row, column.key) }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
