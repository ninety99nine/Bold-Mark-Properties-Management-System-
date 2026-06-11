import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useIdleLogout } from '@/composables/useIdleLogout'

// ──────────────────────────────────────────────────────────────────────────────
// useIdleLogout — proactive inactivity auto-logout (BM-006).
// Default timeout is 30 min (no VITE override in the test env).
// ──────────────────────────────────────────────────────────────────────────────

const TIMEOUT_MS = 30 * 60 * 1000

const replaceMock = vi.fn()
let authState

vi.mock('vue-router', () => ({
  useRouter: () => ({
    currentRoute: { value: { fullPath: '/estates/9' } },
    replace: replaceMock,
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

const Host = defineComponent({
  setup() {
    useIdleLogout()
    return () => h('div')
  },
})

describe('useIdleLogout', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    replaceMock.mockClear()
    authState = { isAuthenticated: true, persistent: false, clearSession: vi.fn() }
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('logs out and redirects to login (with redirect + expired) after the timeout', () => {
    mount(Host)

    vi.advanceTimersByTime(TIMEOUT_MS + 1)

    expect(authState.clearSession).toHaveBeenCalledOnce()
    expect(replaceMock).toHaveBeenCalledWith({
      name: 'login',
      query: { redirect: '/estates/9', expired: '1' },
    })
  })

  it('does not log out before the timeout elapses', () => {
    mount(Host)

    vi.advanceTimersByTime(TIMEOUT_MS - 1000)

    expect(authState.clearSession).not.toHaveBeenCalled()
    expect(replaceMock).not.toHaveBeenCalled()
  })

  it('resets the timer on user activity', () => {
    mount(Host)

    // Almost timed out, then the user does something.
    vi.advanceTimersByTime(TIMEOUT_MS - 1000)
    window.dispatchEvent(new Event('mousemove'))

    // Original deadline passes — but the clock was reset, so still signed in.
    vi.advanceTimersByTime(2000)
    expect(authState.clearSession).not.toHaveBeenCalled()

    // A full fresh window of inactivity finally triggers logout.
    vi.advanceTimersByTime(TIMEOUT_MS)
    expect(authState.clearSession).toHaveBeenCalledOnce()
  })

  it('never times out a persistent ("remember me") session', () => {
    authState.persistent = true
    mount(Host)

    vi.advanceTimersByTime(TIMEOUT_MS * 5)

    expect(authState.clearSession).not.toHaveBeenCalled()
    expect(replaceMock).not.toHaveBeenCalled()
  })

  it('stops listening after the component unmounts', () => {
    const wrapper = mount(Host)
    wrapper.unmount()

    vi.advanceTimersByTime(TIMEOUT_MS + 1)

    expect(authState.clearSession).not.toHaveBeenCalled()
  })
})
