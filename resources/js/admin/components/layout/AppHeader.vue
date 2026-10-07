<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import AppButton from '@admin/components/ui/AppButton.vue';
import { useFlashStore } from '@admin/stores/flash';
import { useSessionStore } from '@admin/stores/session';
import { errorMessage } from '@admin/utils/errors';

const session = useSessionStore();
const flash = useFlashStore();
const router = useRouter();

const loggingOut = ref(false);

async function logout(): Promise<void> {
    loggingOut.value = true;
    try {
        await session.logout();
        await router.replace({ name: 'login' });
    } catch (error) {
        flash.show('error', `Nie udało się wylogować. ${errorMessage(error)}`);
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <header class="bg-white shadow">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
            <RouterLink :to="{ name: 'home' }" class="font-semibold">Panel sklepu</RouterLink>
            <nav aria-label="Główna nawigacja" class="flex gap-1 text-sm">
                <RouterLink
                    :to="{ name: 'home' }"
                    class="rounded px-3 py-1.5 hover:bg-gray-100"
                    exact-active-class="bg-indigo-100 text-indigo-700"
                >
                    Start
                </RouterLink>
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <span>
                    {{ session.user?.name }}
                    <span v-if="session.isAdmin" class="text-gray-500">(administrator)</span>
                </span>
                <AppButton variant="secondary" :busy="loggingOut" @click="logout">
                    {{ loggingOut ? 'Wylogowywanie…' : 'Wyloguj' }}
                </AppButton>
            </div>
        </div>
    </header>
</template>
