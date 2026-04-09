<!--
  AppTabFilter — Pill-style tab filter used across all list pages.

  Props:
    tabs        — array of { label: string, value: string }
    modelValue  — the currently active tab value (v-model)

  Usage:
    <AppTabFilter :tabs="tabs" v-model="activeTab" />
-->
<script setup>
defineProps({
  tabs:       { type: Array, required: true },
  modelValue: { type: String, required: true },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex gap-1.5">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      @click="$emit('update:modelValue', tab.value)"
      :class="[
        'px-2.5 py-1 rounded text-xs font-medium transition-colors',
        modelValue === tab.value
          ? 'bg-primary text-primary-foreground'
          : 'bg-muted text-muted-foreground hover:text-foreground',
      ]"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
