<!--
  Actual vs Budget — WeConnectU GL report clone (Bold Mark branding).

  Reused for both the Main fund and the Reserve Fund via the `fund` prop
  (falls back to the ?fund= query). Toolbar mirrors WeConnectU: Date from/to,
  Hide Zero Values, Add Monthly Budget, Add Monthly Variance, and a green
  Download Excel. Grouped Income / Expenses with YTD Actual / YTD Budget /
  Variance / Total Budget, optionally expanded to a Jan–Dec monthly matrix.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import ReportFyPicker from '@/components/reports/ReportFyPicker.vue'
import DownloadExcelButton from '@/components/reports/DownloadExcelButton.vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { fmtMoney, downloadReportExcel } from '@/composables/useReport'
import { useCommunityStore } from '@/stores/community'

const props = defineProps({
  fund: { type: String, default: null },
})

const router = useRouter()
const route = useRoute()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')
const fund = computed(() => props.fund || route.query.fund || 'main')
const isReserve = computed(() => fund.value === 'reserve')
const title = computed(() => isReserve.value ? 'Reserve Fund Actual vs Budget' : 'Actual vs Budget')

const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const loading = ref(false)
const error = ref(null)
const income = ref([])
const expenses = ref([])
const totals = ref({})
const year = ref(null)

function firstOfYear() {
  const d = new Date(); return `${d.getFullYear()}-01-01`
}
function endOfMonth() {
  const d = new Date(); const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const dateFrom = ref(firstOfYear())
const dateTo = ref(endOfMonth())
const financialYear = ref(null)
const hideZero = ref(false)
const monthlyBudget = ref(false)
const monthlyVariance = ref(false)

// Number of trailing (non-month) columns shown per row.
const summaryCols = 4
const monthCount = computed(() => {
  let n = 0
  if (monthlyBudget.value) n += 12
  if (monthlyVariance.value) n += 12
  return n
})
const totalCols = computed(() => 2 + monthCount.value + summaryCols)

function buildParams() {
  const p = { fund: fund.value }
  if (financialYear.value) p.financial_year = financialYear.value
  if (hideZero.value) p.hide_zero = true
  if (monthlyBudget.value) p.monthly_budget = true
  if (monthlyVariance.value) p.monthly_variance = true
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/actual-vs-budget`, { params: buildParams() })
    income.value = data.income ?? []
    expenses.value = data.expenses ?? []
    totals.value = data.totals ?? {}
    year.value = data.year ?? null
  } catch {
    error.value = 'Failed to load the actual vs budget report. Please try again.'
  } finally {
    loading.value = false
  }
}

function onPeriod(p) {
  financialYear.value = p.year
  if (p.start) dateFrom.value = p.start
  if (p.end) dateTo.value = p.end
  fetchData()
}

async function downloadExcel() {
  try {
    await downloadReportExcel(
      `/communities/${communityId.value}/reports/actual-vs-budget`,
      buildParams(),
      `${title.value.toLowerCase()}-${(communityName.value || 'community').toLowerCase()}-${year.value || dateTo.value}.xlsx`,
    )
  } catch {
    toastError('Could not download the Excel file.')
  }
}

const hasData = computed(() => income.value.length || expenses.value.length)

function boot() {
  if (!communityId.value) return
  fetchData()
}
onMounted(() => { if (!communityStore.loaded) communityStore.fetch(); boot() })
watch(communityId, () => { income.value = []; expenses.value = []; totals.value = {}; boot() })
watch(fund, () => { boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the actual vs budget report.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">{{ title }}</h1>

        <div class="mt-4">
          <ReportFyPicker :community-id="communityId" @select="onPeriod" />
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date from:</label>
            <input v-model="dateFrom" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date to:</label>
            <input v-model="dateTo" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
          </div>
          <div class="flex flex-wrap items-center gap-4 pb-3 text-sm">
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Hide Zero Values
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="monthlyBudget" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Add Monthly Budget
            </label>
            <label class="flex cursor-pointer items-center gap-2 select-none">
              <input v-model="monthlyVariance" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
              Add Monthly Variance
            </label>
          </div>
        </div>
      </div>

      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <div class="mb-4 flex justify-end">
          <DownloadExcelButton :disabled="loading || !hasData" @click="downloadExcel" />
        </div>

        <div v-if="loading" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">Loading…</div>
        <div v-else-if="!hasData" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">No data for this period.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Code</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Account</th>
                <template v-if="monthlyBudget">
                  <th v-for="m in MONTH_LABELS" :key="'hb-' + m" class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">{{ m }} B</th>
                </template>
                <template v-if="monthlyVariance">
                  <th v-for="m in MONTH_LABELS" :key="'hv-' + m" class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">{{ m }} V</th>
                </template>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">YTD Actual</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">YTD Budget</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Variance</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Total Budget</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(section, si) in [{ label: 'Income', groups: income, total: totals.income }, { label: 'Expenses', groups: expenses, total: totals.expense }]" :key="'sec-' + si">
                <tr class="bg-[#f4f5f7] font-bold text-navy-dark"><td class="border border-border px-3 py-2" :colspan="totalCols">{{ section.label }}</td></tr>
                <template v-for="grp in section.groups" :key="section.label + '-' + grp.code">
                  <tr class="font-medium text-navy-dark"><td class="border border-border px-3 py-2" :colspan="totalCols">{{ grp.code }} {{ grp.name }}</td></tr>
                  <tr v-for="acc in grp.accounts" :key="section.label + '-a-' + acc.code">
                    <td class="border border-border px-3 py-2 pl-8 text-navy-dark">{{ acc.code }}</td>
                    <td class="border border-border px-3 py-2 text-navy-dark">{{ acc.name }}</td>
                    <template v-if="monthlyBudget">
                      <td v-for="m in MONTHS" :key="'b-' + m" class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(acc.budget?.[m]) }}</td>
                    </template>
                    <template v-if="monthlyVariance">
                      <td v-for="m in MONTHS" :key="'v-' + m" class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney((acc.actual?.[m] || 0) - (acc.budget?.[m] || 0)) }}</td>
                    </template>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(acc.ytd_actual) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(acc.ytd_budget) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums" :class="Number(acc.variance) < 0 ? 'text-destructive' : ''">{{ fmtMoney(acc.variance) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(acc.total_budget) }}</td>
                  </tr>
                  <tr class="bg-[#fafbfc] font-semibold text-navy-dark">
                    <td class="border border-border px-3 py-2" :colspan="2 + monthCount">Subtotal — {{ grp.name }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal?.ytd_actual) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal?.ytd_budget) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal?.variance) }}</td>
                    <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal?.total_budget) }}</td>
                  </tr>
                </template>
                <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                  <td class="border border-border px-3 py-2.5" :colspan="2 + monthCount">Total {{ section.label }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(section.total?.ytd_actual) }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(section.total?.ytd_budget) }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(section.total?.variance) }}</td>
                  <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(section.total?.total_budget) }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
