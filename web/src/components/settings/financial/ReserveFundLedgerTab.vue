<!--
  ReserveFundLedgerTab — WeConnectU "Financial Setup → Reserve Fund Ledger" clone.

  Same UI as the General Ledger, scoped to the reserve fund. Delegates entirely to
  GeneralLedgerTab with fund="reserve" (GET /ledgers?fund=reserve, POST fund=reserve).
-->
<script setup>
import GeneralLedgerTab from '@/components/settings/financial/GeneralLedgerTab.vue'
</script>

<template>
  <GeneralLedgerTab fund="reserve" />
</template>
