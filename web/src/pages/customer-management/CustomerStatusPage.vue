<template>
  <div class="p-6">
    <!-- No community -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to manage customer statuses.
      <div class="mt-4"><AppButton variant="secondary" @click="$router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <h1 class="font-body text-2xl font-bold text-foreground">Apply Statuses for {{ reportDate }}</h1>
      <div class="mt-1 flex flex-col gap-0.5 text-sm">
        <button type="button" class="text-left text-[#2f6fb0] hover:underline" @click="$router.push({ name: 'status-batches' })">View status history</button>
        <button type="button" class="text-left text-[#2f6fb0] hover:underline" @click="$router.push({ name: 'automatic-status-changes' })">View automatic status changes</button>
      </div>

      <!-- Apply controls -->
      <div class="mt-4 flex flex-wrap items-start gap-4">
        <div class="w-56">
          <AppSelect v-model="applyStatus" :options="statusOptions" placeholder="Select Status" />
        </div>

        <!-- Handed Over → attorney selector (before the note, WeConnectU order) -->
        <div v-if="isHandedOver" class="w-56">
          <AppSelect v-model="selectedAttorney" :options="attorneyOptions" placeholder="Select Attorney" />
        </div>

        <textarea
          v-model="applyNote"
          placeholder="Enter note"
          rows="3"
          class="min-h-[76px] w-72 rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
        ></textarea>

        <!-- Letter of Demand → Send Email / Send SMS -->
        <template v-if="isLetterOfDemand">
          <label class="flex cursor-pointer items-center gap-2 pt-2 select-none">
            <input v-model="sendEmail" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" /> Send Email
          </label>
          <label class="flex cursor-pointer items-center gap-2 pt-2 select-none">
            <input v-model="sendSms" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" /> Send SMS
          </label>
        </template>

        <!-- Handed Over → Apply Charge / notify -->
        <template v-if="isHandedOver">
          <label class="flex cursor-pointer items-center gap-2 pt-2 select-none">
            <input v-model="applyCharge" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" /> Apply Charge
          </label>
          <label class="flex cursor-pointer items-center gap-2 pt-2 select-none">
            <input v-model="notifyAttorney" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" /> Send Email to Owner and Attorney
          </label>
        </template>
      </div>

      <!-- Filter Options card -->
      <div class="mt-4 rounded-lg border border-border bg-white p-4">
        <p class="mb-2 text-sm font-medium text-muted-foreground">Filter Options</p>
        <div class="flex flex-nowrap items-center gap-2">
          <!-- Date (ISO display + native calendar overlay) -->
          <AppTooltip text="Click to change Ageing Date">
            <div class="relative w-40 shrink-0">
              <div class="flex h-9 items-center gap-1.5 rounded-md border border-border px-2.5 text-sm text-foreground">
                <span class="whitespace-nowrap">{{ filters.status_date }}</span>
                <svg class="ml-auto h-4 w-4 shrink-0 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
              </div>
              <input v-model="filters.status_date" type="date" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" @change="fetchRows" />
            </div>
          </AppTooltip>
          <div class="w-40 shrink-0"><AppSelect v-model="filters.status" size="sm" :options="statusFilterOptions" placeholder="Collection Status" @update:modelValue="fetchRows" /></div>
          <div class="w-40 shrink-0"><AppSelect v-model="filters.interest_status" size="sm" :options="interestOptions" placeholder="Interest Status" @update:modelValue="fetchRows" /></div>
          <div class="w-36 shrink-0"><AppSelect v-model="filters.debt_status" size="sm" :options="[]" placeholder="Debt Status" /></div>
          <div class="w-40 shrink-0"><AppSelect v-model="filters.customer_group" size="sm" :options="groupOptions" placeholder="Customer Group" @update:modelValue="fetchRows" /></div>

          <div class="ml-auto flex items-center gap-2">
            <input
              v-model="filters._search"
              type="text"
              placeholder="Search..."
              class="h-9 w-44 rounded-md border border-border px-3 text-sm focus:border-navy focus:outline-none"
              @keyup.enter="fetchRows"
            />
            <AppButton variant="secondary" size="sm" @click="fetchRows">
              <span class="inline-flex items-center gap-1.5"><IconSearch class="h-4 w-4" /> Search</span>
            </AppButton>
            <button
              type="button"
              class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md bg-[#3B93D6] px-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
              :disabled="downloading"
              @click="download"
            >
              <IconDownload class="h-4 w-4" /> {{ downloading ? 'Downloading…' : 'Download' }}
            </button>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-6 text-sm">
          <label class="inline-flex items-center gap-2"><input v-model="filters.hide_zeros" type="checkbox" class="h-4 w-4" @change="fetchRows" /> Hide Zeros</label>
          <label class="inline-flex items-center gap-2"><input v-model="filters.hide_credits" type="checkbox" class="h-4 w-4" @change="fetchRows" /> Hide Credits</label>
          <label class="inline-flex items-center gap-2"><input v-model="filters.exclude_debt_arrear" type="checkbox" class="h-4 w-4" @change="fetchRows" /> Exclude Debt/Arrear charges</label>
        </div>
      </div>

      <!-- Grid (unless a customer ledger is open) -->
      <template v-if="!ledger">
      <!-- Table -->
      <div class="mt-4 overflow-x-auto rounded-lg border border-border bg-white">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="px-3 py-3 text-left">
                <div class="flex items-center gap-2">
                  <input type="checkbox" class="h-4 w-4" :checked="allSelected" @change="toggleAll($event)" />
                  <IconFlag class="h-4 w-4 text-[#2f6fb0]" />
                </div>
              </th>
              <th class="px-3 py-3 text-left">
                <button class="inline-flex items-center gap-1 font-bold text-[#2f6fb0]" @click="sortBy('unit_number')">Unit No <SortChevrons :state="sortState('unit_number')" /></button>
              </th>
              <th class="px-3 py-3 text-left">
                <button class="inline-flex items-center gap-1 font-bold text-[#2f6fb0]" @click="sortBy('customer_name')">Customer <SortChevrons :state="sortState('customer_name')" /></button>
              </th>
              <th class="px-3 py-3"></th>
              <th v-for="col in bucketCols" :key="col.key" class="px-3 py-3 text-right">
                <button class="inline-flex items-center gap-1 font-bold text-[#2f6fb0]" @click="sortBy(col.key)">{{ col.label }} <SortChevrons :state="sortState(col.key)" /></button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td :colspan="9" class="px-3 py-8 text-center text-muted-foreground">Loading…</td></tr>
            <tr v-else-if="!displayRows.length"><td :colspan="9" class="px-3 py-8 text-center text-muted-foreground">No customers found.</td></tr>
            <tr v-for="row in displayRows" :key="row.unit_id" class="border-b border-border last:border-0 odd:bg-white even:bg-[#f7f9fb] hover:bg-muted/40">
              <td class="px-3 py-3 align-top"><input type="checkbox" class="h-4 w-4" :value="row.unit_id" v-model="selected" /></td>
              <td class="px-3 py-3 align-top text-foreground">{{ row.unit_number }}</td>
              <td class="px-3 py-3 align-top">
                <div class="flex items-start gap-1.5">
                  <AgeStatusIcon
                    class="mt-0.5"
                    :status="row.collection_status"
                    :status-changed-by="row.status_changed_by"
                    :transfer-active="row.transfer_active"
                    :debit-order="row.debit_order"
                  />
                  <div>
                    <button class="text-left font-medium text-[#2f6fb0] hover:underline" @click="openLedger(row)">{{ row.customer_code }} {{ row.customer_name }}</button>
                    <div class="text-xs text-muted-foreground">
                      <span v-if="row.customer_email">{{ row.customer_email }}</span><span v-if="row.customer_email && row.customer_phone">, </span><span v-if="row.customer_phone">{{ row.customer_phone }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 align-top">
                <div class="flex items-center gap-3">
                  <AppTooltip v-if="row.customer_phone" :text="row.customer_phone">
                    <button type="button" class="text-red-600 hover:text-red-700" @click="openPhonecall(row)"><IconPhone class="h-4 w-4" /></button>
                  </AppTooltip>
                  <AppTooltip text="Customer Notes">
                    <button type="button" :class="row.notes_count > 0 ? 'text-[#2f6fb0]' : 'text-muted-foreground/40'" @click="openNotes(row)"><IconNote class="h-4 w-4" /></button>
                  </AppTooltip>
                </div>
              </td>
              <td v-for="col in bucketCols" :key="col.key" class="px-3 py-3 text-right align-top text-muted-foreground">{{ money(row[col.key]) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Apply -->
      <div class="mt-4">
        <AppButton variant="primary" :loading="applying" :disabled="!selected.length" @click="apply">Apply Statuses</AppButton>
      </div>
      </template>

      <!-- Customer ledger (drill-in) -->
      <div v-else class="mt-4 rounded-lg border border-border bg-white p-5">
        <div class="mb-4 flex items-start justify-between gap-3">
          <h2 class="text-xl font-semibold text-foreground">{{ ledger.customer.customer_code }}: {{ ledger.customer.customer_name }}</h2>
          <div class="flex items-center gap-2">
            <AppButton variant="secondary" :loading="emailingStatement" @click="emailStatement">
              <span class="inline-flex items-center gap-2"><IconMail class="h-4 w-4" /> E-Mail Statement</span>
            </AppButton>
            <AppButton variant="outline" square title="Download statement (PDF)" @click="downloadLedgerStatement"><IconKebab class="h-4 w-4" /></AppButton>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-y border-border text-left">
                <th class="px-3 py-2 font-bold text-navy-dark">Date</th>
                <th class="px-3 py-2 font-bold text-navy-dark">Source</th>
                <th class="px-3 py-2 font-bold text-navy-dark">Description</th>
                <th class="px-3 py-2 font-bold text-navy-dark">Remarks</th>
                <th class="px-3 py-2 text-right font-bold text-navy-dark">Debit</th>
                <th class="px-3 py-2 text-right font-bold text-navy-dark">Credit</th>
                <th class="px-3 py-2 text-right font-bold text-navy-dark">Cumulative</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in ledger.rows" :key="i" class="border-b border-border">
                <td class="px-3 py-2 align-top">{{ r.date }}</td>
                <td class="px-3 py-2 align-top">{{ r.source }}</td>
                <td class="px-3 py-2 align-top">
                  <a v-if="r.invoice_id" :href="`/billing/invoices/${r.invoice_id}`" class="text-[#2f6fb0] hover:underline">{{ r.description }}</a>
                  <span v-else>{{ r.description }}</span>
                </td>
                <td class="px-3 py-2 align-top">{{ r.remarks }}</td>
                <td class="px-3 py-2 text-right align-top">{{ num(r.debit) }}</td>
                <td class="px-3 py-2 text-right align-top">{{ num(r.credit) }}</td>
                <td class="px-3 py-2 text-right align-top">{{ numAlways(r.cumulative) }}</td>
              </tr>
              <tr class="border-t-2 border-border font-semibold">
                <td class="px-3 py-2" colspan="4"></td>
                <td class="px-3 py-2 text-right">{{ numAlways(ledger.totals.debit) }}</td>
                <td class="px-3 py-2 text-right">{{ numAlways(ledger.totals.credit) }}</td>
                <td class="px-3 py-2 text-right">{{ numAlways(ledger.totals.balance) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-5">
          <AppButton variant="secondary" @click="closeLedger">&laquo; Back</AppButton>
        </div>
      </div>

      <!-- Customer Phonecall modal -->
      <AppModal :show="phoneModal" size="md" @close="phoneModal = false">
        <template #header><h3 class="w-full text-center text-base font-bold text-foreground">Customer Phonecall</h3></template>
        <div v-if="phoneRow">
          <label class="mb-1 block text-sm font-medium text-foreground">Notes:</label>
          <textarea
            v-model="callNote"
            rows="4"
            class="w-full rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
          ></textarea>
          <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm select-none">
            <input v-model="callCharge" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" />
            Add charge to call:
          </label>
        </div>
        <template #footer>
          <AppButton variant="primary" :loading="callingSaving" @click="confirmCall">Confirm Call</AppButton>
        </template>
      </AppModal>

      <!-- Notes modal (shared component) -->
      <CustomerNotesModal :show="notesModal" :community-id="communityId" :unit="notesRow" @close="notesModal = false" @changed="fetchRows" />
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppTooltip from '@/components/common/AppTooltip.vue'
import AgeStatusIcon from '@/components/age-analysis/AgeStatusIcon.vue'
import CustomerNotesModal from '@/components/customer-management/CustomerNotesModal.vue'

const { success, error: toastError } = useToast()
const communityStore = useCommunityStore()
const communityId = computed(() => communityStore.selectedId)

const stroke = (paths, box = '0 0 24 24') => (props, { attrs }) => h('svg', { viewBox: box, fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, paths.map(d => h('path', { d })))
const IconSearch = (p, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('circle', { cx: 11, cy: 11, r: 7 }), h('path', { d: 'm21 21-4.3-4.3' })])
const IconDownload = stroke(['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3'])
const IconFlag = stroke(['M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z', 'M4 22v-7'])
const IconPhone = (p, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', ...attrs }, [h('path', { d: 'M6.62 10.79a15.5 15.5 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.05-.24 11.4 11.4 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.57 1 1 0 0 1-.24 1.05z' })])
const IconNote = stroke(['M14 3v4a1 1 0 0 0 1 1h4', 'M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z'])
const IconHistory = (p, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('path', { d: 'M3 3v5h5' }), h('path', { d: 'M3.05 13A9 9 0 1 0 6 5.3L3 8' }), h('path', { d: 'M12 7v5l4 2' })])
const IconMail = stroke(['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'm22 7-10 6L2 7'])
const IconKebab = (p, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', ...attrs }, [h('circle', { cx: 12, cy: 5, r: 1.7 }), h('circle', { cx: 12, cy: 12, r: 1.7 }), h('circle', { cx: 12, cy: 19, r: 1.7 })])
const SortChevrons = (props) => h('span', { class: 'inline-flex flex-col text-[8px] leading-[7px]' }, [
  h('span', { class: props.state === 'asc' ? 'text-[#2f6fb0]' : 'text-muted-foreground/40' }, '▲'),
  h('span', { class: props.state === 'desc' ? 'text-[#2f6fb0]' : 'text-muted-foreground/40' }, '▼'),
])
SortChevrons.props = { state: String }

const bucketCols = [
  { key: '120_plus', label: '120 Days' },
  { key: '90_days', label: '90 Days' },
  { key: '60_days', label: '60 Days' },
  { key: '30_days', label: '30 Days' },
  { key: 'current', label: 'Current' },
  { key: 'balance', label: 'Balance' },
]

// WeConnectU defaults the ageing/status date to the LAST day of the current month.
const today = (() => {
  const d    = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  const mm   = String(last.getMonth() + 1).padStart(2, '0')
  const dd   = String(last.getDate()).padStart(2, '0')
  return `${last.getFullYear()}-${mm}-${dd}`
})()
const rows = ref([])
const loading = ref(false)
const downloading = ref(false)
const applying = ref(false)
const reportDate = ref(today)
const selected = ref([])

const applyStatus = ref('')
const applyNote = ref('')

// Contextual controls that appear per selected status (WeConnectU).
const sendEmail        = ref(false)
const sendSms          = ref(false)
const applyCharge      = ref(true)
const notifyAttorney   = ref(false)
const selectedAttorney = ref('')
const attorneyOptions  = ref([])

const isLetterOfDemand = computed(() => applyStatus.value === 'collection:letter_of_demand')
const isHandedOver     = computed(() => applyStatus.value === 'collection:handed_over')

const statusOptions = ref([])       // for the apply "Select Status"
const statusFilterOptions = ref([]) // for the filter
const interestOptions = ref([])
const groupOptions = ref([])

const filters = reactive({
  status_date: today,
  status: '',
  interest_status: '',
  debt_status: '',
  customer_group: '',
  _search: '',
  hide_zeros: false,
  hide_credits: false,
  exclude_debt_arrear: false,
})

const sortKey = ref('unit_number')
const sortDir = ref('asc')
function sortState(k) { return sortKey.value === k ? sortDir.value : null }
function sortBy(k) {
  if (sortKey.value === k) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = k; sortDir.value = 'asc' }
}
const displayRows = computed(() => {
  const list = [...rows.value]
  const k = sortKey.value, dir = sortDir.value === 'asc' ? 1 : -1
  const numeric = k !== 'unit_number' && k !== 'customer_name'
  list.sort((a, b) => {
    let av = a[k], bv = b[k]
    if (numeric) return (Number(av) - Number(bv)) * dir
    if (k === 'unit_number') { av = parseInt(String(av).replace(/\D/g, '')) || 0; bv = parseInt(String(bv).replace(/\D/g, '')) || 0; return (av - bv) * dir }
    return String(av).localeCompare(String(bv)) * dir
  })
  return list
})

const allSelected = computed(() => rows.value.length > 0 && selected.value.length === rows.value.length)
function toggleAll(e) { selected.value = e.target.checked ? rows.value.map(r => r.unit_id) : [] }

function money(v) {
  const n = Number(v || 0)
  const [int, dec] = n.toFixed(2).replace('-', '').split('.')
  return 'R ' + (n < 0 ? '-' : '') + int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + dec
}

async function fetchRows() {
  if (!communityId.value) return
  loading.value = true
  selected.value = []
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-statuses`, { params: { ...filters } })
    rows.value = data.data ?? []
    reportDate.value = data.report_date ?? today
    statusOptions.value = data.apply_status_options ?? []
    statusFilterOptions.value = data.status_options ?? []
    interestOptions.value = data.interest_options ?? []
    groupOptions.value = data.customer_groups ?? []
    attorneyOptions.value = data.attorney_options ?? []
  } catch (e) {
    toastError('Could not load customer statuses.')
  } finally {
    loading.value = false
  }
}

async function apply() {
  if (!selected.value.length) return
  applying.value = true
  try {
    const { data } = await api.post(`/communities/${communityId.value}/customer-statuses/apply`, {
      unit_ids: selected.value,
      status: applyStatus.value || null,
      note: applyNote.value || null,
      status_date: filters.status_date,
      send_email: isLetterOfDemand.value ? sendEmail.value : undefined,
      send_sms: isLetterOfDemand.value ? sendSms.value : undefined,
      apply_charge: isHandedOver.value ? applyCharge.value : undefined,
      notify_attorney: isHandedOver.value ? notifyAttorney.value : undefined,
      attorney: isHandedOver.value ? (selectedAttorney.value || null) : undefined,
    })
    success(data.message || 'Statuses applied')
    applyNote.value = ''
    applyStatus.value = ''
    sendEmail.value = false
    sendSms.value = false
    applyCharge.value = true
    notifyAttorney.value = false
    selectedAttorney.value = ''
    fetchRows()
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not apply statuses.')
  } finally {
    applying.value = false
  }
}

async function download() {
  downloading.value = true
  try {
    const res = await api.get(`/communities/${communityId.value}/customer-statuses/download`, { params: { ...filters }, responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: `customer statuses-${reportDate.value}.xlsx` })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    toastError('Could not download the report.')
  } finally {
    downloading.value = false
  }
}

// Customer Phonecall
const phoneModal = ref(false)
const phoneRow = ref(null)
const callNote = ref('')
const callCharge = ref(false)
const callingSaving = ref(false)
function openPhonecall(row) {
  phoneRow.value = row
  callNote.value = ''
  callCharge.value = false
  phoneModal.value = true
}
async function confirmCall() {
  if (!phoneRow.value) return
  callingSaving.value = true
  try {
    const { data } = await api.post(`/communities/${communityId.value}/units/${phoneRow.value.unit_id}/notes/phonecall`, {
      note: callNote.value || null,
      add_charge: callCharge.value,
    })
    success(data.message || 'Phone call logged.')
    phoneModal.value = false
    callNote.value = ''
    callCharge.value = false
    fetchRows()
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not log the phone call.')
  } finally {
    callingSaving.value = false
  }
}

// Notes (handled by the shared CustomerNotesModal component)
const notesModal = ref(false)
const notesRow = ref(null)

function openNotes(row) {
  notesRow.value = row
  notesModal.value = true
}

// Customer ledger (drill-in)
const ledger = ref(null)
const emailingStatement = ref(false)
function fmt(v) {
  const n = Number(v || 0)
  const [i, d] = n.toFixed(2).replace('-', '').split('.')
  return (n < 0 ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}
function num(v) { return Math.abs(Number(v || 0)) < 0.005 ? '' : fmt(v) }
function numAlways(v) { return fmt(v) }

async function openLedger(row) {
  try {
    const { data } = await api.get(`/communities/${communityId.value}/units/${row.unit_id}/customer-ledger`)
    ledger.value = data
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    toastError('Could not load the customer ledger.')
  }
}
function closeLedger() { ledger.value = null }
async function emailStatement() {
  if (!ledger.value) return
  emailingStatement.value = true
  try {
    const { data } = await api.post(`/communities/${communityId.value}/units/${ledger.value.customer.unit_id}/statement/email`)
    success(data.message || 'Statement e-mailed')
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not e-mail the statement.')
  } finally {
    emailingStatement.value = false
  }
}
async function downloadLedgerStatement() {
  if (!ledger.value) return
  try {
    const res = await api.get(`/communities/${communityId.value}/units/${ledger.value.customer.unit_id}/statement`, { params: { _format: 'pdf' }, responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: `CustomerStatement-${ledger.value.customer.customer_code}.pdf` })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    toastError('Could not download the statement.')
  }
}

watch(communityId, fetchRows)
onMounted(fetchRows)
</script>
