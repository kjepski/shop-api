<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';

import type { PaginationMeta } from '@admin/types/api';
import { pageRange } from '@admin/utils/pagination';

const {
    meta,
    pageLink,
    label = 'Paginacja',
} = defineProps<{
    meta: PaginationMeta;
    /** Address of a page; real links, so a page opens in a new tab and "back" works. */
    pageLink: (page: number) => RouteLocationRaw;
    /** Name of the navigation for screen readers, when a screen has more than one list. */
    label?: string;
}>();

const pages = computed(() => pageRange(meta.current_page, meta.last_page));

const linkClass = 'block rounded px-3 py-1.5 hover:bg-gray-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none';
</script>

<template>
    <nav :aria-label="label" class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="text-gray-600">
            <template v-if="meta.from !== null && meta.to !== null">
                {{ meta.from }}–{{ meta.to }} z {{ meta.total }}
            </template>
            <template v-else-if="meta.total > 0">Ta strona jest pusta ({{ meta.total }} wyników)</template>
            <template v-else>Brak wyników</template>
        </p>
        <ul v-if="meta.last_page > 1" class="flex flex-wrap items-center gap-1">
            <li v-if="meta.current_page > 1">
                <!-- A page past the end (e.g. an old link after rows were deleted) leads back to the last one. -->
                <RouterLink :to="pageLink(Math.min(meta.current_page - 1, meta.last_page))" :class="linkClass">
                    Poprzednia
                </RouterLink>
            </li>
            <li v-for="(item, index) in pages" :key="item === 'gap' ? `gap-${index}` : item">
                <span v-if="item === 'gap'" class="px-2 text-gray-500" aria-hidden="true">…</span>
                <RouterLink
                    v-else
                    :to="pageLink(item)"
                    :aria-label="`Strona ${item}`"
                    :aria-current="item === meta.current_page ? 'page' : undefined"
                    :class="[
                        linkClass,
                        item === meta.current_page ? 'bg-indigo-100 font-semibold text-indigo-700' : '',
                    ]"
                >
                    {{ item }}
                </RouterLink>
            </li>
            <li v-if="meta.current_page < meta.last_page">
                <RouterLink :to="pageLink(meta.current_page + 1)" :class="linkClass">Następna</RouterLink>
            </li>
        </ul>
    </nav>
</template>
