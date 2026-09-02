<template>
  <div class="p-6">
    <h1 class="mb-5 font-body text-2xl font-bold text-foreground">Customers</h1>

    <!-- No community selected -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to manage its customers.
      <div class="mt-4">
        <AppButton variant="secondary" @click="$router.push('/communities')">Choose a community</AppButton>
      </div>
    </div>

    <template v-else>
      <!-- Tabs (WeConnectU folder style) -->
      <div class="flex items-end gap-1">
        <button type="button" :class="tabClass('list')" @click="setTab('list')">Customer List</button>
        <button type="button" :class="tabClass('groups')" @click="setTab('groups')">Customer Groups</button>
      </div>

      <!-- ============================ CUSTOMER LIST ============================ -->
      <div v-show="activeTab === 'list'" class="rounded-lg rounded-tl-none border border-border bg-white p-5">
        <!-- Toolbar -->
        <div class="mb-4 flex items-start justify-between gap-4">
          <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#2f6fb0] hover:underline" @click="showAddForm = !showAddForm">
            <IconPlusCircle class="h-4 w-4" /> Add new Customer
          </button>
          <div class="flex gap-2">
            <AppButton variant="secondary" @click="notesModal = true">
              <span class="inline-flex items-center gap-2"><IconDocument class="h-4 w-4" /> Upload Customer Notes</span>
            </AppButton>
            <AppButton variant="secondary" @click="mandatesModal = true">
              <span class="inline-flex items-center gap-2"><IconPencil class="h-4 w-4" /> Update Debit Order Mandates</span>
            </AppButton>
          </div>
        </div>

        <!-- Inline add form -->
        <div v-if="showAddForm" class="mb-6 border-b border-border">
          <CustomerForm
            :community-id="communityId"
            :group-options="groupOptions"
            @saved="onCustomerSaved"
          />
        </div>

        <!-- Search -->
        <div class="mb-3 flex items-center justify-end gap-2">
          <input
            v-model="customerSearch"
            type="text"
            placeholder="Search..."
            class="h-10 w-64 rounded-md border border-border px-3 text-sm focus:border-navy focus:outline-none"
            @input="debouncedCustomerSearch"
          />
          <button v-if="appliedSearch" type="button" class="text-destructive" title="Clear" @click="clearCustomerSearch">
            <IconX class="h-4 w-4" />
          </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-4 py-3 text-left">
                  <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleSort('customer_code')">
                    Code <SortChevrons :state="sortState('customer_code')" />
                  </button>
                </th>
                <th class="border border-border px-4 py-3 text-left">
                  <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleSort('full_name')">
                    Customer <SortChevrons :state="sortState('full_name')" />
                  </button>
                </th>
                <th class="border border-border px-4 py-3 text-left">
                  <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleSort('reference')">
                    Reference <SortChevrons :state="sortState('reference')" />
                  </button>
                </th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Group</th>
                <th class="border border-border px-4 py-3 text-left">
                  <button class="inline-flex items-center gap-1.5 text-[13px] font-bold text-navy-dark" @click="toggleSort('payment_type')">
                    Payment Type <SortChevrons :state="sortState('payment_type')" />
                  </button>
                </th>
                <th class="w-12 border border-border px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loadingCustomers">
                <td colspan="6" class="border border-border px-4 py-8 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="!customers.length">
                <td colspan="6" class="border border-border px-4 py-8 text-center text-muted-foreground">No customers found.</td>
              </tr>
              <tr
                v-for="c in customers"
                :key="c.id"
                class="hover:bg-muted/40"
                :class="c.is_disabled && 'opacity-50'"
              >
                <td class="border border-border px-4 py-3 align-middle">
                  <button class="text-[#2f6fb0] hover:underline" @click="goToCustomer(c)">{{ c.code || '—' }}</button>
                </td>
                <td class="border border-border px-4 py-3 align-middle">
                  <div class="flex items-center justify-between gap-3">
                    <button class="text-left text-[#2f6fb0] hover:underline" @click="goToCustomer(c)">{{ c.full_name }}</button>
                    <button v-if="c.unit?.id" type="button" title="Download statement" class="shrink-0" @click="downloadStatement(c)">
                      <IconPdf class="h-5 w-5" />
                    </button>
                  </div>
                </td>
                <td class="border border-border px-4 py-3 align-middle">{{ c.reference || '' }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ (c.customer_groups || []).map(g => g.name).join(', ') }}</td>
                <td class="border border-border px-4 py-3 align-middle">{{ c.payment_type_label || 'Not Specified' }}</td>
                <td class="border border-border px-4 py-3 text-center align-middle">
                  <button v-if="!c.is_disabled" type="button" class="text-destructive" title="Disable Customer" @click="disableCustomer(c)">
                    <IconX class="mx-auto h-4 w-4" />
                  </button>
                  <button v-else type="button" class="text-emerald-600" title="Enable Customer" @click="enableCustomer(c)">
                    <IconRefresh class="mx-auto h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
          <span>{{ customerMeta.total }} customer{{ customerMeta.total === 1 ? '' : 's' }}</span>
          <div class="flex items-center gap-2">
            <AppButton variant="outline" size="sm" :disabled="customerPage <= 1" @click="changeCustomerPage(customerPage - 1)">Previous</AppButton>
            <span>Page {{ customerMeta.current_page }} of {{ customerMeta.last_page }}</span>
            <AppButton variant="outline" size="sm" :disabled="customerPage >= customerMeta.last_page" @click="changeCustomerPage(customerPage + 1)">Next</AppButton>
          </div>
        </div>
      </div>

      <!-- ============================ CUSTOMER GROUPS ============================ -->
      <div v-show="activeTab === 'groups'" class="rounded-lg rounded-tl-none border border-border bg-white p-5">
        <button type="button" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#2f6fb0] hover:underline" @click="showGroupForm = !showGroupForm">
          <IconPlusCircle class="h-4 w-4" /> Add new Customer Group
        </button>

        <div v-if="showGroupForm" class="mb-6 border-b border-border pb-6">
          <div class="grid grid-cols-[150px_1fr] items-center gap-3">
            <label class="text-sm font-medium text-foreground">Group Name</label>
            <AppInput v-model="newGroupName" :error="groupError" />
          </div>
          <div class="mt-4 flex justify-end">
            <AppButton variant="secondary" :loading="creatingGroup" @click="createGroup">Create Customer Group</AppButton>
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
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Customer Group</th>
                <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Total Customers</th>
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
                <td class="border border-border px-4 py-3 align-middle">{{ g.customers_count }}</td>
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

      <!-- Modals -->
      <CustomerBulkUploadModal
        :show="notesModal"
        :community-id="communityId"
        title="Upload Customer Notes"
        kind="notes"
        @close="notesModal = false"
        @imported="fetchCustomers()"
      />
      <CustomerBulkUploadModal
        :show="mandatesModal"
        :community-id="communityId"
        title="Update Debit Order Mandates"
        kind="mandates"
        @close="mandatesModal = false"
        @imported="fetchCustomers()"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import CustomerForm from '@/components/customers/CustomerForm.vue'
import CustomerBulkUploadModal from '@/components/customers/CustomerBulkUploadModal.vue'

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
const IconDocument = strokeIcon(['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6'])
const IconPencil   = strokeIcon(['M12 20h9', 'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'])
const IconX        = strokeIcon(['M18 6 6 18', 'M6 6l12 12'])
const IconRefresh  = strokeIcon(['M3 12a9 9 0 1 0 3-6.7L3 8', 'M3 3v5h5'])
const IconPdf = (props, { attrs }) => h('svg', { viewBox: '0 0 32 32', ...attrs }, [
  h('path', { d: 'M8 3h11l6 6v18a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', fill: 'none', stroke: '#2f6fb0', 'stroke-width': 2 }),
  h('path', { d: 'M19 3v6h6', fill: 'none', stroke: '#2f6fb0', 'stroke-width': 2 }),
  h('text', { x: 16, y: 24, 'text-anchor': 'middle', 'font-size': 8, 'font-weight': 700, fill: '#2f6fb0', 'font-family': "'DM Sans', sans-serif" }, 'PDF'),
])
const SortChevrons = (props) => h('span', { class: 'inline-flex flex-col text-[8px] leading-[7px]' }, [
  h('span', { class: props.state === 'asc' ? 'text-accent' : 'text-muted-foreground/40' }, '▲'),
  h('span', { class: props.state === 'desc' ? 'text-accent' : 'text-muted-foreground/40' }, '▼'),
])
SortChevrons.props = { state: String }

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

// ── Customer list state ──
const customers        = ref([])
const loadingCustomers = ref(false)
const customerPage     = ref(1)
const customerMeta     = reactive({ total: 0, current_page: 1, last_page: 1, per_page: 15 })
const customerSearch   = ref('')
const appliedSearch    = ref('')
const sortColumn       = ref('customer_code')
const sortDir          = ref('asc')

const showAddForm = ref(false)

const notesModal    = ref(false)
const mandatesModal = ref(false)

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

const groupOptions = computed(() => groups.value.map(g => ({ value: g.id, label: g.name })))

function sortState(column) {
  return sortColumn.value === column ? sortDir.value : null
}

async function fetchCustomers() {
  if (!communityId.value) return
  loadingCustomers.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customers`, {
      params: {
        _search: appliedSearch.value || undefined,
        _sort: `${sortColumn.value}:${sortDir.value}`,
        _page: customerPage.value,
        _per_page: 15,
      },
    })
    customers.value = data.data ?? []
    Object.assign(customerMeta, data.meta ?? {})
  } catch (e) {
    toastError('Could not load customers.')
  } finally {
    loadingCustomers.value = false
  }
}

async function fetchGroups() {
  if (!communityId.value) return
  loadingGroups.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-groups`, {
      params: {
        _search: groupSearch.value || undefined,
        _page: groupPage.value,
        _per_page: groupPerPage.value,
      },
    })
    groups.value = data.data ?? []
    Object.assign(groupMeta, data.meta ?? {})
  } catch (e) {
    toastError('Could not load customer groups.')
  } finally {
    loadingGroups.value = false
  }
}

function toggleSort(column) {
  if (sortColumn.value === column) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDir.value = 'asc'
  }
  customerPage.value = 1
  fetchCustomers()
}

function applyCustomerSearch() {
  appliedSearch.value = customerSearch.value
  customerPage.value = 1
  fetchCustomers()
}
const debouncedCustomerSearch = debounce(applyCustomerSearch, 300)
function clearCustomerSearch() {
  customerSearch.value = ''
  appliedSearch.value = ''
  customerPage.value = 1
  fetchCustomers()
}
function changeCustomerPage(page) {
  customerPage.value = page
  fetchCustomers()
}

function goToCustomer(c) {
  router.push({ name: 'customer-detail', params: { ownerId: c.id } })
}
function onCustomerSaved() {
  showAddForm.value = false
  fetchCustomers()
  fetchGroups()
}

async function disableCustomer(c) {
  try {
    await api.post(`/communities/${communityId.value}/customers/${c.id}/disable`)
    success('Customer disabled')
    fetchCustomers()
  } catch (e) {
    toastError('Could not disable the customer.')
  }
}
async function enableCustomer(c) {
  try {
    await api.post(`/communities/${communityId.value}/customers/${c.id}/enable`)
    success('Customer enabled')
    fetchCustomers()
  } catch (e) {
    toastError('Could not enable the customer.')
  }
}

async function downloadStatement(c) {
  if (!c.unit?.id) return
  try {
    const res = await api.get(`/communities/${communityId.value}/units/${c.unit.id}/statement`, { params: { _format: 'pdf' }, responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: `CustomerStatement-${c.code || c.full_name}.pdf` })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    toastError('Could not download the statement.')
  }
}

// ── Groups ──
let groupSearchTimer = null
function onGroupSearch() {
  clearTimeout(groupSearchTimer)
  groupSearchTimer = setTimeout(() => { groupPage.value = 1; fetchGroups() }, 300)
}
function changeGroupPage(page) {
  groupPage.value = page
  fetchGroups()
}
async function createGroup() {
  groupError.value = ''
  creatingGroup.value = true
  try {
    await api.post(`/communities/${communityId.value}/customer-groups`, { name: newGroupName.value })
    success('Customer group created')
    newGroupName.value = ''
    showGroupForm.value = false
    fetchGroups()
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
  if (!confirm(`Delete customer group "${g.name}"?`)) return
  try {
    await api.delete(`/communities/${communityId.value}/customer-groups/${g.id}`)
    success('Customer group deleted')
    fetchGroups()
  } catch (e) {
    toastError('Could not delete the group.')
  }
}

watch(activeTab, (tab) => {
  if (tab === 'groups') fetchGroups()
})
watch(groupPerPage, () => { groupPage.value = 1; fetchGroups() })
watch(communityId, () => {
  fetchCustomers()
  fetchGroups()
})

onMounted(() => {
  fetchCustomers()
  fetchGroups()
})
</script>
