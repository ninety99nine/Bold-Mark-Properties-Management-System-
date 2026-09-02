<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import SidebarIcon from '@/components/layout/SidebarIcon.vue'
import { useCommunityStore } from '@/stores/community'

const route          = useRoute()
const router         = useRouter()
const communityStore = useCommunityStore()

// Which context are we in? null selection = Global / portfolio rail.
const inCommunity   = computed(() => communityStore.selectedId != null)
const communityId   = computed(() => communityStore.selectedId)

// ── GLOBAL rail — portfolio-wide (mirrors WeConnectU's global sidebar) ──────────
// Same WeConnectU icon-rail + fly-out pattern as the community rail below:
// each parent slides out a panel; unbuilt destinations are flagged `soon`.
const globalNav = computed(() => [
  { name: 'Dashboard',   to: '/dashboard',   icon: 'line-chart' },
  { name: 'Communities', to: '/communities', icon: 'grid' },
  {
    // Manage flyout — mirrors WeConnectU's global Manage menu.
    // Rail icon = clipboard-check; flyout header icon = list-checks.
    name: 'Manage', icon: 'clipboard-check', headerIcon: 'list-checks',
    children: [
      { name: 'Planner & Compliance', soon: true },
      { name: 'Tasks',                soon: true },
      { name: 'Offences',             soon: true },
      { name: 'Transfers',            soon: true },
    ],
  },
  {
    // Finance flyout — mirrors WeConnectU's global Finance menu (sections + order).
    name: 'Finance', icon: 'wallet',
    groups: [
      { title: 'Customers', icon: 'users', items: [
        { name: 'Netcash Debit Orders', soon: true },
        { name: 'Debit Order Batches',  soon: true },
        { name: 'Customer Management',  to: '/customer-management' },
      ] },
      { title: 'Suppliers', icon: 'truck', items: [
        { name: 'Payments',            soon: true },
        { name: 'Invoice',             soon: true },
        { name: 'Recurring Invoices',  soon: true },
        { name: 'Supplier Management', soon: true },
        { name: 'Supplier Balances',   soon: true },
      ] },
      { title: 'General', icon: 'building', items: [
        { name: 'Cashbooks',              to: '/cashbook' },
        { name: 'Journals',               to: '/journals' },
        { name: 'Lock Financial Periods', soon: true },
      ] },
      { title: 'Billing', icon: 'file-text', items: [
        { name: 'Run Billing',            to: '/billing' },
        { name: 'Import Utility Readings', soon: true },
      ] },
      { title: 'Reports', icon: 'line-chart', items: [
        { name: 'Ledger Report',        soon: true },
        { name: 'Custom Ledger Report', soon: true },
        { name: 'Community Report',     soon: true },
      ] },
    ],
  },
  { name: 'Communicate', to: '/communications', icon: 'mail' },
  {
    // Reports flyout — mirrors WeConnectU's global Reports menu (Company section).
    name: 'Reports', icon: 'file-text', headerIcon: 'line-chart',
    groups: [
      { title: 'Company', icon: 'line-chart', items: [
        { name: 'Costs',         soon: true },
        { name: 'Meeting Costs', soon: true },
        { name: 'Audit Trail',   soon: true },
      ] },
    ],
  },
  {
    // Settings flyout — mirrors WeConnectU's global Settings menu (grouped).
    name: 'Settings', icon: 'sliders',
    groups: [
      { title: 'General Settings', icon: 'sliders', items: [
        { name: 'Company', to: '/settings/company' },
        { name: 'Users',   to: '/settings/users' },
      ] },
      { title: 'Operations Settings', icon: 'clipboard-check', items: [
        { name: 'Compliance',            to: '/compliance' },
        { name: 'Meetings',              soon: true },
        { name: 'Communication',         to: '/settings/communication' },
        { name: 'Community Roles Setup', soon: true },
      ] },
      { title: 'Finances Settings', icon: 'wallet', items: [
        { name: 'Default Ledgers',       soon: true },
        { name: 'Debt Collection',       soon: true },
        { name: 'Utilities Schedule',    soon: true },
        { name: 'Supplier Verification', soon: true },
      ] },
    ],
  },
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
        { name: 'Tasks',     soon: true },
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
          { name: 'Credit Note',             to: '/customers/credit-note' },
          { name: 'Detailed Customer Ledger', to: '/customers/ledger' },
          { name: 'Age Analysis',            to: '/age-analysis' },
          { name: 'Status Management',       to: '/customers/status' },
          { name: 'Interest on Arrears',     soon: true },
          { name: 'Manage Customers',        to: '/customers/manage' },
          { name: 'Customer Statements',     to: '/customers/statements' },
        ] },
        { title: 'Suppliers', icon: 'truck', items: [
          { name: 'Payments',                soon: true },
          { name: 'Invoice',                 to: '/suppliers/invoices' },
          { name: 'Recurring Invoices',      soon: true },
          { name: 'Debit Notes',             soon: true },
          { name: 'Statements',              soon: true },
          { name: 'Detailed Supplier Ledger', to: '/suppliers/ledger' },
          { name: 'Age Analysis',            to: '/suppliers/age-analysis' },
          { name: 'Manage Suppliers',        to: '/suppliers/manage' },
        ] },
        { title: 'General', icon: 'building', items: [
          { name: 'Cashbooks', to: '/cashbook' },
          { name: 'Journals',  to: '/journals' },
        ] },
        { title: 'Reports', icon: 'line-chart', items: [
          { name: 'Actual vs Budget',              to: '/reports/actual-vs-budget' },
          { name: 'Trial Balance',                 to: '/reports/trial-balance' },
          { name: 'Detailed General Ledger',       to: '/reports/general-ledger' },
          { name: 'Reserve Fund Actual vs Budget', to: '/reports/reserve-fund-actual-vs-budget' },
          { name: 'Reserve Fund Income Statement', to: '/reports/reserve-fund-income-statement' },
          { name: 'Document Report',               soon: true },
          { name: 'Levy Roll',                     soon: true },
          { name: 'Basic Cash Movement Report',    to: '/reports/cash-movement' },
          { name: 'VAT 201 Report',                to: '/reports/vat-201' },
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
          { name: 'Financial',           to: '/settings/finance/financial' },
          { name: 'Budget',              to: '/settings/finance/budget' },
          { name: 'Reserve Fund Budget', to: '/settings/finance/reserve-fund-budget' },
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
        { name: 'Charges',                   to: '/settings/charges' },
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

const effectiveTab = computed(() => route.query.tab || 'overview')

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
  <!-- ── WeConnectU-style icon rail — used for BOTH the global & community levels ── -->
  <aside
    class="relative flex flex-col items-center bg-navy-dark text-white min-h-screen border-r border-white/5 flex-shrink-0 w-28"
  >
    <nav class="flex-1 flex flex-col items-center gap-1 py-4 w-full px-2">
      <!-- Global — shown on BOTH levels: an "exit to Global" button inside a
           community, a link to the global dashboard otherwise. Never rendered
           in the active (gold) state. -->
      <button
        v-if="inCommunity"
        type="button"
        @click="exitToGlobal"
        class="flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150 text-white/50 hover:bg-white/5 hover:text-white/80"
      >
        <SidebarIcon name="fingerprint" :size="22" />
        <span>Global</span>
      </button>
      <RouterLink
        v-else to="/dashboard"
        class="flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150 text-white/50 hover:bg-white/5 hover:text-white/80"
      >
        <SidebarIcon name="fingerprint" :size="22" />
        <span>Global</span>
      </RouterLink>

      <template v-for="item in navItems" :key="item.name">
        <!-- Parent with a fly-out sub-menu (e.g. Units → Units / PQs, Finance → …) -->
        <button
          v-if="item.children || item.groups"
          type="button"
          @click="toggleFlyout(item)"
          :class="[
            'flex flex-col items-center justify-center gap-1 w-full rounded-lg py-2.5 text-[10px] font-medium leading-tight transition-all duration-150',
            // Active (gold) only when the current page belongs to this menu — NOT
            // merely because its fly-out is open. An open (but inactive) fly-out
            // gets a subtle neutral highlight so you can see which panel is showing.
            isParentActive(item)
              ? 'text-accent bg-accent/10'
              : flyout?.name === item.name
                ? 'text-white/80 bg-white/5'
                : 'text-white/50 hover:bg-white/5 hover:text-white/80',
          ]"
        >
          <SidebarIcon :name="item.icon" :size="22" />
          <span class="text-center max-w-[80px] leading-tight">{{ item.name }}</span>
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
          <span class="text-center max-w-[80px] leading-tight">{{ item.name }}</span>
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
        <div class="fixed left-28 top-14 bottom-0 z-40 w-72 bg-card shadow-2xl border-r border-border flex flex-col">
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
</template>
