<!--
  AppMultiSelect — Platform-wide branded multi-select dropdown (WeConnectU-style).
  Mirrors AppSelect, but v-model is an array of selected values and options are
  toggled on/off with a checkmark. Useful for "Select Users" style pickers.

  Optional search (same UX as AppAsyncSelect):
    • pass `searchable` to filter the given `options` locally, or
    • pass `fetcher` to query the API as the user types (debounced).
  A search box only appears when one of those is set, so existing static-list
  usages are unchanged. Selected chips keep their labels even after the option
  list moves on (async results), via an internal value→label cache.

  Props:
    modelValue        — array of selected values (use with v-model)
    options           — array of { value, label, sublabel? } objects, or plain strings
    fetcher           — async (query) => [{ value, label, sublabel? }]; enables API search
    searchable        — show a search box that filters `options` locally
    searchPlaceholder — placeholder inside the search box
    minChars          — min query length before the fetcher fires (default 1)
    debounce          — debounce delay in ms (default 300)
    placeholder       — text shown when nothing is selected
    disabled          — disables the select
    id                — optional id for label association
    label             — optional field label
    heading           — optional heading shown above the option list (e.g. "Users")

  Emits:
    update:modelValue — new array when the selection changes
-->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue:        { type: Array, default: () => [] },
  options:           { type: Array, default: () => [] },
  fetcher:           { type: Function, default: null },
  searchable:        { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Start typing to search…' },
  minChars:          { type: Number, default: 1 },
  debounce:          { type: Number, default: 300 },
  placeholder:       { type: String, default: 'Select...' },
  disabled:          { type: Boolean, default: false },
  id:                { type: String, default: null },
  label:             { type: String, default: null },
  heading:           { type: String, default: null },
  required:          { type: Boolean, default: false },
  error:             { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const isOpen        = ref(false)
const openUpward    = ref(false)
const containerRef  = ref(null)
const triggerRef    = ref(null)
const searchRef     = ref(null)
const dropdownStyle = ref({})

const search  = ref('')
const results = ref([])
const loading = ref(false)

const showSearch = computed(() => !!props.fetcher || props.searchable)

// Normalise options → always { value, label, sublabel }
const normalised = computed(() =>
  props.options.map(o =>
    typeof o === 'object' ? o : { value: o, label: String(o) }
  )
)

// Remembers value → option for everything we have seen (given options, fetched
// results, explicit toggles) so selected chips render even when the current
// option list no longer contains them.
const labelCache = ref(new Map())
function remember(list) {
  for (const o of list) {
    if (o && o.value != null) labelCache.value.set(o.value, o)
  }
}
watch(normalised, (list) => remember(list), { immediate: true })

const term = computed(() => search.value.trim())

// When the box is empty, show the given options; otherwise show the search hits.
const displayOptions = computed(() => (showSearch.value && term.value ? results.value : normalised.value))

const isSelected = (value) => props.modelValue.includes(value)

// Selected options as removable chips — resolved from the cache so a selection
// made from search results keeps its label after the results change.
const selectedOptions = computed(() =>
  props.modelValue.map(v => labelCache.value.get(v) ?? { value: v, label: String(v) })
)

const hasSelection = computed(() => selectedOptions.value.length > 0)

function removeOption(value) {
  emit('update:modelValue', props.modelValue.filter(v => v !== value))
}

// ── Debounced search ────────────────────────────────────────────────────────
let debounceTimer = null
let requestToken  = 0

function runSearch(query) {
  if (!props.fetcher) {
    const t = query.toLowerCase()
    results.value = normalised.value.filter(o => String(o.label).toLowerCase().includes(t))
    return
  }
  const token = ++requestToken
  loading.value = true
  Promise.resolve(props.fetcher(query))
    .then((rows) => {
      if (token !== requestToken) return
      const list = (Array.isArray(rows) ? rows : []).map(o =>
        typeof o === 'object' ? o : { value: o, label: String(o) }
      )
      remember(list)
      results.value = list
    })
    .catch(() => { if (token === requestToken) results.value = [] })
    .finally(() => { if (token === requestToken) loading.value = false })
}

watch(search, (val) => {
  clearTimeout(debounceTimer)
  const query = val.trim()
  if (!query) {
    requestToken++
    loading.value = false
    results.value = []
    return
  }
  if (query.length < props.minChars) { results.value = []; return }
  loading.value = !!props.fetcher
  debounceTimer = setTimeout(() => runSearch(query), props.debounce)
})

// Reposition when the option list height changes (search narrows results).
watch(displayOptions, () => { if (isOpen.value) nextTick(updatePosition) })

// Recompute the (fixed-positioned) dropdown's placement from the trigger's
// current rect. Called on open AND whenever the trigger's height changes — e.g.
// selecting an item wraps the chips onto another row, growing the trigger, so
// the panel must follow rect.bottom instead of overlapping the new chip row.
function updatePosition() {
  const rect = (triggerRef.value ?? containerRef.value)?.getBoundingClientRect()
  if (!rect) return
  const dropdownHeight = Math.min(displayOptions.value.length * 42 + 40, 288) + (showSearch.value ? 52 : 0)
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
  if (isOpen.value && showSearch.value) {
    search.value = ''
    results.value = []
    nextTick(() => searchRef.value?.focus())
  }
}

function toggleOption(opt) {
  remember([opt])
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
  if (containerRef.value && !containerRef.value.contains(e.target) && !e.target.closest('[data-multi-panel]')) {
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
  clearTimeout(debounceTimer)
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

      <Teleport to="body">
        <div
          v-if="isOpen"
          data-multi-panel
          class="fixed z-[9999] bg-white border border-border rounded shadow-lg overflow-hidden"
          :style="dropdownStyle"
        >
          <!-- Search box (only when searchable / async) -->
          <div v-if="showSearch" class="p-2 border-b border-border">
            <div class="relative">
              <input
                ref="searchRef"
                v-model="search"
                type="text"
                :placeholder="searchPlaceholder"
                class="w-full h-9 pl-3 pr-8 text-sm rounded border border-border outline-none focus:border-amber"
              />
              <svg v-if="loading" class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-muted-foreground" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
          </div>

          <p v-if="heading" class="px-4 pt-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {{ heading }}
          </p>
          <ul class="py-1 max-h-60 overflow-y-auto">
            <li
              v-for="opt in displayOptions"
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
            <li v-if="loading && !displayOptions.length" class="px-4 py-2.5 text-sm text-muted-foreground italic">
              Searching…
            </li>
            <li v-else-if="!displayOptions.length" class="px-4 py-2.5 text-sm text-muted-foreground italic">
              {{ term ? 'No matches found' : 'No options available' }}
            </li>
          </ul>
        </div>
      </Teleport>
    </div>

    <p v-if="error" class="text-xs text-danger flex items-center gap-1">
      <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
  </div>
</template>
