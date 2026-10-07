import { defineStore } from 'pinia';
import { ref } from 'vue';

export type FlashType = 'info' | 'error';

export interface Flash {
    /** New for every message, so the same text shown twice is announced again. */
    id: number;
    type: FlashType;
    text: string;
}

export const INFO_HIDE_AFTER_MS = 5000;

/** One message at a time for the whole panel. Info hides itself; an error stays until dismissed. */
export const useFlashStore = defineStore('flash', () => {
    const message = ref<Flash | null>(null);
    let nextId = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    function dismiss(): void {
        clearTimeout(timer);
        message.value = null;
    }

    function show(type: FlashType, text: string): void {
        dismiss();
        message.value = { id: ++nextId, type, text };
        if (type === 'info') {
            timer = setTimeout(dismiss, INFO_HIDE_AFTER_MS);
        }
    }

    return { message, show, dismiss };
});
