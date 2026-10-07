<script setup lang="ts">
import { useId } from 'vue';

const model = defineModel<string>({ required: true });

const {
    type = 'text',
    error = '',
    autocomplete = undefined,
    required = false,
} = defineProps<{
    label: string;
    type?: 'text' | 'email' | 'password';
    /** Message shown under the field and linked to it for screen readers. */
    error?: string;
    autocomplete?: string;
    required?: boolean;
}>();

const id = useId();
const errorId = `${id}-error`;
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-gray-700">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? errorId : undefined"
            class="mt-1 block w-full rounded border px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            :class="error ? 'border-red-500' : 'border-gray-300'"
        />
        <p v-if="error" :id="errorId" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
