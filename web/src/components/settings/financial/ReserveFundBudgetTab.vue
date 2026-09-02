<!--
  ReserveFundBudgetTab — WeConnectU "Financial Setup → Reserve Fund Budget Setup" clone.

  TWO sub-tabs (shared so every entry point lands identically):
    · "Reserve Fund vs Reserve Fund Budget"  — a budget-vs-actual report shell
      (months + YTD + Budget YTD + Variance + Total Budget; Date from/to,
       Hide Zero Values, Add Monthly Budget/Variance, Download Excel).
    · "Setup Reserve Fund Budget"            — the Budget grid with fund="reserve".

  Entry points:
    · Financial page tab  → defaults to the report sub-tab.
    · /settings/finance/reserve-fund-budget route → defaults to the setup sub-tab.

  Budget-vs-actual endpoint: best-effort GET communities/{c}/budgets/reserve-actual-vs-budget.
  If it is unavailable the report renders its shell with a "coming soon" note on the actual
  side, while the setup sub-tab remains fully functional.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import AppButton from '@/components/common/AppButton.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import BudgetTab from '@/components/settings/financial/BudgetTab.vue'

const props = defineProps({
  year:          { type: [String, Number], default: null },
  defaultSubTab: { type: String, default: 'report' }, // 'report' | 'setup'
})

const community = useCommunityStore()
const country = useCountryStore()

const SUB_TABS = [
  { key: 'report', label: 'Reserve Fund vs Reserve Fund Budget' },
  { key: 'setup',  label: 'Setup Reserve Fund Budget' },
]
const subTab = ref(props.defaultSubTab)
watch(() => props.defaultSubTab, (v) => { subTab.value = v })

const cid = computed(() => community.selectedId)

// ── Report state ────────────────────────────────────────────────────────────
const dateFrom = ref('')
const dateTo = ref('')
const hideZero = ref(false)
const reportRows = ref([])
const reportMonths = ref([])
const reportAvailable = ref(false)
const reportLoading = ref(false)

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

async function loadReport() {
  if (!cid.value || subTab.value !== 'report') return
  reportLoading.value = true
  try {
    const { data } = await api.get(`/communities/${cid.value}/budgets/reserve-actual-vs-budget`, {
      params: { year: props.year, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined },
    })
    const payload = data.data ?? data ?? {}
    reportRows.value = payload.rows ?? []
    reportMonths.value = payload.months ?? MONTHS
    reportAvailable.value = true
  } catch {
    // Endpoint not available yet — render the shell only.
    reportRows.value = []
    reportMonths.value = MONTHS
    reportAvailable.value = false
  } finally {
    reportLoading.value = false
  }
}

onMounted(loadReport)
watch([cid, subTab, () => props.year], loadReport)

function fmt(v) { return country.formatCurrency(v ?? 0) }

const visibleRows = computed(() =>
  hideZero.value
    ? reportRows.value.filter(r => Number(r.total_budget || 0) !== 0 || Number(r.ytd || 0) !== 0)
    : reportRows.value,
)

async function downloadExcel() {
  if (!cid.value) return
  try {
    const res = await api.get(`/communities/${cid.value}/budgets/reserve-actual-vs-budget/export`, {
      params: { year: props.year, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `reserve-fund-vs-budget-${props.year ?? ''}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    /* silent — export endpoint may not exist yet */
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Sub-tabs -->
    <div class="border-b border-border">
      <nav class="-mb-px flex flex-wrap gap-x-6 gap-y-1">
        <button
          v-for="s in SUB_TABS"
          :key="s.key"
          type="button"
          class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium transition-colors"
          :class="subTab === s.key
            ? 'border-accent text-navy-dark'
            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
          @click="subTab = s.key"
        >
          {{ s.label }}
        </button>
      </nav>
    </div>

    <!-- ── Report sub-tab ──────────────────────────────────────────────── -->
    <div v-if="subTab === 'report'" class="space-y-4">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-fg">Date From</label>
            <AppDatePicker v-model="dateFrom" placeholder="Start date" @update:modelValue="loadReport" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-fg">Date To</label>
            <AppDatePicker v-model="dateTo" placeholder="End date" @update:modelValue="loadReport" />
          </div>
          <label class="flex items-center gap-2.5 self-end h-11 text-sm text-foreground cursor-pointer select-none">
            <input v-model="hideZero" type="checkbox" class="w-5 h-5 rounded accent-[#2f6fb0]" />
            Hide Zero Values
          </label>
        </div>
        <div class="flex items-center gap-2">
          <AppButton variant="outline">Add Monthly Budget/Variance</AppButton>
          <AppButton variant="outline" @click="downloadExcel">Download Excel</AppButton>
        </div>
      </div>

      <div class="rounded-lg border border-border bg-white p-6">
        <div v-if="reportLoading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

        <template v-else>
          <div v-if="!reportAvailable" class="mb-4 rounded-md border border-amber/30 bg-amber/10 px-4 py-3 text-sm text-amber-dark">
            The actual-vs-budget figures for the Reserve Fund are coming soon. Set up your reserve budget in the
            “Setup Reserve Fund Budget” sub-tab; actuals will populate this report once posting is available.
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border">
                  <th class="py-2.5 px-3 text-left font-bold text-navy-dark whitespace-nowrap">Account</th>
                  <th v-for="m in reportMonths" :key="m" class="py-2.5 px-3 text-right font-bold text-navy-dark whitespace-nowrap">{{ m }}</th>
                  <th class="py-2.5 px-3 text-right font-bold text-navy-dark whitespace-nowrap">YTD</th>
                  <th class="py-2.5 px-3 text-right font-bold text-navy-dark whitespace-nowrap">Budget YTD</th>
                  <th class="py-2.5 px-3 text-right font-bold text-navy-dark whitespace-nowrap">Variance</th>
                  <th class="py-2.5 px-3 text-right font-bold text-navy-dark whitespace-nowrap">Total Budget</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in visibleRows" :key="r.ledger_id ?? r.code" class="border-b border-border/60 hover:bg-muted/30">
                  <td class="py-2 px-3">
                    <span class="font-mono text-xs text-muted-foreground">{{ r.code }}</span>
                    <span class="ml-2 text-foreground">{{ r.name }}</span>
                  </td>
                  <td v-for="(m, i) in reportMonths" :key="i" class="py-2 px-3 text-right tabular-nums">{{ fmt(r.months?.[i]) }}</td>
                  <td class="py-2 px-3 text-right tabular-nums">{{ fmt(r.ytd) }}</td>
                  <td class="py-2 px-3 text-right tabular-nums">{{ fmt(r.budget_ytd) }}</td>
                  <td class="py-2 px-3 text-right tabular-nums">{{ fmt(r.variance) }}</td>
                  <td class="py-2 px-3 text-right tabular-nums">{{ fmt(r.total_budget) }}</td>
                </tr>
                <tr v-if="!visibleRows.length">
                  <td :colspan="reportMonths.length + 5" class="py-10 text-center text-sm text-muted-foreground">
                    No reserve fund budget data to display for this period.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Setup sub-tab ───────────────────────────────────────────────── -->
    <BudgetTab v-else :year="year" fund="reserve" />
  </div>
</template>
