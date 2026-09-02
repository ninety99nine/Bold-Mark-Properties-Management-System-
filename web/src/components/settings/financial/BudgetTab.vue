<!--
  BudgetTab — WeConnectU "Budget Setup" grid clone.

  Mounted BOTH as the Financial page's "Budget" tab AND as the standalone
  /settings/finance/budget route ("Budget Setup"), and reused (fund="reserve")
  as the "Setup Reserve Fund Budget" sub-tab — every entry point lands on
  identical content.

  The grid lists every leaf GL account grouped INCOME / EXPENSE, under a computed
  category header (e.g. "1000/000 INCOME"), each row with:
    · Equal Monthly Amount (checkbox)  · Per Month  · Per Year
  Unchecking "Equal Monthly Amount" reveals a "Specify monthly budget" link that
  opens the 12-month editor. Toolbar: Download Budget Template + Budget Excel
  Import; the grid is finalised with "Finalize Budget" (which persists it, flipping
  the period from "(Not Setup)" to "(Setup)") and can be locked / re-opened.

  Endpoints (community-scoped, {c} = community.selectedId):
    GET  communities/{c}/budgets?year=&fund=   → rows (+ group/section) + locked flag
    PUT  communities/{c}/budgets                → save / finalise
    POST communities/{c}/budgets/lock | /unlock
    GET  communities/{c}/budgets/template       → xlsx download
    POST communities/{c}/budgets/import         → xlsx upload
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppButton from '@/components/common/AppButton.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppTooltip from '@/components/common/AppTooltip.vue'
import SpecifyMonthlyBudgetModal from './SpecifyMonthlyBudgetModal.vue'

const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']

const props = defineProps({
  year: { type: [String, Number], default: null },
  fund: { type: String, default: 'main' }, // 'main' | 'reserve'
})

const emit = defineEmits(['finalized'])

const { success, error } = useToast()
const community = useCommunityStore()

const rows = ref([])
const locked = ref(false)
const loading = ref(true)
const finalizing = ref(false)
const importing = ref(false)

// Specify-monthly modal
const monthlyRow = ref(null)
const showMonthly = ref(false)

// Import modal
const showImport = ref(false)
const dragOver = ref(false)
const fileInput = ref(null)

const cid = computed(() => community.selectedId)

function round2(v) {
  return Math.round((Number(v) || 0) * 100) / 100
}
function fmtNum(v) {
  return (Number(v) || 0).toFixed(2)
}

async function load() {
  if (!cid.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${cid.value}/budgets`, {
      params: { year: props.year, fund: props.fund },
    })
    const payload = data.data ? data : (data ?? {})
    const list = payload.data ?? payload.rows ?? []
    rows.value = list.map(normalizeRow)
    locked.value = !!(payload.locked ?? payload.is_locked)
  } catch {
    rows.value = []
    locked.value = false
  } finally {
    loading.value = false
  }
}

function normalizeRow(r) {
  const row = {
    ledger_id: r.ledger_id ?? r.id,
    code: r.code ?? '',
    name: r.name ?? r.label ?? '',
    section: (r.section ?? '').toString().toLowerCase().includes('exp') ? 'expense' : 'income',
    group_code: r.group_code ?? '',
    group_name: r.group_name ?? '',
    equal_monthly: r.equal_monthly !== false,
    per_year: Number(r.per_year ?? 0),
  }
  MONTHS.forEach((m) => { row[m] = Number(r[m] ?? 0) })
  return row
}

onMounted(load)
watch([cid, () => props.year, () => props.fund], load)

// ── Grouping ──────────────────────────────────────────────────────────────
function groupize(list) {
  const groups = []
  const index = {}
  for (const r of list) {
    const key = r.group_code || r.code
    if (!(key in index)) {
      index[key] = groups.length
      groups.push({ code: r.group_code, name: r.group_name, rows: [] })
    }
    groups[index[key]].rows.push(r)
  }
  return groups
}

const incomeGroups = computed(() => groupize(rows.value.filter((r) => r.section === 'income')))
const expenseGroups = computed(() => groupize(rows.value.filter((r) => r.section === 'expense')))

// ── Per-row / per-group derivations ─────────────────────────────────────────
function perMonth(r) {
  return r.equal_monthly ? round2(r.per_year / 12) : 0
}
function groupMonth(g) {
  return g.rows.reduce((s, r) => s + perMonth(r), 0)
}
function groupYear(g) {
  return g.rows.reduce((s, r) => s + Number(r.per_year || 0), 0)
}

// ── Editing ─────────────────────────────────────────────────────────────────
function setMonths(r, v) {
  MONTHS.forEach((m) => { r[m] = v })
}
function onEqualToggle(r) {
  if (r.equal_monthly) {
    // Re-checked → distribute the annual total evenly across the year.
    const pm = round2(r.per_year / 12)
    setMonths(r, pm)
    r.per_year = round2(pm * 12)
  } else {
    // Unchecked → keep current months; the annual total is their sum.
    r.per_year = round2(MONTHS.reduce((s, m) => s + Number(r[m] || 0), 0))
  }
}
function onPerMonth(r, val) {
  const pm = round2(val)
  setMonths(r, pm)
  r.per_year = round2(pm * 12)
}
function onPerYear(r, val) {
  const py = round2(val)
  r.per_year = py
  setMonths(r, round2(py / 12))
}

function openMonthly(r) {
  if (locked.value) return
  monthlyRow.value = r
  showMonthly.value = true
}
function applyMonthly(months) {
  const r = monthlyRow.value
  if (r) {
    MONTHS.forEach((m) => { r[m] = Number(months[m] || 0) })
    r.equal_monthly = false
    r.per_year = round2(MONTHS.reduce((s, m) => s + Number(r[m] || 0), 0))
  }
  showMonthly.value = false
}

// ── Persist ───────────────────────────────────────────────────────────────
function payloadRows() {
  return rows.value.map((r) => {
    const months = {}
    MONTHS.forEach((m) => { months[m] = Number(r[m] || 0) })
    return { ledger_id: r.ledger_id, equal_monthly: r.equal_monthly, per_year: Number(r.per_year || 0), ...months }
  })
}

async function finalize() {
  if (!cid.value || locked.value) return
  finalizing.value = true
  try {
    await api.put(`/communities/${cid.value}/budgets`, {
      year: props.year,
      fund: props.fund,
      rows: payloadRows(),
    })
    success('Budget finalised.')
    emit('finalized')
    await load()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Failed to finalise budget.')
  } finally {
    finalizing.value = false
  }
}

async function toggleLock() {
  if (!cid.value) return
  const action = locked.value ? 'unlock' : 'lock'
  try {
    await api.post(`/communities/${cid.value}/budgets/${action}`, { year: props.year, fund: props.fund })
    locked.value = !locked.value
    success(locked.value ? 'Budget locked.' : 'Budget re-opened for editing.')
  } catch (e) {
    error(e?.response?.data?.message ?? 'Could not update budget lock state.')
  }
}

// ── Template + import ───────────────────────────────────────────────────────
async function downloadTemplate() {
  if (!cid.value) return
  try {
    const res = await api.get(`/communities/${cid.value}/budgets/template`, {
      params: { year: props.year, fund: props.fund },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `budget-template.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error('Could not download the budget template.')
  }
}

function pickFile() { fileInput.value?.click() }

function onFileInput(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (file) uploadBudget(file)
}
function onDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) uploadBudget(file)
}

async function uploadBudget(file) {
  if (!cid.value) return
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('year', props.year ?? '')
    fd.append('fund', props.fund)
    const { data } = await api.post(`/communities/${cid.value}/budgets/import`, fd)
    success(data?.message ?? 'Budget imported.')
    showImport.value = false
    emit('finalized')
    await load()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Budget import failed.')
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar (top-right, as in WeConnectU) -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <span
          v-if="locked"
          class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Budget locked
        </span>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton variant="secondary" @click="downloadTemplate">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download Budget Template
        </AppButton>
        <AppButton variant="secondary" :disabled="locked" @click="showImport = true">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Budget Excel Import
        </AppButton>
      </div>
    </div>

    <div class="rounded-lg border border-border bg-white">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else-if="!rows.length" class="py-12 text-center text-sm text-muted-foreground">
        No budget accounts available for this period yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <tbody>
            <!-- ── INCOME / EXPENSE sections ─────────────────────────────── -->
            <template v-for="section in [{ key: 'income', label: 'INCOME', groups: incomeGroups }, { key: 'expense', label: 'EXPENSE', groups: expenseGroups }]" :key="section.key">
              <!-- Section banner = column headers -->
              <tr class="bg-muted border-y border-border">
                <th class="py-2.5 px-4 text-left font-bold text-navy-dark w-64" colspan="2">{{ section.label }}</th>
                <th class="py-2.5 px-3 text-center font-bold text-navy-dark w-52">
                  <span class="inline-flex items-center gap-1">
                    Equal Monthly Amount
                    <AppTooltip text="Uncheck this box to allow monthly budgeting" position="top">
                      <svg class="w-3.5 h-3.5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </AppTooltip>
                  </span>
                </th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark w-44">Per Month</th>
                <th class="py-2.5 px-3 text-left font-bold text-navy-dark w-44">Per Year</th>
              </tr>

              <template v-for="g in section.groups" :key="section.key + '-' + g.code">
                <!-- Group header row (computed aggregates, read-only) -->
                <tr class="bg-muted/50 border-b border-border">
                  <td class="py-2 px-4 font-bold text-navy-dark font-mono text-xs whitespace-nowrap">{{ g.code }}</td>
                  <td class="py-2 px-3 font-bold text-navy-dark uppercase">{{ g.name }}</td>
                  <td class="py-2 px-3 text-center">
                    <input type="checkbox" disabled class="w-5 h-5 rounded accent-[#2f6fb0] opacity-40" />
                  </td>
                  <td class="py-2 px-3">
                    <input :value="fmtNum(groupMonth(g))" disabled class="w-36 h-9 rounded border border-border bg-muted/60 px-2 text-right text-sm tabular-nums text-muted-foreground" />
                  </td>
                  <td class="py-2 px-3">
                    <input :value="fmtNum(groupYear(g))" disabled class="w-36 h-9 rounded border border-border bg-muted/60 px-2 text-right text-sm tabular-nums text-muted-foreground" />
                  </td>
                </tr>

                <!-- Leaf account rows -->
                <tr v-for="r in g.rows" :key="r.ledger_id" class="border-b border-border/60 hover:bg-muted/20">
                  <td class="py-2 px-4 font-mono text-xs text-muted-foreground whitespace-nowrap">{{ r.code }}</td>
                  <td class="py-2 px-3 text-foreground">{{ r.name }}</td>
                  <td class="py-2 px-3 text-center align-top">
                    <input type="checkbox" v-model="r.equal_monthly" :disabled="locked" class="w-5 h-5 rounded accent-[#2f6fb0]" @change="onEqualToggle(r)" />
                    <div v-if="!r.equal_monthly" class="mt-1">
                      <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline" :disabled="locked" @click="openMonthly(r)">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Specify monthly budget
                      </button>
                    </div>
                  </td>
                  <td class="py-2 px-3">
                    <input
                      :value="fmtNum(perMonth(r))"
                      :disabled="locked || !r.equal_monthly"
                      inputmode="decimal"
                      class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/60 disabled:text-muted-foreground"
                      @change="onPerMonth(r, $event.target.value)"
                    />
                  </td>
                  <td class="py-2 px-3">
                    <input
                      :value="fmtNum(r.per_year)"
                      :disabled="locked || !r.equal_monthly"
                      inputmode="decimal"
                      class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/60 disabled:text-muted-foreground"
                      @change="onPerYear(r, $event.target.value)"
                    />
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Footer actions (Finalize bottom-right, as in WeConnectU) -->
    <div class="flex flex-wrap items-center justify-end gap-2">
      <AppButton v-if="rows.length" variant="outline" @click="toggleLock">
        {{ locked ? 'Re-Open Budget for Editing' : 'Lock Budget' }}
      </AppButton>
      <AppButton v-if="rows.length" variant="secondary" :loading="finalizing" :disabled="locked" @click="finalize">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Finalize Budget
      </AppButton>
    </div>

    <!-- Specify Monthly Budget modal -->
    <SpecifyMonthlyBudgetModal
      :show="showMonthly"
      :row="monthlyRow"
      :year="year"
      @close="showMonthly = false"
      @apply="applyMonthly"
    />

    <!-- Budget Excel Import modal -->
    <AppModal :show="showImport" size="xl" title="Budget Upload" @close="showImport = false">
      <div class="space-y-5">
        <div class="text-sm text-foreground space-y-1">
          <p class="font-bold">Notes on Uploading:</p>
          <p>
            - Download and use
            <button type="button" class="text-primary font-medium hover:underline" @click="downloadTemplate">this Budget Template</button>
          </p>
          <p>- Please upload the file exactly AS IS.</p>
          <p>- <span class="font-bold">DO NOT</span> remove / add columns or alter headers.</p>
        </div>

        <div
          class="flex flex-col items-center justify-center gap-4 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors"
          :class="dragOver ? 'border-primary bg-primary/5' : 'border-border'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <p class="text-muted-foreground">Drag and drop your file here</p>
          <AppButton variant="secondary" :loading="importing" @click="pickFile">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Click to select excel file
          </AppButton>
          <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileInput" />
        </div>
      </div>
    </AppModal>
  </div>
</template>
