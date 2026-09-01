<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOrganizationStore } from '@/stores/organization'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import AuthBrandPanel from '@/components/auth/AuthBrandPanel.vue'

const auth = useAuthStore()
const organization = useOrganizationStore()
const router = useRouter()
const route = useRoute()

const email       = ref('')
const password    = ref('')
const remember    = ref(false)
const showPassword = ref(false)
const error       = ref('')
const loading     = ref(false)
const mounted     = ref(false)

// Set when the user was bounced here by an expired/invalid session (BM-006).
// Only a genuine inactivity timeout carries reason=inactivity; every other 401
// (revoked/invalid token, etc.) gets neutral wording so it isn't mislabelled.
const sessionExpired = ref(route.query.expired === '1')
const expiredMessage = computed(() =>
  route.query.reason === 'inactivity'
    ? 'Your session expired due to inactivity. Sign in again to pick up where you left off.'
    : 'Your session ended. Please sign in again to continue.'
)

// Where to land once authentication (incl. 2FA) completes.
//
// The profile-selection screen is the deliberate entry anchor (WeConnectU
// parity) and must ALWAYS be shown first — even when a `redirect` query is
// present (e.g. a session-expiry bounce off a deep link like /compliance).
// Login is the start of a fresh session, so the user re-selects their profile
// before continuing.
function resolvePostAuthTarget() {
  return '/select-profile'
}

// 2FA challenge state
const twoFactorCode    = ref('')
const twoFaError       = ref('')
const twoFaLoading     = ref(false)
const showTwoFa        = ref(false)

// Forced 2FA enrollment state (mandatory — BUG-003). Shown when a user who has
// not set up 2FA signs in; they cannot reach the app until they enroll.
const showTwoFaSetup   = ref(false)
const setupQrDataUrl   = ref('')
const setupSecret      = ref('')
const setupCode        = ref('')
const setupError       = ref('')
const setupStarting    = ref(false)
const setupConfirming  = ref(false)
const setupCopied      = ref(false)

async function copySetupSecret() {
  try {
    await navigator.clipboard.writeText(setupSecret.value)
    setupCopied.value = true
    setTimeout(() => { setupCopied.value = false }, 2000)
  } catch { /* clipboard unavailable — the code stays selectable */ }
}

onMounted(() => {
  requestAnimationFrame(() => {
    mounted.value = true
  })
})

async function handleLogin() {
  error.value          = ''
  sessionExpired.value = false
  loading.value        = true
  try {
    const result = await auth.login(email.value, password.value, remember.value)
    if (result.two_factor_setup_required) {
      showTwoFaSetup.value = true
      await beginTwoFaSetup()
    } else {
      showTwoFa.value = true
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'The email or password you entered is incorrect.'
  } finally {
    loading.value = false
  }
}

async function handleTwoFactor() {
  twoFaError.value   = ''
  twoFaLoading.value = true
  try {
    await auth.loginWithTwoFactor(twoFactorCode.value)
    router.push(resolvePostAuthTarget())
  } catch (e) {
    twoFaError.value = e.response?.data?.message || 'Invalid code. Please try again.'
  } finally {
    twoFaLoading.value = false
  }
}

// Fetch a fresh secret + QR for the forced enrollment step.
async function beginTwoFaSetup() {
  setupError.value    = ''
  setupStarting.value = true
  try {
    const { qr_uri, secret } = await auth.startTwoFactorEnrollment()
    setupSecret.value = secret
    const QRCode = (await import('qrcode')).default
    setupQrDataUrl.value = await QRCode.toDataURL(qr_uri, { width: 200, margin: 2 })
  } catch (e) {
    setupError.value = e.response?.data?.message || 'Could not start setup. Please sign in again.'
  } finally {
    setupStarting.value = false
  }
}

async function confirmTwoFaSetup() {
  setupError.value      = ''
  setupConfirming.value = true
  try {
    await auth.completeTwoFactorEnrollment(setupCode.value)
    router.push(resolvePostAuthTarget())
  } catch (e) {
    setupError.value = e.response?.data?.message || 'Invalid code. Please try again.'
  } finally {
    setupConfirming.value = false
  }
}

function cancelTwoFactor() {
  showTwoFa.value       = false
  showTwoFaSetup.value  = false
  twoFactorCode.value   = ''
  twoFaError.value      = ''
  setupCode.value       = ''
  setupSecret.value     = ''
  setupQrDataUrl.value  = ''
  setupError.value      = ''
  auth.cancelTwoFactor()
}
</script>

<template>
  <div class="min-h-screen flex">

    <!-- Left panel — Brand -->
    <AuthBrandPanel>
      <template #default="{ accentColor, visible }">
        <!-- Accent label -->
        <div class="flex items-center gap-3 mb-6 overflow-hidden">
          <div
            class="h-px transition-all duration-700 ease-out"
            :class="visible ? 'w-8 opacity-100' : 'w-0 opacity-0'"
            :style="{ backgroundColor: accentColor }"
          />
          <span
            class="text-xs font-bold uppercase tracking-widest transition-all duration-500 ease-out"
            :style="{ color: accentColor, transitionDelay: '150ms' }"
            :class="visible ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-3'"
          >Management Platform</span>
        </div>

        <!-- Heading -->
        <h1
          class="text-white text-4xl xl:text-5xl leading-tight mb-5 transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ fontFamily: '\'DM Serif Display\', serif', transitionDelay: '250ms' }"
        >
          Moving People<br /><em>Forward.</em>
        </h1>

        <!-- Subtitle -->
        <p
          class="text-white/60 text-base leading-relaxed max-w-sm transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ transitionDelay: '350ms' }"
        >
          The complete property management platform for Body Corporate and HOA managing agents.
        </p>

        <!-- Feature tags — staggered -->
        <div class="mt-8 flex flex-wrap gap-2">
          <span
            v-for="(tag, index) in ['Levy Billing', 'Debt Management', 'Compliance', 'Financials', 'Communications']"
            :key="tag"
            class="px-3 py-1.5 rounded text-xs font-medium text-white/70 border border-white/10 bg-white/5 transition-all duration-500 ease-out"
            :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
            :style="{ transitionDelay: (480 + index * 75) + 'ms' }"
          >
            {{ tag }}
          </span>
        </div>
      </template>
    </AuthBrandPanel>

    <!-- Right panel — Form -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-bg">
      <!-- Mobile logo -->
      <div class="lg:hidden mb-10">
        <img :src="organization.logoUrl" :alt="organization.name" class="h-8" />
      </div>

      <div
        class="w-full max-w-sm transition-all duration-700 ease-out"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        :style="{ transitionDelay: '150ms' }"
      >
        <!-- Heading -->
        <div class="mb-8">
          <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Welcome back</h2>
          <p class="text-muted-fg text-sm">Sign in to your management portal</p>
        </div>

        <!-- 2FA Challenge -->
        <div v-if="showTwoFa" class="space-y-5">
          <div class="p-4 rounded-lg border border-border bg-muted/40">
            <div class="flex items-center gap-2 mb-1">
              <svg class="w-4 h-4" :style="{ color: '#D89B4B' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
              <p class="text-sm font-semibold text-fg">Two-Factor Authentication</p>
            </div>
            <p class="text-xs text-muted-fg">Open your authenticator app and enter the 6-digit code to continue.</p>
          </div>
          <AppInput
            v-model="twoFactorCode"
            label="Authentication Code"
            placeholder="000000"
            inputmode="numeric"
            maxlength="6"
            autofocus
          />
          <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
            <div v-if="twoFaError" class="flex items-start gap-2.5 px-4 py-3 rounded border text-sm" style="background-color:#FFF5F5;border-color:#F75A68;color:#C01C2C;">
              <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
              {{ twoFaError }}
            </div>
          </Transition>
          <AppButton type="button" variant="primary" size="lg" :loading="twoFaLoading" full @click="handleTwoFactor">
            {{ twoFaLoading ? 'Verifying…' : 'Verify Code' }}
          </AppButton>
          <button type="button" class="w-full text-center text-xs text-muted-fg hover:underline mt-2" @click="cancelTwoFactor">
            ← Back to login
          </button>
        </div>

        <!-- Forced 2FA setup (mandatory — BUG-003) -->
        <div v-if="showTwoFaSetup" class="space-y-5">
          <div class="p-4 rounded-lg border border-border bg-muted/40">
            <div class="flex items-center gap-2 mb-1">
              <svg class="w-4 h-4" :style="{ color: '#D89B4B' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
              <p class="text-sm font-semibold text-fg">Set up Two-Factor Authentication</p>
            </div>
            <p class="text-xs text-muted-fg">
              Add a quick extra layer of security to your account. Scan the QR code with
              <strong>Microsoft Authenticator</strong> or <strong>Apple Passwords</strong> (Verification Codes),
              then enter the 6-digit code to finish signing in.
            </p>
          </div>

          <div class="flex flex-col items-center gap-3">
            <div class="bg-white p-3 rounded-lg border">
              <img v-if="setupQrDataUrl" :src="setupQrDataUrl" alt="2FA QR Code" class="w-44 h-44" />
              <div v-else class="w-44 h-44 flex items-center justify-center text-muted-fg text-xs">
                {{ setupStarting ? 'Generating…' : 'Loading…' }}
              </div>
            </div>
            <div v-if="setupSecret" class="w-full">
              <p class="text-xs text-muted-fg mb-1">Or enter this code manually:</p>
              <div class="flex items-stretch gap-2">
                <code class="flex-1 text-xs font-mono bg-muted px-3 py-2 rounded break-all select-all">{{ setupSecret }}</code>
                <button
                  type="button"
                  class="shrink-0 px-3 rounded border border-border text-xs font-medium transition-colors flex items-center gap-1.5"
                  :class="setupCopied ? 'text-green-600 border-green-200 bg-green-50' : 'text-muted-fg hover:text-fg hover:bg-muted'"
                  :title="setupCopied ? 'Copied' : 'Copy code'"
                  @click="copySetupSecret"
                >
                  <svg v-if="!setupCopied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                  <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  {{ setupCopied ? 'Copied' : 'Copy' }}
                </button>
              </div>
            </div>
          </div>

          <AppInput
            v-model="setupCode"
            label="Enter the 6-digit code from your app"
            placeholder="000000"
            inputmode="numeric"
            maxlength="6"
          />

          <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
            <div v-if="setupError" class="flex items-start gap-2.5 px-4 py-3 rounded border text-sm" style="background-color:#FFF5F5;border-color:#F75A68;color:#C01C2C;">
              <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
              {{ setupError }}
            </div>
          </Transition>

          <AppButton type="button" variant="primary" size="lg" :loading="setupConfirming" :disabled="!setupSecret" full @click="confirmTwoFaSetup">
            {{ setupConfirming ? 'Verifying…' : 'Verify & Continue' }}
          </AppButton>
          <button type="button" class="w-full text-center text-xs text-muted-fg hover:underline mt-2" @click="cancelTwoFactor">
            ← Back to login
          </button>
        </div>

        <!-- Session expired notice (BM-006) -->
        <div
          v-if="sessionExpired && !showTwoFa && !showTwoFaSetup"
          class="flex items-start gap-2.5 px-4 py-3 mb-5 rounded border text-sm"
          style="background-color:#FFFBEB;border-color:#F59E0B;color:#92400E;"
        >
          <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.5 2.5a1 1 0 001.414-1.414L11 9.586V6z" clip-rule="evenodd" /></svg>
          {{ expiredMessage }}
        </div>

        <!-- Form -->
        <form v-if="!showTwoFa && !showTwoFaSetup" @submit.prevent="handleLogin" class="space-y-5">
          <AppInput
            id="email"
            v-model="email"
            label="Email address"
            type="email"
            placeholder="you@company.com"
            required
            autocomplete="email"
          />

          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label for="password" class="text-sm font-medium text-fg">Password</label>
              <RouterLink to="/forgot-password" class="text-xs font-medium transition-colors hover:opacity-80" style="color: #D89B4B;">
                Forgot password?
              </RouterLink>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••••"
                required
                autocomplete="current-password"
                :class="[
                  'w-full px-4 py-3 pr-11 text-sm text-fg bg-white border-2 rounded transition-all duration-200 outline-none',
                  'placeholder:text-muted-fg',
                  error
                    ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger/20'
                    : 'border-border hover:border-muted-fg focus:ring-2',
                ]"
                :style="!error ? 'border-color: #DCDEE8;' : ''"
                @focus="$event.target.style.borderColor = '#D89B4B'"
                @blur="$event.target.style.borderColor = error ? '#F75A68' : '#DCDEE8'"
              />
              <AppButton
                type="button"
                variant="ghost"
                square
                size="sm"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-fg hover:text-fg hover:bg-transparent"
                @click="showPassword = !showPassword"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </AppButton>
            </div>
          </div>

          <!-- Remember me -->
          <label class="flex items-center gap-2.5 cursor-pointer select-none">
            <input
              id="remember"
              v-model="remember"
              type="checkbox"
              class="h-4 w-4 rounded border-2 border-border text-primary focus:ring-2 focus:ring-primary/20 cursor-pointer"
              style="accent-color: #D89B4B;"
            />
            <span class="text-sm text-muted-fg">Keep me signed in</span>
          </label>

          <!-- Error -->
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
          >
            <div v-if="error" class="flex items-start gap-2.5 px-4 py-3 rounded border text-sm" style="background-color: #FFF5F5; border-color: #F75A68; color: #C01C2C;">
              <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              {{ error }}
            </div>
          </Transition>

          <AppButton type="submit" variant="primary" size="lg" :loading="loading" full>
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </AppButton>
        </form>

        <!-- Footer -->
        <p class="mt-8 text-center text-xs text-muted-fg">
          © {{ new Date().getFullYear() }} {{ organization.copyrightName }} · All rights reserved
        </p>
      </div>
    </div>

  </div>
</template>
