import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'

import './styles/base.css'
import './styles/components.css'
import './styles/sidebar.css'
import './styles/dashboard.css'
import '@tabler/icons-webfont/dist/tabler-icons.min.css';

createApp(App).use(router).mount('#app')