<template>
  <AppModal :show="show" size="lg" @close="handleClose">
    <template #header>
      <h3 class="text-base font-bold text-[#1E2740]" style="font-family: 'DM Sans', sans-serif">Upload New Unit PQs</h3>
    </template>

    <div class="py-1">
      <p class="text-sm text-[#1E2740] mb-3">
        To upload new unit PQs, please download and use the template provided below:
      </p>

      <button
        type="button"
        class="app-link text-sm"
        :disabled="downloading"
        @click="downloadTemplate"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" />
        </svg>
        {{ downloading ? 'Downloading…' : 'Download Unit PQs' }}
      </button>

      <div
        class="mt-6 rounded-xl border-2 border-dashed px-6 py-10 text-center cursor-pointer transition-colors"
        :class="dragging ? 'border-[#D89B4B] bg-amber-50' : 'border-[#DCDEE8] hover:border-[#1F3A5C] hover:bg-[#1F3A5C]/[0.03]'"
        @click="fileInputRef?.click()"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop"
      >
        <div
          class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full transition-colors"
          :class="dragging ? 'bg-[#D89B4B]/15' : 'bg-[#1F3A5C]/5'"
        >
          <svg v-if="!uploading" class="w-6 h-6" :class="dragging ? 'text-[#C6862B]' : 'text-[#1F3A5C]'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242" />
            <path d="M12 12v9" />
            <path d="m16 16-4-4-4 4" />
          </svg>
          <svg v-else class="w-6 h-6 animate-spin text-[#1F3A5C]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
        </div>

        <p v-if="uploading" class="text-sm font-semibold text-[#1F3A5C]">Uploading…</p>
        <p v-else-if="dragging" class="text-sm font-semibold text-[#C6862B]">Drop to upload</p>
        <p v-else class="text-sm text-[#717B99]">
          <span class="font-semibold text-[#1E2740]">Click to upload batch file</span> or drag &amp; drop
        </p>
        <p class="mt-1 text-xs text-[#717B99]">.xlsx, .xls or .csv</p>

        <input ref="fileInputRef" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleUpload" />
      </div>

      <p v-if="error" class="mt-4 text-sm text-[#F75A68] flex items-center gap-1.5">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ error }}
      </p>
    </div>
  </AppModal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { getToken } from '@/composables/authStorage'

const props = defineProps<{
  show: boolean
  communityId: string
}>()

const emit = defineEmits<{
  close: []
  imported: [result: { updated: number; not_found: string[]; message: string }]
}>()

const API_URL = import.meta.env.VITE_API_URL ?? ''

const fileInputRef = ref<HTMLInputElement | null>(null)
const downloading  = ref(false)
const uploading    = ref(false)
const dragging     = ref(false)
const error        = ref('')

function authHeaders(): Record<string, string> {
  return { Authorization: `Bearer ${getToken()}` }
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href: url, download: filename })
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

async function downloadTemplate() {
  downloading.value = true
  error.value = ''
  try {
    const res = await fetch(
      `${API_URL}/api/v1/communities/${props.communityId}/units/pq/export?format=xlsx`,
      { headers: authHeaders() },
    )
    if (!res.ok) throw new Error('download failed')
    const blob = await res.blob()
    const cd = res.headers.get('content-disposition') ?? ''
    let filename = 'unit-pqs.xlsx'
    const m = cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
    if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
    triggerDownload(blob, filename)
  } catch {
    error.value = 'Could not download the batch file.'
  } finally {
    downloading.value = false
  }
}

function handleUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (file) uploadFile(file)
}

function handleDrop(event: DragEvent) {
  dragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) uploadFile(file)
}

async function uploadFile(file: File) {
  if (uploading.value) return
  uploading.value = true
  error.value = ''

  const formData = new FormData()
  formData.append('file', file)

  try {
    const res = await fetch(
      `${API_URL}/api/v1/communities/${props.communityId}/units/pq/import`,
      { method: 'POST', headers: authHeaders(), body: formData },
    )
    const json = await res.json()
    if (!res.ok) {
      error.value = json.message ?? 'Import failed. Please check the file and try again.'
      return
    }
    emit('imported', json)
    emit('close')
  } catch {
    error.value = 'Could not reach the server. Please check your connection and try again.'
  } finally {
    uploading.value = false
  }
}

function handleClose() {
  error.value = ''
  emit('close')
}

watch(() => props.show, (val) => { if (val) { error.value = ''; uploading.value = false; dragging.value = false } })
</script>
