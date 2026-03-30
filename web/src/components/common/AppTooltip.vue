<script setup>
import { ref } from 'vue'

defineProps({
  text: String,
  position: {
    type: String,
    default: 'top',
    // top | bottom | left | right
  },
})

const visible = ref(false)
</script>

<template>
  <div class="relative inline-flex" @mouseenter="visible = true" @mouseleave="visible = false">
    <slot />
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="visible && text"
        :class="[
          'absolute z-50 px-2.5 py-1.5 text-xs font-medium text-white bg-fg rounded whitespace-nowrap pointer-events-none shadow-lg',
          position === 'top'    && 'bottom-full left-1/2 -translate-x-1/2 mb-2',
          position === 'bottom' && 'top-full left-1/2 -translate-x-1/2 mt-2',
          position === 'left'   && 'right-full top-1/2 -translate-y-1/2 mr-2',
          position === 'right'  && 'left-full top-1/2 -translate-y-1/2 ml-2',
        ]"
      >
        {{ text }}
      </div>
    </Transition>
  </div>
</template>
