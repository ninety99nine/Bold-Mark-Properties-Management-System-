<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppInput from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || '/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'The email or password you entered is incorrect.'
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
      style="background: radial-gradient(ellipse at 15% 85%, #2D4A70 0%, transparent 55%), radial-gradient(ellipse at 85% 15%, rgba(216,155,75,0.08) 0%, transparent 45%), #1F3A5C;"
    >
      <!-- Decorative grid pattern -->
      <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;" />

      <!-- Decorative amber circle -->
      <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, #D89B4B, transparent);" />
      <div class="absolute top-1/3 -left-16 w-64 h-64 rounded-full opacity-5" style="background: radial-gradient(circle, #D89B4B, transparent);" />

      <!-- Logo -->
      <div class="relative z-10">
        <div class="flex items-center gap-4">
          <img src="/logo.png" alt="Bold Mark" class="h-8 brightness-0 invert" />
          <div class="w-px h-8 bg-white/20" />
          <div class="text-white/50 text-xs uppercase tracking-widest">Property Management</div>
        </div>
      </div>

      <!-- Centre content -->
      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-px" style="background-color: #D89B4B;" />
          <span class="text-xs font-bold uppercase tracking-widest" style="color: #D89B4B;">Management Platform</span>
        </div>
        <h1 class="text-white text-4xl xl:text-5xl leading-tight mb-5" style="font-family: 'DM Serif Display', serif;">
          Moving People<br /><em>Forward.</em>
        </h1>
        <p class="text-white/60 text-base leading-relaxed max-w-sm">
          The complete property management platform for South African Body Corporate and HOA managing agents.
        </p>

        <!-- Feature pills -->
        <div class="mt-8 flex flex-wrap gap-2">
          <span v-for="tag in ['Levy Billing', 'Debt Management', 'Compliance', 'Financials', 'Communications']" :key="tag"
            class="px-3 py-1.5 rounded text-xs font-medium text-white/70 border border-white/10 bg-white/5">
            {{ tag }}
          </span>
        </div>
      </div>

      <!-- Footer -->
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
      <!-- Mobile logo -->
      <div class="lg:hidden mb-10">
        <img src="/logo.png" alt="Bold Mark" class="h-8" />
      </div>

      <div class="w-full max-w-sm">
        <!-- Heading -->
        <div class="mb-8">
          <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Welcome back</h2>
          <p class="text-muted-fg text-sm">Sign in to your management portal</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-5">
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
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-fg hover:text-fg transition-colors"
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
          </div>

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
          © {{ new Date().getFullYear() }} Bold Mark Properties · All rights reserved
        </p>
      </div>
    </div>

  </div>
</template>
