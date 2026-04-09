<script>
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
} from 'chart.js'
ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title)
</script>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Doughnut, Bar } from 'vue-chartjs'
import AppAlert from '@/components/common/AppAlert.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'
import AppPoptip from '@/components/common/AppPoptip.vue'
import AppDropdown from '@/components/common/AppDropdown.vue'
import AppDropdownItem from '@/components/common/AppDropdownItem.vue'
import BulkImportUnitsModal from '@/components/common/BulkImportUnitsModal.vue'
import AppExportModal from '@/components/common/AppExportModal.vue'
import api from '@/composables/useApi.js'
import { useExport } from '@/composables/useExport.js'
import { useBack } from '@/composables/useBack.js'

const router = useRouter()
const route  = useRoute()
const { goBack } = useBack('/estates')

// ── API state ─────────────────────────────────────────────────────────
const estateLoading = ref(true)
const unitsLoading  = ref(true)
const estateError   = ref(null)
const unitsError    = ref(null)
const savingUnit    = ref(false)
const saveError     = ref(null)

const estate = ref(null)       // raw estate object from API (data key)
const apiStats = ref(null)     // stats key from estate API (occupancy counts + invoice status)
const allUnits = ref([])       // mapped unit list from units API (current page)
const chartStats = ref(null)   // charts key from units API (filter-aware aggregates)

// ── Fetch estate detail + stats ───────────────────────────────────────
async function fetchEstate() {
  estateLoading.value = true
  estateError.value   = null
  try {
    const res = await api.get(`/estates/${route.params.id}`)
    estate.value   = res.data.data
    apiStats.value = res.data.stats ?? null
  } catch (e) {
    estateError.value = 'Failed to load estate details.'
  } finally {
    estateLoading.value = false
  }
}

// ── Fetch units (server-side filtering, sorting, pagination) ──────────
const unitsMeta = ref(null)

function mapOccupancy(type) {
  return type === 'owner_occupied' ? 'owner'
    : type === 'tenant_occupied'  ? 'tenant'
    : 'vacant'
}

function formatLeaseDate(iso) {
  if (!iso) return null
  try {
    return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch { return iso }
}

// Maps frontend toolbar values → API query parameter values
const SORT_API_MAP = {
  unit_asc:     'unit_number:asc',
  unit_desc:    'unit_number:desc',
  owner_asc:    'owner_name:asc',
  owner_desc:   'owner_name:desc',
  balance_asc:  'outstanding_amount:asc',
  balance_desc: 'outstanding_amount:desc',
}
const OCCUPANCY_API_MAP = { owner: 'owner_occupied', tenant: 'tenant_occupied', vacant: 'vacant' }
const BALANCE_API_MAP   = { arrears: 'in_arrears', clear: 'clear' }

function buildApiParams() {
  const state  = toolbarState.value
  const params = { _per_page: PER_PAGE, page: currentPage.value }

  if (state.search?.trim()) {
    params._search = state.search.trim()
  }
  if (state.dateRange && state.dateRange !== 'all_time') {
    params._date_range = state.dateRange
    if (state.dateRange === 'custom') {
      if (state.customStart) params._date_range_start = state.customStart
      if (state.customEnd)   params._date_range_end   = state.customEnd
    }
  }
  if (state.filters?.occupancy) {
    params.occupancy_type = OCCUPANCY_API_MAP[state.filters.occupancy] ?? state.filters.occupancy
  }
  if (state.filters?.balance) {
    params.balance = BALANCE_API_MAP[state.filters.balance] ?? state.filters.balance
  }
  if (state.sort) {
    params._sort = SORT_API_MAP[state.sort] ?? state.sort
  }

  return params
}

async function fetchUnits() {
  unitsLoading.value = true
  unitsError.value   = null
  try {
    const res = await api.get(`/estates/${route.params.id}/units`, {
      params: buildApiParams(),
    })
    chartStats.value = res.data.charts ?? null
    allUnits.value  = (res.data.data ?? []).map(u => ({
      id:             u.id,
      unit:           u.unit_number,
      occupancy:      mapOccupancy(u.occupancy_type),
      balance:        u.balance ?? 0,
      outstanding:    u.outstanding_amount ?? 0,
      effectiveLevy:  u.effective_levy_amount ?? 0,
      rentAmount:     u.rent_amount ?? 0,

      // Owner
      ownerId:        u.owner?.id        ?? null,
      ownerName:      u.owner?.full_name ?? '—',
      ownerEmail:     u.owner?.email     ?? '',
      ownerPhone:     u.owner?.phone     ?? '',
      ownerIdNumber:  u.owner?.id_number ?? '',
      ownerLevy:      u.effective_levy_amount ?? 0,

      // Current tenant
      tenant:              u.current_tenant?.full_name ?? null,
      tenantId:            u.current_tenant?.id        ?? null,
      tenantCount:         u.total_tenants_count        ?? 0,
      tenantEmail:         u.current_tenant?.email      ?? null,
      tenantPhone:         u.current_tenant?.phone      ?? null,
      tenantLeaseStart:    formatLeaseDate(u.current_tenant?.lease_start),
      tenantLeaseEnd:      formatLeaseDate(u.current_tenant?.lease_end),
      tenantLeaseStartRaw: u.current_tenant?.lease_start ? u.current_tenant.lease_start.substring(0, 10) : '',
      tenantLeaseEndRaw:   u.current_tenant?.lease_end   ? u.current_tenant.lease_end.substring(0, 10)   : '',
      tenantRent:          u.rent_amount ?? null,
      tenantLeaseDoc:      null,
    }))
    unitsMeta.value = res.data.meta ?? null
  } catch (e) {
    unitsError.value = 'Failed to load units.'
  } finally {
    unitsLoading.value = false
  }
}

onMounted(() => {
  fetchEstate()
  // fetchUnits() is intentionally NOT called here.
  // AppTableToolbar emits its initial state on mount, which triggers
  // onToolbarUpdate → fetchUnits(). Calling it here too causes a double
  // fetch that produces the "No units" flash before real data arrives.
})

// ── Computed stats — estate-wide totals from apiStats (unaffected by table filters) ──
const computedStats = computed(() => {
  const s = apiStats.value
  return {
    units:          s?.total_units           ?? 0,
    owners:         s?.owner_occupied_count  ?? 0,
    tenants:        s?.tenant_occupied_count ?? 0,
    vacant:         s?.vacant_count          ?? 0,
    monthlyRevenue: s?.monthly_revenue       ?? 0,
    totalBalance:   s?.total_balance         ?? 0,
  }
})

// ── AppTableToolbar config ────────────────────────────────────────────
const OCCUPANCY_OPTS = [
  { value: 'owner',  label: 'Owner Occupied'  },
  { value: 'tenant', label: 'Tenant Occupied' },
  { value: 'vacant', label: 'Vacant'          },
]

const UNITS_FILTER_FIELDS = [
  {
    key: 'occupancy',
    label: 'Occupancy',
    options: [
      { value: 'owner',  label: 'Owner Occupied'  },
      { value: 'tenant', label: 'Tenant Occupied' },
      { value: 'vacant', label: 'Vacant'          },
    ],
  },
  {
    key: 'balance',
    label: 'Balance',
    options: [
      { value: 'arrears', label: 'In Arrears'    },
      { value: 'clear',   label: 'Clear Balance' },
    ],
  },
]

const UNITS_SORT_OPTIONS = [
  { value: 'unit_asc',     label: 'Unit (A → Z)'         },
  { value: 'unit_desc',    label: 'Unit (Z → A)'         },
  { value: 'owner_asc',    label: 'Owner (A → Z)'        },
  { value: 'owner_desc',   label: 'Owner (Z → A)'        },
  { value: 'balance_asc',  label: 'Balance (Low → High)' },
  { value: 'balance_desc', label: 'Balance (High → Low)' },
]

// ── Toolbar state ─────────────────────────────────────────────────────
const toolbarKey              = ref(0)   // increment to force-remount toolbar (clears all filters)
const toolbarInitialDateRange = ref('all_time')
const toolbarState = ref({ search: '', dateRange: 'all_time', customStart: '', customEnd: '', filters: {}, sort: null })

let searchDebounceTimer = null

function onToolbarUpdate(state) {
  const prevSearch   = toolbarState.value.search
  toolbarState.value = state
  currentPage.value  = 1

  // Debounce search input; apply all other changes immediately
  clearTimeout(searchDebounceTimer)
  if (state.search !== prevSearch) {
    unitsLoading.value = true  // show shimmer immediately on keystroke
    searchDebounceTimer = setTimeout(fetchUnits, 350)
  } else {
    fetchUnits()
  }
}

const currentPage = ref(1)
const PER_PAGE    = 15

// Pagination derived from API meta
const totalPages        = computed(() => unitsMeta.value?.last_page ?? 1)
const totalUnitsInQuery = computed(() => unitsMeta.value?.total     ?? 0)

function setPage(page) {
  currentPage.value = Math.min(Math.max(1, page), totalPages.value)
  selectedUnitIds.value = new Set()
  fetchUnits()
}

function onBulkImported() {
  // Set date filter to today so only the newly imported units are shown
  toolbarInitialDateRange.value = 'today'
  toolbarKey.value++
  toolbarState.value = { search: '', dateRange: 'today', customStart: '', customEnd: '', filters: {}, sort: null }
  currentPage.value  = 1
  fetchEstate()
  fetchUnits()
}

// ── Helpers ───────────────────────────────────────────────────────────
function formatAmount(amount) {
  if (amount === 0) return 'R 0'
  const abs  = Math.abs(amount).toLocaleString('en-US').replace(/,/g, '\u00A0')
  const sign = amount < 0 ? '-' : ''
  return `${sign}R\u00A0${abs}`
}

function balanceClass(balance) {
  if (balance < 0) return 'text-destructive'
  if (balance === 0) return 'text-success'
  return 'text-foreground'
}

function occupancyConfig(type) {
  const map = {
    owner:  { wrapClass: 'bg-success/10 text-success border border-success/20',       dotClass: 'bg-success',             label: 'Owner Occupied'  },
    tenant: { wrapClass: 'bg-blue-50 text-blue-700 border border-blue-200',           dotClass: 'bg-blue-500',            label: 'Tenant Occupied' },
    vacant: { wrapClass: 'bg-muted text-muted-foreground border border-border',       dotClass: 'bg-muted-foreground/40', label: 'Vacant'          },
  }
  return map[type] ?? map.vacant
}

function estateTypeLabel(type) {
  const map = {
    sectional_title:    'Sectional Title',
    mixed:              'Mixed',
    residential_rental: 'Residential',
    commercial_rental:  'Commercial',
  }
  return map[type] ?? type
}

function goToUnit(unitId) {
  router.push({ name: 'unit-detail', params: { estateId: route.params.id, unitId } })
}

function goToTenant(event, unit) {
  event.stopPropagation()
  if (unit.tenantId) {
    router.push({ name: 'tenant-detail', params: { estateId: route.params.id, unitId: unit.id, tenantId: unit.tenantId } })
  }
}

function goToOwner(event, unit) {
  event.stopPropagation()
  if (unit.ownerId) {
    router.push({ name: 'owner-detail', params: { ownerId: unit.ownerId } })
  }
}

// ── Send Message modal ────────────────────────────────────────────────
const showSendMessage  = ref(false)
const messageSending   = ref(false)
const messageError     = ref(null)
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
  { value: 'payment_reminder', label: 'Payment Reminder',     subject: 'Rent Payment Reminder',      body: 'Dear {name},\n\nThis is a reminder that your rent payment is due. Please ensure timely payment to avoid any late fees.\n\nKind regards,\nBold Mark Properties' },
  { value: 'welcome',          label: 'Welcome Letter',        subject: 'Welcome to Your New Home',   body: 'Dear {name},\n\nWelcome! We hope you are settling in well.\n\nKind regards,\nBold Mark Properties' },
  { value: 'maintenance',      label: 'Maintenance Notice',    subject: 'Planned Maintenance Notice', body: 'Dear {name},\n\nWe wish to notify you of planned maintenance at your unit.\n\nKind regards,\nBold Mark Properties' },
  { value: 'lease_renewal',    label: 'Lease Renewal Notice',  subject: 'Your Lease Renewal',         body: 'Dear {name},\n\nYour lease is approaching its end date. Please contact our office at your convenience.\n\nKind regards,\nBold Mark Properties' },
  { value: 'statement',        label: 'Monthly Statement',     subject: 'Your Monthly Statement',     body: 'Dear {name},\n\nPlease find your monthly statement attached.\n\nKind regards,\nBold Mark Properties' },
]

const activeTemplates = computed(() =>
  messageRecipient.value?.role === 'tenant' ? TENANT_TEMPLATES : OWNER_TEMPLATES
)

function openSendMessageOwner(unit) {
  const parts = (unit.ownerName ?? '').split(' ')
  messageRecipient.value = {
    name:     unit.ownerName,
    email:    unit.ownerEmail,
    role:     'owner',
    initials: parts.map(p => p[0]).join('').slice(0, 2).toUpperCase(),
  }
  messageForm.value = { template: '', subject: '', body: '' }
  messageError.value = null
  showSendMessage.value = true
}

function openSendMessageTenant(unit) {
  const parts = (unit.tenant ?? '').split(' ')
  messageRecipient.value = {
    name:     unit.tenant,
    email:    unit.tenantEmail,
    role:     'tenant',
    initials: parts.map(p => p[0]).join('').slice(0, 2).toUpperCase(),
  }
  messageForm.value = { template: '', subject: '', body: '' }
  messageError.value = null
  showSendMessage.value = true
}

function onTemplateChange() {
  const tpl = activeTemplates.value.find(t => t.value === messageForm.value.template)
  if (!tpl) { messageForm.value.subject = ''; messageForm.value.body = ''; return }
  const firstName = (messageRecipient.value?.name ?? '').split(' ')[0]
  messageForm.value.subject = tpl.subject
  messageForm.value.body    = tpl.body.replace(/{name}/g, firstName)
}

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

// ── Modals ────────────────────────────────────────────────────────────
const showAddUnit    = ref(false)
const showBulkImport = ref(false)
const showExportModal = ref(false)

// ── Export ────────────────────────────────────────────────────────────
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
  if (state.filters?.occupancy) params.occupancy_type = OCCUPANCY_API_MAP[state.filters.occupancy] ?? state.filters.occupancy
  if (state.filters?.balance)   params.balance        = BALANCE_API_MAP[state.filters.balance]    ?? state.filters.balance
  if (state.sort)               params._sort          = SORT_API_MAP[state.sort] ?? state.sort

  params._format = format
  params._limit  = records

  return params
}

async function handleExportDownload({ format, records }) {
  showExportModal.value = false
  const ext      = format === 'xlsx' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
  const filename = `units-${new Date().toISOString().slice(0, 10)}.${ext}`
  await downloadExport(`/estates/${route.params.id}/units/export`, buildExportParams(format, records), filename)
}

// ── Edit Estate modal ─────────────────────────────────────────────────
const showEditEstate   = ref(false)
const editEstateSaving = ref(false)
const editEstateError  = ref(null)
const editEstateForm   = ref({
  name:                '',
  type:                'sectional_title',
  address:             '',
  default_levy_amount: '',
  default_rent_amount: '',
  billing_day:         '',
})

const ESTATE_TYPE_OPTS = [
  { value: 'sectional_title',    label: 'Sectional Title'    },
  { value: 'mixed',              label: 'Mixed'              },
  { value: 'residential_rental', label: 'Residential Rental' },
  { value: 'commercial_rental',  label: 'Commercial Rental'  },
]

const editShowLevy = computed(() =>
  ['sectional_title', 'mixed'].includes(editEstateForm.value.type)
)
const editShowRent = computed(() =>
  ['residential_rental', 'commercial_rental', 'mixed'].includes(editEstateForm.value.type)
)

function openEditEstate() {
  if (!estate.value) return
  editEstateForm.value = {
    name:                estate.value.name                ?? '',
    type:                estate.value.type                ?? 'sectional_title',
    address:             estate.value.address             ?? '',
    default_levy_amount: estate.value.default_levy_amount ?? '',
    default_rent_amount: estate.value.default_rent_amount ?? '',
    billing_day:         estate.value.billing_day         ?? '',
  }
  editEstateError.value = null
  showEditEstate.value  = true
}

async function saveEditEstate() {
  editEstateSaving.value = true
  editEstateError.value  = null
  try {
    const payload = {
      name:    editEstateForm.value.name    || undefined,
      type:    editEstateForm.value.type    || undefined,
      address: editEstateForm.value.address || undefined,
    }
    if (editShowLevy.value && editEstateForm.value.default_levy_amount !== '') {
      payload.default_levy_amount = parseFloat(editEstateForm.value.default_levy_amount) || 0
    }
    if (editShowRent.value && editEstateForm.value.default_rent_amount !== '') {
      payload.default_rent_amount = parseFloat(editEstateForm.value.default_rent_amount) || 0
    }
    if (editEstateForm.value.billing_day !== '') {
      payload.billing_day = parseInt(editEstateForm.value.billing_day) || undefined
    }
    await api.put(`/estates/${route.params.id}`, payload)
    showEditEstate.value = false
    await fetchEstate()
  } catch (e) {
    editEstateError.value = e?.response?.data?.message ?? 'Failed to update estate. Please try again.'
  } finally {
    editEstateSaving.value = false
  }
}

// ── Delete Estate modal ───────────────────────────────────────────────
const showDeleteEstate   = ref(false)
const deletingEstate     = ref(false)
const deleteEstateError  = ref(null)
const deleteConfirmName  = ref('')
const deleteCounts       = ref({ invoices: 0, cashbook: 0, loadingCounts: false })

const deleteNameMatches = computed(() =>
  deleteConfirmName.value.trim() === (estate.value?.name ?? '').trim()
)

// Items to list in the "will be deleted" section — only shown when count > 0
const deleteItems = computed(() => {
  const s    = computedStats.value
  const dc   = deleteCounts.value
  const type = estate.value?.type ?? ''
  const items = []

  if (s.units > 0) {
    items.push(`All ${s.units} unit${s.units !== 1 ? 's' : ''} and their owner records`)
  }
  // Tenants: only relevant for estates that can have tenants
  const hasTenants = ['residential_rental', 'commercial_rental', 'mixed'].includes(type)
    || s.tenants > 0
  if (hasTenants && s.tenants > 0) {
    items.push(`All ${s.tenants} tenant record${s.tenants !== 1 ? 's' : ''} and lease history`)
  } else if (s.units > 0) {
    items.push('All tenant history and lease records')
  }
  if (dc.invoices > 0) {
    items.push(`All ${dc.invoices} invoice${dc.invoices !== 1 ? 's' : ''} and billing history`)
  }
  if (dc.cashbook > 0) {
    items.push(`All ${dc.cashbook} cashbook entr${dc.cashbook !== 1 ? 'ies' : 'y'} and payment records`)
  }

  return items
})

async function openDeleteEstate() {
  deleteEstateError.value = null
  deleteConfirmName.value = ''
  showDeleteEstate.value  = true

  // Fetch invoice + cashbook counts in the background
  deleteCounts.value = { invoices: 0, cashbook: 0, loadingCounts: true }
  try {
    const [invRes, cbRes] = await Promise.all([
      api.get('/invoices',  { params: { estate_id: route.params.id, _per_page: 1 } }),
      api.get('/cashbook',  { params: { estate_id: route.params.id, _per_page: 1 } }),
    ])
    deleteCounts.value = {
      invoices:      invRes.data.meta?.total ?? 0,
      cashbook:      cbRes.data.meta?.total  ?? 0,
      loadingCounts: false,
    }
  } catch {
    deleteCounts.value = { invoices: 0, cashbook: 0, loadingCounts: false }
  }
}

async function confirmDeleteEstate() {
  if (!deleteNameMatches.value) return
  deletingEstate.value    = true
  deleteEstateError.value = null
  try {
    await api.delete(`/estates/${route.params.id}`)
    router.push('/estates')
  } catch (e) {
    deleteEstateError.value = e?.response?.data?.message ?? 'Failed to delete estate. Please try again.'
    deletingEstate.value    = false
  }
}

const newUnit = ref({
  unitNumber:   '',
  occupancy:    'owner',
  levyOverride: '',
  showTenant:   false,
  owner: {
    name:     '',
    email:    '',
    phone:    '',
    idNumber: '',
  },
  tenant: {
    name:       '',
    email:      '',
    phone:      '',
    rent:       '',
    leaseStart: '',
    leaseEnd:   '',
  },
})

const newUnitShowTenantFields = computed(() =>
  estate.value?.type !== 'sectional_title' &&
  (newUnit.value.occupancy === 'tenant' || newUnit.value.showTenant)
)

function resetNewUnit() {
  newUnit.value = {
    unitNumber: '', occupancy: 'owner', levyOverride: '', showTenant: false,
    owner:  { name: '', email: '', phone: '', idNumber: '' },
    tenant: { name: '', email: '', phone: '', rent: '', leaseStart: '', leaseEnd: '' },
  }
}

async function saveUnit() {
  savingUnit.value = true
  saveError.value  = null

  const occupancyMap = { owner: 'owner_occupied', tenant: 'tenant_occupied', vacant: 'vacant' }

  const payload = {
    unit_number:    newUnit.value.unitNumber,
    occupancy_type: occupancyMap[newUnit.value.occupancy],
    owner: {
      full_name: newUnit.value.owner.name,
      email:     newUnit.value.owner.email,
      phone:     newUnit.value.owner.phone,
      id_number: newUnit.value.owner.idNumber,
    },
  }

  if (editUnitShowLevy.value && newUnit.value.levyOverride !== '') {
    payload.levy_override = parseFloat(newUnit.value.levyOverride) || 0
  }

  if (newUnitShowTenantFields.value) {
    if (newUnit.value.tenant.rent !== '') {
      payload.rent_amount = parseFloat(newUnit.value.tenant.rent) || 0
    }
    if (newUnit.value.tenant.name) {
      payload.tenant = {
        full_name:   newUnit.value.tenant.name,
        email:       newUnit.value.tenant.email,
        phone:       newUnit.value.tenant.phone || null,
        lease_start: newUnit.value.tenant.leaseStart || null,
        lease_end:   newUnit.value.tenant.leaseEnd   || null,
      }
    }
  }

  try {
    await api.post(`/estates/${route.params.id}/units`, payload)
    showAddUnit.value = false
    resetNewUnit()
    // Set date filter to today so the newly created unit is shown prominently
    toolbarInitialDateRange.value = 'today'
    toolbarKey.value++
    toolbarState.value = { search: '', dateRange: 'today', customStart: '', customEnd: '', filters: {}, sort: null }
    currentPage.value  = 1
    await Promise.all([fetchEstate(), fetchUnits()])
  } catch (e) {
    saveError.value = e.response?.data?.message ?? 'Failed to create unit. Please try again.'
  } finally {
    savingUnit.value = false
  }
}

// ── Edit Unit modal ───────────────────────────────────────────────────
const showEditUnit   = ref(false)
const editingUnit    = ref(null)
const savingEditUnit = ref(false)
const editUnitError  = ref(null)

const editUnitForm = ref({
  unitNumber:    '',
  occupancy:     'owner',
  ownerName:     '',
  ownerEmail:    '',
  ownerPhone:    '',
  ownerIdNumber: '',
  levyOverride:  '',
  tenantName:    '',
  tenantEmail:   '',
  tenantPhone:   '',
  rentAmount:    '',
  leaseStart:    '',
  leaseEnd:      '',
})

const editUnitShowLevy = computed(() =>
  ['sectional_title', 'mixed'].includes(estate.value?.type)
)

function openEditUnit(unit) {
  editingUnit.value   = unit
  editUnitError.value = null
  editUnitForm.value  = {
    unitNumber:   unit.unit,
    occupancy:    unit.occupancy,
    levyOverride: unit.effectiveLevy ? String(unit.effectiveLevy) : '',
    owner: {
      name:     unit.ownerName     ?? '',
      email:    unit.ownerEmail    ?? '',
      phone:    unit.ownerPhone    ?? '',
      idNumber: unit.ownerIdNumber ?? '',
    },
    showTenant: unit.occupancy === 'tenant',
    tenant: {
      name:       unit.tenant              ?? '',
      email:      unit.tenantEmail         ?? '',
      phone:      unit.tenantPhone         ?? '',
      rent:       unit.tenantRent          ? String(unit.tenantRent) : '',
      leaseStart: unit.tenantLeaseStartRaw ?? '',
      leaseEnd:   unit.tenantLeaseEndRaw   ?? '',
    },
  }
  showEditUnit.value = true
}

const editUnitOccupancyOptions = computed(() => {
  if (estate.value?.type === 'sectional_title') {
    return [
      { value: 'owner',  label: 'Owner'  },
      { value: 'vacant', label: 'Vacant' },
    ]
  }
  return [
    { value: 'owner',  label: 'Owner'  },
    { value: 'tenant', label: 'Tenant' },
    { value: 'vacant', label: 'Vacant' },
  ]
})

const editUnitShowTenantFields = computed(() =>
  estate.value?.type !== 'sectional_title' &&
  (editUnitForm.value.occupancy === 'tenant' || editUnitForm.value.showTenant)
)

async function saveEditUnit() {
  savingEditUnit.value = true
  editUnitError.value  = null

  const occupancyMap = { owner: 'owner_occupied', tenant: 'tenant_occupied', vacant: 'vacant' }

  const payload = {
    unit_number:    editUnitForm.value.unitNumber,
    occupancy_type: occupancyMap[editUnitForm.value.occupancy],
    owner: {
      full_name: editUnitForm.value.owner.name,
      email:     editUnitForm.value.owner.email,
      phone:     editUnitForm.value.owner.phone,
      id_number: editUnitForm.value.owner.idNumber,
    },
  }

  if (editUnitShowLevy.value && editUnitForm.value.levyOverride !== '') {
    payload.levy_override = parseFloat(editUnitForm.value.levyOverride) || 0
  }

  if (editUnitShowTenantFields.value) {
    if (editUnitForm.value.tenant.rent !== '') {
      payload.rent_amount = parseFloat(editUnitForm.value.tenant.rent) || 0
    }
    if (editUnitForm.value.tenant.name) {
      payload.tenant = {
        full_name:   editUnitForm.value.tenant.name,
        email:       editUnitForm.value.tenant.email,
        phone:       editUnitForm.value.tenant.phone || null,
        lease_start: editUnitForm.value.tenant.leaseStart || null,
        lease_end:   editUnitForm.value.tenant.leaseEnd   || null,
      }
    }
  }

  try {
    await api.put(`/estates/${route.params.id}/units/${editingUnit.value.id}`, payload)
    showEditUnit.value = false
    await fetchUnits()
  } catch (e) {
    editUnitError.value = e?.response?.data?.message ?? 'Failed to update unit. Please try again.'
  } finally {
    savingEditUnit.value = false
  }
}

// ── Delete Unit modal ─────────────────────────────────────────────────
const showDeleteUnit      = ref(false)
const deletingUnitTarget  = ref(null)
const deletingUnitConfirm = ref('')
const deletingUnitLoading = ref(false)
const deleteUnitError     = ref(null)

const deleteUnitConfirmMatches = computed(() =>
  deletingUnitConfirm.value.trim() === (deletingUnitTarget.value?.unit ?? '').trim()
)

function openDeleteUnit(unit) {
  deletingUnitTarget.value  = unit
  deletingUnitConfirm.value = ''
  deleteUnitError.value     = null
  showDeleteUnit.value      = true
}

async function confirmDeleteUnit() {
  if (!deleteUnitConfirmMatches.value) return
  deletingUnitLoading.value = true
  deleteUnitError.value     = null
  try {
    await api.delete(`/estates/${route.params.id}/units/${deletingUnitTarget.value.id}`)
    showDeleteUnit.value = false
    await fetchUnits()
    await fetchEstate()
  } catch (e) {
    deleteUnitError.value = e?.response?.data?.message ?? 'Failed to delete unit. Please try again.'
  } finally {
    deletingUnitLoading.value = false
  }
}

// ── Multi-select & Bulk Delete ────────────────────────────────────────
const selectedUnitIds   = ref(new Set())
const showBulkDelete    = ref(false)
const bulkDeleteConfirm = ref('')
const bulkDeleteLoading = ref(false)
const bulkDeleteError   = ref(null)

const selectedCount = computed(() => selectedUnitIds.value.size)

const allVisibleSelected = computed(() =>
  allUnits.value.length > 0 &&
  allUnits.value.every(u => selectedUnitIds.value.has(u.id))
)

const someVisibleSelected = computed(() =>
  selectedUnitIds.value.size > 0 && !allVisibleSelected.value
)

const bulkDeleteConfirmMatches = computed(() =>
  bulkDeleteConfirm.value.trim() === (estate.value?.name ?? '').trim()
)

function toggleUnitSelection(id, event) {
  event.stopPropagation()
  const next = new Set(selectedUnitIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedUnitIds.value = next
}

function toggleSelectAll(event) {
  event.stopPropagation()
  if (allVisibleSelected.value) {
    selectedUnitIds.value = new Set()
  } else {
    selectedUnitIds.value = new Set(allUnits.value.map(u => u.id))
  }
}

function clearSelection() {
  selectedUnitIds.value = new Set()
}

function openBulkDelete() {
  bulkDeleteConfirm.value = ''
  bulkDeleteError.value   = null
  showBulkDelete.value    = true
}

async function confirmBulkDelete() {
  if (!bulkDeleteConfirmMatches.value) return
  bulkDeleteLoading.value = true
  bulkDeleteError.value   = null
  try {
    await Promise.all(
      [...selectedUnitIds.value].map(id =>
        api.delete(`/estates/${route.params.id}/units/${id}`)
      )
    )
    showBulkDelete.value  = false
    selectedUnitIds.value = new Set()
    await fetchUnits()
    await fetchEstate()
  } catch (e) {
    bulkDeleteError.value = e?.response?.data?.message ?? 'Failed to delete units. Please try again.'
  } finally {
    bulkDeleteLoading.value = false
  }
}

// ── Row action menu (teleported, escapes overflow-x-auto) ─────────────
const rowMenuUnit   = ref(null)
const rowMenuAnchor = ref({ top: 0, right: 0 })

function openRowMenu(event, unit) {
  event.stopPropagation()
  if (rowMenuUnit.value?.id === unit.id) {
    rowMenuUnit.value = null
    return
  }
  const rect = event.currentTarget.getBoundingClientRect()
  rowMenuAnchor.value = {
    top:   rect.bottom + 4,
    right: window.innerWidth - rect.right,
  }
  rowMenuUnit.value = unit
}

function closeRowMenu() {
  rowMenuUnit.value = null
}

// ── Billing schedule ──────────────────────────────────────────────────
const billingSchedule = computed(() => {
  const day = estate.value?.billing_day
  if (!day) return null

  const today       = new Date()
  const currentDay  = today.getDate()
  const yr          = today.getFullYear()
  const mo          = today.getMonth()

  // Pick this month if billing day is still upcoming, otherwise next month
  const nextDate = day > currentDay
    ? new Date(yr, mo, day)
    : new Date(yr, mo + 1, day)

  const daysUntil = Math.ceil((nextDate - today) / 86400000)

  const formatted = nextDate.toLocaleDateString('en-GB', {
    day: 'numeric', month: 'long', year: 'numeric',
  })

  // Ordinal suffix: 1st, 2nd, 3rd, 25th …
  const s = ['th','st','nd','rd']
  const v = day % 100
  const ordinal = day + (s[(v - 20) % 10] || s[v] || s[0])

  return { day, ordinal, daysUntil, formatted, nextDate }
})


// ── Live countdown ────────────────────────────────────────────────────
const countdownNow = ref(Date.now())
let countdownTimer = null

onMounted(() => {
  countdownTimer = setInterval(() => { countdownNow.value = Date.now() }, 1000)
})

onUnmounted(() => {
  clearInterval(countdownTimer)
})

const countdownText = computed(() => {
  if (!billingSchedule.value) return ''
  const diff = billingSchedule.value.nextDate - countdownNow.value
  if (diff <= 0) return 'Today'

  const totalSecs = Math.floor(diff / 1000)
  const days  = Math.floor(totalSecs / 86400)
  const hours = Math.floor((totalSecs % 86400) / 3600)
  const mins  = Math.floor((totalSecs % 3600) / 60)
  const secs  = totalSecs % 60

  if (days >= 1) return `${days}d ${hours}h`
  if (hours >= 1) return `${hours}h ${String(mins).padStart(2, '0')}m`
  return `${mins}m ${String(secs).padStart(2, '0')}s`
})

// ── Charts ────────────────────────────────────────────────────────────

// Helpers — occupancy totals from filter-aware chartStats
const chartOccupancy = computed(() => chartStats.value?.occupancy ?? { owner_occupied: 0, tenant_occupied: 0, vacant: 0 })
const chartTotal     = computed(() => chartOccupancy.value.owner_occupied + chartOccupancy.value.tenant_occupied + chartOccupancy.value.vacant)

// Occupancy Breakdown — Doughnut
const occupancyChartData = computed(() => ({
  labels: ['Owners', 'Tenants', 'Vacant'],
  datasets: [{
    data: [chartOccupancy.value.owner_occupied, chartOccupancy.value.tenant_occupied, chartOccupancy.value.vacant],
    backgroundColor: ['#22c55e', '#3b82f6', '#9ca3af'],
    borderColor: '#ffffff',
    borderWidth: 3,
    hoverOffset: 6,
  }],
}))

const occupancyChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '60%',
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        padding: 20,
        font: { size: 12, family: 'DM Sans' },
        boxWidth: 10,
        boxHeight: 10,
        borderRadius: 3,
        color: '#1E2740',
      },
    },
    tooltip: {
      callbacks: {
        label(ctx) {
          const total = chartTotal.value
          const pct   = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0
          return ` ${ctx.label}: ${ctx.parsed} units (${pct}%)`
        },
      },
    },
  },
}))

// Show the dominant segment (largest count) in the donut center label
const occupancyCenterLabel = computed(() => {
  const occ   = chartOccupancy.value
  const total = chartTotal.value
  if (total === 0) return { pct: 0, label: 'Owners' }

  const segments = [
    { count: occ.owner_occupied,  label: 'Owners'  },
    { count: occ.tenant_occupied, label: 'Tenants' },
    { count: occ.vacant,          label: 'Vacant'  },
  ]
  const dominant = segments.reduce((a, b) => (b.count > a.count ? b : a))
  return {
    pct:   Math.round((dominant.count / total) * 100),
    label: dominant.label,
  }
})

const occupancyCenterTextPlugin = computed(() => ({
  id: 'occupancyCenterText',
  beforeDraw(chart) {
    const { ctx, chartArea: { top, bottom, left, right } } = chart
    const cx = (left + right) / 2
    const cy = (top + bottom) / 2
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillStyle = '#1E2740'
    ctx.font = 'bold 1.75rem DM Sans, sans-serif'
    ctx.fillText(`${occupancyCenterLabel.value.pct}%`, cx, cy - 10)
    ctx.fillStyle = '#717B99'
    ctx.font = '0.875rem DM Sans, sans-serif'
    ctx.fillText(occupancyCenterLabel.value.label, cx, cy + 16)
    ctx.restore()
  },
}))

// Empty-state detection — drives ghost chart visibility
const hasOccupancyData = computed(() => chartTotal.value > 0)
const hasInvoiceData   = computed(() => {
  const inv = chartStats.value?.invoice_status ?? {}
  return (inv.paid || 0) + (inv.overdue || 0) + (inv.partial || 0) > 0
})

// Invoice Status — Vertical Bar (filter-aware from chartStats)
const invoiceChartData = computed(() => {
  const inv = chartStats.value?.invoice_status ?? { paid: 0, overdue: 0, partial: 0 }
  return {
    labels: ['Paid', 'Overdue', 'Partial'],
    datasets: [{
      data: [inv.paid, inv.overdue, inv.partial],
      backgroundColor: ['#22c55e', '#dc2828', '#D89B4B'],
      borderRadius: 4,
    }],
  }
})

const invoiceChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: (ctx) => ` ${ctx.parsed.y} invoices` },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { stepSize: 2, font: { size: 11 }, color: '#717B99' },
      grid: { color: '#DCDEE8' },
    },
    x: {
      grid: { display: false },
      ticks: { font: { size: 12 }, color: '#717B99' },
    },
  },
}

// Top Arrears — Horizontal Bar (top 5 across ALL filtered units, from chartStats)
const arrearsUnits = computed(() => chartStats.value?.top_arrears ?? [])

const arrearsChartData = computed(() => ({
  labels: arrearsUnits.value.map(u => {
    const ownerSurname = (u.owner_name || '—').split(' ').pop()
    return `${u.unit_number} – ${ownerSurname}`
  }),
  datasets: [{
    data: arrearsUnits.value.map(u => u.outstanding),
    backgroundColor: '#dc2828',
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const arrearsChartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` R ${ctx.parsed.x.toLocaleString('en-US').replace(/,/g, '\u00A0')}`,
      },
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        font: { size: 11 },
        color: '#717B99',
        callback: (v) => `R ${(v / 1000).toFixed(0)}k`,
      },
      grid: { color: '#DCDEE8' },
    },
    y: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: '#717B99' },
    },
  },
}

// ── Tenant Insights — only rendered for non-sectional-title estates ──
const estateHasTenants = computed(() => estate.value?.type !== 'sectional_title')

// Tenant Lease Expiry — Vertical Bar
const leaseExpiry = computed(() => chartStats.value?.tenant_lease_expiry ?? {
  expired: 0, this_month: 0, next_month: 0, in_3_months: 0, beyond: 0,
})
const hasLeaseData = computed(() => {
  const l = leaseExpiry.value
  return (l.expired + l.this_month + l.next_month + l.in_3_months + l.beyond) > 0
})

const leaseChartData = computed(() => ({
  labels: ['Expired', 'This Month', 'Next Month', '1–3 Months', '3+ Months'],
  datasets: [{
    data: [
      leaseExpiry.value.expired,
      leaseExpiry.value.this_month,
      leaseExpiry.value.next_month,
      leaseExpiry.value.in_3_months,
      leaseExpiry.value.beyond,
    ],
    backgroundColor: ['#F75A68', '#D89B4B', '#D89B4B', '#3b82f6', '#22c55e'],
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const leaseChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ${ctx.parsed.y} tenant${ctx.parsed.y !== 1 ? 's' : ''}`,
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: '#717B99' },
    },
    y: {
      beginAtZero: true,
      ticks: {
        font: { size: 11 },
        color: '#717B99',
        stepSize: 1,
        callback: (v) => Number.isInteger(v) ? v : '',
      },
      grid: { color: '#DCDEE8' },
    },
  },
}

// Top Tenant Arrears — Horizontal Bar
const topTenantArrears = computed(() => chartStats.value?.top_tenant_arrears ?? [])
const hasTopTenantArrearsData = computed(() => topTenantArrears.value.length > 0)

const tenantArrearsChartData = computed(() => ({
  labels: topTenantArrears.value.map(t => {
    const surname = (t.tenant_name || '—').split(' ').pop()
    return `${t.unit_number} – ${surname}`
  }),
  datasets: [{
    data: topTenantArrears.value.map(t => t.outstanding),
    backgroundColor: '#D89B4B',
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const tenantArrearsChartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` R ${ctx.parsed.x.toLocaleString('en-US').replace(/,/g, '\u00A0')}`,
      },
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        font: { size: 11 },
        color: '#717B99',
        callback: (v) => `R ${(v / 1000).toFixed(0)}k`,
      },
      grid: { color: '#DCDEE8' },
    },
    y: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: '#717B99' },
    },
  },
}
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Error state ─────────────────────────────────────────────── -->
    <div v-if="estateError" class="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive">
      {{ estateError }}
    </div>

    <!-- ── Page Header ─────────────────────────────────────────────── -->
    <div class="flex items-start gap-4">

      <!-- Back to Estates -->
      <button
        class="p-2 rounded-lg hover:bg-muted transition-colors mt-0.5 shrink-0"
        aria-label="Back to Estates"
        @click="goBack()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
          <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
        </svg>
      </button>

      <!-- Estate info -->
      <div class="flex-1 min-w-0">
        <template v-if="estateLoading">
          <!-- Skeleton -->
          <div class="h-7 w-64 bg-muted rounded animate-pulse mb-2" />
          <div class="h-4 w-48 bg-muted rounded animate-pulse" />
        </template>
        <template v-else-if="estate">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="font-body font-bold text-2xl text-foreground">{{ estate.name }}</h1>
            <AppBadge variant="info">{{ estateTypeLabel(estate.type) }}</AppBadge>
          </div>
          <div class="flex items-center gap-1.5 mt-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground shrink-0">
              <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <p class="text-sm text-muted-foreground">{{ estate.address }}</p>
          </div>
        </template>
      </div>

      <!-- Actions -->
      <div class="flex gap-2 shrink-0 items-center">
        <AppButton variant="outline" @click="showBulkImport = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" x2="12" y1="3" y2="15"/>
          </svg>
          Bulk Import
        </AppButton>
        <AppButton variant="primary" @click="showAddUnit = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"/><path d="M12 5v14"/>
          </svg>
          Add Unit
        </AppButton>

        <!-- Estate action menu -->
        <AppDropdown align="right">
          <template #trigger="{ toggle }">
            <button
              type="button"
              class="inline-flex items-center justify-center w-9 h-9 rounded border border-border bg-card text-foreground hover:bg-muted transition-colors focus:outline-none"
              aria-label="Estate options"
              @click="toggle"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>
              </svg>
            </button>
          </template>

          <template #default="{ close }">
            <AppDropdownItem label="Edit Estate" @click="close(); openEditEstate()">
              <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
                </svg>
              </template>
            </AppDropdownItem>

            <AppDropdownItem :divider="true" />

            <AppDropdownItem label="Delete Estate" variant="danger" @click="close(); openDeleteEstate()">
              <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                </svg>
              </template>
            </AppDropdownItem>
          </template>
        </AppDropdown>
      </div>
    </div>

    <!-- ── Compact Summary Stats (6 cards) ────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

      <!-- Skeleton while loading -->
      <template v-if="unitsLoading">
        <div v-for="n in 6" :key="n" class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <div class="h-7 w-12 bg-muted rounded animate-pulse mx-auto mb-1.5" />
            <div class="h-3 w-16 bg-muted rounded animate-pulse mx-auto" />
          </div>
        </div>
      </template>

      <!-- Real stats -->
      <template v-else>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p class="text-2xl font-bold font-body text-foreground">{{ computedStats.units }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Units</p>
          </div>
        </div>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p class="text-2xl font-bold font-body text-foreground">{{ computedStats.owners }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Owners</p>
          </div>
        </div>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p class="text-2xl font-bold font-body text-foreground">{{ computedStats.tenants }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Tenants</p>
          </div>
        </div>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p class="text-2xl font-bold font-body text-muted-foreground">{{ computedStats.vacant }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Vacant</p>
          </div>
        </div>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p class="text-2xl font-bold font-body text-foreground whitespace-nowrap">{{ formatAmount(computedStats.monthlyRevenue) }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Monthly Revenue</p>
          </div>
        </div>
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="p-4 text-center">
            <p :class="['text-2xl font-bold font-body whitespace-nowrap', balanceClass(computedStats.totalBalance)]">
              {{ formatAmount(computedStats.totalBalance) }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5">Total Balance</p>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Billing Schedule Strip ────────────────────────────────────── -->
    <div v-if="!estateLoading && estate" class="rounded-lg border bg-card shadow-sm px-5 py-3.5 flex items-center gap-4 flex-wrap">

      <!-- Calendar icon -->
      <div class="w-8 h-8 rounded-full bg-accent/10 flex items-center justify-center shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-accent">
          <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
        </svg>
      </div>

      <!-- Schedule info -->
      <div class="flex-1 min-w-0">
        <template v-if="billingSchedule">
          <p class="text-sm font-medium text-foreground">
            Billing runs on the <span class="text-accent font-semibold">{{ billingSchedule.ordinal }}</span> of each month
          </p>
          <p class="text-xs text-muted-foreground mt-0.5">
            Invoices are automatically generated and sent to
            <template v-if="estate?.type === 'sectional_title'">owners</template>
            <template v-else-if="estate?.type === 'residential_rental' || estate?.type === 'commercial_rental'">tenants</template>
            <template v-else>owners and tenants</template>
            on this date.
          </p>
        </template>
        <template v-else>
          <p class="text-sm font-medium text-muted-foreground">No billing day configured</p>
          <p class="text-xs text-muted-foreground mt-0.5">Edit the estate to set a billing day so invoices generate automatically each month.</p>
        </template>
      </div>

      <!-- Countdown — only shown when units exist -->
      <template v-if="billingSchedule && computedStats.units > 0">
        <div class="shrink-0 text-right border-l border-border pl-5 ml-1">
          <div class="flex items-center justify-end gap-2 mb-1">
            <p class="text-xs text-muted-foreground leading-none">Next billing run</p>
            <span
              class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono font-medium leading-none bg-[#1F3A5C]/8 text-[#1F3A5C] border border-[#1F3A5C]/15"
            >{{ countdownText }}</span>
          </div>
          <p class="text-sm font-semibold text-foreground">{{ billingSchedule.formatted }}</p>
        </div>
      </template>

      <!-- No units — prompt instead of countdown -->
      <template v-else-if="billingSchedule && computedStats.units === 0">
        <div class="shrink-0 border-l border-border pl-5 ml-1">
          <p class="text-xs text-muted-foreground">Add units to see the</p>
          <p class="text-xs text-muted-foreground">next billing countdown</p>
        </div>
      </template>

      <!-- Edit billing day shortcut -->
      <button
        @click="openEditEstate"
        title="Edit schedule"
        class="shrink-0 inline-flex items-center justify-center w-7 h-7 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
        </svg>
      </button>
    </div>

    <!-- ── Units Table Card ─────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card shadow-sm">

      <!-- ── Empty state: no units exist at all ────────────────────── -->
      <div
        v-if="!unitsLoading && !estateLoading && !unitsError && computedStats.units === 0"
        class="flex flex-col items-center justify-center py-16 px-8 text-center"
      >
        <!-- Icon -->
        <div class="w-16 h-16 rounded-full bg-accent/10 flex items-center justify-center mb-5">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-accent">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>

        <h3 class="font-body font-semibold text-lg text-foreground mb-1">No units yet</h3>
        <p class="text-sm text-muted-foreground max-w-sm mb-8">
          <template v-if="estate?.type === 'sectional_title'">
            Add units to start managing owners, levy billing, and payments.
          </template>
          <template v-else-if="estate?.type === 'residential_rental' || estate?.type === 'commercial_rental'">
            Add units to start managing tenants, rent collection, and payments.
          </template>
          <template v-else>
            Add units to start managing owners, tenants, billing, and payments.
          </template>
          You can add them one by one or import them all at once from a spreadsheet.
        </p>

        <div class="flex flex-col sm:flex-row items-center gap-3">
          <AppButton variant="primary" @click="showAddUnit = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Unit
          </AppButton>
          <AppButton variant="outline" @click="showBulkImport = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Bulk Import from Spreadsheet
          </AppButton>
        </div>
      </div>

      <!-- ── Normal state: toolbar + table ─────────────────────────── -->
      <template v-else>

      <!-- Toolbar header -->
      <div class="px-6 pt-5 pb-3 flex items-center justify-between">
        <h3 class="font-body font-semibold text-lg flex items-center gap-2 text-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
            <path d="M18 21a8 8 0 0 0-16 0"/>
            <circle cx="10" cy="8" r="5"/>
            <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>
          </svg>
          Units
        </h3>
        <button
          class="inline-flex items-center gap-1.5 rounded border border-border bg-card px-3 py-1.5 text-sm font-medium text-foreground shadow-sm hover:bg-muted transition-colors"
          @click="showExportModal = true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" x2="12" y1="3" y2="15"/>
          </svg>
          Export
        </button>
      </div>

      <!-- Toolbar row -->
      <div class="px-6 pb-4 flex items-center gap-3 flex-wrap">
        <div class="flex-1 min-w-0">
          <AppTableToolbar
            :key="toolbarKey"
            search-placeholder="Search units..."
            :filter-fields="UNITS_FILTER_FIELDS"
            :sort-options="UNITS_SORT_OPTIONS"
            storage-key="estate-units"
            date-range-context="when the unit was added"
            :initial-date-range="toolbarInitialDateRange"
            @update:state="onToolbarUpdate"
          />
        </div>
      </div>

      <!-- Errors for units -->
      <div v-if="unitsError" class="mx-6 mb-4 rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
        {{ unitsError }}
      </div>

      <!-- Bulk action bar -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="selectedCount > 0"
          class="mx-6 mb-3 flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2.5"
        >
          <span class="text-sm font-medium text-primary">
            {{ selectedCount }} unit{{ selectedCount === 1 ? '' : 's' }} selected
          </span>
          <div class="flex items-center gap-3">
            <button
              class="text-xs text-muted-foreground hover:text-foreground transition-colors"
              @click="clearSelection"
            >Clear selection</button>
            <AppButton variant="danger" size="sm" @click="openBulkDelete">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                <polyline points="3 6 5 6 21 6"/><path d="m19 6-.867 12.142A2 2 0 0 1 16.138 20H7.862a2 2 0 0 1-1.995-1.858L5 6"/>
                <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
              </svg>
              Delete {{ selectedCount }} unit{{ selectedCount === 1 ? '' : 's' }}
            </AppButton>
          </div>
        </div>
      </Transition>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="py-3 pl-4 pr-2 w-10 align-middle" @click.stop>
                <input
                  type="checkbox"
                  :checked="allVisibleSelected"
                  :indeterminate="someVisibleSelected"
                  class="h-4 w-4 rounded border-border text-primary accent-primary cursor-pointer"
                  @change="toggleSelectAll($event)"
                />
              </th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Unit</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Owner</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Occupancy</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Tenant</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Balance</th>
              <th class="py-3 px-4 w-12" />
            </tr>
          </thead>
          <tbody>

            <!-- Loading skeleton rows -->
            <template v-if="unitsLoading">
              <tr v-for="n in 5" :key="`sk-${n}`" class="border-b border-border">
                <td class="py-3 pl-4 pr-2 w-10 align-middle"><div class="h-4 w-4 bg-muted rounded animate-pulse" /></td>
                <td class="py-3 px-4"><div class="h-4 w-10 bg-muted rounded animate-pulse" /></td>
                <td class="py-3 px-4">
                  <div class="h-4 w-32 bg-muted rounded animate-pulse mb-1" />
                  <div class="h-3 w-24 bg-muted rounded animate-pulse" />
                </td>
                <td class="py-3 px-4"><div class="h-5 w-16 bg-muted rounded-full animate-pulse" /></td>
                <td class="py-3 px-4"><div class="h-4 w-24 bg-muted rounded animate-pulse" /></td>
                <td class="py-3 px-4 text-right"><div class="h-4 w-12 bg-muted rounded animate-pulse ml-auto" /></td>
              </tr>
            </template>

            <!-- Real unit rows -->
            <template v-else>
              <tr
                v-for="unit in allUnits"
                :key="unit.id"
                :class="[
                  'group border-b border-border hover:bg-muted/50 cursor-pointer transition-colors',
                  selectedUnitIds.has(unit.id) && 'bg-primary/5',
                ]"
                @click="goToUnit(unit.id)"
              >
                <td class="py-3 pl-4 pr-2 w-10 align-middle" @click.stop>
                  <input
                    type="checkbox"
                    :checked="selectedUnitIds.has(unit.id)"
                    class="h-4 w-4 rounded border-border text-primary accent-primary cursor-pointer"
                    @change="toggleUnitSelection(unit.id, $event)"
                  />
                </td>
                <td class="py-3 px-4 font-medium text-foreground">{{ unit.unit }}</td>

                <!-- Owner cell -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-1.5">
                    <div class="min-w-0">
                      <button
                        class="text-foreground hover:text-primary hover:underline transition-colors text-left block"
                        @click="goToOwner($event, unit)"
                      >{{ unit.ownerName }}</button>
                      <p class="text-xs text-muted-foreground">{{ unit.ownerEmail }}</p>
                    </div>
                    <!-- Info poptip -->
                    <AppPoptip position="right" max-width="260px">
                      <template #trigger>
                        <button
                          class="invisible group-hover:visible flex-shrink-0 p-0.5 rounded text-muted-foreground hover:text-primary transition-colors"
                          @click.stop
                          aria-label="Owner details"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                          </svg>
                        </button>
                      </template>
                      <div class="p-4 space-y-3 min-w-[220px]">
                        <div class="flex items-center gap-2 pb-2 border-b border-border">
                          <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-primary">
                              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                          </div>
                          <div>
                            <p class="text-xs font-semibold text-foreground">{{ unit.ownerName }}</p>
                            <p class="text-[10px] text-muted-foreground">Owner</p>
                          </div>
                        </div>
                        <div class="space-y-1.5 text-xs">
                          <div class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                            </svg>
                            <span class="truncate">{{ unit.ownerEmail }}</span>
                          </div>
                          <div v-if="unit.ownerPhone" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.4 19.79 19.79 0 0 1 1.61 4.9 2 2 0 0 1 3.6 2.71h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 10.3a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.44 18"/>
                            </svg>
                            <span>{{ unit.ownerPhone }}</span>
                          </div>
                          <div v-if="unit.ownerIdNumber" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <rect width="18" height="14" x="3" y="5" rx="2"/><path d="M7 15h4M7 11h4M15 15h2M15 11h2"/>
                            </svg>
                            <span class="font-mono tracking-wide">{{ unit.ownerIdNumber }}</span>
                          </div>
                          <div v-if="unit.ownerLevy" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <span class="font-medium text-foreground">{{ formatAmount(unit.ownerLevy) }}<span class="font-normal text-muted-foreground"> / month</span></span>
                          </div>
                        </div>
                        <div class="pt-2 border-t border-border flex gap-1.5">
                          <button
                            class="flex-1 flex items-center justify-center gap-1.5 px-2 py-1.5 rounded text-[10px] font-medium bg-blue-50 hover:bg-blue-100 text-blue-600 transition-colors"
                            @click.stop="openSendMessageOwner(unit)"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                              <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
                            </svg>
                            Send Message
                          </button>
                        </div>
                      </div>
                    </AppPoptip>
                  </div>
                </td>

                <!-- Occupancy cell -->
                <td class="py-3 px-4">
                  <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium gap-1.5 leading-tight', occupancyConfig(unit.occupancy).wrapClass]">
                    <span :class="['w-1.5 h-1.5 rounded-full shrink-0', occupancyConfig(unit.occupancy).dotClass]" />
                    {{ occupancyConfig(unit.occupancy).label }}
                  </span>
                </td>

                <!-- Tenant cell -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-1.5">
                    <div class="min-w-0">
                      <button
                        v-if="unit.tenant"
                        class="text-foreground hover:text-primary hover:underline transition-colors text-left block"
                        @click="goToTenant($event, unit)"
                      >{{ unit.tenant }}</button>
                      <span v-else class="text-muted-foreground">—</span>
                      <span
                        v-if="unit.tenantCount > 0"
                        class="text-[10px] text-muted-foreground leading-tight block"
                      >{{ unit.tenantCount }} {{ unit.tenantCount === 1 ? 'tenant' : 'tenants' }} total</span>
                    </div>
                    <!-- Tenant poptip -->
                    <AppPoptip v-if="unit.tenant" position="right" max-width="260px">
                      <template #trigger>
                        <button
                          class="invisible group-hover:visible flex-shrink-0 p-0.5 rounded text-muted-foreground hover:text-blue-600 transition-colors"
                          @click.stop
                          aria-label="Tenant details"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                          </svg>
                        </button>
                      </template>
                      <div class="p-4 space-y-3 min-w-[220px]">
                        <div class="flex items-center gap-2 pb-2 border-b border-border">
                          <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-blue-600">
                              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                          </div>
                          <div>
                            <p class="text-xs font-semibold text-foreground">{{ unit.tenant }}</p>
                            <p class="text-[10px] text-muted-foreground">Tenant</p>
                          </div>
                        </div>
                        <div class="space-y-1.5 text-xs">
                          <div v-if="unit.tenantEmail" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                            </svg>
                            <span class="truncate">{{ unit.tenantEmail }}</span>
                          </div>
                          <div v-if="unit.tenantPhone" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.4 19.79 19.79 0 0 1 1.61 4.9 2 2 0 0 1 3.6 2.71h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 10.3a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.44 18"/>
                            </svg>
                            <span>{{ unit.tenantPhone }}</span>
                          </div>
                          <div v-if="unit.tenantLeaseStart" class="flex items-start gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0 mt-0.5">
                              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
                            </svg>
                            <span>{{ unit.tenantLeaseStart }} → {{ unit.tenantLeaseEnd }}</span>
                          </div>
                          <div v-if="unit.tenantRent" class="flex items-center gap-2 text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 shrink-0">
                              <line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <span class="font-medium text-foreground">{{ formatAmount(unit.tenantRent) }}<span class="font-normal text-muted-foreground"> / month</span></span>
                          </div>
                        </div>
                        <div class="pt-2 border-t border-border flex flex-col gap-1.5">
                          <button
                            class="w-full flex items-center justify-center gap-1.5 px-2 py-1.5 rounded text-[10px] font-medium bg-blue-50 hover:bg-blue-100 text-blue-600 transition-colors"
                            @click.stop="openSendMessageTenant(unit)"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                              <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
                            </svg>
                            Send Message
                          </button>
                        </div>
                      </div>
                    </AppPoptip>
                  </div>
                </td>

                <td :class="['py-3 px-4 text-right font-medium', balanceClass(unit.balance)]">
                  {{ formatAmount(unit.balance) }}
                </td>

                <!-- Row action menu trigger -->
                <td class="py-3 px-4 w-12" @click.stop>
                  <button
                    type="button"
                    class="invisible group-hover:visible inline-flex items-center justify-center w-7 h-7 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                    aria-label="Unit options"
                    @click.stop="openRowMenu($event, unit)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                      <circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>
                    </svg>
                  </button>
                </td>
              </tr>

              <!-- Empty state (filters returned nothing) -->
              <tr v-if="!unitsLoading && allUnits.length === 0">
                <td colspan="7" class="py-12 text-center text-sm text-muted-foreground">
                  No units match your search.
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!unitsLoading && totalPages > 1" class="flex items-center justify-between px-6 py-3 border-t border-border">
        <p class="text-xs text-muted-foreground">
          Showing {{ (currentPage - 1) * PER_PAGE + 1 }}–{{ Math.min(currentPage * PER_PAGE, totalUnitsInQuery) }}
          of {{ totalUnitsInQuery }} units
        </p>
        <div class="flex items-center gap-1">
          <button
            class="h-8 px-3 text-xs rounded border border-border hover:bg-muted transition-colors disabled:opacity-40 disabled:pointer-events-none"
            :disabled="currentPage <= 1"
            @click="setPage(currentPage - 1)"
          >Previous</button>
          <button
            v-for="page in totalPages"
            :key="page"
            :class="[
              'h-8 w-8 text-xs rounded border transition-colors',
              page === currentPage
                ? 'bg-primary text-primary-foreground border-primary'
                : 'border-border hover:bg-muted',
            ]"
            @click="setPage(page)"
          >{{ page }}</button>
          <button
            class="h-8 px-3 text-xs rounded border border-border hover:bg-muted transition-colors disabled:opacity-40 disabled:pointer-events-none"
            :disabled="currentPage >= totalPages"
            @click="setPage(currentPage + 1)"
          >Next</button>
        </div>
      </div>

      </template><!-- end normal state -->
    </div>

    <!-- ── Charts: Occupancy Breakdown + Invoice Status ─────────────── -->
    <!-- Skeleton while primary data is still loading -->
    <div v-if="unitsLoading || estateLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="n in 2" :key="n" class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <div class="h-4 w-40 bg-muted rounded animate-pulse" />
        </div>
        <div class="px-6 pb-6">
          <div class="h-64 bg-muted/50 rounded animate-pulse" />
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Occupancy Breakdown -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Occupancy Breakdown</h3>
        </div>
        <div class="px-6 pb-6">
          <div v-if="unitsLoading" class="h-64 flex items-center justify-center">
            <div class="w-32 h-32 rounded-full border-4 border-muted animate-pulse" />
          </div>
          <!-- Ghost donut when no data -->
          <div v-else-if="!hasOccupancyData" class="flex flex-col items-center justify-center" style="height: 260px;">
            <svg width="240" height="220" viewBox="0 0 240 220" xmlns="http://www.w3.org/2000/svg">
              <!-- Ghost donut segments -->
              <path d="M 120.0 30.0 A 80 80 0 1 1 95.3 186.1 L 104.6 157.6 A 50 50 0 1 0 120.0 60.0 Z" fill="#D1EFE0" opacity="0.7"/>
              <path d="M 95.3 186.1 A 80 80 0 0 1 55.3 63.0 L 79.6 80.6 A 50 50 0 0 0 104.6 157.6 Z" fill="#CCDDF9" opacity="0.7"/>
              <path d="M 55.3 63.0 A 80 80 0 0 1 120.0 30.0 L 120.0 60.0 A 50 50 0 0 0 79.6 80.6 Z" fill="#E8EAF0" opacity="0.7"/>
              <!-- Center text placeholder -->
              <circle cx="120" cy="110" r="42" fill="white"/>
              <rect x="95" y="100" width="50" height="10" rx="5" fill="#E8EAF0"/>
              <rect x="103" y="116" width="34" height="8" rx="4" fill="#E8EAF0"/>
              <!-- Legend placeholders -->
              <rect x="38" y="200" width="10" height="10" rx="2" fill="#D1EFE0"/>
              <rect x="52" y="202" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="100" y="200" width="10" height="10" rx="2" fill="#CCDDF9"/>
              <rect x="114" y="202" width="32" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="162" y="200" width="10" height="10" rx="2" fill="#E8EAF0"/>
              <rect x="176" y="202" width="30" height="6" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-1">Add units to see occupancy breakdown</p>
          </div>
          <div v-else style="height: 260px; position: relative;">
            <Doughnut :data="occupancyChartData" :options="occupancyChartOptions" :plugins="[occupancyCenterTextPlugin]" />
          </div>
        </div>
      </div>

      <!-- Invoice Status -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-2">
          <h3 class="font-body font-semibold text-base text-foreground">Invoice Status</h3>
        </div>
        <div class="px-6 pb-6">
          <div v-if="estateLoading" class="h-64 flex items-end gap-4 justify-center pb-6">
            <div v-for="n in 3" :key="n" :style="{ height: `${60 + n * 30}px` }" class="w-16 bg-muted rounded animate-pulse" />
          </div>
          <!-- Ghost bar chart when no invoice data -->
          <div v-else-if="!hasInvoiceData" class="flex flex-col items-center justify-center" style="height: 260px;">
            <svg width="260" height="200" viewBox="0 0 260 200" xmlns="http://www.w3.org/2000/svg">
              <!-- Y-axis -->
              <line x1="40" y1="10" x2="40" y2="160" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- X-axis -->
              <line x1="40" y1="160" x2="250" y2="160" stroke="#E8EAF0" stroke-width="1.5"/>
              <!-- Y-axis tick labels -->
              <rect x="10" y="10" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="43" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="76" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="109" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <rect x="10" y="142" width="24" height="6" rx="3" fill="#E8EAF0"/>
              <!-- Y-axis grid lines -->
              <line x1="40" y1="16" x2="250" y2="16" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="40" y1="49" x2="250" y2="49" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="40" y1="82" x2="250" y2="82" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <line x1="40" y1="115" x2="250" y2="115" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
              <!-- Ghost bars: Paid (tall), Overdue (medium), Partial (short) -->
              <rect x="60"  y="40"  width="42" height="120" rx="4" fill="#BBF7D0" opacity="0.75"/>
              <rect x="124" y="80"  width="42" height="80"  rx="4" fill="#FECACA" opacity="0.75"/>
              <rect x="188" y="116" width="42" height="44"  rx="4" fill="#FDE68A" opacity="0.75"/>
              <!-- X-axis labels -->
              <rect x="64"  y="168" width="34" height="7" rx="3" fill="#E8EAF0"/>
              <rect x="128" y="168" width="34" height="7" rx="3" fill="#E8EAF0"/>
              <rect x="192" y="168" width="34" height="7" rx="3" fill="#E8EAF0"/>
            </svg>
            <p class="text-xs text-muted-foreground mt-1">Invoice data will appear here once billing is run</p>
          </div>
          <div v-else style="height: 260px; position: relative;">
            <Bar :data="invoiceChartData" :options="invoiceChartOptions" />
          </div>
        </div>
      </div>
    </div>

    <!-- ── Top Arrears (full width) ────────────────────────────────── -->
    <div v-if="unitsLoading || estateLoading" class="rounded-lg border bg-card shadow-sm">
      <div class="px-6 pt-5 pb-2">
        <div class="h-4 w-28 bg-muted rounded animate-pulse" />
      </div>
      <div class="px-6 pb-6">
        <div class="h-48 bg-muted/50 rounded animate-pulse" />
      </div>
    </div>
    <div v-else class="rounded-lg border bg-card shadow-sm">
      <div class="px-6 pt-5 pb-2">
        <h3 class="font-body font-semibold text-base text-foreground">Top Arrears</h3>
      </div>
      <div class="px-6 pb-6">
        <div v-if="unitsLoading" class="space-y-3 py-4">
          <div v-for="n in 5" :key="n" class="h-6 bg-muted rounded animate-pulse" :style="{ width: `${40 + n * 10}%` }" />
        </div>
        <!-- Ghost horizontal bars when no arrears -->
        <div v-else-if="arrearsUnits.length === 0" class="flex flex-col items-center justify-center py-4">
          <svg width="100%" height="190" viewBox="0 0 500 190" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
            <!-- Y-axis -->
            <line x1="110" y1="8" x2="110" y2="170" stroke="#E8EAF0" stroke-width="1.5"/>
            <!-- X-axis (pushed down 8px to give breathing room below last bar) -->
            <line x1="110" y1="170" x2="490" y2="170" stroke="#E8EAF0" stroke-width="1.5"/>
            <!-- X-axis tick labels -->
            <rect x="108"  y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
            <rect x="207"  y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
            <rect x="306"  y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
            <rect x="405"  y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
            <!-- X grid lines -->
            <line x1="208" y1="8" x2="208" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
            <line x1="306" y1="8" x2="306" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
            <line x1="404" y1="8" x2="404" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
            <!-- Row 1 (tallest bar) -->
            <rect x="6" y="14"  width="98" height="14" rx="4" fill="#E8EAF0"/>
            <rect x="114" y="14" width="320" height="20" rx="4" fill="#FBDADA" opacity="0.8"/>
            <!-- Row 2 -->
            <rect x="6" y="46"  width="98" height="14" rx="4" fill="#E8EAF0"/>
            <rect x="114" y="46" width="248" height="20" rx="4" fill="#FBDADA" opacity="0.8"/>
            <!-- Row 3 -->
            <rect x="6" y="78"  width="98" height="14" rx="4" fill="#E8EAF0"/>
            <rect x="114" y="78" width="192" height="20" rx="4" fill="#FBDADA" opacity="0.8"/>
            <!-- Row 4 -->
            <rect x="6" y="110" width="98" height="14" rx="4" fill="#E8EAF0"/>
            <rect x="114" y="110" width="136" height="20" rx="4" fill="#FBDADA" opacity="0.8"/>
            <!-- Row 5 (shortest bar) -->
            <rect x="6" y="142" width="98" height="14" rx="4" fill="#E8EAF0"/>
            <rect x="114" y="142" width="88"  height="20" rx="4" fill="#FBDADA" opacity="0.8"/>
          </svg>
          <p class="text-xs text-muted-foreground mt-2">No arrears — top debtors will appear here</p>
        </div>
        <div v-else style="height: 200px; position: relative;">
          <Bar :data="arrearsChartData" :options="arrearsChartOptions" />
        </div>
      </div>
    </div>

    <!-- ── Tenant Insights (hidden for sectional title estates) ────── -->
    <template v-if="estateHasTenants">

      <!-- Skeleton -->
      <div v-if="unitsLoading || estateLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-for="n in 2" :key="n" class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2"><div class="h-4 w-40 bg-muted rounded animate-pulse" /></div>
          <div class="px-6 pb-6"><div class="h-48 bg-muted/50 rounded animate-pulse" /></div>
        </div>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Lease Expiry Timeline -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Lease Expiry Timeline</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Active tenant leases by expiry window</p>
          </div>
          <div class="px-6 pb-6">
            <div v-if="!hasLeaseData" class="flex flex-col items-center justify-center py-6">
              <!-- Ghost bar chart -->
              <svg width="100%" height="160" viewBox="0 0 360 160" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
                <line x1="30" y1="8" x2="30" y2="130" stroke="#E8EAF0" stroke-width="1.5"/>
                <line x1="30" y1="130" x2="350" y2="130" stroke="#E8EAF0" stroke-width="1.5"/>
                <rect x="40"  y="60"  width="44" height="70" rx="4" fill="#FBDADA" opacity="0.7"/>
                <rect x="100" y="80"  width="44" height="50" rx="4" fill="#FDE8C8" opacity="0.7"/>
                <rect x="160" y="90"  width="44" height="40" rx="4" fill="#FDE8C8" opacity="0.7"/>
                <rect x="220" y="50"  width="44" height="80" rx="4" fill="#DBEAFE" opacity="0.7"/>
                <rect x="280" y="30"  width="44" height="100" rx="4" fill="#DCFCE7" opacity="0.7"/>
                <rect x="38"  y="138" width="48" height="6"  rx="3" fill="#E8EAF0"/>
                <rect x="98"  y="138" width="48" height="6"  rx="3" fill="#E8EAF0"/>
                <rect x="158" y="138" width="48" height="6"  rx="3" fill="#E8EAF0"/>
                <rect x="218" y="138" width="48" height="6"  rx="3" fill="#E8EAF0"/>
                <rect x="278" y="138" width="48" height="6"  rx="3" fill="#E8EAF0"/>
              </svg>
              <p class="text-xs text-muted-foreground mt-2">No active leases with expiry dates</p>
            </div>
            <div v-else style="height: 200px; position: relative;">
              <Bar :data="leaseChartData" :options="leaseChartOptions" />
            </div>
          </div>
        </div>

        <!-- Top Tenant Arrears -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Top Tenant Arrears</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Tenants with highest outstanding rent balances</p>
          </div>
          <div class="px-6 pb-6">
            <div v-if="!hasTopTenantArrearsData" class="flex flex-col items-center justify-center py-4">
              <!-- Ghost horizontal bars -->
              <svg width="100%" height="190" viewBox="0 0 500 190" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
                <line x1="110" y1="8"   x2="110" y2="170" stroke="#E8EAF0" stroke-width="1.5"/>
                <line x1="110" y1="170" x2="490" y2="170" stroke="#E8EAF0" stroke-width="1.5"/>
                <rect x="108" y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
                <rect x="207" y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
                <rect x="306" y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
                <rect x="405" y="178" width="28" height="6" rx="3" fill="#E8EAF0"/>
                <line x1="208" y1="8"   x2="208" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
                <line x1="306" y1="8"   x2="306" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
                <line x1="404" y1="8"   x2="404" y2="170" stroke="#F0F0F5" stroke-width="1" stroke-dasharray="4,3"/>
                <rect x="6"   y="14"  width="98" height="14" rx="4" fill="#E8EAF0"/>
                <rect x="114" y="14"  width="280" height="20" rx="4" fill="#FDE8C8" opacity="0.8"/>
                <rect x="6"   y="46"  width="98" height="14" rx="4" fill="#E8EAF0"/>
                <rect x="114" y="46"  width="210" height="20" rx="4" fill="#FDE8C8" opacity="0.8"/>
                <rect x="6"   y="78"  width="98" height="14" rx="4" fill="#E8EAF0"/>
                <rect x="114" y="78"  width="160" height="20" rx="4" fill="#FDE8C8" opacity="0.8"/>
                <rect x="6"   y="110" width="98" height="14" rx="4" fill="#E8EAF0"/>
                <rect x="114" y="110" width="110" height="20" rx="4" fill="#FDE8C8" opacity="0.8"/>
                <rect x="6"   y="142" width="98" height="14" rx="4" fill="#E8EAF0"/>
                <rect x="114" y="142" width="72"  height="20" rx="4" fill="#FDE8C8" opacity="0.8"/>
              </svg>
              <p class="text-xs text-muted-foreground mt-2">No tenant arrears — all rent is up to date</p>
            </div>
            <div v-else style="height: 200px; position: relative;">
              <Bar :data="tenantArrearsChartData" :options="tenantArrearsChartOptions" />
            </div>
          </div>
        </div>

      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- Modals                                                        -->
    <!-- ══════════════════════════════════════════════════════════════ -->

    <!-- Edit Unit Modal -->
    <AppModal :show="showEditUnit" :title="`Edit Unit ${editUnitForm.unitNumber}`" size="md" @close="showEditUnit = false">
      <div class="space-y-4">

        <div v-if="editUnitError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ editUnitError }}
        </div>

        <!-- Unit Number + Occupancy Type -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="editUnitForm.unitNumber" label="Unit Number" required />
          <AppSelect v-model="editUnitForm.occupancy" label="Occupancy Type" :options="editUnitOccupancyOptions" required />
        </div>

        <!-- Levy Override -->
        <AppInput v-if="editUnitShowLevy" v-model="editUnitForm.levyOverride" label="Levy Override" type="number" placeholder="Use default levy" />

        <!-- Rent Amount (shown when tenant-occupied — not applicable for sectional title) -->
        <AppInput v-if="editUnitForm.occupancy === 'tenant' && estate.type !== 'sectional_title'" v-model="editUnitForm.tenant.rent" label="Rent Amount" type="number" />

        <!-- Owner Details -->
        <div class="border-t border-border pt-4">
          <p class="text-sm font-medium text-foreground mb-3">Owner Details</p>
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="editUnitForm.owner.name"     label="Full Name" size="sm" required />
            <AppInput v-model="editUnitForm.owner.email"    label="Email"     size="sm" type="email" required />
            <AppInput v-model="editUnitForm.owner.phone"    label="Phone"     size="sm" />
            <AppInput v-model="editUnitForm.owner.idNumber" label="ID Number" size="sm" />
          </div>
        </div>

        <!-- Tenant Details — hidden entirely for sectional title estates -->
        <div v-if="estate.type !== 'sectional_title'" class="border-t border-border pt-4">
          <!-- Toggle header (owner/vacant units) -->
          <div v-if="editUnitForm.occupancy !== 'tenant'" class="flex items-center justify-between mb-3">
            <div>
              <p class="text-sm font-medium text-foreground">Tenant Details</p>
              <p class="text-xs text-muted-foreground">Toggle on if this unit also has a tenant</p>
            </div>
            <button type="button" class="text-primary transition-colors" @click="editUnitForm.showTenant = !editUnitForm.showTenant">
              <!-- Toggle ON -->
              <svg v-if="editUnitForm.showTenant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
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

          <!-- Tenant fields -->
          <div v-if="editUnitShowTenantFields" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="editUnitForm.tenant.name"       label="Full Name"   size="sm" placeholder="Tenant name" required />
              <AppInput v-model="editUnitForm.tenant.email"      label="Email"       size="sm" type="email" placeholder="Email address" required />
              <AppInput v-model="editUnitForm.tenant.phone"      label="Phone"       size="sm" placeholder="+27 ..." />
              <AppInput v-model="editUnitForm.tenant.rent"       label="Rent Amount" size="sm" type="number" placeholder="Monthly rent" required />
              <AppDatePicker v-model="editUnitForm.tenant.leaseStart" label="Lease Start" placeholder="Select date..." required />
              <AppDatePicker v-model="editUnitForm.tenant.leaseEnd" label="Lease End" placeholder="Select date..." />
            </div>
            <!-- Lease Document upload -->
            <div>
              <label class="block text-xs text-muted-foreground mb-1">Lease Document</label>
              <div class="border border-dashed border-border rounded-lg p-4 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground mx-auto mb-1.5">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                <p class="text-xs text-muted-foreground mb-1.5">Drop lease PDF here or click to browse</p>
                <AppButton variant="outline" size="sm">Choose File</AppButton>
              </div>
            </div>
          </div>
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="savingEditUnit" @click="showEditUnit = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="savingEditUnit" @click="saveEditUnit">
          {{ savingEditUnit ? 'Saving…' : 'Save Changes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Delete Unit Modal -->
    <AppModal title="Delete Unit" :show="showDeleteUnit" @close="showDeleteUnit = false">
      <div class="space-y-4">

        <div v-if="deleteUnitError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ deleteUnitError }}
        </div>

        <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/5 border border-destructive/20">
          <div class="w-9 h-9 rounded-full bg-destructive/10 flex items-center justify-center shrink-0 mt-0.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-destructive">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-destructive">
              Permanently deleting Unit <span class="text-foreground">{{ deletingUnitTarget?.unit }}</span> — this cannot be undone.
            </p>
            <p class="text-xs text-muted-foreground mt-1">This will remove the unit, owner record, all tenant records, and associated history.</p>
          </div>
        </div>

        <div class="border-t border-border pt-4">
          <label class="block text-sm font-medium text-foreground mb-1.5">
            Type <span class="font-semibold text-destructive">{{ deletingUnitTarget?.unit }}</span> to confirm
          </label>
          <input
            v-model="deletingUnitConfirm"
            type="text"
            :placeholder="deletingUnitTarget?.unit"
            class="w-full h-10 px-3 rounded border text-sm transition-colors outline-none
                   border-border bg-background text-foreground placeholder:text-muted-foreground
                   focus:border-destructive focus:ring-1 focus:ring-destructive/30"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="deletingUnitLoading" @click="showDeleteUnit = false">Cancel</AppButton>
        <AppButton
          variant="danger"
          :disabled="deletingUnitLoading || !deleteUnitConfirmMatches"
          @click="confirmDeleteUnit"
        >
          {{ deletingUnitLoading ? 'Deleting…' : 'Delete Unit' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Bulk Delete Units Modal -->
    <AppModal title="Delete Units" :show="showBulkDelete" @close="showBulkDelete = false">
      <div class="space-y-4">

        <div v-if="bulkDeleteError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ bulkDeleteError }}
        </div>

        <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/5 border border-destructive/20">
          <div class="w-9 h-9 rounded-full bg-destructive/10 flex items-center justify-center shrink-0 mt-0.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-destructive">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-destructive">
              Permanently deleting <span class="text-foreground">{{ selectedCount }} unit{{ selectedCount === 1 ? '' : 's' }}</span> — this cannot be undone.
            </p>
            <p class="text-xs text-muted-foreground mt-1">All owner records, tenant records, invoices, and associated history will be removed.</p>
          </div>
        </div>

        <div class="border-t border-border pt-4">
          <label class="block text-sm font-medium text-foreground mb-1.5">
            Type the estate name <span class="font-semibold text-destructive">{{ estate?.name }}</span> to confirm
          </label>
          <input
            v-model="bulkDeleteConfirm"
            type="text"
            :placeholder="estate?.name"
            class="w-full h-10 px-3 rounded border text-sm transition-colors outline-none
                   border-border bg-background text-foreground placeholder:text-muted-foreground
                   focus:border-destructive focus:ring-1 focus:ring-destructive/30"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="bulkDeleteLoading" @click="showBulkDelete = false">Cancel</AppButton>
        <AppButton
          variant="danger"
          :disabled="bulkDeleteLoading || !bulkDeleteConfirmMatches"
          @click="confirmBulkDelete"
        >
          {{ bulkDeleteLoading ? 'Deleting…' : `Delete ${selectedCount} unit${selectedCount === 1 ? '' : 's'}` }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Add Unit Modal -->
    <AppModal title="Add Unit" :show="showAddUnit" size="md" @close="showAddUnit = false">
      <div class="space-y-4">

        <div v-if="saveError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ saveError }}
        </div>

        <!-- Unit Number + Occupancy Type -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="newUnit.unitNumber" label="Unit Number" placeholder="e.g. A01" required />
          <AppSelect v-model="newUnit.occupancy" label="Occupancy Type" :options="editUnitOccupancyOptions" required />
        </div>

        <!-- Levy Override -->
        <AppInput v-if="editUnitShowLevy" v-model="newUnit.levyOverride" label="Levy Override" type="number" placeholder="Use default levy" />

        <!-- Rent Amount (shown when tenant-occupied — not applicable for sectional title) -->
        <AppInput v-if="newUnit.occupancy === 'tenant' && estate?.type !== 'sectional_title'" v-model="newUnit.tenant.rent" label="Rent Amount" type="number" />

        <!-- Owner Details -->
        <div class="border-t border-border pt-4">
          <p class="text-sm font-medium text-foreground mb-3">Owner Details</p>
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="newUnit.owner.name"     label="Full Name"  size="sm" placeholder="e.g. Sarah van der Merwe" required />
            <AppInput v-model="newUnit.owner.email"    label="Email"      size="sm" type="email" placeholder="e.g. sarah@email.com" required />
            <AppInput v-model="newUnit.owner.phone"    label="Phone"      size="sm" placeholder="e.g. +27 82 555 1234" />
            <AppInput v-model="newUnit.owner.idNumber" label="ID Number"  size="sm" placeholder="e.g. 8001015009088" />
          </div>
        </div>

        <!-- Tenant Details — hidden entirely for sectional title estates -->
        <div v-if="estate?.type !== 'sectional_title'" class="border-t border-border pt-4">
          <!-- Toggle header (owner/vacant units) -->
          <div v-if="newUnit.occupancy !== 'tenant'" class="flex items-center justify-between mb-3">
            <div>
              <p class="text-sm font-medium text-foreground">Tenant Details</p>
              <p class="text-xs text-muted-foreground">Toggle on if this unit also has a tenant</p>
            </div>
            <button type="button" class="text-primary transition-colors" @click="newUnit.showTenant = !newUnit.showTenant">
              <!-- Toggle ON -->
              <svg v-if="newUnit.showTenant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
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

          <!-- Tenant fields -->
          <div v-if="newUnitShowTenantFields" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="newUnit.tenant.name"       label="Full Name"   size="sm" placeholder="Tenant name" required />
              <AppInput v-model="newUnit.tenant.email"      label="Email"       size="sm" type="email" placeholder="Email address" required />
              <AppInput v-model="newUnit.tenant.phone"      label="Phone"       size="sm" placeholder="+27 ..." />
              <AppInput v-model="newUnit.tenant.rent"       label="Rent Amount" size="sm" type="number" placeholder="Monthly rent" required />
              <AppDatePicker v-model="newUnit.tenant.leaseStart" label="Lease Start" placeholder="Select date..." required />
              <AppDatePicker v-model="newUnit.tenant.leaseEnd" label="Lease End" placeholder="Select date..." />
            </div>
            <!-- Lease Document upload -->
            <div>
              <label class="block text-xs text-muted-foreground mb-1">Lease Document</label>
              <div class="border border-dashed border-border rounded-lg p-4 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground mx-auto mb-1.5">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                <p class="text-xs text-muted-foreground mb-1.5">Drop lease PDF here or click to browse</p>
                <AppButton variant="outline" size="sm">Choose File</AppButton>
              </div>
            </div>
          </div>
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="savingUnit" @click="showAddUnit = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="savingUnit" @click="saveUnit">
          {{ savingUnit ? 'Saving…' : 'Add Unit' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Edit Estate Modal -->
    <AppModal title="Edit Estate" :show="showEditEstate" size="lg" @close="showEditEstate = false">
      <div class="space-y-5">

        <div v-if="editEstateError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ editEstateError }}
        </div>

        <!-- Name + Type -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-model="editEstateForm.name"
            label="Estate Name"
            placeholder="e.g. Crystal Mews Body Corporate"
            required
          />
          <AppSelect v-model="editEstateForm.type" label="Estate Type" :options="ESTATE_TYPE_OPTS" required />
        </div>

        <!-- Address -->
        <AppInput
          v-model="editEstateForm.address"
          label="Address"
          placeholder="Full street address"
        />

        <!-- Financial defaults -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-if="editShowLevy"
            v-model="editEstateForm.default_levy_amount"
            label="Default Levy Amount"
            type="number"
            placeholder="0.00"
            prefix="R"
          />
          <AppInput
            v-if="editShowRent"
            v-model="editEstateForm.default_rent_amount"
            label="Default Rent Amount"
            type="number"
            placeholder="0.00"
            prefix="R"
          />
          <AppInput
            v-model="editEstateForm.billing_day"
            label="Billing Day"
            type="number"
            placeholder="e.g. 1"
            hint="Day of month (1–28) billing runs"
          />
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="editEstateSaving" @click="showEditEstate = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="editEstateSaving" @click="saveEditEstate">
          {{ editEstateSaving ? 'Saving…' : 'Save Changes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Delete Estate Modal -->
    <AppModal title="Delete Estate" :show="showDeleteEstate" @close="showDeleteEstate = false">
      <div class="space-y-4">

        <div v-if="deleteEstateError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ deleteEstateError }}
        </div>

        <!-- Warning icon + title -->
        <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/5 border border-destructive/20">
          <div class="w-9 h-9 rounded-full bg-destructive/10 flex items-center justify-center shrink-0 mt-0.5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-destructive">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-destructive">
              Permanently deleting <span class="text-foreground">{{ estate?.name }}</span> — this cannot be undone.
            </p>
          </div>
        </div>

        <!-- What will be deleted -->
        <div>
          <!-- Dynamic count list or generic fallback -->
          <div v-if="deleteCounts.loadingCounts" class="space-y-2 py-1">
            <div v-for="n in 3" :key="n" class="h-4 bg-muted rounded animate-pulse" :style="{ width: `${60 + n * 10}%` }" />
          </div>
          <template v-else-if="deleteItems.length > 0">
            <p class="text-sm font-medium text-foreground mb-2">The following will be permanently deleted:</p>
            <ul class="space-y-1.5 text-sm text-muted-foreground">
              <li v-for="item in deleteItems" :key="item" class="flex items-start gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-destructive shrink-0 mt-0.5">
                  <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
                <span>{{ item }}</span>
              </li>
            </ul>
          </template>
          <p v-else class="text-sm text-muted-foreground">
            This estate has no data yet. Deleting it will remove the estate record permanently.
          </p>
        </div>

        <!-- Name confirmation -->
        <div class="border-t border-border pt-4">
          <label class="block text-sm font-medium text-foreground mb-1.5">
            Type <span class="font-semibold text-destructive">{{ estate?.name }}</span> to confirm
          </label>
          <input
            v-model="deleteConfirmName"
            type="text"
            :placeholder="estate?.name"
            class="w-full h-10 px-3 rounded border text-sm transition-colors outline-none
                   border-border bg-background text-foreground placeholder:text-muted-foreground
                   focus:border-destructive focus:ring-1 focus:ring-destructive/30"
          />
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="deletingEstate" @click="showDeleteEstate = false">Cancel</AppButton>
        <AppButton
          variant="danger"
          :disabled="deletingEstate || !deleteNameMatches"
          @click="confirmDeleteEstate"
        >
          {{ deletingEstate ? 'Deleting…' : 'Delete Estate' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Row action menu — teleported to body to escape overflow-x-auto clipping -->
    <Teleport to="body">
      <!-- Backdrop -->
      <div v-if="rowMenuUnit" class="fixed inset-0 z-40" @click="closeRowMenu" />

      <!-- Menu panel -->
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="rowMenuUnit"
          class="fixed z-50 py-1 w-44 rounded-lg border border-border bg-card shadow-lg origin-top-right"
          :style="{ top: `${rowMenuAnchor.top}px`, right: `${rowMenuAnchor.right}px` }"
        >
          <!-- Edit -->
          <button
            type="button"
            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors"
            @click="openEditUnit(rowMenuUnit); closeRowMenu()"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground shrink-0">
              <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
            </svg>
            Edit Unit
          </button>

          <div class="mx-2 my-1 border-t border-border" />

          <!-- Delete -->
          <button
            type="button"
            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-destructive hover:bg-destructive/5 transition-colors"
            @click="openDeleteUnit(rowMenuUnit); closeRowMenu()"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 shrink-0">
              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
            </svg>
            Delete Unit
          </button>
        </div>
      </Transition>
    </Teleport>

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

    <!-- Bulk Import Modal -->
    <BulkImportUnitsModal
      :show="showBulkImport"
      :estate-id="route.params.id"
      :estate-type="estate?.type"
      @close="showBulkImport = false"
      @imported="onBulkImported"
    />

    <!-- Export Units Modal -->
    <AppExportModal
      :show="showExportModal"
      context="Units"
      @close="showExportModal = false"
      @download="handleExportDownload"
    />

  </div>
</template>
