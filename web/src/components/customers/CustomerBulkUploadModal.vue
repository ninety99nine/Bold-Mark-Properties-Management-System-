<template>
  <AppModal :show="show" size="lg" @close="handleClose">
    <template #header>
      <h3 class="w-full text-center text-base font-bold text-foreground">{{ title }}</h3>
    </template>

    <!-- Upload step -->
    <div v-if="step === 'upload'">
      <ul class="mb-6 space-y-2.5 text-sm text-[#3f4657]">
        <li class="flex items-start gap-2">
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>
            Download and use the
            <button type="button" class="text-[#2f6fb0] hover:underline disabled:opacity-50" :disabled="downloading" @click="downloadTemplate">
              {{ downloading ? 'downloading…' : 'template file here' }}
            </button>
          </span>
        </li>
        <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>The file must conform to the rules laid out in the template file.</li>
        <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>DO NOT remove / add columns or alter headers.</li>
      </ul>

      <div
        class="mb-5 rounded-lg border-2 border-dashed py-10 px-6 text-center transition-colors"
        :class="dragging ? 'border-navy bg-navy/5' : 'border-border'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop"
      >
        <p v-if="!file" class="text-sm text-muted-foreground">Drag and drop your file here</p>
        <p v-else class="text-sm font-medium text-foreground">{{ file.name }}</p>
      </div>

      <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleFileSelect" />
      <AppButton variant="secondary" :loading="importing" @click="pickAndImport">
        <span class="inline-flex items-center gap-2">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          Click to select upload file
        </span>
      </AppButton>
    </div>

    <!-- Result step -->
    <div v-else class="py-4 text-center">
      <div
        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full"
        :class="result?.skipped ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
      >
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <p class="mb-1 text-lg font-semibold text-foreground">{{ result?.imported ?? 0 }} record{{ (result?.imported ?? 0) === 1 ? '' : 's' }} updated</p>
      <p v-if="result?.skipped" class="text-sm text-muted-foreground">{{ result.skipped }} row{{ result.skipped === 1 ? '' : 's' }} skipped</p>

      <div v-if="result?.errors?.length" class="mt-4 max-h-48 divide-y divide-border overflow-auto rounded border border-border text-left">
        <div v-for="(e, i) in result.errors" :key="i" class="px-3 py-2 text-xs text-muted-foreground">
          <span class="font-medium text-foreground">Row {{ e.row }}:</span> {{ (e.errors || []).join('; ') }}
        </div>
      </div>

      <div class="mt-6 flex justify-center gap-3">
        <AppButton variant="outline" @click="resetToUpload">Upload another</AppButton>
        <AppButton variant="secondary" @click="finishAndClose">Done</AppButton>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/composables/useApi'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'

const props = defineProps({
  show:        { type: Boolean, default: false },
  communityId: { type: String,  required: true },
  title:       { type: String,  required: true },
  // 'notes' | 'mandates'
  kind:        { type: String,  required: true },
})
const emit = defineEmits(['close', 'imported'])

const step        = ref('upload')
const file        = ref(null)
const fileInput   = ref(null)
const dragging    = ref(false)
const downloading = ref(false)
const importing   = ref(false)
const error       = ref(null)
const result      = ref(null)

const templatePath = () => props.kind === 'notes'
  ? `/communities/${props.communityId}/customers/notes-template`
  : `/communities/${props.communityId}/customers/mandates-template`

const importPath = () => props.kind === 'notes'
  ? `/communities/${props.communityId}/customers/notes-import`
  : `/communities/${props.communityId}/customers/mandates-import`

const templateFilename = () => props.kind === 'notes'
  ? 'upload-customer-notes-template.xlsx'
  : 'update-debit-order-mandates-template.xlsx'

async function downloadTemplate() {
  downloading.value = true
  try {
    const res = await api.get(templatePath(), { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: templateFilename() })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    error.value = 'Could not download the template file.'
  } finally {
    downloading.value = false
  }
}

function handleFileSelect(event) {
  const f = event.target.files?.[0]
  if (f) { file.value = f; runImport() }
}
function handleDrop(event) {
  dragging.value = false
  const f = event.dataTransfer.files?.[0]
  if (f) { file.value = f; runImport() }
}
function pickAndImport() {
  if (file.value) { runImport(); return }
  fileInput.value?.click()
}

async function runImport() {
  if (!file.value || importing.value) return
  importing.value = true
  error.value = null
  try {
    const form = new FormData()
    form.append('file', file.value)
    const res = await api.post(importPath(), form)
    result.value = res.data
    step.value = 'result'
  } catch (e) {
    error.value = e.response?.data?.message || 'Upload failed. Check the file and try again.'
  } finally {
    importing.value = false
  }
}

function reset() {
  step.value = 'upload'
  file.value = null
  error.value = null
  result.value = null
  dragging.value = false
  if (fileInput.value) fileInput.value.value = ''
}
function resetToUpload() { reset() }

function handleClose() {
  if (result.value?.imported) emit('imported')
  emit('close')
}
function finishAndClose() {
  if (result.value?.imported) emit('imported')
  emit('close')
}

watch(() => props.show, (v) => { if (v) reset() })
</script>
