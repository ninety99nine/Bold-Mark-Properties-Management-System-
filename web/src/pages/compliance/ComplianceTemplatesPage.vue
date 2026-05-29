<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useBack } from '@/composables/useBack'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'

const router = useRouter()
const { goBack } = useBack('/compliance')
const { success, error: toastError } = useToast()

// ── State ────────────────────────────────────────────────────────────
const loading = ref(true)
const templates = ref([])
const showModal = ref(false)
const editingTemplate = ref(null)
const saving = ref(false)

const form = ref({
  name: '',
  description: '',
  country: null,
  is_default: false,
})

const COUNTRY_OPTIONS = [
  { value: null, label: 'All Countries' },
  { value: 'ZA', label: 'South Africa' },
  { value: 'BW', label: 'Botswana' },
  { value: 'NA', label: 'Namibia' },
]

// ── Fetch ────────────────────────────────────────────────────────────
async function fetchTemplates() {
  loading.value = true
  try {
    const { data } = await api.get('/compliance/templates', { params: { _per_page: 50 } })
    templates.value = data.data || []
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

onMounted(fetchTemplates)

// ── CRUD ─────────────────────────────────────────────────────────────
function openAdd() {
  editingTemplate.value = null
  form.value = { name: '', description: '', country: null, is_default: false }
  showModal.value = true
}

function openEdit(template) {
  router.push({ name: 'compliance-template-detail', params: { templateId: template.id } })
}

async function save() {
  saving.value = true
  try {
    const { data } = await api.post('/compliance/templates', form.value)
    showModal.value = false
    success('Template created successfully.')
    router.push({ name: 'compliance-template-detail', params: { templateId: data.data.id } })
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Something went wrong.')
  } finally {
    saving.value = false
  }
}

async function deleteTemplate(template) {
  if (!confirm(`Delete template "${template.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/compliance/templates/${template.id}`)
    success('Template deleted.')
    fetchTemplates()
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Something went wrong.')
  }
}

function countryLabel(code) {
  const map = { ZA: 'South Africa', BW: 'Botswana', NA: 'Namibia' }
  return map[code] || code || 'All'
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-2">
        <button @click="goBack" class="text-muted-foreground hover:text-foreground transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
            <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-foreground">Compliance Templates</h1>
          <p class="text-sm text-muted-foreground mt-0.5">Manage reusable checklist templates for estates</p>
        </div>
      </div>
      <AppButton variant="primary" size="sm" @click="openAdd">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 mr-1.5">
          <path d="M12 5v14"/><path d="M5 12h14"/>
        </svg>
        New Template
      </AppButton>
    </div>

    <!-- Templates list -->
    <div v-if="!loading" class="space-y-3">
      <div
        v-for="template in templates"
        :key="template.id"
        class="bg-white rounded-xl border border-border p-5 hover:shadow-sm hover:border-primary/20 transition-all cursor-pointer"
        @click="openEdit(template)"
      >
        <div class="flex items-start justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-sm font-semibold text-foreground">{{ template.name }}</h3>
              <AppBadge v-if="template.is_default" variant="success" size="sm">Default</AppBadge>
              <AppBadge v-if="template.is_system" variant="info" size="sm">System</AppBadge>
            </div>
            <p v-if="template.description" class="text-xs text-muted-foreground line-clamp-2 mb-2">{{ template.description }}</p>
            <div class="flex items-center gap-3 text-xs text-muted-foreground">
              <span>{{ template.items_count || 0 }} items</span>
              <span>&bull;</span>
              <span>{{ countryLabel(template.country) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-1.5 flex-shrink-0 ml-4">
            <AppButton variant="ghost" size="sm" @click.stop="openEdit(template)">Edit</AppButton>
            <AppButton v-if="!template.is_system" variant="danger-ghost" size="sm" @click.stop="deleteTemplate(template)">Delete</AppButton>
          </div>
        </div>

        <!-- Item preview -->
        <div v-if="template.items?.length" class="mt-3 pt-3 border-t border-border">
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="item in template.items.slice(0, 6)"
              :key="item.id"
              class="text-xs bg-gray-100 text-muted-foreground px-2 py-0.5 rounded"
            >
              {{ item.name }}
            </span>
            <span v-if="template.items.length > 6" class="text-xs text-muted-foreground px-2 py-0.5">
              +{{ template.items.length - 6 }} more
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-else class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-[120px] bg-white rounded-xl border border-border animate-pulse" />
    </div>

    <!-- Empty state -->
    <div v-if="!loading && !templates.length" class="text-center py-16 bg-white rounded-xl border border-border">
      <h3 class="text-sm font-semibold text-foreground mb-1">No templates yet</h3>
      <p class="text-xs text-muted-foreground mb-4">Create a template to quickly set up compliance checklists</p>
      <AppButton variant="primary" size="sm" @click="openAdd">Create Template</AppButton>
    </div>

    <!-- ── New Template Modal ──────────────────────────────────────── -->
    <AppModal :show="showModal" @close="showModal = false" title="New Template" size="sm">
      <div class="space-y-4">
        <AppInput v-model="form.name" label="Template Name" placeholder="e.g. Sectional Title — SA" required />
        <AppInput v-model="form.description" label="Description" type="textarea" :rows="2" placeholder="Describe what this template covers..." />
        <AppSelect v-model="form.country" label="Country" :options="COUNTRY_OPTIONS" />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showModal = false">Cancel</AppButton>
          <AppButton variant="primary" @click="save" :loading="saving">Create Template</AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
