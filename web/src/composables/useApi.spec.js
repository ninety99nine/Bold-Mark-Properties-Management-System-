import { describe, it, expect, vi, beforeEach } from 'vitest'

// ──────────────────────────────────────────────────────────────────────────────
// useApi 401 interceptor — session-expiry handling (BM-006).
// On a 401 it must clear the session and SPA-redirect to /login, preserving the
// current route as ?redirect= (and flagging ?expired=1) — never a hard reload.
// The interceptor dynamically imports the store and router, so we mock both.
// ──────────────────────────────────────────────────────────────────────────────

const replaceMock      = vi.fn()
const clearSessionMock = vi.fn()
let currentRoute

vi.mock('@/router', () => ({
  default: {
    get currentRoute() {
      return { value: currentRoute }
    },
    replace: replaceMock,
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ clearSession: clearSessionMock }),
}))

import api from '@/composables/useApi'

function reject401(data = {}) {
  // Make axios's adapter fail with a 401-shaped error.
  api.defaults.adapter = async () => {
    const err = new Error('Unauthorized')
    err.response = { status: 401, data }
    throw err
  }
}

describe('useApi 401 interceptor', () => {
  beforeEach(() => {
    replaceMock.mockClear()
    clearSessionMock.mockClear()
    currentRoute = { name: 'community-detail', fullPath: '/communities/5' }
    localStorage.clear()
  })

  it('clears the session and redirects to login preserving the current route', async () => {
    reject401()

    await expect(api.get('/auth/me')).rejects.toBeTruthy()

    expect(clearSessionMock).toHaveBeenCalledOnce()
    expect(replaceMock).toHaveBeenCalledWith({
      name: 'login',
      query: { redirect: '/communities/5', expired: '1' },
    })
  })

  it('flags reason=inactivity only when the server reports an inactivity timeout', async () => {
    reject401({ message: 'Your session has expired due to inactivity. Please log in again.' })

    await expect(api.get('/auth/me')).rejects.toBeTruthy()

    expect(replaceMock).toHaveBeenCalledWith({
      name: 'login',
      query: { redirect: '/communities/5', expired: '1', reason: 'inactivity' },
    })
  })

  it('does not redirect when the 401 happens on the login page itself', async () => {
    currentRoute = { name: 'login', fullPath: '/login' }
    reject401()

    await expect(api.post('/auth/login', {})).rejects.toBeTruthy()

    expect(clearSessionMock).toHaveBeenCalledOnce()
    expect(replaceMock).not.toHaveBeenCalled()
  })

  it('passes non-401 errors through without touching the session', async () => {
    api.defaults.adapter = async () => {
      const err = new Error('Server error')
      err.response = { status: 500, data: {} }
      throw err
    }

    await expect(api.get('/communities')).rejects.toBeTruthy()

    expect(clearSessionMock).not.toHaveBeenCalled()
    expect(replaceMock).not.toHaveBeenCalled()
  })
})
