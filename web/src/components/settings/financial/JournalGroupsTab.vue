<!--
  JournalGroupsTab — WeConnectU "Financial Setup → Journal Groups" clone.

  Table of a community's journal groups + an inline "Add Journal Group" (name) modal.

  Endpoints (community-scoped, {c} = community.selectedId):
    GET    communities/{c}/journal-groups
    POST   communities/{c}/journal-groups          { name }
    DELETE communities/{c}/journal-groups/{id}
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppConfirm from '@/components/common/AppConfirm.vue'

const { success, error } = useToast()
const community = useCommunityStore()

const cid = computed(() => community.selectedId)

const groups = ref([])
const loading = ref(true)

async function load() {
  if (!cid.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${cid.value}/journal-groups`)
    groups.value = data.data ?? data ?? []
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(cid, load)

// ── Add modal ─────────────────────────────────────────────────────────────
const showModal = ref(false)
const newName = ref('')
const saving = ref(false)
const saveError = ref(null)

function openAdd() {
  newName.value = ''
  saveError.value = null
  showModal.value = true
}

async function save() {
  saveError.value = null
  if (!newName.value.trim()) { saveError.value = 'Please enter a name.'; return }
  saving.value = true
  try {
    await api.post(`/communities/${cid.value}/journal-groups`, { name: newName.value.trim() })
    success('Journal group added.')
    showModal.value = false
    await load()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to add journal group.'
  } finally {
    saving.value = false
  }
}

// ── Delete ─────────────────────────────────────────────────────────────────
const confirmOpen = ref(false)
const deleteTarget = ref(null)

function askDelete(g) {
  deleteTarget.value = g
  confirmOpen.value = true
}
async function confirmDelete() {
  const g = deleteTarget.value
  confirmOpen.value = false
  if (!g) return
  try {
    await api.delete(`/communities/${cid.value}/journal-groups/${g.id}`)
    success('Journal group deleted.')
    await load()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Could not delete journal group.')
  } finally {
    deleteTarget.value = null
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-end">
      <AppButton variant="secondary" @click="openAdd">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Journal Group
      </AppButton>
    </div>

    <div class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Journal Group</th>
              <th class="py-2.5 px-3 w-24 text-right font-bold text-navy-dark">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in groups" :key="g.id" class="border-b border-border/60 hover:bg-muted/40">
              <td class="py-2.5 px-3 text-foreground">{{ g.name }}</td>
              <td class="py-2.5 px-3">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" class="p-1.5 rounded text-destructive hover:bg-destructive/10" title="Delete" @click="askDelete(g)">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!groups.length">
              <td colspan="2" class="py-10 text-center text-sm text-muted-foreground">
                No journal groups yet. Click “Add Journal Group” to create one.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AppModal :show="showModal" size="md" @close="showModal = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">
          Add Journal Group
        </h3>
      </template>
      <div class="space-y-4">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>
        <AppInput v-model="newName" label="Journal Group Name" placeholder="e.g. Bank Charges" required />
      </div>
      <template #footer>
        <AppButton variant="outline" :disabled="saving" @click="showModal = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="saving" @click="save">Add Journal Group</AppButton>
      </template>
    </AppModal>

    <AppConfirm
      :show="confirmOpen"
      title="Delete Journal Group"
      :message="deleteTarget ? `Delete “${deleteTarget.name}”? This cannot be undone.` : ''"
      confirm-label="Delete"
      danger
      @confirm="confirmDelete"
      @cancel="confirmOpen = false; deleteTarget = null"
    />
  </div>
</template>
