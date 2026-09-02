<!--
  CashbookForm — inline "Add new Cashbook / Bank Account" form, a strict
  WeConnectU "Financial Settings → Cashbooks" clone (BoldMark branded).

  Label-on-left rows exactly like WeConnectU: Bank Name, Account Type, Account
  Number, Branch Code, Branch Name, Bank Integration, Default Account (Yes/No),
  Tenant Billing Account, then read-only General Ledger Details (auto account
  number + live-composed description) and an Opening Balance section.

  The parent (FinancialSettingsPage) owns the request; this component only owns
  the form + the composed payload it emits on save.
-->
<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  mode:    { type: String,  default: 'add' },   // 'add' | 'edit'
  account: { type: Object,  default: null },     // existing account when editing
  glCode:  { type: String,  default: '8000/001' }, // next GL code preview
  saving:  { type: Boolean, default: false },
  error:   { type: String,  default: null },
})
const emit = defineEmits(['save', 'cancel'])

// ── Option sets (mirror WeConnectU) ─────────────────────────────────────────
const BANKS = [
  'ABSA', 'African Bank', 'Bank Windhoek', 'Bidvest Bank', 'Capitec Bank',
  'Capitec Business', 'Discovery Bank', 'First National Bank',
  'Investec Private Bank', 'Mercantile Bank', 'Merchant Bank', 'Nedbank',
  'Other', 'PayFast', 'Sasfin Bank Ltd', 'Standard Bank',
  'Standard Chartered Bank', 'Tyme Bank',
]
const ACCOUNT_TYPES = [
  { value: 'investment', label: 'Investment' },
  { value: 'savings',    label: 'Savings' },
  { value: 'current',    label: 'Current' },
]
const TYPE_LABELS = { current: 'Current', savings: 'Savings', investment: 'Investment' }

// ── Form state ──────────────────────────────────────────────────────────────
function blank() {
  return {
    bank_name: '',
    type: '',
    account_number: '',
    branch_code: '',
    branch_name: '',
    integration: '',
    is_default: false,
    tenant_billing_account: false,
    opening_balance: '',
  }
}
const form = ref(blank())
const localError = ref(null)

watch(
  () => [props.mode, props.account],
  () => {
    localError.value = null
    if (props.mode === 'edit' && props.account) {
      const a = props.account
      form.value = {
        bank_name:              a.bank_name ?? '',
        type:                   a.type ?? 'current',
        account_number:         a.account_number ?? '',
        branch_code:            a.branch_code ?? '',
        branch_name:            a.branch_name ?? '',
        integration:            a.integration ?? '',
        is_default:             !!a.is_default,
        tenant_billing_account: !!a.tenant_billing_account,
        opening_balance:        a.opening_balance != null ? String(a.opening_balance) : '',
      }
    } else {
      form.value = blank()
    }
  },
  { immediate: true },
)

// Live GL description — "First National Bank Current 0500000000123".
const description = computed(() => {
  const parts = [form.value.bank_name, TYPE_LABELS[form.value.type], form.value.account_number]
  return parts.filter(p => p && String(p).trim() !== '').join(' ')
})

function submit() {
  localError.value = null
  if (!form.value.bank_name) { localError.value = 'Please select a bank.'; return }
  if (!form.value.type)      { localError.value = 'Please select an account type.'; return }

  emit('save', {
    // WeConnectU has no separate "name" — the account name is the GL description.
    name:                   description.value || form.value.bank_name,
    bank_name:              form.value.bank_name,
    type:                   form.value.type,
    account_number:         form.value.account_number || null,
    branch_code:            form.value.branch_code || null,
    branch_name:            form.value.branch_name || null,
    integration:            form.value.integration || null,
    is_default:             !!form.value.is_default,
    tenant_billing_account: !!form.value.tenant_billing_account,
    opening_balance:        form.value.opening_balance === '' ? 0 : Number(form.value.opening_balance),
  })
}

const rowClass = 'grid grid-cols-1 sm:grid-cols-[240px_1fr] gap-1.5 sm:gap-4 sm:items-center'
const fieldClass = 'h-11 w-full rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none'
</script>

<template>
  <div class="rounded-lg border border-border bg-white p-6 space-y-5">
    <div v-if="error || localError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ error || localError }}
    </div>

    <!-- Core fields -->
    <div class="space-y-4 max-w-4xl">
      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Bank Name</label>
        <select v-model="form.bank_name" :class="fieldClass">
          <option value="">Nothing selected</option>
          <option v-for="b in BANKS" :key="b" :value="b">{{ b }}</option>
        </select>
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Account Type</label>
        <select v-model="form.type" :class="fieldClass">
          <option value="">Nothing selected</option>
          <option v-for="t in ACCOUNT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Account Number</label>
        <input v-model="form.account_number" type="text" :class="fieldClass" />
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Branch Code</label>
        <input v-model="form.branch_code" type="text" :class="fieldClass" />
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Branch Name</label>
        <input v-model="form.branch_name" type="text" :class="fieldClass" />
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Bank Integration</label>
        <select v-model="form.integration" :class="fieldClass">
          <option value="">Nothing selected</option>
        </select>
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Default Account</label>
        <div class="flex items-center gap-6 text-sm">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input v-model="form.is_default" type="radio" :value="true" class="h-4 w-4 accent-[#2f6fb0]" /> Yes
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input v-model="form.is_default" type="radio" :value="false" class="h-4 w-4 accent-[#2f6fb0]" /> No
          </label>
        </div>
      </div>

      <div :class="rowClass">
        <label class="text-sm text-muted-foreground">Tenant Billing Account</label>
        <input v-model="form.tenant_billing_account" type="checkbox" class="h-5 w-5 rounded border-border accent-[#2f6fb0]" />
      </div>
    </div>

    <!-- General Ledger Details -->
    <div class="pt-2">
      <h3 class="font-body text-lg font-bold text-navy-dark mb-3">General Ledger Details</h3>
      <div class="space-y-4 max-w-4xl">
        <div :class="rowClass">
          <label class="text-sm text-muted-foreground">Account Number</label>
          <input :value="glCode" type="text" readonly
                 class="h-11 w-full rounded-md border border-border bg-muted/40 px-3 text-sm text-muted-foreground" />
        </div>
        <div :class="rowClass">
          <label class="text-sm text-muted-foreground">Description</label>
          <input :value="description" type="text" :class="fieldClass" readonly />
        </div>
      </div>
    </div>

    <!-- Opening Balance -->
    <div class="pt-2">
      <h3 class="font-body text-lg font-bold text-navy-dark mb-3">Opening Balance</h3>
      <div class="rounded-md bg-[#e7f0f7] text-[#2f6fb0] text-sm px-4 py-3 mb-4 max-w-4xl">
        This is the opening balance of the first bank statement upload
      </div>
      <div :class="[rowClass, 'max-w-4xl']">
        <label class="text-sm text-muted-foreground">Opening Balance</label>
        <input v-model="form.opening_balance" type="number" step="0.01" placeholder="0.00" :class="fieldClass" />
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-2 pt-2">
      <button v-if="mode === 'edit'" type="button" class="rounded-md border border-border px-4 py-2.5 text-sm font-medium hover:bg-muted" @click="emit('cancel')">
        Cancel
      </button>
      <button type="button"
              class="inline-flex items-center gap-2 rounded-md bg-navy-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
              :disabled="saving" @click="submit">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M20 6 9 17l-5-5"/></svg>
        {{ saving ? 'Saving…' : (mode === 'edit' ? 'Save Cashbook' : 'Add Cashbook') }}
      </button>
    </div>
  </div>
</template>
