<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, useId, useTemplateRef, watch } from 'vue';

import AppButton from './AppButton.vue';

/**
 * Confirmation only (e.g. deleting); details and forms are separate pages, never dialogs.
 * The native modal dialog makes the rest of the page inert (focus stays inside) and turns
 * Escape into a `cancel` event.
 */
const open = defineModel<boolean>('open', { required: true });

const {
    confirmLabel = 'Potwierdź',
    cancelLabel = 'Anuluj',
    danger = false,
    busy = false,
} = defineProps<{
    title: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Red confirm button for actions that cannot be undone. */
    danger?: boolean;
    /** The confirmed action is running: the dialog cannot be cancelled until it ends. */
    busy?: boolean;
}>();

const emit = defineEmits<{ confirm: []; cancel: [] }>();

defineSlots<{ default(): unknown }>();

const dialog = useTemplateRef<HTMLDialogElement>('dialog');
const titleId = useId();
const bodyId = useId();

let returnFocusTo: HTMLElement | null = null;

/** Cancel first: Enter right after opening must not confirm a delete by accident. */
function focusInitial(): void {
    dialog.value?.querySelector<HTMLElement>('[data-initial-focus]')?.focus();
}

async function show(): Promise<void> {
    returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.value?.showModal();
    await nextTick();
    focusInitial();
}

function restoreFocus(): void {
    // The opener may be gone (e.g. the delete button of a removed row): fall back to the page heading.
    const target = returnFocusTo?.isConnected ? returnFocusTo : document.querySelector<HTMLElement>('main h1');
    target?.focus();
    returnFocusTo = null;
}

function hide(): void {
    if (dialog.value?.open) {
        dialog.value.close();
    }
    restoreFocus();
}

function cancel(): void {
    if (busy) {
        return;
    }
    open.value = false;
    emit('cancel');
}

/**
 * The browser closed the dialog by itself, e.g. after a second Escape, which Chrome does not let
 * the page prevent. Keep the dialog in step with `open`: reopen it while the action runs,
 * otherwise treat it as cancel.
 */
function onNativeClose(): void {
    if (!open.value) {
        return;
    }
    if (busy) {
        dialog.value?.showModal();
        dialog.value?.focus();
        return;
    }
    cancel();
}

// After the DOM update, so a removed opener (e.g. its row is gone) is already disconnected.
watch(open, (isOpen) => (isOpen ? void show() : hide()), { flush: 'post' });

// While the action runs both buttons are disabled, which would drop focus out of the dialog.
watch(
    () => busy,
    async (isBusy) => {
        await nextTick();
        if (!open.value) {
            return;
        }
        if (isBusy) {
            dialog.value?.focus();
        } else {
            focusInitial();
        }
    },
);

onMounted(() => {
    if (open.value) {
        void show();
    }
});

onBeforeUnmount(() => {
    if (open.value) {
        restoreFocus();
    }
});
</script>

<template>
    <dialog
        ref="dialog"
        tabindex="-1"
        :role="danger ? 'alertdialog' : undefined"
        :aria-labelledby="titleId"
        :aria-describedby="bodyId"
        class="m-auto w-full max-w-md rounded p-0 shadow-xl backdrop:bg-black/40"
        @cancel.prevent="cancel"
        @close="onNativeClose"
    >
        <div class="p-6">
            <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
            <div :id="bodyId" class="mt-2 text-sm text-gray-700"><slot /></div>
            <div class="mt-6 flex justify-end gap-2">
                <AppButton data-initial-focus variant="secondary" :disabled="busy" @click="cancel">
                    {{ cancelLabel }}
                </AppButton>
                <AppButton :variant="danger ? 'danger' : 'primary'" :busy="busy" @click="emit('confirm')">
                    {{ confirmLabel }}
                </AppButton>
            </div>
        </div>
    </dialog>
</template>
