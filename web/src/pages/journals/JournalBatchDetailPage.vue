<!--
  JournalBatchDetailPage — WeConnectU "Journal Batch {n}" edit screen.

  Loads a batch by id and edits it through the shared JournalBatchForm (Date,
  Journal Group, lines, files → "Submit Journal Edit"). "Back to Journal Batches"
  returns to the list.
-->
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import JournalBatchForm from '@/components/journals/JournalBatchForm.vue'

const route     = useRoute()
const router    = useRouter()
const community = useCommunityStore()
const { error } = useToast()

const batch   = ref(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/journals/${route.params.batchId}`)
    batch.value = data.data ?? data
  } catch {
    error('Could not load that journal batch.')
    router.push({ name: 'journals' })
  } finally {
    loading.value = false
  }
}

function back()      { router.push({ name: 'journals' }) }
function onSaved()   { back() }

onMounted(load)
</script>

<template>
  <div class="space-y-6 pb-8">
    <div class="flex items-center justify-between">
      <h1 class="font-body font-bold text-2xl text-foreground">
        {{ batch?.batch_name || 'Journal Batch' }}
      </h1>
    </div>

    <div v-if="loading" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Loading…
    </div>

    <div v-else-if="batch" class="rounded-lg border border-border bg-white p-6">
      <JournalBatchForm
        mode="edit"
        :community-id="batch.community_id || community.selectedId"
        :financial-year="batch.financial_year"
        :batch="batch"
        @saved="onSaved"
        @cancel="back"
      />
    </div>
  </div>
</template>
