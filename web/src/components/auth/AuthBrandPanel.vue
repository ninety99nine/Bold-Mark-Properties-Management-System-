<script setup>
import { useTenantStore } from '@/stores/tenant'

const tenant = useTenantStore()
</script>

<template>
  <div
    class="hidden lg:flex lg:w-[58%] flex-col justify-between p-12 relative overflow-hidden"
    style="background: radial-gradient(ellipse at 15% 85%, #1A3254 0%, transparent 55%), radial-gradient(ellipse at 85% 15%, rgba(216,155,75,0.06) 0%, transparent 45%), #0B1F38;"
  >
    <!-- Decorative grid -->
    <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;" />

    <!-- Logo -->
    <div class="relative z-10">
      <div class="flex items-center gap-4">
        <img :src="tenant.logoUrl" :alt="tenant.name" class="h-8" />
        <div class="w-px h-8 bg-white/20" />
        <div class="text-white/50 text-xs uppercase tracking-widest">Property Management</div>
      </div>
    </div>

    <!-- Centre content — page-specific via slot -->
    <div class="relative z-10">
      <slot :accent-color="tenant.accentColor" />
    </div>

    <!-- Footer credentials — tenant-specific -->
    <div class="relative z-10 flex items-center gap-6 text-white/30 text-xs">
      <template v-for="(cred, i) in tenant.credentials" :key="cred">
        <span v-if="i > 0" class="w-px h-3 bg-white/20" />
        <span>{{ cred }}</span>
      </template>
    </div>
  </div>
</template>
