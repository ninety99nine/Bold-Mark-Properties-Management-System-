<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'
import api from '@/composables/useApi'

const route = useRoute()
const router = useRouter()

const token = ref(route.query.token || '')
const email = ref(route.query.email || '')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const done = ref(false)
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
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
    <div
      class="hidden lg:flex lg:w-[58%] flex-col justify-between p-12 relative overflow-hidden"
      style="background: radial-gradient(ellipse at 15% 85%, #1A3254 0%, transparent 55%), radial-gradient(ellipse at 85% 15%, rgba(216,155,75,0.06) 0%, transparent 45%), #0B1F38;"
    >
      <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;" />
      <div class="relative z-10">
        <div class="flex items-center gap-4">
          <img src="/assets/logo2-CB_yk5b_.png" alt="Bold Mark" class="h-8" />
          <div class="w-px h-8 bg-white/20" />
          <div class="text-white/50 text-xs uppercase tracking-widest">Property Management</div>
        </div>
      </div>

      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-px" style="background-color: #D89B4B;" />
          <span class="text-xs font-bold uppercase tracking-widest" style="color: #D89B4B;">Account Security</span>
        </div>
        <h1 class="text-white text-4xl xl:text-5xl leading-tight mb-5" style="font-family: 'DM Serif Display', serif;">
          Create your<br /><em>New Password.</em>
        </h1>
        <p class="text-white/60 text-base leading-relaxed max-w-sm">
          Choose a strong password to keep your property management account secure.
        </p>
      </div>

      <div class="relative z-10 flex items-center gap-6 text-white/30 text-xs">
        <span>NAMA-9141</span>
        <span class="w-px h-3 bg-white/20" />
        <span>PPRA Registered</span>
        <span class="w-px h-3 bg-white/20" />
        <span>Johannesburg · Botswana</span>
      </div>
    </div>

    <!-- Right panel — Form -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-bg">
      <div class="lg:hidden mb-10">
        <img src="/logo.png" alt="Bold Mark" class="h-8" />
      </div>

      <div class="w-full max-w-sm">
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
          <div v-if="done">
            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-6" style="background-color: #22c55e20;">
              <svg class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Password updated</h2>
            <p class="text-muted-fg text-sm leading-relaxed mb-8">
              Your password has been reset successfully. You can now sign in with your new password.
            </p>
            <AppButton variant="primary" size="lg" full @click="$router.push('/login')">
              Sign in
            </AppButton>
          </div>
        </Transition>

        <!-- Form state -->
        <div v-if="!done">
          <div class="mb-8">
            <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Reset password</h2>
            <p class="text-muted-fg text-sm leading-relaxed">
              Enter your new password below.
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

            <div class="relative">
              <AppInput
                id="password"
                v-model="password"
                label="New password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Min. 8 characters"
                required
              />
              <button
                type="button"
                class="absolute right-3 top-8 text-muted-fg hover:text-fg transition-colors"
                @click="showPassword = !showPassword"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </button>
            </div>

            <AppInput
              id="password_confirmation"
              v-model="passwordConfirmation"
              label="Confirm new password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Repeat your password"
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
              {{ loading ? 'Updating…' : 'Set new password' }}
            </AppButton>
          </form>
        </div>

        <p class="mt-8 text-center text-xs text-muted-fg">
          © {{ new Date().getFullYear() }} Bold Mark Properties · All rights reserved
        </p>
      </div>
    </div>

  </div>
</template>
