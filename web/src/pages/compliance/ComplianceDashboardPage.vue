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
const communities = ref([])
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
    communities.value = data.communities
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
const filteredCommunities = computed(() => {
  if (!searchQuery.value) return communities.value
  const q = searchQuery.value.toLowerCase()
  return communities.value.filter(e =>
    e.community_name.toLowerCase().includes(q)
  )
})

const yearOptions = computed(() =>
  financialYears.value.map(y => ({ value: y, label: y }))
)

function statusColor(community) {
  if (community.overdue_items > 0)        return 'text-red-600'
  if (community.status === 'not_started') return 'text-gray-400'
  return 'text-emerald-600'
}

function statusAccent(community) {
  if (community.overdue_items > 0)       return 'border-l-red-500'
  if (community.status === 'not_started') return 'border-l-gray-300'
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

function progressBarColor(community) {
  if (community.overdue_items > 0)        return 'bg-red-500'
  if (community.status === 'not_started') return 'bg-gray-300'
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

function goToChecklist(community) {
  router.push({ name: 'compliance-checklist', params: { checklistId: community.checklist_id } })
}
</script>

<template>
  <div>

    <!-- Page header -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-foreground">Compliance Tracker</h1>
        <p class="text-sm text-muted-foreground mt-1">Portfolio-wide compliance overview across all managed communities</p>
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
      <AppStatCard label="Fully Compliant" :value="summary.fully_compliant + ' / ' + summary.total_communities" />
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
          placeholder="Search communities..."
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

    <!-- Community compliance cards grid -->
    <div v-if="!loading && filteredCommunities.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <button
        v-for="community in filteredCommunities"
        :key="community.checklist_id"
        @click="goToChecklist(community)"
        :class="[
          'text-left rounded-xl border border-l-4 p-5 transition-all duration-150 hover:shadow-md cursor-pointer',
          statusBg(community.status),
          statusAccent(community),
        ]"
      >
        <!-- Community header -->
        <div class="flex items-start justify-between mb-3">
          <div class="min-w-0 flex-1 mr-3">
            <h3 class="font-semibold text-sm text-foreground truncate">{{ community.community_name }}</h3>
            <p class="text-xs text-muted-foreground mt-0.5">{{ community.financial_year }}</p>
          </div>
          <AppBadge :variant="statusBadgeVariant(community.status)" size="sm" bordered>
            {{ statusLabel(community.status) }}
          </AppBadge>
        </div>

        <!-- Progress bar -->
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-muted-foreground">Progress</span>
            <span :class="['text-xs font-bold', statusColor(community)]">{{ community.progress }}%</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              :class="['h-full rounded-full transition-all duration-500', progressBarColor(community)]"
              :style="{ width: community.progress + '%' }"
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
            {{ community.completed_items }} done
          </span>
          <span v-if="community.waived_items > 0" class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
            {{ community.waived_items }} waived
          </span>
          <span class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
              <path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>
              <path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>
            </svg>
            {{ community.total_items }} total
          </span>
        </div>

        <!-- Up Next hint -->
        <div v-if="community.next_item && community.overdue_items === 0" class="mt-3 pt-3 border-t border-border flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground flex-shrink-0">
            <polygon points="5 3 19 12 5 21 5 3"/>
          </svg>
          <span class="flex-1 text-xs text-muted-foreground truncate">{{ community.next_item.name }}</span>
          <span v-if="community.next_item.due_date && dueDateLabel(community.next_item.due_date)"
            :class="['text-xs flex-shrink-0', dueDateLabel(community.next_item.due_date).cls]">
            {{ dueDateLabel(community.next_item.due_date).text }}
          </span>
        </div>

        <!-- Overdue alert -->
        <div v-if="community.overdue_items > 0" class="mt-3 flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-red-50 border border-red-200">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-red-500 flex-shrink-0 mt-0.5">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
          </svg>
          <div class="min-w-0">
            <p class="text-xs font-semibold text-red-700 leading-snug">
              {{ community.overdue_items }} overdue item{{ community.overdue_items !== 1 ? 's' : '' }}
            </p>
            <p v-if="community.next_item" class="text-xs text-red-500 truncate mt-0.5">{{ community.next_item.name }}</p>
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
      <p class="text-xs text-muted-foreground mb-4">Create a checklist for an community to start tracking compliance</p>
      <AppButton variant="primary" size="sm" @click="router.push('/communities')">
        Go to Communities
      </AppButton>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && !communities.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
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
