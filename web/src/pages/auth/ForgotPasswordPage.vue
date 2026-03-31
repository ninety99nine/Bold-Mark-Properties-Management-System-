<script setup>
import { ref, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import AuthBrandPanel from '@/components/auth/AuthBrandPanel.vue'
import api from '@/composables/useApi'

const tenant = useTenantStore()

const email = ref('')
const sent = ref(false)
const error = ref('')
const loading = ref(false)
const mounted = ref(false)

onMounted(() => {
  requestAnimationFrame(() => {
    mounted.value = true
  })
})

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
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
          >Account Security</span>
        </div>

        <!-- Heading -->
        <h1
          class="text-white text-4xl xl:text-5xl leading-tight mb-5 transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ fontFamily: '\'DM Serif Display\', serif', transitionDelay: '250ms' }"
        >
          Secure &amp;<br /><em>Trusted.</em>
        </h1>

        <!-- Subtitle -->
        <p
          class="text-white/60 text-base leading-relaxed max-w-sm transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ transitionDelay: '350ms' }"
        >
          We take the security of your property management data seriously. Password resets are sent securely to your registered email.
        </p>
      </template>
    </AuthBrandPanel>

    <!-- Right panel — Form -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-bg">
      <div class="lg:hidden mb-10">
        <img :src="tenant.logoUrl" :alt="tenant.name" class="h-8" />
      </div>

      <div
        class="w-full max-w-sm transition-all duration-700 ease-out"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        :style="{ transitionDelay: '150ms' }"
      >
        <!-- Back link -->
        <RouterLink
          to="/login"
          class="inline-flex items-center gap-2 text-sm font-medium text-muted-fg hover:text-fg transition-colors mb-8"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to sign in
        </RouterLink>

        <!-- Success state -->
        <Transition
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
        >
          <div v-if="sent">
            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-6" style="background-color: #D89B4B20;">
              <svg class="w-7 h-7" style="color: #D89B4B;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Check your inbox</h2>
            <p class="text-muted-fg text-sm leading-relaxed mb-8">
              We've sent a password reset link to <strong class="text-fg">{{ email }}</strong>.
              The link expires in 60 minutes.
            </p>
            <AppButton variant="secondary" size="lg" full @click="sent = false; email = ''">
              Send another link
            </AppButton>
          </div>
        </Transition>

        <!-- Form state -->
        <div v-if="!sent">
          <div class="mb-8">
            <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Reset password</h2>
            <p class="text-muted-fg text-sm leading-relaxed">
              Enter your email and we'll send you a secure link to reset your password.
            </p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-5">
            <AppInput
              id="email"
              v-model="email"
              label="Email address"
              type="email"
              placeholder="you@company.com"
              required
            />

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
              {{ loading ? 'Sending…' : 'Send reset link' }}
            </AppButton>
          </form>
        </div>

        <p class="mt-8 text-center text-xs text-muted-fg">
          © {{ new Date().getFullYear() }} {{ tenant.copyrightName }} · All rights reserved
        </p>
      </div>
    </div>

  </div>
</template>
