<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import AppButton       from '@/components/common/AppButton.vue'
import AppInput        from '@/components/common/AppInput.vue'
import AppSelect       from '@/components/common/AppSelect.vue'
import AppModal        from '@/components/common/AppModal.vue'
import AppDropdown     from '@/components/common/AppDropdown.vue'
import AppDropdownItem from '@/components/common/AppDropdownItem.vue'
import AddCommunityModal from '@/components/communities/AddCommunityModal.vue'
import CommunityInfoModal from '@/components/communities/CommunityInfoModal.vue'
import { ENTITY_TYPE_OPTIONS } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'

const router = useRouter()
const route  = useRoute()
const countryStore = useCountryStore()

// ── Helpers ───────────────────────────────────────────────────────────
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

// ── Search / Filter / Sort state ──────────────────────────────────────
const search = ref('')

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
      { value: 'fully_occupied',   label: 'Fully occupied'     },
      { value: 'partially_vacant', label: 'Partially occupied' },
      { value: 'fully_vacant',     label: 'Fully vacant'       },
    ],
  },
]

const COMMUNITY_SORT_OPTIONS = [
  { value: 'newest',       label: 'Newest first'    },
  { value: 'oldest',       label: 'Oldest first'    },
  { value: 'name_asc',     label: 'Name A–Z'        },
  { value: 'name_desc',    label: 'Name Z–A'        },
  { value: 'units_desc',   label: 'Most units'      },
  { value: 'units_asc',    label: 'Fewest units'    },
]

const filters   = ref({ entity_type: '', has_occupants: '', occupancy_status: '' })
const sortValue = ref(null)

const activeFilterCount = computed(() =>
  Object.values(filters.value).filter(v => v !== null && v !== '').length
)

const isFiltered = computed(() =>
  !!search.value.trim() ||
  Object.values(filters.value).some(Boolean) ||
  !!sortValue.value
)

const isFirstTimeEmpty = computed(() =>
  !summaryLoading.value && !listLoading.value && summary.value.total_communities === 0 && !isFiltered.value
)

// ── Filter modal (opened via the header sliders icon) ─────────────────
const showFilterModal = ref(false)
const tempFilters = ref({})

function openFilterModal() {
  tempFilters.value = { ...filters.value }
  showFilterModal.value = true
}
function applyFilters() {
  filters.value = { ...tempFilters.value }
  showFilterModal.value = false
  currentPage.value = 1
  fetchCommunities(true)
}
function clearFilters() {
  tempFilters.value = {}
  filters.value = { entity_type: '', has_occupants: '', occupancy_status: '' }
  showFilterModal.value = false
  currentPage.value = 1
  fetchCommunities(true)
}

// ── Sort (opened via the tab-row sort icon dropdown) ──────────────────
function applySort(val) {
  sortValue.value = sortValue.value === val ? null : val
  currentPage.value = 1
  fetchCommunities(true)
}

// ── CSV export (header download icon + kebab menu) ────────────────────
function exportCsv() {
  const header = ['Code', 'Name', 'Address', 'Units', 'Year end']
  const escape = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const lines = [header.join(',')]
  for (const c of communities.value) {
    lines.push([
      communityBadge(c),
      c.name,
      c.address || '',
      c.units_count ?? 0,
      formatYearEnd(c.financial_year_end_month),
    ].map(escape).join(','))
  }
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'communities.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function refreshList() {
  currentPage.value = 1
  fetchCommunities(true)
  fetchSummary()
}

// ── Communities list (infinite scroll) ────────────────────────────────
const communities = ref([])
const listLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const hasMore = computed(() => currentPage.value < lastPage.value)

async function fetchCommunities(reset = false) {
  if (listLoading.value) return
  listLoading.value = true
  try {
    const params = { _per_page: 15, page: currentPage.value }
    if (countryStore.activeCountry)     params.country          = countryStore.activeCountry
    if (statusFilter.value)             params.status           = statusFilter.value
    if (search.value.trim())            params._search          = search.value.trim()
    if (filters.value.entity_type)      params.entity_type      = filters.value.entity_type
    if (filters.value.has_occupants)    params.has_occupants    = filters.value.has_occupants
    if (filters.value.occupancy_status) params.occupancy_status = filters.value.occupancy_status
    if (sortValue.value)                params._sort            = sortValue.value

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

// Debounced search — refetch 350ms after the user stops typing.
let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchCommunities(true)
  }, 350)
})

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

// ── Add / Edit Community modal ────────────────────────────────────────
const showCommunityModal = ref(false)
const editingCommunity   = ref(null)   // null → Add mode; object → Edit mode

function openAdd() {
  editingCommunity.value = null
  showCommunityModal.value = true
}

// Row click → WeConnectU-style community info modal (Information / Admin Charges
// / Bank Details / Roles / Trustees + Go to Settings / Login).
const showInfoModal = ref(false)
const infoCommunity = ref(null)
function openInfo(community) {
  infoCommunity.value = community
  showInfoModal.value = true
}

function openEdit(community) {
  editingCommunity.value = community
  showCommunityModal.value = true
}

function closeCommunityModal() {
  showCommunityModal.value = false
  editingCommunity.value = null
}

// "Login" — enter the community in a NEW TAB (WeConnectU behaviour).
function loginToCommunity(community) {
  const href = router.resolve({ path: `/communities/${community.id}`, query: { login: '1' } }).href
  window.open(href, '_blank')
}

// Login pressed from inside the Edit modal — open the tab and close the modal.
function onModalLogin(community) {
  loginToCommunity(community)
  closeCommunityModal()
}

// A newly created community starts in "take_on" status, so jump to that tab
// (WeConnectU parity) — otherwise it lands out of view under the Active filter.
function onCommunityCreated() {
  statusFilter.value = 'take_on'
  refreshList()
}

// An edit keeps the user on whatever tab they were viewing.
function onCommunityUpdated() {
  refreshList()
}

// ── Delete Community (confirmation modal) ─────────────────────────────
const showDeleteModal   = ref(false)
const deletingCommunity = ref(null)
const deleteLoading     = ref(false)
const deleteError       = ref('')

function openDelete(community) {
  deletingCommunity.value = community
  deleteError.value = ''
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deleteLoading.value) return
  showDeleteModal.value = false
  deletingCommunity.value = null
}

async function confirmDelete() {
  if (!deletingCommunity.value) return
  deleteLoading.value = true
  deleteError.value = ''
  try {
    await api.delete(`/communities/${deletingCommunity.value.id}`)
    showDeleteModal.value = false
    deletingCommunity.value = null
    refreshList()
  } catch (e) {
    deleteError.value = e.response?.data?.message || 'Failed to delete community. Please try again.'
  } finally {
    deleteLoading.value = false
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────
onMounted(() => {
  fetchSummary()
  fetchCommunities(true)
  if (route.query.add === '1') {
    openAdd()
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
  clearTimeout(searchTimer)
})
</script>

<template>
  <div class="space-y-5 pb-8">

    <!-- ── Page heading + action icons ────────────────────────────── -->
    <div class="flex items-center justify-between gap-3">
      <h1 class="font-body font-bold text-2xl text-foreground">Communities</h1>

      <div class="flex items-center gap-2">
        <!-- More (kebab) -->
        <AppDropdown align="right">
          <template #trigger="{ toggle }">
            <button
              type="button"
              @click="toggle"
              class="w-10 h-10 inline-flex items-center justify-center rounded-lg bg-muted text-muted-foreground hover:bg-border hover:text-foreground transition-colors"
              title="More actions"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
              </svg>
            </button>
          </template>
          <template #default="{ close }">
            <AppDropdownItem label="Refresh list" @click="close(); refreshList()" />
            <AppDropdownItem label="Export to CSV" @click="close(); exportCsv()" />
          </template>
        </AppDropdown>

        <!-- Download / export -->
        <button
          type="button"
          @click="exportCsv"
          class="w-10 h-10 inline-flex items-center justify-center rounded-lg bg-muted text-muted-foreground hover:bg-border hover:text-foreground transition-colors"
          title="Export communities"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
          </svg>
        </button>

        <!-- Filter (sliders) -->
        <button
          type="button"
          @click="openFilterModal"
          class="relative w-10 h-10 inline-flex items-center justify-center rounded-lg bg-muted text-muted-foreground hover:bg-border hover:text-foreground transition-colors"
          title="Filter communities"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/>
            <line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/>
            <line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/>
            <line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>
          </svg>
          <span
            v-if="activeFilterCount > 0"
            class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full text-[10px] font-bold bg-accent text-accent-foreground leading-none"
          >{{ activeFilterCount }}</span>
        </button>

        <!-- Add -->
        <AppButton variant="primary" @click="openAdd">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"/><path d="M12 5v14"/>
          </svg>
          Add
        </AppButton>
      </div>
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
          <AppButton variant="primary" @click="openAdd">
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

      <!-- Status tabs (left)  +  sort / search (right) -->
      <div class="flex items-end justify-between gap-4 border-b border-border">
        <!-- Tabs -->
        <div class="flex items-center gap-6">
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

        <!-- Sort + search -->
        <div class="flex items-center gap-2 pb-2">
          <AppDropdown align="right" width="w-52">
            <template #trigger="{ toggle }">
              <button
                type="button"
                @click="toggle"
                :class="[
                  'w-9 h-9 inline-flex items-center justify-center rounded-md border transition-colors',
                  sortValue
                    ? 'border-accent/40 bg-accent/10 text-accent'
                    : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
                title="Sort"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="M20 8h-5"/><path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10"/><path d="M15 14h5l-5 6h5"/>
                </svg>
              </button>
            </template>
            <template #default="{ close }">
              <div class="py-1 max-h-72 overflow-y-auto">
                <button
                  v-for="opt in COMMUNITY_SORT_OPTIONS"
                  :key="opt.value"
                  type="button"
                  @click="applySort(opt.value); close()"
                  :class="[
                    'w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left transition-colors',
                    sortValue === opt.value ? 'text-accent font-medium bg-accent/8' : 'text-foreground hover:bg-muted',
                  ]"
                >
                  {{ opt.label }}
                  <svg v-if="sortValue === opt.value" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                </button>
              </div>
            </template>
          </AppDropdown>

          <div class="w-56">
            <AppInput
              v-model="search"
              size="sm"
              leading-icon="search"
              placeholder="Search communities..."
            />
          </div>
        </div>
      </div>

      <!-- Community rows (WeConnectU card rhythm) -->
      <div class="space-y-3">
        <div
          v-for="community in communities"
          :key="community.id"
          @click="openInfo(community)"
          class="group flex items-center gap-4 border border-border bg-card px-4 sm:px-5 py-4 cursor-pointer transition-all hover:border-accent/60 hover:bg-muted/30"
        >
          <!-- Code badge -->
          <div class="shrink-0">
            <span class="inline-flex items-center justify-center min-w-16 rounded-md bg-muted px-2.5 py-2 text-xs font-bold tracking-wide text-muted-foreground">
              {{ communityBadge(community) }}
            </span>
          </div>

          <!-- Name + address -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-body font-bold text-foreground uppercase tracking-tight truncate">{{ community.name }}</p>
              <span v-if="community.billing_paused" class="inline-flex items-center gap-1 rounded-full px-2 py-px text-[10px] font-medium border border-accent/20 bg-accent/10 text-accent whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                Billing paused
              </span>
            </div>
            <p class="text-sm text-muted-foreground truncate mt-0.5">{{ community.address || 'No address on record' }}</p>
            <!-- Compact meta (mobile only) -->
            <p class="md:hidden text-xs text-muted-foreground mt-1">
              <span class="font-semibold text-foreground">{{ community.units_count }}</span> units ·
              Year end <span class="font-semibold text-foreground">{{ formatYearEnd(community.financial_year_end_month) }}</span>
            </p>
          </div>

          <!-- Take-on action (community still being onboarded) -->
          <div v-if="community.status === 'take_on'" class="hidden md:flex items-center shrink-0" @click.stop>
            <button
              type="button"
              @click="router.push(`/communities/${community.id}/take-on`)"
              class="inline-flex items-center gap-1.5 rounded-lg bg-warning/15 px-3 py-2 text-sm font-medium text-amber-dark hover:bg-warning/25 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
              Submit Take-on
            </button>
          </div>

          <!-- Metadata columns (Units / Year end / Community Manager) -->
          <div v-else class="hidden md:flex items-center gap-8 lg:gap-12 shrink-0">
            <div class="w-14">
              <p class="text-sm text-muted-foreground">Units</p>
              <p class="text-sm font-semibold text-foreground tabular-nums mt-0.5">{{ community.units_count }}</p>
            </div>
            <div class="w-16">
              <p class="text-sm text-muted-foreground">Year end</p>
              <p class="text-sm font-semibold text-foreground tabular-nums mt-0.5">{{ formatYearEnd(community.financial_year_end_month) }}</p>
            </div>
            <div class="w-48">
              <p class="text-sm text-muted-foreground">Community Manager</p>
              <p
                :class="['text-sm mt-0.5 truncate', community.community_manager ? 'font-semibold text-foreground' : 'text-muted-foreground/80']"
              >{{ community.community_manager?.name || community.community_manager?.email || 'No community manager setup' }}</p>
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
                <AppDropdownItem label="Edit" @click="close(); openEdit(community)" />
                <AppDropdownItem label="Login" @click="close(); loginToCommunity(community)" />
                <AppDropdownItem divider />
                <AppDropdownItem label="Delete" variant="danger" @click="close(); openDelete(community)" />
              </template>
            </AppDropdown>
          </div>
        </div>

        <!-- Skeleton rows (initial load) -->
        <template v-if="listLoading && communities.length === 0">
          <div v-for="n in 6" :key="`skel-${n}`" class="flex items-center gap-4 border border-border bg-card px-5 py-4 animate-pulse">
            <div class="w-16 h-9 rounded-md bg-muted shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3.5 w-48 rounded bg-muted"></div>
              <div class="h-2.5 w-64 rounded bg-muted"></div>
            </div>
            <div class="hidden md:flex items-center gap-12">
              <div class="h-8 w-12 rounded bg-muted"></div>
              <div class="h-8 w-14 rounded bg-muted"></div>
              <div class="h-8 w-40 rounded bg-muted"></div>
            </div>
            <div class="w-8 h-8 rounded bg-muted shrink-0"></div>
          </div>
        </template>

        <!-- No results (search / filter active) -->
        <div v-if="!listLoading && communities.length === 0" class="border border-border bg-card py-16 text-center">
          <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
          </div>
          <p class="text-sm font-medium text-foreground mb-1">No communities found</p>
          <p class="text-xs text-muted-foreground">Try adjusting your search or filter.</p>
        </div>
      </div>

      <!-- Entries count footer -->
      <div v-if="communities.length > 0" class="text-xs text-muted-foreground px-1">
        Showing {{ communities.length }}<template v-if="statusCounts[statusFilter]"> of {{ statusCounts[statusFilter] }}</template> {{ statusCounts[statusFilter] === 1 ? 'community' : 'communities' }}
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

    <!-- ── Filter modal (header sliders icon) ─────────────────────────── -->
    <AppModal :show="showFilterModal" title="Filter Communities" @close="showFilterModal = false">
      <div class="space-y-4">
        <AppSelect
          v-for="field in COMMUNITY_FILTER_FIELDS"
          :key="field.key"
          v-model="tempFilters[field.key]"
          :label="field.label"
          :options="[{ value: '', label: `Any ${field.label}` }, ...field.options]"
          :placeholder="`Any ${field.label}`"
        />
      </div>
      <template #footer>
        <AppButton variant="outline" @click="clearFilters">Clear Filters</AppButton>
        <AppButton variant="primary" @click="applyFilters">Apply Filters</AppButton>
      </template>
    </AppModal>

    <!-- ── Add / Edit Community modal (WeConnectU-style) ───────────────── -->
    <AddCommunityModal
      :show="showCommunityModal"
      :community="editingCommunity"
      @close="closeCommunityModal"
      @created="onCommunityCreated"
      @updated="onCommunityUpdated"
      @login="onModalLogin"
    />

    <!-- ── Community info modal (row click) ───────────────────────────── -->
    <CommunityInfoModal
      :show="showInfoModal"
      :community="infoCommunity"
      @close="showInfoModal = false"
    />

    <!-- ── Delete confirmation modal ──────────────────────────────────── -->
    <AppModal :show="showDeleteModal" size="sm" @close="closeDeleteModal">
      <template #header>
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-destructive/10 text-destructive shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
            </svg>
          </span>
          <h3 class="text-base font-bold text-fg" style="font-family: 'DM Sans', sans-serif">Delete Community</h3>
        </div>
      </template>

      <p class="text-sm text-muted-foreground">
        Are you sure you want to delete
        <span class="font-semibold text-foreground">{{ deletingCommunity?.name }}</span>?
        This action cannot be undone and will remove all associated data.
      </p>

      <p v-if="deleteError" class="mt-3 text-sm text-destructive">{{ deleteError }}</p>

      <template #footer>
        <AppButton variant="outline" :disabled="deleteLoading" @click="closeDeleteModal">Cancel</AppButton>
        <AppButton variant="danger" :loading="deleteLoading" @click="confirmDelete">Delete</AppButton>
      </template>
    </AppModal>

  </div>
</template>
