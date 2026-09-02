<script setup>
import { ref, onMounted } from 'vue'
import api from '@/composables/useApi.js'
import { debounce } from '@/utils/debounce'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge  from '@/components/common/AppBadge.vue'

// ─── State ────────────────────────────────────────────────────────────────────
const logs        = ref([])
const loading     = ref(true)
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

// Filters
const searchEmail    = ref('')
const filterStatus   = ref('')
const filterReason   = ref('')

// ─── Constants ────────────────────────────────────────────────────────────────
const FAILURE_REASON_DISPLAY = {
  user_not_found:   'User Not Found',
  wrong_password:   'Wrong Password',
  account_inactive: 'Account Inactive',
  account_invited:  'Not Activated',
}

const AVATAR_COLORS = ['bg-primary','bg-teal-600','bg-blue-600','bg-amber-600','bg-rose-600','bg-purple-600','bg-emerald-600','bg-orange-600']

// ─── Helpers ──────────────────────────────────────────────────────────────────
function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function avatarColor(userId) {
  return AVATAR_COLORS[(userId ?? 0) % AVATAR_COLORS.length]
}

function formatDateTime(str) {
  if (!str) return '—'
  return new Date(str).toLocaleDateString('en-ZA', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatFailureReason(reason) {
  if (!reason) return '—'
  return FAILURE_REASON_DISPLAY[reason] ?? reason
}

function parseAgent(ua) {
  if (!ua) return '—'
  let browser = 'Browser'
  if (ua.includes('Edg/'))           browser = 'Edge'
  else if (ua.includes('Chrome/'))   browser = 'Chrome'
  else if (ua.includes('Firefox/'))  browser = 'Firefox'
  else if (ua.includes('Safari/'))   browser = 'Safari'
  let os = ''
  if (ua.includes('Windows'))                                   os = 'Windows'
  else if (ua.includes('Macintosh') || ua.includes('Mac OS'))   os = 'macOS'
  else if (ua.includes('iPhone') || ua.includes('iPad'))        os = 'iOS'
  else if (ua.includes('Android'))                              os = 'Android'
  else if (ua.includes('Linux'))                                os = 'Linux'
  return os ? `${browser} / ${os}` : browser
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadLogs(page = 1) {
  loading.value = true
  try {
    const params = { page }
    if (searchEmail.value)  params.email          = searchEmail.value
    if (filterStatus.value) params.status         = filterStatus.value
    if (filterReason.value) params.failure_reason = filterReason.value

    const res = await api.get('/login-logs', { params })
    logs.value        = res.data.data
    currentPage.value = res.data.current_page
    lastPage.value    = res.data.last_page
    total.value       = res.data.total
  } catch {
    logs.value = []
  } finally {
    loading.value = false
  }
}

const debouncedApplyFilters = debounce(() => applyFilters(), 300)
function applyFilters() {
  loadLogs(1)
}

function clearFilters() {
  searchEmail.value  = ''
  filterStatus.value = ''
  filterReason.value = ''
  loadLogs(1)
}

onMounted(() => loadLogs(1))
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- Header -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Login Audit</h1>
      <p class="text-sm text-muted-foreground mt-1">Audit log of all login attempts across the platform.</p>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-end gap-3">
      <!-- Email search -->
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-muted-foreground mb-1">Search by email</label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
          <input
            v-model="searchEmail"
            type="text"
            placeholder="user@example.com"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            @input="debouncedApplyFilters"
          />
        </div>
      </div>

      <!-- Status filter -->
      <div class="min-w-[140px]">
        <label class="block text-xs font-medium text-muted-foreground mb-1">Status</label>
        <select
          v-model="filterStatus"
          class="w-full px-3 py-2 text-sm rounded-lg border bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
        >
          <option value="">All</option>
          <option value="success">Success</option>
          <option value="failed">Failed</option>
        </select>
      </div>

      <!-- Failure reason filter -->
      <div class="min-w-[180px]">
        <label class="block text-xs font-medium text-muted-foreground mb-1">Failure Reason</label>
        <select
          v-model="filterReason"
          class="w-full px-3 py-2 text-sm rounded-lg border bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
        >
          <option value="">All</option>
          <option value="user_not_found">User Not Found</option>
          <option value="wrong_password">Wrong Password</option>
          <option value="account_inactive">Account Inactive</option>
          <option value="account_invited">Not Activated</option>
        </select>
      </div>

      <!-- Actions -->
      <div class="flex items-end gap-2">
        <AppButton @click="applyFilters">Apply</AppButton>
        <AppButton variant="outline" @click="clearFilters">Clear</AppButton>
      </div>
    </div>

    <!-- Table card -->
    <div class="rounded-lg border bg-card shadow-sm overflow-hidden">

      <!-- Table header meta -->
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <span class="text-sm font-medium text-foreground">Login Attempts</span>
        <span class="text-xs text-muted-foreground">{{ total.toLocaleString() }} total</span>
      </div>

      <!-- Loading skeleton -->
      <template v-if="loading">
        <div class="divide-y">
          <div v-for="n in 8" :key="n" class="px-4 py-3 flex items-center gap-4 animate-pulse">
            <div class="w-8 h-8 rounded-full bg-muted shrink-0"></div>
            <div class="flex-1 space-y-1.5">
              <div class="h-3 w-40 bg-muted rounded"></div>
              <div class="h-3 w-28 bg-muted rounded"></div>
            </div>
            <div class="h-3 w-16 bg-muted rounded"></div>
            <div class="h-3 w-20 bg-muted rounded"></div>
            <div class="h-3 w-24 bg-muted rounded"></div>
          </div>
        </div>
      </template>

      <!-- Empty state -->
      <div v-else-if="!logs.length" class="px-4 py-16 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-muted-foreground/40 mx-auto mb-3">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
        </svg>
        <p class="text-sm text-muted-foreground">No login attempts found.</p>
        <p v-if="searchEmail || filterStatus || filterReason" class="text-xs text-muted-foreground mt-1">
          Try clearing your filters.
        </p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/30">
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">User</th>
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Date &amp; Time</th>
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Status</th>
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Reason</th>
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">IP Address</th>
              <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Device</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id" class="border-b last:border-0 hover:bg-muted/20 transition-colors">
              <!-- User -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2.5">
                  <div v-if="log.user" :class="['w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0', avatarColor(log.user.id)]">
                    {{ getInitials(log.user.name) }}
                  </div>
                  <div v-else class="w-7 h-7 rounded-full bg-muted flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                      <circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/>
                    </svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-medium text-foreground truncate">{{ log.user?.name ?? 'Unknown' }}</p>
                    <p class="text-[11px] text-muted-foreground truncate">{{ log.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Date -->
              <td class="px-4 py-3 text-xs text-foreground whitespace-nowrap">{{ formatDateTime(log.created_at) }}</td>

              <!-- Status -->
              <td class="px-4 py-3 whitespace-nowrap">
                <span v-if="log.login_successful" class="inline-flex items-center gap-1 text-xs font-medium text-success">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                  </svg>
                  Success
                </span>
                <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-danger">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>
                  </svg>
                  Failed
                </span>
              </td>

              <!-- Reason -->
              <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">{{ formatFailureReason(log.failure_reason) }}</td>

              <!-- IP -->
              <td class="px-4 py-3 font-mono text-xs text-muted-foreground whitespace-nowrap">{{ log.ip_address ?? '—' }}</td>

              <!-- Device -->
              <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">{{ parseAgent(log.user_agent) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t text-sm">
        <span class="text-xs text-muted-foreground">Page {{ currentPage }} of {{ lastPage }}</span>
        <div class="flex items-center gap-2">
          <AppButton
            variant="outline"
            size="sm"
            :disabled="currentPage <= 1"
            @click="loadLogs(currentPage - 1)"
          >
            Previous
          </AppButton>
          <AppButton
            variant="outline"
            size="sm"
            :disabled="currentPage >= lastPage"
            @click="loadLogs(currentPage + 1)"
          >
            Next
          </AppButton>
        </div>
      </div>
    </div>

  </div>
</template>
