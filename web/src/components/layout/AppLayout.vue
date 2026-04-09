<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'

const mainEl = ref(null)
const route  = useRoute()

watch(() => route.path, () => {
  if (mainEl.value) mainEl.value.scrollTop = 0
})
</script>

<template>
  <div class="flex h-screen w-full overflow-hidden">
    <AppSidebar />
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <AppTopbar />
      <main ref="mainEl" class="flex-1 overflow-auto p-6">
        <RouterView v-slot="{ Component }">
          <Transition name="page">
            <component :is="Component" :key="$route.path" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>
