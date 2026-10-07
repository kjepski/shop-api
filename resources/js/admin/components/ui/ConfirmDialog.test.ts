import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';

import ConfirmDialog from '@admin/components/ui/ConfirmDialog.vue';

enableAutoUnmount(afterEach);

// jsdom has no modal dialogs (no showModal/close at all); the browser's own part
// (inert page, top layer) is not tested here.
beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
        this.setAttribute('open', '');
    };
    HTMLDialogElement.prototype.close = function (this: HTMLDialogElement) {
        this.removeAttribute('open');
    };
});

afterAll(() => {
    Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal');
    Reflect.deleteProperty(HTMLDialogElement.prototype, 'close');
});

beforeEach(() => {
    document.body.innerHTML = '';
});

/** A page with a "Usuń" button that opens the dialog, like a details page will. */
function mountPage(props: { busy?: boolean; open?: boolean } = {}) {
    const open = ref(props.open ?? false);
    const busy = ref(props.busy ?? false);
    const showTrigger = ref(true);
    const events: string[] = [];

    const Page = defineComponent(() => () => [
        h('main', [h('h1', { tabindex: -1 }, 'Produkt')]),
        showTrigger.value ? h('button', { id: 'delete', onClick: () => (open.value = true) }, 'Usuń') : null,
        h(
            ConfirmDialog,
            {
                open: open.value,
                'onUpdate:open': (value: boolean) => (open.value = value),
                title: 'Usunąć produkt?',
                confirmLabel: 'Usuń produkt',
                danger: true,
                busy: busy.value,
                onConfirm: () => events.push('confirm'),
                onCancel: () => events.push('cancel'),
            },
            () => 'Tej operacji nie można cofnąć.',
        ),
    ]);

    const wrapper = mount(Page, { attachTo: document.body });

    return { wrapper, open, busy, showTrigger, events };
}

async function openFromButton(page: ReturnType<typeof mountPage>) {
    const trigger = page.wrapper.find<HTMLButtonElement>('#delete');
    trigger.element.focus();
    await trigger.trigger('click');
    await nextTick();
    await nextTick();

    return page.wrapper.find('dialog');
}

function button(page: ReturnType<typeof mountPage>, text: string) {
    const found = page.wrapper.findAll('dialog button').find((b) => b.text() === text);
    if (!found) {
        throw new Error(`No button ${text}`);
    }

    return found;
}

describe('ConfirmDialog', () => {
    it('is closed until asked', () => {
        const page = mountPage();

        expect(page.wrapper.find('dialog').attributes('open')).toBeUndefined();
    });

    it('opens as a named dialog with its message and focuses cancel', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);

        expect(dialog.attributes('open')).toBeDefined();
        expect(page.wrapper.find(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('Usunąć produkt?');
        expect(page.wrapper.find(`#${dialog.attributes('aria-describedby')}`).text()).toBe(
            'Tej operacji nie można cofnąć.',
        );
        expect(document.activeElement).toBe(button(page, 'Anuluj').element);
    });

    it('closes on cancel and gives focus back to the button that opened it', async () => {
        const page = mountPage();
        await openFromButton(page);

        await button(page, 'Anuluj').trigger('click');
        await nextTick();

        expect(page.open.value).toBe(false);
        expect(page.events).toEqual(['cancel']);
        expect(page.wrapper.find('dialog').attributes('open')).toBeUndefined();
        expect(document.activeElement?.id).toBe('delete');
    });

    it('closes on Escape', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);

        // What the browser fires when Escape is pressed in a modal dialog.
        const escape = new Event('cancel', { cancelable: true });
        dialog.element.dispatchEvent(escape);
        await nextTick();

        expect(escape.defaultPrevented).toBe(true);
        expect(page.open.value).toBe(false);
        expect(page.events).toEqual(['cancel']);
    });

    it('reports confirm and leaves closing to the page', async () => {
        const page = mountPage();
        await openFromButton(page);

        await button(page, 'Usuń produkt').trigger('click');

        expect(page.events).toEqual(['confirm']);
        expect(page.open.value).toBe(true);
    });

    it('marks the confirm button as destructive', async () => {
        const page = mountPage();
        await openFromButton(page);

        expect(button(page, 'Usuń produkt').classes()).toContain('bg-red-600');
    });

    it('is announced as an alert dialog when it confirms something destructive', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);

        expect(dialog.attributes('role')).toBe('alertdialog');
    });

    it('opens when it is mounted already open', async () => {
        const page = mountPage({ open: true });
        await nextTick();
        await nextTick();

        expect(page.wrapper.find('dialog').attributes('open')).toBeDefined();
        expect(document.activeElement).toBe(button(page, 'Anuluj').element);
    });

    it('treats a close by the browser itself as cancel', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);

        // E.g. a second Escape in Chrome, which the page cannot prevent.
        dialog.element.removeAttribute('open');
        dialog.element.dispatchEvent(new Event('close'));
        await nextTick();

        expect(page.open.value).toBe(false);
        expect(page.events).toEqual(['cancel']);
    });

    it('reopens itself when the browser closes it while the action runs', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);
        page.busy.value = true;
        await nextTick();

        dialog.element.removeAttribute('open');
        dialog.element.dispatchEvent(new Event('close'));
        await nextTick();

        expect(dialog.attributes('open')).toBeDefined();
        expect(page.open.value).toBe(true);
        expect(page.events).toEqual([]);
    });

    it('keeps focus in the dialog while the action runs and gives it back to cancel after', async () => {
        const page = mountPage();
        const dialog = await openFromButton(page);
        (button(page, 'Usuń produkt').element as HTMLButtonElement).focus();

        page.busy.value = true;
        await nextTick();
        await nextTick();
        expect(document.activeElement).toBe(dialog.element);

        // The action failed and the page keeps the dialog open.
        page.busy.value = false;
        await nextTick();
        await nextTick();
        expect(document.activeElement).toBe(button(page, 'Anuluj').element);
    });

    it('focuses the page heading when the button that opened it is gone', async () => {
        const page = mountPage();
        await openFromButton(page);

        page.showTrigger.value = false;
        page.open.value = false;
        await nextTick();
        await nextTick();

        expect(document.activeElement?.textContent).toBe('Produkt');
    });

    it('cannot be cancelled while the confirmed action runs', async () => {
        const page = mountPage({ busy: true });
        const dialog = await openFromButton(page);

        dialog.element.dispatchEvent(new Event('cancel', { cancelable: true }));
        await nextTick();

        expect(page.open.value).toBe(true);
        expect(page.events).toEqual([]);
        expect(button(page, 'Anuluj').attributes('disabled')).toBeDefined();
        expect(button(page, 'Usuń produkt').attributes('aria-busy')).toBe('true');
    });

    it('gives focus back when the page closes it after confirming', async () => {
        const page = mountPage();
        await openFromButton(page);

        page.open.value = false;
        await nextTick();
        await nextTick();

        expect(page.wrapper.find('dialog').attributes('open')).toBeUndefined();
        expect(document.activeElement?.id).toBe('delete');
    });
});
