<!--
  AllocationRulesTab — WeConnectU "Financial Setup → Allocation Rules" clone.

  Embeds the existing community allocation-rules management (same data + modal used
  by the Cashbook → Edit Rules page) directly inside the Financial Setup page, with
  the WeConnectU bulk columns: Active (Update) and Run.

  Endpoints (community-scoped, {c} = community.selectedId):
    GET    communities/{c}/allocation-rules
    POST   communities/{c}/allocation-rules
    PUT    communities/{c}/allocation-rules/{id}
    DELETE communities/{c}/allocation-rules/{id}
    POST   communities/{c}/allocation-rules/{id}/run     (best-effort — retro apply)
    GET    communities/{c}/cashbook/ledger-options
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppDropdown from '@/components/common/AppDropdown.vue'
import AppDropdownItem from '@/components/common/AppDropdownItem.vue'
import CreateAllocationRuleModal from '@/components/cashbook/CreateAllocationRuleModal.vue'

const community = useCommunityStore()
const { success, error: toastError } = useToast()

const communityId = computed(() => community.selectedId)

const rules = ref([])
const loading = ref(false)
const ledgerOptions = ref(null)
const runningId = ref(null)

const showModal = ref(false)
const editingRule = ref(null)

async function loadRules() {
  if (!communityId.value) return
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/allocation-rules`)
    rules.value = data.data ?? data ?? []
  } catch { rules.value = [] } finally {
    loading.value = false
  }
}

async function loadLedgerOptions() {
  if (!communityId.value) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/cashbook/ledger-options`)
    ledgerOptions.value = data
  } catch { /* silent */ }
}

onMounted(() => { loadRules(); loadLedgerOptions() })
watch(communityId, () => { loadRules(); loadLedgerOptions() })

function openCreate() { editingRule.value = null; showModal.value = true }
function openEdit(rule) { editingRule.value = rule; showModal.value = true }
function onSaved() { loadRules() }

async function toggleActive(rule) {
  try {
    await api.put(`/communities/${communityId.value}/allocation-rules/${rule.id}`, {
      is_active: !rule.is_active,
    })
    rule.is_active = !rule.is_active
    success('Rule updated.')
  } catch (e) {
    toastError(e.response?.data?.message ?? 'Could not update the rule.')
  }
}

async function runRule(rule) {
  runningId.value = rule.id
  try {
    await api.post(`/communities/${communityId.value}/allocation-rules/${rule.id}/run`)
    success('Rule applied to matching transactions.')
    loadRules()
  } catch (e) {
    toastError(e.response?.data?.message ?? 'Could not run the rule.')
  } finally {
    runningId.value = null
  }
}

async function deleteRule(rule) {
  if (!window.confirm('Delete this allocation rule?')) return
  try {
    await api.delete(`/communities/${communityId.value}/allocation-rules/${rule.id}`)
    success('Allocation rule deleted.')
    loadRules()
  } catch (e) {
    toastError(e.response?.data?.message ?? 'Could not delete the rule.')
  }
}

function matchText(r) {
  const parts = []
  if (r.description_starts_with) parts.push(`Starts with "${r.description_starts_with}"`)
  if (r.description_contains) parts.push(`Contains "${r.description_contains}"`)
  return parts.join(', ') || '—'
}
function cashbookNames(r) {
  if (!r.bank_account_ids?.length) return 'All cashbooks'
  return `${r.bank_account_ids.length} cashbook(s)`
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-end">
      <AppButton variant="secondary" @click="openCreate">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Rule
      </AppButton>
    </div>

    <div class="rounded-lg border border-border bg-white">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Description Match</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Allocates To</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Applies To</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Cashbooks</th>
              <th class="text-center py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Active</th>
              <th class="text-center py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Run</th>
              <th class="py-3 px-4 w-12"></th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <tr v-for="n in 3" :key="'s' + n" class="border-b border-border last:border-0">
                <td class="py-3 px-4" colspan="7"><div class="h-4 w-full bg-muted rounded animate-pulse" /></td>
              </tr>
            </template>

            <tr v-else-if="!rules.length">
              <td colspan="7" class="py-16 text-center">
                <p class="text-sm font-medium text-foreground">No allocation rules yet.</p>
                <p class="text-sm text-muted-foreground mt-1">Create a rule to auto-allocate matching bank transactions.</p>
                <AppButton variant="outline" class="mt-4" @click="openCreate">Add your first rule</AppButton>
              </td>
            </tr>

            <tr v-for="r in rules" :key="r.id" class="border-b border-border last:border-0 hover:bg-muted/40 transition-colors">
              <td class="py-3 px-4 text-foreground">{{ matchText(r) }}</td>
              <td class="py-3 px-4 text-foreground">{{ r.account_label || '—' }}</td>
              <td class="py-3 px-4">
                <div class="flex flex-wrap gap-1.5">
                  <AppBadge v-if="r.apply_to_positive" variant="success" size="sm">Receipts</AppBadge>
                  <AppBadge v-if="r.apply_to_negative" variant="danger" size="sm">Payments</AppBadge>
                </div>
              </td>
              <td class="py-3 px-4 text-muted-foreground">{{ cashbookNames(r) }}</td>
              <td class="py-3 px-4 text-center">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                  <input type="checkbox" :checked="r.is_active !== false" class="w-5 h-5 rounded accent-[#2f6fb0]" @change="toggleActive(r)" />
                </label>
              </td>
              <td class="py-3 px-4 text-center">
                <AppButton variant="outline" size="sm" :loading="runningId === r.id" @click="runRule(r)">Run</AppButton>
              </td>
              <td class="py-3 px-4 text-right">
                <AppDropdown align="right">
                  <template #trigger="{ toggle }">
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-muted" @click="toggle" aria-label="Rule actions">
                      <svg class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                    </button>
                  </template>
                  <template #default="{ close }">
                    <AppDropdownItem label="Edit" @click="close(); openEdit(r)" />
                    <AppDropdownItem label="Delete" variant="danger" @click="close(); deleteRule(r)" />
                  </template>
                </AppDropdown>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <CreateAllocationRuleModal
      :show="showModal"
      :rule="editingRule"
      :ledger-options="ledgerOptions"
      @close="showModal = false"
      @saved="onSaved"
    />
  </div>
</template>
