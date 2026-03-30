import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useTenantStore } from '@/stores/tenant'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Fetch tenant branding on boot (non-blocking — falls back to defaults on error)
useTenantStore().fetchBranding()

app.mount('#app')
