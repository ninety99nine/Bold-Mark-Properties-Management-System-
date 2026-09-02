<!--
  GeneralLedgerTab — WeConnectU "Financial Setup → General Ledger" clone.

  A single continuous table: bold MAIN account rows (e.g. "1000/000 - INCOME")
  with their sub-account rows beneath, columns GL Account · Financial Category ·
  Account Type · Tax Type. Each row carries a green "Budget Item" bar-chart toggle
  and a pencil "Edit GL account" action. "Add General Ledger Account" and Edit open
  an INLINE form (not a modal) that replaces the list, mirroring WeConnectU exactly.

  Reused for the Reserve Fund Ledger via `fund="reserve"`.

  Endpoints:
    GET  /ledgers?grouped=1&fund=…   (mains with nested sub_accounts)
    GET  /ledgers/options?fund=…
    GET  /ledgers/next-code
    POST /ledgers
    PUT  /ledgers/{id}               (also toggles is_budget_item)
    DELETE /ledgers/{id}
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppConfirm from '@/components/common/AppConfirm.vue'

const props = defineProps({
  fund: { type: String, default: 'main' }, // 'main' | 'reserve'
})

const { success, error } = useToast()

const groups = ref([])
const options = ref(null)
const loading = ref(true)
const view = ref('list') // 'list' | 'add' | 'edit'

const noun = computed(() => (props.fund === 'reserve' ? 'Reserve Fund Ledger' : 'General Ledger'))
const addLabel = computed(() => `Add ${noun.value} Account`)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ledgers', { params: { grouped: 1, fund: props.fund } })
    groups.value = data.data ?? data ?? []
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/ledgers/options', { params: { fund: props.fund } })
    options.value = data.data ?? data ?? null
  } catch {
    options.value = null
  }
}

onMounted(() => { load(); loadOptions() })
watch(() => props.fund, () => { view.value = 'list'; load(); loadOptions() })

// ── Column label helpers (LedgerResource provides ready-made labels) ─────────
const cat  = (l) => l?.financial_category_label ?? '—'
const type = (l) => l?.account_type_label ?? '—'
const tax  = (l) => l?.tax_type_label ?? '—'

// WeConnectU flags these categories "(cannot be allocated to)".
const NON_ALLOCATABLE = ['bank', 'accounts_receivable', 'accounts_payable', 'retained_income']
const cannotAllocate = (l) => NON_ALLOCATABLE.includes(l?.financial_category)

// ── Budget Item toggle ───────────────────────────────────────────────────────
async function toggleBudgetItem(l) {
  const next = !l.is_budget_item
  l.is_budget_item = next // optimistic
  try {
    await api.put(`/ledgers/${l.id}`, { is_budget_item: next })
  } catch (e) {
    l.is_budget_item = !next // revert
    error(e?.response?.data?.message ?? 'Could not update Budget Item.')
  }
}

// ── Inline Add / Edit form ───────────────────────────────────────────────────
const TYPE_OPTIONS = [
  { value: 'main', label: 'Main Account' },
  { value: 'sub',  label: 'Sub-Account' },
]
const LOCK_OPTIONS = [
  { value: true,  label: 'Allow Sub-Accounts to be created under this Main Account' },
  { value: false, label: 'Do not allow Sub-Accounts under this Main Account' },
]

const form = ref(blankForm())
const editing = ref(null)
const saving = ref(false)
const formError = ref(null)
const codeLoading = ref(false)

function blankForm() {
  return {
    structure: 'main',        // main | sub
    parent_id: null,
    prefix: '',               // main: 4-char code prefix → "{prefix}/000"
    code: '',                 // sub: auto-generated (read-only)
    name: '',
    account_type: 'income_statement',
    financial_category: null,
    tax_type: null,
    allow_sub_accounts: true,
    is_budget_item: false,
  }
}

const isSub = computed(() => form.value.structure === 'sub')

const mainAccountOptions = computed(() =>
  (options.value?.main_accounts ?? []).map((m) => ({ value: m.id, label: m.label ?? `${m.code} - ${m.name ?? ''}`.trim() })),
)
const categoryOptions = computed(() => options.value?.financial_categories ?? [])
const accountTypeOptions = computed(() => options.value?.account_types ?? [])
const taxTypeOptions = computed(() => options.value?.tax_types ?? [])

const formTitle = computed(() => {
  if (view.value === 'edit' && editing.value) return `${editing.value.code} - ${editing.value.name}`
  return addLabel.value
})

function openAdd() {
  editing.value = null
  formError.value = null
  form.value = blankForm()
  view.value = 'add'
  fetchNextCode()
}

function openEdit(l) {
  editing.value = l
  formError.value = null
  form.value = {
    structure: l.parent_id ? 'sub' : 'main',
    parent_id: l.parent_id ?? null,
    prefix: (l.code || '').split('/')[0] ?? '',
    code: l.code ?? '',
    name: l.name ?? '',
    account_type: l.account_type ?? 'income_statement',
    financial_category: l.financial_category ?? null,
    tax_type: l.tax_type ?? null,
    allow_sub_accounts: l.allow_sub_accounts !== false,
    is_budget_item: !!l.is_budget_item,
  }
  view.value = 'edit'
}

function backToList() {
  view.value = 'list'
  editing.value = null
}

// Auto-generate the sub-account number from the chosen parent.
async function fetchNextCode() {
  if (view.value === 'edit' || !isSub.value || !form.value.parent_id) return
  codeLoading.value = true
  form.value.code = ''
  try {
    const { data } = await api.get('/ledgers/next-code', {
      params: { fund: props.fund, type: 'sub', main_account_id: form.value.parent_id },
    })
    form.value.code = data.code ?? data.data?.code ?? ''
  } catch {
    form.value.code = ''
  } finally {
    codeLoading.value = false
  }
}

watch(() => form.value.structure, () => { if (view.value === 'add') fetchNextCode() })
watch(() => form.value.parent_id, () => { if (view.value === 'add' && isSub.value) fetchNextCode() })

async function submit() {
  formError.value = null

  if (view.value === 'edit') {
    if (!form.value.name.trim()) { formError.value = 'Please enter an account name.'; return }
    saving.value = true
    try {
      await api.put(`/ledgers/${editing.value.id}`, {
        name: form.value.name.trim(),
        account_type: form.value.account_type,
        financial_category: form.value.financial_category || null,
        tax_type: form.value.tax_type || null,
        allow_sub_accounts: isSub.value ? undefined : !!form.value.allow_sub_accounts,
      })
      success('Account updated.')
      await load(); await loadOptions()
      backToList()
    } catch (e) {
      formError.value = e?.response?.data?.message ?? 'Failed to update account.'
    } finally {
      saving.value = false
    }
    return
  }

  // Add
  if (isSub.value && !form.value.parent_id) { formError.value = 'Please select a main account.'; return }
  if (!isSub.value && !form.value.prefix.trim()) { formError.value = 'Please enter an account number.'; return }
  if (!form.value.name.trim()) { formError.value = 'Please enter an account name.'; return }

  saving.value = true
  try {
    await api.post('/ledgers', {
      type: form.value.structure,
      fund: props.fund,
      name: form.value.name.trim(),
      prefix: isSub.value ? undefined : form.value.prefix.trim(),
      parent_id: isSub.value ? form.value.parent_id : undefined,
      account_type: form.value.account_type,
      financial_category: form.value.financial_category || null,
      tax_type: form.value.tax_type || null,
      allow_sub_accounts: isSub.value ? undefined : !!form.value.allow_sub_accounts,
    })
    success('Account added.')
    await load(); await loadOptions()
    backToList()
  } catch (e) {
    formError.value = e?.response?.data?.message ?? 'Failed to add account.'
  } finally {
    saving.value = false
  }
}

// ── Delete (from the edit form, non-system accounts only) ─────────────────────
const confirmOpen = ref(false)
function confirmDelete() { confirmOpen.value = true }
async function doDelete() {
  confirmOpen.value = false
  try {
    await api.delete(`/ledgers/${editing.value.id}`)
    success('Account deleted.')
    await load(); await loadOptions()
    backToList()
  } catch (e) {
    error(e?.response?.data?.message ?? 'Could not delete account.')
  }
}
</script>

<template>
  <div class="rounded-lg border border-border bg-white">
    <div class="px-6 pt-5 pb-4 border-b border-border flex items-center justify-between">
      <h2 class="text-lg font-semibold text-navy-dark">{{ noun }}</h2>
    </div>

    <!-- ────────────── LIST VIEW ────────────── -->
    <div v-if="view === 'list'" class="p-6">
      <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-medium text-[#2f6fb0] hover:text-[#245488] mb-4"
        @click="openAdd"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2Z"/></svg>
        {{ addLabel }}
      </button>

      <div v-if="loading" class="py-12 text-center text-sm text-muted-foreground">Loading…</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b-2 border-border">
              <th class="py-2.5 pr-3 text-left font-bold text-navy-dark">{{ noun }} Account</th>
              <th class="py-2.5 px-3 w-16"></th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Financial Category</th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Account Type</th>
              <th class="py-2.5 px-3 text-left font-bold text-navy-dark">Tax Type</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="g in groups" :key="g.id">
              <!-- MAIN account row -->
              <tr class="group border-b border-border/60 bg-muted/30">
                <td class="py-2.5 pr-3">
                  <span class="font-bold text-[#2f6fb0]">{{ g.main.code }} - {{ g.main.name }}</span>
                </td>
                <td class="py-2.5 px-3">
                  <div class="flex items-center justify-end gap-2">
                    <button type="button" class="opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-navy-dark" title="Edit GL account" @click="openEdit(g.main)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button
                      type="button"
                      :title="g.main.is_budget_item ? 'Budget Item - Click to change' : 'NOT a Budget Item - Click to change'"
                      :class="g.main.is_budget_item ? 'text-success' : 'text-muted-foreground/50 hover:text-muted-foreground'"
                      @click="toggleBudgetItem(g.main)"
                    >
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </button>
                  </div>
                </td>
                <td class="py-2.5 px-3 font-semibold text-navy-dark">
                  {{ cat(g.main) }}
                  <span v-if="cannotAllocate(g.main)" class="block text-xs font-normal text-red-600">(cannot be allocated to)</span>
                </td>
                <td class="py-2.5 px-3 font-semibold text-navy-dark">{{ type(g.main) }}</td>
                <td class="py-2.5 px-3 font-semibold text-navy-dark">{{ tax(g.main) }}</td>
              </tr>

              <!-- SUB account rows -->
              <tr v-for="s in g.sub_accounts" :key="s.id" class="group border-b border-border/60 hover:bg-muted/20">
                <td class="py-2.5 pr-3">
                  <button type="button" class="text-left text-[#2f6fb0] hover:underline" @click="openEdit(s)">{{ s.code }} - {{ s.name }}</button>
                </td>
                <td class="py-2.5 px-3">
                  <div class="flex items-center justify-end gap-2">
                    <button type="button" class="opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-navy-dark" title="Edit GL account" @click="openEdit(s)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button
                      type="button"
                      :title="s.is_budget_item ? 'Budget Item - Click to change' : 'NOT a Budget Item - Click to change'"
                      :class="s.is_budget_item ? 'text-success' : 'text-muted-foreground/50 hover:text-muted-foreground'"
                      @click="toggleBudgetItem(s)"
                    >
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </button>
                  </div>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">
                  {{ cat(s) }}
                  <span v-if="cannotAllocate(s)" class="block text-xs text-red-600">(cannot be allocated to)</span>
                </td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ type(s) }}</td>
                <td class="py-2.5 px-3 text-muted-foreground">{{ tax(s) }}</td>
              </tr>
            </template>

            <tr v-if="!groups.length">
              <td colspan="5" class="py-10 text-center text-sm text-muted-foreground">
                No ledger accounts yet. Click “{{ addLabel }}” to create one.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ────────────── ADD / EDIT FORM ────────────── -->
    <div v-else class="p-6">
      <h3 class="text-lg font-semibold text-navy-dark mb-5">{{ formTitle }}</h3>

      <div v-if="formError" class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ formError }}
      </div>

      <div class="space-y-5 max-w-3xl">
        <!-- Type (add only) -->
        <div v-if="view === 'add'" class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Type</label>
          <AppSelect v-model="form.structure" :options="TYPE_OPTIONS" />
        </div>

        <!-- Main Account (sub only) -->
        <div v-if="isSub" class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Main Account</label>
          <AppSelect v-model="form.parent_id" :options="mainAccountOptions" placeholder="Nothing selected" :disabled="view === 'edit'" />
        </div>

        <!-- Account Number -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">{{ view === 'edit' && !isSub ? 'Main Account Number' : 'Account Number' }}</label>
          <!-- Add + Main: editable prefix -->
          <div v-if="view === 'add' && !isSub" class="flex items-center gap-2">
            <AppInput v-model="form.prefix" placeholder="e.g. 3000" class="flex-1" />
            <span class="text-sm font-mono text-muted-foreground">/000</span>
          </div>
          <!-- Otherwise read-only code -->
          <div v-else class="flex items-center h-11 px-4 rounded border border-border bg-muted/40 text-sm font-mono text-muted-foreground">
            <span v-if="codeLoading">Generating…</span>
            <span v-else>{{ (view === 'edit' && !isSub) ? form.prefix : (form.code || '—') }}</span>
          </div>
        </div>

        <!-- Account Name -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Account Name</label>
          <AppInput v-model="form.name" placeholder="Account name" />
        </div>

        <!-- Account Type -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Account Type</label>
          <AppSelect v-model="form.account_type" :options="accountTypeOptions" placeholder="Nothing selected" />
        </div>

        <!-- Financial Category -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Financial Category</label>
          <AppSelect v-model="form.financial_category" :options="categoryOptions" placeholder="Nothing selected" />
        </div>

        <!-- Tax Type -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Tax Type</label>
          <AppSelect v-model="form.tax_type" :options="taxTypeOptions" placeholder="Nothing selected" />
        </div>

        <!-- Editing / Locking (main only) -->
        <div v-if="!isSub" class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] md:items-center gap-y-1.5 gap-x-6">
          <label class="text-sm text-foreground">Editing / Locking</label>
          <AppSelect v-model="form.allow_sub_accounts" :options="LOCK_OPTIONS" />
        </div>
      </div>

      <div class="flex items-center justify-between mt-8">
        <button type="button" class="inline-flex items-center gap-1.5 rounded bg-[#5b8fc7] hover:bg-[#4d7db0] text-white text-sm font-medium px-4 h-10" @click="backToList">
          « Back to {{ noun }}
        </button>

        <div class="flex items-center gap-3">
          <button
            v-if="view === 'edit' && editing && !editing.is_system"
            type="button"
            class="text-sm font-medium text-red-600 hover:text-red-700"
            @click="confirmDelete"
          >
            Delete
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded bg-navy-dark hover:bg-navy text-white text-sm font-medium px-5 h-10 disabled:opacity-60"
            :disabled="saving"
            @click="submit"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ view === 'edit' ? 'Update Account' : 'Add Account' }}
          </button>
        </div>
      </div>
    </div>

    <AppConfirm
      :show="confirmOpen"
      title="Delete Ledger Account"
      :message="editing ? `Delete “${editing.name}”? This cannot be undone.` : ''"
      confirm-label="Delete"
      danger
      @confirm="doDelete"
      @cancel="confirmOpen = false"
    />
  </div>
</template>
