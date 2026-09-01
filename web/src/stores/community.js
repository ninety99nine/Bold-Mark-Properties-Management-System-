import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

const STORAGE_KEY = 'bm_selected_community'

export const useCommunityStore = defineStore('community', () => {
  // null = Global / All Communities (portfolio view); a community id = inside one community
  const selectedId = ref(localStorage.getItem(STORAGE_KEY) || null)

  const communities     = ref([])
  const activeCommunity = ref(null) // cached object for the selected community (deep-link / refresh)
  const loaded  = ref(false)
  const loading = ref(false)

  const selected = computed(() => {
    if (selectedId.value == null) return null
    if (activeCommunity.value && String(activeCommunity.value.id) === String(selectedId.value)) {
      return activeCommunity.value
    }
    return communities.value.find(c => String(c.id) === String(selectedId.value)) ?? null
  })

  function normalize(c) {
    return {
      id:         c.id,
      name:       c.name,
      code:       c.code ?? null,
      address:    c.address ?? '',
      unitsCount: c.units_count ?? c.unitsCount ?? 0,
      country:    c.country ?? null,
      type:       c.type ?? null,
    }
  }

  /** Load the portfolio's communities from the live API (Select Profile + sidebar switcher). */
  async function fetch(force = false) {
    if (loading.value || (loaded.value && !force)) return
    loading.value = true
    try {
      const { data } = await api.get('/communities', { params: { _per_page: 200, _sort: 'name:asc' } })
      communities.value = (data.data ?? data ?? []).map(normalize)
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  /** Enter a community context. Pass the community object when known so the name shows immediately. */
  function select(id, obj = null) {
    selectedId.value = id == null ? null : String(id)
    if (obj) activeCommunity.value = normalize(obj)
    else if (activeCommunity.value && String(activeCommunity.value.id) !== String(id)) activeCommunity.value = null
    if (id == null) localStorage.removeItem(STORAGE_KEY)
    else            localStorage.setItem(STORAGE_KEY, String(id))
  }

  function clearSelection() {
    selectedId.value      = null
    activeCommunity.value = null
    localStorage.removeItem(STORAGE_KEY)
  }

  return { selectedId, communities, activeCommunity, loaded, loading, selected, fetch, select, clearSelection }
})
