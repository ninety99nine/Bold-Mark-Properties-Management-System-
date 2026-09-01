<!--
  Customer Notices — strict WeConnectU "Legal Notices for {date}" batch view.

  Reached from the Age Analysis "View last notice batch" link. Shows a completed
  notice batch grouped by level (1st Notices / 2nd Notices / Final Notices /
  Letters of Demand), each row with the customer, who it was Sent to, the notice
  Charge (with a [CREDIT] action that raises a credit note), the Balance and the
  Customer Type. "View previous notice" walks back through earlier batches.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppModal  from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import api       from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { success, error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)

const loading = ref(true)
const batch   = ref(null)
// Batches we've stepped back from, so "View next notice" can walk forward again.
const forwardStack = ref([])

// ── Credit-note modal ────────────────────────────────────────────────────
const creditOpen       = ref(false)
const creditRow        = ref(null)
const creditReason     = ref('')
const creditDateOption = ref('invoice') // 'today' | 'invoice'
const creditSubmitting = ref(false)

// ── Currency (WeConnectU: "R 1 984.85") ───────────────────────────────────
function money(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}
const charge  = (v) => 'R' + money(v)      // "R3.45"
const balance = (v) => 'R ' + money(v)     // "R 1 984.85"

// ── Load ───────────────────────────────────────────────────────────────────
async function loadLast() {
  if (!communityId.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/age-analysis/notices/last-batch`)
    batch.value = data.batch
    forwardStack.value = []
  } catch (e) {
    toastError('Could not load the notice batch.')
  } finally {
    loading.value = false
  }
}

async function loadBatch(id) {
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/age-analysis/notices/${id}`)
    batch.value = data.batch
    window.scrollTo({ top: 0 })
  } catch (e) {
    toastError('Could not load that notice batch.')
  } finally {
    loading.value = false
  }
}

function viewPrevious() {
  if (batch.value?.previous_batch_id) {
    forwardStack.value.push(batch.value.id)
    loadBatch(batch.value.previous_batch_id)
  }
}

function viewNext() {
  const id = forwardStack.value.pop()
  if (id) loadBatch(id)
}

function backToAgeAnalysis() {
  router.push({ name: 'age-analysis' })
}

// ── Credit note ──────────────────────────────────────────────────────────────
function openCredit(row) {
  if (row.credited) return
  creditRow.value        = row
  creditReason.value     = ''
  creditDateOption.value = 'invoice'
  creditOpen.value       = true
}

async function submitCredit() {
  if (!creditRow.value) return
  creditSubmitting.value = true
  try {
    await api.post(
      `/communities/${communityId.value}/age-analysis/notices/${batch.value.id}/items/${creditRow.value.id}/credit`,
      { reason: creditReason.value || undefined, date_option: creditDateOption.value },
    )
    success('Credit note created.')
    creditOpen.value = false
    await loadBatch(batch.value.id) // refresh credited state + balances
  } catch (e) {
    toastError(e.response?.data?.message || 'Failed to create credit note.')
  } finally {
    creditSubmitting.value = false
  }
}

onMounted(() => {
  if (!communityStore.loaded) communityStore.fetch()
  loadLast()
})
watch(communityId, loadLast)
</script>

<template>
  <div class="p-6">
    <!-- No community -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view its notices.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <!-- Back: walks up the notice nesting first, then to Age Analysis at the top level -->
      <button
        type="button"
        class="mb-4 inline-flex items-center gap-1 text-sm text-[#2f6fb0] hover:underline"
        @click="forwardStack.length ? viewNext() : backToAgeAnalysis()"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
        {{ forwardStack.length ? 'Back' : 'Back to Age Analysis' }}
      </button>

      <!-- Loading -->
      <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>

      <!-- Empty -->
      <div v-else-if="!batch" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
        No notices have been run for this community yet.
      </div>

      <template v-else>
        <!-- Header -->
        <h1 class="font-body text-3xl font-normal text-[#5b6472]">Legal Notices for {{ batch.ageing_date }}</h1>
        <p class="mt-1 text-sm text-muted-foreground">Run on <span class="font-semibold text-foreground">{{ batch.run_on }}</span></p>
        <div class="mt-0.5 flex items-center gap-4">
          <button
            v-if="batch.previous_batch_id"
            type="button"
            class="text-sm text-[#2f6fb0] hover:underline"
            @click="viewPrevious"
          >View previous notice</button>
        </div>

        <!-- Empty batch -->
        <div v-if="!batch.sections.length" class="mt-8 rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
          This batch has no notices.
        </div>

        <!-- Sections -->
        <section v-for="section in batch.sections" :key="section.level" class="mt-8">
          <h2 class="mb-2 text-lg font-semibold text-foreground">{{ section.title }}</h2>

          <div class="overflow-x-auto rounded-lg border border-border bg-white">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border bg-white text-left text-muted-foreground">
                  <th class="px-4 py-3 font-semibold text-navy-dark">Customer</th>
                  <th class="px-4 py-3 font-semibold text-navy-dark">Sent to</th>
                  <th class="px-4 py-3 font-semibold text-navy-dark">Charge</th>
                  <th class="px-4 py-3 font-semibold text-navy-dark">Balance</th>
                  <th class="px-4 py-3 font-semibold text-navy-dark">Customer Type</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, i) in section.rows"
                  :key="row.id"
                  :class="i % 2 ? 'bg-[#f4f5f7]' : 'bg-white'"
                  class="border-b border-border align-top last:border-0"
                >
                  <td class="px-4 py-3 text-foreground">
                    {{ row.customer_code }}<template v-if="row.customer_name && row.customer_name !== '—'"> - {{ row.customer_name }}</template>
                  </td>
                  <td class="px-4 py-3 whitespace-pre-line text-muted-foreground">{{ row.sent_to || '—' }}</td>
                  <td class="px-4 py-3 text-foreground whitespace-nowrap">
                    {{ charge(row.charge) }}
                    <button
                      v-if="!row.credited"
                      type="button"
                      class="ml-1 text-[#2f6fb0] hover:underline"
                      @click="openCredit(row)"
                    >[CREDIT]</button>
                    <span v-else class="ml-1 text-xs font-medium text-muted-foreground">[CREDITED]</span>
                  </td>
                  <td class="px-4 py-3 text-foreground whitespace-nowrap">{{ balance(row.balance) }}</td>
                  <td class="px-4 py-3 capitalize text-muted-foreground">{{ row.customer_type || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </template>

    <!-- Credit-note confirm modal -->
    <AppModal :show="creditOpen" title="Confirm" size="lg" @close="creditOpen = false">
      <div class="space-y-4">
        <p class="text-sm text-foreground">Are you sure you want to create a credit note for this invoice?</p>
        <textarea
          v-model="creditReason"
          rows="5"
          placeholder="Reason for credit note?"
          class="w-full resize-y rounded-md border border-border bg-white px-3 py-2 text-sm outline-none focus:border-navy"
        />
        <div>
          <p class="mb-1 text-sm font-semibold text-foreground">Credit Note Date</p>
          <div class="flex items-center gap-6 text-sm">
            <label class="flex cursor-pointer items-center gap-2">
              <input v-model="creditDateOption" type="radio" value="today" class="h-4 w-4 text-navy focus:ring-navy" />
              Today
            </label>
            <label class="flex cursor-pointer items-center gap-2">
              <input v-model="creditDateOption" type="radio" value="invoice" class="h-4 w-4 text-navy focus:ring-navy" />
              Invoice Date
            </label>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-start gap-2">
          <AppButton variant="secondary" :loading="creditSubmitting" @click="submitCredit">Yes</AppButton>
          <AppButton variant="outline" :disabled="creditSubmitting" @click="creditOpen = false">No</AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
