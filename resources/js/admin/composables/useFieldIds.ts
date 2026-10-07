import { computed, useId } from 'vue';

/** Ids linking a form control to its hint and error, so screen readers read them with the field. */
export function useFieldIds(hint: () => string, error: () => string) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    const describedBy = computed(
        () =>
            [hint() ? hintId : null, error() ? errorId : null].filter((value) => value !== null).join(' ') || undefined,
    );

    return { id, hintId, errorId, describedBy };
}
