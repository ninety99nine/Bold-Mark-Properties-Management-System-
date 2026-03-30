import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token') || null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.data.token
    user.value = data.data.user
    localStorage.setItem('auth_token', token.value)
  }

  async function fetchUser() {
    const { data } = await api.get('/auth/me')
    user.value = data.data
  }

  function logout() {
    api.post('/auth/logout').catch(() => {})
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  return { user, token, isAuthenticated, login, fetchUser, logout }
})
