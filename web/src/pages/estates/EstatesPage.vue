<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import AppModal        from '@/components/common/AppModal.vue'
import AppInput        from '@/components/common/AppInput.vue'
import AppButton       from '@/components/common/AppButton.vue'
import AppSelect       from '@/components/common/AppSelect.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import { useCountryStore } from '@/stores/country'

const router = useRouter()
const route  = useRoute()
const countryStore = useCountryStore()

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  return countryStore.formatCurrency(amount)
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
const summary = ref({ total_estates: 0, total_units: 0, occupied: 0, vacant: 0, monthly_revenue: 0 })
const summaryLoading = ref(true)

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const params = {}
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    const { data } = await api.get('/estates/summary', { params })
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

const isFiltered = computed(() => {
  const { search, filters, dateRange } = toolbarState.value
  if (search?.trim()) return true
  if (Object.values(filters || {}).some(Boolean)) return true
  if (dateRange && dateRange !== 'all_time') return true
  return false
})

const isFirstTimeEmpty = computed(() =>
  !summaryLoading.value && !listLoading.value && summary.value.total_estates === 0 && !isFiltered.value
)

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
    if (countryStore.activeCountry) params.country = countryStore.activeCountry

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

// ── Empty state slideshow ─────────────────────────────────────────────
const ESTATE_SLIDES = [
  '/estates/property-1.jpg',
  '/estates/property-2.png',
  '/estates/property-3.jpeg',
  '/estates/property-4.jpg',
]
const slideIndex = ref(0)
const slideFading = ref(false)
let slideTimer = null

function advanceSlide() {
  slideFading.value = true
  setTimeout(() => {
    slideIndex.value = (slideIndex.value + 1) % ESTATE_SLIDES.length
    slideFading.value = false
  }, 400)
}

function goToSlide(i) {
  if (i === slideIndex.value) return
  clearInterval(slideTimer)
  slideFading.value = true
  setTimeout(() => {
    slideIndex.value = i
    slideFading.value = false
  }, 400)
  slideTimer = setInterval(advanceSlide, 4000)
}

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
  country: '',
  defaultLevy: '',
  defaultRent: '',
})

const estateTypeOptions = [
  { value: 'sectional_title',    label: 'Sectional Title'    },
  { value: 'residential_rental', label: 'Residential Rental' },
  { value: 'commercial_rental',  label: 'Commercial Rental'  },
  { value: 'mixed',              label: 'Mixed'              },
]

const countryOptions = Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({
  value: code,
  label: `${info.flag} ${info.name}`,
}))

const showLevy = computed(() => ['sectional_title', 'mixed'].includes(addForm.value.type))
const showRent = computed(() => ['residential_rental', 'commercial_rental', 'mixed'].includes(addForm.value.type))

// Currency prefix based on the form's selected country (not the global country)
const addFormCurrencySymbol = computed(() => {
  const code = addForm.value.country
  return code ? (countryStore.COUNTRY_MAP[code]?.symbol || countryStore.currencySymbol) : countryStore.currencySymbol
})

function resetAddForm() {
  addForm.value = { name: '', type: '', address: '', country: countryStore.activeCountry || '', defaultLevy: '', defaultRent: '' }
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
    if (addForm.value.address)     payload.address              = addForm.value.address
    if (addForm.value.defaultLevy) payload.default_levy_amount = Number(addForm.value.defaultLevy)
    if (addForm.value.defaultRent) payload.default_rent_amount = Number(addForm.value.defaultRent)
    // Set country + auto-derive currency
    const estateCountry = addForm.value.country || countryStore.activeCountry
    if (estateCountry) {
      payload.country  = estateCountry
      payload.currency = countryStore.COUNTRY_MAP[estateCountry]?.currencyCode || null
    }

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
    addForm.value.country = countryStore.activeCountry || ''
    showAddModal.value = true
    router.replace({ path: '/estates' })
  }
  slideTimer = setInterval(advanceSlide, 4000)
})

watch(() => countryStore.activeCountry, (newVal, oldVal) => {
  if (oldVal !== null && newVal !== oldVal) {
    currentPage.value = 1
    fetchEstates(true)
    fetchSummary()
  }
})

onUnmounted(() => {
  observer?.disconnect()
  clearInterval(slideTimer)
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

    <!-- ── First-time empty hero ──────────────────────────────────── -->
    <template v-if="isFirstTimeEmpty">
      <div class="flex flex-col items-center justify-center pt-4 pb-12 px-4">

        <!-- Slideshow -->
        <div class="relative w-80 h-52 rounded-2xl overflow-hidden mb-8 shadow-2xl">
          <img
            v-for="(src, i) in ESTATE_SLIDES"
            :key="src"
            :src="src"
            :class="['absolute inset-0 w-full h-full object-cover transition-opacity duration-500', (i === slideIndex && !slideFading) ? 'opacity-100' : 'opacity-0']"
            alt=""
          />
          <!-- Gradient overlay for dot legibility -->
          <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent pointer-events-none"></div>
          <!-- Dot indicators -->
          <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
            <button
              v-for="(_, i) in ESTATE_SLIDES"
              :key="i"
              @click="goToSlide(i)"
              :class="['h-1.5 rounded-full transition-all duration-300 bg-white', i === slideIndex ? 'w-5 opacity-100' : 'w-1.5 opacity-50']"
            />
          </div>
        </div>

        <h2 class="font-body font-bold text-3xl text-foreground mb-3 text-center">
          Build your property portfolio
        </h2>
        <p class="text-sm text-muted-foreground text-center max-w-md mb-8 leading-relaxed">
          Add your first estate to start managing units, tracking revenue, and monitoring occupancy across your entire portfolio.
        </p>

        <AppButton variant="primary" @click="showAddModal = true; resetAddForm()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"/><path d="M12 5v14"/>
          </svg>
          Add Your First Estate
        </AppButton>

        <!-- Feature highlights -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-12 w-full max-w-2xl">
          <div class="p-5 rounded-xl border bg-card text-left">
            <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-primary">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
            <p class="text-sm font-semibold text-foreground mb-1">Manage Units</p>
            <p class="text-xs text-muted-foreground leading-relaxed">Track each unit, tenant and lease in one place.</p>
          </div>
          <div class="p-5 rounded-xl border bg-card text-left">
            <div class="w-9 h-9 rounded-lg bg-accent/10 flex items-center justify-center mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-accent">
                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                <polyline points="16 7 22 7 22 13"/>
              </svg>
            </div>
            <p class="text-sm font-semibold text-foreground mb-1">Track Revenue</p>
            <p class="text-xs text-muted-foreground leading-relaxed">Monitor levies, rent and monthly income.</p>
          </div>
          <div class="p-5 rounded-xl border bg-card text-left">
            <div class="w-9 h-9 rounded-lg bg-success/10 flex items-center justify-center mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-success">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <p class="text-sm font-semibold text-foreground mb-1">Monitor Occupancy</p>
            <p class="text-xs text-muted-foreground leading-relaxed">See vacancies and manage tenant move-ins.</p>
          </div>
        </div>

      </div>
    </template>

    <!-- ── Normal view (has estates, or is loading, or has filters) ── -->
    <template v-else>

      <!-- Summary stat cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
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
            <div v-if="summaryLoading" class="h-8 w-12 rounded bg-muted animate-pulse mx-auto mb-1"></div>
            <p v-else class="text-2xl font-bold font-body text-foreground">{{ summary.vacant }}</p>
            <p class="text-xs text-muted-foreground">Vacant</p>
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

      <!-- Search / Filter / Sort toolbar -->
      <AppTableToolbar
        search-placeholder="Search estates..."
        :filter-fields="ESTATE_FILTER_FIELDS"
        :sort-options="ESTATE_SORT_OPTIONS"
        storage-key="estates-toolbar"
        date-range-context="Created"
        @update:state="onToolbarUpdate"
      />

      <!-- Estate cards grid -->
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

        <!-- No results (search / filter active) -->
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

    </template>

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

        <AppSelect
          v-model="addForm.country"
          label="Country"
          :options="countryOptions"
          placeholder="Select country..."
        />

        <AppInput
          v-if="showLevy"
          label="Default Levy Amount"
          type="number"
          v-model="addForm.defaultLevy"
          placeholder="0.00"
          :prefix="addFormCurrencySymbol"
        />

        <AppInput
          v-if="showRent"
          label="Default Rent Amount"
          type="number"
          v-model="addForm.defaultRent"
          placeholder="0.00"
          :prefix="addFormCurrencySymbol"
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
