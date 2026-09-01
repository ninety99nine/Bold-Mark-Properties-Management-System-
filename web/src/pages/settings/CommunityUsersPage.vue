<!--
  CommunityUsersPage — WeConnectU "Setup → Users" clone (BoldMark branded).

  Exact like-for-like of the WeConnectU community Users screen:
   · Select Directors/Trustees   → Update Directors/Trustees
   · Select Trustees/Directors to approve payments → Update Payment Authorisations
     + Single / Two / Auto authorisation radios
   · Filter Users dropdown + table (verified check, name→edit, email, type, row menu)
   · Add / Edit User modal
  Community-scoped via the community store.

  GET/POST/PUT/DELETE  /communities/{id}/users
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import GroupedMultiSelect from '@/components/common/GroupedMultiSelect.vue'

const { success, error } = useToast()
const community = useCommunityStore()

// ── Options (mirror WeConnectU) ─────────────────────────────────────────
const USER_TYPES = [
  { value: 'owner',            label: 'Owner' },
  { value: 'complex_manager',  label: 'Complex Manager' },
  { value: 'director_trustee', label: 'Director/Trustee' },
]
const FILTER_OPTIONS = [
  { value: 'all',                label: 'All Users' },
  { value: 'complex_managers',   label: 'Complex Managers' },
  { value: 'owners',             label: 'Owners' },
  { value: 'directors_trustees', label: 'Directors/Trustees' },
]

// ── State ───────────────────────────────────────────────────────────────
const members = ref([])
const loading = ref(true)
const filter  = ref('all')

const dtSelected   = ref([])   // director/trustee member ids
const paSelected   = ref([])   // payment-authoriser member ids
const authMode     = ref('single')
const savingDt     = ref(false)
const savingPa     = ref(false)

// ── Load ────────────────────────────────────────────────────────────────
async function load() {
  const id = community.selectedId
  if (!id) { loading.value = false; return }
  loading.value = true
  try {
    const [membersRes, communityRes] = await Promise.all([
      api.get(`/communities/${id}/users`, { params: { filter: filter.value } }),
      api.get(`/communities/${id}`),
    ])
    members.value = membersRes.data.data ?? []
    // Seed the two multi-selects from live data
    dtSelected.value = members.value.filter(m => m.is_director_trustee || m.user_type === 'director_trustee').map(m => m.id)
    paSelected.value = members.value.filter(m => m.is_payment_authoriser).map(m => m.id)
    authMode.value   = communityRes.data.data?.payment_authorisation_mode ?? 'single'
  } catch (e) {
    error('Failed to load users.')
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => community.selectedId, load)
watch(filter, load)

// ── Multi-select option groups ──────────────────────────────────────────
// Directors/Trustees picker: current DTs (checked) + non-DT owners.
const directorTrusteeGroups = computed(() => {
  const isDt = m => m.is_director_trustee || m.user_type === 'director_trustee'
  const current = members.value.filter(isDt)
  const nonDt   = members.value.filter(m => !isDt(m) && m.user_type === 'owner')
  const groups = []
  if (current.length) groups.push({ label: 'Current Directors/Trustees', options: current.map(m => ({ value: m.id, label: m.name })) })
  if (nonDt.length)   groups.push({ label: 'Non Director/Trustee Owners', options: nonDt.map(m => ({ value: m.id, label: m.name })) })
  return groups
})
// Payment authorisers picker: only directors/trustees are eligible.
const paymentAuthGroups = computed(() => {
  const dts = members.value.filter(m => m.is_director_trustee || m.user_type === 'director_trustee')
  return [{ options: dts.map(m => ({ value: m.id, label: m.name })) }]
})

async function updateDirectorsTrustees() {
  savingDt.value = true
  try {
    await api.put(`/communities/${community.selectedId}/users/directors-trustees`, { member_ids: dtSelected.value })
    success('Directors/Trustees updated.')
    await load()
  } catch (e) { error('Could not update Directors/Trustees.') } finally { savingDt.value = false }
}
async function updatePaymentAuthorisations() {
  savingPa.value = true
  try {
    await api.put(`/communities/${community.selectedId}/users/payment-authorisations`, { member_ids: paSelected.value, mode: authMode.value })
    success('Payment authorisations updated.')
    await load()
  } catch (e) { error('Could not update payment authorisations.') } finally { savingPa.value = false }
}

// ── Row menu ────────────────────────────────────────────────────────────
const menuId = ref(null)
function toggleMenu(id) { menuId.value = menuId.value === id ? null : id }

async function resetPassword(m) {
  menuId.value = null
  try {
    const { data } = await api.post(`/communities/${community.selectedId}/users/${m.id}/reset-password`)
    success(data.message ?? 'Password reset link sent.')
  } catch (e) { error('Could not reset password.') }
}
async function removeUser(m) {
  menuId.value = null
  if (!confirm(`Remove ${m.name} from this community's users?`)) return
  try {
    await api.delete(`/communities/${community.selectedId}/users/${m.id}`)
    success('User removed.')
    await load()
  } catch (e) { error('Could not remove user.') }
}

// ── Add / Edit modal ────────────────────────────────────────────────────
const showModal = ref(false)
const modalMode = ref('add')
const editingId = ref(null)
const saving    = ref(false)
const saveError = ref(null)
const form = ref({ name: '', email: '', cellphone: '', user_type: 'owner', is_director_trustee: false })

function openAdd() {
  modalMode.value = 'add'
  editingId.value = null
  form.value = { name: '', email: '', cellphone: '', user_type: 'owner', is_director_trustee: false }
  saveError.value = null
  showModal.value = true
}
function openEdit(m) {
  menuId.value = null
  modalMode.value = 'edit'
  editingId.value = m.id
  form.value = {
    name: m.name || '', email: m.email || '', cellphone: m.cellphone || '',
    user_type: m.user_type || 'owner', is_director_trustee: !!m.is_director_trustee,
  }
  saveError.value = null
  showModal.value = true
}
async function saveUser() {
  if (!form.value.name.trim()) { saveError.value = 'Please enter a name.'; return }
  saving.value = true
  saveError.value = null
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email || null,
      cellphone: form.value.cellphone || null,
      user_type: form.value.user_type,
      is_director_trustee: form.value.is_director_trustee,
    }
    if (modalMode.value === 'add') {
      await api.post(`/communities/${community.selectedId}/users`, payload)
    } else {
      await api.put(`/communities/${community.selectedId}/users/${editingId.value}`, payload)
    }
    showModal.value = false
    success(modalMode.value === 'add' ? 'User added.' : 'User updated.')
    await load()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save user.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- Header / breadcrumb -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">
          Setup <span class="mx-1">→</span> <span class="text-foreground font-medium">Users</span>
        </p>
        <h1 class="font-body font-bold text-2xl text-foreground mt-1">Users</h1>
        <p class="text-sm text-muted-foreground">
          Manage community users, directors/trustees and payment authorisers
          <template v-if="community.selected"> · {{ community.selected.name }}</template>
        </p>
      </div>
      <button
        v-if="community.selectedId"
        type="button"
        class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity shrink-0"
        @click="openAdd"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add new User
      </button>
    </div>

    <!-- No community -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to manage its users.
    </div>

    <div v-else class="rounded-lg border border-border bg-white p-6 space-y-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <template v-else>
        <!-- ── Directors/Trustees ─────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-end gap-3">
          <div class="w-full md:w-96">
            <label class="block text-sm text-muted-foreground mb-1.5">Select Directors/Trustees</label>
            <GroupedMultiSelect v-model="dtSelected" :groups="directorTrusteeGroups" />
          </div>
          <button type="button" :disabled="savingDt" class="inline-flex items-center rounded-md bg-navy-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60 h-11" @click="updateDirectorsTrustees">
            {{ savingDt ? 'Updating…' : 'Update Directors/Trustees' }}
          </button>
        </div>

        <!-- ── Payment authorisers ────────────────────────────────────── -->
        <div class="space-y-3">
          <div class="flex flex-col md:flex-row md:items-end gap-3">
            <div class="w-full md:w-96">
              <label class="block text-sm text-muted-foreground mb-1.5">Select Trustees/Directors to approve payments</label>
              <GroupedMultiSelect v-model="paSelected" :groups="paymentAuthGroups" />
            </div>
            <button type="button" :disabled="savingPa" class="inline-flex items-center rounded-md bg-navy-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60 h-11" @click="updatePaymentAuthorisations">
              {{ savingPa ? 'Updating…' : 'Update Payment Authorisations' }}
            </button>
          </div>
          <div class="flex flex-wrap items-center gap-x-10 gap-y-2">
            <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
              <input type="radio" value="single" v-model="authMode" class="accent-accent w-4 h-4" /> Single authorization
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
              <input type="radio" value="two" v-model="authMode" class="accent-accent w-4 h-4" /> Two authorizations
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
              <input type="radio" value="auto" v-model="authMode" class="accent-accent w-4 h-4" /> Auto authorisation
            </label>
          </div>
        </div>

        <hr class="border-border" />

        <!-- ── Filter + table ─────────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-center gap-3">
          <label class="text-sm text-muted-foreground md:w-24">Filter Users</label>
          <div class="w-full md:w-96">
            <AppSelect v-model="filter" :options="FILTER_OPTIONS" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">User</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Email</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Type</th>
                <th class="py-2.5 px-3 w-12"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in members" :key="m.id" class="border-b border-border/60 odd:bg-muted/20 hover:bg-muted/40">
                <td class="py-2.5 px-3">
                  <span class="inline-flex items-center gap-2">
                    <span v-if="m.is_verified" class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-500 text-white shrink-0" title="User if verified">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" class="w-2.5 h-2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <button type="button" class="text-[#2f6fb0] hover:underline" title="Edit user" @click="openEdit(m)">{{ m.name }}</button>
                  </span>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ m.email || '—' }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ m.type_label }}</td>
                <td class="py-2.5 px-3 relative text-right">
                  <button type="button" class="text-muted-foreground hover:text-foreground" title="Menu Options" @click="toggleMenu(m.id)">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="w-5 h-5"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
                  </button>
                  <div v-if="menuId === m.id" class="absolute right-3 top-9 z-20 w-40 rounded-md border border-border bg-white shadow-lg py-1">
                    <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="openEdit(m)">Edit User</button>
                    <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="resetPassword(m)">Reset Password</button>
                    <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-destructive hover:bg-muted" @click="removeUser(m)">Remove User</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!members.length">
                <td colspan="4" class="py-8 text-center text-sm text-muted-foreground">No users.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- ── Add / Edit User modal ────────────────────────────────────────── -->
    <AppModal :show="showModal" size="md" @close="showModal = false">
      <template #header>
        <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">{{ modalMode === 'edit' ? 'Edit User' : 'Add User' }}</h3>
      </template>
      <div class="space-y-4">
        <div v-if="saveError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ saveError }}</div>

        <div class="grid grid-cols-[110px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">Name:</label>
          <input v-model="form.name" type="text" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
        </div>
        <div class="grid grid-cols-[110px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">Email:</label>
          <input v-model="form.email" type="email" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
        </div>
        <div class="grid grid-cols-[110px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">Cellphone:</label>
          <input v-model="form.cellphone" type="text" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
        </div>
        <div class="grid grid-cols-[110px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">User Type:</label>
          <AppSelect v-model="form.user_type" :options="USER_TYPES" />
        </div>
        <div class="grid grid-cols-[110px_1fr] items-center gap-3">
          <label class="text-sm text-muted-foreground">Director/Trustee:</label>
          <input v-model="form.is_director_trustee" type="checkbox" class="w-5 h-5 rounded accent-[#2f6fb0]" />
        </div>

        <!-- Save sits directly under the fields (WeConnectU layout) -->
        <div class="grid grid-cols-[110px_1fr] gap-3 pt-1">
          <span></span>
          <button type="button" :disabled="saving" class="justify-self-start rounded-md bg-accent px-6 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="saveUser">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </AppModal>
  </div>
</template>
