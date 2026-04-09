<script setup>
import AppButton from '@/components/common/AppButton.vue'

defineProps({
  variant: {
    type: String,
    default: 'info',
    // info | success | warning | danger
  },
  title: String,
  dismissible: Boolean,
})

const emit = defineEmits(['dismiss'])
</script>

<template>
  <div
    :class="[
      'flex gap-3 p-4 rounded border text-sm',
      variant === 'info'    && 'bg-navy/5 border-navy/20 text-navy',
      variant === 'success' && 'bg-success/5 border-success/20 text-success',
      variant === 'warning' && 'bg-amber/5 border-amber/20 text-amber-dark',
      variant === 'danger'  && 'bg-danger/5 border-danger/20 text-danger',
    ]"
  >
    <div class="flex-1">
      <p v-if="title" class="font-semibold mb-0.5">{{ title }}</p>
      <slot />
    </div>
    <AppButton
      v-if="dismissible"
      variant="ghost"
      square
      size="sm"
      class="opacity-60 hover:opacity-100 hover:bg-transparent flex-shrink-0"
      @click="$emit('dismiss')"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </AppButton>
  </div>
</template>
