<!--
  Supplier Age Analysis — strict WeConnectU clone (Bold Mark branding).

  Per-community supplier ageing grid with the WeConnectU controls: Financial Year
  / Budget Period selector, Ageing Date, Hide Zero / Hide Negative toggles, a
  green Download Excel button, a Search box, the aged buckets
  (120+/90/60/30/Current) + Balance and a totals row. Clicking a supplier drills
  into its Detailed Ledger (Date · Source · Description · Remarks · Debit · Credit
  · Cumulative) with the "Supplier Invoice" GRV rows linking to their PDF, and a
  "« Back to Supplier Ledgers" button.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton     from '@/components/common/AppButton.vue'
import api           from '@/composables/useApi'
import { useToast }  from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

const communityId   = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')

// ── Data ────────────────────────────────────────────────────────────────
const loading = ref(false)
const error   = ref(null)
const rows    = ref([])
const totals  = ref({})

// Drill-down state (null = summary view).
const openLedger = ref(null)   // { heading, rows, totals }
const openLoading = ref(false)

// ── Controls ────────────────────────────────────────────────────────────
// WeConnectU defaults the ageing date to the LAST day of the current month.
function endOfCurrentMonth() {
  const d    = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  const mm   = String(last.getMonth() + 1).padStart(2, '0')
  const dd   = String(last.getDate()).padStart(2, '0')
  return `${last.getFullYear()}-${mm}-${dd}`
}
const ageingDate = ref(endOfCurrentMonth())

const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)

const hideZero     = ref(true)   // WeConnectU defaults Hide Zero Values on
const hideNegative = ref(false)
const search       = ref('')

// ── FY selector groupings ──────────────────────────────────────────────────
const pastPeriods    = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod  = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods  = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.year === selectedPeriodKey.value)?.label ?? 'Select period',
)

// ── Formatting (WeConnectU: plain 2dp, space thousands, no symbol) ──────────
function fmt(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

// ── Requests ────────────────────────────────────────────────────────────────
function buildParams() {
  const p = { ageing_date: ageingDate.value }
  if (hideZero.value)      p.hide_zero     = true
  if (hideNegative.value)  p.hide_negative = true
  if (search.value.trim()) p._search       = search.value.trim()
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/supplier-age-analysis`, { params: buildParams() })
    rows.value   = data.rows ?? []
    totals.value = data.totals ?? {}
  } catch (e) {
    error.value = 'Failed to load supplier age analysis. Please try again.'
  } finally {
    loading.value = false
  }
}

async function loadPeriods() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/financial-years`)
    periods.value = data.periods ?? []
    const current = periods.value.find(p => p.is_current)
    if (current) selectedPeriodKey.value = current.year
  } catch { /* selector just stays empty */ }
}

function selectPeriod(p) {
  selectedPeriodKey.value = p.year
  ageingDate.value        = p.end
  fyOpen.value            = false
  fetchData()
}

async function openSupplier(row) {
  if (!communityId.value) return
  openLoading.value = true
  try {
    const { data } = await api.get(
      `/communities/${communityId.value}/supplier-age-analysis/${row.supplier_id}`,
      { params: { ageing_date: ageingDate.value } },
    )
    openLedger.value = data.ledger
  } catch (e) {
    toastError('Could not load the supplier ledger.')
  } finally {
    openLoading.value = false
  }
}

function backToLedgers() {
  openLedger.value = null
}

async function openGrv(grv) {
  if (!communityId.value || !grv?.id) return
  try {
    const res = await api.get(
      `/communities/${communityId.value}/supplier-invoices/${grv.id}/pdf`,
      { responseType: 'blob' },
    )
    const url = URL.createObjectURL(res.data)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    toastError('Could not open the supplier invoice.')
  }
}

async function downloadExcel() {
  if (!communityId.value) return
  try {
    const res = await api.get(`/communities/${communityId.value}/supplier-age-analysis/export`, {
      params: buildParams(),
      responseType: 'blob',
    })
    let filename = `supplier age analysis-${(communityName.value || 'community').toLowerCase()}-${ageingDate.value}.xlsx`
    const cd = res.headers['content-disposition'] || ''
    const m  = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(cd)
    if (m) filename = decodeURIComponent(m[1].replace(/"/g, ''))
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e) {
    toastError('Could not download the Excel file.')
  }
}

// ── Init / reactivity ──────────────────────────────────────────────────────
function boot() {
  if (!communityId.value) return
  openLedger.value = null
  loadPeriods()
  fetchData()
}

onMounted(() => {
  if (!communityStore.loaded) communityStore.fetch()
  boot()
})

watch(communityId, () => {
  rows.value = []
  totals.value = {}
  boot()
})
</script>

<template>
  <div class="p-6">
    <!-- No community selected -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view its supplier age analysis.
      <div class="mt-4">
        <AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton>
      </div>
    </div>

    <template v-else>
      <!-- ══════════ Header band ══════════ -->
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Supplier Age Analysis</h1>

        <!-- Financial Year / Budget Period -->
        <div class="mt-4">
          <label class="mb-1 block text-xs font-bold text-muted-foreground">Financial Year / Budget Period:</label>
          <div class="relative w-full max-w-md">
            <button
              type="button"
              class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none"
              @click="fyOpen = !fyOpen"
            >
              <span>{{ selectedPeriodLabel }}</span>
              <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            <div v-if="fyOpen" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
              <div v-if="pastPeriods.length" class="px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Past financial years</div>
              <button v-for="p in pastPeriods" :key="p.year" type="button"
                      class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                      :class="p.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                      @click="selectPeriod(p)">{{ p.label }}</button>

              <template v-if="currentPeriod">
                <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Current financial year end</div>
                <button type="button"
                        class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                        :class="currentPeriod.year === selectedPeriodKey ? 'bg-muted font-medium' : ''"
                        @click="selectPeriod(currentPeriod)">{{ currentPeriod.label }}</button>
              </template>

              <template v-if="futurePeriods.length">
                <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Future financial years</div>
                <button v-for="p in futurePeriods" :key="p.year" type="button"
                        class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
                        :class="[p.year === selectedPeriodKey ? 'bg-muted font-medium' : '', p.is_setup ? '' : 'text-destructive']"
                        @click="selectPeriod(p)">{{ p.label }}</button>
              </template>
            </div>
          </div>
        </div>

        <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-[#2c9b67]">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
          All transactions allocated.
        </p>

        <!-- Ageing date + toggles -->
        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Ageing Date:</label>
            <input v-model="ageingDate" type="date"
                   class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none"
                   @change="fetchData" />
          </div>

          <div class="flex flex-wrap items-center gap-4 pb-2.5 text-sm">
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Hide Zero Values
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="hideNegative" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Hide Negative Values
            </label>
          </div>
        </div>
      </div>

      <!-- ══════════ Table card ══════════ -->
      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <!-- Toolbar: Download Excel + Search -->
        <div class="mb-4 flex items-center justify-end gap-3">
          <button
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md bg-[#2f6fb0] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#2a63a0]"
            @click="downloadExcel"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" />
            </svg>
            Download Excel
          </button>
        </div>

        <div v-if="!openLedger" class="mb-3 flex items-center justify-end gap-2 text-sm">
          <label class="text-muted-foreground">Search:</label>
          <input
            v-model="search"
            type="text"
            class="h-9 w-56 rounded-md border border-border px-3 text-sm focus:border-navy focus:outline-none"
            @keyup.enter="fetchData"
            @input="fetchData"
          />
        </div>

        <!-- ── Summary view ── -->
        <div v-if="!openLedger" class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Supplier</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">120+ days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">90 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">60 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">30 days</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Current</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="7" class="border border-border px-3 py-10 text-center text-muted-foreground">
                  No suppliers match the current filters.
                </td>
              </tr>
              <template v-else>
                <tr v-for="row in rows" :key="row.supplier_id" class="hover:bg-muted/30">
                  <td class="border border-border px-3 py-2">
                    <button type="button" class="text-left font-medium text-[#2f6fb0] hover:underline" @click="openSupplier(row)">
                      {{ row.label }}
                    </button>
                  </td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['120_plus']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['90_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['60_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row['30_days']) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(row.current) }}</td>
                  <td class="border border-border px-3 py-2 text-right font-semibold tabular-nums text-navy-dark">{{ fmt(row.balance) }}</td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="!loading && rows.length">
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5">Totals</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['120_plus']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['90_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['60_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals['30_days']) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals.current) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals.balance) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ── Detailed-ledger drill-down ── -->
        <div v-else>
          <h2 class="mb-3 font-body text-lg font-bold text-navy-dark">{{ openLedger.heading }}</h2>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr>
                  <th class="border border-border px-3 py-2 text-left text-[13px] font-bold text-navy-dark">Date</th>
                  <th class="border border-border px-3 py-2 text-left text-[13px] font-bold text-navy-dark">Source</th>
                  <th class="border border-border px-3 py-2 text-left text-[13px] font-bold text-navy-dark">Description</th>
                  <th class="border border-border px-3 py-2 text-left text-[13px] font-bold text-navy-dark">Remarks</th>
                  <th class="border border-border px-3 py-2 text-right text-[13px] font-bold text-navy-dark">Debit</th>
                  <th class="border border-border px-3 py-2 text-right text-[13px] font-bold text-navy-dark">Credit</th>
                  <th class="border border-border px-3 py-2 text-right text-[13px] font-bold text-navy-dark">Cumulative</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="openLoading">
                  <td colspan="7" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td>
                </tr>
                <tr v-else-if="openLedger.rows.length === 0">
                  <td colspan="7" class="border border-border px-3 py-10 text-center text-muted-foreground">No transactions.</td>
                </tr>
                <tr v-for="(r, i) in openLedger.rows" :key="i">
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ r.date }}</td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ r.source }}</td>
                  <td class="border border-border px-3 py-2">
                    <button v-if="r.grv" type="button" class="text-left font-medium text-[#2f6fb0] hover:underline" @click="openGrv(r.grv)">
                      {{ r.description }}
                    </button>
                    <span v-else class="text-navy-dark">{{ r.description }}</span>
                  </td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ r.remarks }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ r.debit ? fmt(r.debit) : '' }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ r.credit ? fmt(r.credit) : '' }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmt(r.cumulative) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="!openLoading && openLedger.rows.length">
                <tr class="font-bold text-navy-dark">
                  <td class="border border-border px-3 py-2.5" colspan="4"></td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(openLedger.totals.debit) }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(openLedger.totals.credit) }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(openLedger.totals.cumulative) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-4">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-[#6ea8dc] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5c99d0]" @click="backToLedgers">
              « Back to Supplier Ledgers
            </button>
          </div>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
