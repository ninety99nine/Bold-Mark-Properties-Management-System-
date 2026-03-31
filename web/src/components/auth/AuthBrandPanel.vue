<script setup>
import { ref, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()
const visible = ref(false)
onMounted(() => {
  requestAnimationFrame(() => {
    visible.value = true
  })
})
</script>

<template>
  <div
    class="hidden lg:flex lg:w-[58%] flex-col justify-between p-12 relative overflow-hidden"
    style="background: radial-gradient(ellipse at 15% 85%, #1A3254 0%, transparent 55%), radial-gradient(ellipse at 85% 15%, rgba(216,155,75,0.06) 0%, transparent 45%), #0B1F38;"
  >
    <!-- Decorative grid -->
    <div class="absolute inset-0 grid-bg" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;" />

    <!-- Shimmer sweep -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="shimmer-sweep" />
    </div>

    <!-- Logo -->
    <div
      class="relative z-10 transition-all duration-700 ease-out"
      :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-3'"
    >
      <div class="flex items-center gap-4">
        <img :src="tenant.logoUrl" :alt="tenant.name" class="h-8" />
        <div class="w-px h-8 bg-white/20" />
        <div class="text-white/50 text-xs uppercase tracking-widest">Property Management</div>
      </div>
    </div>

    <!-- Centre content — page-specific via slot -->
    <div class="relative z-10">
      <slot :accent-color="tenant.accentColor" :visible="visible" />
    </div>

    <!-- Footer -->
    <div
      class="relative z-10 space-y-4 transition-all duration-700 ease-out"
      :style="{ transitionDelay: '500ms' }"
      :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
    >
      <!-- Trust keywords -->
      <div class="flex items-center gap-4 text-white/25 text-xs">
        <span>Transparent</span>
        <span class="w-px h-3 bg-white/15" />
        <span>Secure</span>
        <span class="w-px h-3 bg-white/15" />
        <span>Reliable</span>
      </div>

      <!-- Credentials — shown only when the tenant has registrations -->
      <div v-if="tenant.credentials.length" class="flex items-center gap-6 text-white/30 text-xs">
        <template v-for="(cred, i) in tenant.credentials" :key="cred">
          <span v-if="i > 0" class="w-px h-3 bg-white/20" />
          <span>{{ cred }}</span>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.grid-bg {
  opacity: 0.04;
  animation: gridPulse 9s ease-in-out infinite;
}
@keyframes gridPulse {
  0%, 100% { opacity: 0.04; }
  50%       { opacity: 0.08; }
}


.shimmer-sweep {
  position: absolute;
  top: 0;
  left: -60%;
  width: 50%;
  height: 100%;
  background: linear-gradient(
    108deg,
    transparent 35%,
    rgba(255,255,255,0.035) 50%,
    transparent 65%
  );
  animation: sweep 6s linear infinite 3s;
}
@keyframes sweep {
  0%   { left: -60%; }
  100% { left: 160%; }
}
</style>
