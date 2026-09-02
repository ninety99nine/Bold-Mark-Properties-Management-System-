<!--
  Detailed Customer Ledger — strict WeConnectU clone (Bold Mark branding).

  Financial Year / Budget Period selector, Date from/to, Customer + OR Group +
  Status multi-selects (each with an "All" toggle), Hide Zero Values, Show
  Invoices Line Items, Run Report + Email Report, a Recent Email Reports panel
  and a "Please note" hint. Running the report renders a per-customer ledger
  (Date · Source · Description · Remarks · Debit · Credit · Balance + totals)
  with a green Download Excel.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton      from '@/components/common/AppButton.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import api            from '@/composables/useApi'
import { useToast }   from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { success: toastSuccess, error: toastError, info } = useToast()

const communityId   = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')

// ── FY selector ─────────────────────────────────────────────────────────
const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)
const pastPeriods   = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.year === selectedPeriodKey.value)?.label ?? 'Select period',
)

// ── Filters ─────────────────────────────────────────────────────────────
function firstOfMonthsAgo(n) {
  const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}
function endOfMonth() {
  const d = new Date(); const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const dateFrom = ref(firstOfMonthsAgo(2))
const dateTo   = ref(endOfMonth())

const customerIds = ref([])
const groupIds    = ref([])
const statuses    = ref([])
const allCustomers = ref(false)
const allGroups    = ref(false)
const hideZero     = ref(false)
const showLineItems = ref(true)

const customerOptions = ref([])
const groupOptions    = ref([])
const STATUS_OPTIONS = [
  { value: 'handed_over',         label: 'Handed Over' },
  { value: 'payment_arrangement', label: 'Payment Arrangement' },
]

// ── Report data ─────────────────────────────────────────────────────────
const ledgers   = ref([])
const collapsed = ref(new Set())
const hasRun    = ref(false)
const running   = ref(false)
const recentEmailReports = ref([])

function fmt(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

// ── Loads ───────────────────────────────────────────────────────────────
async function loadPeriods() {
  try {
    const { data } = await api.get(`/communities/${communityId.value}/financial-years`)
    periods.value = data.periods ?? []
    const current = periods.value.find(p => p.is_current)
    if (current) selectedPeriodKey.value = current.year
  } catch { /* ignore */ }
}
async function loadCustomers() {
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customers`, { params: { _per_page: 1000, _relationships: 'unit' } })
    customerOptions.value = (data.data ?? [])
      .filter(o => o.unit?.id)
      .map(o => ({ value: o.unit.id, label: `${o.code || ''}${o.code ? ' - ' : ''}${o.full_name} (Unit No ${o.unit.unit_number})` }))
  } catch { customerOptions.value = [] }
}
async function loadGroups() {
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-groups`, { params: { _per_page: 200 } })
    groupOptions.value = (data.data ?? []).map(g => ({ value: g.id, label: g.name }))
  } catch { groupOptions.value = [] }
}

function selectPeriod(p) {
  selectedPeriodKey.value = p.year
  if (p.start) dateFrom.value = p.start
  if (p.end)   dateTo.value   = p.end
  fyOpen.value = false
}

function buildParams() {
  const p = {}
  if (dateFrom.value) p.date_from = dateFrom.value
  if (dateTo.value)   p.date_to   = dateTo.value
  if (allCustomers.value) p.all_customers = true
  else customerIds.value.forEach(id => { (p['customer_ids[]'] = p['customer_ids[]'] || []).push(id) })
  groupIds.value.forEach(id => { (p['group_ids[]'] = p['group_ids[]'] || []).push(id) })
  statuses.value.forEach(s => { (p['statuses[]'] = p['statuses[]'] || []).push(s) })
  if (hideZero.value)      p.hide_zero       = true
  if (showLineItems.value) p.show_line_items = true
  return p
}

async function runReport() {
  if (!communityId.value || running.value) return
  running.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/detailed-ledger`, { params: buildParams() })
    ledgers.value = data.ledgers ?? []
    collapsed.value = new Set()
    hasRun.value = true
  } catch (e) {
    toastError('Failed to run the report. Please try again.')
  } finally {
    running.value = false
  }
}

async function loadRecentReports() {
  try {
    const { data } = await api.get(`/communities/${communityId.value}/detailed-ledger/reports`)
    recentEmailReports.value = data.data ?? []
  } catch { recentEmailReports.value = [] }
}

async function emailReport() {
  try {
    await api.post(`/communities/${communityId.value}/detailed-ledger/email`, {}, { params: buildParams() })
    info('The report has been generated and e-mailed to you shortly.')
    loadRecentReports()
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Could not request the report.')
  }
}

async function downloadReport(report) {
  try {
    const res = await api.get(`/communities/${communityId.value}/detailed-ledger/reports/${report.id}/download`, { responseType: 'blob' })
    let filename = `cl-${(communityName.value || 'community').toLowerCase()}.xlsx`
    const cd = res.headers['content-disposition'] || ''
    const m = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url)
  } catch { toastError('Could not download the report.') }
}

async function downloadExcel() {
  try {
    const res = await api.get(`/communities/${communityId.value}/detailed-ledger/export`, { params: buildParams(), responseType: 'blob' })
    let filename = `detailed customer ledger-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`
    const cd = res.headers['content-disposition'] || ''
    const m = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url)
  } catch { toastError('Could not download the Excel file.') }
}

function toggleCollapse(id) {
  const s = new Set(collapsed.value)
  s.has(id) ? s.delete(id) : s.add(id)
  collapsed.value = s
}

function boot() {
  if (!communityId.value) return
  loadPeriods(); loadCustomers(); loadGroups(); loadRecentReports()
}
onMounted(() => { if (!communityStore.loaded) communityStore.fetch(); boot() })
watch(communityId, () => { ledgers.value = []; hasRun.value = false; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the detailed customer ledger.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <!-- Title + FY -->
      <h1 class="font-body text-2xl font-bold text-navy-dark">Detailed Customer Ledger</h1>
      <div class="mt-3">
        <label class="mb-1 block text-xs font-bold text-muted-foreground">Financial Year / Budget Period:</label>
        <div class="relative w-full max-w-md">
          <button type="button" class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none" @click="fyOpen = !fyOpen">
            <span>{{ selectedPeriodLabel }}</span>
            <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
          </button>
          <div v-if="fyOpen" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
            <div v-if="pastPeriods.length" class="px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Past financial years</div>
            <button v-for="p in pastPeriods" :key="p.year" type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="p.year === selectedPeriodKey ? 'bg-muted font-medium' : ''" @click="selectPeriod(p)">{{ p.label }}</button>
            <template v-if="currentPeriod">
              <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Current financial year end</div>
              <button type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="currentPeriod.year === selectedPeriodKey ? 'bg-muted font-medium' : ''" @click="selectPeriod(currentPeriod)">{{ currentPeriod.label }}</button>
            </template>
            <template v-if="futurePeriods.length">
              <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Future financial years</div>
              <button v-for="p in futurePeriods" :key="p.year" type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="[p.year === selectedPeriodKey ? 'bg-muted font-medium' : '', p.is_setup ? '' : 'text-destructive']" @click="selectPeriod(p)">{{ p.label }}</button>
            </template>
          </div>
        </div>
        <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-green-600">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 14-4-4 1.4-1.4L11 13.2l4.6-4.6L17 10l-6 6z"/></svg>
          All transactions allocated.
        </p>
      </div>

      <!-- Filters + Recent Email Reports -->
      <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,560px)_1fr]">
        <!-- Filter card -->
        <div class="rounded-lg border border-border bg-white p-6">
          <div class="grid grid-cols-1 gap-4">
            <div class="flex flex-wrap items-end gap-x-8 gap-y-3">
              <div>
                <label class="mb-1 block text-sm text-muted-foreground">Date from:</label>
                <input v-model="dateFrom" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm text-muted-foreground">Date to:</label>
                <input v-model="dateTo" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-[80px_minmax(0,1fr)_auto] items-center gap-3">
              <label class="text-sm text-muted-foreground">Customer:</label>
              <AppMultiSelect v-model="customerIds" heading="Customers" :options="customerOptions" placeholder="Nothing selected" :disabled="allCustomers" />
              <label class="flex items-center gap-1.5 text-sm text-foreground"><input v-model="allCustomers" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> All</label>
            </div>
            <div class="grid grid-cols-[80px_minmax(0,1fr)_auto] items-center gap-3">
              <label class="text-sm text-muted-foreground">OR Group:</label>
              <AppMultiSelect v-model="groupIds" heading="Customer Groups" :options="groupOptions" placeholder="Nothing selected" :disabled="allGroups" />
              <label class="flex items-center gap-1.5 text-sm text-foreground"><input v-model="allGroups" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> All</label>
            </div>
            <div class="grid grid-cols-[80px_minmax(0,1fr)_auto] items-center gap-3">
              <label class="text-sm text-muted-foreground">Status:</label>
              <AppMultiSelect v-model="statuses" heading="Status" :options="STATUS_OPTIONS" placeholder="Nothing selected" />
              <span></span>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm select-none">
              <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> Hide Zero Values
            </label>
            <label class="flex cursor-pointer items-center gap-2 text-sm select-none">
              <input v-model="showLineItems" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> Show Invoices Line Items
            </label>

            <div class="flex items-center gap-3 pt-1">
              <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-dark disabled:opacity-60" :disabled="running" @click="runReport">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                {{ running ? 'Running…' : 'Run Report' }}
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-dark" @click="emailReport">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                Email Report
              </button>
            </div>
          </div>
        </div>

        <!-- Recent Email Reports -->
        <div class="rounded-lg border border-border bg-white p-6">
          <h3 class="text-base font-bold text-navy-dark">Recent Email Reports:</h3>
          <table class="mt-3 w-full text-sm">
            <thead>
              <tr class="text-left text-muted-foreground">
                <th class="py-2 font-semibold">Date Range</th>
                <th class="py-2 font-semibold">Status</th>
                <th class="py-2 font-semibold">Report Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in recentEmailReports" :key="i" class="border-t border-border">
                <td class="py-2">
                  <button type="button" class="font-medium text-[#2f6fb0] hover:underline" @click="downloadReport(r)">{{ r.date_range }}</button>
                </td>
                <td class="py-2">{{ r.status }}</td>
                <td class="py-2">{{ r.report_date }}</td>
              </tr>
              <tr v-if="!recentEmailReports.length"><td colspan="3" class="py-6 text-center text-muted-foreground">No email reports yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Please note -->
      <div class="mt-6 flex items-start gap-3 rounded-lg border border-[#bcdcff] bg-[#eaf3ff] p-4">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#2f8fe0] text-white">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
        </div>
        <p class="text-sm text-foreground">
          <span class="font-bold">Please note</span><br>
          For larger reports, such as those covering multiple accounts or extended periods, select "Email Report" to receive the report shortly via email. For reports with fewer accounts or shorter periods use the "Run Report" option to view it on screen.
        </p>
      </div>

      <!-- Results -->
      <div v-if="hasRun" class="mt-6">
        <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-[#4a90d9] px-3 py-2 text-sm font-semibold text-white hover:bg-[#3d7ec2]" @click="downloadExcel">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Download Excel
        </button>

        <div v-if="!ledgers.length" class="mt-6 rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
          No transactions match the current filters.
        </div>

        <div v-for="l in ledgers" :key="l.unit_id" class="mt-6">
          <h3 class="mb-2 flex items-center gap-2 text-lg font-bold text-navy-dark">
            {{ l.heading }}
            <button type="button" class="text-[#2f8fe0]" @click="toggleCollapse(l.unit_id)">
              <svg class="h-5 w-5 transition-transform" :class="collapsed.has(l.unit_id) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
            </button>
          </h3>
          <div v-show="!collapsed.has(l.unit_id)" class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-[#f4f5f7] text-left">
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Date</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Source</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Description</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Remarks</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Debit</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Credit</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in l.rows" :key="i" class="border-b border-border last:border-0">
                  <td class="px-3 py-2 whitespace-nowrap">{{ r.date }}</td>
                  <td class="px-3 py-2">{{ r.source }}</td>
                  <td class="px-3 py-2">{{ r.description }}</td>
                  <td class="px-3 py-2 text-muted-foreground">{{ r.remarks }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmt(r.debit) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmt(r.credit) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmt(r.balance) }}</td>
                </tr>
                <tr class="bg-[#f9fafb] font-bold text-navy-dark">
                  <td class="px-3 py-2.5" colspan="4"></td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmt(l.totals.debit) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmt(l.totals.credit) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmt(l.totals.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
