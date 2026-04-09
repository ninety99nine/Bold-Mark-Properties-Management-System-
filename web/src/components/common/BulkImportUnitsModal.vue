<template>
  <AppModal :show="show" size="xl" @close="handleClose">
    <template #header>
      <div class="flex items-center gap-3">
        <h3 class="text-base font-bold text-[#1E2740]" style="font-family: 'DM Sans', sans-serif">Bulk Import Units</h3>
        <span class="text-sm font-normal text-[#717B99]">Step {{ step + 1 }} of 4</span>
      </div>
    </template>

    <!-- Step progress bar -->
    <div class="flex gap-1 mb-6">
      <div
        v-for="i in 4"
        :key="i"
        class="h-1 flex-1 rounded-full transition-colors"
        :class="i - 1 <= step ? 'bg-[#D89B4B]' : 'bg-[#DCDEE8]'"
      />
    </div>

    <!-- ─── Step 0: Upload ─── -->
    <div v-if="step === 0">
      <p class="text-sm text-[#717B99] mb-5">
        Download the <strong class="text-[#1E2740]">{{ estateTypeLabel }}</strong> template, fill in your unit data, then upload the completed file.
        The template includes 5 example rows showing different scenarios — delete them before importing.
      </p>

      <!-- Template download -->
      <div class="border border-[#DCDEE8] rounded p-4 mb-5">
        <p class="text-sm font-medium text-[#1E2740] mb-3">1. Download the import template</p>
        <div class="flex gap-3">
          <button
            type="button"
            class="flex items-center gap-2 px-4 py-2 text-sm border border-[#DCDEE8] rounded hover:border-[#1F3A5C] transition-colors"
            :disabled="downloadingCsv"
            @click="downloadTemplate('csv')"
          >
            <svg class="w-4 h-4 text-[#717B99]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>{{ downloadingCsv ? 'Downloading...' : 'CSV Template' }}</span>
          </button>
          <button
            type="button"
            class="flex items-center gap-2 px-4 py-2 text-sm border border-[#DCDEE8] rounded hover:border-[#1F3A5C] transition-colors"
            :disabled="downloadingXlsx"
            @click="downloadTemplate('xlsx')"
          >
            <svg class="w-4 h-4 text-[#22c55e]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>{{ downloadingXlsx ? 'Downloading...' : 'Excel Template' }}</span>
          </button>
        </div>
      </div>

      <!-- File upload zone -->
      <div class="border border-[#DCDEE8] rounded p-4 mb-5">
        <p class="text-sm font-medium text-[#1E2740] mb-3">2. Upload your completed file</p>

        <!-- Empty state: drop zone -->
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

        <!-- File attached state -->
        <div
          v-else
          class="border-2 border-[#22c55e] bg-green-50 rounded-lg px-4 py-3 flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
            :class="selectedFile.name.endsWith('.csv') ? 'bg-blue-100' : 'bg-green-100'"
          >
            <svg class="w-5 h-5" :class="selectedFile.name.endsWith('.csv') ? 'text-blue-600' : 'text-[#22c55e]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

        <input
          ref="fileInputRef"
          type="file"
          accept=".csv,.xlsx,.xls"
          class="hidden"
          @change="handleFileSelect"
        />
      </div>

      <!-- Expected columns pills -->
      <div>
        <p class="text-xs font-medium text-[#717B99] uppercase tracking-wide mb-2">Expected columns</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="field in systemFields"
            :key="field.key"
            class="px-2 py-1 text-xs rounded"
            :class="field.required ? 'bg-[#1F3A5C]/10 text-[#1F3A5C] font-medium' : 'bg-[#EDEFF5] text-[#717B99]'"
          >
            {{ field.label }}<span v-if="field.required" class="text-[#F75A68] ml-0.5">*</span>
          </span>
        </div>
        <p class="text-xs text-[#717B99] mt-2">* Required fields</p>
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
                  :options="systemFields.map(f => ({ value: f.key, label: f.label + (f.required ? ' *' : '') }))"
                  placeholder="— Skip this column —"
                  @update:modelValue="columnMapping[col] = $event"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Missing required fields warning -->
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
      <!-- Summary cards -->
      <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="border border-[#DCDEE8] rounded p-3 text-center">
          <p class="text-2xl font-bold text-[#1E2740]">{{ previewRows.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Total rows</p>
        </div>
        <div class="border border-[#22c55e] rounded p-3 text-center bg-green-50">
          <p class="text-2xl font-bold text-[#22c55e]">{{ validRows.length }}</p>
          <p class="text-xs text-[#717B99] mt-1">Valid</p>
        </div>
        <div class="border border-[#F75A68] rounded p-3 text-center" :class="invalidRows.length ? 'bg-red-50' : ''">
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

      <!-- Preview table -->
      <div class="rounded border border-[#DCDEE8] overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-[#F8FBFF]">
            <tr>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Row</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Unit #</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Occupancy</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Owner</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Owner Email</th>
              <th v-if="hasTenantFields" class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Tenant</th>
              <th class="text-left px-3 py-2 font-medium text-[#717B99] uppercase tracking-wide">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#DCDEE8]">
            <tr
              v-for="row in paginatedPreviewRows"
              :key="row.__rowIndex"
              :class="row.__errors.length ? 'bg-red-50' : 'hover:bg-[#F8FBFF]'"
            >
              <td class="px-3 py-2 text-[#717B99]">{{ row.__rowIndex }}</td>
              <td class="px-3 py-2 font-medium text-[#1E2740]">{{ row.unit_number || '—' }}</td>
              <td class="px-3 py-2 text-[#1E2740]">
                <span v-if="row.occupancy_type" class="px-1.5 py-0.5 rounded text-xs" :class="occupancyClass(row.occupancy_type)">
                  {{ occupancyLabel(row.occupancy_type) }}
                </span>
                <span v-else class="text-[#717B99]">—</span>
              </td>
              <td class="px-3 py-2 text-[#1E2740]">{{ row.owner_full_name || '—' }}</td>
              <td class="px-3 py-2 text-[#717B99]">{{ row.owner_email || '—' }}</td>
              <td v-if="hasTenantFields" class="px-3 py-2 text-[#717B99]">{{ row.tenant_full_name || '—' }}</td>
              <td class="px-3 py-2">
                <span v-if="!row.__errors.length" class="text-[#22c55e] flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Valid
                </span>
                <AppPoptip v-else position="top" max-width="280px">
                  <template #trigger>
                    <span class="text-[#F75A68] flex items-center gap-1 cursor-default underline decoration-dotted underline-offset-2">
                      <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      {{ row.__errors.length }} error{{ row.__errors.length > 1 ? 's' : '' }}
                    </span>
                  </template>
                  <div class="p-3">
                    <p class="text-xs font-semibold text-[#1E2740] mb-2 flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5 text-[#F75A68] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      Validation errors
                    </p>
                    <ul class="space-y-1.5">
                      <li
                        v-for="error in row.__errors"
                        :key="error"
                        class="flex items-start gap-2 text-xs text-[#1E2740]"
                      >
                        <span class="mt-1 w-1.5 h-1.5 rounded-full bg-[#F75A68] shrink-0" />
                        {{ error }}
                      </li>
                    </ul>
                  </div>
                </AppPoptip>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPreviewPages > 1" class="flex items-center justify-between mt-3 text-xs text-[#717B99]">
        <span>Showing {{ previewPageStart }}–{{ previewPageEnd }} of {{ previewRows.length }}</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="px-3 py-1 border border-[#DCDEE8] rounded disabled:opacity-40"
            :disabled="previewPage === 1"
            @click="previewPage--"
          >Previous</button>
          <button
            type="button"
            class="px-3 py-1 border border-[#DCDEE8] rounded disabled:opacity-40"
            :disabled="previewPage === totalPreviewPages"
            @click="previewPage++"
          >Next</button>
        </div>
      </div>
    </div>

    <!-- ─── Step 3: Results ─── -->
    <div v-else-if="step === 3">
      <div class="text-center mb-6">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3"
          :class="importResult?.error_count ? 'bg-amber-100' : 'bg-green-100'"
        >
          <svg class="w-8 h-8" :class="importResult?.error_count ? 'text-[#D89B4B]' : 'text-[#22c55e]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-base font-semibold text-[#1E2740]">Import Complete</h3>
        <p class="text-sm text-[#717B99] mt-1">{{ importResult?.message }}</p>
      </div>

      <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="border border-[#22c55e] rounded p-3 text-center bg-green-50">
          <p class="text-2xl font-bold text-[#22c55e]">{{ importResult?.imported ?? 0 }}</p>
          <p class="text-xs text-[#717B99] mt-1">Imported</p>
        </div>
        <div class="border border-[#D89B4B] rounded p-3 text-center bg-amber-50">
          <p class="text-2xl font-bold text-[#D89B4B]">{{ importResult?.duplicates ?? 0 }}</p>
          <p class="text-xs text-[#717B99] mt-1">Duplicates skipped</p>
        </div>
        <div
          class="border rounded p-3 text-center"
          :class="importResult?.error_count ? 'border-[#F75A68] bg-red-50' : 'border-[#DCDEE8]'"
        >
          <p class="text-2xl font-bold" :class="importResult?.error_count ? 'text-[#F75A68]' : 'text-[#717B99]'">
            {{ importResult?.error_count ?? 0 }}
          </p>
          <p class="text-xs text-[#717B99] mt-1">Errors</p>
        </div>
      </div>

      <!-- Error details -->
      <div v-if="importResult?.errors?.length" class="rounded border border-[#F75A68] overflow-hidden">
        <div class="bg-red-50 px-4 py-2 border-b border-[#F75A68]">
          <p class="text-sm font-medium text-[#F75A68]">Rows that failed to import</p>
        </div>
        <div class="max-h-48 overflow-y-auto divide-y divide-[#DCDEE8]">
          <div v-for="err in importResult.errors" :key="err.row" class="px-4 py-3">
            <p class="text-xs font-medium text-[#1E2740] mb-1">
              Row {{ err.row }}
              <span class="text-[#717B99] font-normal ml-2">
                {{ err.data?.unit_number || err.data?.owner_full_name || '' }}
              </span>
            </p>
            <ul class="list-disc list-inside space-y-0.5">
              <li v-for="msg in err.errors" :key="msg" class="text-xs text-[#F75A68]">{{ msg }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <template #footer>
      <!-- Step 0 -->
      <template v-if="step === 0">
        <AppButton variant="outline" @click="handleClose">Cancel</AppButton>
        <AppButton
          variant="primary"
          :disabled="!selectedFile || parsing"
          @click="parseFile"
        >
          {{ parsing ? 'Parsing...' : 'Next: Map Columns' }}
        </AppButton>
      </template>

      <!-- Step 1 -->
      <template v-else-if="step === 1">
        <AppButton variant="outline" @click="step = 0">Back</AppButton>
        <AppButton
          variant="primary"
          :disabled="missingRequiredFields.length > 0"
          @click="applyMappingAndPreview"
        >
          Next: Preview
        </AppButton>
      </template>

      <!-- Step 2 -->
      <template v-else-if="step === 2">
        <AppButton variant="outline" @click="step = 1">Back</AppButton>
        <AppButton
          variant="primary"
          :disabled="validRows.length === 0 || invalidRows.length > 0 || importing"
          @click="runImport"
        >
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
import * as XLSX from 'xlsx'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import AppPoptip from './AppPoptip.vue'
import AppSelect from './AppSelect.vue'

const props = defineProps<{
  show: boolean
  estateId: string
  estateType?: string  // 'sectional_title' | 'residential_rental' | 'commercial_rental' | 'mixed'
}>()

const emit = defineEmits<{
  close: []
  imported: []
}>()

const API_URL = import.meta.env.VITE_API_URL ?? ''

// ─── Field definitions (estate-type-aware) ───────────────────────────────────

const ALL_FIELDS = [
  { key: 'unit_number',        label: 'Unit Number',        required: true },
  { key: 'occupancy_type',     label: 'Occupancy Type',     required: true },
  { key: 'levy_override',      label: 'Levy Override',      required: false },
  { key: 'rent_amount',        label: 'Rent Amount',        required: false },
  { key: 'owner_full_name',    label: 'Owner Full Name',    required: true },
  { key: 'owner_id_number',    label: 'Owner ID Number',    required: false },
  { key: 'owner_email',        label: 'Owner Email',        required: true },
  { key: 'owner_phone',        label: 'Owner Phone',        required: false },
  { key: 'owner_address',      label: 'Owner Address',      required: false },
  { key: 'tenant_full_name',   label: 'Tenant Full Name',   required: false },
  { key: 'tenant_email',       label: 'Tenant Email',       required: false },
  { key: 'tenant_phone',       label: 'Tenant Phone',       required: false },
  { key: 'tenant_lease_start', label: 'Tenant Lease Start', required: false },
  { key: 'tenant_lease_end',   label: 'Tenant Lease End',   required: false },
]

const TENANT_KEYS = ['tenant_full_name', 'tenant_email', 'tenant_phone', 'tenant_lease_start', 'tenant_lease_end']

const systemFields = computed(() => {
  const type = props.estateType
  if (type === 'sectional_title') {
    // Sectional title: levy-focused. Owners can rent out units (tenant_occupied) but
    // tenant personal details are not captured at this stage — only owner info + levy config.
    return ALL_FIELDS.filter(f => !TENANT_KEYS.includes(f.key) && f.key !== 'rent_amount')
  }
  if (type === 'residential_rental' || type === 'commercial_rental') {
    // Rental portfolio: rent-focused. No levies.
    return ALL_FIELDS.filter(f => f.key !== 'levy_override')
  }
  // mixed or unknown: all fields
  return ALL_FIELDS
})

const hasTenantFields = computed(() =>
  props.estateType !== 'sectional_title'
)

const estateTypeLabel = computed(() => {
  const labels: Record<string, string> = {
    sectional_title: 'Sectional Title',
    residential_rental: 'Residential Rental',
    commercial_rental: 'Commercial Rental',
    mixed: 'Mixed',
  }
  return labels[props.estateType ?? ''] ?? 'All Types'
})

function allowedOccupancyTypes(): string[] {
  const type = props.estateType
  if (type === 'sectional_title') {
    // Tenants are added per-unit after import — occupancy during bulk import is owner or vacant
    return ['owner_occupied', 'vacant']
  }
  if (type === 'residential_rental' || type === 'commercial_rental') {
    // Pure rental portfolios don't have owner-occupied units
    return ['tenant_occupied', 'vacant']
  }
  return ['owner_occupied', 'tenant_occupied', 'vacant']
}

// ─── Example rows (estate-type-aware) ────────────────────────────────────────

function buildExampleRows(): Record<string, string>[] {
  const type = props.estateType

  if (type === 'sectional_title') {
    // Sectional title only has owner_occupied and vacant — tenant details are added separately after import
    return [
      // Row 1: fully complete, owner_occupied, custom levy override
      { unit_number: 'A01', occupancy_type: 'owner_occupied', levy_override: '3000', owner_full_name: 'Sarah van der Merwe', owner_id_number: '8801015800085', owner_email: 'sarah@example.com', owner_phone: '+27 82 555 0101', owner_address: '12 Oak Street, Johannesburg' },
      // Row 2: owner_occupied, uses estate default levy, partial info
      { unit_number: 'A02', occupancy_type: 'owner_occupied', levy_override: '', owner_full_name: 'Michael Ndaba', owner_id_number: '', owner_email: 'michael@example.com', owner_phone: '+27 73 444 0202', owner_address: '' },
      // Row 3: vacant unit, levy override set, minimal info
      { unit_number: 'B01', occupancy_type: 'vacant', levy_override: '2850', owner_full_name: 'Johan Pretorius', owner_id_number: '', owner_email: 'johan@example.com', owner_phone: '', owner_address: '' },
      // Row 4: vacant, only required fields
      { unit_number: 'B02', occupancy_type: 'vacant', levy_override: '', owner_full_name: 'Thandi Dlamini', owner_id_number: '', owner_email: 'thandi@example.com', owner_phone: '', owner_address: '' },
      // Row 5: fully complete, owner_occupied with custom levy
      { unit_number: 'C01', occupancy_type: 'owner_occupied', levy_override: '2850', owner_full_name: 'James Motsepe', owner_id_number: '7505036800081', owner_email: 'james@example.com', owner_phone: '+27 61 333 0303', owner_address: '8 Linden Drive, Sandton' },
    ]
  }

  if (type === 'residential_rental' || type === 'commercial_rental') {
    return [
      // Row 1: fully complete, tenant_occupied, all tenant details
      { unit_number: '101', occupancy_type: 'tenant_occupied', rent_amount: '9500', owner_full_name: 'Peter Johnson', owner_id_number: '7801015800082', owner_email: 'peter@example.com', owner_phone: '+27 82 111 2233', owner_address: '5 Park Lane, Cape Town', tenant_full_name: 'Lisa Mokoena', tenant_email: 'lisa@example.com', tenant_phone: '+27 71 222 3344', tenant_lease_start: '2025-03-01', tenant_lease_end: '2026-02-28' },
      // Row 2: tenant_occupied, partial tenant info, no lease end
      { unit_number: '102', occupancy_type: 'tenant_occupied', rent_amount: '8500', owner_full_name: 'Susan van der Berg', owner_id_number: '', owner_email: 'susan@example.com', owner_phone: '+27 83 333 4455', owner_address: '', tenant_full_name: 'Sipho Dlamini', tenant_email: 'sipho@example.com', tenant_phone: '', tenant_lease_start: '2025-06-01', tenant_lease_end: '' },
      // Row 3: vacant, only owner details (no tenant)
      { unit_number: '103', occupancy_type: 'vacant', rent_amount: '7500', owner_full_name: 'Anele Zulu', owner_id_number: '', owner_email: 'anele@example.com', owner_phone: '', owner_address: '', tenant_full_name: '', tenant_email: '', tenant_phone: '', tenant_lease_start: '', tenant_lease_end: '' },
      // Row 4: tenant_occupied, fully complete
      { unit_number: '201', occupancy_type: 'tenant_occupied', rent_amount: '12000', owner_full_name: 'Raj Patel', owner_id_number: '8503026200089', owner_email: 'raj@example.com', owner_phone: '+27 79 444 5566', owner_address: '22 Business Park, Sandton', tenant_full_name: 'Nomsa Khumalo', tenant_email: 'nomsa@example.com', tenant_phone: '+27 65 555 6677', tenant_lease_start: '2026-01-01', tenant_lease_end: '2026-12-31' },
      // Row 5: tenant_occupied, minimal tenant info
      { unit_number: '202', occupancy_type: 'tenant_occupied', rent_amount: '10500', owner_full_name: 'David Botha', owner_id_number: '', owner_email: 'david@example.com', owner_phone: '', owner_address: '', tenant_full_name: 'Rachel Naidoo', tenant_email: 'rachel@example.com', tenant_phone: '', tenant_lease_start: '', tenant_lease_end: '' },
    ]
  }

  // mixed or unknown: all fields
  return [
    // Row 1: sectional-title-style, owner_occupied with levy, no tenant
    { unit_number: 'A01', occupancy_type: 'owner_occupied', levy_override: '2850', rent_amount: '', owner_full_name: 'Sarah van der Merwe', owner_id_number: '8801015800085', owner_email: 'sarah@example.com', owner_phone: '+27 82 555 0101', owner_address: '12 Oak Street, Johannesburg', tenant_full_name: '', tenant_email: '', tenant_phone: '', tenant_lease_start: '', tenant_lease_end: '' },
    // Row 2: tenant_occupied, both levy and rent, full tenant info
    { unit_number: 'A02', occupancy_type: 'tenant_occupied', levy_override: '2850', rent_amount: '9500', owner_full_name: 'Michael Ndaba', owner_id_number: '', owner_email: 'michael@example.com', owner_phone: '+27 73 444 0202', owner_address: '', tenant_full_name: 'Lisa Mokoena', tenant_email: 'lisa@example.com', tenant_phone: '+27 71 222 3344', tenant_lease_start: '2025-03-01', tenant_lease_end: '2026-02-28' },
    // Row 3: tenant_occupied, rental only (no levy override), partial tenant
    { unit_number: 'B01', occupancy_type: 'tenant_occupied', levy_override: '', rent_amount: '8000', owner_full_name: 'Johan Pretorius', owner_id_number: '', owner_email: 'johan@example.com', owner_phone: '', owner_address: '', tenant_full_name: 'Sipho Dlamini', tenant_email: 'sipho@example.com', tenant_phone: '', tenant_lease_start: '2025-06-01', tenant_lease_end: '' },
    // Row 4: vacant, levy only, no tenant
    { unit_number: 'B02', occupancy_type: 'vacant', levy_override: '3000', rent_amount: '', owner_full_name: 'Thandi Dlamini', owner_id_number: '', owner_email: 'thandi@example.com', owner_phone: '', owner_address: '', tenant_full_name: '', tenant_email: '', tenant_phone: '', tenant_lease_start: '', tenant_lease_end: '' },
    // Row 5: owner_occupied, fully complete, no tenant
    { unit_number: 'C01', occupancy_type: 'owner_occupied', levy_override: '2850', rent_amount: '', owner_full_name: 'James Motsepe', owner_id_number: '7505036800081', owner_email: 'james@example.com', owner_phone: '+27 61 333 0303', owner_address: '8 Linden Drive, Sandton', tenant_full_name: '', tenant_email: '', tenant_phone: '', tenant_lease_start: '', tenant_lease_end: '' },
  ]
}

// ─── State ───────────────────────────────────────────────────────────────────

const step         = ref(0)
const selectedFile = ref<File | null>(null)
const isDragOver   = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
const parseError   = ref('')
const parsing      = ref(false)
const importing    = ref(false)
const downloadingCsv  = ref(false)
const downloadingXlsx = ref(false)

// Step 1 — column mapping
const fileColumns    = ref<string[]>([])
const fileRows       = ref<Record<string, string>[]>([])
const columnMapping  = ref<Record<string, string>>({})

// Step 2 — preview
const previewRows = ref<Array<Record<string, any> & { __rowIndex: number; __errors: string[] }>>([])
const previewPage = ref(1)
const PREVIEW_PAGE_SIZE = 10

// Step 3 — results
const importResult = ref<{
  imported: number
  duplicates: number
  error_count: number
  errors: Array<{ row: number; errors: string[]; data: any }>
  total: number
  message: string
} | null>(null)

// ─── Computed ────────────────────────────────────────────────────────────────

const missingRequiredFields = computed(() => {
  const mapped = new Set(Object.values(columnMapping.value).filter(Boolean))
  return systemFields.value
    .filter(f => f.required && !mapped.has(f.key))
    .map(f => f.label)
})

const validRows   = computed(() => previewRows.value.filter(r => r.__errors.length === 0))
const invalidRows = computed(() => previewRows.value.filter(r => r.__errors.length > 0))

const totalPreviewPages    = computed(() => Math.ceil(previewRows.value.length / PREVIEW_PAGE_SIZE))
const previewPageStart     = computed(() => (previewPage.value - 1) * PREVIEW_PAGE_SIZE + 1)
const previewPageEnd       = computed(() => Math.min(previewPage.value * PREVIEW_PAGE_SIZE, previewRows.value.length))
const paginatedPreviewRows = computed(() =>
  previewRows.value.slice(previewPageStart.value - 1, previewPageEnd.value)
)

// ─── Helpers ─────────────────────────────────────────────────────────────────

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('auth_token')
  return { Authorization: `Bearer ${token}` }
}

function normalizeKey(str: string): string {
  return str.toLowerCase().replace(/[\s_\-\.]+/g, '_')
}

function autoDetectMapping(columns: string[]): Record<string, string> {
  const mapping: Record<string, string> = {}
  const fields = systemFields.value

  for (const col of columns) {
    const normalized = normalizeKey(col)
    const match = fields.find(f => {
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

  const ot      = row.occupancy_type?.trim()
  const allowed = allowedOccupancyTypes()
  if (!ot) {
    errors.push('Occupancy type is required')
  } else if (!allowed.includes(ot)) {
    errors.push(`Occupancy type must be: ${allowed.join(', ')}`)
  }

  if (!row.owner_full_name?.trim()) errors.push('Owner full name is required')

  const ownerEmail = row.owner_email?.trim()
  if (!ownerEmail) {
    errors.push('Owner email is required')
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ownerEmail)) {
    errors.push('Owner email is invalid')
  }

  // Only validate tenant email if the field exists in this estate type's template
  if (hasTenantFields.value) {
    const tenantEmail = row.tenant_email?.trim()
    if (tenantEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(tenantEmail)) {
      errors.push('Tenant email is invalid')
    }
  }

  return errors
}

function occupancyLabel(type: string): string {
  return { owner_occupied: 'Owner', tenant_occupied: 'Tenant', vacant: 'Vacant' }[type] ?? type
}

function occupancyClass(type: string): string {
  return ({
    owner_occupied:  'bg-green-100 text-green-700',
    tenant_occupied: 'bg-blue-100 text-blue-700',
    vacant:          'bg-gray-100 text-gray-600',
  } as Record<string, string>)[type] ?? 'bg-gray-100 text-gray-600'
}

// ─── Template download (fully client-side) ───────────────────────────────────

function downloadTemplate(format: 'csv' | 'xlsx') {
  const loading = format === 'csv' ? downloadingCsv : downloadingXlsx
  loading.value = true

  try {
    const fields      = systemFields.value
    const headers     = fields.map(f => f.label)
    const examples    = buildExampleRows()
    const dataRows    = examples.map(row => fields.map(f => row[f.key] ?? ''))
    const typeSlug    = (props.estateType ?? 'units').replace(/_/g, '-')
    const filename    = `units-import-template-${typeSlug}`

    if (format === 'csv') {
      // Escape values that contain commas, quotes, or newlines
      const escape = (v: string) => (v.includes(',') || v.includes('"') || v.includes('\n'))
        ? `"${v.replace(/"/g, '""')}"` : v

      const csvContent = [headers, ...dataRows]
        .map(row => row.map(escape).join(','))
        .join('\r\n')

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
      const url  = URL.createObjectURL(blob)
      const a    = document.createElement('a')
      a.href     = url
      a.download = `${filename}.csv`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } else {
      const ws = XLSX.utils.aoa_to_sheet([headers, ...dataRows])

      // Style header row bold (SheetJS community edition — basic cell metadata only)
      const range = XLSX.utils.decode_range(ws['!ref'] ?? 'A1')
      for (let c = range.s.c; c <= range.e.c; c++) {
        const cellAddr = XLSX.utils.encode_cell({ r: 0, c })
        if (ws[cellAddr]) {
          ws[cellAddr].s = { font: { bold: true } }
        }
      }

      // Auto-fit column widths based on content
      ws['!cols'] = headers.map((h, i) => {
        const maxLen = Math.max(
          h.length,
          ...dataRows.map(row => String(row[i] ?? '').length)
        )
        return { wch: Math.min(maxLen + 2, 40) }
      })

      const wb = XLSX.utils.book_new()
      XLSX.utils.book_append_sheet(wb, ws, 'Units Import')
      XLSX.writeFile(wb, `${filename}.xlsx`)
    }
  } catch (e) {
    console.error('Template download failed:', e)
  } finally {
    loading.value = false
  }
}

// ─── File handling ────────────────────────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

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
      `${API_URL}/api/v1/estates/${props.estateId}/units/bulk-import/parse`,
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
  previewPage.value = 1
  step.value        = 2
}

async function runImport() {
  importing.value = true

  try {
    const res  = await fetch(
      `${API_URL}/api/v1/estates/${props.estateId}/units/bulk-import`,
      {
        method:  'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          rows: validRows.value.map(({ __rowIndex, __errors, ...data }) => data),
        }),
      }
    )
    const json = await res.json()

    if (!res.ok) { console.error(json); return }

    importResult.value = json
    step.value         = 3
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
  previewPage.value   = 1
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function handleClose() {
  if (step.value === 3 && importResult.value?.imported) emit('imported')
  emit('close')
}

function finishAndClose() {
  if (importResult.value?.imported) emit('imported')
  emit('close')
  setTimeout(resetWizard, 300)
}

// Reset wizard when modal is re-opened
watch(() => props.show, (newVal) => {
  if (newVal) resetWizard()
})
</script>
