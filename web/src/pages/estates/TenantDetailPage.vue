<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import AppButton from '@/components/common/AppButton.vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppInput         from '@/components/common/AppInput.vue'
import AppInvoiceSelect from '@/components/common/AppInvoiceSelect.vue'
import AppDatePicker    from '@/components/common/AppDatePicker.vue'
import AppBadge        from '@/components/common/AppBadge.vue'
import { useBack } from '@/composables/useBack.js'

const router = useRouter()
const route  = useRoute()

const estateId = computed(() => route.params.estateId)
const unitId   = computed(() => route.params.unitId)
const tenantId = computed(() => route.params.tenantId)

// ── State ─────────────────────────────────────────────────────────────
const tenant   = ref(null)
const invoices = ref([])
const payments = ref([])
const loading  = ref(true)
const error    = ref(null)

const chargeTypes       = ref([])
const showCreateInvoice = ref(false)
const invoiceForm       = ref({ chargeTypeId: '', billingPeriod: '', dueDate: '', amount: '' })
const submittingInvoice = ref(false)
const invoiceError      = ref(null)
const submittingPayment = ref(false)
const paymentError      = ref(null)

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  const num = Math.round(Number(amount))
  if (isNaN(num)) return '—'
  return `R\u00a0${num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')}`
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const [year, month, day] = dateStr.split('-')
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return `${parseInt(day, 10).toString().padStart(2, '0')} ${months[parseInt(month, 10) - 1]} ${year}`
}

function formatBillingPeriod(dateStr) {
  if (!dateStr) return '—'
  const [year, month] = dateStr.split('-')
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
  return `${months[parseInt(month, 10) - 1]} ${year}`
}

// ── Helpers matching UnitDetailPage exactly ───────────────────────────
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

const fmtPeriod = formatBillingPeriod

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

// ── API Fetching ──────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  error.value   = null

  try {
    // 1. Fetch the unit tenant record (loads unit.estate in the backend)
    const { data: tenantResp } = await api.get(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId.value}`
    )
    tenant.value = tenantResp.data

    // 2. Fetch invoices billed to this specific tenant
    const { data: invResp } = await api.get('/invoices', {
      params: {
        unit_id:        unitId.value,
        billed_to_type: 'tenant',
        billed_to_id:   tenantId.value,
        _per_page:      100,
      },
    })
    invoices.value = invResp.data ?? []

    // 3. Fetch all credit cashbook entries for this unit (allocated + unallocated)
    const { data: cbResp } = await api.get('/cashbook', {
      params: {
        unit_id:   unitId.value,
        type:      'credit',
        _per_page: 100,
      },
    })
    payments.value = cbResp.data ?? []

  } catch (err) {
    error.value = 'Failed to load tenant data. Please try again.'
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function fetchChargeTypes() {
  try {
    const { data } = await api.get('/charge-types', { params: { is_active: 1, _per_page: 100 } })
    chargeTypes.value = data.data ?? []
  } catch {
    // non-critical — invoice modal will show empty dropdown
  }
}

onMounted(() => { fetchData(); fetchChargeTypes() })

// ── Computed helpers ──────────────────────────────────────────────────
const isCurrent     = computed(() => tenant.value?.is_active === true)
const unitNumber    = computed(() => tenant.value?.unit?.unit_number ?? '—')
const estateName    = computed(() => tenant.value?.unit?.estate?.name ?? '—')
const estateRouteId = computed(() => tenant.value?.unit?.estate_id ?? estateId.value)

// Map invoice id → invoice_number for the payments table
const invoiceNumberMap = computed(() => {
  const map = {}
  for (const inv of invoices.value) {
    map[inv.id] = inv.invoice_number
  }
  return map
})

// Outstanding balance for past tenants (Lease Details card)
const outstandingBalance = computed(() => {
  if (isCurrent.value) return null
  const total = invoices.value
    .filter(inv => inv.status !== 'paid')
    .reduce((sum, inv) => sum + (Number(inv.outstanding) || Number(inv.amount) || 0), 0)
  return total > 0 ? total : null
})

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
const tenantOutstanding = computed(() =>
  invoices.value
    .filter(i => ['unpaid', 'overdue', 'partially_paid'].includes(i.status))
    .reduce((s, i) => s + Math.max(0, Number(i.amount ?? 0) - Number(i.total_paid ?? 0)), 0)
)
const tenantCreditOnAccount = computed(() =>
  payments.value
    .filter(p => !p.invoice_id)
    .reduce((s, p) => s + Number(p.amount ?? 0), 0)
)
const tenantBalance        = computed(() => tenantCreditOnAccount.value - tenantOutstanding.value)
const tenantNetOutstanding = computed(() => Math.max(0, tenantOutstanding.value - tenantCreditOnAccount.value))

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

// ── Status badge helpers ──────────────────────────────────────────────
function invoiceStatusBadge(status) {
  const map = {
    paid:           { label: 'Paid',    wrapClass: 'bg-success/10 text-success border-success/20'             },
    overdue:        { label: 'Overdue', wrapClass: 'bg-destructive/10 text-destructive border-destructive/20' },
    partially_paid: { label: 'Partial', wrapClass: 'bg-warning/10 text-warning border-warning/20'             },
    unpaid:         { label: 'Unpaid',  wrapClass: 'bg-muted text-muted-foreground border-border'             },
  }
  return map[status] ?? map.unpaid
}

// ── Navigation ────────────────────────────────────────────────────────
const { goBack } = useBack(computed(() => ({ name: 'unit-detail', params: { estateId: estateId.value, unitId: unitId.value } })))

function goToInvoice(invoiceId) {
  router.push({ name: 'invoice-detail', params: { invoiceId } })
}

function goToCashbookEntry(entryId) {
  router.push({ name: 'cashbook-entry', params: { entryId } })
}

function goToUnit() {
  const u = tenant.value?.unit
  if (!u) return
  router.push({ name: 'unit-detail', params: { estateId: u.estate_id, unitId: u.id } })
}

// ── Add Payment modal ─────────────────────────────────────────────────
const showAddPayment     = ref(false)
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
  paymentError.value       = null
  proofOfPaymentFile.value = null
  showAddPayment.value     = true
}

async function savePayment() {
  paymentError.value = null
  submittingPayment.value = true
  try {
    const fd = new FormData()
    fd.append('estate_id',   tenant.value?.unit?.estate_id ?? '')
    fd.append('unit_id',     unitId.value)
    fd.append('type',        'credit')
    fd.append('date',        paymentForm.value.date)
    fd.append('description', paymentForm.value.description)
    fd.append('amount',      String(parseFloat(paymentForm.value.amount)))
    if (paymentForm.value.invoiceId)  fd.append('invoice_id', paymentForm.value.invoiceId)
    if (proofOfPaymentFile.value)     fd.append('proof_of_payment', proofOfPaymentFile.value)
    await api.post('/cashbook', fd)
    showAddPayment.value     = false
    proofOfPaymentFile.value = null
    await fetchData()
  } catch (err) {
    paymentError.value = err?.response?.data?.message ?? 'Failed to record payment. Please try again.'
  } finally {
    submittingPayment.value = false
  }
}

// ── Create Invoice modal ──────────────────────────────────────────────
const tenantChargeTypeOptions = computed(() =>
  chargeTypes.value
    .filter(ct => ct.applies_to === 'tenant' || ct.applies_to === 'either')
    .map(ct => ({ value: ct.id, label: ct.name }))
)

function openCreateInvoice() {
  const today = new Date()
  invoiceForm.value = {
    chargeTypeId:  '',
    billingPeriod: `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`,
    dueDate:       '',
    amount:        '',
  }
  invoiceError.value = null
  showCreateInvoice.value = true
}

async function saveInvoice() {
  invoiceError.value = null
  submittingInvoice.value = true
  try {
    await api.post('/invoices', {
      unit_id:        unitId.value,
      charge_type_id: invoiceForm.value.chargeTypeId,
      billed_to_type: 'tenant',
      billed_to_id:   tenantId.value,
      amount:         invoiceForm.value.amount,
      billing_period: invoiceForm.value.billingPeriod + '-01',
      due_date:       invoiceForm.value.dueDate,
    })
    showCreateInvoice.value = false
    await fetchData()
  } catch (err) {
    invoiceError.value = err?.response?.data?.message ?? 'Failed to create invoice. Please try again.'
  } finally {
    submittingInvoice.value = false
  }
}

// ── Lease Document ────────────────────────────────────────────────────
const leaseDocUploading = ref(false)
const leaseDocDeleting  = ref(false)
const leaseDocError     = ref(null)
const leaseDocDragOver  = ref(false)

const ALLOWED_TYPES = ['application/pdf', 'image/jpeg', 'image/png']
const MAX_LEASE_SIZE = 5 * 1024 * 1024 // 5 MB

function validateLeaseFile(file) {
  if (!ALLOWED_TYPES.includes(file.type)) {
    leaseDocError.value = 'Only PDF, JPG, or PNG files are allowed.'
    return false
  }
  if (file.size > MAX_LEASE_SIZE) {
    leaseDocError.value = 'File must be 5 MB or smaller.'
    return false
  }
  return true
}

async function uploadLeaseFile(file) {
  leaseDocError.value     = null
  leaseDocUploading.value = true

  try {
    const formData = new FormData()
    formData.append('lease_document', file)

    const { data } = await api.post(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId.value}/lease-document`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    tenant.value = { ...tenant.value, lease_document_url: data.data.lease_document_url, lease_document_name: data.data.lease_document_name }
  } catch (err) {
    leaseDocError.value = err?.response?.data?.message ?? 'Upload failed. Please try again.'
  } finally {
    leaseDocUploading.value = false
  }
}

function onLeaseDocumentChange(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !validateLeaseFile(file)) return
  uploadLeaseFile(file)
}

function onLeaseDocumentDrop(event) {
  event.preventDefault()
  leaseDocDragOver.value = false
  if (leaseDocUploading.value) return
  const file = event.dataTransfer?.files?.[0]
  if (!file || !validateLeaseFile(file)) return
  uploadLeaseFile(file)
}

function onLeaseDocumentDragOver(event) {
  event.preventDefault()
  leaseDocDragOver.value = true
}

function onLeaseDocumentDragLeave() {
  leaseDocDragOver.value = false
}

const showDeleteLeaseModal = ref(false)

function confirmDeleteLeaseDocument() {
  showDeleteLeaseModal.value = true
}

async function doDeleteLeaseDocument() {
  showDeleteLeaseModal.value = false
  leaseDocError.value        = null
  leaseDocDeleting.value     = true

  try {
    await api.delete(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId.value}/lease-document`
    )
    tenant.value = { ...tenant.value, lease_document_url: null, lease_document_name: null }
  } catch (err) {
    leaseDocError.value = err?.response?.data?.message ?? 'Delete failed. Please try again.'
  } finally {
    leaseDocDeleting.value = false
  }
}

async function downloadLeaseDocument() {
  try {
    const response = await fetch(tenant.value.lease_document_url)
    const blob = await response.blob()
    const url  = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href     = url
    link.download = tenant.value.lease_document_name ?? 'lease-agreement'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch {
    // fallback: open directly if fetch fails (e.g. CORS restriction in dev)
    const link = document.createElement('a')
    link.href     = tenant.value.lease_document_url
    link.download = tenant.value.lease_document_name ?? 'lease-agreement'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }
}

function printLeaseDocument() {
  const iframe = document.createElement('iframe')
  iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;border:none;'
  iframe.src = tenant.value.lease_document_url
  document.body.appendChild(iframe)
  iframe.onload = () => {
    try {
      iframe.contentWindow.focus()
      iframe.contentWindow.print()
    } catch {
      // fallback: open in new window for printing
      const w = window.open(tenant.value.lease_document_url)
      w?.print()
    }
    setTimeout(() => {
      if (document.body.contains(iframe)) document.body.removeChild(iframe)
    }, 3000)
  }
}

// ── Send Message modal ────────────────────────────────────────────────
const showSendMessage = ref(false)
const messageForm     = ref({ template: '', subject: '', body: '' })

const TENANT_TEMPLATES = [
  { value: 'payment_reminder', label: 'Payment Reminder'     },
  { value: 'welcome',          label: 'Welcome Letter'       },
  { value: 'maintenance',      label: 'Maintenance Notice'   },
  { value: 'lease_renewal',    label: 'Lease Renewal Notice' },
  { value: 'statement',        label: 'Monthly Statement'    },
]

function onTemplateChange() {
  const name = tenant.value?.full_name ?? ''
  const tplMap = {
    payment_reminder: {
      subject: `Payment Reminder — ${name}`,
      body:    `Dear ${name},\n\nThis is a friendly reminder that your rent payment is due.\n\nKind regards,\nBold Mark Properties`,
    },
    welcome: {
      subject: `Welcome to Unit ${unitNumber.value}!`,
      body:    `Dear ${name},\n\nWelcome to your new home. We look forward to working with you.\n\nKind regards,\nBold Mark Properties`,
    },
    maintenance: {
      subject: 'Maintenance Notice',
      body:    `Dear ${name},\n\nWe would like to inform you of upcoming maintenance work at your property.\n\nKind regards,\nBold Mark Properties`,
    },
    lease_renewal: {
      subject: 'Lease Renewal Notice',
      body:    `Dear ${name},\n\nYour current lease is approaching its end date. Please contact us to discuss renewal options.\n\nKind regards,\nBold Mark Properties`,
    },
    statement: {
      subject: `Monthly Statement — ${name}`,
      body:    `Dear ${name},\n\nPlease find your monthly statement attached.\n\nKind regards,\nBold Mark Properties`,
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

// ── Edit Tenant modal ─────────────────────────────────────────────────
const showEditTenant  = ref(false)
const editTenantSaving = ref(false)
const editTenantError  = ref(null)
const editTenantForm   = ref({
  full_name:   '',
  email:       '',
  phone:       '',
  rent_amount: '',
  lease_start: '',
  lease_end:   '',
})

function openEditTenant() {
  editTenantError.value = null
  editTenantForm.value = {
    full_name:   tenant.value?.full_name   ?? '',
    email:       tenant.value?.email       ?? '',
    phone:       tenant.value?.phone       ?? '',
    rent_amount: tenant.value?.rent_amount ?? '',
    lease_start: tenant.value?.lease_start ?? '',
    lease_end:   tenant.value?.lease_end   ?? '',
  }
  showEditTenant.value = true
}

async function saveEditTenant() {
  editTenantError.value  = null
  editTenantSaving.value = true
  try {
    const { data } = await api.put(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId.value}`,
      editTenantForm.value,
    )
    tenant.value = { ...tenant.value, ...(data.data ?? data) }
    showEditTenant.value = false
  } catch (err) {
    editTenantError.value = err?.response?.data?.message ?? 'Failed to save changes. Please try again.'
  } finally {
    editTenantSaving.value = false
  }
}
</script>

<template>
  <!-- ── Loading state ────────────────────────────────────────────── -->
  <div v-if="loading" class="space-y-6 pb-8 animate-pulse">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-muted"></div>
      <div class="flex-1 space-y-2">
        <div class="h-7 w-48 rounded bg-muted"></div>
        <div class="h-4 w-32 rounded bg-muted"></div>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="space-y-4">
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-32 rounded bg-muted"></div>
          <div class="h-4 w-full rounded bg-muted"></div>
          <div class="h-4 w-3/4 rounded bg-muted"></div>
        </div>
        <div class="rounded-lg border bg-card p-6 space-y-3">
          <div class="h-5 w-32 rounded bg-muted"></div>
          <div class="h-4 w-full rounded bg-muted"></div>
          <div class="h-4 w-3/4 rounded bg-muted"></div>
          <div class="h-4 w-2/3 rounded bg-muted"></div>
        </div>
      </div>
      <div class="lg:col-span-2 space-y-4">
        <div class="rounded-lg border bg-card p-6 h-40"></div>
        <div class="rounded-lg border bg-card p-6 h-40"></div>
      </div>
    </div>
  </div>

  <!-- ── Error state ───────────────────────────────────────────────── -->
  <div v-else-if="error" class="flex flex-col items-center justify-center py-24 text-center">
    <p class="text-lg font-medium text-foreground">Something went wrong</p>
    <p class="text-sm text-muted-foreground mt-1">{{ error }}</p>
    <AppButton variant="outline" class="mt-4" @click="fetchData">Try Again</AppButton>
  </div>

  <!-- ── Found state ────────────────────────────────────────────────── -->
  <div v-else-if="tenant" class="space-y-6 pb-8">

    <!-- ── Header ────────────────────────────────────────────────── -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-muted transition-colors" @click="goBack">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
          <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
        </svg>
      </button>
      <div class="flex-1">
        <div class="flex items-center gap-3">
          <h1 class="font-body font-bold text-2xl text-foreground">{{ tenant.full_name }}</h1>
          <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1 leading-tight', isCurrent ? 'bg-success/10 text-success border-success/20' : 'bg-muted text-muted-foreground border-border']">
            {{ isCurrent ? 'Current Tenant' : 'Past Tenant' }}
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          Unit {{ unitNumber }} ·
          <button class="hover:underline text-primary" @click="router.push({ name: 'estate-detail', params: { id: estateRouteId } })">{{ estateName }}</button>
        </p>
      </div>
      <div class="flex gap-2">
        <AppButton variant="outline" @click="openEditTenant">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
          </svg>
          Edit Tenant
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

    <!-- ── Body grid ─────────────────────────────────────────────── -->
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
              <span class="text-foreground">{{ tenant.email }}</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground shrink-0">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
              <span class="text-foreground">{{ tenant.phone ?? '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Lease Details card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Lease Details</h3>
          </div>
          <div class="p-6 pt-0 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-muted-foreground">Lease Start</span>
              <span class="text-foreground font-medium">{{ formatDate(tenant.lease_start) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-muted-foreground">Lease End</span>
              <span class="text-foreground font-medium">{{ formatDate(tenant.lease_end) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-muted-foreground">Monthly Rent</span>
              <span class="text-foreground font-medium">{{ formatCurrency(tenant.rent_amount) }}</span>
            </div>
            <!-- Outstanding balance for past tenants -->
            <div v-if="!isCurrent && outstandingBalance" class="flex justify-between text-sm">
              <span class="text-muted-foreground">Outstanding Balance</span>
              <span class="text-destructive font-medium">{{ formatCurrency(outstandingBalance) }}</span>
            </div>
          </div>
        </div>

        <!-- Lease Document card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex items-center justify-between p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Lease Document</h3>
            <label v-if="tenant.lease_document_url" class="cursor-pointer">
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="onLeaseDocumentChange" :disabled="leaseDocUploading" />
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
                Replace
              </span>
            </label>
          </div>
          <div class="p-6 pt-0">

            <!-- No document: upload zone -->
            <div v-if="!tenant.lease_document_url">
              <label class="block cursor-pointer">
                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="onLeaseDocumentChange" :disabled="leaseDocUploading" />
                <div
                  class="flex flex-col items-center justify-center gap-2 py-6 border-2 border-dashed rounded-lg text-center transition-colors"
                  :class="[
                    leaseDocUploading ? 'opacity-50 pointer-events-none border-border' : 'cursor-pointer',
                    leaseDocDragOver ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40 hover:bg-muted/30'
                  ]"
                  @dragover="onLeaseDocumentDragOver"
                  @dragleave="onLeaseDocumentDragLeave"
                  @drop="onLeaseDocumentDrop"
                >
                  <svg v-if="!leaseDocUploading" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 text-muted-foreground opacity-60">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                    <path d="M12 12v6"/><path d="m15 15-3-3-3 3"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground animate-spin">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                  </svg>
                  <div>
                    <p class="text-xs font-medium text-foreground">{{ leaseDocUploading ? 'Uploading...' : 'Drop lease PDF here or click to browse' }}</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">PDF, JPG, PNG · max 5 MB</p>
                  </div>
                </div>
              </label>
              <p v-if="leaseDocError" class="text-xs text-destructive mt-2">{{ leaseDocError }}</p>
            </div>

            <!-- Document exists: show info + actions -->
            <div v-else class="space-y-3">
              <button
                type="button"
                class="w-full flex items-start gap-3 p-3 rounded-lg bg-muted/40 border border-border hover:bg-muted/70 hover:border-primary/30 transition-colors cursor-pointer text-left"
                @click="downloadLeaseDocument"
              >
                <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-primary">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium text-foreground truncate">{{ tenant.lease_document_name ?? 'Lease Agreement' }}</p>
                  <p class="text-[11px] text-muted-foreground mt-0.5">Lease document</p>
                </div>
              </button>
              <div class="flex gap-2">
                <!-- Download — same-page download, no new tab -->
                <button
                  class="flex-1 inline-flex items-center justify-center gap-1.5 rounded border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors"
                  @click="downloadLeaseDocument"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                  </svg>
                  Download
                </button>
                <!-- Print -->
                <button
                  class="flex-1 inline-flex items-center justify-center gap-1.5 rounded border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors"
                  @click="printLeaseDocument"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>
                  </svg>
                  Print
                </button>
                <!-- Delete — opens branded confirm modal -->
                <button
                  class="inline-flex items-center justify-center rounded border border-destructive/30 bg-background px-3 py-1.5 text-xs font-medium text-destructive hover:bg-destructive/5 transition-colors disabled:opacity-50"
                  :disabled="leaseDocDeleting"
                  @click="confirmDeleteLeaseDocument"
                >
                  <svg v-if="!leaseDocDeleting" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 animate-spin">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                  </svg>
                </button>
              </div>
              <p v-if="leaseDocError" class="text-xs text-destructive">{{ leaseDocError }}</p>
            </div>

          </div>
        </div>

        <!-- Units Leased card -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <h3 class="tracking-tight font-body font-semibold text-lg">Units Leased</h3>
          </div>
          <div class="p-6 pt-0 space-y-2">
            <div
              v-if="tenant?.unit"
              class="flex items-center justify-between p-3 rounded-lg border border-border hover:bg-muted/30 cursor-pointer transition-colors"
              @click="goToUnit()"
            >
              <div>
                <p class="text-sm font-semibold text-foreground">Unit {{ tenant.unit.unit_number }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">{{ tenant.unit.estate?.name ?? '—' }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                  {{ tenant.rent_amount ? 'Rent  ' + fmtAmount(tenant.rent_amount) + ' / month' : '—' }}
                </p>
              </div>
              <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border leading-tight', isCurrent ? 'bg-success/10 text-success border-success/20' : 'bg-muted text-muted-foreground border-border']">
                {{ isCurrent ? 'Current' : 'Past' }}
              </span>
            </div>
            <p v-else class="text-sm text-muted-foreground py-2">No unit linked</p>
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
                <p :class="['text-3xl font-bold font-body', tenantBalance < 0 ? 'text-destructive' : 'text-foreground']">
                  {{ fmtAmount(tenantBalance) }}
                </p>
              </div>
              <div v-if="tenantBalance < 0" class="px-3 py-1 rounded-full bg-destructive/10 text-destructive text-xs font-medium">
                In Arrears
              </div>
            </div>
          </div>
        </div>

        <!-- Invoices section -->
        <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
          <div class="flex flex-col space-y-1.5 p-6 pb-3">
            <div class="flex items-center justify-between">
              <h3 class="tracking-tight font-body font-semibold text-lg">Invoices</h3>
              <AppButton variant="outline" size="sm" @click="openCreateInvoice">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                Create Invoice
              </AppButton>
            </div>
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
                  <td class="py-3 px-2 text-foreground">{{ inv.billed_to_unit_tenant?.full_name ?? tenant.full_name }}</td>
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
              <template v-if="tenantCreditOnAccount > 0">
                <div class="w-px h-3.5 bg-border mr-4" />
                <div class="flex items-center gap-1.5 pr-4">
                  <span class="text-muted-foreground text-xs">Credit</span>
                  <span class="font-semibold text-success font-body">{{ fmtAmount(tenantCreditOnAccount) }}</span>
                </div>
              </template>
              <div class="w-px h-3.5 bg-border mr-4" />
              <div class="flex items-center gap-1.5 pr-4">
                <span class="text-muted-foreground text-xs">Outstanding</span>
                <span :class="['font-semibold font-body', tenantNetOutstanding > 0 ? 'text-destructive' : 'text-foreground']">{{ fmtAmount(tenantNetOutstanding) }}</span>
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

            <table v-if="filteredPayments.length > 0" class="w-full text-sm">
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
                  v-for="pay in pagedPayments"
                  :key="pay.id"
                  class="border-b border-border hover:bg-muted/50 transition-colors cursor-pointer"
                  @click="goToCashbookEntry(pay.id)"
                >
                  <td class="py-3 px-2 text-foreground">{{ formatDate(pay.date) }}</td>
                  <td class="py-3 px-2 text-foreground">{{ pay.description }}</td>
                  <td class="py-3 px-2 text-right font-medium text-success">{{ fmtPaymentAmount(pay.amount) }}</td>
                  <td class="py-3 px-2 text-foreground font-mono text-xs">{{ pay.invoice?.invoice_number ?? '—' }}</td>
                  <td class="py-3 px-2">
                    <AppBadge v-if="pay.is_allocated" variant="success" bordered size="sm">Allocated</AppBadge>
                    <AppBadge v-else variant="warning" bordered size="sm">Unallocated</AppBadge>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-muted-foreground text-center py-6">
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

  <!-- ── Not found state ──────────────────────────────────────────── -->
  <div v-else class="flex flex-col items-center justify-center py-24 text-center">
    <p class="text-lg font-medium text-foreground">Tenant not found</p>
    <p class="text-sm text-muted-foreground mt-1">This tenant record may have been removed or does not exist.</p>
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
      <div v-if="tenant" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ tenant.full_name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">{{ tenant.full_name }}</p>
          <p class="text-xs text-muted-foreground">Tenant · Unit {{ unitNumber }}</p>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-foreground mb-1.5">Payment Date</label>
        <AppDatePicker v-model="paymentForm.date" placeholder="Select date..." />
      </div>
      <AppInput v-model="paymentForm.description" label="Description" placeholder="e.g. EFT – R NAIDOO RENT APR" />
      <AppInput v-model="paymentForm.amount" label="Amount (R)" type="number" placeholder="0.00" />
      <div>
        <label class="text-sm font-medium text-foreground mb-1.5 block">Allocate to Invoice</label>
        <AppInvoiceSelect v-model="paymentForm.invoiceId" :unit-id="unitId" placeholder="Select invoice..." />
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

      <p v-if="paymentError" class="text-xs text-destructive">{{ paymentError }}</p>
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="submittingPayment" @click="showAddPayment = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="submittingPayment" @click="savePayment">
        <svg v-if="submittingPayment" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 animate-spin">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        {{ submittingPayment ? 'Recording...' : 'Record Payment' }}
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
      <div v-if="tenant" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ tenant.full_name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">{{ tenant.full_name }}</p>
          <p class="text-xs text-muted-foreground">{{ tenant.email }} · Tenant</p>
        </div>
      </div>
      <div>
        <label class="text-sm font-medium text-foreground mb-1.5 block">Template</label>
        <AppSelect v-model="messageForm.template" :options="TENANT_TEMPLATES" placeholder="Select a template..." @change="onTemplateChange" />
      </div>
      <AppInput v-model="messageForm.subject" label="Subject" placeholder="Email subject" />
      <AppInput v-model="messageForm.body" type="textarea" label="Message" :rows="8" placeholder="Type your message..." />
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

  <!-- ── Create Invoice modal ─────────────────────────────────────── -->
  <AppModal :show="showCreateInvoice" size="md" @close="showCreateInvoice = false">
    <template #header>
      <h3 class="text-base font-bold font-body flex items-center gap-2 text-foreground">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
          <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
          <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
        </svg>
        Create Invoice
      </h3>
    </template>

    <div class="space-y-4">
      <!-- Recipient pill -->
      <div v-if="tenant" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ tenant.full_name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-foreground">{{ tenant.full_name }}</p>
          <p class="text-xs text-muted-foreground">Tenant · Unit {{ unitNumber }} · {{ estateName }}</p>
        </div>
        <span class="inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border bg-blue-50 text-blue-700 border-blue-200">Tenant</span>
      </div>

      <!-- Charge Type -->
      <AppSelect v-model="invoiceForm.chargeTypeId" label="Charge Type" :options="tenantChargeTypeOptions" placeholder="Select charge type..." required />

      <!-- Billing Period -->
      <AppDatePicker v-model="invoiceForm.billingPeriod" label="Billing Period" mode="month" placeholder="Select month..." required />

      <!-- Amount + Due Date side by side -->
      <div class="grid grid-cols-2 gap-4">
        <AppInput v-model="invoiceForm.amount" label="Amount (R)" type="number" placeholder="0.00" required />
        <AppDatePicker v-model="invoiceForm.dueDate" label="Due Date" placeholder="Select due date..." required />
      </div>

      <p v-if="invoiceError" class="text-xs text-destructive">{{ invoiceError }}</p>
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="submittingInvoice" @click="showCreateInvoice = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="submittingInvoice" @click="saveInvoice">
        <svg v-if="submittingInvoice" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 animate-spin">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        {{ submittingInvoice ? 'Creating...' : 'Create Invoice' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Edit Tenant modal ──────────────────────────────────────────── -->
  <AppModal :show="showEditTenant" size="md" @close="showEditTenant = false">
    <template #header>
      <h3 class="text-base font-bold font-body flex items-center gap-2 text-foreground">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
          <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
        </svg>
        Edit Tenant Details
      </h3>
    </template>

    <div class="space-y-4">
      <p v-if="editTenantError" class="text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-md px-3 py-2">{{ editTenantError }}</p>

      <AppInput v-model="editTenantForm.full_name" label="Full Name" placeholder="e.g. Lisa Mokoena" required />
      <AppInput v-model="editTenantForm.email" label="Email Address" type="email" placeholder="e.g. lisa@email.com" required />
      <AppInput v-model="editTenantForm.phone" label="Phone Number" placeholder="e.g. +267 72 000 0000" />

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-foreground mb-1.5">Lease Start</label>
          <AppDatePicker v-model="editTenantForm.lease_start" placeholder="Select date..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-foreground mb-1.5">Lease End</label>
          <AppDatePicker v-model="editTenantForm.lease_end" placeholder="Select date..." />
        </div>
      </div>

      <AppInput v-model="editTenantForm.rent_amount" label="Monthly Rent" type="number" placeholder="0.00" prefix="R" />
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="editTenantSaving" @click="showEditTenant = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="editTenantSaving" @click="saveEditTenant">
        <svg v-if="editTenantSaving" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 animate-spin">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        {{ editTenantSaving ? 'Saving...' : 'Save Changes' }}
      </AppButton>
    </template>
  </AppModal>
  <!-- ── Delete Lease Document confirm modal ──────────────────────── -->
  <AppModal :show="showDeleteLeaseModal" title="Remove Lease Document" size="sm" @close="showDeleteLeaseModal = false">
    <div class="space-y-1">
      <p class="text-sm text-foreground">Are you sure you want to remove the lease document?</p>
      <p class="text-xs text-muted-foreground">This action cannot be undone.</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showDeleteLeaseModal = false">Cancel</AppButton>
      <AppButton variant="danger" @click="doDeleteLeaseDocument">Remove Document</AppButton>
    </template>
  </AppModal>

</template>
