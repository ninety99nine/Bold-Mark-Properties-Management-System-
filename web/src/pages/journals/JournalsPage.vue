<!--
  JournalsPage — WeConnectU-faithful "Journals" screen (per community).

  Financial Year / Budget Period selector, "Add Manual Journal Batch" (reveals
  the inline line editor) and "Add Journal Batch Upload" (modal with template
  download + file upload), Date from / Date to filters, Download Excel, Search,
  and the batches table (Date · Batch Name · Journal Group · Entries · Created ·
  Last Updated · Files · actions). Community comes from the top-bar selector.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useExport } from '@/composables/useExport'
import { useCommunityStore } from '@/stores/community'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppInput  from '@/components/common/AppInput.vue'
import AppModal  from '@/components/common/AppModal.vue'
import JournalBatchForm from '@/components/journals/JournalBatchForm.vue'

const router    = useRouter()
const community = useCommunityStore()
const { success, error } = useToast()
const { downloadExport } = useExport()

// ── Financial year / budget period ────────────────────────────────────────
const periods       = ref([])
const financialYear = ref(null)
const periodOptions = computed(() => periods.value.map((p) => ({ value: p.year, label: p.label })))

async function loadPeriods() {
  if (!community.selectedId) return
  try {
    const { data } = await api.get(`/communities/${community.selectedId}/financial-years`)
    periods.value = data.periods ?? []
    financialYear.value = data.current_year ?? periods.value.find((p) => p.is_current)?.year ?? periods.value[0]?.year ?? null
  } catch {
    periods.value = []
  }
}

// ── Filters ─────────────────────────────────────────────────────────────
function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function monthsAgo(n) {
  const d = new Date()
  d.setMonth(d.getMonth() - n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

const dateFrom = ref(monthsAgo(2))
const dateTo   = ref(today())
const search   = ref('')

// ── Batches ─────────────────────────────────────────────────────────────
const batches = ref([])
const loading = ref(false)

async function loadBatches() {
  if (!community.selectedId) return
  loading.value = true
  try {
    const { data } = await api.get('/journals', {
      params: {
        community_id:   community.selectedId,
        financial_year: financialYear.value || undefined,
        date_from:      dateFrom.value || undefined,
        date_to:        dateTo.value || undefined,
        search:         search.value || undefined,
        _per_page:      200,
      },
    })
    batches.value = data.data ?? []
  } catch {
    error('Could not load journal batches.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadPeriods()
  await loadBatches()
})
watch(() => community.selectedId, async () => { await loadPeriods(); await loadBatches() })
watch([financialYear, dateFrom, dateTo], loadBatches)

let searchTimer
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadBatches, 300) })

// ── Manual batch form (create / copy) ─────────────────────────────────────
const showManualForm = ref(false)
const copyBatch      = ref(null)

function toggleManual() {
  copyBatch.value = null
  showManualForm.value = !showManualForm.value
}

function onManualSaved() {
  showManualForm.value = false
  copyBatch.value = null
  loadBatches()
}

async function duplicate(row) {
  try {
    const { data } = await api.get(`/journals/${row.id}`)
    const batch = data.data ?? data
    // Strip identity so the form treats it as a fresh create prefilled from this batch.
    copyBatch.value = { ...batch, id: null, files: [] }
    showManualForm.value = true
  } catch {
    error('Could not copy that batch.')
  }
}

// ── Upload modal ───────────────────────────────────────────────────────────
const showUpload    = ref(false)
const uploadGroup   = ref('')
const uploadDate    = ref(today())
const uploadFile    = ref(null)
const uploadExtra   = ref([])
const uploading     = ref(false)
const JOURNAL_GROUPS = ['Accrual', 'Audit', 'Customer Recovery', 'Insurance', 'Interest on Arrears', 'Legal Fees', 'Levy', 'Opening Balances', 'Petty Cash', 'Transfer', 'Water and Sewerage']
const uploadGroupOptions = [{ value: '', label: '--' }, ...JOURNAL_GROUPS.map((g) => ({ value: g, label: g }))]

function downloadTemplate() {
  downloadExport('/journals/template', {}, 'journal-batch.xlsx')
}

async function submitUpload() {
  if (!uploadFile.value) { error('Please choose a completed journal batch file to upload.'); return }
  uploading.value = true
  const fd = new FormData()
  fd.append('community_id', community.selectedId)
  fd.append('financial_year', financialYear.value)
  fd.append('date', uploadDate.value)
  if (uploadGroup.value) fd.append('journal_group', uploadGroup.value)
  fd.append('journal_file', uploadFile.value)
  uploadExtra.value.forEach((f) => fd.append('files[]', f))
  try {
    const { data } = await api.post('/journals/upload', fd)
    const created = data.data ?? data
    success(`${created?.batch_name ?? 'Journal batch'} uploaded successfully.`)
    showUpload.value = false
    uploadFile.value = null; uploadExtra.value = []; uploadGroup.value = ''
    loadBatches()
  } catch (err) {
    const res = err.response?.data
    error(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not upload the batch.'))
  } finally {
    uploading.value = false
  }
}

// ── Row helpers ────────────────────────────────────────────────────────────
function downloadBatch(row) {
  downloadExport(`/journals/${row.id}/download`, {}, `journal batch - ${row.date}-${(row.batch_name || '').toLowerCase()}-.xlsx`)
}
function downloadListExcel() {
  downloadExport('/journals/download', {
    community_id: community.selectedId,
    financial_year: financialYear.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }, 'journals.xlsx')
}
function openBatch(row) { router.push({ name: 'journal-batch', params: { batchId: row.id } }) }
</script>

<template>
  <div class="space-y-6 pb-8">
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Journals</h1>
      <p class="text-sm text-muted-foreground">
        Capture and manage journal batches
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- No community selected -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to manage journals.
    </div>

    <template v-else>
      <!-- Financial Year / Budget Period -->
      <div>
        <label class="block text-sm font-semibold text-foreground mb-1">Financial Year / Budget Period:</label>
        <div class="max-w-md"><AppSelect v-model="financialYear" :options="periodOptions" placeholder="Select period" /></div>
        <p class="mt-2 flex items-center gap-1.5 text-sm text-emerald-600">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.58 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd" /></svg>
          All transactions allocated.
        </p>
      </div>

      <!-- Main card -->
      <div class="rounded-lg border border-border bg-white p-6 space-y-6">
        <div class="flex items-center justify-between">
          <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-600 hover:underline" @click="toggleManual">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM11 7a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
            Add Manual Journal Batch
          </button>
          <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-600 hover:underline" @click="showUpload = true">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM11 7a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
            Add Journal Batch Upload
          </button>
        </div>

        <!-- Manual batch form -->
        <JournalBatchForm
          v-if="showManualForm"
          mode="create"
          :community-id="community.selectedId"
          :financial-year="financialYear"
          :batch="copyBatch"
          @saved="onManualSaved"
        />

        <!-- Date filters -->
        <div class="flex flex-wrap items-center gap-x-10 gap-y-3 pt-2 border-t border-border">
          <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-foreground">Date from:</label>
            <input type="date" v-model="dateFrom" class="h-11 rounded-md border border-border px-3 text-sm" />
          </div>
          <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-foreground">Date to:</label>
            <input type="date" v-model="dateTo" class="h-11 rounded-md border border-border px-3 text-sm" />
          </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-4">
          <AppButton variant="outline" size="sm" @click="downloadListExcel">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Download Excel
          </AppButton>
          <div class="flex items-center gap-2">
            <label class="text-sm text-foreground">Search:</label>
            <AppInput v-model="search" size="sm" placeholder="" />
          </div>
        </div>

        <!-- Batches table -->
        <div class="overflow-x-auto rounded-lg border border-border">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-muted/40 text-left">
                <th class="px-4 py-3 font-semibold text-foreground">Date</th>
                <th class="px-4 py-3 font-semibold text-foreground">Batch Name</th>
                <th class="px-4 py-3 font-semibold text-foreground">Journal Group</th>
                <th class="px-4 py-3 font-semibold text-foreground text-right">Entries</th>
                <th class="px-4 py-3 font-semibold text-foreground">Created</th>
                <th class="px-4 py-3 font-semibold text-foreground">Last Updated</th>
                <th class="px-4 py-3 font-semibold text-foreground text-center">Files</th>
                <th class="px-4 py-3 font-semibold text-foreground text-right"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">Loading…</td>
              </tr>
              <tr v-else-if="!batches.length">
                <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">No data available in table</td>
              </tr>
              <tr v-for="row in batches" :key="row.id" class="border-t border-border hover:bg-muted/20">
                <td class="px-4 py-3 whitespace-nowrap">{{ row.date }}</td>
                <td class="px-4 py-3">
                  <button class="text-sky-600 hover:underline font-medium" @click="openBatch(row)">{{ row.batch_name }}</button>
                </td>
                <td class="px-4 py-3">{{ row.journal_group || '' }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ row.entries_count }} entries</td>
                <td class="px-4 py-3">
                  <template v-if="row.created_by">
                    <div>{{ row.created_by.name }}</div>
                    <div class="text-xs text-muted-foreground">on <b>{{ (row.created_at || '').slice(0, 10) }}</b></div>
                  </template>
                </td>
                <td class="px-4 py-3">
                  <template v-if="row.updated_by">
                    <div>{{ row.updated_by.name }}</div>
                    <div class="text-xs text-muted-foreground">on <b>{{ (row.updated_at || '').slice(0, 10) }}</b></div>
                  </template>
                  <template v-else>N/A</template>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="row.files_count" class="text-sky-600" title="Has files">📎</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-3 text-sky-600">
                    <button title="Download" @click="downloadBatch(row)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    </button>
                    <button title="Edit" @click="openBatch(row)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button title="Copy" @click="duplicate(row)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-sm text-muted-foreground">Showing {{ batches.length ? 1 : 0 }} to {{ batches.length }} of {{ batches.length }} entries</p>
      </div>
    </template>

    <!-- Upload modal -->
    <AppModal :show="showUpload" title="Upload Journal Batch" size="lg" @close="showUpload = false">
      <div class="space-y-5">
        <p class="text-sm text-foreground">
          To upload a journal 'batch', please download and use the template provided below:<br />
          <button class="text-sky-600 hover:underline font-medium" @click="downloadTemplate">Download Template</button>
        </p>
        <div class="grid grid-cols-1 gap-4">
          <div class="flex items-center gap-4">
            <label class="w-32 text-sm text-muted-foreground">Journal Group:</label>
            <div class="flex-1"><AppSelect v-model="uploadGroup" :options="uploadGroupOptions" placeholder="--" /></div>
          </div>
          <div class="flex items-center gap-4">
            <label class="w-32 text-sm text-muted-foreground">Journal File:</label>
            <input type="file" accept=".xlsx,.xls,.csv" class="flex-1 text-sm" @change="uploadFile = $event.target.files[0]" />
          </div>
          <div class="flex items-center gap-4">
            <label class="w-32 text-sm text-muted-foreground">Date:</label>
            <input type="date" v-model="uploadDate" class="h-11 rounded-md border border-border px-3 text-sm" />
          </div>
          <div class="flex items-start gap-4">
            <label class="w-32 text-sm text-muted-foreground pt-2">Additional files:</label>
            <input type="file" multiple class="flex-1 text-sm" @change="uploadExtra = Array.from($event.target.files)" />
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" @click="showUpload = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="uploading" @click="submitUpload">Upload Batch</AppButton>
      </template>
    </AppModal>
  </div>
</template>
