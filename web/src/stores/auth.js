import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'
import { getToken, isPersistent, storeToken, clearToken } from '@/composables/authStorage'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(getToken())

  // Whether the current session is persistent ("Keep me signed in" — BM-009).
  // Persistent sessions are exempt from the inactivity auto-logout (BM-006) and
  // are the only ones that survive the browser closing (BUG-004).
  const persistent = ref(isPersistent())

  // When login requires 2FA, this holds the encrypted challenge plus the
  // remember choice the user made on the login form, applied once the
  // challenge is passed.
  const twoFactorChallenge = ref(null)
  const pendingRemember    = ref(false)

  // Set when login succeeds but the user has not yet set up 2FA. 2FA is
  // mandatory (BUG-003), so they are forced into enrollment before any access
  // token is issued. Holds the encrypted "setup" challenge from the login call.
  const twoFactorSetupChallenge = ref(null)

  const isAuthenticated      = computed(() => !!token.value)
  const twoFactorEnabled     = computed(() => !!user.value?.two_factor_enabled)
  const requiresTwoFactor    = computed(() => !!twoFactorChallenge.value)
  const requiresTwoFactorSetup = computed(() => !!twoFactorSetupChallenge.value)

  /**
   * Persist a freshly issued session (token + user) and the remember choice.
   */
  function setSession(data, remember) {
    twoFactorChallenge.value      = null
    twoFactorSetupChallenge.value = null
    token.value      = data.token
    user.value       = data.user
    persistent.value = !!remember
    storeToken(token.value, !!remember)
  }

  /**
   * Clear all session state locally — used by logout, the 401 interceptor and
   * the inactivity watcher. Does NOT call the API (the token may already be
   * revoked/expired server-side).
   */
  function clearSession() {
    token.value              = null
    user.value               = null
    persistent.value              = false
    twoFactorChallenge.value      = null
    twoFactorSetupChallenge.value = null
    clearToken()
  }

  async function login(email, password, remember = false) {
    const { data } = await api.post('/auth/login', { email, password, remember })

    // 2FA is mandatory — login never returns a token directly. The user is
    // routed to either the TOTP challenge (enrolled) or forced setup (not yet).
    pendingRemember.value = remember

    if (data.data.two_factor_setup_required) {
      twoFactorSetupChallenge.value = data.data.challenge
      return { two_factor_setup_required: true }
    }

    twoFactorChallenge.value = data.data.challenge
    return { two_factor_required: true }
  }

  async function loginWithTwoFactor(code) {
    const { data } = await api.post('/auth/2fa/challenge', {
      challenge: twoFactorChallenge.value,
      code,
    })
    setSession(data.data, pendingRemember.value)
  }

  /**
   * Begin forced 2FA enrollment during login. Returns { qr_uri, secret } for
   * the authenticator app. Gated by the setup challenge from login().
   */
  async function startTwoFactorEnrollment() {
    const { data } = await api.post('/auth/2fa/enroll/start', {
      challenge: twoFactorSetupChallenge.value,
    })
    return data.data
  }

  /**
   * Complete forced enrollment by verifying the first TOTP code. On success the
   * access token is issued and the session is established.
   */
  async function completeTwoFactorEnrollment(code) {
    const { data } = await api.post('/auth/2fa/enroll/confirm', {
      challenge: twoFactorSetupChallenge.value,
      code,
    })
    setSession(data.data, pendingRemember.value)
    twoFactorSetupChallenge.value = null
  }

  function cancelTwoFactor() {
    twoFactorChallenge.value      = null
    twoFactorSetupChallenge.value = null
    pendingRemember.value         = false
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
    user, token, persistent, isAuthenticated, twoFactorEnabled,
    requiresTwoFactor, requiresTwoFactorSetup, twoFactorChallenge,
    login, loginWithTwoFactor, startTwoFactorEnrollment, completeTwoFactorEnrollment,
    cancelTwoFactor, fetchUser, logout, setSession, clearSession,
  }
})
