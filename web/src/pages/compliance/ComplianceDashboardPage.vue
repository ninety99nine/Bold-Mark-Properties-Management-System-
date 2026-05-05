<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useCountryStore } from '@/stores/country'
import AppStatCard from '@/components/common/AppStatCard.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppInput from '@/components/common/AppInput.vue'
import CreateChecklistModal from './CreateChecklistModal.vue'

const router = useRouter()
const countryStore = useCountryStore()
const showCreateChecklist = ref(false)

function onChecklistCreated(checklist) {
  router.push({ name: 'compliance-checklist', params: { checklistId: checklist.id } })
}

// ── State ────────────────────────────────────────────────────────────
const loading = ref(true)
const summary = ref(null)
const estates = ref([])
const financialYears = ref([])
const selectedYear = ref('')
const searchQuery = ref('')

// ── Fetch ────────────────────────────────────────────────────────────
async function fetchPortfolio() {
  loading.value = true
  try {
    const params = {}
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    if (selectedYear.value) params.financial_year_label = selectedYear.value
    const { data } = await api.get('/compliance/portfolio-summary', { params })
    summary.value = data.summary
    estates.value = data.estates
    financialYears.value = data.financial_years || []

    // Auto-select most recent year if none selected
    if (!selectedYear.value && financialYears.value.length > 0) {
      selectedYear.value = financialYears.value[0]
    }
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

onMounted(fetchPortfolio)
watch(() => countryStore.activeCountry, fetchPortfolio)
watch(selectedYear, fetchPortfolio)

// ── Computed ─────────────────────────────────────────────────────────
const filteredEstates = computed(() => {
  if (!searchQuery.value) return estates.value
  const q = searchQuery.value.toLowerCase()
  return estates.value.filter(e =>
    e.estate_name.toLowerCase().includes(q)
  )
})

const yearOptions = computed(() =>
  financialYears.value.map(y => ({ value: y, label: y }))
)

function statusColor(estate) {
  if (estate.overdue_items > 0)        return 'text-red-600'
  if (estate.status === 'not_started') return 'text-gray-400'
  return 'text-emerald-600'
}

function statusAccent(estate) {
  if (estate.overdue_items > 0)       return 'border-l-red-500'
  if (estate.status === 'not_started') return 'border-l-gray-300'
  return 'border-l-emerald-500'
}

function statusBg(status) {
  if (status === 'at_risk') return 'bg-red-50/40 border-red-200'
  return 'bg-white border-border'
}

function statusLabel(status) {
  const map = {
    compliant: 'Compliant',
    in_progress: 'In Progress',
    at_risk: 'At Risk',
    not_started: 'Not Started',
  }
  return map[status] || status
}

function statusBadgeVariant(status) {
  const map = { compliant: 'success', in_progress: 'warning', at_risk: 'danger', not_started: 'default' }
  return map[status] || 'default'
}

function progressBarColor(estate) {
  if (estate.overdue_items > 0)        return 'bg-red-500'
  if (estate.status === 'not_started') return 'bg-gray-300'
  return 'bg-emerald-500'
}

function dueDateLabel(dateStr) {
  if (!dateStr) return null
  const today = new Date(); today.setHours(0,0,0,0)
  const due = new Date(dateStr); due.setHours(0,0,0,0)
  const d = Math.round((due - today) / 86400000)
  if (d < 0)  return { text: `${Math.abs(d)}d overdue`, cls: 'text-red-600 font-semibold' }
  if (d === 0) return { text: 'Due today',              cls: 'text-amber-600 font-semibold' }
  if (d <= 7)  return { text: `Due in ${d}d`,           cls: 'text-amber-600' }
  return { text: `Due in ${d}d`, cls: 'text-muted-foreground' }
}

function goToChecklist(estate) {
  router.push({ name: 'compliance-checklist', params: { checklistId: estate.checklist_id } })
}
</script>

<template>
  <div>

    <!-- ── Phase 2 Overlay ──────────────────────────────────────────────── -->
    <Teleport to="body">
    <div class="fixed top-14 left-60 right-0 bottom-0 z-40 backdrop-blur-[2px] bg-white/60 pointer-events-none"></div>
    <div class="fixed top-14 left-60 right-0 bottom-0 z-50 flex items-center justify-center pointer-events-none">
      <div class="pointer-events-auto flex flex-col items-center gap-4 rounded-2xl border border-[#e8a040]/40 bg-white/95 px-10 py-8 shadow-2xl text-center max-w-sm">
        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-[#1a2744]/8 border border-[#1a2744]/15">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-[#1a2744]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <div>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e8a040]/15 px-3 py-1 text-xs font-semibold text-[#e8a040] border border-[#e8a040]/30 mb-3">
            Phase 2 Feature
          </span>
          <h2 class="font-bold text-lg text-[#1a2744] leading-snug">Compliance Tracker — Coming Soon</h2>
          <p class="mt-2 text-sm text-slate-500 leading-relaxed">
            This feature is part of <span class="font-semibold text-[#1a2744]">Phase 2</span> of your Bold Mark Properties system and is currently under development.
          </p>
          <ul class="mt-4 space-y-2 text-left">
            <li class="flex items-start gap-2 text-sm text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0 text-[#e8a040]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              Monitor portfolio-wide compliance progress across all managed estates
            </li>
            <li class="flex items-start gap-2 text-sm text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0 text-[#e8a040]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              Track overdue items, completed tasks and compliance status per estate
            </li>
            <li class="flex items-start gap-2 text-sm text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0 text-[#e8a040]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              Create checklists from reusable templates and assign them to estates
            </li>
            <li class="flex items-start gap-2 text-sm text-slate-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0 text-[#e8a040]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              Get instant visibility into which estates are compliant, at risk or not started
            </li>
          </ul>
        </div>
      </div>
    </div>
    </Teleport>
    <!-- Page header -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-foreground">Compliance Tracker</h1>
        <p class="text-sm text-muted-foreground mt-1">Portfolio-wide compliance overview across all managed estates</p>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <AppButton variant="ghost" size="sm" @click="router.push({ name: 'compliance-templates' })">
          Templates
        </AppButton>
        <AppButton variant="primary" size="sm" @click="showCreateChecklist = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          New Checklist
        </AppButton>
      </div>
    </div>

    <!-- KPI Cards -->
    <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <AppStatCard label="Portfolio Compliance" :value="summary.portfolio_progress + '%'" />
      <AppStatCard label="Fully Compliant" :value="summary.fully_compliant + ' / ' + summary.total_estates" />
      <AppStatCard label="Items Completed" :value="summary.total_completed + ' / ' + summary.total_items" />
      <AppStatCard label="Overdue Items" :value="String(summary.total_overdue)" />
    </div>

    <!-- Skeleton KPIs while loading -->
    <div v-else-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div v-for="i in 4" :key="i" class="h-[88px] bg-white rounded-xl border border-border animate-pulse" />
    </div>

    <!-- Filters bar -->
    <div class="flex items-center gap-3 mb-6">
      <div class="flex-1 min-w-48">
        <AppInput
          v-model="searchQuery"
          leading-icon="search"
          size="sm"
          placeholder="Search estates..."
        />
      </div>
      <div v-if="yearOptions.length > 1" class="w-44 flex-shrink-0">
        <AppSelect
          v-model="selectedYear"
          :options="yearOptions"
          placeholder="Financial Year"
        />
      </div>
    </div>

    <!-- Estate compliance cards grid -->
    <div v-if="!loading && filteredEstates.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <button
        v-for="estate in filteredEstates"
        :key="estate.checklist_id"
        @click="goToChecklist(estate)"
        :class="[
          'text-left rounded-xl border border-l-4 p-5 transition-all duration-150 hover:shadow-md cursor-pointer',
          statusBg(estate.status),
          statusAccent(estate),
        ]"
      >
        <!-- Estate header -->
        <div class="flex items-start justify-between mb-3">
          <div class="min-w-0 flex-1 mr-3">
            <h3 class="font-semibold text-sm text-foreground truncate">{{ estate.estate_name }}</h3>
            <p class="text-xs text-muted-foreground mt-0.5">{{ estate.financial_year }}</p>
          </div>
          <AppBadge :variant="statusBadgeVariant(estate.status)" size="sm" bordered>
            {{ statusLabel(estate.status) }}
          </AppBadge>
        </div>

        <!-- Progress bar -->
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-muted-foreground">Progress</span>
            <span :class="['text-xs font-bold', statusColor(estate)]">{{ estate.progress }}%</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              :class="['h-full rounded-full transition-all duration-500', progressBarColor(estate)]"
              :style="{ width: estate.progress + '%' }"
            />
          </div>
        </div>

        <!-- Item counts -->
        <div class="flex items-center gap-4 text-xs text-muted-foreground">
          <span class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-emerald-500">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke-linecap="round" stroke-linejoin="round"/>
              <polyline points="22 4 12 14.01 9 11.01" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ estate.completed_items }} done
          </span>
          <span v-if="estate.waived_items > 0" class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
            {{ estate.waived_items }} waived
          </span>
          <span class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>
              <path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>
            </svg>
            {{ estate.total_items }} total
          </span>
        </div>

        <!-- Up Next hint -->
        <div v-if="estate.next_item && estate.overdue_items === 0" class="mt-3 pt-3 border-t border-border flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground flex-shrink-0">
            <polygon points="5 3 19 12 5 21 5 3"/>
          </svg>
          <span class="flex-1 text-xs text-muted-foreground truncate">{{ estate.next_item.name }}</span>
          <span v-if="estate.next_item.due_date && dueDateLabel(estate.next_item.due_date)"
            :class="['text-xs flex-shrink-0', dueDateLabel(estate.next_item.due_date).cls]">
            {{ dueDateLabel(estate.next_item.due_date).text }}
          </span>
        </div>

        <!-- Overdue alert -->
        <div v-if="estate.overdue_items > 0" class="mt-3 flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-red-50 border border-red-200">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-red-500 flex-shrink-0 mt-0.5">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
          </svg>
          <div class="min-w-0">
            <p class="text-xs font-semibold text-red-700 leading-snug">
              {{ estate.overdue_items }} overdue item{{ estate.overdue_items !== 1 ? 's' : '' }}
            </p>
            <p v-if="estate.next_item" class="text-xs text-red-500 truncate mt-0.5">{{ estate.next_item.name }}</p>
          </div>
        </div>
      </button>
    </div>

    <!-- Empty state -->
    <div v-else-if="!loading" class="text-center py-16 bg-white rounded-xl border border-border">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-12 h-12 text-muted-foreground mx-auto mb-3">
        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="9" y="3" width="6" height="4" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M9 14l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <h3 class="text-sm font-semibold text-foreground mb-1">No compliance checklists yet</h3>
      <p class="text-xs text-muted-foreground mb-4">Create a checklist for an estate to start tracking compliance</p>
      <AppButton variant="primary" size="sm" @click="router.push('/estates')">
        Go to Estates
      </AppButton>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && !estates.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="h-[180px] bg-white rounded-xl border border-border animate-pulse" />
    </div>

    <!-- Create Checklist Modal -->
    <CreateChecklistModal
      :show="showCreateChecklist"
      @close="showCreateChecklist = false"
      @created="onChecklistCreated"
    />
  </div>
</template>
