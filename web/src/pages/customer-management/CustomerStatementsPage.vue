<!--
  Customer Statements — strict WeConnectU clone (Bold Mark branding).

  Per-community list of customers with the account balance as at "Date to", the
  same Financial Year / Budget Period selector as Age Analysis, Date from / Date
  to (+ Reset) with a green View PDF, Hide Zero / Hide Negative / Show Invoices
  Line Items toggles, a search box, per-row Download Statement, an E-Mail
  Statement column (Select All), a totals row and a Mail Statement button.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton     from '@/components/common/AppButton.vue'
import AgeStatusIcon from '@/components/age-analysis/AgeStatusIcon.vue'
import api           from '@/composables/useApi'
import { useToast }  from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { success: toastSuccess, error: toastError } = useToast()

const communityId   = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')

// ── Data ────────────────────────────────────────────────────────────────
const loading = ref(false)
const error   = ref(null)
const rows    = ref([])
const totals  = ref({})

// ── Controls ────────────────────────────────────────────────────────────
function today() {
  const d  = new Date()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${mm}-${dd}`
}
const dateFrom = ref('')
const dateTo   = ref(today())

const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)
const pastPeriods   = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.year === selectedPeriodKey.value)?.label ?? 'Select period',
)

const hideZero       = ref(false)
const hideNegative   = ref(false)
const showLineItems  = ref(false)
const search         = ref('')

// ── E-Mail Statement selection ────────────────────────────────────────────
const selected = ref(new Set())
const selectAll = computed({
  get: () => rows.value.length > 0 && rows.value.every(r => selected.value.has(r.unit_id)),
  set: (v) => {
    selected.value = v ? new Set(rows.value.map(r => r.unit_id)) : new Set()
  },
})
function toggleRow(id) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}
const selectedCount = computed(() => selected.value.size)

// ── Formatting (WeConnectU: plain 2dp, space thousands, no symbol) ──────────
function fmt(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

// ── Requests ────────────────────────────────────────────────────────────────
function buildParams() {
  const p = {}
  if (dateFrom.value)     p.date_from       = dateFrom.value
  if (dateTo.value)       p.date_to         = dateTo.value
  if (hideZero.value)     p.hide_zero       = true
  if (hideNegative.value) p.hide_negative   = true
  if (showLineItems.value) p.show_line_items = true
  if (search.value.trim()) p._search        = search.value.trim()
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-statements`, { params: buildParams() })
    rows.value   = data.rows ?? []
    totals.value = data.totals ?? {}
    selected.value = new Set()
  } catch (e) {
    error.value = 'Failed to load customer statements. Please try again.'
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
  if (p.start) dateFrom.value = p.start
  if (p.end)   dateTo.value   = p.end
  fyOpen.value = false
  fetchData()
}

function resetFrom() { dateFrom.value = ''; fetchData() }
function resetTo()   { dateTo.value = today(); fetchData() }

function goToCustomer(row) {
  if (!row.unit_id) return
  router.push({ name: 'unit-detail', params: { communityId: communityId.value, unitId: row.unit_id } })
}

// ── PDF: per-row download + combined View PDF ─────────────────────────────
async function openBlob(url, params, filename, inline = false) {
  const res = await api.get(url, { params, responseType: 'blob' })
  const objUrl = URL.createObjectURL(res.data)
  if (inline) {
    window.open(objUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objUrl), 60000)
  } else {
    const a = Object.assign(document.createElement('a'), { href: objUrl, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    URL.revokeObjectURL(objUrl)
  }
}

async function downloadRow(row) {
  try {
    await openBlob(
      `/communities/${communityId.value}/units/${row.unit_id}/statement`,
      { _format: 'pdf', from: dateFrom.value || undefined, to: dateTo.value || undefined },
      `CustomerStatement-${row.customer_code || row.unit_number}.pdf`,
    )
  } catch { toastError('Could not download the statement.') }
}

async function viewPdf() {
  try {
    await openBlob(`/communities/${communityId.value}/customer-statements/view`, buildParams(), null, true)
  } catch { toastError('Could not generate the statements PDF.') }
}

// ── Mail selected statements ──────────────────────────────────────────────
const mailing = ref(false)
async function mailSelected() {
  const ids = [...selected.value]
  if (!ids.length || mailing.value) return
  mailing.value = true
  let ok = 0, fail = 0
  for (const unitId of ids) {
    try {
      await api.post(`/communities/${communityId.value}/units/${unitId}/statement/email`, {
        from: dateFrom.value || null,
        to:   dateTo.value || null,
      })
      ok++
    } catch { fail++ }
  }
  mailing.value = false
  selected.value = new Set()
  if (ok) toastSuccess(`Statement${ok !== 1 ? 's' : ''} e-mailed to ${ok} customer${ok !== 1 ? 's' : ''}.${fail ? ` ${fail} failed.` : ''}`)
  else toastError('Could not e-mail the selected statements.')
}

// ── Init / reactivity ──────────────────────────────────────────────────────
function boot() {
  if (!communityId.value) return
  loadPeriods()
  fetchData()
}
onMounted(() => {
  if (!communityStore.loaded) communityStore.fetch()
  boot()
})
watch(communityId, () => { rows.value = []; totals.value = {}; boot() })
</script>

<template>
  <div class="p-6">
    <!-- No community selected -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view its customer statements.
      <div class="mt-4">
        <AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton>
      </div>
    </div>

    <template v-else>
      <!-- ══════════ Header band ══════════ -->
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Customer Statements</h1>

        <!-- Financial Year / Budget Period -->
        <div class="mt-4">
          <label class="mb-1 block text-xs font-bold text-muted-foreground">Financial Year / Budget Period:</label>
          <div class="relative w-full max-w-md">
            <button type="button"
                    class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none"
                    @click="fyOpen = !fyOpen">
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
                <button type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted"
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

        <!-- Date range + View PDF -->
        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date from:</label>
            <div class="flex items-center gap-2">
              <input v-model="dateFrom" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
              <button type="button" class="h-11 rounded-md border border-border bg-muted px-4 text-sm font-medium text-foreground hover:bg-muted/70" @click="resetFrom">Reset</button>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date to:</label>
            <div class="flex items-center gap-2">
              <input v-model="dateTo" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
              <button type="button" class="h-11 rounded-md border border-border bg-muted px-4 text-sm font-medium text-foreground hover:bg-muted/70" @click="resetTo">Reset</button>
            </div>
          </div>
          <div class="ml-auto pb-0.5">
            <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-md bg-[#2c9b67] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#268a5b]"
                    @click="viewPdf">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></svg>
              View PDF
            </button>
          </div>
        </div>

        <!-- Toggles -->
        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
          <label class="flex cursor-pointer items-center gap-2 select-none">
            <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
            Hide Zero Values
          </label>
          <label class="flex cursor-pointer items-center gap-2 select-none">
            <input v-model="hideNegative" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
            Hide Negative Values
          </label>
          <label class="flex cursor-pointer items-center gap-2 select-none">
            <input v-model="showLineItems" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
            Show Invoices Line Items
          </label>
        </div>
      </div>

      <!-- ══════════ Table card ══════════ -->
      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <!-- Search -->
        <div class="mb-4 flex items-center justify-end gap-2">
          <label class="text-sm font-medium text-foreground">Search:</label>
          <input v-model="search" type="text"
                 class="h-9 w-56 rounded-md border border-border px-3 text-sm focus:border-navy focus:outline-none"
                 @keyup.enter="fetchData" @input="fetchData" />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Unit No</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Customer</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Balance as @<br>{{ dateTo }}</th>
                <th class="border border-border px-1 py-2.5 w-10"></th>
                <th class="border border-border px-3 py-2.5 text-center text-[13px] font-bold text-navy-dark">
                  E-Mail Statement
                  <label class="mt-1 flex items-center justify-center gap-1.5 text-xs font-medium">
                    <input v-model="selectAll" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" />
                    Select All
                  </label>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="5" class="border border-border px-3 py-10 text-center text-muted-foreground">No customers match the current filters.</td>
              </tr>
              <template v-else>
                <tr v-for="row in rows" :key="row.unit_id" class="hover:bg-muted/30">
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ row.unit_no }}</td>
                  <td class="border border-border px-3 py-2">
                    <div class="flex items-center gap-2">
                      <button type="button" class="text-left font-medium text-[#2f6fb0] hover:underline" title="View Detailed Customer Ledger" @click="goToCustomer(row)">
                        {{ row.customer_code }}<template v-if="row.customer_code">: </template>{{ row.customer_name }}
                      </button>
                      <AgeStatusIcon
                        :status="row.collection_status"
                        :status-changed-by="row.status_changed_by"
                        :transfer-active="row.transfer_active"
                        :debit-order="row.debit_order"
                      />
                    </div>
                  </td>
                  <td class="border border-border px-3 py-2 text-right font-semibold tabular-nums text-navy-dark">{{ fmt(row.balance) }}</td>
                  <td class="border border-border px-1 py-2 text-center">
                    <button type="button" class="text-[#2f6fb0] hover:text-navy" title="Download Statement" @click="downloadRow(row)">
                      <svg class="mx-auto h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" /></svg>
                    </button>
                  </td>
                  <td class="border border-border px-3 py-2 text-center">
                    <input type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" :checked="selected.has(row.unit_id)" @change="toggleRow(row.unit_id)" />
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="!loading && rows.length">
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5"></td>
                <td class="border border-border px-3 py-2.5">Totals</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmt(totals.balance) }}</td>
                <td class="border border-border px-1 py-2.5"></td>
                <td class="border border-border px-3 py-2 text-center">
                  <button type="button"
                          class="inline-flex items-center gap-1.5 rounded-md bg-navy px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-navy-dark disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="selectedCount === 0 || mailing"
                          @click="mailSelected">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" /></svg>
                    {{ mailing ? 'Mailing…' : 'E-Mail Statements' }}
                  </button>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
