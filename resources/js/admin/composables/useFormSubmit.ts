import { nextTick, ref, type Ref } from 'vue';

import type { ValidationErrors } from '@admin/types/api';
import { errorMessage, validationErrors } from '@admin/utils/errors';

/**
 * Submitting a form to the API: busy state, field errors from 422 and a message for anything else.
 * After a 422 focus moves to the first invalid field, so screen reader users hear what is wrong.
 */
export function useFormSubmit(action: () => Promise<void>, form?: Readonly<Ref<HTMLFormElement | null>>) {
    const busy = ref(false);
    const errors = ref<ValidationErrors>({});
    const message = ref<string | null>(null);

    /** Resolves with true when the action succeeded. A second submit while busy is ignored. */
    async function submit(): Promise<boolean> {
        if (busy.value) {
            return false;
        }

        busy.value = true;
        errors.value = {};
        message.value = null;

        try {
            await action();

            return true;
        } catch (error) {
            const fields = validationErrors(error);
            if (fields) {
                errors.value = fields;
                await nextTick();
                form?.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
            } else {
                message.value = errorMessage(error);
            }

            return false;
        } finally {
            busy.value = false;
        }
    }

    /** First message for a field, ready for a `TextField`'s `error` prop. */
    function fieldError(field: string): string | undefined {
        return errors.value[field]?.[0];
    }

    return { busy, errors, message, submit, fieldError };
}
