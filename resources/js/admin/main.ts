import '../../css/app.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from './App.vue';
import { createAdminRouter } from './router';

createApp(App).use(createPinia()).use(createAdminRouter()).mount('#app');
