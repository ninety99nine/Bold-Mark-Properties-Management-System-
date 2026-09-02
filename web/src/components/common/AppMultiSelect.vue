<!--
  AppMultiSelect — Platform-wide branded multi-select dropdown (WeConnectU-style).
  Mirrors AppSelect, but v-model is an array of selected values and options are
  toggled on/off with a checkmark. Useful for "Select Users" style pickers.

  Props:
    modelValue  — array of selected values (use with v-model)
    options     — array of { value, label, sublabel? } objects, or plain strings
    placeholder — text shown when nothing is selected
    disabled    — disables the select
    id          — optional id for label association
    label       — optional field label
    heading     — optional heading shown above the option list (e.g. "Users")

  Emits:
    update:modelValue — new array when the selection changes

  Usage:
    <AppMultiSelect v-model="userIds" :options="userOptions" heading="Users"
      label="Select Users" placeholder="Select users..." />
-->
<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue:  { type: Array, default: () => [] },
  options:     { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  disabled:    { type: Boolean, default: false },
  id:          { type: String, default: null },
  label:       { type: String, default: null },
  heading:     { type: String, default: null },
  required:    { type: Boolean, default: false },
  error:       { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const isOpen        = ref(false)
const openUpward    = ref(false)
const containerRef  = ref(null)
const triggerRef    = ref(null)
const dropdownStyle = ref({})

// Normalise options → always { value, label, sublabel }
const normalised = computed(() =>
  props.options.map(o =>
    typeof o === 'object' ? o : { value: o, label: String(o) }
  )
)

const isSelected = (value) => props.modelValue.includes(value)

// Selected options (in the order they appear in the option list), rendered as
// removable chips in the trigger.
const selectedOptions = computed(() =>
  normalised.value.filter(o => props.modelValue.includes(o.value))
)

const hasSelection = computed(() => selectedOptions.value.length > 0)

function removeOption(value) {
  emit('update:modelValue', props.modelValue.filter(v => v !== value))
}

// Recompute the (fixed-positioned) dropdown's placement from the trigger's
// current rect. Called on open AND whenever the trigger's height changes — e.g.
// selecting an item wraps the chips onto another row, growing the trigger, so
// the panel must follow rect.bottom instead of overlapping the new chip row.
function updatePosition() {
  const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
  if (!rect) return
  const dropdownHeight = Math.min(normalised.value.length * 42 + 40, 288)
  openUpward.value = rect.bottom + dropdownHeight > window.innerHeight
  // Fixed positioning escapes any overflow-hidden ancestor (e.g. modal body)
  dropdownStyle.value = {
    left:  rect.left + 'px',
    width: rect.width + 'px',
    ...(openUpward.value
      ? { bottom: window.innerHeight - rect.top + 4 + 'px', top: 'auto' }
      : { top: rect.bottom + 4 + 'px', bottom: 'auto' }),
  }
}

function toggle() {
  if (props.disabled) return
  if (!isOpen.value) updatePosition()
  isOpen.value = !isOpen.value
}

function toggleOption(opt) {
  const next = isSelected(opt.value)
    ? props.modelValue.filter(v => v !== opt.value)
    : [...props.modelValue, opt.value]
  emit('update:modelValue', next)
  // The chip row count (and trigger height) changes — reposition the panel.
  if (isOpen.value) nextTick(updatePosition)
}

function onReposition() {
  if (isOpen.value) updatePosition()
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  window.addEventListener('resize', onReposition)
  window.addEventListener('scroll', onReposition, true)
})
onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  window.removeEventListener('resize', onReposition)
  window.removeEventListener('scroll', onReposition, true)
})
</script>

<template>
  <div
    ref="containerRef"
    class="flex flex-col gap-1.5 min-w-0"
    :class="disabled && 'opacity-50 pointer-events-none'"
  >
    <label v-if="label" :for="id" class="text-sm font-medium text-fg">
      {{ label }}<span v-if="required" class="text-danger ml-0.5">*</span>
    </label>

    <div class="relative">
      <div
        ref="triggerRef"
        role="button"
        tabindex="0"
        :id="id"
        @click="toggle"
        @keydown.enter.prevent="toggle"
        @keydown.space.prevent="toggle"
        class="w-full min-h-11 flex items-start justify-between gap-2 px-3 py-1.5 text-sm rounded border bg-white outline-none select-none cursor-pointer border-border"
        :class="hasSelection ? 'text-foreground' : 'text-muted-foreground'"
      >
        <!-- Chips wrap to as many rows as needed -->
        <div v-if="hasSelection" class="flex flex-wrap gap-1.5 py-0.5 min-w-0">
          <span
            v-for="opt in selectedOptions"
            :key="String(opt.value)"
            class="inline-flex items-center gap-1 max-w-full pl-2 pr-1 py-0.5 rounded bg-muted text-xs text-foreground border border-border"
          >
            <span class="truncate">{{ opt.label }}</span>
            <button
              type="button"
              @click.stop="removeOption(opt.value)"
              class="shrink-0 inline-flex items-center justify-center w-4 h-4 rounded-full text-muted-foreground hover:bg-border hover:text-foreground transition-colors"
              :aria-label="`Remove ${opt.label}`"
            >
              <svg class="w-3 h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M6 6l8 8M14 6l-8 8" />
              </svg>
            </button>
          </span>
        </div>
        <span v-else class="truncate text-left py-1">{{ placeholder }}</span>

        <svg
          class="w-4 h-4 shrink-0 mt-2 text-muted-foreground transition-transform duration-200"
          :class="isOpen && 'rotate-180'"
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
            clip-rule="evenodd"
          />
        </svg>
      </div>

      <Transition
        enter-active-class="transition duration-100 ease-out"
        :enter-from-class="openUpward ? 'opacity-0 translate-y-1' : 'opacity-0 -translate-y-1'"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        :leave-to-class="openUpward ? 'opacity-0 translate-y-1' : 'opacity-0 -translate-y-1'"
      >
        <div
          v-if="isOpen"
          class="fixed z-[9999] bg-white border border-border rounded shadow-lg overflow-hidden"
          :style="dropdownStyle"
        >
          <p v-if="heading" class="px-4 pt-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {{ heading }}
          </p>
          <ul class="py-1 max-h-60 overflow-y-auto">
            <li
              v-for="opt in normalised"
              :key="String(opt.value)"
              @click="toggleOption(opt)"
              class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm cursor-pointer transition-colors duration-100 text-foreground hover:bg-muted"
            >
              <span class="min-w-0">
                <span class="block truncate">{{ opt.label }}</span>
                <span v-if="opt.sublabel" class="block truncate text-xs text-muted-foreground">{{ opt.sublabel }}</span>
              </span>
              <svg
                v-if="isSelected(opt.value)"
                class="w-4 h-4 shrink-0 text-success"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                stroke-linecap="round" stroke-linejoin="round"
              ><polyline points="20 6 9 17 4 12"/></svg>
            </li>
            <li v-if="normalised.length === 0" class="px-4 py-2.5 text-sm text-muted-foreground italic">
              No users available
            </li>
          </ul>
        </div>
      </Transition>
    </div>

    <p v-if="error" class="text-xs text-danger flex items-center gap-1">
      <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
  </div>
</template>
