import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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

// 2FA is mandatory (BUG-003): login always resolves to a second-factor step,
// never straight through. Enrolled users get a TOTP challenge.
const loginMock              = vi.fn().mockResolvedValue({ two_factor_required: true })
const loginWithTwoFactorMock = vi.fn().mockResolvedValue()
const pushMock               = vi.fn()

// Mutable route query so individual tests can simulate ?expired / ?redirect.
let routeQuery = {}

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute:  () => ({ query: routeQuery }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    login:              loginMock,
    loginWithTwoFactor: loginWithTwoFactorMock,
    startTwoFactorEnrollment:    vi.fn(),
    completeTwoFactorEnrollment: vi.fn(),
    cancelTwoFactor:    vi.fn(),
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
    loginMock.mockClear().mockResolvedValue({ two_factor_required: true })
    loginWithTwoFactorMock.mockClear().mockResolvedValue()
    pushMock.mockClear()
    routeQuery = {}
  })

  // Drive a login through the mandatory TOTP challenge step.
  async function completeLoginWith2fa(wrapper) {
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()
    const verifyBtn = wrapper.findAll('button').find(b => b.text().includes('Verify'))
    await verifyBtn.trigger('click')
    await flushPromises()
  }

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

  it('returns the user to the page they were on (redirect query) after completing 2FA', async () => {
    routeQuery = { redirect: '/billing/invoices/42', expired: '1' }
    const wrapper = mountLogin()

    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await completeLoginWith2fa(wrapper)

    expect(loginWithTwoFactorMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith('/billing/invoices/42')
  })

  it('falls back to /select-profile when there is no redirect target', async () => {
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await completeLoginWith2fa(wrapper)

    expect(pushMock).toHaveBeenCalledWith('/select-profile')
  })

  // Opening the app cold at the root makes the router guard bounce to
  // /login?redirect=/dashboard. That generic landing target must NOT skip the
  // profile-selection anchor — otherwise first-time (2FA-setup) logins land
  // straight on the dashboard.
  it('routes to /select-profile even when the redirect is the generic /dashboard target', async () => {
    routeQuery = { redirect: '/dashboard' }
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await completeLoginWith2fa(wrapper)

    expect(pushMock).toHaveBeenCalledWith('/select-profile')
  })

  it('does not issue a redirect on the password step alone — it shows the 2FA challenge first (BUG-003)', async () => {
    const wrapper = mountLogin()
    await wrapper.find('#email').setValue('manager@boldmark.test')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Two-Factor Authentication')
  })
})
