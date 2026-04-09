<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi.js'
import AppButton    from '@/components/common/AppButton.vue'
import AppBadge     from '@/components/common/AppBadge.vue'
import AppStatCard  from '@/components/common/AppStatCard.vue'
import AppModal     from '@/components/common/AppModal.vue'
import AppInput     from '@/components/common/AppInput.vue'
import AppTabFilter    from '@/components/common/AppTabFilter.vue'
import AppSelect       from '@/components/common/AppSelect.vue'
import AppTableToolbar from '@/components/common/AppTableToolbar.vue'

const router = useRouter()

// ─── Role maps ───────────────────────────────────────────────────────────────
const ROLE_DISPLAY = {
  'company-admin':        'Admin',
  'portfolio-manager':    'Portfolio Manager',
  'financial-controller': 'Financial Controller',
  'portfolio-assistant':  'Portfolio Assistant',
  'trustee':              'Trustee / Director',
  'owner':                'Owner',
  'tenant':               'Tenant',
  'contractor':           'Contractor',
}

const INTERNAL_ROLE_SLUGS = ['company-admin', 'portfolio-manager', 'financial-controller', 'portfolio-assistant']

const INTERNAL_ROLE_OPTS = [
  { value: 'company-admin',        label: 'Admin' },
  { value: 'portfolio-manager',    label: 'Portfolio Manager' },
  { value: 'financial-controller', label: 'Financial Controller' },
  { value: 'portfolio-assistant',  label: 'Portfolio Assistant' },
]
const EXTERNAL_ROLE_OPTS = [
  { value: 'trustee',    label: 'Trustee / Director' },
  { value: 'owner',      label: 'Owner' },
  { value: 'tenant',     label: 'Tenant' },
  { value: 'contractor', label: 'Contractor' },
]
const ALL_ROLE_OPTS = [...INTERNAL_ROLE_OPTS, ...EXTERNAL_ROLE_OPTS]

const STATUS_OPTS = [
  { value: 'active',   label: 'Active' },
  { value: 'invited',  label: 'Invited' },
  { value: 'inactive', label: 'Inactive' },
]

const CATEGORY_OPTS = [
  { value: 'internal', label: 'Internal (Bold Mark Staff)' },
  { value: 'external', label: 'External (Client)'          },
]

const ROLE_BADGE_STYLES = {
  'company-admin':        'bg-primary/10 text-primary border-primary/20',
  'portfolio-manager':    'bg-blue-50 text-blue-700 border-blue-200',
  'financial-controller': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'portfolio-assistant':  'bg-purple-50 text-purple-700 border-purple-200',
  'trustee':              'bg-amber-50 text-amber-700 border-amber-200',
  'owner':                'bg-teal-50 text-teal-700 border-teal-200',
  'tenant':               'bg-sky-50 text-sky-700 border-sky-200',
  'contractor':           'bg-orange-50 text-orange-700 border-orange-200',
}
function getRoleBadgeClass(slug) {
  return ROLE_BADGE_STYLES[slug] ?? 'bg-muted text-muted-foreground border-border'
}

// ─── State ───────────────────────────────────────────────────────────────────
const users       = ref([])
const summary     = ref({ total: 0, active: 0, invited: 0, inactive: 0, internal_count: 0, external_count: 0 })
const loading     = ref(true)
const activeTab   = ref('all')
const searchQuery = ref('')
const openMenuId  = ref(null)

// Pagination
const currentPage = ref(1)
const PER_PAGE    = 15

// Invite modal
const showInviteModal  = ref(false)
const inviteLoading    = ref(false)
const inviteErrors     = ref({})
const inviteForm       = ref({ name: '', email: '', phone: '', category: '', role: '' })

// Edit modal
const showEditModal  = ref(false)
const editLoading    = ref(false)
const editErrors     = ref({})
const editingUser    = ref(null)
const editForm       = ref({ name: '', email: '', phone: '', role: '', status: '' })

// Delete confirm
const showDeleteConfirm = ref(false)
const deleteLoading     = ref(false)
const deletingUser      = ref(null)

// Estate assignment modal
const showEstatesModal  = ref(false)
const estatesLoading    = ref(false)
const estatesUser       = ref(null)
const selectedEstateIds = ref([])
const allEstates        = ref([])

// Resend / password reset feedback
const toastMessage = ref('')
const toastType    = ref('success')

// ─── Computed ────────────────────────────────────────────────────────────────
const tabs = computed(() => [
  { label: `All Users (${summary.value.total})`,             value: 'all' },
  { label: `Internal (${summary.value.internal_count})`,     value: 'internal' },
  { label: `External (${summary.value.external_count})`,     value: 'external' },
])

const categoryFilteredUsers = computed(() => {
  if (activeTab.value === 'internal') return users.value.filter(u => getRoleCategory(u) === 'internal')
  if (activeTab.value === 'external') return users.value.filter(u => getRoleCategory(u) === 'external')
  return users.value
})

const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return categoryFilteredUsers.value
  return categoryFilteredUsers.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q) ||
    getRoleDisplayName(u).toLowerCase().includes(q)
  )
})

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredUsers.value.slice(start, start + PER_PAGE)
})

const lastPage = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / PER_PAGE)))

const availableRoleOpts = computed(() =>
  inviteForm.value.category === 'internal' ? INTERNAL_ROLE_OPTS :
  inviteForm.value.category === 'external' ? EXTERNAL_ROLE_OPTS : []
)

// ─── Helpers ─────────────────────────────────────────────────────────────────
function getRoleSlug(user) {
  return user.roles?.[0]?.name ?? ''
}

function getRoleDisplayName(user) {
  const slug = getRoleSlug(user)
  return ROLE_DISPLAY[slug] ?? slug
}

function getRoleCategory(user) {
  return INTERNAL_ROLE_SLUGS.includes(getRoleSlug(user)) ? 'internal' : 'external'
}

function isInternalUser(user) {
  return INTERNAL_ROLE_SLUGS.includes(getRoleSlug(user))
}

function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const AVATAR_COLORS = [
  'bg-primary',
  'bg-teal-600',
  'bg-blue-600',
  'bg-amber-600',
  'bg-rose-600',
  'bg-purple-600',
  'bg-emerald-600',
  'bg-orange-600',
]
function getAvatarColor(id) {
  return AVATAR_COLORS[(id ?? 0) % AVATAR_COLORS.length]
}

const STATUS_VARIANT = { active: 'success', invited: 'warning', inactive: 'default' }
const getStatusVariant = s => STATUS_VARIANT[s] ?? 'default'

function formatDate(str) {
  if (!str) return '—'
  return new Date(str).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  setTimeout(() => { toastMessage.value = '' }, 4000)
}

// ─── API calls ───────────────────────────────────────────────────────────────
async function loadSummary() {
  try {
    const res = await api.get('/users/summary')
    summary.value = res.data
  } catch (e) {
    console.error('Failed to load user summary', e)
  }
}

async function loadUsers() {
  loading.value = true
  try {
    const res = await api.get('/users', { params: { _per_page: 500 } })
    users.value = res.data.data ?? []
  } catch (e) {
    console.error('Failed to load users', e)
  } finally {
    loading.value = false
  }
}

async function loadEstates() {
  if (allEstates.value.length) return
  try {
    const res = await api.get('/estates', { params: { _per_page: 500 } })
    allEstates.value = res.data.data ?? []
  } catch (e) {
    console.error('Failed to load estates', e)
  }
}

async function reload() {
  await Promise.all([loadUsers(), loadSummary()])
}

// ─── Tab / search resets page ─────────────────────────────────────────────────
watch([activeTab, searchQuery], () => { currentPage.value = 1 })

// ─── Lifecycle ───────────────────────────────────────────────────────────────
function closeMenus() { openMenuId.value = null }

onMounted(async () => {
  document.addEventListener('click', closeMenus)
  await reload()
})
onUnmounted(() => document.removeEventListener('click', closeMenus))

// ─── Menu ────────────────────────────────────────────────────────────────────
function toggleMenu(id, e) {
  e.stopPropagation()
  openMenuId.value = openMenuId.value === id ? null : id
}

// ─── Invite ──────────────────────────────────────────────────────────────────
function openInviteModal() {
  inviteForm.value = { name: '', email: '', phone: '', category: '', role: '' }
  inviteErrors.value = {}
  showInviteModal.value = true
}

function closeInviteModal() {
  showInviteModal.value = false
}

function onCategoryChange() {
  inviteForm.value.role = ''
}

async function sendInvite() {
  inviteErrors.value = {}
  inviteLoading.value = true
  try {
    await api.post('/users', {
      name:  inviteForm.value.name,
      email: inviteForm.value.email,
      phone: inviteForm.value.phone || null,
      role:  inviteForm.value.role,
    })
    closeInviteModal()
    await reload()
    showToast('Invitation sent — ' + inviteForm.value.name + ' will receive an email to set their password.')
  } catch (e) {
    if (e.response?.status === 422) {
      inviteErrors.value = e.response.data.errors ?? {}
    } else {
      showToast('Failed to send invitation. Please try again.', 'error')
    }
  } finally {
    inviteLoading.value = false
  }
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
function openEditModal(user) {
  editingUser.value = user
  editForm.value = {
    name:   user.name,
    email:  user.email,
    phone:  user.phone ?? '',
    role:   getRoleSlug(user),
    status: user.status,
  }
  editErrors.value = {}
  showEditModal.value = true
  openMenuId.value = null
}

function closeEditModal() {
  showEditModal.value = false
  editingUser.value = null
}

async function saveEdit() {
  editErrors.value = {}
  editLoading.value = true
  try {
    await api.put(`/users/${editingUser.value.id}`, {
      name:   editForm.value.name,
      email:  editForm.value.email,
      phone:  editForm.value.phone || null,
      role:   editForm.value.role,
      status: editForm.value.status,
    })
    closeEditModal()
    await reload()
    showToast('User updated successfully.')
  } catch (e) {
    if (e.response?.status === 422) {
      editErrors.value = e.response.data.errors ?? {}
    } else {
      showToast('Failed to update user. Please try again.', 'error')
    }
  } finally {
    editLoading.value = false
  }
}

// ─── Delete ───────────────────────────────────────────────────────────────────
function openDeleteConfirm(user) {
  deletingUser.value = user
  showDeleteConfirm.value = true
  openMenuId.value = null
}

function closeDeleteConfirm() {
  showDeleteConfirm.value = false
  deletingUser.value = null
}

async function confirmDelete() {
  deleteLoading.value = true
  try {
    await api.delete(`/users/${deletingUser.value.id}`)
    closeDeleteConfirm()
    await reload()
    showToast('User removed successfully.')
  } catch (e) {
    showToast('Failed to remove user. Please try again.', 'error')
  } finally {
    deleteLoading.value = false
  }
}

// ─── Resend invite / password reset ──────────────────────────────────────────
async function resendInvite(user) {
  openMenuId.value = null
  try {
    const res = await api.post(`/users/${user.id}/send-password-reset`)
    showToast(res.data.message ?? 'Invite resent.')
  } catch (e) {
    showToast('Failed to resend invite. Please try again.', 'error')
  }
}

// ─── Estate Assignment ───────────────────────────────────────────────────────
async function openEstatesModal(user) {
  estatesUser.value = user
  selectedEstateIds.value = (user.estates ?? []).map(e => e.id)
  openMenuId.value = null
  showEstatesModal.value = true
  await loadEstates()
}

function closeEstatesModal() {
  showEstatesModal.value = false
  estatesUser.value = null
  selectedEstateIds.value = []
}

function toggleEstate(estateId) {
  const idx = selectedEstateIds.value.indexOf(estateId)
  if (idx === -1) {
    selectedEstateIds.value.push(estateId)
  } else {
    selectedEstateIds.value.splice(idx, 1)
  }
}

async function saveEstates() {
  estatesLoading.value = true
  try {
    await api.put(`/users/${estatesUser.value.id}/estates`, { estate_ids: selectedEstateIds.value })
    closeEstatesModal()
    await reload()
    showToast('Estate assignments updated.')
  } catch (e) {
    showToast('Failed to update estate assignments. Please try again.', 'error')
  } finally {
    estatesLoading.value = false
  }
}

// ─── Navigate to detail ───────────────────────────────────────────────────────
function goToUser(user) {
  router.push({ name: 'user-detail', params: { userId: user.id } })
}

// ─── AppTableToolbar config ───────────────────────────────────────────────────
const USERS_SORT_OPTIONS = [
  { value: 'name_asc',        label: 'Name A–Z' },
  { value: 'name_desc',       label: 'Name Z–A' },
  { value: 'newest',          label: 'Newest first' },
  { value: 'oldest',          label: 'Oldest first' },
  { value: 'last_login_desc', label: 'Last login (recent first)' },
  { value: 'last_login_asc',  label: 'Last login (oldest first)' },
]

const usersFilterFields = computed(() => [
  {
    key: 'status',
    label: 'Status',
    options: STATUS_OPTS,
  },
  {
    key: 'role',
    label: 'Role',
    options: ALL_ROLE_OPTS,
  },
])

function onToolbarUpdate(state) {
  searchQuery.value = state.search ?? ''
}
</script>

<template>
  <div>
  <div class="space-y-6 pb-8">

    <!-- ── Toast ───────────────────────────────────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="toastMessage"
        :class="[
          'fixed top-4 right-4 z-50 max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium',
          toastType === 'error' ? 'bg-danger text-white' : 'bg-success text-white',
        ]"
      >
        {{ toastMessage }}
      </div>
    </Transition>

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Users</h1>
        <p class="text-sm text-muted-foreground mt-1">Manage platform users and their access levels</p>
      </div>
      <AppButton @click="openInviteModal">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        Invite User
      </AppButton>
    </div>

    <!-- ── Stat cards ──────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <AppStatCard label="Total Users" :value="summary.total">
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-primary">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </template>
      </AppStatCard>

      <AppStatCard label="Internal Staff" :value="summary.internal_count">
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-primary">
            <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
            <path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/>
            <path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/>
            <path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
          </svg>
        </template>
      </AppStatCard>

      <AppStatCard label="External Users" :value="summary.external_count">
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-primary">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
        </template>
      </AppStatCard>

      <AppStatCard label="Active Now" :value="summary.active">
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-success">
            <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
          </svg>
        </template>
      </AppStatCard>
    </div>

    <!-- ── Users table card ────────────────────────────────────────────────── -->
    <div class="rounded-lg border bg-card shadow-sm">

      <!-- Section header -->
      <div class="px-6 pt-5 pb-3 flex items-center justify-between">
        <h3 class="font-body font-semibold text-lg flex items-center gap-2 text-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          Users
        </h3>
      </div>

      <!-- Toolbar row -->
      <div class="px-6 pb-4">
        <AppTableToolbar
          search-placeholder="Search users..."
          :filter-fields="usersFilterFields"
          :sort-options="USERS_SORT_OPTIONS"
          storage-key="users"
          date-range-context="Joined"
          @update:state="onToolbarUpdate"
        />
      </div>

      <!-- Tab filter -->
      <div class="px-6 pb-3 border-b border-border">
        <AppTabFilter :tabs="tabs" v-model="activeTab" />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border text-[11px] text-muted-foreground uppercase tracking-wider">
              <th class="text-left py-3 px-4 font-medium">User</th>
              <th class="text-left py-3 px-4 font-medium">Role</th>
              <th class="text-left py-3 px-4 font-medium">Assigned Estates</th>
              <th class="text-left py-3 px-4 font-medium">Status</th>
              <th class="text-left py-3 px-4 font-medium">Last Login</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>

          <tbody>
            <!-- Skeleton rows -->
            <template v-if="loading">
              <tr v-for="i in 5" :key="'sk-' + i" class="border-b border-border animate-pulse">
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-muted shrink-0"></div>
                    <div class="space-y-1.5">
                      <div class="h-3 w-32 bg-muted rounded"></div>
                      <div class="h-2.5 w-48 bg-muted rounded"></div>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-4"><div class="h-5 w-28 bg-muted rounded"></div></td>
                <td class="py-3 px-4"><div class="h-5 w-24 bg-muted rounded"></div></td>
                <td class="py-3 px-4"><div class="h-5 w-14 bg-muted rounded-full"></div></td>
                <td class="py-3 px-4"><div class="h-3 w-20 bg-muted rounded"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-6 w-6 bg-muted rounded ml-auto"></div></td>
              </tr>
            </template>

            <!-- Real rows -->
            <template v-else>
              <tr
                v-for="user in paginatedUsers"
                :key="user.id"
                class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                @click="goToUser(user)"
              >
                <!-- USER -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div
                      :class="['w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shrink-0', getAvatarColor(user.id)]"
                    >
                      {{ getInitials(user.name) }}
                    </div>
                    <div>
                      <p class="font-medium text-foreground">{{ user.name }}</p>
                      <div class="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                        <span class="flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                            <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                          </svg>
                          {{ user.email }}
                        </span>
                        <span v-if="user.phone" class="flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                          </svg>
                          {{ user.phone }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- ROLE -->
                <td class="py-3 px-4">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border', getRoleBadgeClass(getRoleSlug(user))]">
                    {{ getRoleDisplayName(user) || 'No role' }}
                  </span>
                </td>

                <!-- ASSIGNED ESTATES -->
                <td class="py-3 px-4">
                  <template v-if="isInternalUser(user)">
                    <span v-if="!user.estates?.length" class="text-xs text-muted-foreground">—</span>
                    <div v-else class="flex flex-wrap gap-1">
                      <span
                        v-for="estate in user.estates.slice(0, 2)"
                        :key="estate.id"
                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-muted text-foreground border border-border"
                      >{{ estate.name }}</span>
                      <span
                        v-if="user.estates.length > 2"
                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground border border-border"
                      >+{{ user.estates.length - 2 }} more</span>
                    </div>
                  </template>
                  <span v-else class="text-xs text-muted-foreground">—</span>
                </td>

                <!-- STATUS -->
                <td class="py-3 px-4">
                  <AppBadge :variant="getStatusVariant(user.status)" bordered size="sm" class="capitalize">
                    {{ user.status }}
                  </AppBadge>
                </td>

                <!-- LAST LOGIN -->
                <td class="py-3 px-4 text-xs text-muted-foreground">
                  {{ formatDate(user.last_login_at) }}
                </td>

                <!-- ACTIONS -->
                <td class="py-3 px-4 text-right" @click.stop>
                  <div class="relative inline-block">
                    <button
                      type="button"
                      class="p-1.5 rounded hover:bg-muted transition-colors"
                      @click="toggleMenu(user.id, $event)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
                        <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
                      </svg>
                    </button>

                    <Transition
                      enter-active-class="transition duration-100 ease-out"
                      enter-from-class="opacity-0 scale-95"
                      enter-to-class="opacity-100 scale-100"
                      leave-active-class="transition duration-75 ease-in"
                      leave-from-class="opacity-100 scale-100"
                      leave-to-class="opacity-0 scale-95"
                    >
                      <div
                        v-if="openMenuId === user.id"
                        class="absolute right-0 top-full mt-1 z-20 w-44 rounded-lg border border-border bg-card shadow-lg py-1"
                        @click.stop
                      >
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-xs text-foreground hover:bg-muted transition-colors" @click="goToUser(user)">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                          </svg>
                          View User
                        </button>
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-xs text-foreground hover:bg-muted transition-colors" @click="openEditModal(user)">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                          </svg>
                          Edit User
                        </button>
                        <button
                          v-if="isInternalUser(user)"
                          type="button"
                          class="flex items-center gap-2 w-full px-3 py-2 text-xs text-foreground hover:bg-muted transition-colors"
                          @click="openEstatesModal(user)"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                          </svg>
                          Manage Estates
                        </button>
                        <button
                          v-if="user.status === 'invited'"
                          type="button"
                          class="flex items-center gap-2 w-full px-3 py-2 text-xs text-foreground hover:bg-muted transition-colors"
                          @click="resendInvite(user)"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-muted-foreground">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                          </svg>
                          Resend Invite
                        </button>
                        <div class="border-t border-border my-1"></div>
                        <button
                          type="button"
                          class="flex items-center gap-2 w-full px-3 py-2 text-xs text-danger hover:bg-muted transition-colors"
                          @click="openDeleteConfirm(user)"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                          </svg>
                          Remove User
                        </button>
                      </div>
                    </Transition>
                  </div>
                </td>
              </tr>

              <!-- Empty state -->
              <tr v-if="!paginatedUsers.length">
                <td colspan="6" class="py-16 text-center">
                  <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                      <circle cx="9" cy="7" r="4"/>
                      <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-foreground">No users found</p>
                  <p class="text-xs text-muted-foreground mt-1">Try adjusting your search or invite a new user.</p>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && filteredUsers.length > 0" class="px-4 py-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
        <span>Showing {{ Math.min((currentPage - 1) * PER_PAGE + 1, filteredUsers.length) }}–{{ Math.min(currentPage * PER_PAGE, filteredUsers.length) }} of {{ filteredUsers.length }} users</span>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="px-2.5 py-1 rounded border border-border hover:bg-muted transition-colors disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--"
          >Previous</button>
          <button
            v-for="p in lastPage"
            :key="p"
            type="button"
            :class="['px-2.5 py-1 rounded border transition-colors', p === currentPage ? 'bg-primary text-primary-foreground border-primary font-medium' : 'border-border hover:bg-muted']"
            @click="currentPage = p"
          >{{ p }}</button>
          <button
            type="button"
            class="px-2.5 py-1 rounded border border-border hover:bg-muted transition-colors disabled:opacity-40"
            :disabled="currentPage === lastPage"
            @click="currentPage++"
          >Next</button>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Invite User modal ──────────────────────────────────────────────────── -->
  <AppModal :show="showInviteModal" title="Invite User" size="md" @close="closeInviteModal">
    <div class="space-y-4">
      <AppInput
        v-model="inviteForm.name"
        label="Full Name"
        placeholder="e.g. John Smith"
        required
        :error="inviteErrors.name?.[0]"
      />
      <AppInput
        v-model="inviteForm.email"
        label="Email Address"
        type="email"
        placeholder="e.g. john@email.com"
        required
        :error="inviteErrors.email?.[0]"
      />
      <AppInput
        v-model="inviteForm.phone"
        label="Phone Number"
        placeholder="e.g. +27 82 000 0000"
        :error="inviteErrors.phone?.[0]"
      />

      <AppSelect
        v-model="inviteForm.category"
        label="User Category"
        :options="CATEGORY_OPTS"
        placeholder="Select category"
        required
        @change="onCategoryChange"
      />

      <AppSelect
        v-if="inviteForm.category"
        v-model="inviteForm.role"
        label="Role"
        :options="availableRoleOpts"
        placeholder="Select role"
        required
        :error="inviteErrors.role?.[0]"
      />
    </div>

    <template #footer>
      <AppButton variant="outline" @click="closeInviteModal">Cancel</AppButton>
      <AppButton :loading="inviteLoading" @click="sendInvite">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
        </svg>
        Send Invitation
      </AppButton>
    </template>
  </AppModal>

  <!-- ── Edit User modal ────────────────────────────────────────────────────── -->
  <AppModal :show="showEditModal" :title="`Edit User — ${editingUser?.name ?? ''}`" size="md" @close="closeEditModal">
    <div class="space-y-4">
      <AppInput
        v-model="editForm.name"
        label="Full Name"
        placeholder="Full name"
        required
        :error="editErrors.name?.[0]"
      />
      <AppInput
        v-model="editForm.email"
        label="Email Address"
        type="email"
        placeholder="Email address"
        required
        :error="editErrors.email?.[0]"
      />
      <AppInput
        v-model="editForm.phone"
        label="Phone Number"
        placeholder="e.g. +27 82 000 0000"
        :error="editErrors.phone?.[0]"
      />
      <AppSelect
        v-model="editForm.role"
        label="Role"
        :options="ALL_ROLE_OPTS"
        placeholder="Select role"
        required
        :error="editErrors.role?.[0]"
      />
      <AppSelect
        v-model="editForm.status"
        label="Status"
        :options="STATUS_OPTS"
        placeholder="Select status"
        required
        :error="editErrors.status?.[0]"
      />
    </div>

    <template #footer>
      <AppButton variant="outline" @click="closeEditModal">Cancel</AppButton>
      <AppButton :loading="editLoading" @click="saveEdit">Save Changes</AppButton>
    </template>
  </AppModal>

  <!-- ── Delete confirmation modal ─────────────────────────────────────────── -->
  <AppModal :show="showDeleteConfirm" title="Remove User" size="sm" @close="closeDeleteConfirm">
    <p class="text-sm text-foreground">
      Are you sure you want to remove <span class="font-semibold">{{ deletingUser?.name }}</span>? This action cannot be undone.
    </p>
    <template #footer>
      <AppButton variant="outline" @click="closeDeleteConfirm">Cancel</AppButton>
      <AppButton variant="danger" :loading="deleteLoading" @click="confirmDelete">Remove User</AppButton>
    </template>
  </AppModal>

  <!-- ── Manage Estates modal ───────────────────────────────────────────────── -->
  <AppModal
    :show="showEstatesModal"
    :title="`Manage Estates — ${estatesUser?.name ?? ''}`"
    size="md"
    @close="closeEstatesModal"
  >
    <div class="space-y-3">
      <p class="text-sm text-muted-foreground">
        Select which estates this staff member is assigned to. They will be able to manage and view data for their assigned estates.
      </p>

      <!-- Estate list -->
      <div class="border border-border rounded-lg divide-y divide-border max-h-80 overflow-y-auto">
        <!-- Loading state -->
        <template v-if="!allEstates.length">
          <div v-for="n in 3" :key="n" class="flex items-center gap-3 px-4 py-3 animate-pulse">
            <div class="w-4 h-4 rounded bg-muted shrink-0"></div>
            <div class="space-y-1.5 flex-1">
              <div class="h-3 w-32 bg-muted rounded"></div>
              <div class="h-2.5 w-20 bg-muted rounded"></div>
            </div>
          </div>
        </template>

        <!-- Estate rows -->
        <label
          v-for="estate in allEstates"
          :key="estate.id"
          class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-muted/40 transition-colors"
        >
          <input
            type="checkbox"
            :value="estate.id"
            :checked="selectedEstateIds.includes(estate.id)"
            class="w-4 h-4 rounded border-border text-primary accent-primary cursor-pointer"
            @change="toggleEstate(estate.id)"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-foreground truncate">{{ estate.name }}</p>
            <p class="text-xs text-muted-foreground truncate">{{ estate.address || 'No address' }}</p>
          </div>
          <span
            v-if="selectedEstateIds.includes(estate.id)"
            class="shrink-0 text-[11px] font-medium text-primary"
          >Assigned</span>
        </label>

        <!-- Empty state -->
        <div v-if="allEstates.length === 0 && !estatesLoading" class="px-4 py-8 text-center">
          <p class="text-sm text-muted-foreground">No estates found.</p>
        </div>
      </div>

      <!-- Summary -->
      <p class="text-xs text-muted-foreground">
        {{ selectedEstateIds.length }} estate{{ selectedEstateIds.length !== 1 ? 's' : '' }} selected
      </p>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="closeEstatesModal">Cancel</AppButton>
      <AppButton :loading="estatesLoading" @click="saveEstates">Save Assignments</AppButton>
    </template>
  </AppModal>

  </div>
</template>

