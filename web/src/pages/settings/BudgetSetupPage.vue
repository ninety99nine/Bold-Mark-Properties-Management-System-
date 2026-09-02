<!--
  BudgetSetupPage — routed at /settings/finance/budget.

  Standalone "Budget Setup" page that mounts the SAME shared BudgetTab component
  used by the Financial page's Budget tab, so both entry points land on identical
  content. Includes the Financial Year / Budget Period selector + "all transactions
  allocated" indicator.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import AppSelect from '@/components/common/AppSelect.vue'
import BudgetTab from '@/components/settings/financial/BudgetTab.vue'

const community = useCommunityStore()

const periods = ref([])
const selectedYear = ref(null)
const allAllocated = ref(false)

const cid = computed(() => community.selectedId)

const yearOptions = computed(() =>
  periods.value.map(p => ({ value: String(p.year), label: p.label ?? String(p.year) })),
)

async function loadYears() {
  if (!cid.value) return
  try {
    const { data } = await api.get(`/communities/${cid.value}/financial-years`)
    const payload = data.data ?? data ?? {}
    periods.value = payload.periods ?? []
    allAllocated.value = !!payload.all_transactions_allocated
    const current = periods.value.find(p => p.is_current)
    selectedYear.value = String(payload.current_year ?? current?.year ?? periods.value[0]?.year ?? '')
  } catch {
    periods.value = []
  }
}

onMounted(loadYears)
watch(cid, loadYears)
</script>

<template>
  <div class="space-y-6 pb-8">
    <div>
      <p class="text-sm text-muted-foreground">
        Finance <span class="mx-1">→</span> Setup <span class="mx-1">→</span>
        <span class="text-foreground font-medium">Budget</span>
      </p>
      <h1 class="font-body font-bold text-2xl text-foreground mt-1">Budget Setup</h1>
      <p class="text-sm text-muted-foreground">
        Set income and expense budgets per account
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <div v-if="!cid" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to manage its budget.
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center gap-4">
        <div class="w-64">
          <AppSelect v-model="selectedYear" :options="yearOptions" label="Financial Year / Budget Period" placeholder="Select period…" />
        </div>
        <span v-if="allAllocated" class="inline-flex items-center gap-1.5 self-end h-11 rounded-full bg-success/10 px-3 text-xs font-medium text-success">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          All transactions allocated
        </span>
      </div>

      <BudgetTab :year="selectedYear" fund="main" />
    </template>
  </div>
</template>
