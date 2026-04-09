<!--
  AppInvoiceSelect — Searchable, rich invoice allocation dropdown.

  Props:
    modelValue        — selected invoice id, or '' for "no invoice / advance payment"
    unitId            — filters invoices to this unit (required for fetching)
    placeholder       — trigger placeholder text
    showAdvanceOption — whether to show the "No invoice (advance payment)" option (default: true)
    excludeStatuses   — invoice statuses to hide (default: ['paid'])
    disabled          — disables the select

  Emits:
    update:modelValue — invoice id string, or '' for advance payment
    select-invoice    — full invoice object, or null for advance payment

  Usage:
    <AppInvoiceSelect
      v-model="paymentForm.invoiceId"
      :unit-id="unitId"
      @select-invoice="selectedInvoice = $event"
    />
-->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import api from '@/composables/useApi'

const props = defineProps({
  modelValue:        { default: '' },
  unitId:            { type: String, default: null },
  placeholder:       { type: String, default: 'Select invoice...' },
  showAdvanceOption: { type: Boolean, default: true },
  excludeStatuses:   { type: Array, default: () => ['paid'] },
  disabled:          { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'select-invoice'])

const isOpen        = ref(false)
const search        = ref('')
const invoices      = ref([])
const loading       = ref(false)
const containerRef  = ref(null)
const triggerRef    = ref(null)
const searchRef     = ref(null)
const dropdownStyle = ref({})
const openUpward    = ref(false)

let debounceTimer = null

// ── Display helpers ────────────────────────────────────────────────────
function fmtCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  const num = Math.round(Number(amount))
  if (isNaN(num)) return '—'
  return `R\u00a0${num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')}`
}

function fmtPeriod(dateStr) {
  if (!dateStr) return '—'
  const [year, month] = dateStr.split('-')
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
  return `${months[parseInt(month, 10) - 1]} ${year}`
}

const STATUS_STYLE = {
  paid:           'bg-success/10 text-success border-success/20',
  overdue:        'bg-destructive/10 text-destructive border-destructive/20',
  partially_paid: 'bg-warning/10 text-warning border-warning/20',
  sent:           'bg-blue-50 text-blue-700 border-blue-200',
  draft:          'bg-muted text-muted-foreground border-border',
}
const STATUS_LABEL = { paid: 'Paid', overdue: 'Overdue', partially_paid: 'Partial', sent: 'Sent', draft: 'Draft' }

function statusClass(s) { return STATUS_STYLE[s] ?? STATUS_STYLE.draft }
function statusLabel(s) { return STATUS_LABEL[s] ?? s }

function billedToName(inv) {
  if (inv.billed_to_type === 'owner') return inv.billed_to_owner?.full_name ?? '—'
  return inv.billed_to_unit_tenant?.full_name ?? '—'
}

// ── Trigger label ──────────────────────────────────────────────────────
const selectedInvoice = computed(() => {
  if (!props.modelValue) return null
  return invoices.value.find(inv => inv.id === props.modelValue) ?? null
})

const triggerLabel = computed(() => {
  if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) return null
  if (props.modelValue === '__advance__') return 'No invoice (advance payment)'
  if (selectedInvoice.value) {
    return `${selectedInvoice.value.invoice_number} — ${fmtPeriod(selectedInvoice.value.billing_period)} (${fmtCurrency(selectedInvoice.value.amount)})`
  }
  return 'Invoice selected'
})

// ── Fetch ──────────────────────────────────────────────────────────────
async function fetchInvoices() {
  if (!props.unitId) { invoices.value = []; return }
  loading.value = true
  try {
    const params = { unit_id: props.unitId, _per_page: 50 }
    if (search.value.trim()) params.search = search.value.trim()
    const { data } = await api.get('/invoices', { params })
    invoices.value = (data.data ?? []).filter(inv => !props.excludeStatuses.includes(inv.status))
  } catch {
    invoices.value = []
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(debounceTimer)
  loading.value = true
  debounceTimer = setTimeout(fetchInvoices, 300)
})

watch(() => props.unitId, () => {
  search.value  = ''
  invoices.value = []
  if (isOpen.value) fetchInvoices()
})

// ── Dropdown positioning (fixed, escapes overflow:hidden) ──────────────
function positionDropdown() {
  const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
  if (!rect) return
  openUpward.value = rect.bottom + 340 > window.innerHeight
  dropdownStyle.value = {
    left:  rect.left + 'px',
    width: Math.max(rect.width, 360) + 'px',
    ...(openUpward.value
      ? { bottom: window.innerHeight - rect.top + 4 + 'px', top: 'auto' }
      : { top: rect.bottom + 4 + 'px', bottom: 'auto' }),
  }
}

function open() {
  if (props.disabled) return
  positionDropdown()
  isOpen.value  = true
  search.value  = ''
  fetchInvoices()
  nextTick(() => searchRef.value?.focus())
}

function close() { isOpen.value = false }
function toggle() { isOpen.value ? close() : open() }

function selectAdvance() {
  emit('update:modelValue', '')
  emit('select-invoice', null)
  close()
}

function selectInvoice(inv) {
  emit('update:modelValue', inv.id)
  emit('select-invoice', inv)
  close()
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) close()
}

onMounted(()  => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  clearTimeout(debounceTimer)
})
</script>

<template>
  <div ref="containerRef" class="relative" :class="disabled && 'opacity-50 pointer-events-none'">

    <!-- ── Trigger ──────────────────────────────────────────────────────── -->
    <button
      ref="triggerRef"
      type="button"
      @click="toggle"
      class="w-full h-11 flex items-center justify-between gap-2 px-4 text-sm rounded border bg-white outline-none select-none border-border transition-colors"
      :class="[
        triggerLabel ? 'text-foreground' : 'text-muted-foreground',
        isOpen ? 'border-primary ring-1 ring-primary/20' : 'hover:border-border/80',
      ]"
    >
      <span class="truncate text-left">{{ triggerLabel ?? placeholder }}</span>
      <svg
        class="w-4 h-4 shrink-0 text-muted-foreground transition-transform duration-200"
        :class="isOpen && 'rotate-180'"
        viewBox="0 0 20 20" fill="currentColor"
      >
        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
      </svg>
    </button>

    <!-- ── Dropdown (Teleported to body so it escapes any overflow:hidden) ── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-100 ease-out"
        :enter-from-class="openUpward ? 'opacity-0 translate-y-1' : 'opacity-0 -translate-y-1'"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        :leave-to-class="openUpward ? 'opacity-0 translate-y-1' : 'opacity-0 -translate-y-1'"
      >
        <div
          v-if="isOpen"
          class="fixed z-[9999] bg-white border border-border rounded-lg shadow-xl overflow-hidden flex flex-col"
          :style="{ ...dropdownStyle, maxHeight: '340px' }"
        >

          <!-- Search bar -->
          <div class="px-3 pt-3 pb-2 border-b border-border shrink-0">
            <div class="relative">
              <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
              </svg>
              <input
                ref="searchRef"
                v-model="search"
                type="text"
                placeholder="Search by invoice #, unit, name, or charge type..."
                class="w-full h-8 pl-8 pr-3 text-xs rounded border border-border bg-muted/30 outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 placeholder:text-muted-foreground"
              />
              <!-- Loading spinner inside search -->
              <svg v-if="loading" class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
              </svg>
            </div>
          </div>

          <!-- Options list -->
          <ul class="overflow-y-auto flex-1 py-1">

            <!-- No invoice / advance payment -->
            <li
              v-if="showAdvanceOption"
              @click="selectAdvance"
              class="px-4 py-3 cursor-pointer transition-colors duration-100 border-b border-border/50"
              :class="(!modelValue || modelValue === '') ? 'bg-amber/90 text-white' : 'hover:bg-amber/10 text-foreground'"
            >
              <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 opacity-70">
                  <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <span class="text-sm font-medium">No invoice (advance payment)</span>
              </div>
              <p class="text-[11px] mt-0.5 ml-5" :class="(!modelValue || modelValue === '') ? 'text-white/75' : 'text-muted-foreground'">
                Payment will sit as an unallocated credit on the account
              </p>
            </li>

            <!-- Loading skeleton -->
            <template v-if="loading && invoices.length === 0">
              <li v-for="n in 3" :key="n" class="px-4 py-3 animate-pulse">
                <div class="flex items-center justify-between mb-1.5">
                  <div class="h-3.5 w-28 rounded bg-muted"></div>
                  <div class="flex items-center gap-2">
                    <div class="h-3.5 w-14 rounded bg-muted"></div>
                    <div class="h-4 w-12 rounded-full bg-muted"></div>
                  </div>
                </div>
                <div class="h-3 w-48 rounded bg-muted/60"></div>
              </li>
            </template>

            <!-- Invoice rows -->
            <template v-else>
              <li
                v-for="inv in invoices"
                :key="inv.id"
                @click="selectInvoice(inv)"
                class="px-4 py-2.5 cursor-pointer transition-colors duration-100 border-b border-border/40 last:border-0"
                :class="modelValue === inv.id ? 'bg-primary/5 border-l-2 border-l-primary' : 'hover:bg-muted/50'"
              >
                <!-- Row 1: number + amount + status -->
                <div class="flex items-center justify-between gap-2">
                  <span class="text-sm font-semibold text-foreground font-mono">{{ inv.invoice_number }}</span>
                  <div class="flex items-center gap-2 shrink-0">
                    <span class="text-sm font-medium text-foreground">{{ fmtCurrency(inv.amount) }}</span>
                    <span :class="['inline-flex items-center rounded-full px-2 py-px text-[10px] font-medium border leading-tight', statusClass(inv.status)]">
                      {{ statusLabel(inv.status) }}
                    </span>
                  </div>
                </div>
                <!-- Row 2: charge type · period · billed to -->
                <p class="text-[11px] text-muted-foreground mt-0.5 truncate">
                  {{ inv.charge_type?.name ?? '—' }}
                  <span class="mx-1 opacity-40">·</span>
                  {{ fmtPeriod(inv.billing_period) }}
                  <span class="mx-1 opacity-40">·</span>
                  {{ billedToName(inv) }}
                  <span v-if="inv.unit?.unit_number" class="mx-1 opacity-40">·</span>
                  <span v-if="inv.unit?.unit_number" class="font-medium">Unit {{ inv.unit.unit_number }}</span>
                </p>
              </li>

              <!-- Empty state -->
              <li v-if="!loading && invoices.length === 0" class="px-4 py-6 text-center">
                <p class="text-sm text-muted-foreground">
                  {{ search ? 'No invoices match your search.' : 'No outstanding invoices for this unit.' }}
                </p>
              </li>
            </template>

          </ul>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
