<!--
  ReportFyPicker — the WeConnectU "Financial Year / Budget Period" dropdown,
  extracted verbatim from AgeAnalysisPage / DetailedCustomerLedgerPage so the
  GL report pages share one implementation. Fetches periods from
  communities/{c}/financial-years and emits the chosen period on select.
-->
<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/composables/useApi'

const props = defineProps({
  communityId: { default: null },
})
const emit = defineEmits(['select'])

const periods           = ref([])
const selectedPeriodKey = ref(null)
const fyOpen            = ref(false)

const pastPeriods   = computed(() => periods.value.filter(p => p.is_past))
const currentPeriod = computed(() => periods.value.find(p => p.is_current) ?? null)
const futurePeriods = computed(() => periods.value.filter(p => p.is_future))
const selectedPeriodLabel = computed(() =>
  periods.value.find(p => p.year === selectedPeriodKey.value)?.label ?? 'Select period',
)

async function load() {
  if (!props.communityId) return
  try {
    const { data } = await api.get(`/communities/${props.communityId}/financial-years`)
    periods.value = data.periods ?? []
    const current = periods.value.find(p => p.is_current)
    if (current) {
      selectedPeriodKey.value = current.year
      emit('select', current)
    }
  } catch { /* selector just stays empty */ }
}

function selectPeriod(p) {
  selectedPeriodKey.value = p.year
  fyOpen.value = false
  emit('select', p)
}

watch(() => props.communityId, () => { periods.value = []; selectedPeriodKey.value = null; load() }, { immediate: true })

defineExpose({ selectedYear: () => selectedPeriodKey.value })
</script>

<template>
  <div>
    <label class="mb-1 block text-xs font-bold text-muted-foreground">Financial Year / Budget Period:</label>
    <div class="relative w-full max-w-md">
      <button type="button" class="flex h-11 w-full items-center justify-between rounded-md border border-border bg-white px-3 text-sm text-foreground focus:border-navy focus:outline-none" @click="fyOpen = !fyOpen">
        <span>{{ selectedPeriodLabel }}</span>
        <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
      </button>
      <div v-if="fyOpen" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-border bg-white py-1 shadow-lg">
        <div v-if="pastPeriods.length" class="px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Past financial years</div>
        <button v-for="p in pastPeriods" :key="p.year" type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="p.year === selectedPeriodKey ? 'bg-muted font-medium' : ''" @click="selectPeriod(p)">{{ p.label }}</button>
        <template v-if="currentPeriod">
          <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Current financial year end</div>
          <button type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="currentPeriod.year === selectedPeriodKey ? 'bg-muted font-medium' : ''" @click="selectPeriod(currentPeriod)">{{ currentPeriod.label }}</button>
        </template>
        <template v-if="futurePeriods.length">
          <div class="border-t border-border px-3 pb-1 pt-2 text-xs font-semibold text-muted-foreground">Future financial years</div>
          <button v-for="p in futurePeriods" :key="p.year" type="button" class="block w-full px-4 py-1.5 text-left text-sm hover:bg-muted" :class="[p.year === selectedPeriodKey ? 'bg-muted font-medium' : '', p.is_setup ? '' : 'text-destructive']" @click="selectPeriod(p)">{{ p.label }}</button>
        </template>
      </div>
    </div>
  </div>
</template>
