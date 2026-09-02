<!--
  Income Statement — WeConnectU GL report clone (Bold Mark branding).

  Reused for both the Main fund and the Reserve Fund via the `fund` prop
  (falls back to the ?fund= query). Financial Year selector + Date from/to,
  a green Download Excel, grouped Income / Expenses sections with per-group
  subtotals and a Net Surplus / (Deficit) footer.
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
const title = computed(() => isReserve.value ? 'Reserve Fund Income Statement' : 'Income Statement')

const loading = ref(false)
const error = ref(null)
const income = ref([])
const expenses = ref([])
const totals = ref({})

function firstOfYear() {
  const d = new Date()
  return `${d.getFullYear()}-01-01`
}
function endOfMonth() {
  const d = new Date(); const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const dateFrom = ref(firstOfYear())
const dateTo = ref(endOfMonth())
const financialYear = ref(null)

function buildParams() {
  const p = { fund: fund.value, from: dateFrom.value, to: dateTo.value }
  if (financialYear.value) p.financial_year = financialYear.value
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/income-statement`, { params: buildParams() })
    income.value = data.income ?? []
    expenses.value = data.expenses ?? []
    totals.value = data.totals ?? {}
  } catch {
    error.value = 'Failed to load the income statement. Please try again.'
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
      `/communities/${communityId.value}/reports/income-statement`,
      buildParams(),
      `${title.value.toLowerCase()}-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`,
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
      Select a community to view the income statement.
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
        </div>
      </div>

      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <div class="mb-4 flex justify-end">
          <DownloadExcelButton :disabled="loading || !hasData" @click="downloadExcel" />
        </div>

        <div v-if="loading" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">Loading…</div>
        <div v-else-if="!hasData" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">No income or expenses for this period.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Code</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Account</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Actual</th>
              </tr>
            </thead>
            <tbody>
              <!-- Income -->
              <tr class="bg-[#f4f5f7] font-bold text-navy-dark"><td class="border border-border px-3 py-2" colspan="3">Income</td></tr>
              <template v-for="grp in income" :key="'inc-' + grp.code">
                <tr class="font-medium text-navy-dark"><td class="border border-border px-3 py-2" colspan="3">{{ grp.code }} {{ grp.name }}</td></tr>
                <tr v-for="acc in grp.accounts" :key="'inc-a-' + acc.code">
                  <td class="border border-border px-3 py-2 pl-8 text-navy-dark">{{ acc.code }}</td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ acc.name }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(acc.actual) }}</td>
                </tr>
                <tr class="bg-[#fafbfc] font-semibold text-navy-dark">
                  <td class="border border-border px-3 py-2" colspan="2">Subtotal — {{ grp.name }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal) }}</td>
                </tr>
              </template>
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5" colspan="2">Total Income</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.income) }}</td>
              </tr>

              <!-- Expenses -->
              <tr class="bg-[#f4f5f7] font-bold text-navy-dark"><td class="border border-border px-3 py-2" colspan="3">Expenses</td></tr>
              <template v-for="grp in expenses" :key="'exp-' + grp.code">
                <tr class="font-medium text-navy-dark"><td class="border border-border px-3 py-2" colspan="3">{{ grp.code }} {{ grp.name }}</td></tr>
                <tr v-for="acc in grp.accounts" :key="'exp-a-' + acc.code">
                  <td class="border border-border px-3 py-2 pl-8 text-navy-dark">{{ acc.code }}</td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ acc.name }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(acc.actual) }}</td>
                </tr>
                <tr class="bg-[#fafbfc] font-semibold text-navy-dark">
                  <td class="border border-border px-3 py-2" colspan="2">Subtotal — {{ grp.name }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums">{{ fmtMoney(grp.subtotal) }}</td>
                </tr>
              </template>
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5" colspan="2">Total Expenses</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.expense) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-navy font-bold text-white">
                <td class="border border-border px-3 py-3" colspan="2">Net Surplus / (Deficit)</td>
                <td class="border border-border px-3 py-3 text-right tabular-nums">{{ fmtMoney(totals.net) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
