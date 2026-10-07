<script setup lang="ts">
import FormField from './FormField.vue';
import { inputClass } from './fieldClasses';
import type { SelectOption } from './types';

/** The selected option's value; '' for the empty option. */
const model = defineModel<string>({ required: true });

const {
    hint = '',
    error = '',
    placeholder = '',
    required = false,
} = defineProps<{
    label: string;
    options: SelectOption[];
    hint?: string;
    error?: string;
    /** Label of an empty option (value ''), e.g. "Wszystkie kategorie"; no empty option when left out. */
    placeholder?: string;
    required?: boolean;
}>();
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :hint="hint" :error="error">
        <select
            :id="id"
            v-model="model"
            :required="required"
            :aria-invalid="invalid ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="inputClass(invalid)"
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </FormField>
</template>
