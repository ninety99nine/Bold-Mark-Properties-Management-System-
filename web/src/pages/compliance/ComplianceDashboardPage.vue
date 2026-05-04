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

function statusColor(status) {
  const map = {
    compliant: 'text-emerald-600',
    in_progress: 'text-amber-600',
    at_risk: 'text-red-600',
    not_started: 'text-gray-400',
  }
  return map[status] || 'text-gray-400'
}

function statusBg(status) {
  const map = {
    compliant: 'bg-emerald-50 border-emerald-200',
    in_progress: 'bg-amber-50 border-amber-200',
    at_risk: 'bg-red-50 border-red-200',
    not_started: 'bg-gray-50 border-gray-200',
  }
  return map[status] || 'bg-gray-50 border-gray-200'
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

function progressBarColor(progress) {
  if (progress === 100) return 'bg-emerald-500'
  if (progress >= 50)   return 'bg-amber-500'
  if (progress > 0)     return 'bg-red-500'
  return 'bg-gray-300'
}

function goToChecklist(estate) {
  router.push({ name: 'compliance-checklist', params: { checklistId: estate.checklist_id } })
}
</script>

<template>
  <div class="min-h-screen">
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
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <div class="w-64">
        <AppInput
          v-model="searchQuery"
          type="search"
          placeholder="Search estates..."
          size="sm"
        />
      </div>
      <div v-if="yearOptions.length > 1" class="w-44">
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
          'text-left rounded-xl border p-5 transition-all duration-150 hover:shadow-md hover:border-primary/30 cursor-pointer',
          statusBg(estate.status),
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
            <span :class="['text-xs font-bold', statusColor(estate.status)]">{{ estate.progress }}%</span>
          </div>
          <div class="w-full h-2 bg-white/80 rounded-full overflow-hidden">
            <div
              :class="['h-full rounded-full transition-all duration-500', progressBarColor(estate.progress)]"
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
          <span v-if="estate.overdue_items > 0" class="flex items-center gap-1 text-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 8v4"/><path d="M12 16h.01"/>
            </svg>
            {{ estate.overdue_items }} overdue
          </span>
          <span class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>
              <path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>
            </svg>
            {{ estate.total_items }} total
          </span>
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
