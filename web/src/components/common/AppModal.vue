<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import AppButton from '@/components/common/AppButton.vue'

const props = defineProps({
  show: Boolean,
  title: String,
  size: {
    type: String,
    default: 'md',
    // sm | md | lg | xl
  },
})

const emit = defineEmits(['close'])

// Stay visible until the panel leave-transition completes,
// so the backdrop doesn't disappear before the panel finishes sliding out.
const overlayVisible = ref(props.show)

watch(() => props.show, (val) => {
  if (val) {
    overlayVisible.value = true
    window.dispatchEvent(new Event('modal-opened'))
  }
  // Don't hide here — onAfterLeave handles it
})

function onAfterLeave() {
  overlayVisible.value = false
}

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <!--
      The container (and backdrop) is shown via v-if with NO opacity transition.
      This means the backdrop-blur element is inserted at opacity:1, so Chrome
      computes the GPU compositing layer immediately — no blur delay.

      The panel slides in/out independently via its own Transition.
      onAfterLeave hides the backdrop only after the panel finishes leaving.
    -->
    <div
      v-if="overlayVisible"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <!-- Backdrop: no opacity transition — blur is ready the instant it mounts -->
      <div class="absolute inset-0 bg-navy/60 backdrop-blur-sm" @click="$emit('close')" />

      <!-- Panel: slides in on mount (appear), slides out on leave -->
      <Transition
        appear
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95 translate-y-2"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 translate-y-2"
        @after-leave="onAfterLeave"
      >
        <div
          v-if="show"
          :class="[
            'relative bg-white rounded shadow-2xl border border-border/50 w-full flex flex-col max-h-[90vh]',
            size === 'sm' && 'max-w-sm',
            size === 'md' && 'max-w-md',
            size === 'lg' && 'max-w-2xl',
            size === 'xl' && 'max-w-4xl',
          ]"
        >
          <!-- Header -->
          <div v-if="title || $slots.header" class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
            <slot name="header">
              <h3 class="text-base font-bold text-fg" style="font-family: 'DM Sans', sans-serif">{{ title }}</h3>
            </slot>
            <AppButton variant="ghost" square size="sm" @click="$emit('close')">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </AppButton>
          </div>

          <!-- Body: mr-2 gaps scrollbar from modal right border; pr-4 gaps content from scrollbar -->
          <div class="pl-6 pr-4 py-5 mr-2 overflow-y-auto app-modal-body">
            <slot />
          </div>

          <!-- Footer -->
          <div v-if="$slots.footer" class="px-6 py-4 border-t border-border flex items-center justify-end gap-3 shrink-0">
            <slot name="footer" />
          </div>
        </div>
      </Transition>
    </div>
  </Teleport>
</template>

<style>
/* Unscoped — webkit scrollbar pseudo-elements require global CSS to apply reliably */
.app-modal-body {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}
.app-modal-body::-webkit-scrollbar {
  width: 4px;
}
.app-modal-body::-webkit-scrollbar-track {
  background: transparent;
}
.app-modal-body::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 9999px;
}
</style>
