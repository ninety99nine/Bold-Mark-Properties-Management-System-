<!--
  AppButton — Platform-wide reusable button.

  Variants:
    primary      — amber/accent fill          (Run Billing, Save, Create, Add Estate)
    secondary    — navy fill                  (Upload Cashbook)
    outline      — border only               (Bulk Import, Cancel, Allocate, Edit Entry, Print)
    ghost        — transparent, hover reveal  (back nav, View All, icon-only toolbar actions)
    danger       — red fill                  (destructive primary actions)
    danger-ghost — transparent, red text     (icon-only delete: trash, remove proof)
    link         — no border/bg, muted text  (View All → inline text links)

  Sizes:
    sm — h-8,  px-3, text-xs   compact inline actions
    md — h-10, px-4, text-sm   standard action buttons  (default)
    lg — h-12, px-6, text-sm   prominent / full-width CTAs

  Props:
    square — icon-only mode: width = height, no horizontal padding
    full   — w-full
-->
<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    // primary | secondary | outline | ghost | danger | danger-ghost | link
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
  loading:  Boolean,
  full:     Boolean,
  square:   Boolean,  // icon-only — makes width = height, removes horizontal padding
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 font-medium font-body transition-all duration-150 cursor-pointer select-none rounded whitespace-nowrap',
      'disabled:opacity-50 disabled:pointer-events-none',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',

      // ── Sizes — standard (with horizontal padding) ────────────────────
      !square && size === 'sm' && 'h-8 px-3 text-xs',
      !square && size === 'md' && 'h-10 px-4 text-sm',
      !square && size === 'lg' && 'h-12 px-6 text-sm',

      // ── Sizes — square / icon-only (no horizontal padding) ───────────
      square  && size === 'sm' && 'h-8 w-8 text-xs',
      square  && size === 'md' && 'h-10 w-10 text-sm',
      square  && size === 'lg' && 'h-12 w-12 text-sm',

      // ── Variants ─────────────────────────────────────────────────────
      variant === 'primary'      && 'bg-accent text-accent-foreground hover:bg-amber-dark shadow-sm',
      variant === 'secondary'    && 'bg-primary text-primary-foreground hover:bg-navy-light shadow-sm',
      variant === 'outline'      && 'border border-border bg-card text-foreground hover:bg-muted',
      variant === 'ghost'        && 'text-foreground bg-transparent hover:bg-muted',
      variant === 'danger'       && 'bg-destructive text-destructive-foreground hover:opacity-90 shadow-sm',
      variant === 'danger-ghost' && 'text-destructive bg-transparent hover:bg-destructive/10',
      variant === 'link'         && 'text-muted-foreground bg-transparent hover:text-foreground px-0 underline-offset-4 hover:underline',

      // ── Full width ────────────────────────────────────────────────────
      full && 'w-full',
    ]"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin h-4 w-4 shrink-0"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>
    <slot />
  </button>
</template>
