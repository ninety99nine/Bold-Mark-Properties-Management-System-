<template>
  <AppModal :show="show" size="lg" @close="handleClose">
    <template #header>
      <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">Upload New Occupants</h3>
    </template>

    <!-- ─── Upload step ─── -->
    <div v-if="step === 'upload'">
      <!-- Download occupants template -->
      <button
        type="button"
        class="app-link text-sm mb-5"
        :disabled="downloading"
        @click="downloadTemplate"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        {{ downloading ? 'Downloading…' : 'Download occupants template file' }}
      </button>

      <!-- Rules -->
      <ul class="space-y-2.5 mb-6 text-sm text-[#3f4657]">
        <li class="flex items-start gap-2"><span class="text-[#E8A040] mt-0.5">&#9888;</span> The file must conform to the rules laid out in the template file.</li>
        <li class="flex items-start gap-2"><span class="text-[#E8A040] mt-0.5">&#9888;</span> DO NOT remove / add columns or alter headers.</li>
      </ul>

      <!-- Drop zone -->
      <div
        class="border-2 border-dashed rounded-lg py-10 px-6 text-center transition-colors mb-5"
        :class="dragging ? 'border-[#1F3A5C] bg-[#1F3A5C]/5' : 'border-[#DCDEE8]'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop"
      >
        <p v-if="!file" class="text-sm text-[#717B99]">Drag and drop your file here</p>
        <p v-else class="text-sm text-[#1E2740] font-medium">{{ file.name }} <span class="text-[#717B99] font-normal">({{ formatFileSize(file.size) }})</span></p>
      </div>

      <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleFileSelect" />
      <button
        type="button"
        :disabled="importing"
        class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition-colors disabled:opacity-60"
        @click="pickAndImport"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        {{ importing ? 'Uploading…' : (file ? 'Upload file' : 'Click to upload template') }}
      </button>
    </div>

    <!-- ─── Results step ─── -->
    <div v-else-if="step === 'result'" class="text-center py-4">
      <div
        class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
        :class="result?.error_count ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
      >
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <p class="text-lg font-semibold text-[#1E2740] mb-1">{{ result?.imported ?? 0 }} occupant{{ (result?.imported ?? 0) === 1 ? '' : 's' }} imported</p>
      <p v-if="result?.skipped" class="text-sm text-[#717B99]">{{ result.skipped }} row{{ result.skipped === 1 ? '' : 's' }} skipped (no matching unit)</p>

      <div v-if="result?.errors?.length" class="mt-4 text-left max-h-48 overflow-auto rounded border border-[#DCDEE8] divide-y divide-[#DCDEE8]">
        <div v-for="(e, i) in result.errors" :key="i" class="px-3 py-2 text-xs text-[#717B99]">
          <span class="font-medium text-[#1E2740]">Row {{ e.row }}:</span> {{ (e.errors || []).join('; ') }}
        </div>
      </div>

      <div class="mt-6 flex justify-center gap-3">
        <AppButton variant="outline" @click="resetToUpload">Upload another</AppButton>
        <AppButton variant="primary" @click="finishAndClose">Done</AppButton>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import api from '@/composables/useApi.js'

const props = defineProps({
  show:        { type: Boolean, default: false },
  communityId: { type: String,  required: true },
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

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function downloadTemplate() {
  downloading.value = true
  try {
    const res = await api.get(`/communities/${props.communityId}/units/occupants/export`, {
      params: { _format: 'xlsx' },
      responseType: 'blob',
    })
    let filename = 'occupants template.xlsx'
    const cd = res.headers['content-disposition']
    const m = cd && cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
    if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    error.value = 'Could not download the template.'
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
    const parsed = await api.post(`/communities/${props.communityId}/units/occupants-import/parse`, form)
    const rows = parsed.data.rows ?? []
    if (!rows.length) { error.value = 'The file contains no data rows.'; return }
    const res = await api.post(`/communities/${props.communityId}/units/occupants-import`, { rows })
    result.value = res.data
    step.value = 'result'
  } catch (e) {
    error.value = e.response?.data?.message || 'Import failed. Check the file and try again.'
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
