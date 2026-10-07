import '../../css/app.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from './App.vue';
import { createAdminRouter } from './router';

// Pinia first: the router's session guard uses a store on the very first navigation.
createApp(App).use(createPinia()).use(createAdminRouter()).mount('#app');
