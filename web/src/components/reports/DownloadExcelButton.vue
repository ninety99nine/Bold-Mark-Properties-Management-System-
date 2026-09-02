<!--
  DownloadExcelButton — the green WeConnectU "Download Excel" button used across
  the GL report pages. Emits @click; parent hits the report's ?export=1 twin.
-->
<script setup>
defineProps({
  disabled: { type: Boolean, default: false },
  label:    { type: String, default: 'Download Excel' },
})
defineEmits(['click'])
</script>

<template>
  <button
    type="button"
    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md bg-[#2c9b67] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#268a5b] disabled:opacity-60"
    :disabled="disabled"
    @click="$emit('click')"
  >
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" />
    </svg>
    {{ label }}
  </button>
</template>
