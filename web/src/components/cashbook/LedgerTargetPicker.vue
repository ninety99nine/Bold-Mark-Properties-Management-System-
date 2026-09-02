<!--
  LedgerTargetPicker — WeConnectU-style "Ledger Type → target account" picker.

  Shared by the Allocate, Split and Create-Rule flows so the four ledger types
  (General / Customer / Supplier / Reserve Fund) all resolve to the correct id
  field in exactly the same way. It renders:

    - General / Reserve Fund → an AppAccountSelect grouped by category, backed by
      the /cashbook/ledger-options `general` / `reserve_fund` lists → ledger_id.
    - Customer → an AppAsyncSelect (the exact same searchable dropdown as the
      Customer Invoice page: "CODE — Name · Unit N") backed by
      /communities/{id}/units → unit_id.
    - Supplier → an AppAsyncSelect ("CODE - Supplier Name") backed by /suppliers
      → supplier_id.

  v-model is an object: { ledger_type, ledger_id, unit_id, supplier_id, account_label }.
  Whenever the type changes the old target ids are cleared. The parent reads back
  the resolved id field it needs.
-->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import AppSelect        from '@/components/common/AppSelect.vue'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppAsyncSelect   from '@/components/common/AppAsyncSelect.vue'

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
}

function onLedgerChange(id) {
  const list = model.value.ledger_type === 'reserve_fund' ? reserveOptions.value : generalOptions.value
  patch({ ledger_id: id, account_label: ledgerLabelFor(id, list) })
}

// ── Customer picker (exact same input/dropdown as Customer Invoice) ────────
const units = ref([])

const unitOption = (u) => {
  const name = u.owner?.full_name || u.current_occupant?.full_name || 'No owner'
  const code = u.customer_code || u.unit_number
  return { value: u.id, label: `${code} — ${name} · Unit ${u.unit_number}` }
}
const customerOptions = computed(() => units.value.map(unitOption))

function mergeUnits(list) {
  const byId = new Map(units.value.map((u) => [u.id, u]))
  for (const u of list) byId.set(u.id, u)
  units.value = [...byId.values()]
}

async function loadUnits() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/units`, {
      params: { _per_page: 200, page: 1, _sort: 'unit_number:asc' },
    })
    units.value = data?.data ?? []
  } catch { units.value = [] }
}

// Debounced API search (inside AppAsyncSelect). Fetched units are merged so the
// selected customer resolves even when picked from a search hit outside the list.
async function searchCustomers(query) {
  if (!communityId.value) return []
  const { data } = await api.get(`/communities/${communityId.value}/units`, {
    params: { _search: query, _per_page: 50, _sort: 'unit_number:asc' },
  })
  const found = data?.data ?? []
  mergeUnits(found)
  return found.map(unitOption)
}

function onCustomerChange(id) {
  const label = customerOptions.value.find(o => o.value === id)?.label ?? model.value.account_label
  patch({ unit_id: id, account_label: label })
}

// ── Supplier picker (same searchable dropdown) ────────────────────────────
const suppliers = ref([])

const supplierOption = (s) => ({ value: s.id, label: s.label ?? `${s.supplier_code} - ${s.name}` })
const supplierOptions = computed(() => suppliers.value.map(supplierOption))

function mergeSuppliers(list) {
  const byId = new Map(suppliers.value.map((s) => [s.id, s]))
  for (const s of list) byId.set(s.id, s)
  suppliers.value = [...byId.values()]
}

async function loadSuppliers() {
  if (!communityId.value) return
  try {
    const { data } = await api.get('/suppliers', {
      params: { community_id: communityId.value, _per_page: 200 },
    })
    suppliers.value = data?.data ?? data ?? []
  } catch { suppliers.value = [] }
}

async function searchSuppliers(query) {
  if (!communityId.value) return []
  const { data } = await api.get('/suppliers', {
    params: { community_id: communityId.value, search: query || undefined, _per_page: 50 },
  })
  const found = data?.data ?? data ?? []
  mergeSuppliers(found)
  return found.map(supplierOption)
}

function onSupplierChange(id) {
  const label = supplierOptions.value.find(o => o.value === id)?.label ?? model.value.account_label
  patch({ supplier_id: id, account_label: label })
}

// Lazy-load the list the moment its type is active (covers the Ledger Type
// selector, split-row columns that set the type externally, and edit presets).
watch(() => model.value.ledger_type, (type) => {
  if (type === 'customer' && !units.value.length) loadUnits()
  if (type === 'supplier' && !suppliers.value.length) loadSuppliers()
}, { immediate: true })

onMounted(loadOptions)
watch(communityId, () => {
  options.value = props.ledgerOptions
  units.value = []
  suppliers.value = []
  loadOptions()
  if (model.value.ledger_type === 'customer') loadUnits()
  if (model.value.ledger_type === 'supplier') loadSuppliers()
})
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
      <AppAsyncSelect
        :model-value="model.unit_id"
        :options="customerOptions"
        :fetcher="searchCustomers"
        placeholder="Nothing selected"
        @update:model-value="onCustomerChange"
      />
    </div>

    <!-- Supplier -->
    <div v-else-if="model.ledger_type === 'supplier'">
      <label class="block text-sm font-medium text-fg mb-1.5">Supplier</label>
      <AppAsyncSelect
        :model-value="model.supplier_id"
        :options="supplierOptions"
        :fetcher="searchSuppliers"
        placeholder="Nothing selected"
        @update:model-value="onSupplierChange"
      />
    </div>
  </div>
</template>
