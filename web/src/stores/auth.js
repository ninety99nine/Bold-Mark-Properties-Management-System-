import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(localStorage.getItem('auth_token') || null)

  // Whether the current session is persistent ("Keep me signed in" — BM-009).
  // Persistent sessions are exempt from the inactivity auto-logout (BM-006).
  const persistent = ref(localStorage.getItem('auth_remember') === '1')

  // When login requires 2FA, this holds the encrypted challenge plus the
  // remember choice the user made on the login form, applied once the
  // challenge is passed.
  const twoFactorChallenge = ref(null)
  const pendingRemember    = ref(false)

  const isAuthenticated   = computed(() => !!token.value)
  const twoFactorEnabled  = computed(() => !!user.value?.two_factor_enabled)
  const requiresTwoFactor = computed(() => !!twoFactorChallenge.value)

  /**
   * Persist a freshly issued session (token + user) and the remember choice.
   */
  function setSession(data, remember) {
    twoFactorChallenge.value = null
    token.value      = data.token
    user.value       = data.user
    persistent.value = !!remember
    localStorage.setItem('auth_token', token.value)
    localStorage.setItem('auth_remember', remember ? '1' : '0')
  }

  /**
   * Clear all session state locally — used by logout, the 401 interceptor and
   * the inactivity watcher. Does NOT call the API (the token may already be
   * revoked/expired server-side).
   */
  function clearSession() {
    token.value              = null
    user.value               = null
    persistent.value         = false
    twoFactorChallenge.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_remember')
  }

  async function login(email, password, remember = false) {
    const { data } = await api.post('/auth/login', { email, password, remember })

    if (data.data.two_factor_required) {
      twoFactorChallenge.value = data.data.challenge
      pendingRemember.value    = remember
      return { two_factor_required: true }
    }

    setSession(data.data, remember)
    return { two_factor_required: false }
  }

  async function loginWithTwoFactor(code) {
    const { data } = await api.post('/auth/2fa/challenge', {
      challenge: twoFactorChallenge.value,
      code,
    })
    setSession(data.data, pendingRemember.value)
  }

  function cancelTwoFactor() {
    twoFactorChallenge.value = null
    pendingRemember.value    = false
  }

  async function fetchUser() {
    const { data } = await api.get('/auth/me')
    user.value = data.data
  }

  function logout() {
    api.post('/auth/logout').catch(() => {})
    clearSession()
  }

  return {
    user, token, persistent, isAuthenticated, twoFactorEnabled, requiresTwoFactor, twoFactorChallenge,
    login, loginWithTwoFactor, cancelTwoFactor, fetchUser, logout, setSession, clearSession,
  }
})
