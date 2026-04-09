<script setup>
import { ref, watch } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppSelect from '@/components/common/AppSelect.vue'

const activeTab = ref('account')

// Account — Profile
const profile = ref({
  fullName: 'Justin Sobhee',
  email: 'justin@boldmarkprop.co.za',
  role: 'Company Admin',
  phone: '+27 82 555 1234',
})

// Account — Password
const password = ref({
  current: '',
  newPass: '',
  confirm: '',
})

// Company settings
const company = ref({
  name: 'Bold Mark Properties',
  slogan: 'Moving People Forward',
  email: 'info@boldmarkprop.co.za',
  country: 'ZA',
  phone: '+27 10 442 0012',
  currency: 'ZAR',
  primaryColor: '#1F3A5C',
  secondaryColor: '#D89B4B',
})

const DEFAULT_PRIMARY   = '#1F3A5C'
const DEFAULT_SECONDARY = '#D89B4B'

// Select options
const COUNTRY_OPTS = [
  { value: 'ZA', label: 'South Africa (ZA)' },
  { value: 'BW', label: 'Botswana (BW)'     },
  { value: 'NA', label: 'Namibia (NA)'      },
  { value: 'MZ', label: 'Mozambique (MZ)'   },
  { value: 'KE', label: 'Kenya (KE)'        },
]
const CURRENCY_OPTS = [
  { value: 'ZAR', label: 'R — South African Rand (ZAR)'  },
  { value: 'BWP', label: 'P — Botswana Pula (BWP)'       },
  { value: 'USD', label: '$ — US Dollar (USD)'           },
  { value: 'EUR', label: '€ — Euro (EUR)'                },
  { value: 'GBP', label: '£ — British Pound (GBP)'      },
  { value: 'NAD', label: 'N$ — Namibian Dollar (NAD)'   },
]
const APPLIES_TO_OPTS = [
  { value: 'Owner',  label: 'Owner'  },
  { value: 'Tenant', label: 'Tenant' },
  { value: 'Either', label: 'Either' },
]
const RECURRING_OPTS = [
  { value: 'Yes', label: 'Yes — Monthly' },
  { value: 'No',  label: 'No — Ad-hoc'  },
]

// Charge types
const chargeTypes = ref([
  { id: 1,  name: 'Levy',                  description: 'Regular monthly body corporate levy',        code: 'LEVY',                 appliesTo: 'Owner',  recurring: 'Monthly', status: 'Active',   isSystem: true  },
  { id: 2,  name: 'Rent',                  description: 'Regular monthly rental payment',             code: 'RENT',                 appliesTo: 'Tenant', recurring: 'Monthly', status: 'Active',   isSystem: true  },
  { id: 3,  name: 'Special Levy',          description: 'Once-off body corporate charge',             code: 'SPECIAL_LEVY',         appliesTo: 'Owner',  recurring: 'Ad-hoc',  status: 'Active',   isSystem: false },
  { id: 4,  name: 'Water Recovery',        description: 'Metered water billed per unit',              code: 'WATER_RECOVERY',       appliesTo: 'Either', recurring: 'Monthly', status: 'Active',   isSystem: false },
  { id: 5,  name: 'Electricity Recovery',  description: 'Metered electricity billed per unit',        code: 'ELECTRICITY_RECOVERY', appliesTo: 'Either', recurring: 'Monthly', status: 'Active',   isSystem: false },
  { id: 6,  name: 'Late Payment Interest', description: 'Interest charged on overdue balances',       code: 'LATE_INTEREST',        appliesTo: 'Either', recurring: 'Ad-hoc',  status: 'Active',   isSystem: false },
  { id: 7,  name: 'Late Payment Penalty',  description: 'Flat penalty fee for late payment',          code: 'LATE_PENALTY',         appliesTo: 'Either', recurring: 'Ad-hoc',  status: 'Active',   isSystem: false },
  { id: 8,  name: 'Damage Deposit',        description: 'Security/damage deposit',                    code: 'DAMAGE_DEPOSIT',       appliesTo: 'Tenant', recurring: 'Ad-hoc',  status: 'Active',   isSystem: false },
  { id: 9,  name: 'Parking Rental',        description: 'Monthly parking bay rental',                 code: 'PARKING_RENTAL',       appliesTo: 'Either', recurring: 'Monthly', status: 'Active',   isSystem: false },
  { id: 10, name: 'Pet Levy',              description: 'Recurring charge for pet-owning residents',  code: 'PET_LEVY',             appliesTo: 'Either', recurring: 'Monthly', status: 'Active',   isSystem: false },
  { id: 11, name: 'Insurance Excess',      description: 'Damage-related excess billed back',          code: 'INSURANCE_EXCESS',     appliesTo: 'Owner',  recurring: 'Ad-hoc',  status: 'Inactive', isSystem: false },
  { id: 12, name: 'Legal Recovery',        description: 'Recovery of legal costs',                    code: 'LEGAL_RECOVERY',       appliesTo: 'Either', recurring: 'Ad-hoc',  status: 'Inactive', isSystem: false },
])

// Add Charge Type modal
const showAddChargeType = ref(false)
const newChargeType = ref({
  name: '',
  code: '',
  description: '',
  appliesTo: 'Either',
  recurring: 'No',
})

watch(
  () => newChargeType.value.name,
  (val) => {
    newChargeType.value.code = val
      .toUpperCase()
      .replace(/\s+/g, '_')
      .replace(/[^A-Z0-9_]/g, '')
      .replace(/_+/g, '_')
      .replace(/^_|_$/g, '')
  }
)

function closeAddChargeType() {
  showAddChargeType.value = false
  newChargeType.value = { name: '', code: '', description: '', appliesTo: 'Either', recurring: 'No' }
}

function saveChargeType() {
  // TODO: POST /api/v1/charge-types
  closeAddChargeType()
}

function appliesToVariant(v) {
  if (v === 'Owner')  return 'success'
  if (v === 'Tenant') return 'info'
  return 'default'
}
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- Page header -->
    <div>
      <h1 class="font-body font-bold text-2xl text-foreground">Settings</h1>
      <p class="text-sm text-muted-foreground">Manage your account and company configuration</p>
    </div>

    <!-- Tab toggle -->
    <div class="flex gap-1 bg-muted rounded-lg p-0.5 w-fit">
      <button
        @click="activeTab = 'account'"
        :class="[
          'flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors',
          activeTab === 'account'
            ? 'bg-card text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground',
        ]"
      >
        <!-- User icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        Account
      </button>
      <button
        @click="activeTab = 'company'"
        :class="[
          'flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors',
          activeTab === 'company'
            ? 'bg-card text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground',
        ]"
      >
        <!-- Building icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
          <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
          <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
          <path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
        </svg>
        Company
      </button>
    </div>

    <!-- ───────────────────────── ACCOUNT TAB ───────────────────────── -->
    <div v-if="activeTab === 'account'" class="space-y-6">

      <!-- Profile card -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="p-6 pb-3">
          <h3 class="font-body font-semibold text-lg flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            Profile
          </h3>
        </div>
        <div class="p-6 pt-0">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppInput
              v-model="profile.fullName"
              label="Full Name"
              placeholder="Your full name"
            />
            <AppInput
              v-model="profile.email"
              type="email"
              label="Email Address"
              placeholder="your@email.com"
            />
            <AppInput
              v-model="profile.role"
              label="Role"
              disabled
            />
            <AppInput
              v-model="profile.phone"
              type="tel"
              label="Phone"
              placeholder="+27 82 000 0000"
            />
          </div>
          <div class="pt-4 flex justify-end">
            <AppButton variant="primary">Update Profile</AppButton>
          </div>
        </div>
      </div>

      <!-- Change Password card -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="p-6 pb-3">
          <h3 class="font-body font-semibold text-lg flex items-center gap-2">
            <!-- Key icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/>
              <circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
            </svg>
            Change Password
          </h3>
        </div>
        <div class="p-6 pt-0">
          <div class="max-w-md space-y-4">
            <AppInput
              v-model="password.current"
              type="password"
              label="Current Password"
              placeholder="••••••••"
            />
            <AppInput
              v-model="password.newPass"
              type="password"
              label="New Password"
              placeholder="••••••••"
            />
            <AppInput
              v-model="password.confirm"
              type="password"
              label="Confirm New Password"
              placeholder="••••••••"
            />
          </div>
          <div class="pt-4 flex justify-end">
            <AppButton variant="primary">Change Password</AppButton>
          </div>
        </div>
      </div>

      <!-- Security card -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="p-6 pb-3">
          <h3 class="font-body font-semibold text-lg flex items-center gap-2">
            <!-- Shield icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
            </svg>
            Security
          </h3>
        </div>
        <div class="p-6 pt-0 space-y-0">
          <div class="flex items-center justify-between py-3 border-b border-border">
            <div>
              <p class="text-sm font-medium text-foreground">Two-Factor Authentication</p>
              <p class="text-xs text-muted-foreground">Add an extra layer of security to your account</p>
            </div>
            <AppButton variant="outline" size="sm">Enable</AppButton>
          </div>
          <div class="flex items-center justify-between py-3">
            <div>
              <p class="text-sm font-medium text-foreground">Active Sessions</p>
              <p class="text-xs text-muted-foreground">Manage your active login sessions</p>
            </div>
            <AppButton variant="outline" size="sm">View Sessions</AppButton>
          </div>
        </div>
      </div>

    </div>
    <!-- end Account tab -->

    <!-- ───────────────────────── COMPANY TAB ───────────────────────── -->
    <div v-if="activeTab === 'company'" class="space-y-6">

      <!-- Company Settings + Branding card -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="p-6 pb-3">
          <h3 class="font-body font-semibold text-lg">Company Settings</h3>
        </div>
        <div class="p-6 pt-0">

          <!-- Company info grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Left column -->
            <div class="space-y-4">
              <AppInput
                v-model="company.name"
                label="Company Name"
                placeholder="e.g. Bold Mark Properties"
                hint="Used as the company name in the sidebar for all users."
              />
              <AppInput
                v-model="company.slogan"
                label="Company Slogan"
                placeholder="e.g. Property Management"
                hint="Shown below the company name in the sidebar."
              />
              <AppInput
                v-model="company.email"
                type="email"
                label="Contact Email"
                placeholder="info@company.co.za"
              />
              <AppInput
                v-model="company.phone"
                type="tel"
                label="Phone"
                placeholder="+27 10 000 0000"
              />
            </div>

            <!-- Right column -->
            <div class="space-y-4">
              <!-- Country select -->
              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-fg">Country</label>
                <AppSelect v-model="company.country" :options="COUNTRY_OPTS" />
              </div>

              <!-- Currency select -->
              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-fg flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
                    <path d="M2 12h20"/>
                  </svg>
                  Currency
                </label>
                <AppSelect v-model="company.currency" :options="CURRENCY_OPTS" />
              </div>
            </div>

          </div>

          <!-- Divider -->
          <div class="h-px bg-border my-6" />

          <!-- Branding section -->
          <h3 class="font-body font-semibold text-lg mb-4">Branding</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Primary colour -->
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-fg">Primary Color</label>
              <div class="flex items-center gap-3">
                <input
                  type="color"
                  v-model="company.primaryColor"
                  class="w-10 h-10 rounded border-2 border-border cursor-pointer p-0.5 bg-white"
                />
                <AppInput
                  v-model="company.primaryColor"
                  placeholder="#1F3A5C"
                  autocomplete="off"
                />
                <AppButton
                  type="button"
                  variant="ghost"
                  square
                  size="sm"
                  title="Reset to default"
                  @click="company.primaryColor = DEFAULT_PRIMARY"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                  </svg>
                </AppButton>
              </div>
            </div>

            <!-- Secondary colour -->
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-fg">Secondary Color</label>
              <div class="flex items-center gap-3">
                <input
                  type="color"
                  v-model="company.secondaryColor"
                  class="w-10 h-10 rounded border-2 border-border cursor-pointer p-0.5 bg-white"
                />
                <AppInput
                  v-model="company.secondaryColor"
                  placeholder="#D89B4B"
                  autocomplete="off"
                />
                <AppButton
                  type="button"
                  variant="ghost"
                  square
                  size="sm"
                  title="Reset to default"
                  @click="company.secondaryColor = DEFAULT_SECONDARY"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                  </svg>
                </AppButton>
              </div>
            </div>

          </div>

          <!-- Save button -->
          <div class="pt-6 flex justify-end">
            <AppButton variant="primary">Save Settings</AppButton>
          </div>

        </div>
      </div>

      <!-- Charge Types card -->
      <div class="rounded-lg border bg-card shadow-sm">
        <div class="p-6 pb-3">
          <div class="flex items-center justify-between">
            <h3 class="font-body font-semibold text-lg">Charge Types</h3>
            <AppButton variant="primary" size="sm" @click="showAddChargeType = true">
              <!-- Plus icon -->
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
              </svg>
              Add Charge Type
            </AppButton>
          </div>
        </div>
        <div class="p-6 pt-0">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border">
                  <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Name</th>
                  <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Code</th>
                  <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Applies To</th>
                  <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Recurring</th>
                  <th class="text-left py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                  <th class="text-right py-3 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="ct in chargeTypes"
                  :key="ct.id"
                  class="border-b border-border hover:bg-muted/30 transition-colors"
                >
                  <!-- Name + description -->
                  <td class="py-3 px-3">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-foreground">{{ ct.name }}</span>
                      <!-- Lock icon for system defaults -->
                      <svg
                        v-if="ct.isSystem"
                        xmlns="http://www.w3.org/2000/svg"
                        class="w-3.5 h-3.5 text-muted-foreground shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                      </svg>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ ct.description }}</p>
                  </td>

                  <!-- Code -->
                  <td class="py-3 px-3 font-mono text-xs text-muted-foreground">{{ ct.code }}</td>

                  <!-- Applies To -->
                  <td class="py-3 px-3">
                    <AppBadge :variant="appliesToVariant(ct.appliesTo)" bordered size="sm">
                      {{ ct.appliesTo }}
                    </AppBadge>
                  </td>

                  <!-- Recurring -->
                  <td class="py-3 px-3 text-foreground text-sm">{{ ct.recurring }}</td>

                  <!-- Status -->
                  <td class="py-3 px-3">
                    <AppBadge
                      :variant="ct.status === 'Active' ? 'success' : 'default'"
                      bordered
                      size="sm"
                    >
                      {{ ct.status }}
                    </AppBadge>
                  </td>

                  <!-- Actions -->
                  <td class="py-3 px-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <!-- Edit -->
                      <AppButton variant="ghost" square size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                          <path d="m15 5 4 4"/>
                        </svg>
                      </AppButton>
                      <!-- Delete (non-system only) -->
                      <AppButton
                        v-if="!ct.isSystem"
                        variant="danger-ghost"
                        square
                        size="sm"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M3 6h18"/>
                          <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                          <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                          <line x1="10" x2="10" y1="11" y2="17"/>
                          <line x1="14" x2="14" y1="11" y2="17"/>
                        </svg>
                      </AppButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    <!-- end Company tab -->

  </div>

  <!-- ─────────────── Add Custom Charge Type modal ─────────────── -->
  <AppModal
    :show="showAddChargeType"
    title="Add Custom Charge Type"
    size="md"
    @close="closeAddChargeType"
  >
    <div class="space-y-4">
      <AppInput
        v-model="newChargeType.name"
        label="Name"
        placeholder="e.g. Generator Fee"
        required
      />
      <AppInput
        v-model="newChargeType.code"
        label="Code"
        placeholder="e.g. GENERATOR_FEE"
        hint="Auto-generated from name. Must be uppercase with underscores."
        required
      />

      <!-- Description textarea -->
      <AppInput
        v-model="newChargeType.description"
        type="textarea"
        label="Description"
        :rows="2"
        placeholder="Brief description..."
      />

      <!-- Applies To select -->
      <AppSelect v-model="newChargeType.appliesTo" label="Applies To" :options="APPLIES_TO_OPTS" required />

      <!-- Recurring select -->
      <AppSelect v-model="newChargeType.recurring" label="Recurring?" :options="RECURRING_OPTS" required />
    </div>

    <template #footer>
      <AppButton variant="outline" @click="closeAddChargeType">Cancel</AppButton>
      <AppButton variant="primary" @click="saveChargeType">Save</AppButton>
    </template>
  </AppModal>

</template>
