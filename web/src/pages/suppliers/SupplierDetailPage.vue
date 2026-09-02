<template>
  <div class="p-6">
    <h1 class="mb-5 font-body text-2xl font-bold text-foreground">Setup Suppliers</h1>

    <!-- Top folder tabs (WeConnectU) -->
    <div class="flex items-end gap-1">
      <button type="button" class="-mb-px rounded-t-lg border border-border border-b-0 bg-white px-6 py-2.5 text-sm font-semibold text-foreground" @click="goToList('list')">Supplier List</button>
      <button type="button" class="px-6 py-2.5 text-sm text-[#2f6fb0] hover:underline" @click="goToList('groups')">Supplier Groups</button>
    </div>

    <div class="rounded-lg rounded-tl-none border border-border bg-white p-6">
      <div v-if="loading" class="py-10 text-center text-muted-foreground">Loading…</div>
      <div v-else-if="!supplier" class="py-10 text-center text-muted-foreground">Supplier not found.</div>

      <template v-else>
        <h2 class="mb-5 text-xl font-semibold text-foreground">{{ supplier.supplier_code }}: {{ supplier.name }}</h2>

        <!-- Sub-tabs -->
        <div class="mb-6 flex items-center gap-6 text-base">
          <button type="button" :class="subTabClass('details')" @click="subTab = 'details'">Supplier Details</button>
          <button type="button" :class="subTabClass('docs')" @click="switchSub('docs')">Documents</button>
        </div>

        <!-- Supplier Details -->
        <div v-show="subTab === 'details'">
          <h3 class="mb-5 text-2xl font-semibold text-foreground">Supplier Details</h3>
          <!-- Editable Supplier Code (WeConnectU shows it above the form on the edit view) -->
          <div class="mb-6 grid gap-x-10 md:grid-cols-2">
            <div class="grid grid-cols-[150px_1fr] items-start gap-3">
              <label class="pt-2 text-sm font-medium text-foreground">Supplier Code</label>
              <AppInput v-model="supplierCode" :error="codeError" />
            </div>
          </div>
          <SupplierForm
            :community-id="communityId"
            :options="options"
            :supplier="{ ...supplier, supplier_code: supplierCode }"
            @saved="onSaved"
          />
        </div>

        <!-- Documents -->
        <div v-show="subTab === 'docs'">
          <div class="mb-5 flex items-center justify-between">
            <h3 class="text-2xl font-semibold text-foreground">Supplier Documents</h3>
            <AppButton variant="secondary" @click="openDocModal">
              <span class="inline-flex items-center gap-2"><IconPlusCircle class="h-4 w-4" /> Upload</span>
            </AppButton>
          </div>
          <div v-if="loadingDocs" class="py-6 text-center text-muted-foreground">Loading…</div>
          <div v-else-if="!documents.length" class="rounded border border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
            No documents uploaded.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-[#eef1f5]">
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Name</th>
                  <th class="border border-border px-4 py-3 text-left text-[13px] font-bold text-navy-dark">Date</th>
                  <th class="w-12 border border-border px-4 py-3"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="doc in documents" :key="doc.id" class="hover:bg-muted/40">
                  <td class="border border-border px-4 py-3 align-middle">
                    <a :href="doc.url" target="_blank" class="text-[#2f6fb0] hover:underline">{{ doc.name }}</a>
                  </td>
                  <td class="border border-border px-4 py-3 align-middle">{{ formatDate(doc.created_at) }}</td>
                  <td class="border border-border px-4 py-3 text-center align-middle">
                    <button type="button" class="text-destructive" title="Delete document" @click="deleteDocument(doc)">
                      <IconX class="mx-auto h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Back -->
        <div class="mt-8">
          <AppButton variant="secondary" @click="goToList('list')">&laquo; Back to Supplier List</AppButton>
        </div>
      </template>
    </div>

    <!-- Upload Supplier Document modal -->
    <AppModal :show="docModal" size="md" @close="docModal = false">
      <template #header>
        <h3 class="w-full text-center text-base font-bold text-foreground">Upload Supplier Document</h3>
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
import { ref, reactive, computed, onMounted, h } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppModal from '@/components/common/AppModal.vue'
import SupplierForm from '@/components/suppliers/SupplierForm.vue'

const router = useRouter()
const route = useRoute()
const { success, error: toastError } = useToast()
const communityStore = useCommunityStore()
const communityId = computed(() => communityStore.selectedId)
const supplierId = computed(() => route.params.supplierId)

const IconPlusCircle = (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('circle', { cx: 12, cy: 12, r: 9 }), h('path', { d: 'M12 8v8M8 12h8' })])
const IconX = (props, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('path', { d: 'M18 6 6 18' }), h('path', { d: 'M6 6l12 12' })])

const loading  = ref(true)
const supplier = ref(null)
const subTab   = ref('details')

const supplierCode = ref('')
const codeError    = ref('')

const options = reactive({
  supplier_types: [],
  payment_types: [],
  account_types: [],
  banks: [],
  statuses: [],
  groups: [],
})

const documents   = ref([])
const loadingDocs  = ref(false)
const docsLoaded   = ref(false)
const docInput     = ref(null)
const docModal     = ref(false)
const docName      = ref('')
const docFile      = ref(null)
const dragging     = ref(false)
const uploadingDoc = ref(false)

function formatDate(v) {
  if (!v) return ''
  return new Date(v).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
}

function subTabClass(tab) {
  return subTab.value === tab ? 'font-semibold text-foreground' : 'text-[#2f6fb0] hover:underline'
}
function goToList(tab) {
  router.push({ name: 'manage-suppliers', query: tab === 'groups' ? { tab: 'groups' } : {} })
}

async function fetchOptions() {
  if (!communityId.value) return
  try {
    const { data } = await api.get('/suppliers/options', { params: { community_id: communityId.value } })
    Object.assign(options, data)
  } catch (e) { /* non-fatal */ }
}

async function fetchSupplier() {
  if (!communityId.value) { router.replace('/communities'); return }
  loading.value = true
  try {
    const { data } = await api.get(`/suppliers/${supplierId.value}`)
    supplier.value = data.data ?? data
    supplierCode.value = supplier.value?.supplier_code ?? ''
  } catch (e) {
    toastError('Could not load the supplier.')
  } finally {
    loading.value = false
  }
}

async function onSaved() {
  codeError.value = ''
  await fetchSupplier()
}

function switchSub(tab) {
  subTab.value = tab
  if (tab === 'docs' && !docsLoaded.value) fetchDocuments()
}

async function fetchDocuments() {
  loadingDocs.value = true
  try {
    const { data } = await api.get(`/suppliers/${supplierId.value}/documents`)
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
async function uploadDocument() {
  if (!docFile.value || uploadingDoc.value) return
  uploadingDoc.value = true
  try {
    const form = new FormData()
    form.append('file', docFile.value)
    if (docName.value) form.append('name', docName.value)
    await api.post(`/suppliers/${supplierId.value}/documents`, form)
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
async function deleteDocument(doc) {
  if (!confirm(`Delete document "${doc.name}"?`)) return
  try {
    await api.delete(`/suppliers/${supplierId.value}/documents/${doc.id}`)
    success('Document deleted')
    fetchDocuments()
  } catch (e) {
    toastError('Could not delete the document.')
  }
}

onMounted(async () => {
  await Promise.all([fetchSupplier(), fetchOptions()])
})
</script>
