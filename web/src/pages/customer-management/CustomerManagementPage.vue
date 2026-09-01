<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
import api from '@/composables/useApi'
import AppStatCard from '@/components/common/AppStatCard.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import AppModal from '@/components/common/AppModal.vue'
import HighRiskSection from './HighRiskSection.vue'
import { ENTITY_TYPE_OPTIONS, entityTypeLabel, entityTypeBadgeClass, ENTITY_TYPE_COLOR } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'

const countryStore = useCountryStore()

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend)

const NAVY   = '#1F3A5C'
const AMBER  = '#D89B4B'
const RED    = '#dc2828'
const MUTED  = '#717B99'
const BORDER = '#DCDEE8'
const GREEN  = '#22c55e'

const router = useRouter()

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  const num = Math.round(Number(amount))
  if (isNaN(num)) return '—'
  return countryStore.formatCurrency(amount)
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function daysOverdue(dateStr) {
  if (!dateStr) return 0
  const due = new Date(dateStr)
  const now = new Date()
  const diff = Math.floor((now - due) / (1000 * 60 * 60 * 24))
  return Math.max(0, diff)
}

function overdueLabel(days) {
  if (days <= 30) return '< 30 days'
  if (days <= 60) return '30–60 days'
  if (days <= 90) return '60–90 days'
  return '90+ days'
}

function overdueBadgeClass(days) {
  if (days <= 30) return 'bg-warning/10 text-amber-dark border-warning/20'
  if (days <= 60) return 'bg-orange-100 text-orange-700 border-orange-200'
  if (days <= 90) return 'bg-destructive/10 text-destructive border-destructive/20'
  return 'bg-destructive/20 text-destructive border-destructive/30'
}

const typeLabel = entityTypeLabel
const typeBadgeClass = entityTypeBadgeClass

// ── State ─────────────────────────────────────────────────────────────
const loading = ref(true)
const units = ref([])
const summary = ref(null)
const communities = ref([])
const ledgers = ref([])
const meta = ref({ total: 0, current_page: 1, last_page: 1, per_page: 15 })

// ── Community cards (capped at 5) ───────────────────────────────────────
const COMMUNITY_CARD_LIMIT = 4
const showAllCommunitiesModal = ref(false)
const allCommunityCards = computed(() => summary.value?.by_community ?? [])
const visibleCommunityCards = computed(() => allCommunityCards.value.slice(0, COMMUNITY_CARD_LIMIT))
const hasMoreCommunities = computed(() => allCommunityCards.value.length > COMMUNITY_CARD_LIMIT)

// ── Toolbar ───────────────────────────────────────────────────────────
const toolbarState = ref({ search: '', dateRange: 'all_time', filters: {}, sort: null })

function onToolbarUpdate(state) {
  toolbarState.value = state
  meta.value.current_page = 1
  fetchArrears()
}

const filterFields = computed(() => {
  const communityOptions = communities.value.map(e => ({ value: e.id, label: e.name }))
  const chargeOptions = ledgers.value.map(ct => ({ value: ct.id, label: ct.name }))

  return [
    { key: 'community_id', label: 'Community', options: communityOptions },
    {
      key: 'community_type', label: 'Entity Type',
      options: ENTITY_TYPE_OPTIONS,
    },
    { key: 'ledger', label: 'Ledger', options: chargeOptions },
  ]
})

const sortOptions = [
  { value: 'overdue_amount:desc', label: 'Highest arrears'  },
  { value: 'overdue_amount:asc',  label: 'Lowest arrears'   },
  { value: 'oldest_overdue:asc',  label: 'Longest overdue'  },
  { value: 'oldest_overdue:desc', label: 'Recently overdue' },
  { value: 'unit_number:asc',     label: 'Unit A–Z'         },
  { value: 'unit_number:desc',    label: 'Unit Z–A'         },
  { value: 'owner_name:asc',     label: 'Owner A–Z'        },
  { value: 'owner_name:desc',    label: 'Owner Z–A'        },
  { value: 'community_name:asc',    label: 'Community A–Z'       },
  { value: 'community_name:desc',   label: 'Community Z–A'       },
]

// ── Fetch ─────────────────────────────────────────────────────────────
async function fetchArrears() {
  loading.value = true
  try {
    const { search, dateRange, customStart, customEnd, filters, sort } = toolbarState.value
    const params = { _per_page: 15, page: meta.value.current_page }

    if (search?.trim())          params._search     = search.trim()
    if (filters?.community_id)      params.community_id   = filters.community_id
    if (filters?.community_type)    params.community_type = filters.community_type
    if (filters?.ledger)    params.ledger = filters.ledger
    if (sort)                    params._sort       = sort
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    if (dateRange && dateRange !== 'all_time') {
      params._date_range = dateRange
      if (dateRange === 'custom') {
        if (customStart) params._date_range_start = customStart
        if (customEnd)   params._date_range_end   = customEnd
      }
    }

    const { data } = await api.get('/customer-management', { params })
    units.value       = data.data ?? []
    summary.value     = data.summary ?? null
    communities.value     = data.communities ?? []
    ledgers.value = data.ledgers ?? []
    meta.value        = data.meta ?? meta.value
  } catch (e) {
    console.error('Failed to load arrears:', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchArrears)

// Re-fetch when active country changes
watch(() => countryStore.activeCountry, () => {
  meta.value.current_page = 1
  fetchArrears()
})

// ── Pagination ────────────────────────────────────────────────────────
function goToPage(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchArrears()
}

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const current = meta.value.current_page
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)

  const pages = [1]
  if (current > 3) pages.push('...')
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.push(i)
  }
  if (current < total - 2) pages.push('...')
  if (total > 1) pages.push(total)
  return pages
})

// ── Charts ───────────────────────────────────────────────────────────
const fmtK = v => countryStore.formatCurrencyCompact(v)
const fmtTooltip = ctx => {
  const raw = ctx.parsed.x !== undefined && ctx.parsed.x !== null ? ctx.parsed.x : (ctx.parsed.y !== undefined && ctx.parsed.y !== null ? ctx.parsed.y : ctx.parsed)
  return ' ' + countryStore.formatCurrency(Math.abs(raw))
}
const hasChartData = computed(() => summary.value && (summary.value.units_in_arrears ?? 0) > 0)

const CHART_FONT = { family: "'DM Sans', sans-serif" }

// 1 — Arrears by Duration Bucket (vertical bar)
const durationData = computed(() => {
  const d = summary.value?.by_duration ?? {}
  return {
    labels: ['< 30 days', '30–60 days', '60–90 days', '90+ days'],
    datasets: [{
      data: [d.under_30 ?? 0, d.d30_60 ?? 0, d.d60_90 ?? 0, d.d90_plus ?? 0],
      backgroundColor: [AMBER, '#f97316', RED, '#991b1b'],
      borderRadius: 4,
      borderSkipped: false,
    }],
  }
})

const durationOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: fmtTooltip } },
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED } },
    y: { grid: { color: BORDER }, border: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED, callback: fmtK } },
  },
}

// 2 — Top Owner Arrears (horizontal bar — reversed so longest at bottom)
const reversedOwners = computed(() => [...(summary.value?.top_owner_arrears ?? [])].reverse())

const ownerBarData = computed(() => ({
  labels: reversedOwners.value.map(d => `${d.unit_number} – ${d.name.length > 18 ? d.name.slice(0, 18) + '...' : d.name}`),
  datasets: [{
    data: reversedOwners.value.map(d => d.amount),
    backgroundColor: RED,
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const ownerBarOpts = computed(() => ({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  onHover: (event) => { event.native.target.style.cursor = 'pointer' },
  onClick: (_event, elements) => {
    if (!elements.length) return
    const owner = reversedOwners.value[elements[0].index]
    if (owner?.unit_id && owner?.community_id) {
      router.push(`/communities/${owner.community_id}/units/${owner.unit_id}`)
    }
  },
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: fmtTooltip } },
  },
  scales: {
    x: { grid: { color: BORDER }, border: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED, callback: fmtK } },
    y: {
      grid: { display: false },
      ticks: {
        font: { size: 11, ...CHART_FONT },
        color: MUTED,
      },
    },
  },
}))

// 3 — Arrears by Ledger (vertical bar)
const ledgerData = computed(() => {
  const ct = summary.value?.by_ledger ?? {}
  const labels = Object.keys(ct)
  const values = Object.values(ct)
  return {
    labels,
    datasets: [{
      data: values,
      backgroundColor: NAVY,
      borderRadius: 4,
      borderSkipped: false,
    }],
  }
})

const ledgerOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: fmtTooltip } },
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED, maxRotation: 45 } },
    y: { grid: { color: BORDER }, border: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED, callback: fmtK } },
  },
}

// 4 — Arrears by Entity Type (donut)

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
    ctx.fillStyle = MUTED
    ctx.fillText(opts.label, cx, cy + 10)
    ctx.restore()
  },
}

const communityTypeDonutData = computed(() => {
  const raw = summary.value?.by_community_type ?? {}
  const labels = Object.keys(raw).map(k => entityTypeLabel(k))
  const values = Object.values(raw).map(v => parseFloat(v))
  const colors = Object.keys(raw).map(k => ENTITY_TYPE_COLOR[k] ?? MUTED)
  return {
    labels,
    datasets: [{
      data: values,
      backgroundColor: colors,
      borderWidth: 2,
      borderColor: '#fff',
      hoverBorderColor: '#fff',
    }],
  }
})

const communityTypeDonutOpts = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    centerText: { value: formatCurrency(summary.value?.total_overdue_amount), label: 'Total Overdue' },
    legend: {
      position: 'bottom',
      labels: { font: { size: 12, ...CHART_FONT }, color: MUTED, padding: 16, boxWidth: 10, boxHeight: 10, borderRadius: 3 },
    },
    tooltip: { callbacks: { label: fmtTooltip } },
  },
}))

// 5 — Top Occupant Arrears (horizontal bar — reversed so longest at bottom)
const reversedOccupants = computed(() => [...(summary.value?.top_occupant_arrears ?? [])].reverse())

const topOccupantData = computed(() => ({
  labels: reversedOccupants.value.map(d => `${d.unit_number} – ${d.name.length > 18 ? d.name.slice(0, 18) + '...' : d.name}`),
  datasets: [{
    data: reversedOccupants.value.map(d => d.amount),
    backgroundColor: AMBER,
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const topOccupantOpts = computed(() => ({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  onHover: (event) => { event.native.target.style.cursor = 'pointer' },
  onClick: (_event, elements) => {
    if (!elements.length) return
    const occupant = reversedOccupants.value[elements[0].index]
    if (occupant?.unit_id && occupant?.community_id) {
      router.push(`/communities/${occupant.community_id}/units/${occupant.unit_id}`)
    }
  },
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: fmtTooltip } },
  },
  scales: {
    x: { grid: { color: BORDER }, border: { display: false }, ticks: { font: { size: 11, ...CHART_FONT }, color: MUTED, callback: fmtK } },
    y: {
      grid: { display: false },
      ticks: {
        font: { size: 11, ...CHART_FONT },
        color: MUTED,
      },
    },
  },
}))
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading ──────────────────────────────────────────────── -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Customer Management</h1>
      <p class="text-sm text-muted-foreground">Units with overdue invoices across your portfolio</p>
    </div>

    <!-- ── Summary Cards ─────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Skeleton cards -->
      <template v-if="loading && !summary">
        <div v-for="n in 4" :key="n" class="rounded-lg border bg-card shadow-sm p-5 animate-pulse">
          <div class="flex items-center justify-between mb-3">
            <div class="h-3 w-24 bg-muted rounded" />
            <div class="h-4 w-4 bg-muted rounded" />
          </div>
          <div class="h-8 w-28 bg-muted rounded mb-2" />
          <div class="h-3 w-20 bg-muted rounded" />
        </div>
      </template>

      <template v-else>

        <AppStatCard
          label="Total Overdue"
          :value="formatCurrency(summary?.total_overdue_amount)"
          value-class="text-destructive"
          :subtitle="`${summary?.total_overdue_invoices ?? 0} overdue invoices`"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-destructive">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 6v6l4 2"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Units in Arrears"
          :value="summary?.units_in_arrears ?? 0"
          :subtitle="`of ${summary?.total_units ?? 0} total units`"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-warning">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
              <path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Arrears Rate"
          :value="`${summary?.arrears_rate ?? 0}%`"
          :subtitle="`${(summary?.total_units ?? 0) - (summary?.units_in_arrears ?? 0)} units clear`"
          :value-class="(summary?.arrears_rate ?? 0) > 15 ? 'text-destructive' : 'text-foreground'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-muted-foreground">
              <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
              <polyline points="16 7 22 7 22 13"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Overdue Invoices"
          :value="summary?.total_overdue_invoices ?? 0"
          :subtitle="`across ${summary?.communities_affected ?? 0} communities`"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-destructive">
              <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
              <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
              <path d="M12 12v4"/><path d="M12 18h.01"/>
            </svg>
          </template>
        </AppStatCard>

      </template>
    </div>

    <!-- ── Communities with most arrears (capped at 5) ──────────────────── -->
    <div v-if="allCommunityCards.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
      <div
        v-for="community in visibleCommunityCards"
        :key="community.id"
        @click="router.push(`/communities/${community.id}`)"
        class="rounded-lg border bg-card p-4 cursor-pointer hover:border-accent/40 hover:shadow-sm transition-all group relative"
      >
        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-[11px] text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-10">View community</span>
        <div class="flex items-center justify-between mb-1">
          <p class="text-sm font-medium text-foreground truncate">{{ community.name }}</p>
        </div>
        <p class="text-2xl font-bold font-body text-destructive">{{ formatCurrency(community.overdue_total) }}</p>
        <p class="text-xs text-muted-foreground">{{ community.units_count }} {{ community.units_count === 1 ? 'unit' : 'units' }} overdue</p>
      </div>
      <button
        v-if="hasMoreCommunities"
        @click="showAllCommunitiesModal = true"
        class="rounded-lg border border-dashed border-border p-4 flex flex-col items-center justify-center gap-1 cursor-pointer hover:border-accent/40 hover:bg-muted/50 transition-all"
      >
        <span class="text-lg font-bold font-body text-muted-foreground">+{{ allCommunityCards.length - COMMUNITY_CARD_LIMIT }}</span>
        <span class="text-xs text-muted-foreground">View all</span>
      </button>
    </div>

    <!-- ── Toolbar + Table ───────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card shadow-sm">

      <div class="p-4 pb-0">
        <AppTableToolbar
          search-placeholder="Search units, owners, communities..."
          :filter-fields="filterFields"
          :sort-options="sortOptions"
          storage-key="arrears-toolbar"
          date-range-context="Created"
          @update:state="onToolbarUpdate"
        />
      </div>

      <!-- ── Table ─────────────────────────────────────────────────── -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Unit</th>
              <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Community</th>
              <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Owner / Occupant</th>
              <th class="text-right font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Overdue</th>
              <th class="text-center font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Invoices</th>
              <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Since</th>
              <th class="text-center font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wider">Duration</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton rows -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="n" class="border-b border-border animate-pulse">
                <td class="px-4 py-3"><div class="h-3 w-14 bg-muted rounded" /></td>
                <td class="px-4 py-3"><div class="h-3 w-32 bg-muted rounded" /></td>
                <td class="px-4 py-3">
                  <div class="h-3 w-28 bg-muted rounded mb-1" />
                  <div class="h-2.5 w-36 bg-muted rounded" />
                </td>
                <td class="px-4 py-3 text-right"><div class="h-3 w-16 bg-muted rounded ml-auto" /></td>
                <td class="px-4 py-3 text-center"><div class="h-3 w-6 bg-muted rounded mx-auto" /></td>
                <td class="px-4 py-3"><div class="h-3 w-20 bg-muted rounded" /></td>
                <td class="px-4 py-3 text-center"><div class="h-5 w-20 bg-muted rounded-full mx-auto" /></td>
              </tr>
            </template>

            <!-- Real rows -->
            <template v-else>
              <tr
                v-for="unit in units"
                :key="unit.id"
                @click="router.push(`/communities/${unit.community_id}/units/${unit.id}`)"
                class="border-b border-border hover:bg-muted/50 cursor-pointer transition-colors"
              >
                <td class="px-4 py-3">
                  <span class="font-medium text-foreground">{{ unit.unit_number }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <span class="text-foreground">{{ unit.community?.name ?? '—' }}</span>
                    <span
                      v-if="unit.community?.entity_type"
                      :class="['inline-flex items-center rounded-full px-1.5 py-px text-[9px] font-medium border leading-tight', typeBadgeClass(unit.community.entity_type)]"
                    >
                      {{ typeLabel(unit.community.entity_type) }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <template v-if="unit.owner">
                    <p class="font-medium text-foreground">{{ unit.owner.full_name }}</p>
                    <p class="text-xs text-muted-foreground">{{ unit.owner.email }}</p>
                  </template>
                  <span v-else class="text-muted-foreground">—</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="font-semibold text-destructive whitespace-nowrap">
                    {{ formatCurrency(unit.overdue_amount) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center min-w-[24px] h-5 rounded-full bg-destructive/10 text-destructive text-xs font-medium px-1.5">
                    {{ unit.overdue_count }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-muted-foreground text-xs whitespace-nowrap">
                    {{ formatDate(unit.oldest_overdue_date) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border leading-tight whitespace-nowrap', overdueBadgeClass(daysOverdue(unit.oldest_overdue_date))]"
                  >
                    {{ overdueLabel(daysOverdue(unit.oldest_overdue_date)) }}
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <!-- Empty state -->
        <div v-if="!loading && units.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
          <div class="w-14 h-14 rounded-full bg-success/10 flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-success">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <p class="text-sm font-medium text-foreground">No overdue units</p>
          <p class="text-xs text-muted-foreground mt-1">All invoices across your portfolio are up to date</p>
        </div>
      </div>

      <!-- ── Pagination ──────────────────────────────────────────────── -->
      <div v-if="meta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-border">
        <span class="text-xs text-muted-foreground">
          Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total
        </span>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(meta.current_page - 1)"
            :disabled="meta.current_page <= 1"
            class="inline-flex items-center justify-center h-7 w-7 rounded text-xs border border-border transition-colors disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </button>
          <template v-for="page in visiblePages" :key="page">
            <span v-if="page === '...'" class="px-1 text-xs text-muted-foreground">...</span>
            <button
              v-else
              @click="goToPage(page)"
              :class="[
                'inline-flex items-center justify-center h-7 w-7 rounded text-xs font-medium transition-colors',
                page === meta.current_page
                  ? 'bg-primary text-primary-foreground'
                  : 'border border-border hover:bg-muted text-foreground',
              ]"
            >
              {{ page }}
            </button>
          </template>
          <button
            @click="goToPage(meta.current_page + 1)"
            :disabled="meta.current_page >= meta.last_page"
            class="inline-flex items-center justify-center h-7 w-7 rounded text-xs border border-border transition-colors disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </button>
        </div>
      </div>

    </div>

    <!-- ── High Risk Monitoring ────────────────────────────────────────── -->
    <HighRiskSection />

    <!-- ── Charts ──────────────────────────────────────────────────────── -->
    <!-- Skeleton while loading -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="n in 4" :key="n" class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-36 bg-muted rounded animate-pulse" />
          <div class="h-3 w-48 bg-muted rounded animate-pulse mt-1.5" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-64 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <template v-else-if="hasChartData">

      <!-- Row 1: Duration + Community Type donut -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Arrears by Duration -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Arrears by Duration</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Outstanding amounts grouped by how long overdue</p>
          </div>
          <div class="px-6 pb-6">
            <div class="h-[260px]">
              <Bar :data="durationData" :options="durationOpts" />
            </div>
          </div>
        </div>

        <!-- Arrears by Community Type -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Arrears by Community Type</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Overdue distribution across property types</p>
          </div>
          <div class="px-6 pb-6">
            <div class="h-[260px]">
              <Doughnut :data="communityTypeDonutData" :options="communityTypeDonutOpts" :plugins="[centerTextPlugin]" />
            </div>
          </div>
        </div>

      </div>

      <!-- Row 2: By Community + Top Debtors -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Top Owner Arrears -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Top Owner Arrears</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Owners with highest outstanding rent balances</p>
          </div>
          <div class="px-6 pb-6">
            <div class="h-[260px]">
              <Bar :data="ownerBarData" :options="ownerBarOpts" />
            </div>
          </div>
        </div>

        <!-- Top Occupant Arrears -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Top Occupant Arrears</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Occupants with highest outstanding rent balances</p>
          </div>
          <div class="px-6 pb-6">
            <div class="h-[260px]">
              <Bar :data="topOccupantData" :options="topOccupantOpts" />
            </div>
          </div>
        </div>

      </div>

      <!-- Row 3: By Ledger (full width) -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Arrears by Ledger</h3>
          <p class="text-xs text-muted-foreground mt-0.5">Overdue amounts broken down by billing category</p>
        </div>
        <div class="px-6 pb-6">
          <div class="h-[280px]">
            <Bar :data="ledgerData" :options="ledgerOpts" />
          </div>
        </div>
      </div>

    </template>

    <!-- ── All Communities Modal ──────────────────────────────────────────── -->
    <AppModal :show="showAllCommunitiesModal" title="Arrears by Community" size="md" @close="showAllCommunitiesModal = false">
      <div class="space-y-2 -mx-2">
        <div
          v-for="community in allCommunityCards"
          :key="community.id"
          @click="showAllCommunitiesModal = false; router.push(`/communities/${community.id}`)"
          class="flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 cursor-pointer transition-colors"
        >
          <div class="min-w-0">
            <p class="text-sm font-medium text-foreground truncate">{{ community.name }}</p>
            <p class="text-xs text-muted-foreground">{{ community.units_count }} {{ community.units_count === 1 ? 'unit' : 'units' }} overdue</p>
          </div>
          <p class="text-lg font-bold font-body text-destructive whitespace-nowrap ml-4">{{ formatCurrency(community.overdue_total) }}</p>
        </div>
      </div>
    </AppModal>

  </div>
</template>
