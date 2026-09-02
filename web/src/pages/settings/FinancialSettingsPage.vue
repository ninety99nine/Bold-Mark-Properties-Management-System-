<!--
  FinancialSettingsPage — WeConnectU "Settings → Finance → Setup → Financial" clone.
  BoldMark branded (navy/gold), community-scoped via the community store.

  Full 7-tab "Financial Setup" screen (WeConnectU parity):
    1. Cashbooks                 — BankAccount CRUD (/bank-accounts)
    2. General Ledger            — GL account CRUD grouped by main account (/ledgers)
    3. Budget                    — shared BudgetTab (also the /settings/finance/budget page)
    4. Reserve Fund Ledger       — GL CRUD with fund=reserve
    5. Reserve Fund Budget Setup — shared ReserveFundBudgetTab (report + setup sub-tabs)
    6. Journal Groups            — journal-group CRUD (communities/{c}/journal-groups)
    7. Allocation Rules          — embedded allocation-rules management

  A Financial Year / Budget Period selector + "all transactions allocated" indicator
  sits above the tabs (GET communities/{c}/financial-years).
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import AppBadge from '@/components/common/AppBadge.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppConfirm from '@/components/common/AppConfirm.vue'
import CashbookModal from '@/components/settings/financial/CashbookModal.vue'
import GeneralLedgerTab from '@/components/settings/financial/GeneralLedgerTab.vue'
import BudgetTab from '@/components/settings/financial/BudgetTab.vue'
import ReserveFundLedgerTab from '@/components/settings/financial/ReserveFundLedgerTab.vue'
import ReserveFundBudgetTab from '@/components/settings/financial/ReserveFundBudgetTab.vue'
import JournalGroupsTab from '@/components/settings/financial/JournalGroupsTab.vue'
import AllocationRulesTab from '@/components/settings/financial/AllocationRulesTab.vue'

const { success, error } = useToast()
const community = useCommunityStore()
const country = useCountryStore()

// ── Tabs ──────────────────────────────────────────────────────────────────
const TABS = [
  { key: 'cashbooks',      label: 'Cashbooks' },
  { key: 'general-ledger', label: 'General Ledger' },
  { key: 'budget',         label: 'Budget' },
  { key: 'reserve-ledger', label: 'Reserve Fund Ledger' },
  { key: 'reserve-budget', label: 'Reserve Fund Budget Setup' },
  { key: 'journal-groups', label: 'Journal Groups' },
  { key: 'allocation',     label: 'Allocation Rules' },
]
const activeTab = ref('cashbooks')

const cid = computed(() => community.selectedId)

// ── Financial Year / Budget Period ──────────────────────────────────────────
const periods = ref([])
const selectedYear = ref(null)
const allAllocated = ref(false)

const yearOptions = computed(() =>
  periods.value.map(p => ({ value: String(p.year), label: p.label ?? String(p.year) })),
)

async function loadYears() {
  if (!cid.value) return
  try {
    const { data } = await api.get(`/communities/${cid.value}/financial-years`)
    const payload = data.data ?? data ?? {}
    periods.value = payload.periods ?? []
    allAllocated.value = !!payload.all_transactions_allocated
    const current = periods.value.find(p => p.is_current)
    selectedYear.value = String(payload.current_year ?? current?.year ?? periods.value[0]?.year ?? '')
  } catch {
    periods.value = []
    allAllocated.value = false
  }
}

// ── Cashbooks state ─────────────────────────────────────────────────────────
const accounts = ref([])
const loading = ref(true)

async function loadAccounts() {
  const id = cid.value
  if (!id) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get('/bank-accounts', {
      params: { community_id: id, is_active: '' },
    })
    accounts.value = data.data ?? []
  } catch (e) {
    error('Failed to load cashbooks.')
  } finally {
    loading.value = false
  }
}

onMounted(() => { loadAccounts(); loadYears() })
watch(() => community.selectedId, () => { loadAccounts(); loadYears() })

function fmtMoney(v) {
  return country.formatCurrency(v ?? 0)
}

// ── Cashbook Add / Edit modal ────────────────────────────────────────────────
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
async function openEdit(acct) {
  modalMode.value = 'edit'
  saveError.value = null
  try {
    const { data } = await api.get(`/bank-accounts/${acct.id}`)
    editing.value = data.data ?? acct
  } catch (e) {
    editing.value = acct
  }
  showModal.value = true
}

async function saveAccount(payload) {
  saving.value = true
  saveError.value = null
  try {
    if (modalMode.value === 'add') {
      await api.post('/bank-accounts', { ...payload, community_id: cid.value })
      success('Cashbook added.')
    } else {
      await api.put(`/bank-accounts/${editing.value.id}`, payload)
      success('Cashbook updated.')
    }
    showModal.value = false
    await loadAccounts()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save cashbook.'
  } finally {
    saving.value = false
  }
}

// ── Cashbook delete ──────────────────────────────────────────────────────────
const confirmOpen = ref(false)
const deleteTarget = ref(null)

function askDelete(acct) {
  deleteTarget.value = acct
  confirmOpen.value = true
}
async function confirmDelete() {
  const acct = deleteTarget.value
  confirmOpen.value = false
  if (!acct) return
  try {
    await api.delete(`/bank-accounts/${acct.id}`)
    success('Cashbook deleted.')
    await loadAccounts()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Could not delete cashbook.')
  } finally {
    deleteTarget.value = null
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- Header / breadcrumb -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">
          Finance <span class="mx-1">→</span> Setup <span class="mx-1">→</span>
          <span class="text-foreground font-medium">Financial</span>
        </p>
        <h1 class="font-body font-bold text-2xl text-foreground mt-1">Financial Setup</h1>
        <p class="text-sm text-muted-foreground">
          Configure cashbooks, ledgers and budgets
          <template v-if="community.selected"> · {{ community.selected.name }}</template>
        </p>
      </div>
      <button
        v-if="cid && activeTab === 'cashbooks'"
        type="button"
        class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity shrink-0"
        @click="openAdd"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Cashbook
      </button>
    </div>

    <!-- No community -->
    <div v-if="!cid" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to manage its financial setup.
    </div>

    <template v-else>
      <!-- Financial Year / Budget Period selector + allocation indicator -->
      <div class="flex flex-wrap items-center gap-4">
        <div class="w-64">
          <AppSelect
            v-model="selectedYear"
            :options="yearOptions"
            label="Financial Year / Budget Period"
            placeholder="Select period…"
          />
        </div>
        <span
          v-if="allAllocated"
          class="inline-flex items-center gap-1.5 self-end h-11 rounded-full bg-success/10 px-3 text-xs font-medium text-success"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          All transactions allocated
        </span>
      </div>

      <!-- Tabs -->
      <div class="border-b border-border">
        <nav class="-mb-px flex flex-wrap gap-x-6 gap-y-1">
          <button
            v-for="t in TABS"
            :key="t.key"
            type="button"
            class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium transition-colors"
            :class="activeTab === t.key
              ? 'border-accent text-navy-dark'
              : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
            @click="activeTab = t.key"
          >
            {{ t.label }}
          </button>
        </nav>
      </div>

      <!-- ── Cashbooks tab ─────────────────────────────────────────────── -->
      <div v-if="activeTab === 'cashbooks'" class="rounded-lg border border-border bg-white p-6">
        <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Cashbook Name</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Bank</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Account Number</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">GL Account</th>
                <th class="py-2.5 px-3 text-right font-bold text-navy-dark">Opening Balance</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Status</th>
                <th class="py-2.5 px-3 w-24 text-right font-bold text-navy-dark">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in accounts" :key="a.id" class="border-b border-border/60 odd:bg-muted/20 hover:bg-muted/40">
                <td class="py-2.5 px-3">
                  <button type="button" class="text-[#2f6fb0] hover:underline text-left" title="Edit cashbook" @click="openEdit(a)">
                    {{ a.name }}
                  </button>
                  <span class="block text-xs text-muted-foreground capitalize">{{ a.type }}</span>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ a.bank_name || '—' }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ a.account_number || '—' }}</td>
                <td class="py-2.5 px-3 font-mono text-muted-foreground">{{ a.gl_account || '—' }}</td>
                <td class="py-2.5 px-3 text-right tabular-nums">{{ fmtMoney(a.balance) }}</td>
                <td class="py-2.5 px-3">
                  <AppBadge :variant="a.is_active ? 'success' : 'default'">
                    {{ a.is_active ? 'Active' : 'Inactive' }}
                  </AppBadge>
                </td>
                <td class="py-2.5 px-3">
                  <div class="flex items-center justify-end gap-1">
                    <button type="button" class="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted" title="Edit" @click="openEdit(a)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button type="button" class="p-1.5 rounded text-destructive hover:bg-destructive/10" title="Delete" @click="askDelete(a)">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!accounts.length">
                <td colspan="7" class="py-10 text-center text-sm text-muted-foreground">
                  No cashbooks yet. Click “Add Cashbook” to create one.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── General Ledger tab ────────────────────────────────────────── -->
      <GeneralLedgerTab v-else-if="activeTab === 'general-ledger'" fund="main" />

      <!-- ── Budget tab (shared component) ─────────────────────────────── -->
      <BudgetTab v-else-if="activeTab === 'budget'" :year="selectedYear" fund="main" />

      <!-- ── Reserve Fund Ledger tab ───────────────────────────────────── -->
      <ReserveFundLedgerTab v-else-if="activeTab === 'reserve-ledger'" />

      <!-- ── Reserve Fund Budget Setup tab (defaults to report sub-tab) ── -->
      <ReserveFundBudgetTab v-else-if="activeTab === 'reserve-budget'" :year="selectedYear" default-sub-tab="report" />

      <!-- ── Journal Groups tab ────────────────────────────────────────── -->
      <JournalGroupsTab v-else-if="activeTab === 'journal-groups'" />

      <!-- ── Allocation Rules tab ──────────────────────────────────────── -->
      <AllocationRulesTab v-else-if="activeTab === 'allocation'" />
    </template>

    <!-- ── Cashbook Add / Edit modal ──────────────────────────────────────── -->
    <CashbookModal
      :show="showModal"
      :mode="modalMode"
      :account="editing"
      :saving="saving"
      :error="saveError"
      @close="showModal = false"
      @save="saveAccount"
    />

    <!-- ── Cashbook delete confirm ────────────────────────────────────────── -->
    <AppConfirm
      :show="confirmOpen"
      title="Delete Cashbook"
      :message="deleteTarget ? `Delete “${deleteTarget.name}”? This cannot be undone.` : ''"
      confirm-label="Delete"
      danger
      @confirm="confirmDelete"
      @cancel="confirmOpen = false; deleteTarget = null"
    />
  </div>
</template>
