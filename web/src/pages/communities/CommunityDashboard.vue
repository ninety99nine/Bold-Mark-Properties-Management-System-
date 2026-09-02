<script setup>
/**
 * Community Dashboard — an exact-parity clone of WeConnectU's `/app` dashboard
 * (WeConnectU layout/structure, Bold Mark navy/gold styling).
 *
 * Two columns:
 *  - LEFT  : Planner & Compliance (FY tabs + item cards + Add Item)
 *  - RIGHT : Bank Balance / Investments / Outstanding Debt (gold trend) +
 *            Open Tasks / Pending Transfers / Warnings·Penalties·Fines cards +
 *            Pending Transfers & My Tasks panels.
 *
 * FY tabs scope ONLY the planner; the right column is live current state.
 * Source of truth: ~/Downloads/boldmark-plans/community-dashboard-plan.md
 */
import { ref, computed, reactive, onMounted } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS, LineElement, PointElement, Filler, Tooltip, CategoryScale, LinearScale,
} from 'chart.js'

// Draws a WeConnectU-style vertical dashed crosshair through the hovered point.
const crosshairPlugin = {
  id: 'debtCrosshair',
  afterDraw(chart) {
    const active = chart.tooltip?.getActiveElements?.() ?? []
    if (!active.length) return
    const { ctx, chartArea } = chart
    const x = active[0].element.x
    ctx.save()
    ctx.beginPath()
    ctx.setLineDash([4, 4])
    ctx.moveTo(x, chartArea.top)
    ctx.lineTo(x, chartArea.bottom)
    ctx.lineWidth = 1
    ctx.strokeStyle = 'rgba(26, 39, 68, 0.35)'
    ctx.stroke()
    ctx.restore()
  },
}
import api from '@/composables/useApi'
import { useCountryStore } from '@/stores/country'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'

ChartJS.register(LineElement, PointElement, Filler, Tooltip, CategoryScale, LinearScale, crosshairPlugin)

const props = defineProps({
  communityId: { type: String, required: true },
})

const countryStore = useCountryStore()
const { success, error: toastError } = useToast()

const loading = ref(true)
const board   = ref(null)
const selectedYear = ref(null)

// ── Data loading ────────────────────────────────────────────────────────────
async function load(year = null) {
  loading.value = true
  try {
    const res = await api.get(`/communities/${props.communityId}/dashboard`, {
      params: year ? { financial_year: year } : {},
    })
    board.value = res.data
    selectedYear.value = res.data.selected_year
  } catch (e) {
    toastError('Could not load the dashboard.')
  } finally {
    loading.value = false
  }
}
onMounted(() => load())

// Switching a financial year re-fetches ONLY the planner (right column is
// year-independent, so we simply re-request with the new year).
function selectYear(y) {
  if (y === selectedYear.value) return
  selectedYear.value = y
  load(y)
}

// ── Derived ─────────────────────────────────────────────────────────────────
const financialYears = computed(() => board.value?.financial_years ?? [])
const planner  = computed(() => board.value?.planner ?? [])
const finance  = computed(() => board.value?.finance ?? null)
const counts   = computed(() => board.value?.counts ?? {})
const pendingTransfers = computed(() => board.value?.pending_transfers ?? [])
const myTasks  = computed(() => board.value?.my_tasks ?? [])

const displayYear = (y) => (y ? String(y).replace('-', '/') : '')

const fmt = (v) => countryStore.formatCurrency(Number(v ?? 0))

// ── Planner status pills (WeConnectU: Confirm Date / Planned / Confirmed / Done) ──
const PILLS = {
  confirm_date: { label: 'Confirm Date', class: 'bg-muted text-muted-foreground' },
  planned:      { label: 'Planned',      class: 'bg-blue-100 text-blue-700' },
  confirmed:    { label: 'Confirmed',    class: 'bg-sky-100 text-sky-700' },
  done:         { label: 'Done',         class: 'bg-emerald-100 text-emerald-700' },
  overdue:      { label: 'Overdue',      class: 'bg-red-100 text-red-700' },
}
const pill = (key) => PILLS[key] ?? PILLS.planned

// ── Debt trend chart (gold area, interactive — matches WeConnectU) ────────────
// Snapshot dates power the tooltip title ("31 Jul 2026"); a scriptable gradient
// gives the fill depth without needing a chart plugin.
const trendDates = computed(() => (finance.value?.debt_trend?.series ?? []).map((p) => p.date))

const fmtTrendDate = (iso) => {
  if (!iso) return ''
  const d = new Date(`${iso}T00:00:00`)
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

const trendData = computed(() => {
  const series = finance.value?.debt_trend?.series ?? []
  return {
    labels: series.map((p) => p.label),
    datasets: [{
      data: series.map((p) => p.value),
      borderColor: '#D89B4B',
      backgroundColor: (ctx) => {
        const { chart } = ctx
        const { ctx: c, chartArea } = chart
        if (!chartArea) return 'rgba(216, 155, 75, 0.10)'
        const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
        g.addColorStop(0, 'rgba(216, 155, 75, 0.28)')
        g.addColorStop(1, 'rgba(216, 155, 75, 0.02)')
        return g
      },
      fill: true,
      tension: 0.4,
      borderWidth: 3,
      pointRadius: 0,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: '#D89B4B',
      pointHoverBorderColor: '#ffffff',
      pointHoverBorderWidth: 2,
    }],
  }
})

const trendOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  layout: { padding: { top: 8, bottom: 4 } },
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#ffffff',
      titleColor: '#1a2744',
      bodyColor: '#1a2744',
      borderColor: 'rgba(26, 39, 68, 0.12)',
      borderWidth: 1,
      padding: 10,
      displayColors: false,
      titleFont: { weight: '600' },
      callbacks: {
        title: (items) => fmtTrendDate(trendDates.value[items[0]?.dataIndex]),
        label: (ctx) => `Debt: ${countryStore.formatCurrency(ctx.parsed.y)}`,
      },
    },
  },
  scales: {
    x: {
      display: true,
      grid: { display: false },
      border: { display: false },
      ticks: { color: '#94a3b8', font: { size: 11 } },
    },
    y: { display: false, beginAtZero: true },
  },
}))

// ── Add Item (Planner & Compliance) ───────────────────────────────────────────
const addMenuOpen = ref(false)
const addModalOpen = ref(false)
const saving = ref(false)
const form = reactive({ name: '', category: 'Governance', due_date: '' })
const categoryOptions = ['Governance', 'Financial', 'Insurance', 'Legal', 'Maintenance']
  .map((c) => ({ label: c, value: c }))

const canAddItem = computed(() => !!board.value?.selected_checklist_id)

function openAdd(kind) {
  addMenuOpen.value = false
  // Meeting types (Director/Trustee, SGM) will open the full Meeting module once
  // built; for now they create a planner item pre-named with the meeting type.
  form.name = kind === 'director_trustee' ? 'Director/Trustee Meeting'
    : kind === 'sgm' ? 'Special General Meeting'
    : ''
  form.category = kind === 'other' ? 'Governance' : 'Governance'
  form.due_date = ''
  addModalOpen.value = true
}

async function createItem() {
  if (!board.value?.selected_checklist_id || !form.name.trim()) return
  saving.value = true
  try {
    await api.post(`/compliance/checklists/${board.value.selected_checklist_id}/items`, {
      name: form.name.trim(),
      category: form.category,
      priority: 'medium',
      due_date: form.due_date || null,
    })
    addModalOpen.value = false
    success('Planner item added.')
    await load(selectedYear.value)
  } catch (e) {
    toastError('Could not add the item.')
  } finally {
    saving.value = false
  }
}

async function deleteItem(item) {
  if (!board.value?.selected_checklist_id) return
  if (!window.confirm(`Delete “${item.title}”?`)) return
  try {
    await api.delete(`/compliance/checklists/${board.value.selected_checklist_id}/items/${item.id}`)
    success('Planner item removed.')
    await load(selectedYear.value)
  } catch (e) {
    toastError('Could not delete the item.')
  }
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- ══════════════ LEFT: Planner & Compliance ══════════════ -->
    <div class="lg:col-span-6 space-y-3">

      <!-- Financial-year tabs -->
      <div class="flex flex-wrap gap-3">
        <template v-if="loading && !financialYears.length">
          <div v-for="n in 4" :key="n" class="h-11 w-28 rounded-full bg-muted animate-pulse" />
        </template>
        <button
          v-for="y in financialYears" :key="y" type="button" @click="selectYear(y)"
          :class="[
            'rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition-colors',
            y === selectedYear ? 'bg-navy text-white' : 'bg-card text-navy hover:bg-muted',
          ]"
        >{{ displayYear(y) }}</button>
      </div>

      <!-- Section header -->
      <div class="flex items-center justify-between gap-3 flex-wrap pt-3">
        <div class="flex items-center gap-4">
          <span class="rounded-full bg-muted px-6 py-2.5 text-sm font-semibold text-muted-foreground">
            {{ displayYear(selectedYear) || '—' }}
          </span>
          <h2 class="font-body text-2xl font-semibold text-slate-600">Planner and Compliance</h2>
        </div>

        <div class="relative">
          <AppButton
            variant="primary" size="sm" :disabled="!canAddItem"
            :title="canAddItem ? undefined : 'Add a compliance year first'"
            @click="addMenuOpen = !addMenuOpen"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add Item
          </AppButton>
          <div
            v-if="addMenuOpen"
            class="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-border bg-card shadow-lg py-1"
          >
            <button class="block w-full text-left px-4 py-2 text-sm text-navy hover:bg-muted" @click="openAdd('director_trustee')">Director/Trustee Meeting</button>
            <button class="block w-full text-left px-4 py-2 text-sm text-navy hover:bg-muted" @click="openAdd('sgm')">Special General Meeting</button>
            <button class="block w-full text-left px-4 py-2 text-sm text-navy hover:bg-muted" @click="openAdd('other')">Other</button>
          </div>
        </div>
      </div>

      <!-- Planner cards -->
      <div class="space-y-2">
        <template v-if="loading">
          <div v-for="n in 5" :key="n" class="rounded-xl bg-card px-4 py-4 shadow-sm">
            <div class="h-4 w-2/3 bg-muted rounded animate-pulse" />
            <div class="mt-3 h-3 w-24 bg-muted rounded animate-pulse" />
          </div>
        </template>

        <div v-else-if="!planner.length" class="rounded-xl border border-dashed border-border bg-card px-4 py-10 text-center">
          <p class="text-sm font-medium text-muted-foreground">No planner items for this year.</p>
          <p class="text-xs text-muted-foreground/70 mt-1">Use “Add Item” to plan compliance activities.</p>
        </div>

        <div
          v-for="item in planner" :key="item.id"
          class="group flex items-start gap-4 rounded-lg bg-card px-4 py-2.5 shadow-sm cursor-pointer transition-colors hover:bg-muted/40"
        >
          <!-- Status pill + date -->
          <div class="w-28 shrink-0">
            <span :class="['inline-block rounded-full px-3 py-1 text-xs font-semibold', pill(item.pill).class]">
              {{ pill(item.pill).label }}
            </span>
            <p v-if="item.due_date" class="mt-1.5 text-xs text-muted-foreground">{{ item.due_date }}</p>
          </div>

          <!-- Title + checklist progress -->
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground">{{ item.title }}</p>
            <div v-if="item.checklist" class="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              {{ item.checklist.completed }}/{{ item.checklist.total }}
            </div>
            <div v-if="item.has_attachment" class="mt-2 inline-flex items-center gap-1 text-sm text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </div>
          </div>

          <!-- Delete -->
          <button
            type="button" @click="deleteItem(item)" title="Delete item"
            class="shrink-0 text-muted-foreground transition-colors hover:text-red-600"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════════ RIGHT: financial + operational ══════════════ -->
    <div class="lg:col-span-6 space-y-4">

      <!-- Financial summary card -->
      <div class="rounded-lg bg-card p-6 shadow-sm">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <span class="inline-block rounded-full bg-muted px-4 py-1.5 text-sm text-muted-foreground">
              Bank Balance ({{ finance?.bank_balance?.count ?? 0 }})
            </span>
            <p class="mt-3 font-body font-bold tabular-nums text-3xl md:text-4xl text-navy whitespace-nowrap">
              {{ fmt(finance?.bank_balance?.value) }}
            </p>
            <p class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
              {{ finance?.bank_balance?.as_at ?? '--' }}
            </p>
          </div>
          <div class="text-right">
            <span class="inline-block rounded-full bg-muted px-4 py-1.5 text-sm text-muted-foreground">
              Investments ({{ finance?.investments?.count ?? 0 }})
            </span>
            <p class="mt-3 font-body font-bold tabular-nums text-3xl md:text-4xl text-navy whitespace-nowrap">
              {{ fmt(finance?.investments?.value) }}
            </p>
            <p class="mt-1 flex items-center justify-end gap-1.5 text-sm text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
              {{ finance?.investments?.as_at ?? '--' }}
            </p>
          </div>
        </div>

        <div class="mt-5">
          <span class="inline-block rounded-full bg-muted px-4 py-1.5 text-sm text-muted-foreground">Outstanding Debt</span>
          <div class="mt-3 h-40">
            <Line v-if="finance" :data="trendData" :options="trendOptions" />
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="flex gap-4">
        <div class="flex-1 rounded-lg bg-card p-4 text-center border-l-4 border-l-blue-500 shadow-sm">
          <p class="text-3xl font-bold font-body text-foreground">{{ counts.open_tasks ?? 0 }}</p>
          <p class="text-sm text-muted-foreground mt-1">Open Tasks</p>
        </div>
        <div class="flex-1 rounded-lg bg-card p-4 text-center border-l-4 border-l-purple-500 shadow-sm">
          <p class="text-3xl font-bold font-body text-foreground">{{ counts.pending_transfers ?? 0 }}</p>
          <p class="text-sm text-muted-foreground mt-1">Pending Transfers</p>
        </div>
        <div class="flex-[1.5] rounded-lg bg-card p-4 border-l-4 border-l-orange-500 shadow-sm">
          <div class="grid grid-cols-3 text-center">
            <div>
              <p class="text-3xl font-bold font-body text-foreground">{{ counts.warnings ?? 0 }}</p>
              <p class="text-xs text-muted-foreground mt-1">Warnings</p>
            </div>
            <div>
              <p class="text-3xl font-bold font-body text-foreground">{{ counts.penalties ?? 0 }}</p>
              <p class="text-xs text-muted-foreground mt-1">Penalties</p>
            </div>
            <div>
              <p class="text-3xl font-bold font-body text-foreground">{{ counts.fines ?? 0 }}</p>
              <p class="text-xs text-muted-foreground mt-1">Fines</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Panels -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-lg bg-card p-5 shadow-sm min-h-[180px]">
          <p class="font-semibold text-foreground">Pending Transfers</p>
          <p v-if="!pendingTransfers.length" class="mt-4 text-sm text-muted-foreground">No pending transfers.</p>
          <ul v-else class="mt-4 space-y-2">
            <li v-for="t in pendingTransfers" :key="t.ref" class="flex justify-between text-sm">
              <span class="text-accent font-medium">{{ t.ref }}</span>
              <span class="text-accent">{{ t.unit_label }}</span>
            </li>
          </ul>
        </div>
        <div class="rounded-lg bg-card p-5 shadow-sm min-h-[180px]">
          <div class="flex items-center justify-between">
            <p class="font-semibold text-foreground">My Tasks</p>
            <AppButton variant="primary" size="sm" disabled title="Coming soon">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
              Add Task
            </AppButton>
          </div>
          <p v-if="!myTasks.length" class="mt-4 text-sm text-muted-foreground">No open tasks.</p>
        </div>
      </div>
    </div>

    <!-- ── Add Item modal ─────────────────────────────────────────── -->
    <AppModal :show="addModalOpen" title="Add Planner Item" @close="addModalOpen = false">
      <div class="space-y-4">
        <AppInput v-model="form.name" label="Title" placeholder="e.g. Audit (draft statement)" />
        <AppSelect v-model="form.category" :options="categoryOptions" label="Category" />
        <AppDatePicker v-model="form.due_date" label="Due Date" placeholder="Select a date" />
      </div>
      <template #footer>
        <AppButton variant="ghost" size="sm" @click="addModalOpen = false">Cancel</AppButton>
        <AppButton variant="primary" size="sm" :loading="saving" :disabled="!form.name.trim()" @click="createItem">
          Create Item
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>
