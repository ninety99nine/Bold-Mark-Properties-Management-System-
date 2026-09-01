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
import { ref, computed, onMounted, onUnmounted } from 'vue'

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

// Trigger text: comma-joined labels of the selected values, else placeholder.
const selectedText = computed(() => {
  if (!props.modelValue.length) return null
  return normalised.value
    .filter(o => props.modelValue.includes(o.value))
    .map(o => o.label)
    .join(', ')
})

function toggle() {
  if (props.disabled) return
  if (!isOpen.value) {
    const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
    if (rect) {
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
  }
  isOpen.value = !isOpen.value
}

function toggleOption(opt) {
  const next = isSelected(opt.value)
    ? props.modelValue.filter(v => v !== opt.value)
    : [...props.modelValue, opt.value]
  emit('update:modelValue', next)
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

onMounted(()  => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div
    ref="containerRef"
    class="flex flex-col gap-1.5"
    :class="disabled && 'opacity-50 pointer-events-none'"
  >
    <label v-if="label" :for="id" class="text-sm font-medium text-fg">
      {{ label }}<span v-if="required" class="text-danger ml-0.5">*</span>
    </label>

    <div class="relative">
      <button
        ref="triggerRef"
        type="button"
        :id="id"
        @click="toggle"
        class="w-full min-h-11 flex items-center justify-between gap-2 px-4 py-2 text-sm rounded border bg-white outline-none select-none cursor-pointer border-border"
        :class="selectedText ? 'text-foreground' : 'text-muted-foreground'"
      >
        <span class="truncate text-left">{{ selectedText ?? placeholder }}</span>
        <svg
          class="w-4 h-4 shrink-0 text-muted-foreground transition-transform duration-200"
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
      </button>

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
