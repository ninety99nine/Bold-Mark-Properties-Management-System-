<!--
  CommunityGeneralSettingsPage — WeConnectU "Setup → General" clone.

  Full like-for-like of the WeConnectU community General settings screen: name,
  code, (read-only) year end, entity type, suppress-entity-type flag, unit
  addressing, registration / VAT / tax / CSOS numbers, Apply PQ, ageing type,
  interest %, interest exempt threshold, and the "Bank details for Transfer
  Clearance fee" block. Community-scoped via the community store.

  GET /communities/{id}  ·  PUT /communities/{id}
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import { ENTITY_TYPE_OPTIONS } from '@/utils/communityEntityType'
import AppInput  from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'

const { success, error } = useToast()
const community = useCommunityStore()

// ── Option sets (mirror WeConnectU) ─────────────────────────────────────
// Order + labels match WeConnectU exactly.
const UNIT_ADDRESSING_OPTS = [
  { value: 'door_no',    label: 'Door No' },
  { value: 'unit_no',    label: 'Unit No' },
  { value: 'section_no', label: 'Section / Erf No' },
]
const AGEING_TYPE_OPTS = [
  { value: 'calendar_month', label: 'Calendar Month' },
  { value: 'days',           label: 'Period Aging' },
]
const INTEREST_PERIOD_OPTS = [
  { value: 'per_annum', label: 'per annum' },
  { value: 'per_month', label: 'per month' },
]
const ACCOUNT_TYPE_OPTS = [
  { value: 'Current',    label: 'Current' },
  { value: 'Savings',    label: 'Savings' },
  { value: 'Investment', label: 'Investment' },
]
// Exact WeConnectU "Bank Name" list (alphabetical).
const BANK_NAME_OPTS = [
  'ABSA', 'African Bank', 'Bank Windhoek', 'Bidvest Bank', 'Capitec Bank',
  'Capitec Business', 'Discovery Bank', 'First National Bank', 'Investec Private Bank',
  'Mercantile Bank', 'Merchant Bank', 'Nedbank', 'Other', 'PayFast',
  'Sasfin Bank Ltd', 'Standard Bank', 'Standard Chartered Bank', 'Tyme Bank',
].map((b) => ({ value: b, label: b }))

// ── Form state ──────────────────────────────────────────────────────────
function blankForm() {
  return {
    name: '', code: '', financial_year_end_month: '',
    entity_type: '', suppress_entity_type: false, unit_addressing: 'unit_no',
    registration_number: '', is_vat_registered: false, vat_number: '',
    income_tax_number: '', csos_registration_number: '', apply_pq: true,
    ageing_type: 'calendar_month', interest_rate: '0.00', interest_period: 'per_annum',
    interest_exempt_threshold: '0',
    transfer: {
      account_holder: '', bank_name: '', account_type: '',
      account_number: '', branch_code: '', branch_name: '',
    },
  }
}

const form    = ref(blankForm())
const loading = ref(true)
const saving  = ref(false)

// Year end is derived (read-only), shown as DD/MM — the last day of the
// financial year-end month, matching WeConnectU (e.g. 6 → 30/06).
const LAST_DAY = { 1: 31, 2: 28, 3: 31, 4: 30, 5: 31, 6: 30, 7: 31, 8: 31, 9: 30, 10: 31, 11: 30, 12: 31 }
const yearEnd = computed(() => {
  const m = parseInt(form.value.financial_year_end_month)
  if (!m || m < 1 || m > 12) return ''
  return `${String(LAST_DAY[m]).padStart(2, '0')}/${String(m).padStart(2, '0')}`
})

// ── Load ────────────────────────────────────────────────────────────────
async function load() {
  const id = community.selectedId
  if (!id) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${id}`)
    apply(data.data ?? data)
  } catch {
    error('Could not load the community settings. Please refresh.')
  } finally {
    loading.value = false
  }
}

function apply(c) {
  if (!c) return
  const f = blankForm()
  f.name                     = c.name ?? ''
  f.code                     = c.code ?? ''
  f.financial_year_end_month = c.financial_year_end_month != null ? String(c.financial_year_end_month) : ''
  f.entity_type              = c.entity_type ?? ''
  f.suppress_entity_type     = !!c.suppress_entity_type
  f.unit_addressing          = c.unit_addressing ?? 'unit_no'
  f.registration_number      = c.registration_number ?? ''
  f.is_vat_registered        = !!c.is_vat_registered
  f.vat_number               = c.vat_number ?? ''
  f.income_tax_number        = c.income_tax_number ?? ''
  f.csos_registration_number = c.csos_registration_number ?? ''
  f.apply_pq                 = c.apply_pq !== false
  f.ageing_type              = c.ageing_type ?? 'calendar_month'
  f.interest_rate            = c.interest_rate != null ? String(c.interest_rate) : '0.00'
  f.interest_period          = c.interest_period ?? 'per_annum'
  f.interest_exempt_threshold = c.interest_exempt_threshold != null ? String(c.interest_exempt_threshold) : '0'
  const tb = c.transfer_clearance_bank ?? {}
  f.transfer = {
    account_holder: tb.account_holder ?? '',
    bank_name:      tb.bank_name ?? '',
    account_type:   tb.account_type ?? '',
    account_number: tb.account_number ?? '',
    branch_code:    tb.branch_code ?? '',
    branch_name:    tb.branch_name ?? '',
  }
  form.value = f
}

onMounted(load)
watch(() => community.selectedId, load)

// ── Save ────────────────────────────────────────────────────────────────
async function save() {
  const id = community.selectedId
  if (!id || saving.value) return
  saving.value = true
  try {
    const payload = {
      name:                     form.value.name || undefined,
      code:                     form.value.code?.trim() || null,
      entity_type:              form.value.entity_type || undefined,
      suppress_entity_type:     !!form.value.suppress_entity_type,
      unit_addressing:          form.value.unit_addressing || 'unit_no',
      registration_number:      form.value.registration_number?.trim() || null,
      is_vat_registered:        !!form.value.is_vat_registered,
      vat_number:               form.value.vat_number?.trim() || null,
      income_tax_number:        form.value.income_tax_number?.trim() || null,
      csos_registration_number: form.value.csos_registration_number?.trim() || null,
      apply_pq:                 !!form.value.apply_pq,
      ageing_type:              form.value.ageing_type || 'calendar_month',
      interest_rate:            form.value.interest_rate !== '' ? parseFloat(form.value.interest_rate) : 0,
      interest_period:          form.value.interest_period || 'per_annum',
      interest_exempt_threshold: form.value.interest_exempt_threshold !== '' ? parseFloat(form.value.interest_exempt_threshold) : 0,
      transfer_clearance_bank: {
        account_holder: form.value.transfer.account_holder?.trim() || null,
        bank_name:      form.value.transfer.bank_name || null,
        account_type:   form.value.transfer.account_type || null,
        account_number: form.value.transfer.account_number?.trim() || null,
        branch_code:    form.value.transfer.branch_code?.trim() || null,
        branch_name:    form.value.transfer.branch_name?.trim() || null,
      },
    }
    const { data } = await api.put(`/communities/${id}`, payload)
    const saved = data.data ?? data
    apply(saved)
    // Keep the top-bar community label in sync if the name/code changed.
    community.select(id, saved)
    success('Community settings saved.')
  } catch (e) {
    error(e.response?.data?.message ?? 'Could not save the community settings.')
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
        Setup <span class="mx-1">→</span> <span class="text-foreground font-medium">General</span>
      </p>
      <h1 class="font-body font-bold text-2xl text-foreground mt-1">General</h1>
      <p class="text-sm text-muted-foreground">
        Core details for this community
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- No community -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to configure its settings.
    </div>

    <div v-else class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="divide-y divide-border/60">
        <!-- Community Name -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Community Name</label>
          <AppInput v-model="form.name" />
        </div>

        <!-- Community Code -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Community Code (Max 5 Chars)</label>
          <AppInput v-model="form.code" maxlength="5" />
        </div>

        <!-- Year end (read-only, derived) -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Year end</label>
          <AppInput :model-value="yearEnd" disabled />
        </div>

        <!-- Entity Type -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Entity Type</label>
          <AppSelect v-model="form.entity_type" :options="ENTITY_TYPE_OPTIONS" placeholder="Select entity type..." />
        </div>

        <!-- Suppress Entity Type -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Suppress <span class="font-semibold text-foreground">Entity Type</span> in communication:</label>
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.suppress_entity_type" class="accent-navy w-4 h-4" />
          </label>
        </div>

        <!-- Unit Addressing -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Unit Addressing</label>
          <AppSelect v-model="form.unit_addressing" :options="UNIT_ADDRESSING_OPTS" />
        </div>

        <!-- Community Registration Number -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Community Registration Number</label>
          <AppInput v-model="form.registration_number" />
        </div>

        <!-- VAT Registered -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">VAT Registered:</label>
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_vat_registered" class="accent-navy w-4 h-4" />
          </label>
        </div>

        <!-- VAT Number -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">VAT Number:</label>
          <AppInput v-model="form.vat_number" :disabled="!form.is_vat_registered" />
        </div>

        <!-- Entity Income Tax Number -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Entity Income Tax Number:</label>
          <AppInput v-model="form.income_tax_number" />
        </div>

        <!-- CSOS Registration Number -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">CSOS Registration Number:</label>
          <AppInput v-model="form.csos_registration_number" />
        </div>

        <!-- Apply PQ -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Apply PQ:</label>
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.apply_pq" class="accent-navy w-4 h-4" />
          </label>
        </div>

        <!-- Ageing Type -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Ageing Type</label>
          <AppSelect v-model="form.ageing_type" :options="AGEING_TYPE_OPTS" />
        </div>

        <!-- Interest % + period -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Interest %</label>
          <div class="flex items-center gap-3">
            <div class="w-40"><AppInput v-model="form.interest_rate" type="number" :min="0" :max="100" /></div>
            <div class="w-48"><AppSelect v-model="form.interest_period" :options="INTEREST_PERIOD_OPTS" /></div>
          </div>
        </div>

        <!-- Interest Exempt Threshold -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Interest Exempt Threshold</label>
          <div class="w-40"><AppInput v-model="form.interest_exempt_threshold" type="number" :min="0" /></div>
        </div>

        <!-- ── Bank details for Transfer Clearance fee ── -->
        <div class="pt-5">
          <p class="text-sm font-semibold text-foreground bg-muted/40 -mx-6 px-6 py-2.5 border-y border-border/60">
            Bank details for Transfer Clearance fee
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Account Holder</label>
          <AppInput v-model="form.transfer.account_holder" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Bank Name</label>
          <AppSelect v-model="form.transfer.bank_name" :options="BANK_NAME_OPTS" placeholder="Select bank..." />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Account Type</label>
          <AppSelect v-model="form.transfer.account_type" :options="ACCOUNT_TYPE_OPTS" placeholder="Select account type..." />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Account Number</label>
          <AppInput v-model="form.transfer.account_number" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Branch Code</label>
          <AppInput v-model="form.transfer.branch_code" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Branch Name</label>
          <AppInput v-model="form.transfer.branch_name" />
        </div>

        <!-- Save -->
        <div class="pt-5">
          <AppButton variant="primary" :loading="saving" @click="save">Save</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
