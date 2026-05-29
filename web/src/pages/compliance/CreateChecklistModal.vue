<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'

const { success } = useToast()

const props = defineProps({
  show: Boolean,
  estateId: { type: String, default: null },
  estateName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'created'])

// ── State ────────────────────────────────────────────────────────────
const saving = ref(false)
const estates = ref([])
const templates = ref([])
const errors = ref({})
const generalError = ref('')

const form = ref({
  estate_id: '',
  template_id: null,
  financial_year_label: '',
  financial_year_start: '',
  financial_year_end: '',
  notes: '',
})

// ── Computed ─────────────────────────────────────────────────────────
const estateOptions = computed(() =>
  estates.value.map(e => ({ value: e.id, label: e.name }))
)

const templateOptions = computed(() => [
  { value: null, label: 'Blank checklist (no template)' },
  ...templates.value.map(t => ({
    value: t.id,
    label: `${t.name} (${t.items_count || 0} items)`,
  })),
])

// Quick-fill helpers for financial year
const currentYear = new Date().getFullYear()
const yearPresets = [
  { label: `${currentYear}/${currentYear + 1}`, start: `${currentYear}-01-01`, end: `${currentYear}-12-31` },
  { label: `${currentYear - 1}/${currentYear}`, start: `${currentYear - 1}-01-01`, end: `${currentYear - 1}-12-31` },
  { label: `Jul ${currentYear} – Jun ${currentYear + 1}`, start: `${currentYear}-07-01`, end: `${currentYear + 1}-06-30` },
  { label: `Apr ${currentYear} – Mar ${currentYear + 1}`, start: `${currentYear}-04-01`, end: `${currentYear + 1}-03-31` },
]

function applyPreset(preset) {
  form.value.financial_year_label = preset.label
  form.value.financial_year_start = preset.start
  form.value.financial_year_end = preset.end
}

// ── Fetch ────────────────────────────────────────────────────────────
async function fetchEstates() {
  try {
    const { data } = await api.get('/estates', { params: { _per_page: 100, is_active: true } })
    estates.value = data.data || []
  } catch { /* silent */ }
}

async function fetchTemplates() {
  try {
    const { data } = await api.get('/compliance/templates', { params: { _per_page: 50 } })
    templates.value = data.data || []
  } catch { /* silent */ }
}

onMounted(() => {
  fetchEstates()
  fetchTemplates()
})

watch(() => props.show, (val) => {
  if (val) {
    form.value = {
      estate_id: props.estateId || '',
      template_id: null,
      financial_year_label: '',
      financial_year_start: '',
      financial_year_end: '',
      notes: '',
    }
    errors.value = {}
    generalError.value = ''
  }
})

// ── Submit ───────────────────────────────────────────────────────────
async function submit() {
  saving.value = true
  errors.value = {}
  generalError.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.template_id) delete payload.template_id
    if (!payload.notes) delete payload.notes

    const { data } = await api.post('/compliance/checklists', payload)
    success('Compliance checklist created.')
    emit('created', data.data)
    emit('close')
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      if (err.response.data.message && !Object.keys(errors.value).length) {
        generalError.value = err.response.data.message
      }
    } else if (err.response?.status === 409) {
      generalError.value = err.response.data.message || 'A checklist for this financial year already exists.'
    } else {
      generalError.value = 'Something went wrong. Please try again.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :show="show" @close="emit('close')" title="Create Compliance Checklist" size="lg">
    <div class="space-y-5">
      <!-- General error -->
      <div v-if="generalError" class="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
        {{ generalError }}
      </div>

      <!-- Estate select (hidden if pre-set) -->
      <div v-if="!estateId">
        <AppSelect
          v-model="form.estate_id"
          label="Estate"
          :options="estateOptions"
          placeholder="Select estate..."
          :error="errors.estate_id?.[0]"
          required
        />
      </div>
      <div v-else class="text-sm">
        <span class="text-muted-foreground">Estate:</span>
        <span class="font-semibold text-foreground ml-1">{{ estateName }}</span>
      </div>

      <!-- Financial year presets -->
      <div>
        <label class="block text-sm font-medium text-foreground mb-1.5">Financial Year</label>
        <div class="flex flex-wrap gap-2 mb-3">
          <button
            v-for="preset in yearPresets"
            :key="preset.label"
            @click="applyPreset(preset)"
            :class="[
              'text-xs px-3 py-1.5 rounded-lg border transition-colors',
              form.financial_year_label === preset.label
                ? 'bg-primary border-primary text-white font-medium'
                : 'bg-gray-50 border-border text-muted-foreground hover:bg-gray-100',
            ]"
          >
            {{ preset.label }}
          </button>
        </div>
      </div>

      <!-- Financial year details -->
      <div class="grid grid-cols-3 gap-4">
        <AppInput
          v-model="form.financial_year_label"
          label="Year Label"
          placeholder="2026/2027"
          :error="errors.financial_year_label?.[0]"
          required
        />
        <AppDatePicker
          v-model="form.financial_year_start"
          label="Start Date"
          :error="errors.financial_year_start?.[0]"
        />
        <AppDatePicker
          v-model="form.financial_year_end"
          label="End Date"
          :error="errors.financial_year_end?.[0]"
        />
      </div>

      <!-- Template selection -->
      <AppSelect
        v-model="form.template_id"
        label="Compliance Template"
        :options="templateOptions"
        placeholder="Choose a template..."
      />
      <p class="text-xs text-muted-foreground -mt-3">
        Templates pre-fill the checklist with standard compliance items. You can add, edit, or remove items after creation.
      </p>

      <!-- Notes -->
      <AppInput
        v-model="form.notes"
        label="Notes (optional)"
        type="textarea"
        :rows="2"
        placeholder="Any notes about this financial year..."
      />
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <AppButton variant="ghost" @click="emit('close')">Cancel</AppButton>
        <AppButton variant="primary" @click="submit" :loading="saving">
          Create Checklist
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>
