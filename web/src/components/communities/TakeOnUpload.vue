<!--
  TakeOnUpload — reusable WeConnectU-style upload block for the Take-on page.
  One-click (WeConnectU-style): choosing a file uploads + imports it immediately.
  After success (or a "Not Applicable" toggle) the step is marked done and a
  status row shows the upload date + Download link, or "NOT APPLICABLE".

  Props:
    communityId        — the community UUID (kept for clarity)
    templatePath       — GET endpoint that streams the .xlsx template
    importPath         — POST endpoint that commits the upload
    notApplicablePath  — POST endpoint that marks the step Not Applicable
    downloadBase       — GET base for a stored entry file: `${downloadBase}/${id}/file`
    entries            — append-only history: [{ id, status, file_name, date, has_file }]
    templateName       — filename to save the template as
    instruction/linkText/linkSuffix — the grey instruction line

  Emits:
    changed — the history changed (parent should refetch)
-->
<script setup>
import { ref } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'

const props = defineProps({
  communityId:       { type: String, required: true },
  templatePath:      { type: String, required: true },
  importPath:        { type: String, required: true },
  notApplicablePath: { type: String, required: true },
  downloadBase:      { type: String, required: true },
  entries:           { type: Array, default: () => [] },
  templateName:      { type: String, default: 'template.xlsx' },
  instruction:       { type: String, default: '' },
  linkText:          { type: String, default: 'Click here' },
  linkSuffix:        { type: String, default: '' },
})

const emit = defineEmits(['changed'])
const { success, error } = useToast()

const fileInput = ref(null)
const uploading = ref(false)

async function downloadTemplate() {
  await streamDownload(props.templatePath, props.templateName)
}

async function downloadEntry(entry) {
  await streamDownload(`${props.downloadBase}/${entry.id}/file`, entry.file_name || 'take-on-file.xlsx')
}

async function streamDownload(path, name) {
  try {
    const res = await api.get(path, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = name
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(url)
  } catch {
    error('Could not download the file. Please try again.')
  }
}

function pickFile() {
  fileInput.value?.click()
}

// WeConnectU-style: choosing a file uploads + imports it immediately.
async function onFileChosen(e) {
  const chosen = e.target.files?.[0]
  if (!chosen) return
  await upload(chosen)
  if (fileInput.value) fileInput.value.value = ''
}

async function upload(file) {
  uploading.value = true
  try {
    const form = new FormData()
    form.append('file', file)
    const res = await api.post(props.importPath, form)
    success(res.data.message || 'Uploaded successfully.')
    if (res.data.error_count) {
      error(`${res.data.error_count} row(s) had issues and were skipped.`)
    }
    emit('changed')
  } catch (e) {
    error(e.response?.data?.message || 'Upload failed. Please use the template and try again.')
  } finally {
    uploading.value = false
  }
}

async function markNotApplicable() {
  try {
    await api.post(props.notApplicablePath)
    success('Take-on file status changed.')
    emit('changed')
  } catch (e) {
    error(e.response?.data?.message || 'Could not update the status. Please try again.')
  }
}
</script>

<template>
  <div>
    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileChosen" />

    <!-- Upload / Not Applicable bar -->
    <div class="flex justify-end gap-2 mb-3">
      <AppButton variant="primary" size="sm" :loading="uploading" @click="pickFile">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload
      </AppButton>
      <button
        type="button"
        class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-foreground/80 text-white hover:bg-foreground transition-colors"
        title="Mark as Not Applicable"
        @click="markNotApplicable"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/></svg>
      </button>
    </div>

    <!-- Instruction line with download-template link -->
    <div class="rounded-md bg-muted/50 px-4 py-3 text-sm text-foreground">
      {{ instruction }}
      <button type="button" class="text-accent hover:underline font-medium" @click="downloadTemplate">{{ linkText }}</button>
      {{ linkSuffix }}
    </div>

    <!-- Append-only history (WeConnectU: one row per upload / N/A marker) -->
    <div v-if="entries.length" class="mt-3 border-t border-border">
      <div
        v-for="entry in entries"
        :key="entry.id"
        class="flex items-center justify-between py-2 border-b border-border/60 last:border-b-0 text-sm"
      >
        <template v-if="entry.status === 'uploaded'">
          <span class="text-foreground tabular-nums">{{ entry.date }}</span>
          <button type="button" class="text-accent hover:underline font-medium" @click="downloadEntry(entry)">Download</button>
        </template>
        <span v-else class="font-semibold tracking-wide text-muted-foreground">NOT APPLICABLE</span>
      </div>
    </div>
  </div>
</template>
