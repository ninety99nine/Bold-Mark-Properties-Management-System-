<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import AppModal      from './AppModal.vue'
import AppButton     from './AppButton.vue'
import AppInput      from './AppInput.vue'
import AppSelect     from './AppSelect.vue'
import AppDatePicker from './AppDatePicker.vue'

const props = defineProps({
  searchPlaceholder: { type: String, default: 'Search...' },
  filterFields: {
    // [{ key: 'occupancy', label: 'Occupancy', options: [{ value: 'owner', label: 'Owner' }] }]
    type: Array,
    default: () => [],
  },
  sortOptions: {
    // [{ value: 'newest', label: 'Newest first' }]
    type: Array,
    default: () => [],
  },
  storageKey: { type: String, default: 'table-toolbar' },
  dateRangeContext: { type: String, default: 'Added' }, // e.g. "Added", "Invoice Date", "Transaction Date"
  initialDateRange: { type: String, default: 'all_time' }, // seed the date range on mount
  initialFilters: { type: Object, default: () => ({}) }, // seed active filters on mount
})

const emit = defineEmits(['update:state'])

// ── Internal state ────────────────────────────────────────────────────
const search       = ref('')
const dateRange    = ref(props.initialDateRange)
const customStart  = ref('')
const customEnd    = ref('')
const activeFilters = ref({ ...props.initialFilters })   // { occupancy: 'owner', ... }
const activeSort   = ref(null)  // string value | null
const activeView   = ref('all') // 'all' | saved view id

// ── Saved views (localStorage) ────────────────────────────────────────
const savedViews = ref(
  JSON.parse(localStorage.getItem(`${props.storageKey}-views`) || '[]')
)

function persistViews() {
  localStorage.setItem(`${props.storageKey}-views`, JSON.stringify(savedViews.value))
}

// ── Modal visibility ──────────────────────────────────────────────────
const showDateModal   = ref(false)
const showFilterModal = ref(false)
const showSortModal   = ref(false)
const showViewModal   = ref(false)

// ── Date presets ──────────────────────────────────────────────────────
const DATE_PRESETS = [
  { value: 'today',      label: 'Today'      },
  { value: 'this_week',  label: 'This Week'  },
  { value: 'this_month', label: 'This Month' },
  { value: 'this_year',  label: 'This Year'  },
  { value: 'custom',     label: 'Custom'     },
  { value: 'all_time',   label: 'All Time'   },
]

// Format YYYY-MM-DD → "17 Apr 2026" for display; keeps YYYY-MM-DD for API
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function formatDisplayDate(dateStr) {
  if (!dateStr) return ''
  const [year, month, day] = dateStr.split('-')
  return `${parseInt(day)} ${MONTHS[parseInt(month) - 1]} ${year}`
}

const dateRangeLabel = computed(() => {
  if (dateRange.value === 'custom' && customStart.value && customEnd.value) {
    return `${formatDisplayDate(customStart.value)} → ${formatDisplayDate(customEnd.value)}`
  }
  return DATE_PRESETS.find(p => p.value === dateRange.value)?.label ?? 'All Time'
})

// ── Badge counts ──────────────────────────────────────────────────────
const filterCount = computed(() =>
  Object.values(activeFilters.value).filter(v => v !== null && v !== '').length
)
const sortCount = computed(() => (activeSort.value ? 1 : 0))
const hasDateFilter = computed(() => dateRange.value !== 'all_time')

// ── Active pills ──────────────────────────────────────────────────────
const activePills = computed(() => {
  const pills = []

  if (hasDateFilter.value) {
    pills.push({ type: 'date', key: '__date', label: `${props.dateRangeContext} → ${dateRangeLabel.value}` })
  }

  for (const field of props.filterFields) {
    const val = activeFilters.value[field.key]
    if (val) {
      const opt = field.options.find(o => o.value === val)
      pills.push({
        type: 'filter',
        key: field.key,
        label: `${field.label} → ${opt?.label ?? val}`,
      })
    }
  }

  if (activeSort.value) {
    const opt = props.sortOptions.find(o => o.value === activeSort.value)
    pills.push({ type: 'sort', key: '__sort', label: opt?.label ?? activeSort.value })
  }

  return pills
})

const hasPills = computed(() => activePills.value.length > 0)

// When a saved view is active, suppress all badges and pills — the view tab
// itself communicates that settings are applied; showing them twice is noisy.
const isViewActive = computed(() => activeView.value !== 'all')

// Custom tab: shown when filters/sort/date are active but don't match any saved view.
// It's ephemeral (client-side only, never persisted).
const isCustomView = computed(() => activeView.value === 'all' && hasPills.value)

// ── Emit state ────────────────────────────────────────────────────────
function emitState() {
  emit('update:state', {
    search:      search.value,
    dateRange:   dateRange.value,
    customStart: customStart.value,
    customEnd:   customEnd.value,
    filters:     { ...activeFilters.value },
    sort:        activeSort.value,
  })
}

watch(search, emitState)
onMounted(emitState)

// ── Date Range modal ──────────────────────────────────────────────────
function selectDatePreset(val) {
  dateRange.value = val
  if (val !== 'custom') {
    showDateModal.value = false
    emitState()
  }
}

function applyCustomDate() {
  if (!customStart.value || !customEnd.value) return
  showDateModal.value = false
  emitState()
}


// ── Filter modal ──────────────────────────────────────────────────────
// Temporary filter state while modal is open
const tempFilters = ref({})

function openFilterModal() {
  tempFilters.value = { ...activeFilters.value }
  showFilterModal.value = true
}

function applyFilters() {
  activeFilters.value = { ...tempFilters.value }
  showFilterModal.value = false
  emitState()
}

function clearFilters() {
  tempFilters.value = {}
  activeFilters.value = {}
  showFilterModal.value = false
  emitState()
}

// ── Sort modal ────────────────────────────────────────────────────────
const tempSort = ref(null)

function openSortModal() {
  tempSort.value = activeSort.value
  showSortModal.value = true
}

function selectSort(val) {
  tempSort.value = tempSort.value === val ? null : val
}

function applySort() {
  activeSort.value = tempSort.value
  showSortModal.value = false
  emitState()
}

function clearSort() {
  tempSort.value = null
  activeSort.value = null
  showSortModal.value = false
  emitState()
}

// ── Remove single pill ────────────────────────────────────────────────
function removePill(pill) {
  if (pill.type === 'date') {
    dateRange.value = 'all_time'
    customStart.value = ''
    customEnd.value = ''
  } else if (pill.type === 'filter') {
    delete activeFilters.value[pill.key]
    activeFilters.value = { ...activeFilters.value }
  } else if (pill.type === 'sort') {
    activeSort.value = null
  }
  emitState()
}

function clearAll() {
  dateRange.value = 'all_time'
  customStart.value = ''
  customEnd.value = ''
  activeFilters.value = {}
  activeSort.value = null
  emitState()
}

// ── Saved Views ───────────────────────────────────────────────────────
const newViewName       = ref('')
const newViewDateRange  = ref('all_time')
const newViewFilters    = ref({})
const newViewSort       = ref(null)

// Temp state within the Create View modal for its sub-sections
const createViewDateRange = ref('all_time')
const createViewFilters   = ref({})
const createViewSort      = ref(null)

function openCreateViewModal() {
  newViewName.value      = ''
  createViewDateRange.value = dateRange.value
  createViewFilters.value   = { ...activeFilters.value }
  createViewSort.value      = activeSort.value
  showViewModal.value = true
}

function saveView() {
  if (!newViewName.value.trim()) return
  const view = {
    id:        Date.now().toString(),
    name:      newViewName.value.trim(),
    dateRange: createViewDateRange.value,
    filters:   { ...createViewFilters.value },
    sort:      createViewSort.value,
  }
  savedViews.value.push(view)
  persistViews()
  showViewModal.value = false
  applyView(view)
}

function applyView(view) {
  activeView.value      = view.id
  dateRange.value       = view.dateRange
  activeFilters.value   = { ...view.filters }
  activeSort.value      = view.sort
  emitState()
}

function selectAllView() {
  activeView.value = 'all'
  clearAll()
}

function deleteView(id, event) {
  event.stopPropagation()
  savedViews.value = savedViews.value.filter(v => v.id !== id)
  persistViews()
  if (activeView.value === id) {
    selectAllView()
  }
}
</script>

<template>
  <div class="space-y-0">

    <!-- ── Row 1: Search + Action Buttons ───────────────────────────── -->
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Search -->
      <div class="flex-1 min-w-48">
        <AppInput
          v-model="search"
          leading-icon="search"
          size="sm"
          :placeholder="searchPlaceholder"
        />
      </div>

      <!-- Date Range button -->
      <AppButton variant="outline" size="sm" class="whitespace-nowrap" @click="showDateModal = true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
          <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
          <line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/>
          <line x1="3" x2="21" y1="10" y2="10"/>
        </svg>
        Date Range
        <span
          v-if="hasDateFilter && !isViewActive"
          class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold
                 bg-primary text-white leading-none"
        >{{ dateRangeLabel }}</span>
      </AppButton>

      <!-- Filter button -->
      <AppButton variant="outline" size="sm" class="whitespace-nowrap" @click="openFilterModal">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        Filter
        <span
          v-if="filterCount > 0 && !isViewActive"
          class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                 bg-primary text-white leading-none"
        >{{ filterCount }}</span>
      </AppButton>

      <!-- Sort button -->
      <AppButton variant="outline" size="sm" class="whitespace-nowrap" @click="openSortModal">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground">
          <path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="m21 8-4-4-4 4"/><path d="M17 4v16"/>
        </svg>
        Sort
        <span
          v-if="sortCount > 0 && !isViewActive"
          class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                 bg-primary text-white leading-none"
        >{{ sortCount }}</span>
      </AppButton>
    </div>

    <!-- ── Row 2: Saved Views ─────────────────────────────────────── -->
    <div class="flex items-center gap-2 pt-3 flex-wrap">
      <!-- All tab -->
      <button
        :class="[
          'inline-flex items-center h-7 px-3 text-xs font-medium rounded-full transition-colors',
          activeView === 'all' && !isCustomView
            ? 'bg-primary text-white shadow-sm'
            : 'bg-white border border-border text-foreground hover:bg-muted',
        ]"
        @click="selectAllView"
      >All</button>

      <!-- Saved view tabs -->
      <div
        v-for="view in savedViews"
        :key="view.id"
        :class="[
          'group inline-flex items-center gap-1.5 h-7 px-3 text-xs font-medium rounded-full',
          'transition-colors cursor-pointer select-none',
          activeView === view.id
            ? 'bg-primary text-white shadow-sm'
            : 'bg-white border border-border text-foreground hover:bg-muted',
        ]"
        @click="applyView(view)"
      >
        {{ view.name }}
        <button
          class="w-4 h-4 rounded-full flex items-center justify-center opacity-60 hover:opacity-100 transition-opacity"
          :class="activeView === view.id ? 'hover:bg-white/20' : 'hover:bg-muted-foreground/20'"
          @click="deleteView(view.id, $event)"
          :title="`Remove '${view.name}' view`"
        >
          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Custom tab — ephemeral, shown when active state matches no saved view -->
      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 scale-90"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-90"
      >
        <div
          v-if="isCustomView"
          class="inline-flex items-center h-7 px-3 text-xs font-medium rounded-full bg-primary text-white shadow-sm cursor-default select-none"
          title="Custom combination of filters — save as a view to keep it"
        >Custom</div>
      </Transition>

      <!-- + Add View -->
      <button
        class="inline-flex items-center gap-1.5 h-7 px-3 text-xs font-medium rounded-full
               border border-dashed border-border text-muted-foreground
               hover:border-accent hover:text-accent transition-colors"
        @click="openCreateViewModal"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/>
        </svg>
        Add View
      </button>
    </div>

    <!-- ── Row 3: Active pills ────────────────────────────────────── -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="hasPills && !isViewActive" class="flex items-center gap-2 flex-wrap pt-3">
        <span
          v-for="pill in activePills"
          :key="pill.key"
          :class="[
            'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium',
            pill.type === 'date'   && 'bg-success/10 text-success border border-success/25',
            pill.type === 'filter' && 'bg-primary/10 text-primary border border-primary/20',
            pill.type === 'sort'   && 'bg-amber/15 text-amber-dark border border-amber/30',
          ]"
        >
          {{ pill.label }}
          <button
            class="w-3.5 h-3.5 rounded-full flex items-center justify-center opacity-70 hover:opacity-100 transition-opacity"
            @click="removePill(pill)"
          >
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </span>

        <button
          class="text-xs text-muted-foreground hover:text-foreground transition-colors underline-offset-2 hover:underline"
          @click="clearAll"
        >Clear all</button>
      </div>
    </Transition>

  </div>

  <!-- ══════════════════════════════════════════════════════════════════ -->
  <!-- Date Range Modal                                                   -->
  <!-- ══════════════════════════════════════════════════════════════════ -->
  <AppModal :show="showDateModal" title="Select Date Range" @close="showDateModal = false">
    <p v-if="dateRangeContext" class="text-xs text-muted-foreground mb-4 flex items-center gap-1.5">
      <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
      </svg>
      Range applies to <span class="font-medium text-foreground">{{ dateRangeContext }}</span>
    </p>
    <div class="grid grid-cols-2 gap-3">
      <button
        v-for="preset in DATE_PRESETS"
        :key="preset.value"
        :class="[
          'h-12 px-4 rounded text-sm font-medium border transition-colors',
          dateRange === preset.value
            ? 'bg-accent text-white border-accent shadow-sm'
            : 'bg-white text-foreground border-border hover:bg-muted',
        ]"
        @click="selectDatePreset(preset.value)"
      >{{ preset.label }}</button>
    </div>

    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 max-h-0"
      enter-to-class="opacity-100 max-h-40"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 max-h-40"
      leave-to-class="opacity-0 max-h-0"
    >
      <div v-if="dateRange === 'custom'" class="overflow-hidden pt-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">

          <!-- From date -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-foreground">From</label>
            <AppDatePicker v-model="customStart" placeholder="Start date" />
          </div>

          <!-- To date -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-foreground">To</label>
            <AppDatePicker v-model="customEnd" placeholder="End date" :min="customStart" />
          </div>

        </div>
        <AppButton
          variant="primary"
          class="w-full"
          :disabled="!customStart || !customEnd"
          @click="applyCustomDate"
        >Apply</AppButton>
      </div>
    </Transition>
  </AppModal>

  <!-- ══════════════════════════════════════════════════════════════════ -->
  <!-- Filter Modal                                                        -->
  <!-- ══════════════════════════════════════════════════════════════════ -->
  <AppModal :show="showFilterModal" title="Add Filters" @close="showFilterModal = false">
    <div class="space-y-4">
      <AppSelect
        v-for="field in filterFields"
        :key="field.key"
        v-model="tempFilters[field.key]"
        :label="field.label"
        :options="[{ value: '', label: `Any ${field.label}` }, ...field.options]"
        :placeholder="`Any ${field.label}`"
      />
      <p v-if="filterFields.length === 0" class="text-sm text-muted-foreground text-center py-4">
        No filter options available.
      </p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="clearFilters">Clear Filters</AppButton>
      <AppButton variant="primary" @click="applyFilters">Apply Filters</AppButton>
    </template>
  </AppModal>

  <!-- ══════════════════════════════════════════════════════════════════ -->
  <!-- Sort Modal                                                          -->
  <!-- ══════════════════════════════════════════════════════════════════ -->
  <AppModal :show="showSortModal" title="Sort By" @close="showSortModal = false">
    <div class="-mx-6 -mt-5">
      <button
        v-for="opt in sortOptions"
        :key="opt.value"
        :class="[
          'w-full flex items-center justify-between px-6 py-3.5 text-sm transition-colors border-b border-border/40 last:border-b-0',
          tempSort === opt.value
            ? 'bg-primary/8 text-primary font-medium'
            : 'text-foreground hover:bg-muted',
        ]"
        @click="selectSort(opt.value)"
      >
        <span>{{ opt.label }}</span>
        <svg
          v-if="tempSort === opt.value"
          class="w-4 h-4 text-primary"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
      </button>
      <p v-if="sortOptions.length === 0" class="px-6 py-6 text-sm text-muted-foreground text-center">
        No sort options available.
      </p>
    </div>
    <template #footer>
      <AppButton variant="outline" @click="clearSort">Clear Sort</AppButton>
      <AppButton variant="primary" @click="applySort">Done</AppButton>
    </template>
  </AppModal>

  <!-- ══════════════════════════════════════════════════════════════════ -->
  <!-- Create View Modal                                                  -->
  <!-- ══════════════════════════════════════════════════════════════════ -->
  <AppModal :show="showViewModal" title="Create View" size="md" @close="showViewModal = false">
    <div class="space-y-4">
      <!-- View Name -->
      <AppInput
        v-model="newViewName"
        label="View Name"
        placeholder="e.g. Overdue Tenants, High Balance..."
      />

      <!-- Date Range accordion -->
      <div class="border border-border rounded overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 text-sm">
          <div class="flex items-center gap-2 text-foreground font-medium">
            <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/>
              <line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
            </svg>
            Date Range
          </div>
          <span class="text-xs text-muted-foreground">
            {{ DATE_PRESETS.find(p => p.value === createViewDateRange)?.label ?? 'All Time' }}
          </span>
        </div>
        <div class="px-4 pb-3 grid grid-cols-3 gap-2 border-t border-border pt-3">
          <button
            v-for="preset in DATE_PRESETS.filter(p => p.value !== 'custom')"
            :key="preset.value"
            :class="[
              'h-8 px-2 rounded text-xs font-medium border transition-colors',
              createViewDateRange === preset.value
                ? 'bg-accent text-white border-accent'
                : 'bg-white text-foreground border-border hover:bg-muted',
            ]"
            @click="createViewDateRange = preset.value"
          >{{ preset.label }}</button>
        </div>
      </div>

      <!-- Filters accordion -->
      <div class="border border-border rounded overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 text-sm">
          <div class="flex items-center gap-2 text-foreground font-medium">
            <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Filters
          </div>
          <span class="text-xs text-muted-foreground">
            {{ Object.values(createViewFilters).filter(v => v).length > 0
               ? `${Object.values(createViewFilters).filter(v => v).length} active`
               : 'None' }}
          </span>
        </div>
        <div v-if="filterFields.length > 0" class="px-4 pb-4 border-t border-border pt-3 space-y-3">
          <AppSelect
            v-for="field in filterFields"
            :key="field.key"
            v-model="createViewFilters[field.key]"
            :label="field.label"
            :options="[{ value: '', label: `Any ${field.label}` }, ...field.options]"
            :placeholder="`Any ${field.label}`"
          />
        </div>
      </div>

      <!-- Sorting accordion -->
      <div class="border border-border rounded overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 text-sm">
          <div class="flex items-center gap-2 text-foreground font-medium">
            <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="m21 8-4-4-4 4"/><path d="M17 4v16"/>
            </svg>
            Sorting
          </div>
          <span class="text-xs text-muted-foreground">
            {{ createViewSort
               ? (sortOptions.find(o => o.value === createViewSort)?.label ?? createViewSort)
               : 'Default' }}
          </span>
        </div>
        <div v-if="sortOptions.length > 0" class="border-t border-border">
          <button
            v-for="opt in sortOptions"
            :key="opt.value"
            :class="[
              'w-full flex items-center justify-between px-4 py-2.5 text-sm transition-colors',
              createViewSort === opt.value
                ? 'bg-primary/8 text-primary font-medium'
                : 'text-foreground hover:bg-muted',
            ]"
            @click="createViewSort = createViewSort === opt.value ? null : opt.value"
          >
            <span>{{ opt.label }}</span>
            <svg v-if="createViewSort === opt.value" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <template #footer>
      <AppButton variant="outline" @click="showViewModal = false">Cancel</AppButton>
      <AppButton variant="primary" :disabled="!newViewName.trim()" @click="saveView">Save View</AppButton>
    </template>
  </AppModal>
</template>
