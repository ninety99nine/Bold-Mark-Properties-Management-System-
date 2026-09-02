<!--
  CashbookAllocateModal — WeConnectU "Allocate transaction" dialog.

  Allocates a single unallocated cashbook entry to a General / Customer / Supplier
  / Reserve Fund target (via the shared LedgerTargetPicker), plus a VAT Type and
  free-text Remarks. POSTs to /cashbook/entries/{id}/allocate and emits `saved`
  so the parent page can refresh.
-->
<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useCountryStore } from '@/stores/country'
import { useToast } from '@/composables/useToast'
import AppModal          from '@/components/common/AppModal.vue'
import AppButton         from '@/components/common/AppButton.vue'
import AppSelect         from '@/components/common/AppSelect.vue'
import AppInput          from '@/components/common/AppInput.vue'
import LedgerTargetPicker from '@/components/cashbook/LedgerTargetPicker.vue'

const props = defineProps({
  show:          { type: Boolean, default: false },
  entry:         { type: Object, default: null },
  ledgerOptions: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const communityStore = useCommunityStore()
const countryStore   = useCountryStore()
const { success, error } = useToast()

const communityId = computed(() => communityStore.selectedId)

const target  = ref({ ledger_type: '', ledger_id: null, unit_id: null, supplier_id: null, account_label: '' })
const vatType = ref('')
const remarks = ref('')
const submitting = ref(false)

const vatTypeOptions = computed(() =>
  [{ value: '', label: 'None' }, ...((props.ledgerOptions?.vat_types ?? []).map(v => ({ value: v.value, label: v.label })))],
)

const hasTarget = computed(() => {
  const t = target.value
  if (t.ledger_type === 'general' || t.ledger_type === 'reserve_fund') return !!t.ledger_id
  if (t.ledger_type === 'customer') return !!t.unit_id
  if (t.ledger_type === 'supplier') return !!t.supplier_id
  return false
})

function reset() {
  target.value  = { ledger_type: '', ledger_id: null, unit_id: null, supplier_id: null, account_label: '' }
  vatType.value = ''
  remarks.value = ''
}

watch(() => props.show, (v) => { if (v) reset() })

const money = (v) => countryStore.formatCurrency(v)

async function submit() {
  if (!hasTarget.value || submitting.value) return
  submitting.value = true
  const t = target.value
  const payload = { ledger_type: t.ledger_type }
  if (t.ledger_type === 'general' || t.ledger_type === 'reserve_fund') payload.ledger_id = t.ledger_id
  else if (t.ledger_type === 'customer') payload.unit_id = t.unit_id
  else if (t.ledger_type === 'supplier') payload.supplier_id = t.supplier_id
  if (vatType.value) payload.vat_type = vatType.value
  if (remarks.value.trim()) payload.remarks = remarks.value.trim()

  try {
    const { data } = await api.post(
      `/communities/${communityId.value}/cashbook/entries/${props.entry.id}/allocate`,
      payload,
    )
    success(data.message ?? 'Transaction allocated.')
    emit('saved', data.data ?? null)
    emit('close')
  } catch (e) {
    const res = e.response?.data
    error(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not allocate the transaction.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :show="show" title="Allocate Transaction" size="lg" @close="$emit('close')">
    <div v-if="entry" class="space-y-5">
      <!-- Transaction summary -->
      <div class="rounded border border-border bg-muted/30 px-4 py-3 text-sm">
        <div class="flex items-center justify-between">
          <span class="text-muted-foreground">{{ entry.date }}</span>
          <span class="font-semibold tabular-nums" :class="entry.type === 'credit' ? 'text-success' : 'text-destructive'">
            {{ money(entry.signed_amount) }}
          </span>
        </div>
        <p class="text-foreground mt-1">{{ entry.description }}</p>
      </div>

      <LedgerTargetPicker v-model="target" :ledger-options="ledgerOptions" />

      <div>
        <label class="block text-sm font-medium text-fg mb-1.5">VAT Type</label>
        <AppSelect v-model="vatType" :options="vatTypeOptions" placeholder="None" />
      </div>

      <AppInput v-model="remarks" label="Remarks" type="textarea" :rows="2" placeholder="Optional notes…" />
    </div>

    <template #footer>
      <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
      <AppButton variant="primary" :loading="submitting" :disabled="!hasTarget" @click="submit">
        Allocate
      </AppButton>
    </template>
  </AppModal>
</template>
