<!--
  Status Batches — strict WeConnectU "Status Batches" clone (Bold Mark branding).

  Reached from the Customer Status page "View status history" link. Lists every
  batch of statuses that has been applied to the community's customers, one row
  per batch, showing the date, status, customer count, note and (email/SMS)
  channels the batch was communicated over.
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import api       from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const communityStore = useCommunityStore()
const { error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)

const loading = ref(true)
const batches = ref([])

async function fetchBatches() {
  if (!communityId.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-statuses/history`)
    batches.value = Array.isArray(data) ? data : (data.batches ?? [])
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not load status batches.')
  } finally {
    loading.value = false
  }
}

watch(communityId, fetchBatches)
onMounted(fetchBatches)
</script>

<template>
  <div class="p-6">
    <!-- No community -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view status batches.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <h1 class="font-body text-2xl font-bold text-foreground">Status Batches</h1>

      <!-- Loading -->
      <div v-if="loading" class="mt-8 text-sm text-muted-foreground">Loading…</div>

      <!-- Empty -->
      <div v-else-if="!batches.length" class="mt-8 rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
        No status batches.
      </div>

      <!-- Table -->
      <div v-else class="mt-4 overflow-hidden rounded-lg border border-border bg-white">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/40 text-left align-top text-muted-foreground">
              <th class="px-4 py-2 font-semibold">Date</th>
              <th class="px-4 py-2 font-semibold">Status</th>
              <th class="px-4 py-2 font-semibold">Customers</th>
              <th class="px-4 py-2 font-semibold">Note</th>
              <th class="px-4 py-2 font-semibold">Email</th>
              <th class="px-4 py-2 font-semibold">SMS</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(batch, i) in batches"
              :key="i"
              :class="i % 2 ? 'bg-muted/20' : 'bg-white'"
              class="border-b border-border last:border-0"
            >
              <td class="px-4 py-3 align-top"><span class="text-[#2f6fb0]">{{ batch.status_date }}</span></td>
              <td class="px-4 py-3 align-top text-foreground">{{ batch.status_label }}</td>
              <td class="px-4 py-3 align-top text-foreground">{{ batch.customers }}</td>
              <td class="px-4 py-3 align-top whitespace-normal text-muted-foreground">{{ batch.note }}</td>
              <td class="px-4 py-3 align-top text-muted-foreground">{{ batch.email ?? '' }}</td>
              <td class="px-4 py-3 align-top text-muted-foreground">{{ batch.sms ?? '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
