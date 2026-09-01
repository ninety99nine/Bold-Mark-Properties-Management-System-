<script setup>
import { ref, computed, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import SidebarIcon from '@/components/layout/SidebarIcon.vue'
import { useCommunityStore } from '@/stores/community'

const route          = useRoute()
const router         = useRouter()
const communityStore = useCommunityStore()
const collapsed = ref(false)

// Which context are we in? null selection = Global / portfolio rail.
const inCommunity   = computed(() => communityStore.selectedId != null)
const community     = computed(() => communityStore.selected)
const communityId   = computed(() => communityStore.selectedId)

// ── GLOBAL rail — portfolio-wide (mirrors WeConnectU's global sidebar) ──────────
const globalNav = computed(() => [
  { name: 'Dashboard',   to: '/dashboard',    icon: 'house' },
  { name: 'Communities', to: '/communities',  icon: 'building' },
  {
    name: 'Manage', key: 'g-manage', icon: 'layers',
    children: [
      { name: 'Planner & Compliance', to: '/compliance', icon: 'clipboard-check' },
      { name: 'Tasks',                to: '/tasks',      icon: 'list-checks' },
    ],
  },
  {
    name: 'Finance', key: 'g-finance', icon: 'wallet',
    children: [
      { name: 'Run Billing',         to: '/billing',             icon: 'file-text' },
      { name: 'Cashbooks',           to: '/cashbook',            icon: 'wallet' },
      { name: 'Customer Management', to: '/customer-management', icon: 'alert-circle' },
      { name: 'Age Analysis',        to: '/age-analysis',        icon: 'trending-down' },
    ],
  },
  { name: 'Communicate', to: '/communications', icon: 'megaphone' },
  { name: 'Vacancies',   to: '/vacancies',    icon: 'door-open', badge: 'new' },
])

// ── COMMUNITY rail — a single community (mirrors WeConnectU's /app sidebar) ──────
const communityNav = computed(() => {
  const id = communityId.value
  // Flat WeConnectU-style rail: each item is a single icon that lands on its
  // section; deeper navigation (PQs, Cashbook, Age Analysis…) lives as in-page tabs.
  return [
    { name: 'Dashboard',   tab: 'overview',      icon: 'line-chart' },
    {
      name: 'Units', icon: 'building',
      children: [
        { name: 'Unit Details', tab: 'units', icon: 'list' },
        { name: 'PQs',          tab: 'pq',    icon: 'percent' },
      ],
    },
    {
      // Manage flyout — mirrors WeConnectU exactly (soon = page not built yet).
      // Rail icon = clipboard-check; flyout header icon = list-checks (like WeConnectU).
      name: 'Manage', icon: 'clipboard-check', headerIcon: 'list-checks',
      children: [
        { name: 'Tasks',     to: '/tasks' },
        { name: 'Offences',  soon: true },
        { name: 'Transfers', soon: true },
        { name: 'Documents', soon: true },
      ],
    },
    {
      // Finance flyout — mirrors WeConnectU's menu exactly (names, sections, order).
      name: 'Finance', icon: 'wallet',
      groups: [
        { title: 'Customers', icon: 'users', items: [
          { name: 'Invoice',                 to: '/customers/invoice' },
          { name: 'Recurring Invoices',      soon: true },
          { name: 'Credit Note',             soon: true },
          { name: 'Detailed Customer Ledger', soon: true },
          { name: 'Age Analysis',            to: '/age-analysis' },
          { name: 'Status Management',       soon: true },
          { name: 'Interest on Arrears',     soon: true },
          { name: 'Manage Customers',        to: '/customer-management' },
          { name: 'Customer Statements',     soon: true },
        ] },
        { title: 'Suppliers', icon: 'truck', items: [
          { name: 'Payments',                soon: true },
          { name: 'Invoice',                 soon: true },
          { name: 'Recurring Invoices',      soon: true },
          { name: 'Debit Notes',             soon: true },
          { name: 'Statements',              soon: true },
          { name: 'Detailed Supplier Ledger', soon: true },
          { name: 'Age Analysis',            soon: true },
          { name: 'Manage Suppliers',        soon: true },
        ] },
        { title: 'General', icon: 'building', items: [
          { name: 'Cashbooks', to: '/cashbook' },
          { name: 'Journals',  soon: true },
        ] },
        { title: 'Reports', icon: 'line-chart', items: [
          { name: 'Actual vs Budget',              soon: true },
          { name: 'Trial Balance',                 soon: true },
          { name: 'Detailed General Ledger',       soon: true },
          { name: 'Reserve Fund Actual vs Budget', soon: true },
          { name: 'Reserve Fund Income Statement', soon: true },
          { name: 'Document Report',               soon: true },
          { name: 'Levy Roll',                     soon: true },
          { name: 'Basic Cash Movement Report',    soon: true },
          { name: 'VAT 201 Report',                soon: true },
        ] },
        { title: 'Billing', icon: 'file-text', items: [
          { name: 'Water Meters',          soon: true },
          { name: 'Electricity Meters',    soon: true },
          { name: 'Water Readings',        soon: true },
          { name: 'Electricity Readings',  soon: true },
          { name: 'Additional Recoveries', soon: true },
          { name: 'Run Billing',           to: '/billing' },
          { name: 'Batch Billing',         soon: true },
        ] },
        { title: 'Setup', icon: 'sliders', items: [
          { name: 'Financial',           soon: true },
          { name: 'Budget',              soon: true },
          { name: 'Reserve Fund Budget', soon: true },
        ] },
      ],
    },
    { name: 'Communicate',      tab: 'communication', icon: 'mail' },
    { name: 'Community Report', tab: 'report',        icon: 'file-text' },
    {
      // Settings flyout — mirrors WeConnectU's community Settings menu.
      name: 'Settings', key: 'c-settings', icon: 'sliders',
      children: [
        { name: 'General',                   to: '/settings/general' },
        { name: 'Address & Contact Details', to: '/settings/address-contact' },
        { name: 'Charges',                   soon: true },
        { name: 'Default Billing Setup',     to: '/settings/default-billing-setup' },
        { name: 'Community Roles',           soon: true },
        { name: 'Offences Clause Setup',     soon: true },
        { name: 'Compliance',                to: '/compliance' },
        { name: 'Users',                     to: '/settings/users' },
      ],
    },
  ].map(item => withCommunityTargets(item, id))
})

// Resolve `tab` leaves into router-link targets for the active community.
function withCommunityTargets(item, id) {
  const map = leaf => leaf.tab
    ? { ...leaf, to: { path: `/communities/${id}`, query: { tab: leaf.tab, ...(leaf.view ? { view: leaf.view } : {}) } } }
    : leaf
  if (item.children) return { ...item, children: item.children.map(map) }
  return map(item)
}

const navItems = computed(() => (inCommunity.value ? communityNav.value : globalNav.value))

// Track which fly-out parents are open (default: open).
const expandedParents = ref({})
function toggleParent(key) { expandedParents.value[key] = !expandedParents.value[key] }

const effectiveTab = computed(() => route.query.tab || 'overview')

// Open the parent group of the active route automatically (and default-open groups).
watch([navItems, () => route.fullPath], () => {
  for (const item of navItems.value) {
    if (!item.key) continue
    if (expandedParents.value[item.key] === undefined) expandedParents.value[item.key] = true
    if (item.children && item.children.some(isActive)) expandedParents.value[item.key] = true
  }
}, { immediate: true })

function isActive(item) {
  const to = item.to
  if (!to) return false
  if (typeof to === 'object') {
    if (route.path !== to.path || effectiveTab.value !== to.query.tab) return false
    // When a target pins a specific view (Units vs Unit Details), match it too.
    if (to.query.view) return (route.query.view || 'details') === to.query.view
    return true
  }
  if (to === '/dashboard') return route.path === '/dashboard'
  return route.path === to || route.path.startsWith(to + '/')
}
function isParentActive(item) {
  if (item.children) return item.children.some(isActive)
  if (item.groups) return item.groups.some(g => g.items.some(isActive))
  return false
}

function exitToGlobal() {
  flyout.value = null
  communityStore.clearSelection()
  router.push('/dashboard')
}

// ── Fly-out sub-menu (WeConnectU slide-out for rail items with children) ────────
const flyout = ref(null)
const flyoutSearch = ref('')
const flyoutChildren = computed(() => {
  const kids = flyout.value?.children ?? []
  const q = flyoutSearch.value.trim().toLowerCase()
  return q ? kids.filter(c => c.name.toLowerCase().includes(q)) : kids
})
// Grouped flyouts (e.g. Finance → Customers / Suppliers / General / Billing).
const flyoutGroups = computed(() => {
  const groups = flyout.value?.groups ?? null
  if (!groups) return null
  const q = flyoutSearch.value.trim().toLowerCase()
  if (!q) return groups
  return groups
    .map(g => ({ ...g, items: g.items.filter(i => i.name.toLowerCase().includes(q)) }))
    .filter(g => g.items.length)
})
function toggleFlyout(item) {
  if (flyout.value?.name === item.name) { flyout.value = null; return }
  flyout.value = item
  flyoutSearch.value = ''
  // Do NOT navigate on open — the panel just slides out; the page only
  // changes once the user picks a specific sub-section (matches WeConnectU).
}
function openChild(child) {
  if (child.soon) return          // not built yet — leave the panel open
  if (child.to) router.push(child.to)
  flyout.value = null
}
</script>

<template>
  <!-- ── COMMUNITY context: compact WeConnectU-style icon rail ──────────────── -->
  <aside
    v-if="inCommunity"
    class="relative flex flex-col items-center bg-navy-dark text-white min-h-screen border-r border-white/5 flex-shrink-0 w-20"
  >
    <nav class="flex-1 flex flex-col items-center gap-1 py-4 w-full px-2">
      <!-- Global exit -->
      <button
        type="button"
        @click="exitToGlobal"
        class="flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150 text-white/50 hover:bg-white/5 hover:text-white/80"
      >
        <SidebarIcon name="fingerprint" :size="22" />
        <span>Global</span>
      </button>

      <template v-for="item in navItems" :key="item.name">
        <!-- Parent with a fly-out sub-menu (e.g. Units → Units / PQs, Finance → …) -->
        <button
          v-if="item.children || item.groups"
          type="button"
          @click="toggleFlyout(item)"
          :class="[
            'flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150',
            (flyout?.name === item.name || isParentActive(item)) ? 'text-accent bg-accent/10' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
          ]"
        >
          <SidebarIcon :name="item.icon" :size="22" />
          <span class="text-center">{{ item.name }}</span>
        </button>

        <!-- Leaf item -->
        <RouterLink
          v-else :to="item.to"
          :class="[
            'flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150',
            isActive(item) ? 'text-accent bg-accent/10' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
          ]"
        >
          <SidebarIcon :name="item.icon" :size="22" />
          <span class="text-center">{{ item.name }}</span>
        </RouterLink>
      </template>
    </nav>

    <!-- ── Fly-out sub-menu panel (WeConnectU slide-out) ─────────────────────── -->
    <template v-if="flyout">
      <!-- click-away backdrop (transparent, like WeConnectU) -->
      <div class="fixed inset-0 z-30" @click="flyout = null" />
      <Transition
        appear
        enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 -translate-x-3" enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100 translate-x-0" leave-to-class="opacity-0 -translate-x-3"
      >
        <div class="fixed left-20 top-14 bottom-0 z-40 w-72 bg-card shadow-2xl border-r border-border flex flex-col">
          <!-- Search -->
          <div class="p-3 border-b border-border">
            <div class="relative">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                v-model="flyoutSearch" type="text" placeholder="Search..."
                class="w-full rounded-full bg-muted pl-9 pr-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-accent/40"
              />
            </div>
          </div>

          <!-- Grouped flyout (e.g. Finance → Customers / Suppliers / General / Billing) -->
          <nav v-if="flyoutGroups" class="flex-1 overflow-y-auto px-2 pt-3 pb-4 space-y-4">
            <div v-for="g in flyoutGroups" :key="g.title">
              <div class="flex items-center gap-2 px-3 pb-1">
                <SidebarIcon :name="g.icon" :size="16" class="text-navy" />
                <span class="text-sm font-bold text-foreground">{{ g.title }}</span>
              </div>
              <button
                v-for="item in g.items" :key="item.name"
                type="button" :disabled="item.soon" @click="openChild(item)"
                :class="[
                  'w-full flex items-center justify-between gap-3 pl-9 pr-3 py-2 rounded-lg text-sm text-left transition-colors',
                  item.soon ? 'text-muted-foreground/50 cursor-default'
                    : isActive(item) ? 'bg-accent/10 text-accent font-medium' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
              >
                <span>{{ item.name }}</span>
                <span v-if="item.soon" class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-muted-foreground/70">Soon</span>
              </button>
            </div>
            <p v-if="!flyoutGroups.length" class="px-3 py-4 text-sm text-muted-foreground">No matches.</p>
          </nav>

          <!-- Simple flyout (header + flat children, e.g. Units) -->
          <template v-else>
            <div class="flex items-center gap-2.5 px-4 pt-4 pb-1">
              <SidebarIcon :name="flyout.headerIcon || flyout.icon" :size="20" class="text-navy" />
              <span class="text-lg font-bold text-foreground">{{ flyout.name }}</span>
            </div>
            <nav class="flex-1 overflow-y-auto px-2 pt-1 pb-4">
              <button
                v-for="child in flyoutChildren" :key="child.name"
                type="button" :disabled="child.soon" @click="openChild(child)"
                :class="[
                  'w-full flex items-center justify-between gap-3 rounded-lg text-sm text-left transition-colors',
                  child.icon ? 'px-3 py-2.5' : 'pl-9 pr-3 py-2',
                  child.soon ? 'text-muted-foreground/50 cursor-default'
                    : isActive(child) ? 'bg-accent/10 text-accent font-medium' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
              >
                <span class="flex items-center gap-3">
                  <SidebarIcon v-if="child.icon" :name="child.icon" :size="16" />
                  {{ child.name }}
                </span>
                <span v-if="child.soon" class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-muted-foreground/70">Soon</span>
              </button>
              <p v-if="!flyoutChildren.length" class="px-3 py-4 text-sm text-muted-foreground">No matches.</p>
            </nav>
          </template>
        </div>
      </Transition>
    </template>
  </aside>

  <!-- ── GLOBAL context: full portfolio sidebar ─────────────────────────────── -->
  <aside
    v-else
    :class="[
      'flex flex-col bg-navy-dark text-white transition-all duration-300 min-h-screen border-r border-white/5 flex-shrink-0',
      collapsed ? 'w-[60px]' : 'w-60',
    ]"
  >
    <!-- Branding / context header -->
    <div class="flex items-center gap-3 border-b border-white/5 px-5 py-5 min-h-[64px]">
      <template v-if="!collapsed">
        <div v-if="inCommunity" class="overflow-hidden min-w-0">
          <p class="text-xs uppercase tracking-widest text-accent/80 font-semibold mb-0.5">Community</p>
          <p class="font-body font-semibold text-sm text-white truncate">{{ community?.name }}</p>
        </div>
        <div v-else class="overflow-hidden min-w-0">
          <p class="text-xs uppercase tracking-widest text-accent/80 font-semibold mb-0.5">Global</p>
          <p class="font-body font-semibold text-sm text-white truncate">Bold Mark Properties</p>
          <p class="text-[11px] text-white/40 font-normal truncate">Moving People Forward</p>
        </div>
      </template>
      <div v-else class="w-8 h-8 rounded-lg bg-accent/20 flex items-center justify-center flex-shrink-0">
        <span class="text-accent font-bold text-xs">B</span>
      </div>
    </div>

    <!-- Global exit (community context only) -->
    <div v-if="inCommunity" class="px-3 pt-3">
      <button
        type="button"
        @click="exitToGlobal"
        :title="collapsed ? 'Back to Global' : undefined"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium w-full transition-all duration-150 text-white/60 hover:bg-white/5 hover:text-white',
          collapsed ? 'justify-center' : '',
        ]"
      >
        <SidebarIcon name="globe" :size="18" />
        <span v-if="!collapsed" class="flex-1 text-left">Global</span>
        <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 rotate-180"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 flex flex-col py-5 px-3 gap-0.5">
      <p v-if="!collapsed" class="text-[10px] uppercase tracking-widest text-white/30 font-semibold px-3 mb-2">Menu</p>

      <template v-for="item in navItems" :key="item.key || item.name">
        <!-- Parent with children (fly-out / collapsible) -->
        <template v-if="item.children">
          <button
            v-if="!collapsed"
            @click="toggleParent(item.key)"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150 w-full text-left',
              isParentActive(item) ? 'text-accent' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
            ]"
          >
            <SidebarIcon :name="item.icon" :size="18" />
            <span class="flex-1">{{ item.name }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              :class="['w-3.5 h-3.5 transition-transform duration-200', expandedParents[item.key] ? 'rotate-180' : '']"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <!-- Collapsed: show child icons directly -->
          <template v-if="collapsed">
            <RouterLink
              v-for="child in item.children" :key="child.name" :to="child.to" :title="child.name"
              :class="[
                'flex items-center justify-center px-3 py-2 rounded-lg transition-all duration-150',
                isActive(child) ? 'bg-accent/15 text-accent shadow-sm' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
              ]"
            ><SidebarIcon :name="child.icon" :size="18" /></RouterLink>
          </template>

          <!-- Expanded children -->
          <Transition
            enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-60"
            leave-active-class="transition-all duration-150 ease-in" leave-from-class="opacity-100 max-h-60" leave-to-class="opacity-0 max-h-0"
          >
            <div v-if="!collapsed && expandedParents[item.key]" class="overflow-hidden">
              <RouterLink
                v-for="child in item.children" :key="child.name" :to="child.to"
                :class="[
                  'flex items-center gap-3 pl-9 pr-3 py-1.5 rounded-lg text-[13px] font-medium transition-all duration-150',
                  isActive(child) ? 'bg-accent/15 text-accent shadow-sm' : 'text-white/40 hover:bg-white/5 hover:text-white/70',
                ]"
              >
                <SidebarIcon :name="child.icon" :size="15" />
                <span class="flex-1">{{ child.name }}</span>
              </RouterLink>
            </div>
          </Transition>
        </template>

        <!-- Regular item -->
        <RouterLink
          v-else :to="item.to" :title="collapsed ? item.name : undefined"
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
            collapsed ? 'justify-center' : '',
            isActive(item) ? 'bg-accent/15 text-accent shadow-sm' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
          ]"
        >
          <SidebarIcon :name="item.icon" :size="18" />
          <span v-if="!collapsed" class="flex-1">{{ item.name }}</span>
          <span v-if="!collapsed && item.badge === 'new'" class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 leading-none">New</span>
        </RouterLink>
      </template>
    </nav>

    <!-- Bottom: Settings + Collapse -->
    <div class="px-3 pb-4 space-y-0.5 border-t border-white/10 pt-3">
      <RouterLink
        to="/settings" :title="collapsed ? 'Settings' : undefined"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-150',
          collapsed ? 'justify-center' : '',
          route.path.startsWith('/settings') ? 'bg-accent/15 text-accent shadow-sm' : 'text-white/50 hover:bg-white/5 hover:text-white/80',
        ]"
      >
        <SidebarIcon name="settings" :size="18" />
        <span v-if="!collapsed">Settings</span>
      </RouterLink>

      <AppButton
        variant="ghost" :full="!collapsed" :square="collapsed" size="sm"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        class="text-white/30 hover:text-white/60 hover:bg-white/5 text-[13px] px-3"
        :class="collapsed ? 'justify-center' : 'justify-start gap-3'"
        @click="collapsed = !collapsed"
      >
        <svg v-if="!collapsed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] shrink-0"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m16 15-3-3 3-3"/></svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px] shrink-0"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m14 9 3 3-3 3"/></svg>
        <span v-if="!collapsed">Collapse</span>
      </AppButton>
    </div>
  </aside>
</template>
