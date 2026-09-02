<!--
  CreateAllocationRuleModal — WeConnectU "Create / Edit allocation rule" dialog.

  A rule matches incoming bank descriptions ("starts with" and/or "contains") and
  auto-allocates matching transactions to a chosen ledger target (via the shared
  LedgerTargetPicker), with a VAT type, remarks, receipt/payment toggles, an
  optional cashbook restriction (bank_account_ids — empty = all) and a sort order.

  POSTs /allocation-rules (create) or PUTs /allocation-rules/{id} (edit).
-->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useToast } from '@/composables/useToast'
import AppModal          from '@/components/common/AppModal.vue'
import AppButton         from '@/components/common/AppButton.vue'
import AppSelect         from '@/components/common/AppSelect.vue'
import AppMultiSelect    from '@/components/common/AppMultiSelect.vue'
import AppInput          from '@/components/common/AppInput.vue'
import LedgerTargetPicker from '@/components/cashbook/LedgerTargetPicker.vue'

const props = defineProps({
  show:          { type: Boolean, default: false },
  rule:          { type: Object, default: null }, // edit prefill
  ledgerOptions: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const communityStore = useCommunityStore()
const { success, error } = useToast()
const communityId = computed(() => communityStore.selectedId)

const isEdit = computed(() => !!props.rule?.id)

const startsWith  = ref('')
const contains    = ref('')
const target      = ref({ ledger_type: '', ledger_id: null, unit_id: null, supplier_id: null, account_label: '' })
const vatType     = ref('')
const remarks     = ref('')
const applyPositive = ref(true)
const applyNegative = ref(true)
const bankAccountIds = ref([])
const sortOrder   = ref('0')
const submitting  = ref(false)

const vatTypeOptions = computed(() =>
  [{ value: '', label: 'None' }, ...((props.ledgerOptions?.vat_types ?? []).map(v => ({ value: v.value, label: v.label })))],
)

// ── Cashbooks (bank accounts) from /cashbook/status ───────────────────────
const cashbooks = ref([])
const cashbookOptions = computed(() =>
  cashbooks.value.map(c => ({ value: c.bank_account_id, label: c.name })),
)

async function loadCashbooks() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/status`)
    cashbooks.value = data.bank_accounts ?? []
  } catch { cashbooks.value = [] }
}

const hasTarget = computed(() => {
  const t = target.value
  if (t.ledger_type === 'general' || t.ledger_type === 'reserve_fund') return !!t.ledger_id
  if (t.ledger_type === 'customer') return !!t.unit_id
  if (t.ledger_type === 'supplier') return !!t.supplier_id
  return false
})

const canSubmit = computed(() =>
  (startsWith.value.trim() || contains.value.trim()) && hasTarget.value,
)

function prefill() {
  const r = props.rule
  startsWith.value = r?.description_starts_with ?? ''
  contains.value   = r?.description_contains ?? ''
  target.value = {
    ledger_type:   r?.ledger_type ?? '',
    ledger_id:     r?.ledger_id ?? null,
    unit_id:       r?.unit_id ?? null,
    supplier_id:   r?.supplier_id ?? null,
    account_label: r?.account_label ?? '',
  }
  vatType.value     = r?.vat_type ?? ''
  remarks.value     = r?.remarks ?? ''
  applyPositive.value = r?.apply_to_positive ?? true
  applyNegative.value = r?.apply_to_negative ?? true
  bankAccountIds.value = r?.bank_account_ids ?? []
  sortOrder.value   = String(r?.sort_order ?? 0)
}

watch(() => props.show, (v) => { if (v) { prefill(); loadCashbooks() } })
onMounted(() => { if (props.show) loadCashbooks() })

async function submit() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  const t = target.value
  const payload = {
    description_starts_with: startsWith.value.trim() || null,
    description_contains:    contains.value.trim() || null,
    ledger_type:             t.ledger_type,
    vat_type:                vatType.value || null,
    remarks:                 remarks.value.trim() || null,
    apply_to_positive:       applyPositive.value,
    apply_to_negative:       applyNegative.value,
    bank_account_ids:        bankAccountIds.value,
    sort_order:              Number(sortOrder.value) || 0,
    account_label:           t.account_label || null,
  }
  if (t.ledger_type === 'general' || t.ledger_type === 'reserve_fund') payload.ledger_id = t.ledger_id
  else if (t.ledger_type === 'customer') payload.unit_id = t.unit_id
  else if (t.ledger_type === 'supplier') payload.supplier_id = t.supplier_id

  try {
    if (isEdit.value) {
      await api.put(`/communities/${communityId.value}/allocation-rules/${props.rule.id}`, payload)
      success('Allocation rule updated.')
    } else {
      await api.post(`/communities/${communityId.value}/allocation-rules`, payload)
      success('Allocation rule created.')
    }
    emit('saved')
    emit('close')
  } catch (e) {
    const res = e.response?.data
    error(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not save the rule.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :show="show" :title="isEdit ? 'Edit Allocation Rule' : 'Create Allocation Rule'" size="lg" @close="$emit('close')">
    <div class="space-y-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <AppInput v-model="startsWith" label="Description starts with" placeholder="e.g. LEVY" />
        <AppInput v-model="contains" label="Description contains" placeholder="e.g. WATER" />
      </div>
      <p class="text-xs text-muted-foreground -mt-3">Provide at least one of the two match fields.</p>

      <LedgerTargetPicker v-model="target" :ledger-options="ledgerOptions" />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-fg mb-1.5">VAT Type</label>
          <AppSelect v-model="vatType" :options="vatTypeOptions" placeholder="None" />
        </div>
        <AppInput v-model="sortOrder" label="Sort order" type="number" />
      </div>

      <AppInput v-model="remarks" label="Remarks" type="textarea" :rows="2" placeholder="Optional notes…" />

      <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
          <input type="checkbox" v-model="applyPositive" class="h-4 w-4 accent-primary" />
          Apply to receipts (money in)
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
          <input type="checkbox" v-model="applyNegative" class="h-4 w-4 accent-primary" />
          Apply to payments (money out)
        </label>
      </div>

      <AppMultiSelect
        v-model="bankAccountIds"
        :options="cashbookOptions"
        label="Cashbooks"
        heading="Cashbooks"
        placeholder="All cashbooks"
      />
      <p class="text-xs text-muted-foreground -mt-3">Leave empty to apply the rule to all cashbooks.</p>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
      <AppButton variant="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">
        {{ isEdit ? 'Save Rule' : 'Create Rule' }}
      </AppButton>
    </template>
  </AppModal>
</template>
