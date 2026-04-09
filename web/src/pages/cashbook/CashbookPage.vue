<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import api from '@/composables/useApi'
import { useExport } from '@/composables/useExport.js'
import AppStatCard      from '@/components/common/AppStatCard.vue'
import AppButton        from '@/components/common/AppButton.vue'
import AppBadge         from '@/components/common/AppBadge.vue'
import AppModal         from '@/components/common/AppModal.vue'
import AppInput         from '@/components/common/AppInput.vue'
import AppSelect        from '@/components/common/AppSelect.vue'
import AppTooltip       from '@/components/common/AppTooltip.vue'
import AppTableToolbar  from '@/components/common/AppTableToolbar.vue'
import AppExportModal   from '@/components/common/AppExportModal.vue'
import AppDatePicker    from '@/components/common/AppDatePicker.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend)

const MUTED  = '#717B99'
const BORDER = '#DCDEE8'

const router = useRouter()
const route  = useRoute()

// ── Loading ───────────────────────────────────────────────────────────────
const summaryLoading = ref(true)
const entriesLoading = ref(true)

// ── Export ────────────────────────────────────────────────────────────────
const showExport = ref(false)
const { downloadExport } = useExport()

async function handleExportDownload({ format, records }) {
  showExport.value = false
  const ext      = format === 'xlsx' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
  const filename = `cashbook-${new Date().toISOString().slice(0, 10)}.${ext}`

  const state  = toolbarState.value
  const params = {}
  if (state.filters?.allocation_status) params.allocation_status = state.filters.allocation_status
  if (state.filters?.type)              params.type              = state.filters.type
  if (state.search?.trim())             params._search           = state.search.trim()
  if (state.dateRange && state.dateRange !== 'all_time') {
    params._date_range = state.dateRange
    if (state.dateRange === 'custom') {
      if (state.customStart) params._date_range_start = state.customStart
      if (state.customEnd)   params._date_range_end   = state.customEnd
    }
  }
  if (state.sort) params._sort = SORT_API_MAP[state.sort] ?? state.sort
  params._format = format
  params._limit  = records

  await downloadExport('/cashbook/export', params, filename)
}

// ── Summary ───────────────────────────────────────────────────────────────
const summary = ref({
  total_credits:      0,
  total_debits:       0,
  net_balance:        0,
  unallocated_count:  0,
  unallocated_amount: 0,
})

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const { data } = await api.get('/cashbook/summary')
    summary.value = data
  } catch { /* silent */ } finally {
    summaryLoading.value = false
  }
}

// ── Entries ───────────────────────────────────────────────────────────────
const entries     = ref([])
const allCount    = ref(0)
const currentPage = ref(1)
const lastPage    = ref(1)

// ── AppTableToolbar config ────────────────────────────────────────────────
const CASHBOOK_FILTER_FIELDS = [
  {
    key: 'allocation_status',
    label: 'Allocation Status',
    options: [
      { value: 'allocated',   label: 'Allocated'   },
      { value: 'unallocated', label: 'Unallocated' },
    ],
  },
  {
    key: 'type',
    label: 'Type',
    options: [
      { value: 'credit', label: 'Credit (Received)' },
      { value: 'debit',  label: 'Debit (Paid Out)'  },
    ],
  },
]

const CASHBOOK_SORT_OPTIONS = [
  { value: 'date_desc',   label: 'Newest first'   },
  { value: 'date_asc',    label: 'Oldest first'   },
  { value: 'amount_desc', label: 'Highest amount' },
  { value: 'amount_asc',  label: 'Lowest amount'  },
]

const SORT_API_MAP = {
  date_desc:   'date:desc',
  date_asc:    'date:asc',
  amount_desc: 'amount:desc',
  amount_asc:  'amount:asc',
}

const toolbarKey      = ref(0)
const initialFilters  = ref({})
const toolbarState    = ref({ search: '', dateRange: 'all_time', customStart: '', customEnd: '', filters: {}, sort: null })
const tableRef        = ref(null)
let searchDebounceTimer = null

function showUnallocatedOnly() {
  initialFilters.value          = { allocation_status: 'unallocated' }
  toolbarState.value.filters    = { allocation_status: 'unallocated' }
  toolbarKey.value++
  currentPage.value = 1
  fetchEntries()
  tableRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function onToolbarUpdate(state) {
  const prevSearch   = toolbarState.value.search
  toolbarState.value = state
  currentPage.value  = 1
  clearTimeout(searchDebounceTimer)
  if (state.search !== prevSearch) {
    entriesLoading.value = true
    searchDebounceTimer = setTimeout(fetchEntries, 350)
  } else {
    fetchEntries()
  }
}

function buildApiParams() {
  const state  = toolbarState.value
  const params = { _per_page: 15, page: currentPage.value }

  if (state.filters?.allocation_status) params.allocation_status = state.filters.allocation_status
  if (state.search?.trim())             params._search = state.search.trim()
  if (state.dateRange && state.dateRange !== 'all_time') {
    params._date_range = state.dateRange
    if (state.dateRange === 'custom') {
      if (state.customStart) params._date_range_start = state.customStart
      if (state.customEnd)   params._date_range_end   = state.customEnd
    }
  }
  if (state.filters?.type) params.type = state.filters.type
  if (state.sort) params._sort = SORT_API_MAP[state.sort] ?? state.sort

  return params
}

async function fetchEntries() {
  entriesLoading.value = true
  try {
    const { data } = await api.get('/cashbook', { params: buildApiParams() })
    entries.value  = data.data ?? []
    const meta     = data.meta ?? {}
    lastPage.value = meta.last_page ?? 1
    allCount.value = meta.total ?? 0
  } catch { /* silent */ } finally {
    entriesLoading.value = false
  }
}

// ── Init ──────────────────────────────────────────────────────────────────
onMounted(() => {
  // Pre-apply filters from route query (e.g. ?allocation_status=unallocated)
  if (route.query.allocation_status) {
    initialFilters.value = { allocation_status: route.query.allocation_status }
    toolbarState.value.filters = { ...initialFilters.value }
    toolbarKey.value++ // remount toolbar with initialFilters
  }
  fetchSummary()
  fetchEntries()
})

// ── Derived stats ─────────────────────────────────────────────────────────
const allocatedCount = computed(() =>
  Math.max(0, allCount.value - summary.value.unallocated_count)
)
const allocatedPct   = computed(() =>
  allCount.value > 0 ? Math.round((allocatedCount.value / allCount.value) * 100) : 0
)
const unallocatedPct = computed(() => 100 - allocatedPct.value)

// ── Helpers ───────────────────────────────────────────────────────────────
function fmtCurrency(amount) {
  return 'R\u00a0' + Math.abs(Number(amount) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
}

function fmtSigned(amount, type) {
  return (type === 'credit' ? '+' : '-') + fmtCurrency(amount)
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ── Pagination ────────────────────────────────────────────────────────────
function goToPage(page) {
  if (page < 1 || page > lastPage.value || page === currentPage.value) return
  currentPage.value = page
  fetchEntries()
}

const pageNumbers = computed(() => {
  const total   = lastPage.value
  const current = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)

  const pages = [1]
  if (current > 3)        pages.push('...')
  const start = Math.max(2, current - 1)
  const end   = Math.min(total - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)
  if (current < total - 2) pages.push('...')
  pages.push(total)
  return pages
})

// ── Add Entry modal ───────────────────────────────────────────────────────
const showAddEntry       = ref(false)
const submittingEntry    = ref(false)
const estateOptions      = ref([])
const estatesLoading     = ref(false)
const unitOptionsForAdd  = ref([])
const unitsLoadingForAdd = ref(false)

const ENTRY_TYPE_OPTS = [
  { value: 'credit', label: 'Credit (Received)' },
  { value: 'debit',  label: 'Debit (Paid Out)'  },
]

const newEntry = ref({
  estate_id:   '',
  date:        '',
  type:        'credit',
  description: '',
  amount:      '',
  unit_id:     '',
  notes:       '',
})

async function fetchEstates() {
  estatesLoading.value = true
  try {
    const { data } = await api.get('/estates', { params: { _per_page: 100 } })
    estateOptions.value = (data.data ?? []).map(e => ({ value: e.id, label: e.name }))
  } finally {
    estatesLoading.value = false
  }
}

watch(() => newEntry.value.estate_id, async (estateId) => {
  newEntry.value.unit_id = ''
  unitOptionsForAdd.value = []
  if (!estateId) return
  unitsLoadingForAdd.value = true
  try {
    const { data } = await api.get(`/estates/${estateId}/units`, { params: { _per_page: 200 } })
    unitOptionsForAdd.value = (data.data ?? []).map(u => ({ value: u.id, label: u.unit_number }))
  } finally {
    unitsLoadingForAdd.value = false
  }
})

function openAddEntry() {
  fetchEstates()
  showAddEntry.value = true
}

async function handleAddEntry() {
  const { estate_id, date, type, description, amount } = newEntry.value
  if (submittingEntry.value || !estate_id || !date || !description || !amount) return
  submittingEntry.value = true
  try {
    await api.post('/cashbook', {
      estate_id,
      date,
      type,
      description,
      amount:  parseFloat(amount),
      unit_id: newEntry.value.unit_id || undefined,
      notes:   newEntry.value.notes   || undefined,
    })
    showAddEntry.value = false
    newEntry.value = { estate_id: '', date: '', type: 'credit', description: '', amount: '', unit_id: '', notes: '' }
    await Promise.all([fetchSummary(), fetchEntries()])
  } catch { /* silent */ } finally {
    submittingEntry.value = false
  }
}

// ── Allocate modal ────────────────────────────────────────────────────────
const showAllocate            = ref(false)
const allocatingEntry         = ref(null)
const allocateUnitId          = ref('')
const allocateInvoiceId       = ref('')
const allocateUnits           = ref([])
const allocateUnitsLoading    = ref(false)
const allocateInvoices        = ref([])
const allocateInvoicesLoading = ref(false)
const submittingAllocate      = ref(false)

// ── Smart invoice scoring ─────────────────────────────────────────────────
const CHARGE_KEYWORDS = {
  LEVY:                   ['levy'],
  RENT:                   ['rent'],
  SPECIAL_LEVY:           ['special'],
  WATER_RECOVERY:         ['water'],
  ELECTRICITY_RECOVERY:   ['electricity', 'elec'],
  GAS_RECOVERY:           ['gas'],
  SEWERAGE_RECOVERY:      ['sewerage', 'sewer'],
  REFUSE_RECOVERY:        ['refuse', 'waste'],
  LATE_INTEREST:          ['interest'],
  LATE_PENALTY:           ['penalty', 'fine'],
  INSURANCE_EXCESS:       ['insurance', 'excess'],
  DAMAGE_DEPOSIT:         ['damage', 'deposit'],
  KEY_DEPOSIT:            ['key'],
  PARKING_RENTAL:         ['parking'],
  STORAGE_RENTAL:         ['storage'],
  MOVING_IN:              ['moving'],
  MOVING_OUT:             ['moving'],
  ACCESS_CARD:            ['access', 'card', 'remote'],
  GYM_ACCESS:             ['gym'],
  POOL_ACCESS:            ['pool'],
  GARDEN_MAINT:           ['garden'],
  PET_LEVY:               ['pet'],
  SECURITY_CONTRIB:       ['security'],
  LEGAL_RECOVERY:         ['legal', 'attorney'],
}

const MONTH_ABBREVS = {
  1:  ['jan'], 2:  ['feb'], 3:  ['mar'], 4:  ['apr'],
  5:  ['may'], 6:  ['jun'], 7:  ['jul'], 8:  ['aug'],
  9:  ['sep'], 10: ['oct'], 11: ['nov'], 12: ['dec'],
}

function scoreInvoice(inv, entry) {
  let score = 0
  const desc        = (entry.description ?? '').toLowerCase()
  const amount      = parseFloat(entry.amount) || 0
  const outstanding = parseFloat(inv.outstanding ?? inv.amount) || 0

  // 1. Charge type keyword match (highest weight — most predictive)
  const code     = inv.charge_type?.code ?? ''
  const typeName = (inv.charge_type?.name ?? '').toLowerCase()
  const kws      = CHARGE_KEYWORDS[code] ?? [typeName]
  for (const kw of kws) {
    if (desc.includes(kw)) { score += 30; break }
  }

  // 2. Billing period month match
  if (inv.billing_period) {
    const d      = new Date(inv.billing_period + 'T00:00:00')
    const month  = d.getMonth() + 1
    const year   = d.getFullYear()
    const abbrevs = MONTH_ABBREVS[month] ?? []
    if (abbrevs.some(a => desc.includes(a))) score += 20
    if (desc.includes(String(year)))          score += 5
  }

  // 3. Amount proximity
  if (outstanding > 0) {
    if (Math.abs(outstanding - amount) < 0.01)           score += 25  // exact
    else if (outstanding <= amount && outstanding > 0)   score += 12  // payment covers it
    else if (amount > outstanding * 0.5)                 score += 6   // partial > half
  }

  // 4. Urgency — overdue needs clearing first
  if (inv.status === 'overdue')       score += 10
  else if (inv.status === 'sent')     score += 5

  // 5. Has actual balance to clear
  if (outstanding > 0) score += 5

  return score
}

const sortedAllocateInvoices = computed(() => {
  const invoices = allocateInvoices.value
  const entry    = allocatingEntry.value
  if (!entry || invoices.length === 0) return invoices
  return [...invoices]
    .map(inv => ({ inv, score: scoreInvoice(inv, entry) }))
    .sort((a, b) => b.score - a.score)
    .map(({ inv }) => inv)
})

function openAllocate(entry) {
  allocatingEntry.value   = entry
  allocateUnitId.value    = ''
  allocateInvoiceId.value = ''
  allocateInvoices.value  = []
  allocateUnits.value     = []
  showAllocate.value      = true
  fetchAllocateUnits(entry.estate_id)
}

async function fetchAllocateUnits(estateId) {
  if (!estateId) return
  allocateUnitsLoading.value = true
  try {
    const { data } = await api.get(`/estates/${estateId}/units`, { params: { _per_page: 200 } })
    allocateUnits.value = (data.data ?? []).map(u => ({ value: u.id, label: u.unit_number }))
  } finally {
    allocateUnitsLoading.value = false
  }
}

watch(allocateUnitId, async (unitId) => {
  allocateInvoiceId.value = ''
  allocateInvoices.value  = []
  if (!unitId) return
  allocateInvoicesLoading.value = true
  try {
    const { data } = await api.get('/invoices', { params: { unit_id: unitId, _per_page: 50 } })
    allocateInvoices.value = (data.data ?? []).filter(inv => {
      if (inv.status === 'paid' || inv.status === 'draft') return false
      const outstanding = parseFloat(inv.outstanding ?? inv.amount) || 0
      return outstanding > 0
    })
    // Auto-select best match
    if (sortedAllocateInvoices.value.length > 0) {
      allocateInvoiceId.value = sortedAllocateInvoices.value[0].id
    }
  } finally {
    allocateInvoicesLoading.value = false
  }
})

async function handleAllocate() {
  if (!allocateUnitId.value || !allocateInvoiceId.value || submittingAllocate.value) return
  submittingAllocate.value = true
  try {
    await api.post(`/cashbook/${allocatingEntry.value.id}/allocate`, {
      unit_id:    allocateUnitId.value,
      invoice_id: allocateInvoiceId.value,
    })
    showAllocate.value    = false
    allocatingEntry.value = null
    await Promise.all([fetchSummary(), fetchEntries()])
  } catch { /* silent */ } finally {
    submittingAllocate.value = false
  }
}

// ── Auto-Allocate ─────────────────────────────────────────────────────────
async function handleAutoAllocate() {
  try {
    await api.post('/cashbook/auto-allocate')
    await Promise.all([fetchSummary(), fetchEntries()])
  } catch { /* silent */ }
}

// ── Chart: Cash Flow ──────────────────────────────────────────────────────
const cashFlowData = computed(() => ({
  labels: ['Credits', 'Debits'],
  datasets: [{
    data: [summary.value.total_credits, summary.value.total_debits],
    backgroundColor: ['#22c55e', '#dc2828'],
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const cashFlowOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: ctx => ' R\u00a0' + Math.abs(ctx.parsed.y).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0'),
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11, family: "'DM Sans', sans-serif" }, color: MUTED },
    },
    y: {
      grid: { color: BORDER },
      border: { display: false },
      ticks: {
        font: { size: 11, family: "'DM Sans', sans-serif" },
        color: MUTED,
        callback: v => 'R ' + (v >= 1000 ? Math.round(v / 1000) + 'k' : v),
      },
    },
  },
}

// ── Chart: Allocation Status ──────────────────────────────────────────────
const centerTextPlugin = {
  id: 'centerText',
  beforeDraw(chart) {
    const opts = chart.config.options.plugins?.centerText
    if (!opts) return
    const { ctx, chartArea } = chart
    if (!chartArea) return
    const cx = (chartArea.left + chartArea.right) / 2
    const cy = (chartArea.top + chartArea.bottom) / 2
    ctx.save()
    ctx.textAlign    = 'center'
    ctx.textBaseline = 'middle'
    ctx.font      = 'bold 24px "DM Sans", sans-serif'
    ctx.fillStyle = '#1E2740'
    ctx.fillText(opts.value, cx, cy - 10)
    ctx.font      = '10px "DM Sans", sans-serif'
    ctx.fillStyle = '#717B99'
    ctx.fillText(opts.label, cx, cy + 10)
    ctx.restore()
  },
}

const allocData = computed(() => ({
  labels: [`Allocated ${allocatedPct.value}%`, `Unallocated ${unallocatedPct.value}%`],
  datasets: [{
    data: [allocatedCount.value, summary.value.unallocated_count],
    backgroundColor: ['#22c55e', '#D89B4B'],
    borderWidth: 2,
    borderColor: '#fff',
    hoverBorderColor: '#fff',
  }],
}))

const allocOpts = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    centerText: allocatedPct.value >= unallocatedPct.value
      ? { value: `${allocatedPct.value}%`, label: 'Allocated' }
      : { value: `${unallocatedPct.value}%`, label: 'Unallocated' },
    legend: {
      position: 'bottom',
      labels: {
        font: { size: 12, family: "'DM Sans', sans-serif" },
        color: MUTED,
        padding: 16,
        boxWidth: 10,
        boxHeight: 10,
        borderRadius: 3,
      },
    },
    tooltip: {
      callbacks: {
        label: ctx => ` ${ctx.label.split(' ')[0]}: ${ctx.parsed} entries`,
      },
    },
  },
}))

// ── Empty-state detection ─────────────────────────────────────────────────
const hasCashFlowData  = computed(() => summary.value.total_credits > 0 || summary.value.total_debits > 0)
const hasAllocationData = computed(() => allCount.value > 0)

</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading ───────────────────────────────────────────────── -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Cashbook</h1>
        <p class="text-sm text-muted-foreground">Payment recording &amp; allocation</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <AppButton variant="outline" @click="handleAutoAllocate">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="m16 3 4 4-4 4"/>
            <path d="M20 7H4"/>
            <path d="m8 21-4-4 4-4"/>
            <path d="M4 17h16"/>
          </svg>
          Auto-Allocate
        </AppButton>
        <AppButton variant="primary" @click="openAddEntry">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"/>
            <path d="M12 5v14"/>
          </svg>
          Add Entry
        </AppButton>
      </div>
    </div>

    <!-- ── Summary cards ──────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Total Credits -->
      <AppStatCard
        label="Total Credits"
        :value="summaryLoading ? '—' : fmtCurrency(summary.total_credits)"
        value-class="text-success"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-success">
            <path d="m7 7 10 10"/>
            <path d="M17 7v10H7"/>
          </svg>
        </template>
      </AppStatCard>

      <!-- Total Debits -->
      <AppStatCard
        label="Total Debits"
        :value="summaryLoading ? '—' : fmtCurrency(summary.total_debits)"
        value-class="text-destructive"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-destructive">
            <path d="M7 7h10v10"/>
            <path d="M7 17 17 7"/>
          </svg>
        </template>
      </AppStatCard>

      <!-- Net Balance -->
      <AppStatCard
        label="Net Balance"
        :value="summaryLoading ? '—' : fmtCurrency(summary.net_balance)"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-muted-foreground">
            <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
            <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
          </svg>
        </template>
      </AppStatCard>

      <!-- Unallocated -->
      <div class="rounded-lg border shadow-sm border-amber/40 bg-amber/5">
        <div class="p-5">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-muted-foreground">Unallocated</span>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-amber/10">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-amber">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" x2="12" y1="8" y2="12"/>
                <line x1="12" x2="12.01" y1="16" y2="16"/>
              </svg>
            </div>
          </div>
          <p class="text-3xl font-bold font-body text-foreground">
            {{ summaryLoading ? '—' : summary.unallocated_count + ' entr' + (summary.unallocated_count === 1 ? 'y' : 'ies') }}
          </p>
          <p class="text-xs text-muted-foreground mt-1">
            {{ summaryLoading ? '' : fmtCurrency(summary.unallocated_amount) + ' total' }}
          </p>
        </div>
      </div>

    </div>

    <!-- ── Unallocated warning banner ─────────────────────────────────── -->
    <div
      v-if="!summaryLoading && summary.unallocated_count > 0"
      class="flex items-center gap-3 rounded-lg border border-amber/30 bg-amber/5 px-4 py-3"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-amber shrink-0">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" x2="12" y1="8" y2="12"/>
        <line x1="12" x2="12.01" y1="16" y2="16"/>
      </svg>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-foreground">
          {{ summary.unallocated_count }} payment{{ summary.unallocated_count !== 1 ? 's' : '' }} not linked to any invoice
        </p>
        <p class="text-xs text-muted-foreground">
          {{ fmtCurrency(summary.unallocated_amount) }} in unmatched funds. Allocate them to clear outstanding balances.
        </p>
      </div>
      <button
        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-amber/40 bg-amber/10 text-xs font-medium text-amber hover:bg-amber/20 transition-colors whitespace-nowrap"
        @click="showUnallocatedOnly()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" x2="20" y1="6" y2="6"/><line x1="8" x2="16" y1="12" y2="12"/><line x1="12" x2="12" y1="18" y2="18"/>
        </svg>
        Show Unallocated
      </button>
    </div>

    <!-- ── Cashbook Table Card ────────────────────────────────────────── -->
    <div ref="tableRef" class="rounded-lg border bg-card shadow-sm">

      <!-- Section header -->
      <div class="px-6 pt-5 pb-3 flex items-center justify-between">
        <h3 class="font-body font-semibold text-lg flex items-center gap-2 text-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
            <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
            <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
          </svg>
          Cashbook Entries
        </h3>
        <!-- Export button aligned to section header -->
        <button
          @click="showExport = true"
          class="inline-flex items-center gap-1.5 h-8 px-3 rounded text-sm font-medium font-body text-muted-foreground hover:bg-muted hover:text-foreground transition-colors border border-border"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" x2="12" y1="15" y2="3"/>
          </svg>
          Export
        </button>
      </div>

      <!-- Toolbar row -->
      <div class="px-6 pb-4">
        <AppTableToolbar
          :key="toolbarKey"
          search-placeholder="Search entries..."
          :filter-fields="CASHBOOK_FILTER_FIELDS"
          :sort-options="CASHBOOK_SORT_OPTIONS"
          storage-key="cashbook-entries"
          date-range-context="transaction date"
          :initial-filters="initialFilters"
          @update:state="onToolbarUpdate"
        />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Date</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Description</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Type</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Unit</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>

            <!-- Loading skeleton rows -->
            <template v-if="entriesLoading">
              <tr v-for="n in 5" :key="n" class="border-b border-border last:border-0">
                <td class="py-3 px-4"><div class="h-4 bg-muted rounded animate-pulse w-24"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-muted rounded animate-pulse w-56"></div></td>
                <td class="py-3 px-4"><div class="h-5 bg-muted rounded animate-pulse w-16"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-4 bg-muted rounded animate-pulse w-20 ml-auto"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-muted rounded animate-pulse w-10"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-muted rounded animate-pulse w-28"></div></td>
                <td class="py-3 px-4"><div class="h-5 bg-muted rounded animate-pulse w-20"></div></td>
                <td class="py-3 px-4"></td>
              </tr>
            </template>

            <!-- Data rows -->
            <template v-else>
              <tr
                v-for="entry in entries"
                :key="entry.id"
                class="border-b border-border hover:bg-muted/50 transition-colors cursor-pointer last:border-0"
                @click="router.push('/cashbook/' + entry.id)"
              >
                <td class="py-3 px-4 text-foreground whitespace-nowrap">{{ formatDate(entry.date) }}</td>
                <td class="py-3 px-4 text-foreground">{{ entry.description }}</td>
                <td class="py-3 px-4">
                  <AppBadge :variant="entry.type === 'credit' ? 'success' : 'danger'" bordered size="sm">
                    {{ entry.type === 'credit' ? 'Credit' : 'Debit' }}
                  </AppBadge>
                </td>
                <td
                  class="py-3 px-4 text-right font-medium whitespace-nowrap"
                  :class="entry.type === 'credit' ? 'text-success' : 'text-destructive'"
                >
                  {{ fmtSigned(entry.amount, entry.type) }}
                </td>
                <td class="py-3 px-4">
                  <span v-if="entry.unit?.unit_number" class="text-foreground">{{ entry.unit.unit_number }}</span>
                  <span v-else class="text-muted-foreground">—</span>
                </td>
                <td class="py-3 px-4 font-mono text-xs">
                  <router-link
                    v-if="entry.invoice_id && entry.invoice"
                    :to="`/billing/invoices/${entry.invoice_id}`"
                    class="text-primary font-medium hover:underline"
                    @click.stop
                  >
                    {{ entry.invoice.invoice_number }}
                  </router-link>
                  <span v-else class="text-muted-foreground">—</span>
                </td>
                <td class="py-3 px-4">
                  <AppBadge :variant="entry.is_allocated ? 'success' : 'warning'" bordered size="sm">
                    {{ entry.is_allocated ? 'Allocated' : 'Unallocated' }}
                  </AppBadge>
                </td>
                <td class="py-3 px-4 text-right">
                  <AppTooltip
                    v-if="!entry.is_allocated"
                    text="Link this payment to a unit and invoice to clear the outstanding balance"
                    position="left"
                  >
                    <button
                      @click.stop="openAllocate(entry)"
                      class="inline-flex items-center gap-1.5 px-3 rounded-md border border-input bg-background hover:bg-muted hover:text-foreground text-xs h-7 font-medium font-body transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <path d="m16 3 4 4-4 4"/>
                        <path d="M20 7H4"/>
                        <path d="m8 21-4-4 4-4"/>
                        <path d="M4 17h16"/>
                      </svg>
                      Allocate
                    </button>
                  </AppTooltip>
                </td>
              </tr>

              <!-- Empty state -->
              <tr v-if="entries.length === 0">
                <td colspan="8" class="py-16 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground">
                        <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
                        <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                      </svg>
                    </div>
                    <p class="text-sm text-muted-foreground">No cashbook entries found.</p>
                  </div>
                </td>
              </tr>
            </template>

          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!entriesLoading && lastPage > 1" class="flex items-center justify-center gap-1 px-4 py-3 border-t border-border">
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1.5 text-xs rounded-md border border-input bg-background hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          Previous
        </button>
        <template v-for="p in pageNumbers" :key="p">
          <span v-if="p === '...'" class="px-2 text-xs text-muted-foreground">...</span>
          <button
            v-else
            @click="goToPage(p)"
            :class="[
              'px-3 py-1.5 text-xs rounded-md border transition-colors',
              currentPage === p
                ? 'bg-primary text-primary-foreground border-primary'
                : 'border-input bg-background hover:bg-muted',
            ]"
          >
            {{ p }}
          </button>
        </template>
        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === lastPage"
          class="px-3 py-1.5 text-xs rounded-md border border-input bg-background hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          Next
        </button>
      </div>

    </div>

    <!-- ── Charts ──────────────────────────────────────────────────────── -->
    <!-- Skeleton while primary data is still loading -->
    <div v-if="entriesLoading || summaryLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="n in 2" :key="n" class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-36 bg-muted rounded animate-pulse" />
          <div class="h-3 w-48 bg-muted rounded animate-pulse mt-1.5" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-64 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Cash Flow -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Cash Flow</h3>
          <p class="text-xs text-muted-foreground mt-0.5">Credits vs debits across all entries</p>
        </div>
        <div class="px-6 pb-6">
          <!-- Ghost chart when no data -->
          <div v-if="!hasCashFlowData" class="flex flex-col items-center">
            <svg width="100%" height="220" viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis -->
              <line x1="42" y1="10" x2="42" y2="168" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis -->
              <line x1="42" y1="168" x2="285" y2="168" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Grid lines -->
              <line x1="42" y1="44"  x2="285" y2="44"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="42" y1="86"  x2="285" y2="86"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="42" y1="128" x2="285" y2="128" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Y-axis tick label placeholders -->
              <rect x="6" y="41"  width="28" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="6" y="83"  width="28" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="6" y="125" width="28" height="6" rx="3" fill="#E8EAF0"/>
              <!-- Credits bar (green, tall) -->
              <rect x="68"  y="20" width="84" height="148" rx="4" fill="#D1EFE0" opacity="0.85"/>
              <!-- Debits bar (red, shorter) -->
              <rect x="172" y="70" width="84" height="98"  rx="4" fill="#FECACA" opacity="0.85"/>
              <!-- X-axis label placeholders -->
              <rect x="88"  y="176" width="44" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="192" y="176" width="44" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-3">Payment data will appear here once entries are added</p>
          </div>
          <!-- Real chart -->
          <div v-else class="h-[220px]">
            <Bar :data="cashFlowData" :options="cashFlowOpts" />
          </div>
        </div>
      </div>

      <!-- Allocation Status -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Allocation Status</h3>
          <p class="text-xs text-muted-foreground mt-0.5">Percentage of entries linked to invoices</p>
        </div>
        <div class="px-6 pb-6">
          <!-- Ghost chart when no data -->
          <div v-if="!hasAllocationData" class="flex flex-col items-center">
            <svg width="220" height="230" viewBox="0 0 220 230" xmlns="http://www.w3.org/2000/svg">
              <!-- Ghost donut: ~65% green (allocated), ~35% amber (unallocated) -->
              <path d="M 110 28 A 82 82 0 1 1 43.7 158.2 L 67.9 140.6 A 52 52 0 1 0 110 58 Z" fill="#D1EFE0" opacity="0.75"/>
              <path d="M 43.7 158.2 A 82 82 0 0 1 110 28 L 110 58 A 52 52 0 0 0 67.9 140.6 Z" fill="#FDE68A" opacity="0.75"/>
              <!-- Center hole -->
              <circle cx="110" cy="110" r="46" fill="white"/>
              <!-- Center text placeholders -->
              <rect x="84"  y="100" width="52" height="10" rx="5" fill="#E8EAF0"/>
              <rect x="92"  y="116" width="36" height="8"  rx="4" fill="#E8EAF0"/>
              <!-- Legend placeholders -->
              <rect x="30"  y="212" width="10" height="10" rx="2" fill="#D1EFE0"/>
              <rect x="44"  y="215" width="44" height="6"  rx="3" fill="#E8EAF0"/>
              <rect x="104" y="212" width="10" height="10" rx="2" fill="#FDE68A"/>
              <rect x="118" y="215" width="52" height="6"  rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-3">Allocation data will appear here once entries are added</p>
          </div>
          <!-- Real chart -->
          <div v-else class="h-[260px]">
            <Doughnut :data="allocData" :options="allocOpts" :plugins="[centerTextPlugin]" />
          </div>
        </div>
      </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- Add Entry Modal                                                  -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showAddEntry" title="Add Cashbook Entry" size="md" @close="showAddEntry = false">
      <div class="space-y-3">

        <!-- Estate -->
        <AppSelect
          v-model="newEntry.estate_id"
          label="Estate"
          :options="estateOptions"
          :placeholder="estatesLoading ? 'Loading estates...' : 'Select estate...'"
          :disabled="estatesLoading"
          required
        />

        <!-- Date + Type -->
        <div class="grid grid-cols-2 gap-3">
          <AppDatePicker v-model="newEntry.date" label="Date" placeholder="Select date..." required />
          <AppSelect v-model="newEntry.type" label="Type" :options="ENTRY_TYPE_OPTS" required />
        </div>

        <!-- Description -->
        <AppInput
          v-model="newEntry.description"
          label="Description"
          placeholder="e.g. EFT - S VAN DER MERWE LEVY APR"
          required
        />

        <!-- Amount + Unit -->
        <div class="grid grid-cols-2 gap-3">
          <AppInput
            v-model="newEntry.amount"
            label="Amount"
            type="number"
            placeholder="0.00"
            required
            prefix="R"
          />
          <div>
            <label class="block text-sm font-medium text-foreground mb-1.5">
              Unit <span class="text-muted-foreground font-normal text-xs">(optional)</span>
            </label>
            <AppSelect
              v-model="newEntry.unit_id"
              :options="unitOptionsForAdd"
              :placeholder="unitsLoadingForAdd ? 'Loading units...' : (newEntry.estate_id ? 'Select unit...' : 'Select estate first')"
              :disabled="!newEntry.estate_id || unitsLoadingForAdd"
            />
          </div>
        </div>

        <!-- Notes -->
        <AppInput
          v-model="newEntry.notes"
          label="Notes"
          placeholder="Optional notes (e.g. Advance payment — May and June levies)"
        />

      </div>
      <template #footer>
        <AppButton variant="outline" @click="showAddEntry = false">Cancel</AppButton>
        <AppButton
          variant="primary"
          :disabled="submittingEntry || !newEntry.estate_id || !newEntry.date || !newEntry.description || !newEntry.amount"
          @click="handleAddEntry"
        >
          {{ submittingEntry ? 'Adding...' : 'Add Entry' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- Allocate Modal                                                    -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showAllocate" title="Allocate Entry" size="md" @close="showAllocate = false">
      <div v-if="allocatingEntry" class="space-y-4">

        <!-- Entry summary card -->
        <div class="rounded-lg bg-muted p-4 space-y-2">
          <div class="flex items-center justify-between gap-4">
            <span class="text-xs text-muted-foreground shrink-0">Date</span>
            <span class="text-sm font-medium text-foreground">{{ formatDate(allocatingEntry.date) }}</span>
          </div>
          <div class="flex items-start justify-between gap-4">
            <span class="text-xs text-muted-foreground shrink-0">Description</span>
            <span class="text-sm font-medium text-foreground text-right">{{ allocatingEntry.description }}</span>
          </div>
          <div class="flex items-center justify-between gap-4">
            <span class="text-xs text-muted-foreground shrink-0">Amount</span>
            <span
              class="text-sm font-bold"
              :class="allocatingEntry.type === 'credit' ? 'text-success' : 'text-destructive'"
            >
              {{ fmtSigned(allocatingEntry.amount, allocatingEntry.type) }}
            </span>
          </div>
        </div>

        <!-- Unit selector -->
        <AppSelect
          v-model="allocateUnitId"
          label="Select Unit"
          :options="allocateUnits"
          :placeholder="allocateUnitsLoading ? 'Loading units...' : 'Search and select a unit...'"
          :disabled="allocateUnitsLoading"
          required
        />

        <!-- Outstanding invoices -->
        <div>
          <p class="text-sm font-medium text-foreground mb-2">Outstanding Invoices</p>

          <div v-if="allocateInvoicesLoading" class="rounded-lg border border-border bg-muted/40 py-8 text-center">
            <p class="text-xs text-muted-foreground">Loading invoices...</p>
          </div>

          <div v-else-if="!allocateUnitId" class="rounded-lg border border-border bg-muted/40 py-8 text-center">
            <p class="text-xs text-muted-foreground">Select a unit to see outstanding invoices.</p>
          </div>

          <div v-else-if="allocateInvoices.length === 0" class="rounded-lg border border-border bg-muted/40 py-8 text-center">
            <p class="text-xs text-muted-foreground">No outstanding invoices for this unit.</p>
          </div>

          <div v-else class="space-y-2">
            <label
              v-for="(inv, idx) in sortedAllocateInvoices"
              :key="inv.id"
              class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
              :class="allocateInvoiceId === inv.id
                ? 'border-primary bg-primary/5'
                : 'border-border bg-muted/20 hover:bg-muted/40'"
            >
              <input type="radio" :value="inv.id" v-model="allocateInvoiceId" class="accent-primary" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                  <p class="text-sm font-medium text-foreground">{{ inv.invoice_number }}</p>
                  <span
                    v-if="idx === 0"
                    class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-success/10 text-success leading-none"
                  >Best match</span>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ inv.charge_type?.name ?? 'Charge' }}
                  <span v-if="inv.billing_period"> · {{ new Date(inv.billing_period + 'T00:00:00').toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) }}</span>
                </p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-sm font-bold text-destructive">{{ fmtCurrency(inv.outstanding ?? inv.amount) }}</p>
                <p class="text-xs text-muted-foreground">outstanding</p>
              </div>
            </label>
          </div>
        </div>

      </div>
      <template #footer>
        <AppButton variant="outline" @click="showAllocate = false">Cancel</AppButton>
        <AppButton
          variant="primary"
          :disabled="submittingAllocate || !allocateUnitId || !allocateInvoiceId"
          @click="handleAllocate"
        >
          {{ submittingAllocate ? 'Allocating...' : 'Confirm Allocation' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Export Modal ───────────────────────────────────────────────────── -->
    <AppExportModal
      :show="showExport"
      context="Cashbook Entries"
      @close="showExport = false"
      @download="handleExportDownload"
    />

  </div>
</template>
