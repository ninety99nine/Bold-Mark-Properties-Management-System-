<!--
  Detailed General Ledger — WeConnectU GL report clone (Bold Mark branding).

  Financial Year selector + Date from/to + optional Account selector, a green
  Download Excel button, and one collapsible section per account with an
  opening balance, transaction lines (running balance) and a closing total.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
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
const accounts = ref([])
const collapsed = ref(new Set())

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
const ledgerId = ref('')

const ledgerOptions = ref([{ value: '', label: 'All accounts' }])

function buildParams() {
  const p = { from: dateFrom.value, to: dateTo.value }
  if (ledgerId.value) p.ledger_id = ledgerId.value
  return p
}

async function fetchData() {
  if (!communityId.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/communities/${communityId.value}/reports/general-ledger`, { params: buildParams() })
    accounts.value = data.accounts ?? []
    collapsed.value = new Set()
    // Populate the account selector once from the full (unfiltered) result set.
    if (!ledgerId.value && accounts.value.length) {
      ledgerOptions.value = [
        { value: '', label: 'All accounts' },
        ...accounts.value.map(a => ({ value: a.ledger.id, label: `${a.ledger.code} - ${a.ledger.name}` })),
      ]
    }
  } catch {
    error.value = 'Failed to load the general ledger. Please try again.'
  } finally {
    loading.value = false
  }
}

function onPeriod(p) {
  if (p.start) dateFrom.value = p.start
  if (p.end) dateTo.value = p.end
  fetchData()
}

function toggle(id) {
  const s = new Set(collapsed.value)
  s.has(id) ? s.delete(id) : s.add(id)
  collapsed.value = s
}

async function downloadExcel() {
  try {
    await downloadReportExcel(
      `/communities/${communityId.value}/reports/general-ledger`,
      buildParams(),
      `detailed general ledger-${(communityName.value || 'community').toLowerCase()}-${dateFrom.value} to ${dateTo.value}.xlsx`,
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
watch(communityId, () => { accounts.value = []; ledgerId.value = ''; ledgerOptions.value = [{ value: '', label: 'All accounts' }]; boot() })
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view the general ledger.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <div class="rounded-t-lg border border-border bg-[#eef1f5] px-6 py-5">
        <h1 class="font-body text-2xl font-bold text-navy-dark">Detailed General Ledger</h1>

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
          <div class="w-64">
            <label class="mb-1 block text-xs font-bold text-muted-foreground">Account:</label>
            <AppSelect v-model="ledgerId" :options="ledgerOptions" placeholder="All accounts" @change="fetchData" />
          </div>
        </div>
      </div>

      <div class="rounded-b-lg border border-t-0 border-border bg-white p-5">
        <div class="mb-4 flex justify-end">
          <DownloadExcelButton :disabled="loading || !accounts.length" @click="downloadExcel" />
        </div>

        <div v-if="loading" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">Loading…</div>
        <div v-else-if="!accounts.length" class="rounded-lg border border-border bg-white p-10 text-center text-muted-foreground">No transactions match the current filters.</div>

        <div v-for="acc in accounts" v-else :key="acc.ledger.id" class="mb-6">
          <h3 class="mb-2 flex items-center gap-2 text-lg font-bold text-navy-dark">
            {{ acc.ledger.code }} - {{ acc.ledger.name }}
            <button type="button" class="text-[#2f8fe0]" @click="toggle(acc.ledger.id)">
              <svg class="h-5 w-5 transition-transform" :class="collapsed.has(acc.ledger.id) ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
            </button>
          </h3>
          <div v-show="!collapsed.has(acc.ledger.id)" class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-[#f4f5f7] text-left">
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Date</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Source</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Reference</th>
                  <th class="border-b border-border px-3 py-2.5 font-bold text-navy-dark">Description</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Debit</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Credit</th>
                  <th class="border-b border-border px-3 py-2.5 text-right font-bold text-navy-dark">Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-border bg-[#fafbfc] font-medium text-navy-dark">
                  <td class="px-3 py-2" colspan="6">Opening balance</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmtMoney(acc.opening) }}</td>
                </tr>
                <tr v-for="(r, i) in acc.transactions" :key="i" class="border-b border-border">
                  <td class="px-3 py-2 whitespace-nowrap">{{ r.date }}</td>
                  <td class="px-3 py-2">{{ r.source }}</td>
                  <td class="px-3 py-2">{{ r.reference }}</td>
                  <td class="px-3 py-2">{{ r.description }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmtMoney(r.debit) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmtMoney(r.credit) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ fmtMoney(r.balance) }}</td>
                </tr>
                <tr v-if="!acc.transactions?.length" class="border-b border-border">
                  <td class="px-3 py-4 text-center text-muted-foreground" colspan="7">No transactions in this period.</td>
                </tr>
                <tr class="bg-[#f9fafb] font-bold text-navy-dark">
                  <td class="px-3 py-2.5" colspan="4">Closing balance</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(acc.totals?.debit) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(acc.totals?.credit) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ fmtMoney(acc.closing) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
      </div>
    </template>
  </div>
</template>
