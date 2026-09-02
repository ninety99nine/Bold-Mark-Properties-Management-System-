<!--
  CustomerInvoicePage — WeConnectU-style "Customer Invoice" capture screen.

  Faithful clone of the WeConnectU Customers → Invoice page, wired to the Bold
  Mark backend: multi-line account/description/qty/tax/amount rows with live
  sub-total / VAT / total, a customer (unit) picker, bank account, invoice + due
  dates, an optional source-document upload, and e-mail delivery options.

  POSTs multipart/form-data to /invoices/customer-invoice.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import { useAuthStore } from '@/stores/auth'
import AppSelect     from '@/components/common/AppSelect.vue'
import AppAsyncSelect from '@/components/common/AppAsyncSelect.vue'
import AppAccountSelect from '@/components/common/AppAccountSelect.vue'
import AppInput      from '@/components/common/AppInput.vue'
import AppButton     from '@/components/common/AppButton.vue'
import EmailTagsInput from '@/components/common/EmailTagsInput.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'

const { success, error } = useToast()
const community   = useCommunityStore()
const auth        = useAuthStore()

// ── Reference data ──────────────────────────────────────────────────────
const units        = ref([])
const bankAccounts = ref([])
const ledgers      = ref([])
const loadingRefs  = ref(true)

// ── Form state ──────────────────────────────────────────────────────────
function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function plusDays(n) {
  const d = new Date()
  d.setDate(d.getDate() + n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const form = ref({
  unit_id:         '',
  bank_account_id: '',
  invoice_date:    today(),
  due_date:        plusDays(7),
})

const items = ref([blankRow()])

function blankRow() {
  return { ledger_id: '', description: '', quantity: '1', tax_rate: '0', amount: '0.00' }
}

// File upload (max 1)
const file       = ref(null)
const fileInput  = ref(null)
const dragOver   = ref(false)

// E-mail options
const email = ref({
  send:        true,
  attachStmt:  true,
  toCustomer:  true,
  toAlternate: false,
  toMyself:    false,
  toOther:     false,
  otherAddresses: [],
})
const otherAddressesValid = ref(true)

const submitting = ref(false)
const errors     = ref({})

// ── Options ─────────────────────────────────────────────────────────────
const unitOption = (u) => {
  const name = u.owner?.full_name || u.current_occupant?.full_name || 'No owner'
  const code = u.customer_code || u.unit_number
  return { value: u.id, label: `${code} — ${name} · Unit ${u.unit_number}` }
}

const customerOptions = computed(() => units.value.map(unitOption))

// API-backed search for the customer picker (debounced inside AppAsyncSelect).
// Fetched units are merged into `units` so the selected customer's e-mail / unit
// details resolve even when picked from a search hit outside the initial list.
async function searchUnits(query) {
  const communityId = community.selectedId
  if (!communityId) return []
  const { data } = await api.get(`/communities/${communityId}/units`, {
    params: { _search: query, _per_page: 50, _sort: 'unit_number:asc' },
  })
  const found = data?.data ?? []
  mergeUnits(found)
  return found.map(unitOption)
}

function mergeUnits(list) {
  const byId = new Map(units.value.map((u) => [u.id, u]))
  for (const u of list) byId.set(u.id, u)
  units.value = [...byId.values()]
}

const bankOptions = computed(() =>
  bankAccounts.value.map((b) => ({
    value: b.id,
    label: [b.name, b.account_number].filter(Boolean).join(' '),
  }))
)

// Chart-of-accounts options for the Account picker: "code - name" grouped by
// category, mirroring WeConnectU. When a chart of accounts exists we show only
// the coded accounts (the COA); otherwise we fall back to every ledger so the
// picker is never empty.
const ledgerOptions = computed(() => {
  const coded = ledgers.value.filter((l) => l.code)
  const source = coded.length ? coded : ledgers.value
  return source.map((l) => ({
    value: l.id,
    label: l.code ? `${l.code} - ${l.name}` : l.name,
    category: l.category || 'Other Accounts',
  }))
})

const selectedUnit = computed(() => units.value.find((u) => u.id === form.value.unit_id) || null)

// Recipient e-mail addresses derived from the selected customer.
const customerEmail  = computed(() => selectedUnit.value?.owner?.email || selectedUnit.value?.current_occupant?.email || '')
const alternateEmail = computed(() => {
  const o = selectedUnit.value?.owner
  return o?.alt_email || o?.secondary_emails?.[0] || ''
})
const myEmail = computed(() => auth.user?.email || '')

// ── Totals (live) ───────────────────────────────────────────────────────
function lineSubtotal(row) {
  const q = parseFloat(row.quantity) || 0
  const a = parseFloat(row.amount) || 0
  return q * a
}
function lineVat(row) {
  const rate = parseFloat(row.tax_rate) || 0
  return lineSubtotal(row) * rate / 100
}
function lineTotal(row) {
  return lineSubtotal(row) + lineVat(row)
}

const subtotal = computed(() => items.value.reduce((s, r) => s + lineSubtotal(r), 0))
const vatTotal = computed(() => items.value.reduce((s, r) => s + lineVat(r), 0))
const total    = computed(() => subtotal.value + vatTotal.value)

function money(n) {
  return (Number(n) || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Row management ──────────────────────────────────────────────────────
function addRow() {
  items.value.push(blankRow())
}
function removeRow(index) {
  if (items.value.length === 1) {
    items.value[0] = blankRow()
    return
  }
  items.value.splice(index, 1)
}
function moveRow(index, dir) {
  const target = index + dir
  if (target < 0 || target >= items.value.length) return
  const rows = items.value
  ;[rows[index], rows[target]] = [rows[target], rows[index]]
}

// ── File handling ───────────────────────────────────────────────────────
function onFilePicked(e) {
  const picked = e.target.files?.[0]
  if (picked) file.value = picked
}
function onDrop(e) {
  dragOver.value = false
  const dropped = e.dataTransfer?.files?.[0]
  if (dropped) file.value = dropped
}
function clearFile() {
  file.value = null
  if (fileInput.value) fileInput.value.value = ''
}

// ── Data loading ────────────────────────────────────────────────────────
// Each resource loads independently: one failing request must never blank the
// others (e.g. a stale community id shouldn't wipe the Account list).
const rows = (res) => res?.data?.data ?? res?.data ?? []

// Fetch every unit for a community, paging at the endpoint's 200-row cap.
async function loadAllUnits(communityId) {
  const PER_PAGE = 200
  const first = await api.get(`/communities/${communityId}/units`, {
    params: { _per_page: PER_PAGE, page: 1, _sort: 'unit_number:asc' },
  })
  const all = rows(first)
  const lastPage = first?.data?.meta?.last_page ?? 1
  if (lastPage > 1) {
    const more = await Promise.all(
      Array.from({ length: lastPage - 1 }, (_, i) =>
        api.get(`/communities/${communityId}/units`, {
          params: { _per_page: PER_PAGE, page: i + 2, _sort: 'unit_number:asc' },
        }).then(rows),
      ),
    )
    more.forEach((chunk) => all.push(...chunk))
  }
  return all
}

async function loadRefs() {
  loadingRefs.value = true
  const communityId = community.selectedId
  const failed = []

  const jobs = [
    api.get('/ledgers', { params: { _per_page: 500, is_active: true } })
      .then((r) => { ledgers.value = rows(r) })
      .catch(() => failed.push('accounts')),
  ]

  if (communityId) {
    jobs.push(
      // The units endpoint caps _per_page at 200, so page through it to gather
      // every customer rather than requesting an over-limit page (which 422s).
      loadAllUnits(communityId)
        .then((all) => { units.value = all })
        .catch(() => failed.push('customers')),
      api.get('/bank-accounts', { params: { community_id: communityId, _per_page: 100 } })
        .then((r) => {
          bankAccounts.value = rows(r)
          if (bankAccounts.value.length && !form.value.bank_account_id) {
            form.value.bank_account_id = bankAccounts.value[0].id
          }
        })
        .catch(() => failed.push('bank accounts')),
    )
  }

  await Promise.allSettled(jobs)

  if (failed.length) {
    error(`Could not load ${failed.join(', ')}. Please refresh to try again.`)
  }

  loadingRefs.value = false
}

onMounted(loadRefs)
watch(() => community.selectedId, loadRefs)

// ── Validation + submit ─────────────────────────────────────────────────
function validate() {
  const e = {}
  if (!form.value.unit_id) e.unit_id = 'Select a customer.'
  if (!form.value.invoice_date) e.invoice_date = 'Required.'
  if (!form.value.due_date) e.due_date = 'Required.'
  if (form.value.due_date && form.value.invoice_date && form.value.due_date < form.value.invoice_date) {
    e.due_date = 'Due date must be on or after the invoice date.'
  }
  const validRows = items.value.filter((r) => r.ledger_id)
  if (!validRows.length) e.items = 'Add at least one line item with an account.'
  errors.value = e
  return Object.keys(e).length === 0
}

function buildRecipients() {
  const list = []
  if (email.value.toCustomer && customerEmail.value) list.push(customerEmail.value)
  if (email.value.toAlternate && alternateEmail.value) list.push(alternateEmail.value)
  if (email.value.toMyself && myEmail.value) list.push(myEmail.value)
  if (email.value.toOther) email.value.otherAddresses.forEach(a => list.push(a))
  return [...new Set(list)]
}

async function submit() {
  if (!validate()) return
  if (email.value.send && email.value.toOther && !otherAddressesValid.value) {
    error('Please fix the highlighted e-mail address(es) before creating the invoice.')
    return
  }
  submitting.value = true

  const fd = new FormData()
  fd.append('unit_id', form.value.unit_id)
  if (form.value.bank_account_id) fd.append('bank_account_id', form.value.bank_account_id)
  fd.append('invoice_date', form.value.invoice_date)
  fd.append('due_date', form.value.due_date)

  items.value
    .filter((r) => r.ledger_id)
    .forEach((r, i) => {
      fd.append(`items[${i}][ledger_id]`, r.ledger_id)
      fd.append(`items[${i}][description]`, r.description || '')
      fd.append(`items[${i}][quantity]`, String(parseFloat(r.quantity) || 0))
      fd.append(`items[${i}][tax_rate]`, String(parseFloat(r.tax_rate) || 0))
      fd.append(`items[${i}][amount]`, String(parseFloat(r.amount) || 0))
    })

  if (file.value) fd.append('attachment', file.value)

  fd.append('email_invoice', email.value.send ? '1' : '0')
  fd.append('attach_statement', email.value.attachStmt ? '1' : '0')
  if (email.value.send) {
    buildRecipients().forEach((addr, i) => fd.append(`email_recipients[${i}]`, addr))
  }

  try {
    const { data } = await api.post('/invoices/customer-invoice', fd)
    const created = data.data ?? data
    success(`Invoice ${created?.invoice_number ?? ''} created successfully.`.trim())
    resetForm()
  } catch (err) {
    const res = err.response?.data
    if (res?.errors) {
      error(Object.values(res.errors)[0]?.[0] ?? 'Please fix the highlighted fields.')
    } else {
      error(res?.message ?? 'Could not create the invoice.')
    }
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  form.value = { unit_id: '', bank_account_id: bankAccounts.value[0]?.id ?? '', invoice_date: today(), due_date: plusDays(7) }
  items.value = [blankRow()]
  clearFile()
  errors.value = {}
  email.value.otherAddresses = []
  otherAddressesValid.value = true
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- ── Page header ──────────────────────────────────────────────── -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Customer Invoice</h1>
      <p class="text-sm text-muted-foreground">
        Raise an invoice against a customer's account
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- ── No community selected ────────────────────────────────────── -->
    <div
      v-if="!community.selectedId"
      class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground"
    >
      Select a community from the top bar to raise a customer invoice.
    </div>

    <!-- ── Invoice form ─────────────────────────────────────────────── -->
    <div v-else class="rounded-lg border border-border bg-white p-6 space-y-8">
      <!-- Customer / bank / dates -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-5">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-fg">Customer<span class="text-danger ml-0.5">*</span></label>
          <AppAsyncSelect
            v-model="form.unit_id"
            :options="customerOptions"
            :fetcher="searchUnits"
            placeholder="Nothing selected"
            :error="errors.unit_id"
          />
        </div>
        <AppSelect
          v-model="form.bank_account_id"
          label="Bank Account"
          :options="bankOptions"
          placeholder="Nothing selected"
        />
        <AppDatePicker v-model="form.invoice_date" label="Date" required />
        <AppDatePicker v-model="form.due_date" label="Due Date" required />
      </div>

      <!-- File upload -->
      <div>
        <label class="text-sm font-medium text-fg">Upload file <span class="text-muted-foreground font-normal">(max 1 file)</span></label>
        <div
          class="mt-1.5 rounded-lg border-2 border-dashed transition-colors px-6 py-8 text-center"
          :class="dragOver ? 'border-amber bg-amber/5' : 'border-border'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <p v-if="!file" class="text-sm text-muted-foreground italic">Drag and drop the customer invoice file here</p>
          <div v-else class="flex items-center justify-center gap-3 text-sm text-foreground">
            <svg class="w-4 h-4 text-amber" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V18a2 2 0 01-2 2z"/></svg>
            <span class="font-medium">{{ file.name }}</span>
            <button type="button" class="text-danger hover:underline" @click="clearFile">Remove</button>
          </div>
        </div>
        <div class="mt-3">
          <input ref="fileInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" @change="onFilePicked" />
          <AppButton variant="secondary" size="sm" @click="fileInput?.click()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Click to select file
          </AppButton>
        </div>
      </div>

      <!-- ── Line items ─────────────────────────────────────────────── -->
      <div>
        <div class="overflow-x-auto rounded-lg border border-border">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-muted/40 text-left">
                <th class="px-4 py-3 font-semibold text-foreground w-[26%]">Account</th>
                <th class="px-4 py-3 font-semibold text-foreground">Description</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[110px]">Qty</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[120px]">Tax</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[160px]">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in items" :key="i" class="border-t border-border align-top">
                <td class="px-3 py-3">
                  <AppAccountSelect v-model="row.ledger_id" :options="ledgerOptions" placeholder="Nothing selected" />
                  <!-- Remove / reorder controls (WeConnectU-style) -->
                  <div class="flex items-center gap-3 mt-2 pl-1">
                    <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-danger hover:underline" @click="removeRow(i)">
                      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                      Remove row
                    </button>
                    <button type="button" class="text-navy/60 hover:text-navy disabled:opacity-30" :disabled="i === 0" title="Move up" @click="moveRow(i, -1)">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a1 1 0 01.7.29l5 5a1 1 0 01-1.4 1.42L11 6.4V16a1 1 0 11-2 0V6.41L5.7 9.71a1 1 0 01-1.4-1.42l5-5A1 1 0 0110 3z" clip-rule="evenodd" /></svg>
                    </button>
                    <button type="button" class="text-navy/60 hover:text-navy disabled:opacity-30" :disabled="i === items.length - 1" title="Move down" @click="moveRow(i, 1)">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 17a1 1 0 01-.7-.29l-5-5a1 1 0 011.4-1.42L9 13.6V4a1 1 0 112 0v9.59l3.3-3.3a1 1 0 011.4 1.42l-5 5A1 1 0 0110 17z" clip-rule="evenodd" /></svg>
                    </button>
                  </div>
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.description" type="textarea" :rows="2" placeholder="Description" />
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.quantity" type="number" />
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.tax_rate" type="number" suffix="%" />
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.amount" type="number" prefix="R" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between mt-3">
          <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-dark hover:underline" @click="addRow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            Add row
          </button>
          <p v-if="errors.items" class="text-xs text-danger">{{ errors.items }}</p>
        </div>

        <!-- Totals -->
        <div class="flex justify-end mt-4">
          <table class="text-sm w-full max-w-xs">
            <tbody>
              <tr class="border-t border-border">
                <td class="py-2.5 px-4 text-muted-foreground">Sub-total</td>
                <td class="py-2.5 px-4 text-right font-medium text-foreground tabular-nums">R {{ money(subtotal) }}</td>
              </tr>
              <tr class="border-t border-border">
                <td class="py-2.5 px-4 text-muted-foreground">VAT</td>
                <td class="py-2.5 px-4 text-right font-medium text-foreground tabular-nums">R {{ money(vatTotal) }}</td>
              </tr>
              <tr class="border-t-2 border-navy">
                <td class="py-2.5 px-4 font-bold text-navy">TOTAL</td>
                <td class="py-2.5 px-4 text-right font-bold text-navy tabular-nums">R {{ money(total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── E-mail options ─────────────────────────────────────────── -->
      <div class="rounded-lg border border-amber/40 bg-amber/5 p-5 space-y-3">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-2">
          <label class="flex items-center gap-2 text-sm font-semibold text-foreground cursor-pointer">
            <input type="checkbox" v-model="email.send" class="accent-navy w-4 h-4" />
            E-mail this Invoice
          </label>
          <label class="flex items-center gap-2 text-sm font-semibold text-foreground cursor-pointer">
            <input type="checkbox" v-model="email.attachStmt" class="accent-navy w-4 h-4" />
            Attach Statement
          </label>
        </div>

        <div v-if="email.send" class="space-y-2 pt-1">
          <p class="text-xs font-bold uppercase tracking-wide text-amber-dark">E-Mail Addresses</p>

          <label class="flex items-center gap-2 text-sm cursor-pointer" :class="!customerEmail && 'opacity-50'">
            <input type="checkbox" v-model="email.toCustomer" :disabled="!customerEmail" class="accent-navy w-4 h-4" />
            <span class="text-foreground">{{ customerEmail || 'Customer e-mail address will appear here' }}</span>
            <span v-if="customerEmail" class="italic text-muted-foreground">(customer e-mail address)</span>
          </label>

          <label class="flex items-center gap-2 text-sm cursor-pointer" :class="!alternateEmail && 'opacity-50'">
            <input type="checkbox" v-model="email.toAlternate" :disabled="!alternateEmail" class="accent-navy w-4 h-4" />
            <span class="text-foreground">{{ alternateEmail || 'Customer alternate e-mail address will appear here' }}</span>
          </label>

          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="email.toMyself" class="accent-navy w-4 h-4" />
            <span class="text-foreground">E-mail myself ({{ myEmail || '—' }})</span>
            <span class="italic text-muted-foreground">(you will be CC'd if the client e-mail address is selected)</span>
          </label>

          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="email.toOther" class="accent-navy w-4 h-4" />
            <span class="text-foreground">E-mail to another e-mail address</span>
          </label>

          <!-- Multi-address tip + input, revealed when "another address" is ticked -->
          <div v-if="email.toOther" class="space-y-2 pl-6">
            <div class="rounded-md bg-sky-50 border border-sky-100 px-4 py-3 text-sm text-sky-800">
              You can send to multiple addresses by separating each with a comma (,)<br>
              e.g. john@doe.com,jane@doe.com
            </div>
            <EmailTagsInput v-model="email.otherAddresses" v-model:valid="otherAddressesValid" placeholder="Type e-mail, comma or Enter to add" />
          </div>
        </div>
      </div>

      <!-- ── Actions ────────────────────────────────────────────────── -->
      <div class="flex justify-end">
        <AppButton variant="primary" :loading="submitting" :disabled="email.send && email.toOther && !otherAddressesValid" @click="submit">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Create Invoice
        </AppButton>
      </div>
    </div>
  </div>
</template>
