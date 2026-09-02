<!--
  CommunityAddressContactPage — WeConnectU "Setup → Address & Contact Details".

  Four fields, like-for-like: Physical Address (textarea), Postal Address
  (textarea), Contact Number, Contact Email address. Community-scoped via the
  community store. The existing community `address` column stores the physical
  address.

  GET /communities/{id}  ·  PUT /communities/{id}
-->
<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useCommunityStore } from '@/stores/community'
import AppInput  from '@/components/common/AppInput.vue'
import AppButton from '@/components/common/AppButton.vue'

const { success, error } = useToast()
const community = useCommunityStore()

const form    = ref({ physical_address: '', postal_address: '', contact_number: '', contact_email: '' })
const loading = ref(true)
const saving  = ref(false)

async function load() {
  const id = community.selectedId
  if (!id) { loading.value = false; return }
  loading.value = true
  try {
    const { data } = await api.get(`/communities/${id}`)
    apply(data.data ?? data)
  } catch {
    error('Could not load the address & contact details. Please refresh.')
  } finally {
    loading.value = false
  }
}

function apply(c) {
  if (!c) return
  form.value = {
    physical_address: c.address ?? '',
    postal_address:   c.postal_address ?? '',
    contact_number:   c.contact_number ?? '',
    contact_email:    c.contact_email ?? '',
  }
}

onMounted(load)
watch(() => community.selectedId, load)

async function save() {
  const id = community.selectedId
  if (!id || saving.value) return
  saving.value = true
  try {
    const { data } = await api.put(`/communities/${id}`, {
      address:        form.value.physical_address?.trim() || null,
      postal_address: form.value.postal_address?.trim() || null,
      contact_number: form.value.contact_number?.trim() || null,
      contact_email:  form.value.contact_email?.trim() || null,
    })
    apply(data.data ?? data)
    success('Address & contact details saved.')
  } catch (e) {
    error(e.response?.data?.message ?? 'Could not save the address & contact details.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- Header / breadcrumb -->
    <div>
      <p class="text-sm text-muted-foreground">
        Setup <span class="mx-1">→</span> <span class="text-foreground font-medium">Address &amp; Contact Details</span>
      </p>
      <h1 class="font-body font-bold text-2xl text-foreground mt-1">Address &amp; Contact Details</h1>
      <p class="text-sm text-muted-foreground">
        Physical / postal address and contact details
        <template v-if="community.selected"> · {{ community.selected.name }}</template>
      </p>
    </div>

    <!-- No community -->
    <div v-if="!community.selectedId" class="rounded-lg border border-border bg-white p-8 text-center text-muted-foreground">
      Select a community from the top bar to configure its address &amp; contact details.
    </div>

    <div v-else class="rounded-lg border border-border bg-white p-6">
      <div v-if="loading" class="py-12 text-center text-muted-foreground text-sm">Loading…</div>

      <div v-else class="divide-y divide-border/60">
        <!-- Physical Address -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-start gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground md:pt-2">Physical Address</label>
          <textarea
            v-model="form.physical_address"
            rows="4"
            class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-y"
          />
        </div>

        <!-- Postal Address -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-start gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground md:pt-2">Postal Address</label>
          <textarea
            v-model="form.postal_address"
            rows="4"
            class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-y"
          />
        </div>

        <!-- Contact Number -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Contact Number</label>
          <AppInput v-model="form.contact_number" />
        </div>

        <!-- Contact Email address -->
        <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] md:items-center gap-2 md:gap-6 py-3">
          <label class="text-sm text-muted-foreground">Contact Email address</label>
          <AppInput v-model="form.contact_email" type="email" />
        </div>

        <!-- Save -->
        <div class="pt-5">
          <AppButton variant="primary" :loading="saving" @click="save">Save</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
