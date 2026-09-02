<template>
  <AppModal :show="show" size="lg" @close="handleClose">
    <template #header>
      <h3 class="text-base font-bold text-[#1E2740] w-full text-center" style="font-family: 'DM Sans', sans-serif">Send E-Mail</h3>
    </template>

    <div class="space-y-4">
      <div v-if="error" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <!-- To -->
      <div class="grid grid-cols-[90px_1fr] items-center gap-3">
        <label class="text-sm text-muted-foreground">To</label>
        <div>
          <AppSelect
            v-model="form.recipient_email"
            :options="recipientOptions"
            placeholder="Nothing selected"
          />
          <button type="button" class="text-navy-dark hover:text-accent text-xs mt-1.5 inline-flex items-center gap-1 transition-colors" @click="showBcc = !showBcc">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
            Add BCC
          </button>
        </div>
      </div>

      <div v-if="showBcc" class="grid grid-cols-[90px_1fr] items-start gap-3">
        <label class="text-sm text-muted-foreground pt-2.5">BCC</label>
        <EmailTagsInput ref="bccRef" v-model="bccList" v-model:valid="bccValid" placeholder="comma-separated e-mails" />
      </div>

      <!-- Subject -->
      <div class="grid grid-cols-[90px_1fr] items-center gap-3">
        <label class="text-sm text-muted-foreground">Subject</label>
        <input v-model="form.subject" type="text" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" />
      </div>

      <!-- Attachment -->
      <div class="grid grid-cols-[90px_1fr] gap-3">
        <label class="text-sm text-muted-foreground pt-2">Attachment</label>
        <div>
          <div
            class="border-2 border-dashed rounded-lg py-6 px-4 text-center transition-colors"
            :class="dragging ? 'border-[#1F3A5C] bg-[#1F3A5C]/5' : 'border-[#DCDEE8]'"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop"
          >
            <p class="text-sm text-[#717B99]">Drag and drop your attachments here</p>
          </div>
          <input ref="fileInput" type="file" multiple class="hidden" @change="onFileSelect" />
          <button type="button" class="mt-2 inline-flex items-center gap-2 rounded-md bg-accent px-3.5 py-2 text-sm font-semibold text-white hover:opacity-90 transition-opacity" @click="fileInput?.click()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Click to select attachments
          </button>
          <ul v-if="files.length" class="mt-2 space-y-1">
            <li v-for="(f, i) in files" :key="i" class="flex items-center justify-between text-xs text-foreground bg-muted/40 rounded px-2 py-1">
              <span class="truncate">{{ f.name }}</span>
              <button type="button" class="text-muted-foreground hover:text-destructive" @click="files.splice(i, 1)">✕</button>
            </li>
          </ul>
        </div>
      </div>

      <!-- Body: minimal rich text -->
      <div>
        <div class="flex items-center gap-1 border border-border border-b-0 rounded-t-md px-2 py-1.5 bg-muted/30">
          <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm font-bold" title="Bold" @mousedown.prevent="exec('bold')">B</button>
          <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm italic" title="Italic" @mousedown.prevent="exec('italic')">I</button>
          <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm underline" title="Underline" @mousedown.prevent="exec('underline')">U</button>
          <span class="w-px h-5 bg-border mx-1"></span>
          <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Bulleted list" @mousedown.prevent="exec('insertUnorderedList')">•</button>
          <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Numbered list" @mousedown.prevent="exec('insertOrderedList')">1.</button>
        </div>
        <div
          ref="editor"
          contenteditable="true"
          class="min-h-[160px] max-h-[320px] overflow-auto rounded-b-md border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          @input="onBodyInput"
        ></div>
        <p class="text-xs text-muted-foreground mt-1">Characters: {{ charCount }}</p>
      </div>
    </div>

    <template #footer>
      <button type="button" :disabled="sending || !bccValid" class="inline-flex items-center gap-2 rounded-md bg-accent px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60" @click="submit">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        {{ sending ? 'Sending…' : 'Send E-Mail' }}
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import AppModal from '@/components/common/AppModal.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import EmailTagsInput from '@/components/common/EmailTagsInput.vue'
import api from '@/composables/useApi.js'

const props = defineProps({
  show:            { type: Boolean, default: false },
  communityId:     { type: String,  required: true },
  unitId:          { type: String,  required: true },
  recipients:      { type: Array,   default: () => [] },
  defaultRecipient:{ type: String,  default: '' },
})
const emit = defineEmits(['close', 'sent'])

const form      = ref({ recipient_email: '', subject: '' })
const showBcc   = ref(false)
const bccList   = ref([])
const bccValid  = ref(true)
const bccRef    = ref(null)
const files     = ref([])
const dragging  = ref(false)
const sending   = ref(false)
const error     = ref(null)
const charCount = ref(0)
const editor    = ref(null)
const fileInput = ref(null)

const recipientOptions = computed(() =>
  props.recipients.map(r => ({ value: r.email, label: r.name ? `${r.name} — ${r.email}` : r.email }))
)

function exec(cmd) { document.execCommand(cmd, false, null); onBodyInput() }
function onBodyInput() { charCount.value = (editor.value?.textContent || '').length }
function onFileSelect(e) { files.value.push(...Array.from(e.target.files || [])); e.target.value = '' }
function onDrop(e) { dragging.value = false; files.value.push(...Array.from(e.dataTransfer.files || [])) }

function reset() {
  form.value = { recipient_email: props.defaultRecipient || '', subject: '' }
  showBcc.value = false
  bccList.value = []
  bccValid.value = true
  files.value = []
  error.value = null
  sending.value = false
  charCount.value = 0
  nextTick(() => { if (editor.value) editor.value.innerHTML = '' })
}

async function submit() {
  if (!form.value.recipient_email) { error.value = 'Please choose a recipient.'; return }
  if (!form.value.subject.trim())  { error.value = 'Please enter a subject.'; return }
  bccRef.value?.commit()
  if (!bccValid.value) { error.value = 'Please fix the BCC e-mail address(es).'; return }
  sending.value = true
  error.value = null
  try {
    const fd = new FormData()
    fd.append('recipient_email', form.value.recipient_email)
    const r = props.recipients.find(x => x.email === form.value.recipient_email)
    if (r?.name) fd.append('recipient_name', r.name)
    if (bccList.value.length) fd.append('bcc', bccList.value.join(','))
    fd.append('subject', form.value.subject)
    fd.append('body', editor.value?.innerHTML || '')
    files.value.forEach(f => fd.append('attachments[]', f))
    await api.post(`/communities/${props.communityId}/units/${props.unitId}/communications`, fd)
    emit('sent')
    emit('close')
  } catch (e) {
    error.value = e?.response?.data?.message ?? 'Failed to send e-mail.'
  } finally {
    sending.value = false
  }
}

function handleClose() { emit('close') }

watch(() => props.show, (v) => { if (v) reset() })
</script>
