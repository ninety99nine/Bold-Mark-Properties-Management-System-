<!--
  CashbookModal — Add / Edit a community Cashbook (BankAccount).
  WeConnectU "Financial Setup → Cashbooks" clone. BoldMark branded.

  Wired against /bank-accounts by the parent (FinancialSettingsPage). This
  component only owns the form + validation; the parent performs the request.
-->
<script setup>
import { ref, computed, watch } from 'vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { useCountryStore } from '@/stores/country'

const props = defineProps({
  show:    { type: Boolean, default: false },
  mode:    { type: String,  default: 'add' }, // 'add' | 'edit'
  account: { type: Object,  default: null },  // existing acct when editing
  saving:  { type: Boolean, default: false },
  error:   { type: String,  default: null },
})

const emit = defineEmits(['close', 'save'])

const country = useCountryStore()

// ── Banks list (mirrors WeConnectU) ─────────────────────────────────────
const BANKS = [
  'ABSA', 'African Bank', 'Bank Windhoek', 'Bidvest Bank', 'Capitec Bank',
  'Capitec Business', 'Discovery Bank', 'First National Bank',
  'Investec Private Bank', 'Mercantile Bank', 'Merchant Bank', 'Nedbank',
  'Other', 'PayFast', 'Sasfin Bank Ltd', 'Standard Bank',
  'Standard Chartered Bank', 'Tyme Bank',
].map(b => ({ value: b, label: b }))

const ACCOUNT_TYPES = [
  { value: 'current',    label: 'Current' },
  { value: 'investment', label: 'Investment' },
]

// ── Form state ──────────────────────────────────────────────────────────
const form = ref(blank())

function blank() {
  return {
    name: '',
    bank_name: '',
    type: 'current',
    account_number: '',
    branch_code: '',
    branch_name: '',
    integration: '',
    balance: '',
    balance_as_at: '',
    is_active: true,
  }
}

const localError = ref(null)

// Re-seed the form each time the modal opens.
watch(
  () => props.show,
  (open) => {
    if (!open) return
    localError.value = null
    if (props.mode === 'edit' && props.account) {
      const a = props.account
      form.value = {
        name:           a.name ?? '',
        bank_name:      a.bank_name ?? '',
        type:           a.type ?? 'current',
        account_number: a.account_number ?? '',
        branch_code:    a.branch_code ?? '',
        branch_name:    a.branch_name ?? '',
        integration:    a.integration ?? '',
        balance:        a.balance != null ? String(a.balance) : '',
        balance_as_at:  a.balance_as_at ?? '',
        is_active:      a.is_active !== false,
      }
    } else {
      form.value = blank()
    }
  },
  { immediate: true },
)

// Derived GL account is read-only info from the server (edit mode only).
const glAccount = computed(() => props.account?.gl_account ?? null)
const glDescription = computed(() => props.account?.general_ledger_description ?? null)

const title = computed(() => (props.mode === 'edit' ? 'Edit Cashbook' : 'Add Cashbook'))

function submit() {
  localError.value = null
  if (!form.value.name.trim()) {
    localError.value = 'Please enter a cashbook / account name.'
    return
  }
  if (!form.value.type) {
    localError.value = 'Please select an account type.'
    return
  }
  const payload = {
    name:           form.value.name.trim(),
    type:           form.value.type,
    bank_name:      form.value.bank_name || null,
    account_number: form.value.account_number || null,
    branch_code:    form.value.branch_code || null,
    branch_name:    form.value.branch_name || null,
    integration:    form.value.integration || null,
    balance:        form.value.balance === '' ? null : Number(form.value.balance),
    balance_as_at:  form.value.balance_as_at || null,
    is_active:      !!form.value.is_active,
  }
  emit('save', payload)
}
</script>

<template>
  <AppModal :show="show" size="lg" @close="emit('close')">
    <template #header>
      <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">
        {{ title }}
      </h3>
    </template>

    <div class="space-y-4">
      <div v-if="error || localError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ error || localError }}
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <AppInput v-model="form.name" label="Cashbook / Account Name" placeholder="e.g. Main Trust Account" required />
        <AppSelect v-model="form.type" :options="ACCOUNT_TYPES" label="Account Type" placeholder="Select type…" required />

        <AppSelect v-model="form.bank_name" :options="BANKS" label="Bank Name" placeholder="Select bank…" />
        <AppInput v-model="form.account_number" label="Account Number" placeholder="e.g. 1234567890" />

        <AppInput v-model="form.branch_code" label="Branch Code" placeholder="e.g. 632005" />
        <AppInput v-model="form.branch_name" label="Branch Name" placeholder="e.g. Sandton" />

        <AppInput v-model="form.integration" label="Bank Integration" placeholder="Optional — e.g. Netcash" />
        <AppInput
          v-model="form.balance"
          type="number"
          label="Opening Balance"
          :prefix="country.currencySymbol"
          placeholder="0.00"
        />

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-fg">Balance As At</label>
          <AppDatePicker v-model="form.balance_as_at" placeholder="Select date" />
        </div>

        <label class="flex items-center gap-2.5 self-end h-11 text-sm text-foreground cursor-pointer select-none">
          <input v-model="form.is_active" type="checkbox" class="w-5 h-5 rounded accent-[#2f6fb0]" />
          Active
        </label>
      </div>

      <!-- Derived GL Account (read-only info, edit mode) -->
      <div v-if="glAccount" class="rounded-md border border-border bg-muted/30 px-4 py-3 text-sm">
        <div class="flex items-center gap-2">
          <span class="text-muted-foreground">GL Account:</span>
          <span class="font-mono font-medium text-foreground">{{ glAccount }}</span>
        </div>
        <div v-if="glDescription" class="text-muted-foreground mt-0.5">{{ glDescription }}</div>
      </div>
    </div>

    <template #footer>
      <AppButton variant="outline" :disabled="saving" @click="emit('close')">Cancel</AppButton>
      <AppButton variant="primary" :loading="saving" @click="submit">
        {{ mode === 'edit' ? 'Save Changes' : 'Add Cashbook' }}
      </AppButton>
    </template>
  </AppModal>
</template>
