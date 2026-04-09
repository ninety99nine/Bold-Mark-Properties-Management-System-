<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  /**
   * Preferred position relative to the trigger.
   * Smart-flips left↔right when the poptip would overflow the viewport.
   */
  position: {
    type: String,
    default: 'right',
    validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v),
  },
  maxWidth: {
    type: String,
    default: '260px',
  },
})

const triggerRef = ref(null)
const isVisible  = ref(false)
const style      = ref({})
let   hideTimer  = null

function recalculate() {
  if (!triggerRef.value) return
  const rect = triggerRef.value.getBoundingClientRect()
  const vw   = window.innerWidth
  const maxW = parseInt(props.maxWidth) || 260

  let pos = props.position

  // Auto-flip right → left when poptip would overflow viewport
  if (pos === 'right' && rect.right + 8 + maxW > vw) pos = 'left'
  // Auto-flip left → right when it would overflow the left edge
  if (pos === 'left'  && rect.left  - 8 - maxW < 0)  pos = 'right'

  if (pos === 'right') {
    style.value = {
      top:       `${rect.top + rect.height / 2}px`,
      left:      `${rect.right + 8}px`,
      transform: 'translateY(-50%)',
    }
  } else if (pos === 'left') {
    style.value = {
      top:       `${rect.top + rect.height / 2}px`,
      left:      `${rect.left - 8}px`,
      transform: 'translate(-100%, -50%)',
    }
  } else if (pos === 'bottom') {
    style.value = {
      top:       `${rect.bottom + 8}px`,
      left:      `${rect.left + rect.width / 2}px`,
      transform: 'translateX(-50%)',
    }
  } else {
    // top
    style.value = {
      top:       `${rect.top - 8}px`,
      left:      `${rect.left + rect.width / 2}px`,
      transform: 'translate(-50%, -100%)',
    }
  }
}

function show() {
  clearTimeout(hideTimer)
  recalculate()
  isVisible.value = true
}

function hide() {
  hideTimer = setTimeout(() => { isVisible.value = false }, 80)
}

function hideImmediately() {
  clearTimeout(hideTimer)
  isVisible.value = false
}

onMounted(()  => window.addEventListener('modal-opened', hideImmediately))
onUnmounted(() => window.removeEventListener('modal-opened', hideImmediately))
</script>

<template>
  <!-- Trigger wrapper — always inline so it doesn't break table cell layout -->
  <span
    ref="triggerRef"
    class="inline-flex items-center"
    @mouseenter="show"
    @mouseleave="hide"
  >
    <slot name="trigger" />
  </span>

  <!-- Poptip rendered at body level to escape overflow:hidden containers -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isVisible"
        class="fixed z-[9999] rounded-xl border border-border bg-white shadow-xl origin-left overflow-hidden"
        :style="{ ...style, maxWidth }"
        @mouseenter="show"
        @mouseleave="hide"
      >
        <slot />
      </div>
    </Transition>
  </Teleport>
</template>
