<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppInput from '@/components/common/AppInput.vue'

const router = useRouter()
const auth = useAuthStore()

const userMenuOpen = ref(false)
const userMenuRef = ref(null)

const notifOpen = ref(false)
const notifRef = ref(null)

function toggleNotif() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) userMenuOpen.value = false
}

// --- Global Search ---
const searchQuery = ref('')
const searchOpen = ref(false)
const searchRef = ref(null)

// Sample data — in production this would come from the API
const sampleEstates = [
  { id: 1, name: 'Crystal Mews Body Corporate', type: 'Sectional Title', units: 47 },
  { id: 2, name: 'King Arthur BC', type: 'Mixed', units: 32 },
  { id: 3, name: 'Lyndhurst Estate', type: 'Residential', units: 28 },
  { id: 4, name: 'Gaborone Residences', type: 'Residential', units: 20 },
  { id: 5, name: 'Sandton Heights', type: 'Commercial', units: 15 },
]

const sampleUnits = [
  { id: 1, number: 'A01', estate: 'Crystal Mews BC', owner: 'Sarah van der Merwe' },
  { id: 2, number: 'A02', estate: 'Crystal Mews BC', owner: 'Michael Ndaba' },
  { id: 3, number: 'B04', estate: 'Crystal Mews BC', owner: 'Anele Zulu' },
  { id: 4, number: 'C12', estate: 'King Arthur BC', owner: 'Johan Pretorius' },
  { id: 5, number: '5A', estate: 'Lyndhurst Estate', owner: 'Thandi Dlamini' },
]

const samplePeople = [
  { id: 1, name: 'Sarah van der Merwe', role: 'Owner', unit: 'A01 · Crystal Mews BC' },
  { id: 2, name: 'Michael Ndaba', role: 'Owner', unit: 'A02 · Crystal Mews BC' },
  { id: 3, name: 'Lisa Mokoena', role: 'Tenant', unit: 'A02 · Crystal Mews BC' },
  { id: 4, name: 'Johan Pretorius', role: 'Owner', unit: 'C12 · King Arthur BC' },
  { id: 5, name: 'Rachel Naidoo', role: 'Tenant', unit: 'B04 · Crystal Mews BC' },
  { id: 6, name: 'Justin Mokoena', role: 'Admin', unit: null },
]

const q = computed(() => searchQuery.value.trim().toLowerCase())

const filteredEstates = computed(() =>
  q.value.length < 1 ? sampleEstates.slice(0, 3) : sampleEstates.filter(e => e.name.toLowerCase().includes(q.value)).slice(0, 4)
)

const filteredUnits = computed(() =>
  q.value.length < 1 ? sampleUnits.slice(0, 3) : sampleUnits.filter(u =>
    u.number.toLowerCase().includes(q.value) ||
    u.owner.toLowerCase().includes(q.value) ||
    u.estate.toLowerCase().includes(q.value)
  ).slice(0, 4)
)

const filteredPeople = computed(() =>
  q.value.length < 1 ? samplePeople.slice(0, 3) : samplePeople.filter(p =>
    p.name.toLowerCase().includes(q.value) ||
    p.role.toLowerCase().includes(q.value)
  ).slice(0, 4)
)

const hasResults = computed(() =>
  filteredEstates.value.length > 0 || filteredUnits.value.length > 0 || filteredPeople.value.length > 0
)

function onSearchFocus() {
  searchOpen.value = true
}

function onSearchInput() {
  searchOpen.value = true
}

function closeSearch() {
  searchOpen.value = false
}

function navigateToEstate(estate) {
  closeSearch()
  searchQuery.value = ''
  router.push(`/estates/${estate.id}`)
}

function navigateToUnit(unit) {
  closeSearch()
  searchQuery.value = ''
  router.push(`/estates/${unit.id}/units/${unit.id}`)
}

function navigateToPerson(person) {
  closeSearch()
  searchQuery.value = ''
}

function estateTypeBadgeClass(type) {
  const map = {
    'Sectional Title': 'bg-primary/10 text-primary',
    'Mixed': 'bg-[#717B99]/10 text-[#717B99]',
    'Residential': 'bg-green-100 text-green-700',
    'Commercial': 'bg-amber-100 text-amber-700',
  }
  return map[type] || 'bg-muted text-muted-foreground'
}

// --- User menu ---
function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value
}

function goToSettings() {
  userMenuOpen.value = false
  router.push('/settings')
}

function handleLogout() {
  userMenuOpen.value = false
  auth.logout()
  router.push('/login')
}

function handleDocumentClick(e) {
  if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
    userMenuOpen.value = false
  }
  if (notifRef.value && !notifRef.value.contains(e.target)) {
    notifOpen.value = false
  }
  if (searchRef.value && !searchRef.value.contains(e.target)) {
    searchOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleDocumentClick))
onUnmounted(() => document.removeEventListener('click', handleDocumentClick))
</script>

<template>
  <header class="h-14 border-b border-border bg-card flex items-center justify-between px-6 shrink-0 z-10">

    <!-- Left: Global search -->
    <div class="flex items-center gap-3">
      <div class="relative w-80" ref="searchRef">
        <AppInput
          v-model="searchQuery"
          leading-icon="search"
          placeholder="Search estates, units, people..."
          size="sm"
          @focus="onSearchFocus"
          @input="onSearchInput"
          @keydown.escape="closeSearch"
        />

        <!-- Search dropdown -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.98] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.98] translate-y-[-4px]"
        >
          <div
            v-if="searchOpen"
            class="absolute left-0 top-full mt-1.5 w-[420px] rounded-xl bg-card border border-border shadow-xl z-50 overflow-hidden"
          >
            <!-- Query hint row -->
            <div v-if="searchQuery" class="flex items-center gap-2 px-3 pt-3 pb-1">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
              </svg>
              <span class="text-xs text-muted-foreground">Results for "<span class="text-foreground font-medium">{{ searchQuery }}</span>"</span>
            </div>
            <div v-else class="px-3 pt-3 pb-1">
              <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Recent & Suggested</p>
            </div>

            <div class="max-h-[420px] overflow-y-auto">

              <!-- Estates section -->
              <div v-if="filteredEstates.length > 0" class="px-2 pt-2">
                <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Estates</p>
                <button
                  v-for="estate in filteredEstates"
                  :key="estate.id"
                  @click="navigateToEstate(estate)"
                  class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left group"
                >
                  <!-- Icon -->
                  <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-primary">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                  </div>
                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-foreground truncate">{{ estate.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ estate.units }} units</p>
                  </div>
                  <!-- Badge -->
                  <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full flex-shrink-0" :class="estateTypeBadgeClass(estate.type)">
                    {{ estate.type }}
                  </span>
                </button>
              </div>

              <!-- Divider -->
              <div v-if="filteredEstates.length > 0 && (filteredUnits.length > 0 || filteredPeople.length > 0)" class="mx-3 my-1.5 h-px bg-border" />

              <!-- Units section -->
              <div v-if="filteredUnits.length > 0" class="px-2">
                <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Units</p>
                <button
                  v-for="unit in filteredUnits"
                  :key="unit.id"
                  @click="navigateToUnit(unit)"
                  class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left"
                >
                  <!-- Icon -->
                  <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-600">
                      <rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
                    </svg>
                  </div>
                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-foreground">Unit {{ unit.number }}</p>
                    <p class="text-xs text-muted-foreground truncate">{{ unit.owner }} · {{ unit.estate }}</p>
                  </div>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground opacity-0 group-hover:opacity-100 flex-shrink-0">
                    <path d="m9 18 6-6-6-6"/>
                  </svg>
                </button>
              </div>

              <!-- Divider -->
              <div v-if="filteredUnits.length > 0 && filteredPeople.length > 0" class="mx-3 my-1.5 h-px bg-border" />

              <!-- People section -->
              <div v-if="filteredPeople.length > 0" class="px-2 pb-2">
                <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">People</p>
                <button
                  v-for="person in filteredPeople"
                  :key="person.id"
                  @click="navigateToPerson(person)"
                  class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-muted transition-colors text-left"
                >
                  <!-- Avatar -->
                  <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-semibold"
                    :class="person.role === 'Tenant' ? 'bg-blue-100 text-blue-700' : person.role === 'Admin' ? 'bg-primary/10 text-primary' : 'bg-green-100 text-green-700'"
                  >
                    {{ person.name.split(' ').map(n => n[0]).join('').slice(0, 2) }}
                  </div>
                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-foreground">{{ person.name }}</p>
                    <p class="text-xs text-muted-foreground truncate">{{ person.unit || 'System User' }}</p>
                  </div>
                  <!-- Role badge -->
                  <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full flex-shrink-0"
                    :class="person.role === 'Tenant' ? 'bg-blue-100 text-blue-700' : person.role === 'Admin' ? 'bg-primary/10 text-primary' : 'bg-green-100 text-green-700'"
                  >
                    {{ person.role }}
                  </span>
                </button>
              </div>

              <!-- Empty state -->
              <div v-if="!hasResults" class="px-4 py-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40 mx-auto mb-2">
                  <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <p class="text-sm text-muted-foreground">No results for "<span class="font-medium">{{ searchQuery }}</span>"</p>
                <p class="text-xs text-muted-foreground mt-0.5">Try searching for an estate name, unit number, or person</p>
              </div>

            </div>

            <!-- Footer hint -->
            <div class="border-t border-border px-3 py-2 flex items-center justify-between bg-muted/40">
              <span class="text-[10px] text-muted-foreground">Press <kbd class="px-1 py-0.5 rounded bg-border text-[10px] font-mono">↵</kbd> to search all</span>
              <span class="text-[10px] text-muted-foreground">ESC to close</span>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Right: Notifications + User -->
    <div class="flex items-center gap-2">

      <!-- Notification bell -->
      <div class="relative" ref="notifRef">
        <button
          @click="toggleNotif"
          class="relative w-9 h-9 rounded-lg flex items-center justify-center hover:bg-muted transition-colors"
          :class="notifOpen ? 'bg-muted' : ''"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="w-[18px] h-[18px] text-muted-foreground"
          >
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
          </svg>
          <!-- Badge -->
          <span class="absolute top-1 right-1 min-w-[16px] h-4 bg-destructive rounded-full ring-2 ring-card text-[10px] font-bold text-white flex items-center justify-center px-1">
            3
          </span>
        </button>

        <!-- Notifications dropdown -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.97] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.97] translate-y-[-4px]"
        >
          <div
            v-if="notifOpen"
            class="absolute right-0 top-full mt-2 w-96 bg-card border border-border rounded-xl shadow-lg z-50 overflow-hidden"
          >
            <!-- Header -->
            <div class="px-4 py-3 border-b border-border flex items-center justify-between">
              <p class="text-sm font-semibold text-foreground">Notifications</p>
              <button class="text-xs text-primary hover:underline">Mark all read</button>
            </div>
            <!-- Items -->
            <div class="max-h-80 overflow-y-auto">
              <!-- Invoice overdue (unread) -->
              <div class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors border-b border-border bg-primary/[0.03]">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-destructive">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-foreground">Invoice overdue</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">INV-2026-0002 — Michael Ndaba owes R 2 850</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">2 hours ago</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0"></span>
              </div>
              <!-- Payment received (unread) -->
              <div class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors border-b border-border bg-primary/[0.03]">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-success">
                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-foreground">Payment received</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">Sarah van der Merwe paid R 2 850 for Levy</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">3 hours ago</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0"></span>
              </div>
              <!-- Unallocated payment (unread) -->
              <div class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors border-b border-border bg-primary/[0.03]">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-warning">
                    <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-foreground">Unallocated payment</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">EFT ref 8827 — R 2 850 not linked to any invoice</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">5 hours ago</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0"></span>
              </div>
              <!-- Payment received (read) -->
              <div class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors border-b border-border">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-success">
                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-foreground">Payment received</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">Lisa Mokoena paid R 9 500 for Rent</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">1 day ago</p>
                </div>
              </div>
              <!-- 3 invoices overdue (read) -->
              <div class="flex gap-3 px-4 py-3 hover:bg-muted/50 cursor-pointer transition-colors">
                <div class="mt-0.5 shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-destructive">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-foreground">3 invoices overdue</p>
                  <p class="text-xs text-muted-foreground mt-0.5 truncate">Crystal Mews — Johan Pretorius, Thandi Dlamini, Anele Zulu</p>
                  <p class="text-[11px] text-muted-foreground/60 mt-1">1 day ago</p>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Divider -->
      <div class="w-px h-8 bg-border mx-1"></div>

      <!-- User profile -->
      <div class="relative" ref="userMenuRef">
        <button
          @click="toggleUserMenu"
          class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-muted transition-colors"
        >
          <!-- Avatar -->
          <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
            <svg
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
              class="w-4 h-4 text-white"
            >
              <circle cx="12" cy="8" r="5"/>
              <path d="M20 21a8 8 0 0 0-16 0"/>
            </svg>
          </div>
          <!-- Name + role -->
          <div class="text-left">
            <p class="text-sm font-medium text-foreground leading-tight">
              {{ auth.user?.name?.split(' ')[0] || 'Justin' }}
            </p>
            <p class="text-[11px] text-muted-foreground leading-tight">
              {{ auth.user?.role_name || 'Company Admin' }}
            </p>
          </div>
          <!-- Chevron -->
          <svg
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="w-3.5 h-3.5 text-muted-foreground ml-1 transition-transform"
            :class="userMenuOpen ? 'rotate-180' : ''"
          >
            <path d="m6 9 6 6 6-6"/>
          </svg>
        </button>

        <!-- Dropdown menu -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-[0.97] translate-y-[-4px]"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-[0.97] translate-y-[-4px]"
        >
          <div
            v-if="userMenuOpen"
            class="absolute right-0 top-full mt-1.5 w-48 rounded-lg bg-card border border-border shadow-lg py-1 z-50"
          >
            <button
              @click="goToSettings"
              class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors text-left"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              Account Settings
            </button>
            <div class="h-px bg-border mx-2 my-1" />
            <button
              @click="handleLogout"
              class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-destructive hover:bg-destructive/5 transition-colors text-left"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" x2="9" y1="12" y2="12"/>
              </svg>
              Log Out
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>
