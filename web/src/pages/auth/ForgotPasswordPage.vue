<script setup>
import { ref } from 'vue'
import api from '@/composables/useApi'

const email = ref('')
const sent = ref(false)
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center" style="background-color: #1a2744;">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-8">
          <h1 class="text-2xl font-bold text-gray-900">Reset Password</h1>
          <p class="text-sm text-gray-500 mt-1">We'll send a reset link to your email.</p>
        </div>

        <div v-if="sent" class="text-sm text-green-700 bg-green-50 px-4 py-3 rounded-lg mb-4">
          Password reset link sent! Check your inbox.
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
            <input v-model="email" type="email" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none" />
          </div>

          <div v-if="error" class="text-sm text-red-600 bg-red-50 px-4 py-2.5 rounded-lg">{{ error }}</div>

          <button type="submit" :disabled="loading" class="w-full py-2.5 px-4 text-white font-semibold rounded-lg" style="background-color: #1a2744;">
            {{ loading ? 'Sending…' : 'Send reset link' }}
          </button>
        </form>

        <div class="mt-4 text-center">
          <RouterLink to="/login" class="text-sm hover:underline" style="color: #e8a040;">Back to sign in</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
