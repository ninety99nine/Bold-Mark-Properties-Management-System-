<!--
  Automatic Status Changes — strict WeConnectU "Automatic Status Changes" clone
  (Bold Mark branding).

  Reached from the Customer Status page "View automatic status changes" link.
  Lists every status change the system applied automatically (e.g. via rules /
  scheduled runs), one row per change, showing the date it happened and the
  affected customer.
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
const rows    = ref([])

async function fetchChanges() {
  if (!communityId.value) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${communityId.value}/customer-statuses/automatic-changes`)
    rows.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not load automatic status changes.')
  } finally {
    loading.value = false
  }
}

watch(communityId, fetchChanges)
onMounted(fetchChanges)
</script>

<template>
  <div class="p-6">
    <!-- No community -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to view automatic status changes.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <h1 class="font-body text-2xl font-bold text-foreground">Automatic Status Changes</h1>

      <!-- Loading -->
      <div v-if="loading" class="mt-8 text-sm text-muted-foreground">Loading…</div>

      <!-- Empty -->
      <div v-else-if="!rows.length" class="mt-8 rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
        No automatic status changes.
      </div>

      <!-- Table -->
      <div v-else class="mt-4 overflow-hidden rounded-lg border border-border bg-white">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/40 text-left align-top text-muted-foreground">
              <th class="px-4 py-2 font-semibold">Date</th>
              <th class="px-4 py-2 font-semibold">Customer</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, i) in rows"
              :key="row.id ?? i"
              :class="i % 2 ? 'bg-muted/20' : 'bg-white'"
              class="border-b border-border last:border-0"
            >
              <td class="px-4 py-3 align-top text-foreground">{{ row.created_at ?? row.status_date }}</td>
              <td class="px-4 py-3 align-top text-foreground">{{ row.customer }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
