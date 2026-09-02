<!--
  Supplier Invoices (GRVs) — strict WeConnectU clone (Bold Mark branding).

  Faithful clone of WeConnectU Suppliers → Invoice: a Drafts / Created list
  filtered by month + supplier + type, and the "Upload Supplier Invoice" capture
  modal (source-document upload, supplier picker, references, VAT-inclusive
  account lines, live Invoice Total, Previous Invoices / Budget tabs, Save As
  Draft / Create). A Created GRV posts a credit to the supplier ledger.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api           from '@/composables/useApi'
import { useToast }  from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppModal        from '@/components/common/AppModal.vue'
import AppButton       from '@/components/common/AppButton.vue'
import AppSelect       from '@/components/common/AppSelect.vue'
import AppInput        from '@/components/common/AppInput.vue'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppAsyncSelect   from '@/components/common/AppAsyncSelect.vue'
import AppDatePicker   from '@/components/common/AppDatePicker.vue'

const community = useCommunityStore()
const { success, error: toastError } = useToast()

const communityId = computed(() => community.selectedId)

// ── List state ────────────────────────────────────────────────────────────
const tab      = ref('created')          // 'draft' | 'created'
const month    = ref(currentMonth())     // YYYY-MM
const invoices = ref([])
const loading  = ref(false)

function currentMonth() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

// ── Month picker (WeConnectU-style popover: year nav + Jan–Dec grid) ─────────
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const monthOpen     = ref(false)
const pickerYear    = ref(Number(month.value.slice(0, 4)))
const selectedYear  = computed(() => Number(month.value.slice(0, 4)))
const selectedMonth = computed(() => Number(month.value.slice(5, 7)))   // 1–12

function toggleMonthPicker() {
  if (!monthOpen.value) pickerYear.value = selectedYear.value
  monthOpen.value = !monthOpen.value
}
function pickMonth(idx) {   // idx 0–11
  month.value = `${pickerYear.value}-${String(idx + 1).padStart(2, '0')}`
  monthOpen.value = false
}

function money(n) {
  return 'R' + (Number(n) || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const rowsFrom = (res) => res?.data?.data ?? res?.data ?? []

async function loadInvoices() {
  if (!communityId.value) return
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/supplier-invoices`, {
      params: { status: tab.value, month: month.value, _per_page: 100 },
    })
    invoices.value = data.data ?? []
  } catch (e) {
    toastError('Could not load supplier invoices.')
  } finally {
    loading.value = false
  }
}

function primaryAccount(inv) {
  const item = inv.items?.[0]
  return item?.account_label || item?.account_name || ''
}

// ── Reference data (suppliers + chart of accounts) ─────────────────────────
const suppliers = ref([])
const ledgers   = ref([])

const supplierOption = (s) => ({ value: s.id, label: `${s.supplier_code} - ${s.name}` })

const supplierOptions = computed(() => suppliers.value.map(supplierOption))

// API-backed search for the supplier picker (debounced inside AppAsyncSelect).
async function searchSuppliers(query) {
  if (!communityId.value) return []
  const { data } = await api.get('/suppliers', {
    params: { community_id: communityId.value, search: query, _per_page: 50 },
  })
  return rowsFrom({ data }).map(supplierOption)
}

const ledgerOptions = computed(() => {
  const coded = ledgers.value.filter((l) => l.code)
  const source = coded.length ? coded : ledgers.value
  return source.map((l) => ({
    value: l.id,
    label: l.code ? `${l.code} - ${l.name}` : l.name,
    category: l.category || 'Other Accounts',
  }))
})

async function loadRefs() {
  if (!communityId.value) return
  try {
    const [s, l] = await Promise.all([
      api.get('/suppliers', { params: { community_id: communityId.value, _per_page: 500 } }),
      api.get('/ledgers',   { params: { _per_page: 500, is_active: true, postable: 1 } }),
    ])
    suppliers.value = rowsFrom(s)
    ledgers.value   = rowsFrom(l)
  } catch { /* pickers just stay empty */ }
}

// ── Create modal ────────────────────────────────────────────────────────────
const modalOpen  = ref(false)
const submitting = ref(false)
const bottomTab  = ref('previous')       // 'previous' | 'budget'

function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function blankLine() {
  return { ledger_id: '', description: '', amount: '0.00' }
}

const form = ref({
  supplier_id:            '',
  invoice_date:           today(),
  source_document_number: '',
  our_reference:          '',
  supplier_reference:     '',
})
const lines = ref([blankLine()])
const files = ref([])
const dragOver = ref(false)

const invoiceTotal = computed(() =>
  lines.value.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0),
)

// Previous invoices for the selected supplier (created GRVs).
const previousInvoices = ref([])
async function loadPreviousInvoices() {
  previousInvoices.value = []
  if (!communityId.value || !form.value.supplier_id) return
  try {
    const { data } = await api.get(`/communities/${communityId.value}/supplier-invoices`, {
      params: { status: 'created', supplier_id: form.value.supplier_id, _per_page: 10 },
    })
    previousInvoices.value = data.data ?? []
  } catch { /* ignore */ }
}
watch(() => form.value.supplier_id, loadPreviousInvoices)

// Budget rows for the selected ledger accounts (figures not yet modelled → 0.00,
// matching the WeConnectU sample which reports R0.00 across the board).
const budgetRows = computed(() =>
  lines.value
    .filter((l) => l.ledger_id)
    .map((l) => {
      const led = ledgers.value.find((x) => x.id === l.ledger_id)
      return {
        description: led ? `${led.code}: ${led.name}` : 'Account',
        budget: 0, actual: 0, remaining: 0,
      }
    }),
)

function openModal() {
  form.value = { supplier_id: '', invoice_date: today(), source_document_number: '', our_reference: '', supplier_reference: '' }
  lines.value = [blankLine()]
  files.value = []
  previousInvoices.value = []
  bottomTab.value = 'previous'
  modalOpen.value = true
}

function addLine()          { lines.value.push(blankLine()) }
function removeLine(i)       { lines.value.length === 1 ? (lines.value[0] = blankLine()) : lines.value.splice(i, 1) }

function onDrop(e) {
  dragOver.value = false
  const dropped = Array.from(e.dataTransfer?.files ?? [])
  files.value.push(...dropped)
}
function onFilePicked(e) { files.value.push(...Array.from(e.target.files ?? [])) }
function removeFile(i)   { files.value.splice(i, 1) }

async function save(status) {
  if (!form.value.supplier_id) { toastError('Select a supplier.'); return }
  const validLines = lines.value.filter((l) => l.ledger_id || parseFloat(l.amount))
  if (!validLines.length) { toastError('Add at least one line.'); return }

  submitting.value = true
  const fd = new FormData()
  fd.append('supplier_id', form.value.supplier_id)
  fd.append('status', status)
  fd.append('type', 'adhoc')
  fd.append('invoice_date', form.value.invoice_date)
  if (form.value.source_document_number) fd.append('source_document_number', form.value.source_document_number)
  if (form.value.our_reference)          fd.append('our_reference', form.value.our_reference)
  if (form.value.supplier_reference)     fd.append('supplier_reference', form.value.supplier_reference)

  const items = validLines.map((l) => ({
    ledger_id:   l.ledger_id || null,
    description: l.description || null,
    quantity:    1,
    unit_price:  parseFloat(l.amount) || 0,
  }))
  fd.append('items', JSON.stringify(items))
  files.value.forEach((f) => fd.append('attachments[]', f))

  try {
    const { data } = await api.post(`/communities/${communityId.value}/supplier-invoices`, fd)
    const grv = data.data?.grv_number ?? ''
    success(`Supplier invoice ${grv} ${status === 'draft' ? 'saved as draft' : 'created'}.`.trim())
    modalOpen.value = false
    if (status === tab.value) loadInvoices()
    else { tab.value = status; loadInvoices() }
  } catch (err) {
    const res = err.response?.data
    toastError(res?.errors ? Object.values(res.errors)[0]?.[0] : (res?.message ?? 'Could not save the supplier invoice.'))
  } finally {
    submitting.value = false
  }
}

// ── Init ────────────────────────────────────────────────────────────────────
function boot() { loadInvoices(); loadRefs() }
onMounted(() => { if (!community.loaded) community.fetch(); boot() })
watch(communityId, boot)
watch([tab, month], loadInvoices)
</script>

<template>
  <div class="p-6">
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to capture supplier invoices.
    </div>

    <template v-else>
      <h1 class="mb-4 font-body text-2xl font-bold text-navy-dark">Supplier Invoices</h1>

      <div class="rounded-lg border border-border bg-white">
        <!-- Tabs + toolbar -->
        <div class="flex items-center justify-between border-b border-border px-5 pt-3">
          <div class="flex gap-6">
            <button type="button" class="border-b-2 pb-2 text-sm font-semibold transition-colors"
                    :class="tab === 'draft' ? 'border-[#2f6fb0] text-[#2f6fb0]' : 'border-transparent text-muted-foreground hover:text-navy'"
                    @click="tab = 'draft'">Drafts</button>
            <button type="button" class="border-b-2 pb-2 text-sm font-semibold transition-colors"
                    :class="tab === 'created' ? 'border-[#2f6fb0] text-[#2f6fb0]' : 'border-transparent text-muted-foreground hover:text-navy'"
                    @click="tab = 'created'">Created</button>
          </div>
          <div class="flex items-center gap-2 pb-2">
            <!-- Month picker: click to open a year-nav + Jan–Dec calendar popover -->
            <div class="relative">
              <button type="button"
                      class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm text-foreground hover:border-navy focus:border-navy focus:outline-none"
                      @click="toggleMonthPicker">
                <svg class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                <span>{{ month }}</span>
              </button>
              <template v-if="monthOpen">
                <div class="fixed inset-0 z-20" @click="monthOpen = false"></div>
                <div class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-border bg-white p-3 shadow-lg">
                  <div class="mb-2 flex items-center justify-between">
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted" title="Previous year" @click="pickerYear--">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <span class="text-sm font-semibold text-navy-dark">{{ pickerYear }}</span>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted" title="Next year" @click="pickerYear++">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" /></svg>
                    </button>
                  </div>
                  <div class="grid grid-cols-3 gap-1.5">
                    <button v-for="(m, i) in MONTHS" :key="m" type="button"
                            class="rounded-md py-2 text-sm transition-colors"
                            :class="(pickerYear === selectedYear && selectedMonth === i + 1) ? 'bg-navy font-semibold text-white' : 'text-foreground hover:bg-muted'"
                            @click="pickMonth(i)">{{ m }}</button>
                  </div>
                </div>
              </template>
            </div>
            <button type="button" class="inline-flex h-9 w-10 items-center justify-center rounded-md bg-[#2f6fb0] text-white hover:bg-[#2a63a0]" title="New supplier invoice" @click="openModal">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            </button>
          </div>
        </div>

        <!-- List -->
        <div class="p-4">
          <div v-if="loading" class="py-10 text-center text-muted-foreground">Loading…</div>
          <div v-else-if="invoices.length === 0" class="py-10 text-center text-muted-foreground">No results.</div>
          <div v-else class="space-y-3">
            <div v-for="inv in invoices" :key="inv.id" class="flex items-center gap-6 rounded-lg border border-border px-5 py-4">
              <div class="w-24 shrink-0 text-center">
                <span class="inline-block rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">{{ inv.type_label }}</span>
                <div class="mt-1 text-xs text-muted-foreground">{{ inv.invoice_date }}</div>
              </div>
              <div class="min-w-[220px] font-semibold text-[#2f6fb0]">{{ inv.supplier_code }} : {{ inv.supplier_name }}</div>
              <div class="min-w-[240px] text-sm text-muted-foreground">
                <div>Supplier Ref: <span class="text-foreground">{{ [inv.source_document_number, inv.supplier_reference].filter(Boolean).join('/') || '—' }}</span></div>
                <div>Our Ref: <span class="text-foreground">{{ inv.our_reference || '—' }}</span></div>
              </div>
              <div class="flex-1">
                <div class="font-bold text-navy-dark">{{ money(inv.total) }}</div>
                <div class="text-sm text-muted-foreground">{{ primaryAccount(inv) }}</div>
              </div>
              <div class="shrink-0 text-right">
                <span class="inline-block rounded-md px-3 py-1 text-sm font-semibold"
                      :class="inv.status === 'created' ? 'bg-[#e6f6ed] text-[#2c9b67]' : 'bg-muted text-muted-foreground'">
                  {{ inv.status_label }}
                </span>
                <div class="mt-1 text-xs text-muted-foreground">{{ inv.grv_number }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════ Create modal ══════════ -->
      <AppModal :show="modalOpen" title="Upload Supplier Invoice" size="xl" @close="modalOpen = false">
        <div class="space-y-5">
          <!-- Source documents -->
          <div>
            <label class="mb-1 block text-sm font-medium text-foreground">Source Document(s)</label>
            <div class="rounded-lg border-2 border-dashed px-6 py-6 text-center transition-colors"
                 :class="dragOver ? 'border-amber bg-amber/5' : 'border-border'"
                 @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop">
              <p class="text-sm text-muted-foreground">
                Drag &amp; Drop your files or
                <label class="cursor-pointer font-medium text-[#2f6fb0] underline">
                  Browse
                  <input type="file" multiple class="hidden" @change="onFilePicked" />
                </label>
              </p>
              <ul v-if="files.length" class="mt-2 space-y-1 text-left text-sm">
                <li v-for="(f, i) in files" :key="i" class="flex items-center justify-between rounded bg-muted/40 px-3 py-1">
                  <span class="text-foreground">{{ f.name }}</span>
                  <button type="button" class="text-destructive hover:underline" @click="removeFile(i)">Remove</button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Supplier -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-foreground">Supplier</label>
            <AppAsyncSelect
              v-model="form.supplier_id"
              :options="supplierOptions"
              :fetcher="searchSuppliers"
              placeholder="Nothing selected"
              search-placeholder="Start typing to search…"
            />
          </div>

          <!-- Date + Source Doc Number -->
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <AppDatePicker v-model="form.invoice_date" label="Date" />
            <AppInput v-model="form.source_document_number" label="Source Document Number" />
          </div>

          <!-- References -->
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <AppInput v-model="form.our_reference" label="Our Reference" />
            <AppInput v-model="form.supplier_reference" label="Supplier Reference" />
          </div>

          <!-- Lines -->
          <div class="space-y-3">
            <div v-for="(l, i) in lines" :key="i" class="flex items-start gap-3">
              <div class="w-[36%]">
                <AppAccountSelect v-model="l.ledger_id" :options="ledgerOptions" placeholder="Select your ledger" />
              </div>
              <div class="flex-1">
                <AppInput v-model="l.description" placeholder="Enter your description" />
              </div>
              <div class="w-40">
                <AppInput v-model="l.amount" type="number" prefix="Incl." />
              </div>
              <button type="button" class="mt-2 text-muted-foreground hover:text-destructive" title="Delete line" @click="removeLine(i)">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14" /></svg>
              </button>
            </div>
            <button type="button" class="text-sm font-medium text-[#2f6fb0] hover:underline" @click="addLine">+ Add line</button>
          </div>

          <!-- Total -->
          <div class="flex items-center justify-between border-t border-border pt-4">
            <span class="text-lg font-bold text-navy-dark">Invoice Total:</span>
            <span class="text-lg font-bold text-navy-dark">{{ money(invoiceTotal) }}</span>
          </div>

          <!-- Bottom tabs -->
          <div>
            <div class="flex gap-6 border-b border-border">
              <button type="button" class="border-b-2 pb-2 text-sm font-semibold transition-colors"
                      :class="bottomTab === 'previous' ? 'border-[#2f6fb0] text-[#2f6fb0]' : 'border-transparent text-muted-foreground hover:text-navy'"
                      @click="bottomTab = 'previous'">Previous Invoices</button>
              <button type="button" class="border-b-2 pb-2 text-sm font-semibold transition-colors"
                      :class="bottomTab === 'budget' ? 'border-[#2f6fb0] text-[#2f6fb0]' : 'border-transparent text-muted-foreground hover:text-navy'"
                      @click="bottomTab = 'budget'">Budget</button>
            </div>

            <div v-if="bottomTab === 'previous'" class="pt-3 text-sm">
              <p v-if="!previousInvoices.length" class="text-muted-foreground">No previous invoices.</p>
              <table v-else class="w-full">
                <tbody>
                  <tr v-for="p in previousInvoices" :key="p.id" class="border-b border-border last:border-0">
                    <td class="py-1.5 font-medium text-navy-dark">{{ p.invoice_date }}</td>
                    <td class="py-1.5 text-[#2f6fb0]">{{ p.supplier_code }} : {{ p.supplier_name }}</td>
                    <td class="py-1.5 text-muted-foreground">{{ primaryAccount(p) }}</td>
                    <td class="py-1.5 text-right font-medium text-navy-dark">{{ money(p.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="pt-3 text-sm">
              <p v-if="!budgetRows.length" class="font-medium text-navy-dark">Select ledger accounts to view budget figures</p>
              <table v-else class="w-full">
                <thead>
                  <tr class="text-muted-foreground">
                    <th class="py-1.5 text-left font-medium">Description</th>
                    <th class="py-1.5 text-right font-medium">YTD Budget</th>
                    <th class="py-1.5 text-right font-medium">YTD Actual</th>
                    <th class="py-1.5 text-right font-medium">YTD Remaining</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(b, i) in budgetRows" :key="i" class="border-t border-border">
                    <td class="py-1.5 font-medium text-navy-dark">{{ b.description }}</td>
                    <td class="py-1.5 text-right">{{ money(b.budget) }}</td>
                    <td class="py-1.5 text-right">{{ money(b.actual) }}</td>
                    <td class="py-1.5 text-right">{{ money(b.remaining) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-2">
            <AppButton variant="secondary" :loading="submitting" @click="save('draft')">Save As Draft</AppButton>
            <AppButton variant="primary" :loading="submitting" @click="save('created')">Create</AppButton>
          </div>
        </div>
      </AppModal>
    </template>
  </div>
</template>
