<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi.js'
import AppButton from '@/components/common/AppButton.vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppBadge  from '@/components/common/AppBadge.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppInput         from '@/components/common/AppInput.vue'
import AppInvoiceSelect from '@/components/common/AppInvoiceSelect.vue'
import AppDatePicker    from '@/components/common/AppDatePicker.vue'
import { useBack } from '@/composables/useBack.js'

const router = useRouter()
const route  = useRoute()
const { goBack } = useBack({ name: 'age-analysis' })

// ── State ─────────────────────────────────────────────────────────────
const loading         = ref(true)
const error           = ref(null)
const owner           = ref(null)
const payments        = ref([])
const paymentsLoading = ref(false)

// ── Data fetching ──────────────────────────────────────────────────────
async function fetchOwner() {
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/owners/${route.params.ownerId}`)
    owner.value = data.data ?? data
  } catch (e) {
    owner.value = null
    if (e.response?.status !== 404) {
      error.value = 'Failed to load owner data.'
    }
  } finally {
    loading.value = false
  }
}

async function fetchPayments(unitId) {
  paymentsLoading.value = true
  try {
    const { data } = await api.get('/cashbook', {
      params: {
        unit_id:   unitId,
        type:      'credit',
        _per_page: 100,
      },
    })
    payments.value = data.data ?? []
  } catch {
    payments.value = []
  } finally {
    paymentsLoading.value = false
  }
}

onMounted(async () => {
  await fetchOwner()
  if (owner.value?.unit?.id) {
    fetchPayments(owner.value.unit.id)
  }
})

// ── Invoices computed wrapper ─────────────────────────────────────────
const invoices = computed(() => owner.value?.invoices ?? [])

// ── Invoice filter + pagination (mirrors UnitDetailPage exactly) ──────
const INVOICES_PER_PAGE = 5
const invoiceFilter = ref('all')
const invoicePage   = ref(1)

const filteredInvoices = computed(() => {
  const f = invoiceFilter.value
  if (f === 'all') return invoices.value
  return invoices.value.filter(i => i.status === f)
})

const invoicePageCount = computed(() =>
  Math.max(1, Math.ceil(filteredInvoices.value.length / INVOICES_PER_PAGE))
)

const pagedInvoices = computed(() => {
  const start = (invoicePage.value - 1) * INVOICES_PER_PAGE
  return filteredInvoices.value.slice(start, start + INVOICES_PER_PAGE)
})

watch(invoiceFilter, () => { invoicePage.value = 1 })

const invoiceTotals = computed(() => {
  const list = invoices.value
  const total = list.reduce((s, i) => s + Number(i.amount ?? 0), 0)
  const paid  = list.reduce((s, i) => s + Number(i.total_paid ?? 0), 0)
  const counts = {
    paid:           list.filter(i => i.status === 'paid').length,
    unpaid:         list.filter(i => i.status === 'unpaid').length,
    overdue:        list.filter(i => i.status === 'overdue').length,
    partially_paid: list.filter(i => i.status === 'partially_paid').length,
  }
  return { total, paid, counts }
})

// ── Account Balance — computed client-side from fetched data ──────────
const ownerOutstanding = computed(() =>
  invoices.value
    .filter(i => ['unpaid', 'overdue', 'partially_paid'].includes(i.status))
    .reduce((s, i) => s + Math.max(0, Number(i.amount ?? 0) - Number(i.total_paid ?? 0)), 0)
)
const ownerCreditOnAccount = computed(() =>
  payments.value
    .filter(p => !p.invoice_id)
    .reduce((s, p) => s + Number(p.amount ?? 0), 0)
)
const ownerBalance        = computed(() => ownerCreditOnAccount.value - ownerOutstanding.value)
const ownerNetOutstanding = computed(() => Math.max(0, ownerOutstanding.value - ownerCreditOnAccount.value))

// ── Payment filter + pagination (mirrors UnitDetailPage exactly) ──────
const PAYMENTS_PER_PAGE = 5
const paymentFilter = ref('all')
const paymentPage   = ref(1)

const filteredPayments = computed(() => {
  const f = paymentFilter.value
  if (f === 'all')         return payments.value
  if (f === 'allocated')   return payments.value.filter(p =>  p.is_allocated)
  if (f === 'unallocated') return payments.value.filter(p => !p.is_allocated)
  return payments.value
})

const paymentPageCount = computed(() =>
  Math.max(1, Math.ceil(filteredPayments.value.length / PAYMENTS_PER_PAGE))
)

const pagedPayments = computed(() => {
  const start = (paymentPage.value - 1) * PAYMENTS_PER_PAGE
  return filteredPayments.value.slice(start, start + PAYMENTS_PER_PAGE)
})

watch(paymentFilter, () => { paymentPage.value = 1 })

const paymentTotals = computed(() => {
  const list = payments.value
  const total       = list.reduce((s, p) => s + Number(p.amount ?? 0), 0)
  const allocated   = list.filter(p =>  p.is_allocated).reduce((s, p) => s + Number(p.amount ?? 0), 0)
  const unallocated = list.filter(p => !p.is_allocated).reduce((s, p) => s + Number(p.amount ?? 0), 0)
  return { total, allocated, unallocated }
})

// ── Computed arrears from invoice due_dates + outstanding ─────────────
const arrears = computed(() => {
  const invoiceList = owner.value?.invoices ?? []
  const today    = new Date()
  const result   = { current: 0, d30: 0, d60: 0, d90: 0, d120: 0, total: 0 }

  for (const inv of invoiceList) {
    const outstanding = parseFloat(inv.outstanding ?? 0)
    if (outstanding <= 0) continue

    const dueDate  = new Date(inv.due_date)
    const daysLate = Math.floor((today - dueDate) / 86_400_000)

    if      (daysLate <= 0)  result.current += outstanding
    else if (daysLate <= 30) result.d30     += outstanding
    else if (daysLate <= 60) result.d60     += outstanding
    else if (daysLate <= 90) result.d90     += outstanding
    else                     result.d120    += outstanding

    result.total += outstanding
  }

  return result
})

// ── Units owned (wraps the single unit into an array) ──────────────────
const ownerUnits = computed(() => {
  if (!owner.value?.unit) return []
  const u = owner.value.unit
  return [{
    unitId:      u.id,
    unitNumber:  u.unit_number,
    estateId:    u.estate_id,
    estateName:  u.estate?.name ?? '',
    monthlyLevy: u.levy_override ?? u.estate?.default_levy_amount ?? null,
    occupancy:   u.occupancy_type,
  }]
})

// ── Helpers ───────────────────────────────────────────────────────────
function invoiceStatusBadge(status) {
  const map = {
    paid:          { label: 'Paid',    wrapClass: 'bg-success/10 text-success border-success/20'             },
    overdue:       { label: 'Overdue', wrapClass: 'bg-destructive/10 text-destructive border-destructive/20' },
    partially_paid:{ label: 'Partial', wrapClass: 'bg-warning/10 text-warning border-warning/20'             },
    unpaid:        { label: 'Unpaid',  wrapClass: 'bg-muted text-muted-foreground border-border'             },
  }
  return map[status] ?? map.unpaid
}

function occupancyBadge(occupancy) {
  const map = {
    owner_occupied:  { label: 'Owner Occupied',  wrapClass: 'bg-success/10 text-success border-success/20' },
    tenant_occupied: { label: 'Tenant Occupied', wrapClass: 'bg-blue-50 text-blue-700 border-blue-200'     },
    vacant:          { label: 'Vacant',          wrapClass: 'bg-muted text-muted-foreground border-border'  },
  }
  return map[occupancy] ?? map.vacant
}

function fmt(val) {
  const num = parseFloat(val ?? 0)
  if (num === 0) return '—'
  return 'R\u00a0' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
}

function fmtAmount(n) {
  if (n == null) return '—'
  const abs  = Math.abs(n).toLocaleString('en-US').replace(/,/g, '\u00A0')
  const sign = n < 0 ? '-' : ''
  return `${sign}R\u00A0${abs}`
}

function fmtPaymentAmount(n) {
  if (n == null) return '—'
  const abs = Math.abs(n).toLocaleString('en-US').replace(/,/g, '\u00A0')
  return `+R\u00A0${abs}`
}

function statusVariant(s) {
  return { paid: 'success', overdue: 'danger', partially_paid: 'warning', unpaid: 'default' }[s] ?? 'default'
}

function statusLabel(s) {
  return { paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', unpaid: 'Unpaid' }[s] ?? s
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

function fmtPeriod(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + (dateStr.length === 7 ? '-01' : ''))
  return d.toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })
}

function fmtDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function initials(name) {
  if (!name) return '??'
  return name.split(' ').map(n => n[0]).slice(0, 2).join('')
}

// ── Navigation ────────────────────────────────────────────────────────

function goToEstate(estateId) {
  router.push({ name: 'estate-detail', params: { id: estateId } })
}

function goToUnit(unit) {
  router.push({ name: 'unit-detail', params: { estateId: unit.estateId, unitId: unit.unitId } })
}

function goToInvoice(invoiceId) {
  router.push({ name: 'invoice-detail', params: { invoiceId } })
}

function goToCashbookEntry(entryId) {
  router.push({ name: 'cashbook-entry', params: { entryId } })
}

// ── Add Payment modal ─────────────────────────────────────────────────
const showAddPayment     = ref(false)
const paymentSaving      = ref(false)
const paymentError2      = ref(null)
const paymentForm        = ref({ date: '', description: '', amount: '', invoiceId: '' })
const proofOfPaymentFile = ref(null)
const proofInput         = ref(null)

// invoiceOptions removed — replaced by AppInvoiceSelect component

function handleProofChange(e) {
  const file = e.target.files[0]
  if (file) proofOfPaymentFile.value = file
}

function handleProofDrop(e) {
  const file = e.dataTransfer.files[0]
  if (file) proofOfPaymentFile.value = file
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function openAddPayment() {
  paymentForm.value        = { date: '', description: '', amount: '', invoiceId: '' }
  paymentError2.value      = null
  proofOfPaymentFile.value = null
  showAddPayment.value     = true
}

async function savePayment() {
  paymentSaving.value = true
  paymentError2.value = null
  try {
    const fd = new FormData()
    fd.append('estate_id',   owner.value?.unit?.estate_id ?? '')
    fd.append('unit_id',     owner.value?.unit?.id ?? '')
    fd.append('type',        'credit')
    fd.append('date',        paymentForm.value.date)
    fd.append('description', paymentForm.value.description)
    fd.append('amount',      String(parseFloat(paymentForm.value.amount)))
    if (paymentForm.value.invoiceId)  fd.append('invoice_id', paymentForm.value.invoiceId)
    if (proofOfPaymentFile.value)     fd.append('proof_of_payment', proofOfPaymentFile.value)
    await api.post('/cashbook', fd)
    showAddPayment.value     = false
    proofOfPaymentFile.value = null
    await fetchOwner()
  } catch (err) {
    paymentError2.value = err?.response?.data?.message ?? 'Failed to record payment. Please try again.'
  } finally {
    paymentSaving.value = false
  }
}

// ── Send Message modal ────────────────────────────────────────────────
const showSendMessage = ref(false)

const OWNER_TEMPLATES = [
  { value: 'payment_reminder', label: 'Payment Reminder'     },
  { value: 'levy_increase',    label: 'Levy Increase Notice' },
  { value: 'welcome',          label: 'Welcome Letter'       },
  { value: 'maintenance',      label: 'Maintenance Notice'   },
  { value: 'statement',        label: 'Monthly Statement'    },
]

const messageForm = ref({ template: '', subject: '', body: '' })

function onTemplateChange() {
  const name = owner.value?.full_name ?? ''
  const tplMap = {
    payment_reminder: {
      subject: `Payment Reminder — ${name}`,
      body:    `Dear ${name},\n\nThis is a friendly reminder that your levy payment is due. Please ensure payment is made before the due date to avoid penalties.\n\nKind regards,\nBold Mark Properties`,
    },
    levy_increase: {
      subject: 'Levy Increase Notice',
      body:    `Dear ${name},\n\nWe wish to inform you of an upcoming levy adjustment effective next month. Please contact us if you have any questions.\n\nKind regards,\nBold Mark Properties`,
    },
    welcome: {
      subject: `Welcome — ${name}`,
      body:    `Dear ${name},\n\nWelcome to the estate. We look forward to working with you and ensuring your property is well managed.\n\nKind regards,\nBold Mark Properties`,
    },
    maintenance: {
      subject: 'Maintenance Notice',
      body:    `Dear ${name},\n\nWe would like to inform you of upcoming maintenance work at the estate. Please contact us if you have any concerns.\n\nKind regards,\nBold Mark Properties`,
    },
    statement: {
      subject: `Monthly Statement — ${name}`,
      body:    `Dear ${name},\n\nPlease find your monthly levy statement attached for your records.\n\nKind regards,\nBold Mark Properties`,
    },
  }
  const tpl = tplMap[messageForm.value.template]
  if (tpl) {
    messageForm.value.subject = tpl.subject
    messageForm.value.body    = tpl.body
  }
}

function openSendMessage() {
  messageForm.value = { template: '', subject: '', body: '' }
  showSendMessage.value = true
}

function sendMessage() {
  // TODO: POST /api/v1/communications
  showSendMessage.value = false
}

// ── Edit Owner modal ──────────────────────────────────────────────────
const showEditOwner   = ref(false)
const editOwnerSaving = ref(false)
const editOwnerError  = ref(null)
const editOwnerForm   = ref({
  full_name:  '',
  email:      '',
  phone:      '',
  id_number:  '',
  address:    '',
})

function openEditOwner() {
  editOwnerError.value = null
  editOwnerForm.value = {
    full_name: owner.value?.full_name  ?? '',
    email:     owner.value?.email      ?? '',
    phone:     owner.value?.phone      ?? '',
    id_number: owner.value?.id_number  ?? '',
    address:   owner.value?.address    ?? '',
  }
  showEditOwner.value = true
}

async function saveEditOwner() {
  editOwnerError.value  = null
  editOwnerSaving.value = true
  try {
    const { data } = await api.put(`/owners/${route.params.ownerId}`, editOwnerForm.value)
    owner.value = { ...owner.value, ...(data.data ?? data) }
    showEditOwner.value = false
  } catch (err) {
    editOwnerError.value = err?.response?.data?.message ?? 'Failed to save changes. Please try again.'
  } finally {
    editOwnerSaving.value = false
  }
}
</script>

<template>
<div>

  <!-- ── Loading skeleton ──────────────────────────────────────────────── -->
  <div v-if="loading" class="space-y-6 pb-8 animate-pulse">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-muted" />
      <div class="space-y-2">
        <div class="h-6 w-48 rounded bg-muted" />
        <div class="h-4 w-32 rounded bg-muted" />
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="space-y-4">
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-32 rounded bg-muted" />
          <div class="h-4 w-full rounded bg-muted" />
          <div class="h-4 w-3/4 rounded bg-muted" />
          <div class="h-4 w-1/2 rounded bg-muted" />
        </div>
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-36 rounded bg-muted" />
          <div v-for="i in 6" :key="i" class="h-4 w-full rounded bg-muted" />
        </div>
      </div>
      <div class="lg:col-span-2 space-y-4">
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-24 rounded bg-muted" />
          <div v-for="i in 3" :key="i" class="h-10 w-full rounded bg-muted" />
        </div>
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-36 rounded bg-muted" />
          <div v-for="i in 2" :key="i" class="h-10 w-full rounded bg-muted" />
        </div>
      </div>
    </div>
  </div>

  <!-- ── Error state ───────────────────────────────────────────────────── -->
  <div v-else-if="error" class="flex flex-col items-center justify-center py-24 text-center">
    <p class="text-lg font-medium text-destructive">{{ error }}</p>
    <AppButton variant="outline" class="mt-4" @click="fetchOwner">Retry</AppButton>
  </div>

  <!-- ── Found state ───────────────────────────────────────────────────── -->
  <div v-else-if="owner" class="space-y-6 pb-8">

    <!-- ── Header ────────────────────────────────────────────────────── -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-muted transition-colors" @click="goBack">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
          <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
        </svg>
      </button>
      <div class="flex-1">
        <div class="flex items-center gap-3">
          <h1 class="font-body font-bold text-2xl text-foreground">{{ owner.full_name }}</h1>
          <span class="inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1 leading-tight bg-primary/10 text-primary border-primary/20">
            Owner
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          <button
            v-if="owner.unit?.estate_id"
            class="hover:underline text-primary"
            @click="goToEstate(owner.unit.estate_id)"
          >
            {{ owner.unit?.estate?.name ?? '—' }}
          </button>
          <span v-else>{{ owner.unit?.estate?.name ?? '—' }}</span>
        </p>
      </div>
      <div class="flex gap-2">
        <AppButton variant="outline" @click="openEditOwner">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
          </svg>
          Edit Owner
        </AppButton>
        <AppButton variant="outline" @click="openSendMessage">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/>
            <path d="m21.854 2.147-10.94 10.939"/>
          </svg>
          Send Message
        </AppButton>
      </div>
    </div>

    <!-- ── Body grid ──────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- ╔══════════════ LEFT COLUMN ══════════════╗ -->
      <div class="space-y-6">

        <!-- Contact Details card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Contact Details</h3>
          </div>
          <div class="p-6 pt-0 space-y-3">
            <div class="flex items-center gap-3 text-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground shrink-0">
                <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
              </svg>
              <span class="text-foreground">{{ owner.email }}</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground shrink-0">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
              <span class="text-foreground">{{ owner.phone ?? '—' }}</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground shrink-0">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <span class="text-muted-foreground">ID:</span>
              <span class="text-foreground">{{ owner.id_number ?? '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Arrears Summary card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Arrears Summary</h3>
          </div>
          <div class="p-6 pt-0 space-y-2">
            <div class="flex justify-between text-sm py-1.5 border-b border-border/50">
              <span class="text-muted-foreground">Current</span>
              <span class="text-foreground font-medium">{{ fmt(arrears.current) }}</span>
            </div>
            <div class="flex justify-between text-sm py-1.5 border-b border-border/50">
              <span class="text-muted-foreground">30 Days</span>
              <span :class="arrears.d30 ? 'text-accent font-medium' : 'text-muted-foreground'">{{ fmt(arrears.d30) }}</span>
            </div>
            <div class="flex justify-between text-sm py-1.5 border-b border-border/50">
              <span class="text-muted-foreground">60 Days</span>
              <span :class="arrears.d60 ? 'text-destructive font-medium' : 'text-muted-foreground'">{{ fmt(arrears.d60) }}</span>
            </div>
            <div class="flex justify-between text-sm py-1.5 border-b border-border/50">
              <span class="text-muted-foreground">90 Days</span>
              <span :class="arrears.d90 ? 'text-destructive font-medium' : 'text-muted-foreground'">{{ fmt(arrears.d90) }}</span>
            </div>
            <div class="flex justify-between text-sm py-1.5 border-b border-border/50">
              <span class="text-muted-foreground">120+ Days</span>
              <span :class="arrears.d120 ? 'text-destructive font-medium' : 'text-muted-foreground'">{{ fmt(arrears.d120) }}</span>
            </div>
            <div class="flex justify-between text-sm py-1.5 pt-2">
              <span class="font-semibold text-foreground">Total Outstanding</span>
              <span :class="['font-bold', arrears.total > 0 ? 'text-destructive' : 'text-foreground']">{{ fmt(arrears.total) }}</span>
            </div>
          </div>
        </div>

        <!-- Units Owned card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Units Owned</h3>
          </div>
          <div class="p-6 pt-0 space-y-2">
            <div
              v-for="unit in ownerUnits"
              :key="unit.unitId"
              class="flex items-center justify-between p-3 rounded-lg border border-border hover:bg-muted/30 cursor-pointer transition-colors"
              @click="goToUnit(unit)"
            >
              <div>
                <p class="text-sm font-semibold text-foreground">Unit {{ unit.unitNumber }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                  {{ unit.monthlyLevy ? 'Levy  ' + fmtAmount(unit.monthlyLevy) + ' / month' : '—' }}
                </p>
              </div>
              <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border leading-tight', occupancyBadge(unit.occupancy).wrapClass]">
                {{ occupancyBadge(unit.occupancy).label }}
              </span>
            </div>
            <p v-if="ownerUnits.length === 0" class="text-sm text-muted-foreground py-2">No units found</p>
          </div>
        </div>

      </div>
      <!-- ╚══════════════ END LEFT COLUMN ══════════════╝ -->

      <!-- ╔══════════════ RIGHT COLUMN ══════════════╗ -->
      <div class="lg:col-span-2 space-y-4">

        <!-- Account Balance card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-muted-foreground">Account Balance</p>
                <p :class="['text-3xl font-bold font-body', ownerBalance < 0 ? 'text-destructive' : 'text-foreground']">
                  {{ fmtAmount(ownerBalance) }}
                </p>
              </div>
              <div v-if="ownerBalance < 0" class="px-3 py-1 rounded-full bg-destructive/10 text-destructive text-xs font-medium">
                In Arrears
              </div>
            </div>
          </div>
        </div>

        <!-- Invoices section -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Invoices</h3>
          </div>
          <div class="p-6 pt-0">
            <!-- Filter tabs -->
            <div class="flex items-center gap-1.5 mb-4 flex-wrap">
              <button
                v-for="tab in [
                  { key: 'all',           label: 'All',     count: invoices.length },
                  { key: 'paid',          label: 'Paid',    count: invoiceTotals.counts.paid },
                  { key: 'unpaid',        label: 'Unpaid',  count: invoiceTotals.counts.unpaid },
                  { key: 'overdue',       label: 'Overdue', count: invoiceTotals.counts.overdue },
                  { key: 'partially_paid',label: 'Partial', count: invoiceTotals.counts.partially_paid },
                ].filter(t => t.key === 'all' || t.count > 0)"
                :key="tab.key"
                type="button"
                :class="[
                  'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-all',
                  invoiceFilter === tab.key
                    ? 'bg-foreground/5 text-foreground border-foreground/25'
                    : 'bg-transparent text-muted-foreground border-border hover:border-foreground/20 hover:text-foreground',
                ]"
                @click="invoiceFilter = tab.key"
              >
                {{ tab.label }}
                <span :class="[
                  'inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] font-bold',
                  invoiceFilter === tab.key ? 'bg-foreground/10 text-foreground' : 'bg-muted-foreground/15 text-muted-foreground',
                ]">{{ tab.count }}</span>
              </button>
            </div>

            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border">
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Type</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Period</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Billed To</th>
                  <th class="text-right py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                  <th class="text-center py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Sent</th>
                  <th class="text-right py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="inv in pagedInvoices"
                  :key="inv.id"
                  class="border-b border-border hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="goToInvoice(inv.id)"
                >
                  <td class="py-3 px-2 font-medium" :class="inv.status === 'overdue' ? 'text-danger' : 'text-foreground'">{{ inv.invoice_number }}</td>
                  <td class="py-3 px-2 text-foreground">{{ inv.charge_type?.name ?? '—' }}</td>
                  <td class="py-3 px-2 text-muted-foreground">{{ fmtPeriod(inv.billing_period) }}</td>
                  <td class="py-3 px-2 text-foreground">{{ owner.full_name }}</td>
                  <td class="py-3 px-2 text-right font-medium text-foreground whitespace-nowrap">{{ fmtAmount(inv.amount) }}</td>
                  <!-- Email delivery tick indicator -->
                  <td class="py-3 px-2 text-center" :title="emailDeliveryTooltip(inv)">
                    <span v-if="emailDeliveryStatus(inv) === 'none'" class="text-xs text-muted-foreground/40">—</span>
                    <span v-else-if="emailDeliveryStatus(inv) === 'sent'" class="inline-flex items-center">
                      <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-muted-foreground">
                        <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </span>
                    <span v-else-if="emailDeliveryStatus(inv) === 'delivered'" class="inline-flex items-center gap-[-4px]">
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
                  <td class="py-3 px-2 text-right">
                    <AppBadge :variant="statusVariant(inv.status)" bordered size="sm">{{ statusLabel(inv.status) }}</AppBadge>
                  </td>
                </tr>
                <tr v-if="filteredInvoices.length === 0">
                  <td colspan="7" class="py-8 text-center text-sm text-muted-foreground">
                    {{ invoiceFilter === 'all' ? 'No invoices found' : 'No ' + invoiceFilter.replace('_', ' ') + ' invoices' }}
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="invoicePageCount > 1" class="flex items-center justify-between mt-4 pt-3">
              <p class="text-xs text-muted-foreground">
                Showing {{ (invoicePage - 1) * INVOICES_PER_PAGE + 1 }}–{{ Math.min(invoicePage * INVOICES_PER_PAGE, filteredInvoices.length) }} of {{ filteredInvoices.length }}
              </p>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  :disabled="invoicePage === 1"
                  :class="['px-2.5 py-1 rounded text-xs font-medium border transition-all', invoicePage === 1 ? 'text-muted-foreground/40 border-border cursor-not-allowed' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                  @click="invoicePage--"
                >← Prev</button>
                <template v-for="p in invoicePageCount" :key="p">
                  <button
                    v-if="p === 1 || p === invoicePageCount || Math.abs(p - invoicePage) <= 1"
                    type="button"
                    :class="['w-7 h-7 rounded text-xs font-medium border transition-all', p === invoicePage ? 'bg-primary text-primary-foreground border-primary' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                    @click="invoicePage = p"
                  >{{ p }}</button>
                  <span v-else-if="p === 2 || p === invoicePageCount - 1" class="px-0.5 text-muted-foreground text-xs">…</span>
                </template>
                <button
                  type="button"
                  :disabled="invoicePage === invoicePageCount"
                  :class="['px-2.5 py-1 rounded text-xs font-medium border transition-all', invoicePage === invoicePageCount ? 'text-muted-foreground/40 border-border cursor-not-allowed' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                  @click="invoicePage++"
                >Next →</button>
              </div>
            </div>

            <!-- Totals footer -->
            <div v-if="invoices.length > 0" class="mt-3 pt-3 flex items-center gap-0 text-sm flex-wrap">
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Billed</span>
                <span class="font-semibold text-foreground font-body">{{ fmtAmount(invoiceTotals.total) }}</span>
              </div>
              <div class="w-px h-3.5 bg-border mr-4" />
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Paid</span>
                <span class="font-semibold text-success font-body">{{ fmtAmount(invoiceTotals.paid) }}</span>
              </div>
              <template v-if="ownerCreditOnAccount > 0">
                <div class="w-px h-3.5 bg-border mr-4" />
                <div class="flex items-center gap-1.5 pr-4">
                  <span class="text-muted-foreground text-xs">Credit</span>
                  <span class="font-semibold text-success font-body">{{ fmtAmount(ownerCreditOnAccount) }}</span>
                </div>
              </template>
              <div class="w-px h-3.5 bg-border mr-4" />
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Outstanding</span>
                <span :class="['font-semibold font-body', ownerNetOutstanding > 0 ? 'text-destructive' : 'text-foreground']">{{ fmtAmount(ownerNetOutstanding) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Payments Received section -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                  <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                </svg>
                <h3 class="tracking-tight font-body font-semibold text-lg">Payments Received</h3>
              </div>
              <AppButton variant="outline" size="sm" @click="openAddPayment">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                Add Payment
              </AppButton>
            </div>
          </div>
          <div class="p-6 pt-0">
            <!-- Filter tabs -->
            <div class="flex items-center gap-1.5 mb-4 flex-wrap">
              <button
                v-for="tab in [
                  { key: 'all',         label: 'All',         count: payments.length },
                  { key: 'allocated',   label: 'Allocated',   count: payments.filter(p => p.is_allocated).length },
                  { key: 'unallocated', label: 'Unallocated', count: payments.filter(p => !p.is_allocated).length },
                ].filter(t => t.key === 'all' || t.count > 0)"
                :key="tab.key"
                type="button"
                :class="[
                  'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-all',
                  paymentFilter === tab.key
                    ? 'bg-foreground/5 text-foreground border-foreground/25'
                    : 'bg-transparent text-muted-foreground border-border hover:border-foreground/20 hover:text-foreground',
                ]"
                @click="paymentFilter = tab.key"
              >
                {{ tab.label }}
                <span :class="[
                  'inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] font-bold',
                  paymentFilter === tab.key ? 'bg-foreground/10 text-foreground' : 'bg-muted-foreground/15 text-muted-foreground',
                ]">{{ tab.count }}</span>
              </button>
            </div>

            <!-- skeleton rows while loading -->
            <div v-if="paymentsLoading" class="space-y-2 animate-pulse">
              <div v-for="i in 2" :key="i" class="h-10 w-full rounded bg-muted" />
            </div>
            <table v-else-if="filteredPayments.length > 0" class="w-full text-sm">
              <thead>
                <tr class="border-b border-border">
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Description</th>
                  <th class="text-right py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice</th>
                  <th class="text-left py-3 px-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="pmt in pagedPayments"
                  :key="pmt.id"
                  class="border-b border-border hover:bg-muted/50 transition-colors cursor-pointer"
                  @click="goToCashbookEntry(pmt.id)"
                >
                  <td class="py-3 px-2 text-foreground">{{ fmtDate(pmt.date) }}</td>
                  <td class="py-3 px-2 text-foreground">{{ pmt.description }}</td>
                  <td class="py-3 px-2 text-right font-medium text-success">{{ fmtPaymentAmount(pmt.amount) }}</td>
                  <td class="py-3 px-2 text-foreground font-mono text-xs">{{ pmt.invoice?.invoice_number ?? '—' }}</td>
                  <td class="py-3 px-2">
                    <AppBadge v-if="pmt.is_allocated" variant="success" bordered size="sm">Allocated</AppBadge>
                    <AppBadge v-else variant="warning" bordered size="sm">Unallocated</AppBadge>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else-if="!paymentsLoading" class="text-sm text-muted-foreground text-center py-6">
              {{ paymentFilter === 'all' ? 'No payments recorded' : 'No ' + paymentFilter + ' payments' }}
            </p>

            <!-- Pagination -->
            <div v-if="paymentPageCount > 1" class="flex items-center justify-between mt-4 pt-3">
              <p class="text-xs text-muted-foreground">
                Showing {{ (paymentPage - 1) * PAYMENTS_PER_PAGE + 1 }}–{{ Math.min(paymentPage * PAYMENTS_PER_PAGE, filteredPayments.length) }} of {{ filteredPayments.length }}
              </p>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  :disabled="paymentPage === 1"
                  :class="['px-2.5 py-1 rounded text-xs font-medium border transition-all', paymentPage === 1 ? 'text-muted-foreground/40 border-border cursor-not-allowed' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                  @click="paymentPage--"
                >← Prev</button>
                <template v-for="p in paymentPageCount" :key="p">
                  <button
                    v-if="p === 1 || p === paymentPageCount || Math.abs(p - paymentPage) <= 1"
                    type="button"
                    :class="['w-7 h-7 rounded text-xs font-medium border transition-all', p === paymentPage ? 'bg-primary text-primary-foreground border-primary' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                    @click="paymentPage = p"
                  >{{ p }}</button>
                  <span v-else-if="p === 2 || p === paymentPageCount - 1" class="px-0.5 text-muted-foreground text-xs">…</span>
                </template>
                <button
                  type="button"
                  :disabled="paymentPage === paymentPageCount"
                  :class="['px-2.5 py-1 rounded text-xs font-medium border transition-all', paymentPage === paymentPageCount ? 'text-muted-foreground/40 border-border cursor-not-allowed' : 'text-foreground border-border hover:border-primary/40 hover:text-primary']"
                  @click="paymentPage++"
                >Next →</button>
              </div>
            </div>

            <!-- Totals footer -->
            <div v-if="payments.length > 0" class="mt-3 pt-3 flex items-center gap-0 text-sm flex-wrap">
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Received</span>
                <span class="font-semibold text-success font-body">+{{ fmtAmount(paymentTotals.total) }}</span>
              </div>
              <div class="w-px h-3.5 bg-border mr-4" />
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Allocated</span>
                <span class="font-semibold text-foreground font-body">{{ fmtAmount(paymentTotals.allocated) }}</span>
              </div>
              <div class="w-px h-3.5 bg-border mr-4" />
              <div class="flex items-center gap-1.5">
                <span class="text-muted-foreground text-xs">Unallocated</span>
                <span :class="['font-semibold font-body', paymentTotals.unallocated > 0 ? 'text-warning' : 'text-foreground']">{{ fmtAmount(paymentTotals.unallocated) }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- ╚══════════════ END RIGHT COLUMN ══════════════╝ -->

    </div>
  </div>

  <!-- ── Not found state ───────────────────────────────────────────── -->
  <div v-else class="flex flex-col items-center justify-center py-24 text-center">
    <p class="text-lg font-medium text-foreground">Owner not found</p>
    <p class="text-sm text-muted-foreground mt-1">This owner record may have been removed or does not exist.</p>
    <AppButton variant="outline" class="mt-4" @click="goBack()">Go Back</AppButton>
  </div>

  <!-- ── Add Payment modal ─────────────────────────────────────────── -->
  <AppModal :show="showAddPayment" size="md" @close="showAddPayment = false">
    <template #header>
      <h3 class="text-base font-bold font-body flex items-center gap-2 text-foreground">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
          <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
          <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
        </svg>
        Add Payment
      </h3>
    </template>

    <div class="space-y-4">
      <div v-if="owner" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ initials(owner.full_name) }}
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">{{ owner.full_name }}</p>
          <p class="text-xs text-muted-foreground">
            Owner · {{ ownerUnits.map(u => 'Unit ' + u.unitNumber).join(', ') }}
          </p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-foreground mb-1.5">Payment Date</label>
        <AppDatePicker v-model="paymentForm.date" placeholder="Select date..." />
      </div>
      <AppInput v-model="paymentForm.description" label="Description" placeholder="e.g. EFT – M NDABA LEVY APR" />
      <AppInput v-model="paymentForm.amount" label="Amount (R)" type="number" placeholder="0.00" />

      <div>
        <label class="text-sm font-medium text-foreground mb-1.5 block">Allocate to Invoice</label>
        <AppInvoiceSelect v-model="paymentForm.invoiceId" :unit-id="owner?.unit?.id ?? null" placeholder="Select invoice..." />
        <p class="text-xs text-muted-foreground mt-1">If no invoice yet, the payment will sit as an unallocated credit.</p>
      </div>

      <!-- Proof of Payment upload -->
      <div>
        <label class="block text-sm font-medium text-foreground mb-1.5">
          Proof of Payment <span class="text-muted-foreground font-normal">(optional)</span>
        </label>
        <div
          v-if="!proofOfPaymentFile"
          class="border-2 border-dashed border-border rounded-lg p-4 text-center cursor-pointer hover:border-primary/40 transition-colors"
          @click="proofInput.click()"
          @dragover.prevent
          @drop.prevent="handleProofDrop"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 mx-auto text-muted-foreground mb-2">
            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
          </svg>
          <p class="text-sm text-muted-foreground">Drop file here or <span class="text-primary font-medium">browse</span></p>
          <p class="text-xs text-muted-foreground mt-0.5">PDF, JPG, PNG — max 10 MB</p>
        </div>
        <div v-else class="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-primary shrink-0">
            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
          </svg>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-foreground truncate">{{ proofOfPaymentFile.name }}</p>
            <p class="text-xs text-muted-foreground">{{ formatFileSize(proofOfPaymentFile.size) }}</p>
          </div>
          <button type="button" class="text-muted-foreground hover:text-destructive transition-colors" @click="proofOfPaymentFile = null">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
          </button>
        </div>
        <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="handleProofChange" />
      </div>

      <p v-if="paymentError2" class="text-xs text-destructive">{{ paymentError2 }}</p>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="showAddPayment = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="paymentSaving" @click="savePayment">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        {{ paymentSaving ? 'Recording...' : 'Record Payment' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Send Message modal ─────────────────────────────────────────── -->
  <AppModal :show="showSendMessage" size="md" @close="showSendMessage = false">
    <template #header>
      <h3 class="text-base font-bold font-body flex items-center gap-2 text-foreground">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
          <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        Send Message
      </h3>
    </template>

    <div class="space-y-4">
      <div v-if="owner" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ initials(owner.full_name) }}
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">{{ owner.full_name }}</p>
          <p class="text-xs text-muted-foreground">{{ owner.email }} · <span>Owner</span></p>
        </div>
      </div>

      <div>
        <label class="text-sm font-medium text-foreground mb-1.5 block">Template</label>
        <AppSelect
          v-model="messageForm.template"
          :options="OWNER_TEMPLATES"
          placeholder="Select a template..."
          @update:modelValue="onTemplateChange"
        />
      </div>

      <AppInput v-model="messageForm.subject" label="Subject" placeholder="Email subject..." />

      <div>
        <label class="text-sm font-medium text-foreground mb-1.5 block">Message</label>
        <textarea
          v-model="messageForm.body"
          rows="6"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/50 resize-none"
          placeholder="Write your message..."
        />
      </div>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="showSendMessage = false">Cancel</AppButton>
      <AppButton variant="primary" @click="sendMessage">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/>
          <path d="m21.854 2.147-10.94 10.939"/>
        </svg>
        Send Email
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Edit Owner modal ───────────────────────────────────────────── -->
  <AppModal :show="showEditOwner" size="md" @close="showEditOwner = false">
    <template #header>
      <h3 class="text-base font-bold font-body flex items-center gap-2 text-foreground">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
          <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
        </svg>
        Edit Owner Details
      </h3>
    </template>

    <div class="space-y-4">
      <p v-if="editOwnerError" class="text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-md px-3 py-2">{{ editOwnerError }}</p>

      <AppInput v-model="editOwnerForm.full_name" label="Full Name" placeholder="e.g. Michael Ndaba" required />
      <AppInput v-model="editOwnerForm.email" label="Email Address" type="email" placeholder="e.g. michael@email.com" required />
      <AppInput v-model="editOwnerForm.phone" label="Phone Number" placeholder="e.g. +27 82 000 0000" />
      <AppInput v-model="editOwnerForm.id_number" label="ID / Passport Number" placeholder="e.g. 8001015800080" />
      <AppInput v-model="editOwnerForm.address" label="Address" placeholder="e.g. 12 Main Street, Johannesburg" />
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="editOwnerSaving" @click="showEditOwner = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="editOwnerSaving" @click="saveEditOwner">
        <svg v-if="editOwnerSaving" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 animate-spin">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        {{ editOwnerSaving ? 'Saving...' : 'Save Changes' }}
      </AppButton>
    </template>
  </AppModal>

</div>
</template>
