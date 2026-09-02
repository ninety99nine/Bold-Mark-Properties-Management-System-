<!--
  CreditNotePage — WeConnectU-style "Credit Note" capture screen.

  Faithful clone of the WeConnectU Customers → Credit Note page, wired to the
  Bold Mark backend and styled to match our Customer Invoice page: a customer
  picker + date, a credit-note reason, an optional order-no / reference, an
  "apply to a specific invoice" flow (pick an invoice, tick the lines to credit,
  they copy down into editable rows), multi-line account/description/qty/unit-
  price rows with live sub-total / VAT / total, and e-mail delivery options.

  POSTs JSON to /credit-notes.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import { useAuthStore } from '@/stores/auth'
import AppSelect        from '@/components/common/AppSelect.vue'
import AppAsyncSelect    from '@/components/common/AppAsyncSelect.vue'
import AppAccountSelect  from '@/components/common/AppAccountSelect.vue'
import AppInput         from '@/components/common/AppInput.vue'
import AppButton        from '@/components/common/AppButton.vue'
import EmailTagsInput   from '@/components/common/EmailTagsInput.vue'
import AppDatePicker    from '@/components/common/AppDatePicker.vue'

const { success, error } = useToast()
const community = useCommunityStore()
const auth      = useAuthStore()

// ── Reference data ──────────────────────────────────────────────────────
const units       = ref([])
const ledgers     = ref([])
const loadingRefs = ref(true)

// ── Form state ──────────────────────────────────────────────────────────
function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const form = ref({
  unit_id:          '',
  credit_note_date: today(),
  reason:           '',
  order_no:         '',
  reference:        '',
})

// Toggles the smooth reveal of the Order No. / Reference fields.
const showReference = ref(false)

// Apply-to-invoice flow
const applyToInvoice     = ref('yes')          // 'yes' | 'no'
const creditableInvoices = ref([])
const selectedInvoiceId  = ref('')
const lineSelected       = ref([])             // parallel booleans for the picked invoice's lines
const loadingInvoices    = ref(false)

const items = ref([blankRow()])

function blankRow(extra = {}) {
  return { ledger_id: '', description: '', quantity: '1', tax_rate: '0', amount: '0.00', _fromLine: null, ...extra }
}

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
  const byId = new Map(units.value.map((u) => [u.id, u]))
  for (const u of found) byId.set(u.id, u)
  units.value = [...byId.values()]
  return found.map(unitOption)
}

// Chart-of-accounts options for the Account picker: "code - name" grouped by
// category. When a chart of accounts exists we show only the coded accounts,
// otherwise we fall back to every ledger so the picker is never empty.
const ledgerOptions = computed(() => {
  const coded = ledgers.value.filter((l) => l.code)
  const source = coded.length ? coded : ledgers.value
  return source.map((l) => ({
    value: l.id,
    label: l.code ? `${l.code} - ${l.name}` : l.name,
    category: l.category || 'Other Accounts',
  }))
})

function fmtInvoiceDate(iso) {
  if (!iso) return ''
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}

const invoiceOptions = computed(() => {
  if (!creditableInvoices.value.length) return []
  return [{
    group: 'Last 20 Invoices',
    options: creditableInvoices.value.map((inv) => ({
      value: inv.id,
      label: `${inv.invoice_number} (${fmtInvoiceDate(inv.invoice_date)})`,
    })),
  }]
})

const selectedInvoice = computed(() => creditableInvoices.value.find((i) => i.id === selectedInvoiceId.value) || null)
const selectedUnit    = computed(() => units.value.find((u) => u.id === form.value.unit_id) || null)

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

// ── Apply-to-invoice line selection ─────────────────────────────────────
// Ticking an invoice line copies it into the editable rows below (tagged with
// its source line index); unticking removes that tagged row — mirroring
// WeConnectU. Rows the user adds manually are never touched.
function ensureBlankTrailingRow() {
  const last = items.value[items.value.length - 1]
  if (!last || last.ledger_id || last._fromLine !== null) {
    items.value.push(blankRow())
  }
}

function toggleLine(index) {
  const inv  = selectedInvoice.value
  const line = inv?.lines?.[index]
  if (!line) return

  if (lineSelected.value[index]) {
    // Remove any empty placeholder rows so the copied line replaces them.
    const onlyBlank = items.value.length === 1 && !items.value[0].ledger_id && items.value[0]._fromLine === null
    if (onlyBlank) items.value = []
    items.value.push(blankRow({
      ledger_id:   line.ledger_id,
      description: `${line.description || line.account} (${inv.invoice_number} line ${index + 1})`,
      quantity:    String(line.quantity ?? 1),
      tax_rate:    String(line.tax_rate ?? 0),
      amount:      Number(line.unit_price ?? 0).toFixed(2),
      _fromLine:   index,
    }))
    ensureBlankTrailingRow()
  } else {
    items.value = items.value.filter((r) => r._fromLine !== index)
    if (!items.value.length) items.value = [blankRow()]
  }
}

const allLinesSelected = computed(() =>
  !!selectedInvoice.value?.lines?.length && lineSelected.value.every(Boolean)
)

function toggleAllLines() {
  const target = !allLinesSelected.value
  selectedInvoice.value?.lines?.forEach((_, i) => {
    if (lineSelected.value[i] !== target) {
      lineSelected.value[i] = target
      toggleLine(i)
    }
  })
}

// Reset line selection (and remove copied rows) whenever the invoice changes.
watch(selectedInvoiceId, () => {
  items.value = items.value.filter((r) => r._fromLine === null)
  if (!items.value.length) items.value = [blankRow()]
  lineSelected.value = (selectedInvoice.value?.lines || []).map(() => false)
})

// ── Data loading ────────────────────────────────────────────────────────
const rows = (res) => res?.data?.data ?? res?.data ?? []

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
      loadAllUnits(communityId)
        .then((all) => { units.value = all })
        .catch(() => failed.push('customers')),
    )
  }

  await Promise.allSettled(jobs)

  if (failed.length) {
    error(`Could not load ${failed.join(', ')}. Please refresh to try again.`)
  }

  loadingRefs.value = false
}

// Load the customer's last-20 invoices for the apply-to-invoice picker.
async function loadCreditableInvoices() {
  creditableInvoices.value = []
  selectedInvoiceId.value  = ''
  lineSelected.value       = []
  if (!form.value.unit_id || applyToInvoice.value !== 'yes') return
  loadingInvoices.value = true
  try {
    const { data } = await api.get('/credit-notes/creditable-invoices', { params: { unit_id: form.value.unit_id } })
    creditableInvoices.value = data.data ?? []
  } catch {
    error('Could not load the customer\'s invoices.')
  } finally {
    loadingInvoices.value = false
  }
}

onMounted(loadRefs)
watch(() => community.selectedId, loadRefs)
watch(() => form.value.unit_id, loadCreditableInvoices)
watch(applyToInvoice, loadCreditableInvoices)

// ── Validation + submit ─────────────────────────────────────────────────
function validate() {
  const e = {}
  if (!form.value.unit_id) e.unit_id = 'Select a customer.'
  if (!form.value.credit_note_date) e.credit_note_date = 'Required.'
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
  if (email.value.toOther) email.value.otherAddresses.forEach((a) => list.push(a))
  return [...new Set(list)]
}

async function submit() {
  if (!validate()) return
  if (email.value.send && email.value.toOther && !otherAddressesValid.value) {
    error('Please fix the highlighted e-mail address(es) before creating the credit note.')
    return
  }
  submitting.value = true

  const payload = {
    unit_id:          form.value.unit_id,
    credit_note_date: form.value.credit_note_date,
    reason:           form.value.reason || null,
    order_no:         showReference.value ? (form.value.order_no || null) : null,
    reference:        showReference.value ? (form.value.reference || null) : null,
    applied_invoice_id: applyToInvoice.value === 'yes' && selectedInvoiceId.value ? selectedInvoiceId.value : null,
    items: items.value
      .filter((r) => r.ledger_id)
      .map((r) => ({
        ledger_id:   r.ledger_id,
        description: r.description || '',
        quantity:    parseFloat(r.quantity) || 0,
        tax_rate:    parseFloat(r.tax_rate) || 0,
        amount:      parseFloat(r.amount) || 0,
      })),
    email_credit_note: email.value.send ? 1 : 0,
    attach_statement:  email.value.attachStmt ? 1 : 0,
    email_recipients:  email.value.send ? buildRecipients() : [],
  }

  try {
    const { data } = await api.post('/credit-notes', payload)
    const created = data.data ?? data
    success(`Credit Note ${created?.credit_note_number ?? ''} created successfully.`.trim())
    resetForm()
  } catch (err) {
    const res = err.response?.data
    if (res?.errors) {
      error(Object.values(res.errors)[0]?.[0] ?? 'Please fix the highlighted fields.')
    } else {
      error(res?.message ?? 'Could not create the credit note.')
    }
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  form.value = { unit_id: '', credit_note_date: today(), reason: '', order_no: '', reference: '' }
  showReference.value      = false
  applyToInvoice.value     = 'yes'
  creditableInvoices.value = []
  selectedInvoiceId.value  = ''
  lineSelected.value       = []
  items.value = [blankRow()]
  errors.value = {}
  email.value.otherAddresses = []
  otherAddressesValid.value  = true
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- ── Page header ──────────────────────────────────────────────── -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Credit Note</h1>
      <p class="text-sm text-muted-foreground">
        Raise a credit note against a customer's account
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- ── No community selected ────────────────────────────────────── -->
    <div
      v-if="!community.selectedId"
      class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground"
    >
      Select a community from the top bar to raise a credit note.
    </div>

    <!-- ── Credit note form ─────────────────────────────────────────── -->
    <div v-else class="rounded-lg border border-border bg-white p-6 space-y-8">
      <!-- Customer / date / reason / reference -->
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
        <AppDatePicker v-model="form.credit_note_date" label="Date" required />
        <AppInput v-model="form.reason" label="Credit note reason" placeholder="" />
      </div>

      <!-- Add Order No. / Reference — click to toggle the smooth reveal -->
      <div class="-mt-3">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-dark hover:underline"
          @click="showReference = !showReference"
        >
          <svg
            class="w-4 h-4 transition-transform duration-300"
            :class="showReference && 'rotate-45'"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
          ><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
          Add Order No. / Reference
        </button>

        <!-- grid-rows 0fr→1fr gives a smooth height transition without fixed heights -->
        <div
          class="grid transition-all duration-300 ease-in-out"
          :class="showReference ? 'grid-rows-[1fr] opacity-100 mt-4' : 'grid-rows-[0fr] opacity-0'"
        >
          <div class="overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-5">
              <AppInput v-model="form.order_no"  label="Order No." placeholder="" />
              <AppInput v-model="form.reference" label="Reference" placeholder="" />
            </div>
          </div>
        </div>
      </div>

      <!-- ── Apply to a specific invoice? (blue panel) ──────────────── -->
      <div class="rounded-lg bg-sky-50 border border-sky-100 p-5 space-y-4">
        <p class="text-sm font-semibold text-navy">Does this credit note apply to a specific invoice?</p>
        <div class="flex items-center gap-8">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" value="yes" v-model="applyToInvoice" class="accent-sky-600 w-4 h-4" />
            <span class="text-foreground">Yes</span>
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" value="no" v-model="applyToInvoice" class="accent-sky-600 w-4 h-4" />
            <span class="text-foreground">No</span>
          </label>
        </div>

        <template v-if="applyToInvoice === 'yes'">
          <AppSelect
            v-model="selectedInvoiceId"
            :options="invoiceOptions"
            :placeholder="loadingInvoices ? 'Loading invoices…' : 'Select Invoice'"
          />

          <!-- Hint when nothing chosen yet -->
          <p v-if="!selectedInvoice" class="flex items-center gap-2 text-sm text-navy/70">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
            Invoice detail will display here
          </p>

          <!-- Selectable invoice lines -->
          <div v-else class="space-y-2">
            <p class="text-sm font-semibold text-navy">Select the line items you want to credit</p>
            <div class="overflow-x-auto rounded-lg border border-sky-200 bg-white">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-navy border-b border-sky-200">
                    <th class="px-3 py-2.5 w-10 text-center">
                      <input type="checkbox" :checked="allLinesSelected" class="accent-navy w-4 h-4" @change="toggleAllLines" />
                    </th>
                    <th class="px-3 py-2.5 text-left font-semibold">Account</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Description</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Tax Type</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Qty</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Unit Price</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Tax</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(line, i) in selectedInvoice.lines"
                    :key="i"
                    class="border-b border-sky-100 last:border-0 odd:bg-sky-50/40"
                  >
                    <td class="px-3 py-2.5 text-center">
                      <input type="checkbox" v-model="lineSelected[i]" class="accent-navy w-4 h-4" @change="toggleLine(i)" />
                    </td>
                    <td class="px-3 py-2.5 text-navy">{{ line.account }}</td>
                    <td class="px-3 py-2.5 text-navy">{{ line.description }}</td>
                    <td class="px-3 py-2.5 text-navy">{{ line.tax_type }}</td>
                    <td class="px-3 py-2.5 text-right text-navy tabular-nums">{{ money(line.quantity) }}</td>
                    <td class="px-3 py-2.5 text-right text-navy tabular-nums">{{ money(line.unit_price) }}</td>
                    <td class="px-3 py-2.5 text-right text-navy tabular-nums">{{ money(line.tax) }}</td>
                    <td class="px-3 py-2.5 text-right text-navy tabular-nums">{{ money(line.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>

      <!-- ── Editable line items ────────────────────────────────────── -->
      <div>
        <div class="overflow-x-auto rounded-lg border border-border">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-muted/40 text-left">
                <th class="px-4 py-3 font-semibold text-foreground w-[180px]">Account</th>
                <th class="px-4 py-3 font-semibold text-foreground">Description</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[90px]">Qty</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[100px]">Unit Price</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[95px]">Tax</th>
                <th class="px-4 py-3 font-semibold text-foreground w-[100px]">Total</th>
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
                  <AppInput v-model="row.description" type="textarea" :rows="2" placeholder="" />
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.quantity" type="number" />
                </td>
                <td class="px-3 py-3">
                  <AppInput v-model="row.amount" type="number" prefix="R" />
                </td>
                <td class="px-3 py-3">
                  <AppInput :model-value="money(lineVat(row))" prefix="R" readonly disabled />
                </td>
                <td class="px-3 py-3">
                  <AppInput :model-value="money(lineTotal(row))" prefix="R" readonly disabled />
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
            E-mail this Credit Note
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
          Create Credit Note
        </AppButton>
      </div>
    </div>
  </div>
</template>
