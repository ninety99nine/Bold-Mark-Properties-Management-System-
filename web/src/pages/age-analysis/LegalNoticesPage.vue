<!--
  Legal Notices — strict WeConnectU "Run Automatic Notices" clone (Bold Mark branding).

  Reached from the Age Analysis "Run Automatic Notices" button. Previews every
  overdue, chaseable customer grouped by the notice level they are next due for
  (1st Notices / 2nd Notices / Final Notices / Letters of Demand), each with a
  per-customer Email + SMS toggle (SMS is visual-only — Bold Mark sends email
  only) and an "ALL" select-all per column, per section. "Confirm and run
  notices" escalates each customer one step and emails the ticked ones.
-->
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import api       from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'

const router         = useRouter()
const route          = useRoute()
const communityStore = useCommunityStore()
const { success, error: toastError } = useToast()

const communityId = computed(() => communityStore.selectedId)

const loading    = ref(true)
const submitting = ref(false)
function endOfCurrentMonth() {
  const d    = new Date()
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  const mm   = String(last.getMonth() + 1).padStart(2, '0')
  const dd   = String(last.getDate()).padStart(2, '0')
  return `${last.getFullYear()}-${mm}-${dd}`
}
const ageingDate = ref(route.query.ageing_date || endOfCurrentMonth())
const sections   = ref([])

// Per-unit channel selection, keyed by unit_id → { email, sms }.
const selection = reactive({})

const totalCustomers = computed(() =>
  sections.value.reduce((n, s) => n + s.rows.length, 0),
)

// ── Currency (WeConnectU: "R 3 690.59", space thousands) ──────────────────
function fmt(v) {
  const n = Number(v) || 0
  const neg = n < 0
  const [i, d] = Math.abs(n).toFixed(2).split('.')
  return 'R ' + (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + d
}

// ── Load preview ───────────────────────────────────────────────────────────
async function fetchPreview() {
  if (!communityId.value) { loading.value = false; return }
  loading.value = true
  try {
    const params = { ...route.query }
    const { data } = await api.get(
      `/communities/${communityId.value}/age-analysis/notices/preview`,
      { params },
    )
    ageingDate.value = data.ageing_date
    sections.value   = data.sections || []
    for (const s of sections.value) {
      for (const r of s.rows) {
        // Below-threshold customers are excluded from the run by default.
        const on = !r.below_threshold
        selection[r.unit_id] = { email: on, sms: on }
      }
    }
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not load legal notices.')
  } finally {
    loading.value = false
  }
}

// ── Select-all per section / channel ─────────────────────────────────────────
function allChecked(section, channel) {
  return section.rows.length > 0 && section.rows.every(r => selection[r.unit_id]?.[channel])
}
function toggleAll(section, channel, value) {
  for (const r of section.rows) {
    if (selection[r.unit_id]) selection[r.unit_id][channel] = value
  }
}

// ── Confirm & run ────────────────────────────────────────────────────────────
async function confirm() {
  if (!communityId.value || totalCustomers.value === 0) return
  submitting.value = true

  const unitIds      = []
  const emailUnitIds = []
  for (const s of sections.value) {
    for (const r of s.rows) {
      const sel = selection[r.unit_id]
      // Only customers with a channel ticked are actioned (below-threshold rows
      // default to unticked and are skipped).
      if (!sel?.email && !sel?.sms) continue
      unitIds.push(r.unit_id)
      if (sel.email && r.emails?.length) emailUnitIds.push(r.unit_id)
    }
  }

  if (unitIds.length === 0) {
    toastError('Select at least one customer to notice.')
    submitting.value = false
    return
  }

  try {
    const { data } = await api.post(
      `/communities/${communityId.value}/age-analysis/notices/run`,
      {
        ageing_date:    ageingDate.value,
        unit_ids:       unitIds,
        email_unit_ids: emailUnitIds,
      },
    )
    success(data.message || 'Notices generated.')
    router.push({ name: 'age-analysis' })
  } catch (e) {
    toastError(e.response?.data?.message || 'Failed to run notices.')
  } finally {
    submitting.value = false
  }
}

onMounted(fetchPreview)
</script>

<template>
  <div class="p-6">
    <!-- No community -->
    <div v-if="!communityId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community to run legal notices.
      <div class="mt-4"><AppButton variant="secondary" @click="router.push('/communities')">Choose a community</AppButton></div>
    </div>

    <template v-else>
      <h1 class="font-body text-2xl font-bold text-foreground">Legal Notices for {{ ageingDate }}</h1>

      <!-- Loading -->
      <div v-if="loading" class="mt-8 text-sm text-muted-foreground">Loading…</div>

      <!-- Empty -->
      <div v-else-if="totalCustomers === 0" class="mt-8 rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
        No overdue customers are due for a notice.
      </div>

      <template v-else>
        <!-- Sections -->
        <section v-for="section in sections" :key="section.level" class="mt-8">
          <h2 class="mb-2 text-lg font-semibold text-foreground">{{ section.title }}</h2>

          <div class="overflow-hidden rounded-lg border border-border bg-white">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border bg-muted/40 text-left align-top text-muted-foreground">
                  <th class="w-24 px-4 py-2 font-semibold">
                    <div>Email</div>
                    <label class="mt-1 flex items-center gap-1 font-normal">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-border text-navy focus:ring-navy"
                        :checked="allChecked(section, 'email')"
                        @change="toggleAll(section, 'email', $event.target.checked)"
                      />
                      ALL
                    </label>
                  </th>
                  <th class="w-24 px-4 py-2 font-semibold">
                    <div>SMS</div>
                    <label class="mt-1 flex items-center gap-1 font-normal">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-border text-navy focus:ring-navy"
                        :checked="allChecked(section, 'sms')"
                        @change="toggleAll(section, 'sms', $event.target.checked)"
                      />
                      ALL
                    </label>
                  </th>
                  <th class="px-4 py-2 font-semibold">Customer</th>
                  <th class="px-4 py-2 font-semibold">Send to</th>
                  <th class="px-4 py-2 text-right font-semibold">Balance</th>
                  <th class="px-4 py-2 font-semibold">Customer Type</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, i) in section.rows"
                  :key="row.unit_id"
                  :class="i % 2 ? 'bg-muted/20' : 'bg-white'"
                  class="border-b border-border last:border-0"
                >
                  <td class="px-4 py-3 align-top">
                    <input
                      v-if="selection[row.unit_id]"
                      v-model="selection[row.unit_id].email"
                      type="checkbox"
                      class="h-4 w-4 rounded border-border text-navy focus:ring-navy"
                    />
                  </td>
                  <td class="px-4 py-3 align-top">
                    <input
                      v-if="selection[row.unit_id]"
                      v-model="selection[row.unit_id].sms"
                      type="checkbox"
                      class="h-4 w-4 rounded border-border text-navy focus:ring-navy"
                    />
                  </td>
                  <td class="px-4 py-3 align-top text-foreground">
                    {{ row.customer_code }}<template v-if="row.customer_name && row.customer_name !== '—'">: {{ row.customer_name }}</template>
                  </td>
                  <td class="px-4 py-3 align-top text-muted-foreground">
                    <div v-if="row.emails?.length">{{ row.emails.join(', ') }}</div>
                    <div v-if="row.phones?.length">{{ row.phones.join(', ') }}</div>
                    <span v-if="!row.emails?.length && !row.phones?.length">—</span>
                  </td>
                  <td class="px-4 py-3 text-right align-top text-foreground">
                    <span class="inline-flex items-center justify-end gap-1">
                      <span v-if="row.below_threshold" class="group relative inline-flex">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-muted-foreground">
                          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                        </svg>
                        <span class="pointer-events-none absolute right-0 top-full z-20 mt-1 whitespace-nowrap rounded-md bg-[#1f2430] px-2.5 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100">Balance below notice threshold</span>
                      </span>
                      {{ fmt(row.balance) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 align-top capitalize text-muted-foreground">{{ row.customer_type || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Confirm -->
        <div class="mt-8">
          <button
            type="button"
            class="inline-flex h-10 items-center rounded-md bg-[#e8722e] px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            :disabled="submitting || totalCustomers === 0"
            @click="confirm"
          >
            {{ submitting ? 'Running…' : 'Confirm and run notices' }}
          </button>
        </div>
      </template>
    </template>
  </div>
</template>
