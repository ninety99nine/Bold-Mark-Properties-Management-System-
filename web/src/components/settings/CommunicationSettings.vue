<!--
  CommunicationSettings — global Settings → Communication (WeConnectU parity).
  Left: default email header/footer (upload/remove) + live preview + DNS link.
  Right: message-template manager (9 email + 3 SMS) with per-template editor modal.
-->
<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useOrganizationStore } from '@/stores/organization'
import AppModal from '@/components/common/AppModal.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import RichTextEditor from '@/components/common/RichTextEditor.vue'

const { success: toastSuccess, error: toastError } = useToast()
const organizationStore = useOrganizationStore()

// ── Default email header / footer ──────────────────────────────────────
const emailHeaderUrl = ref(null)
const emailFooterUrl = ref(null)
const headerInput = ref(null)
const footerInput = ref(null)
const companyName = computed(() => organizationStore.name || 'Bold Mark Properties')

async function loadOrganization() {
  try {
    const { data } = await api.get('/organization')
    const o = data.data ?? data
    emailHeaderUrl.value = o.email_header_url ?? null
    emailFooterUrl.value = o.email_footer_url ?? null
  } catch { /* ignore */ }
}

async function uploadImage(field, file) {
  const fd = new FormData()
  fd.append('_method', 'PUT')
  fd.append(field, file)
  const { data } = await api.post('/organization', fd)
  return data.data ?? data
}

function pickHeader() { headerInput.value?.click() }
function pickFooter() { footerInput.value?.click() }

async function onHeaderSelect(e) {
  const f = e.target.files?.[0]; e.target.value = ''
  if (!f) return
  try { const o = await uploadImage('email_header', f); emailHeaderUrl.value = o.email_header_url; toastSuccess('Email header updated.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Upload failed.') }
}
async function onFooterSelect(e) {
  const f = e.target.files?.[0]; e.target.value = ''
  if (!f) return
  try { const o = await uploadImage('email_footer', f); emailFooterUrl.value = o.email_footer_url; toastSuccess('Email footer updated.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Upload failed.') }
}
async function removeHeader() {
  try { await api.put('/organization', { remove_email_header: true }); emailHeaderUrl.value = null; toastSuccess('Email header removed.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Remove failed.') }
}
async function removeFooter() {
  try { await api.put('/organization', { remove_email_footer: true }); emailFooterUrl.value = null; toastSuccess('Email footer removed.') }
  catch (err) { toastError(err?.response?.data?.message ?? 'Remove failed.') }
}

// ── Message templates ──────────────────────────────────────────────────
const templates = ref([])
const templatesLoading = ref(false)

const TAGS = [
  '[Customer Name]', '[Customer Code]', '[Community Name]', '[Balance]',
  '[Interest Percent]', '[Interest Period]', '[Fine Amount]', '[Admin Fee]',
  '[Days Label]', '[Unit Address]', '[Attorney Name]', '[Attorney Email]', '[Attorney Number]',
]
const TAG_OPTS = TAGS.map(t => ({ value: t, label: t }))
const TERMS_KEYS = ['fine', 'penalty', 'warning']

async function loadTemplates() {
  templatesLoading.value = true
  try {
    const { data } = await api.get('/message-templates')
    templates.value = data.data ?? []
  } catch (err) {
    toastError('Failed to load message templates.')
  } finally {
    templatesLoading.value = false
  }
}

// ── Editor modal ───────────────────────────────────────────────────────
const showEditor = ref(false)
const editing = ref(null)         // the template being edited
const form = ref({ subject: '', body: '', terms: '' })
const saving = ref(false)
const insertTag = ref('')

const isSms = computed(() => editing.value?.type === 'sms')
const hasTerms = computed(() => editing.value && TERMS_KEYS.includes(editing.value.key))
const smsCharCount = computed(() => (form.value.body || '').length)
const smsSegments = computed(() => Math.max(1, Math.ceil(smsCharCount.value / 160)))

function openEditor(t) {
  editing.value = t
  form.value = { subject: t.subject ?? '', body: t.body ?? '', terms: t.terms ?? '' }
  insertTag.value = ''
  showEditor.value = true
}
function closeEditor() { showEditor.value = false; editing.value = null }

function onInsertTag(tag) {
  if (!tag) return
  // Insert at the cursor of whichever editor is focused; fall back to appending.
  const ok = document.execCommand && document.execCommand('insertText', false, tag)
  if (!ok && isSms.value) form.value.body += tag
  insertTag.value = ''
}

async function saveTemplate() {
  if (!editing.value || saving.value) return
  saving.value = true
  try {
    const payload = { subject: form.value.subject, body: form.value.body }
    if (hasTerms.value) payload.terms = form.value.terms
    const { data } = await api.put(`/message-templates/${editing.value.id}`, payload)
    const updated = data.data ?? data
    const idx = templates.value.findIndex(t => t.id === updated.id)
    if (idx !== -1) templates.value[idx] = updated
    toastSuccess('Message saved.')
    closeEditor()
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Save failed.')
  } finally {
    saving.value = false
  }
}

async function resetTemplate() {
  if (!editing.value || saving.value) return
  saving.value = true
  try {
    const { data } = await api.post(`/message-templates/${editing.value.id}/reset`)
    const updated = data.data ?? data
    const idx = templates.value.findIndex(t => t.id === updated.id)
    if (idx !== -1) templates.value[idx] = updated
    form.value = { subject: updated.subject ?? '', body: updated.body ?? '', terms: updated.terms ?? '' }
    editing.value = updated
    toastSuccess('Template reset to default.')
  } catch (err) {
    toastError(err?.response?.data?.message ?? 'Reset failed.')
  } finally {
    saving.value = false
  }
}

// ── DNS help modal ─────────────────────────────────────────────────────
const showDns = ref(false)

onMounted(() => { loadOrganization(); loadTemplates() })
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- ── Left: Default Email Header & footer ── -->
    <div class="space-y-6">
      <div class="rounded-lg border bg-card shadow-sm p-6">
        <h3 class="text-base font-semibold text-foreground">Default Email Header &amp; footer</h3>
        <p class="text-sm text-muted-foreground mt-2">Please select a file for your default mail header and footer.</p>
        <p class="text-sm text-muted-foreground mt-2">
          The file should be in <strong>PNG or JPG</strong> format and will be cropped to a width of <strong>600</strong> pixels.
          Please ensure that an appropriate file is uploaded.
        </p>

        <hr class="border-border my-4" />

        <div class="grid grid-cols-2 gap-4 text-sm">
          <div class="space-y-2">
            <input ref="headerInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onHeaderSelect" />
            <button type="button" class="flex items-center gap-1.5 text-accent hover:underline" @click="pickHeader">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Click to select email header
            </button>
            <button type="button" class="flex items-center gap-1.5 text-destructive hover:underline" @click="removeHeader">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
              Click to remove email header
            </button>
          </div>
          <div class="space-y-2">
            <input ref="footerInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onFooterSelect" />
            <button type="button" class="flex items-center gap-1.5 text-accent hover:underline" @click="pickFooter">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Click to select email footer
            </button>
            <button type="button" class="flex items-center gap-1.5 text-destructive hover:underline" @click="removeFooter">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
              Click to remove email footer
            </button>
          </div>
        </div>

        <!-- Live preview -->
        <div class="mt-6 border border-border rounded-md overflow-hidden">
          <img v-if="emailHeaderUrl" :src="emailHeaderUrl" alt="Email header" class="w-full object-contain" />
          <div class="p-5 text-sm text-foreground space-y-3">
            <p><strong>ATT: {{ companyName }}</strong></p>
            <p>This is what an email would look like with a custom header and footer.</p>
            <p>If you do not see any header or footer, please click on the buttons above to select and upload your images.</p>
            <p>Kind regards,<br><strong>{{ companyName }}</strong></p>
          </div>
          <img v-if="emailFooterUrl" :src="emailFooterUrl" alt="Email footer" class="w-full object-contain" />
        </div>

        <button type="button" class="mt-4 text-sm text-accent hover:underline" @click="showDns = true">
          Click here to see the DNS entries to improve email deliverability
        </button>
      </div>
    </div>

    <!-- ── Right: Message templates ── -->
    <div class="rounded-lg border bg-card shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border bg-muted/40 text-left">
            <th class="px-5 py-3 font-semibold text-muted-foreground">Description</th>
            <th class="px-5 py-3 font-semibold text-muted-foreground">Type</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="t in templates"
            :key="t.id"
            class="border-b border-border last:border-0 hover:bg-muted/40 cursor-pointer transition-colors"
            @click="openEditor(t)"
          >
            <td class="px-5 py-3">
              <span class="inline-flex items-center gap-2.5">
                <svg v-if="t.type === 'email'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3aa0c9" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3aa0c9" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                <span class="font-medium text-foreground">{{ t.name }}</span>
              </span>
            </td>
            <td class="px-5 py-3 text-muted-foreground">{{ t.type === 'email' ? 'Email' : 'SMS' }}</td>
          </tr>
          <tr v-if="!templatesLoading && !templates.length">
            <td colspan="2" class="px-5 py-10 text-center text-muted-foreground">No templates found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Editor modal ── -->
    <AppModal :show="showEditor" size="lg" @close="closeEditor">
      <template #header>
        <h3 class="text-base font-bold text-navy w-full text-center">{{ editing?.name }}</h3>
      </template>

      <div v-if="editing" class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div class="w-52">
            <AppSelect v-model="insertTag" :options="TAG_OPTS" placeholder="Insert Tag..." @update:modelValue="onInsertTag" />
          </div>
          <button type="button" class="inline-flex items-center gap-1.5 text-sm text-accent hover:underline" @click="resetTemplate">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Reset to default
          </button>
        </div>

        <!-- Subject (email only) -->
        <input
          v-if="!isSms"
          v-model="form.subject"
          type="text"
          placeholder="Subject"
          class="w-full h-10 rounded-md border border-border bg-white px-3 text-sm outline-none focus:border-accent"
        />

        <!-- Body -->
        <template v-if="isSms">
          <textarea
            v-model="form.body"
            rows="7"
            maxlength="450"
            class="w-full px-3 py-2 text-sm rounded border border-border bg-white outline-none focus:border-accent resize-y"
          />
          <p class="text-xs text-muted-foreground">Characters: {{ smsCharCount }} (Max: 450) &nbsp;&nbsp; Amount of SMS's: {{ smsSegments }}</p>
        </template>
        <RichTextEditor v-else v-model="form.body" />

        <!-- Terms & Conditions (fine / penalty / warning) -->
        <template v-if="hasTerms">
          <p class="text-sm font-medium text-foreground pt-2">Terms and Conditions</p>
          <RichTextEditor v-model="form.terms" />
        </template>
      </div>

      <template #footer>
        <AppButton variant="primary" :loading="saving" @click="saveTemplate">Save Message</AppButton>
      </template>
    </AppModal>

    <!-- ── DNS help modal ── -->
    <AppModal :show="showDns" size="lg" @close="showDns = false">
      <template #header>
        <h3 class="text-base font-bold text-navy w-full text-center">DNS records for email deliverability</h3>
      </template>
      <div class="space-y-4 text-sm text-foreground">
        <p>To maximise deliverability, add the following records to your sending domain's DNS. Your email provider (Resend) supplies the exact values in your account dashboard.</p>
        <div class="rounded-md border border-border overflow-hidden">
          <table class="w-full">
            <thead><tr class="bg-muted/40 text-left"><th class="px-3 py-2">Type</th><th class="px-3 py-2">Purpose</th></tr></thead>
            <tbody>
              <tr class="border-t border-border"><td class="px-3 py-2 font-mono">TXT (SPF)</td><td class="px-3 py-2">Authorises the sending servers for your domain.</td></tr>
              <tr class="border-t border-border"><td class="px-3 py-2 font-mono">CNAME/TXT (DKIM)</td><td class="px-3 py-2">Cryptographically signs your outgoing mail.</td></tr>
              <tr class="border-t border-border"><td class="px-3 py-2 font-mono">TXT (DMARC)</td><td class="px-3 py-2">Tells receivers how to handle unauthenticated mail.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <template #footer>
        <AppButton variant="primary" @click="showDns = false">Close</AppButton>
      </template>
    </AppModal>
  </div>
</template>
