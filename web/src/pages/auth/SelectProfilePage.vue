<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCommunityStore } from '@/stores/community'
import { useOrganizationStore } from '@/stores/organization'
import AppInput from '@/components/common/AppInput.vue'
import AuthBrandPanel from '@/components/auth/AuthBrandPanel.vue'

const router         = useRouter()
const communityStore = useCommunityStore()
const organization   = useOrganizationStore()

const search   = ref('')
const mounted   = ref(false)
const entering = ref(null) // { label } while transitioning into the chosen profile

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return communityStore.communities
  return communityStore.communities.filter(c =>
    c.name.toLowerCase().includes(q) || (c.address ?? '').toLowerCase().includes(q)
  )
})

onMounted(() => {
  communityStore.fetch()
  requestAnimationFrame(() => { mounted.value = true })
})

function chooseGlobal() {
  if (entering.value) return
  entering.value = { label: 'Global Dashboard' }
  communityStore.clearSelection()
  setTimeout(() => router.push('/dashboard'), 650)
}

function chooseCommunity(community) {
  if (entering.value) return
  entering.value = { label: community.name }
  communityStore.select(community.id, community)
  setTimeout(() => router.push(`/communities/${community.id}?login=1`), 650)
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- Left panel — Brand -->
    <AuthBrandPanel>
      <template #default="{ accentColor, visible }">
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
        <h1
          class="text-white text-4xl xl:text-5xl leading-tight mb-5 transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ fontFamily: '\'DM Serif Display\', serif', transitionDelay: '250ms' }"
        >
          Choose your<br /><em>workspace.</em>
        </h1>
        <p
          class="text-white/60 text-base leading-relaxed max-w-sm transition-all duration-700 ease-out"
          :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'"
          :style="{ transitionDelay: '350ms' }"
        >
          Work across your whole portfolio from the Global dashboard, or step into a single community.
        </p>
      </template>
    </AuthBrandPanel>

    <!-- Right panel — Profile selection -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-bg">
      <div class="lg:hidden mb-10">
        <img :src="organization.logoUrl" :alt="organization.name" class="h-8" />
      </div>

      <div
        class="w-full max-w-md transition-all duration-700 ease-out"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        :style="{ transitionDelay: '150ms' }"
      >
        <!-- Entering state — mirrors the chosen profile + a "Logging in" confirmation -->
        <div v-if="entering" class="text-center py-10">
          <div class="w-12 h-12 mx-auto mb-5 rounded-full border-2 border-accent/25 border-t-accent animate-spin"></div>
          <p class="text-lg font-semibold text-fg mb-1">{{ entering.label }}</p>
          <p class="text-sm font-medium text-emerald-600">Logging in…</p>
        </div>

        <template v-else>
        <div class="mb-7">
          <h2 class="text-3xl text-fg mb-2" style="font-family: 'DM Serif Display', serif;">Select a profile</h2>
          <p class="text-muted-fg text-sm">Where would you like to work today?</p>
        </div>

        <!-- Global Dashboard -->
        <button
          type="button"
          class="group w-full flex items-center gap-4 p-4 rounded-xl border border-border bg-card hover:border-accent hover:shadow-sm transition-all duration-150 text-left mb-6"
          @click="chooseGlobal"
        >
          <div class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 group-hover:bg-accent/15 group-hover:text-accent transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="w-[22px] h-[22px]">
              <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-body font-semibold text-[15px] text-fg">Global Dashboard</p>
            <p class="text-xs text-muted-fg">Portfolio-wide view across all communities</p>
          </div>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted-fg group-hover:text-accent transition-colors shrink-0"><path d="m9 18 6-6-6-6"/></svg>
        </button>

        <!-- Communities -->
        <div class="flex items-center gap-3 mb-3">
          <span class="text-[10px] uppercase tracking-widest text-muted-fg font-semibold">Communities</span>
          <div class="flex-1 h-px bg-border"></div>
        </div>

        <AppInput v-model="search" leading-icon="search" placeholder="Search communities…" class="mb-3" />

        <div class="max-h-[320px] overflow-y-auto -mr-2 pr-2 space-y-1.5">
          <div v-if="communityStore.loading && !communityStore.communities.length" class="py-8 text-center text-sm text-muted-fg">
            Loading communities…
          </div>
          <p v-else-if="!filtered.length" class="py-8 text-center text-sm text-muted-fg">
            {{ search ? 'No communities match your search.' : 'No communities yet.' }}
          </p>
          <button
            v-for="community in filtered"
            :key="community.id"
            type="button"
            class="group w-full flex items-center gap-3 p-3 rounded-lg border border-transparent hover:border-border hover:bg-muted/50 transition-all duration-150 text-left"
            @click="chooseCommunity(community)"
          >
            <div class="w-9 h-9 rounded-lg bg-muted text-muted-fg flex items-center justify-center shrink-0 group-hover:bg-accent/15 group-hover:text-accent transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-[18px] h-[18px]">
                <rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/>
                <path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M8 10h.01"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-body font-medium text-sm text-fg truncate">{{ community.name }}</p>
              <p class="text-xs text-muted-fg truncate">{{ community.address || '—' }}</p>
            </div>
            <span class="text-[11px] text-muted-fg shrink-0">{{ community.unitsCount }} units</span>
          </button>
        </div>
        </template>
      </div>
    </div>
  </div>
</template>
