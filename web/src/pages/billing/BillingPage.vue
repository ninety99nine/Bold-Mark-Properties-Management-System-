<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useExport } from '@/composables/useExport.js'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import AppExportModal from '@/components/common/AppExportModal.vue'

const router = useRouter()
const route  = useRoute()

// ─── Remote state ─────────────────────────────────────────────────────────
const invoices   = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })
const summary    = ref({
  total: 0, total_amount: 0,
  paid_count: 0, overdue_count: 0, partially_paid_count: 0, unpaid_count: 0,
  revenue_by_charge_type: [],
})
const chargeTypes = ref([])
const estates     = ref([])

const loading        = ref(false)
const summaryLoading = ref(false)

// ─── Toolbar state (replaces raw search/status/type filters) ──────────────
const toolbarState = ref({
  search: '', dateRange: 'all_time', customStart: '', customEnd: '', filters: {}, sort: null,
})
const currentPage = ref(1)
const PER_PAGE    = 15

let searchDebounceTimer = null

function onToolbarUpdate(state) {
  const prevSearch   = toolbarState.value.search
  toolbarState.value = state
  currentPage.value  = 1

  clearTimeout(searchDebounceTimer)
  if (state.search !== prevSearch) {
    loading.value = true
    searchDebounceTimer = setTimeout(fetchInvoices, 350)
  } else {
    fetchInvoices()
  }
}

// ─── AppTableToolbar config ───────────────────────────────────────────────
const billingFilterFields = computed(() => [
  {
    key: 'status',
    label: 'Status',
    options: [
      { value: 'paid',           label: 'Paid'     },
      { value: 'overdue',        label: 'Overdue'  },
      { value: 'partially_paid', label: 'Partial'  },
      { value: 'unpaid',         label: 'Unpaid'   },
    ],
  },
  {
    key: 'charge_type_id',
    label: 'Charge Type',
    options: chargeTypes.value.map(ct => ({ value: ct.id, label: ct.name })),
  },
  {
    key: 'billed_to_type',
    label: 'Recipient Type',
    options: [
      { value: 'owner',  label: 'Owner'  },
      { value: 'tenant', label: 'Tenant' },
    ],
  },
])

const BILLING_SORT_OPTIONS = [
  { value: 'date_desc',   label: 'Newest first'         },
  { value: 'date_asc',    label: 'Oldest first'         },
  { value: 'amount_desc', label: 'Amount (High → Low)'  },
  { value: 'amount_asc',  label: 'Amount (Low → High)'  },
]

const SORT_API_MAP = {
  date_desc:   'created_at:desc',
  date_asc:    'created_at:asc',
  amount_desc: 'amount:desc',
  amount_asc:  'amount:asc',
}

function buildApiParams() {
  const state  = toolbarState.value
  const params = { _per_page: PER_PAGE, page: currentPage.value }

  if (state.search?.trim()) params._search = state.search.trim()

  if (state.dateRange && state.dateRange !== 'all_time') {
    params._date_range = state.dateRange
    if (state.dateRange === 'custom') {
      if (state.customStart) params._date_range_start = state.customStart
      if (state.customEnd)   params._date_range_end   = state.customEnd
    }
  }

  if (state.filters?.status)          params.status          = state.filters.status
  if (state.filters?.charge_type_id)  params.charge_type_id  = state.filters.charge_type_id
  if (state.filters?.billed_to_type)  params.billed_to_type  = state.filters.billed_to_type
  if (state.sort)                      params._sort           = SORT_API_MAP[state.sort] ?? state.sort

  return params
}

// ─── Filter option lists (for modals) ─────────────────────────────────────
const estateOpts = computed(() => [
  { value: '', label: 'Select estate...' },
  ...estates.value.map(e => ({ value: e.id, label: e.name })),
])

// Past 12 months as billing period options
const periodOpts = computed(() => {
  const opts = []
  const now  = new Date()
  for (let i = 0; i < 12; i++) {
    const d     = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const label = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
    opts.push({ value, label })
  }
  return opts
})

// Non-recurring charge types for ad-hoc billing
const adHocChargeTypeOpts = computed(() => [
  { value: '', label: 'Select charge type...' },
  ...chargeTypes.value
    .filter(ct => !ct.is_recurring)
    .map(ct => ({ value: ct.id, label: ct.name })),
])

// ─── API fetches ──────────────────────────────────────────────────────────
async function fetchInvoices() {
  loading.value = true
  try {
    const { data } = await api.get('/invoices', { params: buildApiParams() })
    invoices.value = data.data
    pagination.value = {
      current_page: data.meta.current_page,
      last_page:    data.meta.last_page,
      total:        data.meta.total,
      per_page:     data.meta.per_page,
    }
  } catch (e) {
    console.error('Failed to fetch invoices', e)
  } finally {
    loading.value = false
  }
}

async function fetchSummary() {
  summaryLoading.value = true
  try {
    const { data } = await api.get('/invoices/summary')
    summary.value = data
  } catch (e) {
    console.error('Failed to fetch invoice summary', e)
  } finally {
    summaryLoading.value = false
  }
}

async function fetchChargeTypes() {
  try {
    const { data } = await api.get('/charge-types', { params: { _per_page: 100 } })
    chargeTypes.value = data.data
  } catch (e) {
    console.error('Failed to fetch charge types', e)
  }
}

async function fetchEstates() {
  try {
    const { data } = await api.get('/estates', { params: { _per_page: 100 } })
    estates.value = data.data
  } catch (e) {
    console.error('Failed to fetch estates', e)
  }
}

onMounted(() => {
  fetchInvoices()
  fetchSummary()
  fetchChargeTypes()
  fetchEstates()
  if (route.query.tab === 'trash') {
    switchView('trash')
  }
})

// ─── Pagination ───────────────────────────────────────────────────────────
function goPage(p) {
  const clamped = Math.min(Math.max(1, p), pagination.value.last_page)
  if (clamped !== currentPage.value) {
    currentPage.value = clamped
    fetchInvoices()
  }
}

const pageNumbers = computed(() => {
  const last = pagination.value.last_page
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const cur = pagination.value.current_page
  const pages = new Set([1, 2, last - 1, last, cur - 1, cur, cur + 1].filter(p => p >= 1 && p <= last))
  const sorted = [...pages].sort((a, b) => a - b)
  const result = []
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push(null)
    result.push(sorted[i])
  }
  return result
})

// ─── Summary stat cards ───────────────────────────────────────────────────
const statCards = computed(() => {
  const s = summary.value
  return [
    { label: 'Total Invoices', value: s.total || 0,                type: 'count' },
    { label: 'Paid',           value: s.paid_count || 0,           type: 'count', accent: 'success' },
    { label: 'Overdue',        value: s.overdue_count || 0,        type: 'count', accent: 'danger'  },
    { label: 'Total Billed',   value: s.total_amount || 0,         type: 'amount' },
  ]
})

// ─── Helpers ──────────────────────────────────────────────────────────────
const BADGE_VARIANT = {
  paid: 'success', overdue: 'danger', partially_paid: 'warning', unpaid: 'default',
}
const BADGE_LABEL = {
  paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', unpaid: 'Unpaid',
}

function fmt(n) {
  const num = Math.round(Number(n) * 100) / 100
  return 'R\u00a0' + num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f')
}

function fmtStat(card) {
  if (card.type === 'amount') return fmt(card.value)
  return card.value.toLocaleString()
}

function fmtPeriod(dateStr) {
  if (!dateStr) return '—'
  const [year, month] = dateStr.split('-')
  const d = new Date(parseInt(year), parseInt(month) - 1, 1)
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
}

function billedToName(inv) {
  if (inv.billed_to_type === 'owner')  return inv.billed_to_owner?.full_name       || '—'
  if (inv.billed_to_type === 'tenant') return inv.billed_to_unit_tenant?.full_name  || '—'
  return '—'
}

function goToInvoice(inv) {
  router.push(`/billing/invoices/${inv.id}`)
}

function emailDeliveryStatus(inv) {
  const events = inv.email_events ?? []
  if (events.some(e => e.event_type === 'opened'))    return 'opened'
  if (events.some(e => e.event_type === 'delivered')) return 'delivered'
  if (events.some(e => e.event_type === 'sent'))      return 'sent'
  return 'none'
}

function emailDeliveryTooltip(inv) {
  return {
    none:      'Not yet sent',
    sent:      'Email sent — awaiting delivery confirmation',
    delivered: 'Email delivered to inbox',
    opened:    'Email opened by recipient',
  }[emailDeliveryStatus(inv)]
}

function statValueClass(card) {
  if (card.accent === 'danger')  return 'text-destructive'
  if (card.accent === 'success') return 'text-success'
  return 'text-foreground'
}

// ─── Modals ───────────────────────────────────────────────────────────────
const showRun    = ref(false)
const showAdHoc  = ref(false)
const showExport = ref(false)

// ── Run Billing ────────────────────────────────────────────────────────────
const runEstate        = ref('')
const runPeriod        = ref('')
const runPreview       = ref([])
const runLoading       = ref(false)
const runConfirming    = ref(false)
const runError         = ref('')

watch([runEstate, runPeriod], fetchRunPreview)

async function fetchRunPreview() {
  if (!runEstate.value || !runPeriod.value) { runPreview.value = []; return }
  runLoading.value = true
  runError.value   = ''
  try {
    const { data } = await api.post('/invoices/run-billing', {
      estate_id:      runEstate.value,
      billing_period: runPeriod.value,
      dry_run:        true,
    })
    runPreview.value = data.preview || []
  } catch (e) {
    runError.value   = e.response?.data?.message || 'Failed to load billing preview'
    runPreview.value = []
  } finally {
    runLoading.value = false
  }
}

async function confirmRunBilling() {
  if (!runEstate.value || !runPeriod.value) return
  runConfirming.value = true
  runError.value      = ''
  try {
    await api.post('/invoices/run-billing', {
      estate_id:      runEstate.value,
      billing_period: runPeriod.value,
      dry_run:        false,
    })
    closeRun()
    fetchInvoices()
    fetchSummary()
  } catch (e) {
    runError.value = e.response?.data?.message || 'Failed to run billing'
  } finally {
    runConfirming.value = false
  }
}

function closeRun() {
  showRun.value    = false
  runEstate.value  = ''
  runPeriod.value  = ''
  runPreview.value = []
  runError.value   = ''
}

const nonDuplicatePreview = computed(() => runPreview.value.filter(r => !r.duplicate))
const duplicateCount      = computed(() => runPreview.value.filter(r => r.duplicate).length)

// ── Ad-Hoc Billing ────────────────────────────────────────────────────────
const adHocEstate     = ref('')
const adHocChargeType = ref('')
const adHocAmount     = ref('')
const adHocLoading    = ref(false)
const adHocError      = ref('')
const adHocValid      = computed(() => adHocEstate.value && adHocChargeType.value && Number(adHocAmount.value) > 0)

async function generateAdHoc() {
  if (!adHocValid.value) return
  adHocLoading.value = true
  adHocError.value   = ''
  try {
    await api.post('/invoices/adhoc-billing', {
      estate_id:      adHocEstate.value,
      charge_type_id: adHocChargeType.value,
      amount:         parseFloat(adHocAmount.value),
      billing_period: periodOpts.value[0]?.value,
    })
    closeAdHoc()
    fetchInvoices()
    fetchSummary()
  } catch (e) {
    adHocError.value = e.response?.data?.message || 'Failed to generate ad-hoc billing'
  } finally {
    adHocLoading.value = false
  }
}

function closeAdHoc() {
  showAdHoc.value        = false
  adHocEstate.value      = ''
  adHocChargeType.value  = ''
  adHocAmount.value      = ''
  adHocError.value       = ''
}

// ─── Export ───────────────────────────────────────────────────────────────
const { downloadExport } = useExport()

function buildExportParams(format, records) {
  const state  = toolbarState.value
  const params = {}

  if (state.search?.trim()) params._search = state.search.trim()

  if (state.dateRange && state.dateRange !== 'all_time') {
    params._date_range = state.dateRange
    if (state.dateRange === 'custom') {
      if (state.customStart) params._date_range_start = state.customStart
      if (state.customEnd)   params._date_range_end   = state.customEnd
    }
  }

  if (state.filters?.status)          params.status          = state.filters.status
  if (state.filters?.charge_type_id)  params.charge_type_id  = state.filters.charge_type_id
  if (state.filters?.billed_to_type)  params.billed_to_type  = state.filters.billed_to_type
  if (state.sort)                      params._sort           = SORT_API_MAP[state.sort] ?? state.sort

  params._format = format
  params._limit  = records

  return params
}

async function handleExportDownload({ format, records }) {
  const ext      = format === 'xlsx' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
  const filename = `invoices-${new Date().toISOString().slice(0, 10)}.${ext}`
  await downloadExport('/invoices/export', buildExportParams(format, records), filename)
}

// ─── Trash (deleted invoices) ─────────────────────────────────────────────
const activeView        = ref('active')  // 'active' | 'trash'
const deletedInvoices   = ref([])
const deletedPagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })
const deletedLoading    = ref(false)
const trashMenuOpenId   = ref(null)
const deletedPage       = ref(1)
const deletedSearch     = ref('')
let deletedSearchTimer  = null

const THREE_YEARS_MS = 3 * 365.25 * 24 * 60 * 60 * 1000

function permanentDeleteDate(deletedAt) {
  if (!deletedAt) return '—'
  const d = new Date(new Date(deletedAt).getTime() + THREE_YEARS_MS)
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDeletedDate(deletedAt) {
  if (!deletedAt) return '—'
  const d = new Date(deletedAt)
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function fetchDeletedInvoices() {
  deletedLoading.value = true
  try {
    const params = { _per_page: 15, page: deletedPage.value }
    if (deletedSearch.value.trim()) params.search = deletedSearch.value.trim()
    const { data } = await api.get('/invoices/deleted', { params })
    deletedInvoices.value   = data.data
    deletedPagination.value = {
      current_page: data.meta.current_page,
      last_page:    data.meta.last_page,
      total:        data.meta.total,
      per_page:     data.meta.per_page,
    }
  } catch (e) {
    console.error('Failed to fetch deleted invoices', e)
  } finally {
    deletedLoading.value = false
  }
}

function switchView(view) {
  activeView.value = view
  if (view === 'trash' && deletedInvoices.value.length === 0) {
    fetchDeletedInvoices()
  }
}

function onDeletedSearch(val) {
  deletedSearch.value = val
  deletedPage.value   = 1
  clearTimeout(deletedSearchTimer)
  deletedSearchTimer = setTimeout(fetchDeletedInvoices, 350)
}

function goDeletedPage(p) {
  const clamped = Math.min(Math.max(1, p), deletedPagination.value.last_page)
  if (clamped !== deletedPage.value) {
    deletedPage.value = clamped
    fetchDeletedInvoices()
  }
}

// Restore & force-delete state
const restoringId      = ref(null)
const forceDeleting    = ref(null)
const forceDeleteTarget = ref(null)
const showForceDeleteConfirm = ref(false)

async function restoreInvoice(inv) {
  restoringId.value = inv.id
  try {
    await api.post(`/invoices/${inv.id}/restore`)
    await fetchDeletedInvoices()
    fetchSummary()
  } catch (e) {
    console.error('Restore failed', e)
  } finally {
    restoringId.value = null
  }
}

function openForceDelete(inv) {
  forceDeleteTarget.value     = inv
  showForceDeleteConfirm.value = true
}

async function confirmForceDelete() {
  if (!forceDeleteTarget.value) return
  forceDeleting.value = forceDeleteTarget.value.id
  try {
    await api.delete(`/invoices/${forceDeleteTarget.value.id}/force-delete`)
    showForceDeleteConfirm.value = false
    forceDeleteTarget.value      = null
    await fetchDeletedInvoices()
  } catch (e) {
    console.error('Force delete failed', e)
  } finally {
    forceDeleting.value = null
  }
}

const deletedPageNumbers = computed(() => {
  const last = deletedPagination.value.last_page
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const cur = deletedPagination.value.current_page
  const pages = new Set([1, 2, last - 1, last, cur - 1, cur, cur + 1].filter(p => p >= 1 && p <= last))
  const sorted = [...pages].sort((a, b) => a - b)
  const result = []
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push(null)
    result.push(sorted[i])
  }
  return result
})

// ─── Donut chart — Invoices by Status ────────────────────────────────────
const donutSlices = computed(() => {
  const s = summary.value
  const segments = [
    { key: 'paid',    label: 'Paid',    color: '#22c55e', count: s.paid_count            || 0 },
    { key: 'overdue', label: 'Overdue', color: '#dc2828', count: s.overdue_count         || 0 },
    { key: 'partial', label: 'Partial', color: '#D89B4B', count: s.partially_paid_count  || 0 },
    { key: 'unpaid',  label: 'Unpaid',  color: '#DCDEE8', count: s.unpaid_count          || 0 },
  ]
  const total = segments.reduce((acc, seg) => acc + seg.count, 0)
  if (total === 0) return []

  const CX = 120, CY = 120, RO = 82, RI = 52
  let start = -Math.PI / 2

  return segments.filter(seg => seg.count > 0).map(seg => {
    const frac  = seg.count / total
    const end   = start + frac * 2 * Math.PI
    const large = frac > 0.5 ? 1 : 0
    const c0    = { x: Math.cos(start), y: Math.sin(start) }
    const c1    = { x: Math.cos(end),   y: Math.sin(end)   }
    const path  = [
      `M ${(CX + RO * c0.x).toFixed(1)} ${(CY + RO * c0.y).toFixed(1)}`,
      `A ${RO} ${RO} 0 ${large} 1 ${(CX + RO * c1.x).toFixed(1)} ${(CY + RO * c1.y).toFixed(1)}`,
      `L ${(CX + RI * c1.x).toFixed(1)} ${(CY + RI * c1.y).toFixed(1)}`,
      `A ${RI} ${RI} 0 ${large} 0 ${(CX + RI * c0.x).toFixed(1)} ${(CY + RI * c0.y).toFixed(1)}`,
      'Z',
    ].join(' ')
    start = end
    return { ...seg, path, pct: Math.round(frac * 100) }
  })
})

const paidPct = computed(() => donutLegend.value.find(s => s.key === 'paid')?.pct ?? 0)

const donutLegend = computed(() => {
  const s = summary.value
  const total = (s.paid_count || 0) + (s.overdue_count || 0) + (s.partially_paid_count || 0) + (s.unpaid_count || 0)
  if (total === 0) return []
  return [
    { key: 'paid',    label: 'Paid',    color: '#22c55e', pct: Math.round((s.paid_count           || 0) / total * 100) },
    { key: 'overdue', label: 'Overdue', color: '#dc2828', pct: Math.round((s.overdue_count        || 0) / total * 100) },
    { key: 'partial', label: 'Partial', color: '#D89B4B', pct: Math.round((s.partially_paid_count || 0) / total * 100) },
    { key: 'unpaid',  label: 'Unpaid',  color: '#DCDEE8', pct: Math.round((s.unpaid_count         || 0) / total * 100) },
  ]
})

// ─── Bar chart — Revenue by Charge Type ───────────────────────────────────
const barChart = computed(() => {
  const entries = (summary.value.revenue_by_charge_type || []).map(r => [r.name, r.total])
  if (entries.length === 0) return null

  const maxVal = Math.max(...entries.map(([, v]) => v), 1)
  const scale  = Math.ceil(maxVal / 6000) * 6000 || 6000
  const colors = ['#1F3A5C', '#717B99', '#2D4A70', '#4D6A90']

  const PY0 = 10, PY1 = 185, PH = PY1 - PY0
  const PX0 = 55, PX1 = 390
  const colW = (PX1 - PX0) / entries.length
  const barW = Math.min(90, Math.floor(colW * 0.55))

  const bars = entries.map(([type, amount], idx) => {
    const h  = Math.max(2, Math.round((amount / scale) * PH))
    const cx = PX0 + colW * idx + colW / 2
    const r  = 4
    const x  = cx - barW / 2
    const y  = PY1 - h
    const path = [
      `M ${x} ${y + r}`,
      `Q ${x} ${y} ${x + r} ${y}`,
      `L ${x + barW - r} ${y}`,
      `Q ${x + barW} ${y} ${x + barW} ${y + r}`,
      `L ${x + barW} ${PY1}`,
      `L ${x} ${PY1}`,
      'Z',
    ].join(' ')
    const shortType = type.length > 14 ? type.slice(0, 14) + '…' : type
    return { type, shortType, amount, path, cx, color: colors[idx % colors.length] }
  })

  const TICKS = 4
  const yTicks = Array.from({ length: TICKS + 1 }, (_, i) => {
    const val = (scale / TICKS) * i
    return {
      val,
      y:     PY1 - Math.round((val / scale) * PH),
      label: val >= 1000 ? `R ${val / 1000}k` : `R ${val}`,
    }
  })

  return { bars, yTicks, PX0, PX1, PY0, PY1 }
})

// ─── Tooltips ─────────────────────────────────────────────────────────────
const donutTooltip = ref(null)
const barTooltip   = ref(null)

function onSliceMove(event, slice) {
  const rect = event.currentTarget.closest('.chart-tip-anchor').getBoundingClientRect()
  donutTooltip.value = { x: event.clientX - rect.left, y: event.clientY - rect.top, slice }
}
function onBarMove(event, bar) {
  const rect = event.currentTarget.closest('.chart-tip-anchor').getBoundingClientRect()
  barTooltip.value = { x: event.clientX - rect.left, y: event.clientY - rect.top, bar }
}
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page Header ─────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Billing &amp; Invoicing</h1>
        <p class="text-sm text-muted-foreground">Generate and manage invoices</p>
      </div>
      <div class="flex gap-2">
        <AppButton variant="outline" @click="showAdHoc = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"/><path d="M12 5v14"/>
          </svg>
          Ad-Hoc Billing
        </AppButton>
        <AppButton variant="primary" @click="showRun = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <polygon points="6 3 20 12 6 21 6 3"/>
          </svg>
          Run Billing
        </AppButton>
      </div>
    </div>

    <!-- ── Summary Stat Cards ────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <template v-if="summaryLoading">
        <div v-for="n in 4" :key="n" class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <div class="h-7 w-16 bg-muted rounded animate-pulse mx-auto mb-1.5" />
            <div class="h-3 w-20 bg-muted rounded animate-pulse mx-auto" />
          </div>
        </div>
      </template>
      <template v-else>
        <div v-for="card in statCards" :key="card.label" class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p :class="['text-2xl font-bold font-body whitespace-nowrap', statValueClass(card)]">
              {{ fmtStat(card) }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5">{{ card.label }}</p>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Invoices Table Card ────────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card shadow-sm">

      <!-- Section header with Active / Trash tabs -->
      <div class="px-6 pt-5 pb-0 flex items-center justify-between">
        <!-- Tab toggle -->
        <div class="flex items-center gap-1 p-1 rounded-lg bg-muted/60 border border-border">
          <button
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
            :class="activeView === 'active'
              ? 'bg-background shadow-sm text-foreground border border-border'
              : 'text-muted-foreground hover:text-foreground'"
            @click="switchView('active')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
              <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
            </svg>
            Invoices
          </button>
          <button
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
            :class="activeView === 'trash'
              ? 'bg-background shadow-sm text-foreground border border-border'
              : 'text-muted-foreground hover:text-foreground'"
            @click="switchView('trash')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
            </svg>
            Trash
          </button>
        </div>

        <!-- Export (active view only) -->
        <button
          v-if="activeView === 'active'"
          @click="showExport = true"
          class="inline-flex items-center gap-1.5 h-8 px-3 rounded text-sm font-medium font-body text-muted-foreground hover:bg-muted hover:text-foreground transition-colors border border-border"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" x2="12" y1="15" y2="3"/>
          </svg>
          Export
        </button>
      </div>

      <!-- ── Active invoices view ── -->
      <template v-if="activeView === 'active'">

      <!-- Toolbar row -->
      <div class="px-6 pt-4 pb-4">
        <AppTableToolbar
          search-placeholder="Search invoices..."
          :filter-fields="billingFilterFields"
          :sort-options="BILLING_SORT_OPTIONS"
          storage-key="billing-invoices"
          date-range-context="Invoice Date"
          @update:state="onToolbarUpdate"
        />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice #</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Estate</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Unit</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Type</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Billed To</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Period</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
              <th class="text-center py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Sent</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading skeleton rows -->
            <template v-if="loading">
              <tr v-for="n in PER_PAGE" :key="'sk-' + n" class="border-b border-border">
                <td v-for="c in 9" :key="c" class="py-3 px-4">
                  <div class="h-4 bg-muted animate-pulse rounded w-full" />
                </td>
              </tr>
            </template>

            <!-- Data rows -->
            <template v-else>
              <tr
                v-for="inv in invoices"
                :key="inv.id"
                class="border-b border-border hover:bg-muted/50 cursor-pointer transition-colors"
                @click="goToInvoice(inv)"
              >
                <td class="py-3 px-4 font-medium text-foreground">{{ inv.invoice_number }}</td>
                <td class="py-3 px-4 text-muted-foreground">{{ inv.unit?.estate?.name || '—' }}</td>
                <td class="py-3 px-4 text-foreground">{{ inv.unit?.unit_number || '—' }}</td>
                <td class="py-3 px-4 text-foreground">{{ inv.charge_type?.name || '—' }}</td>
                <td class="py-3 px-4 text-foreground">{{ billedToName(inv) }}</td>
                <td class="py-3 px-4 text-muted-foreground">{{ fmtPeriod(inv.billing_period) }}</td>
                <td class="py-3 px-4 text-right font-medium text-foreground whitespace-nowrap">{{ fmt(inv.amount) }}</td>
                <!-- Email delivery tick indicator -->
                <td class="py-3 px-4 text-center" :title="emailDeliveryTooltip(inv)">
                  <span v-if="emailDeliveryStatus(inv) === 'none'" class="text-xs text-muted-foreground/40">—</span>
                  <span v-else-if="emailDeliveryStatus(inv) === 'sent'" class="inline-flex items-center">
                    <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-muted-foreground">
                      <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <span v-else-if="emailDeliveryStatus(inv) === 'delivered'" class="inline-flex items-center">
                    <svg width="15" height="8" viewBox="0 0 20 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-muted-foreground">
                      <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M5.5 5.5L9.5 9.5L18.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <span v-else-if="emailDeliveryStatus(inv) === 'opened'" class="inline-flex items-center">
                    <svg width="15" height="8" viewBox="0 0 20 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-blue-500">
                      <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M5.5 5.5L9.5 9.5L18.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <AppBadge :variant="BADGE_VARIANT[inv.status] || 'default'" bordered size="sm">
                    {{ BADGE_LABEL[inv.status] || inv.status }}
                  </AppBadge>
                </td>
              </tr>

              <tr v-if="invoices.length === 0">
                <td colspan="9" class="py-12 text-center text-sm text-muted-foreground">
                  No invoices found matching your filters.
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-border">
        <p class="text-xs text-muted-foreground">
          Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="goPage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors"
          >Previous</button>

          <template v-for="p in pageNumbers" :key="p ?? 'ellipsis'">
            <span v-if="p === null" class="px-1 text-xs text-muted-foreground">…</span>
            <button
              v-else
              @click="goPage(p)"
              :class="[
                'w-7 h-7 rounded text-xs font-medium border transition-colors',
                p === pagination.current_page
                  ? 'bg-primary border-primary text-white'
                  : 'border-input bg-card text-foreground hover:bg-muted',
              ]"
            >{{ p }}</button>
          </template>

          <button
            @click="goPage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors"
          >Next</button>
        </div>
      </div>

      </template>
      <!-- ── End active invoices view ── -->

      <!-- ── Trash view ── -->
      <template v-else-if="activeView === 'trash'">
        <!-- Info banner -->
        <div class="mx-6 mt-4 mb-3 rounded-lg bg-muted/50 border border-border px-4 py-3 flex items-start gap-3 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground mt-0.5 shrink-0">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
          </svg>
          <p class="text-muted-foreground leading-relaxed">
            Deleted invoices are kept for <strong class="text-foreground">3 years</strong> before permanent deletion.
            You can restore any invoice during this period. <strong class="text-foreground">{{ deletedPagination.total }}</strong> invoice{{ deletedPagination.total !== 1 ? 's' : '' }} in Trash.
          </p>
        </div>

        <!-- Search -->
        <div class="px-6 pb-3">
          <input
            :value="deletedSearch"
            type="text"
            placeholder="Search deleted invoices..."
            class="w-full max-w-xs rounded border border-border bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            @input="onDeletedSearch($event.target.value)"
          />
        </div>

        <!-- Backdrop to close trash row menu -->
        <div v-if="trashMenuOpenId" class="fixed inset-0 z-40" @click="trashMenuOpenId = null" />

        <!-- Trash table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Invoice #</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Estate · Unit</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Type</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Billed To</th>
                <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Amount</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Deleted On</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">Permanent Delete</th>
                <th class="py-3 px-4" />
              </tr>
            </thead>
            <tbody>
              <template v-if="deletedLoading">
                <tr v-for="n in 5" :key="'del-sk-' + n" class="border-b border-border">
                  <td v-for="c in 8" :key="c" class="py-3 px-4">
                    <div class="h-4 bg-muted animate-pulse rounded w-full" />
                  </td>
                </tr>
              </template>
              <template v-else>
                <tr
                  v-for="inv in deletedInvoices"
                  :key="inv.id"
                  class="border-b border-border hover:bg-muted/30 transition-colors cursor-pointer"
                  @click="goToInvoice(inv)"
                >
                  <td class="py-3 px-4 font-medium text-foreground">{{ inv.invoice_number }}</td>
                  <td class="py-3 px-4 text-muted-foreground">
                    {{ inv.unit?.estate?.name || '—' }}
                    <span v-if="inv.unit?.unit_number" class="text-foreground"> · {{ inv.unit.unit_number }}</span>
                  </td>
                  <td class="py-3 px-4 text-foreground">{{ inv.charge_type?.name || '—' }}</td>
                  <td class="py-3 px-4 text-foreground">{{ billedToName(inv) }}</td>
                  <td class="py-3 px-4 text-right font-medium text-foreground whitespace-nowrap">{{ fmt(inv.amount) }}</td>
                  <td class="py-3 px-4 text-muted-foreground whitespace-nowrap">{{ formatDeletedDate(inv.deleted_at) }}</td>
                  <td class="py-3 px-4 whitespace-nowrap">
                    <span class="text-destructive text-xs font-medium">{{ permanentDeleteDate(inv.deleted_at) }}</span>
                  </td>
                  <td class="py-3 px-4 text-right" @click.stop>
                    <div class="relative inline-block">
                      <button
                        class="p-1.5 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                        @click.stop="trashMenuOpenId = trashMenuOpenId === inv.id ? null : inv.id"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                      </button>
                      <div
                        v-if="trashMenuOpenId === inv.id"
                        class="absolute right-0 z-50 mt-1 w-44 rounded-md border border-border bg-card shadow-lg py-1"
                      >
                        <button
                          class="w-full flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                          :disabled="restoringId === inv.id"
                          @click.stop="restoreInvoice(inv); trashMenuOpenId = null"
                        >
                          <svg v-if="restoringId === inv.id" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                          <svg v-else xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                          {{ restoringId === inv.id ? 'Restoring…' : 'Restore' }}
                        </button>
                        <div class="h-px bg-border mx-2 my-1" />
                        <button
                          class="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-destructive/8 transition-colors"
                          @click.stop="openForceDelete(inv); trashMenuOpenId = null"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                          Delete Forever
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <tr v-if="deletedInvoices.length === 0">
                  <td colspan="8" class="py-12 text-center text-sm text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-3 text-muted-foreground/40">
                      <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                    </svg>
                    Trash is empty
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Trash pagination -->
        <div v-if="deletedPagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-border">
          <p class="text-xs text-muted-foreground">
            Showing {{ (deletedPagination.current_page - 1) * deletedPagination.per_page + 1 }}–{{ Math.min(deletedPagination.current_page * deletedPagination.per_page, deletedPagination.total) }} of {{ deletedPagination.total }}
          </p>
          <div class="flex items-center gap-1">
            <button @click="goDeletedPage(deletedPagination.current_page - 1)" :disabled="deletedPagination.current_page === 1" class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors">Previous</button>
            <template v-for="p in deletedPageNumbers" :key="p ?? 'ellipsis'">
              <span v-if="p === null" class="px-1 text-xs text-muted-foreground">…</span>
              <button v-else @click="goDeletedPage(p)" :class="['w-7 h-7 rounded text-xs font-medium border transition-colors', p === deletedPagination.current_page ? 'bg-primary border-primary text-white' : 'border-input bg-card text-foreground hover:bg-muted']">{{ p }}</button>
            </template>
            <button @click="goDeletedPage(deletedPagination.current_page + 1)" :disabled="deletedPagination.current_page === deletedPagination.last_page" class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors">Next</button>
          </div>
        </div>
      </template>
      <!-- ── End trash view ── -->

    </div>

    <!-- ── Charts ───────────────────────────────────────────────────────── -->
    <!-- Skeleton while primary data is still loading -->
    <div v-if="loading || summaryLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="n in 2" :key="n" class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-36 bg-muted rounded animate-pulse" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-64 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Invoices by Status (Donut) -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Invoices by Status</h3>
        </div>
        <div class="chart-tip-anchor relative px-6 pb-6 flex flex-col items-center gap-5">

          <!-- Loading state -->
          <div v-if="summaryLoading" class="w-64 h-64 flex items-center justify-center">
            <div class="w-48 h-48 rounded-full bg-muted animate-pulse" />
          </div>

          <!-- Ghost donut when no data -->
          <div v-else-if="donutSlices.length === 0" class="flex flex-col items-center justify-center" style="height: 260px;">
            <svg width="240" height="220" viewBox="0 0 240 220" xmlns="http://www.w3.org/2000/svg">
              <!-- Ghost donut segments: Paid (green), Overdue (red), Partial (amber) -->
              <path d="M 120.0 30.0 A 80 80 0 1 1 95.3 186.1 L 104.6 157.6 A 50 50 0 1 0 120.0 60.0 Z" fill="#BBF7D0" opacity="0.7"/>
              <path d="M 95.3 186.1 A 80 80 0 0 1 55.3 63.0 L 79.6 80.6 A 50 50 0 0 0 104.6 157.6 Z" fill="#FECACA" opacity="0.7"/>
              <path d="M 55.3 63.0 A 80 80 0 0 1 120.0 30.0 L 120.0 60.0 A 50 50 0 0 0 79.6 80.6 Z" fill="#FDE68A" opacity="0.7"/>
              <!-- Center placeholder -->
              <circle cx="120" cy="110" r="42" fill="white"/>
              <rect x="95" y="100" width="50" height="10" rx="5" fill="#E8EAF0"/>
              <rect x="103" y="116" width="34" height="8" rx="4" fill="#E8EAF0"/>
              <!-- Legend placeholders -->
              <rect x="28" y="200" width="10" height="10" rx="2" fill="#BBF7D0"/>
              <rect x="42" y="202" width="28" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="88" y="200" width="10" height="10" rx="2" fill="#FECACA"/>
              <rect x="102" y="202" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="152" y="200" width="10" height="10" rx="2" fill="#FDE68A"/>
              <rect x="166" y="202" width="28" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-1">Invoice data will appear here once billing is run</p>
          </div>

          <!-- Donut chart -->
          <template v-else>
            <svg viewBox="0 0 240 240" class="w-64 h-64" xmlns="http://www.w3.org/2000/svg">
              <!-- 100% single-segment: SVG arcs can't describe a full circle, use concentric circles instead -->
              <template v-if="donutSlices.length === 1">
                <circle cx="120" cy="120" r="82" :fill="donutSlices[0].color"
                  class="cursor-pointer"
                  @mousemove="onSliceMove($event, donutSlices[0])"
                  @mouseleave="donutTooltip = null"
                />
                <circle cx="120" cy="120" r="52" fill="white" />
              </template>
              <path
                v-else
                v-for="slice in donutSlices"
                :key="slice.key"
                :d="slice.path"
                :fill="slice.color"
                stroke="#fff"
                stroke-width="2"
                class="cursor-pointer"
                @mousemove="onSliceMove($event, slice)"
                @mouseleave="donutTooltip = null"
              />
              <text x="120" y="114" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="24" font-weight="700" fill="#1E2740">
                {{ paidPct }}%
              </text>
              <text x="120" y="128" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="10" fill="#717B99">
                Paid
              </text>
            </svg>

            <!-- Tooltip -->
            <div
              v-if="donutTooltip"
              class="pointer-events-none absolute z-20 rounded bg-[#1E2740] text-white text-xs px-2.5 py-1.5 shadow-md whitespace-nowrap"
              :style="{ left: donutTooltip.x + 'px', top: donutTooltip.y + 'px', transform: 'translate(-50%, calc(-100% - 8px))' }"
            >
              <span class="flex items-center gap-1.5">
                <span class="inline-block w-2 h-2 rounded-full shrink-0" :style="{ background: donutTooltip.slice.color }"></span>
                {{ donutTooltip.slice.label }}: {{ donutTooltip.slice.pct }}% ({{ donutTooltip.slice.count }} invoices)
              </span>
            </div>

            <div class="flex items-center justify-center gap-5 flex-wrap">
              <div v-for="item in donutLegend" :key="'leg-' + item.key" class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
                <span class="text-xs text-muted-foreground">{{ item.label }} {{ item.pct }}%</span>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Revenue by Charge Type (Bar) -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Revenue by Charge Type</h3>
        </div>
        <div class="chart-tip-anchor relative px-4 pb-4">

          <!-- Loading state -->
          <div v-if="summaryLoading" class="flex items-end gap-4 h-52 px-2">
            <div v-for="n in 3" :key="n" class="flex-1 bg-muted animate-pulse rounded-t" :style="{ height: (40 + n * 30) + 'px' }" />
          </div>

          <!-- Ghost bar chart when no data -->
          <div v-else-if="!barChart" class="flex flex-col items-center justify-center" style="height: 220px;">
            <svg width="100%" height="180" viewBox="0 0 400 180" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis -->
              <line x1="50" y1="10" x2="50" y2="155" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis -->
              <line x1="50" y1="155" x2="390" y2="155" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Y-axis tick labels -->
              <rect x="8"  y="10"  width="36" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8"  y="44"  width="36" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8"  y="78"  width="36" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8"  y="112" width="36" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="8"  y="146" width="36" height="6" rx="3" fill="#E8EAF0"/>
              <!-- Y-axis grid lines -->
              <line x1="50" y1="16"  x2="390" y2="16"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="50" y1="50"  x2="390" y2="50"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="50" y1="84"  x2="390" y2="84"  stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="50" y1="118" x2="390" y2="118" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Ghost bars: Levy (tall), Rent (medium-tall), Special Levy (short) -->
              <rect x="72"  y="30"  width="58" height="125" rx="4" fill="#C7D8EC" opacity="0.75"/>
              <rect x="162" y="60"  width="58" height="95"  rx="4" fill="#C7D8EC" opacity="0.6"/>
              <rect x="252" y="98"  width="58" height="57"  rx="4" fill="#C7D8EC" opacity="0.5"/>
              <rect x="342" y="120" width="40" height="35"  rx="4" fill="#C7D8EC" opacity="0.4"/>
              <!-- X-axis label placeholders -->
              <rect x="76"  y="163" width="48" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="166" y="163" width="48" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="256" y="163" width="48" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="344" y="163" width="34" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-1">Revenue data will appear here once invoices are created</p>
          </div>

          <!-- Bar chart -->
          <template v-else>
            <svg viewBox="0 0 400 255" class="w-full" xmlns="http://www.w3.org/2000/svg">
              <g>
                <line
                  v-for="tick in barChart.yTicks"
                  :key="'gl-' + tick.val"
                  :x1="barChart.PX0" :y1="tick.y"
                  :x2="barChart.PX1" :y2="tick.y"
                  stroke="#DCDEE8" stroke-width="1"
                />
                <text
                  v-for="tick in barChart.yTicks"
                  :key="'yt-' + tick.val"
                  :x="barChart.PX0 - 5" :y="tick.y"
                  text-anchor="end" dominant-baseline="middle"
                  font-size="10" fill="#717B99"
                >{{ tick.label }}</text>
              </g>
              <g>
                <g v-for="bar in barChart.bars" :key="'b-' + bar.type">
                  <path
                    :d="bar.path"
                    :fill="bar.color"
                    class="cursor-pointer hover:opacity-80 transition-opacity duration-150"
                    @mousemove="onBarMove($event, bar)"
                    @mouseleave="barTooltip = null"
                  />
                  <text
                    :x="bar.cx" :y="barChart.PY1 + 8"
                    :transform="`rotate(-45, ${bar.cx}, ${barChart.PY1 + 8})`"
                    text-anchor="end" font-size="9" fill="#717B99"
                  >{{ bar.shortType }}</text>
                </g>
              </g>
            </svg>

            <!-- Tooltip -->
            <div
              v-if="barTooltip"
              class="pointer-events-none absolute z-20 rounded bg-[#1E2740] text-white text-xs px-2.5 py-1.5 shadow-md whitespace-nowrap"
              :style="{ left: barTooltip.x + 'px', top: barTooltip.y + 'px', transform: 'translate(-50%, calc(-100% - 8px))' }"
            >
              {{ barTooltip.bar.type }}: {{ fmt(barTooltip.bar.amount) }}
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         Run Monthly Billing Modal
    ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showRun" title="Run Monthly Billing" size="lg" @close="closeRun">
      <div class="space-y-4 py-4">
        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="runEstate" label="Estate" :options="estateOpts" required />
          <AppSelect v-model="runPeriod" label="Billing Period" :options="periodOpts" placeholder="Select period..." required />
        </div>

        <!-- Error -->
        <p v-if="runError" class="text-sm text-danger">{{ runError }}</p>

        <!-- Preview loading -->
        <div v-if="runLoading" class="border rounded border-border p-6 flex items-center justify-center gap-2 text-sm text-muted-foreground">
          <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          Loading billing preview…
        </div>

        <!-- Prompt to select estate + period -->
        <div
          v-else-if="!runEstate || !runPeriod"
          class="border rounded border-border p-6 text-center text-sm text-muted-foreground"
        >
          Select an estate and billing period to preview invoices.
        </div>

        <!-- Preview table -->
        <div v-else-if="runPreview.length > 0" class="border rounded border-border overflow-hidden">
          <div class="bg-muted px-4 py-2 border-b border-border flex items-center justify-between">
            <p class="text-sm font-medium text-foreground">
              Billing Preview — {{ nonDuplicatePreview.length }} invoices to generate
            </p>
            <span v-if="duplicateCount > 0" class="text-xs text-muted-foreground">
              {{ duplicateCount }} duplicate(s) skipped
            </span>
          </div>
          <div class="max-h-72 overflow-y-auto">
            <table class="w-full text-sm">
              <thead class="sticky top-0 bg-muted/80">
                <tr class="border-b border-border">
                  <th class="text-left py-2 px-3 text-xs font-medium text-muted-foreground">Unit</th>
                  <th class="text-left py-2 px-3 text-xs font-medium text-muted-foreground">Charge Type</th>
                  <th class="text-left py-2 px-3 text-xs font-medium text-muted-foreground">Recipient</th>
                  <th class="text-right py-2 px-3 text-xs font-medium text-muted-foreground">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, idx) in runPreview"
                  :key="idx"
                  :class="['border-b border-border', row.duplicate ? 'opacity-40' : '']"
                >
                  <td class="py-2 px-3 font-medium text-foreground">{{ row.unit_number }}</td>
                  <td class="py-2 px-3 text-foreground">{{ row.charge_type }}</td>
                  <td class="py-2 px-3 text-foreground">
                    {{ row.recipient_name || (row.billed_to_type === 'owner' ? 'Owner' : 'Tenant') }}
                    <span v-if="row.duplicate" class="ml-1 text-xs text-muted-foreground">(duplicate)</span>
                  </td>
                  <td class="py-2 px-3 text-right font-medium text-foreground whitespace-nowrap">{{ fmt(row.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- No invoices to generate -->
        <div
          v-else-if="runEstate && runPeriod && !runLoading"
          class="border rounded border-border p-6 text-center text-sm text-muted-foreground"
        >
          No invoices to generate for this estate and period.
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="outline" @click="closeRun">Cancel</AppButton>
          <AppButton
            variant="primary"
            :disabled="nonDuplicatePreview.length === 0 || runConfirming"
            @click="confirmRunBilling"
          >
            <svg v-if="runConfirming" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Confirm &amp; Send Invoices
          </AppButton>
        </div>
      </div>
    </AppModal>

    <!-- ════════════════════════════════════════════════════════════════
         Ad-Hoc Billing Modal
    ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showAdHoc" title="Create Ad-Hoc Billing" size="md" @close="closeAdHoc">
      <div class="space-y-4 py-4">
        <AppSelect v-model="adHocEstate" label="Estate" :options="estateOpts" required />
        <div>
          <AppSelect v-model="adHocChargeType" label="Charge Type" :options="adHocChargeTypeOpts" required />
          <p class="text-xs text-muted-foreground mt-1">Only non-recurring (ad-hoc) charge types are shown.</p>
        </div>
        <AppInput
          v-model="adHocAmount"
          label="Amount"
          type="number"
          placeholder="0.00"
          prefix="R"
          required
        />
        <p v-if="adHocError" class="text-sm text-danger">{{ adHocError }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="outline" @click="closeAdHoc">Cancel</AppButton>
          <AppButton
            variant="primary"
            :disabled="!adHocValid || adHocLoading"
            @click="generateAdHoc"
          >
            <svg v-if="adHocLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Generate &amp; Send
          </AppButton>
        </div>
      </div>
    </AppModal>

    <!-- ════════════════════════════════════════════════════════════════
         Export Modal
    ════════════════════════════════════════════════════════════════ -->
    <AppExportModal
      :show="showExport"
      context="Invoices"
      @close="showExport = false"
      @download="handleExportDownload"
    />

    <!-- ════════════════════════════════════════════════════════════════
         Force Delete Confirmation Modal
    ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showForceDeleteConfirm" title="Delete Forever" size="sm" @close="showForceDeleteConfirm = false">
      <div class="space-y-3">
        <div class="rounded-lg bg-destructive/8 border border-destructive/20 px-4 py-3 text-sm">
          <p class="font-medium text-foreground">This cannot be undone.</p>
          <p class="text-muted-foreground mt-1">
            <strong class="text-foreground">{{ forceDeleteTarget?.invoice_number }}</strong> will be permanently removed from the system. All related data will be lost.
          </p>
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" @click="showForceDeleteConfirm = false">Cancel</AppButton>
        <AppButton variant="danger" :disabled="!!forceDeleting" @click="confirmForceDelete">
          <svg v-if="forceDeleting" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ forceDeleting ? 'Deleting…' : 'Delete Forever' }}
        </AppButton>
      </template>
    </AppModal>

  </div>
</template>
