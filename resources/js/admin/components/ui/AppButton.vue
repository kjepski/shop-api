<script setup lang="ts">
const {
    type = 'button',
    variant = 'primary',
    busy = false,
    disabled = false,
} = defineProps<{
    type?: 'button' | 'submit';
    variant?: 'primary' | 'secondary';
    /** Disables the button while an action runs; the label is the caller's (e.g. "Logowanie…"). */
    busy?: boolean;
    disabled?: boolean;
}>();
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || busy"
        :aria-busy="busy || undefined"
        class="rounded px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        :class="
            variant === 'primary'
                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
        "
    >
        <slot />
    </button>
</template>
