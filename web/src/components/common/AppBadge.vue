<script setup>
defineProps({
  variant: {
    type: String,
    default: 'default',
    // default | success | warning | danger | info
  },
  size: {
    type: String,
    default: 'md',
    // sm | md
  },
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-medium rounded-full',
      size === 'sm' && 'px-2 py-0.5 text-xs',
      size === 'md' && 'px-2.5 py-1 text-xs',
      variant === 'default' && 'bg-muted text-muted-fg',
      variant === 'success' && 'bg-success/10 text-success',
      variant === 'warning' && 'bg-amber/10 text-amber-dark',
      variant === 'danger'  && 'bg-danger/10 text-danger',
      variant === 'info'    && 'bg-navy/10 text-navy',
    ]"
  >
    <slot />
  </span>
</template>
