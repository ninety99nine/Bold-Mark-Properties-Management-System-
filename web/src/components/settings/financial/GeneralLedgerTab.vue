<!--
  GeneralLedgerTab — WeConnectU "Financial Setup → General Ledger" clone.

  Table of GL accounts (GL Account · Financial Category · Account Type · Tax Type),
  grouped by main account, plus an "Add General Ledger Account" flow (LedgerModal).

  Reused for the Reserve Fund Ledger by passing `fund="reserve"`, which filters
  the list via GET /ledgers?fund=reserve and posts new accounts with fund=reserve.

  Endpoints:
    GET    /ledgers            (params: fund)
    GET    /ledgers/options    (params: fund)
    POST   /ledgers
    PUT    /ledgers/{id}
    DELETE /ledgers/{id}
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppConfirm from '@/components/common/AppConfirm.vue'
import LedgerModal from '@/components/settings/financial/LedgerModal.vue'

const props = defineProps({
  fund: { type: String, default: 'main' }, // 'main' | 'reserve'
})

const { success, error } = useToast()

const ledgers = ref([])
const options = ref(null)
const loading = ref(true)

const addLabel = computed(() =>
  props.fund === 'reserve' ? 'Add Reserve Fund Ledger Account' : 'Add General Ledger Account',
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ledgers', { params: { fund: props.fund } })
    ledgers.value = data.data ?? data ?? []
  } catch {
    ledgers.value = []
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/ledgers/options', { params: { fund: props.fund } })
    options.value = data.data ?? data ?? null
  } catch {
    options.value = null
  }
}

onMounted(() => { load(); loadOptions() })
watch(() => props.fund, () => { load(); loadOptions() })

// Group accounts by their main account for the grouped table view.
const groups = computed(() => {
  const byId = new Map()
  const mains = []
  for (const l of ledgers.value) {
    if (!l.main_account_id) {
      const g = { main: l, children: [] }
      byId.set(l.id, g)
      mains.push(g)
    }
  }
  const orphans = []
  for (const l of ledgers.value) {
    if (l.main_account_id) {
      const g = byId.get(l.main_account_id)
      if (g) g.children.push(l)
      else orphans.push(l)
    }
  }
  if (orphans.length) mains.push({ main: null, children: orphans })
  return mains
})

// ── Add / Edit modal ───────────────────────────────────────────────────────
const showModal = ref(false)
const modalMode = ref('add')
const editing = ref(null)
const saving = ref(false)
const saveError = ref(null)

function openAdd() {
  modalMode.value = 'add'
  editing.value = null
  saveError.value = null
  showModal.value = true
}
function openEdit(l) {
  modalMode.value = 'edit'
  editing.value = l
  saveError.value = null
  showModal.value = true
}

async function saveLedger(payload) {
  saving.value = true
  saveError.value = null
  try {
    if (modalMode.value === 'add') {
      await api.post('/ledgers', payload)
      success('Ledger account added.')
    } else {
      await api.put(`/ledgers/${editing.value.id}`, payload)
      success('Ledger account updated.')
    }
    showModal.value = false
    await load()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save ledger account.'
  } finally {
    saving.value = false
  }
}

// ── Delete ──────────────────────────────────────────────────────────────────
const confirmOpen = ref(false)
const deleteTarget = ref(null)

function askDelete(l) {
  deleteTarget.value = l
  confirmOpen.value = true
}
async function confirmDelete() {
  const l = deleteTarget.value
  confirmOpen.value = false
  if (!l) return
  try {
    await api.delete(`/ledgers/${l.id}`)
    success('Ledger account deleted.')
    await load()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Could not delete ledger account.')
  } finally {
    deleteTarget.value = null
  }
}

function catLabel(l) {
  const opt = (options.value?.financial_categories ?? []).find(o => o.value === l.category)
  return opt?.label ?? l.category ?? '—'
}
function typeLabel(l) {
  const opt = (options.value?.account_types ?? []).find(o => o.value === l.type)
  return opt?.label ?? l.type ?? '—'
}
function taxLabel(l) {
  const opt = (options.value?.tax_types ?? []).find(o => o.value === l.tax_type)
  return opt?.label ?? l.tax_type ?? '—'
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-end">
      <AppButton variant="secondary" @click="openAdd">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        {{ addLabel }}
      </AppButton>
    </div>

    <div class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">GL Account</th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Financial Category</th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Account Type</th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Tax Type</th>
              <th class="py-2.5 px-3 w-24 text-right font-bold text-navy-dark">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(g, gi) in groups" :key="g.main?.id ?? ('orphan-' + gi)">
              <!-- Main account row -->
              <tr v-if="g.main" class="border-b border-border/60 bg-muted/30">
                <td class="py-2.5 px-3">
                  <span class="font-mono text-xs text-muted-foreground">{{ g.main.code }}</span>
                  <span class="ml-2 font-semibold text-navy-dark">{{ g.main.name }}</span>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ catLabel(g.main) }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ typeLabel(g.main) }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ taxLabel(g.main) }}</td>
                <td class="py-2.5 px-3">
                  <div class="flex items-center justify-end gap-1">
                    <button type="button" class="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted" title="Edit" @click="openEdit(g.main)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button v-if="!g.main.is_system" type="button" class="p-1.5 rounded text-destructive hover:bg-destructive/10" title="Delete" @click="askDelete(g.main)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <!-- Sub-account rows -->
              <tr v-for="c in g.children" :key="c.id" class="border-b border-border/60 hover:bg-muted/40">
                <td class="py-2.5 px-3 pl-8">
                  <span class="font-mono text-xs text-muted-foreground">{{ c.code }}</span>
                  <span class="ml-2 text-foreground">{{ c.name }}</span>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ catLabel(c) }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ typeLabel(c) }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ taxLabel(c) }}</td>
                <td class="py-2.5 px-3">
                  <div class="flex items-center justify-end gap-1">
                    <button type="button" class="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted" title="Edit" @click="openEdit(c)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button v-if="!c.is_system" type="button" class="p-1.5 rounded text-destructive hover:bg-destructive/10" title="Delete" @click="askDelete(c)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!groups.length">
              <td colspan="5" class="py-10 text-center text-sm text-muted-foreground">
                No ledger accounts yet. Click “{{ addLabel }}” to create one.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <LedgerModal
      :show="showModal"
      :mode="modalMode"
      :ledger="editing"
      :options="options"
      :fund="fund"
      :saving="saving"
      :error="saveError"
      @close="showModal = false"
      @save="saveLedger"
    />

    <AppConfirm
      :show="confirmOpen"
      title="Delete Ledger Account"
      :message="deleteTarget ? `Delete “${deleteTarget.name}”? This cannot be undone.` : ''"
      confirm-label="Delete"
      danger
      @confirm="confirmDelete"
      @cancel="confirmOpen = false; deleteTarget = null"
    />
  </div>
</template>
