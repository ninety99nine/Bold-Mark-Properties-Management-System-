<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton     from '@/components/common/AppButton.vue'
import AppBadge      from '@/components/common/AppBadge.vue'
import AppCard       from '@/components/common/AppCard.vue'
import AppModal      from '@/components/common/AppModal.vue'
import AppInput      from '@/components/common/AppInput.vue'
import AppSelect     from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import api from '@/composables/useApi'
import { useBack } from '@/composables/useBack.js'

const route  = useRoute()
const router = useRouter()
const { goBack } = useBack('/billing')

const invoice       = ref(null)
const loading       = ref(true)
const error         = ref(null)
const resending     = ref(false)
const downloading   = ref(false)
const resendSuccess = ref(false)

// ── Quick Status Change ────────────────────────────────────────────────────
const showStatusMenu  = ref(false)
const statusChanging  = ref(false)

const STATUS_OPTIONS = [
  { value: 'unpaid',         label: 'Unpaid',          dot: 'bg-muted-foreground/60' },
  { value: 'paid',           label: 'Paid',             dot: 'bg-success' },
  { value: 'overdue',        label: 'Overdue',          dot: 'bg-destructive' },
  { value: 'partially_paid', label: 'Partial Payment',  dot: 'bg-[#D89B4B]' },
]

async function changeStatus(newStatus) {
  if (statusChanging.value || invoice.value?.status === newStatus) {
    showStatusMenu.value = false
    return
  }
  statusChanging.value = true
  try {
    await api.put(`/invoices/${route.params.invoiceId}`, { status: newStatus })
    await fetchInvoice()
  } catch {
    // silent
  } finally {
    statusChanging.value  = false
    showStatusMenu.value  = false
  }
}

// ── Edit Invoice modal (manual invoices only) ──────────────────────────────
const showEditModal  = ref(false)
const editSaving     = ref(false)
const editError      = ref(null)
const editForm       = ref({ amount: '', due_date: '', billing_period: '' })

function openEditModal() {
  if (!invoice.value) return
  editForm.value = {
    amount:         String(invoice.value.amount ?? ''),
    due_date:       invoice.value.due_date ?? '',
    billing_period: invoice.value.billing_period?.slice(0, 7) ?? '',
  }
  editError.value    = null
  showEditModal.value = true
}

async function submitEdit() {
  if (!invoice.value) return
  editSaving.value = true
  editError.value  = null
  try {
    const payload = {
      amount:   parseFloat(editForm.value.amount),
      due_date: editForm.value.due_date,
    }
    if (editForm.value.billing_period) {
      payload.billing_period = editForm.value.billing_period + '-01'
    }
    await api.put(`/invoices/${route.params.invoiceId}`, payload)
    showEditModal.value = false
    await fetchInvoice()
  } catch (err) {
    editError.value = err.response?.data?.message ?? 'Failed to save changes. Please try again.'
  } finally {
    editSaving.value = false
  }
}

// ── Delete Invoice ─────────────────────────────────────────────────────────
const showDeleteConfirm = ref(false)
const deleteConfirming  = ref(false)
const deleteError       = ref(null)

// ── Restore / Force-delete (trash actions) ────────────────────────────────
const restoring              = ref(false)
const showForceDeleteConfirm = ref(false)
const forceDeleteConfirming  = ref(false)
const forceDeleteError       = ref(null)

async function restoreInvoice() {
  restoring.value = true
  try {
    await api.post(`/invoices/${route.params.invoiceId}/restore`)
    await fetchInvoice()   // reload — deleted_at will now be null, banners hide
  } catch {
    /* ignore — unlikely to fail */
  } finally {
    restoring.value = false
  }
}

async function confirmForceDelete() {
  forceDeleteConfirming.value = true
  forceDeleteError.value      = null
  try {
    await api.delete(`/invoices/${route.params.invoiceId}/force-delete`)
    router.push('/billing?tab=trash')
  } catch (err) {
    forceDeleteError.value = err.response?.data?.message ?? 'Failed to permanently delete invoice.'
    forceDeleteConfirming.value = false
  }
}

async function confirmDelete() {
  deleteConfirming.value = true
  deleteError.value      = null
  try {
    await api.delete(`/invoices/${route.params.invoiceId}`)
    router.push('/billing?tab=trash')
  } catch (err) {
    deleteError.value = err.response?.data?.message ?? 'Failed to delete invoice. Please try again.'
    deleteConfirming.value = false
  }
}

// ── Fetch ─────────────────────────────────────────────────────────────────
async function fetchInvoice() {
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get(`/invoices/${route.params.invoiceId}`)
    invoice.value = data.data
  } catch (err) {
    error.value = err.response?.status === 404
      ? 'Invoice not found.'
      : 'Failed to load invoice. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchInvoice)

// ── Derived values ────────────────────────────────────────────────────────
const billedTo = computed(() => {
  if (!invoice.value) return null
  return invoice.value.billed_to_type === 'owner'
    ? invoice.value.billed_to_owner
    : invoice.value.billed_to_unit_tenant
})

const statusVariant = computed(() => {
  const map = { paid: 'success', overdue: 'danger', partially_paid: 'warning', unpaid: 'default' }
  return map[invoice.value?.status] || 'default'
})

const statusLabel = computed(() => {
  const map = { paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', unpaid: 'Unpaid' }
  return map[invoice.value?.status] || invoice.value?.status
})

// ── Email tracking helpers ─────────────────────────────────────────────────

// Group all events by resend_email_id — one group per send attempt
const sendGroups = computed(() => {
  const events = invoice.value?.email_events ?? []
  const map = new Map()
  for (const e of events) {
    const key = e.resend_email_id ?? String(e.id)
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(e)
  }
  return [...map.values()]
    .map(evts => {
      const sentEvt = evts.find(e => e.event_type === 'sent')
      return { email: sentEvt?.email ?? null, sentAt: sentEvt?.occurred_at ?? null, events: evts }
    })
    .filter(g => g.email)
    .sort((a, b) => new Date(b.sentAt) - new Date(a.sentAt)) // most recent first
})

// Unique recipient emails across all sends, most recent first
const uniqueSentEmails = computed(() => {
  const seen = new Set()
  const result = []
  for (const g of sendGroups.value) {
    if (g.email && !seen.has(g.email)) { seen.add(g.email); result.push(g.email) }
  }
  return result
})

// Whether this invoice has ever been sent to anyone (used for button label)
const hasEverBeenSent = computed(() => uniqueSentEmails.value.length > 0)

// Selected email for the tracking view — defaults to most recently sent
const selectedTrackingEmail = ref(null)
watch(uniqueSentEmails, (emails) => {
  if (emails.length > 0 && !emails.includes(selectedTrackingEmail.value)) {
    selectedTrackingEmail.value = emails[0]
  }
}, { immediate: true })

// Most recent send group for the currently selected email
const selectedSendGroup = computed(() =>
  sendGroups.value.find(g => g.email === selectedTrackingEmail.value) ?? null
)

function getEmailEvent(type) {
  const group = selectedSendGroup.value
  if (group) return group.events.find(e => e.event_type === type) ?? null
  return invoice.value?.email_events?.find(e => e.event_type === type) ?? null
}

const sentEvent      = computed(() => getEmailEvent('sent'))
const deliveredEvent = computed(() => getEmailEvent('delivered'))
const openedEvent    = computed(() => getEmailEvent('opened'))

const timeToOpen = computed(() => {
  if (!deliveredEvent.value || !openedEvent.value) return null
  const diffMs = new Date(openedEvent.value.occurred_at) - new Date(deliveredEvent.value.occurred_at)
  if (diffMs <= 0) return null
  const totalSeconds = Math.floor(diffMs / 1000)
  const days    = Math.floor(totalSeconds / 86400)
  const hours   = Math.floor((totalSeconds % 86400) / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60
  if (days > 0)    return `${days}d ${hours}h`
  if (hours > 0)   return `${hours}h ${minutes}m`
  if (minutes > 0) return `${minutes}m ${seconds}s`
  return `${seconds}s`
})

// ── Formatting ────────────────────────────────────────────────────────────
function formatCurrency(amount) {
  const parts = Math.abs(amount ?? 0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
  return `R\u00a0${parts}`
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateLong(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'long', year: 'numeric' })
}

function formatPeriod(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })
}

function formatDateTime(datetimeStr) {
  if (!datetimeStr) return null
  const d = new Date(datetimeStr)
  const date = d.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
  const time = d.toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit', hour12: true })
  return `${date}, ${time}`
}

// ── Actions ───────────────────────────────────────────────────────────────
async function resendInvoice() {
  if (resending.value) return
  resending.value  = true
  resendSuccess.value = false
  try {
    await api.post(`/invoices/${route.params.invoiceId}/resend`)
    resendSuccess.value = true
    await fetchInvoice()
    setTimeout(() => { resendSuccess.value = false }, 3000)
  } catch {
    // silent — future: toast notification
  } finally {
    resending.value = false
  }
}

async function downloadPdf() {
  if (downloading.value) return
  downloading.value = true
  try {
    const response = await api.get(`/invoices/${route.params.invoiceId}/download-pdf`, {
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href     = url
    link.download = `${invoice.value.invoice_number}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch {
    // silent — future: toast notification
  } finally {
    downloading.value = false
  }
}

function printInvoice() {
  if (!invoice.value) return
  const inv = invoice.value
  const to  = billedTo.value

  const lineItems = (inv.line_items?.length ? inv.line_items : [{
    description: `${inv.charge_type?.name ?? 'Charge'} — Unit ${inv.unit?.unit_number}`,
    period:      formatPeriod(inv.billing_period),
    amount:      inv.amount,
  }])

  const rows = lineItems.map(li => `
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;">
        <div style="font-weight:600;color:#111;">${li.description}</div>
        ${li.period ? `<div style="font-size:12px;color:#6b7280;margin-top:2px;">${li.period}</div>` : ''}
      </td>
      <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #e5e7eb;font-weight:600;color:#111;">${formatCurrency(li.amount)}</td>
    </tr>`).join('')

  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${inv.invoice_number}</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; color: #1e2740; background: #fff; padding: 40px; max-width: 800px; margin: 0 auto; }
    @media print {
      body { padding: 20px; }
      @page { margin: 10mm; size: A4; }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <table width="100%" style="margin-bottom:32px;">
    <tr>
      <td>
        <div style="font-family:'DM Serif Display',serif;font-size:26px;font-weight:700;color:#1F3A5C;letter-spacing:2px;">BOLD MARK</div>
        <div style="font-size:12px;font-weight:600;color:#D89B4B;letter-spacing:3px;margin-top:2px;">PROPERTIES</div>
        <div style="font-size:11px;color:#717b99;margin-top:6px;">Property Management Services</div>
      </td>
      <td style="text-align:right;vertical-align:top;">
        <div style="font-size:24px;font-weight:700;color:#1F3A5C;">${inv.invoice_number}</div>
        <div style="font-size:11px;font-weight:600;letter-spacing:2px;color:#717b99;margin-top:4px;">TAX INVOICE</div>
      </td>
    </tr>
  </table>

  <hr style="border:none;border-top:2px solid #1F3A5C;margin-bottom:32px;">

  <!-- Bill To + Dates -->
  <table width="100%" style="margin-bottom:32px;">
    <tr>
      <td style="vertical-align:top;width:55%;">
        <div style="font-size:10px;font-weight:600;letter-spacing:2px;color:#717b99;margin-bottom:8px;">BILL TO</div>
        <div style="font-size:16px;font-weight:700;color:#1e2740;">${to?.full_name ?? '—'}</div>
        <div style="font-size:12px;color:#717b99;margin-top:4px;">${inv.billed_to_type === 'owner' ? 'Owner' : 'Tenant'}</div>
        <div style="font-size:12px;color:#717b99;margin-top:2px;">${to?.email ?? ''}</div>
      </td>
      <td style="vertical-align:top;text-align:right;">
        <table style="margin-left:auto;">
          <tr><td style="font-size:12px;color:#717b99;padding:3px 0;padding-right:16px;">Invoice Date</td><td style="font-size:12px;font-weight:600;text-align:right;">${formatDateLong(inv.invoice_date ?? inv.created_at?.split('T')[0])}</td></tr>
          <tr><td style="font-size:12px;color:#717b99;padding:3px 0;padding-right:16px;">Due Date</td><td style="font-size:12px;font-weight:600;text-align:right;">${formatDateLong(inv.due_date)}</td></tr>
          <tr><td style="font-size:12px;color:#717b99;padding:3px 0;padding-right:16px;">Period</td><td style="font-size:12px;font-weight:600;text-align:right;">${formatPeriod(inv.billing_period)}</td></tr>
          <tr><td style="font-size:12px;color:#717b99;padding:3px 0;padding-right:16px;">Estate</td><td style="font-size:12px;font-weight:600;text-align:right;">${inv.unit?.estate?.name ?? '—'}</td></tr>
          <tr><td style="font-size:12px;color:#717b99;padding:3px 0;padding-right:16px;">Unit</td><td style="font-size:12px;font-weight:600;text-align:right;">${inv.unit?.unit_number ?? '—'}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Line Items -->
  <table width="100%" style="border-collapse:collapse;margin-bottom:0;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;letter-spacing:1px;color:#717b99;">DESCRIPTION</th>
        <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:600;letter-spacing:1px;color:#717b99;">AMOUNT</th>
      </tr>
    </thead>
    <tbody>${rows}</tbody>
    <tfoot>
      <tr style="background:#f9fafb;">
        <td style="padding:14px 16px;font-weight:700;font-size:14px;color:#1e2740;">Total Due</td>
        <td style="padding:14px 16px;text-align:right;font-weight:700;font-size:14px;color:#1e2740;">${formatCurrency(inv.amount)}</td>
      </tr>
    </tfoot>
  </table>

  <!-- Payment Instructions -->
  <div style="margin-top:32px;padding:16px;background:#f9fafb;border-radius:6px;font-size:12px;color:#717b99;line-height:1.6;">
    <strong style="color:#1e2740;">Payment Instructions</strong><br>
    Please use your invoice number <strong style="color:#1e2740;">${inv.invoice_number}</strong> as the payment reference when making your EFT payment.
    Payment is due by <strong style="color:#1e2740;">${formatDateLong(inv.due_date)}</strong>.
  </div>
</body>
</html>`

  const win = window.open('', '_blank', 'width=900,height=700')
  if (!win) return
  win.document.write(html)
  win.document.close()
  // Wait for fonts/layout to settle before printing
  win.onload = () => { win.focus(); win.print() }
  // Fallback if onload already fired
  setTimeout(() => { try { win.focus(); win.print() } catch {} }, 800)
}

// ── Add Payment modal ─────────────────────────────────────────────────
const showAddPayment      = ref(false)
const addPaymentSaving    = ref(false)
const addPaymentError     = ref(null)
const addPaymentForm      = ref({ date: '', description: '', amount: '', notes: '' })
const proofOfPaymentFile  = ref(null)
const proofInput          = ref(null)
const paymentsHintInput   = ref(null)

// Invoice selector: outstanding invoices for this unit
const unitInvoices        = ref([])
const loadingInvoices     = ref(false)
const selectedInvoiceId   = ref(null)

const invoiceOptions = computed(() => {
  const outstanding = unitInvoices.value.filter(inv =>
    ['unpaid', 'overdue', 'partially_paid'].includes(inv.status)
  )
  const opts = outstanding.map(inv => ({
    value: inv.id,
    label: `${inv.invoice_number} — ${inv.charge_type?.name ?? 'Charge'} (${formatCurrency(inv.outstanding ?? inv.amount)} outstanding)`,
  }))
  return [{ value: '__none__', label: 'None — record as unallocated' }, ...opts]
})

async function loadUnitInvoices() {
  if (!invoice.value?.unit?.id) return
  loadingInvoices.value = true
  try {
    const { data } = await api.get('/invoices', {
      params: { unit_id: invoice.value.unit.id, per_page: 100 },
    })
    unitInvoices.value = data.data ?? []
  } catch {
    unitInvoices.value = []
  } finally {
    loadingInvoices.value = false
  }
}

// Auto-fill description + amount when the selected invoice changes
watch(selectedInvoiceId, (newId) => {
  if (!newId || newId === '__none__') {
    addPaymentForm.value.description = ''
    addPaymentForm.value.amount      = ''
    return
  }
  // Check current invoice first (always available)
  if (newId === invoice.value?.id) {
    addPaymentForm.value.description = `Payment — ${invoice.value.invoice_number}`
    addPaymentForm.value.amount      = invoice.value.outstanding > 0 ? String(invoice.value.outstanding) : ''
    return
  }
  const found = unitInvoices.value.find(inv => inv.id === newId)
  if (found) {
    addPaymentForm.value.description = `Payment — ${found.invoice_number}`
    addPaymentForm.value.amount      = (found.outstanding ?? found.amount) > 0
      ? String(found.outstanding ?? found.amount)
      : ''
  }
})

function openAddPayment(droppedFile = null) {
  const today = new Date().toISOString().split('T')[0]
  addPaymentForm.value = { date: today, description: '', amount: '', notes: '' }
  addPaymentError.value    = null
  proofOfPaymentFile.value = droppedFile ?? null
  // Pre-select current invoice only if it still has an outstanding balance
  const isOutstanding = invoice.value && ['unpaid', 'overdue', 'partially_paid'].includes(invoice.value.status)
  selectedInvoiceId.value  = isOutstanding ? (invoice.value.id ?? '__none__') : '__none__'
  showAddPayment.value     = true
  loadUnitInvoices()
}

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

async function submitAddPayment() {
  if (!invoice.value) return
  addPaymentSaving.value = true
  addPaymentError.value  = null
  try {
    const fd = new FormData()
    fd.append('estate_id',   invoice.value.unit?.estate_id ?? '')
    fd.append('unit_id',     invoice.value.unit?.id ?? '')
    fd.append('type',        'credit')
    fd.append('date',        addPaymentForm.value.date)
    fd.append('description', addPaymentForm.value.description)
    fd.append('amount',      String(parseFloat(addPaymentForm.value.amount)))
    if (selectedInvoiceId.value && selectedInvoiceId.value !== '__none__') {
      fd.append('invoice_id', selectedInvoiceId.value)
    }
    if (addPaymentForm.value.notes)   fd.append('notes', addPaymentForm.value.notes)
    if (proofOfPaymentFile.value)     fd.append('proof_of_payment', proofOfPaymentFile.value)
    await api.post('/cashbook', fd)
    showAddPayment.value     = false
    proofOfPaymentFile.value = null
    await fetchInvoice()
  } catch (err) {
    addPaymentError.value = err.response?.data?.message ?? 'Failed to record payment. Please try again.'
  } finally {
    addPaymentSaving.value = false
  }
}

// ── Drag-and-drop on Payment History section ──────────────────────────
const isDraggingOverPayments = ref(false)

function onPaymentsDragOver() {
  isDraggingOverPayments.value = true
}

function onPaymentsDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isDraggingOverPayments.value = false
  }
}

function onPaymentsDrop(e) {
  isDraggingOverPayments.value = false
  const file = e.dataTransfer?.files?.[0]
  if (!file) return
  openAddPayment(file)
}

// ── Remove Payment modal ───────────────────────────────────────────────
const showRemovePayment       = ref(false)
const removePaymentEntry      = ref(null)
const removePaymentReason     = ref('')
const removePaymentSaving     = ref(false)
const removePaymentError      = ref(null)

function openRemovePayment(entry) {
  removePaymentEntry.value  = entry
  removePaymentReason.value = ''
  removePaymentError.value  = null
  showRemovePayment.value   = true
}

async function submitRemovePayment() {
  if (!removePaymentEntry.value) return
  removePaymentSaving.value = true
  removePaymentError.value  = null
  try {
    await api.post(`/cashbook/${removePaymentEntry.value.id}/deallocate`, {
      reason: removePaymentReason.value,
    })
    showRemovePayment.value = false
    await fetchInvoice()
  } catch (err) {
    removePaymentError.value = err.response?.data?.message ?? 'Failed to remove payment. Please try again.'
  } finally {
    removePaymentSaving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Loading skeleton ────────────────────────────────────────────── -->
    <template v-if="loading">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-muted animate-pulse" />
        <div class="flex-1 space-y-2">
          <div class="h-7 w-48 rounded bg-muted animate-pulse" />
          <div class="h-4 w-64 rounded bg-muted animate-pulse" />
        </div>
        <div class="flex gap-2">
          <div class="h-9 w-20 rounded bg-muted animate-pulse" />
          <div class="h-9 w-32 rounded bg-muted animate-pulse" />
          <div class="h-9 w-36 rounded bg-muted animate-pulse" />
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
          <div class="h-64 rounded-lg bg-muted animate-pulse" />
          <div class="h-32 rounded-lg bg-muted animate-pulse" />
        </div>
        <div class="space-y-4">
          <div class="h-40 rounded-lg bg-muted animate-pulse" />
          <div class="h-32 rounded-lg bg-muted animate-pulse" />
          <div class="h-44 rounded-lg bg-muted animate-pulse" />
        </div>
      </div>
    </template>

    <!-- ── Error state ─────────────────────────────────────────────────── -->
    <template v-else-if="error">
      <div class="flex items-center gap-3 mb-4">
        <AppButton variant="ghost" square size="md" @click="goBack()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="text-muted-foreground">
            <path d="m12 19-7-7 7-7" /><path d="M19 12H5" />
          </svg>
        </AppButton>
      </div>
      <AppCard>
        <p class="text-center text-muted-foreground py-8">{{ error }}</p>
      </AppCard>
    </template>

    <!-- ── Invoice content ─────────────────────────────────────────────── -->
    <template v-else-if="invoice">

      <!-- Deleted banner -->
      <div v-if="invoice.deleted_at" class="flex items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
        <span>This invoice is in the <strong>Trash</strong> — deleted on {{ new Date(invoice.deleted_at).toLocaleDateString('en-ZA', { day: '2-digit', month: 'long', year: 'numeric' }) }}. Use the <strong>Restore</strong> or <strong>Delete Forever</strong> buttons below to take action.</span>
      </div>

      <!-- Page Header -->
      <div class="flex items-center gap-3">
        <AppButton variant="ghost" square size="md" @click="goBack()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="text-muted-foreground">
            <path d="m12 19-7-7 7-7" /><path d="M19 12H5" />
          </svg>
        </AppButton>

        <div class="flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="font-body font-bold text-2xl text-foreground">{{ invoice.invoice_number }}</h1>

            <!-- Static deleted badge -->
            <span v-if="invoice.deleted_at" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium border bg-destructive/10 text-destructive border-destructive/30">
              Deleted
            </span>

            <!-- Status badge + quick-change dropdown (hidden when deleted) -->
            <div class="relative" v-if="!invoice.deleted_at">
              <!-- Backdrop closes the menu when clicking outside -->
              <div v-if="showStatusMenu" class="fixed inset-0 z-40" @click="showStatusMenu = false" />

              <button
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium border transition-colors hover:opacity-80 focus:outline-none"
                :class="{
                  'bg-success/10 text-success border-success/30':       invoice.status === 'paid',
                  'bg-destructive/10 text-destructive border-destructive/30': invoice.status === 'overdue',
                  'bg-warning/10 text-warning border-warning/30':        invoice.status === 'partially_paid',
                  'bg-muted text-muted-foreground border-border':         invoice.status === 'unpaid',
                }"
                :disabled="statusChanging"
                @click.stop="showStatusMenu = !showStatusMenu"
                title="Click to change status"
              >
                <span v-if="statusChanging" class="w-3 h-3 border border-current border-t-transparent rounded-full animate-spin" />
                {{ statusLabel }}
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m6 9 6 6 6-6"/>
                </svg>
              </button>

              <!-- Status dropdown -->
              <div
                v-if="showStatusMenu"
                class="absolute top-full left-0 mt-1.5 w-44 bg-background border border-border rounded-lg shadow-lg z-50 py-1 overflow-hidden"
              >
                <button
                  v-for="opt in STATUS_OPTIONS"
                  :key="opt.value"
                  class="w-full text-left px-3 py-2 text-sm flex items-center gap-2.5 transition-colors"
                  :class="invoice.status === opt.value
                    ? 'bg-muted text-foreground font-medium'
                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'"
                  @click.stop="changeStatus(opt.value)"
                >
                  <span class="w-2 h-2 rounded-full shrink-0" :class="opt.dot" />
                  {{ opt.label }}
                  <svg v-if="invoice.status === opt.value"
                    xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    class="ml-auto text-foreground">
                    <path d="M20 6 9 17l-5-5"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ invoice.unit?.estate?.name }} · Unit {{ invoice.unit?.unit_number }}
          </p>
        </div>

        <div class="flex gap-2 items-center">
          <!-- Restore & Delete Forever (only shown for deleted invoices) -->
          <template v-if="invoice.deleted_at">
            <AppButton variant="outline" :disabled="restoring" @click="restoreInvoice">
              <svg v-if="restoring" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
              {{ restoring ? 'Restoring…' : 'Restore' }}
            </AppButton>
            <AppButton variant="danger" @click="showForceDeleteConfirm = true">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
              Delete Forever
            </AppButton>
          </template>

          <!-- Edit (manual invoices only, not deleted) -->
          <AppButton
            v-if="invoice.issued_by_type === 'user' && !invoice.deleted_at"
            variant="outline"
            @click="openEditModal"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
              <path d="m15 5 4 4"/>
            </svg>
            Edit
          </AppButton>

          <!-- Delete (hidden for already-deleted invoices) -->
          <AppButton v-if="!invoice.deleted_at" variant="outline" @click="showDeleteConfirm = true">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="text-destructive">
              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
            </svg>
          </AppButton>

          <!-- Print -->
          <AppButton variant="outline" @click="printInvoice">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
              <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6" />
              <rect x="6" y="14" width="12" height="8" rx="1" />
            </svg>
            Print
          </AppButton>

          <!-- Resend Email (hidden for deleted invoices) -->
          <AppButton
            v-if="!invoice.deleted_at"
            variant="outline"
            :disabled="resending"
            @click="resendInvoice"
          >
            <svg v-if="resending" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-6.219-8.56" />
            </svg>
            <svg v-else-if="resendSuccess" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" class="text-success">
              <path d="M20 6 9 17l-5-5" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="20" height="16" x="2" y="4" rx="2" />
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
            </svg>
            {{ resending ? 'Sending…' : resendSuccess ? 'Sent!' : hasEverBeenSent ? 'Resend Email' : 'Send Email' }}
          </AppButton>

          <!-- Download PDF -->
          <AppButton
            variant="primary"
            :disabled="downloading"
            @click="downloadPdf"
          >
            <svg v-if="downloading" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12a9 9 0 1 1-6.219-8.56" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" x2="12" y1="15" y2="3" />
            </svg>
            {{ downloading ? 'Generating…' : 'Download PDF' }}
          </AppButton>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left column (2/3) -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Invoice Details card -->
          <AppCard padding="none" shadow="sm">
            <div class="px-6 pt-6 pb-3">
              <h3 class="font-body font-semibold text-base text-foreground flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                  <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                  <path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />
                </svg>
                Invoice Details
              </h3>
            </div>

            <div class="px-6 pb-6">
              <div class="border border-border rounded-lg overflow-hidden">

                <!-- Document header -->
                <div class="bg-muted/50 px-5 py-4 border-b border-border">
                  <div class="flex justify-between items-start">
                    <div>
                      <p class="font-body font-bold text-lg text-foreground">Bold Mark Properties</p>
                      <p class="text-xs text-muted-foreground mt-1">Property Management Services</p>
                    </div>
                    <div class="text-right">
                      <p class="font-body font-bold text-lg text-foreground">{{ invoice.invoice_number }}</p>
                      <p class="text-xs text-muted-foreground mt-1">Tax Invoice</p>
                    </div>
                  </div>
                </div>

                <!-- Bill To + Invoice meta -->
                <div class="grid grid-cols-2 gap-6 px-5 py-4 border-b border-border">
                  <div>
                    <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-2">Bill To</p>
                    <p class="text-sm font-medium text-foreground">{{ billedTo?.full_name ?? '—' }}</p>
                    <p class="text-xs text-muted-foreground capitalize">{{ invoice.billed_to_type }}</p>
                    <p class="text-xs text-muted-foreground mt-1">{{ billedTo?.email ?? '—' }}</p>
                  </div>
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-muted-foreground">Invoice Date</span>
                      <span class="text-foreground">{{ formatDateLong(invoice.created_at?.split(' ')[0]) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-muted-foreground">Due Date</span>
                      <span class="text-foreground">{{ formatDate(invoice.due_date) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-muted-foreground">Period</span>
                      <span class="text-foreground">{{ formatPeriod(invoice.billing_period) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Line items -->
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-border bg-muted/30">
                      <th class="text-left py-2.5 px-5 text-xs font-medium text-muted-foreground uppercase">Description</th>
                      <th class="text-right py-2.5 px-5 text-xs font-medium text-muted-foreground uppercase">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-border">
                      <td class="py-3 px-5 text-foreground">
                        <p class="font-medium">{{ invoice.charge_type?.name }} — Unit {{ invoice.unit?.unit_number }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatPeriod(invoice.billing_period) }}</p>
                      </td>
                      <td class="py-3 px-5 text-right font-medium text-foreground whitespace-nowrap">
                        {{ formatCurrency(invoice.amount) }}
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="bg-muted/30">
                      <td class="py-3 px-5 font-bold text-foreground">Total Due</td>
                      <td class="py-3 px-5 text-right font-bold text-foreground whitespace-nowrap">
                        {{ formatCurrency(invoice.amount) }}
                      </td>
                    </tr>
                  </tfoot>
                </table>

              </div>
            </div>
          </AppCard>

          <!-- Payment History card — drag receipt here to open Add Payment modal -->
          <AppCard padding="none" shadow="sm">
            <div
              class="rounded-lg transition-colors"
              :class="isDraggingOverPayments ? 'ring-2 ring-primary/40 bg-primary/5' : ''"
              @dragover.prevent="onPaymentsDragOver"
              @dragleave="onPaymentsDragLeave"
              @drop.prevent="onPaymentsDrop"
            >
            <div class="px-6 pt-6 pb-3 flex items-center justify-between">
              <h3 class="font-body font-semibold text-base text-foreground flex items-center gap-2">
                Payment History
                <span
                  v-if="isDraggingOverPayments"
                  class="text-xs font-normal text-primary animate-pulse"
                >Drop receipt to attach</span>
              </h3>
              <AppButton
                v-if="invoice.status !== 'paid'"
                variant="outline"
                size="sm"
                @click="openAddPayment()"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                Add Payment
              </AppButton>
            </div>
            <div class="px-6 pb-6">
              <table v-if="invoice.cashbook_entries?.length" class="w-full text-sm">
                <thead>
                  <tr class="border-b border-border">
                    <th class="text-left py-2.5 px-3 text-xs font-medium text-muted-foreground uppercase">Date</th>
                    <th class="text-left py-2.5 px-3 text-xs font-medium text-muted-foreground uppercase">Description</th>
                    <th class="text-right py-2.5 px-3 text-xs font-medium text-muted-foreground uppercase">Amount</th>
                    <th class="w-10" />
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="entry in invoice.cashbook_entries"
                    :key="entry.id"
                    class="border-b border-border last:border-0 hover:bg-muted/50 transition-colors group"
                  >
                    <td class="py-2.5 px-3 text-foreground whitespace-nowrap cursor-pointer" @click="router.push('/cashbook/' + entry.id)">{{ formatDate(entry.date) }}</td>
                    <td class="py-2.5 px-3 text-foreground cursor-pointer" @click="router.push('/cashbook/' + entry.id)">{{ entry.description }}</td>
                    <td class="py-2.5 px-3 text-right font-medium text-success whitespace-nowrap cursor-pointer" @click="router.push('/cashbook/' + entry.id)">
                      +{{ formatCurrency(entry.amount) }}
                    </td>
                    <td class="py-2.5 px-3 text-right">
                      <button
                        type="button"
                        class="opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive transition-all"
                        title="Remove payment"
                        @click.stop="openRemovePayment(entry)"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="text-sm text-muted-foreground text-center py-6">
                No payments received yet
              </p>

              <!-- Persistent drag / click hint -->
              <div
                class="mt-3 flex items-center justify-center gap-2 rounded-md border border-dashed border-border px-4 py-2.5 text-xs text-muted-foreground cursor-pointer hover:border-primary/50 hover:text-primary hover:bg-primary/5 transition-colors select-none"
                @click="paymentsHintInput.click()"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
                Drop a receipt here or <span class="font-medium underline underline-offset-2">click to browse</span>
              </div>
              <input
                ref="paymentsHintInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                class="hidden"
                @change="e => { const f = e.target.files[0]; if (f) openAddPayment(f); e.target.value = '' }"
              />
            </div>
            </div><!-- /drag wrapper -->
          </AppCard>

        </div>

        <!-- Right column (1/3) -->
        <div class="space-y-4">

          <!-- Financial summary card -->
          <AppCard padding="none" shadow="sm">
            <div class="p-5 space-y-4">
              <div>
                <p class="text-xs text-muted-foreground">Total Amount</p>
                <p class="font-body font-bold text-2xl text-foreground whitespace-nowrap">
                  {{ formatCurrency(invoice.amount) }}
                </p>
              </div>
              <div class="h-px bg-border" />
              <div>
                <p class="text-xs text-muted-foreground">Total Paid</p>
                <p class="font-body font-bold text-lg text-success whitespace-nowrap">
                  {{ formatCurrency(invoice.total_paid) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-muted-foreground">Outstanding</p>
                <p
                  class="font-body font-bold text-lg whitespace-nowrap"
                  :class="invoice.outstanding > 0 ? 'text-destructive' : 'text-success'"
                >
                  {{ formatCurrency(invoice.outstanding) }}
                </p>
              </div>
            </div>
          </AppCard>

          <!-- Context card -->
          <AppCard padding="none" shadow="sm">
            <div class="p-5 space-y-3">
              <!-- Estate -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground shrink-0">
                  <rect width="16" height="20" x="4" y="2" rx="2" ry="2" />
                  <path d="M9 22v-4h6v4" />
                  <path d="M8 6h.01" /><path d="M16 6h.01" /><path d="M12 6h.01" />
                  <path d="M12 10h.01" /><path d="M12 14h.01" /><path d="M16 10h.01" />
                  <path d="M16 14h.01" /><path d="M8 10h.01" /><path d="M8 14h.01" />
                </svg>
                <span class="text-muted-foreground">Estate:</span>
                <router-link
                  :to="`/estates/${invoice.unit?.estate_id}`"
                  class="text-primary font-medium hover:underline"
                >
                  {{ invoice.unit?.estate?.name ?? '—' }}
                </router-link>
              </div>

              <!-- Unit -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground shrink-0">
                  <line x1="4" x2="20" y1="9" y2="9" /><line x1="4" x2="20" y1="15" y2="15" />
                  <line x1="10" x2="8" y1="3" y2="21" /><line x1="16" x2="14" y1="3" y2="21" />
                </svg>
                <span class="text-muted-foreground">Unit:</span>
                <router-link
                  v-if="invoice.unit"
                  :to="`/estates/${invoice.unit.estate_id}/units/${invoice.unit.id}`"
                  class="text-primary font-medium hover:underline"
                >
                  {{ invoice.unit.unit_number }}
                </router-link>
                <span v-else class="text-foreground font-medium">—</span>
              </div>

              <!-- Type -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground shrink-0">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                </svg>
                <span class="text-muted-foreground">Type:</span>
                <span class="text-foreground font-medium capitalize">{{ invoice.billed_to_type }}</span>
              </div>

              <!-- Due date -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground shrink-0">
                  <path d="M8 2v4" /><path d="M16 2v4" />
                  <rect width="18" height="18" x="3" y="4" rx="2" /><path d="M3 10h18" />
                </svg>
                <span class="text-muted-foreground">Due:</span>
                <span class="text-foreground font-medium">{{ formatDate(invoice.due_date) }}</span>
              </div>

              <!-- Issued By -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground shrink-0">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                </svg>
                <span class="text-muted-foreground">Issued by:</span>
                <span v-if="invoice.issued_by_type === 'user' && invoice.issued_by?.name"
                  class="text-foreground font-medium">
                  {{ invoice.issued_by.name }}
                </span>
                <span v-else class="text-muted-foreground italic">System (Automated)</span>
              </div>
            </div>
          </AppCard>

          <!-- Email Tracking card -->
          <AppCard padding="none" shadow="sm">
            <div class="px-5 pt-5 pb-3">
              <h3 class="font-body font-semibold text-base text-foreground flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="text-muted-foreground">
                  <rect width="20" height="16" x="2" y="4" rx="2" />
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                </svg>
                Email Tracking
              </h3>
            </div>

            <div class="px-5 pb-5">

              <!-- Recipient selector — only shown when sent to 2+ different emails -->
              <div v-if="uniqueSentEmails.length > 1" class="mb-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Recipient</p>
                <div class="flex flex-col gap-1">
                  <button
                    v-for="(email, i) in uniqueSentEmails"
                    :key="email"
                    class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-md border text-xs text-left transition-colors w-full"
                    :class="selectedTrackingEmail === email
                      ? 'border-primary/40 bg-primary/5 text-foreground font-medium'
                      : 'border-border bg-transparent text-muted-foreground hover:bg-muted/40'"
                    @click="selectedTrackingEmail = email"
                  >
                    <span class="truncate min-w-0">{{ email }}</span>
                    <span
                      v-if="i === 0"
                      class="shrink-0 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-success/10 text-success leading-none"
                    >Latest</span>
                  </button>
                </div>
              </div>

              <!-- Connecting line + items -->
              <div class="relative">

                <!-- Vertical connector line -->
                <div class="absolute left-[13px] top-4 bottom-4 w-px bg-border" />

                <div class="space-y-5">

                  <!-- Sent -->
                  <div class="flex items-start gap-3 relative">
                    <div
                      class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 z-10 ring-2 ring-background"
                      :class="sentEvent ? 'bg-success/15' : 'bg-muted'"
                    >
                      <!-- Sent icon (paper plane) -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        :class="sentEvent ? 'text-success' : 'text-muted-foreground'">
                        <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z" />
                        <path d="m21.854 2.147-10.94 10.939" />
                      </svg>
                    </div>
                    <div class="pt-0.5 min-w-0">
                      <p class="text-sm font-semibold text-foreground">Sent</p>
                      <template v-if="sentEvent">
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(sentEvent.occurred_at) }}</p>
                        <p v-if="sentEvent.email" class="text-xs text-muted-foreground truncate">{{ sentEvent.email }}</p>
                      </template>
                      <p v-else class="text-xs text-muted-foreground">Not yet sent</p>
                    </div>
                  </div>

                  <!-- Delivered -->
                  <div class="flex items-start gap-3 relative">
                    <div
                      class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 z-10 ring-2 ring-background"
                      :class="deliveredEvent ? 'bg-success/15' : 'bg-muted'"
                    >
                      <!-- Delivered icon (circle check) -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        :class="deliveredEvent ? 'text-success' : 'text-muted-foreground'">
                        <circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" />
                      </svg>
                    </div>
                    <div class="pt-0.5">
                      <p class="text-sm font-semibold text-foreground">Delivered</p>
                      <template v-if="deliveredEvent">
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(deliveredEvent.occurred_at) }}</p>
                      </template>
                      <p v-else class="text-xs text-muted-foreground">
                        {{ sentEvent ? 'Waiting for delivery confirmation…' : 'Not tracked yet' }}
                      </p>
                    </div>
                  </div>

                  <!-- Opened -->
                  <div class="flex items-start gap-3 relative">
                    <div
                      class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 z-10 ring-2 ring-background"
                      :class="openedEvent ? 'bg-success/15' : 'bg-muted'"
                    >
                      <!-- Opened icon (envelope open) -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        :class="openedEvent ? 'text-success' : 'text-muted-foreground'">
                        <path d="M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6Z" />
                        <path d="m22 10-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10" />
                      </svg>
                    </div>
                    <div class="pt-0.5">
                      <p class="text-sm font-semibold text-foreground">Opened</p>
                      <template v-if="openedEvent">
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(openedEvent.occurred_at) }}</p>
                      </template>
                      <p v-else class="text-xs text-muted-foreground">Not yet opened</p>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Time-to-open stat -->
              <div v-if="openedEvent && timeToOpen" class="mt-4 px-3 py-2.5 bg-success/8 border border-success/20 rounded-md flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  class="text-success shrink-0">
                  <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
                </svg>
                <p class="text-xs text-success leading-relaxed">
                  Opened <strong>{{ timeToOpen }}</strong> after delivery
                </p>
              </div>

              <!-- Tip when delivered but not yet opened -->
              <div
                v-else-if="deliveredEvent && !openedEvent"
                class="mt-4 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-md"
              >
                <p class="text-xs text-amber-800 leading-relaxed">
                  Delivered but not yet opened. Consider following up if payment is overdue.
                </p>
              </div>
            </div>
          </AppCard>

        </div>
      </div>

    </template>

  <!-- ── Modals ─────────────────────────────────────────────────────────── -->
  <!-- ── Add Payment modal ──────────────────────────────────────────────── -->
  <AppModal :show="showAddPayment" title="Add Payment" size="sm" @close="showAddPayment = false">
    <div class="space-y-4">
      <AppDatePicker
        v-model="addPaymentForm.date"
        label="Payment Date"
        placeholder="Select date"
      />

      <!-- Allocate to Invoice (optional) -->
      <div>
        <AppSelect
          v-model="selectedInvoiceId"
          label="Allocate to Invoice"
          :options="invoiceOptions"
          :placeholder="loadingInvoices ? 'Loading invoices…' : 'Select invoice…'"
          :disabled="loadingInvoices"
        />
        <p v-if="selectedInvoiceId && selectedInvoiceId !== '__none__'" class="mt-1 text-xs text-muted-foreground">
          Description and amount have been pre-filled from the selected invoice.
        </p>
      </div>

      <AppInput
        v-model="addPaymentForm.description"
        label="Description"
        placeholder="e.g. EFT — April levy"
      />
      <AppInput
        v-model="addPaymentForm.amount"
        label="Amount"
        type="number"
        placeholder="0.00"
      />
      <AppInput
        v-model="addPaymentForm.notes"
        label="Notes (optional)"
        type="textarea"
        placeholder="Any additional notes…"
      />

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

      <p v-if="addPaymentError" class="text-sm text-destructive">{{ addPaymentError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showAddPayment = false">Cancel</AppButton>
      <AppButton
        variant="primary"
        :disabled="addPaymentSaving || !addPaymentForm.date || !addPaymentForm.description || !addPaymentForm.amount"
        @click="submitAddPayment"
      >
        {{ addPaymentSaving ? 'Saving…' : 'Record Payment' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Edit Invoice modal ───────────────────────────────────────────────── -->
  <AppModal :show="showEditModal" title="Edit Invoice" size="sm" @close="showEditModal = false">
    <div class="space-y-4">
      <div class="rounded-lg bg-muted/50 border border-border px-4 py-3 text-sm text-muted-foreground">
        Editing is available for manually-created invoices. Changes will update the invoice record immediately.
      </div>

      <AppInput
        v-model="editForm.amount"
        label="Amount"
        type="number"
        placeholder="0.00"
      />

      <AppDatePicker
        v-model="editForm.due_date"
        label="Due Date"
        placeholder="Select due date"
      />

      <AppDatePicker
        v-model="editForm.billing_period"
        label="Billing Period"
        placeholder="Select billing period"
        mode="month"
      />

      <p v-if="editError" class="text-sm text-destructive">{{ editError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showEditModal = false">Cancel</AppButton>
      <AppButton
        variant="primary"
        :disabled="editSaving || !editForm.amount || !editForm.due_date"
        @click="submitEdit"
      >
        {{ editSaving ? 'Saving…' : 'Save Changes' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Delete Invoice confirmation ──────────────────────────────────────── -->
  <AppModal :show="showDeleteConfirm" title="Delete Invoice" size="sm" @close="showDeleteConfirm = false">
    <div class="space-y-3">
      <div class="rounded-lg bg-destructive/8 border border-destructive/20 px-4 py-3 text-sm">
        <p class="font-medium text-foreground">Move to Trash?</p>
        <p class="text-muted-foreground mt-1">
          <strong class="text-foreground">{{ invoice?.invoice_number }}</strong> will be moved to Trash.
          It will be permanently deleted after 3 years. You can restore it from Billing → Trash at any time.
        </p>
      </div>
      <p v-if="deleteError" class="text-sm text-destructive">{{ deleteError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showDeleteConfirm = false">Cancel</AppButton>
      <AppButton variant="danger" :disabled="deleteConfirming" @click="confirmDelete">
        {{ deleteConfirming ? 'Deleting…' : 'Move to Trash' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Remove Payment modal ──────────────────────────────────────────────── -->
  <AppModal :show="showRemovePayment" title="Remove Payment" size="sm" @close="showRemovePayment = false">
    <div class="space-y-4">
      <div v-if="removePaymentEntry" class="rounded-lg bg-muted/50 border border-border px-4 py-3 text-sm">
        <p class="text-muted-foreground">Payment being removed</p>
        <p class="font-medium text-foreground mt-0.5">{{ removePaymentEntry.description }}</p>
        <p class="text-destructive font-semibold mt-0.5">+{{ formatCurrency(removePaymentEntry.amount) }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-foreground mb-1.5">
          Reason for removal <span class="text-destructive">*</span>
        </label>
        <textarea
          v-model="removePaymentReason"
          rows="3"
          placeholder="e.g. Payment was recorded in error, incorrect amount entered…"
          class="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
        />
      </div>
      <p v-if="removePaymentError" class="text-sm text-destructive">{{ removePaymentError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showRemovePayment = false">Cancel</AppButton>
      <AppButton
        variant="danger"
        :disabled="removePaymentSaving || !removePaymentReason.trim()"
        @click="submitRemovePayment"
      >
        {{ removePaymentSaving ? 'Removing…' : 'Remove Payment' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Force Delete confirmation ──────────────────────────────────────── -->
  <AppModal :show="showForceDeleteConfirm" title="Delete Forever" size="sm" @close="showForceDeleteConfirm = false">
    <div class="space-y-3">
      <p class="text-sm text-muted-foreground">
        <strong class="text-foreground">{{ invoice?.invoice_number }}</strong> will be permanently removed from the system. This action <strong class="text-destructive">cannot be undone</strong>.
      </p>
      <p v-if="forceDeleteError" class="text-sm text-destructive">{{ forceDeleteError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showForceDeleteConfirm = false">Cancel</AppButton>
      <AppButton variant="danger" :disabled="forceDeleteConfirming" @click="confirmForceDelete">
        {{ forceDeleteConfirming ? 'Deleting…' : 'Delete Forever' }}
      </AppButton>
    </template>
  </AppModal>
</div>
</template>
