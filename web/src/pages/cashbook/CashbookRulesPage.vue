<script setup>
/**
 * Cashbook — Edit Rules (allocation rules).
 *
 * WeConnectU-parity list of a community's cashbook allocation rules. Each rule
 * matches bank descriptions (starts-with / contains) and auto-allocates matching
 * transactions to a ledger target, optionally restricted to receipts, payments
 * and specific cashbooks. Reached via Cashbook → "Cashbook Options → Edit Rules".
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useCommunityStore } from '@/stores/community'
import { useToast } from '@/composables/useToast'
import AppButton                from '@/components/common/AppButton.vue'
import AppBadge                 from '@/components/common/AppBadge.vue'
import AppDropdown              from '@/components/common/AppDropdown.vue'
import AppDropdownItem          from '@/components/common/AppDropdownItem.vue'
import CreateAllocationRuleModal from '@/components/cashbook/CreateAllocationRuleModal.vue'

const router         = useRouter()
const communityStore = useCommunityStore()
const { success, error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)
const inCommunity = computed(() => communityId.value != null)

const rules   = ref([])
const loading = ref(false)
const ledgerOptions = ref(null)

const showModal   = ref(false)
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

function openCreate() { editingRule.value = null; showModal.value = true }
function openEdit(rule) { editingRule.value = rule; showModal.value = true }

function onSaved() { loadRules() }

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
  if (r.description_contains)    parts.push(`Contains "${r.description_contains}"`)
  return parts.join(', ') || '—'
}

function cashbookNames(r) {
  if (!r.bank_account_ids?.length) return 'All cashbooks'
  return `${r.bank_account_ids.length} cashbook(s)`
}

onMounted(() => { loadRules(); loadLedgerOptions() })
watch(communityId, () => { loadRules(); loadLedgerOptions() })
</script>

<template>
  <div class="pb-10">
    <!-- No community selected -->
    <div v-if="!inCommunity" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-14 h-14 rounded-full bg-muted flex items-center justify-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 text-muted-foreground">
          <path d="M4 7V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3"/><path d="M4 7h16v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z"/>
        </svg>
      </div>
      <h2 class="font-body font-bold text-lg text-foreground">Select a community</h2>
      <p class="text-sm text-muted-foreground mt-1 max-w-sm">Allocation rules are managed per community.</p>
    </div>

    <template v-else>
      <!-- Heading -->
      <div class="flex items-center justify-between mb-5">
        <div>
          <button type="button" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mb-2" @click="router.push('/cashbook')">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Cashbook
          </button>
          <h1 class="font-body font-normal text-3xl text-foreground">Allocation Rules</h1>
        </div>
        <AppButton variant="primary" @click="openCreate">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM11 7a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
          Add Rule
        </AppButton>
      </div>

      <div class="rounded-lg border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Description Match</th>
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Allocates To</th>
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Applies To</th>
                <th class="text-left py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Cashbooks</th>
                <th class="text-right py-3 px-6 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sort</th>
                <th class="py-3 px-6 w-12"></th>
              </tr>
            </thead>
            <tbody>
              <!-- Loading -->
              <template v-if="loading">
                <tr v-for="n in 3" :key="'s' + n" class="border-b border-border last:border-0">
                  <td class="py-3 px-6" colspan="6"><div class="h-4 w-full bg-muted rounded animate-pulse" /></td>
                </tr>
              </template>

              <!-- Empty -->
              <tr v-else-if="!rules.length">
                <td colspan="6" class="py-16 text-center">
                  <p class="text-sm font-medium text-foreground">No allocation rules yet.</p>
                  <p class="text-sm text-muted-foreground mt-1">Create a rule to auto-allocate matching bank transactions.</p>
                  <AppButton variant="outline" class="mt-4" @click="openCreate">Add your first rule</AppButton>
                </td>
              </tr>

              <!-- Rows -->
              <tr v-for="r in rules" :key="r.id" class="border-b border-border last:border-0 hover:bg-muted/40 transition-colors">
                <td class="py-3 px-6 text-foreground">{{ matchText(r) }}</td>
                <td class="py-3 px-6 text-foreground">{{ r.account_label || '—' }}</td>
                <td class="py-3 px-6">
                  <div class="flex flex-wrap gap-1.5">
                    <AppBadge v-if="r.apply_to_positive" variant="success" size="sm">Receipts</AppBadge>
                    <AppBadge v-if="r.apply_to_negative" variant="danger" size="sm">Payments</AppBadge>
                  </div>
                </td>
                <td class="py-3 px-6 text-muted-foreground">{{ cashbookNames(r) }}</td>
                <td class="py-3 px-6 text-right text-foreground tabular-nums">{{ r.sort_order ?? 0 }}</td>
                <td class="py-3 px-6 text-right">
                  <AppDropdown align="right">
                    <template #trigger="{ toggle }">
                      <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded hover:bg-muted" @click="toggle" aria-label="Rule actions">
                        <svg class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                      </button>
                    </template>
                    <template #default="{ close }">
                      <AppDropdownItem label="Edit"   @click="close(); openEdit(r)" />
                      <AppDropdownItem label="Delete" variant="danger" @click="close(); deleteRule(r)" />
                    </template>
                  </AppDropdown>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <CreateAllocationRuleModal
      :show="showModal"
      :rule="editingRule"
      :ledger-options="ledgerOptions"
      @close="showModal = false"
      @saved="onSaved"
    />
  </div>
</template>
