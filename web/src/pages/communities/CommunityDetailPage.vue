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
import CommunityDashboard from './CommunityDashboard.vue'
import CommunityUnitPq from './CommunityUnitPq.vue'
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
import UploadOccupantsModal from '@/components/common/UploadOccupantsModal.vue'
import UploadUnitsModal from '@/components/common/UploadUnitsModal.vue'
import SendEmailModal from '@/components/common/SendEmailModal.vue'
import { ownerNames, ownerEmails, ownerPhones } from '@/utils/units'
import { useCountryStore } from '@/stores/country'
import { useCommunityStore } from '@/stores/community'
import { ENTITY_TYPE_OPTIONS, entityTypeLabel, billingBasis, isLevyBilled, isRentBilled, isLevyOnly } from '@/utils/communityEntityType'
import AppExportModal from '@/components/common/AppExportModal.vue'
import CreateChecklistModal from '@/pages/compliance/CreateChecklistModal.vue'
import CommunityCommunicate from '@/components/communities/CommunityCommunicate.vue'
import api from '@/composables/useApi.js'
import { useExport } from '@/composables/useExport.js'
import { useBack } from '@/composables/useBack.js'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route  = useRoute()
const { goBack } = useBack('/communities')
const countryStore = useCountryStore()
const communityStore = useCommunityStore()
const { success, error: toastError } = useToast()

// "Login Successful" banner shown after explicitly entering a community.
const showLoginSuccess = ref(false)

// ── Tab navigation ────────────────────────────────────────────────────
const VALID_TABS = ['units', 'pq', 'compliance', 'communication', 'overview', 'report']
const activeTab = computed(() => {
  const q = route.query.tab
  // Default section is the Dashboard (overview) — the community sidebar rail
  // drives navigation, so entering a community lands here, like WeConnectU.
  return VALID_TABS.includes(q) ? q : 'overview'
})
function setTab(tabId) {
  router.replace({ query: { ...route.query, tab: tabId } })
}

// ── API state ─────────────────────────────────────────────────────────
const communityLoading = ref(true)
const unitsLoading  = ref(true)
const communityError   = ref(null)
const unitsError    = ref(null)
const savingUnit    = ref(false)
const saveError     = ref(null)

const community = ref(null)       // raw community object from API (data key)
const apiStats = ref(null)     // stats key from community API (occupancy counts + invoice status)
const allUnits = ref([])       // mapped unit list from units API (current page)
const chartStats = ref(null)   // charts key from units API (filter-aware aggregates)

// ── Fetch community detail + stats ───────────────────────────────────────
async function fetchCommunity() {
  communityLoading.value = true
  communityError.value   = null
  try {
    const res = await api.get(`/communities/${route.params.id}`)
    community.value   = res.data.data
    communityStore.select(route.params.id, res.data.data) // keep sidebar context name in sync
    apiStats.value = res.data.stats ?? null
    syncEditCommunityForm() // keep the General settings tab in sync with saved data
    fetchAllPqUnits()
  } catch (e) {
    communityError.value = 'Failed to load community details.'
  } finally {
    communityLoading.value = false
  }
}

// ── Fetch units (server-side filtering, sorting, pagination) ──────────
const unitsMeta = ref(null)

function mapOccupancy(type) {
  return type === 'owner_occupied' ? 'owner'
    : type === 'occupant_occupied'  ? 'occupant'
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
const OCCUPANCY_API_MAP = { owner: 'owner_occupied', occupant: 'occupant_occupied', vacant: 'vacant' }
const BALANCE_API_MAP   = { arrears: 'in_arrears', clear: 'clear' }

function buildApiParams() {
  const params = showAllUnits.value
    ? { _per_page: 1000, page: 1 }
    : { _per_page: recordsPerPage.value, page: currentPage.value }

  if (unitsSearch.value?.trim()) {
    params._search = unitsSearch.value.trim()
  }
  if (dateRangeFilter.value && dateRangeFilter.value !== 'all_time') {
    params._date_range = dateRangeFilter.value
  }
  if (columnSort.value) {
    params._sort = `${columnSort.value.field}:${columnSort.value.dir}`
  }

  return params
}

async function fetchUnits() {
  unitsLoading.value = true
  unitsError.value   = null
  try {
    const res = await api.get(`/communities/${route.params.id}/units`, {
      params: buildApiParams(),
    })
    chartStats.value = res.data.charts ?? null
    allUnits.value  = (res.data.data ?? []).map(u => ({
      id:             u.id,
      unit:           u.unit_number,
      blockNumber:    u.block_number || null,
      section:        u.section || null,
      doorNo:         u.door_number || null,
      customerCode:   u.customer_code || null,
      billingPdf:     !!u.billing_pdf,
      isDevelopment:  !!u.is_development,
      pq:             u.pq ?? null,
      effectiveReserveLevy: u.effective_reserve_levy ?? 0,
      occupancy:      mapOccupancy(u.occupancy_type),
      balance:        u.balance ?? 0,
      outstanding:    u.outstanding_amount ?? 0,
      effectiveLevy:  u.effective_levy_amount ?? 0,
      levyOverride:   u.levy_override ?? null,
      rentAmount:     u.rent_amount ?? 0,

      // Owner — raw object retained so the WeConnectU "Owner/s / E-Mail/s / Cellphone/s"
      // line-joins (primary owner + Contact 2) can be derived from one source.
      owner:          u.owner ?? null,
      ownerNames:     ownerNames(u.owner),
      ownerEmails:    ownerEmails(u.owner),
      ownerPhones:    ownerPhones(u.owner),
      ownerId:        u.owner?.id        ?? null,
      ownerName:      u.owner?.full_name ?? '—',
      ownerEmail:     u.owner?.email     ?? '',
      ownerPhone:     u.owner?.phone     ?? '',
      ownerIdNumber:  u.owner?.id_number ?? '',
      ownerLevy:      (u.effective_levy_amount ?? 0) + (u.effective_reserve_levy ?? 0),

      // Current occupant
      occupant:              u.current_occupant?.full_name ?? null,
      occupantId:            u.current_occupant?.id        ?? null,
      occupantCount:         u.total_occupants_count        ?? 0,
      occupantEmail:         u.current_occupant?.email      ?? null,
      occupantSecondaryEmail: u.current_occupant?.secondary_emails?.[0] ?? null,
      occupantPhone:         u.current_occupant?.phone      ?? null,
      occupantIdNumber:      u.current_occupant?.id_number  ?? null,
      occupantPostalAddress: u.current_occupant?.postal_address   ?? null,
      occupantCarReg:        u.current_occupant?.car_registration ?? null,
      occupantLeaseStart:    formatLeaseDate(u.current_occupant?.lease_start),
      occupantLeaseEnd:      formatLeaseDate(u.current_occupant?.lease_end),
      occupantLeaseStartRaw: u.current_occupant?.lease_start ? u.current_occupant.lease_start.substring(0, 10) : '',
      occupantLeaseEndRaw:   u.current_occupant?.lease_end   ? u.current_occupant.lease_end.substring(0, 10)   : '',
      occupantRent:          u.rent_amount ?? null,
      occupantLeaseDoc:      null,
      hasOccupant:           !!u.current_occupant?.id,
      // WeConnectU renders a greyed tenant sub-row with its own customer code
      // (e.g. "Z003-U113t"). Occupants carry no stored code, so we derive one
      // from the tenant's name + door number to mirror the visible pattern.
      tenantCode: u.current_occupant?.id
        ? `${(u.current_occupant.full_name || 'TEN').replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase().padEnd(3, 'X')}001-U${u.door_number || u.unit_number}t`
        : null,
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
      params: { community_id: route.params.id, _per_page: 5 },
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
  // Entering a community sets the workspace context → AppSidebar shows the Community rail.
  communityStore.select(route.params.id)

  // "Login Successful" banner — shown when the user explicitly logged into this
  // community (via the Communities list / dashboard), mirroring WeConnectU. The
  // flag is stripped so it does not re-show on refresh or tab changes.
  if (route.query.login === '1') {
    showLoginSuccess.value = true
    const { login, ...rest } = route.query
    router.replace({ query: rest })
    setTimeout(() => { showLoginSuccess.value = false }, 5000)
  }

  fetchCommunity()
  fetchComplianceChecklists()
  fetchUnits()
})

// ── Computed stats — community-wide totals from apiStats (unaffected by table filters) ──
const computedStats = computed(() => {
  const s = apiStats.value
  return {
    units:          s?.total_units           ?? 0,
    owners:         s?.owner_occupied_count  ?? 0,
    occupants:        s?.occupant_occupied_count ?? 0,
    vacant:         s?.vacant_count          ?? 0,
    monthlyRevenue: s?.monthly_revenue       ?? 0,
    totalBalance:   s?.total_balance         ?? 0,
  }
})

// ── AppTableToolbar config ────────────────────────────────────────────
const OCCUPANCY_OPTS = [
  { value: 'owner',  label: 'Owner Occupied'  },
  { value: 'occupant', label: 'Occupant Occupied' },
  { value: 'vacant', label: 'Vacant'          },
]

const UNITS_FILTER_FIELDS = [
  {
    key: 'occupancy',
    label: 'Occupancy',
    options: [
      { value: 'owner',  label: 'Owner Occupied'  },
      { value: 'occupant', label: 'Occupant Occupied' },
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

// ── Units view state (WeConnectU-style header) ────────────────────────
// viewMode mirrors WeConnectU's "Units" (condensed) vs "Unit Details" (full column
// set) sub-menu items, resolved from the ?view= query param the sidebar flyout sets.
const viewMode = computed(() => (route.query.view === 'units' ? 'units' : 'details'))

// Records-per-page dropdown (WeConnectU defaults to 150).
const RECORDS_PER_PAGE_OPTIONS = [
  { value: 10,  label: '10'  },
  { value: 25,  label: '25'  },
  { value: 50,  label: '50'  },
  { value: 100, label: '100' },
  { value: 150, label: '150' },
  { value: 200, label: '200' },
]
const recordsPerPage  = ref(150)
const unitsSearch     = ref('')
const dateRangeFilter = ref('all_time')

// Column sort: { field, dir }. field maps directly to the API _sort key.
const columnSort = ref(null)
const UNIT_SORT_FIELDS = {
  section:       'section',
  door_number:   'door_number',
  customer_code: 'customer_code',
  owner:         'owner_name',
  balance:       'outstanding_amount',
  unit:          'unit_number',
}
function toggleColumnSort(key) {
  const field = UNIT_SORT_FIELDS[key]
  if (!field) return
  if (columnSort.value?.field === field) {
    columnSort.value = columnSort.value.dir === 'asc' ? { field, dir: 'desc' } : null
  } else {
    columnSort.value = { field, dir: 'asc' }
  }
  currentPage.value  = 1
  showAllUnits.value = false
  fetchUnits()
}
function sortDir(key) {
  const field = UNIT_SORT_FIELDS[key]
  return columnSort.value?.field === field ? columnSort.value.dir : null
}

let searchDebounceTimer = null
function onUnitsSearch() {
  currentPage.value  = 1
  showAllUnits.value = false
  clearTimeout(searchDebounceTimer)
  unitsLoading.value = true
  searchDebounceTimer = setTimeout(fetchUnits, 350)
}

function onRecordsPerPageChange() {
  currentPage.value  = 1
  showAllUnits.value = false
  fetchUnits()
}

const currentPage  = ref(1)
const showAllUnits = ref(false)

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
  // Show the newly imported units by resetting search + filters to today.
  unitsSearch.value     = ''
  dateRangeFilter.value = 'today'
  columnSort.value      = null
  currentPage.value     = 1
  showAllUnits.value    = false
  fetchCommunity()
  fetchUnits()
}

// Refresh the units list after an occupants import (occupancy may have changed).
function onOccupantsImported() {
  showImportOccupants.value = false
  fetchCommunity()
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
    occupant: { wrapClass: 'bg-blue-50 text-blue-700 border border-blue-200',           dotClass: 'bg-blue-500',            label: 'Occupant Occupied' },
    vacant: { wrapClass: 'bg-muted text-muted-foreground border border-border',       dotClass: 'bg-muted-foreground/40', label: 'Vacant'          },
  }
  return map[type] ?? map.vacant
}

function goToUnit(unitId) {
  router.push({ name: 'unit-detail', params: { communityId: route.params.id, unitId } })
}

function goToOccupant(event, unit) {
  event.stopPropagation()
  if (unit.occupantId) {
    router.push({ name: 'occupant-detail', params: { communityId: route.params.id, unitId: unit.id, occupantId: unit.occupantId } })
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

const OCCUPANT_TEMPLATES = [
  { value: 'payment_reminder', label: 'Payment Reminder',     subject: 'Rent Payment Reminder',      body: 'Dear {name},\n\nThis is a reminder that your rent payment is due. Please ensure timely payment to avoid any late fees.\n\nKind regards,\nBold Mark Properties' },
  { value: 'welcome',          label: 'Welcome Letter',        subject: 'Welcome to Your New Home',   body: 'Dear {name},\n\nWelcome! We hope you are settling in well.\n\nKind regards,\nBold Mark Properties' },
  { value: 'maintenance',      label: 'Maintenance Notice',    subject: 'Planned Maintenance Notice', body: 'Dear {name},\n\nWe wish to notify you of planned maintenance at your unit.\n\nKind regards,\nBold Mark Properties' },
  { value: 'lease_renewal',    label: 'Lease Renewal Notice',  subject: 'Your Lease Renewal',         body: 'Dear {name},\n\nYour lease is approaching its end date. Please contact our office at your convenience.\n\nKind regards,\nBold Mark Properties' },
  { value: 'statement',        label: 'Monthly Statement',     subject: 'Your Monthly Statement',     body: 'Dear {name},\n\nPlease find your monthly statement attached.\n\nKind regards,\nBold Mark Properties' },
]

const activeTemplates = computed(() =>
  messageRecipient.value?.role === 'occupant' ? OCCUPANT_TEMPLATES : OWNER_TEMPLATES
)

// WeConnectU-style compose: the envelope icon opens the full Send E-Mail modal.
const sendEmailUnit = ref(null)
const showSendEmail = ref(false)
const sendEmailRecipients = computed(() => {
  const o = sendEmailUnit.value?.owner
  const list = []
  if (o?.email) list.push({ name: o.full_name, email: o.email })
  if (o?.contact2_email) list.push({ name: o.contact2_name || 'Contact 2', email: o.contact2_email })
  ;(o?.secondary_emails ?? []).forEach(e => list.push({ name: o.full_name, email: e }))
  if (sendEmailUnit.value?.occupantEmail) list.push({ name: sendEmailUnit.value.occupant, email: sendEmailUnit.value.occupantEmail })
  const seen = new Set()
  return list.filter(r => r.email && !seen.has(r.email.toLowerCase()) && seen.add(r.email.toLowerCase()))
})
function openSendMessageOwner(unit) {
  sendEmailUnit.value = unit
  showSendEmail.value = true
}
function openSendMessageOccupant(unit) {
  sendEmailUnit.value = unit
  showSendEmail.value = true
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
const showAddUnit         = ref(false)
const showBulkImport      = ref(false)
const showUploadUnits     = ref(false)
const showImportOccupants = ref(false)
const showExportModal     = ref(false)

// ── Export ────────────────────────────────────────────────────────────
const { downloadExport } = useExport()

function buildExportParams(format, records) {
  const params = {}

  if (unitsSearch.value?.trim()) params._search = unitsSearch.value.trim()
  if (dateRangeFilter.value && dateRangeFilter.value !== 'all_time') params._date_range = dateRangeFilter.value
  if (columnSort.value)          params._sort   = `${columnSort.value.field}:${columnSort.value.dir}`

  params._format = format
  params._limit  = records

  return params
}

async function handleExportDownload({ format, records }) {
  showExportModal.value = false
  const ext      = format === 'xlsx' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
  const filename = `units-${new Date().toISOString().slice(0, 10)}.${ext}`
  await downloadExport(`/communities/${route.params.id}/units/export`, buildExportParams(format, records), filename)
}

// WeConnectU "Export Units" — one-click xlsx download of the full list (server sets the filename).
const exportingUnits = ref(false)
async function exportUnitsXlsx() {
  exportingUnits.value = true
  try {
    const filename = `unit export-${(community.value?.name || 'units').toLowerCase()}.xlsx`
    await downloadExport(`/communities/${route.params.id}/units/export`, buildExportParams('xlsx', 'all'), filename)
  } finally {
    exportingUnits.value = false
  }
}

// WeConnectU "Download Occupants" — one-click xlsx of the occupant contact list.
const downloadingOccupants = ref(false)
async function downloadOccupants() {
  downloadingOccupants.value = true
  try {
    const filename = `units occupants export-${(community.value?.name || 'units').toLowerCase()}.xlsx`
    await downloadExport(`/communities/${route.params.id}/units/occupants/export`, { _format: 'xlsx' }, filename)
  } finally {
    downloadingOccupants.value = false
  }
}

// ── Edit Community modal ─────────────────────────────────────────────────
const showEditCommunity   = ref(false)
const editCommunitySaving = ref(false)
const editCommunityError  = ref(null)
const editCommunityForm   = ref({
  name:                '',
  code:                '',
  entity_type:         '',
  address:             '',
  country:             '',
  admin_fund_amount:   '',
  reserve_fund_amount: '',
  csos_levy_amount:    '',
  default_rent_amount: '',
  billing_day:         '',
  payment_terms_days:  '',
  registration_number:      '',
  csos_registration_number: '',
  income_tax_number:        '',
  financial_year_end_month: '',
  is_vat_registered:        false,
  vat_number:               '',
  interest_rate:            '',
  interest_exempt_threshold: '',
  ageing_type:              'calendar_month',
})

// Option lists for the General settings tab selects.
const AGEING_TYPE_OPTS = [
  { value: 'calendar_month', label: 'Calendar Month' },
  { value: 'days',           label: 'Period Aging'   },
]
const YEAR_END_MONTH_OPTS = [
  { value: '1', label: 'January' },   { value: '2', label: 'February' },
  { value: '3', label: 'March' },     { value: '4', label: 'April' },
  { value: '5', label: 'May' },       { value: '6', label: 'June' },
  { value: '7', label: 'July' },      { value: '8', label: 'August' },
  { value: '9', label: 'September' }, { value: '10', label: 'October' },
  { value: '11', label: 'November' }, { value: '12', label: 'December' },
]

// Mirror the loaded community into the edit form. Used both by the General
// settings tab (pre-filled on load) and the Edit Community modal.
function syncEditCommunityForm() {
  if (!community.value) return
  editCommunityForm.value = {
    name:                community.value.name                ?? '',
    code:                community.value.code                ?? '',
    entity_type:         community.value.entity_type         ?? '',
    address:             community.value.address             ?? '',
    country:             community.value.country             ?? countryStore.activeCountry ?? '',
    admin_fund_amount:   community.value.admin_fund_amount   ?? '',
    reserve_fund_amount: community.value.reserve_fund_amount ?? '',
    csos_levy_amount:    community.value.csos_levy_amount    ?? '',
    default_rent_amount: community.value.default_rent_amount ?? '',
    billing_day:         community.value.billing_day         ?? '',
    payment_terms_days:  community.value.payment_terms_days  ?? '',
    registration_number:      community.value.registration_number      ?? '',
    csos_registration_number: community.value.csos_registration_number ?? '',
    income_tax_number:        community.value.income_tax_number        ?? '',
    financial_year_end_month: community.value.financial_year_end_month != null ? String(community.value.financial_year_end_month) : '',
    is_vat_registered:        !!community.value.is_vat_registered,
    vat_number:               community.value.vat_number               ?? '',
    interest_rate:            community.value.interest_rate            ?? '',
    interest_exempt_threshold: community.value.interest_exempt_threshold ?? '',
    ageing_type:              community.value.ageing_type              ?? 'calendar_month',
  }
}

const editCountryOptions = Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({
  value: code,
  label: `${info.flag} ${info.name}`,
}))

const editShowLevy = computed(() => isLevyBilled(editCommunityForm.value.entity_type))
const editShowRent = computed(() => isRentBilled(editCommunityForm.value.entity_type))

const editFormCurrencySymbol = computed(() => {
  const code = editCommunityForm.value.country
  return code ? (countryStore.COUNTRY_MAP[code]?.symbol || countryStore.currencySymbol) : countryStore.currencySymbol
})

function openEditCommunity() {
  if (!community.value) return
  syncEditCommunityForm()
  editCommunityError.value = null
  showEditCommunity.value  = true
}

async function saveEditCommunity() {
  editCommunitySaving.value = true
  editCommunityError.value  = null
  try {
    const payload = {
      name:        editCommunityForm.value.name        || undefined,
      entity_type: editCommunityForm.value.entity_type || undefined,
      address:     editCommunityForm.value.address     || undefined,
    }
    if (editCommunityForm.value.country) {
      payload.country  = editCommunityForm.value.country
      payload.currency = countryStore.COUNTRY_MAP[editCommunityForm.value.country]?.currencyCode || null
    }
    if (editShowLevy.value && editCommunityForm.value.admin_fund_amount !== '') {
      payload.admin_fund_amount = parseFloat(editCommunityForm.value.admin_fund_amount) || 0
    }
    if (editShowLevy.value && editCommunityForm.value.reserve_fund_amount !== '') {
      payload.reserve_fund_amount = parseFloat(editCommunityForm.value.reserve_fund_amount) || 0
    }
    if (editShowLevy.value && editCommunityForm.value.country === 'ZA' && editCommunityForm.value.csos_levy_amount !== '') {
      payload.csos_levy_amount = parseFloat(editCommunityForm.value.csos_levy_amount) || 0
    }
    if (editShowRent.value && editCommunityForm.value.default_rent_amount !== '') {
      payload.default_rent_amount = parseFloat(editCommunityForm.value.default_rent_amount) || 0
    }
    if (editCommunityForm.value.billing_day !== '') {
      payload.billing_day = parseInt(editCommunityForm.value.billing_day) || undefined
    }
    if (editCommunityForm.value.payment_terms_days !== '') {
      payload.payment_terms_days = parseInt(editCommunityForm.value.payment_terms_days) || undefined
    }
    // Registration / statutory numbers (nullable — send empty string as null to clear).
    payload.registration_number      = editCommunityForm.value.registration_number?.trim()      || null
    payload.csos_registration_number = editCommunityForm.value.csos_registration_number?.trim() || null
    payload.income_tax_number        = editCommunityForm.value.income_tax_number?.trim()        || null

    // General settings (nullable — empty clears).
    payload.code         = editCommunityForm.value.code?.trim() || null
    payload.entity_type  = editCommunityForm.value.entity_type  || null
    payload.ageing_type  = editCommunityForm.value.ageing_type  || 'calendar_month'
    payload.financial_year_end_month = editCommunityForm.value.financial_year_end_month
      ? parseInt(editCommunityForm.value.financial_year_end_month)
      : null
    payload.is_vat_registered = !!editCommunityForm.value.is_vat_registered
    payload.vat_number        = editCommunityForm.value.vat_number?.trim() || null
    payload.interest_rate     = editCommunityForm.value.interest_rate !== '' && editCommunityForm.value.interest_rate != null
      ? parseFloat(editCommunityForm.value.interest_rate)
      : null
    payload.interest_exempt_threshold = editCommunityForm.value.interest_exempt_threshold !== '' && editCommunityForm.value.interest_exempt_threshold != null
      ? parseFloat(editCommunityForm.value.interest_exempt_threshold)
      : null

    await api.put(`/communities/${route.params.id}`, payload)
    showEditCommunity.value = false
    await fetchCommunity()
    success('Community updated successfully.')
  } catch (e) {
    editCommunityError.value = e?.response?.data?.message ?? 'Failed to update community. Please try again.'
  } finally {
    editCommunitySaving.value = false
  }
}

// ── Billing Settings modal ────────────────────────────────────────────
const showBillingDay             = ref(false)
const billingDayForm             = ref('')
const paymentTermsForm           = ref('')
const paymentReminderDaysForm    = ref('')
const billingDaySaving           = ref(false)
const billingDayError            = ref(null)

function openBillingDay() {
  billingDayForm.value          = community.value?.billing_day ?? ''
  paymentTermsForm.value        = community.value?.payment_terms_days ?? 7
  paymentReminderDaysForm.value = community.value?.payment_reminder_days ?? ''
  billingDayError.value         = null
  showBillingDay.value          = true
}

async function saveBillingDay() {
  billingDaySaving.value = true
  billingDayError.value  = null
  try {
    const day      = parseInt(billingDayForm.value)
    const terms    = parseInt(paymentTermsForm.value)
    const reminder = paymentReminderDaysForm.value !== '' ? parseInt(paymentReminderDaysForm.value) : null
    if (!day || day < 1 || day > 28) {
      billingDayError.value = 'Please enter a billing day between 1 and 28.'
      return
    }
    if (!terms || terms < 1 || terms > 365) {
      billingDayError.value = 'Please enter payment terms between 1 and 365 days.'
      return
    }
    if (reminder !== null && (reminder < 1 || reminder > 365)) {
      billingDayError.value = 'Please enter a reminder threshold between 1 and 365 days.'
      return
    }
    await api.put(`/communities/${route.params.id}`, {
      billing_day:           day,
      payment_terms_days:    terms,
      payment_reminder_days: reminder,
    })
    showBillingDay.value = false
    await fetchCommunity()
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
    const newState = !community.value.billing_paused
    await api.put(`/communities/${route.params.id}`, { billing_paused: newState })
    await fetchCommunity()
    success(newState ? 'Automatic billing paused.' : 'Automatic billing resumed.')
  } catch (e) {
    toastError(e?.response?.data?.message ?? 'Failed to update billing schedule.')
  } finally {
    billingPauseToggling.value = false
  }
}

// ── Delete Community modal ───────────────────────────────────────────────
const showDeleteCommunity   = ref(false)
const deletingCommunity     = ref(false)
const deleteCommunityError  = ref(null)
const deleteConfirmName  = ref('')
const deleteCounts       = ref({ invoices: 0, cashbook: 0, loadingCounts: false })

const deleteNameMatches = computed(() =>
  deleteConfirmName.value.trim() === (community.value?.name ?? '').trim()
)

// Items to list in the "will be deleted" section — only shown when count > 0
const deleteItems = computed(() => {
  const s    = computedStats.value
  const dc   = deleteCounts.value
  const items = []

  if (s.units > 0) {
    items.push(`All ${s.units} unit${s.units !== 1 ? 's' : ''} and their owner records`)
  }
  // Occupants: only relevant for communities that can have occupants
  const hasOccupants = isRentBilled(community.value?.entity_type)
    || s.occupants > 0
  if (hasOccupants && s.occupants > 0) {
    items.push(`All ${s.occupants} occupant record${s.occupants !== 1 ? 's' : ''} and lease history`)
  } else if (s.units > 0) {
    items.push('All occupant history and lease records')
  }
  if (dc.invoices > 0) {
    items.push(`All ${dc.invoices} invoice${dc.invoices !== 1 ? 's' : ''} and billing history`)
  }
  if (dc.cashbook > 0) {
    items.push(`All ${dc.cashbook} cashbook entr${dc.cashbook !== 1 ? 'ies' : 'y'} and payment records`)
  }

  return items
})

async function openDeleteCommunity() {
  deleteCommunityError.value = null
  deleteConfirmName.value = ''
  showDeleteCommunity.value  = true

  // Fetch invoice + cashbook counts in the background
  deleteCounts.value = { invoices: 0, cashbook: 0, loadingCounts: true }
  try {
    const [invRes, cbRes] = await Promise.all([
      api.get('/invoices',  { params: { community_id: route.params.id, _per_page: 1 } }),
      api.get('/cashbook',  { params: { community_id: route.params.id, _per_page: 1 } }),
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

async function confirmDeleteCommunity() {
  if (!deleteNameMatches.value) return
  deletingCommunity.value    = true
  deleteCommunityError.value = null
  try {
    await api.delete(`/communities/${route.params.id}`)
    showDeleteCommunity.value = false
    success('Community deleted successfully.')
    router.push('/communities')
  } catch (e) {
    deleteCommunityError.value = e?.response?.data?.message ?? 'Failed to delete community. Please try again.'
    deletingCommunity.value    = false
  }
}

const addUnitStep = ref(1)
const addUnitTotalSteps = computed(() => isLevyOnly(community.value?.entity_type) ? 2 : 3)

const ENTITY_TYPE_SELECT = [
  { value: 'individual',        label: 'Individual'        },
  { value: 'company',           label: 'Company'           },
  { value: 'trust',             label: 'Trust'             },
  { value: 'close_corporation', label: 'Close Corporation' },
  { value: 'other',             label: 'Other'             },
]

function blankNewUnit() {
  return {
    unitNumber:   '',
    blockNumber:  '',
    section:      '',
    doorNumber:   '',
    occupancy:    'owner',
    overrideLevy: false,
    levyOverride: '',
    pq:           '',
    billingPdf:   false,
    showOccupant: false,
    owner: {
      name:            '',
      email:           '',
      phone:           '',
      landline:        '',
      idNumber:        '',
      entityType:      'individual',
      contact2Name:     '',
      contact2Email:    '',
      contact2Phone:    '',
      contact2Landline: '',
    },
    occupant: {
      name:       '',
      email:      '',
      phone:      '',
      rent:       '',
      leaseStart: '',
      leaseEnd:   '',
    },
  }
}

const newUnit = ref(blankNewUnit())

const newUnitShowOccupantFields = computed(() =>
  !isLevyOnly(community.value?.entity_type) &&
  (newUnit.value.occupancy === 'occupant' || newUnit.value.showOccupant)
)

function resetNewUnit() {
  addUnitStep.value = 1
  newUnit.value = blankNewUnit()
}

async function saveUnit() {
  savingUnit.value = true
  saveError.value  = null

  const occupancyMap = { owner: 'owner_occupied', occupant: 'occupant_occupied', vacant: 'vacant' }

  const payload = {
    unit_number:    newUnit.value.unitNumber,
    occupancy_type: occupancyMap[newUnit.value.occupancy],
    billing_pdf:    !!newUnit.value.billingPdf,
    owner: {
      full_name:         newUnit.value.owner.name,
      email:             newUnit.value.owner.email,
      phone:             newUnit.value.owner.phone,
      landline:          newUnit.value.owner.landline || null,
      id_number:         newUnit.value.owner.idNumber,
      entity_type:       newUnit.value.owner.entityType || null,
      contact2_name:     newUnit.value.owner.contact2Name || null,
      contact2_email:    newUnit.value.owner.contact2Email || null,
      contact2_phone:    newUnit.value.owner.contact2Phone || null,
      contact2_landline: newUnit.value.owner.contact2Landline || null,
    },
  }

  if (newUnit.value.blockNumber !== '') payload.block_number = newUnit.value.blockNumber || null
  if (newUnit.value.section !== '')     payload.section      = newUnit.value.section || null
  if (newUnit.value.doorNumber !== '')  payload.door_number  = newUnit.value.doorNumber || null
  if (newUnit.value.pq !== '')          payload.pq           = parseFloat(newUnit.value.pq) || null
  if (editUnitShowLevy.value && newUnit.value.overrideLevy && newUnit.value.levyOverride !== '') {
    payload.levy_override = parseFloat(newUnit.value.levyOverride) || 0
  }

  if (newUnitShowOccupantFields.value) {
    if (newUnit.value.occupant.rent !== '') {
      payload.rent_amount = parseFloat(newUnit.value.occupant.rent) || 0
    }
    if (newUnit.value.occupant.name) {
      payload.occupant = {
        full_name:   newUnit.value.occupant.name,
        email:       newUnit.value.occupant.email,
        phone:       newUnit.value.occupant.phone || null,
        lease_start: newUnit.value.occupant.leaseStart || null,
        lease_end:   newUnit.value.occupant.leaseEnd   || null,
      }
    }
  }

  try {
    await api.post(`/communities/${route.params.id}/units`, payload)
    showAddUnit.value = false
    resetNewUnit()
    success('Unit created successfully.')
    // Reset filters so the newly created unit is shown prominently.
    unitsSearch.value     = ''
    dateRangeFilter.value = 'today'
    columnSort.value      = null
    currentPage.value     = 1
    await Promise.all([fetchCommunity(), fetchUnits()])
    fetchAllPqUnits()
  } catch (e) {
    saveError.value = e.response?.data?.message ?? 'Failed to create unit. Please try again.'
  } finally {
    savingUnit.value = false
  }
}

// ── Edit Unit modal ───────────────────────────────────────────────────
const showEditUnit     = ref(false)
const editingUnit      = ref(null)
const savingEditUnit   = ref(false)
const editUnitError    = ref(null)
const changingOwnership = ref(false)  // when true, saving regenerates the customer_code

// WeConnectU "Change Ownership" reuses the edit-unit modal but flags the update so the
// backend regenerates the customer_code from the new owner surname.
function openChangeOwnership(unit) {
  openEditUnit(unit)
  changingOwnership.value = true
}

// ── Add / Edit Tenant (WeConnectU person+ icon) ───────────────────────
const showTenantModal   = ref(false)
const tenantUnit        = ref(null)
const tenantOccupantId  = ref(null)   // set when editing an existing occupant
const tenantSaving      = ref(false)
const tenantError       = ref(null)
const tenantForm        = ref({
  name: '', email: '', emailAlt: '', billingPdf: false,
  contactNumber: '', contactNumberAlt: '', idNumber: '',
  postalAddress: '', carRegistration: '',
  leaseStart: '', leaseEnd: '', leaseAmount: '',
})

function resetTenantForm() {
  tenantForm.value = {
    name: '', email: '', emailAlt: '', billingPdf: false,
    contactNumber: '', contactNumberAlt: '', idNumber: '',
    postalAddress: '', carRegistration: '',
    leaseStart: '', leaseEnd: '', leaseAmount: '',
  }
}

function openAddTenant(unit) {
  tenantUnit.value       = unit
  tenantOccupantId.value = null
  tenantError.value      = null
  resetTenantForm()
  showTenantModal.value  = true
}

function openEditTenant(unit) {
  tenantUnit.value       = unit
  tenantOccupantId.value = unit.occupantId
  tenantError.value      = null
  tenantForm.value = {
    name:             unit.occupant               ?? '',
    email:            unit.occupantEmail          ?? '',
    emailAlt:         unit.occupantSecondaryEmail ?? '',
    billingPdf:       false,
    contactNumber:    unit.occupantPhone          ?? '',
    contactNumberAlt: '',
    idNumber:         unit.occupantIdNumber       ?? '',
    postalAddress:    unit.occupantPostalAddress  ?? '',
    carRegistration:  unit.occupantCarReg         ?? '',
    leaseStart:       unit.occupantLeaseStartRaw  ?? '',
    leaseEnd:         unit.occupantLeaseEndRaw     ?? '',
    leaseAmount:      unit.occupantRent != null ? String(unit.occupantRent) : '',
  }
  showTenantModal.value = true
}

async function saveTenant() {
  if (tenantSaving.value || !tenantUnit.value) return
  if (!tenantForm.value.name.trim() || !tenantForm.value.email.trim()) {
    tenantError.value = 'Name and email are required.'
    return
  }
  tenantSaving.value = true
  tenantError.value  = null
  try {
    const payload = {
      full_name:        tenantForm.value.name.trim(),
      email:            tenantForm.value.email.trim(),
      secondary_emails: tenantForm.value.emailAlt.trim() ? [tenantForm.value.emailAlt.trim()] : [],
      phone:            tenantForm.value.contactNumber.trim() || null,
      id_number:        tenantForm.value.idNumber.trim() || null,
      postal_address:   tenantForm.value.postalAddress.trim() || null,
      car_registration: tenantForm.value.carRegistration.trim() || null,
      lease_start:      tenantForm.value.leaseStart || null,
      lease_end:        tenantForm.value.leaseEnd || null,
      rent_amount:      tenantForm.value.leaseAmount !== '' ? parseFloat(tenantForm.value.leaseAmount) : null,
    }
    const base = `/communities/${route.params.id}/units/${tenantUnit.value.id}/occupants`
    if (tenantOccupantId.value) {
      await api.put(`${base}/${tenantOccupantId.value}`, payload)
      success('Tenant updated.')
    } else {
      await api.post(base, payload)
      success('Tenant added.')
    }
    showTenantModal.value = false
    await fetchUnits()
  } catch (e) {
    tenantError.value = e?.response?.data?.message ?? 'Failed to save tenant. Please try again.'
  } finally {
    tenantSaving.value = false
  }
}

// ── Vacate Tenant (WeConnectU red person-x icon) ──────────────────────
const showVacateModal = ref(false)
const vacateUnit      = ref(null)
const vacateDate      = ref('')
const vacateSaving    = ref(false)
const vacateError     = ref(null)

function openVacateTenant(unit) {
  vacateUnit.value  = unit
  vacateDate.value  = ''
  vacateError.value = null
  showVacateModal.value = true
}

async function confirmVacateTenant() {
  if (vacateSaving.value || !vacateUnit.value?.occupantId) return
  vacateSaving.value = true
  vacateError.value  = null
  try {
    await api.post(
      `/communities/${route.params.id}/units/${vacateUnit.value.id}/occupants/${vacateUnit.value.occupantId}/move-out`,
      { move_out_date: vacateDate.value || null },
    )
    success('Tenant vacated.')
    showVacateModal.value = false
    await fetchUnits()
  } catch (e) {
    vacateError.value = e?.response?.data?.message ?? 'Failed to vacate tenant. Please try again.'
  } finally {
    vacateSaving.value = false
  }
}

// ── Confirm Transfer (WeConnectU double-arrow icon) ───────────────────
const showTransferModal = ref(false)
const transferUnit      = ref(null)

function openTransfer(unit) {
  transferUnit.value      = unit
  showTransferModal.value = true
}

function confirmTransfer() {
  const unit = transferUnit.value
  showTransferModal.value = false
  if (unit) openChangeOwnership(unit)
}

// ── Development-unit toggle (WeConnectU wrench) ────────────────────────
const devConfirmUnit = ref(null)   // unit pending a development-status change
const togglingDev    = ref(false)

function openDevConfirm(unit) {
  devConfirmUnit.value = unit
}

async function confirmToggleDevelopment() {
  if (!devConfirmUnit.value || togglingDev.value) return
  togglingDev.value = true
  try {
    await api.post(`/communities/${route.params.id}/units/${devConfirmUnit.value.id}/toggle-development`)
    devConfirmUnit.value = null
    await fetchUnits()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to change development status.'
  } finally {
    togglingDev.value = false
  }
}

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
  occupantName:    '',
  occupantEmail:   '',
  occupantPhone:   '',
  rentAmount:    '',
  leaseStart:    '',
  leaseEnd:      '',
})

const editUnitShowLevy = computed(() => isLevyBilled(community.value?.entity_type))

function openEditUnit(unit) {
  editingUnit.value   = unit
  editUnitError.value = null
  changingOwnership.value = false
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
    showOccupant: unit.occupancy === 'occupant',
    occupant: {
      name:       unit.occupant              ?? '',
      email:      unit.occupantEmail         ?? '',
      phone:      unit.occupantPhone         ?? '',
      rent:       unit.occupantRent          ? String(unit.occupantRent) : '',
      leaseStart: unit.occupantLeaseStartRaw ?? '',
      leaseEnd:   unit.occupantLeaseEndRaw   ?? '',
    },
  }
  showEditUnit.value = true
}

const editUnitOccupancyOptions = computed(() => {
  if (isLevyOnly(community.value?.entity_type)) {
    return [
      { value: 'owner',  label: 'Owner'  },
      { value: 'vacant', label: 'Vacant' },
    ]
  }
  return [
    { value: 'owner',  label: 'Owner'  },
    { value: 'occupant', label: 'Occupant' },
    { value: 'vacant', label: 'Vacant' },
  ]
})

const editUnitShowOccupantFields = computed(() =>
  !isLevyOnly(community.value?.entity_type) &&
  (editUnitForm.value.occupancy === 'occupant' || editUnitForm.value.showOccupant)
)

async function saveEditUnit() {
  savingEditUnit.value = true
  editUnitError.value  = null

  const occupancyMap = { owner: 'owner_occupied', occupant: 'occupant_occupied', vacant: 'vacant' }

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

  if (editUnitShowOccupantFields.value) {
    if (editUnitForm.value.occupant.rent !== '') {
      payload.rent_amount = parseFloat(editUnitForm.value.occupant.rent) || 0
    }
    if (editUnitForm.value.occupant.name) {
      payload.occupant = {
        full_name:   editUnitForm.value.occupant.name,
        email:       editUnitForm.value.occupant.email,
        phone:       editUnitForm.value.occupant.phone || null,
        lease_start: editUnitForm.value.occupant.leaseStart || null,
        lease_end:   editUnitForm.value.occupant.leaseEnd   || null,
      }
    }
  }

  if (changingOwnership.value) payload.ownership_change = true

  try {
    await api.put(`/communities/${route.params.id}/units/${editingUnit.value.id}`, payload)
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
    await api.delete(`/communities/${route.params.id}/units/${deletingUnitTarget.value.id}`)
    showDeleteUnit.value = false
    success('Unit deleted successfully.')
    await fetchUnits()
    await fetchCommunity()
    fetchAllPqUnits()
  } catch (e) {
    deleteUnitError.value = e?.response?.data?.message ?? 'Failed to delete unit. Please try again.'
  } finally {
    deletingUnitLoading.value = false
  }
}

// ── Multi-select & Bulk Delete ────────────────────────────────────────
const selectedUnitIds      = ref(new Set())
const selectAllAcrossPages = ref(false)   // true when user has opted into "select all X units"
const showBulkDelete       = ref(false)
const bulkDeleteConfirm    = ref('')
const bulkDeleteLoading    = ref(false)
const bulkDeleteError      = ref(null)

const selectedCount = computed(() =>
  selectAllAcrossPages.value ? totalUnitsInQuery.value : selectedUnitIds.value.size
)

const allVisibleSelected = computed(() =>
  allUnits.value.length > 0 &&
  allUnits.value.every(u => selectedUnitIds.value.has(u.id))
)

const someVisibleSelected = computed(() =>
  selectedUnitIds.value.size > 0 && !allVisibleSelected.value && !selectAllAcrossPages.value
)

// Show the "select all X units" banner only when the full current page is ticked
// and there are more units on other pages
const showSelectAllBanner = computed(() =>
  allVisibleSelected.value &&
  !selectAllAcrossPages.value &&
  totalUnitsInQuery.value > allUnits.value.length
)

const bulkDeleteConfirmMatches = computed(() =>
  bulkDeleteConfirm.value.trim() === (community.value?.name ?? '').trim()
)

function toggleUnitSelection(id, event) {
  event.stopPropagation()
  selectAllAcrossPages.value = false
  const next = new Set(selectedUnitIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedUnitIds.value = next
}

function toggleSelectAll(event) {
  event.stopPropagation()
  selectAllAcrossPages.value = false
  if (allVisibleSelected.value) {
    selectedUnitIds.value = new Set()
  } else {
    selectedUnitIds.value = new Set(allUnits.value.map(u => u.id))
  }
}

function activateSelectAllAcrossPages() {
  selectAllAcrossPages.value = true
  // Keep current page IDs selected so the checkboxes look right
  selectedUnitIds.value = new Set(allUnits.value.map(u => u.id))
}

function clearSelection() {
  selectedUnitIds.value      = new Set()
  selectAllAcrossPages.value = false
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
    let unitIds = [...selectedUnitIds.value]

    // If the user selected across all pages, fetch every unit ID first
    if (selectAllAcrossPages.value) {
      const PER_PAGE = 200
      const first    = await api.get(`/communities/${route.params.id}/units`, { params: { _per_page: PER_PAGE, page: 1 } })
      const lastPage = first.data.meta?.last_page ?? 1
      let   raw      = first.data.data ?? []
      if (lastPage > 1) {
        const pages = await Promise.all(
          Array.from({ length: lastPage - 1 }, (_, i) =>
            api.get(`/communities/${route.params.id}/units`, { params: { _per_page: PER_PAGE, page: i + 2 } })
          )
        )
        for (const r of pages) raw = raw.concat(r.data.data ?? [])
      }
      unitIds = raw.map(u => u.id)
    }

    await api.delete(`/communities/${route.params.id}/units`, { data: { unit_ids: unitIds } })
    showBulkDelete.value       = false
    selectAllAcrossPages.value = false
    success('Selected units deleted successfully.')
    selectedUnitIds.value = new Set()
    await fetchUnits()
    await fetchCommunity()
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
  const day = community.value?.billing_day
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
const showRunBilling            = ref(false)
const runBillingPreview         = ref([])
const runBillingLoading         = ref(false)
const runBillingConfirm         = ref(false)
const runBillingConfirmStep     = ref(false)  // true = show final confirmation screen
const runBillingError           = ref('')
const runBillingPeriod          = ref('')     // YYYY-MM, user-selectable

// Offer current month + 3 previous months
const runBillingPeriodOptions = computed(() => {
  const options = []
  const now = new Date()
  for (let i = 0; i < 4; i++) {
    const d     = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const label = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
    options.push({ value, label: i === 0 ? `${label} (current)` : label })
  }
  return options
})

const runBillingPeriodLabel = computed(() => {
  const opt = runBillingPeriodOptions.value.find(o => o.value === runBillingPeriod.value)
  return opt?.label ?? runBillingPeriod.value
})

const nonDuplicateRun = computed(() => runBillingPreview.value.filter(r => !r.duplicate))
const duplicateRunCount = computed(() => runBillingPreview.value.filter(r => r.duplicate).length)
const runBillingTotal = computed(() => nonDuplicateRun.value.reduce((sum, r) => sum + Number(r.amount), 0))

async function loadRunBillingPreview() {
  runBillingLoading.value = true
  runBillingError.value   = ''
  runBillingPreview.value = []
  runBillingConfirmStep.value = false
  try {
    const { data } = await api.post('/invoices/run-billing', {
      community_id:      route.params.id,
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

async function openRunBilling() {
  const now = new Date()
  runBillingPeriod.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  showRunBilling.value   = true
  await loadRunBillingPreview()
}

function promptRunBillingConfirm() {
  runBillingConfirmStep.value = true
  runBillingError.value       = ''
}

async function confirmRunBilling() {
  runBillingConfirm.value = true
  runBillingError.value   = ''
  try {
    await api.post('/invoices/run-billing', {
      community_id:      route.params.id,
      billing_period: runBillingPeriod.value,
      dry_run:        false,
    })
    closeRunBilling()
    fetchCommunity()
    fetchUnits()
  } catch (e) {
    runBillingError.value = e.response?.data?.message || 'Failed to run billing'
  } finally {
    runBillingConfirm.value = false
  }
}

function closeRunBilling() {
  showRunBilling.value        = false
  runBillingPreview.value     = []
  runBillingError.value       = ''
  runBillingConfirmStep.value = false
}

// ── Charts ────────────────────────────────────────────────────────────

// Helpers — occupancy totals from filter-aware chartStats
const chartOccupancy = computed(() => chartStats.value?.occupancy ?? { owner_occupied: 0, occupant_occupied: 0, vacant: 0 })
const chartTotal     = computed(() => chartOccupancy.value.owner_occupied + chartOccupancy.value.occupant_occupied + chartOccupancy.value.vacant)

// Occupancy Breakdown — Doughnut
const occupancyChartData = computed(() => ({
  labels: ['Owners', 'Occupants', 'Vacant'],
  datasets: [{
    data: [chartOccupancy.value.owner_occupied, chartOccupancy.value.occupant_occupied, chartOccupancy.value.vacant],
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
    { count: occ.occupant_occupied, label: 'Occupants' },
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
      router.push({ name: 'unit-detail', params: { communityId: route.params.id, unitId: unit.unit_id } })
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

// ── Occupant Insights — only rendered for non-sectional-title communities ──
const communityHasOccupants = computed(() => !isLevyOnly(community.value?.entity_type))

// Occupant Lease Expiry — Vertical Bar
const leaseExpiry = computed(() => chartStats.value?.occupant_lease_expiry ?? {
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
        label: (ctx) => ` ${ctx.parsed.y} occupant${ctx.parsed.y !== 1 ? 's' : ''}`,
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

// Top Occupant Arrears — Horizontal Bar (top 10, reversed so longest at bottom)
const topOccupantArrears = computed(() => [...(chartStats.value?.top_occupant_arrears ?? [])].reverse())
const hasTopOccupantArrearsData = computed(() => topOccupantArrears.value.length > 0)

const occupantArrearsChartData = computed(() => ({
  labels: topOccupantArrears.value.map(t => {
    const surname = (t.occupant_name || '—').split(' ').pop()
    return `${t.unit_number} – ${surname}`
  }),
  datasets: [{
    data: topOccupantArrears.value.map(t => t.outstanding),
    backgroundColor: '#D89B4B',
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const occupantArrearsChartOptions = {
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
const isPqCommunity = computed(() => isLevyBilled(community.value?.entity_type))

const showPqImportModal  = ref(false)
const showPqExplainModal = ref(false)
const showClearPqModal   = ref(false)
const clearingPq         = ref(false)
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

  const adminBudget   = parseFloat(community.value?.admin_fund_amount   ?? 0)
  const reserveBudget = parseFloat(community.value?.reserve_fund_amount ?? 0)
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
      return api.put(`/communities/${route.params.id}/units/${d.id}`, payload)
    }))
    cancelPqEditMode()
    await Promise.all([fetchAllPqUnits(), fetchCommunity()])
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
  if (!isPqCommunity.value) return
  pqAllUnitsLoading.value = true
  try {
    const PER_PAGE = 200
    const first    = await api.get(`/communities/${route.params.id}/units`, { params: { _per_page: PER_PAGE, page: 1 } })
    const lastPage = first.data.meta?.last_page ?? 1
    let   raw      = first.data.data ?? []

    if (lastPage > 1) {
      const pages = await Promise.all(
        Array.from({ length: lastPage - 1 }, (_, i) =>
          api.get(`/communities/${route.params.id}/units`, { params: { _per_page: PER_PAGE, page: i + 2 } })
        )
      )
      for (const r of pages) raw = raw.concat(r.data.data ?? [])
    }

    pqAllUnits.value = raw.map(u => ({
      id:                   u.id,
      unit:                 u.unit_number,
      section:              u.section || null,
      pq:                   u.pq ?? null,
      effectiveLevy:        u.effective_levy_amount ?? 0,
      effectiveReserveLevy: u.effective_reserve_levy ?? 0,
      levyOverride:         u.levy_override ?? null,
      ownerName:            u.owner?.full_name ?? '—',
      ownerId:              u.owner?.id ?? null,
    }))
  } catch (e) {
    console.error('Failed to load PQ units', e)
  } finally {
    pqAllUnitsLoading.value = false
  }
}

const pqBoardRef = ref(null)

async function onPqImported(result) {
  await Promise.all([fetchUnits(), fetchCommunity()])
  await fetchAllPqUnits()
  pqBoardRef.value?.load?.()   // refresh the WeConnectU-style PQ board
  if (result?.updated) {
    success(result.message ?? `${result.updated} unit${result.updated !== 1 ? 's' : ''} updated.`)
  } else {
    success('PQs imported successfully.')
  }
}

async function clearAllPqs() {
  clearingPq.value = true
  try {
    const res = await api.delete(`/communities/${route.params.id}/units/pq`)
    showClearPqModal.value = false
    await Promise.all([fetchCommunity(), fetchAllPqUnits()])
    success(res.data.message ?? 'PQs cleared.')
  } catch (e) {
    toastError(e?.response?.data?.message ?? 'Failed to clear PQs.')
  } finally {
    clearingPq.value = false
  }
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
  if (!community.value || !pqRows.value.length) return null
  const totalAdmin    = pqRows.value.reduce((s, r) => s + r.effectiveLevy, 0)
  const totalReserve  = pqRows.value.reduce((s, r) => s + r.effectiveReserveLevy, 0)
  const adminBudget   = parseFloat(community.value.admin_fund_amount   ?? 0)
  const reserveBudget = parseFloat(community.value.reserve_fund_amount ?? 0)
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

const communityTabs = computed(() => {
  const tabs = [
    { id: 'units', label: 'Units', badge: computedStats.value.units > 0 ? computedStats.value.units : null },
    { id: 'compliance', label: 'Compliance', badge: complianceChecklists.value.length > 0 ? complianceChecklists.value.length : null },
    { id: 'communication', label: 'Communication', badge: null },
    { id: 'overview', label: 'Overview', badge: null },
  ]
  if (isPqCommunity.value) {
    tabs.splice(1, 0, { id: 'pq', label: 'Participation Quotas', badge: null })
  }
  return tabs
})
</script>

<template>
  <div class="pb-8">

    <!-- ── Login Successful banner (WeConnectU parity) — collapses gracefully ── -->
    <div
      class="grid transition-all duration-300 ease-in-out"
      :class="showLoginSuccess ? 'grid-rows-[1fr] opacity-100 mb-6' : 'grid-rows-[0fr] opacity-0 mb-0 pointer-events-none'"
    >
      <div class="overflow-hidden min-h-0">
        <AppAlert variant="success" title="Success" dismissible @dismiss="showLoginSuccess = false">
          Login Successful
        </AppAlert>
      </div>
    </div>

    <div class="space-y-6">

    <!-- ── Error state ─────────────────────────────────────────────── -->
    <div v-if="communityError" class="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive">
      {{ communityError }}
    </div>


    <!-- ── Community Dashboard (exact WeConnectU parity) — Dashboard only ── -->
    <div v-show="activeTab === 'overview'">
      <CommunityDashboard :community-id="route.params.id" />
    </div>

    <!-- ── Legacy summary stats / charts — replaced by CommunityDashboard (kept dormant) ── -->
    <div v-if="false" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

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
            <p class="text-2xl font-bold font-body text-foreground">{{ computedStats.occupants }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">Occupants</p>
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

    <!-- ── Billing Schedule Strip — Dashboard only ───────────────────── -->
    <div v-if="false" class="rounded-lg border bg-card shadow-sm overflow-hidden">
      <div class="flex items-center gap-0">

        <!-- Left section: schedule info with accent left border (muted when paused) -->
        <div :class="['flex items-center gap-3.5 flex-1 min-w-0 px-5 py-4 border-l-[3px]', community.billing_paused ? 'border-l-muted-foreground/30' : 'border-l-accent']">
          <!-- Calendar icon -->
          <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', community.billing_paused ? 'bg-muted' : 'bg-accent/10']">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="community.billing_paused ? 'text-muted-foreground' : 'text-accent'">
              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
            </svg>
          </div>

          <!-- Schedule text -->
          <div class="min-w-0">
            <template v-if="billingSchedule">
              <p class="text-sm font-medium text-foreground">
                Billing runs on the <span :class="community.billing_paused ? 'text-muted-foreground font-semibold' : 'text-accent font-semibold'">{{ billingSchedule.ordinal }}</span> of each month
                <span v-if="community.billing_paused" class="ml-1.5 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-muted text-muted-foreground border border-border">Paused</span>
              </p>
              <p class="text-xs text-muted-foreground mt-0.5">
                <template v-if="community.billing_paused">Automatic billing is paused — run manually or resume to re-enable.</template>
                <template v-else>Invoices auto-generated for
                  <template v-if="billingBasis(community?.entity_type) === 'levy'">owners</template>
                  <template v-else-if="billingBasis(community?.entity_type) === 'rent'">occupants</template>
                  <template v-else>owners &amp; occupants</template>
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
          <AppPoptip position="top" max-width="240px">
            <template #trigger>
              <AppButton variant="primary" size="sm" @click="openRunBilling">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>
                </svg>
                Run Now
              </AppButton>
            </template>
            <p class="px-3 py-2 text-xs">Trigger billing for all active units now</p>
          </AppPoptip>
        </div>

        <!-- Countdown section (hidden when paused) -->
        <template v-if="billingSchedule && computedStats.units > 0 && !community.billing_paused">
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
        <template v-else-if="billingSchedule && computedStats.units === 0 && !community.billing_paused">
          <div class="shrink-0 px-5 py-4 border-l border-border">
            <p class="text-xs text-muted-foreground">Add units to see the</p>
            <p class="text-xs text-muted-foreground">billing countdown</p>
          </div>
        </template>

        <!-- Pause / Resume toggle -->
        <div v-if="billingSchedule && computedStats.units > 0" class="shrink-0 flex items-center ml-4">
          <!-- When paused: single "Resume" pill button -->
          <button
            v-if="community.billing_paused"
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
          <AppPoptip v-else position="top" max-width="240px">
            <template #trigger>
              <button
                @click="toggleBillingPaused"
                :disabled="billingPauseToggling"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                </svg>
              </button>
            </template>
            <p class="px-3 py-2 text-xs whitespace-nowrap">Pause automatic billing schedule</p>
          </AppPoptip>
        </div>

        <!-- Edit button -->
        <AppPoptip position="top" max-width="240px">
          <template #trigger>
            <button
              @click="openBillingDay"
              class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors ml-2 mr-4"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
              </svg>
            </button>
          </template>
          <p class="px-3 py-2 text-xs whitespace-nowrap">Edit billing day</p>
        </AppPoptip>

      </div>
    </div>

    <!-- Section content is selected by the community sidebar rail (no in-page tabs). -->

    <!-- Community Report — not built yet (Coming Soon placeholder) -->
    <div v-show="activeTab === 'report'" class="rounded-lg border bg-card shadow-sm">
      <div class="flex flex-col items-center justify-center py-20 px-8 text-center">
        <div class="w-14 h-14 rounded-full bg-muted flex items-center justify-center mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>
          </svg>
        </div>
        <p class="text-lg font-semibold text-foreground">Community Report</p>
        <p class="text-sm text-muted-foreground/70 mt-1">Coming soon.</p>
      </div>
    </div>

    <!-- Page heading (outside the card, WeConnectU-style) -->
    <h2 v-show="activeTab === 'units'" class="font-body font-semibold text-2xl text-foreground">Unit Details</h2>

    <!-- ── Units Table Card ─────────────────────────────────────────── -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'units'" class="rounded-lg border bg-card shadow-sm">

      <!-- ── Empty state: no units exist at all ────────────────────── -->
      <div
        v-if="!unitsLoading && !communityLoading && !unitsError && computedStats.units === 0"
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
          <template v-if="billingBasis(community?.entity_type) === 'levy'">
            Add units to start managing owners, levy billing, and payments.
          </template>
          <template v-else-if="billingBasis(community?.entity_type) === 'rent'">
            Add units to start managing occupants, rent collection, and payments.
          </template>
          <template v-else>
            Add units to start managing owners, occupants, billing, and payments.
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

      <!-- Toolbar: WeConnectU button row — solid navy actions, green Export on the right -->
      <div class="px-6 pt-5 pb-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-3.5 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity"
            @click="showUploadUnits = true"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Bulk edit Units
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-3.5 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity"
            @click="showImportOccupants = true"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Import New Occupants
          </button>
          <button
            type="button"
            :disabled="downloadingOccupants"
            class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-3.5 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity disabled:opacity-60"
            @click="downloadOccupants"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="3" y2="15"/>
            </svg>
            Download Occupants
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-accent px-3.5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity"
            @click="showAddUnit = true"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Unit
          </button>
        </div>
        <button
          type="button"
          :disabled="exportingUnits"
          class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors disabled:opacity-60"
          @click="exportUnitsXlsx"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
            <path d="m9 15 2 2 4-4"/>
          </svg>
          {{ exportingUnits ? 'Exporting…' : 'Export Units' }}
        </button>
      </div>

      <!-- Records-per-page + Search (WeConnectU chrome) -->
      <div class="px-6 pb-4 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
          <div class="w-36">
            <AppSelect
              :model-value="recordsPerPage"
              :options="RECORDS_PER_PAGE_OPTIONS"
              @update:model-value="v => { recordsPerPage = v; onRecordsPerPageChange() }"
            />
          </div>
          <span>records per page</span>
        </div>
        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          Search:
          <input
            v-model="unitsSearch"
            type="text"
            class="h-9 w-56 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
            @input="onUnitsSearch"
          />
        </label>
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
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-primary">
              {{ selectedCount }} unit{{ selectedCount === 1 ? '' : 's' }} selected
            </span>
            <span v-if="showSelectAllBanner" class="text-xs text-muted-foreground">
              All {{ allUnits.length }} on this page are selected.
              <button class="underline font-medium text-primary hover:text-foreground transition-colors" @click="activateSelectAllAcrossPages">
                Select all {{ totalUnitsInQuery }} units in this community
              </button>
            </span>
            <span v-else-if="selectAllAcrossPages" class="text-xs text-muted-foreground">
              All {{ totalUnitsInQuery }} units in this community are selected.
            </span>
          </div>
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

      <!-- Table — WeConnectU bordered grid -->
      <div class="px-6 pb-4 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-[#eef1f5]">
              <th class="border border-border px-4 py-3 text-left">
                <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleColumnSort('section')">
                  Section
                  <span class="inline-flex flex-col text-[8px] leading-[7px]">
                    <span :class="sortDir('section') === 'asc' ? 'text-accent' : 'text-muted-foreground/40'">▲</span>
                    <span :class="sortDir('section') === 'desc' ? 'text-accent' : 'text-muted-foreground/40'">▼</span>
                  </span>
                </button>
              </th>
              <th class="border border-border px-4 py-3 text-left">
                <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleColumnSort('door_number')">
                  Door No
                  <span class="inline-flex flex-col text-[8px] leading-[7px]">
                    <span :class="sortDir('door_number') === 'asc' ? 'text-accent' : 'text-muted-foreground/40'">▲</span>
                    <span :class="sortDir('door_number') === 'desc' ? 'text-accent' : 'text-muted-foreground/40'">▼</span>
                  </span>
                </button>
              </th>
              <th class="border border-border px-4 py-3 text-left">
                <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleColumnSort('customer_code')">
                  Customer Code
                  <span class="inline-flex flex-col text-[8px] leading-[7px]">
                    <span :class="sortDir('customer_code') === 'asc' ? 'text-accent' : 'text-muted-foreground/40'">▲</span>
                    <span :class="sortDir('customer_code') === 'desc' ? 'text-accent' : 'text-muted-foreground/40'">▼</span>
                  </span>
                </button>
              </th>
              <th class="border border-border px-4 py-3 text-left">
                <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleColumnSort('owner')">
                  Owner/s
                  <span class="inline-flex flex-col text-[8px] leading-[7px]">
                    <span :class="sortDir('owner') === 'asc' ? 'text-accent' : 'text-muted-foreground/40'">▲</span>
                    <span :class="sortDir('owner') === 'desc' ? 'text-accent' : 'text-muted-foreground/40'">▼</span>
                  </span>
                </button>
              </th>
              <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">E-Mail/s</th>
              <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Cellphone/s</th>
              <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark w-32">Action</th>
            </tr>
          </thead>
          <tbody>

            <!-- Loading skeleton rows -->
            <template v-if="unitsLoading">
              <tr v-for="n in 8" :key="`sk-${n}`">
                <td class="border border-border px-4 py-3"><div class="h-4 w-10 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-10 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-24 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-32 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-40 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-24 bg-muted rounded animate-pulse" /></td>
                <td class="border border-border px-4 py-3"><div class="h-4 w-16 bg-muted rounded animate-pulse" /></td>
              </tr>
            </template>

            <!-- Real unit rows -->
            <template v-else>
              <template v-for="unit in allUnits" :key="unit.id">
              <!--
                Tenant sub-row (WeConnectU parity): occupied units show the tenant as a
                separate, greyed row ABOVE the owner row. Its cells are plain muted text
                (no links) since the tenant isn't the billable party-of-record — but the
                row itself navigates to the same unit detail page (where the tenant lives
                under "Occupants & Tenants"). Edit Tenant + Vacate Tenant stay inline.
              -->
              <tr
                v-if="unit.hasOccupant"
                class="bg-muted/40 text-muted-foreground/70 select-none cursor-pointer hover:bg-muted/60 transition-colors"
                @click="goToUnit(unit.id)"
              >
                <td class="border border-border px-4 py-3 align-middle">{{ unit.section || unit.unit }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ unit.doorNo || unit.unit || '—' }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ unit.tenantCode || '—' }}</td>
                <td class="border border-border px-4 py-3 align-middle">
                  <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                      <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    {{ unit.occupant || '—' }}
                  </span>
                </td>
                <td class="border border-border px-4 py-3 align-middle">{{ unit.occupantEmail || '—' }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ unit.occupantPhone || '—' }}</td>
                <td class="border border-border px-4 py-3 w-32 align-middle">
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center text-navy-dark hover:text-accent transition-colors"
                      title="Edit tenant"
                      @click.stop="openEditTenant(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center justify-center text-destructive hover:text-destructive/80 transition-colors"
                      title="Vacate tenant"
                      @click.stop="openVacateTenant(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" x2="22" y1="8" y2="13"/><line x1="22" x2="17" y1="8" y2="13"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              <tr
                class="hover:bg-muted/40 cursor-pointer transition-colors"
                @click="goToUnit(unit.id)"
              >
                <!-- Section (with development-unit wrench toggle) -->
                <td class="border border-border px-4 py-3 text-foreground align-middle">
                  <span class="inline-flex items-center gap-2">
                    {{ unit.section || unit.unit }}
                    <button
                      type="button"
                      :title="unit.isDevelopment ? 'Unset as development unit' : 'Set as development unit'"
                      :class="['transition-colors', unit.isDevelopment ? 'text-accent' : 'text-muted-foreground/50 hover:text-accent']"
                      @click.stop="openDevConfirm(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                      </svg>
                    </button>
                  </span>
                </td>

                <!-- Door No -->
                <td class="border border-border px-4 py-3 text-foreground align-middle">{{ unit.doorNo || unit.unit || '—' }}</td>

                <!-- Customer Code -->
                <td class="border border-border px-4 py-3 align-middle">
                  <button class="text-[#2f6fb0] hover:underline font-medium" @click.stop="goToUnit(unit.id)">{{ unit.customerCode || '—' }}</button>
                </td>

                <!-- Owner/s -->
                <td class="border border-border px-4 py-3 align-middle">
                  <template v-if="unit.ownerNames.length">
                    <button
                      v-for="(name, i) in unit.ownerNames"
                      :key="i"
                      class="text-[#2f6fb0] hover:underline transition-colors text-left block"
                      @click="goToOwner($event, unit)"
                    >{{ name }}</button>
                  </template>
                  <span v-else class="text-muted-foreground">—</span>
                </td>

                <!-- E-Mail/s -->
                <td class="border border-border px-4 py-3 align-middle">
                  <template v-if="unit.ownerEmails.length">
                    <a
                      v-for="(email, i) in unit.ownerEmails"
                      :key="i"
                      :href="`mailto:${email}`"
                      class="text-[#2f6fb0] hover:underline block"
                      @click.stop
                    >{{ email }}</a>
                  </template>
                  <span v-else class="text-muted-foreground">—</span>
                </td>

                <!-- Cellphone/s -->
                <td class="border border-border px-4 py-3 text-foreground align-middle">
                  <template v-if="unit.ownerPhones.length">
                    <span v-for="(phone, i) in unit.ownerPhones" :key="i" class="block">{{ phone }}</span>
                  </template>
                  <span v-else class="text-muted-foreground">—</span>
                </td>

                <!-- Action: edit unit / add tenant / email / transfer -->
                <td class="border border-border px-4 py-3 w-32 align-middle" @click.stop>
                  <div class="flex items-center gap-2 text-navy-dark">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center hover:text-accent transition-colors"
                      title="Edit unit"
                      @click.stop="goToUnit(unit.id)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                      </svg>
                    </button>
                    <!-- Add tenant (hidden once a tenant occupies the unit) -->
                    <button
                      v-if="!unit.hasOccupant"
                      type="button"
                      class="inline-flex items-center justify-center hover:text-accent transition-colors"
                      title="Add tenant"
                      @click.stop="openAddTenant(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center justify-center hover:text-accent transition-colors disabled:opacity-30 disabled:pointer-events-none"
                      title="Send email"
                      :disabled="!unit.ownerEmails.length"
                      @click.stop="openSendMessageOwner(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center justify-center hover:text-accent transition-colors"
                      title="Transfer unit"
                      @click.stop="openTransfer(unit)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              </template>

              <!-- Empty state (filters returned nothing) -->
              <tr v-if="!unitsLoading && allUnits.length === 0">
                <td colspan="7" class="border border-border py-12 text-center text-sm text-muted-foreground">
                  No units match your search.
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!unitsLoading && totalUnitsInQuery > recordsPerPage" class="flex items-center justify-between px-6 py-3 border-t border-border">
        <p class="text-xs text-muted-foreground">
          <template v-if="showAllUnits">Showing all {{ totalUnitsInQuery }} units</template>
          <template v-else>Showing {{ (currentPage - 1) * recordsPerPage + 1 }}–{{ Math.min(currentPage * recordsPerPage, totalUnitsInQuery) }} of {{ totalUnitsInQuery }} units</template>
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
    <!-- Unit PQ's Section — WeConnectU-style (see CommunityUnitPq.vue)  -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-show="activeTab === 'pq'">
      <CommunityUnitPq ref="pqBoardRef" :community-id="route.params.id" @upload="showPqImportModal = true" />
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
          <p class="text-xs text-muted-foreground mt-0.5">Annual compliance checklists for this community</p>
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
          <p class="text-sm text-muted-foreground mb-3">No compliance checklists for this community yet</p>
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
      :community-id="route.params.id"
      :community-name="community?.name || ''"
      @close="showCreateChecklist = false"
      @created="onChecklistCreated"
    />

    <!-- ── Overview Tab: Charts ─────────────────────────────────────── -->
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition-none">
    <div v-if="false" class="space-y-6">

    <!-- ── Charts: Occupancy Breakdown + Invoice Status ─────────────── -->
    <!-- Skeleton while primary data is still loading -->
    <div v-if="unitsLoading || communityLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
          <div v-if="communityLoading" class="h-64 flex items-end gap-4 justify-center pb-6">
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
    <div v-if="unitsLoading || communityLoading" class="rounded-lg border bg-card shadow-sm">
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

    <!-- ── Occupant Insights (hidden for sectional title communities) ────── -->
    <template v-if="communityHasOccupants">

      <!-- Skeleton -->
      <div v-if="unitsLoading || communityLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
            <p class="text-xs text-muted-foreground mt-0.5">Active occupant leases by expiry window</p>
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

        <!-- Top Occupant Arrears -->
        <div class="rounded-lg border bg-card shadow-sm">
          <div class="px-6 pt-5 pb-2">
            <h3 class="font-body font-semibold text-base text-foreground">Top Occupant Arrears</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Occupants with highest outstanding rent balances</p>
          </div>
          <div class="px-6 pb-6">
            <div v-if="!hasTopOccupantArrearsData" class="flex flex-col items-center justify-center py-4">
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
              <p class="text-xs text-muted-foreground mt-2">No occupant arrears — all rent is up to date</p>
            </div>
            <div v-else style="height: 200px; position: relative;">
              <Bar :data="occupantArrearsChartData" :options="occupantArrearsChartOptions" />
            </div>
          </div>
        </div>

      </div>
    </template>

    </div><!-- end overview tab -->
    </Transition>

    <!-- ── Communication Tab ─────────────────────────────────────────── -->
    <div v-show="activeTab === 'communication'">
      <CommunityCommunicate
        v-if="activeTab === 'communication'"
        :community-id="route.params.id"
        :community-name="community?.name || ''"
      />
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
          <AppInput v-model="editUnitForm.pq" label="Participation Quota (PQ)" type="number" placeholder="e.g. 8.53" hint="Proportional share — levies are distributed relative to the sum of all unit PQ values." :min="0" />
        </div>

        <!-- Rent Amount (shown when occupant-occupied — not applicable for levy-only communities) -->
        <AppInput v-if="editUnitForm.occupancy === 'occupant' && !isLevyOnly(community?.entity_type)" v-model="editUnitForm.occupant.rent" label="Rent Amount" type="number" :min="0" :max="9999999999.99" />

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

        <!-- Occupant Details — hidden entirely for levy-only communities -->
        <div v-if="!isLevyOnly(community?.entity_type)" class="border-t border-border pt-4">
          <!-- Toggle header (owner/vacant units) -->
          <div v-if="editUnitForm.occupancy !== 'occupant'" class="flex items-center justify-between mb-3">
            <div>
              <p class="text-sm font-medium text-foreground">Occupant Details</p>
              <p class="text-xs text-muted-foreground">Toggle on if this unit also has a occupant</p>
            </div>
            <button type="button" class="text-primary transition-colors" @click="editUnitForm.showOccupant = !editUnitForm.showOccupant">
              <!-- Toggle ON -->
              <svg v-if="editUnitForm.showOccupant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
              </svg>
              <!-- Toggle OFF -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
                <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
              </svg>
            </button>
          </div>
          <!-- Direct heading (occupant-occupied units) -->
          <p v-else class="text-sm font-medium text-foreground mb-3">Occupant Details</p>

          <!-- Occupant fields -->
          <div v-if="editUnitShowOccupantFields" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="editUnitForm.occupant.name"       label="Full Name"   size="sm" placeholder="Occupant name" required />
              <AppInput v-model="editUnitForm.occupant.email"      label="Email"       size="sm" type="email" placeholder="Email address" required />
              <AppInput v-model="editUnitForm.occupant.phone"      label="Phone"       size="sm" placeholder="+27 ..." />
              <AppInput v-model="editUnitForm.occupant.rent"       label="Rent Amount" size="sm" type="number" placeholder="Monthly rent" required :min="0" :max="9999999999.99" />
              <AppDatePicker v-model="editUnitForm.occupant.leaseStart" label="Lease Start" placeholder="Select date..." required />
              <AppDatePicker v-model="editUnitForm.occupant.leaseEnd" label="Lease End" placeholder="Select date..." />
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

    <!-- Add / Edit Tenant Modal (WeConnectU person+ icon) -->
    <AppModal
      :show="showTenantModal"
      :title="tenantOccupantId ? 'Edit Tenant' : 'Add Tenant'"
      size="md"
      @close="showTenantModal = false"
    >
      <div class="space-y-4">
        <div v-if="tenantError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ tenantError }}
        </div>

        <AppInput v-model="tenantForm.name"  label="Name"  required />
        <AppInput v-model="tenantForm.email" label="Email" type="email" required />
        <AppInput v-model="tenantForm.emailAlt" label="Email Alt" type="email" />

        <label class="flex items-center gap-2 text-sm text-foreground">
          <input type="checkbox" v-model="tenantForm.billingPdf" class="rounded border-border text-primary focus:ring-primary" />
          Billing PDF
        </label>

        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="tenantForm.contactNumber"    label="Contact Number" />
          <AppInput v-model="tenantForm.contactNumberAlt" label="Contact Number Alt" />
          <AppInput v-model="tenantForm.idNumber"         label="ID Number" />
          <AppInput v-model="tenantForm.carRegistration"  label="Car Registration #" />
        </div>

        <AppInput v-model="tenantForm.postalAddress" label="Postal Address" />

        <div class="grid grid-cols-2 gap-4">
          <AppDatePicker v-model="tenantForm.leaseStart" label="Lease Start" placeholder="Select date..." />
          <AppDatePicker v-model="tenantForm.leaseEnd"   label="Lease End"   placeholder="Select date..." />
        </div>
        <AppInput v-model="tenantForm.leaseAmount" label="Lease Amount" type="number" :min="0" :max="9999999999.99" />
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="tenantSaving" @click="showTenantModal = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="tenantSaving" @click="saveTenant">
          {{ tenantSaving ? 'Saving…' : 'Save' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Vacate Tenant Modal (WeConnectU red person-x icon) -->
    <AppModal title="Vacate Tenant" :show="showVacateModal" @close="showVacateModal = false">
      <div class="space-y-4">
        <div v-if="vacateError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ vacateError }}
        </div>

        <div class="rounded-lg bg-success/10 border border-success/20 p-4 text-sm text-foreground space-y-3">
          <p><span class="font-semibold">Please note:</span> All items currently billed to this tenant will be billed to the owner until a new tenant is added or the billing entry is removed.</p>
          <p class="font-semibold">Are you sure you want to un-link this tenant from the unit?</p>
        </div>

        <AppDatePicker v-model="vacateDate" label="Vacated Date" placeholder="Select date..." />
      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="vacateSaving" @click="showVacateModal = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="vacateSaving" @click="confirmVacateTenant">
          {{ vacateSaving ? 'Vacating…' : 'Yes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Confirm Transfer Modal (WeConnectU double-arrow icon) -->
    <AppModal title="Confirm Transfer" :show="showTransferModal" @close="showTransferModal = false">
      <div class="space-y-4">
        <p class="text-sm text-foreground">The selected unit is currently owned by:</p>

        <div class="rounded-lg bg-muted/40 border border-border p-4 space-y-1">
          <p class="font-semibold text-foreground">{{ transferUnit?.ownerName || '—' }}</p>
          <p class="text-sm text-muted-foreground">{{ transferUnit?.ownerEmail || '—' }}</p>
          <p class="text-sm text-muted-foreground">{{ transferUnit?.ownerPhone || '—' }}</p>
        </div>

        <p class="text-sm font-semibold text-foreground">Would you like to initiate the transfer process?</p>
      </div>

      <template #footer>
        <AppButton variant="outline" @click="showTransferModal = false">No</AppButton>
        <AppButton variant="primary" @click="confirmTransfer">Yes</AppButton>
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
            <p class="text-xs text-muted-foreground mt-1">This will remove the unit, owner record, all occupant records, and associated history.</p>
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
            <p class="text-xs text-muted-foreground mt-1">All owner records, occupant records, invoices, and associated history will be removed.</p>
          </div>
        </div>

        <div class="border-t border-border pt-4">
          <label class="block text-sm font-medium text-foreground mb-1.5">
            Type the community name <span class="font-semibold text-destructive">{{ community?.name }}</span> to confirm
          </label>
          <input
            v-model="bulkDeleteConfirm"
            type="text"
            :placeholder="community?.name"
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
            <span :class="['text-xs font-medium', addUnitStep === 3 ? 'text-foreground' : 'text-muted-foreground']">Occupant Details</span>
          </div>
        </template>
      </div>

      <!-- Step 1: Unit Details -->
      <div v-if="addUnitStep === 1" class="space-y-4">
        <div v-if="saveError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">{{ saveError }}</div>

        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="newUnit.blockNumber" label="Block Number" placeholder="e.g. B1" />
          <AppInput v-model="newUnit.unitNumber" label="Unit Number" placeholder="e.g. A01" required />
          <AppInput v-model="newUnit.section" label="Section Number" placeholder="e.g. A" />
          <AppInput v-model="newUnit.doorNumber" label="Door Number" placeholder="e.g. 1" />
        </div>
        <AppSelect v-model="newUnit.occupancy" label="Occupancy Type" :options="editUnitOccupancyOptions" required />

        <!-- Billing PDF toggle -->
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-foreground">Billing PDF</p>
            <p class="text-xs text-muted-foreground">Attach a PDF invoice when billing this unit</p>
          </div>
          <button type="button" @click="newUnit.billingPdf = !newUnit.billingPdf">
            <svg v-if="newUnit.billingPdf" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
            </svg>
          </button>
        </div>

        <template v-if="editUnitShowLevy">
          <AppInput v-model="newUnit.pq" label="Participation Quota (PQ)" type="number" placeholder="e.g. 8.53" hint="Proportional share — levies are distributed relative to the sum of all unit PQ values." :min="0" />

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

        <AppInput v-if="newUnit.occupancy === 'occupant' && !isLevyOnly(community?.entity_type)" v-model="newUnit.occupant.rent" label="Rent Amount" type="number" :min="0" :max="9999999999.99" />
      </div>

      <!-- Step 2: Owner Details -->
      <div v-if="addUnitStep === 2" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="newUnit.owner.name"     label="Name"       placeholder="e.g. Sarah van der Merwe" required />
          <AppInput v-model="newUnit.owner.email"    label="Email"      type="email" placeholder="e.g. sarah@email.com" required />
          <AppInput v-model="newUnit.owner.phone"    label="Cellphone"  placeholder="e.g. +27 82 555 1234" />
          <AppInput v-model="newUnit.owner.landline" label="Landline"   placeholder="e.g. +27 11 555 1234" />
          <AppSelect v-model="newUnit.owner.entityType" label="Entity Type" :options="ENTITY_TYPE_SELECT" />
          <AppInput v-model="newUnit.owner.idNumber" label="ID Number or Reg Number" placeholder="e.g. 8001015009088" />
        </div>

        <div class="pt-2 border-t border-border">
          <p class="text-sm font-medium text-foreground mb-3">Contact 2 <span class="font-normal text-muted-foreground">(optional)</span></p>
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="newUnit.owner.contact2Name"     label="Contact 2 Name"      placeholder="Additional contact" />
            <AppInput v-model="newUnit.owner.contact2Email"    label="Contact 2 Email"     type="email" placeholder="Email address" />
            <AppInput v-model="newUnit.owner.contact2Phone"    label="Contact 2 Cellphone" placeholder="+27 ..." />
            <AppInput v-model="newUnit.owner.contact2Landline" label="Contact 2 Landline"  placeholder="+27 ..." />
          </div>
        </div>
        <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
      </div>

      <!-- Step 3: Occupant Details (non-sectional-title only) -->
      <div v-if="addUnitStep === 3" class="space-y-4">
        <!-- Toggle header (owner/vacant units) -->
        <div v-if="newUnit.occupancy !== 'occupant'" class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-foreground">Occupant Details</p>
            <p class="text-xs text-muted-foreground">Toggle on if this unit also has a occupant</p>
          </div>
          <button type="button" class="text-primary transition-colors" @click="newUnit.showOccupant = !newUnit.showOccupant">
            <svg v-if="newUnit.showOccupant" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-accent">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9 text-muted-foreground">
              <rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/>
            </svg>
          </button>
        </div>
        <!-- Direct heading (occupant-occupied) -->
        <p v-else class="text-sm font-medium text-foreground">Occupant Details</p>

        <!-- Occupant fields -->
        <div v-if="newUnitShowOccupantFields" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <AppInput v-model="newUnit.occupant.name"       label="Full Name"   placeholder="Occupant name" required />
            <AppInput v-model="newUnit.occupant.email"      label="Email"       type="email" placeholder="Email address" required />
            <AppInput v-model="newUnit.occupant.phone"      label="Phone"       placeholder="+27 ..." />
            <AppInput v-model="newUnit.occupant.rent"       label="Rent Amount" type="number" placeholder="Monthly rent" :min="0" :max="9999999999.99" />
            <AppDatePicker v-model="newUnit.occupant.leaseStart" label="Lease Start" placeholder="Select date..." />
            <AppDatePicker v-model="newUnit.occupant.leaseEnd"   label="Lease End"   placeholder="Select date..." />
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
        <!-- Step 2 — middle step for other communities -->
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

    <!-- Edit Community Modal -->
    <AppModal title="Edit Community" :show="showEditCommunity" size="lg" @close="showEditCommunity = false">
      <div class="space-y-5">

        <div v-if="editCommunityError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ editCommunityError }}
        </div>

        <!-- Name + Type -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-model="editCommunityForm.name"
            label="Community Name"
            placeholder="e.g. Crystal Mews Body Corporate"
            required
          />
          <AppSelect v-model="editCommunityForm.entity_type" label="Entity Type" :options="ENTITY_TYPE_OPTIONS" required />
        </div>

        <!-- Address + Country -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-model="editCommunityForm.address"
            label="Address"
            placeholder="Full street address"
          />
          <AppSelect
            v-model="editCommunityForm.country"
            label="Country"
            :options="editCountryOptions"
            placeholder="Select country..."
          />
        </div>

        <!-- Financial defaults -->
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            v-if="editShowLevy"
            v-model="editCommunityForm.admin_fund_amount"
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
            v-model="editCommunityForm.reserve_fund_amount"
            label="Reserve Fund Budget"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            hint="Monthly reserve fund total"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            v-if="editShowLevy && editCommunityForm.country === 'ZA'"
            v-model="editCommunityForm.csos_levy_amount"
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
            v-model="editCommunityForm.default_rent_amount"
            label="Default Rent Amount"
            type="number"
            placeholder="0.00"
            :prefix="editFormCurrencySymbol"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            v-model="editCommunityForm.billing_day"
            label="Billing Day"
            type="number"
            placeholder="e.g. 1"
            hint="Day of month (1–28) billing runs"
            :min="1"
            :max="28"
          />
          <AppInput
            v-model="editCommunityForm.payment_terms_days"
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
        <AppButton variant="outline" :disabled="editCommunitySaving" @click="showEditCommunity = false">Cancel</AppButton>
        <AppButton variant="primary" :disabled="editCommunitySaving" @click="saveEditCommunity">
          {{ editCommunitySaving ? 'Saving…' : 'Save Changes' }}
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
          label="Days Until Due"
          type="number"
          placeholder="e.g. 30"
          hint="Days from invoice date until payment is due"
          :min="1"
          :max="365"
        />

        <AppInput
          v-model="paymentReminderDaysForm"
          label="Payment Reminder (days after due date)"
          type="number"
          placeholder="e.g. 3 — leave blank to disable"
          hint="Send a reminder this many days after the due date (leave blank to disable)"
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

    <!-- Delete Community Modal -->
    <AppModal title="Delete Community" :show="showDeleteCommunity" @close="showDeleteCommunity = false">
      <div class="space-y-4">

        <div v-if="deleteCommunityError" class="rounded border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
          {{ deleteCommunityError }}
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
              Permanently deleting <span class="text-foreground">{{ community?.name }}</span> — this cannot be undone.
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
            This community has no data yet. Deleting it will remove the community record permanently.
          </p>
        </div>

        <!-- Name confirmation -->
        <div class="border-t border-border pt-4">
          <label class="block text-sm font-medium text-foreground mb-1.5">
            Type <span class="font-semibold text-destructive">{{ community?.name }}</span> to confirm
          </label>
          <input
            v-model="deleteConfirmName"
            type="text"
            :placeholder="community?.name"
            class="w-full h-10 px-3 rounded border text-sm transition-colors outline-none
                   border-border bg-background text-foreground placeholder:text-muted-foreground
                   focus:border-destructive focus:ring-1 focus:ring-destructive/30"
          />
        </div>

      </div>

      <template #footer>
        <AppButton variant="outline" :disabled="deletingCommunity" @click="showDeleteCommunity = false">Cancel</AppButton>
        <AppButton
          variant="danger"
          :disabled="deletingCommunity || !deleteNameMatches"
          @click="confirmDeleteCommunity"
        >
          {{ deletingCommunity ? 'Deleting…' : 'Delete Community' }}
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

        <!-- ── STEP 1: Preview ──────────────────────────────────── -->
        <template v-if="!runBillingConfirmStep">

          <!-- Period selector -->
          <div class="rounded-lg border border-border bg-muted/40 px-4 py-3 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0">
              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground">{{ community?.name }}</p>
              <p class="text-xs text-muted-foreground mt-0.5">Billing period</p>
            </div>
            <AppSelect
              v-model="runBillingPeriod"
              :options="runBillingPeriodOptions"
              :disabled="runBillingLoading"
              class="w-52"
              @change="loadRunBillingPreview"
            />
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
                  <thead class="sticky top-0 bg-muted">
                    <tr class="border-b border-border">
                      <th class="text-left py-2 px-3 text-xs font-medium text-muted-foreground">Unit</th>
                      <th class="text-left py-2 px-3 text-xs font-medium text-muted-foreground">Ledger</th>
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
                      <td class="py-2 px-3 text-foreground">{{ row.ledger }}</td>
                      <td class="py-2 px-3 text-foreground">
                        {{ row.recipient_name || (row.billed_to_type === 'owner' ? 'Owner' : 'Occupant') }}
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
            No invoices to generate for this community and period.
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <AppButton variant="outline" @click="closeRunBilling">Cancel</AppButton>
            <AppButton
              variant="primary"
              :disabled="nonDuplicateRun.length === 0 || runBillingLoading"
              @click="promptRunBillingConfirm"
            >
              Confirm
            </AppButton>
          </div>

        </template>

        <!-- ── STEP 2: Final confirmation ───────────────────────── -->
        <template v-else>

          <!-- Warning icon + heading -->
          <div class="flex items-start gap-4 p-4 rounded-lg border border-amber-200 bg-amber-50">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-amber-600">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-amber-900">You are about to send {{ nonDuplicateRun.length }} invoice email{{ nonDuplicateRun.length !== 1 ? 's' : '' }}</p>
              <p class="mt-1 text-xs text-amber-700 leading-relaxed">Invoices will be generated and emailed to {{ nonDuplicateRun.length }} recipients. This cannot be undone.</p>
            </div>
          </div>

          <!-- Summary card -->
          <div class="border rounded-lg border-border overflow-hidden">
            <div class="bg-muted px-4 py-2 border-b border-border">
              <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Send Summary</p>
            </div>
            <div class="divide-y divide-border">
              <div class="flex items-center justify-between px-4 py-3">
                <p class="text-sm text-muted-foreground">Community</p>
                <p class="text-sm font-medium text-foreground">{{ community?.name }}</p>
              </div>
              <div class="flex items-center justify-between px-4 py-3">
                <p class="text-sm text-muted-foreground">Billing Period</p>
                <p class="text-sm font-medium text-foreground">{{ runBillingPeriodLabel }}</p>
              </div>
              <div class="flex items-center justify-between px-4 py-3">
                <p class="text-sm text-muted-foreground">Invoices to Generate</p>
                <p class="text-sm font-semibold text-foreground">{{ nonDuplicateRun.length }}</p>
              </div>
              <div v-if="duplicateRunCount > 0" class="flex items-center justify-between px-4 py-3">
                <p class="text-sm text-muted-foreground">Duplicates Skipped</p>
                <p class="text-sm text-muted-foreground">{{ duplicateRunCount }}</p>
              </div>
              <div class="flex items-center justify-between px-4 py-3 bg-muted/40">
                <p class="text-sm font-semibold text-foreground">Total Amount</p>
                <p class="text-base font-bold text-foreground">{{ formatAmount(runBillingTotal) }}</p>
              </div>
            </div>
          </div>

          <!-- Error (if the actual submit fails) -->
          <p v-if="runBillingError" class="text-sm text-danger">{{ runBillingError }}</p>

          <div class="flex justify-end gap-2 pt-2">
            <AppButton variant="outline" :disabled="runBillingConfirm" @click="runBillingConfirmStep = false">
              Go Back
            </AppButton>
            <AppButton variant="primary" :disabled="runBillingConfirm" @click="confirmRunBilling">
              <svg v-if="runBillingConfirm" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Send Invoices
            </AppButton>
          </div>

        </template>

      </div>
    </AppModal>

    <!-- Bulk Import Modal -->
    <BulkImportUnitsModal
      :show="showBulkImport"
      :community-id="route.params.id"
      :community-type="community?.entity_type"
      @close="showBulkImport = false"
      @imported="onBulkImported"
    />

    <!-- Bulk edit Units (Upload Units) Modal -->
    <UploadUnitsModal
      :show="showUploadUnits"
      :community-id="route.params.id"
      @close="showUploadUnits = false"
      @imported="onBulkImported"
    />

    <!-- Import New Occupants Modal -->
    <UploadOccupantsModal
      :show="showImportOccupants"
      :community-id="route.params.id"
      @close="showImportOccupants = false"
      @imported="onOccupantsImported"
    />

    <!-- Send E-Mail Modal (units-list envelope) -->
    <SendEmailModal
      v-if="sendEmailUnit"
      :show="showSendEmail"
      :community-id="route.params.id"
      :unit-id="sendEmailUnit.id"
      :recipients="sendEmailRecipients"
      :default-recipient="sendEmailUnit.ownerEmail || ''"
      @close="showSendEmail = false"
      @sent="showSendEmail = false"
    />

    <!-- Development-status Confirm dialog (WeConnectU parity) -->
    <Teleport to="body">
      <div v-if="devConfirmUnit" class="fixed inset-0 z-[60] flex items-start justify-center pt-28 px-4">
        <div class="absolute inset-0 bg-black/40" @click="devConfirmUnit = null" />
        <div class="relative w-full max-w-xl rounded-lg bg-white shadow-xl overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3 bg-[#eaf3fb] border-b border-[#d6e4f2]">
            <h3 class="text-base font-bold text-[#2f6fb0]" style="font-family: 'DM Sans', sans-serif">Confirm</h3>
            <button type="button" class="text-[#9bb3c9] hover:text-[#2f6fb0] transition-colors" @click="devConfirmUnit = null">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          <!-- Body -->
          <div class="px-5 py-5 text-sm text-[#3f4657]">
            Are you sure you want to change the development status of this unit?
          </div>
          <!-- Actions -->
          <div class="px-5 pb-5 flex items-center gap-3">
            <button
              type="button"
              :disabled="togglingDev"
              class="rounded-md bg-navy-dark px-5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity disabled:opacity-60"
              @click="confirmToggleDevelopment"
            >{{ togglingDev ? 'Saving…' : 'Yes' }}</button>
            <button
              type="button"
              class="rounded-md border border-border bg-white px-5 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
              @click="devConfirmUnit = null"
            >No</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- PQ Import Modal -->
    <BulkImportPqModal
      :show="showPqImportModal"
      :community-id="route.params.id"
      @close="showPqImportModal = false"
      @imported="onPqImported"
    />

    <!-- Clear All PQs Confirmation Modal -->
    <AppModal title="Clear All PQs" :show="showClearPqModal" size="sm" @close="showClearPqModal = false">
      <p class="text-sm text-muted-foreground">
        This will remove the PQ value and any levy overrides from all
        <strong class="text-foreground">{{ pqAllUnits.length }} unit{{ pqAllUnits.length !== 1 ? 's' : '' }}</strong>
        in this community. Units will fall back to equal-share levy distribution.
      </p>
      <p class="text-sm font-medium text-[#F75A68] mt-3">This action cannot be undone.</p>
      <template #footer>
        <AppButton variant="outline" @click="showClearPqModal = false" :disabled="clearingPq">Cancel</AppButton>
        <AppButton
          class="bg-[#F75A68] text-white hover:bg-[#e04455] border-[#F75A68]"
          :disabled="clearingPq"
          @click="clearAllPqs"
        >
          <svg v-if="clearingPq" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ clearingPq ? 'Clearing…' : 'Yes, Clear All PQs' }}
        </AppButton>
      </template>
    </AppModal>

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

    </div><!-- /space-y-6 -->
  </div>
</template>
