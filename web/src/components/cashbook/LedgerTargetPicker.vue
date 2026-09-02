<!--
  LedgerTargetPicker — WeConnectU-style "Ledger Type → target account" picker.

  Shared by the Allocate, Split and Create-Rule flows so the four ledger types
  (General / Customer / Supplier / Reserve Fund) all resolve to the correct id
  field in exactly the same way. It renders:

    - General / Reserve Fund → an AppAccountSelect grouped by category, backed by
      the /cashbook/ledger-options `general` / `reserve_fund` lists → ledger_id.
    - Customer → a debounced searchable table (Code / Customer / Reference /
      Balance) backed by /cashbook/customer-search → unit_id.
    - Supplier → a debounced searchable AppSelect backed by /suppliers → supplier_id.

  v-model is an object: { ledger_type, ledger_id, unit_id, supplier_id, account_label }.
  Whenever the type changes the old target ids are cleared. The parent reads back
  the resolved id field it needs.
-->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import AppSelect        from '@/components/common/AppSelect.vue'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppInput         from '@/components/common/AppInput.vue'

const props = defineProps({
  /** { ledger_type, ledger_id, unit_id, supplier_id, account_label } */
  modelValue:      { type: Object, default: () => ({}) },
  /** Pre-fetched ledger-options envelope { general, reserve_fund, vat_types } */
  ledgerOptions:   { type: Object, default: null },
  /** Hide the Ledger Type selector (the split rows keep it in their own column). */
  hideTypeSelect:  { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const communityStore = useCommunityStore()
const countryStore   = useCountryStore()
const communityId    = computed(() => communityStore.selectedId)

const LEDGER_TYPES = [
  { value: 'general',      label: 'General Ledger' },
  { value: 'customer',     label: 'Customer Ledger' },
  { value: 'supplier',     label: 'Supplier Ledger' },
  { value: 'reserve_fund', label: 'Reserve Fund Ledger' },
]

// ── Local mirror of the v-model object ────────────────────────────────────
const model = computed(() => ({
  ledger_type:   props.modelValue.ledger_type ?? '',
  ledger_id:     props.modelValue.ledger_id ?? null,
  unit_id:       props.modelValue.unit_id ?? null,
  supplier_id:   props.modelValue.supplier_id ?? null,
  account_label: props.modelValue.account_label ?? '',
}))

function patch(next) {
  emit('update:modelValue', { ...model.value, ...next })
}

// ── Ledger options (General / Reserve Fund) ───────────────────────────────
const options = ref(props.ledgerOptions)

async function loadOptions() {
  if (options.value || !communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/ledger-options`)
    options.value = data
  } catch { /* silent */ }
}

const generalOptions = computed(() =>
  (options.value?.general ?? []).map(l => ({
    value:    l.ledger_id,
    label:    l.label ?? `${l.code} - ${l.name}`,
    category: l.category || 'Other Accounts',
  })),
)
const reserveOptions = computed(() =>
  (options.value?.reserve_fund ?? []).map(l => ({
    value:    l.ledger_id,
    label:    l.label ?? `${l.code} - ${l.name}`,
    category: l.category || 'Reserve Fund',
  })),
)

function ledgerLabelFor(id, list) {
  return list.find(o => o.value === id)?.label ?? ''
}

function onTypeChange(type) {
  patch({ ledger_type: type, ledger_id: null, unit_id: null, supplier_id: null, account_label: '' })
  supplierResults.value = []
  supplierSearch.value  = ''
  customerResults.value = []
  customerSearch.value  = ''
}

function onLedgerChange(id) {
  const list = model.value.ledger_type === 'reserve_fund' ? reserveOptions.value : generalOptions.value
  patch({ ledger_id: id, account_label: ledgerLabelFor(id, list) })
}

// ── Customer search ───────────────────────────────────────────────────────
const customerSearch  = ref('')
const customerResults = ref([])
const customerLoading = ref(false)
let customerTimer = null

function onCustomerSearch() {
  if (customerTimer) clearTimeout(customerTimer)
  customerTimer = setTimeout(runCustomerSearch, 300)
}

async function runCustomerSearch() {
  if (!communityId.value) return
  const q = customerSearch.value.trim()
  if (!q) { customerResults.value = []; return }
  customerLoading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/customer-search`, { params: { q } })
    customerResults.value = data ?? []
  } catch { customerResults.value = [] } finally {
    customerLoading.value = false
  }
}

function selectCustomer(c) {
  patch({ unit_id: c.unit_id, account_label: `${c.code} - ${c.customer}` })
}

// ── Supplier search ───────────────────────────────────────────────────────
const supplierSearch  = ref('')
const supplierResults = ref([])
const supplierLoading = ref(false)
let supplierTimer = null

function onSupplierSearch() {
  if (supplierTimer) clearTimeout(supplierTimer)
  supplierTimer = setTimeout(runSupplierSearch, 300)
}

async function runSupplierSearch() {
  if (!communityId.value) return
  supplierLoading.value = true
  try {
    const { data } = await api.get('/suppliers', {
      params: { community_id: communityId.value, search: supplierSearch.value.trim() || undefined },
    })
    supplierResults.value = data.data ?? data ?? []
  } catch { supplierResults.value = [] } finally {
    supplierLoading.value = false
  }
}

const supplierOptions = computed(() =>
  supplierResults.value.map(s => ({ value: s.id, label: s.label ?? `${s.supplier_code} - ${s.name}` })),
)

function onSupplierChange(id) {
  const label = supplierOptions.value.find(o => o.value === id)?.label ?? ''
  patch({ supplier_id: id, account_label: label })
}

const money = (v) => countryStore.formatCurrency(v)

onMounted(loadOptions)
watch(communityId, () => { options.value = props.ledgerOptions; loadOptions() })
</script>

<template>
  <div class="space-y-3">
    <!-- Ledger Type -->
    <div v-if="!hideTypeSelect">
      <label class="block text-sm font-medium text-fg mb-1.5">Ledger Type</label>
      <AppSelect
        :model-value="model.ledger_type"
        :options="LEDGER_TYPES"
        placeholder="Nothing selected"
        @update:model-value="onTypeChange"
      />
    </div>

    <!-- General / Reserve Fund -->
    <div v-if="model.ledger_type === 'general' || model.ledger_type === 'reserve_fund'">
      <label class="block text-sm font-medium text-fg mb-1.5">Account</label>
      <AppAccountSelect
        :model-value="model.ledger_id"
        :options="model.ledger_type === 'reserve_fund' ? reserveOptions : generalOptions"
        :placeholder="model.ledger_type === 'reserve_fund' ? 'Select Reserve Fund Ledger' : 'Select Ledger'"
        @update:model-value="onLedgerChange"
      />
    </div>

    <!-- Customer -->
    <div v-else-if="model.ledger_type === 'customer'">
      <label class="block text-sm font-medium text-fg mb-1.5">Customer</label>
      <AppInput
        v-model="customerSearch"
        leading-icon="search"
        placeholder="Search by code, name or reference…"
        @input="onCustomerSearch"
      />
      <div v-if="model.unit_id && model.account_label" class="mt-2 flex items-center gap-2 text-sm text-fg">
        <svg class="w-4 h-4 text-success" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/></svg>
        <span class="font-medium">{{ model.account_label }}</span>
      </div>
      <div v-if="customerResults.length" class="mt-2 overflow-x-auto rounded border border-border max-h-56 overflow-y-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/40 text-left">
              <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Code</th>
              <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Customer</th>
              <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Reference</th>
              <th class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in customerResults"
              :key="c.unit_id"
              class="border-b border-border last:border-0 cursor-pointer hover:bg-muted/40"
              :class="c.unit_id === model.unit_id ? 'bg-amber/10' : ''"
              @click="selectCustomer(c)"
            >
              <td class="py-2 px-3 text-foreground whitespace-nowrap">{{ c.code }}</td>
              <td class="py-2 px-3 text-foreground">{{ c.customer }}</td>
              <td class="py-2 px-3 text-muted-foreground">{{ c.reference }}</td>
              <td class="py-2 px-3 text-right text-foreground whitespace-nowrap">{{ money(c.balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="customerSearch && !customerLoading" class="mt-2 text-sm text-muted-foreground italic">No customers found.</p>
    </div>

    <!-- Supplier -->
    <div v-else-if="model.ledger_type === 'supplier'">
      <label class="block text-sm font-medium text-fg mb-1.5">Supplier</label>
      <AppInput
        v-model="supplierSearch"
        leading-icon="search"
        placeholder="Search suppliers…"
        @input="onSupplierSearch"
      />
      <div class="mt-2">
        <AppSelect
          :model-value="model.supplier_id"
          :options="supplierOptions"
          :placeholder="supplierLoading ? 'Searching…' : 'Select Supplier'"
          @update:model-value="onSupplierChange"
        />
      </div>
    </div>
  </div>
</template>
