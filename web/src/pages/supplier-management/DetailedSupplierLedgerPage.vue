<!--
  Detailed Supplier Ledger — strict WeConnectU clone (Bold Mark branding).

  Financial Year / Budget Period selector, Date from/to, Supplier + OR Group
  multi-selects (each with an "All" toggle) and Hide Zero Values. Running the
  report renders a per-supplier ledger (Date · Source · Description · Remarks ·
  Debit · Credit · Balance + totals) with a blue Download Excel. Mirrors the
  Detailed Customer Ledger page for a uniform look and feel.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton      from '@/components/common/AppButton.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import AppTooltip     from '@/components/common/AppTooltip.vue'
import api            from '@/composables/useApi'
import { useToast }   from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

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

const supplierIds  = ref([])
const groupIds     = ref([])
const allSuppliers = ref(false)
const allGroups    = ref(false)
const hideZero     = ref(false)

const supplierOptions = ref([])
const groupOptions    = ref([])

// ── Report data ─────────────────────────────────────────────────────────
const ledgers   = ref([])
const collapsed = ref(new Set())
const hasRun    = ref(false)
const running   = ref(false)

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
const supplierOption = (s) => ({
  value: s.id,
  label: s.label || `${s.supplier_code || ''}${s.supplier_code ? ' - ' : ''}${s.name}`,
})

async function loadSuppliers() {
  try {
    const { data } = await api.get('/suppliers', { params: { community_id: communityId.value, _per_page: 50 } })
    supplierOptions.value = (data.data ?? []).map(supplierOption)
  } catch { supplierOptions.value = [] }
}

// API-backed search for the supplier picker (debounced inside AppMultiSelect).
async function searchSuppliers(query) {
  if (!communityId.value) return []
  const { data } = await api.get('/suppliers', {
    params: { community_id: communityId.value, search: query, _per_page: 50 },
  })
  return (data.data ?? []).map(supplierOption)
}
async function loadGroups() {
  try {
    const { data } = await api.get('/supplier-groups', { params: { community_id: communityId.value, _per_page: 200 } })
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
  if (allSuppliers.value) p.all_suppliers = true
  else supplierIds.value.forEach(id => { (p['supplier_ids[]'] = p['supplier_ids[]'] || []).push(id) })
  groupIds.value.forEach(id => { (p['group_ids[]'] = p['group_ids[]'] || []).push(id) })
  if (hideZero.value) p.hide_zero = true
  return p
}

async function runReport() {
  if (!communityId.value || running.value) return
  running.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/detailed-supplier-ledger`, { params: buildParams() })
    ledgers.value = data.ledgers ?? []
    collapsed.value = new Set()
    hasRun.value = true
  } catch {
    toastError('Failed to run the report. Please try again.')
  } finally {
    running.value = false
  }
}

async function downloadExcel() {
  try {
    const res = await api.get(`/communities/${communityId.value}/detailed-supplier-ledger/export`, { params: buildParams(), responseType: 'blob' })
    let filename = `detailed supplier ledger-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`
    const cd = res.headers['content-disposition'] || ''
    const m = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url)
  } catch { toastError('Could not download the Excel file.') }
}

// WeConnectU cashbook-allocation tooltip: "Allocated by {name} on {datetime}".
function allocationTip(r) {
  const who = r.allocated_by ? `Allocated by ${r.allocated_by}` : 'Allocated'
  return r.allocated_at ? `${who} on ${r.allocated_at}` : who
}

// Download a supplier invoice (GRV) PDF when its number/icon is clicked.
async function downloadSupplierInvoice(grvId, grvNumber) {
  if (!grvId) return
  try {
    const res = await api.get(`/communities/${communityId.value}/supplier-invoices/${grvId}/pdf`, { responseType: 'blob' })
    let filename = `${grvNumber || 'grv'}.pdf`
    const cd = res.headers['content-disposition'] || ''
    const m = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url)
  } catch { toastError('Could not download the supplier invoice.') }
}

function toggleCollapse(id) {
  const s = new Set(collapsed.value)
  s.has(id) ? s.delete(id) : s.add(id)
  collapsed.value = s
}

function boot() {
  if (!communityId.value) return
  loadPeriods(); loadSuppliers(); loadGroups()
}
onMounted(() => { if (!communityStore.loaded) communityStore.fetch(); boot() })
watch(communityId, () => { ledgers.value = []; hasRun.value = false; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the detailed supplier ledger.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <!-- Title + FY -->
      <h1 class="font-body text-2xl font-bold text-navy-dark">Detailed Supplier Ledger</h1>
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

      <!-- Filters -->
      <div class="mt-4 rounded-lg border border-border bg-white p-6">
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
            <label class="text-sm text-muted-foreground">Supplier:</label>
            <AppMultiSelect v-model="supplierIds" heading="Suppliers" :options="supplierOptions" :fetcher="searchSuppliers" placeholder="Nothing selected" :disabled="allSuppliers" />
            <label class="flex items-center gap-1.5 text-sm text-foreground"><input v-model="allSuppliers" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> All</label>
          </div>
          <div class="grid grid-cols-[80px_minmax(0,1fr)_auto] items-center gap-3">
            <label class="text-sm text-muted-foreground">OR Group:</label>
            <AppMultiSelect v-model="groupIds" heading="Supplier Groups" :options="groupOptions" placeholder="Nothing selected" :disabled="allGroups" />
            <label class="flex items-center gap-1.5 text-sm text-foreground"><input v-model="allGroups" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> All</label>
          </div>

          <label class="flex cursor-pointer items-center gap-2 text-sm select-none">
            <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border accent-navy" /> Hide Zero Values
          </label>

          <div class="flex items-center gap-3 pt-1">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-dark disabled:opacity-60" :disabled="running" @click="runReport">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              {{ running ? 'Running…' : 'Run Report' }}
            </button>
          </div>
        </div>
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

        <div v-for="l in ledgers" :key="l.supplier_id" class="mt-6">
          <h3 class="mb-2 flex items-center gap-2 text-lg font-bold text-navy-dark">
            {{ l.heading }}
            <button type="button" class="text-[#2f8fe0]" @click="toggleCollapse(l.supplier_id)">
              <svg class="h-5 w-5 transition-transform" :class="collapsed.has(l.supplier_id) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
            </button>
          </h3>
          <div v-show="!collapsed.has(l.supplier_id)" class="overflow-x-auto rounded-lg border border-border">
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
                  <td class="px-3 py-2">
                    <span class="inline-flex items-center gap-1.5">
                      {{ r.source }}
                      <AppTooltip v-if="r.allocated_by || r.allocated_at" :text="allocationTip(r)">
                        <svg class="h-4 w-4 text-[#2f8fe0]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 5a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 12 7zm1.25 10h-2.5v-6h2.5z"/></svg>
                      </AppTooltip>
                    </span>
                  </td>
                  <td class="px-3 py-2">
                    <template v-if="r.grv">
                      <button type="button" class="text-left text-[#2f8fe0] hover:underline" @click="downloadSupplierInvoice(r.grv.id, r.grv.number)">{{ r.description }}</button>
                      <div class="mt-1">
                        <button type="button" title="Download PDF" class="text-[#e2483d] hover:text-[#c53a30]" @click="downloadSupplierInvoice(r.grv.id, r.grv.number)">
                          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM8 13h1.5a1.5 1.5 0 0 1 0 3H9v1.5H8V13zm1 2h.5a.5.5 0 0 0 0-1H9v1zm3.5-2H14a1.5 1.5 0 0 1 1.5 1.5v0A1.5 1.5 0 0 1 14 16h-.5v1.5h-1V13zm1 2h.5a.5.5 0 0 0 .5-.5v0a.5.5 0 0 0-.5-.5h-.5v1zM16.5 13H18v1h-1v.75h1v1h-1V17h-.5v-4z"/></svg>
                        </button>
                      </div>
                    </template>
                    <template v-else>{{ r.description }}</template>
                  </td>
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
