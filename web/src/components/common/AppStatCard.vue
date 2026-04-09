<!--
  AppStatCard — KPI summary card used on every page header row.

  Props:
    label      — card label e.g. "Total Estates"
    value      — primary display value e.g. "7" or "R 32 050"
    subtitle   — small text below value e.g. "241 units"
    valueClass — Tailwind class for value colour (default: text-foreground)
    trend      — optional { text: string, direction: 'up'|'down', colorClass: string }

  Slot:
    #icon — SVG icon element (rendered without any background container)
-->
<script setup>
defineProps({
  label:       { type: String, required: true },
  value:       { type: [String, Number], required: true },
  subtitle:    { type: String, default: '' },
  valueClass:  { type: String, default: 'text-foreground' },
  trend:       { type: Object, default: null }, // { text, direction, colorClass }
})
</script>

<template>
  <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
    <div class="p-5">
      <!-- Label row -->
      <div class="flex items-center justify-between mb-3">
        <span class="text-sm text-muted-foreground">{{ label }}</span>
        <slot name="icon" />
      </div>

      <!-- Value -->
      <p :class="['text-3xl font-bold font-body', valueClass]">{{ value }}</p>

      <!-- Subtitle (shown when no trend) -->
      <p v-if="subtitle && !trend" class="text-xs text-muted-foreground mt-1">{{ subtitle }}</p>

      <!-- Trend indicator -->
      <div v-if="trend" class="flex items-center gap-1 mt-1">
        <!-- Arrow up-right (bad — e.g. outstanding increasing) -->
        <svg v-if="trend.direction === 'up'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="['w-3 h-3', trend.colorClass]">
          <path d="M7 7h10v10"/><path d="M7 17 17 7"/>
        </svg>
        <!-- Arrow down-right (good — e.g. on track) -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="['w-3 h-3', trend.colorClass]">
          <path d="m7 7 10 10"/><path d="M17 7v10H7"/>
        </svg>
        <span :class="['text-xs', trend.colorClass]">{{ trend.text }}</span>
      </div>
    </div>
  </div>
</template>
