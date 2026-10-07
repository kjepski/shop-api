<script setup lang="ts">
import FormField from './FormField.vue';
import { inputClass } from './fieldClasses';

const model = defineModel<string>({ required: true });

const {
    hint = '',
    error = '',
    rows = 3,
    required = false,
} = defineProps<{
    label: string;
    hint?: string;
    error?: string;
    rows?: number;
    required?: boolean;
}>();
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :hint="hint" :error="error">
        <textarea
            :id="id"
            v-model="model"
            :rows="rows"
            :required="required"
            :aria-invalid="invalid ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="inputClass(invalid)"
        ></textarea>
    </FormField>
</template>
