<!--
  GroupedMultiSelect — WeConnectU-style multi-select with a search box, optional
  grouped sections, and a checkmark next to each selected option. Used by the
  community Users screen for "Select Directors/Trustees" and the payment
  authorisers picker.

  Props:
    modelValue — array of selected values (use with v-model)
    groups     — [{ label?: string, options: [{ value, label }] }]
    placeholder
-->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue:  { type: Array,  default: () => [] },
  groups:      { type: Array,  default: () => [] },
  placeholder: { type: String, default: 'Nothing selected' },
})
const emit = defineEmits(['update:modelValue'])

const open   = ref(false)
const search = ref('')
const rootRef = ref(null)

const allOptions = computed(() => props.groups.flatMap(g => g.options))
const selectedLabels = computed(() =>
  props.modelValue
    .map(v => allOptions.value.find(o => o.value === v)?.label)
    .filter(Boolean)
)
const triggerText = computed(() =>
  selectedLabels.value.length ? selectedLabels.value.join(', ') : props.placeholder
)

const filteredGroups = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.groups
    .map(g => ({
      label: g.label,
      options: q ? g.options.filter(o => o.label.toLowerCase().includes(q)) : g.options,
    }))
    .filter(g => g.options.length)
})

function isSelected(v) { return props.modelValue.includes(v) }
function toggle(v) {
  const next = isSelected(v)
    ? props.modelValue.filter(x => x !== v)
    : [...props.modelValue, v]
  emit('update:modelValue', next)
}

function onClickOutside(e) {
  if (rootRef.value && !rootRef.value.contains(e.target)) open.value = false
}
onMounted(()  => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      class="w-full h-11 flex items-center justify-between gap-2 px-3 text-sm rounded-md border border-border bg-muted/40 text-left"
      @click="open = !open"
    >
      <span class="truncate" :class="selectedLabels.length ? 'text-foreground' : 'text-muted-foreground'">{{ triggerText }}</span>
      <svg class="w-4 h-4 shrink-0 text-muted-foreground transition-transform" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
    </button>

    <div v-if="open" class="absolute z-30 mt-1 w-full rounded-md border border-border bg-white shadow-lg">
      <div class="p-2 border-b border-border">
        <input v-model="search" type="text" class="w-full h-9 rounded-md border border-border bg-background px-3 text-sm" @click.stop />
      </div>
      <div class="max-h-72 overflow-y-auto py-1">
        <template v-for="(g, gi) in filteredGroups" :key="gi">
          <p v-if="g.label" class="px-4 pt-2 pb-1 text-xs text-muted-foreground">{{ g.label }}</p>
          <button
            v-for="o in g.options"
            :key="o.value"
            type="button"
            class="w-full flex items-center justify-between gap-2 px-4 py-2 text-left text-[15px] text-foreground hover:bg-muted/60"
            @click="toggle(o.value)"
          >
            <span class="truncate">{{ o.label }}</span>
            <svg v-if="isSelected(o.value)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-foreground shrink-0"><polyline points="20 6 9 17 4 12"/></svg>
          </button>
        </template>
        <p v-if="!filteredGroups.length" class="px-4 py-3 text-sm text-muted-foreground">No matches.</p>
      </div>
    </div>
  </div>
</template>
