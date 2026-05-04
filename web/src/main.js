import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useOrganizationStore } from '@/stores/organization'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Fetch tenant branding on boot (non-blocking — falls back to defaults on error)
useOrganizationStore().fetchBranding()

app.mount('#app')
