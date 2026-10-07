<script setup lang="ts">
import { useFieldIds } from '@admin/composables/useFieldIds';

const model = defineModel<boolean>({ required: true });

const {
    hint = '',
    error = '',
    disabled = false,
} = defineProps<{
    label: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
}>();

const { id, hintId, errorId, describedBy } = useFieldIds(
    () => hint,
    () => error,
);
</script>

<template>
    <div>
        <div class="flex items-center gap-2">
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="rounded border-gray-300 focus:ring-2 focus:ring-indigo-500"
            />
            <label :for="id" class="text-sm text-gray-700">{{ label }}</label>
        </div>
        <p v-if="hint" :id="hintId" class="mt-0.5 ml-6 text-xs text-gray-500">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="mt-1 ml-6 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
