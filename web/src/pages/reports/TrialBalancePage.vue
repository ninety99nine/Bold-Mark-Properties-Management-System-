<!--
  Trial Balance — WeConnectU GL report clone (Bold Mark branding).

  Financial Year selector + Ageing/As-at date + Hide Zero toggle, a green
  Download Excel button, and the two-column Debit/Credit table with a totals
  row that must balance.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import ReportFyPicker from '@/components/reports/ReportFyPicker.vue'
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
const rows = ref([])
const totals = ref({})

function endOfCurrentMonth() {
  const d = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const asAt = ref(endOfCurrentMonth())
const financialYear = ref(null)
const hideZero = ref(false)

function buildParams() {
  const p = { as_at: asAt.value }
  if (financialYear.value) p.financial_year = financialYear.value
  if (hideZero.value) p.hide_zero = true
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/trial-balance`, { params: buildParams() })
    rows.value = data.rows ?? []
    totals.value = data.totals ?? {}
  } catch {
    error.value = 'Failed to load the trial balance. Please try again.'
  } finally {
    loading.value = false
  }
}

function onPeriod(p) {
  financialYear.value = p.year
  if (p.end) asAt.value = p.end
  fetchData()
}

async function downloadExcel() {
  try {
    await downloadReportExcel(
      `/communities/${communityId.value}/reports/trial-balance`,
      buildParams(),
      `trial balance-${(communityName.value || 'community').toLowerCase()}-${asAt.value}.xlsx`,
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
watch(communityId, () => { rows.value = []; totals.value = {}; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the trial balance.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <!-- Header band -->
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Trial Balance</h1>

        <div class="mt-4">
          <ReportFyPicker :community-id="communityId" @select="onPeriod" />
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">As at:</label>
            <input v-model="asAt" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
          </div>
          <label class="flex cursor-pointer items-center gap-2 pb-3 text-sm select-none">
            <input v-model="hideZero" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" @change="fetchData" />
            Hide Zero Values
          </label>
        </div>
      </div>

      <!-- Table card -->
      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <div class="mb-4 flex justify-end">
          <DownloadExcelButton :disabled="loading || !rows.length" @click="downloadExcel" />
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-[#eef1f5]">
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Code</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Account</th>
                <th class="border border-border px-3 py-2.5 text-left text-[13px] font-bold text-navy-dark">Category</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Debit</th>
                <th class="border border-border px-3 py-2.5 text-right text-[13px] font-bold text-navy-dark">Credit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td colspan="5" class="border border-border px-3 py-10 text-center text-muted-foreground">Loading…</td></tr>
              <tr v-else-if="rows.length === 0"><td colspan="5" class="border border-border px-3 py-10 text-center text-muted-foreground">No accounts match the current filters.</td></tr>
              <template v-else>
                <tr v-for="row in rows" :key="row.ledger_id" class="hover:bg-muted/30">
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ row.code }}</td>
                  <td class="border border-border px-3 py-2 text-navy-dark">{{ row.name }}</td>
                  <td class="border border-border px-3 py-2 text-muted-foreground">{{ row.financial_category }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(row.debit) }}</td>
                  <td class="border border-border px-3 py-2 text-right tabular-nums text-navy-dark">{{ fmtMoney(row.credit) }}</td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="!loading && rows.length">
              <tr class="bg-[#eef1f5] font-bold text-navy-dark">
                <td class="border border-border px-3 py-2.5" colspan="3">Totals</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.debit) }}</td>
                <td class="border border-border px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(totals.credit) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
