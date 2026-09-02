import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/composables/useApi'

/**
 * Organization / company store. Holds the company details captured on
 * Settings → Company (Company Details) so they can be reused everywhere in the
 * app and in communication assets — including the topbar brand logo.
 */
export const useOrganizationStore = defineStore("organization", () => {
  const name = ref('Property Management Platform')
  const logoUrl = ref('/assets/logo2-CB_yk5b_.png')
  const iconUrl = ref(null)
  const accentColor = ref('#D89B4B')
  const credentials = ref([])
  const copyrightName = ref('Property Management Platform')

  // Full company details (Company Details page + everywhere it's reused).
  const details = ref(null)
  const loaded = ref(false)

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

  /**
   * Load the authenticated organization's company details (requires auth).
   * Populates the topbar logo + company/bank fields used across the app.
   */
  async function fetchOrganization() {
    try {
      const { data } = await api.get('/organization')
      apply(data.data ?? data)
    } catch {
      // Not authenticated yet / endpoint unavailable — keep defaults.
    }
  }

  /** Apply a fresh organization payload to the store. */
  function apply(org) {
    if (!org) return
    details.value = org
    loaded.value = true
    if (org.company_name) name.value = org.company_name
    if (org.logo_url) logoUrl.value = org.logo_url
    iconUrl.value = org.icon_url ?? null
    if (org.secondary_color) accentColor.value = org.secondary_color
    if (org.copyright_name) copyrightName.value = org.copyright_name
  }

  return {
    name, logoUrl, iconUrl, accentColor, credentials, copyrightName,
    details, loaded, fetchBranding, fetchOrganization, apply,
  }
})
