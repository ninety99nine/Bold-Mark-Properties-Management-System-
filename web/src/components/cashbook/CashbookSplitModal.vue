<!--
  CashbookSplitModal — WeConnectU "Split transaction" dialog.

  Splits a single cashbook entry across multiple ledger targets. Each row carries
  a Ledger Type, a target (resolved via LedgerTargetPicker to ledger_id/unit_id/
  supplier_id), Remarks and a signed Amount. A live footer shows Total (the row's
  signed_amount) / Allocated (sum of line amounts) / Remaining — submit is blocked
  until Remaining reaches 0.00.

  Supports:
    - "Select template"  — GET /split-templates → load lines.
    - "Save as template" — POST /split-templates.
    - Bulk upload        — download the xlsx template (/split-template-file) and
                           drag/drop/pick a filled file → POST /split/upload, which
                           returns parsed `lines` (with per-row `error`) to load.

  Submits POST /cashbook/entries/{id}/split.
-->
<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import { useToast } from '@/composables/useToast'
import AppModal          from '@/components/common/AppModal.vue'
import AppButton         from '@/components/common/AppButton.vue'
import AppSelect         from '@/components/common/AppSelect.vue'
import AppInput          from '@/components/common/AppInput.vue'
import LedgerTargetPicker from '@/components/cashbook/LedgerTargetPicker.vue'

const props = defineProps({
  show:          { type: Boolean, default: false },
  entry:         { type: Object, default: null },
  ledgerOptions: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const communityStore = useCommunityStore()
const countryStore   = useCountryStore()
const { success, error } = useToast()

const communityId = computed(() => communityStore.selectedId)

function blankLine() {
  return {
    target: { ledger_type: '', ledger_id: null, unit_id: null, supplier_id: null, account_label: '' },
    remarks: '',
    amount: '0.00',
    error: null,
  }
}

const lines      = ref([blankLine()])
const submitting = ref(false)
const dragging   = ref(false)

// ── Templates ─────────────────────────────────────────────────────────────
const templates       = ref([])
const selectedTemplate = ref('')
const showSaveTemplate = ref(false)
const templateName     = ref('')
const savingTemplate   = ref(false)

const templateOptions = computed(() =>
  [{ value: '', label: 'Select template…' }, ...templates.value.map(t => ({ value: t.id, label: `${t.name} (${t.lines_count})` }))],
)

// ── Totals ──────────────────────────────────────────────────────────────
const money  = (v) => countryStore.formatCurrency(v)
const total  = computed(() => Number(props.entry?.signed_amount ?? 0))
const allocated = computed(() => lines.value.reduce((s, l) => s + (Number(l.amount) || 0), 0))
const remaining = computed(() => Math.round((total.value - allocated.value) * 100) / 100)
const balanced  = computed(() => Math.abs(remaining.value) < 0.005)

const LEDGER_TYPES = [
  { value: 'general',      label: 'General Ledger' },
  { value: 'customer',     label: 'Customer Ledger' },
  { value: 'supplier',     label: 'Supplier Ledger' },
  { value: 'reserve_fund', label: 'Reserve Fund Ledger' },
]

function lineHasTarget(l) {
  const t = l.target
  if (t.ledger_type === 'general' || t.ledger_type === 'reserve_fund') return !!t.ledger_id
  if (t.ledger_type === 'customer') return !!t.unit_id
  if (t.ledger_type === 'supplier') return !!t.supplier_id
  return false
}

const canSubmit = computed(() =>
  balanced.value &&
  lines.value.length > 0 &&
  lines.value.every(l => lineHasTarget(l) && (Number(l.amount) || 0) !== 0),
)

function addLine()      { lines.value.push(blankLine()) }
function removeLine(i)  { lines.value.splice(i, 1); if (!lines.value.length) lines.value.push(blankLine()) }

function reset() {
  lines.value = [blankLine()]
  selectedTemplate.value = ''
  showSaveTemplate.value = false
  templateName.value = ''
  loadTemplates()
}

watch(() => props.show, (v) => { if (v) reset() })

// ── Template loading ────────────────────────────────────────────────────
async function loadTemplates() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/split-templates`)
    templates.value = data.data ?? data ?? []
  } catch { templates.value = [] }
}

function lineFromTemplateLine(l) {
  const line = blankLine()
  line.target.ledger_type   = l.ledger_type ?? ''
  line.target.ledger_id     = l.ledger_id ?? null
  line.target.unit_id       = l.unit_id ?? null
  line.target.supplier_id   = l.supplier_id ?? null
  line.target.account_label = l.account_label ?? l.resolved_label ?? l.account ?? ''
  line.remarks = l.remarks ?? ''
  line.amount  = l.amount != null ? Number(l.amount).toFixed(2) : '0.00'
  line.error   = l.error ?? null
  return line
}

async function onSelectTemplate(id) {
  selectedTemplate.value = id
  if (!id) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/split-templates/${id}`)
    const tpl = data.data ?? data
    const tplLines = tpl.lines ?? []
    if (tplLines.length) lines.value = tplLines.map(lineFromTemplateLine)
  } catch {
    error('Could not load the selected template.')
  }
}

async function saveTemplate() {
  if (!templateName.value.trim() || savingTemplate.value) return
  savingTemplate.value = true
  try {
    await api.post(`/communities/${communityId.value}/split-templates`, {
      name: templateName.value.trim(),
      lines: lines.value.map(buildLinePayload),
    })
    success('Split template saved.')
    showSaveTemplate.value = false
    templateName.value = ''
    loadTemplates()
  } catch (e) {
    error(e.response?.data?.message ?? 'Could not save the template.')
  } finally {
    savingTemplate.value = false
  }
}

// ── Bulk upload ─────────────────────────────────────────────────────────
async function downloadTemplateFile() {
  try {
    const { data, headers } = await api.get(
      `/communities/${communityId.value}/cashbook/split-template-file`,
      { responseType: 'blob' },
    )
    let filename = 'cashbook-split-template.xlsx'
    const cd = headers['content-disposition']
    if (cd) {
      const m = cd.match(/filename[^;=\n]*=["']?([^;"'\n]+)/)
      if (m?.[1]) filename = m[1].replace(/['"]/g, '').trim()
    }
    const url  = URL.createObjectURL(new Blob([data]))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch {
    error('Could not download the split template.')
  }
}

function onDrop(e) { dragging.value = false; uploadFile(e.dataTransfer?.files?.[0]) }
function onPick(e) { uploadFile(e.target.files?.[0]); e.target.value = '' }

async function uploadFile(file) {
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data } = await api.post(
      `/communities/${communityId.value}/cashbook/entries/${props.entry.id}/split/upload`,
      fd,
    )
    const parsed = data.lines ?? []
    if (parsed.length) lines.value = parsed.map(lineFromTemplateLine)
    success('Split file loaded. Review the rows below.')
  } catch (e) {
    error(e.response?.data?.message ?? 'Could not parse the uploaded file.')
  }
}

// ── Submit ────────────────────────────────────────────────────────────────
function buildLinePayload(l) {
  const t = l.target
  const payload = { ledger_type: t.ledger_type, amount: Number(l.amount) || 0 }
  // The backend accepts a resolved id via `target`; send the resolved id where
  // available and fall back to the human account label.
  payload.target =
    t.ledger_id ?? t.unit_id ?? t.supplier_id ?? t.account_label ?? ''
  if (t.ledger_id)   payload.ledger_id = t.ledger_id
  if (t.unit_id)     payload.unit_id = t.unit_id
  if (t.supplier_id) payload.supplier_id = t.supplier_id
  if (l.remarks?.trim()) payload.remarks = l.remarks.trim()
  return payload
}

async function submit() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  try {
    const { data } = await api.post(
      `/communities/${communityId.value}/cashbook/entries/${props.entry.id}/split`,
      { lines: lines.value.map(buildLinePayload) },
    )
    success(data.message ?? 'Transaction split.')
    emit('saved', data.data ?? null)
    emit('close')
  } catch (e) {
    const res = e.response?.data
    error(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not split the transaction.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :show="show" title="Split Transaction" size="xl" @close="$emit('close')">
    <div v-if="entry" class="space-y-5">
      <!-- Transaction summary -->
      <div class="rounded border border-border bg-muted/30 px-4 py-3 text-sm">
        <div class="flex items-center justify-between">
          <span class="text-muted-foreground">{{ entry.date }}</span>
          <span class="font-semibold tabular-nums" :class="entry.type === 'credit' ? 'text-success' : 'text-destructive'">
            {{ money(entry.signed_amount) }}
          </span>
        </div>
        <p class="text-foreground mt-1">{{ entry.description }}</p>
      </div>

      <!-- Template controls -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="w-64">
          <AppSelect :model-value="selectedTemplate" :options="templateOptions" placeholder="Select template…" @update:model-value="onSelectTemplate" />
        </div>
        <AppButton variant="outline" size="sm" @click="showSaveTemplate = !showSaveTemplate">Save as template</AppButton>
      </div>
      <div v-if="showSaveTemplate" class="flex items-end gap-3">
        <div class="w-64"><AppInput v-model="templateName" label="Template name" placeholder="e.g. Monthly split" /></div>
        <AppButton variant="primary" size="sm" :loading="savingTemplate" :disabled="!templateName.trim()" @click="saveTemplate">Save</AppButton>
      </div>

      <!-- Lines -->
      <div class="space-y-4">
        <div
          v-for="(l, i) in lines"
          :key="i"
          class="rounded border border-border p-4"
          :class="l.error ? 'border-destructive/50 bg-destructive/5' : ''"
        >
          <div class="grid grid-cols-1 lg:grid-cols-[210px_1fr] gap-4">
            <!-- Ledger type -->
            <div>
              <label class="block text-sm font-medium text-fg mb-1.5">Ledger Type</label>
              <AppSelect
                :model-value="l.target.ledger_type"
                :options="LEDGER_TYPES"
                placeholder="Nothing selected"
                @update:model-value="(v) => (lines[i].target = { ledger_type: v, ledger_id: null, unit_id: null, supplier_id: null, account_label: '' })"
              />
            </div>
            <!-- Target picker (type select hidden — driven by the column above) -->
            <LedgerTargetPicker v-model="lines[i].target" :ledger-options="ledgerOptions" hide-type-select />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-[1fr_180px_auto] gap-4 mt-3 items-end">
            <AppInput v-model="l.remarks" label="Remarks" placeholder="Optional" />
            <AppInput v-model="l.amount" label="Amount" type="number" class="text-right" />
            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-destructive hover:underline h-11" @click="removeLine(i)">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
              Remove
            </button>
          </div>

          <p v-if="l.error" class="mt-2 text-xs text-destructive">{{ l.error }}</p>
        </div>

        <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline" @click="addLine">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM11 7a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
          Add Line
        </button>
      </div>

      <!-- Bulk upload -->
      <div class="pt-4 border-t border-border">
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-medium text-foreground">Bulk upload</p>
          <button type="button" class="text-sm text-primary hover:underline" @click="downloadTemplateFile">Download template</button>
        </div>
        <div
          class="rounded-lg border-2 border-dashed transition-colors px-4 py-6 text-center text-muted-foreground"
          :class="dragging ? 'border-amber bg-amber/5' : 'border-border'"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop"
        >
          <p class="text-sm">Drag and drop a filled split file here, or</p>
          <label class="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded bg-navy text-white text-sm font-medium cursor-pointer hover:bg-navy-dark">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5 5 5M12 5v12"/></svg>
            Select file
            <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onPick" />
          </label>
        </div>
      </div>

      <!-- Footer totals -->
      <div class="flex justify-end pt-4 border-t border-border">
        <table class="text-sm w-full max-w-xs">
          <tbody>
            <tr><td class="py-1 text-muted-foreground">Total</td><td class="py-1 text-right font-medium tabular-nums">{{ money(total) }}</td></tr>
            <tr><td class="py-1 text-muted-foreground">Allocated</td><td class="py-1 text-right font-medium tabular-nums">{{ money(allocated) }}</td></tr>
            <tr class="border-t border-border">
              <td class="py-1 font-semibold" :class="balanced ? 'text-success' : 'text-destructive'">Remaining</td>
              <td class="py-1 text-right font-semibold tabular-nums" :class="balanced ? 'text-success' : 'text-destructive'">{{ money(remaining) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
      <AppButton variant="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">Split Transaction</AppButton>
    </template>
  </AppModal>
</template>
