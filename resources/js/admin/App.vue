<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';

import AppHeader from '@admin/components/layout/AppHeader.vue';
import FlashMessages from '@admin/components/layout/FlashMessages.vue';
import { useSessionStore } from '@admin/stores/session';

const route = useRoute();
const session = useSessionStore();

// The login page stands alone; every other screen gets the header once the user is known.
const showHeader = computed(() => session.status === 'authenticated' && !route.meta.public);
</script>

<template>
    <div class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        <AppHeader v-if="showHeader" />
        <FlashMessages />
        <RouterView />
    </div>
</template>
