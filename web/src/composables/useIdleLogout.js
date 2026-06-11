import { onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Proactively logs the user out after a period of inactivity (BM-006) and
 * sends them to the login page — preserving the current route so they return
 * to exactly where they were after signing back in.
 *
 * This mirrors the server-side inactivity timeout so the user is kicked the
 * moment their time is up, rather than only on their next API call. Sessions
 * created with "Keep me signed in" (BM-009) are persistent and never idle out.
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

  let timer = null

  function expire() {
    if (!auth.isAuthenticated) return
    const redirect = router.currentRoute.value.fullPath
    auth.clearSession()
    router.replace({ name: 'login', query: { redirect, expired: '1' } })
  }

  function reset() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    // Persistent ("remember me") sessions are exempt — match the server.
    if (auth.persistent || !auth.isAuthenticated) return
    timer = setTimeout(expire, timeoutMs)
  }

  onMounted(() => {
    ACTIVITY_EVENTS.forEach((e) => window.addEventListener(e, reset, { passive: true }))
    reset()
  })

  onBeforeUnmount(() => {
    ACTIVITY_EVENTS.forEach((e) => window.removeEventListener(e, reset))
    if (timer) clearTimeout(timer)
  })
}
