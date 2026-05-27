<script setup>
import { ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'

const route = useRoute()
const collapsed = ref(false)

// Track which parent menus are expanded
const expandedParents = ref({ portfolio: true, collections: true })

function toggleParent(key) {
  expandedParents.value[key] = !expandedParents.value[key]
}

// Main nav items — matches CLAUDE.md Phase 1 routes
// Items with `children` are collapsible parent menus
const navItems = [
  {
    name: 'Home',
    to: '/dashboard',
    icon: 'house',
  },
  {
    name: 'Portfolio',
    icon: 'building',
    key: 'portfolio',
    children: [
      { name: 'Estates', to: '/estates', icon: 'grid' },
      { name: 'Vacancies', to: '/vacancies', icon: 'door-open', badge: 'new' },
    ],
  },
  {
    name: 'Billing',
    to: '/billing',
    icon: 'file-text',
  },
  {
    name: 'Cashbook',
    to: '/cashbook',
    icon: 'wallet',
  },
  {
    name: 'Collections',
    icon: 'shield-alert',
    key: 'collections',
    children: [
      { name: 'Arrears', to: '/arrears', icon: 'alert-circle', badge: 'new' },
      { name: 'Age Analysis', to: '/age-analysis', icon: 'trending-down' },
    ],
  },
  {
    name: 'Compliance',
    to: '/compliance',
    icon: 'clipboard-check',
    badge: 'soon',
  },
  {
    name: 'Users',
    to: '/users',
    icon: 'users',
  },
]

// Auto-expand parent menus when navigating to a child route
watch(() => route.path, (path) => {
  for (const item of navItems) {
    if (item.children && item.key) {
      if (item.children.some(child => path.startsWith(child.to))) {
        expandedParents.value[item.key] = true
      }
    }
  }
}, { immediate: true })

function isActive(to) {
  if (to === '/dashboard') return route.path === '/dashboard'
  if (to === '/settings')  return route.path === '/settings'
  return route.path.startsWith(to)
}

function isParentActive(item) {
  if (!item.children) return false
  return item.children.some(child => isActive(child.to))
}
</script>

<template>
  <aside
    :class="[
      'flex flex-col bg-navy-dark text-white transition-all duration-300 min-h-screen border-r border-white/5 flex-shrink-0',
      collapsed ? 'w-[60px]' : 'w-60',
    ]"
  >
    <!-- Company branding -->
    <div class="flex items-center gap-3 border-b border-white/5 px-5 py-5 min-h-[64px]">
      <div v-if="!collapsed" class="overflow-hidden min-w-0">
        <p class="font-body font-semibold text-sm text-white truncate">Bold Mark Properties</p>
        <p class="text-[11px] text-white/40 font-normal truncate">Moving People Forward</p>
      </div>
      <!-- Collapsed: show BM initials -->
      <div v-else class="w-8 h-8 rounded-lg bg-accent/20 flex items-center justify-center flex-shrink-0">
        <span class="text-accent font-bold text-xs">B</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 flex flex-col py-5 px-3 gap-0.5">
      <p
        v-if="!collapsed"
        class="text-[10px] uppercase tracking-widest text-white/30 font-semibold px-3 mb-2"
      >
        Menu
      </p>

      <template v-for="item in navItems" :key="item.to || item.key">

        <!-- Parent with children (collapsible) -->
        <template v-if="item.children">

          <!-- Parent button -->
          <button
            v-if="!collapsed"
            @click="toggleParent(item.key)"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150 w-full text-left',
              isParentActive(item)
                ? 'text-accent'
                : 'text-white/50 hover:bg-white/5 hover:text-white/80',
            ]"
          >
            <!-- Building icon for Portfolio -->
            <svg v-if="item.icon === 'building'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
              <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
              <path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/>
              <path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/>
              <path d="M16 10h.01"/><path d="M16 14h.01"/>
              <path d="M8 10h.01"/><path d="M8 14h.01"/>
            </svg>
            <!-- Shield-alert icon for Collections -->
            <svg v-else-if="item.icon === 'shield-alert'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
              <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
              <path d="M12 8v4"/><path d="M12 16h.01"/>
            </svg>

            <span class="flex-1">{{ item.name }}</span>

            <!-- Chevron -->
            <svg
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              :class="['w-3.5 h-3.5 transition-transform duration-200', expandedParents[item.key] ? 'rotate-180' : '']"
            >
              <path d="m6 9 6 6 6-6"/>
            </svg>
          </button>

          <!-- Collapsed: show first child link with parent icon -->
          <template v-if="collapsed">
            <RouterLink
              v-for="child in item.children"
              :key="child.to"
              :to="child.to"
              :title="child.name"
              :class="[
                'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150 justify-center',
                isActive(child.to)
                  ? 'bg-accent/15 text-accent shadow-sm'
                  : 'text-white/50 hover:bg-white/5 hover:text-white/80',
              ]"
            >
              <!-- Grid icon for Estates -->
              <svg v-if="child.icon === 'grid'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
                <rect width="7" height="7" x="3" y="3" rx="1"/>
                <rect width="7" height="7" x="14" y="3" rx="1"/>
                <rect width="7" height="7" x="3" y="14" rx="1"/>
                <rect width="7" height="7" x="14" y="14" rx="1"/>
              </svg>
              <!-- Door-open icon for Vacancies -->
              <svg v-else-if="child.icon === 'door-open'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
                <path d="M13 4h3a2 2 0 0 1 2 2v14"/>
                <path d="M2 20h3"/>
                <path d="M13 20h9"/>
                <path d="M10 12v.01"/>
                <path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z"/>
              </svg>
              <!-- Alert-circle icon for Arrears -->
              <svg v-else-if="child.icon === 'alert-circle'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4"/><path d="M12 16h.01"/>
              </svg>
              <!-- Trending-down icon for Age Analysis -->
              <svg v-else-if="child.icon === 'trending-down'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
                <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/>
                <polyline points="16 17 22 17 22 11"/>
              </svg>
            </RouterLink>
          </template>

          <!-- Expanded children (with animation) -->
          <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-24"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 max-h-24"
            leave-to-class="opacity-0 max-h-0"
          >
            <div v-if="!collapsed && expandedParents[item.key]" class="overflow-hidden">
              <RouterLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                :class="[
                  'flex items-center gap-3 pl-9 pr-3 py-1.5 rounded-lg text-[13px] font-medium transition-all duration-150',
                  isActive(child.to)
                    ? 'bg-accent/15 text-accent shadow-sm'
                    : 'text-white/40 hover:bg-white/5 hover:text-white/70',
                ]"
              >
                <!-- Grid icon for Estates -->
                <svg v-if="child.icon === 'grid'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[15px] h-[15px]">
                  <rect width="7" height="7" x="3" y="3" rx="1"/>
                  <rect width="7" height="7" x="14" y="3" rx="1"/>
                  <rect width="7" height="7" x="3" y="14" rx="1"/>
                  <rect width="7" height="7" x="14" y="14" rx="1"/>
                </svg>
                <!-- Door-open icon for Vacancies -->
                <svg v-else-if="child.icon === 'door-open'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[15px] h-[15px]">
                  <path d="M13 4h3a2 2 0 0 1 2 2v14"/>
                  <path d="M2 20h3"/>
                  <path d="M13 20h9"/>
                  <path d="M10 12v.01"/>
                  <path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z"/>
                </svg>
                <!-- Alert-circle icon for Arrears -->
                <svg v-else-if="child.icon === 'alert-circle'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[15px] h-[15px]">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M12 8v4"/><path d="M12 16h.01"/>
                </svg>
                <!-- Trending-down icon for Age Analysis -->
                <svg v-else-if="child.icon === 'trending-down'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[15px] h-[15px]">
                  <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/>
                  <polyline points="16 17 22 17 22 11"/>
                </svg>

                <span class="flex-1">{{ child.name }}</span>
                <span
                  v-if="child.badge === 'new'"
                  class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 leading-none"
                >New</span>
              </RouterLink>
            </div>
          </Transition>
        </template>

        <!-- Regular nav item (no children) -->
        <RouterLink
          v-else
          :to="item.to"
          :title="collapsed ? item.name : undefined"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
            collapsed ? 'justify-center' : '',
            isActive(item.to)
              ? 'bg-accent/15 text-accent shadow-sm'
              : 'text-white/50 hover:bg-white/5 hover:text-white/80',
          ]"
        >
          <!-- House icon -->
          <svg v-if="item.icon === 'house'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/>
            <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          </svg>

          <!-- File-text icon -->
          <svg v-else-if="item.icon === 'file-text'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
            <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
            <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
          </svg>

          <!-- Wallet icon -->
          <svg v-else-if="item.icon === 'wallet'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
            <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
          </svg>

          <!-- Trending-down icon -->
          <svg v-else-if="item.icon === 'trending-down'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/>
            <polyline points="16 17 22 17 22 11"/>
          </svg>

          <!-- Clipboard-check icon -->
          <svg v-else-if="item.icon === 'clipboard-check'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
            <rect x="9" y="3" width="6" height="4" rx="1"/>
            <path d="m9 14 2 2 4-4"/>
          </svg>

          <!-- Users icon -->
          <svg v-else-if="item.icon === 'users'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>

          <span v-if="!collapsed" class="flex-1">{{ item.name }}</span>
          <span
            v-if="!collapsed && item.badge === 'new'"
            class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 leading-none"
          >New</span>
          <span
            v-if="!collapsed && item.badge === 'soon'"
            class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-white/10 text-white/40 leading-none"
          >Soon</span>
        </RouterLink>
      </template>
    </nav>

    <!-- Bottom section: Settings + Collapse -->
    <div class="px-3 pb-4 space-y-0.5 border-t border-white/10 pt-3">
      <RouterLink
        to="/settings"
        :title="collapsed ? 'Settings' : undefined"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
          collapsed ? 'justify-center' : '',
          isActive('/settings')
            ? 'bg-accent/15 text-accent shadow-sm'
            : 'text-white/50 hover:bg-white/5 hover:text-white/80',
        ]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <span v-if="!collapsed">Settings</span>
      </RouterLink>

      <RouterLink
        to="/settings/login-audit"
        :title="collapsed ? 'Login Audit' : undefined"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
          collapsed ? 'justify-center' : '',
          isActive('/settings/login-audit')
            ? 'bg-accent/15 text-accent shadow-sm'
            : 'text-white/50 hover:bg-white/5 hover:text-white/80',
        ]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 w-[18px] h-[18px]">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
        <span v-if="!collapsed">Login Audit</span>
      </RouterLink>

      <!-- Collapse toggle -->
      <AppButton
        variant="ghost"
        :full="!collapsed"
        :square="collapsed"
        size="sm"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        class="text-white/30 hover:text-white/60 hover:bg-white/5 text-[13px] px-3"
        :class="collapsed ? 'justify-center' : 'justify-start gap-3'"
        @click="collapsed = !collapsed"
      >
        <!-- Panel-left-close icon -->
        <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] shrink-0">
          <rect width="18" height="18" x="3" y="3" rx="2"/>
          <path d="M9 3v18"/>
          <path d="m16 15-3-3 3-3"/>
        </svg>
        <!-- Panel-left-open icon when collapsed -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] shrink-0">
          <rect width="18" height="18" x="3" y="3" rx="2"/>
          <path d="M9 3v18"/>
          <path d="m14 9 3 3-3 3"/>
        </svg>
        <span v-if="!collapsed">Collapse</span>
      </AppButton>
    </div>
  </aside>
</template>
