<!--
  AgeStatusIcon — WeConnectU collection-status markers for the Age Analysis grid.

  Icons + tooltip wording are matched EXACTLY to WeConnectU so operators using
  both systems never see a different marker or label. A row can carry several
  markers at once (e.g. transfer arrows + a red handed-over flag), rendered
  left-to-right in WeConnectU order: transfer → debit order → collection status.
  Tooltips are dark pills that sit to the right of the marker with a left caret.

  Collection status (App\Enums\CollectionStatus → WeConnectU marker + tooltip).
  Only statuses WeConnectU actually shows carry a marker — reminder/paid do not:
    first_notice        → peach dot    "1st Notice"
    second_notice       → red dot      "2nd Notice"
    final_notice        → black dot    "Letter of demand"
    letter_of_demand    → document     "Letter of Demand sent"
    payment_arrangement → gold flag    "Payment Arrangement"
    handed_over         → red flag     "Handed over to Attorneys (name)"

  Overlays (independent of status):
    transfer_active     → navy arrows  "Transfer active on unit"
    debit_order         → green clock  "Debit Order Client"
-->
<script setup>
import { computed } from 'vue'

const props = defineProps({
  status:         { type: String,  default: 'none' },
  // The user who applied the status (e.g. handed the customer over to attorneys),
  // shown in the "Handed over to Attorneys (name)" tooltip — NOT the customer.
  statusChangedBy: { type: String, default: '' },
  transferActive: { type: Boolean, default: false },
  debitOrder:     { type: Boolean, default: false },
})

// status → { kind, color?, tip } — tooltip strings are the exact WeConnectU wording.
// Only the statuses WeConnectU actually shows carry a marker (no reminder/paid).
const STATUS = {
  first_notice:        { kind: 'dot', color: '#e8926a', tip: '1st Notice' },
  second_notice:       { kind: 'dot', color: '#c43d2e', tip: '2nd Notice' },
  final_notice:        { kind: 'dot', color: '#1c1c1c', tip: 'Letter of demand' },
  letter_of_demand:    { kind: 'doc',                   tip: 'Letter of Demand sent' },
  payment_arrangement: { kind: 'flag-gold',             tip: 'Payment Arrangement' },
  handed_over:         { kind: 'flag-red',              tip: 'Handed over to Attorneys' },
}

const statusMarker = computed(() => {
  const m = STATUS[props.status]
  if (!m) return null
  const tip = props.status === 'handed_over' && props.statusChangedBy
    ? `${m.tip} (${props.statusChangedBy})`
    : m.tip
  return { ...m, tip }
})

const hasMarker = computed(() =>
  props.transferActive || props.debitOrder || statusMarker.value !== null,
)
</script>

<template>
  <span v-if="hasMarker" class="inline-flex items-center gap-1.5 align-middle">
    <!-- Transfer active on unit -->
    <span v-if="transferActive" class="group/tt relative inline-flex">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#1a2744"
           stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0">
        <path d="m17 2 4 4-4 4" /><path d="M3 6h18" /><path d="m7 22-4-4 4-4" /><path d="M21 18H3" />
      </svg>
      <span class="tt-pill">Transfer active on unit</span>
    </span>

    <!-- Debit Order Client -->
    <span v-if="debitOrder" class="group/tt relative inline-flex">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2e9b6b"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /><path d="M12 7v5l3 2" />
      </svg>
      <span class="tt-pill">Debit Order Client</span>
    </span>

    <!-- Collection-status marker -->
    <span v-if="statusMarker" class="group/tt relative inline-flex">
      <!-- Coloured dot -->
      <span v-if="statusMarker.kind === 'dot'" class="inline-block h-3.5 w-3.5 shrink-0 rounded-full"
            :style="{ backgroundColor: statusMarker.color }" />

      <!-- Flag (payment arrangement = gold, handed over = red) -->
      <svg v-else-if="statusMarker.kind === 'flag-gold' || statusMarker.kind === 'flag-red'"
           xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 shrink-0"
           :fill="statusMarker.kind === 'flag-red' ? '#d0342a' : '#e8a33d'"
           :stroke="statusMarker.kind === 'flag-red' ? '#d0342a' : '#e8a33d'" stroke-width="1.5" stroke-linejoin="round">
        <path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 8 2a6 6 0 0 0 3-1 1 1 0 0 1 1 .9V13a1 1 0 0 1-.4.8A6 6 0 0 1 16 15c-3 0-5-2-8-2a6 6 0 0 0-4 1.3" />
        <line x1="4" y1="22" x2="4" y2="15" stroke-width="2" />
      </svg>

      <!-- Letter of Demand sent (solid document) -->
      <svg v-else-if="statusMarker.kind === 'doc'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
           class="h-4 w-4 shrink-0">
        <path fill="#1c1c1c" d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6z" />
        <path fill="#ffffff" d="M14 2l6 6h-4.5A1.5 1.5 0 0 1 14 6.5V2z" />
        <rect fill="#ffffff" x="7.5" y="12.4" width="9" height="1.4" rx=".7" />
        <rect fill="#ffffff" x="7.5" y="15.4" width="9" height="1.4" rx=".7" />
        <rect fill="#ffffff" x="7.5" y="18.4" width="6" height="1.4" rx=".7" />
      </svg>

      <span class="tt-pill">{{ statusMarker.tip }}</span>
    </span>
  </span>
</template>

<style scoped>
/* WeConnectU tooltip — dark pill to the right of the marker, left caret, on hover. */
.tt-pill {
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%);
  margin-left: 0.5rem;
  z-index: 30;
  white-space: nowrap;
  border-radius: 0.5rem;
  background: #1f2430;
  padding: 0.375rem 0.625rem;
  font-size: 12px;
  font-weight: 500;
  color: #fff;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.12s ease;
}
.tt-pill::before {
  content: '';
  position: absolute;
  left: -4px;
  top: 50%;
  transform: translateY(-50%) rotate(45deg);
  width: 8px;
  height: 8px;
  background: #1f2430;
  border-radius: 1px;
}
.group\/tt:hover .tt-pill {
  opacity: 1;
}
</style>
