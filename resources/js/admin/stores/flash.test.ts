import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { INFO_HIDE_AFTER_MS, useFlashStore } from '@admin/stores/flash';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('flash store', () => {
    it('hides an info message by itself', () => {
        const flash = useFlashStore();

        flash.show('info', 'Wylogowano.');
        vi.advanceTimersByTime(INFO_HIDE_AFTER_MS - 1);
        expect(flash.message?.text).toBe('Wylogowano.');

        vi.advanceTimersByTime(1);
        expect(flash.message).toBeNull();
    });

    it('keeps an error until it is dismissed', () => {
        const flash = useFlashStore();

        flash.show('error', 'Brak połączenia.');
        vi.advanceTimersByTime(INFO_HIDE_AFTER_MS * 10);
        expect(flash.message?.text).toBe('Brak połączenia.');

        flash.dismiss();
        expect(flash.message).toBeNull();
    });

    it('does not let an older timer hide a newer message', () => {
        const flash = useFlashStore();

        flash.show('info', 'Pierwszy');
        vi.advanceTimersByTime(INFO_HIDE_AFTER_MS - 100);
        flash.show('error', 'Drugi');
        vi.advanceTimersByTime(200);

        expect(flash.message?.text).toBe('Drugi');
    });

    it('gives the same text shown again a new id, so it is announced again', () => {
        const flash = useFlashStore();

        flash.show('info', 'Wylogowano.');
        const first = flash.message?.id;
        flash.show('info', 'Wylogowano.');

        expect(flash.message?.id).not.toBe(first);
    });
});
