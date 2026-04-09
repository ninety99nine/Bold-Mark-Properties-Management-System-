<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
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
import AppButton       from '@/components/common/AppButton.vue'
import AppExportModal  from '@/components/common/AppExportModal.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import api             from '@/composables/useApi.js'
import { useExport }   from '@/composables/useExport.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend)

const router = useRouter()

// ─── Brand colours ────────────────────────────────────────────────────────
const NAVY   = '#1F3A5C'
const AMBER  = '#D89B4B'
const RED    = '#dc2828'
const MUTED  = '#717B99'
const BORDER = '#DCDEE8'

// ─── UI state ─────────────────────────────────────────────────────────────
const showExportModal = ref(false)

// ─── Export ───────────────────────────────────────────────────────────────
const { downloadExport } = useExport()

async function handleExportDownload({ format, records }) {
  showExportModal.value = false
  const ext      = format === 'xlsx' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
  const filename = `age-analysis-${new Date().toISOString().slice(0, 10)}.${ext}`

  const params = {}
  if (toolbarState.value.filters?.estate)       params.estate_id      = toolbarState.value.filters.estate
  if (toolbarState.value.filters?.charge_type)  params.charge_type_id = toolbarState.value.filters.charge_type
  if (toolbarState.value.filters?.billed_to)    params.billed_to_type = toolbarState.value.filters.billed_to
  params._format = format
  params._limit  = records

  await downloadExport('/age-analysis/export', params, filename)
}

// ─── Loading / error ──────────────────────────────────────────────────────
const loading = ref(false)
const error   = ref(null)

// ─── API data ─────────────────────────────────────────────────────────────
const ownersData  = ref([])
const tenantsData = ref([])
const summaryData = ref(null)

// ─── Filter option lists (from API) ───────────────────────────────────────
const estateOpts     = ref([])
const chargeTypeOpts = ref([])

// ─── Toolbar ──────────────────────────────────────────────────────────────
const toolbarState = ref({
  search: '', dateRange: 'all_time', customStart: '', customEnd: '', filters: {}, sort: null,
})
let searchDebounceTimer = null

const BILLED_TO_OPTS = [
  { value: 'owner',  label: 'Owners'  },
  { value: 'tenant', label: 'Tenants' },
]

const FILTER_FIELDS = computed(() => [
  {
    key:     'estate',
    label:   'Estate',
    options: estateOpts.value,
  },
  {
    key:     'charge_type',
    label:   'Charge Type',
    options: chargeTypeOpts.value,
  },
  {
    key:     'billed_to',
    label:   'Billed To',
    options: BILLED_TO_OPTS,
  },
])

const SORT_OPTIONS = [
  { value: 'name_asc',   label: 'Name (A → Z)'       },
  { value: 'name_desc',  label: 'Name (Z → A)'       },
  { value: 'total_desc', label: 'Total (High → Low)' },
  { value: 'total_asc',  label: 'Total (Low → High)' },
  { value: 'unit_asc',   label: 'Unit (A → Z)'       },
  { value: 'unit_desc',  label: 'Unit (Z → A)'       },
]

// ─── Pagination ───────────────────────────────────────────────────────────
const currentPage = ref(1)
const PER_PAGE    = 15

// ─── Load estates for filter dropdown ────────────────────────────────────
async function loadEstates() {
  try {
    const res = await api.get('/estates', { params: { _per_page: 200 } })
    estateOpts.value = (res.data.data ?? []).map(e => ({ value: e.id, label: e.name }))
  } catch { /* silently ignore — filters just won't be populated */ }
}

// ─── Load charge types for filter dropdown ────────────────────────────────
async function loadChargeTypes() {
  try {
    const res = await api.get('/charge-types', { params: { _per_page: 100 } })
    chargeTypeOpts.value = (res.data.data ?? []).map(c => ({ value: c.id, label: c.name }))
  } catch { /* silently ignore */ }
}

// ─── Fetch age analysis (server filters: estate_id, charge_type_id) ────────
async function fetchAgeAnalysis() {
  loading.value = true
  error.value   = null

  const params = {}
  if (toolbarState.value.filters?.estate) {
    params.estate_id = toolbarState.value.filters.estate
  }
  if (toolbarState.value.filters?.charge_type) {
    params.charge_type_id = toolbarState.value.filters.charge_type
  }

  try {
    const res     = await api.get('/age-analysis', { params })
    ownersData.value  = res.data.owners  ?? []
    tenantsData.value = res.data.tenants ?? []
    summaryData.value = res.data.summary ?? null
  } catch (e) {
    error.value = 'Failed to load age analysis data. Please try again.'
  } finally {
    loading.value = false
  }
}

// ─── Toolbar update handler ───────────────────────────────────────────────
function onToolbarUpdate(state) {
  const prev    = toolbarState.value
  toolbarState.value = state
  currentPage.value  = 1

  // Re-fetch only when server-side filters (estate, charge_type) change
  const serverChanged =
    state.filters?.estate       !== prev.filters?.estate ||
    state.filters?.charge_type  !== prev.filters?.charge_type
  if (serverChanged) {
    fetchAgeAnalysis()
    return
  }

  // Debounce search (client-side computed handles it)
  if (state.search !== prev.search) {
    clearTimeout(searchDebounceTimer)
    searchDebounceTimer = setTimeout(() => {}, 350)
  }
}

// ─── Client-side filtered + sorted rows ───────────────────────────────────
const activeRows = computed(() => {
  // Merge owners and tenants, tagging each with _role
  const billedTo = toolbarState.value.filters?.billed_to
  let base
  if (billedTo === 'owner') {
    base = ownersData.value.map(r => ({ ...r, _role: 'owner' }))
  } else if (billedTo === 'tenant') {
    base = tenantsData.value.map(r => ({ ...r, _role: 'tenant' }))
  } else {
    base = [
      ...ownersData.value.map(r => ({ ...r, _role: 'owner' })),
      ...tenantsData.value.map(r => ({ ...r, _role: 'tenant' })),
    ]
  }

  // Apply search
  const search = (toolbarState.value.search ?? '').trim().toLowerCase()
  let rows = base
  if (search) {
    rows = base.filter(r =>
      (r.person_name ?? '').toLowerCase().includes(search) ||
      (r.unit_number ?? '').toLowerCase().includes(search) ||
      (r.charge_type ?? '').toLowerCase().includes(search)
    )
  }

  // Apply sort
  const sort = toolbarState.value.sort
  if (sort) {
    rows = [...rows].sort((a, b) => {
      if (sort === 'name_asc')   return (a.person_name ?? '').localeCompare(b.person_name ?? '')
      if (sort === 'name_desc')  return (b.person_name ?? '').localeCompare(a.person_name ?? '')
      if (sort === 'total_asc')  return (a.outstanding ?? 0) - (b.outstanding ?? 0)
      if (sort === 'total_desc') return (b.outstanding ?? 0) - (a.outstanding ?? 0)
      if (sort === 'unit_asc')   return (a.unit_number ?? '').localeCompare(b.unit_number ?? '')
      if (sort === 'unit_desc')  return (b.unit_number ?? '').localeCompare(a.unit_number ?? '')
      return 0
    })
  }

  return rows
})

// ─── Pagination computed ──────────────────────────────────────────────────
const totalPages       = computed(() => Math.max(1, Math.ceil(activeRows.value.length / PER_PAGE)))
const totalRowsInQuery = computed(() => activeRows.value.length)
const paginatedRows    = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return activeRows.value.slice(start, start + PER_PAGE)
})

function setPage(page) {
  currentPage.value = Math.min(Math.max(1, page), totalPages.value)
}

// ─── Summary (from API) ───────────────────────────────────────────────────
const summary = computed(() => ({
  current: summaryData.value?.current           ?? 0,
  d30:     summaryData.value?.['30_days']        ?? 0,
  d60:     summaryData.value?.['60_days']        ?? 0,
  d90:     summaryData.value?.['90_days']        ?? 0,
  d120:    summaryData.value?.['120_plus']       ?? 0,
  total:   summaryData.value?.total_outstanding  ?? 0,
}))

// ─── Chart helpers ────────────────────────────────────────────────────────
const ownersTotal  = computed(() => ownersData.value.reduce((s, r) => s + (r.outstanding ?? 0), 0))
const tenantsTotal = computed(() => tenantsData.value.reduce((s, r) => s + (r.outstanding ?? 0), 0))
const grandTotal   = computed(() => ownersTotal.value + tenantsTotal.value)
const ownersPct    = computed(() =>
  grandTotal.value > 0 ? Math.round((ownersTotal.value / grandTotal.value) * 100) : 0
)
const tenantsPct   = computed(() => 100 - ownersPct.value)

const hasAgeData     = computed(() => summary.value.total > 0)
const hasOwnersData  = computed(() => ownersData.value.length > 0)
const hasTenantsData = computed(() => tenantsData.value.length > 0)

// ─── Format helpers ───────────────────────────────────────────────────────
function fmt(val) {
  if (!val || val === 0) return '—'
  return 'R\u00a0' + Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
}

function fmtFull(val) {
  return 'R\u00a0' + Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
}

function fmtTip(val) {
  return 'R ' + Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
}

// ─── Selected estate label (for page subtitle) ────────────────────────────
const selectedEstateLabel = computed(() => {
  const estateId = toolbarState.value.filters?.estate
  if (!estateId) return null
  return estateOpts.value.find(e => e.value === estateId)?.label ?? null
})

// ─── Navigation ───────────────────────────────────────────────────────────
function goToOwner(row) {
  if (row.person_id) {
    router.push({ name: 'owner-detail', params: { ownerId: row.person_id } })
  }
}

// ─── Chart 1: Arrears by Ageing Bucket ───────────────────────────────────
const ageBucketData = computed(() => ({
  labels: ['Current', '30 Days', '60 Days', '90 Days', '120+ Days'],
  datasets: [{
    label: 'Arrears',
    data: [summary.value.current, summary.value.d30, summary.value.d60, summary.value.d90, summary.value.d120],
    backgroundColor: [NAVY, AMBER, RED, RED, RED],
    borderRadius: 4,
  }],
}))

const ageBucketOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: ctx => ' ' + fmtTip(ctx.parsed.y) } },
  },
  scales: {
    x: {
      grid: { display: false },
      border: { display: false },
      ticks: { font: { size: 11 }, color: MUTED },
    },
    y: {
      beginAtZero: true,
      border: { display: false },
      grid: { color: BORDER },
      ticks: {
        font: { size: 11 },
        color: MUTED,
        callback: v => `R ${(v / 1000).toFixed(0)}k`,
      },
    },
  },
}

// ─── Shared horizontal bar options ────────────────────────────────────────
const hBarOpts = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: ctx => ' ' + fmtTip(ctx.parsed.x) } },
  },
  scales: {
    x: {
      beginAtZero: true,
      border: { display: false },
      grid: { color: BORDER },
      ticks: {
        font: { size: 11 },
        color: MUTED,
        callback: v => `R ${(v / 1000).toFixed(0)}k`,
      },
    },
    y: {
      grid: { display: false },
      border: { display: false },
      ticks: { font: { size: 12 }, color: MUTED },
    },
  },
}

// ─── Chart 2: Owners Outstanding ─────────────────────────────────────────
const ownersBarData = computed(() => {
  const sorted = [...ownersData.value].sort((a, b) => (a.outstanding ?? 0) - (b.outstanding ?? 0))
  return {
    labels: sorted.map(r => (r.person_name ?? '—').split(' ').pop()),
    datasets: [{
      label: 'Outstanding',
      data: sorted.map(r => r.outstanding ?? 0),
      backgroundColor: RED,
      borderRadius: 4,
    }],
  }
})

// ─── Chart 3: Tenants Outstanding ────────────────────────────────────────
const tenantsBarData = computed(() => {
  const sorted = [...tenantsData.value].sort((a, b) => (a.outstanding ?? 0) - (b.outstanding ?? 0))
  return {
    labels: sorted.map(r => (r.person_name ?? '—').split(' ').pop()),
    datasets: [{
      label: 'Outstanding',
      data: sorted.map(r => r.outstanding ?? 0),
      backgroundColor: RED,
      borderRadius: 4,
    }],
  }
})

// ─── Chart 4: Owner vs Tenant Split (donut) ───────────────────────────────
const splitData = computed(() => ({
  labels: [`Owners ${ownersPct.value}%`, `Tenants ${tenantsPct.value}%`],
  datasets: [{
    data: [ownersTotal.value, tenantsTotal.value],
    backgroundColor: ['#22c55e', '#3b82f6'],
    borderWidth: 2,
    borderColor: '#fff',
    hoverBorderColor: '#fff',
  }],
}))

const splitOpts = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '62%',
  plugins: {
    centerText: ownersPct.value >= tenantsPct.value
      ? { value: `${ownersPct.value}%`, label: 'Owners' }
      : { value: `${tenantsPct.value}%`, label: 'Tenants' },
    legend: {
      position: 'bottom',
      labels: {
        font: { size: 12, family: "'DM Sans', sans-serif" },
        color: '#1E2740',
        padding: 16,
        boxWidth: 10,
        boxHeight: 10,
        borderRadius: 3,
      },
    },
    tooltip: { callbacks: { label: ctx => ' ' + fmtTip(ctx.parsed) } },
  },
}))

// ─── Center text plugin (donut) ───────────────────────────────────────────
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
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.font = 'bold 24px "DM Sans", sans-serif'
    ctx.fillStyle = '#1E2740'
    ctx.fillText(opts.value, cx, cy - 10)
    ctx.font = '10px "DM Sans", sans-serif'
    ctx.fillStyle = '#717B99'
    ctx.fillText(opts.label, cx, cy + 10)
    ctx.restore()
  },
}

// ─── Init ─────────────────────────────────────────────────────────────────
onMounted(() => {
  loadEstates()
  loadChargeTypes()
  fetchAgeAnalysis()
})
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Error state ────────────────────────────────────────────────────── -->
    <div
      v-if="error"
      class="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive"
    >
      {{ error }}
    </div>

    <!-- ── Page Header ────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Age Analysis</h1>
        <p class="text-sm text-muted-foreground mt-0.5">
          <template v-if="selectedEstateLabel">
            {{ selectedEstateLabel }} — Arrears by ageing bucket
          </template>
          <template v-else>
            All Estates — Arrears by ageing bucket
          </template>
        </p>
      </div>
    </div>

    <!-- ── 6 Summary Cards ────────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

      <!-- Skeleton while loading -->
      <template v-if="loading && !summaryData">
        <div v-for="n in 6" :key="n" class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <div class="h-7 w-20 bg-muted rounded animate-pulse mx-auto mb-1.5" />
            <div class="h-3 w-14 bg-muted rounded animate-pulse mx-auto" />
          </div>
        </div>
      </template>

      <!-- Real summary cards -->
      <template v-else>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">Current</p>
            <p class="text-xl font-bold font-body text-foreground">{{ fmtFull(summary.current) }}</p>
          </div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">30 Days</p>
            <p class="text-xl font-bold font-body text-foreground">{{ fmtFull(summary.d30) }}</p>
          </div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">60 Days</p>
            <p class="text-xl font-bold font-body text-foreground">{{ fmtFull(summary.d60) }}</p>
          </div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">90 Days</p>
            <p class="text-xl font-bold font-body text-destructive">{{ fmtFull(summary.d90) }}</p>
          </div>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">120+ Days</p>
            <p class="text-xl font-bold font-body text-destructive">{{ fmtFull(summary.d120) }}</p>
          </div>
        </div>

        <!-- Total Outstanding -->
        <div class="rounded-lg border border-accent/30 bg-accent/5 text-card-foreground shadow-sm">
          <div class="p-4 text-center">
            <p class="text-xs text-muted-foreground">Total Outstanding</p>
            <p class="text-xl font-bold font-body text-destructive">{{ fmtFull(summary.total) }}</p>
          </div>
        </div>

      </template>
    </div>

    <!-- ── Table Card ─────────────────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card shadow-sm">

      <!-- Table card header -->
      <div class="px-6 pt-5 pb-3 flex items-center justify-between">
        <h3 class="font-body font-semibold text-lg flex items-center gap-2 text-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
            <path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>
          </svg>
          Arrears Report
        </h3>
        <!-- Export button aligned to section header -->
        <button
          @click="showExportModal = true"
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
          search-placeholder="Search by name, unit..."
          :filter-fields="FILTER_FIELDS"
          :sort-options="SORT_OPTIONS"
          storage-key="age-analysis"
          date-range-context="Due Date"
          @update:state="onToolbarUpdate"
        />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/50">
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Name</th>
              <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Unit</th>
              <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Type</th>
              <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Current</th>
              <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">30 Days</th>
              <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">60 Days</th>
              <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">90 Days</th>
              <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">120+ Days</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-foreground uppercase tracking-wider font-bold">Total</th>
            </tr>
          </thead>
          <tbody>

            <!-- Loading skeleton rows -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="`sk-${n}`" class="border-b border-border">
                <td class="py-3 px-4">
                  <div class="h-4 w-36 bg-muted rounded animate-pulse mb-1" />
                  <div class="h-3 w-28 bg-muted rounded animate-pulse" />
                </td>
                <td class="py-3 px-3"><div class="h-4 w-10 bg-muted rounded animate-pulse" /></td>
                <td class="py-3 px-3"><div class="h-4 w-14 bg-muted rounded animate-pulse" /></td>
                <td class="py-3 px-3 text-right"><div class="h-4 w-14 bg-muted rounded animate-pulse ml-auto" /></td>
                <td class="py-3 px-3 text-right"><div class="h-4 w-4 bg-muted rounded animate-pulse ml-auto" /></td>
                <td class="py-3 px-3 text-right"><div class="h-4 w-4 bg-muted rounded animate-pulse ml-auto" /></td>
                <td class="py-3 px-3 text-right"><div class="h-4 w-4 bg-muted rounded animate-pulse ml-auto" /></td>
                <td class="py-3 px-3 text-right"><div class="h-4 w-4 bg-muted rounded animate-pulse ml-auto" /></td>
                <td class="py-3 px-4 text-right"><div class="h-4 w-14 bg-muted rounded animate-pulse ml-auto" /></td>
              </tr>
            </template>

            <!-- Real rows -->
            <template v-else>
              <tr
                v-for="row in paginatedRows"
                :key="row.invoice_id"
                class="border-b border-border hover:bg-muted/30 transition-colors"
              >
                <!-- Name: clickable link -->
                <td class="py-3 px-4 font-medium">
                  <div class="flex items-center gap-2">
                    <button
                      class="text-foreground hover:text-accent hover:underline transition-colors text-left"
                      @click="row._role === 'owner' ? goToOwner(row) : undefined"
                    >
                      {{ row.person_name ?? '—' }}
                    </button>
                    <!-- Role badge — shown when viewing all (no billed_to filter) -->
                    <span
                      v-if="!toolbarState.filters?.billed_to"
                      :class="[
                        'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium leading-none shrink-0',
                        row._role === 'owner'
                          ? 'bg-success/10 text-success border border-success/20'
                          : 'bg-blue-50 text-blue-600 border border-blue-200',
                      ]"
                    >
                      {{ row._role === 'owner' ? 'Owner' : 'Tenant' }}
                    </span>
                  </div>
                  <p v-if="row.person_email" class="text-xs text-muted-foreground mt-0.5 truncate max-w-[200px]">
                    {{ row.person_email }}
                  </p>
                </td>
                <td class="py-3 px-3 text-foreground font-medium">{{ row.unit_number ?? '—' }}</td>
                <td class="py-3 px-3 text-muted-foreground">{{ row.charge_type ?? '—' }}</td>
                <!-- Current: neutral -->
                <td class="py-3 px-3 text-right text-foreground">{{ fmt(row.current) }}</td>
                <!-- 30 Days: neutral -->
                <td class="py-3 px-3 text-right text-foreground">{{ fmt(row['30_days']) }}</td>
                <!-- 60 Days: amber warning -->
                <td class="py-3 px-3 text-right text-accent">{{ fmt(row['60_days']) }}</td>
                <!-- 90 Days: red danger -->
                <td class="py-3 px-3 text-right text-destructive">{{ fmt(row['90_days']) }}</td>
                <!-- 120+ Days: red danger bold -->
                <td class="py-3 px-3 text-right text-destructive font-medium">{{ fmt(row['120_plus']) }}</td>
                <!-- Total: bold -->
                <td class="py-3 px-4 text-right font-bold text-foreground">{{ fmt(row.outstanding) }}</td>
              </tr>

              <!-- Empty state -->
              <tr v-if="paginatedRows.length === 0">
                <td colspan="9" class="py-12 text-center">
                  <div class="flex flex-col items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40">
                      <path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>
                    </svg>
                    <p class="text-sm text-muted-foreground">
                      <template v-if="toolbarState.search || toolbarState.filters?.estate || toolbarState.filters?.charge_type">
                        No arrears match your filters.
                      </template>
                      <template v-else>
                        No outstanding arrears found. All accounts are clear.
                      </template>
                    </p>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="!loading && totalPages > 1"
        class="flex items-center justify-between px-6 py-3 border-t border-border"
      >
        <p class="text-xs text-muted-foreground">
          Showing {{ (currentPage - 1) * PER_PAGE + 1 }}–{{ Math.min(currentPage * PER_PAGE, totalRowsInQuery) }}
          of {{ totalRowsInQuery }} records
        </p>
        <div class="flex items-center gap-1">
          <button
            class="h-8 px-3 text-xs rounded border border-border hover:bg-muted transition-colors disabled:opacity-40 disabled:pointer-events-none"
            :disabled="currentPage <= 1"
            @click="setPage(currentPage - 1)"
          >Previous</button>
          <button
            v-for="page in totalPages"
            :key="page"
            :class="[
              'h-8 w-8 text-xs rounded border transition-colors',
              page === currentPage
                ? 'bg-primary text-primary-foreground border-primary'
                : 'border-border hover:bg-muted',
            ]"
            @click="setPage(page)"
          >{{ page }}</button>
          <button
            class="h-8 px-3 text-xs rounded border border-border hover:bg-muted transition-colors disabled:opacity-40 disabled:pointer-events-none"
            :disabled="currentPage >= totalPages"
            @click="setPage(currentPage + 1)"
          >Next</button>
        </div>
      </div>

    </div>

    <!-- ── Charts: 3-column row ───────────────────────────────────────────── -->
    <!-- Skeleton while primary data is still loading -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div v-for="n in 3" :key="n" class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-40 bg-muted rounded animate-pulse" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-56 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Chart 1: Arrears by Ageing Bucket -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Arrears by Ageing Bucket</h3>
        </div>
        <div class="px-6 pb-6">
          <!-- Loading skeleton -->
          <div v-if="loading" class="h-[280px] flex items-end gap-4 justify-center pb-6">
            <div v-for="n in 5" :key="n" :style="{ height: `${30 + n * 20}px` }" class="w-12 bg-muted rounded animate-pulse" />
          </div>
          <!-- Ghost chart when no data -->
          <div v-else-if="!hasAgeData" class="h-[280px] flex flex-col items-center justify-center">
            <svg width="100%" height="220" viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis -->
              <line x1="38" y1="10" x2="38" y2="168" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis (extended to cover all 5 bars) -->
              <line x1="38" y1="168" x2="298" y2="168" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Grid lines -->
              <line x1="38" y1="44"  x2="298" y2="44"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="38" y1="86"  x2="298" y2="86"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="38" y1="128" x2="298" y2="128" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Y-axis tick labels -->
              <rect x="8" y="41"  width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8" y="83"  width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8" y="125" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <!-- 5 bars evenly spaced (width=36, gap=16): Current(navy), 30d(amber), 60d-90d-120+(red) -->
              <rect x="50"  y="28"  width="36" height="140" rx="4" fill="#CBD5E8" opacity="0.75"/>
              <rect x="102" y="80"  width="36" height="88"  rx="4" fill="#FDE68A" opacity="0.75"/>
              <rect x="154" y="112" width="36" height="56"  rx="4" fill="#FECACA" opacity="0.75"/>
              <rect x="206" y="148" width="36" height="20"  rx="4" fill="#FECACA" opacity="0.55"/>
              <rect x="258" y="154" width="36" height="14"  rx="4" fill="#FECACA" opacity="0.45"/>
              <!-- X-axis labels (centred under each bar) -->
              <rect x="52"  y="176" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="104" y="176" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="156" y="176" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="208" y="176" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="260" y="176" width="32" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground -mt-1">Arrears data will appear here once invoices are overdue</p>
          </div>
          <!-- Chart -->
          <div v-else class="h-[280px] relative">
            <Bar :data="ageBucketData" :options="ageBucketOpts" />
          </div>
        </div>
      </div>

      <!-- Chart 2: Owners Outstanding -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Owners — Outstanding</h3>
        </div>
        <div class="px-6 pb-6">
          <!-- Loading skeleton -->
          <div v-if="loading" class="h-[280px] space-y-3 py-4">
            <div v-for="n in 5" :key="n" class="h-6 bg-muted rounded animate-pulse" :style="{ width: `${30 + n * 12}%` }" />
          </div>
          <!-- Ghost chart when no data -->
          <div v-else-if="!hasOwnersData" class="h-[280px] flex flex-col items-center justify-center">
            <svg width="100%" height="220" viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis (left) -->
              <line x1="90" y1="10" x2="90" y2="178" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis (bottom) -->
              <line x1="90" y1="178" x2="285" y2="178" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Grid lines (vertical) -->
              <line x1="148" y1="10" x2="148" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="210" y1="10" x2="210" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="270" y1="10" x2="270" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Y-axis labels (name placeholders) -->
              <rect x="10" y="19"  width="74" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="53"  width="62" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="87"  width="68" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="121" width="56" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="155" width="66" height="6" rx="3" fill="#E8EAF0"/>
              <!-- Horizontal bars (red ghost) -->
              <rect x="92" y="12"  height="16" width="186" rx="4" fill="#FECACA" opacity="0.8"/>
              <rect x="92" y="46"  height="16" width="150" rx="4" fill="#FECACA" opacity="0.8"/>
              <rect x="92" y="80"  height="16" width="118" rx="4" fill="#FECACA" opacity="0.8"/>
              <rect x="92" y="114" height="16" width="84"  rx="4" fill="#FECACA" opacity="0.75"/>
              <rect x="92" y="148" height="16" width="52"  rx="4" fill="#FECACA" opacity="0.7"/>
              <!-- X-axis tick labels -->
              <rect x="128" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="192" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="254" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground -mt-1">No owner arrears — data will appear here once invoices are overdue</p>
          </div>
          <!-- Chart -->
          <div v-else class="h-[280px] relative">
            <Bar :data="ownersBarData" :options="hBarOpts" />
          </div>
        </div>
      </div>

      <!-- Chart 3: Tenants Outstanding -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Tenants — Outstanding</h3>
        </div>
        <div class="px-6 pb-6">
          <!-- Loading skeleton -->
          <div v-if="loading" class="h-[280px] space-y-3 py-4">
            <div v-for="n in 3" :key="n" class="h-6 bg-muted rounded animate-pulse" :style="{ width: `${40 + n * 15}%` }" />
          </div>
          <!-- Ghost chart when no data -->
          <div v-else-if="!hasTenantsData" class="h-[280px] flex flex-col items-center justify-center">
            <svg width="100%" height="220" viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis -->
              <line x1="90" y1="10" x2="90" y2="178" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis -->
              <line x1="90" y1="178" x2="285" y2="178" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Grid lines -->
              <line x1="148" y1="10" x2="148" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="210" y1="10" x2="210" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="270" y1="10" x2="270" y2="178" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Y-axis labels -->
              <rect x="10" y="37"  width="74" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="87"  width="60" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="137" width="68" height="6" rx="3" fill="#E8EAF0"/>
              <!-- Horizontal bars (3 tenants, red ghost) -->
              <rect x="92" y="28"  height="16" width="172" rx="4" fill="#FECACA" opacity="0.8"/>
              <rect x="92" y="78"  height="16" width="120" rx="4" fill="#FECACA" opacity="0.8"/>
              <rect x="92" y="128" height="16" width="72"  rx="4" fill="#FECACA" opacity="0.75"/>
              <!-- X-axis tick labels -->
              <rect x="128" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="192" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="254" y="186" width="24" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground -mt-1">No tenant arrears — data will appear here once invoices are overdue</p>
          </div>
          <!-- Chart -->
          <div v-else class="h-[280px] relative">
            <Bar :data="tenantsBarData" :options="hBarOpts" />
          </div>
        </div>
      </div>

    </div>

    <!-- ── Owner vs Tenant Split (donut) ──────────────────────────────────── -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-40 bg-muted rounded animate-pulse" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-64 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Owner vs Tenant Split</h3>
        </div>
        <div class="px-6 pb-6">
          <!-- Loading skeleton -->
          <div v-if="loading" class="h-[260px] flex items-center justify-center">
            <div class="w-40 h-40 rounded-full border-4 border-muted animate-pulse" />
          </div>
          <!-- Ghost chart when no data -->
          <div v-else-if="!hasAgeData" class="h-[260px] flex flex-col items-center justify-center">
            <svg width="240" height="220" viewBox="0 0 240 220" xmlns="http://www.w3.org/2000/svg">
              <!-- Ghost donut: 2 segments (~65% green/owner, ~35% blue/tenant) -->
              <!-- Geometry: center(120,110), outer r=80, inner r=50 — matches Occupancy Breakdown -->
              <!-- Segment 1: Owners (65% — large arc, sweep-flag=1) -->
              <path d="M 120.0 30.0 A 80 80 0 1 1 55.3 157.0 L 79.5 139.4 A 50 50 0 1 0 120.0 60.0 Z" fill="#D1EFE0" opacity="0.75"/>
              <!-- Segment 2: Tenants (35% — small arc, sweep-flag=1) -->
              <path d="M 55.3 157.0 A 80 80 0 0 1 120.0 30.0 L 120.0 60.0 A 50 50 0 0 0 79.5 139.4 Z" fill="#CCDDF9" opacity="0.75"/>
              <!-- Center hole fill -->
              <circle cx="120" cy="110" r="42" fill="white"/>
              <!-- Center text placeholders -->
              <rect x="94"  y="102" width="52" height="10" rx="5" fill="#E8EAF0"/>
              <rect x="102" y="118" width="36" height="8"  rx="4" fill="#E8EAF0"/>
              <!-- Legend placeholders -->
              <rect x="46"  y="202" width="10" height="10" rx="2" fill="#D1EFE0"/>
              <rect x="60"  y="205" width="40" height="6"  rx="3" fill="#E8EAF0"/>
              <rect x="120" y="202" width="10" height="10" rx="2" fill="#CCDDF9"/>
              <rect x="134" y="205" width="48" height="6"  rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-3">Data will appear here once arrears exist</p>
          </div>
          <!-- Chart -->
          <div v-else class="h-[260px] w-full relative">
            <Doughnut :data="splitData" :options="splitOpts" :plugins="[centerTextPlugin]" />
          </div>
        </div>
      </div>

    </div>

    <!-- ── Export Modal ───────────────────────────────────────────────────── -->
    <AppExportModal
      :show="showExportModal"
      context="Age Analysis"
      @close="showExportModal = false"
      @download="handleExportDownload"
    />

  </div>
</template>
