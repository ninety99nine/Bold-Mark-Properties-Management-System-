<!--
  AddCommunityModal — WeConnectU "Add / Edit Community" modal (single form).

  Mirrors WeConnectU exactly: label-on-the-left rows for Community, Code, Entity
  Type, Year End, Merchant Number, PDF Passwords and Select Users. One "Save"
  button in add mode; "Save" + "Login" in edit mode.

  Props:
    show      — controls visibility (passed to AppModal)
    community — null → Add mode; a community resource → Edit mode (prefilled)
  Emits:
    close             — request to close (parent sets show=false)
    created(community) — a community was created; payload is the API resource
    updated(community) — an existing community was saved
    login(community)   — user pressed "Login" for this community (parent opens it)
-->
<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppModal  from '@/components/common/AppModal.vue'
import AppInput  from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import { ENTITY_TYPE_OPTIONS, YEAR_END_MONTH_OPTIONS } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  show:      { type: Boolean, default: false },
  community: { type: Object,  default: null },
})
const emit = defineEmits(['close', 'created', 'updated', 'login'])

const countryStore = useCountryStore()
const authStore = useAuthStore()
const { success } = useToast()

const loading = ref(false)
const error   = ref('')

const isEdit = computed(() => !!props.community?.id)
const modalTitle = computed(() => (isEdit.value ? (props.community?.name || 'Community').toUpperCase() : 'Add Community'))

const form = ref({
  name: '',
  code: '',
  entity_type: 'body_corporate',
  country: '',
  financial_year_end_month: '',
  merchant_number: '',
  pdf_passwords: false,
  userIds: [],
})

// Country is REQUIRED — Bold Mark operates in both South Africa and Botswana
// (WeConnectU is South-Africa-only, so it has no country field).
const countryOptions = Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({
  value: code,
  label: `${info.flag} ${info.name}`,
}))

// Year End is optional — a leading blank ("--") mirrors WeConnectU.
const yearEndOptions = [{ value: '', label: '--' }, ...YEAR_END_MONTH_OPTIONS]

// ── Assignable users (WeConnectU "Select Users") ──────────────────────
const users = ref([])
const userOptions = computed(() =>
  users.value.map(u => ({ value: u.id, label: u.email || u.name }))
)

async function fetchUsers() {
  try {
    const { data } = await api.get('/users', { params: { _per_page: 500 } })
    users.value = data.data ?? []
  } catch (e) {
    console.error('Failed to load users', e)
  }
}

// Populate the form for edit, or reset it for add. In edit mode we also fetch
// the full community so the assigned users (and latest values) prefill.
async function initForm() {
  error.value = ''
  if (isEdit.value) {
    const c = props.community
    form.value = {
      name: c.name ?? '',
      code: c.code ?? '',
      entity_type: c.entity_type ?? 'body_corporate',
      country: c.country ?? '',
      financial_year_end_month: c.financial_year_end_month != null ? String(c.financial_year_end_month) : '',
      merchant_number: c.merchant_number ?? '',
      pdf_passwords: !!c.pdf_passwords,
      userIds: Array.isArray(c.assigned_user_ids) ? [...c.assigned_user_ids] : [],
    }
    // Pull fresh detail (assigned_user_ids is only on the show endpoint).
    try {
      const { data } = await api.get(`/communities/${c.id}`)
      const full = data.data ?? data
      if (full) {
        form.value.merchant_number = full.merchant_number ?? form.value.merchant_number
        form.value.pdf_passwords   = !!full.pdf_passwords
        form.value.country         = full.country ?? form.value.country
        if (Array.isArray(full.assigned_user_ids)) form.value.userIds = [...full.assigned_user_ids]
      }
    } catch {
      // keep row-derived values
    }
  } else {
    // Pre-select the creator so they retain access to the new community, and
    // default the country to the active region.
    const creatorId = authStore.user?.id
    form.value = {
      name: '',
      code: '',
      entity_type: 'body_corporate',
      country: countryStore.activeCountry || '',
      financial_year_end_month: '',
      merchant_number: '',
      pdf_passwords: false,
      userIds: creatorId ? [creatorId] : [],
    }
  }
}

// Start fresh each time the modal opens.
watch(() => props.show, (open) => {
  if (open) {
    initForm()
    fetchUsers()
  }
})

function close() {
  emit('close')
}

// Assemble the WeConnectU field set shared by create + update.
function buildPayload() {
  const payload = {
    name: form.value.name.trim(),
    entity_type: form.value.entity_type,
    code: form.value.code?.trim() || null,
    financial_year_end_month: form.value.financial_year_end_month ? Number(form.value.financial_year_end_month) : null,
    merchant_number: form.value.merchant_number?.trim() || null,
    pdf_passwords: !!form.value.pdf_passwords,
    user_ids: form.value.userIds ?? [],
  }
  // Country is required in the form; send it plus the derived currency.
  if (form.value.country) {
    payload.country  = form.value.country
    payload.currency = countryStore.COUNTRY_MAP[form.value.country]?.currencyCode || null
  }
  return payload
}

const canSave = computed(() =>
  !!form.value.name.trim() && !!form.value.entity_type && !!form.value.country
)

async function submit() {
  if (!canSave.value) return
  loading.value = true
  error.value = ''
  try {
    if (isEdit.value) {
      const res = await api.put(`/communities/${props.community.id}`, buildPayload())
      success('Community saved.')
      emit('updated', res.data?.data ?? null)
      emit('close')
    } else {
      const res = await api.post('/communities', buildPayload())
      success('Community created successfully.')
      emit('created', res.data?.data ?? null)
      emit('close')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save community. Please try again.'
  } finally {
    loading.value = false
  }
}

function onLogin() {
  emit('login', props.community)
}
</script>

<template>
  <AppModal :title="modalTitle" size="lg" :show="show" @close="close">

    <div class="space-y-4">
      <!-- Community -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Community:</label>
        <AppInput v-model="form.name" placeholder="" />
      </div>

      <!-- Code -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Code (Max 5 Chars):</label>
        <AppInput v-model="form.code" maxlength="5" placeholder="" />
      </div>

      <!-- Entity Type -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Entity Type:</label>
        <AppSelect v-model="form.entity_type" :options="ENTITY_TYPE_OPTIONS" />
      </div>

      <!-- Country (Bold Mark operates in ZA + BW — required) -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Country:</label>
        <AppSelect v-model="form.country" :options="countryOptions" placeholder="Select country..." />
      </div>

      <!-- Year End -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Year End:</label>
        <AppSelect v-model="form.financial_year_end_month" :options="yearEndOptions" placeholder="--" />
      </div>

      <!-- Merchant Number -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Merchant Number:</label>
        <AppInput v-model="form.merchant_number" placeholder="" />
      </div>

      <!-- PDF Passwords -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <div class="flex items-center gap-2">
          <label class="text-sm text-muted-foreground">PDF Passwords:</label>
          <span class="group relative inline-flex">
            <span
              class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-primary text-white text-[10px] font-bold cursor-help"
            >i</span>
            <!-- WeConnectU-style hover tooltip: black bubble with a downward arrow -->
            <span
              role="tooltip"
              class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-56 -translate-x-1/2 rounded-md bg-black px-3 py-2 text-center text-xs font-semibold leading-snug text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100"
            >
              All statements and invoices sent via email will be Password Protected
              <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-black"></span>
            </span>
          </span>
        </div>
        <label class="inline-flex items-center">
          <input
            type="checkbox"
            v-model="form.pdf_passwords"
            class="w-4 h-4 rounded border-border text-accent focus:ring-accent cursor-pointer"
          />
        </label>
      </div>

      <!-- Select Users -->
      <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] sm:items-center gap-1.5 sm:gap-4">
        <label class="text-sm text-muted-foreground">Select Users:</label>
        <AppMultiSelect
          v-model="form.userIds"
          heading="Users"
          :options="userOptions"
          placeholder="Select users..."
        />
      </div>

      <!-- Error -->
      <p v-if="error" class="text-sm text-danger pt-1">{{ error }}</p>
    </div>

    <template #footer>
      <div :class="['w-full flex items-center', isEdit ? 'justify-between' : 'justify-end']">
        <AppButton variant="primary" :disabled="loading || !canSave" @click="submit">
          {{ loading ? 'Saving…' : 'Save' }}
        </AppButton>
        <AppButton v-if="isEdit" variant="secondary" @click="onLogin">
          Login
        </AppButton>
      </div>
    </template>

  </AppModal>
</template>
