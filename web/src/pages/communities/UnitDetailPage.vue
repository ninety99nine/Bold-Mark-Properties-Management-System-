<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi.js'
import { useToast } from '@/composables/useToast'
import { useCountryStore } from '@/stores/country'
import { useAuthStore } from '@/stores/auth'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import SendEmailModal from '@/components/common/SendEmailModal.vue'

const router       = useRouter()
const route        = useRoute()
const countryStore = useCountryStore()
const authStore    = useAuthStore()
const { error: toastError } = useToast()

const communityId = computed(() => route.params.communityId)
const unitId      = computed(() => route.params.unitId)

// ── Tabs ──────────────────────────────────────────────────────────────
const TABS = [
  { key: 'details',       label: 'Unit Details'  },
  { key: 'finances',      label: 'Finances'      },
  { key: 'communication', label: 'Communication' },
  { key: 'offences',      label: 'Offences'      },
  { key: 'tasks',         label: 'Tasks'         },
  { key: 'documents',     label: 'Documents'     },
]
const activeTab = computed(() => TABS.some(t => t.key === route.query.tab) ? route.query.tab : 'details')
function setTab(key) {
  router.replace({ query: { ...route.query, tab: key } })
}

// ── Data ──────────────────────────────────────────────────────────────
const loading   = ref(true)
const error     = ref(null)
const unit      = ref(null)
const invoices  = ref([])
const payments  = ref([])
const communications = ref([])
const tasks          = ref([])
const offences       = ref([])
const documents      = ref([])
const staff          = ref([])

async function fetchAll() {
  loading.value = true
  error.value   = null
  try {
    const [unitRes, invRes, cashRes, commRes, offRes, taskRes, docRes] = await Promise.all([
      api.get(`/communities/${communityId.value}/units/${unitId.value}`),
      api.get('/invoices', { params: { unit_id: unitId.value, _per_page: 200 } }),
      api.get('/cashbook', { params: { unit_id: unitId.value, type: 'credit', _per_page: 200 } }),
      api.get(`/communities/${communityId.value}/units/${unitId.value}/communications`, { params: { _per_page: 100 } }),
      api.get(`/communities/${communityId.value}/units/${unitId.value}/offences`),
      api.get(`/communities/${communityId.value}/units/${unitId.value}/tasks`),
      api.get(`/communities/${communityId.value}/units/${unitId.value}/documents`),
    ])
    unit.value     = unitRes.data.data
    invoices.value = invRes.data.data ?? []
    payments.value = cashRes.data.data ?? []
    communications.value = commRes.data.data ?? []
    offences.value = offRes.data.data ?? []
    tasks.value    = taskRes.data.data ?? []
    documents.value = docRes.data.data ?? []
    // Staff list for the task Assignee picker (best-effort — non-fatal if it fails).
    api.get('/users', { params: { _per_page: 200 } }).then(r => { staff.value = r.data.data ?? [] }).catch(() => {})
  } catch (e) {
    error.value = 'Failed to load unit.'
  } finally {
    loading.value = false
  }
}
onMounted(fetchAll)

// ── Derived ───────────────────────────────────────────────────────────
const owner        = computed(() => unit.value?.owner ?? null)
const occupant     = computed(() => unit.value?.current_occupant ?? null)
const occupants    = computed(() => unit.value?.occupants ?? [])
const customerCode = computed(() => unit.value?.customer_code || '')

const ENTITY_LABELS = {
  individual: 'Individual', company: 'Company', trust: 'Trust',
  close_corporation: 'Close Corporation', other: 'Other',
}
function entityLabel(t) { return ENTITY_LABELS[t] || 'Individual' }

const occupancyLabel = computed(() =>
  occupant.value ? 'Occupant Occupied' : (unit.value?.occupancy_type === 'vacant' ? 'Vacant' : 'Owner Occupied')
)

// Collection-status badge on the Customer card (WeConnectU "Handed Over" /
// "Letter of Demand" / …), driven by the unit's collection_status field.
const COLLECTION_STATUSES = [
  { value: 'none',             label: 'None' },
  { value: 'reminder',         label: 'Reminder' },
  { value: 'first_notice',     label: '1st Notice' },
  { value: 'second_notice',    label: '2nd Notice' },
  { value: 'final_notice',     label: 'Final Notice' },
  { value: 'letter_of_demand', label: 'Letter of Demand' },
  { value: 'handed_over',      label: 'Handed Over' },
  { value: 'paid',             label: 'Balance Paid' },
]
const COLLECTION_BADGES = {
  reminder:         { label: 'Reminder',         class: 'bg-amber-100 text-amber-700' },
  first_notice:     { label: '1st Notice',       class: 'bg-[#e2645a] text-white' },
  second_notice:    { label: '2nd Notice',       class: 'bg-[#e2645a] text-white' },
  final_notice:     { label: 'Final Notice',     class: 'bg-[#3a3f4b] text-white' },
  letter_of_demand: { label: 'Letter of Demand', class: 'bg-[#3a3f4b] text-white' },
  handed_over:      { label: 'Handed Over',       class: 'bg-[#f6d0c0] text-[#8a4b2f]' },
  paid:             { label: 'Balance Paid',      class: 'bg-emerald-100 text-emerald-700' },
}
const statusBadge = computed(() => COLLECTION_BADGES[unit.value?.collection_status] || null)

// Owner cards from the unit's real owner records (WeConnectU multi-owner).
const ownerCards = computed(() =>
  (unit.value?.owners ?? []).map(o => ({
    id: o.id, name: o.full_name, entity: entityLabel(o.entity_type),
    email: o.email, phone: o.phone, primary: !!o.is_primary,
    verified: !!o.user_verified, displayName: o.user_display_name || o.full_name,
    raw: o,
  }))
)

function fmt(amount) {
  const n = Number(amount || 0)
  return (n < 0 ? '-' : '') + countryStore.formatCurrency(Math.abs(n))
}
function fmtPq(v) { return v == null ? '0.0000' : Number(v).toFixed(4) }
function today() { return new Date().toISOString().slice(0, 10) }

// ── Finances statement (Date / Source / Description / Debit / Credit / Balance) ──
const dateFrom = ref('')
const dateTo   = ref('')
const showLineItems = ref(false)

// All statement events (invoices = debit, payments = credit), unsorted.
// Builds the "Allocated by {name} on {DD/MM/YYYY HH:MM:SS}" tooltip payload for a payment.
function allocationInfo(p) {
  const raw = p.allocated_at || p.created_at
  if (!raw) return null
  // raw is 'YYYY-MM-DD HH:MM:SS' (or ISO 'YYYY-MM-DDTHH:MM:SS')
  const [datePart = '', timePartRaw = ''] = raw.replace('T', ' ').split(' ')
  const [y, m, d] = datePart.split('-')
  const timePart = (timePartRaw || '00:00:00').slice(0, 8).padEnd(8, ':00')
  const at = y && m && d ? `${d}/${m}/${y} ${timePart}` : raw
  return { by: p.allocated_by_name || null, at }
}

const statementEvents = computed(() => {
  const rows = []
  for (const inv of invoices.value) {
    rows.push({
      date: (inv.billing_period || inv.due_date || inv.created_at || '').slice(0, 10),
      source: 'Invoice', description: inv.invoice_number, invoiceId: inv.id,
      debit: Number(inv.amount || 0), credit: 0, info: null,
    })
  }
  for (const p of payments.value) {
    rows.push({
      date: (p.date || p.created_at || '').slice(0, 10),
      source: p.ledger?.name || 'Receipt', description: p.description || 'Payment received', invoiceId: null,
      debit: 0, credit: Number(p.amount || 0),
      info: allocationInfo(p),
    })
  }
  return rows
})

// Opening "Balance b/f" = debits − credits strictly before the "from" date.
const openingBalance = computed(() => {
  if (!dateFrom.value) return 0
  let bal = 0
  for (const e of statementEvents.value) {
    if ((e.date || '') < dateFrom.value) bal += e.debit - e.credit
  }
  return bal
})

const statement = computed(() => {
  const filtered = statementEvents.value.filter(r => {
    if (dateFrom.value && (r.date || '') < dateFrom.value) return false
    if (dateTo.value && (r.date || '') > dateTo.value) return false
    return true
  }).sort((a, b) => (a.date || '').localeCompare(b.date || ''))

  let bal = openingBalance.value
  return filtered.map(r => {
    bal += r.debit - r.credit
    return { ...r, balance: bal }
  })
})

const totals = computed(() => {
  const debit  = statement.value.reduce((s, r) => s + r.debit, 0)
  const credit = statement.value.reduce((s, r) => s + r.credit, 0)
  return { debit, credit, balance: openingBalance.value + debit - credit }
})

// Notes log + collection actions
const collectionNotes = computed(() => unit.value?.collection_notes ?? [])
const newNote = ref('')
async function addCollectionNote() {
  if (!newNote.value.trim()) return
  try {
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/collection-notes`, { note: newNote.value.trim() })
    newNote.value = ''
    await fetchAll()
  } catch (e) { /* ignore */ }
}

// Statement download (⋮ menu) + e-mail (confirm dialog)
const showStatementMenu   = ref(false)
const showEmailConfirm     = ref(false)
const emailingStatement    = ref(false)
async function downloadStatement(format) {
  showStatementMenu.value = false
  const params = { _format: format }
  if (dateFrom.value) params.from = dateFrom.value
  if (dateTo.value) params.to = dateTo.value
  try {
    const res = await api.get(`/communities/${communityId.value}/units/${unitId.value}/statement`, { params, responseType: 'blob' })
    let filename = `customer statement.${format === 'xlsx' ? 'xlsx' : format}`
    const cd = res.headers['content-disposition']
    const m = cd && cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
    if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) { /* ignore */ }
}
async function confirmEmailStatement() {
  emailingStatement.value = true
  try {
    const body = {}
    if (dateFrom.value) body.from = dateFrom.value
    if (dateTo.value) body.to = dateTo.value
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/statement/email`, body)
    showEmailConfirm.value = false
    await fetchAll()
  } catch (e) { /* ignore */ } finally {
    emailingStatement.value = false
  }
}

// Open the invoice PDF in a new tab (like WeConnectU). The endpoint is auth-guarded,
// so we open a blank tab synchronously (preserving the click gesture), fetch the PDF
// with the bearer token, then point the tab at the blob URL.
async function goToInvoice(id) {
  if (!id) return
  const win = window.open('', '_blank')
  try {
    const res = await api.get(`/invoices/${id}/download-pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    if (win) win.location.href = url
    else window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    if (win) win.close()
    toastError('Could not open the invoice PDF.')
  }
}
function goToOwner() {
  if (owner.value?.id) router.push({ name: 'owner-detail', params: { id: owner.value.id } }).catch(() => {})
}
function backToUnits() {
  router.push({ path: `/communities/${communityId.value}`, query: { tab: 'units' } })
}

// ── Communication (e-mail) log ────────────────────────────────────────
const showSendEmail = ref(false)
const emailRecipients = computed(() => {
  const list = []
  for (const o of (unit.value?.owners ?? [])) {
    if (o.email) list.push({ name: o.full_name, email: o.email })
    if (o.contact2_email) list.push({ name: o.contact2_name || 'Contact 2', email: o.contact2_email })
  }
  if (occupant.value?.email) list.push({ name: occupant.value.full_name, email: occupant.value.email })
  // De-dupe by email
  const seen = new Set()
  return list.filter(r => r.email && !seen.has(r.email.toLowerCase()) && seen.add(r.email.toLowerCase()))
})
const defaultRecipient = computed(() => owner.value?.email || '')

async function resendComm(id) {
  try {
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/communications/${id}/resend`)
    await fetchAll()
  } catch (e) { /* ignore */ }
}
async function downloadComm(id) {
  try {
    const res = await api.get(`/communities/${communityId.value}/units/${unitId.value}/communications/${id}/download`, { responseType: 'blob' })
    let filename = 'communication.pdf'
    const cd = res.headers['content-disposition']
    const m = cd && cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
    if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) { /* ignore */ }
}
function stripHtml(html) {
  const d = document.createElement('div'); d.innerHTML = html || ''
  return (d.textContent || '').trim()
}

// ── Email preview modal (click a subject to read the sent e-mail) ──────────
const emailModalOpen = ref(false)
const activeEmail     = ref(null)
function openEmail(c) {
  activeEmail.value    = c
  emailModalOpen.value = true
}

// ── Offences ──────────────────────────────────────────────────────────
const OFFENCE_STATUSES = [
  { value: 'warning',      label: 'Warning' },
  { value: 'first_notice', label: 'First Notice' },
  { value: 'final_notice', label: 'Final Notice' },
  { value: 'fine',         label: 'Fine' },
  { value: 'resolved',     label: 'Resolved' },
]
const OFFENCE_BADGES = {
  warning:      { label: 'Warning',      class: 'bg-[#dbeafe] text-[#2563a8]' },
  first_notice: { label: 'First Notice', class: 'bg-amber-100 text-amber-700' },
  final_notice: { label: 'Final Notice', class: 'bg-[#3a3f4b] text-white' },
  fine:         { label: 'Fine',         class: 'bg-red-100 text-red-700' },
  resolved:     { label: 'Resolved',     class: 'bg-emerald-100 text-emerald-700' },
}
function offenceBadge(s) { return OFFENCE_BADGES[s] || OFFENCE_BADGES.warning }

const offenceSearch = ref('')
const offenceSortAsc = ref(false)
const filteredOffences = computed(() => {
  const q = offenceSearch.value.trim().toLowerCase()
  let list = offences.value
  if (q) {
    list = list.filter(o =>
      (o.rules || []).some(r => `${r.rule} ${r.clause}`.toLowerCase().includes(q)) ||
      (o.description || '').toLowerCase().includes(q) ||
      (o.status || '').toLowerCase().includes(q)
    )
  }
  return [...list].sort((a, b) => {
    const cmp = (a.issued_date || '').localeCompare(b.issued_date || '')
    return offenceSortAsc.value ? cmp : -cmp
  })
})

const showOffenceModal = ref(false)
const offenceMode      = ref('add')
const offenceEditingId = ref(null)
const offenceForm      = ref({ status: 'warning', issued_date: '', description: '', rules: [{ rule: '', clause: '' }] })
const offenceFiles     = ref([])
const offenceMenuId    = ref(null)

function openAddOffence() {
  offenceMode.value = 'add'
  offenceEditingId.value = null
  offenceForm.value = { status: 'warning', issued_date: today(), description: '', rules: [{ rule: '', clause: '' }] }
  offenceFiles.value = []
  saveError.value = null
  showOffenceModal.value = true
}
function openEditOffence(o) {
  offenceMode.value = 'edit'
  offenceEditingId.value = o.id
  offenceForm.value = {
    status: o.status || 'warning', issued_date: o.issued_date || '', description: o.description || '',
    rules: (o.rules && o.rules.length) ? o.rules.map(r => ({ rule: r.rule || '', clause: r.clause || '' })) : [{ rule: '', clause: '' }],
  }
  offenceFiles.value = []
  offenceMenuId.value = null
  saveError.value = null
  showOffenceModal.value = true
}
function addRuleLine() { offenceForm.value.rules.push({ rule: '', clause: '' }) }
function removeRuleLine(i) { offenceForm.value.rules.splice(i, 1); if (!offenceForm.value.rules.length) addRuleLine() }

async function saveOffence() {
  saving.value = true
  saveError.value = null
  try {
    const fd = new FormData()
    fd.append('status', offenceForm.value.status)
    if (offenceForm.value.issued_date) fd.append('issued_date', offenceForm.value.issued_date)
    if (offenceForm.value.description) fd.append('description', offenceForm.value.description)
    offenceForm.value.rules.forEach((r, i) => {
      if (r.rule || r.clause) {
        fd.append(`rules[${i}][rule]`, r.rule || '')
        fd.append(`rules[${i}][clause]`, r.clause || '')
      }
    })
    offenceFiles.value.forEach(f => fd.append('attachments[]', f))
    if (offenceMode.value === 'add') {
      await api.post(`/communities/${communityId.value}/units/${unitId.value}/offences`, fd)
    } else {
      fd.append('_method', 'PUT')
      await api.post(`/communities/${communityId.value}/units/${unitId.value}/offences/${offenceEditingId.value}`, fd)
    }
    showOffenceModal.value = false
    await fetchAll()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save offence.'
  } finally {
    saving.value = false
  }
}
async function deleteOffence(o) {
  offenceMenuId.value = null
  try {
    await api.delete(`/communities/${communityId.value}/units/${unitId.value}/offences/${o.id}`)
    await fetchAll()
  } catch (e) { /* ignore */ }
}

// ── Tasks ─────────────────────────────────────────────────────────────
const TASK_STATUSES = [
  { value: 'open',        label: 'Open' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'complete',    label: 'Complete' },
]
const TASK_BADGES = {
  open:        { label: 'Open',        class: 'bg-[#dbeafe] text-[#2563a8]' },
  in_progress: { label: 'In Progress', class: 'bg-amber-100 text-amber-700' },
  complete:    { label: 'Complete',    class: 'bg-emerald-100 text-emerald-700' },
}
function taskBadge(s) { return TASK_BADGES[s] || TASK_BADGES.open }
function taskStatusLabel(s) { return (TASK_STATUSES.find(t => t.value === s) || {}).label || 'Open' }

const TASK_CATEGORIES = [
  'Ad-hoc Maintenance', 'Scheduled Maintenance', 'Reserve Fund Projects', 'Developer Items',
  'Insurance Claims', 'Minutes/Meetings actions', 'Client Requests', 'Admin', 'Finance',
  'Payments', 'No Category', 'Other',
]
const TASK_TYPES_BY_CATEGORY = {
  'Ad-hoc Maintenance':   ['Plumbing', 'Electrical', 'Painting', 'Carpentry', 'General'],
  'Insurance Claims':     ['Geyser Repair', 'Geyser Replacement', 'Resultant Damage', 'Burst Pipe'],
  'Scheduled Maintenance':['Garden Services', 'Cleaning', 'Pool Maintenance', 'Fire Equipment'],
}
const TASK_AREAS = ['Not Applicable', 'Common Property', 'Common Property & Unit', 'Unit']
const RECURRING_TYPES = ['Once Off', 'Weekly', 'Monthly', 'Yearly', 'Last day of Month', 'Every second Week']

// Assignee options — "Me" + organisation staff.
const assigneeOptions = computed(() => {
  const me = authStore.user
  const opts = [{ value: '', label: '-- Select --' }]
  if (me?.id) opts.push({ value: me.id, label: `Me (${me.name})` })
  for (const u of staff.value) {
    if (me?.id && u.id === me.id) continue
    opts.push({ value: u.id, label: u.name })
  }
  return opts
})
// Contacts = the unit's owners.
const contactOptions = computed(() =>
  (unit.value?.owners ?? []).map(o => ({ value: o.full_name, label: `${o.full_name}${unit.value?.unit_number ? ' — Unit No ' + unit.value.unit_number : ''}` }))
)
const taskTypeOptions = computed(() => TASK_TYPES_BY_CATEGORY[taskForm.value.category] || [])

const taskSearch = ref('')
const filteredTasks = computed(() => {
  const q = taskSearch.value.trim().toLowerCase()
  if (!q) return tasks.value
  return tasks.value.filter(t =>
    `${t.code} ${t.title} ${t.category} ${t.task_type} ${t.assignee_name}`.toLowerCase().includes(q)
  )
})

const showTaskModal = ref(false)
const taskMode      = ref('add')
const taskEditingId = ref(null)
const taskShowDetails = ref(false)
const taskForm = ref({
  title: '', due_date: '', recurring_type: 'Once Off', assignee_name: '', assignee_user_id: '',
  internal: false, area: 'Unit', category: '', task_type: '', contacts: [], description: '',
  supplier_names: [], status: 'open',
})
const taskFiles     = ref([])
const supplierInput = ref('')
const taskMenuId    = ref(null)

function blankTask() {
  return {
    title: '', due_date: '', recurring_type: 'Once Off', assignee_name: '', assignee_user_id: '',
    internal: false, area: 'Unit', category: '', task_type: '', contacts: [], description: '',
    supplier_names: [], status: 'open',
  }
}
function openAddTask() {
  taskMode.value = 'add'
  taskEditingId.value = null
  taskForm.value = blankTask()
  taskFiles.value = []
  supplierInput.value = ''
  taskShowDetails.value = false
  saveError.value = null
  showTaskModal.value = true
}
function openEditTask(t) {
  taskMode.value = 'edit'
  taskEditingId.value = t.id
  taskForm.value = {
    title: t.title || '', due_date: t.due_date || '', recurring_type: t.recurring_type || 'Once Off',
    assignee_name: t.assignee_name || '', assignee_user_id: t.assignee_user_id || '',
    internal: !!t.internal, area: t.area || 'Unit', category: t.category || '', task_type: t.task_type || '',
    contacts: [...(t.contacts || [])], description: t.description || '',
    supplier_names: [...(t.supplier_names || [])], status: t.status || 'open',
  }
  taskFiles.value = []
  supplierInput.value = ''
  taskShowDetails.value = (t.supplier_names || []).length > 0
  taskMenuId.value = null
  showTaskDetail.value = false
  saveError.value = null
  showTaskModal.value = true
}
function onAssigneeChange() {
  const opt = assigneeOptions.value.find(o => o.value === taskForm.value.assignee_user_id)
  taskForm.value.assignee_name = opt && opt.value ? opt.label.replace(/^Me \(|\)$/g, '') : ''
}
function addSupplier() {
  const v = supplierInput.value.trim()
  if (v && !taskForm.value.supplier_names.includes(v)) taskForm.value.supplier_names.push(v)
  supplierInput.value = ''
}
function removeSupplier(i) { taskForm.value.supplier_names.splice(i, 1) }
function onTaskFileSelect(e) { taskFiles.value.push(...Array.from(e.target.files || [])); e.target.value = '' }

async function saveTask() {
  if (!taskForm.value.title.trim()) { saveError.value = 'Please enter a task title.'; return }
  saving.value = true
  saveError.value = null
  try {
    const f = taskForm.value
    const fd = new FormData()
    fd.append('title', f.title)
    if (f.description)    fd.append('description', f.description)
    if (f.category)       fd.append('category', f.category)
    if (f.task_type)      fd.append('task_type', f.task_type)
    if (f.area)           fd.append('area', f.area)
    if (f.recurring_type) fd.append('recurring_type', f.recurring_type)
    if (f.assignee_name)  fd.append('assignee_name', f.assignee_name)
    if (f.assignee_user_id) fd.append('assignee_user_id', f.assignee_user_id)
    if (f.status)         fd.append('status', f.status)
    fd.append('internal', f.internal ? '1' : '0')
    if (f.due_date)       fd.append('due_date', f.due_date)
    f.contacts.forEach(c => fd.append('contacts[]', c))
    f.supplier_names.forEach(s => fd.append('supplier_names[]', s))
    taskFiles.value.forEach(file => fd.append('attachments[]', file))
    if (taskMode.value === 'add') {
      await api.post(`/communities/${communityId.value}/units/${unitId.value}/tasks`, fd)
    } else {
      fd.append('_method', 'PUT')
      await api.post(`/communities/${communityId.value}/units/${unitId.value}/tasks/${taskEditingId.value}`, fd)
    }
    showTaskModal.value = false
    await fetchAll()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save task.'
  } finally {
    saving.value = false
  }
}
async function deleteTask(t) {
  taskMenuId.value = null
  try {
    await api.delete(`/communities/${communityId.value}/units/${unitId.value}/tasks/${t.id}`)
    if (activeTask.value?.id === t.id) showTaskDetail.value = false
    await fetchAll()
  } catch (e) { /* ignore */ }
}

// Task detail modal + feedback
const showTaskDetail = ref(false)
const activeTask     = ref(null)
const taskDescExpanded = ref(false)
const feedbackText   = ref('')
const feedbackNotify = ref('')
const feedbackFiles  = ref([])
const feedbackSaving = ref(false)

function openTaskDetail(t) {
  activeTask.value = t
  taskDescExpanded.value = false
  feedbackText.value = ''
  feedbackNotify.value = ''
  feedbackFiles.value = []
  showTaskDetail.value = true
}
const notifyOptions = computed(() => {
  const t = activeTask.value
  const opts = [{ value: '', label: 'Send Notification' }]
  if (t?.assignee_name) opts.push({ value: `Assignee – ${t.assignee_name}`, label: `Assignee – ${t.assignee_name}` })
  if (t?.created_by_name) opts.push({ value: `Created by – ${t.created_by_name}`, label: `Created by – ${t.created_by_name}` })
  return opts
})
async function changeTaskStatus(status) {
  if (!activeTask.value || activeTask.value.status === status) return
  try {
    const { data } = await api.put(`/communities/${communityId.value}/units/${unitId.value}/tasks/${activeTask.value.id}`, { status })
    activeTask.value = data.data
    const i = tasks.value.findIndex(x => x.id === activeTask.value.id)
    if (i !== -1) tasks.value[i] = data.data
  } catch (e) { /* ignore */ }
}
function onFeedbackFileSelect(e) { feedbackFiles.value.push(...Array.from(e.target.files || [])); e.target.value = '' }
async function submitFeedback() {
  if (!feedbackText.value.trim() && !feedbackFiles.value.length) return
  feedbackSaving.value = true
  try {
    const fd = new FormData()
    if (feedbackText.value.trim()) fd.append('feedback', feedbackText.value.trim())
    if (feedbackNotify.value)      fd.append('notify', feedbackNotify.value)
    feedbackFiles.value.forEach(f => fd.append('attachments[]', f))
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/tasks/${activeTask.value.id}/updates`, fd)
    feedbackText.value = ''
    feedbackNotify.value = ''
    feedbackFiles.value = []
    const { data } = await api.get(`/communities/${communityId.value}/units/${unitId.value}/tasks`)
    tasks.value = data.data ?? []
    activeTask.value = tasks.value.find(x => x.id === activeTask.value.id) || activeTask.value
  } catch (e) { /* ignore */ } finally {
    feedbackSaving.value = false
  }
}

// ── Documents ─────────────────────────────────────────────────────────
const showDocModal = ref(false)
const docName      = ref('')
const docFile      = ref(null)
const docDragging  = ref(false)
const docSaving    = ref(false)
const docError     = ref(null)

function openUploadDoc() {
  docName.value = ''
  docFile.value = null
  docDragging.value = false
  docError.value = null
  showDocModal.value = true
}
function onDocSelect(e) {
  const f = (e.target.files || [])[0]
  if (f) { docFile.value = f; if (!docName.value) docName.value = f.name }
  e.target.value = ''
}
function onDocDrop(e) {
  docDragging.value = false
  const f = (e.dataTransfer.files || [])[0]
  if (f) { docFile.value = f; if (!docName.value) docName.value = f.name }
}
function formatBytes(n) {
  if (!n) return '—'
  if (n < 1024) return n + ' B'
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB'
  return (n / 1024 / 1024).toFixed(1) + ' MB'
}
async function uploadDocument() {
  if (!docFile.value) { docError.value = 'Please select a document.'; return }
  docSaving.value = true
  docError.value = null
  try {
    const fd = new FormData()
    if (docName.value.trim()) fd.append('name', docName.value.trim())
    fd.append('document', docFile.value)
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/documents`, fd)
    showDocModal.value = false
    await fetchAll()
  } catch (e) {
    docError.value = e?.response?.data?.message ?? 'Failed to upload document.'
  } finally {
    docSaving.value = false
  }
}
async function deleteDocument(d) {
  try {
    await api.delete(`/communities/${communityId.value}/units/${unitId.value}/documents/${d.id}`)
    await fetchAll()
  } catch (e) { /* ignore */ }
}

const PENCIL = 'M12 20h9 M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'

// ── Option lists (WeConnectU parity) ──────────────────────────────────
const CUSTOMER_TYPES = ['Body Corporate', 'Cash Only', 'Closed Corporation (CC)', 'Incorporated', 'Individual', 'Non Profit Organization', 'Partnership', 'Private Company (Pty) Ltd', 'Public Company Ltd', 'Trust']
const ENTITY_TYPES   = [
  { value: 'individual', label: 'Individual' },
  { value: 'close_corporation', label: 'CC' },
  { value: 'company', label: 'PTY' },
  { value: 'trust', label: 'Trust' },
  { value: 'other', label: 'Body Corporate' },
]
const PAYMENT_TYPES  = ['Not Specified', 'EFT', 'Debit Order', 'Cash']
const PDF_PASSWORD   = ['Community Default', 'Disabled', 'Enabled']
const ACCOUNT_TYPES  = ['', 'Current', 'Savings', 'Investment']
const BANK_NAMES     = ['', 'ABSA', 'African Bank', 'Bank Windhoek', 'Bidvest Bank', 'Capitec Bank', 'Capitec Business', 'Discovery Bank', 'First National Bank', 'Investec Private Bank', 'Mercantile Bank', 'Merchant Bank', 'Nedbank', 'Other', 'PayFast', 'Sasfin Bank Ltd', 'Standard Bank', 'Standard Chartered Bank', 'Tyme Bank']

const saving    = ref(false)
const saveError = ref(null)

async function saveUnitPatch(payload) {
  saving.value = true
  saveError.value = null
  try {
    await api.put(`/communities/${communityId.value}/units/${unitId.value}`, payload)
    await fetchAll()
    return true
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save. Please try again.'
    return false
  } finally {
    saving.value = false
  }
}

// ── Edit Unit modal (Additional Contacts + Unit notes) ────────────────
const showEditUnit = ref(false)
const editUnitForm = ref({ unit_notes: '', rental_agent_email: '', attorney_email: '', bondholder_email: '' })
function openEditUnit() {
  editUnitForm.value = {
    unit_notes:         unit.value?.unit_notes || '',
    rental_agent_email: unit.value?.rental_agent_email || '',
    attorney_email:     unit.value?.attorney_email || '',
    bondholder_email:   unit.value?.bondholder_email || '',
  }
  saveError.value = null
  showEditUnit.value = true
}
async function saveEditUnit() {
  if (await saveUnitPatch({ ...editUnitForm.value })) showEditUnit.value = false
}

// ── Owner modal (edit / add / transfer) ───────────────────────────────
const showEditOwner  = ref(false)
const ownerMode      = ref('edit')   // 'edit' | 'add' | 'transfer'
const ownerEditingId = ref(null)
const editOwnerForm  = ref({})
const ownerModalTitle = computed(() => ownerMode.value === 'edit' ? (editOwnerForm.value.name || 'Owner') : 'Add Owner')

const BLANK_OWNER = () => ({
  name: '', email: '', billing_pdf: !!unit.value?.billing_pdf, phone: '', landline: '', entity_type: 'individual',
  id_number: '', contact2_name: '', contact2_email: '', contact2_phone: '', contact2_landline: '',
  owner_occupied: unit.value?.occupancy_type === 'owner_occupied',
})

function ownerFieldsPayload(f) {
  return {
    full_name: f.name, email: f.email, phone: f.phone || null, landline: f.landline || null,
    entity_type: f.entity_type || null, id_number: f.id_number || null,
    contact2_name: f.contact2_name || null, contact2_email: f.contact2_email || null,
    contact2_phone: f.contact2_phone || null, contact2_landline: f.contact2_landline || null,
  }
}

function openEditOwner(oc) {
  const o = oc?.raw || owner.value || {}
  ownerMode.value = 'edit'
  ownerEditingId.value = o.id || null
  editOwnerForm.value = {
    name: o.full_name || '', email: o.email || '', billing_pdf: !!unit.value?.billing_pdf,
    phone: o.phone || '', landline: o.landline || '', entity_type: o.entity_type || 'individual',
    id_number: o.id_number || '',
    contact2_name: o.contact2_name || '', contact2_email: o.contact2_email || '',
    contact2_phone: o.contact2_phone || '', contact2_landline: o.contact2_landline || '',
    owner_occupied: unit.value?.occupancy_type === 'owner_occupied',
  }
  saveError.value = null
  showEditOwner.value = true
}
function openAddOwner() {
  ownerMode.value = 'add'
  ownerEditingId.value = null
  editOwnerForm.value = BLANK_OWNER()
  saveError.value = null
  showEditOwner.value = true
}
async function saveEditOwner() {
  const f = editOwnerForm.value
  saving.value = true
  saveError.value = null
  try {
    const unitPatch = {
      billing_pdf: !!f.billing_pdf,
      occupancy_type: f.owner_occupied ? 'owner_occupied' : (unit.value?.occupancy_type || 'owner_occupied'),
    }
    if (ownerMode.value === 'transfer') {
      await api.put(`/communities/${communityId.value}/units/${unitId.value}`, { ...unitPatch, ownership_change: true, owner: ownerFieldsPayload(f) })
    } else if (ownerMode.value === 'add') {
      await api.post(`/communities/${communityId.value}/units/${unitId.value}/owners`, ownerFieldsPayload(f))
      await api.put(`/communities/${communityId.value}/units/${unitId.value}`, unitPatch)
    } else {
      await api.put(`/communities/${communityId.value}/units/${unitId.value}/owners/${ownerEditingId.value}`, ownerFieldsPayload(f))
      await api.put(`/communities/${communityId.value}/units/${unitId.value}`, unitPatch)
    }
    showEditOwner.value = false
    await fetchAll()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save owner.'
  } finally {
    saving.value = false
  }
}
async function deleteOwnerCard(oc) {
  if (!oc?.id) return
  try {
    await api.delete(`/communities/${communityId.value}/units/${unitId.value}/owners/${oc.id}`)
    await fetchAll()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to remove owner.'
  }
}

// ── Change Ownership → Confirm Transfer ───────────────────────────────
const showConfirmTransfer = ref(false)
function openChangeOwnership() { showConfirmTransfer.value = true }
function confirmTransfer() {
  showConfirmTransfer.value = false
  ownerMode.value = 'transfer'
  ownerEditingId.value = null
  editOwnerForm.value = BLANK_OWNER()
  saveError.value = null
  showEditOwner.value = true
}

// ── Add Occupant modal ────────────────────────────────────────────────
const showAddOccupant = ref(false)
const addOccupantForm = ref({})
function openAddOccupant() {
  addOccupantForm.value = {
    first_name: '', last_name: '', email: '', phone: '', id_number: '',
    postal_address: '', car_registration: '', lease_start: '', lease_end: '', lease_amount: '',
  }
  saveError.value = null
  showAddOccupant.value = true
}
async function saveAddOccupant() {
  const f = addOccupantForm.value
  const fullName = [f.first_name, f.last_name].filter(Boolean).join(' ').trim()
  saving.value = true
  saveError.value = null
  try {
    await api.post(`/communities/${communityId.value}/units/${unitId.value}/occupants`, {
      full_name: fullName || f.email, email: f.email,
      phone: f.phone || null, id_number: f.id_number || null,
      postal_address: f.postal_address || null, car_registration: f.car_registration || null,
      lease_start: f.lease_start || null, lease_end: f.lease_end || null,
      rent_amount: f.lease_amount !== '' ? parseFloat(f.lease_amount) : null,
    })
    showAddOccupant.value = false
    await fetchAll()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to add occupant.'
  } finally {
    saving.value = false
  }
}

// ── Edit Customer modal ───────────────────────────────────────────────
const showEditCustomer = ref(false)
const editCustomerForm = ref({})
function openEditCustomer() {
  const o = owner.value || {}
  editCustomerForm.value = {
    customer_type: o.customer_type || 'Individual', name: o.full_name || '', customer_code: unit.value?.customer_code || '',
    vat_no: o.vat_no || '', email: o.email || '', phone: o.phone || '', id_number: o.id_number || '',
    pdf_password: o.pdf_password || 'Community Default',
    customer_group: o.customer_group || '', reference: o.reference || `Unit No ${unit.value?.unit_number || ''}`,
    old_customer_code: o.old_customer_code || '', alt_email: o.alt_email || '', alt_phone: o.alt_phone || '',
    payment_type: o.payment_type || 'Not Specified',
    address: o.address || '', address_line_2: o.address_line_2 || '', suburb: o.suburb || '', town: o.town || '', postal_code: o.postal_code || '',
    account_holder: o.account_holder || '', bank_name: o.bank_name || '', account_type: o.account_type || '',
    account_number: o.account_number || '', branch_code: o.branch_code || '', branch_name: o.branch_name || '',
    notes: o.notes || '',
    collection_status: unit.value?.collection_status || 'none',
  }
  saveError.value = null
  showEditCustomer.value = true
}
async function saveEditCustomer() {
  const f = editCustomerForm.value
  const payload = {
    customer_code: f.customer_code || null,
    collection_status: f.collection_status || 'none',
    owner: {
      full_name: f.name, email: f.email, phone: f.phone || null, id_number: f.id_number || null,
      customer_type: f.customer_type || null, vat_no: f.vat_no || null,
      alt_email: f.alt_email || null, alt_phone: f.alt_phone || null, payment_type: f.payment_type || null,
      pdf_password: f.pdf_password || null, customer_group: f.customer_group || null, reference: f.reference || null,
      old_customer_code: f.old_customer_code || null,
      address: f.address || null, address_line_2: f.address_line_2 || null, suburb: f.suburb || null, town: f.town || null, postal_code: f.postal_code || null,
      account_holder: f.account_holder || null, bank_name: f.bank_name || null, account_type: f.account_type || null,
      account_number: f.account_number || null, branch_code: f.branch_code || null, branch_name: f.branch_name || null,
      notes: f.notes || null,
    },
  }
  if (await saveUnitPatch(payload)) showEditCustomer.value = false
}
</script>

<template>
  <div class="pb-10">
    <!-- ── Page header ─────────────────────────────────────────────── -->
    <div class="mb-5">
      <h1 class="font-body font-semibold text-2xl text-foreground">Unit No {{ unit?.unit_number || '—' }}</h1>
      <p class="text-sm text-muted-foreground mt-0.5">{{ customerCode || '—' }}</p>
    </div>

    <!-- ── Tab pills ───────────────────────────────────────────────── -->
    <div class="inline-flex items-center gap-1 rounded-full bg-muted/60 p-1 mb-5 flex-wrap">
      <button
        v-for="t in TABS"
        :key="t.key"
        type="button"
        :class="[
          'px-5 py-2 rounded-full text-sm font-medium transition-colors',
          activeTab === t.key ? 'bg-navy-dark text-white shadow-sm' : 'text-muted-foreground hover:text-foreground',
        ]"
        @click="setTab(t.key)"
      >{{ t.label }}</button>
    </div>

    <div v-if="error" class="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive mb-4">{{ error }}</div>

    <!-- ══════ TAB: Unit Details ══════ -->
    <div v-show="activeTab === 'details'" class="rounded-lg border bg-card shadow-sm p-6 space-y-6">

      <!-- Unit Details -->
      <div>
        <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Unit Details</h3></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-border p-4">
            <div class="space-y-1.5 text-sm">
              <div class="flex justify-between"><span class="font-semibold text-foreground">Block</span><span class="text-muted-foreground">{{ unit?.block_number || '—' }}</span></div>
              <div class="flex justify-between"><span class="font-semibold text-foreground">Section</span><span class="text-muted-foreground">{{ unit?.section || '—' }}</span></div>
              <div class="flex justify-between"><span class="font-semibold text-foreground">Unit</span><span class="text-muted-foreground">{{ unit?.unit_number || '—' }}</span></div>
              <div class="flex justify-between"><span class="font-semibold text-foreground">Door</span><span class="text-muted-foreground">{{ unit?.door_number || '—' }}</span></div>
              <div class="flex justify-between"><span class="font-semibold text-foreground">PQ</span><span class="text-muted-foreground">{{ fmtPq(unit?.pq) }}</span></div>
            </div>
          </div>
          <div class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-muted-foreground/50 hover:text-accent" title="Edit Unit" @click="openEditUnit"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
            <h4 class="text-sm font-semibold text-foreground mb-3">Additional Contacts</h4>
            <div class="space-y-2.5 text-sm">
              <div><p class="font-medium text-foreground">Rental Agent</p><a v-if="unit?.rental_agent_email" :href="`mailto:${unit.rental_agent_email}`" class="text-xs text-[#2f6fb0] hover:underline">{{ unit.rental_agent_email }}</a></div>
              <div><p class="font-medium text-foreground">Attorney / Other</p><a v-if="unit?.attorney_email" :href="`mailto:${unit.attorney_email}`" class="text-xs text-[#2f6fb0] hover:underline">{{ unit.attorney_email }}</a></div>
              <div><p class="font-medium text-foreground">Bondholder</p><a v-if="unit?.bondholder_email" :href="`mailto:${unit.bondholder_email}`" class="text-xs text-[#2f6fb0] hover:underline">{{ unit.bondholder_email }}</a></div>
            </div>
          </div>
          <div class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-muted-foreground/50 hover:text-accent" title="Edit Unit" @click="openEditUnit"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
            <h4 class="text-sm font-semibold text-foreground mb-3">Unit notes</h4>
            <p class="text-sm text-muted-foreground whitespace-pre-line">{{ unit?.unit_notes || '' }}</p>
          </div>
        </div>
      </div>

      <!-- Customer -->
      <div>
        <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Customer</h3></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-[#2f6fb0] hover:opacity-80" title="Edit Customer" @click="openEditCustomer"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
            <span v-if="statusBadge" :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium mb-3', statusBadge.class]">{{ statusBadge.label }}</span>
            <p class="text-sm font-semibold text-foreground" :class="statusBadge ? '' : 'mt-1'">{{ customerCode }} <span class="font-normal">{{ owner?.full_name || '—' }}</span></p>
            <p class="text-xs text-muted-foreground mb-2">{{ entityLabel(owner?.entity_type) }}</p>
            <div class="flex items-center justify-between gap-2 text-sm">
              <a v-if="owner?.email" :href="`mailto:${owner.email}`" class="text-[#2f6fb0] hover:underline truncate">{{ owner.email }}</a>
              <span v-if="owner?.phone" class="text-[#2f6fb0] shrink-0">{{ owner.phone }}</span>
            </div>
          </div>
          <div class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-muted-foreground/50 hover:text-accent" title="Edit Customer" @click="openEditCustomer"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
            <h4 class="text-sm font-semibold text-foreground mb-3">Bank Details</h4>
            <div class="space-y-2 text-sm text-foreground">
              <div class="flex justify-between gap-2"><span class="font-medium">Account Holder</span><span class="text-muted-foreground text-right">{{ owner?.account_holder || '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Bank</span><span class="text-muted-foreground text-right">{{ owner?.bank_name || '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Account Type</span><span class="text-muted-foreground text-right">{{ owner?.account_type || '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Account Number</span><span class="text-muted-foreground text-right">{{ owner?.account_number || '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Branch Code</span><span class="text-muted-foreground text-right">{{ owner?.branch_code || '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Branch Name</span><span class="text-muted-foreground text-right">{{ owner?.branch_name || '' }}</span></div>
            </div>
          </div>
          <div class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-muted-foreground/50 hover:text-accent" title="Edit Customer" @click="openEditCustomer"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
            <h4 class="text-sm font-semibold text-foreground mb-3">Debit Order Details</h4>
            <div class="space-y-2 text-sm text-foreground">
              <div class="flex justify-between gap-2"><span class="font-medium">Contract Number</span><span class="text-muted-foreground text-right">{{ customerCode }}-</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Collection Date</span><span class="text-muted-foreground text-right"></span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Collection Type</span><span class="text-muted-foreground text-right">{{ owner?.payment_type && owner.payment_type !== 'Not Specified' ? owner.payment_type : '' }}</span></div>
              <div class="flex justify-between gap-2"><span class="font-medium">Maximum Balance</span><span class="text-muted-foreground text-right"></span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Owners -->
      <div>
        <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Owners</h3></div>
        <div v-if="ownerCards.length" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div v-for="oc in ownerCards" :key="oc.id" class="rounded-lg border border-border p-4 relative">
            <div class="absolute top-3 right-3 flex items-center gap-1.5">
              <button class="text-muted-foreground/50 hover:text-accent" title="Edit Owner" @click="openEditOwner(oc)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path :d="PENCIL"/></svg></button>
              <button v-if="!oc.primary" class="text-muted-foreground/40 hover:text-destructive" title="Unlink" @click="deleteOwnerCard(oc)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="m18.84 12.25 1.72-1.71a3.83 3.83 0 0 0-5.42-5.42l-1.71 1.72"/><path d="m5.17 11.75-1.72 1.71a3.83 3.83 0 0 0 5.42 5.42l1.71-1.72"/><line x1="8" x2="16" y1="2" y2="22"/></svg></button>
            </div>
            <span v-if="oc.verified" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-0.5 text-[11px] font-medium mb-2"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-2.5 h-2.5"><path d="M20 6 9 17l-5-5"/></svg> User verified</span>
            <span v-else class="inline-flex items-center rounded-full bg-[#e6f0f9] text-[#2f6fb0] px-2.5 py-0.5 text-[11px] font-medium mb-2">User linked</span>
            <button class="block text-sm font-semibold text-foreground hover:text-[#2f6fb0] text-left" @click="goToOwner">{{ oc.name }}</button>
            <p class="text-xs text-muted-foreground mb-2">{{ oc.entity }}</p>
            <div class="flex items-center justify-between gap-2 text-sm">
              <a v-if="oc.email" :href="`mailto:${oc.email}`" class="text-[#2f6fb0] hover:underline truncate">{{ oc.email }}</a>
              <span v-if="oc.phone" class="text-[#2f6fb0] shrink-0">{{ oc.phone }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openAddOwner">Add Owner</button>
          <button type="button" class="rounded-md bg-[#e2645a] px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openChangeOwnership">Change Ownership</button>
        </div>
      </div>

      <!-- Occupants & Tenants -->
      <div>
        <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Occupants &amp; Tenants</h3></div>
        <div v-if="occupants.length" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div v-for="occ in occupants" :key="occ.id" class="rounded-lg border border-border p-4">
            <p class="text-sm font-semibold text-foreground">{{ occ.full_name }}</p>
            <p class="text-xs text-muted-foreground mb-2">{{ occ.is_active ? 'Active' : 'Past' }}</p>
            <a v-if="occ.email" :href="`mailto:${occ.email}`" class="text-sm text-[#2f6fb0] hover:underline">{{ occ.email }}</a>
          </div>
        </div>
        <button type="button" class="rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openAddOccupant">Add Occupant</button>
      </div>

      <!-- Users -->
      <div>
        <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Users</h3></div>
        <div v-if="ownerCards.some(o => o.email)" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="oc in ownerCards.filter(o => o.email)" :key="oc.id" class="rounded-lg border border-border p-4 relative">
            <button class="absolute top-3 right-3 text-muted-foreground/40 hover:text-destructive" title="Unlink"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="m18.84 12.25 1.72-1.71a3.83 3.83 0 0 0-5.42-5.42l-1.71 1.72"/><path d="m5.17 11.75-1.72 1.71a3.83 3.83 0 0 0 5.42 5.42l1.71-1.72"/><line x1="8" x2="16" y1="2" y2="22"/></svg></button>
            <p class="text-sm font-semibold text-foreground">{{ oc.displayName }}</p>
            <a :href="`mailto:${oc.email}`" class="text-sm text-[#2f6fb0] hover:underline">{{ oc.email }}</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════ TAB: Finances ══════ -->
    <div v-show="activeTab === 'finances'" class="rounded-lg border bg-card shadow-sm p-6">
      <div class="rounded-lg border border-border p-4 mb-5 max-w-md">
        <span v-if="statusBadge" :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium mb-2', statusBadge.class]">{{ statusBadge.label }}</span>
        <p class="text-sm font-semibold text-foreground" :class="statusBadge ? '' : 'mt-1'">{{ customerCode }} <span class="font-normal">{{ owner?.full_name || '—' }}</span></p>
        <p class="text-xs text-muted-foreground mb-2">{{ entityLabel(owner?.entity_type) }}</p>
        <div class="flex items-center justify-between gap-2 text-sm">
          <a v-if="owner?.email" :href="`mailto:${owner.email}`" class="text-[#2f6fb0] hover:underline truncate">{{ owner.email }}</a>
          <span v-if="owner?.phone" class="text-[#2f6fb0] shrink-0">{{ owner.phone }}</span>
        </div>
      </div>

      <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Finances</h3></div>

      <div class="flex items-end justify-between gap-4 flex-wrap mb-4">
        <div class="flex items-center gap-4 flex-wrap">
          <label class="text-sm text-muted-foreground">Date from:
            <input v-model="dateFrom" type="date" class="ml-2 h-9 rounded-md border border-border bg-background px-2 text-sm text-foreground" />
          </label>
          <label class="text-sm text-muted-foreground">Date to:
            <input v-model="dateTo" type="date" class="ml-2 h-9 rounded-md border border-border bg-background px-2 text-sm text-foreground" />
          </label>
        </div>
        <div class="flex items-center gap-2 relative">
          <button type="button" class="inline-flex items-center gap-2 rounded-md bg-accent px-3.5 py-2 text-sm font-medium text-white hover:opacity-90 transition-colors" @click="showEmailConfirm = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            E-Mail Statement
          </button>
          <button type="button" class="w-9 h-9 rounded-md border border-border text-muted-foreground hover:bg-muted flex items-center justify-center" @click="showStatementMenu = !showStatementMenu"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg></button>
          <div v-if="showStatementMenu" class="absolute right-0 top-11 z-20 w-52 rounded-md border border-border bg-card shadow-lg py-1">
            <button type="button" class="w-full text-left px-4 py-2 text-sm text-foreground hover:bg-muted" @click="downloadStatement('pdf')">Download PDF Statement</button>
            <button type="button" class="w-full text-left px-4 py-2 text-sm text-foreground hover:bg-muted" @click="downloadStatement('xlsx')">Download Excel</button>
          </div>
        </div>
      </div>

      <label class="flex items-center gap-2 text-sm text-muted-foreground mb-4">
        <input v-model="showLineItems" type="checkbox" class="h-4 w-4 rounded border-border accent-primary" />
        Show invoices line items
      </label>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-y border-border text-left">
              <th class="py-2.5 px-3 font-semibold text-foreground">Date</th>
              <th class="py-2.5 px-3 font-semibold text-foreground">Source</th>
              <th class="py-2.5 px-3 font-semibold text-foreground">Description</th>
              <th class="py-2.5 px-3 font-semibold text-foreground text-right">Debit</th>
              <th class="py-2.5 px-3 font-semibold text-foreground text-right">Credit</th>
              <th class="py-2.5 px-3 font-semibold text-foreground text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            <!-- Opening balance brought forward -->
            <tr class="border-b border-border">
              <td class="py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ dateFrom || (statement[0] && statement[0].date) }}</td>
              <td class="py-2.5 px-3"></td>
              <td class="py-2.5 px-3 text-muted-foreground">Balance b/f</td>
              <td class="py-2.5 px-3 text-right"></td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ openingBalance ? fmt(openingBalance) : '' }}</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ fmt(openingBalance) }}</td>
            </tr>
            <tr v-for="(r, i) in statement" :key="i" class="border-b border-border">
              <td class="py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ r.date }}</td>
              <td class="py-2.5 px-3 text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                  {{ r.source }}
                  <span v-if="r.info" class="group relative inline-flex">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2f6fb0" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/><path fill="#fff" d="M11 10h2v7h-2zM11 7h2v2h-2z"/></svg>
                    <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 bottom-5 whitespace-nowrap rounded bg-[#2b303a] text-white text-[11px] px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                      <template v-if="r.info.by">Allocated by <b>{{ r.info.by }}</b> on <b>{{ r.info.at }}</b></template>
                      <template v-else>Allocated on <b>{{ r.info.at }}</b></template>
                    </span>
                  </span>
                </span>
              </td>
              <td class="py-2.5 px-3">
                <button v-if="r.invoiceId" class="text-[#2f6fb0] hover:underline" @click="goToInvoice(r.invoiceId)">{{ r.description }}</button>
                <span v-else class="text-foreground">{{ r.description }}</span>
              </td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ r.debit ? fmt(r.debit) : '' }}</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ r.credit ? fmt(r.credit) : '' }}</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ fmt(r.balance) }}</td>
            </tr>
            <tr class="border-t-2 border-border font-semibold">
              <td class="py-2.5 px-3 text-foreground" colspan="3">Totals</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ fmt(totals.debit) }}</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ fmt(totals.credit) }}</td>
              <td class="py-2.5 px-3 text-right text-foreground">{{ fmt(totals.balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Notes log -->
      <div class="bg-muted/40 rounded px-4 py-2.5 mt-6 mb-3"><h3 class="text-sm font-semibold text-foreground">Notes</h3></div>
      <div class="flex items-center gap-2 mb-4">
        <input v-model="newNote" type="text" placeholder="Add a note…" class="flex-1 h-9 rounded-md border border-border bg-background px-3 text-sm" @keyup.enter="addCollectionNote" />
        <button type="button" class="rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90" @click="addCollectionNote">Add</button>
      </div>
      <div class="divide-y divide-border">
        <div v-for="n in collectionNotes" :key="n.id" class="flex gap-4 py-2 text-sm">
          <span class="text-muted-foreground whitespace-nowrap w-40 shrink-0">{{ (n.created_at || '').slice(0, 16).replace('T', ' ') }}</span>
          <span class="text-foreground">{{ n.note }}</span>
        </div>
        <div v-if="!collectionNotes.length" class="py-3 text-sm text-muted-foreground">No notes yet.</div>
      </div>
    </div>

    <!-- ══════ TAB: Communication ══════ -->
    <div v-show="activeTab === 'communication'" class="rounded-lg border bg-card shadow-sm p-6">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div class="bg-muted/40 rounded px-4 py-2.5 flex-1"><h3 class="text-sm font-semibold text-foreground">Communication</h3></div>
        <button type="button" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity shrink-0" @click="showSendEmail = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
          Send E-Mail
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-[#eef1f5]">
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark whitespace-nowrap">Date</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark">Subject</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark">Sent To</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark">Sent By</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark">Message</th>
              <th class="border border-border py-2.5 px-3 text-center font-bold text-navy-dark">Resend</th>
              <th class="border border-border py-2.5 px-3 text-center font-bold text-navy-dark">Download</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in communications" :key="c.id" class="hover:bg-muted/30">
              <td class="border border-border px-3 py-2.5 align-top text-muted-foreground whitespace-nowrap">{{ (c.created_at || '').slice(0, 16).replace('T', ' ') }}</td>
              <td class="border border-border px-3 py-2.5 align-top">
                <button type="button" class="text-left text-[#2f6fb0] font-medium hover:underline" @click="openEmail(c)">{{ c.subject }}</button>
              </td>
              <td class="border border-border px-3 py-2.5 align-top">
                <p class="text-foreground">{{ c.recipient_name }}</p>
                <a :href="`mailto:${c.recipient_email}`" class="text-[#2f6fb0] hover:underline text-xs">{{ c.recipient_email }}</a>
              </td>
              <td class="border border-border px-3 py-2.5 align-top text-foreground whitespace-nowrap">{{ c.sent_by_name }}</td>
              <td class="border border-border px-3 py-2.5 align-top text-muted-foreground max-w-[360px]">
                <p class="line-clamp-4">{{ stripHtml(c.body) }}</p>
              </td>
              <td class="border border-border px-3 py-2.5 align-middle text-center">
                <button type="button" class="text-navy-dark hover:text-accent" title="Resend" @click="resendComm(c.id)">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 inline"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                </button>
              </td>
              <td class="border border-border px-3 py-2.5 align-middle text-center">
                <button type="button" class="text-navy-dark hover:text-accent" title="Download" @click="downloadComm(c.id)">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 inline"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                </button>
              </td>
            </tr>
            <tr v-if="!communications.length"><td colspan="7" class="border border-border py-8 text-center text-muted-foreground">No results.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════ TAB: Offences ══════ -->
    <div v-show="activeTab === 'offences'" class="rounded-lg border bg-card shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-foreground">Offences</h3>
        <button type="button" class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openAddOffence">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          Add
        </button>
      </div>
      <div class="rounded-lg bg-muted/30 p-4">
        <div class="flex items-center justify-end gap-3 mb-3">
          <button class="text-muted-foreground hover:text-foreground" title="Sort by date" @click="offenceSortAsc = !offenceSortAsc"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="M11 4h10"/><path d="M11 8h7"/><path d="M11 12h4"/></svg></button>
          <input v-model="offenceSearch" type="text" placeholder="Search" class="h-9 w-56 rounded-md border border-border bg-background px-3 text-sm" />
        </div>

        <div v-if="!filteredOffences.length" class="rounded-lg bg-card border border-border px-4 py-3 text-sm text-muted-foreground">No results.</div>

        <div v-else class="space-y-px">
          <div v-for="o in filteredOffences" :key="o.id" class="bg-card border border-border first:rounded-t-lg last:rounded-b-lg px-4 py-3">
            <div class="grid grid-cols-[140px_1fr_auto_auto_auto_auto] items-start gap-4">
              <div class="text-sm font-medium text-foreground">Unit No {{ unit?.unit_number }}</div>
              <div class="text-sm">
                <template v-if="o.rules && o.rules.length">
                  <div v-for="(r, i) in o.rules" :key="i" class="mb-0.5">
                    <p class="font-semibold text-foreground">{{ r.rule }}</p>
                    <p class="text-muted-foreground">{{ r.clause }}</p>
                  </div>
                </template>
                <p v-if="o.description" class="text-muted-foreground mt-1">{{ o.description }}</p>
              </div>
              <div class="text-sm text-muted-foreground whitespace-nowrap">
                <span class="text-foreground">Issued:</span><br />{{ o.issued_date }}
              </div>
              <div class="text-sm text-muted-foreground inline-flex items-center gap-1 pt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                {{ (o.attachment_names || []).length }}
              </div>
              <div class="pt-0.5">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium', offenceBadge(o.status).class]">{{ offenceBadge(o.status).label }}</span>
              </div>
              <div class="relative pt-0.5">
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="offenceMenuId = offenceMenuId === o.id ? null : o.id"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg></button>
                <div v-if="offenceMenuId === o.id" class="absolute right-0 top-6 z-20 w-32 rounded-md border border-border bg-card shadow-lg py-1">
                  <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="openEditOffence(o)">Edit</button>
                  <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-destructive hover:bg-muted" @click="deleteOffence(o)">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════ TAB: Tasks ══════ -->
    <div v-show="activeTab === 'tasks'" class="rounded-lg border bg-card shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-foreground">Tasks</h3>
        <button type="button" class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openAddTask">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          Add
        </button>
      </div>

      <div class="rounded-lg bg-muted/30 p-4">
        <div class="flex items-center justify-end mb-3">
          <input v-model="taskSearch" type="text" placeholder="Search" class="h-9 w-56 rounded-md border border-border bg-background px-3 text-sm" />
        </div>

        <div v-if="!filteredTasks.length" class="rounded-lg bg-card border border-border px-4 py-8 text-center text-sm text-muted-foreground">No tasks.</div>

        <div v-else class="space-y-px">
          <div v-for="t in filteredTasks" :key="t.id" class="bg-card border border-border first:rounded-t-lg last:rounded-b-lg px-4 py-3 hover:bg-muted/30 transition-colors">
            <div class="grid grid-cols-[1fr_130px_120px_140px_70px_100px_auto] items-center gap-4">
              <div class="min-w-0 cursor-pointer" @click="openTaskDetail(t)">
                <p class="text-sm font-semibold text-foreground truncate">{{ t.code }} : {{ t.title }}</p>
                <p class="text-xs text-muted-foreground truncate">{{ t.category || 'No Category' }} ({{ t.task_type || '' }})</p>
              </div>
              <div class="text-sm text-muted-foreground">
                <span class="text-[11px] uppercase tracking-wide text-muted-foreground/70 block">Area</span>
                {{ t.area === 'Unit' ? 'Unit No ' + (unit?.unit_number || '') : (t.area || '—') }}
              </div>
              <div class="text-sm">
                <span class="text-[11px] uppercase tracking-wide text-muted-foreground/70 block">Due Date</span>
                <span class="font-semibold text-foreground">{{ t.due_date || '—' }}</span>
              </div>
              <div class="text-sm">
                <span class="text-[11px] uppercase tracking-wide text-muted-foreground/70 block">Assignee</span>
                <span class="font-semibold text-foreground">{{ t.assignee_name || '—' }}</span>
              </div>
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-0.5" title="Attachments">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  {{ (t.attachment_names || []).length }}
                </span>
                <span class="inline-flex items-center gap-0.5" title="Updates">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  {{ t.updates_count ?? (t.updates || []).length }}
                </span>
              </div>
              <div>
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium', taskBadge(t.status).class]">{{ taskBadge(t.status).label }}</span>
              </div>
              <div class="relative">
                <button type="button" class="text-muted-foreground hover:text-foreground" @click.stop="taskMenuId = taskMenuId === t.id ? null : t.id"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg></button>
                <div v-if="taskMenuId === t.id" class="absolute right-0 top-6 z-20 w-32 rounded-md border border-border bg-card shadow-lg py-1">
                  <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="taskMenuId = null; openTaskDetail(t)">View</button>
                  <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="openEditTask(t)">Edit</button>
                  <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-destructive hover:bg-muted" @click="deleteTask(t)">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════ TAB: Documents ══════ -->
    <div v-show="activeTab === 'documents'" class="rounded-lg border bg-card shadow-sm p-6">
      <div class="bg-muted/40 rounded px-4 py-2.5 mb-4"><h3 class="text-sm font-semibold text-foreground">Customer Documents</h3></div>
      <div class="flex justify-end mb-4">
        <button type="button" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="openUploadDoc">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
          Upload
        </button>
      </div>

      <div v-if="!documents.length" class="rounded-lg border border-border px-4 py-8 text-center text-sm text-muted-foreground">No documents.</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-[#eef1f5]">
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark">Name</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark whitespace-nowrap">Uploaded By</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark whitespace-nowrap">Date</th>
              <th class="border border-border py-2.5 px-3 text-left font-bold text-navy-dark whitespace-nowrap">Size</th>
              <th class="border border-border py-2.5 px-3 text-center font-bold text-navy-dark">Download</th>
              <th class="border border-border py-2.5 px-3 text-center font-bold text-navy-dark">Delete</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in documents" :key="d.id" class="hover:bg-muted/30">
              <td class="border border-border py-2.5 px-3 text-foreground">{{ d.name }}</td>
              <td class="border border-border py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ d.uploaded_by_name || '—' }}</td>
              <td class="border border-border py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ (d.created_at || '').slice(0, 10) }}</td>
              <td class="border border-border py-2.5 px-3 text-muted-foreground whitespace-nowrap">{{ formatBytes(d.size) }}</td>
              <td class="border border-border py-2.5 px-3 text-center">
                <a :href="d.download_url" target="_blank" rel="noopener" class="inline-flex text-[#2f6fb0] hover:text-accent" title="Download">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </a>
              </td>
              <td class="border border-border py-2.5 px-3 text-center">
                <button type="button" class="inline-flex text-muted-foreground hover:text-destructive" title="Delete" @click="deleteDocument(d)">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════ Edit Unit modal ══════ -->
    <AppModal :show="showEditUnit" size="md" @close="showEditUnit = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">Unit No {{ unit?.unit_number }}</h3>
      </template>
      <div class="space-y-4">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>
        <div>
          <label class="block text-sm text-muted-foreground mb-1">Unit Notes:</label>
          <textarea v-model="editUnitForm.unit_notes" rows="3" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"></textarea>
        </div>
        <AppInput v-model="editUnitForm.rental_agent_email" label="Rental Agent Email:" type="email" />
        <AppInput v-model="editUnitForm.attorney_email" label="Attorney/Other Email:" type="email" />
        <AppInput v-model="editUnitForm.bondholder_email" label="Bondholder Email:" type="email" />
      </div>
      <template #footer>
        <button type="button" :disabled="saving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveEditUnit">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>

    <!-- ══════ Owner modal (edit / add / transfer) ══════ -->
    <AppModal :show="showEditOwner" size="md" @close="showEditOwner = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">{{ ownerModalTitle }}</h3>
      </template>
      <div class="space-y-3">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>
        <AppInput v-model="editOwnerForm.name" label="Name:" />
        <AppInput v-model="editOwnerForm.email" label="Email:" type="email" />
        <label class="flex items-center gap-2 text-sm text-muted-foreground"><input v-model="editOwnerForm.billing_pdf" type="checkbox" class="h-4 w-4 rounded border-border accent-primary" /> Billing PDF:</label>
        <AppInput v-model="editOwnerForm.phone" label="Cellphone:" />
        <AppInput v-model="editOwnerForm.landline" label="Landline:" />
        <AppSelect v-model="editOwnerForm.entity_type" label="Entity Type:" :options="ENTITY_TYPES" />
        <AppInput v-model="editOwnerForm.id_number" label="ID Number or Reg Number:" />
        <AppInput v-model="editOwnerForm.contact2_name" label="Contact 2 Name:" />
        <AppInput v-model="editOwnerForm.contact2_email" label="Contact 2 Email:" type="email" />
        <AppInput v-model="editOwnerForm.contact2_phone" label="Contact 2 Cellphone:" />
        <AppInput v-model="editOwnerForm.contact2_landline" label="Contact 2 Landline:" />
        <label class="flex items-center gap-2 text-sm text-muted-foreground"><input v-model="editOwnerForm.owner_occupied" type="checkbox" class="h-4 w-4 rounded border-border accent-primary" /> Owner Occupied:</label>
      </div>
      <template #footer>
        <button type="button" :disabled="saving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveEditOwner">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>

    <!-- ══════ Edit Customer modal ══════ -->
    <AppModal :show="showEditCustomer" size="xl" @close="showEditCustomer = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">{{ customerCode }}: {{ owner?.full_name }}</h3>
      </template>
      <div class="space-y-5">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
          <AppSelect v-model="editCustomerForm.customer_type" label="Customer Type" :options="CUSTOMER_TYPES" />
          <AppSelect v-model="editCustomerForm.customer_group" label="Customer Group" :options="['', ...(editCustomerForm.customer_group ? [editCustomerForm.customer_group] : [])]" placeholder="Select Groups" />
          <AppInput v-model="editCustomerForm.name" label="Name" />
          <AppInput v-model="editCustomerForm.reference" label="Reference" />
          <AppInput v-model="editCustomerForm.customer_code" label="Customer Code" />
          <AppInput v-model="editCustomerForm.old_customer_code" label="OLD Customer Code" hint="Only for reference purposes" />
          <AppInput v-model="editCustomerForm.vat_no" label="Vat No." />
          <AppSelect v-model="editCustomerForm.country" label="Country" :options="['South Africa']" placeholder="South Africa" />
          <AppInput v-model="editCustomerForm.email" label="E-mail" type="email" />
          <AppInput v-model="editCustomerForm.alt_email" label="Alt E-mail" type="email" />
          <AppInput v-model="editCustomerForm.phone" label="Tel No." />
          <AppInput v-model="editCustomerForm.alt_phone" label="Alt No." />
          <AppInput v-model="editCustomerForm.id_number" label="ID Number" />
          <AppSelect v-model="editCustomerForm.payment_type" label="Payment Type" :options="PAYMENT_TYPES" />
          <AppSelect v-model="editCustomerForm.pdf_password" label="PDF Password" :options="PDF_PASSWORD" />
          <AppSelect v-model="editCustomerForm.collection_status" label="Collection Status" :options="COLLECTION_STATUSES" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
          <div>
            <h4 class="text-base font-semibold text-foreground mb-3">Address Details</h4>
            <div class="space-y-3">
              <AppInput v-model="editCustomerForm.address" label="Address" />
              <AppInput v-model="editCustomerForm.address_line_2" label="Address Line 2" />
              <AppInput v-model="editCustomerForm.suburb" label="Suburb" />
              <AppInput v-model="editCustomerForm.town" label="Town" />
              <AppInput v-model="editCustomerForm.postal_code" label="Postal Code" />
            </div>
          </div>
          <div>
            <h4 class="text-base font-semibold text-foreground mb-3">Banking Details</h4>
            <div class="space-y-3">
              <AppInput v-model="editCustomerForm.account_holder" label="Account Holder" />
              <AppSelect v-model="editCustomerForm.bank_name" label="Bank Name" :options="BANK_NAMES" placeholder="Nothing selected" />
              <AppSelect v-model="editCustomerForm.account_type" label="Account Type" :options="ACCOUNT_TYPES" placeholder="Nothing selected" />
              <AppInput v-model="editCustomerForm.account_number" label="Account Number" />
              <AppInput v-model="editCustomerForm.branch_code" label="Branch Code" />
              <AppInput v-model="editCustomerForm.branch_name" label="Branch Name" />
            </div>
          </div>
        </div>

        <div>
          <label class="block text-base font-semibold text-foreground mb-2">Notes</label>
          <textarea v-model="editCustomerForm.notes" rows="3" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"></textarea>
        </div>
      </div>
      <template #footer>
        <button type="button" :disabled="saving" class="inline-flex items-center gap-2 rounded-md bg-accent px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveEditCustomer">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M20 6 9 17l-5-5"/></svg>
          {{ saving ? 'Saving…' : 'Update Customer' }}
        </button>
      </template>
    </AppModal>

    <!-- ══════ Add Occupant modal ══════ -->
    <AppModal :show="showAddOccupant" size="md" @close="showAddOccupant = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">Add Occupant</h3>
      </template>
      <div class="space-y-3">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>
        <AppInput v-model="addOccupantForm.first_name" label="First Name:" />
        <AppInput v-model="addOccupantForm.last_name" label="Last Name:" />
        <AppInput v-model="addOccupantForm.email" label="Email:" type="email" />
        <AppInput v-model="addOccupantForm.phone" label="Cell Number:" />
        <AppInput v-model="addOccupantForm.id_number" label="ID Number:" />
        <AppInput v-model="addOccupantForm.postal_address" label="Postal Address:" />
        <AppInput v-model="addOccupantForm.car_registration" label="Car Registration #:" />
        <AppInput v-model="addOccupantForm.lease_start" label="Lease Start:" type="date" />
        <AppInput v-model="addOccupantForm.lease_end" label="Lease End:" type="date" />
        <AppInput v-model="addOccupantForm.lease_amount" label="Lease Amount:" type="number" />
      </div>
      <template #footer>
        <button type="button" :disabled="saving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveAddOccupant">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>

    <!-- ══════ Send E-Mail modal (Communication) ══════ -->
    <SendEmailModal
      :show="showSendEmail"
      :community-id="communityId"
      :unit-id="unitId"
      :recipients="emailRecipients"
      :default-recipient="defaultRecipient"
      @close="showSendEmail = false"
      @sent="fetchAll"
    />

    <!-- ══════ E-mail preview modal (click a subject) ══════ -->
    <AppModal :show="emailModalOpen" size="xl" @close="emailModalOpen = false">
      <template #header>
        <h3 class="text-base font-semibold text-foreground pr-8">{{ activeEmail?.subject }}</h3>
      </template>

      <div class="max-h-[72vh] overflow-y-auto -mx-1 px-1">
        <!-- Attachments -->
        <div v-if="activeEmail?.attachment_names?.length" class="flex items-start gap-3 mb-5 pb-4 border-b border-border">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 text-muted-foreground shrink-0">
            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
          </svg>
          <div>
            <p class="text-sm font-bold text-foreground mb-1">Attachments:</p>
            <button
              v-for="(a, i) in activeEmail.attachment_names" :key="i"
              type="button" class="block text-sm text-[#2f6fb0] hover:underline"
              @click="downloadComm(activeEmail.id)"
            >{{ a }}</button>
          </div>
        </div>

        <!-- Rendered e-mail body (Bold Mark branding is baked into the sent HTML) -->
        <div class="email-body text-sm text-foreground" v-html="activeEmail?.body"></div>
      </div>
    </AppModal>

    <!-- ══════ Add / Edit Offence modal ══════ -->
    <AppModal :show="showOffenceModal" size="lg" @close="showOffenceModal = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">{{ offenceMode === 'edit' ? 'Edit Offence' : 'Add Offence' }}</h3>
      </template>
      <div class="space-y-4">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>

        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="offenceForm.status" label="Status" :options="OFFENCE_STATUSES" />
          <AppInput v-model="offenceForm.issued_date" label="Issued Date" type="date" />
        </div>

        <div>
          <label class="block text-sm font-medium text-foreground mb-2">Conduct Rules</label>
          <div class="space-y-2">
            <div v-for="(r, i) in offenceForm.rules" :key="i" class="grid grid-cols-[1fr_1fr_auto] gap-2 items-center">
              <input v-model="r.rule" type="text" placeholder="Rule (e.g. Prescribed Conduct Rule 7)" class="h-9 rounded-md border border-border bg-background px-3 text-sm" />
              <input v-model="r.clause" type="text" placeholder="Clause (e.g. Rule 7(1))" class="h-9 rounded-md border border-border bg-background px-3 text-sm" />
              <button type="button" class="text-muted-foreground hover:text-destructive px-1" title="Remove" @click="removeRuleLine(i)">✕</button>
            </div>
          </div>
          <button type="button" class="text-[#2f6fb0] text-xs mt-2 inline-flex items-center gap-1" @click="addRuleLine">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M5 12h14M12 5v14"/></svg> Add rule
          </button>
        </div>

        <div>
          <label class="block text-sm font-medium text-foreground mb-1">Description</label>
          <textarea v-model="offenceForm.description" rows="2" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-foreground mb-1">Attachments</label>
          <input type="file" multiple class="text-sm" @change="e => offenceFiles = Array.from(e.target.files || [])" />
        </div>
      </div>
      <template #footer>
        <button type="button" :disabled="saving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveOffence">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>

    <!-- ══════ Add / Edit Task modal ══════ -->
    <AppModal :show="showTaskModal" size="lg" @close="showTaskModal = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full" style="font-family: 'DM Sans', sans-serif">{{ taskMode === 'edit' ? 'Edit Task' : 'Add Task' }}</h3>
      </template>
      <div class="space-y-4">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>

        <div>
          <label class="block text-sm text-muted-foreground mb-1">Task Title</label>
          <input v-model="taskForm.title" type="text" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-muted-foreground mb-1">Due Date</label>
            <input v-model="taskForm.due_date" type="date" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
          </div>
          <AppSelect v-model="taskForm.recurring_type" label="Recurring Type" :options="RECURRING_TYPES" />
        </div>

        <div>
          <AppSelect v-model="taskForm.assignee_user_id" label="Assignee" :options="assigneeOptions" @update:modelValue="onAssigneeChange" />
        </div>

        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          <input v-model="taskForm.internal" type="checkbox" class="rounded border-border text-accent focus:ring-accent" />
          Internal
        </label>

        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="taskForm.area" label="Area" :options="TASK_AREAS" />
          <div>
            <label class="block text-sm text-muted-foreground mb-1">Select Unit</label>
            <input :value="'Unit No ' + (unit?.unit_number || '')" type="text" disabled class="w-full h-10 rounded-md border border-border bg-muted/40 px-3 text-sm text-muted-foreground" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="taskForm.category" label="Task Category" :options="TASK_CATEGORIES" placeholder="-- Select --" @update:modelValue="taskForm.task_type = ''" />
          <AppSelect v-model="taskForm.task_type" label="Task Type" :options="taskTypeOptions" placeholder="-- Select --" :disabled="!taskTypeOptions.length" />
        </div>

        <div>
          <label class="block text-sm text-muted-foreground mb-1">Contacts</label>
          <select v-model="taskForm.contacts" multiple class="w-full min-h-[64px] rounded-md border border-border bg-background px-3 py-2 text-sm">
            <option v-for="c in contactOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <p v-if="!contactOptions.length" class="text-xs text-muted-foreground mt-1">Not Applicable</p>
        </div>

        <div>
          <label class="block text-sm text-muted-foreground mb-1">Task Description</label>
          <textarea v-model="taskForm.description" rows="4" maxlength="5000" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"></textarea>
          <p class="text-xs text-muted-foreground mt-1 text-right">Characters: {{ (taskForm.description || '').length }}</p>
        </div>

        <div>
          <label class="block text-sm text-muted-foreground mb-1">File Uploads</label>
          <input type="file" multiple class="text-sm" @change="onTaskFileSelect" />
          <ul v-if="taskFiles.length" class="mt-2 space-y-1">
            <li v-for="(f, i) in taskFiles" :key="i" class="flex items-center justify-between text-xs bg-muted/40 rounded px-2 py-1">
              <span class="truncate">{{ f.name }}</span>
              <button type="button" class="text-muted-foreground hover:text-destructive" @click="taskFiles.splice(i, 1)">✕</button>
            </li>
          </ul>
        </div>

        <!-- Add Details → Suppliers -->
        <div v-if="taskShowDetails" class="rounded-md bg-muted/30 border border-border p-3">
          <label class="block text-sm text-muted-foreground mb-1">Supplier</label>
          <div class="flex gap-2">
            <input v-model="supplierInput" type="text" placeholder="Start typing to search suppliers (Name, Code or Account)" class="flex-1 h-9 rounded-md border border-border bg-background px-3 text-sm" @keydown.enter.prevent="addSupplier" />
            <button type="button" class="rounded-md bg-accent px-3 text-sm font-medium text-white hover:opacity-90" @click="addSupplier">Add</button>
          </div>
          <ul v-if="taskForm.supplier_names.length" class="mt-2 space-y-1">
            <li v-for="(s, i) in taskForm.supplier_names" :key="i" class="flex items-center justify-between text-xs bg-card border border-border rounded px-2 py-1">
              <span>{{ s }}</span>
              <button type="button" class="text-muted-foreground hover:text-destructive" @click="removeSupplier(i)">✕</button>
            </li>
          </ul>
        </div>
        <button type="button" class="text-sm rounded-md border border-border bg-muted/40 px-3 py-1.5 text-foreground hover:bg-muted" @click="taskShowDetails = !taskShowDetails">
          {{ taskShowDetails ? 'Hide Suppliers' : 'Add Details' }}
        </button>
      </div>
      <template #footer>
        <div class="flex items-center gap-3">
          <button type="button" :disabled="saving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveTask">{{ saving ? 'Saving…' : (taskMode === 'edit' ? 'Update' : 'Save') }}</button>
          <button type="button" class="rounded-md border border-border bg-white px-6 py-2 text-sm font-medium text-foreground hover:bg-muted" @click="showTaskModal = false">Cancel</button>
        </div>
      </template>
    </AppModal>

    <!-- ══════ Task detail modal ══════ -->
    <AppModal :show="showTaskDetail" size="lg" @close="showTaskDetail = false">
      <template #header>
        <div class="w-full flex items-center justify-between">
          <select :value="activeTask?.status" class="rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium px-3 py-1 border-0 focus:ring-1 focus:ring-accent" @change="changeTaskStatus($event.target.value)">
            <option v-for="s in TASK_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
      </template>
      <div v-if="activeTask" class="space-y-4">
        <div>
          <p class="text-sm text-muted-foreground">{{ activeTask.code }} : {{ unit?.community?.name || 'Community' }}</p>
          <h4 class="text-base font-bold text-foreground">{{ activeTask.title }}</h4>
        </div>

        <div v-if="activeTask.description" class="text-sm text-muted-foreground">
          <p :class="taskDescExpanded ? '' : 'line-clamp-2'">{{ activeTask.description }}</p>
          <button v-if="(activeTask.description || '').length > 120" type="button" class="text-[#2f6fb0] text-xs mt-0.5" @click="taskDescExpanded = !taskDescExpanded">{{ taskDescExpanded ? 'read less' : 'read more...' }}</button>
        </div>

        <p class="text-xs text-muted-foreground flex items-center gap-2">
          Created by: {{ activeTask.created_by_name || 'System' }}<span v-if="activeTask.created_at"> on {{ (activeTask.created_at || '').slice(0, 10).split('-').reverse().join('/') }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#e8a040" class="w-3.5 h-3.5"><path d="M4 22V4a1 1 0 0 1 1-1h13l-3 5 3 5H6v9z"/></svg>
        </p>

        <div class="rounded-lg bg-muted/30 border border-border p-4 space-y-2.5 text-sm">
          <div class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Area</span><span class="font-medium text-foreground">{{ activeTask.area === 'Unit' ? 'Unit No ' + (unit?.unit_number || '') : (activeTask.area || 'Not Applicable') }}</span></div>
          <div class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Task Category</span><span class="font-medium text-foreground">{{ activeTask.category || 'No Category' }}{{ activeTask.task_type ? ' (' + activeTask.task_type + ')' : '' }}</span></div>
          <div class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Contacts</span><span class="font-medium text-foreground">{{ (activeTask.contacts || []).length ? activeTask.contacts.join(', ') : 'N/A' }}</span></div>
          <div class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Assignee</span><span class="font-medium text-foreground">{{ activeTask.assignee_name || '—' }}</span></div>
          <div class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Due Date</span><span class="font-medium text-foreground">{{ activeTask.due_date ? (activeTask.due_date.split('-').reverse().join('/')) : '—' }}</span></div>
          <div v-if="(activeTask.supplier_names || []).length" class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Suppliers</span><span class="font-medium text-foreground">{{ activeTask.supplier_names.join(', ') }}</span></div>
          <div v-if="(activeTask.attachment_names || []).length" class="grid grid-cols-[130px_1fr] gap-2"><span class="text-muted-foreground">Uploads</span><span class="font-medium text-foreground">{{ activeTask.attachment_names.join(', ') }}</span></div>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" class="text-sm rounded-md border border-border bg-muted/40 px-3 py-1.5 text-foreground hover:bg-muted" @click="openEditTask(activeTask)">Edit</button>
          <button type="button" class="text-sm rounded-md border border-border bg-muted/40 px-3 py-1.5 text-foreground hover:bg-muted" @click="openEditTask(activeTask)">Add Details</button>
          <button type="button" class="text-sm rounded-md border border-border bg-muted/40 px-3 py-1.5 text-foreground hover:bg-muted" @click="openEditTask(activeTask)">Manage Suppliers</button>
        </div>

        <!-- Task Updates -->
        <div class="pt-2 border-t border-border">
          <h5 class="text-sm font-semibold text-foreground mb-2">Task Updates</h5>
          <textarea v-model="feedbackText" rows="3" placeholder="Add Feedback" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"></textarea>
          <div class="flex items-center gap-2 mt-2">
            <select v-model="feedbackNotify" class="h-9 rounded-md border border-border bg-background px-3 text-sm text-muted-foreground">
              <option v-for="o in notifyOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <label class="cursor-pointer text-muted-foreground hover:text-foreground" title="Attach">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
              <input type="file" multiple class="hidden" @change="onFeedbackFileSelect" />
            </label>
            <span v-if="feedbackFiles.length" class="text-xs text-muted-foreground">{{ feedbackFiles.length }} file(s)</span>
            <button type="button" :disabled="feedbackSaving" class="ml-auto rounded-md bg-accent px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="submitFeedback">{{ feedbackSaving ? 'Sending…' : 'Submit' }}</button>
          </div>

          <ul v-if="(activeTask.updates || []).length" class="mt-4 space-y-3">
            <li v-for="u in activeTask.updates" :key="u.id" class="text-sm border-l-2 border-border pl-3">
              <p v-if="u.event" class="font-medium text-foreground">{{ u.event }}</p>
              <p v-if="u.feedback" class="text-foreground">{{ u.feedback }}</p>
              <p class="text-xs text-muted-foreground mt-0.5">{{ u.created_by_name }}<span v-if="u.created_at"> · {{ u.created_at.slice(0, 16).replace('T', ' ') }}</span></p>
            </li>
          </ul>
        </div>
      </div>
    </AppModal>

    <!-- ══════ Upload Customer Document modal ══════ -->
    <AppModal :show="showDocModal" size="md" @close="showDocModal = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">Upload Customer Document</h3>
      </template>
      <div class="space-y-4">
        <div v-if="docError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ docError }}</div>

        <div class="grid grid-cols-[120px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">Document Name</label>
          <input v-model="docName" type="text" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
        </div>

        <div class="grid grid-cols-[120px_1fr] gap-3">
          <label class="text-sm text-muted-foreground pt-2">Upload</label>
          <div>
            <div
              class="border-2 border-dashed rounded-lg py-6 px-4 text-center transition-colors"
              :class="docDragging ? 'border-accent bg-accent/5' : 'border-[#DCDEE8]'"
              @dragover.prevent="docDragging = true" @dragleave.prevent="docDragging = false" @drop.prevent="onDocDrop"
            >
              <p class="text-sm text-[#717B99]">Drag and drop your document here</p>
            </div>
            <input ref="docInput" type="file" class="hidden" @change="onDocSelect" />
            <button type="button" class="mt-2 inline-flex items-center gap-2 rounded-md bg-accent px-3.5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity" @click="$refs.docInput.click()">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Click to select document
            </button>
            <p v-if="docFile" class="mt-2 text-xs text-foreground bg-muted/40 rounded px-2 py-1 inline-flex items-center gap-2">
              {{ docFile.name }}
              <button type="button" class="text-muted-foreground hover:text-destructive" @click="docFile = null">✕</button>
            </p>
          </div>
        </div>
      </div>
      <template #footer>
        <button type="button" :disabled="docSaving" class="rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="uploadDocument">{{ docSaving ? 'Uploading…' : 'Upload' }}</button>
      </template>
    </AppModal>

    <!-- ══════ Confirm e-mail statement dialog ══════ -->
    <Teleport to="body">
      <div v-if="showEmailConfirm" class="fixed inset-0 z-[60] flex items-start justify-center pt-24 px-4">
        <div class="absolute inset-0 bg-black/40" @click="showEmailConfirm = false" />
        <div class="relative w-full max-w-xl rounded-lg bg-white shadow-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 bg-muted/40 border-b border-border">
            <h3 class="text-base font-bold text-navy-dark w-full text-center" style="font-family: 'DM Sans', sans-serif">Confirm</h3>
            <button type="button" class="text-muted-foreground hover:text-navy-dark" @click="showEmailConfirm = false"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
          </div>
          <div class="px-6 py-5">
            <p class="text-sm text-foreground mb-5">Are you sure you want to e-mail the statement?</p>
            <div class="flex items-center gap-3">
              <button type="button" :disabled="emailingStatement" class="rounded-md bg-accent px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="confirmEmailStatement">{{ emailingStatement ? 'Sending…' : 'Yes' }}</button>
              <button type="button" class="rounded-md border border-border bg-white px-5 py-2 text-sm font-medium text-foreground hover:bg-muted" @click="showEmailConfirm = false">No</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════ Confirm Transfer dialog (Change Ownership) ══════ -->
    <Teleport to="body">
      <div v-if="showConfirmTransfer" class="fixed inset-0 z-[60] flex items-start justify-center pt-24 px-4">
        <div class="absolute inset-0 bg-black/40" @click="showConfirmTransfer = false" />
        <div class="relative w-full max-w-xl rounded-lg bg-white shadow-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 bg-muted/40 border-b border-border">
            <h3 class="text-base font-bold text-navy-dark w-full text-center" style="font-family: 'DM Sans', sans-serif">Confirm Transfer</h3>
            <button type="button" class="text-muted-foreground hover:text-navy-dark" @click="showConfirmTransfer = false"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
          </div>
          <div class="px-6 py-5">
            <p class="text-sm text-foreground mb-4">The selected unit is currently owned by:</p>
            <div class="rounded-lg bg-muted/30 border border-border px-5 py-4 mb-5">
              <p class="text-sm font-bold text-foreground mb-2">{{ owner?.full_name || '—' }}</p>
              <p v-if="owner?.email" class="text-sm text-foreground mb-1">{{ owner.email }}</p>
              <p v-if="owner?.phone" class="text-sm text-foreground">{{ owner.phone }}</p>
            </div>
            <p class="text-sm font-bold text-foreground mb-4">Would you like to initiate the transfer process?</p>
            <div class="flex items-center gap-3">
              <button type="button" class="rounded-md bg-accent px-5 py-2 text-sm font-semibold text-white hover:opacity-90" @click="confirmTransfer">Yes</button>
              <button type="button" class="rounded-md border border-border bg-white px-5 py-2 text-sm font-medium text-foreground hover:bg-muted" @click="showConfirmTransfer = false">No</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Back to units ───────────────────────────────────────────── -->
    <div class="mt-5">
      <button type="button" class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity" @click="backToUnits">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="m15 18-6-6 6-6"/></svg>
        Back to units
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Sent-email HTML is rendered as-is; keep its own branding but fit the modal. */
.email-body :deep(img) { max-width: 100%; height: auto; }
.email-body :deep(table) { max-width: 100%; }
.email-body :deep(a) { color: #2f6fb0; }
</style>
