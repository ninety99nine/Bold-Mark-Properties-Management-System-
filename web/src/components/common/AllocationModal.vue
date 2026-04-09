<script setup>
import { ref, computed, watch } from 'vue'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import api from '@/composables/useApi.js'

const props = defineProps({
  show:  { type: Boolean, required: true },
  entry: { type: Object,  default: null  },
})

const emit = defineEmits(['close', 'allocated'])

// ── State ──────────────────────────────────────────────────────────────────
const step          = ref('unit')   // 'unit' | 'invoice' | 'confirm'
const unitOptions   = ref([])
const unitLoading   = ref(false)
const selectedUnitId = ref(null)

const invoices      = ref([])
const invoicesLoading = ref(false)
const selectedInvoiceId = ref(null)

const submitting    = ref(false)
const submitError   = ref(null)

// ── Computed ───────────────────────────────────────────────────────────────
const selectedInvoice = computed(() =>
  invoices.value.find(i => i.id === selectedInvoiceId.value) ?? null
)

const entryAmount = computed(() => Number(props.entry?.amount ?? 0))

const willSplit = computed(() => {
  if (!selectedInvoice.value) return false
  return entryAmount.value > Number(selectedInvoice.value.outstanding)
})

const splitAllocated = computed(() =>
  willSplit.value ? Number(selectedInvoice.value.outstanding) : entryAmount.value
)

const splitRemainder = computed(() =>
  willSplit.value ? entryAmount.value - Number(selectedInvoice.value.outstanding) : 0
)

function fmt(n) {
  const num = Number(n)
  const decimals = num % 1 === 0 ? 0 : 2
  return `R ${num.toLocaleString('en-ZA', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`
}

function formatDate(d) {
  if (!d) return '—'
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  const [y, m, day] = d.split('-')
  return `${day} ${months[parseInt(m,10)-1]} ${y}`
}

// ── Reset when modal opens ──────────────────────────────────────────────────
watch(() => props.show, async (val) => {
  if (!val) return
  submitError.value       = null
  selectedInvoiceId.value = null
  invoices.value          = []

  if (props.entry?.unit_id) {
    selectedUnitId.value = props.entry.unit_id
    step.value = 'invoice'
    await loadInvoices(props.entry.unit_id)
  } else {
    selectedUnitId.value = null
    step.value = 'unit'
    await loadUnits()
  }
})

// ── Load units for the entry's estate ─────────────────────────────────────
async function loadUnits() {
  if (!props.entry?.estate_id) return
  unitLoading.value = true
  try {
    const res = await api.get(`/estates/${props.entry.estate_id}/units`, { params: { per_page: 200 } })
    unitOptions.value = (res.data.data ?? []).map(u => ({
      value: u.id,
      label: `Unit ${u.unit_number}`,
    }))
  } finally {
    unitLoading.value = false
  }
}

async function confirmUnitStep() {
  if (!selectedUnitId.value) return
  step.value = 'invoice'
  await loadInvoices(selectedUnitId.value)
}

// ── Load outstanding invoices for selected unit ────────────────────────────
async function loadInvoices(unitId) {
  invoicesLoading.value = true
  invoices.value = []
  try {
    const res = await api.get('/invoices', {
      params: { unit_id: unitId, per_page: 200 },
    })
    const all = res.data.data ?? []
    invoices.value = all.filter(i => i.status !== 'paid')
  } finally {
    invoicesLoading.value = false
  }
}

function selectInvoice(id) {
  selectedInvoiceId.value = id
}

function confirmInvoiceStep() {
  if (!selectedInvoiceId.value) return
  step.value = 'confirm'
}

function backToInvoice() {
  step.value = 'confirm' // go back to invoice list
  step.value = 'invoice'
}

// ── Submit ─────────────────────────────────────────────────────────────────
async function submit() {
  submitting.value  = true
  submitError.value = null
  try {
    await api.post(`/cashbook/${props.entry.id}/allocate`, {
      invoice_id: selectedInvoiceId.value,
      unit_id:    selectedUnitId.value,
    })
    emit('allocated')
    emit('close')
  } catch (e) {
    submitError.value = e.response?.data?.message ?? 'Failed to allocate. Please try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :show="show" title="Allocate to Invoice" size="lg" @close="$emit('close')">

    <!-- ── Step: Unit selection (only when entry has no unit) ────────────── -->
    <template v-if="step === 'unit'">
      <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Select the unit this payment belongs to, then choose the invoice to allocate against.
        </p>
        <div v-if="unitLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
          </svg>
          Loading units…
        </div>
        <AppSelect
          v-else
          v-model="selectedUnitId"
          label="Unit"
          :options="unitOptions"
          placeholder="Select unit..."
        />
      </div>
      <div class="flex justify-end gap-2 mt-6">
        <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
        <AppButton variant="primary" :disabled="!selectedUnitId" @click="confirmUnitStep">
          Next — Select Invoice
        </AppButton>
      </div>
    </template>

    <!-- ── Step: Invoice selection ────────────────────────────────────────── -->
    <template v-else-if="step === 'invoice'">
      <div class="space-y-4">
        <!-- Entry amount pill -->
        <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border">
          <div>
            <p class="text-xs text-muted-foreground">Payment amount</p>
            <p class="text-lg font-bold text-success font-body">{{ fmt(entryAmount) }}</p>
          </div>
          <div class="h-8 w-px bg-border mx-1" />
          <div>
            <p class="text-xs text-muted-foreground">Description</p>
            <p class="text-sm font-medium text-foreground">{{ entry?.description }}</p>
          </div>
        </div>

        <p class="text-sm font-medium text-foreground">Select invoice to allocate against:</p>

        <div v-if="invoicesLoading" class="flex items-center gap-2 text-sm text-muted-foreground py-4">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
          </svg>
          Loading invoices…
        </div>

        <div v-else-if="invoices.length === 0" class="py-6 text-center">
          <p class="text-sm text-muted-foreground">No outstanding invoices found for this unit.</p>
          <p class="text-xs text-muted-foreground mt-1">This payment will remain unallocated until a matching invoice is created.</p>
        </div>

        <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
          <button
            v-for="inv in invoices"
            :key="inv.id"
            type="button"
            :class="[
              'w-full text-left rounded-lg border transition-all',
              selectedInvoiceId === inv.id
                ? 'border-primary ring-2 ring-primary/20 shadow-sm'
                : 'border-border hover:border-primary/50 hover:shadow-sm',
            ]"
            @click="selectInvoice(inv.id)"
          >
            <div class="flex">
              <!-- Status accent bar — rounded-l-lg matches the button's own border-radius -->
              <div :class="[
                'w-1 shrink-0 rounded-l-lg self-stretch',
                inv.status === 'overdue'        ? 'bg-destructive' :
                inv.status === 'partially_paid' ? 'bg-amber-400' :
                'bg-border/50'
              ]" />

              <div class="flex-1 p-3">
                <!-- Top row: invoice number + outstanding amount -->
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-sm font-bold font-mono text-foreground tracking-wide">{{ inv.invoice_number }}</span>
                      <!-- Match indicator -->
                      <span
                        v-if="entryAmount === Number(inv.outstanding)"
                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5"
                      >
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                        Exact match
                      </span>
                      <span
                        v-else-if="entryAmount > Number(inv.outstanding)"
                        class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5"
                      >
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>
                        Will split
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-full px-2 py-0.5"
                      >
                        Partial payment
                      </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      {{ inv.charge_type?.name ?? '—' }}
                      <span v-if="inv.billing_period" class="before:content-['·'] before:mx-1">{{ formatDate(inv.billing_period) }}</span>
                    </p>
                  </div>

                  <!-- Outstanding amount -->
                  <div class="text-right shrink-0">
                    <p :class="[
                      'text-base font-bold font-body leading-tight',
                      inv.status === 'overdue' ? 'text-destructive' : 'text-foreground'
                    ]">{{ fmt(inv.outstanding) }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">of {{ fmt(inv.amount) }} total</p>
                  </div>
                </div>

                <!-- Bottom row: status badge + due date + billed to -->
                <div class="flex items-center gap-2 mt-2.5 pt-2.5 border-t border-border/60">
                  <AppBadge
                    :variant="inv.status === 'overdue' ? 'danger' : inv.status === 'partially_paid' ? 'warning' : 'default'"
                    bordered size="sm"
                  >
                    {{ inv.status === 'partially_paid' ? 'Partial' : inv.status?.charAt(0).toUpperCase() + inv.status?.slice(1) }}
                  </AppBadge>
                  <span v-if="inv.due_date" class="flex items-center gap-1 text-xs text-muted-foreground">
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    Due {{ formatDate(inv.due_date) }}
                  </span>
                  <span
                    v-if="inv.billed_to_owner?.full_name || inv.billed_to_unit_tenant?.full_name"
                    class="flex items-center gap-1 text-xs text-muted-foreground ml-auto"
                  >
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                    {{ inv.billed_to_owner?.full_name ?? inv.billed_to_unit_tenant?.full_name }}
                  </span>
                </div>
              </div>

              <!-- Selection checkmark column -->
              <div class="flex items-center px-3">
                <div :class="[
                  'w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all',
                  selectedInvoiceId === inv.id
                    ? 'border-primary bg-primary'
                    : 'border-border',
                ]">
                  <svg v-if="selectedInvoiceId === inv.id" class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M20 6 9 17l-5-5"/>
                  </svg>
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <div class="flex justify-between gap-2 mt-6">
        <AppButton v-if="!entry?.unit_id" variant="ghost" size="sm" @click="step = 'unit'">
          ← Back
        </AppButton>
        <div v-else />
        <div class="flex gap-2">
          <AppButton variant="outline" @click="$emit('close')">Cancel</AppButton>
          <AppButton variant="primary" :disabled="!selectedInvoiceId || invoices.length === 0" @click="confirmInvoiceStep">
            Review Allocation
          </AppButton>
        </div>
      </div>
    </template>

    <!-- ── Step: Confirm ─────────────────────────────────────────────────── -->
    <template v-else-if="step === 'confirm'">
      <div class="space-y-4">
        <p class="text-sm font-semibold text-foreground">Confirm allocation</p>

        <!-- Summary table -->
        <div class="rounded-lg border overflow-hidden">
          <table class="w-full text-sm">
            <tbody class="divide-y divide-border">
              <tr class="bg-muted/20">
                <td class="px-4 py-2.5 text-muted-foreground">Payment amount</td>
                <td class="px-4 py-2.5 font-semibold text-success text-right">{{ fmt(entryAmount) }}</td>
              </tr>
              <tr>
                <td class="px-4 py-2.5 text-muted-foreground">Invoice</td>
                <td class="px-4 py-2.5 font-mono text-right">{{ selectedInvoice?.invoice_number }}</td>
              </tr>
              <tr>
                <td class="px-4 py-2.5 text-muted-foreground">Charge type</td>
                <td class="px-4 py-2.5 text-right">{{ selectedInvoice?.charge_type?.name ?? '—' }}</td>
              </tr>
              <tr>
                <td class="px-4 py-2.5 text-muted-foreground">Invoice outstanding</td>
                <td class="px-4 py-2.5 font-semibold text-right">{{ fmt(selectedInvoice?.outstanding) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Split preview -->
        <template v-if="willSplit">
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2">
            <p class="text-sm font-semibold text-amber-900 flex items-center gap-1.5">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>
              </svg>
              Payment will be split
            </p>
            <p class="text-xs text-amber-800">
              The payment ({{ fmt(entryAmount) }}) exceeds the invoice outstanding ({{ fmt(selectedInvoice?.outstanding) }}).
              The system will create two entries:
            </p>
            <div class="space-y-1">
              <div class="flex justify-between text-xs font-medium text-amber-900">
                <span>Allocated to {{ selectedInvoice?.invoice_number }}</span>
                <span>{{ fmt(splitAllocated) }}</span>
              </div>
              <div class="flex justify-between text-xs font-medium text-amber-900">
                <span>Unallocated remainder (credit on account)</span>
                <span>{{ fmt(splitRemainder) }}</span>
              </div>
            </div>
          </div>
        </template>

        <!-- Exact / partial match info -->
        <template v-else>
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs text-emerald-800">
              <template v-if="entryAmount === Number(selectedInvoice?.outstanding)">
                Exact match — invoice will be marked as <strong>Paid</strong>.
              </template>
              <template v-else>
                Partial payment — {{ fmt(entryAmount) }} of {{ fmt(selectedInvoice?.outstanding) }} outstanding.
                Invoice will be marked as <strong>Partial</strong>.
              </template>
            </p>
          </div>
        </template>

        <p v-if="submitError" class="text-sm text-destructive">{{ submitError }}</p>
      </div>

      <div class="flex justify-between gap-2 mt-6">
        <AppButton variant="ghost" size="sm" @click="step = 'invoice'">← Back</AppButton>
        <div class="flex gap-2">
          <AppButton variant="outline" :disabled="submitting" @click="$emit('close')">Cancel</AppButton>
          <AppButton variant="primary" :disabled="submitting" @click="submit">
            {{ submitting ? 'Allocating…' : 'Confirm Allocation' }}
          </AppButton>
        </div>
      </div>
    </template>

  </AppModal>
</template>
