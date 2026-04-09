<!--
  AppBadge — Status/label pill component.

  Props:
    variant — visual style
      'default'  — gray background, gray text
      'success'  — green tint, green text
      'warning'  — amber tint, amber text
      'danger'   — red tint, red text
      'info'     — navy tint, navy text
    bordered — adds border ring matching the variant colour (for invoice/payment status badges)
    size    — 'sm' | 'md'

  Usage:
    <AppBadge variant="success">Paid</AppBadge>
    <AppBadge variant="danger" bordered>Overdue</AppBadge>
-->
<script setup>
defineProps({
  variant: {
    type: String,
    default: 'default',
    // default | success | warning | danger | info
  },
  bordered: {
    type: Boolean,
    default: false,
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
      'inline-flex items-center font-medium leading-tight',
      bordered ? 'rounded-full border' : 'rounded-full',
      size === 'sm' && 'px-2 py-px text-[10px]',
      size === 'md' && 'px-2.5 py-1 text-xs',
      // No-border variants
      !bordered && variant === 'default'  && 'bg-muted text-muted-foreground',
      !bordered && variant === 'success'  && 'bg-success/10 text-success',
      !bordered && variant === 'warning'  && 'bg-amber/10 text-amber-dark',
      !bordered && variant === 'danger'   && 'bg-destructive/10 text-destructive',
      !bordered && variant === 'info'     && 'bg-primary/10 text-primary',
      // Bordered variants (invoice status pills)
      bordered && variant === 'default'  && 'bg-muted text-muted-foreground border-border',
      bordered && variant === 'success'  && 'bg-success/10 text-success border-success/20',
      bordered && variant === 'warning'  && 'bg-amber/10 text-amber-dark border-amber/20',
      bordered && variant === 'danger'   && 'bg-destructive/10 text-destructive border-destructive/20',
      bordered && variant === 'info'     && 'bg-primary/10 text-primary border-primary/20',
    ]"
  >
    <slot />
  </span>
</template>
