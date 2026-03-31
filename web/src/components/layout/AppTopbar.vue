<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { useCommunityStore } from '@/stores/community'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const tenant = useTenantStore()
const community = useCommunityStore()

const pageMap = {
  '/dashboard': 'Dashboard',
  '/communities': 'Communities',
  '/owners': 'Owners',
  '/levies': 'Levy Billing',
  '/debt': 'Debt Management',
  '/finances': 'Financials',
  '/compliance': 'Compliance',
  '/tasks': 'Tasks',
  '/communications': 'Communications',
  '/documents': 'Documents',
}

const currentPage = computed(() => {
  for (const [path, name] of Object.entries(pageMap)) {
    if (route.path === path || route.path.startsWith(path + '/')) return name
  }
  return null
})

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'U'
})

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-16 bg-white flex items-center justify-between px-6 flex-shrink-0" style="border-bottom: 1px solid #DCDEE8;">

    <!-- Left: Company + breadcrumb -->
    <div class="flex items-center gap-2 min-w-0">
      <span class="text-sm font-semibold flex-shrink-0" style="color: #1F3A5C;">{{ tenant.name }}</span>

      <!-- Community context crumb -->
      <template v-if="community.selected">
        <svg class="w-3.5 h-3.5 flex-shrink-0" style="color: #DCDEE8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span
          class="text-sm font-medium truncate max-w-[140px] cursor-pointer transition-opacity hover:opacity-70"
          style="color: #1F3A5C;"
          :title="community.selected.name"
        >{{ community.selected.name }}</span>
      </template>

      <!-- Page crumb -->
      <template v-if="currentPage">
        <svg class="w-3.5 h-3.5 flex-shrink-0" style="color: #DCDEE8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-sm font-medium flex-shrink-0" style="color: #717B99;">{{ currentPage }}</span>
      </template>
    </div>

    <!-- Right: Notifications + user -->
    <div class="flex items-center gap-1">

      <!-- Notification bell -->
      <button
        class="relative p-2 rounded-lg transition-colors"
        style="color: #717B99;"
        @mouseenter="$event.currentTarget.style.backgroundColor = '#EDEFF5'; $event.currentTarget.style.color = '#1E2740'"
        @mouseleave="$event.currentTarget.style.backgroundColor = ''; $event.currentTarget.style.color = '#717B99'"
      >
        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <!-- Notification badge -->
        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full" style="background-color: #F75A68;" />
      </button>

      <!-- Divider -->
      <div class="w-px h-5 mx-2" style="background-color: #DCDEE8;" />

      <!-- User -->
      <div class="flex items-center gap-2.5">
        <!-- Avatar -->
        <div
          class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
          style="background-color: #1F3A5C; color: white;"
        >
          {{ initials }}
        </div>
        <!-- Name -->
        <div class="hidden sm:block">
          <p class="text-sm font-medium leading-none" style="color: #1E2740;">{{ auth.user?.name || 'User' }}</p>
          <p class="text-xs leading-none mt-1" style="color: #717B99;">{{ auth.user?.role_name || 'Administrator' }}</p>
        </div>
        <!-- Divider -->
        <div class="w-px h-5 mx-1" style="background-color: #DCDEE8;" />
        <!-- Logout -->
        <button
          class="text-xs font-medium transition-colors px-2 py-1 rounded"
          style="color: #717B99;"
          @click="handleLogout"
          @mouseenter="$event.currentTarget.style.color = '#F75A68'"
          @mouseleave="$event.currentTarget.style.color = '#717B99'"
        >
          Sign out
        </button>
      </div>

    </div>
  </header>
</template>
