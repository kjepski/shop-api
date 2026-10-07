import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { h } from 'vue';

import DataTable from '@admin/components/ui/DataTable.vue';
import type { TableColumn } from '@admin/components/ui/types';

enableAutoUnmount(afterEach);

interface Row {
    id: number;
    name: string;
    stock: number;
    category: { name: string } | null;
}

const rows: Row[] = [
    { id: 1, name: 'Kubek', stock: 3, category: { name: 'Dom' } },
    { id: 2, name: 'Talerz', stock: 0, category: null },
];

const columns: TableColumn[] = [
    { key: 'name', label: 'Nazwa', sortable: true },
    { key: 'stock', label: 'Stan', sortable: true, align: 'right' },
    { key: 'category', label: 'Kategoria' },
];

function mountTable(props: Record<string, unknown> = {}, slots = {}) {
    return mount(DataTable<Row>, {
        props: { columns, rows, rowKey: (row: Row) => row.id, caption: 'Produkty', ...props },
        slots,
    });
}

function header(wrapper: ReturnType<typeof mountTable>, label: string) {
    const th = wrapper.findAll('th').find((cell) => cell.text().startsWith(label));
    if (!th) {
        throw new Error(`No header ${label}`);
    }

    return th;
}

describe('DataTable', () => {
    it('names the table and its columns', () => {
        const wrapper = mountTable();

        expect(wrapper.find('caption').text()).toBe('Produkty');
        expect(wrapper.findAll('th').map((th) => th.attributes('scope'))).toEqual(['col', 'col', 'col']);
    });

    it('can keep the caption for screen readers only', () => {
        const wrapper = mountTable({ captionHidden: true });

        expect(wrapper.find('caption').classes()).toContain('sr-only');
    });

    it('shows plain values and leaves objects to slots', () => {
        const wrapper = mountTable();

        const firstRow = wrapper
            .findAll('tbody tr')[0]!
            .findAll('td')
            .map((td) => td.text());
        expect(firstRow).toEqual(['Kubek', '3', '']);
    });

    it('renders a cell through its slot', () => {
        const wrapper = mountTable(
            {},
            { 'cell-category': ({ row }: { row: Row }) => h('em', row.category?.name ?? '—') },
        );

        expect(wrapper.findAll('tbody tr')[0]!.find('em').text()).toBe('Dom');
        expect(wrapper.findAll('tbody tr')[1]!.find('em').text()).toBe('—');
    });

    it('marks the sorted column and offers sorting only where allowed', () => {
        const wrapper = mountTable({ sort: '-stock' });

        // Only the sorted column carries aria-sort.
        expect(header(wrapper, 'Nazwa').attributes('aria-sort')).toBeUndefined();
        expect(header(wrapper, 'Stan').attributes('aria-sort')).toBe('descending');
        expect(header(wrapper, 'Kategoria').attributes('aria-sort')).toBeUndefined();
        expect(header(wrapper, 'Kategoria').find('button').exists()).toBe(false);
    });

    it('changes the sort from a header button', async () => {
        const wrapper = mountTable({ sort: 'name' });

        await header(wrapper, 'Nazwa').find('button').trigger('click');
        await header(wrapper, 'Stan').find('button').trigger('click');

        expect(wrapper.emitted('update:sort')).toEqual([['-name'], ['stock']]);
    });

    it('shows the API default sort when the list has no sort of its own', async () => {
        const wrapper = mountTable({ sort: undefined, defaultSort: 'name' });

        expect(header(wrapper, 'Nazwa').attributes('aria-sort')).toBe('ascending');

        // The first click changes the order instead of asking for the same one again.
        await header(wrapper, 'Nazwa').find('button').trigger('click');
        expect(wrapper.emitted('update:sort')).toEqual([['-name']]);
    });

    it('shows yes or no for true and false', () => {
        const wrapper = mount(DataTable<{ id: number; active: boolean }>, {
            props: {
                columns: [{ key: 'active', label: 'Aktywny' }],
                rows: [
                    { id: 1, active: true },
                    { id: 2, active: false },
                ],
                rowKey: (row: { id: number }) => row.id,
                caption: 'Lista',
            },
        });

        expect(wrapper.findAll('tbody td').map((td) => td.text())).toEqual(['Tak', 'Nie']);
    });

    it('aligns numeric columns to the right', () => {
        const wrapper = mountTable();

        expect(header(wrapper, 'Stan').classes()).toContain('text-right');
        expect(wrapper.findAll('tbody tr')[0]!.findAll('td')[1]!.classes()).toContain('text-right');
        expect(header(wrapper, 'Nazwa').classes()).not.toContain('text-right');
    });

    it('says when there are no rows', () => {
        const wrapper = mountTable({ rows: [], emptyText: 'Brak produktów.' });

        const cell = wrapper.find('tbody td');
        expect(cell.text()).toBe('Brak produktów.');
        expect(cell.attributes('colspan')).toBe('3');
    });

    it('keeps the rows while loading and tells screen readers it is busy', () => {
        const wrapper = mountTable({ loading: true });

        expect(wrapper.find('table').attributes('aria-busy')).toBe('true');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('says it is loading instead of "no results" before the first rows arrive', () => {
        const wrapper = mountTable({ rows: [], loading: true });

        expect(wrapper.find('tbody td').text()).toBe('Ładowanie…');
    });
});
