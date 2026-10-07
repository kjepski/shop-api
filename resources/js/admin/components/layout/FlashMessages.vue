<script setup lang="ts">
import { storeToRefs } from 'pinia';

import { useFlashStore } from '@admin/stores/flash';

const flash = useFlashStore();
const { message } = storeToRefs(flash);

/** The close button disappears with the message; keep keyboard focus on the page heading. */
function close(): void {
    flash.dismiss();
    document.querySelector<HTMLElement>('main h1')?.focus();
}
</script>

<template>
    <!-- The live region is always in the page, so screen readers announce messages added to it. -->
    <div aria-live="polite" class="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4">
        <div
            v-if="message"
            :key="message.id"
            class="pointer-events-auto flex max-w-lg items-start gap-3 rounded px-4 py-3 text-sm shadow"
            :class="message.type === 'error' ? 'bg-red-50 text-red-800' : 'bg-indigo-50 text-indigo-800'"
        >
            <p>{{ message.text }}</p>
            <button
                type="button"
                class="ml-auto shrink-0 rounded px-1 font-semibold hover:bg-black/5 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                aria-label="Zamknij komunikat"
                @click="close"
            >
                ×
            </button>
        </div>
    </div>
</template>
