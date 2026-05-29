<template>
  <AppModal :show="show" size="xl" @close="handleClose">
    <template #header>
      <div class="flex items-center gap-3">
        <h3 class="text-base font-bold text-[#1E2740]" style="font-family: 'DM Sans', sans-serif">Upload PQs</h3>
        <span class="text-sm font-normal text-[#717B99]">Step {{ step + 1 }} of 4</span>
      </div>
    </template>

    <!-- Progress bar -->
    <div class="flex gap-1 mb-6">
      <div
        v-for="i in 4"
        :key="i"
        class="h-1 flex-1 rounded-full transition-colors"
        :class="i - 1 <= step ? 'bg-[#D89B4B]' : 'bg-[#DCDEE8]'"
      />
    </div>

    <!-- ─── Step 0: Download + Upload ─── -->
    <div v-if="step === 0">
      <p class="text-sm text-[#717B99] mb-5">
        Download the <strong class="text-[#1E2740]">PQ template</strong> pre-filled with your unit numbers, enter each unit's
        participation quota value, then upload the completed file.
      </p>

      <!-- Download template -->
      <div class="border border-[#DCDEE8] rounded p-4 mb-5">
        <p class="text-sm font-medium text-[#1E2740] mb-3">1. Download your pre-filled file</p>
        <div class="flex gap-3">
          <button
            type="button"
            class="flex items-center gap-2 px-4 py-2 text-sm border border-[#DCDEE8] rounded hover:border-[#1F3A5C] transition-colors disabled:opacity-50"
            :disabled="downloadingCsv"
            @click="downloadTemplate('csv')"
          >
            <svg class="w-4 h-4 text-[#717B99]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>{{ downloadingCsv ? 'Downloading...' : 'CSV Download' }}</span>
          </button>
          <button
            type="button"
            class="flex items-center gap-2 px-4 py-2 text-sm border border-[#DCDEE8] rounded hover:border-[#1F3A5C] transition-colors disabled:opacity-50"
            :disabled="downloadingXlsx"
            @click="downloadTemplate('xlsx')"
          >
            <svg class="w-4 h-4 text-[#22c55e]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>{{ downloadingXlsx ? 'Downloading...' : 'Excel Download' }}</span>
          </button>
        </div>
      </div>

      <!-- Upload zone -->
      <div class="border border-[#DCDEE8] rounded p-4 mb-5">
        <p class="text-sm font-medium text-[#1E2740] mb-3">2. Upload your completed file</p>

        <div
          v-if="!selectedFile"
          class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
          :class="isDragOver ? 'border-[#D89B4B] bg-amber-50' : 'border-[#DCDEE8] hover:border-[#1F3A5C]'"
          @dragover.prevent="isDragOver = true"
          @dragleave.prevent="isDragOver = false"
          @drop.prevent="handleDrop"
          @click="fileInputRef?.click()"
        >
          <svg class="w-10 h-10 mx-auto text-[#717B99] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <p class="text-sm text-[#1E2740] font-medium mb-1">Drop your file here or click to browse</p>
          <p class="text-xs text-[#717B99]">Supports .csv, .xlsx (max 5MB)</p>
        </div>

        <div
          v-else
          class="border-2 border-[#22c55e] bg-green-50 rounded-lg px-4 py-3 flex items-center gap-3"
        >
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
            :class="selectedFile.name.endsWith('.csv') ? 'bg-blue-100' : 'bg-green-100'"
          >
            <svg
              class="w-5 h-5"
              :class="selectedFile.name.endsWith('.csv') ? 'text-blue-600' : 'text-[#22c55e]'"
              fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E2740] truncate">{{ selectedFile.name }}</p>
            <p class="text-xs text-[#717B99] mt-0.5">{{ formatFileSize(selectedFile.size) }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <AppButton variant="outline" size="sm" @click="fileInputRef?.click()">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Change file
            </AppButton>
            <AppButton variant="ghost" size="sm" square @click="removeFile">
              <svg class="w-4 h-4 text-[#717B99]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </AppButton>
          </div>
        </div>

        <input ref="fileInputRef" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleFileSelect" />
      </div>

      <!-- Expected columns -->
      <div>
        <p class="text-xs font-medium text-[#717B99] uppercase tracking-wide mb-2">Expected columns</p>
        <div class="flex flex-wrap gap-2">
          <span class="px-2 py-1 text-xs rounded bg-[#1F3A5C]/10 text-[#1F3A5C] font-medium">
            unit_number<span class="text-[#F75A68] ml-0.5">*</span>
          </span>
          <span class="px-2 py-1 text-xs rounded bg-[#EDEFF5] text-[#717B99]">section</span>
          <span class="px-2 py-1 text-xs rounded bg-[#1F3A5C]/10 text-[#1F3A5C] font-medium">
            pq<span class="text-[#F75A68] ml-0.5">*</span>
          </span>
          <span class="px-2 py-1 text-xs rounded bg-[#EDEFF5] text-[#717B99]">levy_override</span>
        </div>
        <p class="text-xs text-[#717B99] mt-2">* Required fields &nbsp;·&nbsp; Leave <code class="bg-muted px-1 rounded">levy_override</code> empty to clear an existing override.</p>
      </div>

      <p v-if="parseError" class="mt-4 text-sm text-[#F75A68] flex items-center gap-1">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ parseError }}
      </p>
    </div>

    <!-- ─── Step 1: Map Columns ─── -->
    <div v-else-if="step === 1">
      <p class="text-sm text-[#717B99] mb-5">
        Map the columns from your file to the system fields. Required fields are marked with <span class="text-[#F75A68]">*</span>.
      </p>

      <div class="rounded border border-[#DCDEE8] overflow-hidden mb-4">
        <table class="w-full text-sm">
          <thead class="bg-[#F8FBFF]">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-[#717B99] text-xs uppercase tracking-wide w-1/2">Your file column</th>
              <th class="text-left px-4 py-3 font-medium text-[#717B99] text-xs uppercase tracking-wide w-1/2">System field</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#DCDEE8]">
            <tr v-for="col in fileColumns" :key="col" class="hover:bg-[#F8FBFF]">
              <td class="px-4 py-3 text-[#1E2740] font-medium">{{ col }}</td>
              <td class="px-4 py-3">
                <AppSelect
                  :modelValue="columnMapping[col]"
                  :options="PQ_FIELDS.map(f => ({ value: f.key, label: f.label + (f.required ? ' *' : '') }))"
                  placeholder="— Skip this column —"
                  @update:modelValue="columnMapping[col] = $event"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="missingRequiredFields.length" class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>
          Required fields not mapped: <strong>{{ missingRequiredFields.join(', ') }}</strong>
        </span>
      </div>
    </div>

    <!-- ─── Step 2: Preview ─── -->
    <div v-else-if="step === 2">
      <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="border border-[#DCDEE8] rounded p-3 text-center">
          <p class="text-2xl font-bold text-[#1E2740]">{{ previewRows.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Total rows</p>
        </div>
        <div class="border border-[#22c55e] rounded p-3 text-center bg-green-50">
          <p class="text-2xl font-bold text-[#22c55e]">{{ validRows.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Valid</p>
        </div>
        <div class="border rounded p-3 text-center" :class="invalidRows.length ? 'border-[#F75A68] bg-red-50' : 'border-[#DCDEE8]'">
          <p class="text-2xl font-bold" :class="invalidRows.length ? 'text-[#F75A68]' : 'text-[#717B99]'">{{ invalidRows.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Errors</p>
        </div>
      </div>

      <p v-if="invalidRows.length" class="text-xs text-[#F75A68] mb-3 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Fix the <strong>{{ invalidRows.length }} error{{ invalidRows.length > 1 ? 's' : '' }}</strong> in your file and re-upload before importing.
      </p>
      <p v-else class="text-xs text-[#717B99] mb-3">
        All <strong class="text-[#22c55e]">{{ validRows.length }} rows</strong> passed validation and are ready to import.
      </p>

      <div class="rounded border border-[#DCDEE8] overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-[#F8FBFF]">
            <tr>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Row</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Unit #</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Section</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">PQ</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Levy Override</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#DCDEE8]">
            <tr
              v-for="row in paginatedRows"
              :key="row.__rowIndex"
              :class="row.__errors.length ? 'bg-red-50' : 'hover:bg-[#F8FBFF]'"
            >
              <td class="px-3 py-2 text-[#717B99]">{{ row.__rowIndex }}</td>
              <td class="px-3 py-2 font-medium text-[#1E2740]">{{ row.unit_number || '—' }}</td>
              <td class="px-3 py-2 text-[#717B99]">{{ row.section || '—' }}</td>
              <td class="px-3 py-2 font-mono text-[#1E2740]">{{ row.pq !== '' && row.pq !== undefined ? row.pq : '—' }}</td>
              <td class="px-3 py-2 font-mono text-[#1E2740]">
                <span v-if="row.levy_override !== '' && row.levy_override !== undefined">{{ row.levy_override }}</span>
                <span v-else class="text-[#717B99] italic text-[10px]">clear</span>
              </td>
              <td class="px-3 py-2">
                <span v-if="!row.__errors.length" class="text-[#22c55e] flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Valid
                </span>
                <AppPoptip v-else position="top" max-width="260px">
                  <template #trigger>
                    <span class="text-[#F75A68] flex items-center gap-1 cursor-default underline decoration-dotted underline-offset-2">
                      <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      {{ row.__errors.length }} error{{ row.__errors.length > 1 ? 's' : '' }}
                    </span>
                  </template>
                  <div class="p-3">
                    <p class="text-xs font-semibold text-[#1E2740] mb-2">Validation errors</p>
                    <ul class="space-y-1.5">
                      <li v-for="err in row.__errors" :key="err" class="flex items-start gap-2 text-xs text-[#1E2740]">
                        <span class="mt-1 w-1.5 h-1.5 rounded-full bg-[#F75A68] shrink-0" />
                        {{ err }}
                      </li>
                    </ul>
                  </div>
                </AppPoptip>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="totalPages > 1" class="flex items-center justify-between mt-3 text-xs text-[#717B99]">
        <span>Showing {{ pageStart }}–{{ pageEnd }} of {{ previewRows.length }}</span>
        <div class="flex gap-2">
          <button type="button" class="px-3 py-1 border border-[#DCDEE8] rounded disabled:opacity-40" :disabled="page === 1" @click="page--">Previous</button>
          <button type="button" class="px-3 py-1 border border-[#DCDEE8] rounded disabled:opacity-40" :disabled="page === totalPages" @click="page++">Next</button>
        </div>
      </div>
    </div>

    <!-- ─── Step 3: Results ─── -->
    <div v-else-if="step === 3">
      <div class="text-center mb-6">
        <div
          class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3"
          :class="importResult?.not_found?.length ? 'bg-amber-100' : 'bg-green-100'"
        >
          <svg
            class="w-8 h-8"
            :class="importResult?.not_found?.length ? 'text-[#D89B4B]' : 'text-[#22c55e]'"
            fill="none" viewBox="0 0 24 24" stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-base font-semibold text-[#1E2740]">Import Complete</h3>
        <p class="text-sm text-[#717B99] mt-1">{{ importResult?.message }}</p>
      </div>

      <div class="flex gap-3 mb-5">
        <div class="border border-[#22c55e] rounded p-3 text-center bg-green-50 flex-1">
          <p class="text-2xl font-bold text-[#22c55e]">{{ importResult?.updated ?? 0 }}</p>
          <p class="text-xs text-[#717B99] mt-1">Units updated</p>
        </div>
        <div v-if="importResult?.not_found?.length" class="border border-[#D89B4B] rounded p-3 text-center bg-amber-50 flex-1">
          <p class="text-2xl font-bold text-[#D89B4B]">{{ importResult.not_found.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Rows skipped</p>
        </div>
      </div>

      <div v-if="importResult?.not_found?.length" class="rounded border border-[#D89B4B] overflow-hidden">
        <div class="bg-amber-50 px-4 py-2 border-b border-[#D89B4B]">
          <p class="text-sm font-medium text-[#D89B4B]">Unit numbers not found in this estate</p>
        </div>
        <div class="px-4 py-3 flex flex-wrap gap-2">
          <span
            v-for="num in importResult.not_found"
            :key="num"
            class="inline-flex px-2 py-0.5 rounded text-xs font-mono bg-amber-100 text-amber-800"
          >{{ num }}</span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <template #footer>
      <!-- Step 0 -->
      <template v-if="step === 0">
        <AppButton variant="outline" @click="handleClose">Cancel</AppButton>
        <AppButton variant="primary" :disabled="!selectedFile || parsing" @click="parseFile">
          <svg v-if="parsing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ parsing ? 'Parsing...' : 'Next: Map Columns' }}
        </AppButton>
      </template>

      <!-- Step 1 -->
      <template v-else-if="step === 1">
        <AppButton variant="outline" @click="step = 0">Back</AppButton>
        <AppButton variant="primary" :disabled="missingRequiredFields.length > 0" @click="applyMappingAndPreview">
          Next: Preview
        </AppButton>
      </template>

      <!-- Step 2 -->
      <template v-else-if="step === 2">
        <AppButton variant="outline" @click="step = 1">Back</AppButton>
        <AppButton variant="primary" :disabled="validRows.length === 0 || invalidRows.length > 0 || importing" @click="runImport">
          <svg v-if="importing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ importing ? 'Importing...' : `Import ${validRows.length} unit${validRows.length === 1 ? '' : 's'}` }}
        </AppButton>
      </template>

      <!-- Step 3 -->
      <template v-else-if="step === 3">
        <AppButton variant="outline" @click="resetWizard">Import Another File</AppButton>
        <AppButton variant="primary" @click="finishAndClose">Done</AppButton>
      </template>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import AppPoptip from './AppPoptip.vue'
import AppSelect from './AppSelect.vue'

const props = defineProps<{
  show: boolean
  estateId: string
}>()

const emit = defineEmits<{
  close: []
  imported: []
}>()

const API_URL = import.meta.env.VITE_API_URL ?? ''

const PQ_FIELDS = [
  { key: 'unit_number',   label: 'Unit Number',         required: true },
  { key: 'section',       label: 'Section',             required: false },
  { key: 'pq',           label: 'PQ',                   required: true },
  { key: 'levy_override', label: 'Admin Levy Override',  required: false },
]

// ─── State ────────────────────────────────────────────────────────────────────

const step          = ref(0)
const selectedFile  = ref<File | null>(null)
const isDragOver    = ref(false)
const fileInputRef  = ref<HTMLInputElement | null>(null)
const parseError    = ref('')
const parsing       = ref(false)
const importing     = ref(false)
const downloadingCsv  = ref(false)
const downloadingXlsx = ref(false)

const fileColumns   = ref<string[]>([])
const fileRows      = ref<Record<string, string>[]>([])
const columnMapping = ref<Record<string, string>>({})

const previewRows = ref<Array<Record<string, any> & { __rowIndex: number; __errors: string[] }>>([])
const page        = ref(1)
const PAGE_SIZE   = 10

const importResult = ref<{ updated: number; not_found: string[]; message: string } | null>(null)

// ─── Computed ─────────────────────────────────────────────────────────────────

const missingRequiredFields = computed(() => {
  const mapped = new Set(Object.values(columnMapping.value).filter(Boolean))
  return PQ_FIELDS.filter(f => f.required && !mapped.has(f.key)).map(f => f.label)
})

const validRows   = computed(() => previewRows.value.filter(r => r.__errors.length === 0))
const invalidRows = computed(() => previewRows.value.filter(r => r.__errors.length > 0))

const totalPages  = computed(() => Math.ceil(previewRows.value.length / PAGE_SIZE))
const pageStart   = computed(() => (page.value - 1) * PAGE_SIZE + 1)
const pageEnd     = computed(() => Math.min(page.value * PAGE_SIZE, previewRows.value.length))
const paginatedRows = computed(() => previewRows.value.slice(pageStart.value - 1, pageEnd.value))

// ─── Helpers ──────────────────────────────────────────────────────────────────

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a   = Object.assign(document.createElement('a'), { href: url, download: filename })
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('auth_token')
  return { Authorization: `Bearer ${token}` }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function normalizeKey(str: string): string {
  return str.toLowerCase().replace(/[\s_\-\.]+/g, '_')
}

function autoDetectMapping(columns: string[]): Record<string, string> {
  const mapping: Record<string, string> = {}
  for (const col of columns) {
    const normalized = normalizeKey(col)
    const match = PQ_FIELDS.find(f => {
      const fk = normalizeKey(f.key)
      const fl = normalizeKey(f.label)
      return normalized === fk || normalized === fl || fk.includes(normalized) || normalized.includes(fk)
    })
    mapping[col] = match?.key ?? ''
  }
  return mapping
}

function validateRow(row: Record<string, string>): string[] {
  const errors: string[] = []
  if (!row.unit_number?.trim()) errors.push('Unit number is required')
  const pqRaw = row.pq?.trim()
  if (!pqRaw) {
    errors.push('PQ value is required')
  } else {
    const pqNum = parseFloat(pqRaw)
    if (isNaN(pqNum) || pqNum < 0 || pqNum > 100) {
      errors.push('PQ must be a number between 0 and 100')
    }
  }
  const overrideRaw = row.levy_override?.trim()
  if (overrideRaw) {
    const overrideNum = parseFloat(overrideRaw)
    if (isNaN(overrideNum) || overrideNum < 0) {
      errors.push('Admin levy override must be a positive number')
    }
  }
  return errors
}

// ─── Template download (server-side, pre-filled with unit numbers) ─────────────

async function downloadTemplate(format: 'csv' | 'xlsx') {
  const loading = format === 'csv' ? downloadingCsv : downloadingXlsx
  loading.value = true
  try {
    const res = await fetch(
      `${API_URL}/api/v1/estates/${props.estateId}/units/pq/export?format=${format}`,
      { headers: authHeaders() }
    )
    if (!res.ok) throw new Error('Download failed')
    const blob = await res.blob()
    const cd   = res.headers.get('content-disposition') ?? ''
    let filename = `pq-template.${format}`
    const m = cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
    if (m?.[1]) {
      const extracted = m[1].replace(/['"]/g, '').trim()
      if (extracted) filename = extracted
    }
    triggerDownload(blob, filename)
  } catch (e) {
    console.error('Template download failed:', e)
  } finally {
    loading.value = false
  }
}

// ─── File handling ────────────────────────────────────────────────────────────

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files?.[0]) {
    selectedFile.value = input.files[0]
    parseError.value   = ''
  }
  input.value = ''
}

function handleDrop(event: DragEvent) {
  isDragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    selectedFile.value = file
    parseError.value   = ''
  }
}

function removeFile() {
  selectedFile.value = null
  parseError.value   = ''
  if (fileInputRef.value) fileInputRef.value.value = ''
}

// ─── Wizard steps ─────────────────────────────────────────────────────────────

async function parseFile() {
  if (!selectedFile.value) return
  parsing.value    = true
  parseError.value = ''

  const formData = new FormData()
  formData.append('file', selectedFile.value)

  try {
    const res  = await fetch(
      `${API_URL}/api/v1/estates/${props.estateId}/units/pq/parse`,
      { method: 'POST', headers: authHeaders(), body: formData }
    )
    const json = await res.json()
    if (!res.ok) {
      parseError.value = json.message ?? 'Failed to parse file.'
      return
    }
    fileColumns.value   = json.columns ?? []
    fileRows.value      = json.rows ?? []
    columnMapping.value = autoDetectMapping(fileColumns.value)
    step.value          = 1
  } catch {
    parseError.value = 'Could not read the file. Please check the format and try again.'
  } finally {
    parsing.value = false
  }
}

function applyMappingAndPreview() {
  const mapped = fileRows.value.map((rawRow, idx) => {
    const row: Record<string, string> = {}
    for (const [fileCol, systemField] of Object.entries(columnMapping.value)) {
      if (systemField) row[systemField] = rawRow[fileCol] ?? ''
    }
    const errors = validateRow(row)
    return { ...row, __rowIndex: idx + 1, __errors: errors }
  })
  previewRows.value = mapped
  page.value        = 1
  step.value        = 2
}

async function runImport() {
  importing.value = true
  const formData  = new FormData()

  // Rebuild a CSV in memory from the valid rows for the existing file-based import endpoint
  const headers = ['unit_number', 'section', 'pq', 'levy_override']
  const lines   = [headers.join(',')]
  for (const row of validRows.value) {
    lines.push(headers.map(h => `"${(row[h] ?? '').toString().replace(/"/g, '""')}"`).join(','))
  }
  const blob = new Blob([lines.join('\r\n')], { type: 'text/csv' })
  formData.append('file', blob, 'pq-import.csv')

  try {
    const res  = await fetch(
      `${API_URL}/api/v1/estates/${props.estateId}/units/pq/import`,
      { method: 'POST', headers: authHeaders(), body: formData }
    )
    const json = await res.json()
    if (!res.ok) { console.error(json); return }
    importResult.value = json
    step.value         = 3
    emit('imported')
  } catch (e) {
    console.error(e)
  } finally {
    importing.value = false
  }
}

function resetWizard() {
  step.value          = 0
  selectedFile.value  = null
  fileColumns.value   = []
  fileRows.value      = []
  columnMapping.value = {}
  previewRows.value   = []
  importResult.value  = null
  parseError.value    = ''
  page.value          = 1
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function handleClose() {
  emit('close')
}

function finishAndClose() {
  emit('close')
  setTimeout(resetWizard, 300)
}

watch(() => props.show, (val) => { if (val) resetWizard() })
</script>
