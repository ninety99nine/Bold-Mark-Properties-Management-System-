<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
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
import AppStatCard from '@/components/common/AppStatCard.vue'

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

// ── Status filter ─────────────────────────────────────────────────────
const statusFilter = ref('all')

// ── Item modal ───────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem = ref(null)
const itemForm = ref({ name: '', category: '', description: '', priority: 'medium', due_date: null, assigned_to_id: null })
const itemSaving = ref(false)

// ── Confirm modal ────────────────────────────────────────────────────
const confirmModal = ref({ show: false, title: '', body: '', confirmLabel: 'Delete', loading: false, onConfirm: null })

function showConfirm({ title, body, confirmLabel = 'Delete', onConfirm }) {
  confirmModal.value = { show: true, title, body, confirmLabel, loading: false, onConfirm }
}

async function handleConfirm() {
  confirmModal.value.loading = true
  try {
    await confirmModal.value.onConfirm()
  } finally {
    confirmModal.value.loading = false
    confirmModal.value.show = false
  }
}

// ── Status picker ────────────────────────────────────────────────────
const activeStatusPicker = ref(null) // stores item object
const statusPickerPos = ref({ top: 0, left: 0 })

function toggleStatusPicker(item, event) {
  if (activeStatusPicker.value?.id === item.id) {
    activeStatusPicker.value = null
    return
  }
  const rect = event.currentTarget.getBoundingClientRect()
  const dropdownHeight = 136 // 4 options × 34px
  const spaceBelow = window.innerHeight - rect.bottom
  statusPickerPos.value = {
    top: spaceBelow < dropdownHeight + 8 ? rect.top - dropdownHeight - 4 : rect.bottom + 4,
    left: rect.left,
  }
  activeStatusPicker.value = item
}

function setStatus(item, status) {
  activeStatusPicker.value = null
  markStatus(item, status)
}

function closeStatusPicker() {
  activeStatusPicker.value = null
}

// ── Attachments ─────────────────────────────────────────────────────
const attachmentUploading = ref({}) // keyed by item.id
const MAX_ATTACHMENTS = 5

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

const STATUS_OPTIONS = [
  { value: 'pending',     label: 'Pending',     dot: 'bg-gray-400'    },
  { value: 'in_progress', label: 'In Progress', dot: 'bg-blue-400'    },
  { value: 'completed',   label: 'Completed',   dot: 'bg-emerald-500' },
  { value: 'waived',      label: 'Waived',      dot: 'bg-gray-300'    },
]

const STATUS_CHIP_CLASS = {
  pending:     { chip: 'bg-gray-100 text-gray-600',      dot: 'bg-gray-400'    },
  in_progress: { chip: 'bg-blue-50 text-blue-700',       dot: 'bg-blue-400'    },
  completed:   { chip: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
  overdue:     { chip: 'bg-red-50 text-red-700',         dot: 'bg-red-400'     },
  waived:      { chip: 'bg-gray-100 text-gray-400',      dot: 'bg-gray-300'    },
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
  document.addEventListener('click', closeStatusPicker)
  document.addEventListener('scroll', closeStatusPicker, true)
})

onUnmounted(() => {
  document.removeEventListener('click', closeStatusPicker)
  document.removeEventListener('scroll', closeStatusPicker, true)
})

// ── Computed ─────────────────────────────────────────────────────────
const filteredItems = computed(() => {
  if (statusFilter.value === 'all') return items.value
  return items.value.filter(i => i.status === statusFilter.value)
})

const upNextItem = computed(() => {
  const active = items.value.filter(i => i.status !== 'completed' && i.status !== 'waived')
  if (!active.length) return null
  const withDate = active.filter(i => i.due_date).sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
  return withDate[0] || active.find(i => !i.due_date) || null
})

const groupedItems = computed(() => {
  const groups = {}
  for (const item of filteredItems.value) {
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
  if (applicableCount.value === 0) return totalItems.value > 0 ? 100 : 0
  return Math.round((completedCount.value / applicableCount.value) * 100)
})

const totalItems = computed(() => items.value.length)
const completedCount = computed(() => items.value.filter(i => i.status === 'completed').length)
const overdueCount = computed(() => items.value.filter(i => {
  if (i.status === 'completed' || i.status === 'waived') return false
  if (i.status === 'overdue') return true
  return i.due_date && new Date(i.due_date) < new Date(new Date().toDateString())
}).length)
const inProgressCount = computed(() => items.value.filter(i => i.status === 'in_progress').length)
const waivedCount = computed(() => items.value.filter(i => i.status === 'waived').length)
const applicableCount = computed(() => totalItems.value - waivedCount.value)
const pendingCount = computed(() => items.value.filter(i => i.status === 'pending' || i.status === 'in_progress').length)
const strictPendingCount = computed(() => items.value.filter(i => i.status === 'pending').length)

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

function daysUntil(dateStr) {
  if (!dateStr) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const due = new Date(dateStr)
  due.setHours(0, 0, 0, 0)
  return Math.round((due - today) / (1000 * 60 * 60 * 24))
}

function daysUntilLabel(dateStr) {
  const d = daysUntil(dateStr)
  if (d === null) return { text: 'No due date', class: 'text-muted-foreground' }
  if (d === 0) return { text: 'Due today', class: 'text-amber-600 font-semibold' }
  if (d < 0) return { text: `${Math.abs(d)}d overdue`, class: 'text-red-600 font-semibold' }
  if (d <= 7) return { text: `Due in ${d}d`, class: 'text-amber-600 font-semibold' }
  return { text: `Due in ${d}d`, class: 'text-muted-foreground' }
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

function deleteItem(item) {
  showConfirm({
    title: 'Delete Compliance Item',
    body: `"${item.name}" will be permanently removed from this checklist.`,
    onConfirm: async () => {
      await api.delete(`/compliance/checklists/${checklist.value.id}/items/${item.id}`)
      fetchChecklist()
    },
  })
}

// ── Checklist delete ────────────────────────────────────────────────
function deleteChecklist() {
  showConfirm({
    title: 'Delete Compliance Checklist',
    body: `This will permanently delete the FY ${checklist.value.financial_year_label} checklist for ${checklist.value.estate?.name} and all its items. This cannot be undone.`,
    confirmLabel: 'Delete Checklist',
    onConfirm: async () => {
      await api.delete(`/compliance/checklists/${checklist.value.id}`)
      goBack()
    },
  })
}

// ── Attachments ─────────────────────────────────────────────────────
function onFileInputChange(e, item) {
  const existing = item.attachments?.length || 0
  const remaining = Math.max(0, MAX_ATTACHMENTS - existing)
  const files = Array.from(e.target.files || []).slice(0, remaining)
  if (files.length) uploadAttachments(item, files)
  e.target.value = ''
}

async function uploadAttachments(item, files) {
  attachmentUploading.value = { ...attachmentUploading.value, [item.id]: true }
  try {
    const form = new FormData()
    files.forEach(f => form.append('attachments[]', f))
    await api.post(`/compliance/checklists/${checklist.value.id}/items/${item.id}/attachments`, form)
    fetchChecklist()
  } catch {
    // silent
  } finally {
    attachmentUploading.value = { ...attachmentUploading.value, [item.id]: false }
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

function deleteAttachment(item, attachment) {
  showConfirm({
    title: 'Remove Attachment',
    body: `"${attachment.file_name}" will be permanently removed from this item.`,
    confirmLabel: 'Remove',
    onConfirm: async () => {
      await api.delete(`/compliance/checklists/${checklist.value.id}/items/${item.id}/attachments/${attachment.id}`)
      fetchChecklist()
    },
  })
}

function toggleCategory(cat) {
  expandedCategories.value[cat] = !expandedCategories.value[cat]
}

async function scrollToItem(item) {
  statusFilter.value = 'all'
  expandedCategories.value[item.category] = true
  await nextTick()
  const el = document.getElementById('item-' + item.id)
  if (!el) return
  el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  el.classList.add('!bg-primary/5')
  setTimeout(() => el.classList.remove('!bg-primary/5'), 1400)
}
</script>

<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-muted animate-pulse" />
        <div class="flex-1 space-y-2">
          <div class="h-7 w-48 rounded bg-muted animate-pulse" />
          <div class="h-4 w-80 rounded bg-muted animate-pulse" />
        </div>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        <div v-for="i in 4" :key="i" class="h-[88px] bg-white rounded-xl border border-border animate-pulse" />
      </div>
      <div class="h-[72px] bg-white rounded-xl border border-border animate-pulse mt-4" />
      <div v-for="i in 3" :key="i" class="h-[200px] bg-white rounded-xl border border-border animate-pulse mt-3" />
    </div>

    <template v-else-if="checklist">
      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <div class="flex items-start gap-3 mb-6">
        <AppButton variant="ghost" square size="md" @click="goBack()">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="text-muted-foreground">
            <path d="m12 19-7-7 7-7" /><path d="M19 12H5" />
          </svg>
        </AppButton>

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 mb-0.5">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-widest">Compliance</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3 text-border">
              <path d="m9 18 6-6-6-6"/>
            </svg>
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-widest">Checklist</span>
          </div>
          <h1 class="font-body font-bold text-2xl text-foreground leading-tight">{{ checklist.estate?.name }}</h1>
          <p class="text-sm text-muted-foreground mt-0.5">
            FY {{ checklist.financial_year_label }}
            <span class="mx-1.5 opacity-40">&middot;</span>
            {{ formatDate(checklist.financial_year_start) }} &ndash; {{ formatDate(checklist.financial_year_end) }}
          </p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <AppButton variant="outline" size="sm" @click="openAddItem()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
              <path d="M12 5v14"/><path d="M5 12h14"/>
            </svg>
            Add Item
          </AppButton>
          <AppButton variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/5" @click="deleteChecklist">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
            </svg>
            Delete
          </AppButton>
        </div>
      </div>

      <!-- ── Stat cards ──────────────────────────────────────────────────── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <AppStatCard label="Total Items" :value="String(totalItems)" valueSize="text-2xl">
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4 text-muted-foreground">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </template>
        </AppStatCard>
        <AppStatCard
          label="Completed"
          :value="String(completedCount)"
          valueSize="text-2xl"
          :value-class="completedCount > 0 ? 'text-emerald-600' : 'text-foreground'"
          :subtitle="applicableCount > 0 ? `${progress}% of applicable` : (waivedCount > 0 ? 'All waived' : 'None done yet')"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" :class="['w-4 h-4', completedCount > 0 ? 'text-emerald-500' : 'text-muted-foreground']">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </template>
        </AppStatCard>
        <AppStatCard
          label="In Progress"
          :value="String(inProgressCount)"
          valueSize="text-2xl"
          :value-class="inProgressCount > 0 ? 'text-blue-600' : 'text-foreground'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" :class="['w-4 h-4', inProgressCount > 0 ? 'text-blue-500' : 'text-muted-foreground']">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </template>
        </AppStatCard>
        <AppStatCard
          label="Overdue"
          :value="String(overdueCount)"
          valueSize="text-2xl"
          :value-class="overdueCount > 0 ? 'text-red-600' : 'text-foreground'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" :class="['w-4 h-4', overdueCount > 0 ? 'text-red-500' : 'text-muted-foreground']">
              <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
            </svg>
          </template>
        </AppStatCard>
      </div>

      <!-- ── Overall progress ────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-border px-5 py-4 mb-6">
        <div class="flex items-center justify-between mb-2.5">
          <p class="text-sm font-semibold text-foreground">Overall Compliance Progress</p>
          <span :class="['text-sm font-bold tabular-nums', progress === 100 ? 'text-emerald-600' : progress >= 50 ? 'text-amber-600' : progress > 0 ? 'text-red-600' : 'text-muted-foreground']">
            {{ progress }}%
          </span>
        </div>
        <!-- Segmented bar -->
        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden flex">
          <div
            v-if="completedCount > 0"
            class="h-full bg-emerald-500 transition-all duration-700"
            :style="{ width: (applicableCount > 0 ? completedCount / applicableCount * 100 : 100) + '%' }"
          />
          <div
            v-if="inProgressCount > 0"
            class="h-full bg-blue-400 transition-all duration-700"
            :style="{ width: (inProgressCount / applicableCount * 100) + '%' }"
          />
          <div
            v-if="overdueCount > 0"
            class="h-full bg-red-400 transition-all duration-700"
            :style="{ width: (overdueCount / applicableCount * 100) + '%' }"
          />
        </div>
        <div class="flex items-center gap-5 mt-2.5">
          <button @click="statusFilter = 'completed'" class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors">
            <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" />
            {{ completedCount }} completed
          </button>
          <button @click="statusFilter = 'in_progress'" class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors">
            <span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0" />
            {{ inProgressCount }} in progress
          </button>
          <button v-if="overdueCount > 0" @click="statusFilter = 'overdue'" class="flex items-center gap-1.5 text-xs text-red-600 font-medium hover:text-red-700 transition-colors">
            <span class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0" />
            {{ overdueCount }} overdue
          </button>
          <button @click="statusFilter = 'pending'" class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors">
            <span class="w-2 h-2 rounded-full bg-gray-300 flex-shrink-0" />
            {{ pendingCount - inProgressCount }} pending
          </button>
          <button v-if="waivedCount > 0" @click="statusFilter = 'waived'" class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors">
            <span class="w-2 h-2 rounded-full bg-gray-300 border border-gray-400 flex-shrink-0" />
            {{ waivedCount }} waived
          </button>
        </div>
      </div>

      <!-- ── Up Next ──────────────────────────────────────────────────────── -->
      <div v-if="upNextItem" class="rounded-xl border border-border bg-white mb-4 overflow-hidden cursor-pointer hover:border-primary/30 hover:shadow-sm transition-all" @click="scrollToItem(upNextItem)">
        <div class="flex items-stretch">
          <div :class="['w-1 flex-shrink-0', CATEGORY_BAR[upNextItem.category] || 'bg-primary']" />
          <div class="flex-1 flex items-center gap-4 px-5 py-3.5">
            <div class="w-8 h-8 rounded-lg bg-primary/10 border border-primary/10 flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-primary">
                <polygon points="5 3 19 12 5 21 5 3"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-semibold text-primary uppercase tracking-widest mb-0.5">Up Next</p>
              <p class="text-sm font-semibold text-foreground truncate">{{ upNextItem.name }}</p>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="text-xs text-muted-foreground">{{ upNextItem.category }}</span>
                <span class="text-muted-foreground/30">·</span>
                <span :class="['inline-flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded', PRIORITY_BADGE[upNextItem.priority] || 'bg-gray-100 text-gray-600']">
                  <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', PRIORITY_DOT[upNextItem.priority] || 'bg-gray-400']" />
                  {{ PRIORITY_CONFIG[upNextItem.priority]?.label }}
                </span>
              </div>
            </div>
            <div class="flex-shrink-0 text-right">
              <p v-if="upNextItem.due_date" :class="['text-sm tabular-nums', daysUntilLabel(upNextItem.due_date).class]">
                {{ daysUntilLabel(upNextItem.due_date).text }}
              </p>
              <p :class="['text-[11px] mt-0.5', upNextItem.due_date ? 'text-muted-foreground' : 'text-muted-foreground/50 italic']">
                {{ upNextItem.due_date ? formatDate(upNextItem.due_date) : 'No due date set' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Filter tabs ───────────────────────────────────────────────────── -->
      <div class="flex items-center gap-1 mb-3 bg-white rounded-xl border border-border p-1">
        <button
          v-for="tab in [
            { value: 'all',         label: 'All',         count: totalItems        },
            { value: 'completed',   label: 'Completed',   count: completedCount    },
            { value: 'in_progress', label: 'In Progress', count: inProgressCount   },
            { value: 'pending',     label: 'Pending',     count: strictPendingCount },
            { value: 'overdue',     label: 'Overdue',     count: overdueCount      },
            { value: 'waived',      label: 'Waived',      count: waivedCount       },
          ]"
          :key="tab.value"
          @click="statusFilter = tab.value"
          :class="[
            'flex-1 flex items-center justify-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg transition-colors',
            statusFilter === tab.value
              ? 'bg-primary text-white shadow-sm'
              : 'text-muted-foreground hover:text-foreground hover:bg-gray-50',
          ]"
        >
          {{ tab.label }}
          <span :class="[
            'text-[10px] px-1.5 py-0.5 rounded-full font-semibold',
            statusFilter === tab.value ? 'bg-white/20 text-white' : 'bg-gray-100 text-muted-foreground',
          ]">{{ tab.count }}</span>
        </button>
      </div>

      <!-- ── Category sections ───────────────────────────────────────────── -->
      <div class="space-y-3">
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
              <p class="text-xs text-muted-foreground">
                {{ statusSummary[category]?.completed || 0 }} of {{ statusSummary[category]?.total || catItems.length }} items completed
              </p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
              <div class="w-24 h-1 bg-gray-100 rounded-full overflow-hidden hidden sm:block">
                <div
                  :class="['h-full rounded-full transition-all duration-500', progressBarColor(categoryProgress(category))]"
                  :style="{ width: categoryProgress(category) + '%' }"
                />
              </div>
              <span class="text-xs font-semibold text-muted-foreground tabular-nums w-8 text-right">{{ categoryProgress(category) }}%</span>
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
                <div class="w-[112px] flex-shrink-0 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Status</div>
                <div class="flex-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Task</div>
                <div class="w-[96px] hidden sm:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground text-center">Priority</div>
                <div class="w-[112px] hidden sm:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Due Date</div>
                <div class="w-[112px] hidden md:block text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Assigned To</div>
                <div class="w-[84px] flex-shrink-0 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground text-right">Actions</div>
              </div>

              <div
                v-for="item in catItems"
                :key="item.id"
                :id="'item-' + item.id"
                :class="[
                  'border-b border-border last:border-b-0 group transition-colors duration-700',
                  item.status === 'overdue' ? 'bg-red-50/20' : item.status === 'completed' ? 'bg-gray-50/30' : '',
                ]"
              >
                <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50/40">
                  <!-- Status chip -->
                  <div class="w-[112px] flex-shrink-0 mt-0.5">
                    <button
                      @click.stop="toggleStatusPicker(item, $event)"
                      :class="[
                        'flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded-md w-full transition-colors',
                        STATUS_CHIP_CLASS[item.status]?.chip || 'bg-gray-100 text-gray-600',
                      ]"
                    >
                      <span :class="['w-2 h-2 rounded-full flex-shrink-0', STATUS_CHIP_CLASS[item.status]?.dot || 'bg-gray-400']" />
                      <span class="flex-1 text-left truncate">{{ STATUS_CONFIG[item.status]?.label || item.status }}</span>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3 flex-shrink-0 opacity-50">
                        <path d="m6 9 6 6 6-6"/>
                      </svg>
                    </button>
                  </div>

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <p :class="['text-sm font-medium leading-snug', item.status === 'completed' ? 'text-muted-foreground' : 'text-foreground']">
                      {{ item.name }}
                    </p>
                    <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ item.description }}</p>

                    <!-- Completion info -->
                    <p v-if="item.completed_at" class="text-[10px] text-muted-foreground/60 mt-0.5">
                      Done {{ formatDate(item.completed_at) }}{{ item.completed_by ? ' · ' + item.completed_by.name.split(' ')[0] : '' }}
                    </p>

                    <!-- Completion notes -->
                    <p v-if="item.completion_notes" class="mt-1.5 text-xs text-emerald-800 bg-emerald-50 border border-emerald-100 rounded px-2 py-1 leading-relaxed">
                      {{ item.completion_notes }}
                    </p>

                    <!-- Attachment chips -->
                    <div v-if="item.attachments?.length" class="mt-2 flex flex-wrap gap-1.5 items-center">
                      <div
                        v-for="att in item.attachments"
                        :key="att.id"
                        class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs text-foreground transition-colors"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-3 h-3 text-muted-foreground flex-shrink-0">
                          <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                        </svg>
                        <span class="max-w-[120px] truncate">{{ att.file_name }}</span>
                        <span v-if="att.file_size" class="text-muted-foreground">{{ formatFileSize(att.file_size) }}</span>
                        <button @click.stop="downloadAttachment(item, att)" class="p-0.5 rounded hover:bg-white text-muted-foreground hover:text-foreground transition-colors" title="Download">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                          </svg>
                        </button>
                        <button @click.stop="deleteAttachment(item, att)" class="p-0.5 rounded hover:bg-white text-muted-foreground hover:text-red-500 transition-colors" title="Remove">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                          </svg>
                        </button>
                      </div>
                      <!-- Count / uploading indicator -->
                      <span v-if="attachmentUploading[item.id]" class="text-[10px] text-primary animate-pulse">Uploading…</span>
                      <span v-else-if="item.attachments.length >= MAX_ATTACHMENTS" class="text-[10px] text-muted-foreground/60">5 / 5</span>
                      <span v-else class="text-[10px] text-muted-foreground/60">{{ item.attachments.length }} / 5</span>
                    </div>

                    <!-- Mobile meta row -->
                    <div class="flex items-center flex-wrap gap-2 mt-2 sm:hidden">
                      <span :class="['inline-flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded', PRIORITY_BADGE[item.priority] || 'bg-gray-100 text-gray-600']">
                        <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', PRIORITY_DOT[item.priority] || 'bg-gray-400']" />
                        {{ PRIORITY_CONFIG[item.priority]?.label }}
                      </span>
                      <span v-if="item.due_date" :class="['text-xs flex items-center gap-1', item.status === 'overdue' ? 'text-red-600 font-medium' : 'text-muted-foreground']">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3">
                          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>
                        </svg>
                        {{ formatDate(item.due_date) }}
                      </span>
                      <span v-if="item.assigned_to" class="text-xs text-muted-foreground">
                        {{ item.assigned_to.name }}
                      </span>
                    </div>
                  </div>

                  <!-- Priority (desktop) -->
                  <div class="hidden sm:flex w-[96px] justify-center items-start pt-0.5 flex-shrink-0">
                    <span :class="['inline-flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded', PRIORITY_BADGE[item.priority] || 'bg-gray-100 text-gray-600']">
                      <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', PRIORITY_DOT[item.priority] || 'bg-gray-400']" />
                      {{ PRIORITY_CONFIG[item.priority]?.label }}
                    </span>
                  </div>

                  <!-- Due date (desktop) -->
                  <div class="hidden sm:block w-[112px] flex-shrink-0 pt-0.5">
                    <p v-if="item.due_date" :class="['text-xs', item.status === 'overdue' ? 'text-red-600 font-medium' : 'text-muted-foreground']">
                      {{ formatDate(item.due_date) }}
                    </p>
                    <p v-else class="text-xs text-muted-foreground/40">—</p>
                  </div>

                  <!-- Assignee (desktop) -->
                  <div class="hidden md:block w-[112px] flex-shrink-0 pt-0.5">
                    <p class="text-xs text-muted-foreground truncate">{{ item.assigned_to?.name || '—' }}</p>
                  </div>

                  <!-- Actions (hover-reveal) -->
                  <div class="flex items-center gap-0.5 w-[84px] justify-end flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      @click="openEditItem(item)"
                      title="Edit item"
                      class="p-1.5 rounded hover:bg-blue-50 text-muted-foreground hover:text-blue-600 transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                      </svg>
                    </button>
                    <label
                      :title="(item.attachments?.length || 0) >= MAX_ATTACHMENTS ? 'Max 5 attachments reached' : 'Attach file'"
                      :class="[
                        'p-1.5 rounded transition-colors',
                        (item.attachments?.length || 0) >= MAX_ATTACHMENTS
                          ? 'text-muted-foreground/30 cursor-not-allowed pointer-events-none'
                          : attachmentUploading[item.id]
                            ? 'text-primary animate-pulse cursor-wait pointer-events-none'
                            : 'hover:bg-gray-100 text-muted-foreground hover:text-foreground cursor-pointer',
                      ]"
                    >
                      <input
                        type="file"
                        class="sr-only"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx"
                        :multiple="(MAX_ATTACHMENTS - (item.attachments?.length || 0)) > 1"
                        :disabled="(item.attachments?.length || 0) >= MAX_ATTACHMENTS || attachmentUploading[item.id]"
                        @change="e => onFileInputChange(e, item)"
                      />
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                      </svg>
                    </label>
                    <button
                      @click="deleteItem(item)"
                      title="Delete item"
                      class="p-1.5 rounded hover:bg-red-50 text-muted-foreground hover:text-red-600 transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Add item row -->
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

      <!-- Empty filter state -->
      <div v-if="Object.keys(groupedItems).length === 0 && statusFilter !== 'all'" class="py-12 text-center">
        <p class="text-sm text-muted-foreground">No {{ statusFilter === 'in_progress' ? 'in progress' : statusFilter }} items.</p>
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

    <!-- ── Global status picker (teleported to escape overflow:hidden) ──── -->
    <Teleport to="body">
      <div
        v-if="activeStatusPicker"
        :style="{ position: 'fixed', top: statusPickerPos.top + 'px', left: statusPickerPos.left + 'px', zIndex: 9999 }"
        class="bg-white rounded-lg shadow-lg border border-border py-1 w-36"
        @click.stop
      >
        <button
          v-for="opt in STATUS_OPTIONS"
          :key="opt.value"
          @click.stop="setStatus(activeStatusPicker, opt.value)"
          :class="[
            'w-full flex items-center gap-2 px-3 py-1.5 text-xs transition-colors',
            opt.value === activeStatusPicker.status
              ? 'bg-primary/5 text-primary font-semibold'
              : 'text-foreground hover:bg-gray-50',
          ]"
        >
          <span :class="['w-2 h-2 rounded-full flex-shrink-0', opt.dot]" />
          {{ opt.label }}
          <svg v-if="opt.value === activeStatusPicker.status" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3 ml-auto text-primary">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </button>
      </div>
    </Teleport>

    <!-- ── Add / Edit Item Modal ────────────────────────────────────────── -->
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

    <!-- ── Confirm / Delete Modal ────────────────────────────────────────── -->
    <AppModal :show="confirmModal.show" @close="confirmModal.show = false" :title="confirmModal.title" size="sm">
      <div class="space-y-4">
        <div class="flex items-start gap-4 p-4 rounded-xl bg-destructive/5 border border-destructive/20">
          <div class="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-destructive">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-destructive mb-1">This action cannot be undone.</p>
            <p class="text-sm text-muted-foreground leading-relaxed">{{ confirmModal.body }}</p>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="outline" :disabled="confirmModal.loading" @click="confirmModal.show = false">Cancel</AppButton>
          <AppButton variant="danger" :loading="confirmModal.loading" @click="handleConfirm">
            {{ confirmModal.confirmLabel }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- ── Complete Item Modal ──────────────────────────────────────────── -->
    <AppModal :show="showCompleteModal" @close="showCompleteModal = false" title="Mark as Completed" size="sm">
      <div class="space-y-4">
        <div class="rounded-lg bg-muted/50 border border-border px-4 py-3 text-sm">
          <p class="text-muted-foreground text-xs uppercase font-semibold tracking-wider mb-1">Marking as completed</p>
          <p class="font-medium text-foreground">{{ completingItem?.name }}</p>
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
