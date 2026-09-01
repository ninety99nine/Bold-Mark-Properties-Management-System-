<!--
  RichTextEditor — minimal contenteditable editor mirroring the WeConnectU
  message toolbar (clear-format, undo/redo, bold/italic/underline, ordered +
  unordered lists, indent/outdent). v-model is the HTML string.
-->
<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  minHeight:  { type: String, default: '220px' },
  showCount:  { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])

const editor = ref(null)
const charCount = ref(0)

function syncFromModel() {
  if (editor.value && editor.value.innerHTML !== (props.modelValue || '')) {
    editor.value.innerHTML = props.modelValue || ''
    updateCount()
  }
}

function exec(cmd) {
  document.execCommand(cmd, false, null)
  onInput()
}

function onInput() {
  emit('update:modelValue', editor.value?.innerHTML || '')
  updateCount()
}

function updateCount() {
  charCount.value = (editor.value?.textContent || '').length
}

onMounted(syncFromModel)
watch(() => props.modelValue, syncFromModel)
</script>

<template>
  <div>
    <div class="flex items-center gap-1 border border-border border-b-0 rounded-t-md px-2 py-1.5 bg-muted/30">
      <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm" title="Clear formatting" @mousedown.prevent="exec('removeFormat')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto"><path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/></svg>
      </button>
      <span class="w-px h-5 bg-border mx-1"></span>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Undo" @mousedown.prevent="exec('undo')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5 5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
      </button>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Redo" @mousedown.prevent="exec('redo')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5 5.5 5.5 0 0 0 9.5 20H13"/></svg>
      </button>
      <span class="w-px h-5 bg-border mx-1"></span>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm font-bold" title="Bold" @mousedown.prevent="exec('bold')">B</button>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm underline" title="Underline" @mousedown.prevent="exec('underline')">U</button>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted text-sm italic" title="Italic" @mousedown.prevent="exec('italic')">I</button>
      <span class="w-px h-5 bg-border mx-1"></span>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Numbered list" @mousedown.prevent="exec('insertOrderedList')">1.</button>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Bulleted list" @mousedown.prevent="exec('insertUnorderedList')">•</button>
      <span class="w-px h-5 bg-border mx-1"></span>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Outdent" @mousedown.prevent="exec('outdent')">⇤</button>
      <button type="button" class="w-7 h-7 rounded hover:bg-muted" title="Indent" @mousedown.prevent="exec('indent')">⇥</button>
    </div>
    <div
      ref="editor"
      contenteditable="true"
      class="rte-content overflow-auto rounded-b-md border border-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
      :style="{ minHeight, maxHeight: '420px' }"
      @input="onInput"
    ></div>
    <p v-if="showCount" class="text-xs text-muted-foreground mt-1">Characters: {{ charCount }}</p>
  </div>
</template>

<style scoped>
/* Restore list markers / indentation that Tailwind's Preflight resets,
   so the ordered/unordered-list toolbar buttons are visibly applied. */
.rte-content :deep(ol) { list-style: decimal; padding-left: 1.5rem; margin: 0.25rem 0; }
.rte-content :deep(ul) { list-style: disc;    padding-left: 1.5rem; margin: 0.25rem 0; }
.rte-content :deep(li) { margin: 0.125rem 0; }
.rte-content :deep(blockquote) { margin-left: 1.5rem; padding-left: 0.5rem; border-left: 2px solid var(--color-border, #dcdee8); }
</style>
