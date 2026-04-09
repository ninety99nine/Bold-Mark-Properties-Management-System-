<!--
  AppDropdown — Reusable dropdown menu component.

  Usage:
    <AppDropdown align="right">
      <template #trigger="{ toggle, isOpen }">
        <AppButton square variant="outline" @click="toggle">...</AppButton>
      </template>
      <template #default="{ close }">
        <AppDropdownItem label="Edit" @click="close(); doEdit()" />
        <AppDropdownItem label="Delete" variant="danger" @click="close(); doDelete()" />
      </template>
    </AppDropdown>

  Props:
    align — 'right' (default) | 'left'  — horizontal alignment of the panel
    width — Tailwind w-* class, default 'w-44'

  Slots:
    #trigger({ toggle, isOpen })  — the element that opens/closes the dropdown
    #default({ close })           — the dropdown panel content
-->
<script setup>
import { ref } from 'vue'

defineProps({
  align: {
    type: String,
    default: 'right',
    validator: (v) => ['left', 'right'].includes(v),
  },
  width: {
    type: String,
    default: 'w-44',
  },
})

const isOpen = ref(false)

function toggle() { isOpen.value = !isOpen.value }
function close()  { isOpen.value = false }
</script>

<template>
  <div class="relative inline-flex">
    <!-- Trigger slot -->
    <slot name="trigger" :toggle="toggle" :is-open="isOpen" />

    <!-- Transparent backdrop — closes menu on outside click -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-10"
      aria-hidden="true"
      @click="close"
    />

    <!-- Dropdown panel -->
    <Transition
      enter-active-class="transition ease-out duration-100 origin-top-right"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75 origin-top-right"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute top-full mt-1 z-20 py-1',
          'rounded-lg border border-border bg-card shadow-lg',
          width,
          align === 'right' ? 'right-0' : 'left-0',
        ]"
        role="menu"
      >
        <slot :close="close" />
      </div>
    </Transition>
  </div>
</template>
