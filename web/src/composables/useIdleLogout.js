import { onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/composables/useApi'

/**
 * Proactively logs the user out after a period of inactivity (BM-006) and
 * sends them to the login page — preserving the current route so they return
 * to exactly where they were after signing back in.
 *
 * This mirrors the server-side inactivity timeout so the user is kicked the
 * moment their time is up, rather than only on their next API call. Sessions
 * created with "Keep me signed in" (BM-009) are persistent and never idle out.
 *
 * While the user is genuinely interacting, we also send a throttled heartbeat
 * to the API (at most once per VITE_SESSION_HEARTBEAT_MINUTES). Because every
 * authenticated request resets the server's sliding inactivity window, this
 * keeps an actively-used session alive even on pages that make no other API
 * calls — so "interacting" really does extend the session, not just reset the
 * client-side clock.
 *
 * Mount this once inside the authenticated app shell (AppLayout).
 */
const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'wheel', 'scroll']

export function useIdleLogout() {
  const router = useRouter()
  const auth   = useAuthStore()

  // Keep in lockstep with the server's auth.session_inactivity_timeout.
  const minutes   = Number(import.meta.env.VITE_SESSION_INACTIVITY_MINUTES) || 30
  const timeoutMs = Math.max(1, minutes) * 60 * 1000

  // Throttle the keep-alive ping to well under the inactivity window.
  const heartbeatMinutes = Number(import.meta.env.VITE_SESSION_HEARTBEAT_MINUTES) || 5
  const heartbeatMs      = Math.max(1, heartbeatMinutes) * 60 * 1000
  let lastHeartbeat = 0

  let timer = null

  function expire() {
    if (!auth.isAuthenticated) return
    const redirect = router.currentRoute.value.fullPath
    auth.clearSession()
    router.replace({ name: 'login', query: { redirect, expired: '1', reason: 'inactivity' } })
  }

  // Persistent ("remember me") sessions never idle out, so they need no ping.
  function maybeHeartbeat() {
    if (!auth.isAuthenticated || auth.persistent) return
    const now = Date.now()
    if (now - lastHeartbeat < heartbeatMs) return
    lastHeartbeat = now
    api.post('/auth/heartbeat').catch(() => {})
  }

  function reset() {
    maybeHeartbeat()
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    // Persistent ("remember me") sessions are exempt — match the server.
    if (auth.persistent || !auth.isAuthenticated) return
    timer = setTimeout(expire, timeoutMs)
  }

  onMounted(() => {
    // The initial page load already hit the API, so seed the throttle to avoid
    // an immediate redundant ping.
    lastHeartbeat = Date.now()
    ACTIVITY_EVENTS.forEach((e) => window.addEventListener(e, reset, { passive: true }))
    reset()
  })

  onBeforeUnmount(() => {
    ACTIVITY_EVENTS.forEach((e) => window.removeEventListener(e, reset))
    if (timer) clearTimeout(timer)
  })
}
