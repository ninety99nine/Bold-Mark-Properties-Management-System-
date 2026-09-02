<script setup>
import AppButton from '@/components/common/AppButton.vue'

// Tasks has no backend module yet — this is the UI scaffold. The list wires up
// automatically once a Tasks API lands; for now it shows the empty state.
</script>

<template>
  <div class="space-y-6 pb-8">

    <!-- ── Page heading ──────────────────────────────────────────────── -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h1 class="font-body font-bold text-2xl text-foreground">Tasks</h1>
        <p class="text-sm text-muted-foreground">Track and manage tasks across your portfolio</p>
      </div>
      <AppButton variant="primary" size="sm" disabled title="Coming soon">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        Add Task
      </AppButton>
    </div>

    <!-- ── Tasks list (empty scaffold) ───────────────────────────────── -->
    <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="px-6 py-4 border-b border-border">
        <h3 class="tracking-tight font-body font-semibold text-lg">All Tasks</h3>
      </div>

      <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-full bg-muted flex items-center justify-center mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
            <path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>
          </svg>
        </div>
        <p class="text-sm font-medium text-muted-foreground">No tasks yet</p>
        <p class="text-xs text-muted-foreground/70 mt-1">Task management is coming soon.</p>
      </div>
    </div>

  </div>
</template>
