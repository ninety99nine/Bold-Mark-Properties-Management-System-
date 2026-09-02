<template>
  <div class="p-6">
    <h1 class="mb-5 font-body text-2xl font-bold text-foreground">Setup Suppliers</h1>

    <!-- No community selected -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to manage its suppliers.
      <div class="mt-4">
        <AppButton variant="secondary" @click="$router.push('/communities')">Choose a community</AppButton>
      </div>
    </div>

    <template v-else>
      <!-- Tabs (WeConnectU folder style) -->
      <div class="flex items-end gap-1">
        <button type="button" :class="tabClass('list')" @click="setTab('list')">Supplier List</button>
        <button type="button" :class="tabClass('groups')" @click="setTab('groups')">Supplier Groups</button>
      </div>

      <!-- ============================ SUPPLIER LIST ============================ -->
      <div v-show="activeTab === 'list'" class="rounded-lg rounded-tl-none border border-border bg-white p-5">
        <!-- Toolbar -->
        <div class="mb-4 flex items-start justify-between gap-4">
          <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#2f6fb0] hover:underline" @click="showAddForm = !showAddForm">
            <IconPlusCircle class="h-4 w-4" /> Add new Supplier
          </button>
          <div class="flex gap-2">
            <AppButton variant="secondary" :loading="exporting" @click="exportSuppliers">
              <span class="inline-flex items-center gap-2"><IconDownload class="h-4 w-4" /> Export Suppliers</span>
            </AppButton>
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-emerald-700"
              @click="uploadModal = true"
            >
              <IconUpload class="h-4 w-4" /> Upload Suppliers
            </button>
          </div>
        </div>

        <!-- Inline add form -->
        <div v-if="showAddForm" class="mb-6 border-b border-border">
          <SupplierForm
            :community-id="communityId"
            :options="options"
            @saved="onSupplierSaved"
          />
        </div>

        <!-- DataTable-style controls -->
        <div class="mb-3 flex items-center justify-between text-sm">
          <div class="flex items-center gap-2">
            <div class="w-24">
              <AppSelect v-model="perPage" :options="perPageOptions" />
            </div>
            <span class="text-muted-foreground">records per page</span>
          </div>
          <label class="flex items-center gap-2">
            <span class="text-muted-foreground">Search:</span>
            <input
              v-model="search"
              type="text"
              class="h-9 w-56 rounded-md border border-border px-3 focus:border-navy focus:outline-none"
              @input="onSearch"
            />
          </label>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Code</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Supplier</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Reference</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Group</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Payment Type</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingSuppliers">
                <td colspan="6" class="border border-border px-4 py-8 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="!suppliers.length">
                <td colspan="6" class="border border-border px-4 py-8 text-center text-muted-foreground">No data available in table</td>
              </tr>
              <tr
                v-for="s in suppliers"
                :key="s.id"
                class="hover:bg-muted/40"
                :class="!s.is_active && 'opacity-50'"
              >
                <td class="border border-border px-4 py-3 align-middle">
                  <button class="text-[#2f6fb0] hover:underline" @click="goToSupplier(s)">{{ s.supplier_code || '—' }}</button>
                </td>
                <td class="border border-border px-4 py-3 align-middle">
                  <button class="text-left text-[#2f6fb0] hover:underline" @click="goToSupplier(s)">{{ s.name }}</button>
                </td>
                <td class="border border-border px-4 py-3 align-middle">{{ s.reference || '' }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ s.group_name || '' }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ paymentTypeLabel(s.payment_type) }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ statusLabel(s.status) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
          <span>Showing {{ showingFrom }} to {{ showingTo }} of {{ meta.total }} entries</span>
          <div class="flex items-center gap-2">
            <AppButton variant="outline" size="sm" :disabled="page <= 1" @click="changePage(page - 1)">Previous</AppButton>
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <AppButton variant="outline" size="sm" :disabled="page >= meta.last_page" @click="changePage(page + 1)">Next</AppButton>
          </div>
        </div>
      </div>

      <!-- ============================ SUPPLIER GROUPS ============================ -->
      <div v-show="activeTab === 'groups'" class="rounded-lg rounded-tl-none border border-border bg-white p-5">
        <button type="button" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#2f6fb0] hover:underline" @click="showGroupForm = !showGroupForm">
          <IconPlusCircle class="h-4 w-4" /> Add new Supplier Group
        </button>

        <div v-if="showGroupForm" class="mb-6 border-b border-border pb-6">
          <div class="grid grid-cols-[150px_1fr] items-center gap-3">
            <label class="text-sm font-medium text-foreground">Group Name</label>
            <AppInput v-model="newGroupName" :error="groupError" />
          </div>
          <div class="mt-4 flex justify-end">
            <AppButton variant="secondary" :loading="creatingGroup" @click="createGroup">Create Supplier Group</AppButton>
          </div>
        </div>

        <!-- DataTable-style controls -->
        <div class="mb-3 flex items-center justify-between text-sm">
          <div class="flex items-center gap-2">
            <div class="w-24">
              <AppSelect v-model="groupPerPage" :options="perPageOptions" />
            </div>
            <span class="text-muted-foreground">records per page</span>
          </div>
          <label class="flex items-center gap-2">
            <span class="text-muted-foreground">Search:</span>
            <input
              v-model="groupSearch"
              type="text"
              class="h-9 w-56 rounded-md border border-border px-3 focus:border-navy focus:outline-none"
              @input="onGroupSearch"
            />
          </label>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Supplier Group</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Total Suppliers</th>
                <th class="w-12 border border-border px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingGroups">
                <td colspan="3" class="border border-border px-4 py-6 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="!groups.length">
                <td colspan="3" class="border border-border px-4 py-6 text-center text-muted-foreground">No data available in table</td>
              </tr>
              <tr v-for="g in groups" :key="g.id" class="hover:bg-muted/40">
                <td class="border border-border px-4 py-3 align-middle">{{ g.name }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ g.suppliers_count }}</td>
                <td class="border border-border px-4 py-3 text-center align-middle">
                  <button type="button" class="text-destructive" title="Delete group" @click="deleteGroup(g)">
                    <IconX class="mx-auto h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
          <span>Showing {{ groups.length }} of {{ groupMeta.total }} entries</span>
          <div class="flex items-center gap-2">
            <AppButton variant="outline" size="sm" :disabled="groupPage <= 1" @click="changeGroupPage(groupPage - 1)">Previous</AppButton>
            <span>Page {{ groupMeta.current_page }} of {{ groupMeta.last_page }}</span>
            <AppButton variant="outline" size="sm" :disabled="groupPage >= groupMeta.last_page" @click="changeGroupPage(groupPage + 1)">Next</AppButton>
          </div>
        </div>
      </div>

      <!-- Upload modal -->
      <SupplierUploadModal
        :show="uploadModal"
        :community-id="communityId"
        @close="uploadModal = false"
        @imported="onImported"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import SupplierForm from '@/components/suppliers/SupplierForm.vue'
import SupplierUploadModal from '@/components/suppliers/SupplierUploadModal.vue'

const router = useRouter()
const route = useRoute()
const { success, error: toastError } = useToast()
const communityStore = useCommunityStore()
const communityId = computed(() => communityStore.selectedId)

// ── Inline stroke icons (no emojis) ──
const IconPlusCircle = (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('circle', { cx: 12, cy: 12, r: 9 }), h('path', { d: 'M12 8v8M8 12h8' })])
function strokeIcon(paths) {
  return (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, paths.map(d => h('path', { d })))
}
const IconX        = strokeIcon(['M18 6 6 18', 'M6 6l12 12'])
const IconDownload = strokeIcon(['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3'])
const IconUpload   = strokeIcon(['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M17 8l-5-5-5 5', 'M12 3v12'])

const activeTab = ref(route.query.tab === 'groups' ? 'groups' : 'list')

function tabClass(tab) {
  return activeTab.value === tab
    ? '-mb-px rounded-t-lg border border-border border-b-0 bg-white px-6 py-2.5 text-sm font-semibold text-foreground'
    : 'px-6 py-2.5 text-sm text-[#2f6fb0] hover:underline'
}
function setTab(tab) {
  activeTab.value = tab
  router.replace({ query: tab === 'groups' ? { tab: 'groups' } : {} })
}

const perPageOptions = [
  { value: 10, label: '10' },
  { value: 25, label: '25' },
  { value: 50, label: '50' },
]

// ── Options (enum labels) ──
const options = reactive({
  supplier_types: [],
  payment_types: [],
  account_types: [],
  banks: [],
  statuses: [],
  groups: [],
})

function labelFor(list, value) {
  if (value === null || value === undefined || value === '') return ''
  return (options[list] || []).find(o => o.value === value)?.label ?? value
}
const paymentTypeLabel = (v) => labelFor('payment_types', v) || 'Not Specified'
const statusLabel = (v) => labelFor('statuses', v)

// ── Supplier list state ──
const suppliers        = ref([])
const loadingSuppliers = ref(false)
const page             = ref(1)
const perPage          = ref(10)
const meta             = reactive({ total: 0, current_page: 1, last_page: 1, per_page: 10 })
const search           = ref('')

const showAddForm = ref(false)
const uploadModal = ref(false)
const exporting   = ref(false)

const showingFrom = computed(() => (meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1))
const showingTo   = computed(() => Math.min(meta.current_page * meta.per_page, meta.total))

// ── Group state ──
const groups        = ref([])
const loadingGroups = ref(false)
const groupPage     = ref(1)
const groupPerPage  = ref(10)
const groupMeta     = reactive({ total: 0, current_page: 1, last_page: 1 })
const groupSearch   = ref('')
const showGroupForm = ref(false)
const newGroupName  = ref('')
const groupError    = ref('')
const creatingGroup = ref(false)

async function fetchOptions() {
  if (!communityId.value) return
  try {
    const { data } = await api.get('/suppliers/options', { params: { community_id: communityId.value } })
    Object.assign(options, data)
  } catch (e) { /* non-fatal */ }
}

async function fetchSuppliers() {
  if (!communityId.value) return
  loadingSuppliers.value = true
  try {
    const { data } = await api.get('/suppliers', {
      params: {
        community_id: communityId.value,
        search: search.value || undefined,
        page: page.value,
        _per_page: perPage.value,
      },
    })
    suppliers.value = data.data ?? []
    Object.assign(meta, data.meta ?? {})
  } catch (e) {
    toastError('Could not load suppliers.')
  } finally {
    loadingSuppliers.value = false
  }
}

async function fetchGroups() {
  if (!communityId.value) return
  loadingGroups.value = true
  try {
    const { data } = await api.get('/supplier-groups', {
      params: {
        community_id: communityId.value,
        search: groupSearch.value || undefined,
        page: groupPage.value,
        _per_page: groupPerPage.value,
      },
    })
    groups.value = data.data ?? []
    Object.assign(groupMeta, data.meta ?? {})
  } catch (e) {
    toastError('Could not load supplier groups.')
  } finally {
    loadingGroups.value = false
  }
}

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; fetchSuppliers() }, 300)
}
function changePage(p) {
  page.value = p
  fetchSuppliers()
}

function goToSupplier(s) {
  router.push({ name: 'supplier-detail', params: { supplierId: s.id } })
}
function onSupplierSaved() {
  showAddForm.value = false
  fetchSuppliers()
  fetchGroups()
  fetchOptions()
}
function onImported() {
  uploadModal.value = false
  fetchSuppliers()
}

async function exportSuppliers() {
  exporting.value = true
  try {
    const res = await api.get('/suppliers/export', {
      params: { community_id: communityId.value, search: search.value || undefined },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: 'supplier-export.xlsx' })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    toastError('Could not export suppliers.')
  } finally {
    exporting.value = false
  }
}

// ── Groups ──
let groupSearchTimer = null
function onGroupSearch() {
  clearTimeout(groupSearchTimer)
  groupSearchTimer = setTimeout(() => { groupPage.value = 1; fetchGroups() }, 300)
}
function changeGroupPage(p) {
  groupPage.value = p
  fetchGroups()
}
async function createGroup() {
  groupError.value = ''
  creatingGroup.value = true
  try {
    await api.post('/supplier-groups', { name: newGroupName.value, community_id: communityId.value })
    success('Supplier group created')
    newGroupName.value = ''
    showGroupForm.value = false
    fetchGroups()
    fetchOptions()
  } catch (e) {
    if (e.response?.status === 422) {
      groupError.value = e.response.data.errors?.name?.[0] || 'Invalid group name.'
    } else {
      toastError('Could not create the group.')
    }
  } finally {
    creatingGroup.value = false
  }
}
async function deleteGroup(g) {
  if (!confirm(`Delete supplier group "${g.name}"?`)) return
  try {
    await api.delete(`/supplier-groups/${g.id}`)
    success('Supplier group deleted')
    fetchGroups()
    fetchOptions()
  } catch (e) {
    toastError('Could not delete the group.')
  }
}

watch(activeTab, (tab) => {
  if (tab === 'groups') fetchGroups()
})
watch(perPage, () => { page.value = 1; fetchSuppliers() })
watch(groupPerPage, () => { groupPage.value = 1; fetchGroups() })
watch(communityId, () => {
  fetchOptions()
  fetchSuppliers()
  fetchGroups()
})

onMounted(() => {
  fetchOptions()
  fetchSuppliers()
  fetchGroups()
})
</script>
