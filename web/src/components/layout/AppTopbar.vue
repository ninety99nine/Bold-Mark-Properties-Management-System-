<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { useCountryStore } from '@/stores/country'
import { useCommunityStore } from '@/stores/community'
import { useOrganizationStore } from '@/stores/organization'
import AppInput from '@/components/common/AppInput.vue'
import CommunitySwitcherDrawer from '@/components/layout/CommunitySwitcherDrawer.vue'
import { entityTypeLabel, entityTypeBadgeClass } from '@/utils/communityEntityType'
import api from '@/composables/useApi'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const notifStore = useNotificationStore()
const countryStore = useCountryStore()
const communityStore = useCommunityStore()
const organizationStore = useOrganizationStore()

// When inside a community, WeConnectU shows the community "CODE - NAME" in the
// topbar (in place of the company logo). Community context is store-driven — the
// same signal the sidebar uses (`selectedId`) — so the label also shows on
// community-scoped pages that live outside /communities/:id (e.g. /age-analysis,
// /customers/manage). Requires the name so we never render an empty label.
const insideCommunity = computed(() =>
  communityStore.selectedId != null && !!communityStore.selected?.name
)
const activeCommunityLabel = computed(() => {
  const c = communityStore.selected
  if (!c) return ''
  return c.code ? `${c.code} - ${c.name}` : c.name
})

// Shared: close every open popover (used before opening a new one).
function closeAllMenus() {
  searchOpen.value = false
  notifOpen.value = false
  userMenuOpen.value = false
  countryOpen.value = false
}

// --- Country Switcher ---
const countryOpen = ref(false)
const countryRef = ref(null)

function toggleCountry() {
  const willOpen = !countryOpen.value
  closeAllMenus()
  countryOpen.value = willOpen
}

// Detail routes that are region-specific → map to their parent list page
const detailRouteParents = {
  'community-detail': 'communities',
  'unit-detail': 'communities',
  'occupant-detail': 'communities',
  'owner-detail': 'communities',
  'invoice-detail': 'billing',
  'cashbook-entry': 'cashbook',
  'user-detail': 'users',
}

function selectCountry(code) {
  countryStore.select(code)
  countryOpen.value = false

  const currentRoute = router.currentRoute.value.name
  const parentRoute = detailRouteParents[currentRoute]
  if (parentRoute) {
    router.push({ name: parentRoute })
  }
}

// --- Notifications (bell) ---
const notifOpen = ref(false)
const notifRef = ref(null)
let notifPollTimer = null

function toggleNotif() {
  const willOpen = !notifOpen.value
  closeAllMenus()
  notifOpen.value = willOpen
}

function handleNotifClick(notif) {
  if (!notif.read_at) notifStore.markAsRead(notif.id)
  if (notif.data?.community_id) router.push(`/communities/${notif.data.community_id}`)
  notifOpen.value = false
}

function handleMarkAllRead() {
  notifStore.markAllAsRead()
}

function timeAgo(dateStr) {
  const now = new Date()
  const date = new Date(dateStr)
  const seconds = Math.floor((now - date) / 1000)
  if (seconds < 60) return 'Just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days === 1) return '1 day ago'
  if (days < 30) return `${days} days ago`
  return date.toLocaleDateString()
}

// --- Search (icon trigger; popover adapts to context) ---
// Inside a community → customer search (WeConnectU parity). Globally → the
// portfolio-wide search across communities / units / people / invoices.
const searchOpen = ref(false)
const searchRef = ref(null)

function toggleSearch() {
  const willOpen = !searchOpen.value
  closeAllMenus()
  searchOpen.value = willOpen
  if (willOpen && insideCommunity.value) {
    nextTick(() => customerInputRef.value?.focus())
  }
}

function closeSearch() {
  searchOpen.value = false
}

// -- Global (portfolio) search --
const searchQuery = ref('')
const searchLoading = ref(false)
let searchDebounce = null
const searchResults = ref({ communities: [], units: [], people: [], invoices: [] })

const hasResults = computed(() =>
  searchResults.value.communities.length > 0 ||
  searchResults.value.units.length > 0 ||
  searchResults.value.people.length > 0 ||
  searchResults.value.invoices.length > 0
)

async function performSearch(query) {
  if (!query || query.trim().length < 1) {
    searchResults.value = { communities: [], units: [], people: [], invoices: [] }
    return
  }
  searchLoading.value = true
  try {
    const { data } = await api.get('/search', { params: { q: query.trim() } })
    searchResults.value = data
  } catch {
    searchResults.value = { communities: [], units: [], people: [], invoices: [] }
  } finally {
    searchLoading.value = false
  }
}

function onSearchInput() {
  searchOpen.value = true
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => performSearch(searchQuery.value), 300)
}

function navigateToCommunity(community) {
  closeSearch(); searchQuery.value = ''
  router.push(`/communities/${community.id}`)
}
function navigateToUnit(unit) {
  closeSearch(); searchQuery.value = ''
  router.push(`/communities/${unit.community_id}/units/${unit.id}`)
}
function navigateToPerson(person) {
  closeSearch(); searchQuery.value = ''
  if ((person.role === 'Owner' || person.role === 'Occupant') && person.community_id && person.unit_id) {
    router.push(`/communities/${person.community_id}/units/${person.unit_id}`)
  } else {
    router.push(`/users/${person.id}`)
  }
}
function navigateToInvoice(invoice) {
  closeSearch(); searchQuery.value = ''
  router.push(`/billing/invoices/${invoice.id}`)
}

// -- Customer search (inside a community) --
const customerQuery = ref('')
const customerResults = ref([])
const customerLoading = ref(false)
const customerSearched = ref(false)
const customerInputRef = ref(null)

async function submitCustomerSearch() {
  const q = customerQuery.value.trim()
  const communityId = communityStore.selectedId
  if (!q || !communityId) return
  customerLoading.value = true
  customerSearched.value = true
  try {
    const { data } = await api.get(`/communities/${communityId}/customers`, {
      params: { _search: q, _per_page: 10 },
    })
    customerResults.value = data.data ?? []
  } catch {
    customerResults.value = []
  } finally {
    customerLoading.value = false
  }
}

function navigateToCustomer(cust) {
  searchOpen.value = false
  customerQuery.value = ''
  customerResults.value = []
  customerSearched.value = false
  router.push({ name: 'owner-detail', params: { ownerId: cust.id } })
}

const communityTypeBadgeClass = entityTypeBadgeClass
const communityTypeLabel = entityTypeLabel

function invoiceStatusClass(status) {
  const map = {
    'paid': 'bg-green-100 text-green-700',
    'unpaid': 'bg-amber-100 text-amber-700',
    'overdue': 'bg-red-100 text-red-700',
    'partially_paid': 'bg-blue-100 text-blue-700',
    'draft': 'bg-muted text-muted-foreground',
  }
  return map[status] || 'bg-muted text-muted-foreground'
}
function invoiceStatusLabel(status) {
  const map = {
    'paid': 'Paid', 'unpaid': 'Unpaid', 'overdue': 'Overdue',
    'partially_paid': 'Partial', 'draft': 'Draft',
  }
  return map[status] || status
}
function formatCurrency(amount) {
  if (amount == null) return '—'
  return countryStore.formatCurrency(amount)
}

// --- Community switcher drawer (house icon) ---
const drawerOpen = ref(false)
function toggleDrawer() {
  closeAllMenus()
  drawerOpen.value = !drawerOpen.value
}

// --- User / profile menu ---
const userMenuOpen = ref(false)
const userMenuRef = ref(null)

function toggleUserMenu() {
  const willOpen = !userMenuOpen.value
  closeAllMenus()
  userMenuOpen.value = willOpen
}

const userInitial = computed(() => {
  const n = auth.user?.name?.trim()
  if (n) return n[0].toUpperCase()
  const e = auth.user?.email?.trim()
  return e ? e[0].toUpperCase() : '?'
})
const roleLabel = computed(() => {
  const r = auth.user?.roles?.[0]?.name
  if (!r) return 'User'
  return r.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
})

function changeProfile() {
  userMenuOpen.value = false
  router.push('/select-profile')
}
function changePassword() {
  userMenuOpen.value = false
  router.push('/settings/password')
}
function handleLogout() {
  userMenuOpen.value = false
  auth.logout()
  router.push('/login')
}

function handleDocumentClick(e) {
  if (userMenuRef.value && !userMenuRef.value.contains(e.target)) userMenuOpen.value = false
  if (notifRef.value && !notifRef.value.contains(e.target)) notifOpen.value = false
  if (searchRef.value && !searchRef.value.contains(e.target)) searchOpen.value = false
  if (countryRef.value && !countryRef.value.contains(e.target)) countryOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick)
  notifStore.fetch()
  countryStore.fetch()
  organizationStore.fetchBranding()
  organizationStore.fetchOrganization()
  notifPollTimer = setInterval(() => notifStore.fetch(), 60000)
})
onUnmounted(() => {
  document.removeEventListener('click', handleDocumentClick)
  if (notifPollTimer) clearInterval(notifPollTimer)
  clearTimeout(searchDebounce)
})
</script>

<template>
  <header class="h-14 border-b border-border bg-card flex items-center justify-between px-6 shrink-0 z-10">

    <!-- Left: Top-Left Icon (always) + company lockup (global) OR community "CODE - NAME" -->
    <div class="flex items-center gap-3 min-w-0">
      <!-- Top-left brand icon uploaded in Settings → Company (the 55×55 "Top Left
           Icon"). Always shown — WeConnectU keeps the icon in both the global and
           community context. Falls back to the built-in emblem when none is set. -->
      <img
        v-if="organizationStore.details?.icon_url"
        :src="organizationStore.details.icon_url"
        alt="Company icon"
        class="h-9 w-9 object-contain rounded shrink-0"
      />
      <img v-else src="/apple-touch-icon.png" alt="Bold Mark Properties" class="h-8 w-8 object-contain shrink-0" />

      <!-- Global: company wordmark (only when no custom icon is set). -->
      <div v-if="!insideCommunity && !organizationStore.details?.icon_url" class="leading-none shrink-0">
        <div class="font-serif text-lg text-accent leading-none">BOLD MARK</div>
        <div class="text-[9px] font-semibold uppercase tracking-[0.3em] text-navy mt-1 leading-none">Properties</div>
      </div>

      <!-- Community context title (shown alongside the icon). -->
      <h1 v-if="insideCommunity" class="font-body text-lg font-bold text-navy truncate">{{ activeCommunityLabel }}</h1>
    </div>

    <!-- Right: action icons (Search · Notifications · Calendar · Communities · Profile) -->
    <div class="flex items-center gap-2">

      <!-- Country switcher — only when communities span multiple countries -->
      <div v-if="countryStore.isMultiCountry" class="relative" ref="countryRef">
        <button
          @click="toggleCountry"
          class="flex items-center gap-2 h-9 px-3 rounded-lg text-sm font-medium transition-colors"
          :class="countryOpen ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
        >
          <span class="text-base leading-none">{{ countryStore.activeCountryInfo?.flag }}</span>
          <span class="hidden sm:inline">{{ countryStore.activeCountryInfo?.name }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 transition-transform" :class="countryOpen ? 'rotate-180' : ''">
            <path d="m6 9 6 6 6-6"/>
          </svg>
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.97] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.97] translate-y-[-4px]"
        >
          <div v-if="countryOpen" class="absolute right-0 top-full mt-1.5 w-56 rounded-lg bg-card border border-border shadow-lg py-1 z-50">
            <p class="px-3 pt-2 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Portfolio Region</p>
            <button
              v-for="c in countryStore.countries"
              :key="c.code"
              @click="selectCountry(c.code)"
              class="w-full flex items-center gap-3 px-3 py-2 text-sm transition-colors text-left"
              :class="c.code === countryStore.activeCountry ? 'bg-primary/5 text-foreground font-medium' : 'text-foreground hover:bg-muted'"
            >
              <span class="text-base leading-none">{{ c.flag }}</span>
              <span class="flex-1">{{ c.name }}</span>
              <span class="w-[20px] text-center text-xs font-medium text-muted-foreground bg-muted px-1.5 py-0.5 rounded-full shrink-0">{{ c.communityCount }}</span>
              <span class="w-3.5 shrink-0 flex items-center justify-center">
                <svg v-if="c.code === countryStore.activeCountry" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-primary">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
            </button>
          </div>
        </Transition>
      </div>

      <!-- Search -->
      <div class="relative" ref="searchRef">
        <button
          type="button"
          @click="toggleSearch"
          class="w-10 h-10 rounded-full inline-flex items-center justify-center transition-colors"
          :class="searchOpen ? 'bg-border text-foreground' : 'bg-muted text-muted-foreground hover:bg-border hover:text-foreground'"
          aria-label="Search"
          title="Search"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
        </button>

        <!-- Customer search popover (inside a community) -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.98] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.98] translate-y-[-4px]"
        >
          <div v-if="searchOpen && insideCommunity" class="absolute right-0 top-full mt-2 w-[560px] max-w-[85vw] rounded-xl bg-card border border-border shadow-xl z-50 p-4">
            <form @submit.prevent="submitCustomerSearch" class="flex items-center gap-3">
              <input
                ref="customerInputRef"
                v-model="customerQuery"
                type="text"
                placeholder="Customer Code, Name, ID, Email or Tel"
                class="flex-1 h-11 px-3.5 rounded-lg border border-border bg-card text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                @keydown.escape="closeSearch"
              />
              <button type="submit" class="h-11 px-5 rounded-lg bg-primary text-primary-foreground text-sm font-medium inline-flex items-center gap-2 hover:bg-navy-light transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                Search
              </button>
            </form>

            <!-- Results -->
            <div v-if="customerLoading" class="py-8 text-center text-sm text-muted-foreground">Searching…</div>
            <div v-else-if="customerSearched && !customerResults.length" class="py-8 text-center text-sm text-muted-foreground">
              No customers match "<span class="text-foreground font-medium">{{ customerQuery }}</span>".
            </div>
            <table v-else-if="customerResults.length" class="w-full mt-4 text-sm">
              <thead>
                <tr class="text-left text-muted-foreground border-b border-border">
                  <th class="py-2 pr-3 font-semibold">Code</th>
                  <th class="py-2 pr-3 font-semibold">Customer</th>
                  <th class="py-2 font-semibold">Reference</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="cust in customerResults"
                  :key="cust.id"
                  @click="navigateToCustomer(cust)"
                  class="border-b border-border last:border-0 cursor-pointer hover:bg-muted transition-colors"
                >
                  <td class="py-2.5 pr-3 text-accent font-medium whitespace-nowrap">{{ cust.code || '—' }}</td>
                  <td class="py-2.5 pr-3 text-foreground">{{ cust.full_name }}</td>
                  <td class="py-2.5 text-muted-foreground">{{ cust.reference || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Transition>

        <!-- Global (portfolio) search popover -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.98] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.98] translate-y-[-4px]"
        >
          <div v-if="searchOpen && !insideCommunity" class="absolute right-0 top-full mt-2 w-[420px] max-w-[85vw] rounded-xl bg-card border border-border shadow-xl z-50 overflow-hidden">
            <div class="p-2 border-b border-border">
              <AppInput v-model="searchQuery" leading-icon="search" placeholder="Search communities, units, people..." size="sm" @input="onSearchInput" @keydown.escape="closeSearch" />
            </div>

            <div v-if="searchQuery" class="flex items-center gap-2 px-3 pt-3 pb-1">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
              </svg>
              <span class="text-xs text-muted-foreground">Results for "<span class="text-foreground font-medium">{{ searchQuery }}</span>"</span>
            </div>
            <div v-else class="px-3 pt-3 pb-1">
              <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Recent &amp; Suggested</p>
            </div>

            <div class="max-h-[420px] overflow-y-auto">
              <div v-if="searchLoading && searchQuery" class="px-4 py-8 text-center">
                <div class="w-5 h-5 border-2 border-primary/30 border-t-primary rounded-full animate-spin mx-auto mb-2"></div>
                <p class="text-xs text-muted-foreground">Searching...</p>
              </div>

              <template v-else-if="searchQuery">
                <div v-if="searchResults.communities.length > 0" class="px-2 pt-2">
                  <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Communities</p>
                  <button v-for="community in searchResults.communities" :key="community.id" @click="navigateToCommunity(community)" class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-primary">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground truncate">{{ community.name }}</p>
                      <p class="text-xs text-muted-foreground">{{ community.units_count }} units</p>
                    </div>
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full flex-shrink-0" :class="communityTypeBadgeClass(community.entity_type)">{{ communityTypeLabel(community.entity_type) }}</span>
                  </button>
                </div>

                <div v-if="searchResults.communities.length > 0 && (searchResults.units.length > 0 || searchResults.people.length > 0 || searchResults.invoices.length > 0)" class="mx-3 my-1.5 h-px bg-border" />

                <div v-if="searchResults.units.length > 0" class="px-2">
                  <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Units</p>
                  <button v-for="unit in searchResults.units" :key="unit.id" @click="navigateToUnit(unit)" class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-600">
                        <rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground">Unit {{ unit.unit_number }}</p>
                      <p class="text-xs text-muted-foreground truncate">{{ unit.owner_name || '—' }} · {{ unit.community_name }}</p>
                    </div>
                  </button>
                </div>

                <div v-if="searchResults.units.length > 0 && (searchResults.people.length > 0 || searchResults.invoices.length > 0)" class="mx-3 my-1.5 h-px bg-border" />

                <div v-if="searchResults.people.length > 0" class="px-2">
                  <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">People</p>
                  <button v-for="person in searchResults.people" :key="`${person.role}-${person.id}`" @click="navigateToPerson(person)" class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-semibold" :class="person.role === 'Occupant' ? 'bg-blue-100 text-blue-700' : (person.role === 'Owner' ? 'bg-green-100 text-green-700' : 'bg-primary/10 text-primary')">
                      {{ person.name.split(' ').map(n => n[0]).join('').slice(0, 2) }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground">{{ person.name }}</p>
                      <p class="text-xs text-muted-foreground truncate">{{ person.context || 'System User' }}</p>
                    </div>
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full flex-shrink-0" :class="person.role === 'Occupant' ? 'bg-blue-100 text-blue-700' : (person.role === 'Owner' ? 'bg-green-100 text-green-700' : 'bg-primary/10 text-primary')">{{ person.role }}</span>
                  </button>
                </div>

                <div v-if="searchResults.people.length > 0 && searchResults.invoices.length > 0" class="mx-3 my-1.5 h-px bg-border" />

                <div v-if="searchResults.invoices.length > 0" class="px-2 pb-2">
                  <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Invoices</p>
                  <button v-for="invoice in searchResults.invoices" :key="invoice.id" @click="navigateToInvoice(invoice)" class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left">
                    <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-purple-600">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground">{{ invoice.invoice_number }}</p>
                      <p class="text-xs text-muted-foreground truncate">{{ invoice.unit_number ? `Unit ${invoice.unit_number}` : '' }}{{ invoice.unit_number && invoice.community_name ? ' · ' : '' }}{{ invoice.community_name || '' }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                      <span class="text-xs font-medium text-foreground">{{ formatCurrency(invoice.amount) }}</span>
                      <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" :class="invoiceStatusClass(invoice.status)">{{ invoiceStatusLabel(invoice.status) }}</span>
                    </div>
                  </button>
                </div>

                <div v-if="!hasResults" class="px-4 py-8 text-center">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40 mx-auto mb-2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                  </svg>
                  <p class="text-sm text-muted-foreground">No results for "<span class="font-medium">{{ searchQuery }}</span>"</p>
                  <p class="text-xs text-muted-foreground mt-0.5">Try a community name, unit number, invoice number, or person</p>
                </div>
              </template>

              <div v-else class="px-4 py-6 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/30 mx-auto mb-2">
                  <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <p class="text-sm text-muted-foreground">Search communities, units, people, invoices...</p>
                <p class="text-xs text-muted-foreground/60 mt-0.5">Start typing to find results</p>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Notifications (bell) -->
      <div class="relative" ref="notifRef">
        <button
          @click="toggleNotif"
          class="relative w-10 h-10 rounded-full inline-flex items-center justify-center transition-colors"
          :class="notifOpen ? 'bg-border text-foreground' : 'bg-muted text-muted-foreground hover:bg-border hover:text-foreground'"
          aria-label="Notifications"
          title="Notifications"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
          </svg>
          <span v-if="notifStore.unreadCount > 0" class="absolute top-0.5 right-0.5 min-w-[16px] h-4 bg-destructive rounded-full ring-2 ring-card text-[10px] font-bold text-white flex items-center justify-center px-1">
            {{ notifStore.unreadCount > 9 ? '9+' : notifStore.unreadCount }}
          </span>
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.97] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.97] translate-y-[-4px]"
        >
          <div v-if="notifOpen" class="absolute right-0 top-full mt-2 w-96 bg-card border border-border rounded-xl shadow-lg z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-border flex items-center justify-between">
              <p class="text-sm font-semibold text-foreground">Notifications</p>
              <button v-if="notifStore.unreadCount > 0" @click="handleMarkAllRead" class="text-xs text-primary hover:underline">Mark all read</button>
            </div>
            <div class="max-h-80 overflow-y-auto">
              <div v-for="notif in notifStore.notifications" :key="notif.id" @click="handleNotifClick(notif)" class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors border-b border-border" :class="!notif.read_at ? 'bg-primary/[0.03]' : ''">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-primary">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm" :class="!notif.read_at ? 'font-semibold text-foreground' : 'font-medium text-foreground'">Billing run completed</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ notif.data?.message }}</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">{{ timeAgo(notif.created_at) }}</p>
                </div>
                <span v-if="!notif.read_at" class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0"></span>
              </div>

              <div v-if="notifStore.notifications.length === 0" class="px-4 py-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40 mx-auto mb-2">
                  <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <p class="text-sm text-muted-foreground">No notifications yet</p>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Calendar (placeholder — wiring deferred) -->
      <button
        type="button"
        class="w-10 h-10 rounded-full inline-flex items-center justify-center bg-muted text-muted-foreground hover:bg-border hover:text-foreground transition-colors"
        aria-label="Calendar"
        title="Calendar (coming soon)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
          <rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M16 2v4"/>
        </svg>
      </button>

      <!-- Communities (house) — opens the switcher drawer -->
      <button
        type="button"
        @click="toggleDrawer"
        class="w-10 h-10 rounded-full inline-flex items-center justify-center transition-colors"
        :class="drawerOpen ? 'bg-border text-foreground' : 'bg-muted text-muted-foreground hover:bg-border hover:text-foreground'"
        aria-label="Switch community"
        title="Communities"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </button>

      <!-- Profile -->
      <div class="relative" ref="userMenuRef">
        <button
          @click="toggleUserMenu"
          class="w-10 h-10 rounded-full bg-primary text-white text-sm font-bold inline-flex items-center justify-center transition-opacity hover:opacity-90"
          aria-label="Account menu"
        >
          {{ userInitial }}
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.97] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.97] translate-y-[-4px]"
        >
          <div v-if="userMenuOpen" class="absolute right-0 top-full mt-2 w-72 rounded-xl bg-card border border-border shadow-xl z-50 overflow-hidden">
            <!-- Identity -->
            <div class="px-4 py-3.5 border-b border-border">
              <p class="text-sm text-muted-foreground">Logged in as <span class="font-semibold text-foreground">{{ roleLabel }}</span></p>
              <p class="text-sm font-bold text-foreground truncate mt-0.5">{{ auth.user?.email }}</p>
            </div>
            <button @click="changeProfile" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted transition-colors border-b border-border">Change Profile</button>
            <button @click="changePassword" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted transition-colors border-b border-border">Change Password</button>
            <button @click="handleLogout" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted transition-colors">Log Out</button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Communities switcher drawer (right slide-over) -->
    <CommunitySwitcherDrawer :show="drawerOpen" @close="drawerOpen = false" />
  </header>
</template>
