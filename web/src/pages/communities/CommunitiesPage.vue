<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import AppButton       from '@/components/common/AppButton.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import AppDropdown     from '@/components/common/AppDropdown.vue'
import AppDropdownItem from '@/components/common/AppDropdownItem.vue'
import AddCommunityModal from '@/components/communities/AddCommunityModal.vue'
import { ENTITY_TYPE_OPTIONS, entityTypeLabel, entityTypeBadgeClass } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'

const router = useRouter()
const route  = useRoute()
const countryStore = useCountryStore()

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  return countryStore.formatCurrency(amount)
}

// The left-hand badge shows the community's code (the same left-hand "code"
// rhythm WeConnectU uses), falling back to initials derived from the name when
// a community has no code yet.
function communityBadge(community) {
  if (community?.code) return community.code.toUpperCase()
  const name = community?.name
  if (!name) return '—'
  const words = name.trim().split(/\s+/).filter(Boolean)
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return (words[0][0] + words[words.length - 1][0]).toUpperCase()
}

// Financial year end is stored as a month (1–12); display it as DD/MM using the
// last day of that month (e.g. June → 30/06, December → 31/12).
const YEAR_END_LAST_DAY = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
function formatYearEnd(month) {
  if (!month || month < 1 || month > 12) return '—'
  const dd = String(YEAR_END_LAST_DAY[month - 1]).padStart(2, '0')
  const mm = String(month).padStart(2, '0')
  return `${dd}/${mm}`
}

// ── Summary stats ─────────────────────────────────────────────────────
const summary = ref({ total_communities: 0, total_units: 0, occupied: 0, vacant: 0, monthly_revenue: 0 })
const summaryLoading = ref(true)

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const params = {}
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    const { data } = await api.get('/communities/summary', { params })
    summary.value = data
  } catch {
    // silent — show zeros
  } finally {
    summaryLoading.value = false
  }
}

// ── Status tabs (Active / Take-on / Suspended — WeConnectU lifecycle) ──
const STATUS_TABS = [
  { value: 'active',    label: 'Active'    },
  { value: 'take_on',   label: 'Take-on'   },
  { value: 'suspended', label: 'Suspended' },
]
const statusFilter = ref('active')
const statusCounts = computed(() => summary.value.status_counts || { active: 0, take_on: 0, suspended: 0 })

function setStatus(value) {
  if (statusFilter.value === value) return
  statusFilter.value = value
  currentPage.value = 1
  fetchCommunities(true)
}

// ── Toolbar state (from AppTableToolbar) ──────────────────────────────
const toolbarState = ref({ search: '', dateRange: 'all_time', filters: {}, sort: null })

function onToolbarUpdate(state) {
  toolbarState.value = state
  currentPage.value = 1
  fetchCommunities(true)
}

const isFiltered = computed(() => {
  const { search, filters, dateRange } = toolbarState.value
  if (search?.trim()) return true
  if (Object.values(filters || {}).some(Boolean)) return true
  if (dateRange && dateRange !== 'all_time') return true
  return false
})

const isFirstTimeEmpty = computed(() =>
  !summaryLoading.value && !listLoading.value && summary.value.total_communities === 0 && !isFiltered.value
)

// ── Toolbar config ────────────────────────────────────────────────────
const COMMUNITY_FILTER_FIELDS = [
  {
    key: 'entity_type',
    label: 'Entity Type',
    options: ENTITY_TYPE_OPTIONS,
  },
  {
    key: 'has_occupants',
    label: 'Occupants',
    options: [
      { value: 'yes', label: 'Has occupants' },
      { value: 'no',  label: 'No occupants'  },
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

const COMMUNITY_SORT_OPTIONS = [
  { value: 'newest',       label: 'Newest first'     },
  { value: 'oldest',       label: 'Oldest first'     },
  { value: 'name_asc',     label: 'Name A–Z'         },
  { value: 'name_desc',    label: 'Name Z–A'         },
  { value: 'units_desc',   label: 'Most units'       },
  { value: 'units_asc',    label: 'Fewest units'     },
  { value: 'revenue_desc', label: 'Highest revenue'  },
  { value: 'revenue_asc',  label: 'Lowest revenue'   },
]

// ── Communities list (infinite scroll) ───────────────────────────────────
const communities = ref([])
const listLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const hasMore = computed(() => currentPage.value < lastPage.value)

async function fetchCommunities(reset = false) {
  if (listLoading.value) return
  listLoading.value = true
  try {
    const { search, dateRange, customStart, customEnd, filters, sort } = toolbarState.value
    const params = { _per_page: 15, page: currentPage.value }
    if (countryStore.activeCountry) params.country = countryStore.activeCountry
    if (statusFilter.value)          params.status           = statusFilter.value

    if (search?.trim())              params._search          = search.trim()
    if (filters?.entity_type)        params.entity_type      = filters.entity_type
    if (filters?.has_occupants)        params.has_occupants      = filters.has_occupants
    if (filters?.occupancy_status)   params.occupancy_status = filters.occupancy_status
    if (sort)                        params._sort            = sort
    if (dateRange && dateRange !== 'all_time') {
      params._date_range = dateRange
      if (dateRange === 'custom') {
        if (customStart) params._date_from = customStart
        if (customEnd)   params._date_to   = customEnd
      }
    }

    const { data } = await api.get('/communities', { params })
    const incoming = data.data ?? []
    communities.value = reset ? incoming : [...communities.value, ...incoming]
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
  await fetchCommunities(false)
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

// ── Add Community modal ──────────────────────────────────────────────────
const showAddModal = ref(false)

// A community was created via the shared modal — open it to finish setup, or
// refresh the list if no id came back.
function onCommunityCreated(community) {
  if (community?.id) {
    // Land on the new community's General settings (WeConnectU flow).
    router.push(`/communities/${community.id}?tab=settings&login=1`)
  } else {
    currentPage.value = 1
    fetchCommunities(true)
    fetchSummary()
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────
onMounted(() => {
  fetchSummary()
  fetchCommunities(true)
  if (route.query.add === '1') {
    showAddModal.value = true
    router.replace({ path: '/communities' })
  }
})

watch(() => countryStore.activeCountry, (newVal, oldVal) => {
  if (oldVal !== null && newVal !== oldVal) {
    currentPage.value = 1
    fetchCommunities(true)
    fetchSummary()
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
        <h1 class="font-body font-bold text-2xl text-foreground">Communities</h1>
        <p class="text-sm text-muted-foreground">Manage your property portfolio</p>
      </div>
      <AppButton variant="primary" @click="showAddModal = true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        Add Community
      </AppButton>
    </div>

    <!-- ── First-time empty state ─────────────────────────────────── -->
    <template v-if="isFirstTimeEmpty">
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="flex flex-col items-center justify-center py-20 px-4 text-center">
          <div class="w-14 h-14 rounded-xl bg-muted flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 text-muted-foreground">
              <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
              <path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/>
              <path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
            </svg>
          </div>
          <h2 class="font-body font-semibold text-lg text-foreground mb-1">No communities yet</h2>
          <p class="text-sm text-muted-foreground max-w-sm mb-6">
            Add your first community to start managing units, tracking revenue, and monitoring occupancy.
          </p>
          <AppButton variant="primary" @click="showAddModal = true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Your First Community
          </AppButton>
        </div>
      </div>
    </template>

    <!-- ── Normal view (has communities, or is loading, or has filters) ── -->
    <template v-else>

      <!-- Search / Filter / Sort toolbar -->
      <AppTableToolbar
        search-placeholder="Search communities..."
        :filter-fields="COMMUNITY_FILTER_FIELDS"
        :sort-options="COMMUNITY_SORT_OPTIONS"
        storage-key="communities-toolbar"
        date-range-context="Created"
        @update:state="onToolbarUpdate"
      />

      <!-- Status tabs (Active / Take-on / Suspended) -->
      <div class="flex items-center gap-6 border-b border-border">
        <button
          v-for="tab in STATUS_TABS"
          :key="tab.value"
          type="button"
          @click="setStatus(tab.value)"
          :class="[
            'relative flex items-center gap-2 pb-2.5 -mb-px text-sm font-medium transition-colors',
            statusFilter === tab.value
              ? 'text-accent border-b-2 border-accent'
              : 'text-muted-foreground hover:text-foreground border-b-2 border-transparent',
          ]"
        >
          <span
            :class="[
              'inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold tabular-nums',
              statusFilter === tab.value ? 'bg-accent/15 text-accent' : 'bg-muted text-muted-foreground',
            ]"
          >{{ statusCounts[tab.value] ?? 0 }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- Community row list (WeConnectU layout) -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">

        <!-- Real data rows -->
        <div class="divide-y divide-border">
          <div
            v-for="community in communities"
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
                <span v-if="community.billing_paused" class="inline-flex items-center gap-1 rounded-full px-2 py-px text-[10px] font-medium border border-accent/20 bg-accent/10 text-accent whitespace-nowrap">
                  <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                  Billing paused
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
                <span class="font-semibold text-foreground">{{ community.vacant_units_count }}</span> vacant
              </p>
            </div>

            <!-- Take-on action (community still being onboarded) -->
            <div v-if="community.status === 'take_on'" class="hidden md:flex items-center shrink-0" @click.stop>
              <button
                type="button"
                @click="router.push(`/communities/${community.id}?tab=settings&login=1`)"
                class="inline-flex items-center gap-1.5 rounded-lg bg-warning/15 px-3 py-2 text-sm font-medium text-amber-dark hover:bg-warning/25 transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                Submit Take-on
              </button>
            </div>

            <!-- Metadata columns -->
            <div v-else class="hidden md:flex items-center gap-6 lg:gap-9 shrink-0">
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
                <p class="font-semibold text-muted-foreground tabular-nums">{{ community.vacant_units_count }}</p>
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

            <!-- Row kebab -->
            <div class="shrink-0" @click.stop>
              <AppDropdown align="right">
                <template #trigger="{ toggle }">
                  <button
                    type="button"
                    class="w-8 h-8 inline-flex items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    @click="toggle"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
                    </svg>
                  </button>
                </template>
                <template #default="{ close }">
                  <AppDropdownItem label="Login" @click="close(); router.push(`/communities/${community.id}?login=1`)" />
                  <AppDropdownItem label="Manage units" @click="close(); router.push(`/communities/${community.id}?tab=units`)" />
                  <AppDropdownItem label="Planner &amp; Compliance" @click="close(); router.push(`/communities/${community.id}?tab=compliance`)" />
                </template>
              </AppDropdown>
            </div>
          </div>
        </div>

        <!-- Skeleton rows (initial load) -->
        <div v-if="listLoading && communities.length === 0" class="divide-y divide-border">
          <div v-for="n in 6" :key="`skel-${n}`" class="flex items-center gap-4 px-5 py-4 animate-pulse">
            <div class="w-11 h-11 rounded-lg bg-muted shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3.5 w-48 rounded bg-muted"></div>
              <div class="h-2.5 w-64 rounded bg-muted"></div>
            </div>
            <div class="hidden md:flex items-center gap-9">
              <div class="h-7 w-10 rounded bg-muted"></div>
              <div class="h-7 w-12 rounded bg-muted"></div>
              <div class="h-7 w-24 rounded bg-muted"></div>
            </div>
            <div class="w-8 h-8 rounded bg-muted shrink-0"></div>
          </div>
        </div>

        <!-- No results (search / filter active) -->
        <div v-if="!listLoading && communities.length === 0" class="py-16 text-center">
          <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
          </div>
          <p class="text-sm font-medium text-foreground mb-1">No communities found</p>
          <p class="text-xs text-muted-foreground">Try adjusting your search or filter.</p>
        </div>

        <!-- Entries count footer -->
        <div v-if="communities.length > 0" class="px-5 py-3 border-t border-border text-xs text-muted-foreground">
          Showing {{ communities.length }}<template v-if="statusCounts[statusFilter]"> of {{ statusCounts[statusFilter] }}</template> {{ statusCounts[statusFilter] === 1 ? 'community' : 'communities' }}
        </div>

      </div>

    </template>

    <!-- Sentinel for infinite scroll -->
    <div ref="sentinelRef" class="h-2"></div>

    <!-- Loading more indicator -->
    <div v-if="listLoading && communities.length > 0" class="flex justify-center py-4">
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        Loading more communities...
      </div>
    </div>

    <!-- ── Add New Community modal (shared component) ──────────────────── -->
    <AddCommunityModal :show="showAddModal" @close="showAddModal = false" @created="onCommunityCreated" />

  </div>
</template>
