<!--
  CommunityInfoModal — WeConnectU "community info" modal shown when a community
  row is clicked. Left nav switches tabs (Information, Admin Charges, Bank
  Details, Roles, Trustees — Integrations intentionally excluded), with
  "Go to Settings" and "Login" actions at the bottom.

  Props:
    show      — visibility
    community — the community row object (for instant header + info); full detail
                (bank accounts / trustees) is fetched on open.
  Emits:
    close
-->
<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { entityTypeLabel } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'
import { useCommunityStore } from '@/stores/community'

const props = defineProps({
  show:      { type: Boolean, default: false },
  community: { type: Object,  default: null },
})
const emit = defineEmits(['close'])

const router = useRouter()
const countryStore = useCountryStore()
const communityStore = useCommunityStore()

const TABS = [
  { key: 'information',  label: 'Information'   },
  { key: 'admin',        label: 'Admin Charges' },
  { key: 'bank',         label: 'Bank Details'  },
  { key: 'roles',        label: 'Roles'         },
  { key: 'trustees',     label: 'Trustees'      },
]
const activeTab = ref('information')

// Merged view: the row object, overlaid with fresh detail once fetched.
const detail = ref(null)
const loading = ref(false)
const data = computed(() => ({ ...(props.community || {}), ...(detail.value || {}) }))

const headerTitle = computed(() => {
  const c = data.value
  const code = c.code ? `${String(c.code).toUpperCase()} - ` : ''
  return `${code}${(c.name || '').toUpperCase()}`
})

function fmtMoney(v) {
  if (v === null || v === undefined || v === '') return '—'
  return countryStore.formatCurrency(Number(v))
}

const AUTH_LABEL = {
  single: 'Single authorisation required',
  two:    'Two authorisations required',
  auto:   'Automatic authorisation',
}
const authLabel = computed(() => AUTH_LABEL[data.value.payment_authorisation_mode] || 'Single authorisation required')

const primaryBank = computed(() => (data.value.bank_accounts || [])[0] || null)
const trustees = computed(() => data.value.directors_trustees || [])

const BANK_TYPE_LABEL = { current: 'CURRENT', savings: 'SAVINGS', investment: 'INVESTMENT', transmission: 'TRANSMISSION' }
function bankType(t) { return BANK_TYPE_LABEL[t] || (t ? String(t).toUpperCase() : '—') }

async function fetchDetail() {
  if (!props.community?.id) return
  loading.value = true
  try {
    const { data: res } = await api.get(`/communities/${props.community.id}`)
    detail.value = res.data ?? res
  } catch {
    // keep row data
  } finally {
    loading.value = false
  }
}

watch(() => props.show, (open) => {
  if (open) {
    activeTab.value = 'information'
    detail.value = null
    fetchDetail()
  }
})

function close() { emit('close') }

// Login / Go to Settings enter the community in a NEW TAB (WeConnectU behaviour).
function openInNewTab(query) {
  const href = router.resolve({ path: `/communities/${props.community.id}`, query }).href
  window.open(href, '_blank')
  close()
}
function login() {
  openInNewTab({ login: '1' })
}
function goToSettings() {
  openSettings('/settings/general')
}

// Open a specific community-scoped settings page in a NEW TAB. The settings
// pages read the active community from the store (persisted to localStorage),
// so we select it first before opening the tab.
function openSettings(path) {
  communityStore.select(props.community.id, props.community)
  window.open(router.resolve({ path }).href, '_blank')
  close()
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="close"></div>

        <div class="relative w-full max-w-4xl max-h-[85vh] bg-card rounded-xl shadow-2xl flex overflow-hidden">

          <!-- Left nav — square items with a left-edge bar on the active tab -->
          <div class="w-56 shrink-0 border-r border-border bg-muted/20 flex flex-col py-4">
            <nav class="flex-1">
              <button
                v-for="tab in TABS"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key"
                :class="[
                  'w-full text-left px-5 py-3 text-sm font-medium transition-colors border-l-2',
                  activeTab === tab.key
                    ? 'border-accent text-accent bg-card'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted',
                ]"
              >{{ tab.label }}</button>
            </nav>

            <div class="mt-3 pt-3 border-t border-border">
              <button
                type="button"
                @click="goToSettings"
                class="w-full text-left px-5 py-3 border-l-2 border-transparent text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
              >Go to Settings</button>
              <button
                type="button"
                @click="login"
                class="w-full text-left px-5 py-3 border-l-2 border-transparent text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
              >Login</button>
            </div>
          </div>

          <!-- Right content -->
          <div class="flex-1 min-w-0 flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-border">
              <h3 class="font-body font-bold text-lg text-foreground truncate">{{ headerTitle }}</h3>
              <button
                type="button"
                @click="close"
                class="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto px-6 py-5 min-h-[320px]">

              <!-- Information -->
              <dl v-if="activeTab === 'information'" class="space-y-4">
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Units:</dt><dd class="text-sm font-semibold text-foreground">{{ data.units_count ?? 0 }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Entity Type:</dt><dd class="text-sm font-semibold text-foreground">{{ entityTypeLabel(data.entity_type) }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Physical Address:</dt><dd class="text-sm font-semibold text-foreground">{{ data.address || '—' }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Community Registration:</dt><dd class="text-sm font-semibold text-foreground">{{ data.registration_number || '—' }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">CSOS Registration:</dt><dd class="text-sm font-semibold text-foreground">{{ data.csos_registration_number || '—' }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Income Tax Number:</dt><dd class="text-sm font-semibold text-foreground">{{ data.income_tax_number || '—' }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Water Recovery:</dt><dd><svg v-if="data.water_recovery" class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span v-else class="text-sm text-muted-foreground">—</span></dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Electricity Recovery:</dt><dd><svg v-if="data.electricity_recovery" class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span v-else class="text-sm text-muted-foreground">—</span></dd></div>
                <div class="pt-3"><button type="button" @click="openSettings('/settings/general')" class="text-xs font-medium text-accent hover:underline">Manage in General settings →</button></div>
              </dl>

              <!-- Admin Charges -->
              <dl v-else-if="activeTab === 'admin'" class="space-y-4">
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Penalty Admin Fee</dt><dd class="text-sm font-semibold text-foreground">{{ fmtMoney(data.penalty_admin_fee) }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Warning Admin Fee</dt><dd class="text-sm font-semibold text-foreground">{{ fmtMoney(data.warning_admin_fee) }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Transfer Clearance Fee</dt><dd class="text-sm font-semibold text-foreground">{{ fmtMoney(data.transfer_clearance_fee) }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Phonecall</dt><dd class="text-sm font-semibold text-foreground">{{ fmtMoney(data.phonecall_fee) }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Interest %</dt><dd class="text-sm font-semibold text-foreground">{{ (data.interest_rate ?? 0) }}% {{ data.interest_period === 'per_month' ? 'per month' : 'per year' }}</dd></div>
                <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Apply Debt Collection Fee</dt><dd><svg v-if="data.apply_debt_collection_fee" class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span v-else class="text-sm text-muted-foreground">—</span></dd></div>
                <div class="pt-3"><button type="button" @click="openSettings('/settings/charges')" class="text-xs font-medium text-accent hover:underline">Manage in Charges settings →</button></div>
              </dl>

              <!-- Bank Details — WeConnectU always shows the fields ("—" when empty) -->
              <div v-else-if="activeTab === 'bank'">
                <dl class="space-y-4">
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Bank:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank?.bank_name ? primaryBank.bank_name.toUpperCase() : '—' }}</dd></div>
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Number:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank?.account_number || '—' }}</dd></div>
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Type:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank ? bankType(primaryBank.type) : '—' }}</dd></div>
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Code:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank?.branch_code || '—' }}</dd></div>
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Branch:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank?.branch_name ? primaryBank.branch_name.toUpperCase() : '—' }}</dd></div>
                  <div class="grid grid-cols-[200px_1fr] gap-4"><dt class="text-sm text-muted-foreground">Integration:</dt><dd class="text-sm font-semibold text-foreground">{{ primaryBank?.integration || '—' }}</dd></div>
                </dl>
                <div class="pt-3"><button type="button" @click="openSettings('/settings/general')" class="text-xs font-medium text-accent hover:underline">Manage in General settings →</button></div>
              </div>

              <!-- Roles — WeConnectU leaves this blank unless portal roles are assigned -->
              <template v-else-if="activeTab === 'roles'">
                <div class="py-10 text-center text-sm text-muted-foreground">
                  No community roles assigned.
                  <div class="mt-3">
                    <button type="button" @click="openSettings('/settings/users')" class="text-xs font-medium text-accent hover:underline">Manage community roles →</button>
                  </div>
                </div>
              </template>

              <!-- Trustees -->
              <template v-else-if="activeTab === 'trustees'">
                <div class="flex items-center gap-2 text-sm text-foreground">
                  <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"/></svg>
                  {{ authLabel }}
                </div>
                <ul v-if="trustees.length" class="mt-5 divide-y divide-border border border-border">
                  <li v-for="t in trustees" :key="t.id" class="flex items-center justify-between gap-4 px-4 py-3">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-foreground truncate">{{ t.name }}</p>
                      <p class="text-xs text-muted-foreground truncate">{{ t.email || '—' }}</p>
                    </div>
                    <span class="text-xs text-muted-foreground shrink-0">{{ t.cellphone || '' }}</span>
                  </li>
                </ul>
                <div class="mt-5">
                  <button type="button" @click="openSettings('/settings/users')" class="text-xs font-medium text-accent hover:underline">Manage directors &amp; trustees →</button>
                </div>
              </template>

            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
