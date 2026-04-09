<!--
  AppSelect — Platform-wide branded dropdown/select component.
  Replaces all native <select> elements with a custom branded dropdown.

  Props:
    modelValue  — currently selected value (use with v-model)
    options     — array of { value, label } objects, or plain strings
    placeholder — text shown when no value is selected
    disabled    — disables the select
    id          — optional id for label association

  Emits:
    update:modelValue — new value when option selected
    change            — fires with new value (for @change handlers)

  Usage:
    <AppSelect
      v-model="country"
      :options="[{ value: 'ZA', label: 'South Africa (ZA)' }]"
      placeholder="Select country..."
    />

    // String options (value === label):
    <AppSelect v-model="val" :options="['Option A', 'Option B']" placeholder="Pick one..." />
-->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue:  { default: null },
  options:     { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  disabled:    { type: Boolean, default: false },
  id:          { type: String, default: null },
  label:       { type: String, default: null },
  required:    { type: Boolean, default: false },
  error:       { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen        = ref(false)
const openUpward    = ref(false)
const containerRef  = ref(null)
const triggerRef    = ref(null)
const dropdownStyle = ref({})

// Normalise options → always { value, label }
const normalised = computed(() =>
  props.options.map(o =>
    typeof o === 'object' ? o : { value: o, label: String(o) }
  )
)

// Display label for the currently selected value
const selectedLabel = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return null
  return normalised.value.find(o => o.value === props.modelValue)?.label ?? null
})

function toggle() {
  if (props.disabled) return
  if (!isOpen.value) {
    const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
    if (rect) {
      const dropdownHeight = Math.min(normalised.value.length * 42 + 8, 248)
      openUpward.value = rect.bottom + dropdownHeight > window.innerHeight
      // Fixed positioning escapes any overflow-hidden ancestor
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

function select(opt) {
  emit('update:modelValue', opt.value)
  emit('change', opt.value)
  isOpen.value = false
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
  <!-- Outer wrapper doubles as click-outside boundary -->
  <div
    ref="containerRef"
    class="flex flex-col gap-1.5"
    :class="disabled && 'opacity-50 pointer-events-none'"
  >
    <!-- ── Label ─────────────────────────────────────────────────────── -->
    <label v-if="label" :for="id" class="text-sm font-medium text-fg">
      {{ label }}<span v-if="required" class="text-danger ml-0.5">*</span>
    </label>

    <!-- ── Trigger + dropdown (relative container) ───────────────────── -->
    <div class="relative">
      <button
        ref="triggerRef"
        type="button"
        :id="id"
        @click="toggle"
        class="w-full h-11 flex items-center justify-between gap-2 px-4 py-0 text-sm rounded border bg-white outline-none select-none cursor-pointer border-border"
        :class="selectedLabel ? 'text-foreground' : 'text-muted-foreground'"
      >
        <span class="truncate text-left">{{ selectedLabel ?? placeholder }}</span>
        <!-- Chevron icon -->
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

      <!-- ── Dropdown list ─────────────────────────────────────────────── -->
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
          <ul class="py-1 max-h-60 overflow-y-auto">
            <li
              v-for="opt in normalised"
              :key="String(opt.value)"
              @click="select(opt)"
              :class="[
                'px-4 py-2.5 text-sm cursor-pointer transition-colors duration-100',
                opt.value === modelValue
                  ? 'bg-amber text-white font-medium'
                  : 'text-foreground hover:bg-amber hover:text-white',
              ]"
            >
              {{ opt.label }}
            </li>
            <li v-if="normalised.length === 0" class="px-4 py-2.5 text-sm text-muted-foreground italic">
              No options available
            </li>
          </ul>
        </div>
      </Transition>
    </div>

    <!-- ── Error message ─────────────────────────────────────────────── -->
    <p v-if="error" class="text-xs text-danger flex items-center gap-1">
      <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
  </div>
</template>
