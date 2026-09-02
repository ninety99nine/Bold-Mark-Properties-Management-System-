<!--
  VAT 201 — WeConnectU GL report clone (Bold Mark branding).

  Date from/to filter, a green Download Excel, and summary blocks for Output
  VAT, Input VAT and the Net VAT payable / refundable (direction-aware).
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
const report = ref(null)

function firstOfMonthsAgo(n) {
  const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}
function endOfMonth() {
  const d = new Date(); const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`
}
const dateFrom = ref(firstOfMonthsAgo(1))
const dateTo = ref(endOfMonth())

const isRefundable = computed(() => (report.value?.direction || '').toLowerCase() === 'refundable')

function buildParams() {
  return { from: dateFrom.value, to: dateTo.value }
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/vat-201`, { params: buildParams() })
    report.value = data
  } catch {
    error.value = 'Failed to load the VAT 201 report. Please try again.'
  } finally {
    loading.value = false
  }
}

async function downloadExcel() {
  try {
    await downloadReportExcel(
      `/communities/${communityId.value}/reports/vat-201`,
      buildParams(),
      `vat 201-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`,
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
watch(communityId, () => { report.value = null; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the VAT 201 report.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">VAT 201 Report</h1>

        <div class="mt-4 flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date from:</label>
            <input v-model="dateFrom" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Date to:</label>
            <input v-model="dateTo" type="date" class="h-11 rounded-md border border-border bg-white px-3 text-sm focus:border-navy focus:outline-none" @change="fetchData" />
          </div>
          <div class="pb-1">
            <DownloadExcelButton :disabled="loading || !report" @click="downloadExcel" />
          </div>
        </div>
      </div>

      <div class="rounded-b-lg border border-t-0 border-border bg-white p-6">
        <div v-if="loading" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">Loading…</div>
        <div v-else-if="!report" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">No VAT data for this period.</div>

        <template v-else>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-border bg-[#f9fafb] p-5">
              <div class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Output VAT</div>
              <div class="mt-2 text-2xl font-bold tabular-nums text-navy-dark">{{ fmtMoney(report.output_vat) }}</div>
            </div>
            <div class="rounded-lg border border-border bg-[#f9fafb] p-5">
              <div class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Input VAT</div>
              <div class="mt-2 text-2xl font-bold tabular-nums text-navy-dark">{{ fmtMoney(report.input_vat) }}</div>
            </div>
            <div class="rounded-lg border p-5" :class="isRefundable ? 'border-green-200 bg-green-50' : 'border-navy/20 bg-navy text-white'">
              <div class="text-xs font-bold uppercase tracking-wide" :class="isRefundable ? 'text-green-700' : 'text-white/70'">
                Net VAT {{ isRefundable ? 'Refundable' : 'Payable' }}
              </div>
              <div class="mt-2 text-2xl font-bold tabular-nums" :class="isRefundable ? 'text-green-700' : 'text-white'">{{ fmtMoney(report.net_vat) }}</div>
            </div>
          </div>

          <p v-if="report.note" class="mt-6 flex items-start gap-3 rounded-lg border border-[#bcdcff] bg-[#eaf3ff] p-4 text-sm text-foreground">
            <span class="font-bold">Please note</span>
            {{ report.note }}
          </p>

          <p class="mt-4 text-sm text-muted-foreground">Period: {{ report.from }} to {{ report.to }}</p>
        </template>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
