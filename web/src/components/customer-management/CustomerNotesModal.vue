<template>
  <div>
    <!-- Notes modal -->
    <AppModal :show="show" size="xl" @close="$emit('close')">
      <template #header>
        <h3 class="text-base font-bold text-foreground" v-if="unit">Notes for {{ unit.customer_code }}{{ unit.customer_name ? ' ' + unit.customer_name : '' }}</h3>
      </template>
      <div v-if="unit">
        <!-- Add note -->
        <label class="mb-1 block text-sm font-medium text-foreground">Notes</label>
        <textarea
          v-model="newNote"
          rows="3"
          class="w-full rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
        ></textarea>

        <div class="mt-4">
          <p class="mb-1 text-sm font-medium text-foreground">Upload Document</p>
          <label class="mb-1 block text-xs text-muted-foreground">Document Name</label>
          <input
            v-model="newDocName"
            type="text"
            class="mb-2 w-full rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
          />
          <input ref="fileInput" type="file" class="hidden" multiple @change="onFileChange" />
          <div
            @click="pickFile"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
            :class="['group cursor-pointer rounded-md border-2 border-dashed px-4 py-8 text-center transition-colors', dragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
          >
            <p class="text-sm font-medium text-accent group-hover:underline">Click to select attachments</p>
            <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop your attachments here (max 5)</p>
          </div>
          <!-- Selected files -->
          <ul v-if="newFiles.length" class="mt-2 space-y-1">
            <li v-for="(f, i) in newFiles" :key="f.name + f.size" class="flex items-center gap-2 text-xs text-foreground">
              <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
              <span class="shrink-0 text-muted-foreground">{{ fileSize(f.size) }}</span>
              <button type="button" class="shrink-0 text-muted-foreground hover:text-red-600" title="Remove" @click="removeNewFile(i)">×</button>
            </li>
          </ul>
        </div>

        <div class="mt-4 flex justify-end">
          <AppButton variant="secondary" :loading="addingNote" @click="addNote">Add Note</AppButton>
        </div>

        <!-- Notes list -->
        <div class="mt-6">
          <div v-if="notesLoading" class="text-sm text-muted-foreground">Loading…</div>
          <div v-else-if="!notesList.length" class="text-sm text-muted-foreground">No notes.</div>
          <ul v-else class="divide-y divide-border">
            <li v-for="n in notesList" :key="n.id" class="group flex items-start gap-3 py-3">
              <div class="w-40 shrink-0 text-xs text-muted-foreground">{{ n.created_at }}</div>
              <div class="min-w-0 flex-1 text-sm text-foreground">
                <p class="whitespace-pre-wrap">{{ n.note }}</p>
                <div v-if="n.attachments && n.attachments.length" class="mt-1 flex flex-col gap-0.5">
                  <a v-for="a in n.attachments" :key="a.id" :href="a.url" target="_blank" rel="noopener" class="text-xs text-[#2f6fb0] hover:underline">{{ a.name || 'Attachment' }}</a>
                </div>
              </div>
              <!-- Edit/delete only on manual notes; system notes are read-only (WeConnectU). -->
              <div v-if="n.editable" class="flex shrink-0 items-center gap-2 opacity-30 transition-opacity group-hover:opacity-100">
                <button type="button" class="text-muted-foreground hover:text-red-600" title="Delete" @click="deleteNote(n)"><IconTrash class="h-4 w-4" /></button>
                <button type="button" class="text-muted-foreground hover:text-[#2f6fb0]" title="Edit" @click="openEditNote(n)"><IconPencil class="h-4 w-4" /></button>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </AppModal>

    <!-- Edit Note modal -->
    <AppModal :show="editModal" size="lg" @close="editModal = false">
      <template #header>
        <h3 class="text-base font-bold text-foreground" v-if="unit">Edit Note for {{ unit.customer_code || unit.customer_name }}</h3>
      </template>
      <div>
        <label class="mb-1 block text-sm font-medium text-foreground">Notes:</label>
        <textarea
          v-model="editNoteText"
          rows="3"
          class="w-full rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
        ></textarea>

        <!-- Existing attachments: view each, or mark it for removal (applied on save) -->
        <div v-if="editAttachments.length" class="mt-4">
          <p class="mb-1 text-sm font-medium text-foreground">Attachments</p>
          <ul class="space-y-1">
            <li v-for="a in editAttachments" :key="a.id" class="flex items-center gap-2">
              <a :href="a.url" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-sm text-[#2f6fb0] hover:underline">{{ a.name || 'Attachment' }}</a>
              <button type="button" class="shrink-0 text-red-600 hover:opacity-80" title="Remove attachment" @click="removeExistingAttachment(a)"><IconTrash class="h-4 w-4" /></button>
            </li>
          </ul>
          <p class="mt-1 text-xs text-muted-foreground">Removed attachments are deleted when you click “Save”.</p>
        </div>

        <div class="mt-4">
          <p class="mb-1 text-sm font-medium text-foreground">Upload Document</p>
          <label class="mb-1 block text-xs text-muted-foreground">Document Name</label>
          <input
            v-model="editDocName"
            type="text"
            class="mb-2 w-full rounded-md border border-border px-3 py-2 text-sm focus:border-navy focus:outline-none"
          />
          <input ref="editFileInput" type="file" class="hidden" multiple @change="onEditFileChange" />
          <div
            @click="pickEditFile"
            @dragover.prevent="editDragOver = true"
            @dragleave.prevent="editDragOver = false"
            @drop.prevent="onEditDrop"
            :class="['group cursor-pointer rounded-md border-2 border-dashed px-4 py-8 text-center transition-colors', editDragOver ? 'border-accent bg-accent/5' : 'border-border hover:bg-muted/40']"
          >
            <p class="text-sm font-medium text-accent group-hover:underline">Click to select attachments</p>
            <p class="mt-1 text-xs text-muted-foreground">or drag &amp; drop your attachments here (max 5)</p>
          </div>
          <!-- Newly selected files -->
          <ul v-if="editFiles.length" class="mt-2 space-y-1">
            <li v-for="(f, i) in editFiles" :key="f.name + f.size" class="flex items-center gap-2 text-xs text-foreground">
              <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
              <span class="shrink-0 text-muted-foreground">{{ fileSize(f.size) }}</span>
              <button type="button" class="shrink-0 text-muted-foreground hover:text-red-600" title="Remove" @click="removeEditFile(i)">×</button>
            </li>
          </ul>
        </div>

        <div class="mt-4 flex justify-end">
          <AppButton variant="secondary" :loading="savingEdit" :disabled="!editHasChanges" @click="saveEditNote">Save</AppButton>
        </div>
      </div>
    </AppModal>

    <AppConfirm
      :show="confirmState.show"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-label="confirmState.confirmLabel"
      :danger="confirmState.danger"
      @confirm="resolveConfirm(true)"
      @cancel="resolveConfirm(false)"
    />
  </div>
</template>

<script setup>
import { ref, computed, h, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppButton from '@/components/common/AppButton.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppConfirm from '@/components/common/AppConfirm.vue'

const props = defineProps({
  show: Boolean,
  communityId: [String, Number],
  unit: Object,
})
const emit = defineEmits(['close', 'changed'])

const { success, error: toastError } = useToast()

const MAX_ATTACHMENTS = 5

// Branded confirmation dialog (replaces window.confirm).
const confirmState = ref({ show: false, title: 'Confirm', message: '', confirmLabel: 'OK', danger: false })
let confirmResolver = null
function askConfirm(opts = {}) {
  confirmState.value = {
    show: true,
    title: opts.title || 'Confirm',
    message: opts.message || '',
    confirmLabel: opts.confirmLabel || 'OK',
    danger: opts.danger ?? false,
  }
  return new Promise((resolve) => { confirmResolver = resolve })
}
function resolveConfirm(val) {
  confirmState.value.show = false
  const r = confirmResolver
  confirmResolver = null
  if (r) r(val)
}

const stroke = (paths, box = '0 0 24 24') => (p, { attrs }) => h('svg', { viewBox: box, fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', ...attrs }, paths.map(d => h('path', { d })))
const IconPencil = stroke(['M12 20h9', 'M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z'])
const IconTrash = stroke(['M3 6h18', 'M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2', 'M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6', 'M10 11v6', 'M14 11v6'])

function fileSize(bytes) {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// Append files to a target list, dedupe by name+size, cap total at 5.
function appendFiles(target, incoming) {
  const files = Array.from(incoming || [])
  let overflowed = false
  for (const f of files) {
    if (target.length >= MAX_ATTACHMENTS) { overflowed = true; break }
    if (target.some((e) => e.name === f.name && e.size === f.size)) continue
    target.push(f)
  }
  if (overflowed) toastError('Max 5 attachments per note.')
}

// Notes
const notesList = ref([])
const notesLoading = ref(false)
const newNote = ref('')
const newDocName = ref('')
const newFiles = ref([])
const fileInput = ref(null)
const addingNote = ref(false)
const dragOver = ref(false)

async function fetchNotes() {
  if (!props.unit) return
  notesLoading.value = true
  try {
    const { data } = await api.get(`/communities/${props.communityId}/units/${props.unit.unit_id}/notes`)
    notesList.value = data.data ?? []
  } catch (e) {
    toastError('Could not load notes.')
  } finally {
    notesLoading.value = false
  }
}

function pickFile() { fileInput.value?.click() }
function onFileChange(e) {
  appendFiles(newFiles.value, e.target.files)
  if (fileInput.value) fileInput.value.value = ''
}
function onDrop(e) {
  dragOver.value = false
  appendFiles(newFiles.value, e.dataTransfer?.files)
}
function removeNewFile(i) { newFiles.value.splice(i, 1) }

async function addNote() {
  if (!props.unit) return
  addingNote.value = true
  try {
    const form = new FormData()
    if (newNote.value) form.append('note', newNote.value)
    if (newDocName.value) form.append('document_name', newDocName.value)
    for (const f of newFiles.value) form.append('documents[]', f)
    const { data } = await api.post(`/communities/${props.communityId}/units/${props.unit.unit_id}/notes`, form)
    success(data.message || 'Note added.')
    newNote.value = ''
    newDocName.value = ''
    newFiles.value = []
    if (fileInput.value) fileInput.value.value = ''
    fetchNotes()
    emit('changed')
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not add the note.')
  } finally {
    addingNote.value = false
  }
}

async function deleteNote(n) {
  if (!props.unit) return
  if (!(await askConfirm({ title: 'Delete note', message: 'Delete this note? This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return
  try {
    const { data } = await api.delete(`/communities/${props.communityId}/units/${props.unit.unit_id}/notes/${n.id}`)
    success(data.message || 'Note deleted.')
    fetchNotes()
    emit('changed')
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not delete the note.')
  }
}

// Edit Note
const editModal = ref(false)
const editingNote = ref(null)
const editNoteText = ref('')
const editDocName = ref('')
const editAttachments = ref([]) // existing attachments still shown (not marked for removal)
const removeIds = ref([])       // attachment ids to delete on save
const editFiles = ref([])       // newly selected files to add
const editFileInput = ref(null)
const savingEdit = ref(false)
const editDragOver = ref(false)

// Original values, so "Save" is only enabled once something actually changes.
const editOrig = ref({ note: '' })
const editHasChanges = computed(() =>
  (editNoteText.value || '') !== editOrig.value.note ||
  removeIds.value.length > 0 ||
  editFiles.value.length > 0
)

function openEditNote(n) {
  editingNote.value = n
  editNoteText.value = n.note || ''
  editDocName.value = ''
  editAttachments.value = [...(n.attachments || [])]
  removeIds.value = []
  editFiles.value = []
  editOrig.value = { note: n.note || '' }
  editModal.value = true
}

function removeExistingAttachment(a) {
  removeIds.value.push(a.id)
  editAttachments.value = editAttachments.value.filter((x) => x.id !== a.id)
}

function pickEditFile() { editFileInput.value?.click() }
function editRemaining() {
  // How many more files may be added: 5 minus (existing kept + already selected).
  return MAX_ATTACHMENTS - editAttachments.value.length - editFiles.value.length
}
function appendEditFiles(incoming) {
  const files = Array.from(incoming || [])
  let overflowed = false
  for (const f of files) {
    if (editAttachments.value.length + editFiles.value.length >= MAX_ATTACHMENTS) { overflowed = true; break }
    if (editFiles.value.some((e) => e.name === f.name && e.size === f.size)) continue
    editFiles.value.push(f)
  }
  if (overflowed) toastError('Max 5 attachments per note.')
}
function onEditFileChange(e) {
  appendEditFiles(e.target.files)
  if (editFileInput.value) editFileInput.value.value = ''
}
function onEditDrop(e) {
  editDragOver.value = false
  appendEditFiles(e.dataTransfer?.files)
}
function removeEditFile(i) { editFiles.value.splice(i, 1) }

async function saveEditNote() {
  if (!props.unit || !editingNote.value) return
  savingEdit.value = true
  try {
    const form = new FormData()
    if (editNoteText.value) form.append('note', editNoteText.value)
    if (editDocName.value) form.append('document_name', editDocName.value)
    for (const f of editFiles.value) form.append('documents[]', f)
    for (const id of removeIds.value) form.append('remove_attachment_ids[]', id)
    // Laravel needs a spoofed method for multipart PUT.
    form.append('_method', 'PUT')
    const { data } = await api.post(`/communities/${props.communityId}/units/${props.unit.unit_id}/notes/${editingNote.value.id}`, form)
    success(data.message || 'Note updated.')
    editModal.value = false
    fetchNotes()
    emit('changed')
  } catch (e) {
    toastError(e.response?.data?.message || 'Could not update the note.')
  } finally {
    savingEdit.value = false
  }
}

watch(() => props.show, (v) => {
  if (v && props.unit) {
    newNote.value = ''
    newDocName.value = ''
    newFiles.value = []
    if (fileInput.value) fileInput.value.value = ''
    fetchNotes()
  }
})
</script>
