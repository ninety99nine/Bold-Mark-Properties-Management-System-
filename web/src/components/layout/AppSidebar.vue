<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCommunityStore } from '@/stores/community'

const route = useRoute()
const auth = useAuthStore()
const community = useCommunityStore()

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'U'
})

// Community switcher state
const isOpen = ref(false)
const searchQuery = ref('')
const switcherRef = ref(null)

const dotColors = { green: '#22c55e', amber: '#D97706', red: '#F75A68' }

const filteredCommunities = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return community.communities
  return community.communities.filter(c =>
    c.name.toLowerCase().includes(q) || c.location.toLowerCase().includes(q)
  )
})

function toggleSwitcher() { isOpen.value = !isOpen.value }

function selectCommunity(id) {
  community.select(id)
  isOpen.value = false
  searchQuery.value = ''
}

function selectAll() {
  community.clearSelection()
  isOpen.value = false
  searchQuery.value = ''
}

function handleClickOutside(e) {
  if (switcherRef.value && !switcherRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

function handleEscape(e) {
  if (e.key === 'Escape') isOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleEscape)
})

// Nav items
const navItems = [
  { name: 'Dashboard', to: '/dashboard', path: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z' },
  { name: 'Communities', to: '/communities', path: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21' },
  { name: 'Owners', to: '/owners', path: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
  { name: 'Levy Billing', to: '/levies', path: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z' },
  { name: 'Debt Management', to: '/debt', path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' },
  { name: 'Financials', to: '/finances', path: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
  { name: 'Compliance', to: '/compliance', path: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' },
  { name: 'Tasks', to: '/tasks', path: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z' },
  { name: 'Communications', to: '/communications', path: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75' },
  { name: 'Documents', to: '/documents', path: 'M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776' },
]

function isActive(to) {
  if (to === '/dashboard') return route.path === '/dashboard' || route.path.startsWith('/dashboard/')
  return route.path.startsWith(to)
}
</script>

<template>
  <aside class="w-64 flex-shrink-0 flex flex-col" style="background-color: #1F3A5C;">

    <!-- Logo -->
    <div class="h-16 flex items-center gap-3 px-5 flex-shrink-0" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
      <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0" style="background-color: #D89B4B; color: #1F3A5C;">B</div>
      <div>
        <span class="text-white font-semibold text-sm tracking-wide leading-none">BoldMark PMS</span>
        <p class="text-xs leading-none mt-0.5" style="color: rgba(255,255,255,0.35);">Management Portal</p>
      </div>
    </div>

    <!-- Community Context Switcher -->
    <div ref="switcherRef" class="px-3 pt-3 pb-2 relative" style="z-index: 50;">

      <!-- Switcher Button -->
      <button
        @click.stop="toggleSwitcher"
        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition-all duration-150 text-left"
        :style="{
          backgroundColor: isOpen ? 'rgba(255,255,255,0.14)' : 'rgba(255,255,255,0.08)',
          border: '1px solid rgba(255,255,255,0.12)',
        }"
        @mouseenter="!isOpen && ($event.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.11)')"
        @mouseleave="!isOpen && ($event.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.08)')"
      >
        <!-- Icon -->
        <div v-if="!community.selected" class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0" style="background-color: rgba(216,155,75,0.2);">
          <svg class="w-3.5 h-3.5" fill="none" stroke="#D89B4B" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
          </svg>
        </div>
        <div v-else class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background-color: #D89B4B;">
          {{ community.selected.name.charAt(0) }}
        </div>

        <!-- Label -->
        <div class="flex-1 min-w-0">
          <p class="text-xs font-semibold leading-none truncate" style="color: rgba(255,255,255,0.9);">
            {{ community.selected?.name || 'All Communities' }}
          </p>
          <p class="text-xs leading-none mt-1 truncate" style="color: rgba(255,255,255,0.4);">
            {{ community.selected ? `${community.selected.units} units` : `${community.communities.length} communities` }}
          </p>
        </div>

        <!-- Chevron -->
        <svg
          class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200"
          :class="{ 'rotate-180': isOpen }"
          fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
      </button>

      <!-- Dropdown -->
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-[0.97] -translate-y-1"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-[0.97] -translate-y-1"
      >
        <div
          v-if="isOpen"
          class="absolute left-3 right-3 mt-1 rounded-xl overflow-hidden"
          style="top: 100%; z-index: 100; background: white; box-shadow: 0 20px 40px rgba(0,0,0,0.2), 0 4px 12px rgba(0,0,0,0.1);"
        >
          <!-- Search -->
          <div class="flex items-center gap-2 px-3.5 py-2.5" style="border-bottom: 1px solid #DCDEE8;">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="#717B99" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              v-model="searchQuery"
              placeholder="Search communities..."
              class="flex-1 text-xs bg-transparent outline-none"
              style="color: #1E2740;"
            />
          </div>

          <!-- All Communities option -->
          <button
            @click="selectAll"
            class="w-full flex items-center gap-3 px-3.5 py-3 text-left transition-colors"
            :style="{ backgroundColor: !community.selected ? '#F8FBFF' : 'transparent' }"
            @mouseenter="community.selected && ($event.currentTarget.style.backgroundColor = '#F8FBFF')"
            @mouseleave="community.selected && ($event.currentTarget.style.backgroundColor = 'transparent')"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: !community.selected ? '#EEF2F8' : '#F5F6FA' }">
              <svg class="w-4 h-4" fill="none" stroke="#1F3A5C" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold leading-none" style="color: #1E2740;">All Communities</p>
              <p class="text-xs mt-1" style="color: #717B99;">Portfolio overview · {{ community.communities.length }} schemes</p>
            </div>
            <svg v-if="!community.selected" class="w-4 h-4 flex-shrink-0" fill="none" stroke="#D89B4B" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
          </button>

          <!-- Divider -->
          <div class="mx-3.5" style="border-top: 1px solid #DCDEE8;" />

          <!-- Community list -->
          <div class="py-1.5 max-h-52 overflow-y-auto">
            <p v-if="filteredCommunities.length === 0" class="px-3.5 py-3 text-xs" style="color: #717B99;">No communities found</p>
            <button
              v-for="c in filteredCommunities"
              :key="c.id"
              @click="selectCommunity(c.id)"
              class="w-full flex items-center gap-3 px-3.5 py-2.5 text-left transition-colors"
              :style="{ backgroundColor: community.selectedId === c.id ? '#F8FBFF' : 'transparent' }"
              @mouseenter="community.selectedId !== c.id && ($event.currentTarget.style.backgroundColor = '#F8FBFF')"
              @mouseleave="community.selectedId !== c.id && ($event.currentTarget.style.backgroundColor = 'transparent')"
            >
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                style="background-color: #1F3A5C;"
              >
                {{ c.name.charAt(0) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold leading-none truncate" style="color: #1E2740;">{{ c.name }}</p>
                <p class="text-xs mt-1 truncate" style="color: #717B99;">{{ c.location }} · {{ c.units }} units</p>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: dotColors[c.complianceStatus] }" />
                <svg v-if="community.selectedId === c.id" class="w-4 h-4" fill="none" stroke="#D89B4B" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
              </div>
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-1 px-2.5 space-y-0.5">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150"
        :style="isActive(item.to)
          ? 'background-color: #D89B4B; color: white;'
          : 'color: rgba(255,255,255,0.55);'"
        @mouseenter="!isActive(item.to) && ($event.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.07)')"
        @mouseleave="!isActive(item.to) && ($event.currentTarget.style.backgroundColor = '')"
      >
        <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" :d="item.path" />
        </svg>
        {{ item.name }}
      </RouterLink>
    </nav>

    <!-- User section -->
    <div class="px-3 py-3 flex-shrink-0" style="border-top: 1px solid rgba(255,255,255,0.08);">
      <div class="flex items-center gap-2.5 px-2 py-2">
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background-color: rgba(216,155,75,0.2); color: #D89B4B;">{{ initials }}</div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium leading-none truncate" style="color: rgba(255,255,255,0.85);">{{ auth.user?.name || 'User' }}</p>
          <p class="text-xs leading-none mt-1 truncate" style="color: rgba(255,255,255,0.35);">{{ auth.user?.role_name || 'Administrator' }}</p>
        </div>
      </div>
    </div>

  </aside>
</template>
