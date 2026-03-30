<script setup>
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const navItems = [
  { name: 'Dashboard', to: '/dashboard', icon: '📊' },
  { name: 'Communities', to: '/communities', icon: '🏘️' },
  { name: 'Owners', to: '/owners', icon: '👤' },
  { name: 'Levy Billing', to: '/levies', icon: '💳' },
  { name: 'Debt Management', to: '/debt', icon: '⚠️' },
  { name: 'Financials', to: '/finances', icon: '💰' },
  { name: 'Compliance', to: '/compliance', icon: '✅' },
  { name: 'Tasks', to: '/tasks', icon: '📋' },
  { name: 'Communications', to: '/communications', icon: '📧' },
  { name: 'Documents', to: '/documents', icon: '📁' },
]
</script>

<template>
  <aside class="w-64 flex-shrink-0 flex flex-col" style="background-color: #1a2744;">
    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-white/10">
      <span class="text-white font-bold text-lg tracking-wide">BoldMark PMS</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-3">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 text-sm font-medium transition-colors"
        :class="route.path.startsWith(item.to)
          ? 'text-white'
          : 'text-white/60 hover:text-white hover:bg-white/10'"
        :style="route.path.startsWith(item.to) ? 'background-color: #e8a040;' : ''"
      >
        <span class="text-base">{{ item.icon }}</span>
        {{ item.name }}
      </RouterLink>
    </nav>
  </aside>
</template>
