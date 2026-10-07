import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';

import PaginationNav from '@admin/components/ui/PaginationNav.vue';
import type { PaginationMeta } from '@admin/types/api';

enableAutoUnmount(afterEach);

function meta(current_page: number, last_page: number, total = last_page * 15): PaginationMeta {
    const from = total === 0 ? null : (current_page - 1) * 15 + 1;

    return {
        current_page,
        last_page,
        per_page: 15,
        total,
        from,
        to: from === null ? null : Math.min(current_page * 15, total),
    };
}

async function mountNav(value: PaginationMeta) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/products', component: { render: () => null } }],
    });
    await router.push('/products');

    return mount(PaginationNav, {
        props: { meta: value, pageLink: (page: number) => ({ path: '/products', query: { page } }) },
        global: { plugins: [router] },
    });
}

function link(wrapper: Awaited<ReturnType<typeof mountNav>>, text: string) {
    return wrapper.findAll('a').find((a) => a.text() === text);
}

describe('PaginationNav', () => {
    it('tells which rows are shown', async () => {
        const wrapper = await mountNav(meta(2, 3, 40));

        expect(wrapper.find('nav').attributes('aria-label')).toBe('Paginacja');
        expect(wrapper.find('p').text()).toBe('16–30 z 40');
    });

    it('links pages and marks the current one', async () => {
        const wrapper = await mountNav(meta(2, 3, 40));

        expect(link(wrapper, '1')?.attributes('href')).toBe('/products?page=1');
        expect(link(wrapper, '2')?.attributes('aria-current')).toBe('page');
        expect(link(wrapper, '3')?.attributes('aria-current')).toBeUndefined();
        expect(link(wrapper, 'Poprzednia')?.attributes('href')).toBe('/products?page=1');
        expect(link(wrapper, 'Następna')?.attributes('href')).toBe('/products?page=3');
    });

    it('has no previous link on the first page and no next link on the last', async () => {
        const first = await mountNav(meta(1, 3));
        expect(link(first, 'Poprzednia')).toBeUndefined();
        expect(link(first, 'Następna')).toBeDefined();

        const last = await mountNav(meta(3, 3));
        expect(link(last, 'Następna')).toBeUndefined();
        expect(link(last, 'Poprzednia')).toBeDefined();
    });

    it('hides skipped pages behind a gap that screen readers skip', async () => {
        const wrapper = await mountNav(meta(5, 10));

        expect(wrapper.findAll('li').map((li) => li.text())).toEqual([
            'Poprzednia',
            '1',
            '…',
            '4',
            '5',
            '6',
            '…',
            '10',
            'Następna',
        ]);
        expect(wrapper.findAll('span[aria-hidden="true"]')).toHaveLength(2);
        expect(link(wrapper, '4')?.attributes('aria-label')).toBe('Strona 4');
    });

    it('shows no page links for a single page', async () => {
        const wrapper = await mountNav(meta(1, 1, 7));
        await flushPromises();

        expect(wrapper.find('p').text()).toBe('1–7 z 7');
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('handles a page past the end, e.g. an old link after rows were deleted', async () => {
        const wrapper = await mountNav({
            current_page: 9,
            last_page: 5,
            per_page: 15,
            total: 40,
            from: null,
            to: null,
        });

        expect(wrapper.find('p').text()).toBe('Ta strona jest pusta (40 wyników)');
        expect(link(wrapper, 'Poprzednia')?.attributes('href')).toBe('/products?page=5');
    });

    it('says when there are no results', async () => {
        const wrapper = await mountNav(meta(1, 1, 0));

        expect(wrapper.find('p').text()).toBe('Brak wyników');
    });
});
