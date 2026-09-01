<!--
  RunNoticesModal — WeConnectU "Run Automatic Notices" confirmation.

  Escalates every overdue customer in scope one step along the collection ladder
  (Reminder → 1st → 2nd → Final → Letter of Demand), generates a branded letter
  PDF for each, and optionally emails the customer. Honours the page's current
  filters (passed via :params).
-->
<script setup>
import { ref } from 'vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import api       from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  show:         { type: Boolean, default: false },
  communityId:  { type: String,  default: null },
  params:       { type: Object,  default: () => ({}) },
  arrearsCount: { type: Number,  default: 0 },
})

const emit = defineEmits(['close', 'ran'])
const { success, error: toastError } = useToast()

const sendEmail  = ref(false)
const submitting = ref(false)

async function run() {
  if (!props.communityId) return
  submitting.value = true
  try {
    const { data } = await api.post(
      `/communities/${props.communityId}/age-analysis/notices/run`,
      { ...props.params, send_email: sendEmail.value },
    )
    success(data.message || 'Notices generated.')
    emit('ran', data.batch)
    emit('close')
  } catch (e) {
    toastError(e.response?.data?.message || 'Failed to run notices.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :show="show" title="Run Automatic Notices" size="md" @close="emit('close')">
    <div class="space-y-4 text-sm text-foreground">
      <p class="leading-relaxed">
        This will advance every overdue customer in the current view one step along the
        collection ladder — <span class="font-medium">Reminder → 1st Notice → 2nd Notice → Final Notice → Letter of Demand</span> —
        generate a branded letter for each, and log a collection note. Customers that are
        <span class="font-medium">Handed Over</span>, on a <span class="font-medium">Payment Arrangement</span>, or already <span class="font-medium">Paid</span> are skipped.
      </p>

      <div class="rounded-md border border-border bg-muted/40 px-4 py-3">
        <span class="font-semibold text-navy-dark">{{ arrearsCount }}</span>
        overdue customer{{ arrearsCount === 1 ? '' : 's' }} currently in scope.
      </div>

      <label class="flex cursor-pointer items-center gap-2 select-none">
        <input v-model="sendEmail" type="checkbox" class="h-4 w-4 rounded border-border text-navy focus:ring-navy" />
        <span>Also email the letter to customers with an email address</span>
      </label>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <AppButton variant="outline" :disabled="submitting" @click="emit('close')">Cancel</AppButton>
        <AppButton variant="primary" :loading="submitting" :disabled="arrearsCount === 0" @click="run">
          Run Notices
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>
