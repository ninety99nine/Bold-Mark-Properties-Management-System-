import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock the API layer so the store never hits the network.
vi.mock('@/composables/useApi', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

import api from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

// ──────────────────────────────────────────────────────────────────────────────
// Auth store — mandatory 2FA (BUG-003) + session persistence (BM-006 / BM-009).
// ──────────────────────────────────────────────────────────────────────────────

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    sessionStorage.clear()
    api.post.mockReset()
    api.get.mockReset()
  })

  it('never establishes a session on password alone — returns a TOTP challenge for an enrolled user (BUG-003)', async () => {
    api.post.mockResolvedValue({ data: { data: { two_factor_required: true, challenge: 'ch' } } })

    const auth = useAuthStore()
    const result = await auth.login('a@b.test', 'pw', false)

    expect(result).toEqual({ two_factor_required: true })
    expect(auth.requiresTwoFactor).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
    expect(sessionStorage.getItem('auth_token')).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('forces 2FA setup when a user without 2FA logs in (BUG-003)', async () => {
    api.post.mockResolvedValue({ data: { data: { two_factor_setup_required: true, challenge: 'setup-ch' } } })

    const auth = useAuthStore()
    const result = await auth.login('new@b.test', 'pw', false)

    expect(result).toEqual({ two_factor_setup_required: true })
    expect(auth.requiresTwoFactorSetup).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('stores a non-remembered session in sessionStorage after 2FA, so it does not survive browser close (BUG-004)', async () => {
    api.post
      .mockResolvedValueOnce({ data: { data: { two_factor_required: true, challenge: 'ch' } } }) // login
      .mockResolvedValueOnce({ data: { data: { token: 'tok-1', user: { id: 1 } } } })            // 2fa/challenge

    const auth = useAuthStore()
    await auth.login('a@b.test', 'pw', false)
    await auth.loginWithTwoFactor('123456')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.persistent).toBe(false)
    expect(sessionStorage.getItem('auth_token')).toBe('tok-1')
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('stores a remembered session in localStorage after 2FA, so it survives browser close', async () => {
    api.post
      .mockResolvedValueOnce({ data: { data: { two_factor_required: true, challenge: 'ch' } } })
      .mockResolvedValueOnce({ data: { data: { token: 'tok-2', user: { id: 2 } } } })

    const auth = useAuthStore()
    await auth.login('a@b.test', 'pw', true)
    await auth.loginWithTwoFactor('123456')

    expect(auth.persistent).toBe(true)
    expect(localStorage.getItem('auth_token')).toBe('tok-2')
    expect(localStorage.getItem('auth_remember')).toBe('1')
    expect(sessionStorage.getItem('auth_token')).toBeNull()
  })

  it('completes forced 2FA enrollment and establishes the session', async () => {
    api.post
      .mockResolvedValueOnce({ data: { data: { two_factor_setup_required: true, challenge: 'setup-ch' } } }) // login
      .mockResolvedValueOnce({ data: { data: { qr_uri: 'otpauth://x', secret: 'SECRET' } } })               // enroll/start
      .mockResolvedValueOnce({ data: { data: { token: 'tok-e', user: { id: 9 } } } })                       // enroll/confirm

    const auth = useAuthStore()
    await auth.login('new@b.test', 'pw', false)

    const enroll = await auth.startTwoFactorEnrollment()
    expect(enroll.secret).toBe('SECRET')
    expect(api.post).toHaveBeenCalledWith('/auth/2fa/enroll/start', { challenge: 'setup-ch' })

    await auth.completeTwoFactorEnrollment('123456')
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.requiresTwoFactorSetup).toBe(false)
    expect(sessionStorage.getItem('auth_token')).toBe('tok-e')
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
    expect(sessionStorage.getItem('auth_token')).toBeNull()
    expect(api.post).not.toHaveBeenCalled()
  })
})
