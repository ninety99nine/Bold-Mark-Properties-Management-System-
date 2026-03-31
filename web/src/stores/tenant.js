import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/composables/useApi'

export const useTenantStore = defineStore('tenant', () => {
  const name = ref('Property Management Platform')
  const logoUrl = ref('/assets/logo2-CB_yk5b_.png')
  const accentColor = ref('#D89B4B')
  const credentials = ref([])
  const copyrightName = ref('Property Management Platform')

  async function fetchBranding() {
    try {
      const { data } = await api.get('/branding')
      const b = data.data
      if (b.name) name.value = b.name
      if (b.logo_url) logoUrl.value = b.logo_url
      if (b.accent_color) accentColor.value = b.accent_color
      if (Array.isArray(b.credentials)) credentials.value = b.credentials
      if (b.copyright_name) copyrightName.value = b.copyright_name
    } catch {
      // Keep defaults — branding endpoint may not be configured yet
    }
  }

  return { name, logoUrl, accentColor, credentials, copyrightName, fetchBranding }
})
