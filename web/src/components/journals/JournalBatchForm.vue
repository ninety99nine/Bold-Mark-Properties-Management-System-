<!--
  JournalBatchForm — the WeConnectU manual journal-batch line editor.

  Shared by the Journals page (create — revealed by "Add Manual Journal Batch")
  and the Journal Batch detail page (edit — "Submit Journal Edit"). Renders the
  Ledger Type / Account / Description / Amount / Debit / Credit rows with live
  Total Debit / Total Credit / Difference, a drag-and-drop file uploader, a Date
  and Journal Group, and a submit button. Enforces a balanced batch (difference
  must be 0.00) before submitting.

  POSTs multipart to /journals (create) or /journals/{id} with _method=PUT (edit).
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppSelect        from '@/components/common/AppSelect.vue'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppInput         from '@/components/common/AppInput.vue'
import AppButton        from '@/components/common/AppButton.vue'

const props = defineProps({
  mode:          { type: String, default: 'create' }, // 'create' | 'edit'
  communityId:   { type: String, default: null },
  financialYear: { type: [Number, String], default: null },
  batch:         { type: Object, default: null },     // for edit / copy prefill
})

const emit = defineEmits(['saved', 'cancel'])

const { success, error } = useToast()

// ── Journal Groups (fixed WeConnectU list) ───────────────────────────────
const JOURNAL_GROUPS = [
  'Accrual', 'Audit', 'Customer Recovery', 'Insurance', 'Interest on Arrears',
  'Legal Fees', 'Levy', 'Opening Balances', 'Petty Cash', 'Transfer', 'Water and Sewerage',
]
const groupOptions = [{ value: '', label: '--' }, ...JOURNAL_GROUPS.map((g) => ({ value: g, label: g }))]

const LEDGER_TYPES = [
  { value: 'general',      label: 'General Ledger' },
  { value: 'customer',     label: 'Customer Ledger' },
  { value: 'supplier',     label: 'Supplier Ledger' },
  { value: 'reserve_fund', label: 'Reserve Fund Ledger' },
]

// ── Reference data ────────────────────────────────────────────────────────
const ledgers = ref([])
const units   = ref([])
const loading = ref(false)

// ── Form state ──────────────────────────────────────────────────────────
function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function blankRow(entryType = 'debit') {
  return { line_type: '', account: '', description: '', amount: '0.00', entry_type: entryType }
}

const rows         = ref([blankRow('debit'), blankRow('credit')])
const date         = ref(today())
const journalGroup = ref('')
const files        = ref([])          // newly-added File objects
const existingFiles = ref([])         // [{name, path}] retained on edit
const submitting   = ref(false)
const dragging     = ref(false)

// ── Account options per row (depends on the row's Ledger Type) ────────────
const generalLedgerOptions = computed(() =>
  ledgers.value
    .filter((l) => l.code && !String(l.code).startsWith('RFI'))
    .map((l) => ({ value: l.id, label: `${l.code} - ${l.name}`, category: l.category || 'Other Accounts' })),
)
const reserveFundOptions = computed(() =>
  ledgers.value
    .filter((l) => l.code && String(l.code).startsWith('RFI'))
    .map((l) => ({ value: l.id, label: `${l.code} - ${l.name}`, category: l.category || 'Reserve Fund' })),
)
const customerOptions = computed(() =>
  units.value.map((u) => {
    const name = u.owner?.full_name || u.current_occupant?.full_name || 'No owner'
    const code = u.customer_code || u.unit_number
    return { value: u.id, label: `${code} - ${name}`, category: 'Customers' }
  }),
)

function accountOptions(row) {
  switch (row.line_type) {
    case 'general':      return generalLedgerOptions.value
    case 'reserve_fund': return reserveFundOptions.value
    case 'customer':     return customerOptions.value
    default:             return [] // supplier (empty for now) / nothing selected
  }
}

function accountPlaceholder(row) {
  switch (row.line_type) {
    case 'general':      return 'Select Ledger'
    case 'reserve_fund': return 'Select Reserve Fund Ledger'
    case 'customer':     return 'Select Customer'
    case 'supplier':     return 'Select Supplier'
    default:             return 'Nothing selected'
  }
}

// Reset the account when the ledger type changes.
function onTypeChange(row) {
  row.account = ''
}

// ── Totals (live) ─────────────────────────────────────────────────────────
function money(n) {
  return (Number(n) || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
const totalDebit  = computed(() => rows.value.filter((r) => r.entry_type === 'debit').reduce((s, r) => s + (parseFloat(r.amount) || 0), 0))
const totalCredit = computed(() => rows.value.filter((r) => r.entry_type === 'credit').reduce((s, r) => s + (parseFloat(r.amount) || 0), 0))
const difference  = computed(() => Math.round((totalDebit.value - totalCredit.value) * 100) / 100)
const balanced    = computed(() => Math.abs(difference.value) < 0.005 && totalDebit.value > 0)

// ── Row management ──────────────────────────────────────────────────────
function addRow() {
  rows.value.push(blankRow(rows.value.length % 2 === 0 ? 'debit' : 'credit'))
}
function removeRow(i) {
  if (rows.value.length <= 1) { rows.value = [blankRow('debit')]; return }
  rows.value.splice(i, 1)
}

// ── Files ────────────────────────────────────────────────────────────────
function onDrop(e) {
  dragging.value = false
  addFiles(e.dataTransfer?.files)
}
function onPick(e) {
  addFiles(e.target.files)
  e.target.value = ''
}
function addFiles(fileList) {
  if (!fileList) return
  for (const f of fileList) files.value.push(f)
}
function removeFile(i)          { files.value.splice(i, 1) }
function removeExistingFile(i)  { existingFiles.value.splice(i, 1) }

// ── Data loading ────────────────────────────────────────────────────────
const listOf = (res) => res?.data?.data ?? res?.data ?? []

async function loadAllUnits(communityId) {
  const PER_PAGE = 200
  const first = await api.get(`/communities/${communityId}/units`, { params: { _per_page: PER_PAGE, page: 1, _sort: 'unit_number:asc' } })
  const all = listOf(first)
  const lastPage = first?.data?.meta?.last_page ?? 1
  if (lastPage > 1) {
    const more = await Promise.all(
      Array.from({ length: lastPage - 1 }, (_, i) =>
        api.get(`/communities/${communityId}/units`, { params: { _per_page: PER_PAGE, page: i + 2, _sort: 'unit_number:asc' } }).then(listOf),
      ),
    )
    more.forEach((chunk) => all.push(...chunk))
  }
  return all
}

async function loadRefs() {
  if (!props.communityId) return
  loading.value = true
  try {
    const [ledgerRes] = await Promise.all([
      api.get('/ledgers', { params: { _per_page: 500, is_active: true } }),
      loadAllUnits(props.communityId).then((all) => { units.value = all }),
    ])
    ledgers.value = listOf(ledgerRes)
  } catch {
    error('Could not load ledgers or customers. Please refresh to try again.')
  } finally {
    loading.value = false
  }
}

// Prefill from an existing batch (edit or copy).
function prefillFromBatch(batch) {
  if (!batch) return
  date.value         = batch.date || today()
  journalGroup.value = batch.journal_group || ''
  existingFiles.value = props.mode === 'edit' ? [...(batch.files || [])] : []
  const lines = batch.lines || []
  rows.value = lines.length
    ? lines.map((l) => ({
        line_type:   l.line_type,
        account:     l.line_type === 'customer' ? l.unit_id : l.ledger_id,
        description: l.description || '',
        amount:      Number(l.amount || 0).toFixed(2),
        entry_type:  l.entry_type,
      }))
    : [blankRow('debit'), blankRow('credit')]
}

onMounted(() => {
  loadRefs()
  if (props.batch) prefillFromBatch(props.batch)
})
watch(() => props.communityId, loadRefs)
watch(() => props.batch, (b) => prefillFromBatch(b))

// ── Submit ────────────────────────────────────────────────────────────────
function validRows() {
  return rows.value.filter((r) => {
    if (!r.line_type) return false
    if ((r.line_type === 'general' || r.line_type === 'reserve_fund' || r.line_type === 'customer') && !r.account) return false
    return (parseFloat(r.amount) || 0) > 0
  })
}

async function submit() {
  const valid = validRows()
  if (valid.length < 2) { error('Add at least two complete lines.'); return }
  if (!balanced.value)  { error('Total debits must equal total credits (the difference must be 0.00).'); return }

  submitting.value = true
  const fd = new FormData()
  fd.append('date', date.value)
  if (journalGroup.value) fd.append('journal_group', journalGroup.value)

  valid.forEach((r, i) => {
    fd.append(`lines[${i}][line_type]`, r.line_type)
    if (r.line_type === 'customer') fd.append(`lines[${i}][unit_id]`, r.account)
    else if (r.line_type === 'general' || r.line_type === 'reserve_fund') fd.append(`lines[${i}][ledger_id]`, r.account)
    fd.append(`lines[${i}][description]`, r.description || '')
    fd.append(`lines[${i}][amount]`, parseFloat(r.amount) || 0)
    fd.append(`lines[${i}][entry_type]`, r.entry_type)
  })
  files.value.forEach((f) => fd.append('files[]', f))

  try {
    let created
    if (props.mode === 'edit') {
      fd.append('_method', 'PUT')
      existingFiles.value.forEach((f, i) => fd.append(`existing_files[${i}][path]`, f.path))
      const { data } = await api.post(`/journals/${props.batch.id}`, fd)
      created = data.data ?? data
      success(`${created?.batch_name ?? 'Journal batch'} updated successfully.`)
    } else {
      fd.append('community_id', props.communityId)
      fd.append('financial_year', props.financialYear)
      const { data } = await api.post('/journals', fd)
      created = data.data ?? data
      success(`${created?.batch_name ?? 'Journal batch'} created successfully.`)
      resetForm()
    }
    emit('saved', created)
  } catch (err) {
    const res = err.response?.data
    error(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not save the journal batch.'))
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  rows.value = [blankRow('debit'), blankRow('credit')]
  date.value = today()
  journalGroup.value = ''
  files.value = []
}

defineExpose({ prefillFromBatch })
</script>

<template>
  <div class="space-y-6">
    <!-- ── Lines table ──────────────────────────────────────────────── -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left border-b border-border">
            <th class="py-2 pr-3 font-semibold text-foreground w-[210px]">Ledger Type</th>
            <th class="py-2 px-3 font-semibold text-foreground w-[230px]">Account</th>
            <th class="py-2 px-3 font-semibold text-foreground">Description</th>
            <th class="py-2 px-3 font-semibold text-foreground text-right w-[150px]">Amount</th>
            <th class="py-2 px-3 font-semibold text-foreground text-center w-[70px]">Debit</th>
            <th class="py-2 px-3 font-semibold text-foreground text-center w-[90px]">Credit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in rows" :key="i" class="border-b border-border align-top">
            <td class="py-3 pr-3">
              <AppSelect
                v-model="row.line_type"
                :options="LEDGER_TYPES"
                placeholder="Nothing selected"
                @update:modelValue="onTypeChange(row)"
              />
            </td>
            <td class="py-3 px-3">
              <AppAccountSelect
                v-if="row.line_type && row.line_type !== 'supplier'"
                v-model="row.account"
                :options="accountOptions(row)"
                :placeholder="accountPlaceholder(row)"
              />
              <AppSelect v-else v-model="row.account" :options="[]" :placeholder="accountPlaceholder(row)" disabled />
            </td>
            <td class="py-3 px-3">
              <AppInput v-model="row.description" placeholder="" />
            </td>
            <td class="py-3 px-3">
              <AppInput v-model="row.amount" type="number" class="text-right" />
            </td>
            <td class="py-3 px-3 text-center">
              <input type="radio" :name="`entry-${i}`" value="debit" v-model="row.entry_type" class="accent-navy w-4 h-4" />
            </td>
            <td class="py-3 px-3 text-center">
              <input type="radio" :name="`entry-${i}`" value="credit" v-model="row.entry_type" class="accent-navy w-4 h-4" />
              <div class="mt-1">
                <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-danger hover:underline" @click="removeRow(i)">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                  Remove
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add line + totals -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-sky-600 hover:underline" @click="addRow">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM11 7a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
        Add Line
      </button>
      <table class="text-sm w-full max-w-xs">
        <tbody>
          <tr><td class="py-1 text-muted-foreground">Total Debit</td><td class="py-1 text-right font-medium tabular-nums">{{ money(totalDebit) }}</td></tr>
          <tr><td class="py-1 text-muted-foreground">Total Credit</td><td class="py-1 text-right font-medium tabular-nums">{{ money(totalCredit) }}</td></tr>
          <tr class="border-t border-border">
            <td class="py-1 font-semibold" :class="balanced ? 'text-emerald-600' : 'text-danger'">Difference</td>
            <td class="py-1 text-right font-semibold tabular-nums" :class="balanced ? 'text-emerald-600' : 'text-danger'">{{ money(difference) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Upload files + Date + Journal Group -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8 pt-4 border-t border-border">
      <div>
        <p class="text-sm font-medium text-foreground mb-2">Upload files</p>
        <div
          class="rounded-lg border-2 border-dashed transition-colors px-4 py-8 text-center text-muted-foreground"
          :class="dragging ? 'border-sky-400 bg-sky-50' : 'border-border'"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop"
        >
          Drag and drop files here
        </div>
        <div class="mt-3">
          <label class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-sky-500 hover:bg-sky-600 text-white text-sm font-medium cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5 5 5M12 5v12"/></svg>
            Click to select files
            <input type="file" multiple class="hidden" @change="onPick" />
          </label>
        </div>
        <ul v-if="existingFiles.length || files.length" class="mt-3 space-y-1 text-sm">
          <li v-for="(f, i) in existingFiles" :key="'e' + i" class="flex items-center gap-2 text-foreground">
            <span>📎 {{ f.name }}</span>
            <button type="button" class="text-danger hover:underline text-xs" @click="removeExistingFile(i)">remove</button>
          </li>
          <li v-for="(f, i) in files" :key="'n' + i" class="flex items-center gap-2 text-foreground">
            <span>📎 {{ f.name }}</span>
            <button type="button" class="text-danger hover:underline text-xs" @click="removeFile(i)">remove</button>
          </li>
        </ul>
      </div>

      <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <label class="text-sm font-medium text-foreground">Date</label>
          <input type="date" v-model="date" class="h-11 rounded-md border border-border px-3 text-sm w-[200px]" />
        </div>
        <div class="flex items-center justify-between gap-3">
          <label class="text-sm font-medium text-foreground">Journal Group</label>
          <div class="w-[200px]"><AppSelect v-model="journalGroup" :options="groupOptions" placeholder="--" /></div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-3 pt-2">
      <AppButton v-if="mode === 'edit'" variant="outline" @click="emit('cancel')">« Back to Journal Batches</AppButton>
      <AppButton variant="primary" :loading="submitting" :disabled="!balanced" @click="submit">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        {{ mode === 'edit' ? 'Submit Journal Edit' : 'Submit Journal Batch' }}
      </AppButton>
    </div>
  </div>
</template>
