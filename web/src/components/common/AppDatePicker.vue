<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue:  { type: String, default: '' }, // YYYY-MM-DD (date mode) | YYYY-MM (month mode)
  placeholder: { type: String, default: 'Select date' },
  min:         { type: String, default: '' },
  max:         { type: String, default: '' },
  mode:        { type: String, default: 'date' }, // 'date' | 'month'
  label:       { type: String, default: null },
  required:    { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const showCalendar  = ref(false)
const wrapperRef    = ref(null)
const triggerRef    = ref(null)
const calendarRef   = ref(null)   // ref on the teleported panel
const dropdownStyle = ref({})
const openUpward    = ref(false)

// 'days' | 'months' | 'years'
const viewMode  = ref('days')
const viewMonth = ref(new Date().getMonth())
const viewYear  = ref(new Date().getFullYear())

const today    = new Date()
const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

// Sync view to selected value when it changes externally
watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      const str = props.mode === 'month' ? val + '-01' : val
      const d = new Date(str + 'T00:00:00')
      viewMonth.value = d.getMonth()
      viewYear.value  = d.getFullYear()
    }
  },
  { immediate: true },
)

// ── Constants ─────────────────────────────────────────────────────────
const MONTHS_LONG  = ['January','February','March','April','May','June','July','August','September','October','November','December']
const MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
const DAY_HEADERS  = ['Su','Mo','Tu','We','Th','Fr','Sa']

// ── Display value ─────────────────────────────────────────────────────
const displayValue = computed(() => {
  if (!props.modelValue) return ''
  if (props.mode === 'month') {
    const [year, month] = props.modelValue.split('-')
    return `${MONTHS_LONG[parseInt(month) - 1]} ${year}`
  }
  const [year, month, day] = props.modelValue.split('-')
  return `${parseInt(day)} ${MONTHS_SHORT[parseInt(month) - 1]} ${year}`
})

// ── Header label per view mode ────────────────────────────────────────
const headerLabel = computed(() => {
  if (viewMode.value === 'days')   return `${MONTHS_LONG[viewMonth.value]} ${viewYear.value}`
  if (viewMode.value === 'months') return `${viewYear.value}`
  const start = Math.floor(viewYear.value / 10) * 10
  return `${start} – ${start + 9}`
})

// ── Navigation ────────────────────────────────────────────────────────
function prevPeriod() {
  if (viewMode.value === 'days') {
    if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value-- }
    else viewMonth.value--
  } else if (viewMode.value === 'months') {
    viewYear.value--
  } else {
    viewYear.value -= 10
  }
}

function nextPeriod() {
  if (viewMode.value === 'days') {
    if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value++ }
    else viewMonth.value++
  } else if (viewMode.value === 'months') {
    viewYear.value++
  } else {
    viewYear.value += 10
  }
}

function cycleViewMode() {
  if (viewMode.value === 'days')   viewMode.value = 'months'
  else if (viewMode.value === 'months') viewMode.value = 'years'
  // years → stays on years (no further level)
}

function selectMonth(m) {
  if (props.mode === 'month') {
    const mm = String(m + 1).padStart(2, '0')
    emit('update:modelValue', `${viewYear.value}-${mm}`)
    showCalendar.value = false
    return
  }
  viewMonth.value = m
  viewMode.value  = 'days'
}

function selectYear(y) {
  viewYear.value = y
  viewMode.value = 'months'
}

// ── Selected month index (for month mode highlight) ───────────────────
const selectedMonthIndex = computed(() => {
  if (props.mode !== 'month' || !props.modelValue) return -1
  const parts = props.modelValue.split('-')
  if (parts.length < 2) return -1
  return parseInt(parts[1]) - 1
})

// ── Years grid ────────────────────────────────────────────────────────
const yearsGrid = computed(() => {
  const start = Math.floor(viewYear.value / 10) * 10 - 1 // include one before for padding
  return Array.from({ length: 12 }, (_, i) => start + i)
})

// ── Days grid ────────────────────────────────────────────────────────
function dayStr(day) {
  const mm = String(viewMonth.value + 1).padStart(2, '0')
  const dd = String(day).padStart(2, '0')
  return `${viewYear.value}-${mm}-${dd}`
}

const calendarDays = computed(() => {
  const firstDay    = new Date(viewYear.value, viewMonth.value, 1).getDay()
  const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
  const days = []
  for (let i = 0; i < firstDay; i++) days.push(null)
  for (let d = 1; d <= daysInMonth; d++) days.push(d)
  while (days.length % 7 !== 0) days.push(null)
  return days
})

function isSelected(day) {
  return !!day && !!props.modelValue && props.modelValue === dayStr(day)
}

function isToday(day) {
  return !!day && dayStr(day) === todayStr
}

function isDisabled(day) {
  if (!day) return true
  const ds = dayStr(day)
  if (props.min && ds < props.min) return true
  if (props.max && ds > props.max) return true
  return false
}

function selectDay(day) {
  if (isDisabled(day)) return
  emit('update:modelValue', dayStr(day))
  showCalendar.value = false
}

// ── Dropdown positioning ──────────────────────────────────────────────
const CALENDAR_HEIGHT = 320

function computeDropdownPosition() {
  const rect = (triggerRef.value ?? wrapperRef.value)?.getBoundingClientRect()
  if (!rect) return
  const spaceBelow  = window.innerHeight - rect.bottom
  openUpward.value  = spaceBelow < CALENDAR_HEIGHT && rect.top > CALENDAR_HEIGHT
  dropdownStyle.value = {
    position: 'fixed',
    left:  rect.left + 'px',
    width: rect.width + 'px',
    zIndex: 9999,
    ...(openUpward.value
      ? { bottom: (window.innerHeight - rect.top + 4) + 'px', top: 'auto' }
      : { top: (rect.bottom + 4) + 'px', bottom: 'auto' }),
  }
}

function toggle() {
  if (!showCalendar.value) {
    viewMode.value = props.mode === 'month' ? 'months' : 'days'
    computeDropdownPosition()
  }
  showCalendar.value = !showCalendar.value
}

// Close when mousedown fires outside the trigger wrapper.
// Clicks inside the teleported calendar panel are stopped at the panel level
// via @mousedown.stop, so they never reach this handler.
function handleClickOutside(e) {
  if (!wrapperRef.value?.contains(e.target)) {
    showCalendar.value = false
  }
}

onMounted(()  => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<template>
  <div class="flex flex-col gap-1.5">
  <label v-if="label" class="text-sm font-medium text-fg">
    {{ label }}<span v-if="required" class="text-danger ml-0.5">*</span>
  </label>
  <div ref="wrapperRef" class="relative">

    <!-- Trigger button -->
    <button
      ref="triggerRef"
      type="button"
      class="h-11 w-full flex items-center justify-between px-3 text-sm border border-border rounded bg-white
             hover:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary
             transition-colors"
      @click="toggle"
    >
      <span :class="displayValue ? 'text-foreground' : 'text-muted-foreground'">
        {{ displayValue || placeholder }}
      </span>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="w-4 h-4 shrink-0 transition-colors"
        :class="showCalendar ? 'text-primary' : 'text-muted-foreground'"
        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
      >
        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
        <line x1="16" x2="16" y1="2" y2="6"/>
        <line x1="8"  x2="8"  y1="2" y2="6"/>
        <line x1="3"  x2="21" y1="10" y2="10"/>
      </svg>
    </button>

    <!-- Calendar — Teleported to body to escape any overflow:hidden ancestor -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 scale-95 -translate-y-1"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition-all duration-100 ease-in"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 -translate-y-1"
      >
        <div
          v-if="showCalendar"
          ref="calendarRef"
          :style="dropdownStyle"
          class="bg-white border border-border rounded-lg shadow-xl p-3 w-72"
          @mousedown.stop
        >

          <!-- Month / Year / Decade navigation header -->
          <div class="flex items-center justify-between mb-3">
            <button
              type="button"
              class="w-7 h-7 flex items-center justify-center rounded hover:bg-muted transition-colors"
              @click="prevPeriod"
            >
              <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>

            <!-- Clickable header label — cycles through days → months → years -->
            <button
              type="button"
              class="text-sm font-semibold text-foreground hover:text-primary transition-colors select-none flex items-center gap-1 px-2 py-0.5 rounded hover:bg-muted"
              @click="cycleViewMode"
            >
              {{ headerLabel }}
              <svg
                v-if="viewMode !== 'years'"
                class="w-3.5 h-3.5 text-muted-foreground"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <button
              type="button"
              class="w-7 h-7 flex items-center justify-center rounded hover:bg-muted transition-colors"
              @click="nextPeriod"
            >
              <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>

          <!-- ── Days view ────────────────────────────────────────────── -->
          <template v-if="viewMode === 'days'">
            <!-- Day-of-week headers -->
            <div class="grid grid-cols-7 mb-1">
              <div
                v-for="h in DAY_HEADERS"
                :key="h"
                class="h-7 flex items-center justify-center text-[11px] font-semibold text-muted-foreground select-none"
              >{{ h }}</div>
            </div>
            <!-- Days grid -->
            <div class="grid grid-cols-7 gap-y-0.5">
              <div
                v-for="(day, i) in calendarDays"
                :key="i"
                class="flex items-center justify-center"
              >
                <button
                  v-if="day"
                  type="button"
                  :disabled="isDisabled(day)"
                  :class="[
                    'w-8 h-8 rounded text-[13px] font-medium transition-all',
                    isSelected(day)
                      ? 'bg-accent text-white shadow-sm font-semibold scale-105'
                      : isToday(day)
                        ? 'ring-1 ring-accent text-accent font-semibold hover:bg-accent/10'
                        : isDisabled(day)
                          ? 'text-muted-foreground/30 cursor-not-allowed'
                          : 'text-foreground hover:bg-accent/10 hover:text-accent cursor-pointer',
                  ]"
                  @click="selectDay(day)"
                >{{ day }}</button>
              </div>
            </div>
          </template>

          <!-- ── Months view ──────────────────────────────────────────── -->
          <template v-else-if="viewMode === 'months'">
            <div class="grid grid-cols-3 gap-1.5">
              <button
                v-for="(m, i) in MONTHS_SHORT"
                :key="i"
                type="button"
                :class="[
                  'py-2 rounded text-sm font-medium transition-all',
                  (props.mode === 'month' ? i === selectedMonthIndex : i === viewMonth)
                    ? 'bg-accent text-white font-semibold'
                    : 'text-foreground hover:bg-accent/10 hover:text-accent',
                ]"
                @click="selectMonth(i)"
              >{{ m }}</button>
            </div>
          </template>

          <!-- ── Years view ───────────────────────────────────────────── -->
          <template v-else>
            <div class="grid grid-cols-3 gap-1.5">
              <button
                v-for="y in yearsGrid"
                :key="y"
                type="button"
                :class="[
                  'py-2 rounded text-sm font-medium transition-all',
                  y === viewYear
                    ? 'bg-accent text-white font-semibold'
                    : 'text-foreground hover:bg-accent/10 hover:text-accent',
                ]"
                @click="selectYear(y)"
              >{{ y }}</button>
            </div>
          </template>

          <!-- Clear selection -->
          <div v-if="modelValue" class="mt-3 pt-2.5 border-t border-border/60 flex justify-center">
            <button
              type="button"
              class="text-xs text-muted-foreground hover:text-foreground transition-colors underline-offset-2 hover:underline"
              @click="emit('update:modelValue', ''); showCalendar = false"
            >Clear date</button>
          </div>

        </div>
      </Transition>
    </Teleport>
  </div>
  </div>
</template>
