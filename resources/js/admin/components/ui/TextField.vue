<script setup lang="ts">
import FormField from './FormField.vue';
import { inputClass } from './fieldClasses';

const model = defineModel<string>({ required: true });

const {
    type = 'text',
    hint = '',
    error = '',
    autocomplete = undefined,
    inputmode = undefined,
    required = false,
    suffix = '',
} = defineProps<{
    label: string;
    /** No `number`: v-model would turn the value into a number. Use `inputmode` and parse in utils/. */
    type?: 'text' | 'email' | 'password' | 'search';
    hint?: string;
    /** Message shown under the field and linked to it for screen readers. */
    error?: string;
    autocomplete?: string;
    /** Keyboard on phones, e.g. `decimal` for prices typed as text. */
    inputmode?: 'text' | 'decimal' | 'numeric' | 'email' | 'search';
    required?: boolean;
    /** Unit shown after the input, e.g. "zł"; put it in the label too if it matters for meaning. */
    suffix?: string;
}>();
</script>

<template>
    <FormField v-slot="{ id, describedBy, invalid }" :label="label" :hint="hint" :error="error">
        <div class="flex items-center gap-2">
            <input
                :id="id"
                v-model="model"
                :type="type"
                :autocomplete="autocomplete"
                :inputmode="inputmode"
                :required="required"
                :aria-invalid="invalid ? 'true' : undefined"
                :aria-describedby="describedBy"
                :class="inputClass(invalid)"
            />
            <span v-if="suffix" class="shrink-0 text-sm text-gray-600">{{ suffix }}</span>
        </div>
    </FormField>
</template>
