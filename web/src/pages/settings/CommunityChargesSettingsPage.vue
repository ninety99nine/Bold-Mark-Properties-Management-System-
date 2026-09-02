<!--
  CommunityChargesSettingsPage — WeConnectU "Setup → Charges" clone.

  Notice-charge matrix (1st / 2nd Notice, Letter of Demand — each with email +
  SMS charge, threshold and status), notices exemption, plus the per-community
  fees (phonecall, handed over, notice threshold, warning/penalty admin, transfer
  clearance) and the apply-debt-collection toggle. Community-scoped.

  GET /communities/{id}  ·  PUT /communities/{id}
-->
<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppInput  from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import AppButton from '@/components/common/AppButton.vue'

const { success, error } = useToast()
const community = useCommunityStore()

// ── Option sets (mirror WeConnectU) ─────────────────────────────────────
const NOTICE_ROWS = [
  { key: 'first',            label: '1st Notice Charge' },
  { key: 'second',           label: '2nd Notice Charge' },
  { key: 'letter_of_demand', label: 'Letter of Demand'  },
]
const THRESHOLD_OPTS = [
  { value: 'current', label: 'Current'  },
  { value: '30_days', label: '30+ days' },
  { value: '60_days', label: '60+ days' },
  { value: '90_days', label: '90+ days' },
]
const STATUS_OPTS = [
  { value: '', label: '--' },
]
const EXEMPTION_OPTS = [
  { value: 'debit_order',         label: 'Debit Order'         },
  { value: 'handed_over',         label: 'Handed Over'         },
  { value: 'payment_arrangement', label: 'Payment Arrangement' },
  { value: 'debt_status',         label: 'Debt Status'         },
]

// ── Form state ──────────────────────────────────────────────────────────
function blankNotice() {
  return { email_charge: '', sms_charge: '', threshold: '', status: '' }
}
function blankForm() {
  return {
    notice_charges: {
      first:            blankNotice(),
      second:           blankNotice(),
      letter_of_demand: blankNotice(),
    },
    notices_exemption: [],
    phonecall_fee: '',
    handed_over_fee: '',
    notice_threshold_amount: '',
    warning_admin_fee: '',
    penalty_admin_fee: '',
    apply_debt_collection_fee: false,
    transfer_clearance_fee: '',
  }
}

const form    = ref(blankForm())
const loading = ref(true)
const saving  = ref(false)

// ── Load ────────────────────────────────────────────────────────────────
async function load() {
  const id = community.selectedId
  if (!id) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${id}`)
    apply(data.data ?? data)
  } catch {
    error('Could not load the charges settings. Please refresh.')
  } finally {
    loading.value = false
  }
}

const str = (v) => (v === null || v === undefined ? '' : String(v))

function apply(c) {
  if (!c) return
  const f = blankForm()
  const nc = c.notice_charges ?? {}
  for (const row of NOTICE_ROWS) {
    const src = nc[row.key] ?? {}
    f.notice_charges[row.key] = {
      email_charge: str(src.email_charge),
      sms_charge:   str(src.sms_charge),
      threshold:    src.threshold ?? '',
      status:       src.status ?? '',
    }
  }
  f.notices_exemption          = Array.isArray(c.notices_exemption) ? [...c.notices_exemption] : []
  f.phonecall_fee              = str(c.phonecall_fee)
  f.handed_over_fee            = str(c.handed_over_fee)
  f.notice_threshold_amount    = str(c.notice_threshold_amount)
  f.warning_admin_fee          = str(c.warning_admin_fee)
  f.penalty_admin_fee          = str(c.penalty_admin_fee)
  f.apply_debt_collection_fee  = !!c.apply_debt_collection_fee
  f.transfer_clearance_fee     = str(c.transfer_clearance_fee)
  form.value = f
}

onMounted(load)
watch(() => community.selectedId, load)

// ── Save ────────────────────────────────────────────────────────────────
const num = (v) => (v === '' || v === null || v === undefined ? null : parseFloat(v))

async function save() {
  const id = community.selectedId
  if (!id || saving.value) return
  saving.value = true
  try {
    const notice_charges = {}
    for (const row of NOTICE_ROWS) {
      const n = form.value.notice_charges[row.key]
      notice_charges[row.key] = {
        email_charge: num(n.email_charge),
        sms_charge:   num(n.sms_charge),
        threshold:    n.threshold || null,
        status:       n.status || null,
      }
    }
    const payload = {
      notice_charges,
      notices_exemption:         form.value.notices_exemption,
      phonecall_fee:             num(form.value.phonecall_fee),
      handed_over_fee:           num(form.value.handed_over_fee),
      notice_threshold_amount:   num(form.value.notice_threshold_amount),
      warning_admin_fee:         num(form.value.warning_admin_fee),
      penalty_admin_fee:         num(form.value.penalty_admin_fee),
      apply_debt_collection_fee: !!form.value.apply_debt_collection_fee,
      transfer_clearance_fee:    num(form.value.transfer_clearance_fee),
    }
    const { data } = await api.put(`/communities/${id}`, payload)
    apply(data.data ?? data)
    success('Charges settings saved.')
  } catch (e) {
    error(e.response?.data?.message ?? 'Could not save the charges settings.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- Header / breadcrumb -->
    <div>
      <p class="text-sm text-muted-foreground">
        Setup <span class="mx-1">→</span> <span class="text-foreground font-medium">Charges</span>
      </p>
      <h1 class="font-body font-bold text-2xl text-foreground mt-1">Charges</h1>
      <p class="text-sm text-muted-foreground">
        Notice charges, exemptions and admin fees for this community
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- No community -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to configure its settings.
    </div>

    <div v-else class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="space-y-6">
        <!-- Notice Type matrix -->
        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] border-separate border-spacing-y-2">
            <thead>
              <tr class="text-left text-sm font-semibold text-foreground">
                <th class="w-52 py-2 pr-4">Notice Type</th>
                <th class="py-2 px-2">Email Charge</th>
                <th class="py-2 px-2">SMS Charge</th>
                <th class="py-2 px-2">Threshold</th>
                <th class="py-2 px-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in NOTICE_ROWS" :key="row.key">
                <td class="py-1 pr-4 text-sm text-muted-foreground align-middle">{{ row.label }}</td>
                <td class="py-1 px-2"><AppInput v-model="form.notice_charges[row.key].email_charge" type="number" :min="0" size="sm" placeholder="0.00" /></td>
                <td class="py-1 px-2"><AppInput v-model="form.notice_charges[row.key].sms_charge" type="number" :min="0" size="sm" placeholder="0.00" /></td>
                <td class="py-1 px-2"><AppSelect v-model="form.notice_charges[row.key].threshold" :options="THRESHOLD_OPTS" placeholder="--" /></td>
                <td class="py-1 px-2"><AppSelect v-model="form.notice_charges[row.key].status" :options="STATUS_OPTS" placeholder="--" /></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="divide-y divide-border/60 border-t border-border/60">
          <!-- Notices Exemption -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Notices Exemption</label>
            <AppMultiSelect v-model="form.notices_exemption" heading="Exemptions" :options="EXEMPTION_OPTS" placeholder="Select exemptions..." />
          </div>

          <!-- Phonecall -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Phonecall</label>
            <div class="w-48"><AppInput v-model="form.phonecall_fee" type="number" :min="0" placeholder="0.00" /></div>
          </div>

          <!-- Handed Over -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Handed Over</label>
            <div class="w-48"><AppInput v-model="form.handed_over_fee" type="number" :min="0" placeholder="0.00" /></div>
          </div>

          <!-- Notice Threshold amount -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Notice Threshold amount</label>
            <div class="w-48"><AppInput v-model="form.notice_threshold_amount" type="number" :min="0" placeholder="0.00" /></div>
          </div>

          <!-- Warning Admin Fee -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Warning Admin Fee</label>
            <div class="w-48"><AppInput v-model="form.warning_admin_fee" type="number" :min="0" placeholder="0.00" /></div>
          </div>

          <!-- Penalty Admin Fee -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Penalty Admin Fee</label>
            <div class="w-48"><AppInput v-model="form.penalty_admin_fee" type="number" :min="0" placeholder="0.00" /></div>
          </div>

          <!-- Apply debt collection fee -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Apply debt collection fee:</label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.apply_debt_collection_fee" class="accent-navy w-4 h-4" />
            </label>
          </div>

          <!-- Transfer Clearance Fee -->
          <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
            <label class="text-sm text-muted-foreground">Transfer Clearance Fee</label>
            <div class="w-48"><AppInput v-model="form.transfer_clearance_fee" type="number" :min="0" placeholder="0.00" /></div>
          </div>
        </div>

        <!-- Save -->
        <div class="pt-1">
          <AppButton variant="primary" :loading="saving" @click="save">Save</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
