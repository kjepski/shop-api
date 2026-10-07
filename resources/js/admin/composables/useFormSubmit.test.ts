import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';

import { ApiError } from '@admin/api/client';
import { useFormSubmit } from '@admin/composables/useFormSubmit';

enableAutoUnmount(afterEach);

function deferred() {
    let resolve!: () => void;
    let reject!: (error: unknown) => void;
    const promise = new Promise<void>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

describe('useFormSubmit', () => {
    it('is busy while the action runs and resolves with true on success', async () => {
        const action = deferred();
        const { busy, submit } = useFormSubmit(() => action.promise);

        const result = submit();
        expect(busy.value).toBe(true);

        action.resolve();
        await expect(result).resolves.toBe(true);
        expect(busy.value).toBe(false);
    });

    it('ignores a second submit while busy', async () => {
        const action = deferred();
        let calls = 0;
        const { submit } = useFormSubmit(() => {
            calls++;
            return action.promise;
        });

        const first = submit();
        await expect(submit()).resolves.toBe(false);
        action.resolve();
        await first;

        expect(calls).toBe(1);
    });

    it('keeps field errors of a 422 and clears them on the next submit', async () => {
        let fail = true;
        const { errors, message, submit, fieldError } = useFormSubmit(() =>
            fail
                ? Promise.reject(new ApiError(422, 'Invalid.', { email: ['Wrong.', 'Also wrong.'] }))
                : Promise.resolve(),
        );

        await expect(submit()).resolves.toBe(false);
        expect(fieldError('email')).toBe('Wrong.');
        expect(fieldError('password')).toBeUndefined();
        expect(message.value).toBeNull();

        fail = false;
        await submit();
        expect(errors.value).toEqual({});
    });

    it('turns other errors into a message', async () => {
        const { errors, message, submit } = useFormSubmit(() =>
            Promise.reject(new ApiError(429, 'Too Many Attempts.', {}, 10)),
        );

        await submit();

        expect(message.value).toBe('Za dużo prób. Spróbuj ponownie za 10 s.');
        expect(errors.value).toEqual({});
    });

    it('clears the previous message when submitting again', async () => {
        const action = deferred();
        let first = true;
        const { message, submit } = useFormSubmit(() => {
            if (first) {
                first = false;
                return Promise.reject(new ApiError(0, 'x'));
            }
            return action.promise;
        });

        await submit();
        expect(message.value).not.toBeNull();

        const second = submit();
        expect(message.value).toBeNull();
        action.resolve();
        await second;
    });

    it('moves focus to the first invalid field after a 422', async () => {
        const Form = defineComponent(() => {
            const form = ref<HTMLFormElement | null>(null);
            const { submit, fieldError } = useFormSubmit(
                () => Promise.reject(new ApiError(422, 'x', { password: ['Required.'] })),
                form,
            );

            return () =>
                h('form', { ref: form, onSubmit: (e: Event) => (e.preventDefault(), void submit()) }, [
                    h('input', { name: 'email', 'aria-invalid': fieldError('email') ? 'true' : undefined }),
                    h('input', { name: 'password', 'aria-invalid': fieldError('password') ? 'true' : undefined }),
                ]);
        });
        const wrapper = mount(Form, { attachTo: document.body });

        await wrapper.find('form').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve));

        expect(document.activeElement?.getAttribute('name')).toBe('password');
    });
});
