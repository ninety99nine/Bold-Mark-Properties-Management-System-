<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import AppModal        from '@/components/common/AppModal.vue'
import AppInput        from '@/components/common/AppInput.vue'
import AppButton       from '@/components/common/AppButton.vue'
import AppSelect       from '@/components/common/AppSelect.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'

const router = useRouter()
const route  = useRoute()

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  const num = Math.round(Number(amount))
  if (isNaN(num)) return '—'
  const formatted = num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
  return `R\u00a0${formatted}`
}

const ESTATE_TYPE_CONFIG = {
  sectional_title:    { label: 'Sectional Title', badgeClass: 'bg-primary/10 text-primary border-primary/20' },
  residential_rental: { label: 'Residential',     badgeClass: 'bg-success/10 text-success border-success/20' },
  commercial_rental:  { label: 'Commercial',       badgeClass: 'bg-warning/10 text-amber-dark border-warning/20' },
  mixed:              { label: 'Mixed',            badgeClass: 'bg-muted text-muted-foreground border-border' },
}

function typeConfig(type) {
  return ESTATE_TYPE_CONFIG[type] || { label: type, badgeClass: 'bg-muted text-muted-foreground border-border' }
}

function estateFinancials(estate) {
  const items = []
  const { type, default_levy_amount, default_rent_amount, monthly_revenue } = estate

  if (type === 'sectional_title' || type === 'mixed') {
    if (default_levy_amount) items.push({ label: 'Default Levy', value: formatCurrency(default_levy_amount) })
  }
  if (type !== 'sectional_title') {
    if (default_rent_amount) items.push({ label: 'Default Rent', value: formatCurrency(default_rent_amount) })
  }
  items.push({ label: 'Monthly Revenue', value: formatCurrency(monthly_revenue ?? 0) })
  return items
}

// ── Summary stats ─────────────────────────────────────────────────────
const summary = ref({ total_estates: 0, total_units: 0, occupied: 0, monthly_revenue: 0 })
const summaryLoading = ref(true)

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const { data } = await api.get('/estates/summary')
    summary.value = data
  } catch {
    // silent — show zeros
  } finally {
    summaryLoading.value = false
  }
}

// ── Toolbar state (from AppTableToolbar) ──────────────────────────────
const toolbarState = ref({ search: '', dateRange: 'all_time', filters: {}, sort: null })

function onToolbarUpdate(state) {
  toolbarState.value = state
  currentPage.value = 1
  fetchEstates(true)
}

// ── Toolbar config ────────────────────────────────────────────────────
const ESTATE_FILTER_FIELDS = [
  {
    key: 'type',
    label: 'Estate Type',
    options: [
      { value: 'sectional_title',    label: 'Sectional Title'    },
      { value: 'residential_rental', label: 'Residential Rental' },
      { value: 'commercial_rental',  label: 'Commercial Rental'  },
      { value: 'mixed',              label: 'Mixed'              },
    ],
  },
  {
    key: 'has_tenants',
    label: 'Tenants',
    options: [
      { value: 'yes', label: 'Has tenants' },
      { value: 'no',  label: 'No tenants'  },
    ],
  },
  {
    key: 'occupancy_status',
    label: 'Occupancy Status',
    options: [
      { value: 'fully_occupied',   label: 'Fully occupied'   },
      { value: 'partially_vacant', label: 'Partially occupied' },
      { value: 'fully_vacant',     label: 'Fully vacant'     },
    ],
  },
]

const ESTATE_SORT_OPTIONS = [
  { value: 'newest',       label: 'Newest first'     },
  { value: 'oldest',       label: 'Oldest first'     },
  { value: 'name_asc',     label: 'Name A–Z'         },
  { value: 'name_desc',    label: 'Name Z–A'         },
  { value: 'units_desc',   label: 'Most units'       },
  { value: 'units_asc',    label: 'Fewest units'     },
  { value: 'revenue_desc', label: 'Highest revenue'  },
  { value: 'revenue_asc',  label: 'Lowest revenue'   },
]

// ── Estates list (infinite scroll) ───────────────────────────────────
const estates = ref([])
const listLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const hasMore = computed(() => currentPage.value < lastPage.value)

async function fetchEstates(reset = false) {
  if (listLoading.value) return
  listLoading.value = true
  try {
    const { search, dateRange, customStart, customEnd, filters, sort } = toolbarState.value
    const params = { _per_page: 15, page: currentPage.value }

    if (search?.trim())              params._search          = search.trim()
    if (filters?.type)               params.type             = filters.type
    if (filters?.has_tenants)        params.has_tenants      = filters.has_tenants
    if (filters?.occupancy_status)   params.occupancy_status = filters.occupancy_status
    if (sort)                        params._sort            = sort
    if (dateRange && dateRange !== 'all_time') {
      params._date_range = dateRange
      if (dateRange === 'custom') {
        if (customStart) params._date_from = customStart
        if (customEnd)   params._date_to   = customEnd
      }
    }

    const { data } = await api.get('/estates', { params })
    const incoming = data.data ?? []
    estates.value = reset ? incoming : [...estates.value, ...incoming]
    lastPage.value = data.meta?.last_page ?? 1
  } catch {
    // silent
  } finally {
    listLoading.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || listLoading.value) return
  currentPage.value++
  await fetchEstates(false)
}

// ── Infinite scroll ───────────────────────────────────────────────────
const sentinelRef = ref(null)
let observer = null

watch(sentinelRef, (el) => {
  if (!el) return
  observer?.disconnect()
  observer = new IntersectionObserver(
    (entries) => { if (entries[0].isIntersecting) loadMore() },
    { threshold: 0.1 }
  )
  observer.observe(el)
})

// ── Add Estate modal ──────────────────────────────────────────────────
const showAddModal = ref(false)
const addLoading = ref(false)
const addError = ref('')

const addForm = ref({
  name: '',
  type: '',
  address: '',
  defaultLevy: '',
  defaultRent: '',
})

const estateTypeOptions = [
  { value: 'sectional_title',    label: 'Sectional Title'    },
  { value: 'residential_rental', label: 'Residential Rental' },
  { value: 'commercial_rental',  label: 'Commercial Rental'  },
  { value: 'mixed',              label: 'Mixed'              },
]

const showLevy = computed(() => ['sectional_title', 'mixed'].includes(addForm.value.type))
const showRent = computed(() => ['residential_rental', 'commercial_rental', 'mixed'].includes(addForm.value.type))

function resetAddForm() {
  addForm.value = { name: '', type: '', address: '', defaultLevy: '', defaultRent: '' }
  addError.value = ''
}

async function submitAddEstate() {
  if (!addForm.value.name || !addForm.value.type) return
  addLoading.value = true
  addError.value = ''
  try {
    const payload = {
      name:    addForm.value.name,
      type:    addForm.value.type,
    }
    if (addForm.value.address)    payload.address              = addForm.value.address
    if (addForm.value.defaultLevy) payload.default_levy_amount = Number(addForm.value.defaultLevy)
    if (addForm.value.defaultRent) payload.default_rent_amount = Number(addForm.value.defaultRent)

    await api.post('/estates', payload)
    showAddModal.value = false
    resetAddForm()
    currentPage.value = 1
    await Promise.all([fetchEstates(true), fetchSummary()])
  } catch (e) {
    addError.value = e.response?.data?.message || 'Failed to create estate. Please try again.'
  } finally {
    addLoading.value = false
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────
onMounted(() => {
  fetchSummary()
  fetchEstates(true)
  if (route.query.add === '1') {
    showAddModal.value = true
    router.replace({ path: '/estates' })
  }
})

onUnmounted(() => {
  observer?.disconnect()
})
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading + Add button ──────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Estates</h1>
        <p class="text-sm text-muted-foreground">Manage your property portfolio</p>
      </div>
      <AppButton variant="primary" @click="showAddModal = true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        Add Estate
      </AppButton>
    </div>

    <!-- ── Summary stat cards ─────────────────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="p-4 text-center">
          <div v-if="summaryLoading" class="h-8 w-10 rounded bg-muted animate-pulse mx-auto mb-1"></div>
          <p v-else class="text-2xl font-bold font-body text-foreground">{{ summary.total_estates }}</p>
          <p class="text-xs text-muted-foreground">Estates</p>
        </div>
      </div>
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="p-4 text-center">
          <div v-if="summaryLoading" class="h-8 w-12 rounded bg-muted animate-pulse mx-auto mb-1"></div>
          <p v-else class="text-2xl font-bold font-body text-foreground">{{ summary.total_units }}</p>
          <p class="text-xs text-muted-foreground">Units</p>
        </div>
      </div>
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="p-4 text-center">
          <div v-if="summaryLoading" class="h-8 w-12 rounded bg-muted animate-pulse mx-auto mb-1"></div>
          <p v-else class="text-2xl font-bold font-body text-foreground">{{ summary.occupied }}</p>
          <p class="text-xs text-muted-foreground">Occupied</p>
        </div>
      </div>
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="p-4 text-center">
          <div v-if="summaryLoading" class="h-8 w-32 rounded bg-muted animate-pulse mx-auto mb-1"></div>
          <p v-else class="text-2xl font-bold font-body text-foreground">{{ formatCurrency(summary.monthly_revenue) }}</p>
          <p class="text-xs text-muted-foreground">Monthly Revenue</p>
        </div>
      </div>
    </div>

    <!-- ── Search / Filter / Sort toolbar ───────────────────────── -->
    <AppTableToolbar
      search-placeholder="Search estates..."
      :filter-fields="ESTATE_FILTER_FIELDS"
      :sort-options="ESTATE_SORT_OPTIONS"
      storage-key="estates-toolbar"
      date-range-context="Created"
      @update:state="onToolbarUpdate"
    />

    <!-- ── Estate cards grid ───────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

      <!-- Real data cards -->
      <div
        v-for="estate in estates"
        :key="estate.id"
        @click="router.push(`/estates/${estate.id}`)"
        class="rounded-lg border bg-card text-card-foreground shadow-sm hover:border-accent/40 cursor-pointer transition-all hover:shadow-md group"
      >
        <div class="p-5">
          <!-- Name + type badge -->
          <div class="flex items-center justify-between mb-2 gap-2">
            <h3 class="font-body font-semibold text-base text-foreground leading-snug truncate min-w-0">
              {{ estate.name }}
            </h3>
            <div class="shrink-0">
              <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1 leading-tight whitespace-nowrap', typeConfig(estate.type).badgeClass]">
                {{ typeConfig(estate.type).label }}
              </span>
            </div>
          </div>

          <!-- Address -->
          <div class="flex items-center gap-1.5 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 text-muted-foreground shrink-0">
              <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <p class="text-xs text-muted-foreground truncate">{{ estate.address || 'No address on record' }}</p>
          </div>

          <!-- Occupancy stats -->
          <div class="grid grid-cols-3 gap-3 py-3 border-t border-b border-border">
            <div class="text-center">
              <p class="text-xl font-bold font-body text-foreground">{{ estate.units_count }}</p>
              <p class="text-[11px] text-muted-foreground">Units</p>
            </div>
            <div class="text-center">
              <p class="text-xl font-bold font-body text-foreground">{{ estate.occupied_units_count }}</p>
              <p class="text-[11px] text-muted-foreground">Occupied</p>
            </div>
            <div class="text-center">
              <p class="text-xl font-bold font-body text-muted-foreground">{{ estate.vacant_units_count }}</p>
              <p class="text-[11px] text-muted-foreground">Vacant</p>
            </div>
          </div>

          <!-- Financial line items -->
          <div class="mt-3 space-y-1.5">
            <div
              v-for="fin in estateFinancials(estate)"
              :key="fin.label"
              class="flex items-center justify-between"
            >
              <span class="text-xs text-muted-foreground">{{ fin.label }}</span>
              <span class="text-sm font-medium text-foreground">{{ fin.value }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Skeleton cards (initial load) -->
      <template v-if="listLoading && estates.length === 0">
        <div v-for="n in 6" :key="`skel-${n}`" class="rounded-lg border bg-card shadow-sm p-5 animate-pulse">
          <div class="flex items-center justify-between mb-2">
            <div class="h-4 w-40 rounded bg-muted"></div>
            <div class="h-5 w-20 rounded-full bg-muted"></div>
          </div>
          <div class="h-3 w-48 rounded bg-muted mb-4"></div>
          <div class="grid grid-cols-3 gap-3 py-3 border-t border-b border-border">
            <div class="text-center space-y-1">
              <div class="h-6 w-8 rounded bg-muted mx-auto"></div>
              <div class="h-3 w-10 rounded bg-muted mx-auto"></div>
            </div>
            <div class="text-center space-y-1">
              <div class="h-6 w-8 rounded bg-muted mx-auto"></div>
              <div class="h-3 w-12 rounded bg-muted mx-auto"></div>
            </div>
            <div class="text-center space-y-1">
              <div class="h-6 w-8 rounded bg-muted mx-auto"></div>
              <div class="h-3 w-10 rounded bg-muted mx-auto"></div>
            </div>
          </div>
          <div class="mt-3 space-y-2">
            <div class="flex justify-between">
              <div class="h-3 w-20 rounded bg-muted"></div>
              <div class="h-3 w-16 rounded bg-muted"></div>
            </div>
            <div class="flex justify-between">
              <div class="h-3 w-28 rounded bg-muted"></div>
              <div class="h-3 w-20 rounded bg-muted"></div>
            </div>
          </div>
        </div>
      </template>

      <!-- Empty state -->
      <div v-if="!listLoading && estates.length === 0" class="col-span-full py-16 text-center">
        <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
        </div>
        <p class="text-sm font-medium text-foreground mb-1">No estates found</p>
        <p class="text-xs text-muted-foreground">Try adjusting your search or filter.</p>
      </div>

    </div>

    <!-- Sentinel for infinite scroll -->
    <div ref="sentinelRef" class="h-2"></div>

    <!-- Loading more indicator -->
    <div v-if="listLoading && estates.length > 0" class="flex justify-center py-4">
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        Loading more estates...
      </div>
    </div>

    <!-- ── Add New Estate modal ───────────────────────────────────── -->
    <AppModal title="Add New Estate" size="md" :show="showAddModal" @close="showAddModal = false; resetAddForm()">

      <div class="space-y-4">

        <AppInput
          label="Estate Name"
          v-model="addForm.name"
          placeholder="e.g. Crystal Mews Body Corporate"
          required
        />

        <AppSelect
          v-model="addForm.type"
          label="Estate Type"
          :options="estateTypeOptions"
          placeholder="Select type..."
          required
        />

        <AppInput
          label="Address"
          v-model="addForm.address"
          placeholder="Full street address"
        />

        <AppInput
          v-if="showLevy"
          label="Default Levy Amount"
          type="number"
          v-model="addForm.defaultLevy"
          placeholder="0.00"
          prefix="R"
        />

        <AppInput
          v-if="showRent"
          label="Default Rent Amount"
          type="number"
          v-model="addForm.defaultRent"
          placeholder="0.00"
          prefix="R"
        />

        <!-- Error -->
        <p v-if="addError" class="text-sm text-danger">{{ addError }}</p>

      </div>

      <template #footer>
        <AppButton variant="outline" @click="showAddModal = false; resetAddForm()">Cancel</AppButton>
        <AppButton variant="primary" :disabled="addLoading || !addForm.name || !addForm.type" @click="submitAddEstate">
          {{ addLoading ? 'Creating…' : 'Create Estate' }}
        </AppButton>
      </template>

    </AppModal>

  </div>
</template>
