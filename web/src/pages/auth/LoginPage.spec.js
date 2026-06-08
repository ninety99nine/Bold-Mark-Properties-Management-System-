import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import LoginPage from '@/pages/auth/LoginPage.vue'

// ──────────────────────────────────────────────────────────────────────────────
// BM-010 — Browser Password Storage Verification
//
// Browsers only offer to save / autofill credentials when the form fields carry
// the correct `autocomplete` tokens. This is a frontend-only behaviour (there is
// no backend endpoint to PEST-test), so we verify it at the component level:
//   • email field    → autocomplete="email"
//   • password field → autocomplete="current-password"
//
// Also covers the BM-009 "remember me" checkbox and the BM-006 session-expired
// flow (notice + return-to-last-page redirect after re-login).
// ──────────────────────────────────────────────────────────────────────────────

const loginMock = vi.fn().mockResolvedValue({ two_factor_required: false })
const pushMock  = vi.fn()

// Mutable route query so individual tests can simulate ?expired / ?redirect.
let routeQuery = {}

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute:  () => ({ query: routeQuery }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    login:             loginMock,
    loginWithTwoFactor: vi.fn(),
    cancelTwoFactor:   vi.fn(),
  }),
}))

vi.mock('@/stores/organization', () => ({
  useOrganizationStore: () => ({
    logoUrl:       '',
    name:          'BoldMark',
    copyrightName: 'BoldMark Properties',
    accentColor:   '#1a2744',
  }),
}))

function mountLogin() {
  return mount(LoginPage, {
    global: {
      stubs: {
        RouterLink:     true,
        AuthBrandPanel: true,
        AppButton:      { template: '<button :type="type"><slot /></button>', props: ['type'] },
      },
    },
  })
}

describe('LoginPage', () => {
  beforeEach(() => {
    loginMock.mockClear().mockResolvedValue({ two_factor_required: false })
    pushMock.mockClear()
    routeQuery = {}
  })

  // ── BM-010 ────────────────────────────────────────────────────────────────
  it('marks the email field with autocomplete="email"', () => {
    expect(mountLogin().find('#email').attributes('autocomplete')).toBe('email')
  })

  it('marks the password field with autocomplete="current-password"', () => {
    expect(mountLogin().find('#password').attributes('autocomplete')).toBe('current-password')
  })

  // ── BM-009 ────────────────────────────────────────────────────────────────
  it('renders a "remember me" checkbox', () => {
    const checkbox = mountLogin().find('#remember')
    expect(checkbox.exists()).toBe(true)
    expect(checkbox.attributes('type')).toBe('checkbox')
  })

  it('passes the remember flag to the auth store on submit', async () => {
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('#remember').setValue(true)
    await wrapper.find('form').trigger('submit.prevent')

    expect(loginMock).toHaveBeenCalledWith('manager@boldmark.test', 'password123', true)
  })

  it('defaults remember to false when the box is left unchecked', async () => {
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('form').trigger('submit.prevent')

    expect(loginMock).toHaveBeenCalledWith('manager@boldmark.test', 'password123', false)
  })

  // ── BM-006 — expired session UX ─────────────────────────────────────────────
  it('shows a session-expired notice when arriving with ?expired=1', () => {
    routeQuery = { expired: '1' }
    expect(mountLogin().text()).toContain('Your session expired due to inactivity')
  })

  it('does not show the expired notice on a normal visit', () => {
    expect(mountLogin().text()).not.toContain('Your session expired due to inactivity')
  })

  it('returns the user to the page they were on (redirect query) after login', async () => {
    routeQuery = { redirect: '/billing/invoices/42', expired: '1' }
    const wrapper = mountLogin()

    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('form').trigger('submit.prevent')
    await Promise.resolve()

    expect(pushMock).toHaveBeenCalledWith('/billing/invoices/42')
  })

  it('falls back to /dashboard when there is no redirect target', async () => {
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('form').trigger('submit.prevent')
    await Promise.resolve()

    expect(pushMock).toHaveBeenCalledWith('/dashboard')
  })
})
