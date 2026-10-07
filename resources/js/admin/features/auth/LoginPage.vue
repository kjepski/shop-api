<script setup lang="ts">
import { reactive, useTemplateRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AppButton from '@admin/components/ui/AppButton.vue';
import TextField from '@admin/components/ui/TextField.vue';
import { useFormSubmit } from '@admin/composables/useFormSubmit';
import { useSessionStore } from '@admin/stores/session';
import { afterLoginTarget } from '@admin/utils/redirect';

const session = useSessionStore();
const route = useRoute();
const router = useRouter();

const credentials = reactive({ email: '', password: '' });
const formElement = useTemplateRef<HTMLFormElement>('form');

// The navigation is part of the action, so the button stays disabled until the next screen shows
// (it loads lazily) and a second Enter cannot send another login attempt.
const { busy, message, submit, fieldError } = useFormSubmit(async () => {
    await session.login(credentials.email, credentials.password);
    // replace: "back" after logging in should not return to the login form.
    await router.replace(afterLoginTarget(route.query.redirect));
}, formElement);
</script>

<template>
    <main class="mx-auto max-w-sm px-4 py-16">
        <h1 tabindex="-1" class="text-2xl font-semibold focus:outline-none">Logowanie</h1>
        <form ref="form" class="mt-6 space-y-4 rounded bg-white p-6 shadow" @submit.prevent="submit">
            <p v-if="message" role="alert" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ message }}</p>
            <TextField
                v-model="credentials.email"
                label="E-mail"
                type="email"
                autocomplete="username"
                required
                :error="fieldError('email')"
            />
            <TextField
                v-model="credentials.password"
                label="Hasło"
                type="password"
                autocomplete="current-password"
                required
                :error="fieldError('password')"
            />
            <AppButton type="submit" :busy="busy" class="w-full">{{ busy ? 'Logowanie…' : 'Zaloguj' }}</AppButton>
        </form>
    </main>
</template>
