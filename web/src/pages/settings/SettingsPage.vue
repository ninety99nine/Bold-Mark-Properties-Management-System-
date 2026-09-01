<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDropdown from '@/components/common/AppDropdown.vue'
import AppDropdownItem from '@/components/common/AppDropdownItem.vue'
import { useAuthStore } from '@/stores/auth'
import CommunicationSettings from '@/components/settings/CommunicationSettings.vue'
import api from '@/composables/useApi'

const route        = useRoute()
const router       = useRouter()
const authStore    = useAuthStore()
const { success: toastSuccess, error: toastError } = useToast()

const activeSection = computed(() => route.params.tab || 'profile')

const NAV = [
  {
    group: 'Account',
    items: [
      { id: 'profile',  label: 'Profile',  icon: 'user'    },
      { id: 'password', label: 'Password', icon: 'key'     },
      { id: 'security',    label: 'Security',    icon: 'shield' },
      { id: 'login-audit', label: 'Login Audit', icon: 'clock'  },
    ],
  },
  {
    group: 'General Settings',
    items: [
      { id: 'company',  label: 'Company Details', icon: 'building', route: '/settings/company' },
      { id: 'users',    label: 'Users',    icon: 'users', route: '/users' },
      { id: 'branding', label: 'Branding', icon: 'palette'  },
      { id: 'ledgers',  label: 'Default Ledgers', icon: 'tag' },
      { id: 'communication', label: 'Communication', icon: 'mail' },
    ],
  },
  {
    group: 'System',
    items: [
      { id: 'danger-zone', label: 'Danger Zone', icon: 'triangle-alert', danger: true },
    ],
  },
]

const SECTION_META = {
  'profile':      { title: 'Profile',       subtitle: 'Update your personal information and contact details' },
  'password':     { title: 'Password',      subtitle: 'Change your account password' },
  'security':     { title: 'Security',      subtitle: 'Manage two-factor authentication and active sessions' },
  'general':      { title: 'Company',       subtitle: 'Configure company name, contact info, and regional settings' },
  'company':      { title: 'Company Details', subtitle: 'Company information, banking details and logos used across the app and communications' },
  'branding':     { title: 'Branding',      subtitle: 'Customise your company colours and visual identity' },
  'ledgers': { title: 'Default Ledgers',  subtitle: 'Define the billing categories used across your communities' },
  'communication': { title: 'Communication', subtitle: 'Default email header/footer and the message templates used across communities' },
  'login-audit':  { title: 'Login Audit',   subtitle: 'Audit log of all login attempts across the platform' },
  'danger-zone':  { title: 'Danger Zone',   subtitle: 'Irreversible data operations — proceed with extreme caution' },
}

// ─── Account — Profile ────────────────────────────────────────────────────────
const profile        = ref({ fullName: '', email: '', role: '', phone: '' })
const profileLoading = ref(false)

async function updateProfile() {
  if (profileLoading.value) return
  profileLoading.value = true
  try {
    const { data } = await api.put(`/users/${authStore.user.id}`, {
      name:  profile.value.fullName,
      email: profile.value.email,
      phone: profile.value.phone || null,
    })
    authStore.user = data.data ?? data
    toastSuccess('Profile updated successfully.')
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Something went wrong. Please try again.')
  } finally {
    profileLoading.value = false
  }
}

// ─── Account — Password ───────────────────────────────────────────────────────
const password        = ref({ current: '', newPass: '', confirm: '' })
const passwordLoading = ref(false)
const passwordError   = ref('')
const passwordSuccess = ref('')

async function changePassword() {
  if (passwordLoading.value) return
  passwordError.value   = ''
  passwordSuccess.value = ''

  if (!password.value.current) {
    passwordError.value = 'Please enter your current password.'
    return
  }
  if (password.value.newPass.length < 8) {
    passwordError.value = 'New password must be at least 8 characters.'
    return
  }
  if (password.value.newPass !== password.value.confirm) {
    passwordError.value = 'New passwords do not match.'
    return
  }

  passwordLoading.value = true
  try {
    await api.put('/users/me/password', {
      current_password:      password.value.current,
      password:              password.value.newPass,
      password_confirmation: password.value.confirm,
    })
    password.value        = { current: '', newPass: '', confirm: '' }
    passwordSuccess.value = 'Password changed. A confirmation email has been sent to your inbox.'
    setTimeout(() => { passwordSuccess.value = '' }, 6000)
  } catch (err) {
    const errors = err?.response?.data?.errors
    if (errors?.current_password) {
      passwordError.value = errors.current_password[0]
    } else {
      passwordError.value = err?.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    passwordLoading.value = false
  }
}

// ─── Account — Security: 2FA ─────────────────────────────────────────────────
// 2FA is mandatory and can never be disabled (BUG-003). The setup flow remains
// only to re-key an authenticator; there is no disable path.
const twoFaEnabled        = computed(() => !!authStore.user?.two_factor_enabled)
const twoFaSetupLoading   = ref(false)
const twoFaConfirmLoading = ref(false)
const twoFaQrUri          = ref('')
const twoFaSecret         = ref('')
const twoFaCode           = ref('')
const twoFaError          = ref('')
const twoFaSuccess        = ref('')
const twoFaStep           = ref('idle') // idle | setup

async function startTwoFaSetup() {
  twoFaError.value        = ''
  twoFaSetupLoading.value = true
  try {
    const { data } = await api.post('/auth/2fa/setup')
    twoFaQrUri.value  = data.data.qr_uri
    twoFaSecret.value = data.data.secret
    twoFaCode.value   = ''
    twoFaStep.value   = 'setup'
  } catch (err) {
    twoFaError.value = err?.response?.data?.message ?? 'Failed to start setup.'
  } finally {
    twoFaSetupLoading.value = false
  }
}

async function confirmTwoFa() {
  twoFaError.value          = ''
  twoFaConfirmLoading.value = true
  try {
    await api.post('/auth/2fa/confirm', { code: twoFaCode.value })
    await authStore.fetchUser()
    twoFaStep.value   = 'idle'
    twoFaCode.value   = ''
    twoFaQrUri.value  = ''
    twoFaSecret.value = ''
    twoFaSuccess.value = 'Two-factor authentication enabled.'
    setTimeout(() => { twoFaSuccess.value = '' }, 5000)
  } catch (err) {
    twoFaError.value = err?.response?.data?.message ?? 'Invalid code.'
  } finally {
    twoFaConfirmLoading.value = false
  }
}

function cancelTwoFaFlow() {
  twoFaStep.value   = 'idle'
  twoFaCode.value   = ''
  twoFaQrUri.value  = ''
  twoFaSecret.value = ''
  twoFaError.value  = ''
}

// ─── Account — Security: QR rendering ───────────────────────────────────────
const qrDataUrl = ref('')

async function renderQrCode(uri) {
  if (!uri) return
  try {
    const QRCode = (await import('qrcode')).default
    qrDataUrl.value = await QRCode.toDataURL(uri, { width: 200, margin: 2 })
  } catch { /* noop */ }
}

watch(twoFaQrUri, (uri) => { if (uri) renderQrCode(uri) })
watch(activeSection, (section) => { if (section === 'security') loadSessions() }, { immediate: true })

// ─── Account — Security: Active Sessions ─────────────────────────────────────
const sessions         = ref([])
const sessionsLoading  = ref(false)
const sessionsError    = ref('')
const revokingId       = ref(null)
const revokeAllLoading = ref(false)

const SESSIONS_PER_PAGE = 5
const sessionsPage      = ref(1)

const paginatedSessions = computed(() => {
  const start = (sessionsPage.value - 1) * SESSIONS_PER_PAGE
  return sessions.value.slice(start, start + SESSIONS_PER_PAGE)
})

const sessionsTotalPages = computed(() =>
  Math.max(1, Math.ceil(sessions.value.length / SESSIONS_PER_PAGE))
)

function parseAgent(ua) {
  if (!ua) return '—'
  let browser = 'Browser'
  if (ua.includes('Edg/'))          browser = 'Edge'
  else if (ua.includes('Chrome/'))  browser = 'Chrome'
  else if (ua.includes('Firefox/')) browser = 'Firefox'
  else if (ua.includes('Safari/'))  browser = 'Safari'
  let os = ''
  if (ua.includes('Windows'))                                  os = 'Windows'
  else if (ua.includes('Macintosh') || ua.includes('Mac OS')) os = 'macOS'
  else if (ua.includes('iPhone') || ua.includes('iPad'))      os = 'iOS'
  else if (ua.includes('Android'))                             os = 'Android'
  else if (ua.includes('Linux'))                               os = 'Linux'
  return os ? `${browser} / ${os}` : browser
}

async function loadSessions() {
  sessionsLoading.value = true
  sessionsError.value   = ''
  sessionsPage.value    = 1
  try {
    const { data } = await api.get('/sessions')
    sessions.value = data.data ?? []
  } catch {
    sessionsError.value = 'Failed to load sessions.'
  } finally {
    sessionsLoading.value = false
  }
}

async function revokeSession(session) {
  revokingId.value = session.id
  try {
    await api.delete(`/sessions/${session.id}`)
    sessions.value = sessions.value.filter(s => s.id !== session.id)
    if (sessionsPage.value > sessionsTotalPages.value) {
      sessionsPage.value = sessionsTotalPages.value
    }
  } catch {
    sessionsError.value = 'Failed to revoke session.'
  } finally {
    revokingId.value = null
  }
}

async function revokeAllOtherSessions() {
  revokeAllLoading.value = true
  try {
    await api.delete('/sessions/other')
    await loadSessions()
  } catch {
    sessionsError.value = 'Failed to revoke sessions.'
  } finally {
    revokeAllLoading.value = false
  }
}

// ─── Account — Login Audit ───────────────────────────────────────────────────
const auditLogs       = ref([])
const auditLoading    = ref(false)
const auditPage       = ref(1)
const auditLastPage   = ref(1)
const auditPerPage    = ref(15)
const auditTotal      = ref(0)
const auditEmail      = ref('')
const auditStatus     = ref('')
const auditReason     = ref('')

const auditPageNumbers = computed(() => {
  const last = auditLastPage.value
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const cur = auditPage.value
  const pages = new Set([1, 2, last - 1, last, cur - 1, cur, cur + 1].filter(p => p >= 1 && p <= last))
  const sorted = [...pages].sort((a, b) => a - b)
  const result = []
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push(null)
    result.push(sorted[i])
  }
  return result
})

const AUDIT_FAILURE_DISPLAY = {
  user_not_found:   'User Not Found',
  wrong_password:   'Wrong Password',
  account_inactive: 'Account Inactive',
  account_invited:  'Not Activated',
}
const AUDIT_AVATAR_COLORS = ['bg-primary','bg-teal-600','bg-blue-600','bg-amber-600','bg-rose-600','bg-purple-600','bg-emerald-600','bg-orange-600']

function auditInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return parts.length === 1
    ? parts[0].substring(0, 2).toUpperCase()
    : (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}
function auditAvatarColor(userId) {
  return AUDIT_AVATAR_COLORS[(userId ?? 0) % AUDIT_AVATAR_COLORS.length]
}
function auditFormatDate(str) {
  if (!str) return '—'
  return new Date(str).toLocaleDateString('en-ZA', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
function auditFormatReason(reason) {
  if (!reason) return '—'
  return AUDIT_FAILURE_DISPLAY[reason] ?? reason
}

async function loadAuditLogs(page = 1) {
  auditLoading.value = true
  try {
    const params = { page }
    if (auditEmail.value)  params.email          = auditEmail.value
    if (auditStatus.value) params.status         = auditStatus.value
    if (auditReason.value) params.failure_reason = auditReason.value
    const { data } = await api.get('/login-logs', { params })
    auditLogs.value     = data.data
    auditPage.value     = data.current_page
    auditLastPage.value = data.last_page
    auditPerPage.value  = data.per_page ?? 15
    auditTotal.value    = data.total
  } catch {
    auditLogs.value = []
  } finally {
    auditLoading.value = false
  }
}

watch(activeSection, (section) => { if (section === 'login-audit') loadAuditLogs(1) }, { immediate: true })

// ─── Company — Branding ───────────────────────────────────────────────────────
const DEFAULT_PRIMARY   = '#1F3A5C'
const DEFAULT_SECONDARY = '#D89B4B'
const branding         = ref({ primaryColor: null, secondaryColor: null })
const brandingReady    = ref(false)
const brandingLoading  = ref(false)

async function saveBranding() {
  if (brandingLoading.value) return
  brandingLoading.value = true
  try {
    await api.put('/organization', {
      primary_color:   branding.value.primaryColor,
      secondary_color: branding.value.secondaryColor,
    })
    toastSuccess('Branding saved.')
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Something went wrong. Please try again.')
  } finally {
    brandingLoading.value = false
  }
}

// ─── Bootstrap: load user + org on mount ─────────────────────────────────────
onMounted(async () => {
  if (!authStore.user) await authStore.fetchUser()
  const u = authStore.user
  profile.value = {
    fullName: u.name  ?? '',
    email:    u.email ?? '',
    role:     u.roles?.[0]?.name ?? '',
    phone:    u.phone ?? '',
  }

  await Promise.all([
    api.get('/organization').then(({ data }) => {
      const o = data.data ?? data
      branding.value = {
        primaryColor:   o.primary_color   ?? DEFAULT_PRIMARY,
        secondaryColor: o.secondary_color ?? DEFAULT_SECONDARY,
      }
      brandingReady.value = true
    }).catch(() => {}),
    loadLedgers(),
  ])
})

// ─── Company — Ledgers ───────────────────────────────────────────────────
const APPLIES_TO_OPTS = [
  { value: 'owner',  label: 'Owner'  },
  { value: 'occupant', label: 'Occupant' },
  { value: 'either', label: 'Either' },
]
const RECURRING_OPTS = [
  { value: 'Yes', label: 'Yes — Monthly' },
  { value: 'No',  label: 'No — Ad-hoc'  },
]

function normalizeLedger(ct) {
  return {
    id:          ct.id,
    name:        ct.name,
    description: ct.description ?? '',
    appliesTo:   ct.applies_to,
    recurring:   ct.is_recurring ? 'Monthly' : 'Ad-hoc',
    isActive:    !!ct.is_active,
    isSystem:    ct.is_system,
  }
}

const ledgers        = ref([])
const ledgersLoading = ref(false)
const ledgersError   = ref('')

async function loadLedgers() {
  ledgersLoading.value = true
  ledgersError.value   = ''
  try {
    const { data } = await api.get('/ledgers', { params: { _per_page: 200 } })
    ledgers.value = (data.data ?? data).map(normalizeLedger)
  } catch {
    ledgersError.value = 'Failed to load ledgers.'
  } finally {
    ledgersLoading.value = false
  }
}

// ── Add ──
const showAddLedger    = ref(false)
const newLedger        = ref({ name: '', description: '', appliesTo: 'either', recurring: 'No' })
const addLedgerLoading = ref(false)
const addLedgerError   = ref('')

function closeAddLedger() {
  showAddLedger.value    = false
  addLedgerError.value   = ''
  addLedgerLoading.value = false
  newLedger.value = { name: '', description: '', appliesTo: 'either', recurring: 'No' }
}

async function saveLedger() {
  if (addLedgerLoading.value) return
  addLedgerError.value   = ''
  addLedgerLoading.value = true
  try {
    const { data } = await api.post('/ledgers', {
      name:         newLedger.value.name,
      description:  newLedger.value.description || null,
      applies_to:   newLedger.value.appliesTo,
      is_recurring: newLedger.value.recurring === 'Yes',
    })
    const created = normalizeLedger(data.data ?? data)
    ledgers.value.push(created)
    closeAddLedger()
    toastSuccess(`Ledger "${created.name}" added.`)
  } catch (err) {
    addLedgerError.value = err?.response?.data?.message ?? 'Something went wrong.'
    toastError(addLedgerError.value)
  } finally {
    addLedgerLoading.value = false
  }
}

// ── Edit ──
const showEditLedger    = ref(false)
const editLedger        = ref(null)
const editLedgerLoading = ref(false)
const editLedgerError   = ref('')

function openEditLedger(ct) {
  editLedger.value = {
    id:          ct.id,
    name:        ct.name,
    description: ct.description,
    appliesTo:   ct.appliesTo,
    recurring:   ct.recurring === 'Monthly' ? 'Yes' : 'No',
  }
  editLedgerError.value = ''
  showEditLedger.value  = true
}

function closeEditLedger() {
  showEditLedger.value    = false
  editLedger.value        = null
  editLedgerError.value   = ''
  editLedgerLoading.value = false
}

async function updateLedger() {
  if (editLedgerLoading.value) return
  editLedgerError.value   = ''
  editLedgerLoading.value = true
  try {
    const payload = {
      name:        editLedger.value.name,
      description: editLedger.value.description || null,
    }
    if (!editLedger.value.isSystem) {
      payload.applies_to   = editLedger.value.appliesTo
      payload.is_recurring = editLedger.value.recurring === 'Yes'
    }
    const { data } = await api.put(`/ledgers/${editLedger.value.id}`, payload)
    const updated = normalizeLedger(data.data ?? data)
    const idx = ledgers.value.findIndex(c => c.id === updated.id)
    if (idx !== -1) ledgers.value[idx] = updated
    closeEditLedger()
    toastSuccess(`Ledger "${updated.name}" updated.`)
  } catch (err) {
    editLedgerError.value = err?.response?.data?.message ?? 'Something went wrong.'
    toastError(editLedgerError.value)
  } finally {
    editLedgerLoading.value = false
  }
}

// ── Delete ──
const deletingLedgerId  = ref(null)
const ledgerToDelete    = ref(null)

function confirmDeleteLedger(ct) {
  ledgerToDelete.value = ct
}

async function deleteLedger() {
  const ct = ledgerToDelete.value
  if (!ct) return
  deletingLedgerId.value = ct.id
  ledgerToDelete.value = null
  try {
    await api.delete(`/ledgers/${ct.id}`)
    ledgers.value = ledgers.value.filter(c => c.id !== ct.id)
    toastSuccess(`"${ct.name}" deleted.`)
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Delete failed.')
  } finally {
    deletingLedgerId.value = null
  }
}

const togglingLedgerId = ref(null)

async function toggleLedgerActive(ct) {
  if (ct.isSystem || togglingLedgerId.value === ct.id) return
  togglingLedgerId.value = ct.id
  const next = !ct.isActive
  try {
    await api.put(`/ledgers/${ct.id}`, { is_active: next })
    const idx = ledgers.value.findIndex(c => c.id === ct.id)
    if (idx !== -1) ledgers.value[idx] = { ...ledgers.value[idx], isActive: next }
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Failed to update.')
  } finally {
    togglingLedgerId.value = null
  }
}


function appliesToVariant(v) {
  if (v === 'owner')  return 'warning'
  if (v === 'occupant') return 'primary'
  return 'default'
}

const ledgerGroups = computed(() => [
  { key: 'system', label: 'System',          items: ledgers.value.filter(c => c.isSystem) },
  { key: 'owner',  label: 'Owner',           items: ledgers.value.filter(c => !c.isSystem && c.appliesTo === 'owner') },
  { key: 'occupant', label: 'Occupant',          items: ledgers.value.filter(c => !c.isSystem && c.appliesTo === 'occupant') },
  { key: 'either', label: 'Shared / Either', items: ledgers.value.filter(c => !c.isSystem && c.appliesTo === 'either') },
].filter(g => g.items.length > 0))

// ─── Danger Zone ──────────────────────────────────────────────────────────────
// `forces` = IDs that must also be deleted when this one is selected (data becomes orphaned/invalid)
// `hidden` = implied by a parent; never shown as a standalone row
const FLUSH_TARGETS = [
  {
    id: 'communities',
    label: 'Communities',
    description: 'All communities and their associated data',
    forces: ['units', 'owners', 'occupants', 'invoices', 'cashbook_entries', 'compliance_checklists'],
    hidden: false,
  },
  {
    id: 'compliance_checklists',
    label: 'Compliance Checklists',
    description: 'All compliance checklists and their items',
    forces: [],
    hidden: true,
  },
  {
    id: 'units',
    label: 'Units',
    description: 'All units, charge configurations, and activity history',
    forces: ['owners', 'occupants', 'invoices', 'cashbook_entries'],
    hidden: true,
  },
  {
    id: 'owners',
    label: 'Owners',
    description: 'All property owner records',
    forces: ['invoices', 'cashbook_entries'],
    hidden: true,
  },
  {
    id: 'occupants',
    label: 'Occupants',
    description: 'All occupant/occupant records',
    forces: ['invoices', 'cashbook_entries'],
    hidden: true,
  },
  {
    id: 'invoices',
    label: 'Invoices',
    description: 'All invoices and associated email events',
    forces: ['cashbook_entries'],
    hidden: false,
  },
  {
    id: 'cashbook_entries',
    label: 'Cashbook Entries',
    description: 'All cashbook / transaction records',
    forces: [],
    hidden: false,
  },
  {
    id: 'users',
    label: 'Users',
    description: 'All user accounts (you can choose which users to keep below)',
    forces: [],
    hidden: false,
  },
]

// IDs forced in by the current selection (would be orphaned otherwise)
const forcedTargetIds = computed(() => {
  const forced = new Set()
  for (const id of flushTargets.value) {
    const t = FLUSH_TARGETS.find(x => x.id === id)
    t?.forces.forEach(dep => forced.add(dep))
  }
  return forced
})

// Full set sent to the API
const allTargetsToDelete = computed(() =>
  [...new Set([...flushTargets.value, ...forcedTargetIds.value])]
)

// Which explicitly-checked parents are forcing a given target
function forcedByLabels(targetId) {
  return FLUSH_TARGETS
    .filter(t => flushTargets.value.includes(t.id) && t.forces.includes(targetId))
    .map(t => t.label)
}

// Labels of what a target will pull in when checked
function alsoRemovesLabels(targetId) {
  const t = FLUSH_TARGETS.find(x => x.id === targetId)
  if (!t?.forces.length) return []
  return t.forces
    .filter(dep => !flushTargets.value.includes(dep)) // only show ones not already checked
    .map(dep => FLUSH_TARGETS.find(x => x.id === dep)?.label)
    .filter(Boolean)
}

const orgUsers        = ref([])
const orgUsersLoading = ref(false)

async function loadOrgUsers() {
  orgUsersLoading.value = true
  try {
    const res = await api.get('/users', { params: { _per_page: 500 } })
    orgUsers.value = (res.data.data ?? []).map(u => ({
      id:    u.id,
      name:  u.name,
      email: u.email,
      role:  u.roles?.[0]?.name ?? 'User',
    }))
    // Always preserve the current user
    const currentUserId = authStore.user?.id
    if (currentUserId && !flushKeepUserIds.value.includes(currentUserId)) {
      flushKeepUserIds.value.push(currentUserId)
    }
  } catch {
    orgUsers.value = []
  } finally {
    orgUsersLoading.value = false
  }
}

const flushTargets        = ref([])
const flushKeepUserIds    = ref([])
const showFlushModal      = ref(false)
const flushConfirmation   = ref('')
const flushLoading        = ref(false)
const flushError          = ref('')

// Progress tracking
const flushJobId          = ref(null)
const flushJobStatus      = ref(null)  // dispatched | running | completed | failed
const flushJobSteps       = ref([])
const flushJobError       = ref(null)
let   flushPollTimer      = null

const usersTargetSelected = computed(
  () => flushTargets.value.includes('users') || forcedTargetIds.value.has('users')
)

const isFlushReady = computed(
  () => allTargetsToDelete.value.length > 0 && flushConfirmation.value === 'DELETE',
)

function toggleFlushTarget(id) {
  if (forcedTargetIds.value.has(id) && !flushTargets.value.includes(id)) return

  const idx = flushTargets.value.indexOf(id)
  if (idx === -1) flushTargets.value.push(id)
  else            flushTargets.value.splice(idx, 1)

  if (allTargetsToDelete.value.includes('users') && orgUsers.value.length === 0) {
    loadOrgUsers()
  }
}

function toggleKeepUser(id) {
  if (id === authStore.user?.id) return
  const idx = flushKeepUserIds.value.indexOf(id)
  if (idx === -1) flushKeepUserIds.value.push(id)
  else            flushKeepUserIds.value.splice(idx, 1)
}

const affectedCommunities        = ref([])
const affectedCommunitiesLoading = ref(false)

async function openFlushModal() {
  flushConfirmation.value = ''
  flushError.value        = ''
  affectedCommunities.value   = []
  showFlushModal.value    = true

  if (flushTargets.value.includes('communities')) {
    affectedCommunitiesLoading.value = true
    try {
      const res = await api.get('/communities', { params: { _per_page: 200 } })
      affectedCommunities.value = (res.data.data ?? []).map(e => e.name)
    } catch {
      affectedCommunities.value = []
    } finally {
      affectedCommunitiesLoading.value = false
    }
  }
}

function closeFlushModal() {
  showFlushModal.value    = false
  flushConfirmation.value = ''
  flushError.value        = ''
}

function startPolling(jobId) {
  flushPollTimer = setInterval(async () => {
    try {
      const res = await api.get(`/organization/flush/${jobId}/status`)
      flushJobStatus.value = res.data.status
      flushJobSteps.value  = res.data.steps ?? []
      flushJobError.value  = res.data.error ?? null

      if (res.data.status === 'completed') {
        stopPolling()
        toastSuccess('Organisation data flushed successfully.')
        flushTargets.value     = []
        flushKeepUserIds.value = []
        orgUsers.value         = []
      } else if (res.data.status === 'failed') {
        stopPolling()
      }
    } catch {
      // silently ignore poll errors
    }
  }, 2000)
}

function stopPolling() {
  if (flushPollTimer) {
    clearInterval(flushPollTimer)
    flushPollTimer = null
  }
}

function dismissFlushProgress() {
  stopPolling()
  flushJobId.value     = null
  flushJobStatus.value = null
  flushJobSteps.value  = []
  flushJobError.value  = null
}

async function executeFlush() {
  if (!isFlushReady.value || flushLoading.value) return

  flushLoading.value = true
  flushError.value   = ''

  try {
    const res = await api.delete('/organization/flush', {
      data: {
        targets:       allTargetsToDelete.value,
        keep_user_ids: flushKeepUserIds.value,
        confirmation:  'DELETE',
      },
    })

    const jobId = res.data.job_id
    flushJobId.value     = jobId
    flushJobStatus.value = 'dispatched'
    flushJobSteps.value  = []
    flushJobError.value  = null

    closeFlushModal()
    startPolling(jobId)
  } catch (err) {
    flushError.value = err?.response?.data?.message ?? 'Something went wrong. Please try again.'
  } finally {
    flushLoading.value = false
  }
}
</script>

<template>
  <div>
  <div class="flex gap-8 pb-8 min-h-0">

    <!-- ─── Sidebar navigation ─────────────────────────────────────────── -->
    <aside class="w-52 shrink-0">
      <div class="sticky top-0 space-y-6 pt-1">
        <div v-for="group in NAV" :key="group.group">
          <p class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
            {{ group.group }}
          </p>
          <nav class="space-y-0.5">
            <button
              v-for="item in group.items"
              :key="item.id"
              @click="item.route ? router.push(item.route) : router.push(`/settings/${item.id}`)"
              :class="[
                'w-full flex items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors text-left',
                activeSection === item.id
                  ? item.danger
                    ? 'bg-destructive text-white font-semibold'
                    : 'bg-accent text-white font-semibold shadow-sm'
                  : item.danger
                    ? 'font-medium text-destructive/80 hover:bg-destructive/10 hover:text-destructive'
                    : 'font-medium text-muted-foreground hover:bg-muted hover:text-foreground',
              ]"
            >
              <!-- user icon -->
              <template v-if="item.icon === 'user'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
              </template>
              <!-- key icon -->
              <template v-else-if="item.icon === 'key'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/>
                  <circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
                </svg>
              </template>
              <!-- shield icon -->
              <template v-else-if="item.icon === 'shield'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                </svg>
              </template>
              <!-- building icon -->
              <template v-else-if="item.icon === 'building'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                  <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
                  <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
                  <path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                </svg>
              </template>
              <!-- users icon -->
              <template v-else-if="item.icon === 'users'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
              </template>
              <!-- palette icon -->
              <template v-else-if="item.icon === 'palette'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                  <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                  <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                  <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                  <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
                </svg>
              </template>
              <!-- tag icon -->
              <template v-else-if="item.icon === 'tag'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/>
                  <circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>
                </svg>
              </template>
              <!-- clock icon -->
              <template v-else-if="item.icon === 'clock'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
              </template>
              <!-- triangle-alert icon -->
              <template v-else-if="item.icon === 'triangle-alert'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                  <path d="M12 9v4"/><path d="M12 17h.01"/>
                </svg>
              </template>
              <!-- mail icon -->
              <template v-else-if="item.icon === 'mail'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
              </template>

              {{ item.label }}
            </button>
          </nav>
        </div>
      </div>
    </aside>

    <!-- ─── Content panel ──────────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 space-y-6">

      <!-- Section header -->
      <div class="border-b border-border pb-4">
        <h1 class="font-body font-bold text-xl text-foreground">{{ SECTION_META[activeSection].title }}</h1>
        <p class="text-sm text-muted-foreground mt-0.5">{{ SECTION_META[activeSection].subtitle }}</p>
      </div>

      <!-- ───── PROFILE ───── -->
      <div v-if="activeSection === 'profile'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <AppInput v-model="profile.fullName" label="Full Name"      placeholder="Your full name" />
          <AppInput v-model="profile.email"    label="Email Address"  type="email" placeholder="your@email.com" />
          <AppInput v-model="profile.phone"    label="Phone"          type="tel" placeholder="+27 82 000 0000" />
          <AppInput v-model="profile.role"     label="Role"           disabled />
        </div>
        <div class="flex justify-end pt-2">
          <AppButton variant="primary" :loading="profileLoading" @click="updateProfile">Update Profile</AppButton>
        </div>
      </div>

      <!-- ───── PASSWORD ───── -->
      <div v-if="activeSection === 'password'" class="space-y-4 max-w-md">
        <AppInput v-model="password.current" type="password" label="Current Password"     placeholder="••••••••" />
        <AppInput v-model="password.newPass" type="password" label="New Password"         placeholder="••••••••" />
        <AppInput v-model="password.confirm" type="password" label="Confirm New Password" placeholder="••••••••" />
        <p v-if="passwordError"   class="text-sm text-destructive">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="text-sm text-green-600">{{ passwordSuccess }}</p>
        <div class="flex justify-end pt-2">
          <AppButton variant="primary" :loading="passwordLoading" @click="changePassword">Change Password</AppButton>
        </div>
      </div>

      <!-- ───── SECURITY ───── -->
      <div v-if="activeSection === 'security'" class="space-y-4">

        <!-- Global feedback -->
        <p v-if="twoFaSuccess" class="text-sm text-green-600 px-1">{{ twoFaSuccess }}</p>

        <!-- Two-Factor Authentication card -->
        <div class="rounded-lg border bg-card shadow-sm">
          <!-- Header row -->
          <div class="flex items-center justify-between p-5">
            <div class="flex items-center gap-3">
              <div :class="twoFaEnabled ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'" class="p-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
              </div>
              <div>
                <p class="text-sm font-medium text-foreground">Two-Factor Authentication</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                  <span v-if="twoFaEnabled" class="text-green-600 font-medium">Enabled · Required — cannot be disabled</span>
                  <span v-else>Required on every account — set it up to continue</span>
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <AppButton v-if="twoFaStep === 'idle' && !twoFaEnabled" variant="outline" size="sm" :loading="twoFaSetupLoading" @click="startTwoFaSetup">Set up</AppButton>
            </div>
          </div>

          <!-- Setup flow: QR + code entry -->
          <div v-if="twoFaStep === 'setup'" class="border-t p-5 space-y-4">
            <p class="text-sm text-foreground font-medium">Scan this QR code with your authenticator app</p>
            <p class="text-xs text-muted-foreground -mt-2">Use Microsoft Authenticator or Apple Passwords (Verification Codes).</p>
            <div class="flex gap-6 items-start flex-wrap">
              <div class="bg-white p-3 rounded-lg border inline-block">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="2FA QR Code" class="w-48 h-48" />
                <div v-else class="w-48 h-48 flex items-center justify-center text-muted-foreground text-xs">Loading…</div>
              </div>
              <div class="flex-1 space-y-3 min-w-48">
                <div>
                  <p class="text-xs text-muted-foreground mb-1">Or enter this code manually:</p>
                  <code class="text-xs font-mono bg-muted px-3 py-2 rounded block break-all select-all">{{ twoFaSecret }}</code>
                </div>
                <AppInput v-model="twoFaCode" label="Enter the 6-digit code from your app" placeholder="000000" inputmode="numeric" maxlength="6" />
                <p v-if="twoFaError" class="text-sm text-destructive">{{ twoFaError }}</p>
                <div class="flex gap-2">
                  <AppButton variant="primary" size="sm" :loading="twoFaConfirmLoading" @click="confirmTwoFa">Confirm & Enable</AppButton>
                  <AppButton variant="ghost"   size="sm" @click="cancelTwoFaFlow">Cancel</AppButton>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Active Sessions card -->
        <div class="rounded-lg border bg-card shadow-sm">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b">
            <div class="flex items-center gap-3">
              <div class="bg-muted text-muted-foreground p-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2" /></svg>
              </div>
              <div>
                <p class="text-sm font-medium text-foreground">Active Sessions</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                  <template v-if="sessions.length">{{ sessions.length }} active session{{ sessions.length !== 1 ? 's' : '' }}</template>
                  <template v-else>Your login sessions across devices</template>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <AppButton
                v-if="sessions.filter(s => !s.is_current).length >= 1"
                variant="danger"
                size="sm"
                :loading="revokeAllLoading"
                @click="revokeAllOtherSessions"
              >
                Log Out All Others
              </AppButton>
              <button
                type="button"
                class="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                :class="{ 'animate-spin': sessionsLoading }"
                @click="loadSessions"
                title="Refresh"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="px-5 py-3">
            <p v-if="sessionsError" class="text-sm text-destructive py-2">{{ sessionsError }}</p>

            <div v-if="sessionsLoading" class="flex justify-center py-6">
              <svg class="w-5 h-5 animate-spin text-muted-foreground" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
            </div>

            <div v-else-if="sessions.length === 0" class="text-center py-6 text-sm text-muted-foreground">
              No active sessions found.
            </div>

            <div v-else class="divide-y">
              <div
                v-for="session in paginatedSessions"
                :key="session.id"
                class="flex items-center justify-between py-3"
              >
                <div class="flex items-center gap-3">
                  <div :class="session.is_current ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'" class="p-2 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2" /></svg>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-foreground">
                      {{ parseAgent(session.user_agent) }}
                      <span v-if="session.is_current" class="ml-2 text-xs font-normal text-green-600">(this session)</span>
                    </p>
                    <p class="text-xs text-muted-foreground">{{ session.ip_address }} · {{ new Date(session.created_at).toLocaleString() }}</p>
                  </div>
                </div>
                <AppButton
                  v-if="!session.is_current"
                  variant="outline"
                  size="sm"
                  :loading="revokingId === session.id"
                  @click="revokeSession(session)"
                >
                  Log Out
                </AppButton>
                <span v-else class="text-xs text-green-600 font-medium">Current</span>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="sessionsTotalPages > 1" class="flex items-center justify-between pt-3 mt-1 border-t">
              <p class="text-xs text-muted-foreground">Page {{ sessionsPage }} of {{ sessionsTotalPages }}</p>
              <div class="flex gap-1">
                <AppButton variant="outline" size="sm" :disabled="sessionsPage <= 1" @click="sessionsPage--">← Prev</AppButton>
                <AppButton variant="outline" size="sm" :disabled="sessionsPage >= sessionsTotalPages" @click="sessionsPage++">Next →</AppButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ───── COMMUNICATION ───── -->
      <CommunicationSettings v-if="activeSection === 'communication'" />

      <!-- ───── BRANDING ───── -->
      <div v-if="activeSection === 'branding'" class="space-y-6 max-w-lg">

        <!-- Skeleton while loading -->
        <template v-if="!brandingReady">
          <div v-for="i in 2" :key="i" class="flex flex-col gap-1.5">
            <div class="h-4 w-24 rounded bg-muted animate-pulse" />
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded border-2 border-border bg-muted animate-pulse" />
              <div class="h-10 flex-1 rounded bg-muted animate-pulse" />
            </div>
          </div>
          <div class="h-20 rounded-lg border border-border bg-muted/30 animate-pulse" />
        </template>

        <!-- Actual fields — only shown after data arrives -->
        <template v-else>
          <!-- Primary colour -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-fg">Primary Color</label>
            <div class="flex items-center gap-3">
              <input type="color" v-model="branding.primaryColor" class="w-10 h-10 rounded border-2 border-border cursor-pointer p-0.5 bg-white" />
              <AppInput v-model="branding.primaryColor" placeholder="#1F3A5C" autocomplete="off" />
              <AppButton type="button" variant="ghost" square size="sm" title="Reset to default" @click="branding.primaryColor = DEFAULT_PRIMARY">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                </svg>
              </AppButton>
            </div>
          </div>

          <!-- Secondary colour -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-fg">Secondary Color</label>
            <div class="flex items-center gap-3">
              <input type="color" v-model="branding.secondaryColor" class="w-10 h-10 rounded border-2 border-border cursor-pointer p-0.5 bg-white" />
              <AppInput v-model="branding.secondaryColor" placeholder="#D89B4B" autocomplete="off" />
              <AppButton type="button" variant="ghost" square size="sm" title="Reset to default" @click="branding.secondaryColor = DEFAULT_SECONDARY">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                </svg>
              </AppButton>
            </div>
          </div>

          <!-- Preview swatches -->
          <div class="flex items-center gap-3 p-4 rounded-lg border border-border bg-muted/30">
            <div class="w-10 h-10 rounded-md shadow-sm" :style="{ background: branding.primaryColor }" />
            <div class="w-10 h-10 rounded-md shadow-sm" :style="{ background: branding.secondaryColor }" />
            <p class="text-xs text-muted-foreground">Live preview of your brand colours</p>
          </div>

          <div class="flex justify-end">
            <AppButton variant="primary" :loading="brandingLoading" @click="saveBranding">Save Branding</AppButton>
          </div>
        </template>
      </div>

      <!-- ───── LEDGERS ───── -->
      <div v-if="activeSection === 'ledgers'">
        <div class="flex items-center justify-between mb-4">
          <p class="text-sm text-muted-foreground">
            <span v-if="ledgersLoading">Loading...</span>
            <span v-else-if="ledgersError" class="text-destructive">{{ ledgersError }}</span>
            <span v-else>{{ ledgers.length }} ledgers configured</span>
          </p>
          <AppButton variant="primary" size="sm" @click="showAddLedger = true">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Ledger
          </AppButton>
        </div>

        <div class="rounded-lg border bg-card shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-muted/40">
              <tr class="border-b border-border">
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Name</th>
                <th class="text-left py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Recurring</th>
                <th class="text-center py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Active</th>
                <th class="text-right py-3 px-4 text-xs font-medium text-muted-foreground uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in ledgerGroups" :key="group.key">
                <!-- Section header row -->
                <tr class="bg-primary/8 border-y border-primary/15">
                  <td colspan="4" class="py-2.5 px-4">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-semibold uppercase tracking-wider text-primary/70">{{ group.label }}</span>
                      <span class="text-xs text-primary/40">{{ group.items.length }}</span>
                    </div>
                  </td>
                </tr>
                <!-- Group rows -->
                <tr v-for="ct in group.items" :key="ct.id" class="border-b border-border hover:bg-muted/20 transition-colors">
                  <td class="py-3 px-4">
                    <span class="font-medium text-foreground">{{ ct.name }}</span>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ ct.description }}</p>
                  </td>
                  <td class="py-3 px-4 text-sm text-foreground">{{ ct.recurring }}</td>
                  <td class="py-3 px-4">
                    <div class="flex justify-center">
                      <!-- System types: dash indicator -->
                      <div v-if="ct.isSystem" class="flex items-center justify-center w-9 h-5">
                        <span class="text-sm text-muted-foreground/40">—</span>
                      </div>
                      <!-- Non-system: instant toggle -->
                      <button
                        v-else
                        type="button"
                        :disabled="togglingLedgerId === ct.id"
                        :class="[
                          'relative h-5 w-9 shrink-0 cursor-pointer rounded-full transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:opacity-50 disabled:cursor-wait',
                          ct.isActive ? 'bg-green-500' : 'bg-muted-foreground/25',
                        ]"
                        :aria-checked="ct.isActive"
                        role="switch"
                        @click="toggleLedgerActive(ct)"
                      >
                        <span
                          :class="[
                            'pointer-events-none absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200',
                            ct.isActive ? 'translate-x-4' : 'translate-x-0',
                          ]"
                        />
                      </button>
                    </div>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <AppDropdown align="right" width="w-36">
                      <template #trigger="{ toggle }">
                        <AppButton variant="ghost" square size="sm" @click="toggle">
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>
                          </svg>
                        </AppButton>
                      </template>
                      <template #default="{ close }">
                        <AppDropdownItem label="Edit" @click="close(); openEditLedger(ct)">
                          <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>
                            </svg>
                          </template>
                        </AppDropdownItem>
                        <AppDropdownItem v-if="!ct.isSystem" label="Delete" variant="danger" @click="close(); confirmDeleteLedger(ct)">
                          <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                            </svg>
                          </template>
                        </AppDropdownItem>
                      </template>
                    </AppDropdown>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ───── LOGIN AUDIT ───── -->
      <div v-if="activeSection === 'login-audit'" class="space-y-4">

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-muted-foreground mb-1">Search by email</label>
            <AppInput
              v-model="auditEmail"
              leading-icon="search"
              placeholder="user@example.com"
              @keyup.enter="loadAuditLogs(1)"
            />
          </div>
          <div class="min-w-[140px]">
            <label class="block text-xs font-medium text-muted-foreground mb-1">Status</label>
            <AppSelect
              v-model="auditStatus"
              placeholder="All"
              :options="[
                { value: '', label: 'All' },
                { value: 'success', label: 'Success' },
                { value: 'failed', label: 'Failed' },
              ]"
            />
          </div>
          <div class="min-w-[180px]">
            <label class="block text-xs font-medium text-muted-foreground mb-1">Failure Reason</label>
            <AppSelect
              v-model="auditReason"
              placeholder="All"
              :options="[
                { value: '', label: 'All' },
                { value: 'user_not_found',   label: 'User Not Found' },
                { value: 'wrong_password',   label: 'Wrong Password' },
                { value: 'account_inactive', label: 'Account Inactive' },
                { value: 'account_invited',  label: 'Not Activated' },
              ]"
            />
          </div>
          <div class="flex items-end gap-2">
            <AppButton @click="loadAuditLogs(1)">Apply</AppButton>
            <AppButton variant="outline" @click="auditEmail = ''; auditStatus = ''; auditReason = ''; loadAuditLogs(1)">Clear</AppButton>
          </div>
        </div>

        <!-- Table card -->
        <div class="rounded-lg border bg-card shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b">
            <span class="text-sm font-medium text-foreground">Login Attempts</span>
            <span class="text-xs text-muted-foreground">{{ auditTotal.toLocaleString() }} total</span>
          </div>

          <template v-if="auditLoading">
            <div class="divide-y">
              <div v-for="n in 8" :key="n" class="px-4 py-3 flex items-center gap-4 animate-pulse">
                <div class="w-8 h-8 rounded-full bg-muted shrink-0"></div>
                <div class="flex-1 space-y-1.5">
                  <div class="h-3 w-40 bg-muted rounded"></div>
                  <div class="h-3 w-28 bg-muted rounded"></div>
                </div>
                <div class="h-3 w-16 bg-muted rounded"></div>
                <div class="h-3 w-20 bg-muted rounded"></div>
                <div class="h-3 w-24 bg-muted rounded"></div>
              </div>
            </div>
          </template>

          <div v-else-if="!auditLogs.length" class="px-4 py-16 text-center">
            <svg class="w-10 h-10 text-muted-foreground/40 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <p class="text-sm text-muted-foreground">No login attempts found.</p>
            <p v-if="auditEmail || auditStatus || auditReason" class="text-xs text-muted-foreground mt-1">Try clearing your filters.</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b bg-muted/30">
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">User</th>
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Date &amp; Time</th>
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Status</th>
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Reason</th>
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">IP Address</th>
                  <th class="text-left px-4 py-2.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Device</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in auditLogs" :key="log.id" class="border-b last:border-0 hover:bg-muted/20 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                      <div v-if="log.user" :class="['w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0', auditAvatarColor(log.user.id)]">
                        {{ auditInitials(log.user.name) }}
                      </div>
                      <div v-else class="w-7 h-7 rounded-full bg-muted flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                      </div>
                      <div class="min-w-0">
                        <p class="text-xs font-medium text-foreground truncate">{{ log.user?.name ?? 'Unknown' }}</p>
                        <p class="text-[11px] text-muted-foreground truncate">{{ log.email }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-xs text-foreground whitespace-nowrap">{{ auditFormatDate(log.created_at) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span v-if="log.login_successful" class="inline-flex items-center gap-1 text-xs font-medium text-success">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                      Success
                    </span>
                    <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-danger">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                      Failed
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">{{ auditFormatReason(log.failure_reason) }}</td>
                  <td class="px-4 py-3 font-mono text-xs text-muted-foreground whitespace-nowrap">{{ log.ip_address ?? '—' }}</td>
                  <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">{{ parseAgent(log.user_agent) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!auditLoading && auditLastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-border">
            <p class="text-xs text-muted-foreground">
              Showing {{ (auditPage - 1) * auditPerPage + 1 }}–{{ Math.min(auditPage * auditPerPage, auditTotal) }} of {{ auditTotal }}
            </p>
            <div class="flex items-center gap-1">
              <button
                @click="loadAuditLogs(auditPage - 1)"
                :disabled="auditPage <= 1"
                class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors"
              >Previous</button>

              <template v-for="p in auditPageNumbers" :key="p ?? 'ellipsis'">
                <span v-if="p === null" class="px-1 text-xs text-muted-foreground">…</span>
                <button
                  v-else
                  @click="loadAuditLogs(p)"
                  :class="[
                    'w-7 h-7 rounded text-xs font-medium border transition-colors',
                    p === auditPage
                      ? 'bg-primary border-primary text-white'
                      : 'border-input bg-card text-foreground hover:bg-muted',
                  ]"
                >{{ p }}</button>
              </template>

              <button
                @click="loadAuditLogs(auditPage + 1)"
                :disabled="auditPage >= auditLastPage"
                class="px-2.5 py-1 rounded text-xs font-medium border border-input bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:pointer-events-none transition-colors"
              >Next</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ───── DANGER ZONE ───── -->
      <div v-if="activeSection === 'danger-zone'" class="space-y-6">

        <!-- Warning banner -->
        <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/5 border border-destructive/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-destructive shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
          </svg>
          <p class="text-sm text-destructive/80 leading-relaxed">
            Flushing organisation data is <strong>permanent and cannot be undone</strong>.
            Select the data categories to wipe below, optionally keep specific users, then confirm.
            Only Company Admins can perform this action.
          </p>
        </div>

        <!-- Flush Organisation card -->
        <div class="rounded-lg border-2 border-destructive/30 bg-card shadow-sm overflow-hidden">
          <div class="flex items-start justify-between p-5 border-b border-destructive/20 bg-destructive/5">
            <div>
              <h3 class="text-sm font-semibold text-destructive">Flush Organisation Data</h3>
              <p class="text-xs text-destructive/70 mt-0.5">Select categories and confirm to wipe</p>
            </div>
            <AppButton
              variant="danger"
              size="sm"
              :disabled="allTargetsToDelete.length === 0"
              @click="openFlushModal"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
              </svg>
              Flush Data
            </AppButton>
          </div>

          <div class="p-5 space-y-2">
            <div
              v-for="target in FLUSH_TARGETS.filter(t => !t.hidden)"
              :key="target.id"
              class="rounded-md border transition-colors"
              :class="allTargetsToDelete.includes(target.id) ? 'border-destructive/40 bg-destructive/5' : 'border-border'"
            >
              <label
                class="flex items-start gap-3 p-3"
                :class="forcedTargetIds.has(target.id) ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
              >
                <input
                  type="checkbox"
                  :value="target.id"
                  :checked="allTargetsToDelete.includes(target.id)"
                  :disabled="forcedTargetIds.has(target.id)"
                  @change="toggleFlushTarget(target.id)"
                  class="mt-0.5 h-4 w-4 accent-destructive shrink-0"
                />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-foreground">{{ target.label }}</p>
                  <p class="text-xs text-muted-foreground mt-0.5">{{ target.description }}</p>
                  <!-- Inline pills showing what this selection also removes -->
                  <div v-if="target.forces.length" class="flex flex-wrap gap-1.5 mt-2">
                    <span
                      v-for="depId in target.forces"
                      :key="depId"
                      class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border transition-colors"
                      :class="allTargetsToDelete.includes(target.id)
                        ? 'bg-destructive/10 border-destructive/25 text-destructive'
                        : 'bg-muted border-border text-muted-foreground'"
                    >
                      <svg v-if="allTargetsToDelete.includes(target.id)" xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                      </svg>
                      {{ FLUSH_TARGETS.find(t => t.id === depId)?.label }}
                    </span>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Keep users -->
        <div v-if="usersTargetSelected" class="rounded-lg border bg-card shadow-sm overflow-hidden">
          <div class="p-5 border-b border-border">
            <h3 class="text-sm font-semibold text-foreground">Users to Keep</h3>
            <p class="text-xs text-muted-foreground mt-0.5">Check the users you want to <em>preserve</em>. All others will be deleted.</p>
          </div>
          <div class="p-5">
            <div v-if="orgUsersLoading" class="flex items-center gap-2 py-2 text-sm text-muted-foreground">
              <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Loading users…
            </div>
            <div v-else class="space-y-2">
              <label
                v-for="user in orgUsers"
                :key="user.id"
                class="flex items-center gap-3 p-3 rounded-md border border-border transition-colors"
                :class="[
                  user.id === authStore.user?.id
                    ? 'border-emerald-500/40 bg-emerald-50/50 dark:bg-emerald-950/20 cursor-not-allowed'
                    : flushKeepUserIds.includes(user.id)
                      ? 'border-emerald-500/40 bg-emerald-50/50 dark:bg-emerald-950/20 cursor-pointer hover:bg-emerald-50/70'
                      : 'hover:bg-muted/30 cursor-pointer'
                ]"
              >
                <input
                  type="checkbox"
                  :checked="flushKeepUserIds.includes(user.id)"
                  :disabled="user.id === authStore.user?.id"
                  @change="toggleKeepUser(user.id)"
                  class="h-4 w-4 shrink-0"
                  style="accent-color: #22c55e"
                />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-foreground">{{ user.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ user.email }} &middot; {{ user.role }}</p>
                </div>
                <AppBadge v-if="user.id === authStore.user?.id" variant="success" size="sm" bordered>You</AppBadge>
                <AppBadge v-else-if="flushKeepUserIds.includes(user.id)" variant="success" size="sm" bordered>Keep</AppBadge>
              </label>
              <p v-if="orgUsers.length === 0" class="text-sm text-muted-foreground py-2">No users found.</p>
            </div>
          </div>
        </div>

        <!-- ── Flush Progress Tracker ─────────────────────────────── -->
        <div v-if="flushJobId" class="rounded-lg border bg-card shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-border">
            <div class="flex items-center gap-2">
              <svg v-if="flushJobStatus === 'completed'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              <svg v-else-if="flushJobStatus === 'failed'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              <svg v-else class="w-4 h-4 animate-spin text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              <h3 class="text-sm font-semibold text-foreground">
                <span v-if="flushJobStatus === 'dispatched'">Preparing flush…</span>
                <span v-else-if="flushJobStatus === 'running'">Flushing data…</span>
                <span v-else-if="flushJobStatus === 'completed'">Flush complete</span>
                <span v-else-if="flushJobStatus === 'failed'">Flush failed</span>
              </h3>
            </div>
            <button
              v-if="flushJobStatus === 'completed' || flushJobStatus === 'failed'"
              @click="dismissFlushProgress"
              class="text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              Dismiss
            </button>
          </div>

          <div class="p-5 space-y-2">
            <!-- Dispatched placeholder -->
            <div v-if="flushJobStatus === 'dispatched'" class="flex items-center gap-2 text-sm text-muted-foreground py-1">
              <svg class="w-4 h-4 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              Starting job…
            </div>

            <!-- Step rows -->
            <div
              v-for="step in flushJobSteps"
              :key="step.id"
              class="flex items-center gap-3 px-3 py-2.5 rounded-md border"
              :class="{
                'border-border bg-muted/20': step.status === 'pending',
                'border-accent/30 bg-accent/5': step.status === 'running',
                'border-emerald-500/30 bg-emerald-50/40': step.status === 'completed',
                'border-destructive/30 bg-destructive/5': step.status === 'failed',
              }"
            >
              <!-- Icon -->
              <span class="shrink-0 w-5 h-5 flex items-center justify-center">
                <svg v-if="step.status === 'completed'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <svg v-else-if="step.status === 'failed'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                <svg v-else-if="step.status === 'running'" class="w-4 h-4 animate-spin text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <span v-else class="w-3.5 h-3.5 rounded-full border-2 border-border block"></span>
              </span>

              <span class="flex-1 text-sm font-medium" :class="{
                'text-muted-foreground': step.status === 'pending',
                'text-accent': step.status === 'running',
                'text-foreground': step.status === 'completed' || step.status === 'failed',
              }">{{ step.label }}</span>

              <span v-if="step.status === 'completed'" class="text-xs text-muted-foreground tabular-nums">
                {{ step.count ?? 0 }} deleted
              </span>
              <span v-else-if="step.status === 'running'" class="text-xs text-accent animate-pulse">Processing…</span>
            </div>

            <!-- Error message -->
            <p v-if="flushJobError" class="text-sm text-destructive pt-1">{{ flushJobError }}</p>
          </div>
        </div>

      </div>
      <!-- end Danger Zone -->

    </div>
    <!-- end content panel -->

  </div>

  <!-- ─── Flush confirmation modal ──────────────────────────────────────── -->
  <AppModal :show="showFlushModal" title="Confirm Data Flush" size="md" @close="closeFlushModal">
    <div class="space-y-5">
      <div class="flex items-start gap-3 p-4 rounded-lg bg-destructive/10 border border-destructive/30">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-destructive shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
          <path d="M12 9v4"/><path d="M12 17h.01"/>
        </svg>
        <p class="text-sm text-destructive font-medium">This action is permanent and cannot be reversed. The following data will be deleted:</p>
      </div>

      <ul class="space-y-2">
        <li
          v-for="target in FLUSH_TARGETS.filter(t => flushTargets.includes(t.id))"
          :key="target.id"
          class="rounded-md border border-destructive/25 bg-destructive/5 px-3 py-2.5"
        >
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-destructive shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
            <span class="text-sm font-semibold text-foreground">{{ target.label }}</span>
            <span v-if="target.id === 'users' && flushKeepUserIds.length > 0" class="text-xs text-muted-foreground">
              ({{ flushKeepUserIds.length }} kept)
            </span>
          </div>

          <!-- Community names list -->
          <div v-if="target.id === 'communities'" class="mt-2 ml-5">
            <div v-if="affectedCommunitiesLoading" class="flex items-center gap-1.5 text-xs text-muted-foreground">
              <svg class="w-3 h-3 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              Loading communities…
            </div>
            <ul v-else-if="affectedCommunities.length" class="space-y-0.5 mb-2">
              <li
                v-for="name in affectedCommunities"
                :key="name"
                class="flex items-center gap-1.5 text-xs text-destructive/80"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                {{ name }}
              </li>
            </ul>
          </div>

          <div v-if="target.forces.length" class="flex flex-wrap gap-1.5 mt-2 ml-5">
            <span
              v-for="depId in target.forces"
              :key="depId"
              class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full bg-destructive/10 border border-destructive/20 text-destructive"
            >
              {{ FLUSH_TARGETS.find(t => t.id === depId)?.label }}
            </span>
          </div>
        </li>
      </ul>

      <div class="space-y-1.5">
        <label class="text-sm font-medium text-foreground">
          Type <span class="font-mono font-bold text-destructive">DELETE</span> to confirm
        </label>
        <AppInput v-model="flushConfirmation" placeholder="DELETE" autocomplete="off" spellcheck="false" />
      </div>

      <p v-if="flushError" class="text-sm text-destructive">{{ flushError }}</p>
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="flushLoading" @click="closeFlushModal">Cancel</AppButton>
      <AppButton variant="danger" :disabled="!isFlushReady || flushLoading" :loading="flushLoading" @click="executeFlush">
        {{ flushLoading ? 'Flushing…' : 'Flush Organisation Data' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- ─── Add Ledger modal ─────────────────────────────────────────── -->
  <AppModal :show="showAddLedger" title="Add Custom Ledger" size="md" @close="closeAddLedger">
    <div class="space-y-4">
      <AppInput v-model="newLedger.name"        label="Name"        placeholder="e.g. Generator Fee" required />
      <AppInput v-model="newLedger.description" label="Description" type="textarea" :rows="2" placeholder="Brief description..." />
      <AppSelect v-model="newLedger.appliesTo"  label="Applies To"  :options="APPLIES_TO_OPTS" required />
      <AppSelect v-model="newLedger.recurring"  label="Recurring?"  :options="RECURRING_OPTS"  required />
      <p v-if="addLedgerError" class="text-sm text-destructive">{{ addLedgerError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="closeAddLedger">Cancel</AppButton>
      <AppButton variant="primary" :loading="addLedgerLoading" @click="saveLedger">Save</AppButton>
    </template>
  </AppModal>

  <!-- ─── Edit Ledger modal ────────────────────────────────────────── -->
  <AppModal v-if="editLedger" :show="showEditLedger" title="Edit Ledger" size="md" @close="closeEditLedger">
    <div class="space-y-4">
      <AppInput v-model="editLedger.name"        label="Name"        placeholder="e.g. Generator Fee" required />
      <AppInput v-model="editLedger.description" label="Description" type="textarea" :rows="2" placeholder="Brief description..." />
      <template v-if="!editLedger.isSystem">
        <AppSelect v-model="editLedger.appliesTo"  label="Applies To"  :options="APPLIES_TO_OPTS" required />
        <AppSelect v-model="editLedger.recurring"  label="Recurring?"  :options="RECURRING_OPTS"  required />
      </template>
      <p v-if="editLedgerError" class="text-sm text-destructive">{{ editLedgerError }}</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="closeEditLedger">Cancel</AppButton>
      <AppButton variant="primary" :loading="editLedgerLoading" @click="updateLedger">Save Changes</AppButton>
    </template>
  </AppModal>

  <!-- ─── Delete ledger confirmation ─────────────────────────────────── -->
  <AppModal :show="!!ledgerToDelete" title="Delete Ledger" size="sm" @close="ledgerToDelete = null">
    <div class="space-y-2">
      <p class="text-sm text-foreground">Are you sure you want to delete <span class="font-semibold">{{ ledgerToDelete?.name }}</span>?</p>
      <p class="text-sm text-muted-foreground">This cannot be undone.</p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="ledgerToDelete = null">Cancel</AppButton>
      <AppButton variant="danger" :loading="!!deletingLedgerId" @click="deleteLedger">Delete</AppButton>
    </template>
  </AppModal>

  </div>
</template>
