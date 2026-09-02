<template>
  <form class="pt-2 pb-6" @submit.prevent="submit">
    <!-- Core details — two columns -->
    <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
      <!-- Left column -->
      <div class="space-y-4">
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Supplier Type <span class="text-red-500">*</span></label>
          <AppSelect v-model="form.supplier_type" :options="options.supplier_types" placeholder="Nothing selected" :error="errors.supplier_type" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Name <span class="text-red-500">*</span></label>
          <AppInput v-model="form.name" :error="errors.name" />
        </div>
        <div v-if="supplier" class="grid grid-cols-[150px_1fr] items-start gap-3">
          <div>
            <label :class="labelClass">Supplier Code</label>
            <p class="text-xs italic text-muted-foreground">* Leave blank to auto generate</p>
          </div>
          <AppInput v-model="form.supplier_code" :error="errors.supplier_code" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">E-mail <span class="text-red-500">*</span></label>
          <AppInput v-model="form.email" type="email" :error="errors.email" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Tel No. <span class="text-red-500">*</span></label>
          <AppInput v-model="form.phone" :error="errors.phone" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Vat No.</label>
          <AppInput v-model="form.vat_number" :error="errors.vat_number" />
        </div>
      </div>

      <!-- Right column -->
      <div class="space-y-4">
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Supplier Group</label>
          <AppSelect v-model="form.supplier_group_id" :options="groupSelectOptions" placeholder="No Group" :error="errors.supplier_group_id" />
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
          <label :class="labelClass">Alt E-mail</label>
          <AppInput v-model="form.alt_email" type="email" :error="errors.alt_email" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Alt No.</label>
          <AppInput v-model="form.alt_phone" :error="errors.alt_phone" />
        </div>
        <div class="grid grid-cols-[150px_1fr] items-start gap-3">
          <label :class="labelClass">Payment Type</label>
          <AppSelect v-model="form.payment_type" :options="options.payment_types" placeholder="Not Specified" :error="errors.payment_type" />
        </div>
      </div>
    </div>

    <!-- Address + Banking -->
    <div class="mt-10 grid gap-x-10 gap-y-4 md:grid-cols-2">
      <!-- Address Details -->
      <div>
        <h3 class="mb-4 text-lg font-semibold text-foreground">Address Details</h3>
        <div class="space-y-4">
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Address Line 1 <span class="text-red-500">*</span></label>
            <AppInput v-model="form.address_line_1" :error="errors.address_line_1" />
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
      </div>

      <!-- Banking Details -->
      <div>
        <h3 class="mb-4 text-lg font-semibold text-foreground">Banking Details</h3>
        <div class="space-y-4">
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Bank Name</label>
            <AppSelect v-model="form.bank_name" :options="options.banks" placeholder="Nothing selected" :error="errors.bank_name" />
          </div>
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Account Type</label>
            <AppSelect v-model="form.account_type" :options="options.account_types" placeholder="Nothing selected" :error="errors.account_type" />
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
          <div class="grid grid-cols-[150px_1fr] items-start gap-3">
            <label :class="labelClass">Supplier Account Confirmation Document</label>
            <div>
              <input ref="confirmInput" type="file" class="hidden" @change="onConfirmSelected" />
              <AppButton type="button" variant="outline" @click="confirmInput?.click()">
                <span class="inline-flex items-center gap-2"><IconUpload class="h-4 w-4" /> {{ confirmFile ? confirmFile.name : 'Select Document' }}</span>
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex justify-end gap-3">
      <AppButton v-if="supplier" type="button" variant="outline" @click="$emit('cancel')">Cancel</AppButton>
      <AppButton type="submit" variant="secondary" :loading="saving">
        {{ supplier ? 'Update Supplier' : 'Create Supplier' }}
      </AppButton>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, computed, watch, h } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppTooltip from '@/components/common/AppTooltip.vue'

const props = defineProps({
  communityId: { type: String, required: true },
  // { supplier_types, payment_types, account_types, banks, statuses, groups }
  options:     { type: Object, default: () => ({}) },
  supplier:    { type: Object, default: null },
})
const emit = defineEmits(['saved', 'cancel'])

const { success, error: toastError } = useToast()

const IconUpload = (props_, { attrs }) => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, [h('path', { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4' }), h('path', { d: 'M17 8l-5-5-5 5' }), h('path', { d: 'M12 3v12' })])

const labelClass = 'pt-2 text-sm font-medium text-foreground'

const saving = ref(false)
const errors = reactive({})

const confirmInput = ref(null)
const confirmFile  = ref(null)

const groupSelectOptions = computed(() => [
  { value: '', label: 'No Group' },
  ...((props.options.groups || []).map(g => ({ value: g.id, label: g.name }))),
])

function blankForm() {
  return {
    supplier_type: '',
    name: '',
    supplier_code: '',
    email: '',
    phone: '',
    vat_number: '',
    supplier_group_id: '',
    reference: '',
    alt_email: '',
    alt_phone: '',
    payment_type: '',
    address_line_1: '',
    address_line_2: '',
    suburb: '',
    town: '',
    postal_code: '',
    bank_name: '',
    account_type: '',
    account_number: '',
    branch_code: '',
    branch_name: '',
  }
}

const form = reactive(blankForm())

function hydrate(supplier) {
  Object.assign(form, blankForm())
  if (!supplier) return
  for (const key of Object.keys(form)) {
    if (supplier[key] !== undefined && supplier[key] !== null) {
      form[key] = supplier[key]
    }
  }
}

watch(() => props.supplier, (s) => hydrate(s), { immediate: true })

function onConfirmSelected(event) {
  const f = event.target.files?.[0]
  if (f) confirmFile.value = f
}

async function submit() {
  saving.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  const payload = { community_id: props.communityId }
  for (const [key, value] of Object.entries(form)) {
    if (value === '' || value === null) continue
    payload[key] = value
  }

  try {
    let savedSupplier
    if (props.supplier) {
      const { data } = await api.put(`/suppliers/${props.supplier.id}`, payload)
      savedSupplier = data.data ?? data
      success('Supplier updated')
    } else {
      const { data } = await api.post('/suppliers', payload)
      savedSupplier = data.data ?? data
      success('Supplier created')
    }

    // Upload the account confirmation document as a supplier document (if selected).
    if (confirmFile.value && savedSupplier?.id) {
      try {
        const fd = new FormData()
        fd.append('file', confirmFile.value)
        fd.append('name', 'Supplier Account Confirmation')
        await api.post(`/suppliers/${savedSupplier.id}/documents`, fd)
      } catch (e) { /* non-fatal: supplier already saved */ }
    }

    if (!props.supplier) {
      Object.assign(form, blankForm())
      confirmFile.value = null
      if (confirmInput.value) confirmInput.value.value = ''
    }
    emit('saved', savedSupplier)
  } catch (e) {
    if (e.response?.status === 422) {
      const bag = e.response.data.errors || {}
      for (const [field, messages] of Object.entries(bag)) {
        errors[field] = Array.isArray(messages) ? messages[0] : messages
      }
      toastError('Please fix the highlighted fields.')
    } else {
      toastError(e.response?.data?.message || 'Could not save the supplier.')
    }
  } finally {
    saving.value = false
  }
}
</script>
