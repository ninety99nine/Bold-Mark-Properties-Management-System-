<script>
// Register Chart.js building blocks once (module side-effect), following the
// same pattern as CommunityDetailPage.vue.
import {
  Chart as ChartJS,
  ArcElement,
  LineElement,
  PointElement,
  Filler,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
} from 'chart.js'
ChartJS.register(ArcElement, LineElement, PointElement, Filler, Tooltip, Legend, CategoryScale, LinearScale)
</script>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Doughnut, Line } from 'vue-chartjs'
import AppButton from '@/components/common/AppButton.vue'
import AddCommunityModal from '@/components/communities/AddCommunityModal.vue'
import { entityTypeLabel, entityTypeBadgeClass } from '@/utils/communityEntityType'
import api from '@/composables/useApi.js'
import { useCountryStore } from '@/stores/country'

const router = useRouter()
const countryStore = useCountryStore()

// ── Community row helpers (mirror the /communities card layout) ────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  return countryStore.formatCurrency(amount)
}

// Left-hand badge: community code, else initials derived from the name.
function communityBadge(community) {
  if (community?.code) return community.code.toUpperCase()
  const name = community?.name
  if (!name) return '—'
  const words = name.trim().split(/\s+/).filter(Boolean)
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return (words[0][0] + words[words.length - 1][0]).toUpperCase()
}

// Financial year end is a month (1–12); display as DD/MM (last day of that month).
const YEAR_END_LAST_DAY = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
function formatYearEnd(month) {
  if (!month || month < 1 || month > 12) return '—'
  const dd = String(YEAR_END_LAST_DAY[month - 1]).padStart(2, '0')
  const mm = String(month).padStart(2, '0')
  return `${dd}/${mm}`
}

// ── Add Community modal (opened in place, no routing) ─────────────────
const showAddCommunity = ref(false)

function onCommunityCreated(community) {
  if (community?.id) {
    // Land on the new community's General settings (WeConnectU flow).
    router.push(`/communities/${community.id}?tab=settings&login=1`)
  } else {
    fetchDashboard()
  }
}

// ── State ─────────────────────────────────────────────────────────────
const loading = ref(true)
const summary = ref(null)
const communitiesOverview = ref([])
const debtTrend = ref(null)
const compliance = ref(null)
const tasks = ref(null)

// ── Compliance year pills (WeConnectU-style) ──────────────────────────
const currentYear = new Date().getFullYear()
const complianceYears = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1]
const selectedComplianceYear = ref(currentYear)
const complianceLoading = ref(false)

// ── Fetch ─────────────────────────────────────────────────────────────
async function fetchDashboard() {
  try {
    loading.value = true
    const params = { year: selectedComplianceYear.value }
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    const { data } = await api.get('/dashboard', { params })
    summary.value = data.summary
    communitiesOverview.value = data.communities_overview ?? []
    debtTrend.value = data.debt_trend ?? null
    compliance.value = data.compliance ?? null
    tasks.value = data.tasks ?? null
  } catch (e) {
    console.error('Failed to load dashboard:', e)
  } finally {
    loading.value = false
  }
}

// Re-fetch just the compliance breakdown when a year pill is selected.
async function fetchCompliance() {
  try {
    complianceLoading.value = true
    const params = { year: selectedComplianceYear.value }
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    const { data } = await api.get('/dashboard', { params })
    compliance.value = data.compliance ?? null
  } catch (e) {
    console.error('Failed to load compliance:', e)
  } finally {
    complianceLoading.value = false
  }
}

function selectComplianceYear(year) {
  if (year === selectedComplianceYear.value) return
  selectedComplianceYear.value = year
  fetchCompliance()
}

onMounted(fetchDashboard)

// Re-fetch when country changes
watch(() => countryStore.activeCountry, (newVal, oldVal) => {
  if (oldVal !== null && newVal !== oldVal) fetchDashboard()
})

// ── Formatters ────────────────────────────────────────────────────────
function formatRate(rate) {
  return rate != null ? `${rate}%` : '—'
}

// entity-type label comes from the shared helper (imported below)

// ── Finances panel rows (WeConnectU "Finances" list) ──────────────────
const financeRows = computed(() => [
  {
    label: 'Collected This Month',
    count: summary.value?.payments_this_month_count ?? 0,
    value: formatCurrency(summary.value?.collected_this_month),
    valueClass: 'text-foreground',
    route: '/cashbook',
  },
  {
    label: 'Total Outstanding',
    count: summary.value?.unpaid_invoices_count ?? 0,
    value: formatCurrency(summary.value?.total_outstanding),
    valueClass: 'text-destructive',
    route: '/customer-management',
  },
  {
    label: 'Occupied Units',
    count: summary.value?.occupied_units ?? 0,
    value: formatRate(summary.value?.occupancy_rate),
    valueClass: 'text-foreground',
    route: '/vacancies',
  },
  {
    label: 'Communities',
    count: summary.value?.total_communities ?? 0,
    value: `${summary.value?.total_units ?? 0} units`,
    valueClass: 'text-muted-foreground',
    route: '/communities',
  },
])

// ── Debt panel (area chart) ───────────────────────────────────────────
const debtPercentChange = computed(() => debtTrend.value?.percent_change ?? 0)
// Rising debt is a negative signal, so an increase is shown in red.
const debtRising = computed(() => debtPercentChange.value >= 0)

const debtChartData = computed(() => ({
  labels: (debtTrend.value?.series ?? []).map(p => p.label),
  datasets: [{
    data: (debtTrend.value?.series ?? []).map(p => p.value),
    borderColor: '#DC2626',
    backgroundColor: 'rgba(220, 38, 38, 0.08)',
    fill: true,
    tension: 0.4,
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 4,
    pointHoverBackgroundColor: '#DC2626',
  }],
}))

const debtChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: ctx => ` ${formatCurrency(ctx.parsed.y)}` },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11, family: 'DM Sans' }, color: '#717B99' },
    },
    y: { display: false, beginAtZero: true },
  },
}))

// ── Compliance panel (doughnut) ───────────────────────────────────────
const complianceSegments = computed(() => {
  const c = compliance.value ?? {}
  return [
    { label: 'Planned',       value: c.planned ?? 0,       color: '#4A90D9' },
    { label: 'Compliant',     value: c.compliant ?? 0,     color: '#34C77B' },
    { label: 'Unplanned',     value: c.unplanned ?? 0,     color: '#F5B93B' },
    { label: 'Non Compliant', value: c.non_compliant ?? 0, color: '#E86A80' },
  ]
})
const complianceTotal = computed(() => complianceSegments.value.reduce((s, x) => s + x.value, 0))

const complianceChartData = computed(() => {
  // Ghost ring when there is nothing to show yet.
  if (complianceTotal.value === 0) {
    return { labels: ['No data'], datasets: [{ data: [1], backgroundColor: ['#E6E8F0'], borderWidth: 0 }] }
  }
  return {
    labels: complianceSegments.value.map(s => s.label),
    datasets: [{
      data: complianceSegments.value.map(s => s.value),
      backgroundColor: complianceSegments.value.map(s => s.color),
      borderColor: '#ffffff',
      borderWidth: 3,
      hoverOffset: 6,
    }],
  }
})

const complianceChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: complianceTotal.value === 0
      ? { display: false }
      : {
          position: 'bottom',
          labels: {
            padding: 14,
            font: { size: 11, family: 'DM Sans' },
            boxWidth: 9,
            boxHeight: 9,
            usePointStyle: true,
            color: '#1E2740',
          },
        },
    tooltip: {
      enabled: complianceTotal.value > 0,
      displayColors: false,
      padding: 10,
      cornerRadius: 8,
      bodyFont: { size: 13, weight: '600', family: 'DM Sans' },
      backgroundColor: ctx => complianceSegments.value[ctx.tooltip?.dataPoints?.[0]?.dataIndex]?.color ?? '#1E2740',
      callbacks: {
        title: () => '',
        label: ctx => ` ${ctx.label}: ${ctx.parsed.toFixed(1)}`,
      },
    },
  },
}))

const complianceCenterTextPlugin = computed(() => ({
  id: 'complianceCenterText',
  beforeDraw(chart) {
    const { ctx, chartArea: { top, bottom, left, right } } = chart
    const cx = (left + right) / 2
    const cy = (top + bottom) / 2
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillStyle = '#1E2740'
    ctx.font = 'bold 1.75rem DM Sans, sans-serif'
    ctx.fillText(`${compliance.value?.percent_compliant ?? 0}%`, cx, cy - 16)
    ctx.fillStyle = '#3A4256'
    ctx.font = '0.85rem DM Sans, sans-serif'
    ctx.fillText('Compliant', cx, cy + 8)
    ctx.fillStyle = '#8A93A8'
    ctx.font = '0.72rem DM Sans, sans-serif'
    ctx.fillText('Year to Date', cx, cy + 26)
    ctx.restore()
  },
}))

// ── Communities table ─────────────────────────────────────────────────
const communitySearch = ref('')
const filteredCommunities = computed(() => {
  const q = communitySearch.value.trim().toLowerCase()
  if (!q) return communitiesOverview.value
  return communitiesOverview.value.filter(c => c.name.toLowerCase().includes(q))
})
const displayedCommunities = computed(() => filteredCommunities.value.slice(0, 8))

// ── Tasks matrix (WeConnectU "Tasks" table) ───────────────────────────
const taskMonths = computed(() => tasks.value?.months ?? [])
const taskRows = computed(() => {
  const t = tasks.value
  if (!t) return []
  return [
    { label: 'Total',                  values: t.total ?? [],          kind: 'count' },
    { label: 'Active/Overdue',         values: t.active_overdue ?? [], kind: 'count' },
    { label: 'Complete',               values: t.complete ?? [],       kind: 'count' },
    { label: 'Completion Strike Rate', values: t.strike_rate ?? [],    kind: 'rate' },
  ]
})
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading ──────────────────────────────────────────────── -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Portfolio overview for Bold Mark Properties</p>
    </div>

    <!-- ── Top row: Finances · Debt · Compliance (WeConnectU layout) ──── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Finances -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 py-4 border-b border-border">
          <h3 class="tracking-tight font-body font-semibold text-lg">Finances</h3>
        </div>
        <div class="p-3">
          <template v-if="loading">
            <div v-for="n in 4" :key="n" class="flex items-center justify-between px-3 py-3 animate-pulse">
              <div class="flex items-center gap-3">
                <div class="h-6 w-9 bg-muted rounded" />
                <div class="h-3 w-28 bg-muted rounded" />
              </div>
              <div class="h-3 w-20 bg-muted rounded" />
            </div>
          </template>
          <template v-else>
            <button
              v-for="row in financeRows"
              :key="row.label"
              @click="router.push(row.route)"
              class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex items-center justify-center min-w-9 h-6 px-1.5 rounded bg-muted text-xs font-semibold text-muted-foreground">
                  {{ row.count }}
                </span>
                <span class="text-sm font-medium text-primary truncate">{{ row.label }}</span>
              </div>
              <span :class="['text-sm font-semibold whitespace-nowrap ml-3', row.valueClass]">{{ row.value }}</span>
            </button>
          </template>
        </div>
      </div>

      <!-- Debt -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between gap-3">
          <h3 class="tracking-tight font-body font-semibold text-lg">Debt</h3>
          <div v-if="!loading" class="flex items-center gap-2">
            <span class="text-base font-bold text-foreground tabular-nums">{{ formatCurrency(debtTrend?.total) }}</span>
            <span
              :class="['inline-flex items-center gap-0.5 text-xs font-semibold', debtRising ? 'text-destructive' : 'text-success']"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <template v-if="debtRising"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></template>
                <template v-else><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></template>
              </svg>
              {{ Math.abs(debtPercentChange) }}%
            </span>
            <button class="ml-1 text-muted-foreground hover:text-accent transition-colors" title="Open Customer Management" @click="router.push('/customer-management')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/><path d="m21 3-9 9"/><path d="M15 3h6v6"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6 pt-4">
          <div v-if="loading" class="h-44 bg-muted/50 rounded animate-pulse" />
          <div v-else class="h-44">
            <Line :data="debtChartData" :options="debtChartOptions" />
          </div>
        </div>
      </div>

      <!-- Compliance -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
          <h3 class="tracking-tight font-body font-semibold text-lg">Compliance</h3>
          <button class="text-muted-foreground hover:text-accent transition-colors" title="Open Planner &amp; Compliance" @click="router.push('/compliance')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/><path d="m21 3-9 9"/><path d="M15 3h6v6"/>
            </svg>
          </button>
        </div>
        <div class="p-6 pt-4">
          <div v-if="loading || complianceLoading" class="h-56 flex items-center justify-center">
            <div class="w-36 h-36 rounded-full border-[14px] border-muted animate-pulse" />
          </div>
          <div v-else :class="complianceTotal === 0 ? 'h-44' : 'h-56'" class="relative">
            <Doughnut :data="complianceChartData" :options="complianceChartOptions" :plugins="[complianceCenterTextPlugin]" />
          </div>
          <p v-if="!loading && !complianceLoading && complianceTotal === 0" class="text-center text-xs text-muted-foreground/70 mt-3">
            No compliance items yet
          </p>

          <!-- Year pills -->
          <div class="mt-4 flex items-center justify-center gap-2">
            <button
              v-for="year in complianceYears"
              :key="year"
              type="button"
              :disabled="complianceLoading"
              @click="selectComplianceYear(year)"
              :class="[
                'px-4 py-1.5 rounded-full text-sm font-medium transition-colors disabled:opacity-60',
                year === selectedComplianceYear
                  ? 'bg-[#3B7C8C] text-white shadow-sm'
                  : 'bg-muted text-muted-foreground hover:bg-muted/70 hover:text-foreground',
              ]"
            >
              {{ year }}
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Tasks matrix (WeConnectU layout) ──────────────────────────── -->
    <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <!-- Skeleton -->
      <div v-if="loading" class="p-6 space-y-3 animate-pulse">
        <div v-for="n in 4" :key="n" class="flex items-center justify-between">
          <div class="h-3 w-40 bg-muted rounded" />
          <div class="h-3 w-64 bg-muted rounded" />
        </div>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
          <!-- Header: "Tasks" + month columns -->
          <thead>
            <tr class="border-b border-border">
              <th class="text-left px-6 py-4">
                <span class="font-body font-semibold text-lg text-accent">Tasks</span>
              </th>
              <th
                v-for="month in taskMonths"
                :key="month"
                class="px-3 py-4 text-right text-xs font-medium text-muted-foreground tabular-nums"
              >
                {{ month }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="row in taskRows" :key="row.label" class="hover:bg-muted/30 transition-colors">
              <td class="px-6 py-3.5 text-left font-medium text-foreground whitespace-nowrap">
                {{ row.label }}
              </td>
              <td
                v-for="(value, i) in row.values"
                :key="i"
                class="px-3 py-3.5 text-right tabular-nums"
              >
                <template v-if="row.kind === 'rate'">
                  <span class="inline-flex items-center justify-end gap-1 font-semibold text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="#e8a040" stroke="#e8a040" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/>
                    </svg>
                    {{ value }}%
                  </span>
                </template>
                <span v-else class="font-semibold text-primary">{{ value }}</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty state: no months resolved -->
        <div v-if="!taskMonths.length" class="flex flex-col items-center justify-center py-12 text-center">
          <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
              <path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>
            </svg>
          </div>
          <p class="text-sm font-medium text-muted-foreground">No tasks yet</p>
        </div>
      </div>
    </div>

    <!-- ── Communities table ─────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <h3 class="tracking-tight font-body font-semibold text-lg">Communities</h3>
          <div class="flex items-center gap-2">
            <div class="relative">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
              </svg>
              <input
                v-model="communitySearch"
                type="text"
                placeholder="Search…"
                class="h-8 w-36 sm:w-44 pl-8 pr-3 text-sm bg-white border border-border rounded outline-none focus:border-accent transition-colors"
              />
            </div>
            <AppButton variant="primary" size="sm" @click="showAddCommunity = true">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
              </svg>
              Add Community
            </AppButton>
          </div>
        </div>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="p-6 space-y-3 animate-pulse">
        <div v-for="n in 4" :key="n" class="flex items-center justify-between">
          <div class="h-3 w-48 bg-muted rounded" />
          <div class="h-3 w-40 bg-muted rounded" />
        </div>
      </div>

      <!-- Community rows (mirrors the /communities card layout) -->
      <div v-else-if="displayedCommunities.length" class="divide-y divide-border">
        <div
          v-for="community in displayedCommunities"
          :key="community.id"
          @click="router.push(`/communities/${community.id}?login=1`)"
          class="flex items-center gap-4 px-4 sm:px-5 py-4 hover:bg-muted/40 cursor-pointer transition-colors"
        >
          <!-- Code badge -->
          <div class="shrink-0 w-11 h-11 rounded-lg bg-muted flex items-center justify-center">
            <span class="text-xs font-bold tracking-wide text-muted-foreground">{{ communityBadge(community) }}</span>
          </div>

          <!-- Name + address + type -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-body font-semibold text-foreground truncate">{{ community.name }}</p>
              <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1 leading-tight whitespace-nowrap', entityTypeBadgeClass(community.entity_type)]">
                {{ entityTypeLabel(community.entity_type) }}
              </span>
            </div>
            <div class="flex items-center gap-1.5 mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 text-muted-foreground shrink-0">
                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
              <p class="text-xs text-muted-foreground truncate">{{ community.address || 'No address on record' }}</p>
            </div>
            <!-- Compact units count (mobile only) -->
            <p class="md:hidden text-xs text-muted-foreground mt-1">
              <span class="font-semibold text-foreground">{{ community.units_count }}</span> units ·
              <span class="font-semibold text-foreground">{{ community.occupied_units_count }}</span> occupied ·
              <span class="font-semibold text-foreground">{{ community.vacant_count }}</span> vacant
            </p>
          </div>

          <!-- Metadata columns -->
          <div class="hidden md:flex items-center gap-6 lg:gap-9 shrink-0">
            <div class="text-right w-12">
              <p class="text-[11px] text-muted-foreground">Units</p>
              <p class="font-semibold text-foreground tabular-nums">{{ community.units_count }}</p>
            </div>
            <div class="text-right w-16">
              <p class="text-[11px] text-muted-foreground">Occupied</p>
              <p class="font-semibold text-foreground tabular-nums">{{ community.occupied_units_count }}</p>
            </div>
            <div class="text-right w-12">
              <p class="text-[11px] text-muted-foreground">Vacant</p>
              <p class="font-semibold text-muted-foreground tabular-nums">{{ community.vacant_count }}</p>
            </div>
            <div class="text-right w-14">
              <p class="text-[11px] text-muted-foreground">Year end</p>
              <p class="font-semibold text-foreground tabular-nums">{{ formatYearEnd(community.financial_year_end_month) }}</p>
            </div>
            <div class="text-right w-28">
              <p class="text-[11px] text-muted-foreground">Monthly Revenue</p>
              <p class="font-semibold text-foreground tabular-nums">{{ formatCurrency(community.monthly_revenue ?? 0) }}</p>
            </div>
          </div>

          <!-- Login icon -->
          <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded text-muted-foreground hover:text-accent hover:bg-accent/10 transition-colors" title="Login">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>
            </svg>
          </span>
        </div>
      </div>

      <!-- Empty -->
      <div v-else class="flex flex-col items-center justify-center py-12 text-center">
        <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <p class="text-sm font-medium text-muted-foreground">{{ communitySearch ? 'No communities match your search' : 'No communities yet' }}</p>
        <p class="text-xs text-muted-foreground/70 mt-1">{{ communitySearch ? 'Try a different search term' : 'Add your first community to get started' }}</p>
      </div>
    </div>

    <!-- ── Add Community modal (opened in place) ─────────────────────── -->
    <AddCommunityModal :show="showAddCommunity" @close="showAddCommunity = false" @created="onCommunityCreated" />

  </div>
</template>
