<!--
  LastNoticeBatchModal — WeConnectU "View last notice batch".

  Shows the most recent notice run: the ageing date it was run against, per-level
  counts, and every customer noticed with a link to download the generated letter PDF.
-->
<script setup>
import { computed } from 'vue'
import AppModal  from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import api       from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  show:        { type: Boolean, default: false },
  communityId: { type: String,  default: null },
  batch:       { type: Object,  default: null },
})

const emit = defineEmits(['close'])
const { error: toastError } = useToast()

const counts = computed(() => {
  const c = props.batch?.counts ?? {}
  return Object.entries(c).map(([level, n]) => ({ level, n }))
})

function money(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

async function download(item) {
  try {
    const res = await api.get(
      `/communities/${props.communityId}/age-analysis/notices/${props.batch.id}/items/${item.id}/download`,
      { responseType: 'blob' },
    )
    const url = URL.createObjectURL(res.data)
    const a = Object.assign(document.createElement('a'), {
      href: url,
      download: `${item.level} - ${item.customer_code ?? 'customer'}.pdf`,
    })
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e) {
    toastError('Could not download the letter.')
  }
}
</script>

<template>
  <AppModal :show="show" title="Last Notice Batch" size="xl" @close="emit('close')">
    <div v-if="!batch" class="py-10 text-center text-sm text-muted-foreground">
      No notices have been run for this community yet.
    </div>

    <div v-else class="space-y-4">
      <!-- Summary -->
      <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <div><span class="text-muted-foreground">Ageing date:</span> <span class="font-medium">{{ batch.ageing_date }}</span></div>
        <div><span class="text-muted-foreground">Run by:</span> <span class="font-medium">{{ batch.created_by_name ?? '—' }}</span></div>
        <div><span class="text-muted-foreground">Total:</span> <span class="font-medium">{{ batch.total }}</span></div>
      </div>

      <div v-if="counts.length" class="flex flex-wrap gap-2">
        <span v-for="c in counts" :key="c.level"
              class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/40 px-2.5 py-0.5 text-xs">
          {{ c.level }} <span class="font-semibold text-navy-dark">{{ c.n }}</span>
        </span>
      </div>

      <!-- Items -->
      <div class="max-h-[50vh] overflow-y-auto rounded-md border border-border">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-[#eef1f5]">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-navy-dark">Customer</th>
              <th class="px-3 py-2 text-left font-semibold text-navy-dark">Notice</th>
              <th class="px-3 py-2 text-right font-semibold text-navy-dark">Balance</th>
              <th class="px-3 py-2 text-center font-semibold text-navy-dark">Emailed</th>
              <th class="px-3 py-2 text-right font-semibold text-navy-dark">Letter</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in batch.items" :key="item.id" class="border-t border-border hover:bg-muted/30">
              <td class="px-3 py-2">
                <span class="font-medium text-navy-dark">{{ item.customer_code }}</span>
                <span class="text-muted-foreground">: {{ item.customer_name }}</span>
              </td>
              <td class="px-3 py-2">{{ item.level }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ money(item.balance) }}</td>
              <td class="px-3 py-2 text-center">
                <span :class="item.emailed ? 'text-success' : 'text-muted-foreground'">{{ item.emailed ? 'Yes' : 'No' }}</span>
              </td>
              <td class="px-3 py-2 text-right">
                <button type="button" class="text-[#2f6fb0] hover:underline" @click="download(item)">Download</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <AppButton variant="outline" @click="emit('close')">Close</AppButton>
      </div>
    </template>
  </AppModal>
</template>
