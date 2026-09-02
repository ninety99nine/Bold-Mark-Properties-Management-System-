<!--
  CommunityTakeOnPage — WeConnectU-style "Take-on" onboarding screen for a
  community that is still being taken on. Collapsible sections:
    • Previous Managing Agent  (text + Save)
    • Opening Balance Date     (date picker, auto-saved)
    • Owner Sheet              (Excel upload — customer template)
    • Budget                   (Excel upload — budget template)
  A "Submit Take-on" action transitions the community from Take-on → Active.
-->
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import TakeOnSection from '@/components/communities/TakeOnSection.vue'
import TakeOnUpload from '@/components/communities/TakeOnUpload.vue'

const route  = useRoute()
const router = useRouter()
const { success, error } = useToast()

const communityId = route.params.communityId
const base = `/communities/${communityId}`

const loading   = ref(true)
const community = ref(null)
const takeonStatus = ref({ owner_sheet: { done: false, entries: [] }, budget: { done: false, entries: [] } })

const previousAgent   = ref('')
const openingDate     = ref('')
const savingAgent     = ref(false)
const savingDate      = ref(false)
const submitting      = ref(false)

// A file step is "done" once it has at least one history entry (upload or N/A).
const isStepDone = (key) => !!takeonStatus.value[key]?.done

async function fetchCommunity() {
  loading.value = true
  try {
    const { data } = await api.get(base)
    community.value = data.data ?? data
    previousAgent.value = community.value.previous_managing_agent ?? ''
    openingDate.value   = community.value.opening_balance_date ?? ''
  } catch {
    error('Could not load this community.')
  } finally {
    loading.value = false
  }
}

async function fetchStatus() {
  try {
    const { data } = await api.get(`${base}/take-on/status`)
    if (data.items) takeonStatus.value = data.items
  } catch {
    // Non-fatal — the sections still work without prior status.
  }
}

async function saveAgent() {
  savingAgent.value = true
  try {
    await api.put(base, { previous_managing_agent: previousAgent.value })
    success('Previous managing agent saved.')
  } catch (e) {
    error(e.response?.data?.message || 'Could not save. Please try again.')
  } finally {
    savingAgent.value = false
  }
}

async function saveDate(value) {
  openingDate.value = value
  savingDate.value = true
  try {
    await api.put(base, { opening_balance_date: value || null })
    success('Opening balance date saved.')
  } catch (e) {
    error(e.response?.data?.message || 'Could not save. Please try again.')
  } finally {
    savingDate.value = false
  }
}

async function submitTakeOn() {
  submitting.value = true
  try {
    await api.post(`${base}/submit-take-on`)
    success('Take-on submitted. Community is now active.')
    router.push('/communities')
  } catch (e) {
    error(e.response?.data?.message || 'Could not submit take-on. Please try again.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchCommunity()
  fetchStatus()
})
</script>

<template>
  <div class="max-w-2xl space-y-4 pb-8">
    <!-- Header -->
    <div>
      <button type="button" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground mb-2" @click="router.push('/communities')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Communities
      </button>

      <div class="flex items-center justify-between gap-4">
        <h1 class="text-lg font-bold text-foreground uppercase tracking-tight truncate">
          {{ community?.name || 'Take-on' }}
        </h1>
        <AppButton variant="primary" size="sm" :loading="submitting" @click="submitTakeOn">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
          Submit Take-on
        </AppButton>
      </div>
    </div>

    <div v-if="loading" class="py-20 text-center text-sm text-muted-foreground">Loading…</div>

    <div v-else class="space-y-3">
      <!-- Previous Managing Agent -->
      <TakeOnSection title="Previous Managing Agent" :done="!!previousAgent">
        <AppInput v-model="previousAgent" placeholder="" />
        <div class="mt-4">
          <AppButton variant="primary" size="sm" :loading="savingAgent" @click="saveAgent">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5"><path d="M20 6 9 17l-5-5"/></svg>
            Save
          </AppButton>
        </div>
      </TakeOnSection>

      <!-- Opening Balance Date -->
      <TakeOnSection title="Opening Balance Date" :done="!!openingDate">
        <AppDatePicker :model-value="openingDate" placeholder="Click to select date" @update:model-value="saveDate" />
      </TakeOnSection>

      <!-- Owner Sheet -->
      <TakeOnSection title="Owner Sheet" :done="isStepDone('owner_sheet')">
        <TakeOnUpload
          :community-id="communityId"
          :template-path="`${base}/owner-sheet/template`"
          :import-path="`${base}/owner-sheet/import`"
          :not-applicable-path="`${base}/take-on/owner_sheet/not-applicable`"
          :download-base="`${base}/take-on/items`"
          :entries="takeonStatus.owner_sheet.entries"
          template-name="owner-sheet-template.xlsx"
          instruction="Please upload the owner sheet in Excel file."
          link-text="Click here"
          link-suffix="to download the customer template."
          @changed="fetchStatus"
        />
      </TakeOnSection>

      <!-- Budget -->
      <TakeOnSection title="Budget" :done="isStepDone('budget')">
        <TakeOnUpload
          :community-id="communityId"
          :template-path="`${base}/budget/template`"
          :import-path="`${base}/budget/import`"
          :not-applicable-path="`${base}/take-on/budget/not-applicable`"
          :download-base="`${base}/take-on/items`"
          :entries="takeonStatus.budget.entries"
          template-name="budget-template.xlsx"
          instruction="Please upload the budget in Excel."
          link-text="Click here"
          link-suffix="to download the budget template."
          @changed="fetchStatus"
        />
      </TakeOnSection>
    </div>
  </div>
</template>
