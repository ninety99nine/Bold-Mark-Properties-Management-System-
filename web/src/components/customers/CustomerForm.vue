<template>
  <form class="pt-2 pb-6" @submit.prevent="submit">
    <!-- Core details — two columns -->
    <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
      <!-- Left column -->
      <div class="space-y-4">
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Customer Type <span class="text-red-500">*</span></label>
          <AppSelect v-model="form.customer_type" :options="CUSTOMER_TYPES" placeholder="Nothing selected" :error="errors.customer_type" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Name <span class="text-red-500">*</span></label>
          <AppInput v-model="form.full_name" :error="errors.full_name" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <div>
            <label :class="labelClass">Customer Code</label>
            <p class="text-xs italic text-muted-foreground">* Leave blank to auto generate</p>
          </div>
          <AppInput v-model="form.customer_code" :error="errors.customer_code" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Vat No.</label>
          <AppInput v-model="form.vat_no" :error="errors.vat_no" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">E-mail <span v-if="detailed" class="text-red-500">*</span></label>
          <AppInput v-model="form.email" type="email" :error="errors.email" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Tel No. <span v-if="detailed" class="text-red-500">*</span></label>
          <AppInput v-model="form.phone" :error="errors.phone" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">ID Number
            <AppTooltip text="Required for Debit Order collections" class="ml-0.5 align-middle">
              <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-[#2f6fb0] text-[9px] font-bold text-white">i</span>
            </AppTooltip>
          </label>
          <AppInput v-model="form.id_number" :error="errors.id_number" />
        </div>
        <div v-if="detailed" class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">PDF Password
            <AppTooltip text="All statements and invoices sent via email will be Password Protected" class="ml-0.5 align-middle">
              <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-[#2f6fb0] text-[9px] font-bold text-white">i</span>
            </AppTooltip>
          </label>
          <AppSelect v-model="form.pdf_password" :options="PDF_PASSWORD_OPTIONS" placeholder="Community Default" :error="errors.pdf_password" />
        </div>
      </div>

      <!-- Right column -->
      <div class="space-y-4">
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Customer Group</label>
          <AppMultiSelect v-model="form.customer_group_ids" :options="groupOptions" placeholder="Select Groups" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Reference
            <AppTooltip text="This is for your own reference purposes" class="ml-0.5 align-middle">
              <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-[#2f6fb0] text-[9px] font-bold text-white">i</span>
            </AppTooltip>
          </label>
          <AppInput v-model="form.reference" :error="errors.reference" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <div>
            <label :class="labelClass">OLD Customer Code</label>
            <p class="text-xs italic text-muted-foreground">* Only for reference purposes</p>
          </div>
          <AppInput v-model="form.old_customer_code" :error="errors.old_customer_code" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Country <span v-if="detailed" class="text-red-500">*</span></label>
          <AppSelect v-model="form.country" :options="COUNTRIES" placeholder="Nothing selected" :error="errors.country" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Alt E-mail</label>
          <AppInput v-model="form.alt_email" type="email" :error="errors.alt_email" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Alt No.</label>
          <AppInput v-model="form.alt_phone" :error="errors.alt_phone" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Payment Type</label>
          <AppSelect v-model="form.payment_type" :options="PAYMENT_TYPES" placeholder="Not Specified" :error="errors.payment_type" />
        </div>
      </div>
    </div>

    <!-- Address + Banking -->
    <div class="mt-10 grid gap-x-10 gap-y-4 md:grid-cols-2">
      <!-- Address Details -->
      <div>
        <h3 class="mb-4 text-lg font-semibold text-foreground">Address Details</h3>
        <div v-if="detailed" class="space-y-4">
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Address <span class="text-red-500">*</span></label>
            <AppInput v-model="form.address" :error="errors.address" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Address Line 2</label>
            <AppInput v-model="form.address_line_2" :error="errors.address_line_2" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Suburb <span class="text-red-500">*</span></label>
            <AppInput v-model="form.suburb" :error="errors.suburb" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Town <span class="text-red-500">*</span></label>
            <AppInput v-model="form.town" :error="errors.town" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Postal Code <span class="text-red-500">*</span></label>
            <AppInput v-model="form.postal_code" :error="errors.postal_code" />
          </div>
        </div>
        <div v-else class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Address</label>
          <AppInput v-model="form.address" type="textarea" :rows="6" :error="errors.address" />
        </div>
      </div>

      <!-- Banking Details -->
      <div>
        <h3 class="mb-4 text-lg font-semibold text-foreground">Banking Details</h3>
        <div class="space-y-4">
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Account Holder</label>
            <AppInput v-model="form.account_holder" :error="errors.account_holder" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Bank Name</label>
            <AppSelect v-model="form.bank_name" :options="BANKS" placeholder="Nothing selected" :error="errors.bank_name" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Account Type</label>
            <AppSelect v-model="form.account_type" :options="ACCOUNT_TYPES" placeholder="Nothing selected" :error="errors.account_type" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Account Number</label>
            <AppInput v-model="form.account_number" :error="errors.account_number" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Branch Code</label>
            <AppInput v-model="form.branch_code" :error="errors.branch_code" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Branch Name</label>
            <AppInput v-model="form.branch_name" :error="errors.branch_name" />
          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="mt-8">
      <label class="mb-2 block text-lg font-semibold text-foreground">Notes</label>
      <AppInput v-model="form.notes" type="textarea" :rows="4" :error="errors.notes" />
    </div>

    <!-- Actions -->
    <div class="mt-6 flex justify-end gap-3">
      <AppButton v-if="customer" type="button" variant="outline" @click="$emit('cancel')">Cancel</AppButton>
      <AppButton type="submit" variant="secondary" :loading="saving">
        {{ customer ? 'Update Customer' : 'Create Customer' }}
      </AppButton>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import AppTooltip from '@/components/common/AppTooltip.vue'
import { CUSTOMER_TYPES, PAYMENT_TYPES, BANKS, ACCOUNT_TYPES, COUNTRIES, PDF_PASSWORD_OPTIONS } from '@/utils/customerOptions'

const props = defineProps({
  communityId:  { type: String, required: true },
  groupOptions: { type: Array,  default: () => [] },
  customer:     { type: Object, default: null },
  // Full WeConnectU customer-detail form (adds PDF Password + full address, required markers).
  detailed:     { type: Boolean, default: false },
})
const emit = defineEmits(['saved', 'cancel'])

const { success, error: toastError } = useToast()

const labelClass = 'pt-2 text-sm font-medium text-foreground'

const saving = ref(false)
const errors = reactive({})

function blankForm() {
  return {
    customer_type: '',
    full_name: '',
    customer_code: '',
    vat_no: '',
    email: '',
    phone: '',
    id_number: '',
    customer_group_ids: [],
    reference: '',
    old_customer_code: '',
    country: 'South Africa',
    alt_email: '',
    alt_phone: '',
    payment_type: 'not_specified',
    pdf_password: '',
    address: '',
    address_line_2: '',
    suburb: '',
    town: '',
    postal_code: '',
    account_holder: '',
    bank_name: '',
    account_type: '',
    account_number: '',
    branch_code: '',
    branch_name: '',
    notes: '',
  }
}

const form = reactive(blankForm())

function hydrate(customer) {
  Object.assign(form, blankForm())
  if (!customer) return
  for (const key of Object.keys(form)) {
    if (key === 'customer_group_ids') {
      form.customer_group_ids = (customer.customer_groups || []).map(g => g.id)
    } else if (customer[key] !== undefined && customer[key] !== null) {
      form[key] = customer[key]
    }
  }
}

watch(() => props.customer, (c) => hydrate(c), { immediate: true })

async function submit() {
  saving.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  const payload = { customer_group_ids: form.customer_group_ids }
  for (const [key, value] of Object.entries(form)) {
    if (key === 'customer_group_ids') continue
    if (value === '' || value === null) continue
    payload[key] = value
  }

  try {
    if (props.customer) {
      await api.put(`/communities/${props.communityId}/customers/${props.customer.id}`, payload)
      success('Customer updated')
    } else {
      await api.post(`/communities/${props.communityId}/customers`, payload)
      success('Customer created')
    }
    if (!props.customer) Object.assign(form, blankForm())
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      const bag = e.response.data.errors || {}
      for (const [field, messages] of Object.entries(bag)) {
        errors[field] = Array.isArray(messages) ? messages[0] : messages
      }
      toastError('Please fix the highlighted fields.')
    } else {
      toastError(e.response?.data?.message || 'Could not save the customer.')
    }
  } finally {
    saving.value = false
  }
}
</script>
