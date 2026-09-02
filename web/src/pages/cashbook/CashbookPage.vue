<script setup>
/**
 * Cashbook — an exact-parity clone of WeConnectU's community Cashbook.
 *
 * Scoped to a selected bank account ("cashbook") + Financial Year / Budget
 * Period, it shows a running-balance transaction table (Opening Balance →
 * transactions → Closing Balance). The "Cashbook Options" menu offers Upload
 * Bank Statement, Manual Transactions, Run Rules, Edit Rules and Download Excel.
 *
 * First slice: the read view + Manual Transactions save; the CSV statement
 * parser and the Run/Edit Rules engine are faithful shells marked "coming soon".
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import { useToast } from '@/composables/useToast'
import { useExport } from '@/composables/useExport.js'
import AppButton              from '@/components/common/AppButton.vue'
import AppDatePicker          from '@/components/common/AppDatePicker.vue'
import AppDropdown            from '@/components/common/AppDropdown.vue'
import AppDropdownItem        from '@/components/common/AppDropdownItem.vue'
import CashbookAllocateModal  from '@/components/cashbook/CashbookAllocateModal.vue'
import CashbookSplitModal     from '@/components/cashbook/CashbookSplitModal.vue'

const router         = useRouter()
const communityStore = useCommunityStore()
const countryStore   = useCountryStore()
const { success, error: toastError } = useToast()
const { downloadExport } = useExport()

const communityId   = computed(() => communityStore.selectedId)
const inCommunity   = computed(() => communityId.value != null)

// ── Financial Year / Budget Period ────────────────────────────────────────
const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)

const pastPeriods    = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod  = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods  = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriod = computed(() => periods.value.find(p => p.year === selectedPeriodKey.value) ?? null)
const selectedPeriodLabel = computed(() => selectedPeriod.value?.label ?? 'Select period')

async function loadPeriods() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/financial-years`)
    periods.value = data.periods ?? []
    const current = periods.value.find(p => p.is_current)
    if (current) {
      selectedPeriodKey.value = current.year
      fromDate.value = current.start
      toDate.value   = current.end
    }
  } catch { /* selector stays empty */ }
}

function selectPeriod(p) {
  selectedPeriodKey.value = p.year
  fromDate.value = p.start
  toDate.value   = p.end
  fyOpen.value   = false
  fetchTransactions()
}

// ── Bank accounts (cashbooks), driven by /cashbook/status ─────────────────
// Each entry: { bank_account_id, name, gl_account, all_allocated, pending_count }
const bankAccounts   = ref([])
const totalPending   = ref(0)
const selectedBankId = ref('')
const bankSelectOpen = ref(false)

const selectedBank = computed(() => bankAccounts.value.find(b => b.bank_account_id === selectedBankId.value) ?? null)
const bankLabel = (b) => b ? `${b.name}${b.gl_account ? ` (${b.gl_account})` : ''}` : 'Nothing selected'

async function fetchStatus() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/status`)
    bankAccounts.value = data.bank_accounts ?? []
    totalPending.value = data.total_pending ?? 0
    if (bankAccounts.value.length && !selectedBankId.value) {
      selectedBankId.value = bankAccounts.value[0].bank_account_id
    }
  } catch { /* silent */ }
}

function selectBank(b) {
  selectedBankId.value = b.bank_account_id
  bankSelectOpen.value = false
  showUploadPanel.value = false
  fetchTransactions()
}

// ── Ledger options (shared with the allocate / split modals) ──────────────
const ledgerOptions = ref(null)
async function fetchLedgerOptions() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/ledger-options`)
    ledgerOptions.value = data
  } catch { /* silent */ }
}

// ── Transactions (running balance) ────────────────────────────────────────
const fromDate     = ref('')
const toDate       = ref('')
const search       = ref('')
const hideAllocated = ref(true)
const loading      = ref(false)

const openingBalance = ref(0)
const closingBalance = ref(0)
const lastUpload     = ref(null)
const allAllocated   = ref(true)
const transactions   = ref([])

async function fetchTransactions() {
  if (!communityId.value) return
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/transactions`, {
      params: {
        bank_account_id: selectedBankId.value || undefined,
        from:            fromDate.value || undefined,
        to:              toDate.value || undefined,
        search:          search.value.trim() || undefined,
        hide_allocated:  hideAllocated.value ? 1 : 0,
      },
    })
    openingBalance.value = data.opening_balance ?? 0
    closingBalance.value = data.closing_balance ?? 0
    lastUpload.value     = data.last_upload ?? null
    allAllocated.value   = data.all_allocated ?? true
    transactions.value   = data.transactions ?? []
  } catch { /* silent */ } finally {
    loading.value = false
  }
}

// ── Cashbook Options ──────────────────────────────────────────────────────
const showUploadPanel = ref(false)
const templateFormat  = ref('create')  // 'create' | 'manual'

function onUploadBankStatement() {
  // Matches WeConnectU: with no cashbook selected the menu item does nothing.
  if (!selectedBankId.value) return
  showUploadPanel.value = true
}

function onManualTransactions() {
  router.push({
    path: '/cashbook/manual',
    query: selectedBankId.value ? { bank_account_id: selectedBankId.value } : {},
  })
}

// ── Run / Edit Rules ──────────────────────────────────────────────────────
const runningRules = ref(false)
async function onRunRules() {
  if (!communityId.value || runningRules.value) return
  runningRules.value = true
  try {
    const { data } = await api.post(`/communities/${communityId.value}/cashbook/run-rules`, {
      bank_account_id: selectedBankId.value || undefined,
    })
    success(data.message ?? `Rules applied — ${data.allocated ?? 0} transaction(s) allocated.`)
    await Promise.all([fetchStatus(), fetchTransactions()])
  } catch (e) {
    toastError(e.response?.data?.message ?? 'Could not run allocation rules.')
  } finally {
    runningRules.value = false
  }
}

function onEditRules() {
  router.push('/cashbook/rules')
}

// ── Allocation actions (per-row) ──────────────────────────────────────────
const allocateEntry = ref(null)
const splitEntry    = ref(null)
const showAllocate  = ref(false)
const showSplit     = ref(false)

function openAllocate(entry) { allocateEntry.value = entry; showAllocate.value = true }
function openSplit(entry)    { splitEntry.value = entry; showSplit.value = true }

async function onAllocationSaved() {
  await Promise.all([fetchStatus(), fetchTransactions()])
}

async function deleteAllocation(entry) {
  if (!window.confirm('Remove this allocation?')) return
  try {
    const { data } = await api.delete(`/communities/${communityId.value}/cashbook/entries/${entry.id}/allocation`)
    success(data.message ?? 'Allocation removed.')
    await Promise.all([fetchStatus(), fetchTransactions()])
  } catch (e) {
    toastError(e.response?.data?.message ?? 'Could not remove the allocation.')
  }
}

// ── Rule-hint line under an unallocated description ────────────────────────
function ruleHintText(hint) {
  if (!hint) return ''
  const parts = []
  if (hint.starts_with) parts.push(`Starts with ${hint.starts_with}`)
  if (hint.contains)    parts.push(`contains ${hint.contains}`)
  return `${parts.join(', ')} → allocates to ${hint.account_label}`
}

function onSubmitUpload() {
  if (templateFormat.value === 'manual') {
    onManualTransactions()
    return
  }
  toastError('CSV bank-statement import is coming soon.')
}

function comingSoon(what) {
  toastError(`${what} is coming soon.`)
}

async function onDownloadExcel() {
  if (!communityId.value) return
  const filename = `cashbook-${new Date().toISOString().slice(0, 10)}.xlsx`
  await downloadExport('/cashbook/export', {
    community_id: communityId.value,
    _format: 'xlsx',
    _limit:  'all',
  }, filename)
  success('Cashbook export downloaded.')
}

// ── Init & reactivity ─────────────────────────────────────────────────────
onMounted(async () => {
  if (!communityId.value) return
  await Promise.all([loadPeriods(), fetchStatus(), fetchLedgerOptions()])
  fetchTransactions()
})

watch(communityId, async (id) => {
  if (!id) return
  selectedBankId.value = ''
  await Promise.all([loadPeriods(), fetchStatus(), fetchLedgerOptions()])
  fetchTransactions()
})

// ── Formatting ────────────────────────────────────────────────────────────
function fmtNum(v) {
  const num = Number(v || 0)
  const [i, d] = Math.abs(num).toFixed(2).split('.')
  const s = i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  return (num < 0 ? '-' : '') + s + '.' + d
}
function fmtSigned(row) {
  const n = Number(row.signed_amount ?? 0)
  return (n >= 0 ? '' : '-') + fmtNum(Math.abs(n))
}
function dmy(iso) {
  if (!iso) return ''
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}
</script>

<template>
  <div class="pb-10">

    <!-- No community selected (global context) -->
    <div v-if="!inCommunity" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-14 h-14 rounded-full bg-muted flex items-center justify-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 text-muted-foreground">
          <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
          <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
        </svg>
      </div>
      <h2 class="font-body font-bold text-lg text-foreground">Select a community</h2>
      <p class="text-sm text-muted-foreground mt-1 max-w-sm">The Cashbook is managed per community. Choose a community to view and capture its bank transactions.</p>
    </div>

    <template v-else>
      <!-- Page heading -->
      <h1 class="font-body font-normal text-3xl text-foreground mb-5">Cashbook</h1>

      <!-- Financial Year / Budget Period -->
      <div class="mb-1">
        <label class="block text-sm font-bold text-foreground mb-1.5">Financial Year / Budget Period:</label>
        <div class="relative max-w-md">
          <button
            type="button"
            class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground"
            @click="fyOpen = !fyOpen"
          >
            <span>{{ selectedPeriodLabel }}</span>
            <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div v-if="fyOpen" class="fixed inset-0 z-20" @click="fyOpen = false" />
          <div v-if="fyOpen" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
            <div v-if="pastPeriods.length" class="px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Past financial years</div>
            <button v-for="p in pastPeriods" :key="p.year" type="button"
                    class="block w-full px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                    :class="p.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                    @click="selectPeriod(p)">{{ p.label }}</button>
            <template v-if="currentPeriod">
              <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Current financial year end</div>
              <button type="button"
                      class="block w-full px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                      :class="currentPeriod.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                      @click="selectPeriod(currentPeriod)">{{ currentPeriod.label }}</button>
            </template>
            <template v-if="futurePeriods.length">
              <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Future financial years</div>
              <button v-for="p in futurePeriods" :key="p.year" type="button"
                      class="block w-full px-3 py-2 text-left text-sm hover:bg-muted"
                      :class="[p.year === selectedPeriodKey ? 'bg-muted font-medium' : '', p.is_setup ? 'text-foreground' : 'text-destructive']"
                      @click="selectPeriod(p)">{{ p.label }}</button>
            </template>
          </div>
        </div>
      </div>

      <!-- All-allocated / pending status line -->
      <p class="flex items-center gap-1.5 text-sm mt-2 mb-5" :class="totalPending ? 'text-amber-dark' : 'text-success'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
          <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/>
        </svg>
        {{ totalPending ? `You have ${totalPending} transaction${totalPending === 1 ? '' : 's'} pending allocation.` : 'All transactions allocated.' }}
      </p>

      <!-- Main card -->
      <div class="rounded-lg border border-border bg-card shadow-sm">

        <!-- Select cashbook row -->
        <div class="flex flex-wrap items-center gap-4 px-6 pt-6 pb-4">
          <label class="text-sm font-medium text-foreground shrink-0">Select Cashbook</label>

          <!-- Bank account dropdown (status-driven) -->
          <div class="relative w-full max-w-md">
            <button
              type="button"
              class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground"
              @click="bankSelectOpen = !bankSelectOpen"
            >
              <span :class="selectedBank ? 'text-foreground' : 'text-muted-foreground'">{{ bankLabel(selectedBank) }}</span>
              <span class="flex items-center gap-2">
                <!-- Selected cashbook status marker -->
                <svg v-if="selectedBank && selectedBank.all_allocated" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-success">
                  <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/>
                </svg>
                <span v-else-if="selectedBank" class="inline-flex items-center gap-1 text-xs font-medium text-amber-dark">
                  <span class="w-2 h-2 rounded-full bg-amber"></span>{{ selectedBank.pending_count }}
                </span>
                <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
              </span>
            </button>
            <div v-if="bankSelectOpen" class="fixed inset-0 z-20" @click="bankSelectOpen = false" />
            <div v-if="bankSelectOpen && bankAccounts.length" class="absolute z-30 mt-1 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
              <button v-for="b in bankAccounts" :key="b.bank_account_id" type="button"
                      class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                      :class="b.bank_account_id === selectedBankId ? 'bg-muted' : ''"
                      @click="selectBank(b)">
                <span>{{ bankLabel(b) }}</span>
                <svg v-if="b.all_allocated" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-success">
                  <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/>
                </svg>
                <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-amber-dark">
                  <span class="w-2 h-2 rounded-full bg-amber"></span>{{ b.pending_count }}
                </span>
              </button>
            </div>
          </div>

          <span class="text-sm text-muted-foreground">Last Upload: {{ lastUpload || '1970-01-01' }}</span>

          <!-- Cashbook Options -->
          <div class="ml-auto">
            <AppDropdown align="right" width="w-56">
              <template #trigger="{ toggle }">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-md bg-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-navy-dark transition-colors"
                  @click="toggle"
                >
                  Cashbook Options
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
              </template>
              <template #default="{ close }">
                <AppDropdownItem label="Upload Bank Statement" disabled />
                <AppDropdownItem label="Manual Transactions"   @click="close(); onManualTransactions()" />
                <AppDropdownItem label="Run Rules"             @click="close(); onRunRules()" />
                <AppDropdownItem label="Edit Rules"            @click="close(); onEditRules()" />
                <AppDropdownItem label="Download Excel"        disabled />
              </template>
            </AppDropdown>
          </div>
        </div>

        <!-- Upload Bank Statement panel -->
        <div v-if="showUploadPanel" class="px-6 pb-4">
          <p class="text-sm font-medium text-foreground mb-2">Upload New Bank Statement</p>
          <div class="rounded-md border border-amber/40 bg-amber/5 p-4 text-sm text-amber-dark mb-4">
            <p class="font-bold mb-1">Notes on Uploading:</p>
            <ul class="space-y-0.5">
              <li>- Only CSV (*.csv) format is permitted.</li>
              <li>- Please upload the file exactly AS IS.</li>
              <li>- Please select the bank account to which you are going to upload</li>
              <li>- Please select the template format that you want to upload using, alternatively create a new template from this upload.</li>
            </ul>
          </div>
          <div class="flex flex-wrap items-center gap-4">
            <label class="text-sm text-foreground w-32 shrink-0">Template Format</label>
            <select v-model="templateFormat" class="h-11 rounded-md border border-border bg-white px-3 text-sm text-foreground w-full max-w-md">
              <option value="create">Create New Template from this File</option>
              <option value="manual">Capture Transactions Manually</option>
            </select>
          </div>
          <div v-if="templateFormat === 'create'" class="flex flex-wrap items-center gap-4 mt-3">
            <label class="text-sm text-foreground w-32 shrink-0">Select File</label>
            <label class="inline-flex items-center gap-2 rounded-md border border-border bg-white px-4 py-2.5 text-sm text-foreground cursor-pointer hover:bg-muted">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Select Bank Statement File
              <input type="file" accept=".csv" class="hidden" />
            </label>
          </div>
          <div class="mt-4">
            <AppButton variant="primary" @click="onSubmitUpload">
              {{ templateFormat === 'manual' ? 'Submit' : 'Upload' }}
            </AppButton>
          </div>
        </div>

        <div class="border-t border-border" />

        <!-- Filters row -->
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
          <div class="flex items-center gap-2">
            <div class="w-40"><AppDatePicker v-model="fromDate" mode="date" /></div>
            <span class="text-sm text-muted-foreground">to</span>
            <div class="w-40"><AppDatePicker v-model="toDate" mode="date" /></div>
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-md bg-navy px-3 text-white hover:bg-navy-dark transition-colors"
              @click="fetchTransactions"
              aria-label="Apply date range"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
          </div>
          <div class="flex items-center gap-2">
            <input
              v-model="search"
              type="text"
              placeholder="Search..."
              class="h-11 rounded-md border border-border bg-muted/40 px-3 text-sm text-foreground placeholder:text-muted-foreground w-56"
              @keyup.enter="fetchTransactions"
            />
            <button
              type="button"
              class="inline-flex h-11 items-center gap-2 rounded-md bg-navy px-4 text-sm font-medium text-white hover:bg-navy-dark transition-colors"
              @click="fetchTransactions"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              Search
            </button>
          </div>
        </div>

        <!-- Hide allocated -->
        <div class="flex items-center justify-between px-6 pb-3">
          <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
            <input type="checkbox" v-model="hideAllocated" class="h-4 w-4 accent-primary" @change="fetchTransactions" />
            Hide Allocated Transactions
          </label>
          <span class="flex items-center gap-1.5 text-sm" :class="allAllocated ? 'text-success' : 'text-amber'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
              <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/>
            </svg>
            {{ allAllocated ? 'All transactions allocated' : 'Unallocated transactions present' }}
          </span>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Date</th>
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Transaction</th>
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Account</th>
                <th class="text-right py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Amount</th>
                <th class="text-right py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Balance</th>
                <th class="py-3 px-6 w-12"></th>
              </tr>
            </thead>
            <tbody>
              <!-- Loading -->
              <template v-if="loading">
                <tr v-for="n in 3" :key="'s' + n" class="border-b border-border last:border-0">
                  <td class="py-3 px-6"><div class="h-4 w-24 bg-muted rounded animate-pulse" /></td>
                  <td class="py-3 px-6"><div class="h-4 w-56 bg-muted rounded animate-pulse" /></td>
                  <td class="py-3 px-6"><div class="h-4 w-24 bg-muted rounded animate-pulse" /></td>
                  <td class="py-3 px-6"><div class="h-4 w-20 bg-muted rounded animate-pulse ml-auto" /></td>
                  <td class="py-3 px-6"><div class="h-4 w-24 bg-muted rounded animate-pulse ml-auto" /></td>
                  <td class="py-3 px-6"></td>
                </tr>
              </template>

              <template v-else>
                <!-- Opening balance -->
                <tr class="border-b border-border bg-muted/30">
                  <td class="py-3 px-6 text-foreground whitespace-nowrap">{{ fromDate }}</td>
                  <td class="py-3 px-6 text-foreground">Opening Balance on {{ dmy(fromDate) }}</td>
                  <td class="py-3 px-6"></td>
                  <td class="py-3 px-6"></td>
                  <td class="py-3 px-6 text-right text-foreground whitespace-nowrap">{{ fmtNum(openingBalance) }}</td>
                  <td class="py-3 px-6"></td>
                </tr>

                <!-- Transactions -->
                <tr v-for="t in transactions" :key="t.id" class="border-b border-border hover:bg-muted/40 transition-colors align-top">
                  <td class="py-3 px-6 text-foreground whitespace-nowrap">{{ t.date }}</td>
                  <td class="py-3 px-6 text-foreground">
                    <div>{{ t.description }}</div>
                    <!-- Rule hint (only when unallocated & a rule would match) -->
                    <div v-if="!t.is_allocated && t.rule_hint" class="mt-0.5 text-xs text-muted-foreground">
                      {{ ruleHintText(t.rule_hint) }}
                    </div>
                  </td>
                  <td class="py-3 px-6">
                    <template v-if="t.is_allocated">
                      <div class="text-foreground">{{ t.account_label }}</div>
                      <div v-if="t.allocation_remarks" class="text-xs text-muted-foreground mt-0.5">{{ t.allocation_remarks }}</div>
                    </template>
                    <button
                      v-else
                      type="button"
                      class="text-sm font-medium text-primary hover:underline"
                      @click="openAllocate(t)"
                    >
                      Allocate
                    </button>
                  </td>
                  <td class="py-3 px-6 text-right whitespace-nowrap" :class="t.type === 'credit' ? 'text-success' : 'text-destructive'">
                    {{ fmtSigned(t) }}
                  </td>
                  <td class="py-3 px-6 text-right text-foreground whitespace-nowrap">{{ fmtNum(t.balance) }}</td>
                  <td class="py-3 px-6 text-right">
                    <AppDropdown align="right">
                      <template #trigger="{ toggle }">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-muted" @click="toggle" aria-label="Transaction actions">
                          <svg class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        </button>
                      </template>
                      <template #default="{ close }">
                        <AppDropdownItem v-if="!t.is_allocated" label="Allocate" @click="close(); openAllocate(t)" />
                        <AppDropdownItem label="Split" @click="close(); openSplit(t)" />
                        <AppDropdownItem v-if="t.is_allocated" label="Delete allocation" variant="danger" @click="close(); deleteAllocation(t)" />
                      </template>
                    </AppDropdown>
                  </td>
                </tr>

                <!-- Closing balance -->
                <tr class="bg-muted/30">
                  <td class="py-3 px-6 text-foreground whitespace-nowrap">{{ toDate }}</td>
                  <td class="py-3 px-6 text-foreground">Closing Balance on {{ dmy(toDate) }}</td>
                  <td class="py-3 px-6"></td>
                  <td class="py-3 px-6"></td>
                  <td class="py-3 px-6 text-right text-foreground whitespace-nowrap">{{ fmtNum(closingBalance) }}</td>
                  <td class="py-3 px-6"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Allocation modals -->
    <CashbookAllocateModal
      :show="showAllocate"
      :entry="allocateEntry"
      :ledger-options="ledgerOptions"
      @close="showAllocate = false"
      @saved="onAllocationSaved"
    />
    <CashbookSplitModal
      :show="showSplit"
      :entry="splitEntry"
      :ledger-options="ledgerOptions"
      @close="showSplit = false"
      @saved="onAllocationSaved"
    />
  </div>
</template>
