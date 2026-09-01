<!--
  AppConfirm — branded confirmation dialog (replaces window.confirm).

  Controlled via the `show` prop; emits `confirm` / `cancel`. Renders above other
  modals (z-[60]). Pair with a promise-based helper for drop-in replacement of
  native confirm():

    const ok = await askConfirm({ title, message, confirmLabel, danger })
-->
<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
  show:         { type: Boolean, default: false },
  title:        { type: String,  default: 'Confirm' },
  message:      { type: String,  default: '' },
  confirmLabel: { type: String,  default: 'OK' },
  cancelLabel:  { type: String,  default: 'Cancel' },
  danger:       { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

function onKeydown(e) {
  if (!props.show) return
  if (e.key === 'Escape') emit('cancel')
  if (e.key === 'Enter')  emit('confirm')
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="emit('cancel')">
      <div class="absolute inset-0 bg-navy/60 backdrop-blur-sm" @click="emit('cancel')" />

      <div class="relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-2xl">
        <div class="px-6 pb-2 pt-5">
          <h3 class="font-body text-lg font-semibold text-navy-dark">{{ title }}</h3>
        </div>
        <div class="px-6 pb-5 text-sm leading-relaxed text-foreground">
          <slot>{{ message }}</slot>
        </div>
        <div class="flex justify-end gap-2 border-t border-border bg-muted/30 px-6 py-3">
          <button
            type="button"
            class="inline-flex h-9 items-center rounded-md border border-border bg-white px-4 text-sm font-medium text-foreground hover:bg-muted"
            @click="emit('cancel')"
          >{{ cancelLabel }}</button>
          <button
            type="button"
            :class="[
              'inline-flex h-9 items-center rounded-md px-4 text-sm font-semibold text-white shadow-sm',
              danger ? 'bg-destructive hover:opacity-90' : 'bg-navy hover:bg-navy-light',
            ]"
            @click="emit('confirm')"
          >{{ confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
