<script setup>
/**
 * Unit PQ's — a like-for-like clone of WeConnectU's Unit PQ page
 * (WeConnectU layout/structure, Bold Mark navy/gold styling — no WeConnectU branding).
 *
 * Columns: Section · Unit No · Customer Code · PQ · Ratio 1 · Unit Size · VOTE
 * Actions: Download PQ Batch file (export xlsx) · Upload new PQs (import).
 */
import { ref, computed, onMounted } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'

const props = defineProps({
  communityId: { type: String, required: true },
})
const emit = defineEmits(['upload'])

const { error: toastError } = useToast()

const loading = ref(true)
const units = ref([])
const search = ref('')
const sortKey = ref('section')
const sortDir = ref('asc')
const votes = ref({}) // unit id → vote value (local until a backend vote field exists)
const downloading = ref(false)

// WeConnectU column set (Vote is rendered separately as an input column).
const columns = [
  { key: 'section',       label: 'Section' },
  { key: 'unit_number',   label: 'Unit No' },
  { key: 'customer_code', label: 'Customer Code' },
  { key: 'pq',            label: 'PQ' },
  { key: 'ratio1',        label: 'Ratio 1' },
  { key: 'unit_size',     label: 'Unit Size' },
]

async function load() {
  loading.value = true
  try {
    const all = []
    let page = 1
    // The units endpoint caps _per_page at 200 — page through until we have them all.
    for (;;) {
      const { data } = await api.get(`/communities/${props.communityId}/units`, {
        params: { _per_page: 200, page },
      })
      const list = data.data ?? data ?? []
      all.push(...list)
      const lastPage = data.meta?.last_page ?? data.last_page ?? 1
      if (page >= lastPage || list.length === 0) break
      page++
    }
    units.value = all.map((u) => ({
      id:            u.id,
      unit_number:   u.unit_number ?? '',
      section:       u.section ?? '',
      customer_code: u.customer_code ?? '',
      pq:            u.pq != null ? Number(u.pq) : null,
      ratio_1:       u.ratio_1 != null ? Number(u.ratio_1) : null,
      unit_size:     u.unit_size != null ? Number(u.unit_size) : 0,
      owner_name:    u.owner?.full_name ?? '',
    }))
  } catch (e) {
    units.value = []
  } finally {
    loading.value = false
  }
}
onMounted(load)
defineExpose({ load })

const totalPq = computed(() => units.value.reduce((s, u) => s + (u.pq ?? 0), 0))
const unitCount = computed(() => units.value.length)

// Ratio 1 = the value uploaded in the PQ batch file. When it wasn't supplied,
// fall back to the unit's participation share (equal share when PQs are unset).
function ratio1(u) {
  if (u.ratio_1 != null) return u.ratio_1
  if (u.pq != null && totalPq.value > 0) return (u.pq / totalPq.value) * 100
  return unitCount.value > 0 ? 100 / unitCount.value : 0
}

const rows = computed(() => {
  let list = units.value.map((u) => ({ ...u, ratio1: ratio1(u) }))

  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter((u) =>
      (u.section || '').toLowerCase().includes(q) ||
      (u.unit_number || '').toLowerCase().includes(q) ||
      (u.customer_code || '').toLowerCase().includes(q) ||
      (u.owner_name || '').toLowerCase().includes(q))
  }

  const dir = sortDir.value === 'asc' ? 1 : -1
  const key = sortKey.value
  return [...list].sort((a, b) => {
    if (key === 'pq' || key === 'ratio1' || key === 'unit_size') {
      return ((a[key] ?? 0) - (b[key] ?? 0)) * dir
    }
    return String(a[key] ?? '').localeCompare(String(b[key] ?? ''), undefined, { numeric: true }) * dir
  })
})

function toggleSort(key) {
  if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; sortDir.value = 'asc' }
}

async function downloadBatch() {
  if (downloading.value) return
  downloading.value = true
  try {
    const res = await api.get(`/communities/${props.communityId}/units/pq/export`, {
      params: { format: 'xlsx' },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'unit-pqs.xlsx'
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    toastError('Could not download the PQ batch file.')
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="font-body text-2xl font-semibold text-foreground mb-4">Unit PQ's</h2>

    <div class="rounded-lg bg-card shadow-sm">
      <!-- Actions -->
      <div class="flex items-center justify-between gap-3 px-5 py-4 flex-wrap">
        <button
          type="button" @click="downloadBatch" :disabled="downloading"
          class="inline-flex items-center gap-2 rounded-md bg-navy px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-navy-dark disabled:opacity-60"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          Download PQ Batch file
        </button>

        <AppButton variant="primary" size="sm" @click="emit('upload')">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          Upload new PQs
        </AppButton>
      </div>

      <!-- Search -->
      <div class="flex items-center justify-end gap-2 px-5 pb-3">
        <label class="text-sm text-muted-foreground">Search:</label>
        <input
          v-model="search" type="text"
          class="w-56 rounded-md border border-border px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-accent/40"
        />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto border-t border-border">
        <table class="w-full text-sm">
          <thead class="bg-muted/40">
            <tr>
              <th
                v-for="col in columns" :key="col.key"
                @click="toggleSort(col.key)"
                class="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground"
              >
                <span class="inline-flex items-center gap-1.5">
                  {{ col.label }}
                  <span class="inline-flex flex-col leading-[0.6] text-[8px]">
                    <span :class="sortKey === col.key && sortDir === 'asc' ? 'text-foreground' : 'text-muted-foreground/40'">▲</span>
                    <span :class="sortKey === col.key && sortDir === 'desc' ? 'text-foreground' : 'text-muted-foreground/40'">▼</span>
                  </span>
                </span>
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Vote</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="columns.length + 1" class="py-12 text-center text-sm text-muted-foreground">Loading participation quotas…</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td :colspan="columns.length + 1" class="py-12 text-center text-sm text-muted-foreground">No units yet.</td>
            </tr>
            <tr
              v-for="(u, i) in rows" :key="u.id"
              :class="i % 2 === 1 ? 'bg-muted/25' : 'bg-card'"
            >
              <td class="px-4 py-3 text-foreground">{{ u.section || '—' }}</td>
              <td class="px-4 py-3 text-foreground">{{ u.unit_number }}</td>
              <td class="px-4 py-3 text-muted-foreground">{{ u.customer_code || '—' }}</td>
              <td class="px-4 py-3 font-mono text-foreground">{{ (u.pq ?? 0).toFixed(4) }}</td>
              <td class="px-4 py-3 font-mono text-foreground">{{ u.ratio1.toFixed(10) }}</td>
              <td class="px-4 py-3 text-muted-foreground">{{ (u.unit_size ?? 0).toFixed(2) }} m²</td>
              <td class="px-4 py-3">
                <input
                  v-model="votes[u.id]" type="text"
                  class="w-24 rounded-md border border-border px-2 py-1 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-accent/40"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
