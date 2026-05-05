<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/composables/useApi'
import { useBack } from '@/composables/useBack'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'

const route = useRoute()
const { goBack } = useBack('/compliance/templates')

// ── State ────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const template = ref(null)
const localItems = ref([])
const expandedCategories = ref({})
const isDirty = ref(false)

// ── Item modal ───────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem = ref(null)
const editingItemIndex = ref(null)
const itemForm = ref({
  name: '', category: 'Governance', description: '', priority: 'medium',
  default_month_due: null, is_recurring: true,
})

// ── Metadata modal ───────────────────────────────────────────────────
const showMetaModal = ref(false)
const metaSaving = ref(false)
const metaForm = ref({ name: '', description: '', country: null, is_default: false })

// ── Constants ────────────────────────────────────────────────────────
const CATEGORY_OPTIONS = [
  { value: 'Governance',  label: 'Governance' },
  { value: 'Financial',   label: 'Financial' },
  { value: 'Insurance',   label: 'Insurance' },
  { value: 'Legal',       label: 'Legal' },
  { value: 'Maintenance', label: 'Maintenance' },
]

const PRIORITY_OPTIONS = [
  { value: 'low',      label: 'Low' },
  { value: 'medium',   label: 'Medium' },
  { value: 'high',     label: 'High' },
  { value: 'critical', label: 'Critical' },
]

const MONTH_OPTIONS = [
  { value: null, label: 'No default' },
  ...Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: `Month ${i + 1} of FY` })),
]

const COUNTRY_OPTIONS = [
  { value: null, label: 'All Countries' },
  { value: 'ZA', label: 'South Africa' },
  { value: 'BW', label: 'Botswana' },
  { value: 'NA', label: 'Namibia' },
]

const CATEGORY_ORDER = ['Governance', 'Financial', 'Insurance', 'Legal', 'Maintenance']

const CATEGORY_ICONS = {
  Governance:  'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z',
  Financial:   'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
  Insurance:   'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z',
  Legal:       'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10ZM12 8v4M12 16h.01',
  Maintenance: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z',
}

const CATEGORY_COLORS = {
  Governance:  'text-indigo-600 bg-indigo-50 border-indigo-200',
  Financial:   'text-emerald-600 bg-emerald-50 border-emerald-200',
  Insurance:   'text-amber-600 bg-amber-50 border-amber-200',
  Legal:       'text-purple-600 bg-purple-50 border-purple-200',
  Maintenance: 'text-sky-600 bg-sky-50 border-sky-200',
}

const CATEGORY_BAR = {
  Governance:  'bg-indigo-500',
  Financial:   'bg-emerald-500',
  Insurance:   'bg-amber-500',
  Legal:       'bg-purple-500',
  Maintenance: 'bg-sky-500',
}

const PRIORITY_BADGE = {
  critical: 'bg-red-50 text-red-700',
  high:     'bg-amber-50 text-amber-700',
  medium:   'bg-sky-50 text-sky-700',
  low:      'bg-gray-100 text-gray-600',
}

const PRIORITY_DOT = {
  critical: 'bg-red-500',
  high:     'bg-amber-500',
  medium:   'bg-sky-500',
  low:      'bg-gray-400',
}

// ── Fetch ────────────────────────────────────────────────────────────
async function fetchTemplate() {
  loading.value = true
  try {
    const { data } = await api.get(`/compliance/templates/${route.params.templateId}`)
    template.value = data.data
    localItems.value = (data.data.items || []).map((item, i) => ({ ...item, _key: i }))

    const categories = [...new Set(localItems.value.map(i => i.category))]
    categories.forEach(c => {
      if (!(c in expandedCategories.value)) expandedCategories.value[c] = true
    })
    isDirty.value = false
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

onMounted(fetchTemplate)

// ── Computed ─────────────────────────────────────────────────────────
const groupedItems = computed(() => {
  const groups = {}
  for (const item of localItems.value) {
    if (!groups[item.category]) groups[item.category] = []
    groups[item.category].push(item)
  }
  const sorted = {}
  for (const cat of CATEGORY_ORDER) {
    if (groups[cat]) sorted[cat] = groups[cat]
  }
  for (const cat of Object.keys(groups)) {
    if (!sorted[cat]) sorted[cat] = groups[cat]
  }
  return sorted
})

const countryLabel = computed(() => {
  const map = { ZA: 'South Africa', BW: 'Botswana', NA: 'Namibia' }
  return template.value?.country ? map[template.value.country] || template.value.country : 'All Countries'
})

function categoryColor(cat) {
  return CATEGORY_COLORS[cat] || 'text-gray-600 bg-gray-50 border-gray-200'
}

function toggleCategory(cat) {
  expandedCategories.value[cat] = !expandedCategories.value[cat]
}

// ── Item management (local state) ────────────────────────────────────
function openAddItem(category = 'Governance') {
  editingItem.value = null
  editingItemIndex.value = null
  itemForm.value = { name: '', category, description: '', priority: 'medium', default_month_due: null, is_recurring: true }
  showItemModal.value = true
}

function openEditItem(item) {
  const idx = localItems.value.indexOf(item)
  editingItem.value = { ...item }
  editingItemIndex.value = idx
  itemForm.value = {
    name: item.name,
    category: item.category,
    description: item.description || '',
    priority: item.priority,
    default_month_due: item.default_month_due ?? null,
    is_recurring: item.is_recurring ?? true,
  }
  showItemModal.value = true
}

function saveItemLocal() {
  if (!itemForm.value.name.trim()) return

  const sortOrder = localItems.value.length
  if (editingItemIndex.value !== null) {
    localItems.value[editingItemIndex.value] = {
      ...localItems.value[editingItemIndex.value],
      ...itemForm.value,
    }
  } else {
    localItems.value.push({
      ...itemForm.value,
      sort_order: sortOrder,
      _key: Date.now(),
    })
    // Auto-expand the new item's category
    expandedCategories.value[itemForm.value.category] = true
  }

  isDirty.value = true
  showItemModal.value = false
}

function deleteItemLocal(item) {
  if (!confirm(`Remove "${item.name}" from this template?`)) return
  localItems.value = localItems.value.filter(i => i !== item)
  isDirty.value = true
}

// ── Save all to API ───────────────────────────────────────────────────
async function saveTemplate() {
  saving.value = true
  try {
    const items = localItems.value.map((item, index) => ({
      name: item.name,
      category: item.category,
      description: item.description || null,
      priority: item.priority,
      default_month_due: item.default_month_due ?? null,
      sort_order: index,
      is_recurring: item.is_recurring ?? true,
    }))

    const { data } = await api.put(`/compliance/templates/${template.value.id}`, { items })
    template.value = data.data
    localItems.value = (data.data.items || []).map((item, i) => ({ ...item, _key: i }))
    isDirty.value = false
  } catch {
    // silent
  } finally {
    saving.value = false
  }
}

function discardChanges() {
  if (!confirm('Discard all unsaved changes?')) return
  fetchTemplate()
}

// ── Template metadata edit ───────────────────────────────────────────
function openMetaEdit() {
  metaForm.value = {
    name: template.value.name,
    description: template.value.description || '',
    country: template.value.country ?? null,
    is_default: template.value.is_default,
  }
  showMetaModal.value = true
}

async function saveMetadata() {
  metaSaving.value = true
  try {
    const { data } = await api.put(`/compliance/templates/${template.value.id}`, metaForm.value)
    template.value = { ...template.value, ...data.data }
    showMetaModal.value = false
  } catch {
    // silent
  } finally {
    metaSaving.value = false
  }
}
</script>

<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-muted animate-pulse" />
        <div class="flex-1 space-y-2">
          <div class="h-7 w-56 rounded bg-muted animate-pulse" />
          <div class="h-4 w-72 rounded bg-muted animate-pulse" />
        </div>
      </div>
      <div v-for="i in 3" :key="i" class="h-[200px] bg-white rounded-xl border border-border animate-pulse mt-4" />
    </div>

    <template v-else-if="template">
      <!-- ── Header ─────────────────────────────────────────────────── -->
      <div class="flex items-start gap-3 mb-6">
        <AppButton variant="ghost" square size="md" @click="goBack()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="text-muted-foreground">
            <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
          </svg>
        </AppButton>

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-widest">Compliance</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3 text-border">
              <path d="m9 18 6-6-6-6"/>
            </svg>
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-widest">Templates</span>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-body font-bold text-2xl text-foreground leading-tight">{{ template.name }}</h1>
            <AppBadge v-if="template.is_default" variant="success" size="sm">Default</AppBadge>
            <AppBadge v-if="template.is_system" variant="info" size="sm">System</AppBadge>
          </div>
          <p class="text-sm text-muted-foreground mt-0.5">
            {{ countryLabel }}
            <span class="mx-1.5 opacity-40">&middot;</span>
            {{ localItems.length }} items
            <template v-if="template.description">
              <span class="mx-1.5 opacity-40">&middot;</span>
              {{ template.description }}
            </template>
          </p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <!-- Unsaved changes indicator -->
          <span v-if="isDirty" class="text-xs text-amber-600 font-medium flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
            Unsaved changes
          </span>

          <AppButton v-if="isDirty" variant="ghost" size="sm" @click="discardChanges">Discard</AppButton>

          <AppButton variant="ghost" size="sm" @click="openMetaEdit">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
            </svg>
            Edit Info
          </AppButton>

          <AppButton variant="outline" size="sm" @click="openAddItem()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
              <path d="M12 5v14"/><path d="M5 12h14"/>
            </svg>
            Add Item
          </AppButton>

          <AppButton variant="primary" size="sm" @click="saveTemplate" :loading="saving" :disabled="!isDirty">
            <svg v-if="!saving" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Save Changes
          </AppButton>
        </div>
      </div>

      <!-- ── Empty state ────────────────────────────────────────────── -->
      <div v-if="localItems.length === 0" class="bg-white rounded-xl border border-border border-dashed p-12 text-center">
        <div class="w-10 h-10 rounded-xl bg-muted flex items-center justify-center mx-auto mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5 text-muted-foreground">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <p class="text-sm font-semibold text-foreground mb-1">No items in this template</p>
        <p class="text-xs text-muted-foreground mb-4">Add compliance items that will be pre-populated when this template is used.</p>
        <AppButton variant="primary" size="sm" @click="openAddItem()">Add First Item</AppButton>
      </div>

      <!-- ── Category sections ──────────────────────────────────────── -->
      <div v-else class="space-y-3">
        <div
          v-for="(catItems, category) in groupedItems"
          :key="category"
          class="bg-white rounded-xl border border-border overflow-hidden"
        >
          <!-- Category header -->
          <button
            @click="toggleCategory(category)"
            class="w-full flex items-center gap-4 px-5 py-4 hover:bg-gray-50/60 transition-colors"
          >
            <div :class="['w-0.5 h-8 rounded-full flex-shrink-0', CATEGORY_BAR[category] || 'bg-gray-400']" />
            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center border flex-shrink-0', categoryColor(category)]">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path :d="CATEGORY_ICONS[category] || CATEGORY_ICONS.Governance" />
              </svg>
            </div>
            <div class="flex-1 text-left min-w-0">
              <p class="text-sm font-semibold text-foreground">{{ category }}</p>
              <p class="text-xs text-muted-foreground">{{ catItems.length }} item{{ catItems.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
              <button
                @click.stop="openAddItem(category)"
                class="hidden sm:flex items-center gap-1 text-xs text-muted-foreground hover:text-primary transition-colors px-2 py-1 rounded hover:bg-gray-100"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                  <path d="M12 5v14"/><path d="M5 12h14"/>
                </svg>
                Add
              </button>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                :class="['w-4 h-4 text-muted-foreground transition-transform duration-200', expandedCategories[category] ? 'rotate-180' : '']"
              >
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </button>

          <!-- Items list -->
          <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-[5000px]"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 max-h-[5000px]"
            leave-to-class="opacity-0 max-h-0"
          >
            <div v-if="expandedCategories[category]" class="overflow-hidden">
              <!-- Column headers -->
              <div class="flex items-center gap-3 px-5 py-2 bg-gray-50/80 border-t border-b border-border">
                <div class="flex-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Item</div>
                <div class="w-[96px] hidden sm:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground text-center">Priority</div>
                <div class="w-[120px] hidden sm:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Due</div>
                <div class="w-[80px] hidden md:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Recurring</div>
                <div class="w-[72px] flex-shrink-0" />
              </div>

              <div
                v-for="item in catItems"
                :key="item._key ?? item.id"
                class="border-b border-border last:border-b-0 group"
              >
                <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50/40 transition-colors">
                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-foreground leading-snug">{{ item.name }}</p>
                    <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ item.description }}</p>
                  </div>

                  <!-- Priority (desktop) -->
                  <div class="hidden sm:flex w-[96px] justify-center items-start pt-0.5 flex-shrink-0">
                    <span :class="['inline-flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded', PRIORITY_BADGE[item.priority] || 'bg-gray-100 text-gray-600']">
                      <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', PRIORITY_DOT[item.priority] || 'bg-gray-400']" />
                      {{ item.priority?.charAt(0).toUpperCase() + item.priority?.slice(1) }}
                    </span>
                  </div>

                  <!-- Default month due (desktop) -->
                  <div class="hidden sm:block w-[120px] flex-shrink-0 pt-0.5">
                    <p class="text-xs text-muted-foreground">
                      {{ item.default_month_due ? `Month ${item.default_month_due} of FY` : '—' }}
                    </p>
                  </div>

                  <!-- Is recurring (desktop) -->
                  <div class="hidden md:block w-[80px] flex-shrink-0 pt-0.5">
                    <span v-if="item.is_recurring" class="text-xs text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Yes</span>
                    <span v-else class="text-xs text-muted-foreground">No</span>
                  </div>

                  <!-- Actions (hover-reveal) -->
                  <div class="flex items-center gap-0.5 w-[72px] justify-end flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      @click="openEditItem(item)"
                      title="Edit item"
                      class="p-1.5 rounded hover:bg-blue-50 text-muted-foreground hover:text-blue-600 transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                      </svg>
                    </button>
                    <button
                      @click="deleteItemLocal(item)"
                      title="Remove item"
                      class="p-1.5 rounded hover:bg-red-50 text-muted-foreground hover:text-red-600 transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Add item to this category -->
              <div class="px-5 py-2.5 bg-gray-50/50 border-t border-dashed border-border">
                <button
                  @click="openAddItem(category)"
                  class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary transition-colors"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                    <path d="M12 5v14"/><path d="M5 12h14"/>
                  </svg>
                  Add {{ category }} item
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <!-- ── Bottom action row ─────────────────────────────────────── -->
      <div class="mt-4 flex items-center gap-3">
        <AppButton variant="outline" size="sm" @click="openAddItem()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          Add Item
        </AppButton>
        <AppButton v-if="isDirty" variant="primary" size="sm" @click="saveTemplate" :loading="saving">
          Save Changes
        </AppButton>
      </div>
    </template>

    <!-- ── Add / Edit Item Modal ──────────────────────────────────────── -->
    <AppModal
      :show="showItemModal"
      @close="showItemModal = false"
      :title="editingItem ? 'Edit Template Item' : 'Add Template Item'"
      size="md"
    >
      <div class="space-y-4">
        <AppInput v-model="itemForm.name" label="Item Name" placeholder="e.g. Annual General Meeting held" required />
        <AppSelect v-model="itemForm.category" label="Category" :options="CATEGORY_OPTIONS" required />
        <AppInput v-model="itemForm.description" label="Description" type="textarea" :rows="3" placeholder="Describe what this item requires..." />
        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="itemForm.priority" label="Priority" :options="PRIORITY_OPTIONS" />
          <AppSelect v-model="itemForm.default_month_due" label="Default Due" :options="MONTH_OPTIONS" />
        </div>
        <div class="flex items-center gap-3 pt-1">
          <button
            type="button"
            @click="itemForm.is_recurring = !itemForm.is_recurring"
            :class="[
              'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
              itemForm.is_recurring ? 'bg-primary' : 'bg-gray-200',
            ]"
          >
            <span :class="['pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200', itemForm.is_recurring ? 'translate-x-4' : 'translate-x-0']" />
          </button>
          <div>
            <p class="text-sm font-medium text-foreground">Recurring annually</p>
            <p class="text-xs text-muted-foreground">This item will appear in checklists for every financial year</p>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showItemModal = false">Cancel</AppButton>
          <AppButton variant="primary" @click="saveItemLocal" :disabled="!itemForm.name.trim()">
            {{ editingItem ? 'Update Item' : 'Add Item' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- ── Edit Template Info Modal ────────────────────────────────────── -->
    <AppModal :show="showMetaModal" @close="showMetaModal = false" title="Edit Template Info" size="sm">
      <div class="space-y-4">
        <AppInput v-model="metaForm.name" label="Template Name" placeholder="e.g. Sectional Title — South Africa" required />
        <AppInput v-model="metaForm.description" label="Description" type="textarea" :rows="3" placeholder="Describe what this template covers..." />
        <AppSelect v-model="metaForm.country" label="Country" :options="COUNTRY_OPTIONS" />
        <div class="flex items-center gap-3 pt-1">
          <button
            type="button"
            @click="metaForm.is_default = !metaForm.is_default"
            :class="[
              'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
              metaForm.is_default ? 'bg-primary' : 'bg-gray-200',
            ]"
          >
            <span :class="['pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200', metaForm.is_default ? 'translate-x-4' : 'translate-x-0']" />
          </button>
          <div>
            <p class="text-sm font-medium text-foreground">Set as default template</p>
            <p class="text-xs text-muted-foreground">Pre-selected when creating new checklists for this country</p>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showMetaModal = false">Cancel</AppButton>
          <AppButton variant="primary" @click="saveMetadata" :loading="metaSaving">Save Changes</AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
