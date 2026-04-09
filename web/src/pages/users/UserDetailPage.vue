<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi.js'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge  from '@/components/common/AppBadge.vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppInput  from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import { useBack } from '@/composables/useBack.js'

const route  = useRoute()
const router = useRouter()
const { goBack } = useBack({ name: 'users' })

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

const ALL_ROLE_OPTS = [
  { value: 'company-admin',        label: 'Admin' },
  { value: 'portfolio-manager',    label: 'Portfolio Manager' },
  { value: 'financial-controller', label: 'Financial Controller' },
  { value: 'portfolio-assistant',  label: 'Portfolio Assistant' },
  { value: 'trustee',              label: 'Trustee / Director' },
  { value: 'owner',                label: 'Owner' },
  { value: 'tenant',               label: 'Tenant' },
  { value: 'contractor',           label: 'Contractor' },
]

const STATUS_OPTS = [
  { value: 'active',   label: 'Active' },
  { value: 'invited',  label: 'Invited' },
  { value: 'inactive', label: 'Inactive' },
]

// ─── State ───────────────────────────────────────────────────────────────────
const user    = ref(null)
const loading = ref(true)
const error   = ref(null)

// Edit modal
const showEditModal = ref(false)
const editLoading   = ref(false)
const editErrors    = ref({})
const editForm      = ref({ name: '', email: '', phone: '', role: '', status: '' })

// Delete modal
const showDeleteModal = ref(false)
const deleteLoading   = ref(false)

// Password reset
const resetLoading = ref(false)
const resetSent    = ref(false)

// Toast
const toastMessage = ref('')
const toastType    = ref('success')

// ─── Computed ─────────────────────────────────────────────────────────────────
const roleSlug = computed(() => user.value?.roles?.[0]?.name ?? '')
const roleName = computed(() => ROLE_DISPLAY[roleSlug.value] ?? roleSlug.value ?? 'No role')

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
const roleBadgeClass = computed(() => ROLE_BADGE_STYLES[roleSlug.value] ?? 'bg-muted text-muted-foreground border-border')

const STATUS_VARIANT = { active: 'success', invited: 'warning', inactive: 'default' }
const statusVariant  = computed(() => STATUS_VARIANT[user.value?.status] ?? 'default')

const AVATAR_COLORS = ['bg-primary','bg-teal-600','bg-blue-600','bg-amber-600','bg-rose-600','bg-purple-600','bg-emerald-600','bg-orange-600']
const avatarColor = computed(() => AVATAR_COLORS[(user.value?.id ?? 0) % AVATAR_COLORS.length])

// ─── Helpers ──────────────────────────────────────────────────────────────────
function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function formatDate(str) {
  if (!str) return '—'
  return new Date(str).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(str) {
  if (!str) return '—'
  return new Date(str).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value    = type
  setTimeout(() => { toastMessage.value = '' }, 4000)
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadUser() {
  loading.value = true
  error.value   = null
  try {
    const res = await api.get(`/users/${route.params.userId}`)
    user.value = res.data.data
  } catch (e) {
    error.value = e.response?.status === 404 ? 'User not found.' : 'Failed to load user.'
  } finally {
    loading.value = false
  }
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
function openEditModal() {
  editForm.value = {
    name:   user.value.name,
    email:  user.value.email,
    phone:  user.value.phone ?? '',
    role:   roleSlug.value,
    status: user.value.status,
  }
  editErrors.value = {}
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
}

async function saveEdit() {
  editErrors.value = {}
  editLoading.value = true
  try {
    await api.put(`/users/${user.value.id}`, {
      name:   editForm.value.name,
      email:  editForm.value.email,
      phone:  editForm.value.phone || null,
      role:   editForm.value.role,
      status: editForm.value.status,
    })
    closeEditModal()
    await loadUser()
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
async function confirmDelete() {
  deleteLoading.value = true
  try {
    await api.delete(`/users/${user.value.id}`)
    router.push({ name: 'users' })
  } catch (e) {
    showToast('Failed to remove user. Please try again.', 'error')
    showDeleteModal.value = false
  } finally {
    deleteLoading.value = false
  }
}

// ─── Password reset ───────────────────────────────────────────────────────────
async function sendPasswordReset() {
  resetLoading.value = true
  resetSent.value    = false
  try {
    const res = await api.post(`/users/${user.value.id}/send-password-reset`)
    resetSent.value = res.data.success
    showToast(res.data.message ?? 'Password reset link sent.')
  } catch (e) {
    showToast('Failed to send password reset link. Please try again.', 'error')
  } finally {
    resetLoading.value = false
  }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(loadUser)
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Toast ──────────────────────────────────────────────────────────── -->
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
        :class="['fixed top-4 right-4 z-50 max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium', toastType === 'error' ? 'bg-danger text-white' : 'bg-success text-white']"
      >
        {{ toastMessage }}
      </div>
    </Transition>

    <!-- ── Loading skeleton ───────────────────────────────────────────────── -->
    <template v-if="loading">
      <div class="animate-pulse space-y-6">
        <div class="flex items-center gap-3">
          <div class="w-6 h-6 bg-muted rounded"></div>
          <div class="h-4 w-24 bg-muted rounded"></div>
        </div>
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-muted"></div>
          <div class="space-y-2">
            <div class="h-6 w-48 bg-muted rounded"></div>
            <div class="h-4 w-32 bg-muted rounded"></div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div class="h-32 bg-muted rounded-lg"></div>
          <div class="h-32 bg-muted rounded-lg col-span-2"></div>
        </div>
      </div>
    </template>

    <!-- ── Error ──────────────────────────────────────────────────────────── -->
    <template v-else-if="error">
      <div class="text-center py-16">
        <p class="text-danger font-medium">{{ error }}</p>
        <AppButton variant="outline" class="mt-4" @click="goBack()">Back to Users</AppButton>
      </div>
    </template>

    <!-- ── Content ────────────────────────────────────────────────────────── -->
    <template v-else-if="user">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <!-- Back + identity -->
        <div class="flex items-center gap-4">
          <button
            type="button"
            class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
            @click="goBack()"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="m15 18-6-6 6-6"/>
            </svg>
            Users
          </button>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2 flex-shrink-0">
          <AppButton variant="outline" @click="openEditModal">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
            Edit User
          </AppButton>
          <AppButton variant="danger" @click="showDeleteModal = true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
            </svg>
            Remove User
          </AppButton>
        </div>
      </div>

      <!-- User identity banner -->
      <div class="flex items-center gap-4 p-4 rounded-lg border bg-card shadow-sm">
        <div :class="['w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white shrink-0', avatarColor]">
          {{ getInitials(user.name) }}
        </div>
        <div>
          <h1 class="font-body font-bold text-2xl text-foreground">{{ user.name }}</h1>
          <div class="flex items-center gap-2 mt-1 flex-wrap">
            <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border', roleBadgeClass]">
              {{ roleName }}
            </span>
            <AppBadge :variant="statusVariant" bordered size="sm" class="capitalize">
              {{ user.status }}
            </AppBadge>
          </div>
        </div>
      </div>

      <!-- Two-column layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Left column ─────────────────────────────────────────────── -->
        <div class="space-y-4">

          <!-- Contact details card -->
          <div class="rounded-lg border bg-card shadow-sm p-4 space-y-3">
            <h2 class="font-semibold text-sm text-foreground">Contact Details</h2>
            <div class="space-y-2.5">
              <div class="flex items-start gap-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground mt-0.5 shrink-0">
                  <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
                <div>
                  <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Email</p>
                  <p class="text-sm text-foreground">{{ user.email }}</p>
                </div>
              </div>
              <div class="flex items-start gap-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground mt-0.5 shrink-0">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                <div>
                  <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Phone</p>
                  <p class="text-sm text-foreground">{{ user.phone ?? '—' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Account details card -->
          <div class="rounded-lg border bg-card shadow-sm p-4 space-y-3">
            <h2 class="font-semibold text-sm text-foreground">Account Details</h2>
            <div class="space-y-2.5">
              <div>
                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Role</p>
                <p class="text-sm text-foreground mt-0.5">{{ roleName }}</p>
              </div>
              <div>
                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Status</p>
                <AppBadge :variant="statusVariant" bordered size="sm" class="capitalize mt-0.5">{{ user.status }}</AppBadge>
              </div>
              <div>
                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Last Login</p>
                <p class="text-sm text-foreground mt-0.5">{{ formatDateTime(user.last_login_at) }}</p>
              </div>
              <div>
                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">Member Since</p>
                <p class="text-sm text-foreground mt-0.5">{{ formatDate(user.created_at) }}</p>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Right column ────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Password reset card -->
          <div class="rounded-lg border bg-card shadow-sm p-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="font-semibold text-sm text-foreground">Password Reset</h2>
                <p class="text-xs text-muted-foreground mt-1">
                  Send a password reset link to <strong>{{ user.email }}</strong>. The user will receive an email with instructions to set a new password.
                </p>
                <p v-if="resetSent" class="text-xs text-success mt-2 flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                  </svg>
                  Reset link sent — check {{ user.email }}
                </p>
              </div>
              <AppButton variant="outline" :loading="resetLoading" @click="sendPasswordReset" class="shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Send Reset Link
              </AppButton>
            </div>
          </div>

          <!-- Danger zone card -->
          <div class="rounded-lg border border-danger/20 bg-danger/5 p-4">
            <h2 class="font-semibold text-sm text-danger">Danger Zone</h2>
            <p class="text-xs text-muted-foreground mt-1">
              Permanently remove this user from the platform. This action cannot be undone. All data associated with this user will be deleted.
            </p>
            <div class="mt-3">
              <AppButton variant="danger" @click="showDeleteModal = true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                </svg>
                Remove User
              </AppButton>
            </div>
          </div>

        </div>
      </div>
    </template>

  </div>

  <!-- ── Edit User modal ────────────────────────────────────────────────────── -->
  <AppModal :show="showEditModal" :title="`Edit — ${user?.name ?? ''}`" size="md" @close="closeEditModal">
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
        :error="editErrors.role?.[0]"
      />
      <AppSelect
        v-model="editForm.status"
        label="Status"
        :options="STATUS_OPTS"
        placeholder="Select status"
        :error="editErrors.status?.[0]"
      />
    </div>
    <template #footer>
      <AppButton variant="outline" @click="closeEditModal">Cancel</AppButton>
      <AppButton :loading="editLoading" @click="saveEdit">Save Changes</AppButton>
    </template>
  </AppModal>

  <!-- ── Delete confirmation modal ─────────────────────────────────────────── -->
  <AppModal :show="showDeleteModal" title="Remove User" size="sm" @close="showDeleteModal = false">
    <p class="text-sm text-foreground">
      Are you sure you want to remove <span class="font-semibold">{{ user?.name }}</span>? This action cannot be undone.
    </p>
    <template #footer>
      <AppButton variant="outline" @click="showDeleteModal = false">Cancel</AppButton>
      <AppButton variant="danger" :loading="deleteLoading" @click="confirmDelete">Remove User</AppButton>
    </template>
  </AppModal>
</template>
