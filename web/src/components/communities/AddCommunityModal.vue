<!--
  AddCommunityModal — the two-step "Add New Community" flow, extracted so it can
  be opened in place from anywhere (Dashboard, Communities page, …) without
  routing. Manages its own form state; resets each time it is opened.

  Props:
    show — controls visibility (passed to AppModal)
  Emits:
    close            — request to close (parent sets show=false)
    created(community) — a community was created; payload is the API resource
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
import { ENTITY_TYPE_OPTIONS, YEAR_END_MONTH_OPTIONS, isLevyBilled, isRentBilled } from '@/utils/communityEntityType'
import { useCountryStore } from '@/stores/country'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'created'])

const countryStore = useCountryStore()
const authStore = useAuthStore()
const { success } = useToast()

const loading = ref(false)
const error   = ref('')
const step    = ref(1)

const form = ref({
  name: '',
  code: '',
  entity_type: '',
  financial_year_end_month: '',
  country: '',
  address: '',
  userIds: [],
  adminFund: '',
  reserveFund: '',
  csosLevy: '',
  defaultRent: '',
  billingDay: '1',
  paymentTermsDays: '30',
})

const countryOptions = Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({
  value: code,
  label: `${info.flag} ${info.name}`,
}))

// Year End is optional — a leading blank ("--") lets it be cleared.
const yearEndOptions = [{ value: '', label: '--' }, ...YEAR_END_MONTH_OPTIONS]

// ── Assignable users (WeConnectU "Select Users") ──────────────────────
const users = ref([])
const userOptions = computed(() =>
  users.value.map(u => ({ value: u.id, label: u.name || u.email, sublabel: u.name ? u.email : null }))
)

async function fetchUsers() {
  try {
    const { data } = await api.get('/users', { params: { _per_page: 500 } })
    users.value = data.data ?? []
  } catch (e) {
    console.error('Failed to load users', e)
  }
}

const showLevy = computed(() => isLevyBilled(form.value.entity_type))
const showRent = computed(() => isRentBilled(form.value.entity_type))

// Currency prefix based on the form's selected country (not the global country)
const currencySymbol = computed(() => {
  const code = form.value.country
  return code ? (countryStore.COUNTRY_MAP[code]?.symbol || countryStore.currencySymbol) : countryStore.currencySymbol
})

function resetForm() {
  // Pre-select the creator so they retain access to the new community.
  const creatorId = authStore.user?.id
  form.value = { name: '', code: '', entity_type: '', financial_year_end_month: '', country: countryStore.activeCountry || '', address: '', userIds: creatorId ? [creatorId] : [], adminFund: '', reserveFund: '', csosLevy: '', defaultRent: '', billingDay: '1', paymentTermsDays: '30' }
  error.value = ''
  step.value = 1
}

// Start fresh each time the modal opens.
watch(() => props.show, (open) => {
  if (open) {
    resetForm()
    fetchUsers()
  }
})

function close() {
  emit('close')
}

function goToStep2() {
  if (!form.value.name || !form.value.entity_type || !form.value.country) return
  step.value = 2
}

async function submit() {
  if (!form.value.name || !form.value.entity_type) return
  loading.value = true
  error.value = ''
  try {
    const payload = {
      name: form.value.name,
      entity_type: form.value.entity_type,
    }
    if (form.value.code?.trim())     payload.code                     = form.value.code.trim()
    if (form.value.financial_year_end_month) payload.financial_year_end_month = Number(form.value.financial_year_end_month)
    if (form.value.address)          payload.address             = form.value.address
    if (form.value.userIds?.length)  payload.user_ids            = form.value.userIds
    if (form.value.adminFund)        payload.admin_fund_amount   = Number(form.value.adminFund)
    if (form.value.reserveFund)      payload.reserve_fund_amount = Number(form.value.reserveFund)
    if (form.value.csosLevy)         payload.csos_levy_amount    = Number(form.value.csosLevy)
    if (form.value.defaultRent)      payload.default_rent_amount = Number(form.value.defaultRent)
    if (form.value.billingDay)       payload.billing_day         = Number(form.value.billingDay)
    if (form.value.paymentTermsDays) payload.payment_terms_days  = Number(form.value.paymentTermsDays)
    // Set country + auto-derive currency
    const communityCountry = form.value.country || countryStore.activeCountry
    if (communityCountry) {
      payload.country  = communityCountry
      payload.currency = countryStore.COUNTRY_MAP[communityCountry]?.currencyCode || null
    }

    const res = await api.post('/communities', payload)
    success('Community created successfully.')
    emit('created', res.data?.data ?? null)
    emit('close')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create community. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppModal title="Add New Community" size="md" :show="show" @close="close">

    <!-- Step indicator -->
    <div class="flex items-center gap-3 mb-5 pb-5 border-b border-border">
      <div class="flex items-center gap-2">
        <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-colors', step === 1 ? 'bg-primary text-white' : 'bg-success text-white']">
          <svg v-if="step > 1" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>1</span>
        </span>
        <span :class="['text-xs font-medium', step === 1 ? 'text-foreground' : 'text-muted-foreground']">Basic Info</span>
      </div>
      <div class="flex-1 h-px bg-border"></div>
      <div class="flex items-center gap-2">
        <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-colors', step === 2 ? 'bg-primary text-white' : 'bg-muted text-muted-foreground']">2</span>
        <span :class="['text-xs font-medium', step === 2 ? 'text-foreground' : 'text-muted-foreground']">Financial Settings</span>
      </div>
    </div>

    <!-- Step 1: Basic Info — field order mirrors WeConnectU (Name, Code, Entity Type, Year End) -->
    <div v-if="step === 1" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <AppInput
            label="Community Name"
            v-model="form.name"
            placeholder="e.g. Crystal Mews Body Corporate"
            required
          />
        </div>
        <AppInput
          label="Community Code"
          v-model="form.code"
          placeholder="e.g. CRYS"
          hint="Max 5 characters"
          maxlength="10"
        />
        <AppSelect
          v-model="form.entity_type"
          label="Entity Type"
          :options="ENTITY_TYPE_OPTIONS"
          placeholder="Select entity type..."
          required
        />
        <AppSelect
          v-model="form.financial_year_end_month"
          label="Year End"
          :options="yearEndOptions"
          placeholder="--"
        />
        <AppSelect
          v-model="form.country"
          label="Country"
          :options="countryOptions"
          placeholder="Select country..."
          required
        />
        <div class="col-span-2">
          <AppInput
            label="Address"
            v-model="form.address"
            placeholder="Full street address"
          />
        </div>
        <div class="col-span-2">
          <AppMultiSelect
            v-model="form.userIds"
            label="Select Users"
            heading="Users"
            :options="userOptions"
            placeholder="Select users..."
          />
        </div>
      </div>
    </div>

    <!-- Step 2: Financial Settings -->
    <div v-if="step === 2" class="space-y-4">

      <template v-if="showLevy">
        <div class="grid grid-cols-2 gap-4">
          <AppInput
            label="Admin Fund Budget"
            type="number"
            v-model="form.adminFund"
            placeholder="0.00"
            :prefix="currencySymbol"
            hint="Monthly admin levy budget"
            :min="0"
            :max="9999999999.99"
          />
          <AppInput
            label="Reserve Fund Budget"
            type="number"
            v-model="form.reserveFund"
            placeholder="0.00"
            :prefix="currencySymbol"
            hint="Monthly reserve fund (STSM Act)"
            :min="0"
            :max="9999999999.99"
          />
        </div>
        <AppInput
          v-if="form.country === 'ZA'"
          label="CSOS Levy (per unit)"
          type="number"
          v-model="form.csosLevy"
          placeholder="0.00"
          :prefix="currencySymbol"
          hint="Flat monthly CSOS government levy charged per unit"
          :min="0"
          :max="99999999.99"
        />
      </template>

      <AppInput
        v-if="showRent"
        label="Default Rent Amount"
        type="number"
        v-model="form.defaultRent"
        placeholder="0.00"
        :prefix="currencySymbol"
        hint="Default monthly rent applied to new units"
        :min="0"
        :max="9999999999.99"
      />

      <div v-if="!showLevy && !showRent" class="py-2 text-center text-sm text-muted-foreground">
        No levy or rent amounts needed for this community type.
      </div>

      <!-- Billing settings — always shown -->
      <div class="grid grid-cols-2 gap-4 pt-1">
        <AppInput
          label="Billing Day"
          type="number"
          v-model="form.billingDay"
          placeholder="1"
          :min="1"
          :max="28"
          hint="Day of month invoices are generated (1–28)"
        />
        <AppInput
          label="Payment Terms (days)"
          type="number"
          v-model="form.paymentTermsDays"
          placeholder="30"
          :min="1"
          :max="365"
          hint="Days before invoice becomes overdue"
        />
      </div>

      <!-- Error -->
      <p v-if="error" class="text-sm text-danger">{{ error }}</p>

    </div>

    <template #footer>
      <template v-if="step === 1">
        <AppButton variant="outline" @click="close">Cancel</AppButton>
        <AppButton variant="primary" :disabled="!form.name || !form.entity_type || !form.country" @click="goToStep2">
          Next
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </AppButton>
      </template>
      <template v-else>
        <AppButton variant="outline" @click="step = 1">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          Back
        </AppButton>
        <AppButton variant="primary" :disabled="loading" @click="submit">
          {{ loading ? 'Creating…' : 'Create Community' }}
        </AppButton>
      </template>
    </template>

  </AppModal>
</template>
