<!--
  AppExportModal — Platform-wide reusable export modal.
  Used on every data table that supports exporting (invoices, cashbook, age analysis, units, etc.).

  Props:
    show     — Boolean, controls visibility
    context  — String label used in title, e.g. "Invoices", "Age Analysis", "Cashbook Entries"

  Emits:
    close    — user dismissed the modal
    download — { format: 'csv'|'xlsx'|'pdf', records: string } when user clicks Download

  Usage:
    <AppExportModal :show="showExport" context="Invoices" @close="showExport = false" @download="handleDownload" />
-->
<script setup>
import { ref } from 'vue'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import AppSelect from './AppSelect.vue'

defineProps({
  show:    { type: Boolean, required: true },
  context: { type: String, default: 'Data' },
})

const emit = defineEmits(['close', 'download'])

const format  = ref('csv')
const records = ref('current')


const recordOptions = [
  { value: 'current', label: 'Current page' },
  { value: '25',      label: '25 records' },
  { value: '50',      label: '50 records' },
  { value: '100',     label: '100 records' },
  { value: '500',     label: '500 records' },
  { value: '1000',    label: '1 000 records' },
]

function handleDownload() {
  emit('download', { format: format.value, records: records.value })
  emit('close')
}
</script>

<template>
  <AppModal :show="show" :title="`Export ${context}`" size="sm" @close="$emit('close')">
    <div class="space-y-5">

      <!-- Format -->
      <div>
        <p class="text-sm font-medium text-foreground mb-2">Format</p>
        <div class="flex rounded-lg border border-border overflow-hidden">

          <!-- CSV -->
          <button
            type="button"
            @click="format = 'csv'"
            :class="[
              'flex-1 flex items-center justify-center gap-2 py-2.5 px-3 text-sm font-medium transition-colors select-none border-r border-border',
              format === 'csv'
                ? 'bg-primary text-white'
                : 'bg-card text-foreground hover:bg-muted',
            ]"
          >
            <!-- Table/grid icon -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
              <rect width="18" height="18" x="3" y="3" rx="2"/>
              <path d="M3 9h18M3 15h18M9 3v18"/>
            </svg>
            CSV
          </button>

          <!-- Excel -->
          <button
            type="button"
            @click="format = 'xlsx'"
            :class="[
              'flex-1 flex items-center justify-center gap-2 py-2.5 px-3 text-sm font-medium transition-colors select-none border-r border-border',
              format === 'xlsx'
                ? 'bg-primary text-white'
                : 'bg-card text-foreground hover:bg-muted',
            ]"
          >
            <!-- Spreadsheet icon -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
              <rect width="18" height="18" x="3" y="3" rx="2"/>
              <path d="M3 9h18M9 3v18"/>
              <path d="m13 15 2 2 4-4"/>
            </svg>
            Excel
          </button>

          <!-- PDF -->
          <button
            type="button"
            @click="format = 'pdf'"
            :class="[
              'flex-1 flex items-center justify-center gap-2 py-2.5 px-3 text-sm font-medium transition-colors select-none',
              format === 'pdf'
                ? 'bg-primary text-white'
                : 'bg-card text-foreground hover:bg-muted',
            ]"
          >
            <!-- Document icon -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0">
              <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
              <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
              <path d="M10 9H8M16 13H8M16 17H8"/>
            </svg>
            PDF
          </button>

        </div>
      </div>

      <!-- Record count -->
      <div>
        <label class="text-sm font-medium text-foreground block mb-2">Records</label>
        <AppSelect v-model="records" :options="recordOptions" />
        <p class="text-xs text-muted-foreground mt-1.5">
          Export respects all applied filters, search, and sorting.
        </p>
      </div>

    </div>

    <template #footer>
      <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
      <AppButton variant="primary" @click="handleDownload">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" x2="12" y1="15" y2="3"/>
        </svg>
        Download
      </AppButton>
    </template>
  </AppModal>
</template>
