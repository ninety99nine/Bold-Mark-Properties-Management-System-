<!--
  TakeOnSection — a WeConnectU-style collapsible card used on the Take-on page.
  Header with a title and a +/− toggle; body is the default slot. When `done`,
  the header turns green with a check (matches WeConnectU's completed steps).

  Props:
    title — section heading
    open  — initial open state (default true)
    done  — completed state (green header + check)
-->
<script setup>
import { ref } from 'vue'

const props = defineProps({
  title: { type: String, required: true },
  open:  { type: Boolean, default: true },
  done:  { type: Boolean, default: false },
})

const isOpen = ref(props.open)
</script>

<template>
  <div class="rounded-md border border-border bg-white overflow-hidden">
    <button
      type="button"
      class="w-full flex items-center justify-between px-4 py-2.5 transition-colors"
      :class="done ? 'bg-success/10 hover:bg-success/15' : 'bg-muted/30 hover:bg-muted/50'"
      @click="isOpen = !isOpen"
    >
      <span class="flex items-center gap-2 text-sm font-medium" :class="done ? 'text-success' : 'text-muted-foreground'">
        <svg
          v-if="done"
          xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
        ><path d="M20 6 9 17l-5-5"/></svg>
        {{ title }}
      </span>
      <svg
        xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
        class="shrink-0" :class="done ? 'text-success' : 'text-muted-foreground'"
      >
        <line x1="5" y1="12" x2="19" y2="12" />
        <line v-if="!isOpen" x1="12" y1="5" x2="12" y2="19" />
      </svg>
    </button>

    <div v-show="isOpen" class="border-t border-border px-4 py-4">
      <slot />
    </div>
  </div>
</template>
