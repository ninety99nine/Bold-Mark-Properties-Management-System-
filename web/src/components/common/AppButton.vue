<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    // primary | secondary | outline | ghost | danger
  },
  size: {
    type: String,
    default: 'md',
    // sm | md | lg
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: Boolean,
  loading: Boolean,
  full: Boolean,
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 cursor-pointer select-none',
      'disabled:opacity-50 disabled:pointer-events-none',
      // Size
      size === 'sm' && 'h-9 px-4 text-sm rounded',
      size === 'md' && 'h-11 px-6 text-sm rounded',
      size === 'lg' && 'h-12 px-8 text-base rounded',
      // Variant
      variant === 'primary'   && 'bg-amber text-white hover:bg-amber-dark shadow-sm hover:shadow-md',
      variant === 'secondary' && 'bg-navy text-white hover:bg-navy-dark shadow-sm hover:shadow-md',
      variant === 'outline'   && 'border-2 border-navy text-navy bg-transparent hover:bg-navy hover:text-white',
      variant === 'ghost'     && 'text-navy bg-transparent hover:bg-muted',
      variant === 'danger'    && 'bg-danger text-white hover:opacity-90 shadow-sm',
      // Full width
      full && 'w-full',
    ]"
  >
    <svg v-if="loading" class="animate-spin -ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
    <slot />
  </button>
</template>
