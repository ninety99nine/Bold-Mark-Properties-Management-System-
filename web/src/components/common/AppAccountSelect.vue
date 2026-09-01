<!--
  AppAccountSelect — WeConnectU-style chart-of-accounts picker.

  A branded dropdown that groups options by category header (e.g. "1000/000 -
  INCOME") and includes a search box, mirroring WeConnectU's account selector.

  Props:
    modelValue  — selected option value (ledger id)
    options     — array of { value, label, category } (label already "code - name")
    placeholder — text shown when nothing selected
    error       — error message shown below

  Emits:
    update:modelValue
-->
<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue:  { default: null },
  options:     { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Nothing selected' },
  error:       { type: String, default: null },
})
const emit = defineEmits(['update:modelValue'])

const isOpen        = ref(false)
const openUpward    = ref(false)
const search        = ref('')
const containerRef  = ref(null)
const triggerRef    = ref(null)
const searchRef     = ref(null)
const dropdownStyle = ref({})

const selectedLabel = computed(() => {
  const match = props.options.find((o) => o.value === props.modelValue)
  return match?.label ?? null
})

// Filter, then group by category preserving option order.
const groups = computed(() => {
  const term = search.value.trim().toLowerCase()
  const filtered = term
    ? props.options.filter((o) => o.label.toLowerCase().includes(term))
    : props.options

  const map = new Map()
  for (const opt of filtered) {
    const cat = opt.category || 'Other Accounts'
    if (!map.has(cat)) map.set(cat, [])
    map.get(cat).push(opt)
  }
  // Push "Other Accounts" to the end.
  const entries = [...map.entries()]
  entries.sort((a, b) => (a[0] === 'Other Accounts' ? 1 : b[0] === 'Other Accounts' ? -1 : 0))
  return entries.map(([category, items]) => ({ category, items }))
})

const hasResults = computed(() => groups.value.some((g) => g.items.length))

function toggle() {
  if (!isOpen.value) {
    const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
    if (rect) {
      const dropdownHeight = 320
      openUpward.value = rect.bottom + dropdownHeight > window.innerHeight && rect.top > dropdownHeight
      dropdownStyle.value = {
        left:  rect.left + 'px',
        width: Math.max(rect.width, 320) + 'px',
        ...(openUpward.value
          ? { bottom: window.innerHeight - rect.top + 4 + 'px', top: 'auto' }
          : { top: rect.bottom + 4 + 'px', bottom: 'auto' }),
      }
    }
  }
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    search.value = ''
    nextTick(() => searchRef.value?.focus())
  }
}

function select(opt) {
  emit('update:modelValue', opt.value)
  isOpen.value = false
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target) && !e.target.closest('[data-account-panel]')) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="containerRef" class="flex flex-col gap-1.5">
    <div class="relative">
      <button
        ref="triggerRef"
        type="button"
        @click="toggle"
        class="w-full h-11 flex items-center justify-between gap-2 px-4 text-sm rounded border bg-white outline-none select-none cursor-pointer"
        :class="[
          error ? 'border-danger' : 'border-border',
          selectedLabel ? 'text-foreground' : 'text-muted-foreground',
        ]"
      >
        <span class="truncate text-left">{{ selectedLabel ?? placeholder }}</span>
        <svg class="w-4 h-4 shrink-0 text-muted-foreground transition-transform duration-200" :class="isOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
      </button>

      <Teleport to="body">
        <div
          v-if="isOpen"
          data-account-panel
          class="fixed z-[9999] bg-white border border-border rounded shadow-lg overflow-hidden"
          :style="dropdownStyle"
        >
          <!-- Search box -->
          <div class="p-2 border-b border-border">
            <input
              ref="searchRef"
              v-model="search"
              type="text"
              placeholder="Search accounts…"
              class="w-full h-9 px-3 text-sm rounded border border-border outline-none focus:border-amber"
            />
          </div>

          <!-- Grouped, scrollable option list -->
          <ul class="max-h-64 overflow-y-auto py-1">
            <template v-for="group in groups" :key="group.category">
              <li v-if="group.items.length" class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-foreground bg-muted/40">
                {{ group.category }}
              </li>
              <li
                v-for="opt in group.items"
                :key="String(opt.value)"
                @click="select(opt)"
                :class="[
                  'px-4 py-2 text-sm cursor-pointer transition-colors',
                  opt.value === modelValue ? 'bg-amber text-white font-medium' : 'text-foreground hover:bg-amber hover:text-white',
                ]"
              >
                {{ opt.label }}
              </li>
            </template>
            <li v-if="!hasResults" class="px-4 py-3 text-sm text-muted-foreground italic">No accounts found</li>
          </ul>
        </div>
      </Teleport>
    </div>

    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
