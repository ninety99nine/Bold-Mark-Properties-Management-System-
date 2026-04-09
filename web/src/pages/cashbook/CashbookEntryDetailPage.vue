<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppBadge from '@/components/common/AppBadge.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import api from '@/composables/useApi.js'
import { useBack } from '@/composables/useBack.js'
import AllocationModal from '@/components/common/AllocationModal.vue'

const route  = useRoute()
const router = useRouter()
const { goBack } = useBack('/cashbook')

// ── State ─────────────────────────────────────────────────────────────────────
const entry   = ref(null)
const loading = ref(true)
const error   = ref(null)

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchEntry() {
  loading.value = true
  error.value   = null
  try {
    const res   = await api.get(`/cashbook/${route.params.entryId}`)
    entry.value = res.data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load cashbook entry.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchEntry)

// ── Computed helpers ──────────────────────────────────────────────────────────
const isCredit    = computed(() => entry.value?.type === 'credit')
const isAllocated = computed(() => entry.value?.is_allocated ?? false)

const formattedAmount = computed(() => {
  if (!entry.value) return '—'
  const prefix    = isCredit.value ? '+' : '-'
  const formatted = Number(entry.value.amount).toLocaleString('en-ZA')
  return `${prefix}R ${formatted}`
})

const amountClass = computed(() =>
  isCredit.value ? 'text-success' : 'text-destructive'
)

// Parse ISO date string safely (avoids timezone offset issues)
function formatDate(dateStr) {
  if (!dateStr) return '—'
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  const [year, month, day] = dateStr.split('-')
  return `${day} ${months[parseInt(month, 10) - 1]} ${year}`
}

// ── Allocation modal ──────────────────────────────────────────────────────
const showAllocateModal = ref(false)

async function onAllocated() {
  await fetchEntry()
}

// ── Proof of Payment ──────────────────────────────────────────────────────────
const fileInput      = ref(null)
const isDragging     = ref(false)
const proofUploading = ref(false)
const proofDeleting  = ref(false)

function triggerFileInput() {
  fileInput.value?.click()
}

function onDragOver(e) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e) {
  e.preventDefault()
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) handleProofFile(file)
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (file) handleProofFile(file)
  e.target.value = ''
}

async function handleProofFile(file) {
  const allowed = ['application/pdf', 'image/jpeg', 'image/png']
  if (!allowed.includes(file.type)) {
    alert('Only PDF, JPG, and PNG files are supported.')
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    alert('File must be under 10 MB.')
    return
  }
  proofUploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    const res = await api.post(`/cashbook/${route.params.entryId}/proof-of-payment`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    entry.value = res.data.data
  } catch (e) {
    alert(e.response?.data?.message ?? 'Upload failed. Please try again.')
  } finally {
    proofUploading.value = false
  }
}

async function removeProof() {
  if (!confirm('Remove proof of payment?')) return
  proofDeleting.value = true
  try {
    await api.delete(`/cashbook/${route.params.entryId}/proof-of-payment`)
    entry.value = { ...entry.value, proof_of_payment_url: null }
  } catch (e) {
    alert(e.response?.data?.message ?? 'Delete failed.')
  } finally {
    proofDeleting.value = false
  }
}

function proofFileName(url) {
  if (!url) return ''
  return decodeURIComponent(url.split('/').pop().split('?')[0])
}

function proofIsImage(url) {
  if (!url) return false
  return /\.(jpe?g|png)(\?|$)/i.test(url)
}

function proofIsPdf(url) {
  if (!url) return false
  return /\.pdf(\?|$)/i.test(url)
}

async function downloadProof() {
  if (!entry.value?.proof_of_payment_url) return
  try {
    const res       = await api.get(`/cashbook/${route.params.entryId}/proof-of-payment/download`, { responseType: 'blob' })
    const objectUrl = URL.createObjectURL(res.data)
    const a         = document.createElement('a')
    a.href          = objectUrl
    a.download      = proofFileName(entry.value.proof_of_payment_url)
    a.style.display = 'none'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(objectUrl)
  } catch {
    window.open(entry.value.proof_of_payment_url, '_blank')
  }
}

// ── Edit mode ─────────────────────────────────────────────────────────────────
const editMode    = ref(false)
const editForm    = ref({})
const editLoading = ref(false)
const editError   = ref(null)
const unitOptions = ref([])

const ENTRY_TYPE_OPTS = [
  { value: 'credit', label: 'Credit (Received)' },
  { value: 'debit',  label: 'Debit (Paid Out)'  },
]

async function fetchUnitsForEstate() {
  if (!entry.value?.estate_id) return
  try {
    const res = await api.get(`/estates/${entry.value.estate_id}/units`, {
      params: { per_page: 200 },
    })
    unitOptions.value = [
      { value: '', label: 'No unit' },
      ...(res.data.data ?? []).map(u => ({ value: u.id, label: u.unit_number })),
    ]
  } catch {
    unitOptions.value = []
  }
}

async function enterEditMode() {
  editForm.value = {
    date:        entry.value.date ?? '',
    amount:      entry.value.amount ?? '',
    description: entry.value.description ?? '',
    type:        entry.value.type ?? 'credit',
    unit_id:     entry.value.unit_id ?? '',
    notes:       entry.value.notes ?? '',
  }
  editMode.value = true
  editError.value = null
  await fetchUnitsForEstate()
}

function cancelEdit() {
  editMode.value  = false
  editError.value = null
}

async function saveEdit() {
  editLoading.value = true
  editError.value   = null
  try {
    await api.put(`/cashbook/${route.params.entryId}`, {
      date:        editForm.value.date,
      amount:      Number(editForm.value.amount),
      description: editForm.value.description,
      type:        editForm.value.type,
      unit_id:     editForm.value.unit_id || null,
      notes:       editForm.value.notes   || null,
    })
    await fetchEntry()
    editMode.value = false
  } catch (e) {
    editError.value = e.response?.data?.message ?? 'Failed to save changes.'
  } finally {
    editLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page Header ───────────────────────────────────────────────── -->
    <div class="flex items-center gap-3">
      <AppButton variant="ghost" square size="md" @click="goBack()">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
        </svg>
      </AppButton>

      <div class="flex-1">
        <div class="flex items-center gap-3">
          <h1 class="font-body font-bold text-2xl text-foreground">Cashbook Entry</h1>
          <template v-if="!loading && entry">
            <AppBadge :variant="isCredit ? 'success' : 'danger'" bordered size="sm">
              {{ isCredit ? 'Credit' : 'Debit' }}
            </AppBadge>
            <AppBadge :variant="isAllocated ? 'success' : 'warning'" bordered size="sm">
              {{ isAllocated ? 'Allocated' : 'Unallocated' }}
            </AppBadge>
          </template>
        </div>
        <p v-if="entry" class="text-sm text-muted-foreground">{{ entry.description }}</p>
        <div v-else-if="loading" class="h-4 w-64 bg-muted animate-pulse rounded mt-1" />
      </div>

      <div class="flex gap-2">
        <template v-if="!editMode">
          <AppButton variant="outline" size="sm" :disabled="loading || !!error" @click="enterEditMode">
            Edit Entry
          </AppButton>
        </template>
        <template v-else>
          <AppButton variant="outline" size="sm" :disabled="editLoading" @click="cancelEdit">Cancel</AppButton>
          <AppButton variant="primary" size="sm" :disabled="editLoading" @click="saveEdit">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            {{ editLoading ? 'Saving…' : 'Save' }}
          </AppButton>
        </template>
      </div>
    </div>

    <!-- ── Error state ───────────────────────────────────────────────── -->
    <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 p-6 text-center">
      <p class="text-sm font-medium text-destructive">{{ error }}</p>
      <AppButton variant="outline" size="sm" class="mt-3" @click="fetchEntry">Retry</AppButton>
    </div>

    <!-- ── Loading skeleton ──────────────────────────────────────────── -->
    <template v-else-if="loading">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
          <div class="rounded-lg border bg-card p-6 space-y-4">
            <div class="h-4 w-32 bg-muted animate-pulse rounded" />
            <div class="border rounded-lg p-4 grid grid-cols-2 gap-4">
              <div class="space-y-3">
                <div class="h-3 w-16 bg-muted animate-pulse rounded" />
                <div class="h-4 w-24 bg-muted animate-pulse rounded" />
                <div class="h-3 w-20 bg-muted animate-pulse rounded" />
                <div class="h-4 w-48 bg-muted animate-pulse rounded" />
                <div class="h-3 w-10 bg-muted animate-pulse rounded" />
                <div class="h-6 w-28 bg-muted animate-pulse rounded-full" />
              </div>
              <div class="space-y-3">
                <div class="h-3 w-16 bg-muted animate-pulse rounded" />
                <div class="h-8 w-32 bg-muted animate-pulse rounded" />
                <div class="h-3 w-24 bg-muted animate-pulse rounded" />
                <div class="h-6 w-24 bg-muted animate-pulse rounded-full" />
              </div>
            </div>
          </div>
          <div class="rounded-lg border bg-card p-6 h-40 bg-muted/20 animate-pulse" />
        </div>
        <div class="space-y-4">
          <div class="rounded-lg border bg-card p-5 h-32 animate-pulse bg-muted/20" />
          <div class="rounded-lg border bg-card p-5 h-40 animate-pulse bg-muted/20" />
        </div>
      </div>
    </template>

    <!-- ── Main content ──────────────────────────────────────────────── -->
    <template v-else-if="entry">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Left column ────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Entry Details Card -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="p-6 pb-3">
              <h3 class="font-body font-semibold text-base flex items-center gap-2 text-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                  <path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                Entry Details
              </h3>
            </div>

            <div class="px-6 pb-6">
              <!-- View mode -->
              <div v-if="!editMode" class="border border-border rounded-lg overflow-hidden">
                <div class="grid grid-cols-2 divide-x divide-border">
                  <div class="p-4 space-y-4">
                    <div>
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Date</p>
                      <p class="text-sm font-medium text-foreground">{{ formatDate(entry.date) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Description</p>
                      <p class="text-sm font-medium text-foreground">{{ entry.description }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Type</p>
                      <AppBadge :variant="isCredit ? 'success' : 'danger'" bordered size="sm">
                        {{ isCredit ? 'Credit (Received)' : 'Debit (Paid Out)' }}
                      </AppBadge>
                    </div>
                    <div v-if="entry.notes">
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Notes</p>
                      <p class="text-sm text-foreground">{{ entry.notes }}</p>
                    </div>
                  </div>

                  <div class="p-4 space-y-4">
                    <div>
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Amount</p>
                      <p :class="['text-xl font-bold font-body', amountClass]">{{ formattedAmount }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Allocation Status</p>
                      <AppBadge :variant="isAllocated ? 'success' : 'warning'" bordered size="sm">
                        {{ isAllocated ? 'Allocated' : 'Unallocated' }}
                      </AppBadge>
                    </div>
                    <div v-if="entry.charge_type">
                      <p class="text-xs text-muted-foreground uppercase tracking-wider font-medium mb-1">Charge Type</p>
                      <p class="text-sm font-medium text-foreground">{{ entry.charge_type.name }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Edit mode -->
              <div v-else class="space-y-4">
                <p v-if="editError" class="text-sm text-destructive">{{ editError }}</p>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-foreground mb-1.5">Date</label>
                    <AppDatePicker v-model="editForm.date" placeholder="Select date..." />
                  </div>
                  <AppInput
                    v-model="editForm.amount"
                    label="Amount"
                    type="number"
                    placeholder="0.00"
                    prefix="R"
                  />
                  <div class="col-span-2">
                    <AppInput
                      v-model="editForm.description"
                      label="Description"
                      placeholder="e.g. EFT – S VAN DER MERWE LEVY APR"
                    />
                  </div>
                  <AppSelect
                    v-model="editForm.type"
                    label="Type"
                    :options="ENTRY_TYPE_OPTS"
                  />
                  <AppSelect
                    v-model="editForm.unit_id"
                    label="Unit (optional)"
                    :options="unitOptions"
                    placeholder="Select unit..."
                  />
                  <div class="col-span-2">
                    <AppInput
                      v-model="editForm.notes"
                      label="Notes (optional)"
                      placeholder="e.g. Advance payment — May and June levies"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Proof of Payment Card -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="p-6 pb-3 flex items-center justify-between">
              <h3 class="font-body font-semibold text-base flex items-center gap-2 text-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                </svg>
                Proof of Payment
              </h3>
              <AppButton variant="outline" size="sm" :disabled="proofUploading" @click="triggerFileInput">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
                {{ proofUploading ? 'Uploading…' : (entry?.proof_of_payment_url ? 'Replace' : 'Upload') }}
              </AppButton>
            </div>
            <div class="px-6 pb-6">
              <!-- Hidden file input -->
              <input
                ref="fileInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                class="hidden"
                @change="onFileChange"
              />

              <!-- File uploaded state -->
              <template v-if="entry?.proof_of_payment_url">
                <div class="border rounded-lg overflow-hidden bg-muted/20">

                  <!-- Image preview -->
                  <img
                    v-if="proofIsImage(entry.proof_of_payment_url)"
                    :src="entry.proof_of_payment_url"
                    alt="Proof of payment"
                    class="w-full max-h-72 object-contain bg-muted/30"
                  />

                  <!-- PDF preview -->
                  <iframe
                    v-else-if="proofIsPdf(entry.proof_of_payment_url)"
                    :src="entry.proof_of_payment_url"
                    class="w-full h-72 border-0"
                    title="Proof of payment"
                  />

                  <!-- Filename row + actions -->
                  <div class="flex items-center gap-3 px-3 py-2 border-t bg-card">
                    <div class="w-7 h-7 rounded bg-muted flex items-center justify-center shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-xs font-medium text-foreground truncate">{{ proofFileName(entry.proof_of_payment_url) }}</p>
                      <p class="text-xs text-muted-foreground">Proof of payment</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                      <a :href="entry.proof_of_payment_url" target="_blank" class="p-1.5 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors" title="Open full size">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                      </a>
                      <button @click="downloadProof" class="p-1.5 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors" title="Download">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                      </button>
                      <button :disabled="proofDeleting" @click="removeProof" class="p-1.5 rounded hover:bg-muted text-muted-foreground hover:text-destructive transition-colors disabled:opacity-50" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Empty / drag-and-drop state -->
              <template v-else>
                <div
                  :class="[
                    'flex flex-col items-center justify-center py-8 text-center border-2 border-dashed rounded-lg transition-colors cursor-pointer',
                    isDragging ? 'border-amber-400 bg-amber-50' : 'border-border hover:border-muted-foreground/40'
                  ]"
                  @dragover="onDragOver"
                  @dragleave="onDragLeave"
                  @drop="onDrop"
                  @click="triggerFileInput"
                >
                  <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                    <svg v-if="proofUploading" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-muted-foreground">{{ proofUploading ? 'Uploading…' : 'Drop file here or click to browse' }}</p>
                  <p class="text-xs text-muted-foreground mt-1">PDF, JPG, PNG — max 10 MB</p>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- ── Right sidebar ─────────────────────────────────────────── -->
        <div class="space-y-4">

          <!-- Financial Summary card -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="p-5 space-y-4">
              <div>
                <p class="text-xs text-muted-foreground mb-1">Amount</p>
                <p :class="['text-2xl font-bold font-body whitespace-nowrap', amountClass]">
                  {{ formattedAmount }}
                </p>
              </div>
              <div class="h-px bg-border" />
              <div>
                <p class="text-xs text-muted-foreground mb-1">Status</p>
                <AppBadge :variant="isAllocated ? 'success' : 'warning'" bordered size="sm">
                  {{ isAllocated ? 'Allocated' : 'Unallocated' }}
                </AppBadge>
              </div>
              <template v-if="!isAllocated">
                <AppButton variant="primary" size="md" full @click="showAllocateModal = true">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>
                  </svg>
                  Allocate to Invoice
                </AppButton>
              </template>
            </div>
          </div>

          <!-- Context card -->
          <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div class="p-5 space-y-3">
              <!-- Estate -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
                  <path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/>
                  <path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/>
                  <path d="M8 10h.01"/><path d="M8 14h.01"/>
                </svg>
                <span class="text-muted-foreground">Estate:</span>
                <router-link
                  :to="`/estates/${entry.estate_id}`"
                  class="text-primary font-medium hover:underline"
                >{{ entry.estate?.name ?? entry.estate_id }}</router-link>
              </div>

              <!-- Unit -->
              <div v-if="entry.unit_id" class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/>
                  <line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>
                </svg>
                <span class="text-muted-foreground">Unit:</span>
                <router-link
                  :to="`/estates/${entry.unit?.estate_id ?? entry.estate_id}/units/${entry.unit_id}`"
                  class="text-primary font-medium hover:underline"
                >{{ entry.unit?.unit_number ?? entry.unit_id }}</router-link>
              </div>

              <!-- Invoice -->
              <div v-if="entry.invoice_id" class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                  <path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
                <span class="text-muted-foreground">Invoice:</span>
                <router-link
                  :to="`/billing/invoices/${entry.invoice_id}`"
                  class="text-primary font-medium hover:underline font-mono text-xs"
                >{{ entry.invoice?.invoice_number ?? entry.invoice_id }}</router-link>
              </div>

              <!-- Date -->
              <div class="flex items-center gap-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M8 2v4"/><path d="M16 2v4"/>
                  <rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>
                </svg>
                <span class="text-muted-foreground">Date:</span>
                <span class="text-foreground font-medium">{{ formatDate(entry.date) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Activity (full width) ──────────────────────────────────── -->
      <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
        <div class="p-6 pb-3 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/>
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
            <path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>
          </svg>
          <h3 class="font-body font-semibold text-lg text-foreground">Activity</h3>
        </div>
        <div class="px-6 pb-6">
          <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/>
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
              </svg>
            </div>
            <p class="text-sm font-medium text-muted-foreground">No activity yet</p>
            <p class="text-xs text-muted-foreground mt-1">Activity logging will be available in a future update</p>
          </div>
        </div>
      </div>
    </template>

  </div>

  <!-- Allocation modal -->
  <AllocationModal
    :show="showAllocateModal"
    :entry="entry"
    @close="showAllocateModal = false"
    @allocated="onAllocated"
  />
</template>
