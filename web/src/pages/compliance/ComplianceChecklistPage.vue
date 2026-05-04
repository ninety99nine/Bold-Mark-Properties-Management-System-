<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useCountryStore } from '@/stores/country'
import { useBack } from '@/composables/useBack'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'

const route = useRoute()
const router = useRouter()
const countryStore = useCountryStore()
const { goBack } = useBack('/compliance')

// ── State ────────────────────────────────────────────────────────────
const loading = ref(true)
const checklist = ref(null)
const items = ref([])
const statusSummary = ref({})
const expandedCategories = ref({})

// ── Item modal ───────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem = ref(null)
const itemForm = ref({ name: '', category: '', description: '', priority: 'medium', due_date: null, assigned_to_id: null })
const itemSaving = ref(false)

// ── Attachments ─────────────────────────────────────────────────────
const attachmentFiles = ref([])
const attachmentUploading = ref(false)
const attachmentInput = ref(null)

// ── Completion modal ─────────────────────────────────────────────────
const showCompleteModal = ref(false)
const completingItem = ref(null)
const completionNotes = ref('')
const completeSaving = ref(false)

// ── Users list for assignment ────────────────────────────────────────
const users = ref([])

// ── Constants ────────────────────────────────────────────────────────
const PRIORITY_CONFIG = {
  critical: { label: 'Critical', variant: 'danger',  order: 0 },
  high:     { label: 'High',     variant: 'warning', order: 1 },
  medium:   { label: 'Medium',   variant: 'info',    order: 2 },
  low:      { label: 'Low',      variant: 'default', order: 3 },
}

const STATUS_CONFIG = {
  pending:     { label: 'Pending',     variant: 'default', icon: 'circle' },
  in_progress: { label: 'In Progress', variant: 'info',    icon: 'clock' },
  completed:   { label: 'Completed',   variant: 'success', icon: 'check' },
  overdue:     { label: 'Overdue',     variant: 'danger',  icon: 'alert' },
  waived:      { label: 'Waived',      variant: 'default', icon: 'skip' },
}

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

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'critical', label: 'Critical' },
]

const CATEGORY_OPTIONS = [
  { value: 'Governance', label: 'Governance' },
  { value: 'Financial', label: 'Financial' },
  { value: 'Insurance', label: 'Insurance' },
  { value: 'Legal', label: 'Legal' },
  { value: 'Maintenance', label: 'Maintenance' },
]

// ── Fetch ────────────────────────────────────────────────────────────
async function fetchChecklist() {
  loading.value = true
  try {
    const { data } = await api.get(`/compliance/checklists/${route.params.checklistId}`)
    checklist.value = data.data
    items.value = data.data.items || []
    statusSummary.value = data.data.status_summary || {}

    // Auto-expand all categories
    const categories = [...new Set(items.value.map(i => i.category))]
    categories.forEach(c => {
      if (!(c in expandedCategories.value)) {
        expandedCategories.value[c] = true
      }
    })
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

async function fetchUsers() {
  try {
    const { data } = await api.get('/users', { params: { _per_page: 100 } })
    users.value = (data.data || []).map(u => ({ value: u.id, label: u.name }))
  } catch {
    // silent
  }
}

onMounted(() => {
  fetchChecklist()
  fetchUsers()
})

// ── Computed ─────────────────────────────────────────────────────────
const groupedItems = computed(() => {
  const groups = {}
  for (const item of items.value) {
    if (!groups[item.category]) groups[item.category] = []
    groups[item.category].push(item)
  }
  const order = ['Governance', 'Financial', 'Insurance', 'Legal', 'Maintenance']
  const sorted = {}
  for (const cat of order) {
    if (groups[cat]) sorted[cat] = groups[cat]
  }
  for (const cat of Object.keys(groups)) {
    if (!sorted[cat]) sorted[cat] = groups[cat]
  }
  return sorted
})

const progress = computed(() => {
  if (!checklist.value) return 0
  return checklist.value.progress_percentage || 0
})

const totalItems = computed(() => items.value.length)
const completedCount = computed(() => items.value.filter(i => i.status === 'completed').length)
const overdueCount = computed(() => items.value.filter(i => i.status === 'overdue').length)
const pendingCount = computed(() => items.value.filter(i => i.status === 'pending' || i.status === 'in_progress').length)

function progressBarColor(pct) {
  if (pct === 100) return 'bg-emerald-500'
  if (pct >= 50)   return 'bg-amber-500'
  if (pct > 0)     return 'bg-red-500'
  return 'bg-gray-300'
}

function categoryColor(cat) {
  return CATEGORY_COLORS[cat] || 'text-gray-600 bg-gray-50 border-gray-200'
}

function categoryProgress(cat) {
  const s = statusSummary.value[cat]
  if (!s) return 0
  return s.total > 0 ? Math.round((s.completed / s.total) * 100) : 0
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + ' ' +
         d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function fileIcon(mimeType) {
  if (!mimeType) return 'document'
  if (mimeType.startsWith('image/')) return 'image'
  if (mimeType.includes('pdf')) return 'pdf'
  if (mimeType.includes('spreadsheet') || mimeType.includes('excel') || mimeType.includes('csv')) return 'spreadsheet'
  return 'document'
}

// ── Status updates ───────────────────────────────────────────────────
async function markStatus(item, status) {
  if (status === 'completed') {
    completingItem.value = item
    completionNotes.value = ''
    showCompleteModal.value = true
    return
  }

  try {
    await api.put(`/compliance/checklists/${checklist.value.id}/items/${item.id}`, { status })
    fetchChecklist()
  } catch {
    // silent
  }
}

async function confirmComplete() {
  completeSaving.value = true
  try {
    await api.put(`/compliance/checklists/${checklist.value.id}/items/${completingItem.value.id}`, {
      status: 'completed',
      completion_notes: completionNotes.value || null,
    })
    showCompleteModal.value = false
    fetchChecklist()
  } catch {
    // silent
  } finally {
    completeSaving.value = false
  }
}

// ── Item CRUD ────────────────────────────────────────────────────────
function openAddItem(category = '') {
  editingItem.value = null
  itemForm.value = { name: '', category: category || 'Governance', description: '', priority: 'medium', due_date: null, assigned_to_id: null }
  showItemModal.value = true
}

function openEditItem(item) {
  editingItem.value = item
  itemForm.value = {
    name: item.name,
    category: item.category,
    description: item.description || '',
    priority: item.priority,
    due_date: item.due_date,
    assigned_to_id: item.assigned_to_id,
  }
  showItemModal.value = true
}

async function saveItem() {
  itemSaving.value = true
  try {
    if (editingItem.value) {
      await api.put(`/compliance/checklists/${checklist.value.id}/items/${editingItem.value.id}`, itemForm.value)
    } else {
      await api.post(`/compliance/checklists/${checklist.value.id}/items`, itemForm.value)
    }
    showItemModal.value = false
    fetchChecklist()
  } catch {
    // silent
  } finally {
    itemSaving.value = false
  }
}

async function deleteItem(item) {
  if (!confirm(`Delete "${item.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/compliance/checklists/${checklist.value.id}/items/${item.id}`)
    fetchChecklist()
  } catch {
    // silent
  }
}

// ── Checklist delete ────────────────────────────────────────────────
async function deleteChecklist() {
  if (!confirm(`Delete this entire compliance checklist (FY ${checklist.value.financial_year_label})? All items will be permanently removed.`)) return
  try {
    await api.delete(`/compliance/checklists/${checklist.value.id}`)
    goBack()
  } catch {
    // silent
  }
}

// ── Attachments ─────────────────────────────────────────────────────
function onAttachmentFilesSelected(e, item) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  uploadAttachments(item, files)
  e.target.value = ''
}

async function uploadAttachments(item, files) {
  attachmentUploading.value = true
  try {
    const form = new FormData()
    files.forEach(f => form.append('attachments[]', f))
    await api.post(`/compliance/checklists/${checklist.value.id}/items/${item.id}/attachments`, form)
    fetchChecklist()
  } catch {
    // silent
  } finally {
    attachmentUploading.value = false
  }
}

async function downloadAttachment(item, attachment) {
  try {
    const response = await api.get(
      `/compliance/checklists/${checklist.value.id}/items/${item.id}/attachments/${attachment.id}/download`,
      { responseType: 'blob' }
    )
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', attachment.file_name || 'attachment')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch {
    // silent
  }
}

async function deleteAttachment(item, attachment) {
  if (!confirm(`Remove "${attachment.file_name}"?`)) return
  try {
    await api.delete(`/compliance/checklists/${checklist.value.id}/items/${item.id}/attachments/${attachment.id}`)
    fetchChecklist()
  } catch {
    // silent
  }
}

function toggleCategory(cat) {
  expandedCategories.value[cat] = !expandedCategories.value[cat]
}
</script>

<template>
  <div class="min-h-screen">
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-muted animate-pulse" />
        <div class="flex-1 space-y-2">
          <div class="h-7 w-48 rounded bg-muted animate-pulse" />
          <div class="h-4 w-80 rounded bg-muted animate-pulse" />
        </div>
      </div>
      <div class="grid grid-cols-4 gap-4 mt-6">
        <div v-for="i in 4" :key="i" class="h-[88px] bg-white rounded-xl border border-border animate-pulse" />
      </div>
      <div v-for="i in 3" :key="i" class="h-[200px] bg-white rounded-xl border border-border animate-pulse mt-4" />
    </div>

    <template v-else-if="checklist">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <AppButton variant="ghost" square size="md" @click="goBack()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="text-muted-foreground">
            <path d="m12 19-7-7 7-7" /><path d="M19 12H5" />
          </svg>
        </AppButton>

        <div class="flex-1">
          <h1 class="font-body font-bold text-2xl text-foreground">{{ checklist.estate?.name }}</h1>
          <p class="text-sm text-muted-foreground">
            Compliance checklist &mdash; FY {{ checklist.financial_year_label }}
            <span class="mx-1.5">&bull;</span>
            {{ formatDate(checklist.financial_year_start) }} to {{ formatDate(checklist.financial_year_end) }}
          </p>
        </div>

        <AppButton variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/5" @click="deleteChecklist">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
          </svg>
          Delete Checklist
        </AppButton>
      </div>

      <!-- Progress overview -->
      <div class="bg-white rounded-xl border border-border p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h2 class="text-sm font-semibold text-foreground">Overall Progress</h2>
            <p class="text-xs text-muted-foreground mt-0.5">{{ completedCount }} of {{ totalItems }} items completed</p>
          </div>
          <span class="text-2xl font-bold" :class="progress === 100 ? 'text-emerald-600' : progress >= 50 ? 'text-amber-600' : 'text-red-600'">
            {{ progress }}%
          </span>
        </div>
        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
          <div
            :class="['h-full rounded-full transition-all duration-700', progressBarColor(progress)]"
            :style="{ width: progress + '%' }"
          />
        </div>

        <!-- Mini stats row -->
        <div class="flex items-center gap-6 mt-3 text-xs">
          <span class="flex items-center gap-1.5 text-emerald-600 font-medium">
            <span class="w-2 h-2 rounded-full bg-emerald-500" />
            {{ completedCount }} completed
          </span>
          <span class="flex items-center gap-1.5 text-muted-foreground font-medium">
            <span class="w-2 h-2 rounded-full bg-gray-300" />
            {{ pendingCount }} pending
          </span>
          <span v-if="overdueCount > 0" class="flex items-center gap-1.5 text-red-600 font-medium">
            <span class="w-2 h-2 rounded-full bg-red-500" />
            {{ overdueCount }} overdue
          </span>
        </div>
      </div>

      <!-- Category sections -->
      <div class="space-y-4">
        <div
          v-for="(catItems, category) in groupedItems"
          :key="category"
          class="bg-white rounded-xl border border-border overflow-hidden"
        >
          <!-- Category header -->
          <button
            @click="toggleCategory(category)"
            class="w-full flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors"
          >
            <div class="flex items-center gap-3">
              <div :class="['w-8 h-8 rounded-lg flex items-center justify-center border', categoryColor(category)]">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path :d="CATEGORY_ICONS[category] || CATEGORY_ICONS.Governance" />
                </svg>
              </div>
              <div class="text-left">
                <h3 class="text-sm font-semibold text-foreground">{{ category }}</h3>
                <p class="text-xs text-muted-foreground">
                  {{ statusSummary[category]?.completed || 0 }} of {{ statusSummary[category]?.total || catItems.length }} completed
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden hidden sm:block">
                <div
                  :class="['h-full rounded-full', progressBarColor(categoryProgress(category))]"
                  :style="{ width: categoryProgress(category) + '%' }"
                />
              </div>
              <span class="text-xs font-medium text-muted-foreground">{{ categoryProgress(category) }}%</span>
              <svg
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
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
            <div v-if="expandedCategories[category]" class="overflow-hidden border-t border-border">
              <div
                v-for="item in catItems"
                :key="item.id"
                class="border-b border-border last:border-b-0"
              >
                <!-- Item row -->
                <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50/50 transition-colors group">
                  <!-- Status checkbox -->
                  <button
                    @click="item.status === 'completed' ? markStatus(item, 'pending') : markStatus(item, 'completed')"
                    :class="[
                      'mt-0.5 w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors',
                      item.status === 'completed'
                        ? 'bg-emerald-500 border-emerald-500 text-white'
                        : item.status === 'overdue'
                          ? 'border-red-400 hover:border-red-500'
                          : 'border-gray-300 hover:border-primary',
                    ]"
                  >
                    <svg v-if="item.status === 'completed'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <polyline points="20 6 9 17 4 12"/>
                    </svg>
                  </button>

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                      <div class="min-w-0">
                        <p :class="['text-sm font-medium', item.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground']">
                          {{ item.name }}
                        </p>
                        <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ item.description }}</p>
                      </div>

                      <!-- Actions -->
                      <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          v-if="item.status !== 'in_progress' && item.status !== 'completed'"
                          @click="markStatus(item, 'in_progress')"
                          title="Mark as in progress"
                          class="p-1.5 rounded-md hover:bg-blue-50 text-muted-foreground hover:text-blue-600 transition-colors"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                          </svg>
                        </button>
                        <button
                          v-if="item.status !== 'waived'"
                          @click="markStatus(item, 'waived')"
                          title="Waive this item"
                          class="p-1.5 rounded-md hover:bg-gray-100 text-muted-foreground hover:text-foreground transition-colors"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                          </svg>
                        </button>
                        <button
                          @click="openEditItem(item)"
                          title="Edit item"
                          class="p-1.5 rounded-md hover:bg-blue-50 text-muted-foreground hover:text-blue-600 transition-colors"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                          </svg>
                        </button>
                        <button
                          @click="deleteItem(item)"
                          title="Delete item"
                          class="p-1.5 rounded-md hover:bg-red-50 text-muted-foreground hover:text-red-600 transition-colors"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                          </svg>
                        </button>
                      </div>
                    </div>

                    <!-- Meta row: badges + details -->
                    <div class="flex items-center flex-wrap gap-2 mt-2">
                      <AppBadge
                        v-if="item.status !== 'pending'"
                        :variant="STATUS_CONFIG[item.status]?.variant || 'default'"
                        size="sm"
                      >
                        {{ STATUS_CONFIG[item.status]?.label || item.status }}
                      </AppBadge>
                      <AppBadge
                        :variant="PRIORITY_CONFIG[item.priority]?.variant || 'default'"
                        size="sm"
                      >
                        {{ PRIORITY_CONFIG[item.priority]?.label || item.priority }}
                      </AppBadge>
                      <span v-if="item.due_date" class="text-xs text-muted-foreground flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>
                        </svg>
                        Due {{ formatDate(item.due_date) }}
                      </span>
                      <span v-if="item.assigned_to" class="text-xs text-muted-foreground flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        {{ item.assigned_to.name }}
                      </span>
                      <span v-if="item.completed_at" class="text-xs text-emerald-600 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        {{ formatDateTime(item.completed_at) }}{{ item.completed_by ? ' by ' + item.completed_by.name : '' }}
                      </span>
                    </div>

                    <!-- Attachments section -->
                    <div v-if="item.attachments?.length" class="mt-3 pt-3 border-t border-border">
                      <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">Attachments</p>
                      <div class="space-y-1.5">
                        <div
                          v-for="att in item.attachments"
                          :key="att.id"
                          class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-muted/50 border border-border group/att"
                        >
                          <!-- File icon -->
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-foreground flex-shrink-0">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                          </svg>
                          <!-- File info -->
                          <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-foreground truncate">{{ att.file_name }}</p>
                            <p class="text-[10px] text-muted-foreground">
                              {{ formatFileSize(att.file_size) }}
                              <template v-if="att.uploaded_by"> &middot; {{ att.uploaded_by.name }}</template>
                            </p>
                          </div>
                          <!-- Actions -->
                          <div class="flex items-center gap-0.5 flex-shrink-0">
                            <button
                              @click="downloadAttachment(item, att)"
                              title="Download"
                              class="p-1 rounded hover:bg-background text-muted-foreground hover:text-foreground transition-colors"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                              </svg>
                            </button>
                            <button
                              @click="deleteAttachment(item, att)"
                              title="Remove"
                              class="p-1 rounded hover:bg-red-50 text-muted-foreground hover:text-red-600 transition-colors"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                              </svg>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Upload attachment area (always visible at the bottom of item) -->
                    <div class="mt-2">
                      <input
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx"
                        class="hidden"
                        :ref="el => { if (el) attachmentInput = el }"
                        @change="e => onAttachmentFilesSelected(e, item)"
                      />
                      <button
                        @click="(() => { const input = $el.querySelector(`[data-upload-item='${item.id}']`); if (input) input.click(); })()"
                        class="hidden"
                      />
                      <label
                        class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary cursor-pointer transition-colors"
                        @click.prevent="(() => {
                          const input = document.createElement('input');
                          input.type = 'file';
                          input.multiple = true;
                          input.accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx';
                          input.onchange = (e) => onAttachmentFilesSelected(e, item);
                          input.click();
                        })()"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                          <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        Attach files
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Add item button for this category -->
              <div class="px-5 py-2.5 border-t border-dashed border-border">
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

      <!-- Add custom item button -->
      <div class="mt-4">
        <AppButton variant="outline" size="sm" @click="openAddItem()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          Add Compliance Item
        </AppButton>
      </div>
    </template>

    <!-- ── Add/Edit Item Modal ──────────────────────────────────────── -->
    <AppModal :show="showItemModal" @close="showItemModal = false" :title="editingItem ? 'Edit Compliance Item' : 'Add Compliance Item'" size="md">
      <div class="space-y-4">
        <AppInput v-model="itemForm.name" label="Item Name" placeholder="e.g. AGM held" required />
        <AppSelect v-model="itemForm.category" label="Category" :options="CATEGORY_OPTIONS" required />
        <AppInput v-model="itemForm.description" label="Description" type="textarea" :rows="3" placeholder="Describe what needs to be done..." />
        <div class="grid grid-cols-2 gap-4">
          <AppSelect v-model="itemForm.priority" label="Priority" :options="PRIORITY_OPTIONS" />
          <AppDatePicker v-model="itemForm.due_date" label="Due Date" />
        </div>
        <AppSelect v-model="itemForm.assigned_to_id" label="Assign To" :options="[{ value: null, label: 'Unassigned' }, ...users]" placeholder="Select user..." />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showItemModal = false">Cancel</AppButton>
          <AppButton variant="primary" @click="saveItem" :loading="itemSaving">
            {{ editingItem ? 'Save Changes' : 'Add Item' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- ── Complete Item Modal ──────────────────────────────────────── -->
    <AppModal :show="showCompleteModal" @close="showCompleteModal = false" title="Mark as Completed" size="sm">
      <div class="space-y-4">
        <div class="rounded-lg bg-muted/50 border border-border px-4 py-3 text-sm">
          <p class="text-muted-foreground">Marking as completed</p>
          <p class="font-medium text-foreground mt-0.5">{{ completingItem?.name }}</p>
        </div>
        <AppInput v-model="completionNotes" label="Completion Notes (optional)" type="textarea" :rows="3" placeholder="Any notes about how this was completed..." />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showCompleteModal = false">Cancel</AppButton>
          <AppButton variant="primary" @click="confirmComplete" :loading="completeSaving">
            Confirm Complete
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
