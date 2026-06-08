import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock the API layer so the store never hits the network.
vi.mock('@/composables/useApi', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

// ──────────────────────────────────────────────────────────────────────────────
// Auth store — session persistence behaviour underpinning BM-006 / BM-009.
// ──────────────────────────────────────────────────────────────────────────────

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    api.post.mockReset()
    api.get.mockReset()
  })

  it('persists token and a non-persistent flag on a normal login', async () => {
    api.post.mockResolvedValue({ data: { data: { token: 'tok-1', user: { id: 1 } } } })

    const auth = useAuthStore()
    const result = await auth.login('a@b.test', 'pw', false)

    expect(result).toEqual({ two_factor_required: false })
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.persistent).toBe(false)
    expect(localStorage.getItem('auth_token')).toBe('tok-1')
    expect(localStorage.getItem('auth_remember')).toBe('0')
  })

  it('marks the session persistent when remember is true', async () => {
    api.post.mockResolvedValue({ data: { data: { token: 'tok-2', user: { id: 2 } } } })

    const auth = useAuthStore()
    await auth.login('a@b.test', 'pw', true)

    expect(auth.persistent).toBe(true)
    expect(localStorage.getItem('auth_remember')).toBe('1')
  })

  it('carries the remember choice through the 2FA challenge', async () => {
    api.post
      .mockResolvedValueOnce({ data: { data: { two_factor_required: true, challenge: 'ch' } } })
      .mockResolvedValueOnce({ data: { data: { token: 'tok-3', user: { id: 3 } } } })

    const auth = useAuthStore()
    const result = await auth.login('a@b.test', 'pw', true)
    expect(result.two_factor_required).toBe(true)
    expect(auth.isAuthenticated).toBe(false)

    await auth.loginWithTwoFactor('123456')
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.persistent).toBe(true)
  })

  it('clearSession wipes all state and storage without calling the API', () => {
    const auth = useAuthStore()
    auth.setSession({ token: 'tok-4', user: { id: 4 } }, true)
    expect(auth.isAuthenticated).toBe(true)

    auth.clearSession()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.persistent).toBe(false)
    expect(auth.user).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
    expect(localStorage.getItem('auth_remember')).toBeNull()
    expect(api.post).not.toHaveBeenCalled()
  })
})
