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
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
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
import BulkImportPqModal from '@/components/common/BulkImportPqModal.vue'
import { useCountryStore } from '@/stores/country'
import AppExportModal from '@/components/common/AppExportModal.vue'
import CreateChecklistModal from '@/pages/compliance/CreateChecklistModal.vue'
import api from '@/composables/useApi.js'
import { useExport } from '@/composables/useExport.js'
import { useBack } from '@/composables/useBack.js'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route  = useRoute()
const { goBack } = useBack('/estates')
const countryStore = useCountryStore()
const { success, error: toastError } = useToast()

// ── Tab navigation ────────────────────────────────────────────────────
const VALID_TABS = ['units', 'pq', 'compliance', 'communication', 'overview']
const activeTab = computed(() => {
  const q = route.query.tab
  return VALID_TABS.includes(q) ? q : 'units'
})
function setTab(tabId) {
  router.replace({ query: { ...route.query, tab: tabId } })
}

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
    fetchAllPqUnits()
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
  const params = showAllUnits.value
    ? { _per_page: 1000, page: 1 }
    : { _per_page: PER_PAGE, page: currentPage.value }

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
      section:        u.section || null,
      pq:             u.pq ?? null,
      effectiveReserveLevy: u.effective_reserve_levy ?? 0,
      occupancy:      mapOccupancy(u.occupancy_type),
      balance:        u.balance ?? 0,
      outstanding:    u.outstanding_amount ?? 0,
      effectiveLevy:  u.effective_levy_amount ?? 0,
      levyOverride:   u.levy_override ?? null,
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

// ── Compliance state ─────────────────────────────────────────────────
const complianceChecklists = ref([])
const complianceLoading = ref(false)
const showCreateChecklist = ref(false)

async function fetchComplianceChecklists() {
  complianceLoading.value = true
  try {
    const { data } = await api.get('/compliance/checklists', {
      params: { estate_id: route.params.id, _per_page: 5 },
    })
    complianceChecklists.value = data.data || []
  } catch {
    // silent
  } finally {
    complianceLoading.value = false
  }
}

function complianceProgressColor(pct) {
  if (pct === 100) return 'bg-emerald-500'
  if (pct >= 50)   return 'bg-amber-500'
  if (pct > 0)     return 'bg-red-500'
  return 'bg-gray-300'
}

function complianceProgress(cl) {
  const total = cl.items_count || 0
  const done = cl.completed_items_count || 0
  return total > 0 ? Math.round((done / total) * 100) : 0
}

function onChecklistCreated(checklist) {
  fetchComplianceChecklists()
  router.push({ name: 'compliance-checklist', params: { checklistId: checklist.id } })
}

onMounted(() => {
  fetchEstate()
  fetchComplianceChecklists()
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
  showAllUnits.value = false

  // Debounce search input; apply all other changes immediately
  clearTimeout(searchDebounceTimer)
  if (state.search !== prevSearch) {
    unitsLoading.value = true  // show shimmer immediately on keystroke
    searchDebounceTimer = setTimeout(fetchUnits, 350)
  } else {
    fetchUnits()
  }
}

const currentPage  = ref(1)
const showAllUnits = ref(false)
const PER_PAGE     = 15

function toggleShowAll() {
  showAllUnits.value = !showAllUnits.value
  currentPage.value  = 1
  fetchUnits()
}

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
  if (amount === 0) return countryStore.formatCurrency(0)
  if (amount < 0) return '-' + countryStore.formatCurrency(Math.abs(amount))
  return countryStore.formatCurrency(amount)
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
    success('Email sent successfully.')
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
  country:             '',
  admin_fund_amount:   '',
  reserve_fund_amount: '',
  csos_levy_amount:    '',
  default_rent_amount: '',
  billing_day:         '',
  payment_terms_days:  '',
})

const editCountryOptions = Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({
  value: code,
  label: `${info.flag} ${info.name}`,
}))

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

const editFormCurrencySymbol = computed(() => {
  const code = editEstateForm.value.country
  return code ? (countryStore.COUNTRY_MAP[code]?.symbol || countryStore.currencySymbol) : countryStore.currencySymbol
})

function openEditEstate() {
  if (!estate.value) return
  editEstateForm.value = {
    name:                estate.value.name                ?? '',
    type:                estate.value.type                ?? 'sectional_title',
    address:             estate.value.address             ?? '',
    country:             estate.value.country             ?? countryStore.activeCountry ?? '',
    admin_fund_amount:   estate.value.admin_fund_amount   ?? '',
    reserve_fund_amount: estate.value.reserve_fund_amount ?? '',
    csos_levy_amount:    estate.value.csos_levy_amount    ?? '',
    default_rent_amount: estate.value.default_rent_amount ?? '',
    billing_day:         estate.value.billing_day         ?? '',
    payment_terms_days:  estate.value.payment_terms_days  ?? '',
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
    if (editEstateForm.value.country) {
      payload.country  = editEstateForm.value.country
      payload.currency = countryStore.COUNTRY_MAP[editEstateForm.value.country]?.currencyCode || null
    }
    if (editShowLevy.value && editEstateForm.value.admin_fund_amount !== '') {
      payload.admin_fund_amount = parseFloat(editEstateForm.value.admin_fund_amount) || 0
    }
    if (editShowLevy.value && editEstateForm.value.reserve_fund_amount !== '') {
      payload.reserve_fund_amount = parseFloat(editEstateForm.value.reserve_fund_amount) || 0
    }
    if (editShowLevy.value && editEstateForm.value.country === 'ZA' && editEstateForm.value.csos_levy_amount !== '') {
      payload.csos_levy_amount = parseFloat(editEstateForm.value.csos_levy_amount) || 0
    }
    if (editShowRent.value && editEstateForm.value.default_rent_amount !== '') {
      payload.default_rent_amount = parseFloat(editEstateForm.value.default_rent_amount) || 0
    }
    if (editEstateForm.value.billing_day !== '') {
      payload.billing_day = parseInt(editEstateForm.value.billing_day) || undefined
    }
    if (editEstateForm.value.payment_terms_days !== '') {
      payload.payment_terms_days = parseInt(editEstateForm.value.payment_terms_days) || undefined
    }
    await api.put(`/estates/${route.params.id}`, payload)
    showEditEstate.value = false
    await fetchEstate()
    success('Estate updated successfully.')
  } catch (e) {
    editEstateError.value = e?.response?.data?.message ?? 'Failed to update estate. Please try again.'
  } finally {
    editEstateSaving.value = false
  }
}

// ── Billing Settings modal ────────────────────────────────────────────
const showBillingDay        = ref(false)
const billingDayForm        = ref('')
const paymentTermsForm      = ref('')
const billingDaySaving      = ref(false)
const billingDayError       = ref(null)

function openBillingDay() {
  billingDayForm.value    = estate.value?.billing_day ?? ''
  paymentTermsForm.value  = estate.value?.payment_terms_days ?? 7
  billingDayError.value   = null
  showBillingDay.value    = true
}

async function saveBillingDay() {
  billingDaySaving.value = true
  billingDayError.value  = null
  try {
    const day   = parseInt(billingDayForm.value)
    const terms = parseInt(paymentTermsForm.value)
    if (!day || day < 1 || day > 28) {
      billingDayError.value = 'Please enter a billing day between 1 and 28.'
      return
    }
    if (!terms || terms < 1 || terms > 365) {
      billingDayError.value = 'Please enter payment terms between 1 and 365 days.'
      return
    }
    await api.put(`/estates/${route.params.id}`, { billing_day: day, payment_terms_days: terms })
    showBillingDay.value = false
    await fetchEstate()
    success('Billing settings updated.')
  } catch (e) {
    billingDayError.value = e?.response?.data?.message ?? 'Failed to update billing settings.'
  } finally {
    billingDaySaving.value = false
  }
}

// ── Billing pause toggle ──────────────────────────────────────────────
const billingPauseToggling = ref(false)

async function toggleBillingPaused() {
  billingPauseToggling.value = true
  try {
    const newState = !estate.value.billing_paused
    await api.put(`/estates/${route.params.id}`, { billing_paused: newState })
    await fetchEstate()
    success(newState ? 'Automatic billing paused.' : 'Automatic billing resumed.')
  } catch (e) {
    toastError(e?.response?.data?.message ?? 'Failed to update billing schedule.')
  } finally {
    billingPauseToggling.value = false
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

const addUnitStep = ref(1)
const addUnitTotalSteps = computed(() => estate.value?.type === 'sectional_title' ? 2 : 3)

const newUnit = ref({
  unitNumber:   '',
  section:      '',
  occupancy:    'owner',
  overrideLevy: false,
  levyOverride: '',
  pq:           '',
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
  addUnitStep.value = 1
  newUnit.value = {
    unitNumber: '', section: '', occupancy: 'owner', overrideLevy: false, levyOverride: '', pq: '', showTenant: false,
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

  if (newUnit.value.section !== '') payload.section = newUnit.value.section || null
  if (newUnit.value.pq !== '')      payload.pq      = parseFloat(newUnit.value.pq) || null
  if (editUnitShowLevy.value && newUnit.value.overrideLevy && newUnit.value.levyOverride !== '') {
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
    success('Unit created successfully.')
    // Set date filter to today so the newly created unit is shown prominently
    toolbarInitialDateRange.value = 'today'
    toolbarKey.value++
    toolbarState.value = { search: '', dateRange: 'today', customStart: '', customEnd: '', filters: {}, sort: null }
    currentPage.value  = 1
    await Promise.all([fetchEstate(), fetchUnits()])
    fetchAllPqUnits()
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
  section:       '',
  pq:            '',
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
    section:      unit.section ?? '',
    pq:           unit.pq != null ? String(unit.pq) : '',
    occupancy:    unit.occupancy,
    levyOverride: unit.levyOverride != null ? String(unit.levyOverride) : '',
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

  if (editUnitForm.value.section !== '') payload.section = editUnitForm.value.section || null
  if (editUnitForm.value.pq !== '')      payload.pq      = parseFloat(editUnitForm.value.pq) || null
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
    fetchAllPqUnits()
    success('Unit updated successfully.')
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
    success('Unit deleted successfully.')
    await fetchUnits()
    await fetchEstate()
    fetchAllPqUnits()
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
    success('Selected units deleted successfully.')
    selectedUnitIds.value = new Set()
    await fetchUnits()
    await fetchEstate()
    fetchAllPqUnits()
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

// ── Run Billing Now ──────────────────────────────────────────────
const showRunBilling    = ref(false)
const runBillingPreview = ref([])
const runBillingLoading = ref(false)
const runBillingConfirm = ref(false)
const runBillingError   = ref('')

const runBillingPeriod = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
})

const runBillingPeriodLabel = computed(() => {
  const now = new Date()
  return now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const nonDuplicateRun = computed(() => runBillingPreview.value.filter(r => !r.duplicate))
const duplicateRunCount = computed(() => runBillingPreview.value.filter(r => r.duplicate).length)
const runBillingTotal = computed(() => nonDuplicateRun.value.reduce((sum, r) => sum + Number(r.amount), 0))

async function openRunBilling() {
  showRunBilling.value    = true
  runBillingLoading.value = true
  runBillingError.value   = ''
  runBillingPreview.value = []
  try {
    const { data } = await api.post('/invoices/run-billing', {
      estate_id:      route.params.id,
      billing_period: runBillingPeriod.value,
      dry_run:        true,
    })
    runBillingPreview.value = data.preview || []
  } catch (e) {
    runBillingError.value = e.response?.data?.message || 'Failed to load billing preview'
  } finally {
    runBillingLoading.value = false
  }
}

async function confirmRunBilling() {
  runBillingConfirm.value = true
  runBillingError.value   = ''
  try {
    await api.post('/invoices/run-billing', {
      estate_id:      route.params.id,
      billing_period: runBillingPeriod.value,
      dry_run:        false,
    })
    closeRunBilling()
    fetchEstate()
    fetchUnits()
  } catch (e) {
    runBillingError.value = e.response?.data?.message || 'Failed to run billing'
  } finally {
    runBillingConfirm.value = false
  }
}

function closeRunBilling() {
  showRunBilling.value    = false
  runBillingPreview.value = []
  runBillingError.value   = ''
}

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

// Top Owner Arrears — Horizontal Bar (top 10 across ALL filtered units, from chartStats)
// Reversed so longest bar (highest arrears) is at the bottom
const arrearsUnits = computed(() => [...(chartStats.value?.top_owner_arrears ?? [])].reverse())

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

const arrearsChartOptions = computed(() => ({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  onHover: (event) => { event.native.target.style.cursor = 'pointer' },
  onClick: (_event, elements) => {
    if (!elements.length) return
    const unit = arrearsUnits.value[elements[0].index]
    if (unit?.unit_id) {
      router.push({ name: 'unit-detail', params: { estateId: route.params.id, unitId: unit.unit_id } })
    }
  },
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
        callback: (v) => countryStore.formatCurrencyCompact(v),
      },
      grid: { color: '#DCDEE8' },
    },
    y: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: '#717B99' },
    },
  },
}))

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

// Top Tenant Arrears — Horizontal Bar (top 10, reversed so longest at bottom)
const topTenantArrears = computed(() => [...(chartStats.value?.top_tenant_arrears ?? [])].reverse())
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
        callback: (v) => countryStore.formatCurrencyCompact(v),
      },
      grid: { color: '#DCDEE8' },
    },
    y: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: '#717B99' },
    },
  },
}

// ── PQ (Participation Quota) section ─────────────────────────────────
const isPqEstate = computed(() =>
  ['sectional_title', 'mixed'].includes(estate.value?.type)
)

const showPqImportModal  = ref(false)
const showPqExplainModal = ref(false)
const pqExplainView      = ref('breakdown') // 'breakdown' | 'learn'

function openPqExplain() {
  pqExplainView.value      = 'breakdown'
  showPqExplainModal.value = true
}

const pqStickyHeaderEl = ref(null)

// PQ search + quick filters
const pqSearch       = ref('')
const pqQuickFilter  = ref(null) // null | 'missing_pq' | 'has_override'

// Bulk PQ edit mode
const pqEditMode  = ref(false)
const pqDraft     = ref([])
const pqSaving    = ref(false)

function enterPqEditMode() {
  pqDraft.value    = pqAllUnits.value.map(u => ({
    id:          u.id,
    pqStr:       u.pq != null ? String(u.pq) : '',
    overrideStr: u.levyOverride != null ? String(u.levyOverride) : '',
  }))
  pqEditMode.value = true
}

function cancelPqEditMode() {
  pqEditMode.value    = false
  pqDraft.value       = []
  pqQuickFilter.value = null
}

const pqEditRows = computed(() => {
  if (!pqEditMode.value || !pqDraft.value.length) return pqRows.value

  const adminBudget   = parseFloat(estate.value?.admin_fund_amount   ?? 0)
  const reserveBudget = parseFloat(estate.value?.reserve_fund_amount ?? 0)
  const unitCount     = pqDraft.value.length

  const totalPq = pqDraft.value.reduce((s, d) => {
    const v = parseFloat(d.pqStr)
    return s + (isNaN(v) ? 0 : v)
  }, 0)

  return pqDraft.value.map(d => {
    const orig        = pqAllUnits.value.find(u => u.id === d.id) ?? {}
    const pq          = d.pqStr !== '' ? parseFloat(d.pqStr) : null
    const hasPq       = pq != null && !isNaN(pq)
    const override    = d.overrideStr !== '' ? parseFloat(d.overrideStr) : null
    const hasOverride = override != null && !isNaN(override)

    let effectiveLevy
    if (hasOverride) {
      effectiveLevy = Math.round(override * 100) / 100
    } else if (hasPq && totalPq > 0 && adminBudget > 0) {
      effectiveLevy = Math.round((pq / totalPq) * adminBudget * 100) / 100
    } else {
      effectiveLevy = unitCount > 0 ? Math.round((adminBudget / unitCount) * 100) / 100 : 0
    }

    let effectiveReserveLevy = 0
    if (hasPq && totalPq > 0 && reserveBudget > 0) {
      effectiveReserveLevy = Math.round((pq / totalPq) * reserveBudget * 100) / 100
    }

    return {
      id: d.id, unit: orig.unit, section: orig.section, ownerName: orig.ownerName,
      pq: hasPq ? pq : null,
      levyOverride: hasOverride ? override : null,
      effectiveLevy, effectiveReserveLevy,
    }
  })
})

const pqHasChanges = computed(() => {
  if (!pqEditMode.value) return false
  return pqDraft.value.some(d => {
    const orig         = pqAllUnits.value.find(u => u.id === d.id)
    if (!orig) return false
    const origPq       = orig.pq       != null ? orig.pq       : null
    const origOverride = orig.levyOverride != null ? orig.levyOverride : null
    const draftPq      = d.pqStr       !== '' ? parseFloat(d.pqStr)       : null
    const draftOverride = d.overrideStr !== '' ? parseFloat(d.overrideStr) : null
    return origPq !== draftPq || origOverride !== draftOverride
  })
})

// Map id → draft item so filtered rows can still bind inputs
const pqDraftMap = computed(() =>
  Object.fromEntries(pqDraft.value.map(d => [d.id, d]))
)

// Filtered view — works in both view and edit mode
const pqFilteredRows = computed(() => {
  let rows = pqEditRows.value

  if (pqQuickFilter.value === 'missing_pq') {
    rows = rows.filter(r => r.pq == null)
  } else if (pqQuickFilter.value === 'has_override') {
    rows = rows.filter(r => r.levyOverride != null)
  }

  const q = pqSearch.value.trim().toLowerCase()
  if (!q) return rows
  return rows.filter(r =>
    (r.section   ?? '').toLowerCase().includes(q) ||
    (r.unit      ?? '').toLowerCase().includes(q) ||
    (r.ownerName ?? '').toLowerCase().includes(q)
  )
})

async function savePqEdits() {
  if (!pqHasChanges.value || pqSaving.value) return
  pqSaving.value = true
  try {
    const changed = pqDraft.value.filter(d => {
      const orig         = pqAllUnits.value.find(u => u.id === d.id)
      if (!orig) return false
      const origPq       = orig.pq       != null ? orig.pq       : null
      const origOverride = orig.levyOverride != null ? orig.levyOverride : null
      const draftPq      = d.pqStr       !== '' ? parseFloat(d.pqStr)       : null
      const draftOverride = d.overrideStr !== '' ? parseFloat(d.overrideStr) : null
      return origPq !== draftPq || origOverride !== draftOverride
    })
    await Promise.all(changed.map(d => {
      const payload = {
        pq:            d.pqStr       !== '' ? (parseFloat(d.pqStr) || null)       : null,
        levy_override: d.overrideStr !== '' ? (parseFloat(d.overrideStr) || null) : null,
      }
      return api.put(`/estates/${route.params.id}/units/${d.id}`, payload)
    }))
    cancelPqEditMode()
    await fetchAllPqUnits()
    success(`${changed.length} unit${changed.length > 1 ? 's' : ''} updated.`)
  } catch {
    toastError('Failed to save changes.')
  } finally {
    pqSaving.value = false
  }
}

// All units for PQ section — fetched independently of the main table's pagination/filters.
const pqAllUnits        = ref([])
const pqAllUnitsLoading = ref(false)

async function fetchAllPqUnits() {
  if (!isPqEstate.value) return
  pqAllUnitsLoading.value = true
  try {
    const res = await api.get(`/estates/${route.params.id}/units`, { params: { _per_page: 1000 } })
    pqAllUnits.value = (res.data.data ?? []).map(u => ({
      id:                   u.id,
      unit:                 u.unit_number,
      section:              u.section || null,
      pq:                   u.pq ?? null,
      effectiveLevy:        u.effective_levy_amount ?? 0,
      effectiveReserveLevy: u.effective_reserve_levy ?? 0,
      levyOverride:         u.levy_override ?? null,
      ownerName:            u.owner?.full_name ?? '—',
    }))
  } catch (e) {
    console.error('Failed to load PQ units', e)
  } finally {
    pqAllUnitsLoading.value = false
  }
}

async function onPqImported() {
  await Promise.all([fetchUnits(), fetchEstate()])
  await fetchAllPqUnits()
}

const pqRows = computed(() => {
  if (!pqAllUnits.value.length) return []
  const totalPq = pqAllUnits.value.reduce((sum, u) => sum + (u.pq ?? 0), 0)
  return pqAllUnits.value.map(u => ({
    ...u,
    pqPercent: totalPq > 0 && u.pq != null ? ((u.pq / totalPq) * 100).toFixed(2) : null,
  }))
})

const pqBudgetStatus = computed(() => {
  if (!estate.value || !pqRows.value.length) return null
  const totalAdmin    = pqRows.value.reduce((s, r) => s + r.effectiveLevy, 0)
  const totalReserve  = pqRows.value.reduce((s, r) => s + r.effectiveReserveLevy, 0)
  const adminBudget   = parseFloat(estate.value.admin_fund_amount   ?? 0)
  const reserveBudget = parseFloat(estate.value.reserve_fund_amount ?? 0)
  const adminDiff     = totalAdmin   - adminBudget
  const reserveDiff   = totalReserve - reserveBudget
  const missingPq     = pqAllUnits.value.filter(u => u.pq == null).length
  const overrideUnits  = pqRows.value.filter(r => r.levyOverride != null)
  const pqUnits        = pqRows.value.filter(r => r.levyOverride == null && r.pq != null)
  const fallbackUnits  = pqRows.value.filter(r => r.levyOverride == null && r.pq == null)
  const adminOverrideCount  = overrideUnits.length
  const adminOverrideTotal  = overrideUnits.reduce((s, r) => s + r.effectiveLevy, 0)
  const adminPqCount        = pqUnits.length
  const adminPqTotal        = pqUnits.reduce((s, r) => s + r.effectiveLevy, 0)
  const adminFallbackCount  = fallbackUnits.length
  const adminFallbackTotal  = fallbackUnits.reduce((s, r) => s + r.effectiveLevy, 0)
  return {
    totalAdmin, totalReserve,
    adminBudget, reserveBudget,
    adminDiff, reserveDiff,
    missingPq,
    adminOverrideCount, adminOverrideTotal,
    adminPqCount, adminPqTotal,
    adminFallbackCount, adminFallbackTotal,
    hasIssues: Math.abs(adminDiff) >= 1 || (reserveBudget > 0 && Math.abs(reserveDiff) >= 1),
  }
})

const estateTabs = computed(() => {
  const tabs = [
    { id: 'units', label: 'Units', badge: computedStats.value.units > 0 ? computedStats.value.units : null },
    { id: 'compliance', label: 'Compliance', badge: complianceChecklists.value.length > 0 ? complianceChecklists.value.length : null },
    { id: 'communication', label: 'Communication', badge: null },
    { id: 'overview', label: 'Overview', badge: null },
  ]
  if (isPqEstate.value) {
    tabs.splice(1, 0, { id: 'pq', label: 'Participation Quotas', badge: null })
  }
  return tabs
})
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
    <div v-if="!estateLoading && estate" class="rounded-lg border bg-card shadow-sm overflow-hidden">
      <div class="flex items-center gap-0">

        <!-- Left section: schedule info with accent left border (muted when paused) -->
        <div :class="['flex items-center gap-3.5 flex-1 min-w-0 px-5 py-4 border-l-[3px]', estate.billing_paused ? 'border-l-muted-foreground/30' : 'border-l-accent']">
          <!-- Calendar icon -->
          <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', estate.billing_paused ? 'bg-muted' : 'bg-accent/10']">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="estate.billing_paused ? 'text-muted-foreground' : 'text-accent'">
              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
            </svg>
          </div>

          <!-- Schedule text -->
          <div class="min-w-0">
            <template v-if="billingSchedule">
              <p class="text-sm font-medium text-foreground">
                Billing runs on the <span :class="estate.billing_paused ? 'text-muted-foreground font-semibold' : 'text-accent font-semibold'">{{ billingSchedule.ordinal }}</span> of each month
                <span v-if="estate.billing_paused" class="ml-1.5 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-muted text-muted-foreground border border-border">Paused</span>
              </p>
              <p class="text-xs text-muted-foreground mt-0.5">
                <template v-if="estate.billing_paused">Automatic billing is paused — run manually or resume to re-enable.</template>
                <template v-else>Invoices auto-generated for
                  <template v-if="estate?.type === 'sectional_title'">owners</template>
                  <template v-else-if="estate?.type === 'residential_rental' || estate?.type === 'commercial_rental'">tenants</template>
                  <template v-else>owners &amp; tenants</template>
                </template>
              </p>
            </template>
            <template v-else>
              <p class="text-sm font-medium text-muted-foreground">No billing day configured</p>
              <p class="text-xs text-muted-foreground mt-0.5">Set a billing day to auto-generate invoices each month.</p>
            </template>
          </div>
        </div>

        <!-- Run Now button (always visible when schedule exists) -->
        <div v-if="billingSchedule && computedStats.units > 0" class="shrink-0 flex items-center px-5 py-3">
          <AppButton
            variant="primary"
            size="sm"
            @click="openRunBilling"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
            Run Now
          </AppButton>
        </div>

        <!-- Countdown section (hidden when paused) -->
        <template v-if="billingSchedule && computedStats.units > 0 && !estate.billing_paused">
          <div class="shrink-0 flex items-center gap-3 pl-5 pr-5 py-4 border-l border-border/40">
            <div>
              <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-medium leading-none mb-1.5">Next Run</p>
              <p class="text-sm font-semibold text-foreground leading-tight">{{ billingSchedule.formatted }}</p>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-mono font-semibold leading-none bg-accent/10 text-accent border border-accent/20">
              <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-70">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              {{ countdownText }}
            </span>
          </div>
        </template>

        <!-- No units prompt -->
        <template v-else-if="billingSchedule && computedStats.units === 0 && !estate.billing_paused">
          <div class="shrink-0 px-5 py-4 border-l border-border">
            <p class="text-xs text-muted-foreground">Add units to see the</p>
            <p class="text-xs text-muted-foreground">billing countdown</p>
          </div>
        </template>

        <!-- Pause / Resume toggle -->
        <div v-if="billingSchedule" class="shrink-0 flex items-center ml-4">
          <!-- When paused: single "Resume" pill button -->
          <button
            v-if="estate.billing_paused"
            @click="toggleBillingPaused"
            :disabled="billingPauseToggling"
            title="Resume automatic billing schedule"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md border border-accent/40 bg-accent/10 hover:bg-accent/20 text-sm font-medium text-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="6 3 20 12 6 21 6 3"/>
            </svg>
            Resume Schedule
          </button>
          <!-- When active: icon-only pause button -->
          <button
            v-else
            @click="toggleBillingPaused"
            :disabled="billingPauseToggling"
            title="Pause automatic billing"
            class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
            </svg>
          </button>
        </div>

        <!-- Edit button -->
        <button
          @click="openBillingDay"
          title="Edit billing day"
          class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors ml-2 mr-4"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
          </svg>
        </button>

      </div>
    </div>

    <!-- ── Tab Navigation ────────────────────────────────────────────── -->
    <div class="border-b border-border">
      <nav class="flex" aria-label="Estate sections">
        <button
          v-for="tab in estateTabs"
          :key="tab.id"
          @click="setTab(tab.id)"
          :class="[
            'inline-flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors focus:outline-none',
            activeTab === tab.id
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
          ]"
        >
          {{ tab.label }}
          <span
            v-if="tab.badge != null"
            :class="[
              'inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full px-1 text-[10px] font-semibold leading-none',
              activeTab === tab.id ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'
            ]"
          >{{ tab.badge }}</span>
        </button>
      </nav>
    </div>

    <!-- ── Units Table Card ─────────────────────────────────────────── -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'units'" class="rounded-lg border bg-card shadow-sm">

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
        <div class="flex items-center gap-2">
          <AppButton variant="primary" size="sm" @click="showAddUnit = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Unit
          </AppButton>
          <AppButton variant="outline" size="sm" @click="showBulkImport = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Bulk Import
          </AppButton>
          <AppButton variant="outline" size="sm" @click="showExportModal = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Export
          </AppButton>
        </div>
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
      <div v-if="!unitsLoading && totalUnitsInQuery > PER_PAGE" class="flex items-center justify-between px-6 py-3 border-t border-border">
        <p class="text-xs text-muted-foreground">
          <template v-if="showAllUnits">Showing all {{ totalUnitsInQuery }} units</template>
          <template v-else>Showing {{ (currentPage - 1) * PER_PAGE + 1 }}–{{ Math.min(currentPage * PER_PAGE, totalUnitsInQuery) }} of {{ totalUnitsInQuery }} units</template>
        </p>
        <div class="flex items-center gap-2">
          <button
            class="h-8 px-3 text-xs rounded border border-border hover:bg-muted transition-colors"
            @click="toggleShowAll"
          >{{ showAllUnits ? 'Show Less' : 'Show All' }}</button>
          <div v-if="!showAllUnits" class="flex items-center gap-1">
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
      </div>

      </template><!-- end normal state -->
    </div>
    </Transition>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- PQ (Participation Quota) Section                              -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'pq'" class="bg-card border border-border rounded-lg">
      <!-- Sticky header group: title + budget strip + search/filters + column headers -->
      <div ref="pqStickyHeaderEl" class="sticky -top-6 z-20 bg-card rounded-t-lg">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-border">
        <div>
          <h3 class="text-sm font-semibold text-foreground">Participation Quotas (PQ)</h3>
          <p class="text-xs text-muted-foreground mt-0.5">PQ determines each unit's share of the admin and reserve fund levy.</p>
        </div>
        <div class="flex items-center gap-2">
          <!-- View mode actions -->
          <template v-if="!pqEditMode">
            <AppButton variant="outline" size="sm" @click="showPqImportModal = true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
              </svg>
              Bulk Import PQs
            </AppButton>
            <AppButton variant="outline" size="sm" @click="enterPqEditMode" :disabled="pqAllUnitsLoading">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/>
              </svg>
              Edit PQs
            </AppButton>
          </template>
          <!-- Edit mode actions -->
          <template v-else>
            <AppButton variant="outline" size="sm" @click="cancelPqEditMode" :disabled="pqSaving">
              Cancel
            </AppButton>
            <AppButton variant="primary" size="sm" @click="savePqEdits" :disabled="!pqHasChanges || pqSaving" :loading="pqSaving">
              <svg v-if="!pqSaving" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
              </svg>
              Save Changes
            </AppButton>
          </template>
        </div>
      </div>

      <!-- Budget health strip -->
      <div v-if="pqBudgetStatus?.hasIssues" class="flex items-center gap-3 px-6 py-2 border-b border-amber-100 bg-amber-50/50">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-amber-500 shrink-0">
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
        </svg>
        <div class="flex items-center gap-2 flex-wrap min-w-0 flex-1">
          <span v-if="Math.abs(pqBudgetStatus.adminDiff) > 0.01" class="inline-flex items-center gap-1.5 text-xs text-amber-700">
            <span class="font-medium text-amber-600/80 uppercase tracking-wide text-[10px]">Admin Fund</span>
            <span class="font-semibold text-amber-900">{{ countryStore.formatCurrency(pqBudgetStatus.totalAdmin) }}</span>
            <span class="text-amber-400">/</span>
            <span class="text-amber-600/70">{{ countryStore.formatCurrency(pqBudgetStatus.adminBudget) }}</span>
            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
              :class="pqBudgetStatus.adminDiff < 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
              {{ pqBudgetStatus.adminDiff > 0 ? '+' : '' }}{{ countryStore.formatCurrency(pqBudgetStatus.adminDiff) }}
            </span>
          </span>
          <span v-if="pqBudgetStatus.reserveBudget > 0 && Math.abs(pqBudgetStatus.reserveDiff) > 0.01" class="text-amber-200 text-xs select-none">|</span>
          <span v-if="pqBudgetStatus.reserveBudget > 0 && Math.abs(pqBudgetStatus.reserveDiff) > 0.01" class="inline-flex items-center gap-1.5 text-xs text-amber-700">
            <span class="font-medium text-amber-600/80 uppercase tracking-wide text-[10px]">Reserve Fund</span>
            <span class="font-semibold text-amber-900">{{ countryStore.formatCurrency(pqBudgetStatus.totalReserve) }}</span>
            <span class="text-amber-400">/</span>
            <span class="text-amber-600/70">{{ countryStore.formatCurrency(pqBudgetStatus.reserveBudget) }}</span>
            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
              :class="pqBudgetStatus.reserveDiff < 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
              {{ pqBudgetStatus.reserveDiff > 0 ? '+' : '' }}{{ countryStore.formatCurrency(pqBudgetStatus.reserveDiff) }}
            </span>
          </span>
          <span v-if="pqBudgetStatus.missingPq > 0" class="text-amber-200 text-xs select-none">|</span>
          <span v-if="pqBudgetStatus.missingPq > 0" class="inline-flex items-center gap-1 text-xs text-amber-700">
            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 bg-amber-100 text-[11px] font-semibold text-amber-700">
              {{ pqBudgetStatus.missingPq }} {{ pqBudgetStatus.missingPq === 1 ? 'unit' : 'units' }} missing PQ
            </span>
          </span>
        </div>
        <AppButton variant="outline" size="sm" @click="openPqExplain">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
          </svg>
          Explain
        </AppButton>
      </div>

      <!-- Search + quick filters -->
      <div class="flex items-center gap-3 px-4 py-2.5 border-b border-border flex-wrap">
        <!-- Text search -->
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            v-model="pqSearch"
            type="text"
            placeholder="Search section, unit, owner…"
            class="w-56 pl-8 pr-7 py-1.5 text-sm bg-background border border-border rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors"
          />
          <button v-if="pqSearch" type="button" @click="pqSearch = ''"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Divider -->
        <div class="w-px h-4 bg-border shrink-0" />

        <!-- Quick filter chips -->
        <div class="flex items-center gap-1.5">
          <button type="button"
            :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors border',
              pqQuickFilter === null
                ? 'bg-foreground text-background border-foreground'
                : 'bg-transparent text-muted-foreground border-border hover:border-foreground/40 hover:text-foreground']"
            @click="pqQuickFilter = null">
            All
            <span class="opacity-60 font-normal">{{ pqEditRows.length }}</span>
          </button>
          <button type="button"
            :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors border',
              pqQuickFilter === 'missing_pq'
                ? 'bg-foreground text-background border-foreground'
                : 'bg-transparent text-muted-foreground border-border hover:border-foreground/40 hover:text-foreground']"
            @click="pqQuickFilter = pqQuickFilter === 'missing_pq' ? null : 'missing_pq'">
            Missing PQ
            <span class="opacity-75 font-normal">{{ pqEditRows.filter(r => r.pq == null).length }}</span>
          </button>
          <button type="button"
            :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors border',
              pqQuickFilter === 'has_override'
                ? 'bg-foreground text-background border-foreground'
                : 'bg-transparent text-muted-foreground border-border hover:border-foreground/40 hover:text-foreground']"
            @click="pqQuickFilter = pqQuickFilter === 'has_override' ? null : 'has_override'">
            Has Override
            <span class="opacity-75 font-normal">{{ pqEditRows.filter(r => r.levyOverride != null).length }}</span>
          </button>
        </div>
      </div>

      <!-- Column headers — part of the sticky group -->
      <div :class="['border-b border-border transition-colors', pqEditMode ? 'bg-primary/5' : 'bg-muted/40']">
        <table class="w-full text-sm table-fixed">
          <colgroup>
            <col class="w-[8%]" /><col class="w-[11%]" /><col /><col class="w-[11%]" /><col class="w-[16%]" /><col class="w-[14%]" /><col class="w-[13%]" />
          </colgroup>
          <thead>
            <tr>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Section</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Unit No</th>
              <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Owner</th>
              <th class="text-right py-3 px-4 text-xs font-medium uppercase tracking-wider"
                :class="pqEditMode ? 'text-primary' : 'text-muted-foreground'">PQ</th>
              <th class="text-right py-3 px-4 text-xs font-medium uppercase tracking-wider"
                :class="pqEditMode ? 'text-primary' : 'text-muted-foreground'">Admin Levy</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Reserve Levy</th>
              <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Levy</th>
            </tr>
          </thead>
        </table>
      </div>

      </div><!-- end sticky header group -->

      <!-- Table data -->
      <table class="w-full text-sm table-fixed">
        <colgroup>
          <col class="w-[8%]" /><col class="w-[11%]" /><col /><col class="w-[11%]" /><col class="w-[16%]" /><col class="w-[14%]" /><col class="w-[13%]" />
        </colgroup>
        <tbody class="divide-y divide-border">
            <tr v-if="!allUnits.length">
              <td colspan="7" class="py-8 text-center text-sm text-muted-foreground">No units found.</td>
            </tr>
            <tr v-else-if="!pqFilteredRows.length">
              <td colspan="7" class="py-12 text-center">
                <div class="flex flex-col items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-muted-foreground/40">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                  </svg>
                  <p class="text-sm font-medium text-muted-foreground">No results found</p>
                  <button type="button" class="text-xs text-primary hover:underline"
                    @click="pqSearch = ''; pqQuickFilter = null">
                    Clear filters
                  </button>
                </div>
              </td>
            </tr>
            <tr v-for="row in pqFilteredRows" :key="row.id"
              :class="['transition-colors', pqEditMode ? 'hover:bg-muted/10' : 'hover:bg-muted/20']">
              <td class="py-3 px-4 text-muted-foreground">{{ row.section ?? '—' }}</td>
              <td class="py-3 px-4 font-medium text-foreground">{{ row.unit }}</td>
              <td class="py-3 px-4 text-muted-foreground">{{ row.ownerName }}</td>
              <!-- PQ cell -->
              <td class="py-2 px-4 text-right">
                <input v-if="pqEditMode"
                  v-model="pqDraftMap[row.id].pqStr"
                  type="number" step="0.0001" min="0" placeholder="—"
                  class="w-24 text-right font-mono text-sm bg-background border border-border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors"
                />
                <template v-else>
                  <span v-if="row.pq != null" class="font-mono text-foreground">{{ Number(row.pq).toFixed(4) }}</span>
                  <span v-else class="text-muted-foreground">—</span>
                </template>
              </td>
              <!-- Admin Levy cell -->
              <td class="py-2 px-4 text-right">
                <!-- Edit mode: override input for every row -->
                <template v-if="pqEditMode">
                  <div class="flex items-center justify-end gap-1">
                    <input
                      v-model="pqDraftMap[row.id].overrideStr"
                      type="number" step="0.01" min="0" placeholder="—"
                      :class="['w-28 text-right text-sm bg-background border rounded px-2 py-1 focus:outline-none focus:ring-1 transition-colors',
                        pqDraftMap[row.id].overrideStr !== ''
                          ? 'border-amber-400 focus:ring-amber-500 focus:border-amber-500'
                          : 'border-border focus:ring-primary focus:border-primary']"
                    />
                    <button v-if="pqDraftMap[row.id].overrideStr !== ''" type="button" title="Remove override"
                      class="flex items-center justify-center w-5 h-5 rounded text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                      @click="pqDraftMap[row.id].overrideStr = ''">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                      </svg>
                    </button>
                    <div v-else class="w-5 shrink-0" />
                  </div>
                </template>
                <!-- View mode: override badge + levy amount -->
                <template v-else>
                  <div class="flex items-center justify-end gap-1.5">
                    <AppPoptip v-if="row.levyOverride != null" position="bottom" max-width="220px">
                      <template #trigger>
                        <span class="inline-flex items-center gap-0.5 rounded px-1 py-px text-[9px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-700 border border-amber-200 cursor-pointer">
                          Override
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-2.5 h-2.5 opacity-70">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                          </svg>
                        </span>
                      </template>
                      <div class="p-3 space-y-1.5">
                        <div class="flex items-center gap-2 pb-2 border-b border-border">
                          <div class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 text-amber-700">
                              <line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                          </div>
                          <p class="text-xs font-semibold text-foreground">Fixed Levy Override</p>
                        </div>
                        <p class="text-xs text-muted-foreground">This unit's levy is set to a fixed amount of <span class="font-semibold text-foreground">{{ countryStore.formatCurrency(row.levyOverride) }}</span> per month.</p>
                        <p class="text-[10px] text-muted-foreground">Not calculated from PQ — overrides the estate formula.</p>
                      </div>
                    </AppPoptip>
                    <span class="text-foreground">{{ countryStore.formatCurrency(row.effectiveLevy) }}</span>
                  </div>
                </template>
              </td>
              <td class="py-3 px-4 text-right text-foreground">{{ countryStore.formatCurrency(row.effectiveReserveLevy) }}</td>
              <td class="py-3 px-4 text-right font-medium text-foreground">{{ countryStore.formatCurrency(row.effectiveLevy + row.effectiveReserveLevy) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="allUnits.length" class="border-t border-border bg-muted/20">
            <tr>
              <td colspan="3" class="py-3 px-4 text-xs font-medium text-muted-foreground">Totals</td>
              <td class="py-3 px-4 text-right text-xs font-mono font-medium text-foreground">
                {{ pqEditRows.reduce((s, r) => s + (r.pq ?? 0), 0).toFixed(4) }}
              </td>
              <td class="py-3 px-4 text-right text-xs font-medium text-foreground">
                {{ countryStore.formatCurrency(pqEditRows.reduce((s, r) => s + r.effectiveLevy, 0)) }}
              </td>
              <td class="py-3 px-4 text-right text-xs font-medium text-foreground">
                {{ countryStore.formatCurrency(pqEditRows.reduce((s, r) => s + r.effectiveReserveLevy, 0)) }}
              </td>
              <td class="py-3 px-4 text-right text-xs font-medium text-foreground">
                {{ countryStore.formatCurrency(pqEditRows.reduce((s, r) => s + r.effectiveLevy + r.effectiveReserveLevy, 0)) }}
              </td>
            </tr>
          </tfoot>
        </table>
    </div>
    </Transition>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- Compliance Section                                            -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'compliance'" class="rounded-lg border bg-card shadow-sm">
      <div class="flex items-center justify-between px-6 pt-5 pb-3">
        <div>
          <h3 class="font-body font-semibold text-base text-foreground">Compliance</h3>
          <p class="text-xs text-muted-foreground mt-0.5">Annual compliance checklists for this estate</p>
        </div>
        <AppButton variant="outline" size="sm" @click="showCreateChecklist = true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 mr-1">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          New Checklist
        </AppButton>
      </div>
      <div class="px-6 pb-5">
        <!-- Loading -->
        <div v-if="complianceLoading" class="space-y-2">
          <div v-for="i in 2" :key="i" class="h-16 bg-muted/50 rounded-lg animate-pulse" />
        </div>

        <!-- Checklists -->
        <div v-else-if="complianceChecklists.length" class="space-y-2">
          <router-link
            v-for="cl in complianceChecklists"
            :key="cl.id"
            :to="{ name: 'compliance-checklist', params: { checklistId: cl.id } }"
            class="flex items-center gap-4 p-3 rounded-lg border border-border hover:bg-gray-50 hover:border-primary/20 transition-all group"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground group-hover:text-primary transition-colors">
                FY {{ cl.financial_year_label }}
              </p>
              <p class="text-xs text-muted-foreground mt-0.5">
                {{ cl.completed_items_count || 0 }} of {{ cl.items_count || 0 }} items completed
                <span v-if="cl.overdue_items_count > 0" class="text-red-600 ml-1">&bull; {{ cl.overdue_items_count }} overdue</span>
              </p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
              <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                  :class="['h-full rounded-full transition-all', complianceProgressColor(complianceProgress(cl))]"
                  :style="{ width: complianceProgress(cl) + '%' }"
                />
              </div>
              <span class="text-xs font-semibold w-8 text-right" :class="complianceProgress(cl) === 100 ? 'text-emerald-600' : 'text-muted-foreground'">
                {{ complianceProgress(cl) }}%
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted-foreground group-hover:text-primary transition-colors">
                <path d="m9 18 6-6-6-6"/>
              </svg>
            </div>
          </router-link>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-8">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-10 h-10 text-muted-foreground mx-auto mb-2">
            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="9" y="3" width="6" height="4" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 14l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="text-sm text-muted-foreground mb-3">No compliance checklists for this estate yet</p>
          <AppButton variant="primary" size="sm" @click="showCreateChecklist = true">
            Create First Checklist
          </AppButton>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Create Checklist Modal -->
    <CreateChecklistModal
      :show="showCreateChecklist"
      :estate-id="route.params.id"
      :estate-name="estate?.name || ''"
      @close="showCreateChecklist = false"
      @created="onChecklistCreated"
    />

    <!-- ── Overview Tab: Charts ─────────────────────────────────────── -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'overview'" class="space-y-6">

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
        <h3 class="font-body font-semibold text-base text-foreground">Top Owner Arrears</h3>
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
          <p class="text-xs text-muted-foreground mt-2">No arrears — top owner arrears will appear here</p>
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

    </div><!-- end overview tab -->
    </Transition>

    <!-- ── Communication Tab ─────────────────────────────────────────── -->
    <div v-show="activeTab === 'communication'" class="space-y-6">

      <!-- Header row -->
      <div class="rounded-lg border bg-card shadow-sm px-6 py-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
          </div>
          <div>
            <h2 class="text-base font-semibold text-foreground">Email Communication</h2>
            <p class="text-sm text-muted-foreground">Send email notices, statements and announcements to estate residents</p>
          </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
          Coming Soon
        </span>
      </div>

      <!-- Feature preview cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Notices & Announcements -->
        <div class="rounded-lg border bg-card shadow-sm p-5 flex flex-col gap-3 opacity-60">
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
            </svg>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-foreground">Notices & Announcements</h3>
            <p class="text-xs text-muted-foreground mt-1">Email important notices and announcements to all owners and tenants in bulk.</p>
          </div>
          <div class="mt-auto pt-2 border-t border-border">
            <span class="text-xs text-muted-foreground">Bulk email · Template library · Open tracking</span>
          </div>
        </div>

        <!-- Monthly Statements -->
        <div class="rounded-lg border bg-card shadow-sm p-5 flex flex-col gap-3 opacity-60">
          <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-foreground">Monthly Statements</h3>
            <p class="text-xs text-muted-foreground mt-1">Automatically email account statements and invoices to owners and tenants each month.</p>
          </div>
          <div class="mt-auto pt-2 border-t border-border">
            <span class="text-xs text-muted-foreground">Auto-send · PDF attachments · Per-unit delivery</span>
          </div>
        </div>

        <!-- Payment Reminders -->
        <div class="rounded-lg border bg-card shadow-sm p-5 flex flex-col gap-3 opacity-60">
          <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
            </svg>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-foreground">Payment Reminders</h3>
            <p class="text-xs text-muted-foreground mt-1">Send automated email reminders for upcoming due dates and overdue balances.</p>
          </div>
          <div class="mt-auto pt-2 border-t border-border">
            <span class="text-xs text-muted-foreground">Scheduled sends · Overdue alerts · Custom triggers</span>
          </div>
        </div>

      </div>

      <!-- Sent email history placeholder -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
          <h3 class="text-sm font-semibold text-foreground">Sent Emails</h3>
          <span class="text-xs text-muted-foreground">No emails sent yet</span>
        </div>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
          <svg class="w-10 h-10 text-muted-foreground/30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
          </svg>
          <p class="text-sm text-muted-foreground">Sent email history will appear here once email communication is enabled.</p>
        </div>
      </div>

    </div><!-- end communication tab -->

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
          <AppInput v-model="editUnitForm.section" label="Section" placeholder="e.g. A" />
        </div>
        <AppSelect v-model="editUnitForm.occupancy" label="Occupancy Type" :options="editUnitOccupancyOptions" required />

        <!-- Levy Override + PQ -->
        <div v-if="editUnitShowLevy" class="grid grid-cols-2 gap-4">
          <AppInput v-model="editUnitForm.levyOverride" label="Levy Override" type="number" placeholder="Calculated from PQ" :min="0" :max="9999999999.99" />
          <AppInput v-model="editUnitForm.pq" label="Participation Quota (PQ)" type="number" placeholder="e.g. 8.53" hint="% of total. All units must sum to 100." :min="0" :max="100" />
        </div>

        <!-- Rent Amount (shown when tenant-occupied — not applicable for sectional title) -->
        <AppInput v-if="editUnitForm.occupancy === 'tenant' && estate.type !== 'sectional_title'" v-model="editUnitForm.tenant.rent" label="Rent Amount" type="number" :min="0" :max="9999999999.99" />

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
              <AppInput v-model="editUnitForm.tenant.rent"       label="Rent Amount" size="sm" type="number" placeholder="Monthly rent" required :min="0" :max="9999999999.99" />
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
    <AppModal title="Add Unit" :show="showAddUnit" size="md" @close="showAddUnit = false; resetNewUnit()">

      <!-- Step indicator -->
      <div class="flex items-center gap-3 mb-5 pb-5 border-b border-border">
        <!-- Step 1 -->
        <div class="flex items-center gap-2">
          <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-colors', addUnitStep === 1 ? 'bg-primary text-white' : 'bg-success text-white']">
            <svg v-if="addUnitStep > 1" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span v-else>1</span>
          </span>
          <span :class="['text-xs font-medium', addUnitStep === 1 ? 'text-foreground' : 'text-muted-foreground']">Unit Details</span>
        </div>
        <div class="flex-1 h-px bg-border"></div>
        <!-- Step 2 -->
        <div class="flex items-center gap-2">
          <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-colors', addUnitStep === 2 ? 'bg-primary text-white' : addUnitStep > 2 ? 'bg-success text-white' : 'bg-muted text-muted-foreground']">
            <svg v-if="addUnitStep > 2" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span v-else>2</span>
          </span>
          <span :class="['text-xs font-medium', addUnitStep === 2 ? 'text-foreground' : 'text-muted-foreground']">Owner Details</span>
        </div>
        <template v-if="addUnitTotalSteps === 3">
          <div class="flex-1 h-px bg-border"></div>
          <!-- Step 3 -->
          <div class="flex items-center gap-2">
            <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-colors', addUnitStep === 3 ? 'bg-primary text-white' : 'bg-muted text-muted-foreground']">3</span>
            <span :class="['text-xs font-medium', addUnitStep === 3 ? 'text-foreground' : 'text-muted-foreground']">Tenant Details</span>
          </div>
        </template>
      </div>

      <!-- Step 1: Unit Details -->
      <div v-if="addUnitStep === 1" class="space-y-4">
        <div v-if="saveError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">{{ saveError }}</div>

        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="newUnit.unitNumber" label="Unit Number" placeholder="e.g. A01" required />
          <AppInput v-model="newUnit.section" label="Section" placeholder="e.g. A" />
        </div>
        <AppSelect v-model="newUnit.occupancy" label="Occupancy Type" :options="editUnitOccupancyOptions" required />

        <template v-if="editUnitShowLevy">
          <AppInput v-model="newUnit.pq" label="Participation Quota (PQ)" type="number" placeholder="e.g. 8.53" hint="% of total. All units must sum to 100." :min="0" :max="100" />

          <!-- Levy Override toggle -->
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-foreground">Override Levy Amount</p>
              <p class="text-xs text-muted-foreground">Set a fixed levy instead of using PQ calculation</p>
            </div>
            <button type="button" @click="newUnit.overrideLevy = !newUnit.overrideLevy; if (!newUnit.overrideLevy) newUnit.levyOverride = ''">
              <svg v-if="newUnit.overrideLevy" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
              </svg>
            </button>
          </div>
          <AppInput v-if="newUnit.overrideLevy" v-model="newUnit.levyOverride" type="number" placeholder="e.g. 1500.00" :min="0" :max="9999999999.99" />
        </template>

        <AppInput v-if="newUnit.occupancy === 'tenant' && estate?.type !== 'sectional_title'" v-model="newUnit.tenant.rent" label="Rent Amount" type="number" :min="0" :max="9999999999.99" />
      </div>

      <!-- Step 2: Owner Details -->
      <div v-if="addUnitStep === 2" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="newUnit.owner.name"     label="Full Name"  placeholder="e.g. Sarah van der Merwe" required />
          <AppInput v-model="newUnit.owner.email"    label="Email"      type="email" placeholder="e.g. sarah@email.com" required />
          <AppInput v-model="newUnit.owner.phone"    label="Phone"      placeholder="e.g. +27 82 555 1234" />
          <AppInput v-model="newUnit.owner.idNumber" label="ID Number"  placeholder="e.g. 8001015009088" />
        </div>
        <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
      </div>

      <!-- Step 3: Tenant Details (non-sectional-title only) -->
      <div v-if="addUnitStep === 3" class="space-y-4">
        <!-- Toggle header (owner/vacant units) -->
        <div v-if="newUnit.occupancy !== 'tenant'" class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-foreground">Tenant Details</p>
            <p class="text-xs text-muted-foreground">Toggle on if this unit also has a tenant</p>
          </div>
          <button type="button" class="text-primary transition-colors" @click="newUnit.showTenant = !newUnit.showTenant">
            <svg v-if="newUnit.showTenant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
            </svg>
          </button>
        </div>
        <!-- Direct heading (tenant-occupied) -->
        <p v-else class="text-sm font-medium text-foreground">Tenant Details</p>

        <!-- Tenant fields -->
        <div v-if="newUnitShowTenantFields" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="newUnit.tenant.name"       label="Full Name"   placeholder="Tenant name" required />
            <AppInput v-model="newUnit.tenant.email"      label="Email"       type="email" placeholder="Email address" required />
            <AppInput v-model="newUnit.tenant.phone"      label="Phone"       placeholder="+27 ..." />
            <AppInput v-model="newUnit.tenant.rent"       label="Rent Amount" type="number" placeholder="Monthly rent" :min="0" :max="9999999999.99" />
            <AppDatePicker v-model="newUnit.tenant.leaseStart" label="Lease Start" placeholder="Select date..." />
            <AppDatePicker v-model="newUnit.tenant.leaseEnd"   label="Lease End"   placeholder="Select date..." />
          </div>
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

        <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
      </div>

      <template #footer>
        <!-- Step 1 -->
        <template v-if="addUnitStep === 1">
          <AppButton variant="outline" @click="showAddUnit = false; resetNewUnit()">Cancel</AppButton>
          <AppButton variant="primary" :disabled="!newUnit.unitNumber || !newUnit.occupancy" @click="addUnitStep = 2">
            Next
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </AppButton>
        </template>
        <!-- Step 2 — last step for sectional title -->
        <template v-else-if="addUnitStep === 2 && addUnitTotalSteps === 2">
          <AppButton variant="outline" @click="addUnitStep = 1">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            Back
          </AppButton>
          <AppButton variant="primary" :disabled="savingUnit || !newUnit.owner.name || !newUnit.owner.email" @click="saveUnit">
            {{ savingUnit ? 'Saving…' : 'Add Unit' }}
          </AppButton>
        </template>
        <!-- Step 2 — middle step for other estates -->
        <template v-else-if="addUnitStep === 2">
          <AppButton variant="outline" @click="addUnitStep = 1">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            Back
          </AppButton>
          <AppButton variant="primary" :disabled="!newUnit.owner.name || !newUnit.owner.email" @click="addUnitStep = 3">
            Next
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </AppButton>
        </template>
        <!-- Step 3 -->
        <template v-else>
          <AppButton variant="outline" @click="addUnitStep = 2">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            Back
          </AppButton>
          <AppButton variant="primary" :disabled="savingUnit" @click="saveUnit">
            {{ savingUnit ? 'Saving…' : 'Add Unit' }}
          </AppButton>
        </template>
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

        <!-- Address + Country -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-model="editEstateForm.address"
            label="Address"
            placeholder="Full street address"
          />
          <AppSelect
            v-model="editEstateForm.country"
            label="Country"
            :options="editCountryOptions"
            placeholder="Select country..."
          />
        </div>

        <!-- Financial defaults -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-if="editShowLevy"
            v-model="editEstateForm.admin_fund_amount"
            label="Admin Fund Budget"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            hint="Monthly admin fund total"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            v-if="editShowLevy"
            v-model="editEstateForm.reserve_fund_amount"
            label="Reserve Fund Budget"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            hint="Monthly reserve fund total"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            v-if="editShowLevy && editEstateForm.country === 'ZA'"
            v-model="editEstateForm.csos_levy_amount"
            label="CSOS Levy (per unit)"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            hint="Flat monthly CSOS government levy charged per unit"
            :min="0"
            :max="99999999.99"
          />
          <AppInput
            v-if="editShowRent"
            v-model="editEstateForm.default_rent_amount"
            label="Default Rent Amount"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            v-model="editEstateForm.billing_day"
            label="Billing Day"
            type="number"
            placeholder="e.g. 1"
            hint="Day of month (1–28) billing runs"
            :min="1"
            :max="28"
          />
          <AppInput
            v-model="editEstateForm.payment_terms_days"
            label="Payment Terms (days)"
            type="number"
            placeholder="e.g. 30"
            hint="Days before invoice becomes overdue"
            :min="1"
            :max="365"
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

    <!-- Billing Settings Modal -->
    <AppModal title="Billing Settings" :show="showBillingDay" size="sm" @close="showBillingDay = false">
      <div class="space-y-4 py-2">

        <div v-if="billingDayError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ billingDayError }}
        </div>

        <AppInput
          v-model="billingDayForm"
          label="Billing Day"
          type="number"
          placeholder="e.g. 25"
          hint="Day of each month invoices are generated (1–28)"
          :min="1"
          :max="28"
        />

        <AppInput
          v-model="paymentTermsForm"
          label="Payment Terms (days)"
          type="number"
          placeholder="e.g. 7"
          hint="Days from invoice date until payment is due"
          :min="1"
          :max="365"
        />
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="billingDaySaving" @click="showBillingDay = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="billingDaySaving || !billingDayForm || !paymentTermsForm" @click="saveBillingDay">
          {{ billingDaySaving ? 'Saving…' : 'Save' }}
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

    <!-- ════════════════════════════════════════════════════════════════
         Run Billing Now Modal
    ════════════════════════════════════════════════════════════════ -->
    <AppModal :show="showRunBilling" title="Run Billing Now" size="lg" @close="closeRunBilling">
      <div class="space-y-4 py-4">

        <!-- Period context -->
        <div class="rounded-lg border border-border bg-muted/40 px-4 py-3 flex items-center gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0">
            <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
          </svg>
          <div>
            <p class="text-sm font-medium text-foreground">{{ estate?.name }}</p>
            <p class="text-xs text-muted-foreground">Billing period: <span class="font-medium">{{ runBillingPeriodLabel }}</span></p>
          </div>
        </div>

        <!-- Error -->
        <p v-if="runBillingError" class="text-sm text-danger">{{ runBillingError }}</p>

        <!-- Preview loading -->
        <div v-if="runBillingLoading" class="border rounded border-border p-6 flex items-center justify-center gap-2 text-sm text-muted-foreground">
          <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          Loading billing preview…
        </div>

        <!-- Preview table -->
        <template v-else-if="runBillingPreview.length > 0">
          <div class="border rounded border-border overflow-hidden">
            <div class="bg-muted px-4 py-2 border-b border-border flex items-center justify-between">
              <p class="text-sm font-medium text-foreground">
                Billing Preview — {{ nonDuplicateRun.length }} invoice{{ nonDuplicateRun.length !== 1 ? 's' : '' }} to generate
              </p>
              <span v-if="duplicateRunCount > 0" class="text-xs text-muted-foreground">
                {{ duplicateRunCount }} duplicate{{ duplicateRunCount !== 1 ? 's' : '' }} skipped
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
                    v-for="(row, idx) in runBillingPreview"
                    :key="idx"
                    :class="['border-b border-border', row.duplicate ? 'opacity-40' : '']"
                  >
                    <td class="py-2 px-3 font-medium text-foreground">{{ row.unit_number }}</td>
                    <td class="py-2 px-3 text-foreground">{{ row.charge_type }}</td>
                    <td class="py-2 px-3 text-foreground">
                      {{ row.recipient_name || (row.billed_to_type === 'owner' ? 'Owner' : 'Tenant') }}
                      <span v-if="row.duplicate" class="ml-1 text-xs text-muted-foreground">(duplicate)</span>
                    </td>
                    <td class="py-2 px-3 text-right font-medium text-foreground whitespace-nowrap">{{ formatAmount(row.amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Total -->
          <div class="flex items-center justify-between rounded-lg border border-border bg-muted/40 px-4 py-3">
            <p class="text-sm font-medium text-foreground">Total to be invoiced</p>
            <p class="text-lg font-bold font-body text-foreground">{{ formatAmount(runBillingTotal) }}</p>
          </div>
        </template>

        <!-- No invoices to generate -->
        <div
          v-else-if="!runBillingLoading"
          class="border rounded border-border p-6 text-center text-sm text-muted-foreground"
        >
          No invoices to generate for this estate and period.
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="outline" @click="closeRunBilling">Cancel</AppButton>
          <AppButton
            variant="primary"
            :disabled="nonDuplicateRun.length === 0 || runBillingConfirm"
            @click="confirmRunBilling"
          >
            <svg v-if="runBillingConfirm" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Confirm &amp; Send Invoices
          </AppButton>
        </div>
      </div>
    </AppModal>

    <!-- Bulk Import Modal -->
    <BulkImportUnitsModal
      :show="showBulkImport"
      :estate-id="route.params.id"
      :estate-type="estate?.type"
      @close="showBulkImport = false"
      @imported="onBulkImported"
    />

    <!-- PQ Import Modal -->
    <BulkImportPqModal
      :show="showPqImportModal"
      :estate-id="route.params.id"
      @close="showPqImportModal = false"
      @imported="onPqImported"
    />

    <!-- Export Units Modal -->
    <AppExportModal
      :show="showExportModal"
      context="Units"
      @close="showExportModal = false"
      @download="handleExportDownload"
    />

    <!-- PQ Budget Explain Modal -->
    <AppModal
      :title="pqExplainView === 'breakdown' ? 'PQ Budget Breakdown' : 'About Participation Quotas'"
      :show="showPqExplainModal"
      size="sm"
      @close="showPqExplainModal = false"
    >
      <!-- ── Breakdown view (default) ─────────────────────────────────── -->
      <div v-if="pqExplainView === 'breakdown'" class="space-y-3">

        <!-- Admin Fund card -->
        <div class="rounded-lg border border-border overflow-hidden text-sm">
          <div class="px-4 py-2.5 bg-muted/40 border-b border-border">
            <span class="font-semibold text-foreground text-sm">Admin Fund</span>
          </div>
          <div class="divide-y divide-border">
            <div class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">Budget target</span>
              <span class="font-medium text-foreground">{{ countryStore.formatCurrency(pqBudgetStatus?.adminBudget ?? 0) }}</span>
            </div>
            <div v-if="pqBudgetStatus?.adminPqCount > 0" class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">PQ levied <span class="text-xs">({{ pqBudgetStatus.adminPqCount }} {{ pqBudgetStatus.adminPqCount === 1 ? 'unit' : 'units' }})</span></span>
              <span class="font-medium text-foreground">{{ countryStore.formatCurrency(pqBudgetStatus.adminPqTotal) }}</span>
            </div>
            <div v-if="pqBudgetStatus?.adminOverrideCount > 0" class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">Overrides <span class="text-xs">({{ pqBudgetStatus.adminOverrideCount }} {{ pqBudgetStatus.adminOverrideCount === 1 ? 'unit' : 'units' }})</span></span>
              <span class="font-medium text-foreground">{{ countryStore.formatCurrency(pqBudgetStatus.adminOverrideTotal) }}</span>
            </div>
            <div v-if="pqBudgetStatus?.adminFallbackCount > 0" class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">Equal-share fallback <span class="text-xs">({{ pqBudgetStatus.adminFallbackCount }} {{ pqBudgetStatus.adminFallbackCount === 1 ? 'unit' : 'units' }}, no PQ set)</span></span>
              <span class="font-medium text-amber-600">{{ countryStore.formatCurrency(pqBudgetStatus.adminFallbackTotal) }}</span>
            </div>
            <div v-if="Math.abs(pqBudgetStatus?.adminDiff ?? 0) > 0.01" class="flex items-center justify-between px-4 py-2.5"
              :class="pqBudgetStatus.adminDiff < 0 ? 'bg-red-50/50' : 'bg-amber-50/50'">
              <span class="text-xs font-medium" :class="pqBudgetStatus.adminDiff < 0 ? 'text-danger' : 'text-amber-700'">
                {{ pqBudgetStatus.adminDiff < 0 ? 'Shortfall — levies fall short of budget' : 'Surplus — levies exceed budget' }}
              </span>
              <span class="text-xs font-semibold" :class="pqBudgetStatus.adminDiff < 0 ? 'text-danger' : 'text-amber-600'">
                {{ pqBudgetStatus.adminDiff > 0 ? '+' : '' }}{{ countryStore.formatCurrency(pqBudgetStatus.adminDiff) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Reserve Fund card -->
        <div v-if="pqBudgetStatus?.reserveBudget > 0" class="rounded-lg border border-border overflow-hidden text-sm">
          <div class="px-4 py-2.5 bg-muted/40 border-b border-border">
            <span class="font-semibold text-foreground text-sm">Reserve Fund</span>
          </div>
          <div class="divide-y divide-border">
            <div class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">Budget target</span>
              <span class="font-medium text-foreground">{{ countryStore.formatCurrency(pqBudgetStatus.reserveBudget) }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-2.5">
              <span class="text-muted-foreground">Total levied via PQ</span>
              <span class="font-semibold" :class="Math.abs(pqBudgetStatus?.reserveDiff ?? 0) > 0.01 ? (pqBudgetStatus.reserveDiff < 0 ? 'text-danger' : 'text-amber-600') : 'text-foreground'">
                {{ countryStore.formatCurrency(pqBudgetStatus.totalReserve) }}
              </span>
            </div>
            <div v-if="Math.abs(pqBudgetStatus?.reserveDiff ?? 0) > 0.01" class="flex items-center justify-between px-4 py-2.5"
              :class="pqBudgetStatus.reserveDiff < 0 ? 'bg-red-50/50' : 'bg-amber-50/50'">
              <span class="text-xs font-medium" :class="pqBudgetStatus.reserveDiff < 0 ? 'text-danger' : 'text-amber-700'">
                {{ pqBudgetStatus.reserveDiff < 0 ? 'Shortfall — levies fall short of budget' : 'Surplus — levies exceed budget' }}
              </span>
              <span class="text-xs font-semibold" :class="pqBudgetStatus.reserveDiff < 0 ? 'text-danger' : 'text-amber-600'">
                {{ pqBudgetStatus.reserveDiff > 0 ? '+' : '' }}{{ countryStore.formatCurrency(pqBudgetStatus.reserveDiff) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Missing PQ notice -->
        <div v-if="pqBudgetStatus?.missingPq > 0" class="flex items-start gap-2.5 rounded-lg bg-amber-50/60 border border-amber-100 px-3.5 py-3 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
          </svg>
          <span class="text-amber-800">
            <span class="font-semibold">{{ pqBudgetStatus.missingPq }} {{ pqBudgetStatus.missingPq === 1 ? 'unit' : 'units' }}</span> {{ pqBudgetStatus.missingPq === 1 ? 'has' : 'have' }} no PQ set — using equal-share fallback instead of PQ formula.
          </span>
        </div>

        <!-- Learn more link -->
        <button type="button" class="text-xs text-primary hover:underline" @click="pqExplainView = 'learn'">
          What is a PQ and how are levies calculated? →
        </button>

      </div>

      <!-- ── Learn view ────────────────────────────────────────────────── -->
      <div v-else class="space-y-4 text-sm">

        <div class="space-y-1.5">
          <h4 class="font-semibold text-foreground">What is a PQ?</h4>
          <p class="text-muted-foreground leading-relaxed">A Participation Quota is each unit's legally registered percentage share of common property in a Sectional Title scheme, recorded on the Deeds Office sectional plan.</p>
        </div>

        <div class="space-y-2">
          <h4 class="font-semibold text-foreground">How levies are calculated</h4>
          <div class="rounded-md bg-muted/40 border border-border px-4 py-3 font-mono text-[10px] text-foreground overflow-x-auto whitespace-nowrap">
            Levy = (Unit PQ ÷ Total PQ) × Admin Fund Budget
          </div>
          <p class="text-muted-foreground text-xs">No PQ → equal-share fallback (Budget ÷ unit count). Levy Override → fixed amount overrides the formula entirely.</p>
        </div>

        <div class="space-y-1.5">
          <h4 class="font-semibold text-foreground">Why collected ≠ budget</h4>
          <ul class="space-y-1.5 text-muted-foreground text-xs">
            <li><span class="font-medium text-foreground">Levy Overrides</span> — fixed amounts that deviate from each unit's PQ-calculated share.</li>
            <li><span class="font-medium text-foreground">Missing PQs</span> — units on equal-share fallback pull the total away from the PQ-weighted sum.</li>
          </ul>
        </div>


      </div>

      <template #footer>
        <AppButton v-if="pqExplainView === 'learn'" variant="ghost" @click="pqExplainView = 'breakdown'">
          ← Back
        </AppButton>
        <AppButton variant="primary" @click="showPqExplainModal = false">Got it</AppButton>
      </template>
    </AppModal>

  </div>
</template>
