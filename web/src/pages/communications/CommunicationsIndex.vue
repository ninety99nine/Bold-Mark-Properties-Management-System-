<!--
  CommunicationsIndex — global Communications page (mirrors WeConnectU).

  Front-end shell only: the Send form and Archive table are fully laid out and
  interactive, but sending is not wired to a backend yet (a toast explains this)
  and the Archive shows an empty state. Communities are loaded for real so the
  multi-select reflects the actual portfolio.
-->
<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import { useToast } from '@/composables/useToast'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppMultiSelect from '@/components/common/AppMultiSelect.vue'
import EmailTagsInput from '@/components/common/EmailTagsInput.vue'

const { success, error: toastError } = useToast()

const activeTab = ref('send') // 'send' | 'archive'

// ── Communities (real) ────────────────────────────────────────────────
const communities = ref([])
const communityOptions = computed(() =>
  communities.value.map(c => ({
    value: c.id,
    label: c.code ? `${c.code} - ${c.name}` : c.name,
  }))
)

async function fetchCommunities() {
  try {
    const { data } = await api.get('/communities', { params: { _per_page: 500 } })
    communities.value = data.data ?? []
  } catch (e) {
    console.error('Failed to load communities', e)
  }
}

onMounted(fetchCommunities)

// ── Send form state ───────────────────────────────────────────────────
const form = ref({
  communityIds: [],
  recipients: [],
  subject: '',
  body: '',
  bcc: [],
})
const bccValid = ref(true)
const showBcc = ref(false)
const attachments = ref([])
const fileInput = ref(null)
const dragOver = ref(false)

// "All communities" convenience toggle.
const allCommunities = computed({
  get: () => communityOptions.value.length > 0 && form.value.communityIds.length === communityOptions.value.length,
  set: (val) => { form.value.communityIds = val ? communityOptions.value.map(o => o.value) : [] },
})

// Recipient groups (matches WeConnectU "Send To").
const RECIPIENT_GROUPS = [
  { value: 'owners',            label: 'Owners' },
  { value: 'directors',         label: 'Directors/Trustees' },
  { value: 'complex_managers',  label: 'Complex Manager/s' },
  { value: 'rental_agents',     label: 'Rental Agents' },
  { value: 'attorneys',         label: 'Attorneys / Additional' },
  { value: 'occupants',         label: 'Occupants' },
  { value: 'bondholders',       label: 'Bondholders' },
]
const allRecipients = computed({
  get: () => form.value.recipients.length === RECIPIENT_GROUPS.length,
  set: (val) => { form.value.recipients = val ? RECIPIENT_GROUPS.map(r => r.value) : [] },
})

const charCount = computed(() => form.value.body.length)

// ── Attachments (front-end only) ──────────────────────────────────────
const MAX_TOTAL_BYTES = 7 * 1024 * 1024
const totalBytes = computed(() => attachments.value.reduce((s, f) => s + f.size, 0))
const overSizeLimit = computed(() => totalBytes.value > MAX_TOTAL_BYTES)

function addFiles(fileList) {
  for (const f of fileList) attachments.value.push(f)
}
function onFileSelect(e) { addFiles(e.target.files); e.target.value = '' }
function onDrop(e) { dragOver.value = false; addFiles(e.dataTransfer.files) }
function removeFile(idx) { attachments.value.splice(idx, 1) }
function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// ── Archive (real) ────────────────────────────────────────────────────
const archiveFrom = ref('')
const archiveTo = ref('')
const archiveSearch = ref('')
const archiveRows = ref([])

function statusLabel(row) {
  if ((row.recipient_count ?? 0) > 1) {
    return `${row.sent_count} of ${row.recipient_count} sent · ${row.error_count} errors`
  }
  const s = row.recipients?.[0]?.status ?? row.status
  return { read: 'Read', delivered: 'Delivered', sent: 'Sent', failed: 'Failed', queued: 'Queued' }[s] ?? s
}

async function fetchArchive() {
  try {
    const { data } = await api.get('/communications', {
      params: { _per_page: 25, _search: archiveSearch.value || undefined },
    })
    archiveRows.value = (data.data ?? []).map(row => ({
      sentDate:  (row.sent_date || '').replace('T', ' ').slice(0, 16),
      community: row.community?.name ?? '',
      subject:   row.subject,
      sentTo:    (row.recipient_count ?? 0) > 1
        ? `${row.recipient_count} recipients`
        : (row.recipients?.[0]?.recipient_email ?? ''),
      sentBy:    row.sent_by_name,
      status:    statusLabel(row),
    }))
  } catch {
    archiveRows.value = []
  }
}

function runArchiveSearch() { fetchArchive() }
const debouncedArchiveSearch = debounce(runArchiveSearch, 300)

onMounted(fetchArchive)

// ── Send (portfolio-wide: fan out to each selected community) ──────────
const sending = ref(false)
const canSend = computed(() =>
  form.value.communityIds.length > 0 &&
  form.value.recipients.length > 0 &&
  form.value.subject.trim() &&
  form.value.body.trim() &&
  bccValid.value &&
  !overSizeLimit.value &&
  !sending.value
)

async function sendCommunication() {
  if (!canSend.value) return
  sending.value = true
  try {
    for (const communityId of form.value.communityIds) {
      const fd = new FormData()
      form.value.recipients.forEach(g => fd.append('recipient_groups[]', g))
      fd.append('subject', form.value.subject)
      fd.append('body', form.value.body)
      if (form.value.bcc.length) fd.append('bcc', form.value.bcc.join(','))
      attachments.value.forEach(f => fd.append('attachments[]', f))
      await api.post(`/communities/${communityId}/communications`, fd)
    }
    success('Communication sent.')
    form.value.subject = ''
    form.value.body = ''
    attachments.value = []
    activeTab.value = 'archive'
    fetchArchive()
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Failed to send communication.')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div>
    <h1 class="mb-5 font-body font-bold text-2xl text-foreground">Communications</h1>

    <!-- Tabs (WeConnectU folder style) -->
    <div class="flex items-end gap-1">
      <button
        v-for="tab in [{ id: 'send', label: 'Send' }, { id: 'archive', label: 'Archive' }]"
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="activeTab === tab.id
          ? '-mb-px rounded-t-lg border border-border border-b-0 bg-white px-6 py-2.5 text-sm font-semibold text-foreground'
          : 'px-6 py-2.5 text-sm text-[#2f6fb0] hover:underline'"
      >{{ tab.label }}</button>
    </div>

    <!-- ── SEND ─────────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'send'" class="rounded-lg rounded-tl-none border border-border bg-white text-card-foreground shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <h3 class="tracking-tight font-body font-semibold text-lg">Send Communication</h3>
      </div>

      <div class="p-6 space-y-5">
        <!-- Communities -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] md:items-center gap-2 md:gap-4">
          <label class="text-sm font-medium text-foreground">Communities</label>
          <div class="flex items-center gap-3">
            <AppMultiSelect
              v-model="form.communityIds"
              heading="Communities"
              :options="communityOptions"
              searchable
              placeholder="Nothing selected"
              class="flex-1"
            />
            <label class="flex items-center gap-2 text-sm text-foreground shrink-0 cursor-pointer">
              <input type="checkbox" v-model="allCommunities" class="w-4 h-4 rounded border-border accent-accent" />
              All
            </label>
          </div>
        </div>

        <!-- Send To -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-2 md:gap-4">
          <label class="text-sm font-medium text-foreground md:pt-1">Send To</label>
          <div>
            <label class="flex items-center gap-2 text-sm font-semibold text-foreground mb-2 cursor-pointer">
              <input type="checkbox" v-model="allRecipients" class="w-4 h-4 rounded border-border accent-accent" />
              All
            </label>
            <div class="grid w-fit grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-1.5">
              <label
                v-for="group in RECIPIENT_GROUPS"
                :key="group.value"
                class="flex items-center gap-2 text-sm text-foreground cursor-pointer"
              >
                <input type="checkbox" :value="group.value" v-model="form.recipients" class="w-4 h-4 rounded border-border accent-accent" />
                {{ group.label }}
              </label>
            </div>
            <div v-if="!showBcc" class="mt-3 flex items-center gap-1.5 text-sm">
              <button
                type="button"
                @click="showBcc = true"
                class="inline-flex items-center gap-1.5 text-accent hover:underline"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                Add BCC
              </button>
              <span class="text-muted-foreground font-normal">(BCC will only receive a copy of the 1st email)</span>
            </div>
            <div v-else class="mt-3 max-w-md">
              <label class="text-sm font-medium text-foreground block mb-1.5">BCC</label>
              <EmailTagsInput v-model="form.bcc" v-model:valid="bccValid" placeholder="bcc@example.com" />
            </div>
          </div>
        </div>

        <!-- Subject -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] md:items-center gap-2 md:gap-4">
          <label class="text-sm font-medium text-foreground">Subject</label>
          <AppInput v-model="form.subject" placeholder="Subject line" />
        </div>

        <!-- Attachments -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-2 md:gap-4">
          <label class="text-sm font-medium text-foreground md:pt-1">Attachments</label>
          <div>
            <div
              @click="fileInput?.click()"
              @dragover.prevent="dragOver = true"
              @dragleave.prevent="dragOver = false"
              @drop.prevent="onDrop"
              :class="[
                'cursor-pointer rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors',
                dragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40',
              ]"
            >
              <p class="text-sm text-muted-foreground">Drag and drop your attachments here</p>
              <p class="text-xs text-muted-foreground/70 mt-1">Total size for all uploaded files must not exceed 7 MB</p>
            </div>
            <input ref="fileInput" type="file" multiple class="hidden" @change="onFileSelect" />
            <AppButton variant="primary" size="sm" class="mt-3" @click="fileInput?.click()">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Click to select attachments
            </AppButton>

            <!-- Selected files -->
            <ul v-if="attachments.length" class="mt-3 space-y-1.5">
              <li v-for="(file, idx) in attachments" :key="idx" class="flex items-center justify-between gap-3 text-sm bg-muted/50 rounded px-3 py-1.5">
                <span class="truncate">{{ file.name }}</span>
                <span class="flex items-center gap-3 shrink-0">
                  <span class="text-xs text-muted-foreground">{{ formatBytes(file.size) }}</span>
                  <button type="button" @click="removeFile(idx)" class="text-muted-foreground hover:text-destructive">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                  </button>
                </span>
              </li>
            </ul>
            <p v-if="overSizeLimit" class="mt-2 text-xs text-destructive">
              Total size {{ formatBytes(totalBytes) }} exceeds the 7 MB limit.
            </p>
          </div>
        </div>

        <!-- Message body -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-2 md:gap-4">
          <label class="text-sm font-medium text-foreground md:pt-1">Message</label>
          <div>
            <textarea
              v-model="form.body"
              rows="10"
              placeholder="Write your message…"
              class="w-full px-4 py-3 text-sm rounded border border-border bg-white outline-none focus:border-accent transition-colors resize-y"
            />
            <p class="mt-1 text-xs text-muted-foreground">Characters: {{ charCount }}</p>
          </div>
        </div>

        <!-- Send -->
        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-2 md:gap-4">
          <span class="hidden md:block"></span>
          <div>
            <AppButton variant="primary" :disabled="!canSend" @click="sendCommunication">
              Send Communication
            </AppButton>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ARCHIVE ──────────────────────────────────────────────────── -->
    <div v-else class="rounded-lg rounded-tl-none border border-border bg-white text-card-foreground shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <h3 class="tracking-tight font-body font-semibold text-lg">Archive</h3>
      </div>

      <div class="p-6 space-y-5">
        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4">
          <div class="w-full sm:w-auto">
            <AppInput v-model="archiveFrom" type="date" label="Date from" />
          </div>
          <div class="w-full sm:w-auto">
            <AppInput v-model="archiveTo" type="date" label="Date to" />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input
            v-model="archiveSearch"
            type="text"
            placeholder="Search…"
            class="h-9 w-full sm:w-64 px-3 text-sm bg-white border border-border rounded outline-none focus:border-accent transition-colors"
            @input="debouncedArchiveSearch"
          />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto border border-border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border bg-muted/40 text-left">
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent Date</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Community</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Subject</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent To</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent By</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Status</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground text-center">Resend</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground text-center">Download</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in archiveRows" :key="idx" class="border-b border-border last:border-0">
                <td class="px-4 py-3 whitespace-nowrap">{{ row.sentDate }}</td>
                <td class="px-4 py-3">{{ row.community }}</td>
                <td class="px-4 py-3 text-accent">{{ row.subject }}</td>
                <td class="px-4 py-3">{{ row.sentTo }}</td>
                <td class="px-4 py-3">{{ row.sentBy }}</td>
                <td class="px-4 py-3">{{ row.status }}</td>
                <td class="px-4 py-3 text-center"></td>
                <td class="px-4 py-3 text-center"></td>
              </tr>
              <tr v-if="!archiveRows.length">
                <td colspan="8" class="px-4 py-12 text-center text-sm text-muted-foreground">
                  No communications yet. Sent messages will appear here once sending is enabled.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
