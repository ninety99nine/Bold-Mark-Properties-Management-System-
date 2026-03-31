import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCommunityStore = defineStore('community', () => {
  const selectedId = ref(null) // null = All Communities (portfolio view)

  // TODO: Load from API when community endpoints are available
  const communities = ref([
    { id: 1, name: 'Crystal Mews BC', location: 'Bramley View', units: 47, complianceStatus: 'green' },
    { id: 2, name: 'King Arthur BC', location: 'Florida', units: 32, complianceStatus: 'amber' },
    { id: 3, name: 'Lyndhurst Estate', location: 'Lyndhurst', units: 28, complianceStatus: 'red' },
  ])

  const selected = computed(() =>
    selectedId.value !== null
      ? communities.value.find(c => c.id === selectedId.value) ?? null
      : null
  )

  function select(id) {
    selectedId.value = id
  }

  function clearSelection() {
    selectedId.value = null
  }

  return { selectedId, communities, selected, select, clearSelection }
})
