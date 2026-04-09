<!--
  AppInput — Platform-wide branded input / textarea component.
  Single source of truth for ALL text inputs and textareas.

  Props:
    modelValue   — v-model value
    label        — field label (optional)
    type         — 'text' | 'email' | 'password' | 'number' | 'date' | 'search' | 'textarea'
    placeholder  — placeholder text
    error        — error message (shown below input, red border)
    hint         — helper text (shown below input when no error)
    required     — shows asterisk on label
    disabled     — disables the field
    readonly     — read-only mode
    id           — for label association
    autocomplete — native autocomplete attribute
    prefix       — text prefix inside border (e.g. "R", "P")
    suffix       — text suffix inside border (e.g. "%", "/mo")
    size         — 'md' (default, h-11) | 'sm' (h-9)
    rows         — textarea row height (default: 4)
    leadingIcon  — 'search' — shows a search icon and adjusts left padding

  Emits:
    update:modelValue — on input change (v-model)
    focus             — native focus event
    blur              — native blur event
    input             — native input event
    keydown           — native keydown event (supports @keydown.escape etc.)

  Usage:
    <AppInput v-model="name" label="Full Name" placeholder="e.g. John Smith" required />
    <AppInput v-model="note" type="textarea" :rows="6" label="Message" />
    <AppInput v-model="q"    leading-icon="search" placeholder="Search..." size="sm" />
    <AppInput v-model="amt"  prefix="R" label="Amount" type="number" />
-->
<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue:   { default: '' },
  label:        String,
  type:         { type: String, default: 'text' },
  placeholder:  String,
  error:        String,
  hint:         String,
  required:     Boolean,
  disabled:     Boolean,
  readonly:     Boolean,
  id:           String,
  autocomplete: String,
  /** Text prefix inside border — e.g. "R", "P" */
  prefix:       String,
  /** Text suffix inside border — e.g. "%", "/mo" */
  suffix:       String,
  /** Input height: 'md' (h-11, default) | 'sm' (h-8) */
  size:         { type: String, default: 'md' },
  /** For textarea — number of visible rows */
  rows:         { type: Number, default: 4 },
  /** Prepend a built-in icon. Currently supports: 'search' */
  leadingIcon:  { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'input', 'keydown'])

const isTextarea     = computed(() => props.type === 'textarea')
const hasAdornment   = computed(() => !!(props.prefix || props.suffix))
const hasLeadingIcon = computed(() => !!props.leadingIcon)

const h = computed(() => props.size === 'sm' ? 'h-8' : 'h-11')

// ── Border classes — no focus ring/outline per design spec ───────────────
const errorBorder  = 'border-danger'
const normalBorder = computed(() => 'border-border')

// ── Container div (prefix/suffix mode) ───────────────────────────────────
const containerClass = computed(() => [
  'flex items-center w-full border rounded bg-white overflow-hidden',
  h.value,
  props.disabled ? 'opacity-60 cursor-not-allowed' : '',
  props.error ? 'border-danger' : 'border-border',
])

const isSearchWithValue = computed(() => props.leadingIcon === 'search' && !!props.modelValue)

// ── Standalone input / textarea ───────────────────────────────────────────
const standaloneClass = computed(() => [
  'w-full text-sm text-fg bg-white border rounded outline-none',
  'placeholder:text-muted-fg',
  'disabled:bg-muted disabled:cursor-not-allowed disabled:opacity-60',
  isTextarea.value
    ? 'px-4 py-3 resize-none'
    : `${h.value} py-0 ${hasLeadingIcon.value ? (isSearchWithValue.value ? 'pl-9 pr-8' : 'pl-9 pr-4') : 'px-4'}`,
  props.error ? errorBorder : normalBorder.value,
])

function handleInput(e) {
  emit('update:modelValue', e.target.value)
  emit('input', e)
}
</script>

<template>
  <div class="flex flex-col gap-1.5">

    <label v-if="label" :for="id" class="text-sm font-medium text-fg">
      {{ label }}
      <span v-if="required" class="text-danger ml-0.5">*</span>
    </label>

    <!-- ── Textarea ───────────────────────────────────────────────────── -->
    <textarea
      v-if="isTextarea"
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      :rows="rows"
      :class="standaloneClass"
      @input="handleInput"
      @focus="$emit('focus', $event)"
      @blur="$emit('blur', $event)"
      @keydown="$emit('keydown', $event)"
    />

    <!-- ── Input with prefix / suffix ────────────────────────────────── -->
    <div v-else-if="hasAdornment" :class="containerClass">

      <span
        v-if="prefix"
        class="self-stretch flex items-center px-3 text-sm font-semibold text-muted-fg select-none
               flex-shrink-0 border-r border-border bg-muted/40"
      >{{ prefix }}</span>

      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :autocomplete="autocomplete"
        class="flex-1 min-w-0 h-full px-3 text-sm text-fg bg-transparent border-none outline-none
               placeholder:text-muted-fg disabled:cursor-not-allowed"
        @input="handleInput"
        @focus="$emit('focus', $event)"
        @blur="$emit('blur', $event)"
        @keydown="$emit('keydown', $event)"
      />

      <span
        v-if="suffix"
        class="self-stretch flex items-center px-3 text-sm font-semibold text-muted-fg select-none
               flex-shrink-0 border-l border-border bg-muted/40"
      >{{ suffix }}</span>

    </div>

    <!-- ── Standard input (with optional leading icon) ───────────────── -->
    <div v-else class="relative">

      <!-- Leading search icon -->
      <svg
        v-if="leadingIcon === 'search'"
        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-fg pointer-events-none"
        viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true"
      >
        <circle cx="11" cy="11" r="8"/>
        <path d="m21 21-4.3-4.3"/>
      </svg>

      <input
        :id="id"
        :type="type === 'textarea' ? 'text' : type"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :autocomplete="autocomplete"
        :class="standaloneClass"
        @input="handleInput"
        @focus="$emit('focus', $event)"
        @blur="$emit('blur', $event)"
        @keydown="$emit('keydown', $event)"
      />

      <!-- Clear button (search inputs only) -->
      <button
        v-if="isSearchWithValue"
        type="button"
        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center
               rounded-full text-muted-fg hover:text-fg hover:bg-muted transition-colors"
        @click="$emit('update:modelValue', '')"
        aria-label="Clear search"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3" aria-hidden="true">
          <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <p v-if="error" class="text-xs text-danger flex items-center gap-1">
      <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-muted-fg">{{ hint }}</p>

  </div>
</template>
