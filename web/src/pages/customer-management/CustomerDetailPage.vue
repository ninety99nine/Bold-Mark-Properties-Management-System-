<template>
  <div class="p-6">
    <h1 class="mb-5 font-body text-2xl font-bold text-foreground">Customers</h1>

    <!-- Top folder tabs (WeConnectU) -->
    <div class="flex items-end gap-1">
      <button type="button" class="-mb-px rounded-t-lg border border-border border-b-0 bg-white px-6 py-2.5 text-sm font-semibold text-foreground" @click="goToList('list')">Customer List</button>
      <button type="button" class="px-6 py-2.5 text-sm text-[#2f6fb0] hover:underline" @click="goToList('groups')">Customer Groups</button>
    </div>

    <div class="rounded-lg rounded-tl-none border border-border bg-white p-6">
      <div v-if="loading" class="py-10 text-center text-muted-foreground">Loading…</div>
      <div v-else-if="!customer" class="py-10 text-center text-muted-foreground">Customer not found.</div>

      <template v-else>
        <h2 class="mb-5 text-xl font-semibold text-foreground">{{ customer.code }}: {{ customer.full_name }}</h2>

        <!-- Sub-tabs -->
        <div class="mb-6 flex items-center gap-6 text-base">
          <button type="button" :class="subTabClass('details')" @click="subTab = 'details'">Customer Details</button>
          <button type="button" :class="subTabClass('sent')" @click="switchSub('sent')">Sent Items</button>
          <button type="button" :class="subTabClass('docs')" @click="switchSub('docs')">Documents</button>
        </div>

        <!-- Customer Details -->
        <div v-show="subTab === 'details'">
          <h3 class="mb-5 text-2xl font-semibold text-foreground">Customer Details</h3>
          <CustomerForm
            :community-id="communityId"
            :group-options="groupOptions"
            :customer="customer"
            detailed
            @saved="reloadCustomer"
          />
        </div>

        <!-- Sent Items -->
        <div v-show="subTab === 'sent'">
          <h3 class="mb-5 text-2xl font-semibold text-foreground">Sent Items</h3>
          <div v-if="!customer.unit?.id" class="rounded border border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
            No unit is linked to this customer, so there are no sent items.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-[#eef1f5]">
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Subject</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">To</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">From</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Date Sent</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loadingSent"><td colspan="5" class="border border-border px-4 py-6 text-center text-muted-foreground">Loading…</td></tr>
                <tr v-else-if="!sentItems.length"><td colspan="5" class="border border-border px-4 py-6 text-center text-muted-foreground">No items sent.</td></tr>
                <tr v-for="item in sentItems" :key="item.id" class="hover:bg-muted/40">
                  <td class="border border-border px-4 py-3 align-middle">
                    <span class="inline-flex items-center gap-2">
                      <span class="text-[#2f6fb0]">{{ item.subject }}</span>
                      <AppTooltip text="Click here for a Printer Friendly version">
                        <button type="button" class="text-red-600 hover:text-red-700" @click="printCommunication(item)">
                          <IconPrinter class="h-4 w-4" />
                        </button>
                      </AppTooltip>
                    </span>
                  </td>
                  <td class="border border-border px-4 py-3 align-middle">{{ item.recipient_email }}</td>
                  <td class="border border-border px-4 py-3 align-middle">
                    <div class="font-semibold text-foreground">Bold Mark Properties</div>
                    <div class="text-xs text-muted-foreground">{{ item.sent_by_name || 'noreply@boldmarkprop.co.za' }}</div>
                  </td>
                  <td class="border border-border px-4 py-3 align-middle">{{ item.created_at }}</td>
                  <td class="border border-border px-4 py-3 align-middle">Sent</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Documents -->
        <div v-show="subTab === 'docs'">
          <div class="mb-5 flex items-center justify-between">
            <h3 class="text-2xl font-semibold text-foreground">Customer Documents</h3>
            <AppButton v-if="customer.unit?.id" variant="secondary" @click="openDocModal">
              <span class="inline-flex items-center gap-2"><IconPlusCircle class="h-4 w-4" /> Upload</span>
            </AppButton>
          </div>
          <div v-if="!customer.unit?.id" class="rounded border border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
            No unit is linked to this customer.
          </div>
          <div v-else-if="loadingDocs" class="py-6 text-center text-muted-foreground">Loading…</div>
          <div v-else-if="!documents.length" class="rounded border border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
            No documents uploaded.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-[#eef1f5]">
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Name</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Uploaded By</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="doc in documents" :key="doc.id" class="hover:bg-muted/40">
                  <td class="border border-border px-4 py-3 align-middle">
                    <a :href="doc.download_url" target="_blank" class="text-[#2f6fb0] hover:underline">{{ doc.name }}</a>
                  </td>
                  <td class="border border-border px-4 py-3 align-middle">{{ doc.uploaded_by_name || '—' }}</td>
                  <td class="border border-border px-4 py-3 align-middle">{{ doc.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Back -->
        <div class="mt-8">
          <AppButton variant="secondary" @click="goToList('list')">&laquo; Back to Customer List</AppButton>
        </div>
      </template>
    </div>

    <!-- Upload Customer Document modal -->
    <AppModal :show="docModal" size="md" @close="docModal = false">
      <template #header>
        <h3 class="w-full text-center text-base font-bold text-foreground">Upload Customer Document</h3>
      </template>
      <div class="space-y-5">
        <div class="grid grid-cols-[150px_1fr] items-center gap-3">
          <label class="text-sm font-medium text-foreground">Document Name</label>
          <AppInput v-model="docName" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label class="pt-2 text-sm font-medium text-foreground">Upload</label>
          <div>
            <div
              class="rounded-lg border-2 border-dashed py-8 px-6 text-center transition-colors"
              :class="dragging ? 'border-navy bg-navy/5' : 'border-border'"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="onDocDrop"
            >
              <p v-if="!docFile" class="text-sm text-muted-foreground">Drag and drop your document here</p>
              <p v-else class="text-sm font-medium text-foreground">{{ docFile.name }}</p>
            </div>
            <input ref="docInput" type="file" class="hidden" @change="onDocSelected" />
            <div class="mt-3">
              <AppButton variant="secondary" :loading="uploadingDoc" @click="docInput?.click()">
                <span class="inline-flex items-center gap-2"><IconPlusCircle class="h-4 w-4" /> Click to select document</span>
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppTooltip from '@/components/common/AppTooltip.vue'
import CustomerForm from '@/components/customers/CustomerForm.vue'

const router = useRouter()
const route = useRoute()
const { success, error: toastError } = useToast()
const communityStore = useCommunityStore()
const communityId = computed(() => communityStore.selectedId)
const ownerId = computed(() => route.params.ownerId)

const IconPlusCircle = (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('circle', { cx: 12, cy: 12, r: 9 }), h('path', { d: 'M12 8v8M8 12h8' })])
const IconPrinter = (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('path', { d: 'M6 9V2h12v7' }), h('path', { d: 'M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2' }), h('rect', { x: 6, y: 14, width: 12, height: 8 })])

const loading  = ref(true)
const customer = ref(null)
const groups   = ref([])
const subTab   = ref('details')

const sentItems   = ref([])
const loadingSent = ref(false)
const sentLoaded  = ref(false)

const documents   = ref([])
const loadingDocs = ref(false)
const docsLoaded  = ref(false)
const docInput    = ref(null)
const docModal    = ref(false)
const docName     = ref('')
const docFile     = ref(null)
const dragging    = ref(false)
const uploadingDoc = ref(false)

const groupOptions = computed(() => groups.value.map(g => ({ value: g.id, label: g.name })))

function subTabClass(tab) {
  return subTab.value === tab ? 'font-semibold text-foreground' : 'text-[#2f6fb0] hover:underline'
}
function goToList(tab) {
  router.push({ name: 'manage-customers', query: tab === 'groups' ? { tab: 'groups' } : {} })
}

async function fetchCustomer() {
  if (!communityId.value) { router.replace('/communities'); return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customers/${ownerId.value}`)
    customer.value = data.data ?? data
  } catch (e) {
    toastError('Could not load the customer.')
  } finally {
    loading.value = false
  }
}
async function reloadCustomer() {
  await fetchCustomer()
}

async function fetchGroups() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-groups`, { params: { _per_page: 200 } })
    groups.value = data.data ?? []
  } catch (e) { /* non-fatal */ }
}

function switchSub(tab) {
  subTab.value = tab
  if (tab === 'sent' && !sentLoaded.value) fetchSentItems()
  if (tab === 'docs' && !docsLoaded.value) fetchDocuments()
}

async function fetchSentItems() {
  if (!customer.value?.unit?.id) return
  loadingSent.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/units/${customer.value.unit.id}/communications`)
    sentItems.value = data.data ?? []
    sentLoaded.value = true
  } catch (e) {
    toastError('Could not load sent items.')
  } finally {
    loadingSent.value = false
  }
}

async function fetchDocuments() {
  if (!customer.value?.unit?.id) return
  loadingDocs.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/units/${customer.value.unit.id}/documents`)
    documents.value = data.data ?? []
    docsLoaded.value = true
  } catch (e) {
    toastError('Could not load documents.')
  } finally {
    loadingDocs.value = false
  }
}

function openDocModal() {
  docName.value = ''
  docFile.value = null
  dragging.value = false
  docModal.value = true
}
function onDocSelected(event) {
  const file = event.target.files?.[0]
  if (file) { docFile.value = file; uploadDocument() }
}
function onDocDrop(event) {
  dragging.value = false
  const file = event.dataTransfer.files?.[0]
  if (file) { docFile.value = file; uploadDocument() }
}
async function printCommunication(item) {
  if (!customer.value?.unit?.id) return
  try {
    const res = await api.get(`/communities/${communityId.value}/units/${customer.value.unit.id}/communications/${item.id}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    toastError('Could not open the printer-friendly version.')
  }
}
async function uploadDocument() {
  if (!docFile.value || !customer.value?.unit?.id || uploadingDoc.value) return
  uploadingDoc.value = true
  try {
    const form = new FormData()
    form.append('file', docFile.value)
    if (docName.value) form.append('name', docName.value)
    await api.post(`/communities/${communityId.value}/units/${customer.value.unit.id}/documents`, form)
    success('Document uploaded')
    docModal.value = false
    docsLoaded.value = false
    fetchDocuments()
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not upload the document.')
  } finally {
    uploadingDoc.value = false
    if (docInput.value) docInput.value.value = ''
  }
}

onMounted(async () => {
  await Promise.all([fetchCustomer(), fetchGroups()])
})
</script>
