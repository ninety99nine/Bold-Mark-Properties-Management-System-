<script setup>
/**
 * Cashbook Manual Transactions — WeConnectU parity.
 *
 * Capture one or more income/expense lines against a bank account and upload
 * them into the cashbook. Reached from the Cashbook "Cashbook Options →
 * Manual Transactions" menu (or "Capture Transactions Manually" on upload).
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useToast } from '@/composables/useToast'
import AppButton     from '@/components/common/AppButton.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'

const route          = useRoute()
const router         = useRouter()
const communityStore = useCommunityStore()
const { success } = useToast()

const communityId  = computed(() => communityStore.selectedId)
const bankAccountId = ref(route.query.bank_account_id ? String(route.query.bank_account_id) : '')
const bankAccount  = ref(null)

// ── Financial year label (display only) ───────────────────────────────────
const periods           = ref([])
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.is_current)?.label ?? '',
)

function today() {
  return new Date().toISOString().slice(0, 10)
}

// ── Lines ─────────────────────────────────────────────────────────────────
function blankLine() {
  return { date: today(), description: '', amount: '0.00', type: 'income' }
}
const lines = ref([blankLine()])

function addLine() { lines.value.push(blankLine()) }
function removeLine(idx) {
  lines.value.splice(idx, 1)
  if (!lines.value.length) lines.value.push(blankLine())
}

const total = computed(() =>
  lines.value.reduce((sum, l) => sum + (Number(l.amount) || 0), 0),
)

function fmtNum(v) {
  const num = Number(v || 0)
  const [i, d] = Math.abs(num).toFixed(2).split('.')
  const s = i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  return (num < 0 ? '-' : '') + s + '.' + d
}

// ── Validation banners (WeConnectU) ───────────────────────────────────────
const banner = ref(null)   // 'incomplete' | 'bank' | null
const invalid = ref(new Set())
const submitting = ref(false)

function isInvalid(idx) { return invalid.value.has(idx) }

// ── Load bank account name ────────────────────────────────────────────────
async function loadBankAccount() {
  if (!communityId.value) return
  try {
    const { data } = await api.get('/bank-accounts', { params: { community_id: communityId.value } })
    const list = data.data ?? data ?? []
    bankAccount.value = list.find(b => b.id === bankAccountId.value) ?? null
  } catch { /* silent */ }
}

async function loadPeriods() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/financial-years`)
    periods.value = data.periods ?? []
  } catch { /* silent */ }
}

onMounted(() => {
  loadBankAccount()
  loadPeriods()
})

const bankTitle = computed(() => {
  const b = bankAccount.value
  if (!b) return ''
  return `${(b.bank_name || b.name || 'Bank')}${b.account_number ? ` ${b.account_number}` : ''}`
})

// ── Submit ────────────────────────────────────────────────────────────────
async function upload() {
  if (submitting.value) return
  banner.value = null
  invalid.value = new Set()

  // Client-side: flag lines missing a description or amount.
  lines.value.forEach((l, idx) => {
    if (!l.description.trim() || !(Number(l.amount) > 0)) invalid.value.add(idx)
  })
  if (!bankAccountId.value) { banner.value = 'bank'; return }
  if (invalid.value.size)  { banner.value = 'incomplete'; return }

  submitting.value = true
  try {
    await api.post(`/communities/${communityId.value}/cashbook/manual-transactions`, {
      bank_account_id: bankAccountId.value,
      transactions: lines.value.map(l => ({
        date:        l.date,
        description: l.description.trim(),
        amount:      Number(l.amount),
        type:        l.type,
      })),
    })
    success('Transactions uploaded.')
    router.push('/cashbook')
  } catch (e) {
    const errors = e?.response?.data?.errors ?? {}
    if (errors.bank_account_id) {
      banner.value = 'bank'
    } else {
      banner.value = 'incomplete'
      Object.keys(errors).forEach(key => {
        const m = key.match(/^transactions\.(\d+)\./)
        if (m) invalid.value.add(Number(m[1]))
      })
    }
  } finally {
    submitting.value = false
  }
}

function cancel() { router.push('/cashbook') }
</script>

<template>
  <div class="pb-10">

    <!-- Validation banners -->
    <div v-if="banner === 'incomplete'" class="mb-5 flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-destructive shrink-0"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1 5h2v6h-2V7Zm0 8h2v2h-2v-2Z"/></svg>
      <div>
        <p class="text-sm font-bold text-foreground">Form incomplete</p>
        <p class="text-sm text-muted-foreground">Please complete the required information. Look out for the boxes encircled in red!</p>
      </div>
    </div>
    <div v-if="banner === 'bank'" class="mb-5 flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-destructive shrink-0"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1 5h2v6h-2V7Zm0 8h2v2h-2v-2Z"/></svg>
      <div>
        <p class="text-sm font-bold text-foreground">Bank account not selected</p>
        <p class="text-sm text-muted-foreground">The bank account was not selected for this cashbook. Please try again.</p>
      </div>
    </div>

    <h1 class="font-body font-normal text-2xl text-muted-foreground mb-4">Cashbook Manual Transactions</h1>

    <div v-if="selectedPeriodLabel" class="mb-6">
      <label class="block text-sm font-bold text-foreground mb-1.5">Financial Year / Budget Period:</label>
      <div class="flex h-11 max-w-md items-center rounded-md border border-border bg-white px-3 text-sm text-foreground">
        {{ selectedPeriodLabel }}
      </div>
    </div>

    <!-- Capture card -->
    <div class="rounded-lg border border-border bg-card shadow-sm p-6">
      <h2 class="font-body text-xl text-foreground mb-5">
        Upload Transactions for <span class="font-bold">{{ bankTitle }}</span>
      </h2>

      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-border">
          <thead>
            <tr class="border-b border-border bg-muted/40">
              <th class="text-left py-3 px-4 text-sm font-bold text-foreground w-44">Date</th>
              <th class="text-left py-3 px-4 text-sm font-bold text-foreground">Description</th>
              <th class="text-left py-3 px-4 text-sm font-bold text-foreground w-44">Amount</th>
              <th class="text-center py-3 px-4 text-sm font-bold text-foreground w-24">Income</th>
              <th class="text-center py-3 px-4 text-sm font-bold text-foreground w-28">Expense</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(l, idx) in lines" :key="idx" class="border-b border-border">
              <td class="py-2 px-4 align-top">
                <AppDatePicker v-model="l.date" mode="date" :class="isInvalid(idx) ? 'ring-1 ring-destructive rounded-md' : ''" />
              </td>
              <td class="py-2 px-4 align-top">
                <input
                  v-model="l.description"
                  type="text"
                  class="h-11 w-full rounded-md border bg-white px-3 text-sm text-foreground"
                  :class="isInvalid(idx) && !l.description.trim() ? 'border-destructive ring-1 ring-destructive' : 'border-border'"
                />
              </td>
              <td class="py-2 px-4 align-top">
                <input
                  v-model="l.amount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="h-11 w-full rounded-md border bg-white px-3 text-sm text-foreground text-right"
                  :class="isInvalid(idx) && !(Number(l.amount) > 0) ? 'border-destructive ring-1 ring-destructive' : 'border-border'"
                />
              </td>
              <td class="py-2 px-4 text-center align-middle">
                <input type="radio" :value="'income'" v-model="l.type" class="h-4 w-4 accent-primary" />
              </td>
              <td class="py-2 px-4 text-center align-middle">
                <input type="radio" :value="'expense'" v-model="l.type" class="h-4 w-4 accent-primary" />
                <button type="button" class="mt-1 flex items-center justify-center gap-1 w-full text-xs text-destructive hover:underline" @click="removeLine(idx)">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm3.5 12.1-1.4 1.4L12 13.4l-2.1 2.1-1.4-1.4L10.6 12 8.5 9.9l1.4-1.4L12 10.6l2.1-2.1 1.4 1.4L13.4 12l2.1 2.1Z"/></svg>
                  Remove
                </button>
              </td>
            </tr>
            <!-- Add line + total -->
            <tr>
              <td class="py-3 px-4">
                <button type="button" class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline" @click="addLine">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 11h4v-2h-4V7h-2v4H7v2h4v4h2v-4Z"/></svg>
                  Add Line
                </button>
              </td>
              <td></td>
              <td class="py-3 px-4 text-right text-sm font-bold text-foreground">{{ fmtNum(total) }}</td>
              <td></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end gap-3 mt-5">
        <AppButton variant="danger" @click="cancel">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm3.5 12.1-1.4 1.4L12 13.4l-2.1 2.1-1.4-1.4L10.6 12 8.5 9.9l1.4-1.4L12 10.6l2.1-2.1 1.4 1.4L13.4 12l2.1 2.1Z"/></svg>
          Cancel
        </AppButton>
        <AppButton variant="primary" :disabled="submitting" @click="upload">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/></svg>
          {{ submitting ? 'Uploading...' : 'Upload Transactions' }}
        </AppButton>
      </div>
    </div>
  </div>
</template>
