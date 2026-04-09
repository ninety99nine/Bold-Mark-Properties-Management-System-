<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import AppStatCard from '@/components/common/AppStatCard.vue'
import api from '@/composables/useApi.js'

const router = useRouter()

// ── State ─────────────────────────────────────────────────────────────
const loading = ref(true)
const summary = ref(null)
const recentInvoices = ref([])
const estatesOverview = ref([])
const invoiceFilter = ref('all')

// ── Fetch ─────────────────────────────────────────────────────────────
async function fetchDashboard() {
  try {
    loading.value = true
    const { data } = await api.get('/dashboard')
    summary.value = data.summary
    recentInvoices.value = data.recent_invoices ?? []
    estatesOverview.value = data.estates_overview ?? []
  } catch (e) {
    console.error('Failed to load dashboard:', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)

// ── Filtered invoices ─────────────────────────────────────────────────
const filteredInvoices = computed(() => {
  if (invoiceFilter.value === 'all') return recentInvoices.value
  return recentInvoices.value.filter(inv => inv.status === invoiceFilter.value)
})

// ── Formatters ────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount == null) return 'R\u00a00'
  return 'R\u00a0' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
}

function formatRate(rate) {
  return rate != null ? `${rate}%` : '—'
}

// ── Badge helpers ─────────────────────────────────────────────────────
const badgeClasses = {
  paid:           'bg-success/10 text-success border-success/20',
  overdue:        'bg-destructive/10 text-destructive border-destructive/20',
  partial:        'bg-amber/10 text-amber-dark border-amber/20',
  partially_paid: 'bg-amber/10 text-amber-dark border-amber/20',
  unpaid:         'bg-muted text-muted-foreground border-border',
}

function statusLabel(s) {
  const map = { paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', unpaid: 'Unpaid' }
  return map[s] ?? (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—')
}

// ── Getting Started steps ─────────────────────────────────────────────
const steps = computed(() => [
  {
    id: 'create_estate',
    title: 'Create your first estate',
    description: 'Set up a body corporate, rental portfolio, or mixed estate to start managing properties.',
    action: 'Create Estate',
    route: '/estates?add=1',
    done: (summary.value?.total_estates ?? 0) > 0,
  },
  {
    id: 'add_units',
    title: 'Add units to your estate',
    description: 'Register the individual units, plots, or apartments that make up the estate.',
    action: 'Add Units',
    route: '/estates',
    done: (summary.value?.total_units ?? 0) > 0,
  },
  {
    id: 'assign_occupants',
    title: 'Assign owners and tenants to units',
    description: 'Link each unit to its registered owner. If rented out, add the tenant and lease details.',
    action: 'Manage Units',
    route: '/estates',
    done: (summary.value?.occupied_units ?? 0) > 0,
  },
  {
    id: 'run_billing',
    title: 'Run your first billing',
    description: 'Generate levy, rent, and all other charge invoices for active units in one click.',
    action: 'Run Billing',
    route: '/billing',
    done: recentInvoices.value.length > 0,
  },
  {
    id: 'record_payment',
    title: 'Record a payment in the cashbook',
    description: 'Log incoming bank payments and allocate them to the correct invoices to track what\'s been collected.',
    action: 'Open Cashbook',
    route: '/cashbook',
    done: (summary.value?.collected_this_month ?? 0) > 0,
  },
])

const completedCount = computed(() => steps.value.filter(s => s.done).length)
const allStepsComplete = computed(() => completedCount.value === steps.value.length)
const progressPercent = computed(() => Math.round((completedCount.value / steps.value.length) * 100))

// Index of the first incomplete step — this is the only active step
const currentStepIndex = computed(() => steps.value.findIndex(s => !s.done))
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading ──────────────────────────────────────────────── -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Portfolio overview for Bold Mark Properties</p>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Skeleton cards while loading -->
      <template v-if="loading">
        <div v-for="n in 4" :key="n" class="rounded-lg border bg-card shadow-sm p-5 animate-pulse">
          <div class="flex items-center justify-between mb-3">
            <div class="h-3 w-24 bg-muted rounded" />
            <div class="h-4 w-4 bg-muted rounded" />
          </div>
          <div class="h-8 w-28 bg-muted rounded mb-2" />
          <div class="h-3 w-20 bg-muted rounded" />
        </div>
      </template>

      <!-- Real stat cards -->
      <template v-else>

        <AppStatCard
          label="Total Estates"
          :value="summary?.total_estates ?? '—'"
          :subtitle="(summary?.total_units ?? 0) + ' units'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-primary">
              <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
              <path d="M9 22v-4h6v4"/>
              <path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/>
              <path d="M12 10h.01"/><path d="M12 14h.01"/>
              <path d="M16 10h.01"/><path d="M16 14h.01"/>
              <path d="M8 10h.01"/><path d="M8 14h.01"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Total Outstanding"
          :value="formatCurrency(summary?.total_outstanding)"
          value-class="text-destructive"
          :trend="{ text: 'Unpaid invoices', direction: 'up', colorClass: 'text-destructive' }"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-destructive">
              <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
              <polyline points="16 7 22 7 22 13"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Collected This Month"
          :value="formatCurrency(summary?.collected_this_month)"
          :trend="{ text: 'Credits this month', direction: 'down', colorClass: 'text-success' }"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-success">
              <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
              <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
            </svg>
          </template>
        </AppStatCard>

        <AppStatCard
          label="Occupancy Rate"
          :value="formatRate(summary?.occupancy_rate)"
          :subtitle="(summary?.vacant_units ?? 0) + ' vacant units'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] text-muted-foreground">
              <path d="M18 21a8 8 0 0 0-16 0"/>
              <circle cx="10" cy="8" r="5"/>
              <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>
            </svg>
          </template>
        </AppStatCard>

      </template>
    </div>

    <!-- ── Quick Actions ─────────────────────────────────────────────── -->
    <div class="flex flex-wrap gap-3">

      <AppButton variant="primary" size="lg" @click="router.push('/billing')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
          <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
          <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
        </svg>
        Run Billing
      </AppButton>

      <AppButton variant="secondary" size="lg" @click="router.push('/cashbook')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
          <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
        </svg>
        Upload Cashbook
      </AppButton>

      <AppButton variant="outline" size="lg" @click="router.push('/age-analysis')">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/>
          <polyline points="16 17 22 17 22 11"/>
        </svg>
        View Age Analysis
      </AppButton>

    </div>

    <!-- ── Two-column: Recent Invoices + Estates Overview ─────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Recent Invoices card -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="flex flex-col space-y-1.5 p-6 pb-3">
          <div class="flex items-center justify-between">
            <h3 class="tracking-tight font-body font-semibold text-lg">Recent Invoices</h3>
            <AppButton variant="ghost" size="sm" @click="router.push('/billing')">
              View All
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
              </svg>
            </AppButton>
          </div>

          <!-- Status filter tabs — only shown when there are invoices -->
          <div v-if="!loading && recentInvoices.length > 0" class="flex gap-1.5 mt-2">
            <button
              v-for="tab in [
                { label: 'All',     value: 'all' },
                { label: 'Paid',    value: 'paid' },
                { label: 'Overdue', value: 'overdue' },
                { label: 'Unpaid',  value: 'unpaid' },
                { label: 'Partial', value: 'partially_paid' },
              ]"
              :key="tab.value"
              @click="invoiceFilter = tab.value"
              :class="[
                'px-2.5 py-1 rounded text-xs font-medium transition-colors',
                invoiceFilter === tab.value
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:text-foreground',
              ]"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <div class="p-6 pt-0">
          <!-- Skeleton rows -->
          <div v-if="loading" class="space-y-3 animate-pulse">
            <div v-for="n in 5" :key="n" class="flex items-center justify-between py-2 border-b border-border last:border-0">
              <div class="space-y-1.5">
                <div class="h-3 w-28 bg-muted rounded" />
                <div class="h-2.5 w-36 bg-muted rounded" />
              </div>
              <div class="flex items-center gap-3">
                <div class="h-3 w-16 bg-muted rounded" />
                <div class="h-5 w-14 bg-muted rounded-full" />
              </div>
            </div>
          </div>

          <!-- Real invoice rows -->
          <div v-else class="space-y-3">
            <div
              v-for="invoice in filteredInvoices"
              :key="invoice.id"
              class="flex items-center justify-between py-2 border-b border-border last:border-0 cursor-pointer hover:bg-muted/50 rounded px-2 -mx-2 transition-colors"
              @click="router.push(`/billing/invoices/${invoice.id}`)"
            >
              <div>
                <p class="text-sm font-medium text-foreground">{{ invoice.invoice_number }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ invoice.billed_to_name ?? ('Unit ' + (invoice.unit_number ?? '—')) }}
                  <template v-if="invoice.charge_type"> · {{ invoice.charge_type }}</template>
                </p>
              </div>
              <div class="text-right flex items-center gap-3">
                <span class="text-sm font-medium text-foreground whitespace-nowrap">{{ formatCurrency(invoice.amount) }}</span>
                <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1 leading-tight', badgeClasses[invoice.status] || badgeClasses.draft]">
                  {{ statusLabel(invoice.status) }}
                </span>
              </div>
            </div>

            <div v-if="filteredInvoices.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
              <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
              </div>
              <p class="text-sm font-medium text-muted-foreground">No invoices found</p>
              <p class="text-xs text-muted-foreground/70 mt-1">Try selecting a different filter above</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Estates Overview card -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="flex flex-col space-y-1.5 p-6 pb-3">
          <div class="flex items-center justify-between">
            <h3 class="tracking-tight font-body font-semibold text-lg">Estates Overview</h3>
            <AppButton variant="ghost" size="sm" @click="router.push('/estates')">
              View All{{ summary?.total_estates ? ` (${summary.total_estates})` : '' }}
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
              </svg>
            </AppButton>
          </div>
        </div>

        <div class="p-6 pt-0">
          <!-- Skeleton estate rows -->
          <div v-if="loading" class="space-y-3 animate-pulse">
            <div v-for="n in 4" :key="n" class="p-3.5 rounded-lg border border-border">
              <div class="flex items-center justify-between mb-2.5">
                <div class="h-3 w-40 bg-muted rounded" />
                <div class="h-3 w-14 bg-muted rounded" />
              </div>
              <div class="flex gap-3">
                <div class="h-2.5 w-20 bg-muted rounded" />
                <div class="h-2.5 w-20 bg-muted rounded" />
                <div class="h-2.5 w-16 bg-muted rounded" />
              </div>
            </div>
          </div>

          <!-- Real estate rows -->
          <div v-else class="space-y-3">
            <div
              v-for="estate in estatesOverview"
              :key="estate.id"
              @click="router.push(`/estates/${estate.id}`)"
              class="p-3.5 rounded-lg border border-border hover:border-accent/40 cursor-pointer transition-colors"
            >
              <div class="flex items-center justify-between mb-2.5">
                <p class="font-medium text-foreground text-sm">{{ estate.name }}</p>
                <span class="text-xs text-muted-foreground">{{ estate.units_count }} units</span>
              </div>
              <div v-if="estate.units_count > 0" class="flex items-center gap-2 text-xs text-muted-foreground">
                <template v-if="estate.owner_occupied_count > 0">
                  <span><span class="font-semibold text-foreground">{{ estate.owner_occupied_count }}</span> owners</span>
                  <span class="text-border">•</span>
                </template>
                <span><span class="font-semibold text-foreground">{{ estate.tenant_occupied_count }}</span> tenants</span>
                <span class="text-border">•</span>
                <span><span class="font-semibold text-foreground">{{ estate.vacant_count }}</span> vacant</span>
              </div>
              <!-- Empty estate CTA -->
              <div v-else class="mt-1 flex items-center justify-between">
                <span class="text-xs text-muted-foreground/70 italic">No units added yet</span>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-accent hover:text-accent/80 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                  </svg>
                  Add Units
                </span>
              </div>
            </div>

            <div v-if="estatesOverview.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
              <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
              </div>
              <p class="text-sm font-medium text-muted-foreground">No estates yet</p>
              <p class="text-xs text-muted-foreground/70 mt-1">Add your first estate to get started</p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Getting Started ──────────────────────────────────────────────── -->
    <div v-if="!loading && !allStepsComplete" class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden">

      <!-- Header -->
      <div class="p-6 pb-5 border-b border-border">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <div class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-accent">
                  <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                </svg>
              </div>
              <h3 class="font-body font-semibold text-lg text-foreground">Getting Started</h3>
            </div>
            <p class="text-sm text-muted-foreground">Complete these steps to unlock the full power of your portfolio management platform.</p>
          </div>
          <div class="flex-shrink-0 text-right">
            <p class="text-2xl font-bold font-body text-foreground">{{ completedCount }}<span class="text-base font-normal text-muted-foreground">/{{ steps.length }}</span></p>
            <p class="text-xs text-muted-foreground">steps complete</p>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="mt-4 h-1.5 bg-muted rounded-full overflow-hidden">
          <div
            class="h-full bg-accent rounded-full transition-all duration-700 ease-out"
            :style="{ width: progressPercent + '%' }"
          />
        </div>
      </div>

      <!-- Steps list -->
      <div class="divide-y divide-border">
        <div
          v-for="(step, index) in steps"
          :key="step.id"
          class="flex items-center gap-4 px-6 py-4 transition-colors"
          :class="{
            'bg-muted/40': step.done,
            'bg-accent/[0.04] border-l-2 border-l-accent': index === currentStepIndex,
            'opacity-40': index > currentStepIndex,
          }"
        >
          <!-- Status indicator -->
          <div class="flex-shrink-0">
            <!-- Done: green checkmark -->
            <div v-if="step.done" class="w-7 h-7 rounded-full bg-success/15 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-success">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </div>
            <!-- Current: amber numbered circle -->
            <div v-else-if="index === currentStepIndex" class="w-7 h-7 rounded-full bg-accent flex items-center justify-center">
              <span class="text-[11px] font-bold text-white">{{ index + 1 }}</span>
            </div>
            <!-- Locked: gray numbered circle -->
            <div v-else class="w-7 h-7 rounded-full border-2 border-border bg-muted flex items-center justify-center">
              <span class="text-[11px] font-bold text-muted-foreground">{{ index + 1 }}</span>
            </div>
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <p
              class="text-sm font-semibold leading-snug"
              :class="{
                'text-muted-foreground line-through decoration-muted-foreground/40': step.done,
                'text-foreground': index === currentStepIndex,
                'text-muted-foreground': index > currentStepIndex,
              }"
            >
              {{ step.title }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed" v-if="!step.done">
              {{ step.description }}
            </p>
          </div>

          <!-- Action (right side) -->
          <div class="flex-shrink-0 flex items-center">
            <!-- Done -->
            <span v-if="step.done" class="inline-flex items-center gap-1 text-xs font-medium text-success">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              Done
            </span>
            <!-- Current: active action button -->
            <button
              v-else-if="index === currentStepIndex"
              @click="router.push(step.route)"
              class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded text-xs font-semibold
                     bg-accent text-white shadow-sm hover:bg-amber-dark active:scale-95
                     transition-all duration-150 whitespace-nowrap"
            >
              {{ step.action }}
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
              </svg>
            </button>
            <!-- Locked -->
            <span v-else class="inline-flex items-center gap-1 text-xs text-muted-foreground/60">
              <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Locked
            </span>
          </div>
        </div>
      </div>

    </div>

  </div>
</template>
