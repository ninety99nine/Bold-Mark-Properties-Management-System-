<!--
  Basic Cash Movement — WeConnectU GL report clone (Bold Mark branding).

  Date from/to filter, a green Download Excel, and a per-bank table with
  Opening / Receipts / Payments / Closing and a totals row.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import DownloadExcelButton from '@/components/reports/DownloadExcelButton.vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { fmtMoney, downloadReportExcel } from '@/composables/useReport'
import { useCommunityStore } from '@/stores/community'

const router = useRouter()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)
const communityName = computed(() => communityStore.selected?.name ?? '')

const loading = ref(false)
const error = ref(null)
const banks = ref([])
const totals = ref({})

function firstOfMonthsAgo(n) {
  const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}
function endOfMonth() {
  const d = new Date(); const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const dateFrom = ref(firstOfMonthsAgo(2))
const dateTo = ref(endOfMonth())

function buildParams() {
  return { from: dateFrom.value, to: dateTo.value }
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/cash-movement`, { params: buildParams() })
    banks.value = data.banks ?? []
    totals.value = data.totals ?? {}
  } catch {
    error.value = 'Failed to load the cash movement report. Please try again.'
  } finally {
    loading.value = false
  }
}

async function downloadExcel() {
  try {
    await downloadReportExcel(
      `/communities/${communityId.value}/reports/cash-movement`,
      buildParams(),
      `basic cash movement-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`,
    )
  } catch {
    toastError('Could not download the Excel file.')
  }
}

function boot() {
  if (!communityId.value) return
  fetchData()
}
onMounted(() => { if (!communityStore.loaded) communityStore.fetch(); boot() })
watch(communityId, () => { banks.value = []; totals.value = {}; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the cash movement report.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Basic Cash Movement Report</h1>

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
          <DownloadExcelButton :disabled="loading || !banks.length" @click="downloadExcel" />
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Code</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Bank Account</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Opening</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Receipts</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Payments</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Closing</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td colspan="6" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td></tr>
              <tr v-else-if="banks.length === 0"><td colspan="6" class="border border-border px-3 py-10 text-center text-muted-foreground">No bank accounts match the current filters.</td></tr>
              <template v-else>
                <tr v-for="b in banks" :key="b.ledger_id" class="hover:bg-muted/30">
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ b.code }}</td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ b.name }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(b.opening) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(b.receipts) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(b.payments) }}</td>
                  <td class="border border-border px-3 py-2 text-right font-semibold tabular-nums text-navy-dark">{{ fmtMoney(b.closing) }}</td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="!loading && banks.length">
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5" colspan="2">Totals</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.opening) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.receipts) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.payments) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.closing) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
