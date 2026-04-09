<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi.js'
import AppAlert  from '@/components/common/AppAlert.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge  from '@/components/common/AppBadge.vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppSelect     from '@/components/common/AppSelect.vue'
import AppInput      from '@/components/common/AppInput.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { useBack } from '@/composables/useBack.js'

const router = useRouter()
const route  = useRoute()

const estateId = computed(() => route.params.estateId)
const unitId   = computed(() => route.params.unitId)

// ── State ─────────────────────────────────────────────────────────────
const loading           = ref(true)
const error             = ref(null)
const unit              = ref(null)
const invoices          = ref([])
const payments          = ref([])
const tenantHistory     = ref([])
const unallocatedCount  = ref(0)
const activities        = ref([])
const activitiesLoading = ref(false)
const showChangeDetails = ref(false)
const selectedLogGroup  = ref(null)

// ── Data fetching ─────────────────────────────────────────────────────
async function fetchAll() {
  loading.value = true
  error.value   = null
  try {
    const [unitRes, invoicesRes, paymentsRes, tenantsRes, unallocRes] = await Promise.all([
      api.get(`/estates/${estateId.value}/units/${unitId.value}`),
      api.get('/invoices', { params: { unit_id: unitId.value, _per_page: 10 } }),
      api.get('/cashbook', { params: { unit_id: unitId.value, type: 'credit', _per_page: 20 } }),
      api.get(`/estates/${estateId.value}/units/${unitId.value}/tenants`),
      api.get('/cashbook', { params: { estate_id: estateId.value, allocation_status: 'unallocated', _per_page: 1 } }),
    ])
    unit.value             = unitRes.data.data
    invoices.value         = invoicesRes.data.data  ?? []
    payments.value         = paymentsRes.data.data  ?? []
    tenantHistory.value    = tenantsRes.data.data   ?? []
    unallocatedCount.value = unallocRes.data.meta?.total ?? 0
  } catch (e) {
    error.value = e?.response?.data?.message ?? 'Failed to load unit data'
  } finally {
    loading.value = false
  }
}

async function fetchActivities() {
  activitiesLoading.value = true
  try {
    const res = await api.get(`/estates/${estateId.value}/units/${unitId.value}/activities`, {
      params: { _per_page: 50 },
    })
    activities.value = res.data.data ?? []
  } catch {
    activities.value = []
  } finally {
    activitiesLoading.value = false
  }
}

onMounted(() => {
  fetchAll()
  fetchActivities()
})

// ── Copy to clipboard ─────────────────────────────────────────────────
const copiedKey = ref(null)
function copyText(text, key) {
  if (!text || text === '—') return
  navigator.clipboard.writeText(text)
  copiedKey.value = key
  setTimeout(() => { copiedKey.value = null }, 1500)
}

// ── Helpers ───────────────────────────────────────────────────────────
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

function fmtDate(s) {
  if (!s) return '—'
  const d = new Date(s + 'T00:00:00')
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function fmtPeriod(s) {
  if (!s) return '—'
  const d = new Date(s + 'T00:00:00')
  return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
}

function fmtActivityDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function statusVariant(s) {
  return { paid: 'success', overdue: 'danger', partially_paid: 'warning', unpaid: 'default' }[s] ?? 'default'
}

function statusLabel(s) {
  return { paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', unpaid: 'Unpaid' }[s] ?? s
}

// Returns 'none' | 'sent' | 'delivered' | 'opened' based on the highest email event present
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

function categoryBadgeClass(_cat) {
  return 'bg-muted text-muted-foreground border-border'
}

function categoryLabel(cat) {
  return { owner: 'Owner', tenant: 'Tenant', charges: 'Charges', unit: 'Unit' }[cat] ?? cat
}

// Short verb shown after the category badge: "[Owner] changed", "[Tenant] moved in", etc.
function eventAction(event) {
  if (!event) return 'changed'
  if (event.toLowerCase().includes('moved in'))  return 'moved in'
  if (event.toLowerCase().includes('moved out')) return 'moved out'
  if (event.toLowerCase().includes('created'))   return 'created'
  if (event.toLowerCase().includes('deleted'))   return 'deleted'
  return 'changed'
}

// Lifecycle events are self-explanatory — no field diff needed.
// Only "update/change" events warrant showing before → after details.
function shouldShowChanges(event) {
  if (!event) return false
  const e = event.toLowerCase()
  return !e.includes('moved in') && !e.includes('moved out') &&
         !e.includes('created')  && !e.includes('deleted')   &&
         !e.includes('archived')
}

// For lifecycle events (moved in / moved out), extract a one-line summary
// from the changes array so the user knows which tenant the event refers to.
function getLifecycleSummary(log) {
  if (!log?.event || !log?.changes?.length) return null
  const e = event => log.event.toLowerCase().includes(event)
  if (e('moved in')) {
    const name  = log.changes.find(c => c.field === 'Full Name')?.new
    const rent  = log.changes.find(c => c.field === 'Monthly Rent')?.new
    const start = log.changes.find(c => c.field === 'Lease Start')?.new
    const end   = log.changes.find(c => c.field === 'Lease End')?.new
    if (!name) return null
    const parts = [name]
    if (rent)  parts.push(`R ${rent}`)
    if (start && end) parts.push(`${start} → ${end}`)
    else if (start)   parts.push(`from ${start}`)
    return parts.join(' · ')
  }
  if (e('moved out')) {
    const name = log.changes.find(c => c.field === 'Full Name')?.old
                 ?? log.changes.find(c => c.field === 'Full Name')?.new
    return name ?? null
  }
  if (e('created') || e('archived') || e('deleted')) {
    const name = log.changes.find(c => c.field === 'Full Name')?.new
                 ?? log.changes.find(c => c.field === 'Unit Number')?.new
    return name ?? null
  }
  return null
}

// Group activity entries that share the same batch_id into one timeline item.
// Entries without a batch_id (pre-migration) each form their own single-entry group.
const CATEGORY_ORDER = { unit: 0, owner: 1, tenant: 2 }

const groupedActivities = computed(() => {
  const groups = []
  const batchMap = {}

  for (const log of activities.value) {
    const key = log.batch_id
    if (key && batchMap[key] !== undefined) {
      groups[batchMap[key]].entries.push(log)
    } else {
      const idx = groups.length
      if (key) batchMap[key] = idx
      groups.push({ batchId: key || null, entries: [log] })
    }
  }

  // Within each group ensure consistent order: unit → owner → tenant
  for (const group of groups) {
    if (group.entries.length > 1) {
      group.entries.sort((a, b) =>
        (CATEGORY_ORDER[a.category] ?? 99) - (CATEGORY_ORDER[b.category] ?? 99)
      )
    }
  }

  return groups
})

function openChangeDetails(group) {
  selectedLogGroup.value = group
  showChangeDetails.value = true
}

function groupDotClass(group) {
  const cat = group.entries[0]?.category
  if (cat === 'owner')   return 'bg-primary'
  if (cat === 'tenant')  return 'bg-blue-500'
  if (cat === 'charges') return 'bg-warning'
  return 'bg-muted-foreground'
}

// ── Computed ──────────────────────────────────────────────────────────
const occupancyKey = computed(() => {
  const t = unit.value?.occupancy_type
  if (t === 'owner_occupied')  return 'owner'
  if (t === 'tenant_occupied') return 'tenant'
  return 'vacant'
})

const occupancyBadge = computed(() => {
  const map = {
    owner:  { wrapClass: 'bg-success/10 text-success border border-success/20',       dotClass: 'bg-success',             label: 'Owner Occupied'  },
    tenant: { wrapClass: 'bg-blue-50 text-blue-700 border border-blue-200',           dotClass: 'bg-blue-500',            label: 'Tenant Occupied' },
    vacant: { wrapClass: 'bg-muted text-muted-foreground border border-border',       dotClass: 'bg-muted-foreground/40', label: 'Vacant'          },
  }
  return map[occupancyKey.value] ?? map.vacant
})

const balance = computed(() => unit.value?.balance ?? 0)

// ── Invoice filter + pagination ───────────────────────────────────────
const INVOICES_PER_PAGE = 5
const invoiceFilter = ref('all')
const invoicePage   = ref(1)

const filteredInvoices = computed(() => {
  const f = invoiceFilter.value
  if (f === 'all') return tabFilteredInvoices.value
  return tabFilteredInvoices.value.filter(i => i.status === f)
})

const invoicePageCount = computed(() =>
  Math.max(1, Math.ceil(filteredInvoices.value.length / INVOICES_PER_PAGE))
)

const pagedInvoices = computed(() => {
  const start = (invoicePage.value - 1) * INVOICES_PER_PAGE
  return filteredInvoices.value.slice(start, start + INVOICES_PER_PAGE)
})

watch(invoiceFilter, () => { invoicePage.value = 1 })

// ── Invoice totals ────────────────────────────────────────────────────
// Counts come from the client-side loaded list (fine for tab badges).
// Financial totals (outstanding, credits) come from the server-side unit record
// so they always reflect ALL invoices and ALL cashbook entries — not just the
// current page — and remain consistent with the Account Balance card.
const invoiceTotals = computed(() => {
  const list = tabFilteredInvoices.value
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

// Server-side financial totals — always accurate across ALL invoices + credits.
// outstanding_amount = gross owed on open invoices (before applying credits).
// unallocated_credits = cash received but not yet matched to any invoice.
// netOutstanding = what the unit actually owes after credits (= |balance| when in arrears).
const serverOutstanding     = computed(() => unit.value?.outstanding_amount   ?? 0)
const serverCreditOnAccount = computed(() => unit.value?.unallocated_credits  ?? 0)
const serverNetOutstanding  = computed(() => Math.max(0, serverOutstanding.value - serverCreditOnAccount.value))

// ── Payment filter + pagination ───────────────────────────────────────
const PAYMENTS_PER_PAGE = 5
const paymentFilter = ref('all')
const paymentPage   = ref(1)

const filteredPayments = computed(() => {
  const f = paymentFilter.value
  if (f === 'all')         return tabFilteredPayments.value
  if (f === 'allocated')   return tabFilteredPayments.value.filter(p =>  p.is_allocated)
  if (f === 'unallocated') return tabFilteredPayments.value.filter(p => !p.is_allocated)
  return tabFilteredPayments.value
})

const paymentPageCount = computed(() =>
  Math.max(1, Math.ceil(filteredPayments.value.length / PAYMENTS_PER_PAGE))
)

const pagedPayments = computed(() => {
  const start = (paymentPage.value - 1) * PAYMENTS_PER_PAGE
  return filteredPayments.value.slice(start, start + PAYMENTS_PER_PAGE)
})

watch(paymentFilter, () => { paymentPage.value = 1 })

// ── Payment totals ────────────────────────────────────────────────────
const paymentTotals = computed(() => {
  const list = tabFilteredPayments.value
  const total       = list.reduce((s, p) => s + Number(p.amount ?? 0), 0)
  const allocated   = list.filter(p =>  p.is_allocated).reduce((s, p) => s + Number(p.amount ?? 0), 0)
  const unallocated = list.filter(p => !p.is_allocated).reduce((s, p) => s + Number(p.amount ?? 0), 0)
  return { total, allocated, unallocated }
})

// Charge config: levy + rent (from unit fields) + per-unit recurring configs
const chargeConfigDisplay = computed(() => {
  if (!unit.value) return []
  const items = []
  if (unit.value.effective_levy_amount) {
    items.push({ name: 'Levy', amount: unit.value.effective_levy_amount, configured: true })
  }
  if (unit.value.occupancy_type === 'tenant_occupied' && unit.value.rent_amount) {
    items.push({ name: 'Rent', amount: unit.value.rent_amount, configured: true })
  }
  ;(unit.value.charge_configs ?? []).forEach(cfg => {
    if (cfg.is_active && cfg.charge_type) {
      items.push({ name: cfg.charge_type.name, amount: cfg.amount, configured: true })
    }
  })
  return items
})

// Lookup: unit_tenant id → full_name (for resolving invoice billed_to_type=unit_tenant)
const tenantNameMap = computed(() => {
  const m = {}
  tenantHistory.value.forEach(t => { m[t.id] = t.full_name })
  return m
})

function resolvedBilledTo(inv) {
  if (inv.billed_to_type === 'owner') return unit.value?.owner?.full_name ?? 'Owner'
  return tenantNameMap.value[inv.billed_to_id]
    ?? unit.value?.current_tenant?.full_name
    ?? 'Tenant'
}

// ── Navigation ────────────────────────────────────────────────────────
const { goBack } = useBack(computed(() => ({ name: 'estate-detail', params: { id: estateId.value } })))

function goToInvoice(id) {
  router.push({ name: 'invoice-detail', params: { invoiceId: id } })
}

function goToCashbook(entryId) {
  if (entryId) router.push({ name: 'cashbook-entry', params: { entryId } })
  else router.push({ name: 'cashbook' })
}

function goToTenant(t) {
  router.push({ name: 'tenant-detail', params: { estateId: estateId.value, unitId: unitId.value, tenantId: t.id } })
}

// ── View tab ──────────────────────────────────────────────────────────
const activeTab = ref('combined')

// Reset status filters and pagination when the view tab changes
watch(activeTab, () => {
  invoiceFilter.value = 'all'
  invoicePage.value   = 1
  paymentFilter.value = 'all'
  paymentPage.value   = 1
})

// ── Tab-aware computed filters ────────────────────────────────────────

// Base invoice list filtered by the active tab
const tabFilteredInvoices = computed(() => {
  if (activeTab.value === 'owner')  return invoices.value.filter(i => i.billed_to_type === 'owner')
  if (activeTab.value === 'tenant') return invoices.value.filter(i => i.billed_to_type !== 'owner')
  return invoices.value
})

// Base payment list filtered by the active tab.
// For allocated payments: attribute via the linked invoice's billed_to_type.
// For unallocated payments: use charge_type.applies_to when available.
//   - applies_to = 'owner'  → show only in Owner/Combined
//   - applies_to = 'tenant' → show only in Tenant/Combined
//   - applies_to = 'either' or no charge type → show in both (can't narrow down)
const tabFilteredPayments = computed(() => {
  if (activeTab.value === 'combined') return payments.value
  return payments.value.filter(pay => {
    if (pay.is_allocated) {
      const inv = invoices.value.find(i => i.id === pay.invoice_id) ?? pay.invoice
      if (!inv) return true
      const isOwner = inv.billed_to_type === 'owner'
      return activeTab.value === 'owner' ? isOwner : !isOwner
    }
    // Unallocated: try charge_type.applies_to
    const appliesTo = pay.charge_type?.applies_to
    if (appliesTo === 'owner')  return activeTab.value === 'owner'
    if (appliesTo === 'tenant') return activeTab.value === 'tenant'
    return true // 'either' or unknown — show in both owner and tenant tabs
  })
})

// Monthly Charges filtered by tab: Owner shows levy + owner charges,
// Tenant shows rent + tenant charges, Combined shows everything.
const filteredChargeConfigDisplay = computed(() => {
  if (activeTab.value === 'combined') return chargeConfigDisplay.value
  if (!unit.value) return []
  const items = []
  if (activeTab.value === 'owner') {
    if (unit.value.effective_levy_amount) {
      items.push({ name: 'Levy', amount: unit.value.effective_levy_amount, configured: true })
    }
    ;(unit.value.charge_configs ?? []).forEach(cfg => {
      if (cfg.is_active && cfg.charge_type) {
        const a = cfg.charge_type.applies_to
        if (a === 'owner' || a === 'either') {
          items.push({ name: cfg.charge_type.name, amount: cfg.amount, configured: true })
        }
      }
    })
  } else if (activeTab.value === 'tenant') {
    if (unit.value.occupancy_type === 'tenant_occupied' && unit.value.rent_amount) {
      items.push({ name: 'Rent', amount: unit.value.rent_amount, configured: true })
    }
    ;(unit.value.charge_configs ?? []).forEach(cfg => {
      if (cfg.is_active && cfg.charge_type && cfg.charge_type.applies_to === 'tenant') {
        items.push({ name: cfg.charge_type.name, amount: cfg.amount, configured: true })
      }
    })
  }
  return items
})

// Activity groups filtered by tab.
// owner → owner + unit + charges events; tenant → tenant + unit events.
const filteredGroupedActivities = computed(() => {
  if (activeTab.value === 'combined') return groupedActivities.value
  return groupedActivities.value.filter(group => {
    if (activeTab.value === 'owner') {
      return group.entries.some(e => e.category === 'owner' || e.category === 'unit' || e.category === 'charges')
    }
    if (activeTab.value === 'tenant') {
      return group.entries.some(e => e.category === 'tenant' || e.category === 'unit')
    }
    return true
  })
})

// ── Move In modal ─────────────────────────────────────────────────────
const showMoveIn   = ref(false)
const moveInSaving = ref(false)
const moveInForm   = ref({ name: '', email: '', phone: '', rent: '', leaseStart: '', leaseEnd: '' })

// ── Reinstate tenant ──────────────────────────────────────────────────
const reinstatingId = ref(null) // id of the tenant currently being reinstated

async function reinstateTenant(t) {
  if (reinstatingId.value) return
  reinstatingId.value = t.id
  try {
    await api.post(`/estates/${estateId.value}/units/${unitId.value}/tenants/${t.id}/reinstate`)
    await Promise.all([fetchAll(), fetchActivities()])
  } catch { /* ignore */ } finally {
    reinstatingId.value = null
  }
}

// ── Move Out modal ────────────────────────────────────────────────────
const showMoveOut   = ref(false)
const moveOutSaving = ref(false)
const moveOutForm   = ref({ date: '', reason: '', notes: '' })

const MOVE_OUT_REASONS = [
  { value: 'lease_expired',     label: 'Lease Expired' },
  { value: 'lease_not_renewed', label: 'Lease Not Renewed' },
  { value: 'tenant_evicted',    label: 'Tenant Evicted' },
  { value: 'tenant_relocated',  label: 'Tenant Relocated' },
  { value: 'mutual_agreement',  label: 'Mutual Agreement' },
  { value: 'property_sold',     label: 'Property Sold' },
  { value: 'other',             label: 'Other' },
]

function openMoveOut() {
  moveOutForm.value = { date: '', reason: '', notes: '' }
  showMoveOut.value = true
}

// Active tenant: unit has a current_tenant OR tenantHistory has an active record
const activeTenant = computed(() =>
  unit.value?.current_tenant ?? tenantHistory.value.find(t => t.is_active) ?? null
)

// Last moved-out tenant: most recent inactive tenant (shown when unit is vacant)
const lastTenant = computed(() => {
  if (activeTenant.value) return null
  const past = tenantHistory.value.filter(t => !t.is_active)
  if (!past.length) return null
  return past.reduce((a, b) => {
    const aDate = a.move_out_date ?? a.updated_at ?? ''
    const bDate = b.move_out_date ?? b.updated_at ?? ''
    return bDate > aDate ? b : a
  })
})

const MOVE_OUT_REASON_LABEL = {
  lease_expired:     'Lease Expired',
  lease_not_renewed: 'Lease Not Renewed',
  tenant_evicted:    'Tenant Evicted',
  tenant_relocated:  'Tenant Relocated',
  mutual_agreement:  'Mutual Agreement',
  property_sold:     'Property Sold',
  other:             'Other',
}

// ── Edit Move-Out Info modal ──────────────────────────────────────────
const showEditMoveOut    = ref(false)
const editMoveOutSaving  = ref(false)
const editMoveOutForm    = ref({ date: '', reason: '', notes: '' })

function openEditMoveOut() {
  const t = lastTenant.value
  editMoveOutForm.value = {
    date:   t?.move_out_date   ? t.move_out_date.slice(0, 10) : '',
    reason: t?.move_out_reason ?? '',
    notes:  t?.move_out_notes  ?? '',
  }
  showEditMoveOut.value = true
}

async function saveEditMoveOut() {
  const t = lastTenant.value
  if (!t) return
  editMoveOutSaving.value = true
  try {
    await api.put(`/estates/${estateId.value}/units/${unitId.value}/tenants/${t.id}`, {
      move_out_date:   editMoveOutForm.value.date   || null,
      move_out_reason: editMoveOutForm.value.reason || null,
      move_out_notes:  editMoveOutForm.value.notes  || null,
    })
    showEditMoveOut.value = false
    await Promise.all([fetchAll(), fetchActivities()])
  } catch {
    // silently ignore
  } finally {
    editMoveOutSaving.value = false
  }
}

async function confirmMoveOut() {
  const tenantId = activeTenant.value?.id
  if (!tenantId) return
  moveOutSaving.value = true
  try {
    await api.post(`/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId}/move-out`, {
      move_out_date:   moveOutForm.value.date   || null,
      move_out_reason: moveOutForm.value.reason || null,
      move_out_notes:  moveOutForm.value.notes  || null,
    })
    showMoveOut.value = false
    await Promise.all([fetchAll(), fetchActivities()])
  } catch {
    // silently ignore for now
  } finally {
    moveOutSaving.value = false
  }
}

async function confirmMoveIn() {
  moveInSaving.value = true
  try {
    await api.post(`/estates/${estateId.value}/units/${unitId.value}/tenants`, {
      full_name:   moveInForm.value.name,
      email:       moveInForm.value.email,
      phone:       moveInForm.value.phone,
      rent_amount: moveInForm.value.rent       || null,
      lease_start: moveInForm.value.leaseStart || null,
      lease_end:   moveInForm.value.leaseEnd   || null,
    })
    showMoveIn.value = false
    moveInForm.value = { name: '', email: '', phone: '', rent: '', leaseStart: '', leaseEnd: '' }
    await Promise.all([fetchAll(), fetchActivities()])
  } catch {
    // silently ignore for now
  } finally {
    moveInSaving.value = false
  }
}

// ── Send Message modal ────────────────────────────────────────────────
const showSendMessage  = ref(false)
const messageRecipient = ref(null)
const messageForm      = ref({ template: '', subject: '', body: '' })

const OWNER_TEMPLATES = [
  { value: 'payment_reminder', label: 'Payment Reminder',    subject: 'Outstanding Payment Reminder', body: 'Dear {name},\n\nThis is a friendly reminder that your account has an outstanding balance. Please arrange payment at your earliest convenience.\n\nKind regards,\nBold Mark Properties' },
  { value: 'levy_increase',    label: 'Levy Increase Notice', subject: 'Levy Increase Notice',         body: 'Dear {name},\n\nPlease be advised that your monthly levy will be adjusted effective next month. Full details are attached.\n\nKind regards,\nBold Mark Properties' },
  { value: 'welcome',          label: 'Welcome Letter',       subject: 'Welcome to Your Community',    body: 'Dear {name},\n\nWelcome! We are pleased to have you as part of our community.\n\nKind regards,\nBold Mark Properties' },
  { value: 'maintenance',      label: 'Maintenance Notice',   subject: 'Planned Maintenance Notice',   body: 'Dear {name},\n\nWe wish to notify you of planned maintenance scheduled for your property.\n\nKind regards,\nBold Mark Properties' },
  { value: 'statement',        label: 'Monthly Statement',    subject: 'Your Monthly Statement',       body: 'Dear {name},\n\nPlease find your monthly statement attached.\n\nKind regards,\nBold Mark Properties' },
]

const TENANT_TEMPLATES = [
  { value: 'payment_reminder', label: 'Payment Reminder',      subject: 'Rent Payment Reminder',       body: 'Dear {name},\n\nThis is a reminder that your rent payment is due. Please ensure timely payment to avoid any late fees.\n\nKind regards,\nBold Mark Properties' },
  { value: 'welcome',          label: 'Welcome Letter',         subject: 'Welcome to Your New Home',    body: 'Dear {name},\n\nWelcome! We hope you are settling in well.\n\nKind regards,\nBold Mark Properties' },
  { value: 'maintenance',      label: 'Maintenance Notice',     subject: 'Planned Maintenance Notice',  body: 'Dear {name},\n\nWe wish to notify you of planned maintenance at your unit.\n\nKind regards,\nBold Mark Properties' },
  { value: 'lease_renewal',    label: 'Lease Renewal Notice',   subject: 'Your Lease Renewal',          body: 'Dear {name},\n\nYour lease is approaching its end date. Please contact our office at your convenience.\n\nKind regards,\nBold Mark Properties' },
  { value: 'statement',        label: 'Monthly Statement',      subject: 'Your Monthly Statement',      body: 'Dear {name},\n\nPlease find your monthly statement attached.\n\nKind regards,\nBold Mark Properties' },
]

const activeTemplates = computed(() =>
  messageRecipient.value?.role === 'tenant' ? TENANT_TEMPLATES : OWNER_TEMPLATES
)

function openSendMessage(recipientType) {
  const person = recipientType === 'owner' ? unit.value?.owner : unit.value?.current_tenant
  if (!person) return
  const parts = (person.full_name ?? '').split(' ')
  messageRecipient.value = {
    name:     person.full_name,
    email:    person.email,
    role:     recipientType,
    initials: parts.map(p => p[0]).join('').slice(0, 2).toUpperCase(),
  }
  messageForm.value = { template: '', subject: '', body: '' }
  showSendMessage.value = true
}

function onTemplateChange() {
  const tpl = activeTemplates.value.find(t => t.value === messageForm.value.template)
  if (!tpl) { messageForm.value.subject = ''; messageForm.value.body = ''; return }
  const firstName = (messageRecipient.value?.name ?? '').split(' ')[0]
  messageForm.value.subject = tpl.subject
  messageForm.value.body    = tpl.body.replace(/{name}/g, firstName)
}

const messageSending = ref(false)
const messageError   = ref(null)

async function sendEmail() {
  if (!messageForm.value.subject.trim() || !messageForm.value.body.trim()) return
  messageSending.value = true
  messageError.value   = null
  try {
    await api.post('/messages/send', {
      recipient_name:  messageRecipient.value.name,
      recipient_email: messageRecipient.value.email,
      subject:         messageForm.value.subject,
      body:            messageForm.value.body,
    })
    showSendMessage.value = false
  } catch (e) {
    messageError.value = e?.response?.data?.message ?? 'Failed to send email. Please try again.'
  } finally {
    messageSending.value = false
  }
}

// ── Edit Unit modal ───────────────────────────────────────────────────
const showEditUnit    = ref(false)
const editSaving      = ref(false)
const leaseFileEdit   = ref(null)   // File selected in the edit modal
const leaseFileEditInput = ref(null) // Hidden <input type="file"> ref
const editForm        = ref({
  unitNumber:   '',
  occupancy:    'owner_occupied',
  levyOverride: '',
  owner:        { name: '', email: '', phone: '', idNumber: '' },
  showTenant:   false,
  tenant:       { name: '', email: '', phone: '', rent: '', leaseStart: '', leaseEnd: '' },
})

function openEditUnit() {
  if (!unit.value) return
  const u = unit.value
  leaseFileEdit.value = null
  editForm.value = {
    unitNumber:   u.unit_number,
    occupancy:    u.occupancy_type ?? 'owner_occupied',
    levyOverride: u.levy_override  ?? '',
    owner: {
      name:     u.owner?.full_name ?? '',
      email:    u.owner?.email     ?? '',
      phone:    u.owner?.phone     ?? '',
      idNumber: u.owner?.id_number ?? '',
    },
    showTenant: u.occupancy_type === 'tenant_occupied' || !!u.current_tenant,
    tenant: u.current_tenant ? {
      name:       u.current_tenant.full_name  ?? '',
      email:      u.current_tenant.email      ?? '',
      phone:      u.current_tenant.phone      ?? '',
      rent:       u.rent_amount               ?? '',
      leaseStart: u.current_tenant.lease_start ?? '',
      leaseEnd:   u.current_tenant.lease_end   ?? '',
    } : { name: '', email: '', phone: '', rent: '', leaseStart: '', leaseEnd: '' },
  }
  showEditUnit.value = true
}

// ── Lease document — tenant card actions ──────────────────────────────
const leaseFileCardInput  = ref(null) // Hidden <input type="file"> ref in tenant card
const leaseCardUploading  = ref(false)
const leaseCardDeleting   = ref(false)

async function uploadLeaseFromCard(event) {
  const file = event.target.files?.[0]
  if (!file || !unit.value?.current_tenant?.id) return
  leaseCardUploading.value = true
  try {
    const fd = new FormData()
    fd.append('lease_document', file)
    await api.post(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${unit.value.current_tenant.id}/lease-document`,
      fd,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    await fetchAll()
  } catch { /* ignore */ } finally {
    leaseCardUploading.value = false
    event.target.value = ''
  }
}

async function deleteLeaseFromCard() {
  if (!unit.value?.current_tenant?.id) return
  leaseCardDeleting.value = true
  try {
    await api.delete(
      `/estates/${estateId.value}/units/${unitId.value}/tenants/${unit.value.current_tenant.id}/lease-document`
    )
    await fetchAll()
  } catch { /* ignore */ } finally {
    leaseCardDeleting.value = false
  }
}

const estateType = computed(() => unit.value?.estate?.type)

const showTenantSection = computed(() => estateType.value !== 'sectional_title')

const showTenantFields = computed(() =>
  showTenantSection.value && (editForm.value.occupancy === 'tenant_occupied' || editForm.value.showTenant)
)

const occupancyOptions = computed(() => {
  if (estateType.value === 'sectional_title') {
    return [
      { value: 'owner_occupied', label: 'Owner' },
      { value: 'vacant',         label: 'Vacant' },
    ]
  }
  return [
    { value: 'owner_occupied',  label: 'Owner'  },
    { value: 'tenant_occupied', label: 'Tenant' },
    { value: 'vacant',          label: 'Vacant' },
  ]
})

// ── Create Invoice modal ──────────────────────────────────────────────
const showCreateInvoice   = ref(false)
const createInvoiceSaving = ref(false)
const createInvoiceError  = ref(null)
const chargeTypes         = ref([])

const createInvoiceForm = ref({
  billTo:        'owner',
  chargeTypeId:  '',
  billingPeriod: '',
  amount:        '',
  dueDate:       '',
})

function currentMonthValue() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function defaultDueDateFor(period) {
  if (!period) return ''
  const d = new Date(period + '-01')
  d.setDate(d.getDate() + 7)
  return d.toISOString().split('T')[0]
}

const billToOptions = computed(() => {
  const opts = [{ value: 'owner', label: 'Owner — ' + (unit.value?.owner?.full_name ?? 'Owner') }]
  if (unit.value?.occupancy_type === 'tenant_occupied' && unit.value?.current_tenant) {
    opts.push({ value: 'tenant', label: 'Tenant — ' + unit.value.current_tenant.full_name })
  }
  return opts
})

const filteredChargeTypeOptions = computed(() => {
  const bt = createInvoiceForm.value.billTo
  return chargeTypes.value
    .filter(ct => {
      if (bt === 'owner')  return ct.applies_to === 'owner'  || ct.applies_to === 'either'
      if (bt === 'tenant') return ct.applies_to === 'tenant' || ct.applies_to === 'either'
      return true
    })
    .map(ct => ({ value: ct.id, label: ct.name }))
})

function autoFillAmount(chargeTypeId) {
  if (!unit.value || !chargeTypeId) { createInvoiceForm.value.amount = ''; return }
  const ct = chargeTypes.value.find(c => c.id === chargeTypeId)
  if (!ct) { createInvoiceForm.value.amount = ''; return }
  if (ct.code === 'LEVY') {
    createInvoiceForm.value.amount = unit.value.effective_levy_amount ?? ''
  } else if (ct.code === 'RENT') {
    createInvoiceForm.value.amount = unit.value.rent_amount ?? ''
  } else {
    const config = (unit.value.charge_configs ?? []).find(c => c.charge_type_id === chargeTypeId)
    createInvoiceForm.value.amount = config?.amount ?? ''
  }
}

function onCreateBillToChange() {
  createInvoiceForm.value.chargeTypeId = ''
  createInvoiceForm.value.amount       = ''
}

function onCreateChargeTypeChange(id) {
  autoFillAmount(id)
}

function onCreatePeriodChange(val) {
  createInvoiceForm.value.dueDate = defaultDueDateFor(val)
}

async function openCreateInvoice() {
  createInvoiceError.value = null
  const period = currentMonthValue()
  createInvoiceForm.value  = {
    billTo:        'owner',
    chargeTypeId:  '',
    billingPeriod: period,
    amount:        '',
    dueDate:       defaultDueDateFor(period),
  }

  if (!chargeTypes.value.length) {
    try {
      const res = await api.get('/charge-types', { params: { is_active: true, _per_page: 100 } })
      chargeTypes.value = res.data.data ?? []
    } catch { /* ignore */ }
  }

  showCreateInvoice.value = true
}

async function submitCreateInvoice() {
  createInvoiceError.value = null
  const f = createInvoiceForm.value
  if (!f.chargeTypeId || !f.billingPeriod || !f.amount || !f.dueDate) return

  const billedToId = f.billTo === 'owner'
    ? unit.value?.owner?.id
    : unit.value?.current_tenant?.id

  if (!billedToId) { createInvoiceError.value = 'Could not resolve billed-to person.'; return }

  createInvoiceSaving.value = true
  try {
    await api.post('/invoices', {
      unit_id:        unitId.value,
      charge_type_id: f.chargeTypeId,
      billed_to_type: f.billTo,
      billed_to_id:   billedToId,
      amount:         parseFloat(f.amount),
      billing_period: f.billingPeriod + '-01',
      due_date:       f.dueDate,
    })
    showCreateInvoice.value = false
    await fetchAll()
  } catch (e) {
    createInvoiceError.value = e?.response?.data?.message ?? 'Failed to create invoice.'
  } finally {
    createInvoiceSaving.value = false
  }
}

async function saveEditUnit() {
  editSaving.value = true
  try {
    const payload = {
      unit_number:    editForm.value.unitNumber   || undefined,
      occupancy_type: editForm.value.occupancy    || undefined,
      levy_override:  editForm.value.levyOverride !== '' ? Number(editForm.value.levyOverride) : undefined,
      owner: {
        full_name:  editForm.value.owner.name,
        email:      editForm.value.owner.email,
        phone:      editForm.value.owner.phone     || null,
        id_number:  editForm.value.owner.idNumber  || null,
      },
    }

    if (showTenantFields.value) {
      if (editForm.value.tenant.rent !== '') {
        payload.rent_amount = Number(editForm.value.tenant.rent) || 0
      }
      payload.tenant = {
        full_name:   editForm.value.tenant.name,
        email:       editForm.value.tenant.email,
        phone:       editForm.value.tenant.phone      || null,
        lease_start: editForm.value.tenant.leaseStart || null,
        lease_end:   editForm.value.tenant.leaseEnd   || null,
      }
    }

    const updateRes = await api.put(`/estates/${estateId.value}/units/${unitId.value}`, payload)

    // Upload lease document if one was selected
    if (leaseFileEdit.value && showTenantFields.value) {
      const tenantId = updateRes.data?.data?.current_tenant?.id ?? unit.value?.current_tenant?.id
      if (tenantId) {
        const fd = new FormData()
        fd.append('lease_document', leaseFileEdit.value)
        await api.post(
          `/estates/${estateId.value}/units/${unitId.value}/tenants/${tenantId}/lease-document`,
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        leaseFileEdit.value = null
      }
    }

    showEditUnit.value = false
    await Promise.all([fetchAll(), fetchActivities()])
  } catch {
    // silently ignore for now
  } finally {
    editSaving.value = false
  }
}

// ── Add Payment modal ─────────────────────────────────────────────────
const showAddPayment      = ref(false)
const addPaymentSaving    = ref(false)
const addPaymentError     = ref(null)
const addPaymentForm      = ref({ date: '', description: '', amount: '', invoiceId: '', chargeTypeId: '', notes: '' })
const paymentFlow         = ref(null) // null | 'without_invoice' | 'with_invoice'
const addPaymentPersonKey = ref('owner') // 'owner' | tenant UUID string
const proofOfPaymentFile  = ref(null)
const proofInput          = ref(null)
const paymentsHintInput   = ref(null)

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

// Charge type options for the Add Payment modal — shows applies_to as a hint
const addPaymentChargeTypeOptions = computed(() => {
  const APPLIES_LABEL = { owner: 'Owner', tenant: 'Tenant', either: 'Either' }
  const opts = [{ value: '', label: 'No charge type specified' }]
  chargeTypes.value.forEach(ct => {
    opts.push({ value: ct.id, label: `${ct.name} · ${APPLIES_LABEL[ct.applies_to] ?? ct.applies_to}` })
  })
  return opts
})

const outstandingInvoiceOptions = computed(() => {
  const opts = [{ value: '', label: 'No invoice (advance payment)' }]
  invoices.value
    .filter(inv => inv.status !== 'paid')
    .forEach(inv => opts.push({
      value: inv.id,
      label: `${inv.invoice_number} — ${inv.charge_type?.name ?? inv.type} (${fmtAmount(inv.outstanding ?? inv.amount)})`,
    }))
  return opts
})

// "With Invoice" flow — no empty option; invoice is required
const withInvoiceOptions = computed(() =>
  invoices.value
    .filter(inv => inv.status !== 'paid')
    .map(inv => ({
      value: inv.id,
      label: `${inv.invoice_number} — ${inv.charge_type?.name ?? inv.type} (${fmtAmount(inv.outstanding ?? inv.amount)})`,
    }))
)

// All people associated with this unit — used for the "Without Invoice" person selector
const addPaymentPersonOptions = computed(() => {
  if (!unit.value) return []
  const mk = n => (n ?? '').trim().split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase()
  const opts = []
  if (unit.value.owner) {
    const name = unit.value.owner.full_name ?? 'Owner'
    opts.push({ key: 'owner', name, role: 'Owner', initials: mk(name) })
  }
  tenantHistory.value.forEach(t => {
    const name = t.full_name ?? 'Tenant'
    opts.push({ key: t.id, name, role: t.is_active ? 'Current Tenant' : 'Past Tenant', initials: mk(name) })
  })
  return opts
})

// Static person card — used for the "With Invoice" flow header (updates when invoice is selected)
const paymentPersonCard = computed(() => {
  if (!unit.value) return null
  const mk = n => (n ?? '').trim().split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase()
  const unitNum = unit.value.unit_number
  // Derive from the selected invoice's billed_to
  if (addPaymentForm.value.invoiceId) {
    const inv = invoices.value.find(i => i.id === addPaymentForm.value.invoiceId)
    if (inv) {
      if (inv.billed_to_type === 'owner') {
        const name = unit.value.owner?.full_name ?? 'Owner'
        return { name, role: 'Owner', unit: unitNum, initials: mk(name) }
      }
      const name = resolvedBilledTo(inv)
      return { name, role: 'Tenant', unit: unitNum, initials: mk(name) }
    }
  }
  // Default: active tab
  if (activeTab.value === 'tenant' && unit.value.current_tenant) {
    const name = unit.value.current_tenant.full_name ?? 'Tenant'
    return { name, role: 'Current Tenant', unit: unitNum, initials: mk(name) }
  }
  const owner = unit.value.owner
  if (!owner) return null
  const name = owner.full_name ?? 'Owner'
  return { name, role: 'Owner', unit: unitNum, initials: mk(name) }
})

// Validation: requirements differ per flow
const isAddPaymentValid = computed(() => {
  if (!addPaymentForm.value.date || !addPaymentForm.value.amount) return false
  if (paymentFlow.value === 'with_invoice') return !!addPaymentForm.value.invoiceId
  // without_invoice: description always required
  return !!addPaymentForm.value.description
})

function backToFlowChooser() {
  paymentFlow.value                 = null
  addPaymentForm.value.invoiceId    = ''
  addPaymentForm.value.chargeTypeId = ''
  addPaymentForm.value.description  = ''
  addPaymentForm.value.amount       = ''
  addPaymentError.value             = null
  addPaymentPersonKey.value         = 'owner'
}

// Always sync description with the selected charge type name.
// Also auto-switches the paying person based on applies_to.
// Clears description when charge type is cleared.
watch(() => addPaymentForm.value.chargeTypeId, (newId) => {
  if (paymentFlow.value !== 'without_invoice') return
  if (!newId) {
    addPaymentForm.value.description = ''
    return
  }
  const ct = chargeTypes.value.find(c => c.id === newId)
  if (!ct) return
  addPaymentForm.value.description = ct.name
  // Auto-switch person based on who this charge type applies to
  if (ct.applies_to === 'owner') {
    addPaymentPersonKey.value = 'owner'
  } else if (ct.applies_to === 'tenant') {
    const tenant = tenantHistory.value.find(t => t.is_active)
    if (tenant) addPaymentPersonKey.value = tenant.id
  }
  // 'either' — leave the person unchanged
})

// Auto-fill description, amount, and charge type when an invoice is selected
watch(() => addPaymentForm.value.invoiceId, (newId) => {
  if (!newId) {
    addPaymentForm.value.description  = ''
    addPaymentForm.value.amount       = ''
    addPaymentForm.value.chargeTypeId = ''
    return
  }
  const found = invoices.value.find(inv => inv.id === newId)
  if (found) {
    addPaymentForm.value.description  = `Payment — ${found.invoice_number}`
    addPaymentForm.value.amount       = (found.outstanding ?? found.amount) > 0
      ? String(found.outstanding ?? found.amount)
      : ''
    addPaymentForm.value.chargeTypeId = found.charge_type_id ?? ''
  }
})

// Drag-and-drop on Payments Received section
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

async function openAddPayment(droppedFile = null) {
  const today = new Date().toISOString().split('T')[0]
  addPaymentForm.value     = { date: today, description: '', amount: '', invoiceId: '', chargeTypeId: '', notes: '' }
  addPaymentError.value    = null
  paymentFlow.value        = droppedFile ? 'without_invoice' : null
  proofOfPaymentFile.value = droppedFile ?? null
  // Default person: tenant if on tenant tab, otherwise owner
  if (activeTab.value === 'tenant') {
    const tenant = tenantHistory.value.find(t => t.is_active)
    addPaymentPersonKey.value = tenant?.id ?? 'owner'
  } else {
    addPaymentPersonKey.value = 'owner'
  }
  // Ensure charge types are loaded (reuse the same list as Create Invoice)
  if (!chargeTypes.value.length) {
    try {
      const res = await api.get('/charge-types', { params: { is_active: true, _per_page: 100 } })
      chargeTypes.value = res.data.data ?? []
    } catch { /* ignore */ }
  }
  showAddPayment.value = true
}

async function submitAddPayment() {
  addPaymentSaving.value = true
  addPaymentError.value  = null
  try {
    const fd = new FormData()
    fd.append('estate_id',   unit.value?.estate_id ?? '')
    fd.append('unit_id',     unitId.value)
    fd.append('type',        'credit')
    fd.append('date',        addPaymentForm.value.date)
    fd.append('description', addPaymentForm.value.description)
    fd.append('amount',      String(parseFloat(addPaymentForm.value.amount)))
    if (addPaymentForm.value.invoiceId)    fd.append('invoice_id',     addPaymentForm.value.invoiceId)
    if (addPaymentForm.value.chargeTypeId) fd.append('charge_type_id', addPaymentForm.value.chargeTypeId)
    if (addPaymentForm.value.notes)        fd.append('notes',           addPaymentForm.value.notes)
    if (proofOfPaymentFile.value)        fd.append('proof_of_payment', proofOfPaymentFile.value)
    await api.post('/cashbook', fd)
    showAddPayment.value     = false
    proofOfPaymentFile.value = null
    await fetchAll()
  } catch (err) {
    addPaymentError.value = err?.response?.data?.message ?? 'Failed to record payment. Please try again.'
  } finally {
    addPaymentSaving.value = false
  }
}
</script>

<template>
  <div>

  <!-- Loading -->
  <div v-if="loading" class="flex items-center justify-center py-24">
    <div class="flex flex-col items-center gap-3 text-muted-foreground">
      <div class="animate-spin rounded-full h-8 w-8 border-2 border-muted border-t-primary" />
      <p class="text-sm">Loading unit…</p>
    </div>
  </div>

  <!-- Error -->
  <div v-else-if="error" class="flex items-center justify-center py-24">
    <div class="text-center space-y-2">
      <p class="text-destructive font-medium">{{ error }}</p>
      <AppButton variant="outline" size="sm" @click="fetchAll">Retry</AppButton>
    </div>
  </div>

  <!-- Not found -->
  <div v-else-if="!unit" class="flex items-center justify-center py-24">
    <p class="text-muted-foreground">Unit not found.</p>
  </div>

  <div v-else>
    <div class="space-y-6 pb-8">

      <!-- ── Header ─────────────────────────────────────────────────── -->
      <div class="flex items-center gap-3">
        <!-- Back -->
        <button class="p-2 rounded-lg hover:bg-muted transition-colors" @click="goBack">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
            <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
          </svg>
        </button>

        <!-- Title block -->
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <h1 class="font-body font-bold text-2xl text-foreground">Unit {{ unit.unit_number }}</h1>
            <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border gap-1.5 leading-tight', occupancyBadge.wrapClass]">
              <span :class="['w-1.5 h-1.5 rounded-full', occupancyBadge.dotClass]" />
              {{ occupancyBadge.label }}
            </span>
          </div>
          <p class="text-sm text-muted-foreground">{{ unit.estate?.name ?? '—' }}</p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <!-- Combined/Owner/Tenant tabs — only for tenant-occupied units -->
          <div v-if="occupancyKey === 'tenant'" class="flex bg-muted rounded-lg p-0.5">
            <button
              v-for="tab in ['combined', 'owner', 'tenant']"
              :key="tab"
              class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors capitalize"
              :class="activeTab === tab ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
              @click="activeTab = tab"
            >
              {{ tab === 'combined' ? 'Combined' : tab.charAt(0).toUpperCase() + tab.slice(1) }}
            </button>
          </div>
          <AppButton variant="outline" @click="openEditUnit">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
            </svg>
            Edit Unit
          </AppButton>
        </div>
      </div>

      <!-- ── Body grid ───────────────────────────────────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ╔══════════════ LEFT COLUMN ══════════════╗ -->
        <div class="space-y-4">

          <!-- Owner card -->
          <div v-if="activeTab !== 'tenant'" class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="flex flex-col space-y-1.5 p-6 pb-2">
              <h3 class="tracking-tight font-body font-semibold text-base">Owner</h3>
            </div>
            <div class="p-6 pt-0 space-y-3">
              <router-link
                v-if="unit.owner?.id"
                :to="{ name: 'owner-detail', params: { ownerId: unit.owner.id } }"
                class="font-semibold text-foreground hover:text-primary hover:underline underline-offset-2 transition-colors"
              >{{ unit.owner?.full_name ?? '—' }}</router-link>
              <p v-else class="font-semibold text-foreground">{{ unit.owner?.full_name ?? '—' }}</p>
              <div class="space-y-1.5 text-sm">
                <!-- Email row with copy -->
                <div class="group flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2 text-muted-foreground min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <span class="truncate">{{ unit.owner?.email ?? '—' }}</span>
                  </div>
                  <button
                    v-if="unit.owner?.email"
                    type="button"
                    class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 text-xs text-muted-foreground hover:text-primary px-1.5 py-0.5 rounded"
                    :title="copiedKey === 'owner-email' ? 'Copied!' : 'Copy email'"
                    @click.stop="copyText(unit.owner.email, 'owner-email')"
                  >
                    <svg v-if="copiedKey === 'owner-email'" class="w-3.5 h-3.5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  </button>
                </div>
                <!-- Phone row with copy -->
                <div class="group flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2 text-muted-foreground min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span class="truncate">{{ unit.owner?.phone ?? '—' }}</span>
                  </div>
                  <button
                    v-if="unit.owner?.phone"
                    type="button"
                    class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 text-xs text-muted-foreground hover:text-primary px-1.5 py-0.5 rounded"
                    :title="copiedKey === 'owner-phone' ? 'Copied!' : 'Copy phone'"
                    @click.stop="copyText(unit.owner.phone, 'owner-phone')"
                  >
                    <svg v-if="copiedKey === 'owner-phone'" class="w-3.5 h-3.5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  </button>
                </div>
              </div>
              <div class="pt-2 border-t border-border space-y-2">
                <div>
                  <p class="text-xs text-muted-foreground">ID Number</p>
                  <p class="text-sm text-foreground">{{ unit.owner?.id_number ?? '—' }}</p>
                </div>
                <div class="pt-2 border-t border-border">
                  <p class="text-xs text-muted-foreground">Monthly Levy</p>
                  <p class="text-lg font-bold font-body text-foreground">{{ fmtAmount(unit.effective_levy_amount) }}</p>
                </div>
                <AppButton variant="outline" size="sm" :full="true" @click="openSendMessage('owner')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>
                  </svg>
                  Send Message
                </AppButton>
              </div>
            </div>
          </div>

          <!-- Tenant card (includes lease document) -->
          <div v-if="unit.current_tenant && activeTab !== 'owner'" class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <!-- Hidden file input for lease upload -->
            <input ref="leaseFileCardInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="uploadLeaseFromCard" />

            <div class="flex flex-col space-y-1.5 p-6 pb-2">
              <h3 class="tracking-tight font-body font-semibold text-base">Tenant</h3>
            </div>
            <div class="p-6 pt-0 space-y-3">
              <router-link
                :to="{ name: 'tenant-detail', params: { estateId: estateId, unitId: unitId, tenantId: unit.current_tenant.id } }"
                class="font-semibold text-foreground hover:text-primary hover:underline underline-offset-2 transition-colors"
              >{{ unit.current_tenant.full_name }}</router-link>
              <div class="space-y-1.5 text-sm">
                <!-- Email row with copy -->
                <div class="group flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2 text-muted-foreground min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <span class="truncate">{{ unit.current_tenant.email ?? '—' }}</span>
                  </div>
                  <button
                    v-if="unit.current_tenant.email"
                    type="button"
                    class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 text-xs text-muted-foreground hover:text-primary px-1.5 py-0.5 rounded"
                    :title="copiedKey === 'tenant-email' ? 'Copied!' : 'Copy email'"
                    @click.stop="copyText(unit.current_tenant.email, 'tenant-email')"
                  >
                    <svg v-if="copiedKey === 'tenant-email'" class="w-3.5 h-3.5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  </button>
                </div>
                <!-- Phone row with copy -->
                <div class="group flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2 text-muted-foreground min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span class="truncate">{{ unit.current_tenant.phone ?? '—' }}</span>
                  </div>
                  <button
                    v-if="unit.current_tenant.phone"
                    type="button"
                    class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 text-xs text-muted-foreground hover:text-primary px-1.5 py-0.5 rounded"
                    :title="copiedKey === 'tenant-phone' ? 'Copied!' : 'Copy phone'"
                    @click.stop="copyText(unit.current_tenant.phone, 'tenant-phone')"
                  >
                    <svg v-if="copiedKey === 'tenant-phone'" class="w-3.5 h-3.5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  </button>
                </div>
                <!-- Lease dates -->
                <div class="flex items-center gap-2 text-muted-foreground">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>
                  </svg>
                  <span>{{ fmtDate(unit.current_tenant.lease_start) }} → {{ fmtDate(unit.current_tenant.lease_end) }}</span>
                </div>
              </div>
              <div class="pt-2 border-t border-border space-y-2">
                <div>
                  <p class="text-xs text-muted-foreground">Monthly Rent</p>
                  <p class="text-lg font-bold font-body text-foreground">{{ fmtAmount(unit.rent_amount) }}</p>
                </div>
                <AppButton variant="outline" size="sm" :full="true" @click="openSendMessage('tenant')">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>
                  </svg>
                  Send Message
                </AppButton>
              </div>

              <!-- Lease Document section (inside Tenant card) -->
              <div class="pt-2 border-t border-border space-y-2">
                <div class="flex items-center justify-between">
                  <p class="text-xs text-muted-foreground">Lease Document</p>
                  <button
                    v-if="unit.current_tenant.lease_document_url"
                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                    :disabled="leaseCardUploading"
                    @click="leaseFileCardInput?.click()"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                      <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
                    </svg>
                    Replace
                  </button>
                </div>

                <!-- Document exists -->
                <div v-if="unit.current_tenant.lease_document_url" class="space-y-2">
                  <div class="flex items-center gap-3 rounded-lg bg-muted/50 border border-border px-3 py-2.5">
                    <div class="w-8 h-8 rounded-lg bg-muted flex items-center justify-center shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground truncate">{{ unit.current_tenant.lease_document_name ?? 'Lease document' }}</p>
                      <p class="text-xs text-muted-foreground">Lease document</p>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <a
                      :href="unit.current_tenant.lease_document_url"
                      target="_blank"
                      download
                      class="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground hover:bg-muted transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                      </svg>
                      Download
                    </a>
                    <a
                      :href="unit.current_tenant.lease_document_url"
                      target="_blank"
                      class="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground hover:bg-muted transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14" rx="1"/>
                      </svg>
                    Print
                  </a>
                  <button
                    class="w-10 flex items-center justify-center rounded-lg border border-destructive/30 bg-card text-destructive hover:bg-destructive/5 transition-colors"
                    :disabled="leaseCardDeleting"
                    @click="deleteLeaseFromCard"
                  >
                    <svg v-if="!leaseCardDeleting" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                      <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                    </svg>
                    <div v-else class="w-3.5 h-3.5 border-2 border-destructive/40 border-t-destructive rounded-full animate-spin" />
                  </button>
                </div>
              </div>

              <!-- No document yet -->
              <div v-else class="text-center py-3 space-y-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40 mx-auto">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                <p class="text-xs text-muted-foreground">No lease document attached</p>
                <button
                  class="text-xs text-primary hover:underline transition-colors"
                  :disabled="leaseCardUploading"
                  @click="leaseFileCardInput?.click()"
                >
                  {{ leaseCardUploading ? 'Uploading…' : '+ Upload Document' }}
                </button>
              </div>
            </div>
          </div>
        </div>

          <!-- Last Tenant card (vacant unit with a prior tenant) -->
          <div v-if="lastTenant && activeTab !== 'owner'" class="rounded-lg border border-amber-200 bg-amber-50/40 text-card-foreground shadow-sm">
            <div class="flex flex-col space-y-1.5 p-6 pb-2">
              <div class="flex items-center justify-between">
                <h3 class="tracking-tight font-body font-semibold text-base">Last Tenant</h3>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                  <span class="w-1.5 h-1.5 rounded-full bg-muted-foreground/50"></span>
                  Moved Out
                </span>
              </div>
            </div>
            <div class="p-6 pt-0 space-y-3">
              <p class="font-medium text-foreground">{{ lastTenant.full_name }}</p>
              <div class="space-y-2 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
                    <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                  </svg>
                  <span>{{ lastTenant.email ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-muted-foreground">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                  </svg>
                  <span>{{ lastTenant.phone ?? '—' }}</span>
                </div>
                <div v-if="lastTenant.lease_start || lastTenant.lease_end" class="flex items-center gap-2 text-muted-foreground">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
                    <path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>
                  </svg>
                  <span>{{ fmtDate(lastTenant.lease_start) }} → {{ fmtDate(lastTenant.lease_end) }}</span>
                </div>
              </div>

              <!-- Move-out details -->
              <div class="pt-2 border-t border-amber-200/60 space-y-2">
                <div v-if="lastTenant.move_out_date" class="flex items-start gap-2 text-sm">
                  <span class="text-muted-foreground w-24 shrink-0">Moved out</span>
                  <span class="font-medium text-foreground">{{ fmtDate(lastTenant.move_out_date) }}</span>
                </div>
                <div v-if="lastTenant.move_out_reason" class="flex items-start gap-2 text-sm">
                  <span class="text-muted-foreground w-24 shrink-0">Reason</span>
                  <span class="font-medium text-foreground">{{ MOVE_OUT_REASON_LABEL[lastTenant.move_out_reason] ?? lastTenant.move_out_reason }}</span>
                </div>
                <div v-if="lastTenant.move_out_notes" class="flex items-start gap-2 text-sm">
                  <span class="text-muted-foreground w-24 shrink-0">Notes</span>
                  <span class="text-foreground leading-relaxed">{{ lastTenant.move_out_notes }}</span>
                </div>
                <p v-if="!lastTenant.move_out_date && !lastTenant.move_out_reason && !lastTenant.move_out_notes" class="text-xs text-muted-foreground italic">
                  No move-out details recorded yet.
                </p>
              </div>

              <!-- Lease document (read-only) -->
              <div class="pt-2 border-t border-amber-200/60 space-y-2">
                <p class="text-xs text-muted-foreground">Lease Document</p>
                <div v-if="lastTenant.lease_document_url" class="space-y-2">
                  <div class="flex items-center gap-3 rounded-lg bg-white/70 border border-amber-200/60 px-3 py-2.5">
                    <div class="w-8 h-8 rounded-lg bg-muted flex items-center justify-center shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-foreground truncate">{{ lastTenant.lease_document_name ?? 'Lease document' }}</p>
                      <p class="text-xs text-muted-foreground">Lease agreement</p>
                    </div>
                  </div>
                  <a
                    :href="lastTenant.lease_document_url"
                    target="_blank"
                    download
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground hover:bg-muted transition-colors w-full"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                    </svg>
                    Download Lease
                  </a>
                </div>
                <p v-else class="text-xs text-muted-foreground italic">No lease document attached.</p>
              </div>

              <AppButton variant="outline" size="sm" :full="true" @click="openEditMoveOut">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                  <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
                </svg>
                Edit Move-Out Info
              </AppButton>
            </div>
          </div>

          <!-- Charge Configuration card -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="flex flex-col space-y-1.5 p-6 pb-2">
              <h3 class="tracking-tight font-body font-semibold text-base">Monthly Charges</h3>
            </div>
            <div class="p-6 pt-0">
              <div v-if="filteredChargeConfigDisplay.length > 0" class="space-y-2">
                <div
                  v-for="(charge, i) in filteredChargeConfigDisplay"
                  :key="charge.name"
                  class="flex items-center justify-between py-2"
                  :class="i < filteredChargeConfigDisplay.length - 1 ? 'border-b border-border' : ''"
                >
                  <span class="text-sm text-foreground">{{ charge.name }}</span>
                  <span class="text-sm font-medium text-foreground">{{ fmtAmount(charge.amount) }}</span>
                </div>
              </div>
              <p v-else class="text-sm text-muted-foreground text-center py-4">No charges configured</p>
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
                  <p :class="['text-3xl font-bold font-body', balance < 0 ? 'text-destructive' : 'text-foreground']">
                    {{ fmtAmount(balance) }}
                  </p>
                </div>
                <div v-if="balance < 0" class="px-3 py-1 rounded-full bg-destructive/10 text-destructive text-xs font-medium">
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
                    { key: 'all',           label: 'All',     count: tabFilteredInvoices.length },
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
                    <td class="py-3 px-2 text-foreground">{{ resolvedBilledTo(inv) }}</td>
                    <td class="py-3 px-2 text-right font-medium text-foreground whitespace-nowrap">{{ fmtAmount(inv.amount) }}</td>
                    <!-- Email delivery tick indicator -->
                    <td class="py-3 px-2 text-center" :title="emailDeliveryTooltip(inv)">
                      <!-- Not sent: no tick -->
                      <span v-if="emailDeliveryStatus(inv) === 'none'" class="text-xs text-muted-foreground/40">—</span>
                      <!-- Sent: single gray tick -->
                      <span v-else-if="emailDeliveryStatus(inv) === 'sent'" class="inline-flex items-center" :title="emailDeliveryTooltip(inv)">
                        <svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-muted-foreground">
                          <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </span>
                      <!-- Delivered: double gray tick -->
                      <span v-else-if="emailDeliveryStatus(inv) === 'delivered'" class="inline-flex items-center gap-[-4px]" :title="emailDeliveryTooltip(inv)">
                        <svg width="15" height="8" viewBox="0 0 20 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-muted-foreground">
                          <path d="M1.5 5.5L5.5 9.5L14.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M5.5 5.5L9.5 9.5L18.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </span>
                      <!-- Opened: double blue tick -->
                      <span v-else-if="emailDeliveryStatus(inv) === 'opened'" class="inline-flex items-center" :title="emailDeliveryTooltip(inv)">
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

              <!-- Totals footer.
                   Billed/Paid come from the loaded invoice list (accurate for ≤10 invoices).
                   Credit and Outstanding come from the server-side unit record so they always
                   reflect ALL invoices + payments — keeping them consistent with Account Balance. -->
              <div v-if="tabFilteredInvoices.length > 0" class="mt-3 pt-3 flex items-center gap-0 text-sm flex-wrap">
                <div class="flex items-center gap-1.5 pr-4">
                  <span class="text-muted-foreground text-xs">Billed</span>
                  <span class="font-semibold text-foreground font-body">{{ fmtAmount(invoiceTotals.total) }}</span>
                </div>
                <div class="w-px h-3.5 bg-border mr-4" />
                <div class="flex items-center gap-1.5 pr-4">
                  <span class="text-muted-foreground text-xs">Paid</span>
                  <span class="font-semibold text-success font-body">{{ fmtAmount(invoiceTotals.paid) }}</span>
                </div>
                <!-- Credit on account — only shown when unallocated money exists.
                     Explains the difference between gross outstanding and Account Balance. -->
                <template v-if="serverCreditOnAccount > 0">
                  <div class="w-px h-3.5 bg-border mr-4" />
                  <div class="flex items-center gap-1.5 pr-4">
                    <span class="text-muted-foreground text-xs">Credit</span>
                    <span class="font-semibold text-success font-body">{{ fmtAmount(serverCreditOnAccount) }}</span>
                  </div>
                </template>
                <div class="w-px h-3.5 bg-border mr-4" />
                <!-- Outstanding always sourced from server — matches Account Balance exactly. -->
                <div class="flex items-center gap-1.5 pr-4">
                  <span class="text-muted-foreground text-xs">Outstanding</span>
                  <span :class="['font-semibold font-body', serverNetOutstanding > 0 ? 'text-destructive' : 'text-foreground']">{{ fmtAmount(serverNetOutstanding) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Payments Received section — drag a receipt here to attach it to a new payment -->
          <div
            class="rounded-lg border bg-card text-card-foreground shadow-sm transition-colors"
            :class="isDraggingOverPayments ? 'ring-2 ring-primary/40 bg-primary/5 border-primary/30' : ''"
            @dragover.prevent="onPaymentsDragOver"
            @dragleave="onPaymentsDragLeave"
            @drop.prevent="onPaymentsDrop"
          >
            <div class="flex flex-col space-y-1.5 p-6 pb-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                    <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                  </svg>
                  <h3 class="tracking-tight font-body font-semibold text-lg flex items-center gap-2">
                    Payments Received
                    <span v-if="isDraggingOverPayments" class="text-sm font-normal text-primary animate-pulse">Drop receipt to attach</span>
                  </h3>
                </div>
                <AppButton variant="outline" size="sm" @click="openAddPayment()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    { key: 'all',         label: 'All',         count: tabFilteredPayments.length },
                    { key: 'allocated',   label: 'Allocated',   count: tabFilteredPayments.filter(p => p.is_allocated).length },
                    { key: 'unallocated', label: 'Unallocated', count: tabFilteredPayments.filter(p => !p.is_allocated).length },
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
                    @click="goToCashbook(pay.id)"
                  >
                    <td class="py-3 px-2 text-foreground">{{ fmtDate(pay.date) }}</td>
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
                {{ paymentFilter === 'all' ? 'No payments recorded for this unit' : 'No ' + paymentFilter + ' payments' }}
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
              <div v-if="tabFilteredPayments.length > 0" class="mt-3 pt-3 flex items-center gap-0 text-sm flex-wrap">
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
          </div>

          <!-- Unallocated payments warning banner -->
          <div v-if="unallocatedCount > 0" class="flex items-center gap-3 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-warning shrink-0">
              <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
            </svg>
            <div class="flex-1">
              <p class="text-sm font-medium text-foreground">There {{ unallocatedCount === 1 ? 'is' : 'are' }} {{ unallocatedCount }} unallocated payment{{ unallocatedCount === 1 ? '' : 's' }} in the cashbook</p>
              <p class="text-xs text-muted-foreground">{{ unallocatedCount === 1 ? 'It' : 'One' }} may belong to this unit.</p>
            </div>
            <AppButton variant="outline" size="sm" @click="router.push({ name: 'cashbook', query: { allocation_status: 'unallocated' } })">Go to Cashbook</AppButton>
          </div>

          <!-- Tenant History section — hidden when viewing Owner tab -->
          <div v-if="activeTab !== 'owner'" class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="flex flex-col space-y-1.5 p-6 pb-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                  </svg>
                  <h3 class="tracking-tight font-body font-semibold text-lg">Tenant History</h3>
                </div>
                <!-- Move Out — shown when an active tenant exists -->
                <AppButton v-if="activeTenant" variant="danger" size="sm" @click="openMoveOut">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                  </svg>
                  Move Out
                </AppButton>
                <!-- Move In — shown when no active tenant -->
                <AppButton v-else variant="primary" size="sm" @click="showMoveIn = true">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>
                  </svg>
                  Move In
                </AppButton>
              </div>
            </div>
            <div class="p-6 pt-0">
              <div v-if="tenantHistory.length > 0" class="space-y-3">
                <div
                  v-for="t in tenantHistory"
                  :key="t.id"
                  class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                  :class="t.is_active
                    ? 'border-success/30 bg-success/5 hover:bg-success/10'
                    : 'border-border hover:bg-muted/50'"
                  @click="goToTenant(t)"
                >
                  <!-- Icon -->
                  <svg v-if="t.is_active" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-success mt-0.5 shrink-0">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground mt-0.5 shrink-0">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" x2="16" y1="11" y2="11"/>
                  </svg>
                  <!-- Details -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-medium text-foreground">{{ t.full_name }}</p>
                      <span v-if="t.is_active" class="px-1.5 py-px text-[10px] font-medium rounded bg-success/10 text-success border border-success/20">Current</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ fmtDate(t.lease_start) }} → {{ fmtDate(t.lease_end) }}</p>
                    <p class="text-xs text-muted-foreground">
                      Rent: {{ t.rent_amount != null ? fmtAmount(t.rent_amount) : '—' }}/month
                    </p>
                  </div>

                  <!-- Reinstate button — only on inactive tenants when no active tenant exists -->
                  <button
                    v-if="!t.is_active && !activeTenant"
                    class="shrink-0 flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 px-2.5 py-1.5 text-xs font-medium text-primary hover:bg-primary/10 transition-colors disabled:opacity-50"
                    :disabled="reinstatingId === t.id"
                    @click.stop="reinstateTenant(t)"
                  >
                    <div v-if="reinstatingId === t.id" class="w-3 h-3 border-2 border-primary/40 border-t-primary rounded-full animate-spin" />
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                    </svg>
                    {{ reinstatingId === t.id ? 'Reinstating…' : 'Reinstate' }}
                  </button>
                </div>
              </div>
              <p v-else class="text-sm text-muted-foreground text-center py-4">No tenant history for this unit.</p>
            </div>
          </div>

          <!-- Activity section -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="flex flex-col space-y-1.5 p-6 pb-3">
              <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                  <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>
                </svg>
                <h3 class="tracking-tight font-body font-semibold text-lg">Activity</h3>
              </div>
            </div>
            <div class="p-6 pt-0">

              <!-- Loading skeleton -->
              <div v-if="activitiesLoading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="flex gap-3">
                  <div class="w-2 h-2 rounded-full bg-muted mt-2 shrink-0 animate-pulse" />
                  <div class="flex-1 space-y-2 pb-4 border-b border-border last:border-0">
                    <div class="flex items-center justify-between gap-2">
                      <div class="h-4 w-40 rounded bg-muted animate-pulse" />
                      <div class="h-3 w-24 rounded bg-muted animate-pulse" />
                    </div>
                    <div class="h-3 w-28 rounded bg-muted animate-pulse" />
                  </div>
                </div>
              </div>

              <!-- Empty state -->
              <p v-else-if="filteredGroupedActivities.length === 0" class="text-sm text-muted-foreground text-center py-6">
                {{ activeTab === 'combined' ? 'No activity recorded yet. Changes and events for this unit will appear here.' : 'No activity recorded for this ' + activeTab + '.' }}
              </p>

              <!-- Timeline — grouped by batch_id -->
              <div v-else class="space-y-0">
                <div
                  v-for="(group, gIndex) in filteredGroupedActivities"
                  :key="group.batchId || group.entries[0].id"
                  class="flex gap-3 cursor-pointer rounded-md -mx-2 px-2 py-1 hover:bg-muted/50 transition-colors"
                  @click="openChangeDetails(group)"
                >
                  <!-- Timeline dot + line -->
                  <div class="flex flex-col items-center">
                    <div :class="['w-2.5 h-2.5 rounded-full mt-1.5 shrink-0 ring-2 ring-background', groupDotClass(group)]" />
                    <div
                      v-if="gIndex < filteredGroupedActivities.length - 1"
                      class="w-px flex-1 bg-border mt-1 mb-1"
                    />
                  </div>

                  <!-- Group content -->
                  <div class="flex-1 pb-5">

                    <!-- Single-entry group: event+badge on left, user+time on right (original layout) -->
                    <template v-if="group.entries.length === 1">
                      <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                          <span :class="['inline-flex items-center px-1.5 py-px rounded text-[10px] font-medium border leading-tight', categoryBadgeClass(group.entries[0].category)]">
                            {{ categoryLabel(group.entries[0].category) }} {{ eventAction(group.entries[0].event) }}
                          </span>
                        </div>
                        <div class="text-right shrink-0">
                          <p class="text-xs font-medium text-foreground">{{ group.entries[0].changed_by_name ?? '—' }}</p>
                          <p class="text-xs text-muted-foreground">{{ fmtActivityDate(group.entries[0].created_at) }}</p>
                        </div>
                      </div>
                      <p v-if="!shouldShowChanges(group.entries[0].event) && getLifecycleSummary(group.entries[0])" class="mt-1 text-xs text-muted-foreground">
                        {{ getLifecycleSummary(group.entries[0]) }}
                      </p>
                      <ul v-if="shouldShowChanges(group.entries[0].event) && group.entries[0].changes?.length > 0" class="mt-2 space-y-1">
                        <li v-for="change in group.entries[0].changes" :key="change.field" class="text-xs text-muted-foreground">
                          <span class="font-medium text-foreground">{{ change.field }}:</span>
                          <span v-if="change.old != null" class="line-through mx-1 text-destructive/70">{{ change.old }}</span>
                          <span v-else class="mx-1 italic">empty</span>
                          <span class="text-muted-foreground mx-0.5">→</span>
                          <span v-if="change.new != null" class="text-success font-medium">{{ change.new }}</span>
                          <span v-else class="italic">empty</span>
                        </li>
                      </ul>
                    </template>

                    <!-- Multi-entry group: user+time once at top-right, entries stacked below -->
                    <template v-else>
                      <div class="flex items-start justify-end mb-2">
                        <div class="text-right shrink-0">
                          <p class="text-xs font-medium text-foreground">{{ group.entries[0].changed_by_name ?? '—' }}</p>
                          <p class="text-xs text-muted-foreground">{{ fmtActivityDate(group.entries[0].created_at) }}</p>
                        </div>
                      </div>
                      <div
                        v-for="(log, eIndex) in group.entries"
                        :key="log.id"
                        :class="['', eIndex < group.entries.length - 1 ? 'mb-3 pb-3 border-b border-border/50' : '']"
                      >
                        <div class="flex items-center gap-2 flex-wrap">
                          <span :class="['inline-flex items-center px-1.5 py-px rounded text-[10px] font-medium border leading-tight', categoryBadgeClass(log.category)]">
                            {{ categoryLabel(log.category) }} {{ eventAction(log.event) }}
                          </span>
                        </div>
                        <p v-if="!shouldShowChanges(log.event) && getLifecycleSummary(log)" class="mt-1 text-xs text-muted-foreground">
                          {{ getLifecycleSummary(log) }}
                        </p>
                        <ul v-if="shouldShowChanges(log.event) && log.changes?.length > 0" class="mt-2 space-y-1">
                          <li v-for="change in log.changes" :key="change.field" class="text-xs text-muted-foreground">
                            <span class="font-medium text-foreground">{{ change.field }}:</span>
                            <span v-if="change.old != null" class="line-through mx-1 text-destructive/70">{{ change.old }}</span>
                            <span v-else class="mx-1 italic">empty</span>
                            <span class="text-muted-foreground mx-0.5">→</span>
                            <span v-if="change.new != null" class="text-success font-medium">{{ change.new }}</span>
                            <span v-else class="italic">empty</span>
                          </li>
                        </ul>
                      </div>
                    </template>

                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>
        <!-- ╚══════════════ END RIGHT COLUMN ══════════════╝ -->

      </div>
    </div>

    <!-- ── Send Message modal ────────────────────────────────────────── -->
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
        <!-- Recipient -->
        <div v-if="messageRecipient" class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-border">
          <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
            {{ messageRecipient.initials }}
          </div>
          <div>
            <p class="text-sm font-medium text-foreground">{{ messageRecipient.name }}</p>
            <p class="text-xs text-muted-foreground">{{ messageRecipient.email }} · <span class="capitalize">{{ messageRecipient.role }}</span></p>
          </div>
        </div>

        <!-- Error -->
        <AppAlert v-if="messageError" variant="danger">{{ messageError }}</AppAlert>

        <!-- Template -->
        <div>
          <label class="text-sm font-medium text-foreground mb-1.5 block">Template</label>
          <AppSelect
            v-model="messageForm.template"
            :options="activeTemplates"
            placeholder="Select a template..."
            @change="onTemplateChange"
          />
        </div>

        <!-- Subject -->
        <AppInput v-model="messageForm.subject" label="Subject" placeholder="Email subject" />

        <!-- Message -->
        <AppInput v-model="messageForm.body" type="textarea" label="Message" :rows="8" placeholder="Type your message..." />
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="messageSending" @click="showSendMessage = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="!messageForm.subject.trim() || messageSending" :loading="messageSending" @click="sendEmail">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>
          </svg>
          Send Email
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Edit Unit modal ──────────────────────────────────────────────── -->
    <AppModal :show="showEditUnit" :title="`Edit Unit ${editForm.unitNumber}`" size="md" @close="showEditUnit = false">
      <div class="space-y-4">

        <!-- Unit Number + Occupancy Type -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="editForm.unitNumber" label="Unit Number" required />
          <AppSelect v-model="editForm.occupancy" label="Occupancy Type" :options="occupancyOptions" required />
        </div>

        <!-- Levy Override -->
        <AppInput v-model="editForm.levyOverride" label="Levy Override" type="number" placeholder="Use default levy" />

        <!-- Owner Details -->
        <div class="border-t border-border pt-4">
          <p class="text-sm font-medium text-foreground mb-3">Owner Details</p>
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="editForm.owner.name"     label="Full Name" size="sm" required />
            <AppInput v-model="editForm.owner.email"    label="Email"     size="sm" type="email" required />
            <AppInput v-model="editForm.owner.phone"    label="Phone"     size="sm" />
            <AppInput v-model="editForm.owner.idNumber" label="ID Number" size="sm" />
          </div>
        </div>

        <!-- Tenant Details -->
        <div v-if="showTenantSection" class="border-t border-border pt-4">
          <!-- Toggle header (owner/vacant units) -->
          <div v-if="editForm.occupancy !== 'tenant_occupied'" class="flex items-center justify-between mb-3">
            <div>
              <p class="text-sm font-medium text-foreground">Tenant Details</p>
              <p class="text-xs text-muted-foreground">Toggle on if this unit also has a tenant</p>
            </div>
            <button type="button" class="text-primary transition-colors" @click="editForm.showTenant = !editForm.showTenant">
              <!-- Toggle ON -->
              <svg v-if="editForm.showTenant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
              </svg>
              <!-- Toggle OFF -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
              </svg>
            </button>
          </div>
          <!-- Direct heading (tenant-occupied units) -->
          <p v-else class="text-sm font-medium text-foreground mb-3">Tenant Details</p>

          <!-- Tenant fields (shown when toggled ON or tenant-occupied) -->
          <div v-if="showTenantFields" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="editForm.tenant.name"       label="Full Name"   size="sm" placeholder="Tenant name" required />
              <AppInput v-model="editForm.tenant.email"      label="Email"       size="sm" type="email" placeholder="Email address" required />
              <AppInput v-model="editForm.tenant.phone"      label="Phone"       size="sm" placeholder="+27 ..." />
              <AppInput v-model="editForm.tenant.rent"       label="Rent Amount" size="sm" type="number" placeholder="Monthly rent" required />
              <AppDatePicker v-model="editForm.tenant.leaseStart" label="Lease Start" placeholder="Select date..." required />
              <AppDatePicker v-model="editForm.tenant.leaseEnd" label="Lease End" placeholder="Select date..." />
            </div>
            <!-- Lease Document upload -->
            <div>
              <label class="block text-xs text-muted-foreground mb-1">Lease Document</label>
              <!-- Hidden file input -->
              <input
                ref="leaseFileEditInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                class="hidden"
                @change="e => leaseFileEdit = e.target.files?.[0] ?? null"
              />
              <!-- Show selected file or existing document -->
              <div v-if="leaseFileEdit || unit?.current_tenant?.lease_document_url" class="space-y-2">
                <div class="flex items-center gap-2 rounded-lg bg-muted/50 border border-border px-3 py-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground shrink-0">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                  </svg>
                  <span class="flex-1 truncate text-xs text-foreground">
                    {{ leaseFileEdit?.name ?? unit?.current_tenant?.lease_document_name ?? 'Lease document' }}
                  </span>
                  <button
                    type="button"
                    class="text-xs text-primary hover:underline shrink-0"
                    @click="leaseFileEdit = null; leaseFileEditInput?.click()"
                  >Replace</button>
                </div>
                <p v-if="leaseFileEdit" class="text-xs text-success">New file selected — will upload on save</p>
              </div>
              <!-- Drop zone (no file yet) -->
              <div v-else class="border border-dashed border-border rounded-lg p-4 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground mx-auto mb-1.5">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                <p class="text-xs text-muted-foreground mb-1.5">Drop lease PDF here or click to browse</p>
                <AppButton variant="outline" size="sm" @click.prevent="leaseFileEditInput?.click()">Choose File</AppButton>
              </div>
            </div>
          </div>
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" @click="showEditUnit = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="editSaving" @click="saveEditUnit">
          {{ editSaving ? 'Saving…' : 'Save Changes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Create Invoice modal ──────────────────────────────────────── -->
    <AppModal :show="showCreateInvoice" title="Create Invoice" size="md" @close="showCreateInvoice = false">
      <div class="space-y-4">

        <!-- Unit info banner -->
        <div class="flex items-start gap-3 rounded-lg bg-muted/50 border border-border px-4 py-3 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground mt-0.5 shrink-0">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
          </svg>
          <div class="space-y-0.5">
            <p class="font-medium text-foreground">Unit {{ unit?.unit_number }} · {{ unit?.estate?.name }}</p>
            <p class="text-xs text-muted-foreground">
              Owner: {{ unit?.owner?.full_name ?? '—' }}
              <span v-if="unit?.current_tenant"> · Tenant: {{ unit.current_tenant.full_name }}</span>
            </p>
          </div>
        </div>

        <!-- Error -->
        <AppAlert v-if="createInvoiceError" variant="danger">{{ createInvoiceError }}</AppAlert>

        <!-- Bill To -->
        <AppSelect
          v-model="createInvoiceForm.billTo"
          label="Bill To"
          :options="billToOptions"
          required
          @change="onCreateBillToChange"
        />

        <!-- Charge Type -->
        <AppSelect
          v-model="createInvoiceForm.chargeTypeId"
          label="Charge Type"
          :options="filteredChargeTypeOptions"
          placeholder="Select charge type..."
          required
          @change="onCreateChargeTypeChange"
        />

        <!-- Billing Period + Amount -->
        <div class="grid grid-cols-2 gap-4">
          <AppDatePicker
            v-model="createInvoiceForm.billingPeriod"
            label="Billing Period"
            mode="month"
            placeholder="Select month..."
            required
            @update:modelValue="onCreatePeriodChange"
          />
          <AppInput
            v-model="createInvoiceForm.amount"
            label="Amount"
            type="number"
            placeholder="0.00"
            required
          />
        </div>

        <!-- Due Date -->
        <AppDatePicker
          v-model="createInvoiceForm.dueDate"
          label="Due Date"
          placeholder="Select due date..."
          required
        />

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="createInvoiceSaving" @click="showCreateInvoice = false">Cancel</AppButton>
        <AppButton
          variant="primary"
          :disabled="createInvoiceSaving || !createInvoiceForm.chargeTypeId || !createInvoiceForm.amount"
          :loading="createInvoiceSaving"
          @click="submitCreateInvoice"
        >
          {{ createInvoiceSaving ? 'Creating…' : 'Create Invoice' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Move Out Confirmation modal ──────────────────────────────── -->
    <AppModal :show="showMoveOut" title="Move Out Tenant" @close="showMoveOut = false">
      <div class="space-y-4">
        <!-- Who is being moved out -->
        <div class="rounded-md bg-muted/50 border border-border px-4 py-3">
          <p class="text-sm text-foreground">
            Moving out <span class="font-semibold">{{ activeTenant?.full_name }}</span> from Unit <span class="font-semibold">{{ unit?.unit_number }}</span>.
          </p>
          <p class="text-xs text-muted-foreground mt-0.5">The tenant record will be archived and the unit will become vacant.</p>
        </div>

        <!-- Optional fields -->
        <div class="space-y-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-fg">Move Out Date (optional)</label>
            <AppDatePicker
              v-model="moveOutForm.date"
              placeholder="Select date..."
            />
          </div>
          <AppSelect
            v-model="moveOutForm.reason"
            label="Reason for Moving Out (optional)"
            placeholder="Select a reason..."
            :options="MOVE_OUT_REASONS"
          />
          <AppInput
            v-model="moveOutForm.notes"
            type="textarea"
            :rows="3"
            label="Additional Notes (optional)"
            placeholder="Any additional context about this move-out..."
          />
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" :disabled="moveOutSaving" @click="showMoveOut = false">Cancel</AppButton>
        <AppButton variant="danger" :disabled="moveOutSaving" :loading="moveOutSaving" @click="confirmMoveOut">
          {{ moveOutSaving ? 'Moving out…' : 'Confirm Move Out' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Edit Move-Out Info modal ─────────────────────────────────────── -->
    <AppModal :show="showEditMoveOut" title="Edit Move-Out Info" @close="showEditMoveOut = false">
      <div class="space-y-4">
        <div class="rounded-md bg-muted/50 border border-border px-4 py-3">
          <p class="text-sm text-foreground">
            Updating move-out details for <span class="font-semibold">{{ lastTenant?.full_name }}</span>.
          </p>
          <p class="text-xs text-muted-foreground mt-0.5">You can add or update this information at any time after the tenant has moved out.</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-fg">Move Out Date</label>
          <AppDatePicker v-model="editMoveOutForm.date" placeholder="Select date..." />
        </div>
        <AppSelect
          v-model="editMoveOutForm.reason"
          label="Reason for Moving Out"
          placeholder="Select a reason..."
          :options="MOVE_OUT_REASONS"
        />
        <AppInput
          v-model="editMoveOutForm.notes"
          type="textarea"
          :rows="3"
          label="Additional Notes"
          placeholder="Any additional context, e.g. inspection outcome, outstanding items..."
        />
      </div>
      <template #footer>
        <AppButton variant="outline" :disabled="editMoveOutSaving" @click="showEditMoveOut = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="editMoveOutSaving" :loading="editMoveOutSaving" @click="saveEditMoveOut">
          {{ editMoveOutSaving ? 'Saving…' : 'Save Changes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Move In New Tenant modal ───────────────────────────────────── -->
    <AppModal :show="showMoveIn" title="Move In New Tenant" @close="showMoveIn = false">
      <div class="grid grid-cols-2 gap-4">
        <AppInput v-model="moveInForm.name"       label="Full Name"    placeholder="Tenant name" required />
        <AppInput v-model="moveInForm.email"      label="Email"        type="email" placeholder="Email address" required />
        <AppInput v-model="moveInForm.phone"      label="Phone"        placeholder="+27 ..." />
        <AppInput v-model="moveInForm.rent"       label="Monthly Rent" type="number" placeholder="e.g. 9500" required />
        <AppDatePicker v-model="moveInForm.leaseStart" label="Lease Start" placeholder="Select date..." required />
        <AppDatePicker v-model="moveInForm.leaseEnd" label="Lease End" placeholder="Select date..." />
      </div>
      <template #footer>
        <AppButton variant="outline" @click="showMoveIn = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="moveInSaving" @click="confirmMoveIn">
          {{ moveInSaving ? 'Saving…' : 'Confirm Move In' }}
        </AppButton>
      </template>
    </AppModal>

  </div><!-- end v-else -->

  <!-- ── Activity Details Modal ────────────────────────────────────────── -->
  <AppModal
    :show="showChangeDetails"
    title="Activity Details"
    size="md"
    @close="showChangeDetails = false"
  >
    <div v-if="selectedLogGroup" class="space-y-5">

      <!-- One section per log entry in the group -->
      <div
        v-for="(log, idx) in selectedLogGroup.entries"
        :key="log.id"
        :class="idx < selectedLogGroup.entries.length - 1 ? 'pb-5 border-b border-border' : ''"
      >
        <!-- Event title + category badge -->
        <div class="flex items-center gap-2 flex-wrap mb-2">
          <span :class="['inline-flex items-center px-1.5 py-px rounded text-[10px] font-medium border leading-tight', categoryBadgeClass(log.category)]">
            {{ categoryLabel(log.category) }} {{ eventAction(log.event) }}
          </span>
        </div>
        <p v-if="!shouldShowChanges(log.event) && getLifecycleSummary(log)" class="text-sm text-muted-foreground mb-4">
          {{ getLifecycleSummary(log) }}
        </p>

        <!-- Changes table — only for update events, not lifecycle events -->
        <template v-if="shouldShowChanges(log.event) && log.changes?.length > 0">
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Changes</p>
          <div class="rounded-md border border-border divide-y divide-border bg-muted/30">
            <div
              v-for="change in log.changes"
              :key="change.field"
              class="flex items-start gap-3 px-4 py-2.5 text-sm"
            >
              <span class="font-medium text-foreground shrink-0 min-w-[100px]">{{ change.field }}</span>
              <div class="flex items-center gap-2 flex-wrap min-w-0">
                <span v-if="change.old != null" class="line-through text-destructive/70">{{ change.old }}</span>
                <span v-else class="italic text-muted-foreground">empty</span>
                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
                <span v-if="change.new != null" class="text-success font-medium">{{ change.new }}</span>
                <span v-else class="italic text-muted-foreground">empty</span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Footer: changed by + date/time -->
      <div class="pt-1 border-t border-border grid grid-cols-2 gap-4">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Changed by</p>
          <p class="text-sm font-semibold text-foreground">{{ selectedLogGroup.entries[0].changed_by_name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Date &amp; Time</p>
          <p class="text-sm font-semibold text-foreground">{{ fmtActivityDate(selectedLogGroup.entries[0].created_at) }}</p>
        </div>
      </div>

    </div>
    <template #footer>
      <AppButton variant="outline" @click="showChangeDetails = false">Close</AppButton>
    </template>
  </AppModal>

  <!-- ── Add Payment modal ──────────────────────────────────────────────── -->
  <AppModal :show="showAddPayment" title="Add Payment" size="sm" @close="showAddPayment = false">
    <div class="space-y-4">

      <!-- With Invoice flow: static card that updates as invoice is selected -->
      <div v-if="paymentFlow === 'with_invoice' && paymentPersonCard" class="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold shrink-0">
          {{ paymentPersonCard.initials }}
        </div>
        <div>
          <p class="text-sm font-semibold text-foreground leading-tight">{{ paymentPersonCard.name }}</p>
          <p class="text-xs text-muted-foreground">{{ paymentPersonCard.role }} · Unit {{ paymentPersonCard.unit }}</p>
        </div>
      </div>

      <!-- ① Flow chooser -->
      <div v-if="paymentFlow === null" class="grid grid-cols-2 gap-3">
        <button
          type="button"
          class="flex flex-col items-start gap-3 p-4 rounded-lg border-2 border-border bg-card hover:border-primary hover:bg-primary/5 transition-all text-left group"
          @click="paymentFlow = 'without_invoice'"
        >
          <div class="w-9 h-9 rounded-lg bg-muted flex items-center justify-center group-hover:bg-primary/10 transition-colors">
            <!-- receipt / cash icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground group-hover:text-primary transition-colors">
              <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
              <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-foreground">Without Invoice</p>
            <p class="text-xs text-muted-foreground mt-0.5 leading-snug">Advance or unlinked payment</p>
          </div>
        </button>
        <button
          type="button"
          class="flex flex-col items-start gap-3 p-4 rounded-lg border-2 border-border bg-card hover:border-primary hover:bg-primary/5 transition-all text-left group"
          @click="paymentFlow = 'with_invoice'"
        >
          <div class="w-9 h-9 rounded-lg bg-muted flex items-center justify-center group-hover:bg-primary/10 transition-colors">
            <!-- file-text / invoice icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground group-hover:text-primary transition-colors">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <line x1="10" y1="9" x2="8" y2="9"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-foreground">With Invoice</p>
            <p class="text-xs text-muted-foreground mt-0.5 leading-snug">Link to an outstanding invoice</p>
          </div>
        </button>
      </div>

      <!-- ② Without Invoice form -->
      <template v-else-if="paymentFlow === 'without_invoice'">
        <!-- Person selector — who is making this payment? -->
        <div>
          <p class="text-xs font-medium text-muted-foreground mb-1.5">Payment from</p>
          <div class="space-y-2">
            <button
              v-for="person in addPaymentPersonOptions"
              :key="person.key"
              type="button"
              :class="[
                'w-full flex items-center gap-3 p-3 rounded-lg border-2 transition-all text-left',
                addPaymentPersonKey === person.key
                  ? 'border-primary bg-primary/5'
                  : 'border-border hover:border-primary/40'
              ]"
              @click="addPaymentPersonKey = person.key"
            >
              <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold shrink-0">
                {{ person.initials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-foreground leading-tight truncate">{{ person.name }}</p>
                <p class="text-xs text-muted-foreground">{{ person.role }}</p>
              </div>
              <svg v-if="addPaymentPersonKey === person.key" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary shrink-0"><path d="M20 6 9 17l-5-5"/></svg>
            </button>
          </div>
        </div>
        <AppSelect
          v-model="addPaymentForm.chargeTypeId"
          label="Charge Type (optional)"
          :options="addPaymentChargeTypeOptions"
          placeholder="Select charge type…"
        />
        <AppDatePicker v-model="addPaymentForm.date" label="Payment Date" placeholder="Select date" />
        <AppInput v-model="addPaymentForm.description" label="Description" placeholder="e.g. Water Recovery — April 2026" />
        <AppInput v-model="addPaymentForm.amount" label="Amount" type="number" placeholder="0.00" />
        <AppInput
          v-model="addPaymentForm.notes"
          label="Notes (optional)"
          type="textarea"
          placeholder="e.g. Advance payment — May and June levies"
        />
        <!-- Proof of Payment -->
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
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-muted-foreground mb-2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p class="text-sm text-muted-foreground">Drop file here or <span class="text-primary font-medium">browse</span></p>
            <p class="text-xs text-muted-foreground mt-0.5">PDF, JPG, PNG — max 10 MB</p>
          </div>
          <div v-else class="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary shrink-0">
              <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground truncate">{{ proofOfPaymentFile.name }}</p>
              <p class="text-xs text-muted-foreground">{{ formatFileSize(proofOfPaymentFile.size) }}</p>
            </div>
            <button type="button" class="text-muted-foreground hover:text-destructive transition-colors" @click="proofOfPaymentFile = null">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="handleProofChange" />
        </div>
      </template>

      <!-- ③ With Invoice form -->
      <template v-else-if="paymentFlow === 'with_invoice'">
        <AppSelect
          v-model="addPaymentForm.invoiceId"
          label="Invoice"
          :options="withInvoiceOptions"
          placeholder="Select outstanding invoice…"
        />
        <!-- Auto-fill confirmation row -->
        <div v-if="addPaymentForm.invoiceId" class="flex items-start gap-2 px-3 py-2 rounded-md bg-primary/5 border border-primary/20 text-xs text-muted-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mt-0.5 shrink-0"><path d="M20 6 9 17l-5-5"/></svg>
          <span>{{ addPaymentForm.description }}</span>
        </div>
        <AppDatePicker v-model="addPaymentForm.date" label="Payment Date" placeholder="Select date" />
        <AppInput v-model="addPaymentForm.amount" label="Amount" type="number" placeholder="0.00" />
        <!-- Proof of Payment -->
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
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-muted-foreground mb-2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p class="text-sm text-muted-foreground">Drop file here or <span class="text-primary font-medium">browse</span></p>
            <p class="text-xs text-muted-foreground mt-0.5">PDF, JPG, PNG — max 10 MB</p>
          </div>
          <div v-else class="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary shrink-0">
              <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground truncate">{{ proofOfPaymentFile.name }}</p>
              <p class="text-xs text-muted-foreground">{{ formatFileSize(proofOfPaymentFile.size) }}</p>
            </div>
            <button type="button" class="text-muted-foreground hover:text-destructive transition-colors" @click="proofOfPaymentFile = null">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="handleProofChange" />
        </div>
      </template>

      <p v-if="addPaymentError" class="text-sm text-destructive">{{ addPaymentError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="showAddPayment = false">Cancel</AppButton>
      <AppButton v-if="paymentFlow !== null" variant="outline" @click="backToFlowChooser">← Back</AppButton>
      <AppButton
        v-if="paymentFlow !== null"
        variant="primary"
        :disabled="addPaymentSaving || !isAddPaymentValid"
        @click="submitAddPayment"
      >
        {{ addPaymentSaving ? 'Saving…' : 'Record Payment' }}
      </AppButton>
    </template>
  </AppModal>

  </div><!-- end root wrapper -->
</template>
