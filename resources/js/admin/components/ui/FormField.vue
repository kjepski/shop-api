<script setup lang="ts">
import { useFieldIds } from '@admin/composables/useFieldIds';

const { hint = '', error = '' } = defineProps<{
    label: string;
    /** Help shown under the label, e.g. the expected format. */
    hint?: string;
    error?: string;
}>();

defineSlots<{
    default(props: { id: string; describedBy: string | undefined; invalid: boolean }): unknown;
}>();

const { id, hintId, errorId, describedBy } = useFieldIds(
    () => hint,
    () => error,
);
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-gray-700">{{ label }}</label>
        <p v-if="hint" :id="hintId" class="mt-0.5 text-xs text-gray-500">{{ hint }}</p>
        <div class="mt-1">
            <slot v-bind="{ id, describedBy, invalid: error !== '' }" />
        </div>
        <p v-if="error" :id="errorId" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
