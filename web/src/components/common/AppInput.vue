<script setup>
defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  label: String,
  type: {
    type: String,
    default: 'text',
  },
  placeholder: String,
  error: String,
  hint: String,
  required: Boolean,
  disabled: Boolean,
  id: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label
      v-if="label"
      :for="id"
      class="text-sm font-medium text-fg"
    >
      {{ label }}
      <span v-if="required" class="text-amber ml-0.5">*</span>
    </label>

    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :class="[
        'w-full px-4 py-3 text-sm text-fg bg-white border-2 rounded transition-all duration-200 outline-none',
        'placeholder:text-muted-fg',
        'disabled:bg-muted disabled:cursor-not-allowed disabled:opacity-60',
        error
          ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger/20'
          : 'border-border hover:border-muted-fg focus:border-amber focus:ring-2 focus:ring-amber/20',
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
    />

    <p v-if="error" class="text-xs text-danger flex items-center gap-1">
      <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-muted-fg">{{ hint }}</p>
  </div>
</template>
