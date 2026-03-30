<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
    <div class="flex items-center gap-4">
      <h1 class="text-base font-semibold text-gray-800">
        Bold Mark Properties
      </h1>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-sm text-gray-600">{{ auth.user?.name }}</span>
      <button
        class="text-sm text-gray-500 hover:text-gray-800 transition-colors"
        @click="handleLogout"
      >
        Logout
      </button>
    </div>
  </header>
</template>
