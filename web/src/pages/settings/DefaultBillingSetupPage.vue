<!--
  DefaultBillingSetupPage — WeConnectU "Default Billing Setup" clone.

  Maps each community billing concept (levies, reserve fund, water, CSOS,
  ratios, bank charges…) to a chart-of-accounts ledger, plus the recovery /
  billing toggles. Community-scoped via the community store.

  GET/PUT /communities/{id}/billing-setup.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppButton from '@/components/common/AppButton.vue'

const { success, error } = useToast()
const community = useCommunityStore()

// ── Field definitions (render rows DRY) ─────────────────────────────────
const LEDGER_ROWS = [
  { key: 'levies_ledger_id',                  label: 'Levies' },
  { key: 'reserve_fund_levies_ledger_id',     label: 'Reserve Fund Levies' },
  { key: 'reserve_fund_ledger_id',            label: 'Reserve Fund' },
  { key: 'arrears_interest_ledger_id',        label: 'Arrears Interest' },
  { key: 'water_ledger_id',                   label: 'Water' },
  { key: 'sewerage_ledger_id',                label: 'Sewerage' },
  { key: 'rule_enforcement_income_ledger_id', label: 'Rule Enforcement Income' },
  { key: 'arrear_admin_ledger_id',            label: 'Arrear Admin' },
  { key: 'debt_collecting_ledger_id',         label: 'Debt Collecting' },
  { key: 'admin_fees_ledger_id',              label: 'Admin Fees' },
  { key: 'electricity_ledger_id',             label: 'Electricity' },
  { key: 'insurance_ledger_id',               label: 'Insurance' },
  { key: 'csos_ledger_id',                    label: 'CSOS' },
]
const RATIO_ROWS = [1, 2, 3, 4, 5].map((n) => ({
  key:       `ratio_${n}_ledger_id`,
  exemptKey: `ratio_${n}_csos_exempt`,
  label:     `Ratio ${n}`,
}))
const TOGGLES = [
  { key: 'apply_csos_levy',      label: 'Apply CSOS Levy' },
  { key: 'occupant_billing',     label: 'Tenant Billing' },
  { key: 'water_recovery',       label: 'Water Recovery' },
  { key: 'electricity_recovery', label: 'Electricity Recovery' },
]

function blankForm() {
  const f = {}
  LEDGER_ROWS.forEach((r) => (f[r.key] = ''))
  RATIO_ROWS.forEach((r) => { f[r.key] = ''; f[r.exemptKey] = false })
  TOGGLES.forEach((t) => (f[t.key] = t.key === 'apply_csos_levy'))
  return f
}

const form        = ref(blankForm())
const ledgers     = ref([])
const loading     = ref(true)
const saving      = ref(false)

// ── Options (chart of accounts) ─────────────────────────────────────────
const ledgerOptions = computed(() => {
  const coded = ledgers.value.filter((l) => l.code)
  const source = coded.length ? coded : ledgers.value
  return source.map((l) => ({
    value: l.id,
    label: l.code ? `${l.code} - ${l.name}` : l.name,
    category: l.category || 'Other Accounts',
  }))
})

// ── Load ────────────────────────────────────────────────────────────────
const rows = (res) => res?.data?.data ?? res?.data ?? []

async function load() {
  const communityId = community.selectedId
  if (!communityId) { loading.value = false; return }
  loading.value = true
  const failed = []

  await Promise.allSettled([
    api.get('/ledgers', { params: { _per_page: 500, is_active: true } })
      .then((r) => { ledgers.value = rows(r) })
      .catch(() => failed.push('accounts')),
    api.get(`/communities/${communityId}/billing-setup`)
      .then((r) => { applySetup(r.data.data ?? r.data) })
      .catch(() => failed.push('billing setup')),
  ])

  if (failed.length) error(`Could not load ${failed.join(', ')}. Please refresh.`)
  loading.value = false
}

function applySetup(setup) {
  if (!setup) return
  const f = blankForm()
  Object.keys(f).forEach((key) => {
    if (setup[key] !== undefined && setup[key] !== null) f[key] = setup[key]
  })
  form.value = f
}

onMounted(load)
watch(() => community.selectedId, load)

// ── Save ────────────────────────────────────────────────────────────────
async function save() {
  const communityId = community.selectedId
  if (!communityId) return
  saving.value = true

  // Send null (not '') for unset ledger selects so the API clears them.
  const payload = {}
  Object.entries(form.value).forEach(([key, val]) => {
    payload[key] = val === '' ? null : val
  })

  try {
    const { data } = await api.put(`/communities/${communityId}/billing-setup`, payload)
    applySetup(data.data ?? data)
    success('Billing setup saved.')
  } catch (err) {
    error(err.response?.data?.message ?? 'Could not save the billing setup.')
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
        Setup <span class="mx-1">→</span> <span class="text-foreground font-medium">Default Billing Setup</span>
      </p>
      <h1 class="font-body font-bold text-2xl text-foreground mt-1">Default Billing Setup</h1>
      <p class="text-sm text-muted-foreground">
        Map each charge to a chart-of-accounts ledger
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- No community -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to configure its billing setup.
    </div>

    <div v-else class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="space-y-5">
        <!-- Standard charge → ledger mappings -->
        <div
          v-for="row in LEDGER_ROWS"
          :key="row.key"
          class="grid grid-cols-1 md:grid-cols-[220px_1fr] md:items-center gap-2 md:gap-6 border-b border-border/60 pb-4"
        >
          <label class="text-sm font-medium text-foreground">{{ row.label }}</label>
          <AppAccountSelect v-model="form[row.key]" :options="ledgerOptions" placeholder="Select Ledger" />
        </div>

        <!-- Ratio rows (each with a CSOS Exempt toggle) -->
        <div
          v-for="row in RATIO_ROWS"
          :key="row.key"
          class="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] md:items-center gap-2 md:gap-6 border-b border-border/60 pb-4"
        >
          <label class="text-sm font-medium text-foreground">{{ row.label }}</label>
          <AppAccountSelect v-model="form[row.key]" :options="ledgerOptions" placeholder="Select Ledger" />
          <label class="flex items-center gap-2 text-sm text-muted-foreground cursor-pointer md:pl-4">
            <input type="checkbox" v-model="form[row.exemptKey]" class="accent-navy w-4 h-4" />
            CSOS Exempt
          </label>
        </div>

        <!-- Bank Charges -->
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] md:items-center gap-2 md:gap-6 border-b border-border/60 pb-4">
          <label class="text-sm font-medium text-foreground">Bank Charges</label>
          <AppAccountSelect v-model="form.bank_charges_ledger_id" :options="ledgerOptions" placeholder="Select Ledger" />
        </div>

        <!-- Toggles -->
        <div
          v-for="toggle in TOGGLES"
          :key="toggle.key"
          class="grid grid-cols-1 md:grid-cols-[220px_1fr] md:items-center gap-2 md:gap-6 border-b border-border/60 pb-4"
        >
          <label class="text-sm font-medium text-foreground">{{ toggle.label }}:</label>
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form[toggle.key]" class="accent-navy w-4 h-4" />
            <span class="text-sm text-muted-foreground">{{ form[toggle.key] ? 'Enabled' : 'Disabled' }}</span>
          </label>
        </div>

        <!-- Save -->
        <div class="pt-2">
          <AppButton variant="primary" :loading="saving" @click="save">Save</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
