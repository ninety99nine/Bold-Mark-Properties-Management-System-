<!--
  CommunityCommunicate — community Communicate tab (WeConnectU parity).
  Three sub-tabs: Send · Archive · Settings.
  - Send: recipient groups, BCC, type, subject (community-prefixed), attachments,
          rich-text body → POST /communities/{id}/communications
  - Archive: sent history with status/progress, Resend + Download (email-view)
  - Settings: per-community email header logo + header/footer images
-->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useOrganizationStore } from '@/stores/organization'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import EmailTagsInput from '@/components/common/EmailTagsInput.vue'
import RichTextEditor from '@/components/common/RichTextEditor.vue'

const props = defineProps({
  communityId:   { type: String, required: true },
  communityName: { type: String, default: '' },
})

const { success: toastSuccess, error: toastError, info } = useToast()
const authStore = useAuthStore()
const organizationStore = useOrganizationStore()

const activeTab = ref('send') // 'send' | 'archive' | 'settings'
const TABS = [
  { id: 'send', label: 'Send' },
  { id: 'archive', label: 'Archive' },
  { id: 'settings', label: 'Settings' },
]

const subjectPrefix = computed(() => (props.communityName || '').toUpperCase())

// ── Send From options: company outgoing email + current user's email ────
const fromOptions = computed(() => {
  const opts = []
  const outgoing = organizationStore.details?.outgoing_email
  if (outgoing) opts.push({ value: outgoing, label: outgoing })
  const me = authStore.user?.email
  if (me && me !== outgoing) opts.push({ value: me, label: me })
  if (!opts.length) opts.push({ value: '', label: 'noreply@boldmarkprop.co.za' })
  return opts
})

// ── Send form state ─────────────────────────────────────────────────────
const RECIPIENT_GROUPS = [
  { value: 'owners',           label: 'Owners' },
  { value: 'directors',        label: 'Directors/Trustees' },
  { value: 'complex_managers', label: 'Complex Manager/s' },
  { value: 'rental_agents',    label: 'Rental Agents' },
  { value: 'attorneys',        label: 'Attorneys / Additional' },
  { value: 'occupants',        label: 'Occupants' },
  { value: 'bondholders',      label: 'Bondholders' },
]
const TYPE_OPTS = [
  { value: 'mail', label: 'Mail' },
  { value: 'sms',  label: 'SMS' },
]

const form = ref({ from: '', groups: [], subject: '', body: '', type: 'mail' })
const bcc = ref([])
const bccValid = ref(true)
const showBcc = ref(false)
const attachments = ref([])
const fileInput = ref(null)
const dragOver = ref(false)
const sending = ref(false)

const allRecipients = computed({
  get: () => form.value.groups.length === RECIPIENT_GROUPS.length,
  set: (v) => { form.value.groups = v ? RECIPIENT_GROUPS.map(g => g.value) : [] },
})

const MAX_TOTAL_BYTES = 7 * 1024 * 1024
const totalBytes = computed(() => attachments.value.reduce((s, f) => s + f.size, 0))
const overSizeLimit = computed(() => totalBytes.value > MAX_TOTAL_BYTES)

function addFiles(list) { for (const f of list) attachments.value.push(f) }
function onFileSelect(e) { addFiles(e.target.files); e.target.value = '' }
function onDrop(e) { dragOver.value = false; addFiles(e.dataTransfer.files) }
function removeFile(i) { attachments.value.splice(i, 1) }
function formatBytes(b) {
  if (b < 1024) return `${b} B`
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(0)} KB`
  return `${(b / (1024 * 1024)).toFixed(1)} MB`
}

// ── Type (Mail vs SMS) ──────────────────────────────────────────────────
const isSms = computed(() => form.value.type === 'sms')
const SMS_MAX = 450
const smsCount = computed(() => form.value.body.length)
const smsParts = computed(() => {
  const n = smsCount.value
  if (n === 0) return 1
  return n <= 160 ? 1 : Math.ceil(n / 153)
})

// Reset the body when switching message type so email HTML doesn't leak into
// the plain-text SMS field (and vice-versa).
watch(() => form.value.type, () => { form.value.body = '' })

const canSend = computed(() => {
  if (form.value.groups.length === 0 || sending.value) return false
  if (isSms.value) return !!form.value.body.trim()
  return !!form.value.subject.trim() && bccValid.value && !overSizeLimit.value
})

async function sendCommunication() {
  if (!canSend.value) return
  sending.value = true
  try {
    const fd = new FormData()
    if (form.value.from) fd.append('from_email', form.value.from)
    fd.append('type', form.value.type)
    form.value.groups.forEach(g => fd.append('recipient_groups[]', g))
    fd.append('subject', `${subjectPrefix.value} - ${form.value.subject}`.trim())
    fd.append('body', form.value.body || '')
    if (bcc.value.length) fd.append('bcc', bcc.value.join(','))
    attachments.value.forEach(f => fd.append('attachments[]', f))

    await api.post(`/communities/${props.communityId}/communications`, fd)
    toastSuccess('Communication sent.')
    form.value = { from: form.value.from, groups: [], subject: '', body: '', type: 'mail' }
    bcc.value = []
    showBcc.value = false
    attachments.value = []
    activeTab.value = 'archive'
    loadArchive(1)
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Failed to send communication.')
  } finally {
    sending.value = false
  }
}

// ── Archive ─────────────────────────────────────────────────────────────
const archive = ref([])
const archiveLoading = ref(false)
const archiveSearch = ref('')
const page = ref(1)
const lastPage = ref(1)

async function loadArchive(p = 1) {
  archiveLoading.value = true
  try {
    const { data } = await api.get(`/communities/${props.communityId}/communications`, {
      params: { page: p, _per_page: 20, _search: archiveSearch.value || undefined },
    })
    archive.value = data.data ?? []
    page.value = data.meta?.current_page ?? data.current_page ?? p
    lastPage.value = data.meta?.last_page ?? data.last_page ?? 1
  } catch {
    archive.value = []
  } finally {
    archiveLoading.value = false
  }
}
const debouncedArchiveSearch = debounce(() => loadArchive(1), 300)

function statusLabel(s) {
  return { read: 'Read', delivered: 'Delivered', sent: 'Sent', failed: 'Failed', queued: 'Queued' }[s] ?? s
}
function formatDate(dt) {
  if (!dt) return ''
  return String(dt).replace('T', ' ').slice(0, 16)
}
function viewUrl(token) {
  const base = import.meta.env.VITE_API_URL || ''
  return `${base}/communications/view/${token}`
}
function downloadRow(row) {
  const token = row.recipients?.[0]?.view_token
  if (token) window.open(viewUrl(token), '_blank')
}
async function resendRow(row) {
  try {
    await api.post(`/communities/${props.communityId}/communications/${row.id}/resend`)
    toastSuccess('Communication resent.')
    loadArchive(page.value)
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Resend failed.')
  }
}

// ── Settings (per-community email branding) ─────────────────────────────
const settings = ref({ email_logo_url: null, email_header_url: null, email_footer_url: null })
const logoInput = ref(null)
const headerInput = ref(null)
const footerInput = ref(null)

async function loadSettings() {
  try {
    const { data } = await api.get(`/communities/${props.communityId}/email-settings`)
    const c = data.data ?? data
    settings.value = {
      email_logo_url: c.email_logo_url ?? null,
      email_header_url: c.email_header_url ?? null,
      email_footer_url: c.email_footer_url ?? null,
    }
  } catch { /* ignore */ }
}
async function uploadSetting(field, file) {
  const fd = new FormData()
  fd.append(field, file)
  const { data } = await api.post(`/communities/${props.communityId}/email-settings`, fd)
  const c = data.data ?? data
  settings.value = {
    email_logo_url: c.email_logo_url ?? null,
    email_header_url: c.email_header_url ?? null,
    email_footer_url: c.email_footer_url ?? null,
  }
}
async function onSettingSelect(field, e) {
  const f = e.target.files?.[0]; e.target.value = ''
  if (!f) return
  try { await uploadSetting(field, f); toastSuccess('Communication settings updated.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Upload failed.') }
}
async function removeSetting(flag) {
  try {
    await api.post(`/communities/${props.communityId}/email-settings`, { [flag]: true })
    await loadSettings()
    toastSuccess('Removed.')
  } catch (err) { toastError(err?.response?.data?.message ?? 'Remove failed.') }
}

// Per-slot drag-and-drop for the email-branding images.
const settingDragOver = ref(null)
async function onSettingDrop(field, e) {
  settingDragOver.value = null
  const f = e.dataTransfer?.files?.[0]
  if (!f) return
  if (!/^image\/(png|jpe?g)$/.test(f.type)) { toastError('Please drop a PNG or JPG image.'); return }
  try { await uploadSetting(field, f); toastSuccess('Communication settings updated.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Upload failed.') }
}

onMounted(() => {
  if (!organizationStore.loaded) organizationStore.fetchOrganization()
  loadArchive(1)
  loadSettings()
})
</script>

<template>
  <div>
    <h2 class="mb-5 font-body font-bold text-2xl text-foreground">Communications</h2>

    <!-- Sub-tabs (WeConnectU folder style) -->
    <div class="flex items-end gap-1">
      <button
        v-for="t in TABS"
        :key="t.id"
        @click="activeTab = t.id"
        :class="activeTab === t.id
          ? '-mb-px rounded-t-lg border border-border border-b-0 bg-white px-6 py-2.5 text-sm font-semibold text-foreground'
          : 'px-6 py-2.5 text-sm text-[#2f6fb0] hover:underline'"
      >{{ t.label }}</button>
    </div>

    <!-- ── SEND ── -->
    <div v-if="activeTab === 'send'" class="rounded-lg rounded-tl-none border border-border bg-white shadow-sm">
      <div class="px-6 py-3 border-b border-border">
        <h3 class="font-body font-semibold text-lg">Send Communication</h3>
      </div>
      <div class="p-5 space-y-3.5">
        <!-- Send From -->
        <div class="grid grid-cols-1 md:grid-cols-[150px_1fr] md:items-center gap-2 md:gap-3">
          <label class="text-sm font-medium text-foreground">Send From</label>
          <div class="max-w-md"><AppSelect v-model="form.from" :options="fromOptions" /></div>
        </div>

        <!-- Send To -->
        <div class="grid grid-cols-1 md:grid-cols-[150px_1fr] gap-2 md:gap-3">
          <label class="text-sm font-medium text-foreground md:pt-1">Send To</label>
          <div>
            <label class="flex items-center gap-2 text-sm font-semibold text-foreground mb-2 cursor-pointer">
              <input type="checkbox" v-model="allRecipients" class="w-4 h-4 rounded border-border accent-accent" /> All
            </label>
            <div class="grid w-fit grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-1.5">
              <label v-for="g in RECIPIENT_GROUPS" :key="g.value" class="flex items-center gap-2 text-sm text-foreground cursor-pointer">
                <input type="checkbox" :value="g.value" v-model="form.groups" class="w-4 h-4 rounded border-border accent-accent" />
                {{ g.label }}
              </label>
            </div>
            <div v-if="!showBcc" class="mt-3 flex items-center gap-1.5 text-sm">
              <button type="button" @click="showBcc = true" class="inline-flex items-center gap-1.5 text-accent hover:underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                Add BCC
              </button>
              <span class="text-muted-foreground font-normal">(BCC will only receive a copy of the 1st email)</span>
            </div>
            <div v-else class="mt-3 max-w-md">
              <label class="text-sm font-medium text-foreground block mb-1.5">BCC</label>
              <EmailTagsInput v-model="bcc" v-model:valid="bccValid" placeholder="bcc@example.com" />
            </div>
          </div>
        </div>

        <!-- Type -->
        <div class="grid grid-cols-1 md:grid-cols-[150px_1fr] md:items-center gap-2 md:gap-3">
          <label class="text-sm font-medium text-foreground">Type</label>
          <div class="w-40"><AppSelect v-model="form.type" :options="TYPE_OPTS" /></div>
        </div>

        <!-- Subject -->
        <div v-if="!isSms" class="grid grid-cols-1 md:grid-cols-[150px_1fr] md:items-center gap-2 md:gap-3">
          <label class="text-sm font-medium text-foreground">Subject</label>
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-foreground whitespace-nowrap">{{ subjectPrefix }} -</span>
            <input v-model="form.subject" type="text" class="flex-1 h-10 rounded-md border border-border bg-white px-3 text-sm outline-none focus:border-accent" />
          </div>
        </div>

        <!-- Attachments -->
        <div v-if="!isSms" class="grid grid-cols-1 md:grid-cols-[150px_1fr] gap-2 md:gap-3">
          <label class="text-sm font-medium text-foreground md:pt-1">Attachments</label>
          <div>
            <div
              @click="fileInput?.click()"
              @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop"
              :class="['cursor-pointer rounded-lg border-2 border-dashed px-6 py-6 text-center transition-colors', dragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
            >
              <p class="text-sm text-muted-foreground">Drag and drop your attachments here</p>
              <p class="text-xs text-muted-foreground/70 mt-1">Total size for all uploaded files must not exceed 7 MB</p>
            </div>
            <input ref="fileInput" type="file" multiple class="hidden" @change="onFileSelect" />
            <AppButton variant="primary" size="sm" class="mt-3" @click="fileInput?.click()">Click to select attachments</AppButton>
            <ul v-if="attachments.length" class="mt-3 space-y-1.5">
              <li v-for="(f, i) in attachments" :key="i" class="flex items-center justify-between gap-3 text-sm bg-muted/50 rounded px-3 py-1.5">
                <span class="truncate">{{ f.name }}</span>
                <span class="flex items-center gap-3 shrink-0">
                  <span class="text-xs text-muted-foreground">{{ formatBytes(f.size) }}</span>
                  <button type="button" @click="removeFile(i)" class="text-muted-foreground hover:text-destructive">✕</button>
                </span>
              </li>
            </ul>
            <p v-if="overSizeLimit" class="mt-2 text-xs text-destructive">Total size {{ formatBytes(totalBytes) }} exceeds the 7 MB limit.</p>
          </div>
        </div>

        <!-- Body (Mail) -->
        <div v-if="!isSms">
          <p class="text-sm font-semibold text-foreground mb-2">Enter communication content below:</p>
          <RichTextEditor v-model="form.body" min-height="240px" />
        </div>

        <!-- Body (SMS) -->
        <div v-else>
          <p class="text-sm font-semibold text-foreground">Enter communication content below:</p>
          <p class="text-sm text-foreground mt-0.5">SMS's will be charged per SMS part.</p>
          <p class="text-xs text-muted-foreground mb-2">Long messages are sent as a single message, but broken up into multiple parts.</p>
          <textarea
            v-model="form.body"
            :maxlength="SMS_MAX"
            rows="8"
            class="w-full resize-y rounded-md border border-border bg-white px-3 py-2 text-sm outline-none focus:border-accent"
          />
          <p class="mt-1 text-sm text-muted-foreground">
            Characters: {{ smsCount }} (Max: {{ SMS_MAX }})<span class="mx-4"></span>Amount of SMS's: {{ smsParts }}
          </p>
        </div>

        <div>
          <AppButton variant="primary" class="!bg-navy" :disabled="!canSend" :loading="sending" @click="sendCommunication">Send Communication</AppButton>
        </div>
      </div>
    </div>

    <!-- ── ARCHIVE ── -->
    <div v-else-if="activeTab === 'archive'" class="rounded-lg rounded-tl-none border border-border bg-white shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <h3 class="font-body font-semibold text-lg">Communication Archive</h3>
        <p class="text-sm text-muted-foreground mt-1">Below is the full list of communication sent to this community's user groups.</p>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex items-center gap-2">
          <input v-model="archiveSearch" type="text" placeholder="Search…" class="h-9 w-full sm:w-64 px-3 text-sm bg-white border border-border rounded outline-none focus:border-accent" @input="debouncedArchiveSearch" />
        </div>

        <div class="overflow-x-auto border border-border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border bg-muted/40 text-left">
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent Date</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Subject</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent To</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Sent By</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground">Status</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground text-center">Resend</th>
                <th class="px-4 py-3 font-semibold text-xs uppercase tracking-wide text-muted-foreground text-center">Download</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in archive" :key="row.id" class="border-b border-border last:border-0 align-top">
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.sent_date) }}</td>
                <td class="px-4 py-3 text-accent">{{ row.subject }}</td>
                <td class="px-4 py-3">
                  <span v-if="row.is_bulk" class="inline-flex items-center rounded-full bg-accent/15 text-accent px-2.5 py-0.5 text-xs font-semibold">
                    {{ row.recipient_count }} RECIPIENTS
                  </span>
                  <span v-else-if="row.recipients?.[0]">
                    <span class="font-medium">{{ row.recipients[0].recipient_name }}</span>
                    <br><span class="text-xs text-muted-foreground">{{ row.recipients[0].recipient_email }}</span>
                  </span>
                </td>
                <td class="px-4 py-3">{{ row.sent_by_name }}<br><span class="text-xs text-muted-foreground">{{ row.from_email }}</span></td>
                <td class="px-4 py-3">
                  <template v-if="row.is_bulk">
                    <div class="w-40">
                      <div class="h-1.5 rounded bg-muted overflow-hidden flex">
                        <div class="bg-green-500 h-full" :style="{ width: (row.recipient_count ? (row.sent_count / row.recipient_count * 100) : 0) + '%' }"></div>
                        <div class="bg-red-500 h-full" :style="{ width: (row.recipient_count ? (row.error_count / row.recipient_count * 100) : 0) + '%' }"></div>
                      </div>
                      <p class="text-xs text-muted-foreground mt-1">{{ row.sent_count }} of {{ row.recipient_count }} sent. <strong>{{ row.error_count }} errors</strong></p>
                    </div>
                  </template>
                  <span v-else>{{ statusLabel(row.recipients?.[0]?.status ?? row.status) }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button v-if="!row.is_bulk" type="button" @click="resendRow(row)" title="Resend" class="text-accent hover:opacity-70">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                  </button>
                </td>
                <td class="px-4 py-3 text-center">
                  <button v-if="!row.is_bulk" type="button" @click="downloadRow(row)" title="Download" class="text-green-600 hover:opacity-70">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!archiveLoading && !archive.length">
                <td colspan="7" class="px-4 py-12 text-center text-sm text-muted-foreground">No communications yet.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="lastPage > 1" class="flex items-center gap-3">
          <AppButton variant="outline" size="sm" :disabled="page <= 1" @click="loadArchive(page - 1)">‹</AppButton>
          <AppButton variant="outline" size="sm" :disabled="page >= lastPage" @click="loadArchive(page + 1)">›</AppButton>
          <span class="text-sm text-muted-foreground">Page {{ page }} of {{ lastPage }}</span>
        </div>
      </div>
    </div>

    <!-- ── SETTINGS ── -->
    <div v-else class="rounded-lg rounded-tl-none border border-border bg-white shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <h3 class="font-body font-semibold text-lg">Communications Settings</h3>
      </div>
      <div class="p-6 space-y-8 max-w-2xl">
        <!-- Header Logo -->
        <div>
          <h4 class="text-base font-semibold text-foreground">Header Logo</h4>
          <p class="text-sm text-muted-foreground mt-1">Please select a file for your logo.</p>
          <p class="text-sm text-muted-foreground mt-1">The file should be in <strong>PNG or JPG</strong> format and will be cropped to fit where necessary.</p>
          <p class="text-sm text-muted-foreground mt-1">As a guideline, the logo should be wider than taller, for example, and optimally <strong>600 x 200</strong> if possible.</p>
          <hr class="border-border my-4" />
          <input ref="logoInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onSettingSelect('email_logo', $event)" />

          <!-- Empty: dashed drop zone (matches the email header/footer slots) -->
          <div
            v-if="!settings.email_logo_url"
            @click="logoInput?.click()"
            @dragover.prevent="settingDragOver = 'email_logo'"
            @dragleave.prevent="settingDragOver = null"
            @drop.prevent="onSettingDrop('email_logo', $event)"
            :class="['group cursor-pointer rounded-md border-2 border-dashed px-4 py-10 text-center transition-colors', settingDragOver === 'email_logo' ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
          >
            <span class="text-sm text-accent group-hover:underline">Click to upload logo</span>
            <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop a PNG/JPG here</p>
          </div>

          <!-- Uploaded: preview + remove -->
          <template v-else>
            <div
              @dragover.prevent="settingDragOver = 'email_logo'"
              @dragleave.prevent="settingDragOver = null"
              @drop.prevent="onSettingDrop('email_logo', $event)"
              :class="['h-40 flex items-center justify-center overflow-hidden rounded-md border transition-colors', settingDragOver === 'email_logo' ? 'border-2 border-accent bg-accent/5' : 'border-border bg-muted/40']"
            >
              <img :src="settings.email_logo_url" alt="Header logo" class="max-h-full max-w-full object-contain" />
            </div>
            <div class="flex items-center justify-between mt-3">
              <div><p class="text-sm font-semibold">Header Logo</p><p class="text-xs italic text-muted-foreground">Preferably Hi-Res</p></div>
              <button type="button" class="text-sm text-destructive hover:underline" @click="removeSetting('remove_email_logo')">Click to remove logo</button>
            </div>
          </template>
        </div>

        <hr class="border-border" />

        <!-- Email header and footer -->
        <div>
          <h4 class="text-base font-semibold text-foreground">Email header and footer</h4>
          <p class="text-sm text-muted-foreground mt-1">Please select a file for your default mail header and footer.</p>
          <p class="text-sm text-muted-foreground mt-1">The file should be in <strong>PNG or JPG</strong> format and will be cropped to a width of <strong>600</strong> pixels.</p>
          <div class="grid grid-cols-2 gap-4 text-sm mt-4">
            <div>
              <input ref="headerInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onSettingSelect('email_header', $event)" />
              <div
                @click="!settings.email_header_url && headerInput?.click()"
                @dragover.prevent="settingDragOver = 'email_header'"
                @dragleave.prevent="settingDragOver = null"
                @drop.prevent="onSettingDrop('email_header', $event)"
                :class="['group rounded-md border-2 border-dashed px-4 py-4 text-center transition-colors', settingDragOver === 'email_header' ? 'border-accent bg-accent/5' : 'border-border', !settings.email_header_url ? 'cursor-pointer hover:bg-muted/40' : '']"
              >
                <span v-if="!settings.email_header_url" class="text-accent group-hover:underline">Click to select email header</span>
                <button v-else type="button" class="text-destructive hover:underline" @click.stop="removeSetting('remove_email_header')">Click to remove email header</button>
                <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop a PNG/JPG here</p>
              </div>
            </div>
            <div>
              <input ref="footerInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onSettingSelect('email_footer', $event)" />
              <div
                @click="!settings.email_footer_url && footerInput?.click()"
                @dragover.prevent="settingDragOver = 'email_footer'"
                @dragleave.prevent="settingDragOver = null"
                @drop.prevent="onSettingDrop('email_footer', $event)"
                :class="['group rounded-md border-2 border-dashed px-4 py-4 text-center transition-colors', settingDragOver === 'email_footer' ? 'border-accent bg-accent/5' : 'border-border', !settings.email_footer_url ? 'cursor-pointer hover:bg-muted/40' : '']"
              >
                <span v-if="!settings.email_footer_url" class="text-accent group-hover:underline">Click to select email footer</span>
                <button v-else type="button" class="text-destructive hover:underline" @click.stop="removeSetting('remove_email_footer')">Click to remove email footer</button>
                <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop a PNG/JPG here</p>
              </div>
            </div>
          </div>

          <!-- Preview -->
          <div class="mt-6 border border-border rounded-md overflow-hidden">
            <img v-if="settings.email_header_url" :src="settings.email_header_url" alt="Email header" class="w-full object-contain" />
            <div class="p-5 text-sm space-y-3">
              <p><strong>ATT: {{ organizationStore.name }}</strong></p>
              <p>This is what an email would look like with a custom header and footer.</p>
              <p>If you do not see any header or footer, please click on the buttons above to select and upload your images.</p>
              <p>Kind regards,<br><strong>{{ organizationStore.name }}</strong></p>
            </div>
            <img v-if="settings.email_footer_url" :src="settings.email_footer_url" alt="Email footer" class="w-full object-contain" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
