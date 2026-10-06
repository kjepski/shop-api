<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <main class="mx-auto max-w-3xl px-4 py-16">
        <h1 tabindex="-1" class="text-2xl font-semibold focus:outline-none">Nie znaleziono</h1>
        <p class="mt-2 text-gray-600">Ta strona nie istnieje.</p>
        <RouterLink :to="{ name: 'home' }" class="mt-4 inline-block text-indigo-600 hover:underline">
            Wróć na stronę główną
        </RouterLink>
    </main>
</template>
