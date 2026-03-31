<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})

const firstName = computed(() => auth.user?.name?.split(' ')[0] || 'there')

const today = computed(() =>
  new Date().toLocaleDateString('en-ZA', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

// TODO: Replace with API data
const kpiCards = [
  {
    label: 'Communities Managed', value: '3', sub: '107 units across portfolio',
    iconBg: '#EEF2F8', iconColor: '#1F3A5C',
    iconPath: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21',
  },
  {
    label: 'Units Under Management', value: '107', sub: 'Across 3 active schemes',
    iconBg: '#FBF5EC', iconColor: '#C87B33',
    iconPath: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
  },
  {
    label: 'Portfolio Arrears', value: 'R 141,666', sub: '15.3% of monthly levy book',
    iconBg: '#FFF5F5', iconColor: '#F75A68',
    iconPath: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    danger: true,
  },
  {
    label: 'Portfolio Compliance', value: '78%', sub: '1 non-compliant scheme',
    iconBg: '#FFFBEB', iconColor: '#D97706',
    iconPath: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    warn: true,
  },
]

const communities = [
  { name: 'Crystal Mews BC', location: 'Bramley View', units: 47, arrears: 'R 54,320', adminFund: 'R 28,450', reserveFund: 'R 42,100', compliance: 'Compliant', status: 'green' },
  { name: 'King Arthur BC', location: 'Florida', units: 32, arrears: 'R 38,190', adminFund: 'R 18,750', reserveFund: 'R 31,200', compliance: 'Due Soon', status: 'amber' },
  { name: 'Lyndhurst Estate', location: 'Lyndhurst', units: 28, arrears: 'R 49,156', adminFund: 'R 15,100', reserveFund: 'R 22,400', compliance: 'Non-Compliant', status: 'red' },
]

const debtAging = [
  { label: 'Current', amount: 'R 12,400', pct: 9, color: '#22c55e' },
  { label: '30 days', amount: 'R 38,250', pct: 27, color: '#F59E0B' },
  { label: '60 days', amount: 'R 41,180', pct: 29, color: '#F97316' },
  { label: '90+ days', amount: 'R 49,836', pct: 35, color: '#F75A68' },
]

const activity = [
  { text: 'Payment received — Crystal Mews BC, Unit 14', time: '2 hours ago', dot: '#22c55e' },
  { text: 'Letter of demand sent — King Arthur BC, Unit 7', time: '3 hours ago', dot: '#F75A68' },
  { text: 'New task assigned — Lyndhurst Estate, roof inspection', time: '5 hours ago', dot: '#1F3A5C' },
  { text: 'Compliance deadline approaching — AGM filing (Crystal Mews)', time: 'Yesterday', dot: '#D97706' },
  { text: 'Bank statement imported — Lyndhurst Estate Admin Fund', time: 'Yesterday', dot: '#D89B4B' },
]

const quickStats = [
  { value: '12', label: 'Open Tasks', iconColor: '#1F3A5C', iconBg: '#EEF2F8', textColor: '#1E2740', iconPath: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z' },
  { value: '2', label: 'Upcoming AGMs', iconColor: '#C87B33', iconBg: '#FBF5EC', textColor: '#1E2740', iconPath: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5' },
  { value: '5', label: 'Overdue Tasks', iconColor: '#F75A68', iconBg: '#FFF5F5', textColor: '#F75A68', iconPath: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
  { value: '4', label: 'Pending Approvals', iconColor: '#16A34A', iconBg: '#F0FDF4', textColor: '#16A34A', iconPath: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
]

const badge = {
  green: { bg: '#F0FDF4', text: '#16A34A' },
  amber: { bg: '#FFFBEB', text: '#D97706' },
  red:   { bg: '#FFF5F5', text: '#DC2626' },
}
</script>

<template>
  <div class="space-y-7">

    <!-- Header -->
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium mb-1.5" style="color: #D89B4B;">{{ today }}</p>
        <h1 class="text-[28px] leading-tight font-serif" style="color: #1E2740;">{{ greeting }}, {{ firstName }}</h1>
        <p class="text-sm mt-1" style="color: #717B99;">Here's your portfolio overview for today.</p>
      </div>
      <button class="hidden sm:flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white hover:opacity-90 flex-shrink-0 mt-1" style="background-color: #1F3A5C;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Refresh
      </button>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
      <div v-for="card in kpiCards" :key="card.label" class="bg-white rounded-xl p-5 border" style="border-color: #DCDEE8;">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" :style="{ backgroundColor: card.iconBg }">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" :style="{ color: card.iconColor }">
            <path stroke-linecap="round" stroke-linejoin="round" :d="card.iconPath" />
          </svg>
        </div>
        <p class="text-2xl font-bold leading-none mb-1.5" :style="{ color: card.danger ? '#F75A68' : card.warn ? '#D97706' : '#1E2740' }">{{ card.value }}</p>
        <p class="text-sm font-medium" style="color: #1E2740;">{{ card.label }}</p>
        <p class="text-xs mt-1" style="color: #717B99;">{{ card.sub }}</p>
      </div>
    </div>

    <!-- Communities + Sidebar -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

      <!-- Communities table -->
      <div class="xl:col-span-2 bg-white rounded-xl border" style="border-color: #DCDEE8;">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom: 1px solid #DCDEE8;">
          <div>
            <h2 class="text-base font-semibold" style="color: #1E2740;">Communities Overview</h2>
            <p class="text-xs mt-0.5" style="color: #717B99;">Managed schemes and current status</p>
          </div>
          <a href="/communities" class="text-xs font-semibold hover:opacity-70" style="color: #D89B4B;">View all →</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr style="background-color: #F8FBFF;">
                <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wide" style="color: #717B99;">Community</th>
                <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide" style="color: #717B99;">Units</th>
                <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide" style="color: #717B99;">Arrears</th>
                <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide" style="color: #717B99;">Compliance</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c, i) in communities" :key="c.name" :style="{ borderTop: i > 0 ? '1px solid #F0F2F8' : 'none' }" class="transition-colors" @mouseenter="$event.currentTarget.style.backgroundColor = '#FAFBFF'" @mouseleave="$event.currentTarget.style.backgroundColor = ''">
                <td class="px-6 py-4">
                  <p class="font-semibold" style="color: #1E2740;">{{ c.name }}</p>
                  <p class="text-xs mt-0.5" style="color: #717B99;">{{ c.location }}</p>
                </td>
                <td class="px-4 py-4 text-center font-medium" style="color: #1E2740;">{{ c.units }}</td>
                <td class="px-4 py-4 text-right font-semibold" style="color: #F75A68;">{{ c.arrears }}</td>
                <td class="px-4 py-4 text-center">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" :style="{ backgroundColor: badge[c.status].bg, color: badge[c.status].text }">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5" :style="{ backgroundColor: badge[c.status].text }" />
                    {{ c.compliance }}
                  </span>
                </td>
                <td class="px-4 py-4 text-right">
                  <button class="text-xs font-medium transition-colors" style="color: #717B99;" @mouseenter="$event.currentTarget.style.color = '#D89B4B'" @mouseleave="$event.currentTarget.style.color = '#717B99'">View →</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Fund balances footer -->
        <div class="px-6 py-4" style="border-top: 1px solid #DCDEE8; background-color: #F8FBFF;">
          <p class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: #717B99;">Fund Balances</p>
          <div class="grid grid-cols-3 gap-4">
            <div v-for="c in communities" :key="c.name + '-f'">
              <p class="text-xs font-medium truncate mb-1.5" style="color: #1E2740;">{{ c.name }}</p>
              <div class="space-y-1">
                <div class="flex justify-between"><span class="text-xs" style="color: #717B99;">Admin</span><span class="text-xs font-semibold" style="color: #1E2740;">{{ c.adminFund }}</span></div>
                <div class="flex justify-between"><span class="text-xs" style="color: #717B99;">Reserve</span><span class="text-xs font-semibold" style="color: #1E2740;">{{ c.reserveFund }}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right sidebar -->
      <div class="space-y-5">

        <!-- Debt Aging -->
        <div class="bg-white rounded-xl border" style="border-color: #DCDEE8;">
          <div class="px-5 py-4" style="border-bottom: 1px solid #DCDEE8;">
            <h2 class="text-base font-semibold" style="color: #1E2740;">Debt Aging</h2>
            <p class="text-xs mt-0.5" style="color: #717B99;">Portfolio arrears breakdown</p>
          </div>
          <div class="px-5 pt-4 pb-2">
            <p class="text-2xl font-bold" style="color: #F75A68;">R 141,666</p>
            <p class="text-xs mt-0.5" style="color: #717B99;">Total outstanding across all communities</p>
          </div>
          <div class="px-5 pb-4 space-y-4 mt-2">
            <div v-for="b in debtAging" :key="b.label">
              <div class="flex justify-between mb-1.5">
                <span class="text-xs font-medium" style="color: #1E2740;">{{ b.label }}</span>
                <span class="text-xs font-semibold" :style="{ color: b.color }">{{ b.amount }}</span>
              </div>
              <div class="h-1.5 rounded-full" style="background-color: #EDEFF5;">
                <div class="h-full rounded-full" :style="{ width: b.pct + '%', backgroundColor: b.color }" />
              </div>
              <p class="text-xs mt-1" style="color: #717B99;">{{ b.pct }}% of total arrears</p>
            </div>
          </div>
          <div class="px-5 pb-5">
            <a href="/debt" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg text-xs font-semibold text-white hover:opacity-90" style="background-color: #1F3A5C;">
              View Full Debt Report
            </a>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl border" style="border-color: #DCDEE8;">
          <div class="px-5 py-4" style="border-bottom: 1px solid #DCDEE8;">
            <h2 class="text-base font-semibold" style="color: #1E2740;">Recent Activity</h2>
          </div>
          <ul>
            <li v-for="(item, i) in activity" :key="item.text" class="flex items-start gap-3 px-5 py-3.5" :style="{ borderTop: i > 0 ? '1px solid #F0F2F8' : 'none' }">
              <span class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5" :style="{ backgroundColor: item.dot }" />
              <div>
                <p class="text-xs leading-snug" style="color: #1E2740;">{{ item.text }}</p>
                <p class="text-xs mt-1" style="color: #717B99;">{{ item.time }}</p>
              </div>
            </li>
          </ul>
        </div>

      </div>
    </div>

    <!-- Quick stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div v-for="s in quickStats" :key="s.label" class="bg-white rounded-xl border px-5 py-4 flex items-center gap-3.5" style="border-color: #DCDEE8;">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: s.iconBg }">
          <svg class="w-4.5 h-4.5" fill="none" :stroke="s.iconColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="s.iconPath" />
          </svg>
        </div>
        <div>
          <p class="text-xl font-bold leading-none" :style="{ color: s.textColor }">{{ s.value }}</p>
          <p class="text-xs mt-1" style="color: #717B99;">{{ s.label }}</p>
        </div>
      </div>
    </div>

  </div>
</template>
