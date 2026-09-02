<!--
  AppAsyncSelect — branded searchable picker with API-backed, debounced search.

  Behaves like AppAccountSelect (WeConnectU-style dropdown + search box) but,
  instead of filtering a static list client-side, it can query the server as the
  user types. A default `options` list is shown when the search box is empty, so
  the user can either pick from the list or type to search the API.

  "Smart" debounce:
    • waits `debounce` ms after the last keystroke before hitting the API
    • only fires once the query reaches `minChars`
    • ignores out-of-order (stale) responses so the newest query always wins
    • an empty box reverts to the default `options` without a request

  Props:
    modelValue        — selected option value
    options           — array of { value, label } shown when the search is empty
    fetcher           — async (query) => [{ value, label }]; omit for local-only filtering
    placeholder       — trigger text when nothing is selected
    searchPlaceholder — placeholder inside the search box
    minChars          — min query length before the fetcher fires (default 1)
    debounce          — debounce delay in ms (default 300)
    error             — error message shown below

  Emits:
    update:modelValue
-->
<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue:        { default: null },
  options:           { type: Array, default: () => [] },
  fetcher:           { type: Function, default: null },
  placeholder:       { type: String, default: 'Nothing selected' },
  searchPlaceholder: { type: String, default: 'Start typing to search…' },
  minChars:          { type: Number, default: 1 },
  debounce:          { type: Number, default: 300 },
  error:             { type: String, default: null },
})
const emit = defineEmits(['update:modelValue'])

const isOpen        = ref(false)
const openUpward    = ref(false)
const search        = ref('')
const results       = ref([])
const loading       = ref(false)
const containerRef  = ref(null)
const triggerRef    = ref(null)
const searchRef     = ref(null)
const dropdownStyle = ref({})

// Remembers value → label for every option we have ever seen (default list,
// fetched results, explicit selection) so the trigger can render the selected
// label even after the search results have moved on.
const labelCache = ref(new Map())

function remember(list) {
  for (const o of list) {
    if (o && o.value != null) labelCache.value.set(o.value, o.label)
  }
}
watch(() => props.options, (list) => remember(list ?? []), { immediate: true })

const selectedLabel = computed(() => {
  if (props.modelValue == null || props.modelValue === '') return null
  return labelCache.value.get(props.modelValue)
    ?? props.options.find((o) => o.value === props.modelValue)?.label
    ?? null
})

const term = computed(() => search.value.trim())

// When the box is empty, show the default list; otherwise show whatever the last
// search produced (server results, or a local filter when no fetcher is given).
const displayOptions = computed(() => (term.value ? results.value : props.options))

// ── Debounced search ────────────────────────────────────────────────────────
let debounceTimer = null
let requestToken  = 0

function runSearch(query) {
  // No fetcher → filter the default list locally.
  if (!props.fetcher) {
    const t = query.toLowerCase()
    results.value = props.options.filter((o) => String(o.label).toLowerCase().includes(t))
    return
  }

  const token = ++requestToken
  loading.value = true
  Promise.resolve(props.fetcher(query))
    .then((rows) => {
      if (token !== requestToken) return   // a newer query has superseded this one
      const list = Array.isArray(rows) ? rows : []
      remember(list)
      results.value = list
    })
    .catch(() => {
      if (token === requestToken) results.value = []
    })
    .finally(() => {
      if (token === requestToken) loading.value = false
    })
}

watch(search, (val) => {
  clearTimeout(debounceTimer)
  const query = val.trim()

  if (!query) {                 // empty box → default list, cancel any pending request
    requestToken++
    loading.value = false
    results.value = []
    return
  }
  if (query.length < props.minChars) {
    results.value = []
    return
  }

  loading.value = !!props.fetcher
  debounceTimer = setTimeout(() => runSearch(query), props.debounce)
})

// ── Open / close & positioning ───────────────────────────────────────────────
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
    results.value = []
    nextTick(() => searchRef.value?.focus())
  }
}

function select(opt) {
  remember([opt])
  emit('update:modelValue', opt.value)
  isOpen.value = false
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target) && !e.target.closest('[data-async-panel]')) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  clearTimeout(debounceTimer)
})
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
          data-async-panel
          class="fixed z-[9999] bg-white border border-border rounded shadow-lg overflow-hidden"
          :style="dropdownStyle"
        >
          <!-- Search box -->
          <div class="p-2 border-b border-border">
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

          <!-- Option list -->
          <ul class="max-h-64 overflow-y-auto py-1">
            <li
              v-for="opt in displayOptions"
              :key="String(opt.value)"
              @click="select(opt)"
              :class="[
                'px-4 py-2 text-sm cursor-pointer transition-colors',
                opt.value === modelValue ? 'bg-amber text-white font-medium' : 'text-foreground hover:bg-amber hover:text-white',
              ]"
            >
              {{ opt.label }}
            </li>
            <li v-if="loading && !displayOptions.length" class="px-4 py-3 text-sm text-muted-foreground italic">Searching…</li>
            <li v-else-if="!displayOptions.length" class="px-4 py-3 text-sm text-muted-foreground italic">
              {{ term ? 'No matches found' : 'No options available' }}
            </li>
          </ul>
        </div>
      </Teleport>
    </div>

    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
