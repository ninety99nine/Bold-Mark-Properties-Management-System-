<script setup>
defineProps({
  padding: {
    type: String,
    default: 'md',
    // sm | md | lg | none
  },
  shadow: {
    type: String,
    default: 'md',
    // sm | md | lg | none
  },
})
</script>

<template>
  <div
    :class="[
      'bg-white border border-border/60 rounded',
      padding === 'sm'   && 'p-4',
      padding === 'md'   && 'p-6',
      padding === 'lg'   && 'p-8 md:p-10',
      padding === 'none' && '',
      shadow === 'sm'    && 'shadow-sm',
      shadow === 'md'    && 'shadow-lg',
      shadow === 'lg'    && 'shadow-2xl',
      shadow === 'none'  && '',
    ]"
  >
    <slot />
  </div>
</template>
