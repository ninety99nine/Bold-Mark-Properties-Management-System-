<!--
  CommunitySwitcherDrawer — right-side slide-over that mirrors WeConnectU's "house"
  icon behaviour: search/select a community to switch context, or jump back to the
  Global Dashboard. Selecting a community follows the same path as SelectProfilePage
  (communityStore.select + /communities/{id}?login=1).
-->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCommunityStore } from '@/stores/community'

const props = defineProps({
  show: Boolean,
})
const emit = defineEmits(['close'])

const router = useRouter()
const communityStore = useCommunityStore()

const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  const list = communityStore.communities || []
  if (!q) return list
  return list.filter(c =>
    c.name?.toLowerCase().includes(q) ||
    c.code?.toLowerCase().includes(q)
  )
})

function badge(c) {
  if (c?.code) return c.code.toUpperCase()
  const name = c?.name?.trim()
  if (!name) return '—'
  const words = name.split(/\s+/).filter(Boolean)
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return (words[0][0] + words[words.length - 1][0]).toUpperCase()
}

function selectCommunity(c) {
  communityStore.select(c.id, c)
  emit('close')
  router.push(`/communities/${c.id}?login=1`)
}

function backToGlobal() {
  communityStore.clearSelection()
  emit('close')
  router.push('/dashboard')
}

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

// Fetch the community list the first time the drawer opens and reset the search.
watch(() => props.show, (open) => {
  if (open) {
    search.value = ''
    communityStore.fetch()
  }
})

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 bg-navy/40"
        @click="$emit('close')"
      />
    </Transition>

    <!-- Panel -->
    <Transition
      enter-active-class="transition-transform duration-300 ease-out"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200 ease-in"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <aside
        v-if="show"
        class="fixed top-0 right-0 bottom-0 z-50 w-full max-w-[420px] bg-card border-l border-border shadow-2xl flex flex-col"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
          <h2 class="font-body text-xl font-bold text-foreground">Communities</h2>
          <button
            type="button"
            @click="$emit('close')"
            class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
            aria-label="Close"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
          </button>
        </div>

        <!-- Search -->
        <div class="px-6 py-4 shrink-0">
          <div class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <input
              v-model="search"
              type="text"
              placeholder="Search..."
              class="w-full h-11 pl-10 pr-3 rounded-lg border border-border bg-card text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
            />
          </div>
        </div>

        <!-- Back to Global Dashboard -->
        <button
          type="button"
          @click="backToGlobal"
          class="flex items-center gap-3 px-6 py-3 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors border-y border-border shrink-0"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
          </svg>
          Back to Global Dashboard
        </button>

        <!-- Community list -->
        <div class="flex-1 overflow-y-auto">
          <div v-if="communityStore.loading && !communityStore.communities.length" class="py-10 text-center text-sm text-muted-foreground">
            Loading communities…
          </div>

          <div v-else-if="!filtered.length" class="py-10 text-center text-sm text-muted-foreground">
            {{ search ? 'No communities match your search.' : 'No communities yet.' }}
          </div>

          <button
            v-for="c in filtered"
            :key="c.id"
            type="button"
            @click="selectCommunity(c)"
            class="w-full flex items-center gap-4 px-6 py-4 text-left border-b border-border transition-colors"
            :class="String(c.id) === String(communityStore.selectedId) ? 'bg-accent/5' : 'hover:bg-muted'"
          >
            <span class="inline-flex items-center justify-center min-w-16 rounded-md bg-muted px-2.5 py-1.5 text-xs font-bold tracking-wide text-muted-foreground shrink-0">
              {{ badge(c) }}
            </span>
            <span class="font-body font-semibold text-foreground uppercase tracking-tight truncate">{{ c.name }}</span>
          </button>
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>
