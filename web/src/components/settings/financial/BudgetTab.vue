<!--
  BudgetTab — WeConnectU "Financial Setup → Budget" grid clone.

  A shared component mounted BOTH as the Financial page's "Budget" tab AND as the
  standalone /settings/finance/budget route ("Budget Setup"), and reused (fund="reserve")
  as the "Setup Reserve Fund Budget" sub-tab. Every entry point lands on identical content.

  The grid lists every GL account grouped INCOME / EXPENSE, each with:
    · Equal Monthly Amount (checkbox) · Per Month · Per Year
  Toolbar: Download Budget Template + Budget Excel Import; the whole grid is lockable
  ("Re-Open Budget for Editing" when locked).

  Endpoints (community-scoped, {c} = community.selectedId):
    GET  communities/{c}/budgets?year=&fund=          → rows + locked flag
    PUT  communities/{c}/budgets                       → save
    POST communities/{c}/budgets/lock | /unlock
    GET  communities/{c}/budgets/template              → xlsx download
    POST communities/{c}/budgets/import                → xlsx upload
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import AppButton from '@/components/common/AppButton.vue'

const props = defineProps({
  year: { type: [String, Number], default: null },
  fund: { type: String, default: 'main' }, // 'main' | 'reserve'
})

const { success, error } = useToast()
const community = useCommunityStore()
const country = useCountryStore()

const rows = ref([])       // [{ ledger_id, code, name, section, equal_monthly, per_month, per_year }]
const locked = ref(false)
const loading = ref(true)
const saving = ref(false)
const importing = ref(false)
const fileInput = ref(null)

const cid = computed(() => community.selectedId)

async function load() {
  if (!cid.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${cid.value}/budgets`, {
      params: { year: props.year, fund: props.fund },
    })
    const payload = data.data ?? data ?? {}
    const list = payload.rows ?? payload.budgets ?? (Array.isArray(payload) ? payload : [])
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
  const perMonth = Number(r.per_month ?? 0)
  const perYear = Number(r.per_year ?? (r.equal_monthly ? perMonth * 12 : 0))
  return {
    ledger_id: r.ledger_id ?? r.id,
    code: r.code ?? '',
    name: r.name ?? r.label ?? '',
    section: (r.section ?? r.type ?? '').toString().toUpperCase().includes('EXP') ? 'EXPENSE' : 'INCOME',
    equal_monthly: r.equal_monthly !== false,
    per_month: perMonth,
    per_year: perYear,
  }
}

onMounted(load)
watch([cid, () => props.year, () => props.fund], load)

const income = computed(() => rows.value.filter(r => r.section === 'INCOME'))
const expense = computed(() => rows.value.filter(r => r.section === 'EXPENSE'))

function fmt(v) { return country.formatCurrency(v ?? 0) }

function onEqualToggle(r) {
  if (r.equal_monthly) r.per_year = Number(r.per_month || 0) * 12
}
function onPerMonth(r, val) {
  r.per_month = Number(val) || 0
  if (r.equal_monthly) r.per_year = r.per_month * 12
}
function onPerYear(r, val) {
  r.per_year = Number(val) || 0
  if (r.equal_monthly) r.per_month = r.per_year / 12
}

function totalMonth(list) { return list.reduce((s, r) => s + Number(r.per_month || 0), 0) }
function totalYear(list) { return list.reduce((s, r) => s + Number(r.per_year || 0), 0) }

async function save() {
  if (!cid.value) return
  saving.value = true
  try {
    await api.put(`/communities/${cid.value}/budgets`, {
      year: props.year,
      fund: props.fund,
      rows: rows.value.map(r => ({
        ledger_id: r.ledger_id,
        equal_monthly: r.equal_monthly,
        per_month: r.per_month,
        per_year: r.per_year,
      })),
    })
    success('Budget saved.')
  } catch (e) {
    error(e?.response?.data?.message ?? 'Failed to save budget.')
  } finally {
    saving.value = false
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
    a.download = `budget-template-${props.fund}-${props.year ?? ''}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error('Could not download the budget template.')
  }
}

function triggerImport() { fileInput.value?.click() }

async function onFilePicked(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file || !cid.value) return
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('year', props.year ?? '')
    fd.append('fund', props.fund)
    await api.post(`/communities/${cid.value}/budgets/import`, fd)
    success('Budget imported.')
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
    <!-- Toolbar -->
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
        <AppButton variant="outline" @click="downloadTemplate">Download Budget Template</AppButton>
        <AppButton variant="outline" :loading="importing" @click="triggerImport">Budget Excel Import</AppButton>
        <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFilePicked" />
        <AppButton variant="outline" @click="toggleLock">
          {{ locked ? 'Re-Open Budget for Editing' : 'Lock Budget' }}
        </AppButton>
        <AppButton variant="primary" :loading="saving" :disabled="locked" @click="save">Save Budget</AppButton>
      </div>
    </div>

    <div class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else-if="!rows.length" class="py-12 text-center text-sm text-muted-foreground">
        No budget accounts available for this period yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Account</th>
              <th class="py-2.5 px-3 text-center font-bold text-navy-dark w-40">Equal Monthly Amount</th>
              <th class="py-2.5 px-3 text-right font-bold text-navy-dark w-44">Per Month</th>
              <th class="py-2.5 px-3 text-right font-bold text-navy-dark w-44">Per Year</th>
            </tr>
          </thead>
          <tbody>
            <!-- INCOME -->
            <tr class="bg-muted/40 border-b border-border">
              <td colspan="4" class="py-2 px-3 text-xs font-bold uppercase tracking-wide text-navy-dark">Income</td>
            </tr>
            <tr v-for="r in income" :key="'inc-' + r.ledger_id" class="border-b border-border/60 hover:bg-muted/30">
              <td class="py-2 px-3">
                <span class="font-mono text-xs text-muted-foreground">{{ r.code }}</span>
                <span class="ml-2 text-foreground">{{ r.name }}</span>
              </td>
              <td class="py-2 px-3 text-center">
                <input type="checkbox" v-model="r.equal_monthly" :disabled="locked" class="w-5 h-5 rounded accent-[#2f6fb0]" @change="onEqualToggle(r)" />
              </td>
              <td class="py-2 px-3 text-right">
                <input type="number" :value="r.per_month" :disabled="locked" class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/50" @input="onPerMonth(r, $event.target.value)" />
              </td>
              <td class="py-2 px-3 text-right">
                <input type="number" :value="r.per_year" :disabled="locked || r.equal_monthly" class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/50" @input="onPerYear(r, $event.target.value)" />
              </td>
            </tr>
            <tr v-if="!income.length"><td colspan="4" class="py-3 px-3 text-sm text-muted-foreground italic">No income accounts.</td></tr>
            <tr class="border-b-2 border-border font-semibold text-navy-dark">
              <td class="py-2 px-3 text-right">Total Income</td>
              <td></td>
              <td class="py-2 px-3 text-right tabular-nums">{{ fmt(totalMonth(income)) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ fmt(totalYear(income)) }}</td>
            </tr>

            <!-- EXPENSE -->
            <tr class="bg-muted/40 border-b border-border">
              <td colspan="4" class="py-2 px-3 text-xs font-bold uppercase tracking-wide text-navy-dark">Expense</td>
            </tr>
            <tr v-for="r in expense" :key="'exp-' + r.ledger_id" class="border-b border-border/60 hover:bg-muted/30">
              <td class="py-2 px-3">
                <span class="font-mono text-xs text-muted-foreground">{{ r.code }}</span>
                <span class="ml-2 text-foreground">{{ r.name }}</span>
              </td>
              <td class="py-2 px-3 text-center">
                <input type="checkbox" v-model="r.equal_monthly" :disabled="locked" class="w-5 h-5 rounded accent-[#2f6fb0]" @change="onEqualToggle(r)" />
              </td>
              <td class="py-2 px-3 text-right">
                <input type="number" :value="r.per_month" :disabled="locked" class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/50" @input="onPerMonth(r, $event.target.value)" />
              </td>
              <td class="py-2 px-3 text-right">
                <input type="number" :value="r.per_year" :disabled="locked || r.equal_monthly" class="w-36 h-9 rounded border border-border px-2 text-right text-sm tabular-nums disabled:bg-muted/50" @input="onPerYear(r, $event.target.value)" />
              </td>
            </tr>
            <tr v-if="!expense.length"><td colspan="4" class="py-3 px-3 text-sm text-muted-foreground italic">No expense accounts.</td></tr>
            <tr class="font-semibold text-navy-dark">
              <td class="py-2 px-3 text-right">Total Expense</td>
              <td></td>
              <td class="py-2 px-3 text-right tabular-nums">{{ fmt(totalMonth(expense)) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ fmt(totalYear(expense)) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
