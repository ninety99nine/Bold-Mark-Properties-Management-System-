<!--
  CompanyDetailsPage — global Settings → Company ("Company Details"), a
  full-width standalone page (WeConnectU parity): no secondary settings sidebar.
  Details + Company Bank Details on the left, Logos (header logo + top-left icon)
  on the right. Saves to /organization and refreshes the org store so the topbar
  logo and communication assets update immediately.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCountryStore } from '@/stores/country'
import { useOrganizationStore } from '@/stores/organization'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import AppButton from '@/components/common/AppButton.vue'

const { success: toastSuccess, error: toastError } = useToast()
const countryStore = useCountryStore()
const organizationStore = useOrganizationStore()

const company = ref({
  name: '', slogan: '', regNo: '', transferClearanceFee: '',
  email: '', outgoingEmail: '', phone: '', country: 'ZA',
  bankAccountHolder: '', bankName: '', bankAccountType: '',
  bankAccountNumber: '', bankBranchCode: '', bankBranchName: '',
})
const companyLoading = ref(false)
const loading = ref(true)

// ── Email validation — never allow an invalid email to be submitted ──────
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const outgoingEmailError = ref(null)
function validateOutgoingEmail() {
  const v = (company.value.outgoingEmail ?? '').trim()
  outgoingEmailError.value = (v && !EMAIL_RE.test(v)) ? 'Please enter a valid email address.' : null
  return !outgoingEmailError.value
}
// Clear the error live once the address becomes valid.
watch(() => company.value.outgoingEmail, () => { if (outgoingEmailError.value) validateOutgoingEmail() })

const companyLogoUrl = ref(null)
const companyIconUrl = ref(null)
const logoInput = ref(null)
const iconInput = ref(null)
const pendingLogo = ref(null)
const pendingIcon = ref(null)
const removeLogoFlag = ref(false)
const removeIconFlag = ref(false)

const BANK_OPTS = [
  'ABSA', 'African Bank', 'Bank Windhoek', 'Bidvest Bank', 'Capitec Bank',
  'Capitec Business', 'Discovery Bank', 'First National Bank', 'Investec Private Bank',
  'Mercantile Bank', 'Merchant Bank', 'Nedbank', 'Other', 'PayFast', 'Sasfin Bank Ltd',
  'Standard Bank', 'Standard Chartered Bank', 'Tyme Bank',
].map(b => ({ value: b, label: b }))
const ACCOUNT_TYPE_OPTS = ['Current', 'Savings', 'Investment'].map(t => ({ value: t, label: t }))

const COUNTRY_OPTS = countryStore.COUNTRY_MAP
  ? Object.entries(countryStore.COUNTRY_MAP).map(([code, info]) => ({ value: code, label: `${info.flag} ${info.name} (${code})` }))
  : []
const companyCurrencyLabel = computed(() => {
  const info = countryStore.COUNTRY_MAP?.[company.value.country]
  return info ? `${info.symbol} — ${info.currencyCode}` : '—'
})

function onLogoSelect(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  pendingLogo.value = f; companyLogoUrl.value = URL.createObjectURL(f); removeLogoFlag.value = false
  saveCompany()
}
function onIconSelect(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  pendingIcon.value = f; companyIconUrl.value = URL.createObjectURL(f); removeIconFlag.value = false
  saveCompany()
}
function removeLogo() { pendingLogo.value = null; companyLogoUrl.value = null; removeLogoFlag.value = true; saveCompany() }
function removeIcon() { pendingIcon.value = null; companyIconUrl.value = null; removeIconFlag.value = true; saveCompany() }

// Drag-and-drop — persists immediately, like the Communications settings.
const logoDragOver = ref(false)
const iconDragOver = ref(false)
function onLogoDrop(e) {
  logoDragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (!f || !/^image\/(png|jpe?g)$/.test(f.type)) return
  pendingLogo.value = f; companyLogoUrl.value = URL.createObjectURL(f); removeLogoFlag.value = false
  saveCompany()
}
function onIconDrop(e) {
  iconDragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (!f || !/^image\/(png|jpe?g)$/.test(f.type)) return
  pendingIcon.value = f; companyIconUrl.value = URL.createObjectURL(f); removeIconFlag.value = false
  saveCompany()
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/organization')
    const o = data.data ?? data
    company.value = {
      name: o.company_name ?? '', slogan: o.company_slogan ?? '', regNo: o.company_reg_no ?? '',
      transferClearanceFee: o.transfer_clearance_fee ?? '', email: o.contact_email ?? '',
      outgoingEmail: o.outgoing_email ?? '', phone: o.contact_phone ?? '', country: o.country ?? 'ZA',
      bankAccountHolder: o.bank_account_holder ?? '', bankName: o.bank_name ?? '',
      bankAccountType: o.bank_account_type ?? '', bankAccountNumber: o.bank_account_number ?? '',
      bankBranchCode: o.bank_branch_code ?? '', bankBranchName: o.bank_branch_name ?? '',
    }
    companyLogoUrl.value = o.logo_url ?? null
    companyIconUrl.value = o.icon_url ?? null
  } catch { /* keep blanks */ } finally {
    loading.value = false
  }
}

async function saveCompany() {
  if (companyLoading.value) return
  if (!validateOutgoingEmail()) { toastError('Please enter a valid outgoing email address.'); return }
  companyLoading.value = true
  try {
    const fd = new FormData()
    fd.append('_method', 'PUT')
    fd.append('company_name', company.value.name ?? '')
    fd.append('company_reg_no', company.value.regNo ?? '')
    fd.append('transfer_clearance_fee', company.value.transferClearanceFee ?? '')
    fd.append('contact_email', company.value.email ?? '')
    fd.append('outgoing_email', company.value.outgoingEmail ?? '')
    fd.append('contact_phone', company.value.phone ?? '')
    fd.append('country', company.value.country ?? 'ZA')
    fd.append('bank_account_holder', company.value.bankAccountHolder ?? '')
    fd.append('bank_name', company.value.bankName ?? '')
    fd.append('bank_account_type', company.value.bankAccountType ?? '')
    fd.append('bank_account_number', company.value.bankAccountNumber ?? '')
    fd.append('bank_branch_code', company.value.bankBranchCode ?? '')
    fd.append('bank_branch_name', company.value.bankBranchName ?? '')
    if (pendingLogo.value) fd.append('logo', pendingLogo.value)
    if (pendingIcon.value) fd.append('icon', pendingIcon.value)
    if (removeLogoFlag.value) fd.append('remove_logo', '1')
    if (removeIconFlag.value) fd.append('remove_icon', '1')

    const { data } = await api.post('/organization', fd)
    const org = data.data ?? data
    companyLogoUrl.value = org.logo_url
    companyIconUrl.value = org.icon_url
    pendingLogo.value = null
    pendingIcon.value = null
    removeLogoFlag.value = false
    removeIconFlag.value = false
    organizationStore.apply(org)
    toastSuccess('Company details saved.')
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Something went wrong. Please try again.')
  } finally {
    companyLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="pb-8">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="font-body font-bold text-2xl text-foreground">Company Details</h1>
      <p class="text-sm text-muted-foreground mt-0.5">Company information, banking details and logos used across the app and communications</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Left: Details + Company Bank Details -->
      <div class="rounded-lg border bg-card shadow-sm p-6 space-y-6">
        <div>
          <h3 class="text-base font-semibold text-foreground border-b border-border pb-3 mb-4">Details</h3>
          <div class="space-y-4">
            <AppInput v-model="company.name" label="Reseller Name" placeholder="e.g. Bold Mark Properties" />
            <AppInput v-model="company.regNo" label="Company Reg No." placeholder="2021/147096/07" />
            <AppInput v-model="company.transferClearanceFee" type="number" step="0.01" label="Transfer Clearance Fee" placeholder="1100.00" />
            <AppInput v-model="company.phone" type="tel" label="Telephone No." placeholder="010 824 9671" />
            <AppInput v-model="company.outgoingEmail" type="email" label="Outgoing Email" placeholder="noreply@boldmarkprop.co.za" :error="outgoingEmailError" @blur="validateOutgoingEmail" />
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-fg">Country</label>
              <AppSelect v-model="company.country" :options="COUNTRY_OPTS" />
              <p class="text-xs text-muted-foreground">Default country for new communities · Currency: {{ companyCurrencyLabel }}</p>
            </div>
          </div>
        </div>

        <div>
          <h3 class="text-base font-semibold text-foreground border-b border-border pb-3 mb-4">Company Bank Details</h3>
          <div class="space-y-4">
            <AppInput v-model="company.bankAccountHolder" label="Account Holder" placeholder="Bold Mark Properties" />
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-fg">Bank Name</label>
              <AppSelect v-model="company.bankName" :options="BANK_OPTS" placeholder="Select bank" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-fg">Account Type</label>
              <AppSelect v-model="company.bankAccountType" :options="ACCOUNT_TYPE_OPTS" placeholder="Select type" />
            </div>
            <AppInput v-model="company.bankAccountNumber" label="Account Number" placeholder="201656302" />
            <AppInput v-model="company.bankBranchCode" label="Branch Code" placeholder="004305" />
            <AppInput v-model="company.bankBranchName" label="Branch Name" placeholder="Rosebank" />
          </div>
          <div class="pt-4">
            <AppButton variant="primary" :loading="companyLoading" @click="saveCompany">Save details</AppButton>
          </div>
        </div>
      </div>

      <!-- Right: Logos -->
      <div class="rounded-lg border bg-card shadow-sm p-6 space-y-6">
        <div>
          <h3 class="text-base font-semibold text-foreground mb-2">Logos</h3>
          <p class="text-sm text-muted-foreground">Please select a file for your logo.</p>
          <p class="text-sm text-muted-foreground mt-2">The file should be in <strong>PNG or JPG</strong> format and will be cropped to fit where necessary.</p>
          <p class="text-sm text-muted-foreground mt-2">As a guideline, the logo should be wider than taller, for example, and optimally <strong>600 x 200</strong> if possible. It is not a strict rule, but more of a guideline.</p>
        </div>

        <hr class="border-border" />

        <div>
          <input ref="logoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onLogoSelect" />

          <!-- Empty: dashed drop zone (matches Communications) -->
          <div
            v-if="!companyLogoUrl"
            @click="logoInput?.click()"
            @dragover.prevent="logoDragOver = true"
            @dragleave.prevent="logoDragOver = false"
            @drop.prevent="onLogoDrop"
            :class="['group cursor-pointer rounded-md border-2 border-dashed px-4 py-10 text-center transition-colors', logoDragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
          >
            <span class="text-sm text-accent group-hover:underline">Click to upload logo</span>
            <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop a PNG/JPG here (600 x 200)</p>
          </div>

          <!-- Uploaded: preview -->
          <div
            v-else
            @dragover.prevent="logoDragOver = true"
            @dragleave.prevent="logoDragOver = false"
            @drop.prevent="onLogoDrop"
            :class="['h-40 flex items-center justify-center overflow-hidden rounded-md border transition-colors', logoDragOver ? 'border-2 border-accent bg-accent/5' : 'border-border bg-muted/40']"
          >
            <img :src="companyLogoUrl" alt="Header logo" class="max-h-full max-w-full object-contain" />
          </div>

          <!-- Label (always shown) + remove when present -->
          <div class="flex items-center justify-between mt-3">
            <div>
              <p class="text-sm font-semibold text-foreground">Header Logo</p>
              <p class="text-xs italic text-muted-foreground">Preferably Hi-Res</p>
            </div>
            <button v-if="companyLogoUrl" type="button" class="inline-flex items-center gap-1.5 text-sm text-destructive hover:underline" @click="removeLogo">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Remove logo
            </button>
          </div>
        </div>

        <hr class="border-border" />

        <div>
          <input ref="iconInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onIconSelect" />

          <!-- Empty: small dashed drop zone -->
          <div
            v-if="!companyIconUrl"
            @click="iconInput?.click()"
            @dragover.prevent="iconDragOver = true"
            @dragleave.prevent="iconDragOver = false"
            @drop.prevent="onIconDrop"
            :class="['group flex h-28 w-44 cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed px-3 text-center transition-colors', iconDragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
          >
            <span class="text-sm text-accent group-hover:underline">Click to upload icon</span>
            <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop (55×55)</p>
          </div>

          <!-- Uploaded: small preview -->
          <div
            v-else
            @dragover.prevent="iconDragOver = true"
            @dragleave.prevent="iconDragOver = false"
            @drop.prevent="onIconDrop"
            :class="['w-16 h-16 flex items-center justify-center overflow-hidden rounded-md border transition-colors', iconDragOver ? 'border-2 border-accent bg-accent/5' : 'border-border bg-muted/40']"
          >
            <img :src="companyIconUrl" alt="Icon" class="max-h-full max-w-full object-contain" />
          </div>

          <!-- Label (always shown) + remove when present -->
          <div class="flex items-center justify-between mt-3">
            <div>
              <p class="text-sm font-semibold text-foreground">Top Left Icon</p>
              <p class="text-xs italic text-muted-foreground">(55×55)</p>
            </div>
            <button v-if="companyIconUrl" type="button" class="inline-flex items-center gap-1.5 text-sm text-destructive hover:underline" @click="removeIcon">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Remove icon
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
