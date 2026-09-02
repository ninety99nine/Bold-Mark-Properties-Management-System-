<!--
  SpecifyMonthlyBudgetModal — WeConnectU "Specify Monthly Budget" dialog.

  Opened from the Budget grid when a row's "Equal Monthly Amount" box is unchecked.
  Presents twelve month inputs (Jan <year> … Dec <year>) pre-filled with the row's
  current monthly figures; Apply emits the twelve values so the parent can store
  them and recompute the Per Year total (= their sum).
-->
<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'

const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
const LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const props = defineProps({
  show: Boolean,
  row: { type: Object, default: null }, // { code, name, jan..dec }
  year: { type: [String, Number], default: null },
})

const emit = defineEmits(['close', 'apply'])

// Local editable copy of the twelve months, seeded when the modal opens.
const values = ref(MONTHS.map(() => ''))

watch(
  () => props.show,
  (open) => {
    if (open && props.row) {
      values.value = MONTHS.map((m) => {
        const v = Number(props.row[m] || 0)
        return v ? String(v) : ''
      })
    }
  },
  { immediate: true },
)

function apply() {
  const months = {}
  MONTHS.forEach((m, i) => {
    months[m] = Number(values.value[i]) || 0
  })
  emit('apply', months)
}
</script>

<template>
  <AppModal :show="show" size="xl" @close="$emit('close')">
    <template #header>
      <h3 class="text-base font-bold text-fg" style="font-family: 'DM Sans', sans-serif">
        Specify Monthly Budget
      </h3>
    </template>

    <div v-if="row" class="space-y-5">
      <p class="text-lg font-bold text-navy-dark">{{ row.code }} - {{ row.name }}</p>

      <div class="grid grid-cols-2 gap-x-6 gap-y-4 md:grid-cols-4">
        <div v-for="(label, i) in LABELS" :key="label">
          <label class="block text-sm font-bold text-navy-dark mb-1">{{ label }} {{ year }}</label>
          <input
            v-model="values[i]"
            type="number"
            step="0.01"
            class="w-full h-10 rounded border border-border px-3 text-sm tabular-nums focus:border-primary focus:outline-none"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="apply">Apply</AppButton>
      <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
    </template>
  </AppModal>
</template>
