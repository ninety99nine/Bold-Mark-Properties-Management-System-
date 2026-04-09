<!--
  AppDropdownItem — A single item inside an AppDropdown.

  Props:
    label    — display text
    variant  — 'default' (dark text) | 'danger' (red text)
    divider  — when true, renders a horizontal rule above this item instead of a menu item
-->
<script setup>
defineProps({
  label: {
    type: String,
    default: '',
  },
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'danger'].includes(v),
  },
  divider: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['click'])
</script>

<template>
  <!-- Divider mode -->
  <div v-if="divider" class="mx-2 my-1 border-t border-border" role="separator" />

  <!-- Item mode -->
  <button
    v-else
    type="button"
    role="menuitem"
    :disabled="disabled"
    :class="[
      'w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors',
      'disabled:opacity-40 disabled:pointer-events-none',
      variant === 'danger'
        ? 'text-destructive hover:bg-destructive/5'
        : 'text-foreground hover:bg-muted',
    ]"
    @click="$emit('click')"
  >
    <!-- Optional icon slot -->
    <span v-if="$slots.icon" class="shrink-0" :class="variant === 'danger' ? 'text-destructive' : 'text-muted-foreground'">
      <slot name="icon" />
    </span>
    <span>{{ label }}</span>
  </button>
</template>
