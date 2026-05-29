import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(localStorage.getItem('auth_token') || null)

  // When login requires 2FA, this holds the encrypted challenge
  const twoFactorChallenge = ref(null)

  const isAuthenticated   = computed(() => !!token.value)
  const twoFactorEnabled  = computed(() => !!user.value?.two_factor_enabled)
  const requiresTwoFactor = computed(() => !!twoFactorChallenge.value)

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password })

    if (data.data.two_factor_required) {
      twoFactorChallenge.value = data.data.challenge
      return { two_factor_required: true }
    }

    twoFactorChallenge.value = null
    token.value = data.data.token
    user.value  = data.data.user
    localStorage.setItem('auth_token', token.value)
    return { two_factor_required: false }
  }

  async function loginWithTwoFactor(code) {
    const { data } = await api.post('/auth/2fa/challenge', {
      challenge: twoFactorChallenge.value,
      code,
    })
    twoFactorChallenge.value = null
    token.value = data.data.token
    user.value  = data.data.user
    localStorage.setItem('auth_token', token.value)
  }

  function cancelTwoFactor() {
    twoFactorChallenge.value = null
  }

  async function fetchUser() {
    const { data } = await api.get('/auth/me')
    user.value = data.data
  }

  function logout() {
    api.post('/auth/logout').catch(() => {})
    token.value              = null
    user.value               = null
    twoFactorChallenge.value = null
    localStorage.removeItem('auth_token')
  }

  return {
    user, token, isAuthenticated, twoFactorEnabled, requiresTwoFactor, twoFactorChallenge,
    login, loginWithTwoFactor, cancelTwoFactor, fetchUser, logout,
  }
})
