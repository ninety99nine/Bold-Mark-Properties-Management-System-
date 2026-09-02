<!--
  Customer Age Analysis — strict WeConnectU clone (Bold Mark branding).

  Per-community ageing grid with the WeConnectU controls: Financial Year / Budget
  Period selector, Ageing Date, Hide Zero / Hide Negative / Exclude Debit-Arrear
  toggles, Status Management + Run Automatic Notices + View last notice batch,
  Filter Type / Debt Status / Customer Group / Debit Order filters, a green
  Download Excel button, and the aged buckets (120+/90/60/30/Current) + Balance
  with the collection-status icons and a totals row.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton           from '@/components/common/AppButton.vue'
import AppSelect           from '@/components/common/AppSelect.vue'
import AgeStatusIcon       from '@/components/age-analysis/AgeStatusIcon.vue'
import LastNoticeBatchModal from '@/components/age-analysis/LastNoticeBatchModal.vue'
import AppModal            from '@/components/common/AppModal.vue'
import CustomerNotesModal   from '@/components/customer-management/CustomerNotesModal.vue'
import api                 from '@/composables/useApi'
import { useToast }        from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

const communityId   = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')

// ── Data ────────────────────────────────────────────────────────────────
const loading = ref(false)
const error   = ref(null)
const rows    = ref([])
const totals  = ref({})

// ── Controls ────────────────────────────────────────────────────────────
// WeConnectU defaults the ageing date to the LAST day of the current month.
function endOfCurrentMonth() {
  const d    = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  const mm   = String(last.getMonth() + 1).padStart(2, '0')
  const dd   = String(last.getDate()).padStart(2, '0')
  return `${last.getFullYear()}-${mm}-${dd}`
}
const ageingDate = ref(endOfCurrentMonth())

const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)

const hideZero           = ref(false)
const hideNegative       = ref(false)
const excludeDebitArrear = ref(false)

// ── Toolbar filters ──────────────────────────────────────────────────────
const search          = ref('')
const filterType      = ref('no_status')
const debtStatus      = ref('')
const customerGroupId = ref('')
const debitOrder      = ref(false)
const groups          = ref([])

// First filter — the collection-status dropdown (WeConnectU: exactly these three,
// mutually exclusive, defaulting to "No Status"). Red flag = handed over, orange
// flag = payment arrangement, no flag = no status.
const FILTER_TYPE_OPTIONS = [
  { value: 'no_status',           label: 'No Status' },
  { value: 'handed_over',         label: 'Handed Over' },
  { value: 'payment_arrangement', label: 'Payment Arrangement' },
]

const DEBT_STATUS_OPTIONS = [
  { value: '',                    label: 'All Statuses' },
  { value: 'none',                label: 'No Status' },
  { value: 'first_notice',        label: '1st Notice' },
  { value: 'second_notice',       label: '2nd Notice' },
  { value: 'final_notice',        label: 'Letter of demand' },
  { value: 'letter_of_demand',    label: 'Letter of Demand sent' },
  { value: 'payment_arrangement', label: 'Payment Arrangement' },
  { value: 'handed_over',         label: 'Handed over to Attorneys' },
]

const customerGroupOptions = computed(() => [
  { value: '', label: 'All Groups' },
  ...groups.value.map(g => ({ value: g.id, label: g.name })),
])

// ── Modals ────────────────────────────────────────────────────────────────
const lastBatchOpen  = ref(false)
const lastBatch      = ref(null)
const notesOpen      = ref(false)
const notesRow       = ref(null)

// ── FY selector groupings ──────────────────────────────────────────────────
const pastPeriods    = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod  = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods  = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.year === selectedPeriodKey.value)?.label ?? 'Select period',
)

// ── Formatting (WeConnectU: plain 2dp, space thousands, no symbol) ──────────
function fmt(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

// ── Requests ────────────────────────────────────────────────────────────────
function buildParams() {
  const p = { ageing_date: ageingDate.value }
  if (filterType.value)      p.filter_type          = filterType.value
  if (debtStatus.value)      p.debt_status          = debtStatus.value
  if (customerGroupId.value) p.customer_group_id    = customerGroupId.value
  if (debitOrder.value)      p.debit_order          = true
  if (hideZero.value)        p.hide_zero            = true
  if (hideNegative.value)    p.hide_negative        = true
  if (excludeDebitArrear.value) p.exclude_debit_arrear = true
  if (search.value.trim())   p._search              = search.value.trim()
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/age-analysis`, { params: buildParams() })
    rows.value   = data.rows ?? []
    totals.value = data.totals ?? {}
  } catch (e) {
    error.value = 'Failed to load age analysis. Please try again.'
  } finally {
    loading.value = false
  }
}

async function loadPeriods() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/financial-years`)
    periods.value = data.periods ?? []
    const current = periods.value.find(p => p.is_current)
    if (current) selectedPeriodKey.value = current.year
  } catch { /* selector just stays empty */ }
}

async function loadGroups() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-groups`, { params: { _per_page: 200 } })
    groups.value = data.data ?? []
  } catch { /* filter just won't populate */ }
}

function selectPeriod(p) {
  selectedPeriodKey.value = p.year
  ageingDate.value        = p.end
  fyOpen.value            = false
  fetchData()
}

async function downloadExcel() {
  if (!communityId.value) return
  try {
    const res = await api.get(`/communities/${communityId.value}/age-analysis/export`, {
      params: buildParams(),
      responseType: 'blob',
    })
    let filename = `customer age analysis-${(communityName.value || 'community').toLowerCase()}-${ageingDate.value}.xlsx`
    const cd = res.headers['content-disposition'] || ''
    const m  = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e) {
    toastError('Could not download the Excel file.')
  }
}

function openLastBatch() {
  router.push({ name: 'customer-notices' })
}

function goToStatusManagement() {
  router.push({ name: 'customer-status' })
}

function goToRunNotices() {
  router.push({ name: 'legal-notices', query: buildParams() })
}

function goToCustomer(row) {
  if (row.person_role === 'owner' && row.person_id) {
    router.push({ name: 'customer-detail', params: { ownerId: row.person_id } })
  }
}

function openNotes(row) {
  notesRow.value  = row
  notesOpen.value = true
}

// ── Init / reactivity ──────────────────────────────────────────────────────
function boot() {
  if (!communityId.value) return
  loadPeriods()
  loadGroups()
  fetchData()
}

onMounted(() => {
  if (!communityStore.loaded) communityStore.fetch()
  boot()
})

watch(communityId, () => {
  rows.value = []
  totals.value = {}
  boot()
})
</script>

<template>
  <div class="p-6">
    <!-- No community selected -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view its age analysis.
      <div class="mt-4">
        <AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton>
      </div>
    </div>

    <template v-else>
      <!-- ══════════ Header band ══════════ -->
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Customer Age Analysis</h1>

        <!-- Financial Year / Budget Period -->
        <div class="mt-4">
          <label class="mb-1 block text-xs font-bold text-muted-foreground">Financial Year / Budget Period:</label>
          <div class="relative w-full max-w-md">
            <button
              type="button"
              class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none"
              @click="fyOpen = !fyOpen"
            >
              <span>{{ selectedPeriodLabel }}</span>
              <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            <div v-if="fyOpen" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
              <div v-if="pastPeriods.length" class="px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Past financial years</div>
              <button v-for="p in pastPeriods" :key="p.year" type="button"
                      class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                      :class="p.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                      @click="selectPeriod(p)">{{ p.label }}</button>

              <template v-if="currentPeriod">
                <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Current financial year end</div>
                <button type="button"
                        class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                        :class="currentPeriod.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                        @click="selectPeriod(currentPeriod)">{{ currentPeriod.label }}</button>
              </template>

              <template v-if="futurePeriods.length">
                <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Future financial years</div>
                <button v-for="p in futurePeriods" :key="p.year" type="button"
                        class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                        :class="[p.year === selectedPeriodKey ? 'bg-muted font-medium' : '', p.is_setup ? '' : 'text-destructive']"
                        @click="selectPeriod(p)">{{ p.label }}</button>
              </template>
            </div>
          </div>
        </div>

        <!-- Ageing date + toggles + actions -->
        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Ageing Date:</label>
            <input v-model="ageingDate" type="date"
                   class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none"
                   @change="fetchData" />
          </div>

          <div class="flex flex-wrap items-center gap-4 pb-2.5 text-sm">
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Hide Zero Values
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="hideNegative" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Hide Negative Values
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="excludeDebitArrear" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Exclude Debit/Arrear charges
            </label>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <AppButton variant="secondary" @click="goToStatusManagement">Status Management</AppButton>
          <AppButton variant="primary" @click="goToRunNotices">Run Automatic Notices</AppButton>
          <button type="button" class="text-sm font-medium text-[#2f6fb0] hover:underline" @click="openLastBatch">
            View last notice batch
          </button>
        </div>
      </div>

      <!-- ══════════ Table card ══════════ -->
      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <!-- Toolbar -->
        <div class="mb-4 flex items-center gap-2">
          <input
            v-model="search"
            type="text"
            placeholder="Search..."
            class="h-9 w-40 shrink-0 rounded-md border border-border px-3 text-sm focus:border-navy focus:outline-none"
            @keyup.enter="fetchData"
          />
          <div class="min-w-0 flex-1"><AppSelect v-model="filterType" :options="FILTER_TYPE_OPTIONS" placeholder="Filter Type" size="sm" @change="fetchData" /></div>
          <div class="min-w-0 flex-1"><AppSelect v-model="debtStatus" :options="DEBT_STATUS_OPTIONS" placeholder="Filter Debt Status" size="sm" @change="fetchData" /></div>
          <div class="min-w-0 flex-1"><AppSelect v-model="customerGroupId" :options="customerGroupOptions" placeholder="Filter Customer Group" size="sm" @change="fetchData" /></div>
          <label class="flex shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap text-xs select-none">
            <input v-model="debitOrder" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
            Debit Order Customers
          </label>

          <button
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md bg-[#2c9b67] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#268a5b]"
            @click="downloadExcel"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" />
            </svg>
            Download Excel
          </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Unit No</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Customer</th>
                <th class="border border-border px-1 py-2.5 w-8"></th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">120+ days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">90 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">60 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">30 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Current</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Balance</th>
              </tr>
            </thead>
            <tbody>
              <!-- Loading -->
              <tr v-if="loading">
                <td colspan="9" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td>
              </tr>

              <!-- Empty -->
              <tr v-else-if="rows.length === 0">
                <td colspan="9" class="border border-border px-3 py-10 text-center text-muted-foreground">
                  No customers match the current filters.
                </td>
              </tr>

              <!-- Rows -->
              <template v-else>
                <tr v-for="row in rows" :key="row.unit_id" class="hover:bg-muted/30">
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ row.unit_no }}</td>
                  <td class="border border-border px-3 py-2">
                    <div class="flex items-center gap-2">
                      <AgeStatusIcon
                        :status="row.collection_status"
                        :status-changed-by="row.status_changed_by"
                        :transfer-active="row.transfer_active"
                        :debit-order="row.debit_order"
                      />
                      <button type="button" class="text-left font-medium text-[#2f6fb0] hover:underline" @click="goToCustomer(row)">
                        {{ row.customer_code }}<template v-if="row.customer_code">: </template>{{ row.customer_name }}
                      </button>
                    </div>
                  </td>
                  <td class="border border-border px-1 py-2 text-center">
                    <button type="button" class="relative" :class="row.notes_count > 0 ? 'text-[#2f6fb0] hover:text-navy' : 'text-muted-foreground/40 hover:text-navy'" title="Collection notes" @click="openNotes(row)">
                      <svg class="mx-auto h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v6h6" />
                      </svg>
                      <span v-if="row.notes_count" class="absolute -right-1 -top-1 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-accent px-0.5 text-[9px] font-bold leading-none text-white">{{ row.notes_count }}</span>
                    </button>
                  </td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['120_plus']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['90_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['60_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['30_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row.current) }}</td>
                  <td class="border border-border px-3 py-2 text-right font-semibold tabular-nums text-navy-dark">{{ fmt(row.balance) }}</td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="!loading && rows.length">
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5"></td>
                <td class="border border-border px-3 py-2.5">Totals</td>
                <td class="border border-border px-1 py-2.5"></td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['120_plus']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['90_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['60_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['30_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals.current) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals.balance) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>

      <!-- ══════════ Modals ══════════ -->
      <LastNoticeBatchModal
        :show="lastBatchOpen"
        :community-id="communityId"
        :batch="lastBatch"
        @close="lastBatchOpen = false"
      />

      <CustomerNotesModal :show="notesOpen" :community-id="communityId" :unit="notesRow" @close="notesOpen = false" @changed="fetchData" />
    </template>
  </div>
</template>
