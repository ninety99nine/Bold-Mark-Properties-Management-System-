<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/composables/useApi'
import AppButton from '@/components/common/AppButton.vue'
import AppBadge from '@/components/common/AppBadge.vue'
import AppModal from '@/components/common/AppModal.vue'
import AppInput from '@/components/common/AppInput.vue'
import AppSelect from '@/components/common/AppSelect.vue'
import { useCountryStore } from '@/stores/country'

const router = useRouter()
const countryStore = useCountryStore()

// ── Helpers ───────────────────────────────────────────────────────────
function formatCurrency(amount) {
  if (amount === null || amount === undefined) return '—'
  const num = Math.round(Number(amount))
  if (isNaN(num)) return '—'
  return countryStore.formatCurrency(amount)
}

const CONDITION_TYPES = computed(() => [
  { value: 'overdue_amount',        label: `Overdue Amount (${countryStore.currencySymbol})` },
  { value: 'overdue_invoice_count', label: 'Overdue Invoices' },
  { value: 'days_overdue',          label: 'Days Overdue' },
  { value: 'arrears_rate',          label: 'Arrears Rate (%)' },
])

const SEVERITY_OPTIONS = [
  { value: 'warning',  label: 'Warning' },
  { value: 'critical', label: 'Critical' },
]

// ── Rule Templates ───────────────────────────────────────────────────
const RULE_TEMPLATES = [
  // ── Critical (worst → less severe) ──────────────────────────────
  {
    id: 'severe_default',
    icon: 'shield',
    name: 'Severe Default',
    description: 'Flag the most critical cases — high balances combined with extended non-payment requiring urgent action.',
    severity: 'critical',
    conditions: [
      { type: 'overdue_amount', operator: '>=', value: 100000 },
      { type: 'days_overdue', operator: '>=', value: 90 },
    ],
  },
  {
    id: 'chronic_non_payment',
    icon: 'calendar',
    name: 'Chronic Non-Payment',
    description: 'Identify units with long-standing overdue accounts that have gone unpaid for extended periods.',
    severity: 'critical',
    conditions: [
      { type: 'days_overdue', operator: '>=', value: 120 },
      { type: 'overdue_invoice_count', operator: '>=', value: 3 },
    ],
  },
  {
    id: 'high_value_debtor',
    icon: 'currency',
    name: 'High Value Debtor',
    description: 'Flag units with large outstanding balances that pose significant financial risk.',
    severity: 'critical',
    conditions: [
      { type: 'overdue_amount', operator: '>=', value: 50000 },
    ],
  },
  // ── Warning (worst → less severe) ──────────────────────────────
  {
    id: 'escalating_arrears',
    icon: 'trending',
    name: 'Escalating Arrears',
    description: 'Catch units accumulating multiple unpaid invoices before the situation worsens.',
    severity: 'warning',
    conditions: [
      { type: 'overdue_invoice_count', operator: '>=', value: 5 },
    ],
  },
  {
    id: 'high_arrears_rate',
    icon: 'percent',
    name: 'High Arrears Rate',
    description: 'Identify units where a large proportion of all invoices issued are overdue.',
    severity: 'warning',
    conditions: [
      { type: 'arrears_rate', operator: '>=', value: 50 },
    ],
  },
  {
    id: 'early_warning',
    icon: 'alert',
    name: 'Early Warning',
    description: 'Detect units just entering arrears territory so you can intervene while the balance is still manageable.',
    severity: 'warning',
    conditions: [
      { type: 'overdue_amount', operator: '>=', value: 5000 },
      { type: 'days_overdue', operator: '>=', value: 30 },
    ],
  },
]

function conditionLabel(cond) {
  const labels = {
    overdue_amount:        'Overdue amount',
    overdue_invoice_count: 'Overdue invoices',
    days_overdue:          'Days overdue',
    arrears_rate:          'Arrears rate',
  }
  const label = labels[cond.type] || cond.type
  if (cond.type === 'overdue_amount') return `${label} ≥ ${formatCurrency(cond.value)}`
  if (cond.type === 'arrears_rate')   return `${label} ≥ ${cond.value}%`
  return `${label} ≥ ${cond.value}`
}

// ── State ─────────────────────────────────────────────────────────────
const loading      = ref(true)
const rules        = ref([])
const flaggedUnits = ref([])
const totalFlagged = ref(0)
const error        = ref(null)

// Drag-and-drop reorder state
const dragIndex = ref(null)
const dropIndex = ref(null)

function onDragStart(index) {
  dragIndex.value = index
}

function onDragOver(e, index) {
  e.preventDefault()
  dropIndex.value = index
}

function onDragLeave() {
  dropIndex.value = null
}

async function onDrop(index) {
  if (dragIndex.value === null || dragIndex.value === index) {
    dragIndex.value = null
    dropIndex.value = null
    return
  }

  const reordered = [...rules.value]
  const [moved] = reordered.splice(dragIndex.value, 1)
  reordered.splice(index, 0, moved)
  rules.value = reordered

  dragIndex.value = null
  dropIndex.value = null

  // Persist the new order
  try {
    await api.put('/risk-rules/reorder', {
      rule_ids: reordered.map(r => r.id),
    })
  } catch (e) {
    console.error('Reorder error:', e)
    await fetchEvaluation()
  }
}

function onDragEnd() {
  dragIndex.value = null
  dropIndex.value = null
}

// Rule modal state
const showRuleModal = ref(false)
const modalStep     = ref('templates') // 'templates' | 'form'
const editingRule   = ref(null)
const saving        = ref(false)
const saveError     = ref(null)

const form = ref({
  name: '',
  description: '',
  severity: 'warning',
  conditions: [{ type: 'overdue_amount', operator: '>=', value: '' }],
})

// Delete confirm state
const showDeleteConfirm = ref(false)
const deletingRule      = ref(null)
const deleting          = ref(false)

// ── API ───────────────────────────────────────────────────────────────
async function fetchEvaluation() {
  loading.value = true
  error.value   = null
  try {
    const { data } = await api.get('/risk-rules/evaluate')
    rules.value        = data.rules ?? []
    flaggedUnits.value = data.flagged_units ?? []
    totalFlagged.value = data.total_flagged ?? 0
  } catch (e) {
    error.value = 'Failed to load risk evaluation'
    console.error('Risk evaluation error:', e)
  } finally {
    loading.value = false
  }
}

async function saveRule() {
  saving.value   = true
  saveError.value = null
  try {
    const payload = {
      name:       form.value.name,
      description: form.value.description || null,
      severity:   form.value.severity,
      conditions: form.value.conditions.map(c => ({
        type:     c.type,
        operator: '>=',
        value:    Number(c.value),
      })),
    }

    if (editingRule.value) {
      await api.put(`/risk-rules/${editingRule.value.id}`, payload)
    } else {
      await api.post('/risk-rules', payload)
    }
    showRuleModal.value = false
    await fetchEvaluation()
  } catch (e) {
    saveError.value = e?.response?.data?.message ?? 'Failed to save rule'
  } finally {
    saving.value = false
  }
}

async function deleteRule() {
  deleting.value = true
  try {
    await api.delete(`/risk-rules/${deletingRule.value.id}`)
    showDeleteConfirm.value = false
    deletingRule.value      = null
    await fetchEvaluation()
  } catch (e) {
    console.error('Delete error:', e)
  } finally {
    deleting.value = false
  }
}

async function toggleRule(rule) {
  try {
    await api.put(`/risk-rules/${rule.id}`, { is_active: !rule.is_active })
    await fetchEvaluation()
  } catch (e) {
    console.error('Toggle error:', e)
  }
}

// ── Modal helpers ─────────────────────────────────────────────────────
function openCreateModal() {
  editingRule.value = null
  saveError.value   = null
  modalStep.value   = 'templates'
  form.value = {
    name: '',
    description: '',
    severity: 'warning',
    conditions: [{ type: 'overdue_amount', operator: '>=', value: '' }],
  }
  showRuleModal.value = true
}

function selectTemplate(template) {
  form.value = {
    name:        template.name,
    description: template.description,
    severity:    template.severity,
    conditions:  template.conditions.map(c => ({ ...c })),
  }
  modalStep.value = 'form'
}

function selectCustom() {
  form.value = {
    name: '',
    description: '',
    severity: 'warning',
    conditions: [{ type: 'overdue_amount', operator: '>=', value: '' }],
  }
  modalStep.value = 'form'
}

function openEditModal(rule) {
  editingRule.value = rule
  saveError.value   = null
  modalStep.value   = 'form'
  form.value = {
    name:        rule.name,
    description: rule.description ?? '',
    severity:    rule.severity,
    conditions:  rule.conditions.map(c => ({ ...c })),
  }
  showRuleModal.value = true
}

function confirmDelete(rule) {
  deletingRule.value      = rule
  showDeleteConfirm.value = true
}

function addCondition() {
  form.value.conditions.push({ type: 'overdue_amount', operator: '>=', value: '' })
}

function removeCondition(index) {
  if (form.value.conditions.length > 1) {
    form.value.conditions.splice(index, 1)
  }
}

const canSave = () => {
  if (!form.value.name.trim()) return false
  return form.value.conditions.every(c => c.type && c.value !== '' && c.value !== null)
}

onMounted(fetchEvaluation)
</script>

<template>
  <div class="space-y-4">

    <!-- Section Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-body font-bold text-lg text-foreground flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-destructive"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          High Risk Monitoring
        </h2>
        <p class="text-sm text-muted-foreground mt-0.5">Configure rules to automatically flag at-risk accounts for early intervention</p>
        <p v-if="countryStore.isMultiCountry" class="text-xs text-muted-foreground/70 mt-1">Rules apply only to estates in your currently selected region ({{ countryStore.activeCountryInfo?.name }}).</p>
      </div>
      <AppButton variant="primary" size="sm" @click="openCreateModal">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Rule
      </AppButton>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="n in 2" :key="n" class="rounded-lg border bg-card shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="h-4 w-28 bg-muted rounded animate-pulse" />
          <div class="h-5 w-16 bg-muted rounded-full animate-pulse" />
        </div>
        <div class="space-y-2 mb-4">
          <div class="h-3 w-40 bg-muted rounded animate-pulse" />
          <div class="h-3 w-32 bg-muted rounded animate-pulse" />
        </div>
        <div class="border-t border-border pt-3">
          <div class="h-3 w-24 bg-muted rounded animate-pulse" />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="rules.length === 0" class="rounded-lg border border-dashed bg-card p-8 text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h3 class="font-body font-semibold text-sm text-foreground mb-1">No risk rules configured</h3>
      <p class="text-xs text-muted-foreground mb-4 max-w-sm mx-auto">
        Create rules to automatically identify units that meet high-risk conditions such as large overdue balances, many unpaid invoices, or extended non-payment periods.
      </p>
      <AppButton variant="primary" size="sm" @click="openCreateModal">Create Your First Rule</AppButton>
    </div>

    <!-- Rule Cards -->
    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="(rule, ruleIdx) in rules"
          :key="rule.id"
          draggable="true"
          @dragstart="onDragStart(ruleIdx)"
          @dragover="(e) => onDragOver(e, ruleIdx)"
          @dragleave="onDragLeave"
          @drop="onDrop(ruleIdx)"
          @dragend="onDragEnd"
          :class="[
            'rounded-lg border bg-card shadow-sm p-5 transition-all',
            !rule.is_active && 'opacity-50',
            dragIndex === ruleIdx && 'opacity-40 scale-[0.97]',
            dropIndex === ruleIdx && dragIndex !== ruleIdx && 'ring-2 ring-primary/30',
          ]"
        >
          <!-- Header: drag handle + name + severity badge -->
          <div class="flex items-center justify-between mb-1">
            <div class="flex items-center gap-1.5 min-w-0">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-border shrink-0 cursor-grab active:cursor-grabbing"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
              <h4 class="font-body font-semibold text-sm text-foreground truncate">{{ rule.name }}</h4>
            </div>
            <AppBadge
              :variant="rule.severity === 'critical' ? 'danger' : 'warning'"
              bordered
              size="sm"
            >
              {{ rule.severity === 'critical' ? 'Critical' : 'Warning' }}
            </AppBadge>
          </div>

          <!-- Description -->
          <p v-if="rule.description" class="text-xs text-muted-foreground mb-3 line-clamp-2">{{ rule.description }}</p>
          <div v-else class="mb-3" />

          <!-- Conditions -->
          <div class="space-y-1.5 mb-4">
            <div
              v-for="(cond, i) in rule.conditions"
              :key="i"
              class="flex items-center gap-2 text-xs text-muted-foreground"
            >
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="rule.severity === 'critical' ? 'bg-destructive' : 'bg-amber-500'" />
              <span>{{ conditionLabel(cond) }}</span>
            </div>
          </div>

          <!-- Footer: flagged count + actions -->
          <div class="flex items-center justify-between border-t border-border pt-3">
            <span class="text-xs">
              <span class="font-semibold" :class="rule.flagged_count > 0 ? 'text-destructive' : 'text-foreground'">{{ rule.flagged_count }}</span>
              <span class="text-muted-foreground ml-1">{{ rule.flagged_count === 1 ? 'unit' : 'units' }} flagged</span>
            </span>
            <div class="flex items-center gap-0.5">
              <!-- Toggle active -->
              <AppButton variant="ghost" square size="sm" @click="toggleRule(rule)" :title="rule.is_active ? 'Disable rule' : 'Enable rule'">
                <svg v-if="rule.is_active" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </AppButton>
              <!-- Edit -->
              <AppButton variant="ghost" square size="sm" @click="openEditModal(rule)" title="Edit rule">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </AppButton>
              <!-- Delete -->
              <AppButton variant="danger-ghost" square size="sm" @click="confirmDelete(rule)" title="Delete rule">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </AppButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Flagged Units Table -->
      <div v-if="flaggedUnits.length > 0" class="rounded-lg border bg-card shadow-sm">
        <div class="px-6 pt-5 pb-3">
          <h3 class="font-body font-semibold text-base text-foreground">Flagged Units</h3>
          <p class="text-xs text-muted-foreground mt-0.5">{{ totalFlagged }} {{ totalFlagged === 1 ? 'unit matches' : 'units match' }} one or more risk rules</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Unit</th>
                <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Estate</th>
                <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Owner / Tenant</th>
                <th class="text-right font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Overdue</th>
                <th class="text-right font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Invoices</th>
                <th class="text-right font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Days</th>
                <th class="text-left font-medium text-muted-foreground px-4 py-3 text-xs uppercase tracking-wide">Matched Rules</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="unit in flaggedUnits"
                :key="unit.unit_id"
                class="border-b border-border last:border-0 hover:bg-muted/50 cursor-pointer transition-colors"
                @click="router.push(`/estates/${unit.estate_id}`)"
              >
                <td class="px-4 py-3 font-medium text-foreground whitespace-nowrap">{{ unit.unit_number }}</td>
                <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">{{ unit.estate_name }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <div class="text-foreground text-sm">{{ unit.owner_name ?? '—' }}</div>
                  <div v-if="unit.owner_email" class="text-xs text-muted-foreground">{{ unit.owner_email }}</div>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-destructive whitespace-nowrap">{{ formatCurrency(unit.overdue_amount) }}</td>
                <td class="px-4 py-3 text-right text-destructive font-medium">{{ unit.overdue_count }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <AppBadge
                    :variant="unit.days_overdue >= 90 ? 'danger' : unit.days_overdue >= 30 ? 'warning' : 'default'"
                    bordered
                    size="sm"
                  >
                    {{ unit.days_overdue >= 90 ? '90+' : unit.days_overdue >= 30 ? '30+' : '< 30' }} days
                  </AppBadge>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1">
                    <AppBadge
                      v-for="r in unit.matched_rules"
                      :key="r.id"
                      :variant="r.severity === 'critical' ? 'danger' : 'warning'"
                      size="sm"
                    >
                      {{ r.name }}
                    </AppBadge>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- ── Create / Edit Rule Modal ──────────────────────────────────── -->
    <AppModal
      :show="showRuleModal"
      :title="editingRule ? 'Edit Risk Rule' : modalStep === 'templates' ? 'Add Risk Rule' : 'Configure Rule'"
      :size="modalStep === 'templates' && !editingRule ? 'xl' : 'lg'"
      @close="showRuleModal = false"
    >
      <!-- ── Step 1: Template Picker (only for new rules) ──────────── -->
      <div v-if="modalStep === 'templates' && !editingRule">
        <p class="text-sm text-muted-foreground mb-4">Choose a template to get started quickly, or create a custom rule from scratch.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <!-- Template cards -->
          <button
            v-for="tpl in RULE_TEMPLATES"
            :key="tpl.id"
            type="button"
            class="text-left p-4 rounded-lg border border-border bg-white hover:border-primary/40 hover:bg-primary/[0.02] transition-colors cursor-pointer group"
            @click="selectTemplate(tpl)"
          >
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="tpl.severity === 'critical' ? 'bg-destructive/10' : 'bg-amber/10'">
                <!-- Currency icon -->
                <svg v-if="tpl.icon === 'currency'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <!-- Calendar icon -->
                <svg v-else-if="tpl.icon === 'calendar'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <!-- Trending icon -->
                <svg v-else-if="tpl.icon === 'trending'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                <!-- Alert icon -->
                <svg v-else-if="tpl.icon === 'alert'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <!-- Shield icon -->
                <svg v-else-if="tpl.icon === 'shield'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <!-- Percent icon -->
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="tpl.severity === 'critical' ? 'text-destructive' : 'text-amber-dark'"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="font-body font-semibold text-sm text-foreground">{{ tpl.name }}</span>
                  <AppBadge :variant="tpl.severity === 'critical' ? 'danger' : 'warning'" size="sm">
                    {{ tpl.severity }}
                  </AppBadge>
                </div>
                <p class="text-xs text-muted-foreground line-clamp-2">{{ tpl.description }}</p>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <span
                    v-for="(cond, ci) in tpl.conditions"
                    :key="ci"
                    class="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded bg-muted text-muted-foreground"
                  >
                    {{ conditionLabel(cond) }}
                  </span>
                </div>
              </div>
            </div>
          </button>

          <!-- Custom rule card -->
          <button
            type="button"
            class="text-left p-4 rounded-lg border border-dashed border-border bg-white hover:border-primary/40 hover:bg-primary/[0.02] transition-colors cursor-pointer group"
            @click="selectCustom"
          >
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg bg-muted flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              </div>
              <div>
                <span class="font-body font-semibold text-sm text-foreground">Custom Rule</span>
                <p class="text-xs text-muted-foreground mt-0.5">Build a rule from scratch with your own conditions and thresholds.</p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- ── Step 2: Rule Form ─────────────────────────────────────── -->
      <div v-else class="space-y-5">
        <!-- Back to templates link (only for new rules) -->
        <button
          v-if="!editingRule"
          type="button"
          class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
          @click="modalStep = 'templates'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to templates
        </button>

        <AppInput
          v-model="form.name"
          label="Rule Name"
          placeholder="e.g. High Value Debtors"
          required
        />

        <AppInput
          v-model="form.description"
          type="textarea"
          label="Description"
          placeholder="Optional description of what this rule monitors..."
          :rows="2"
        />

        <AppSelect
          v-model="form.severity"
          label="Severity"
          :options="SEVERITY_OPTIONS"
          required
        />

        <!-- Conditions builder -->
        <div>
          <label class="text-sm font-medium text-foreground block mb-1.5">
            Conditions
            <span class="text-muted-foreground font-normal ml-1">(all must be met)</span>
          </label>

          <div class="space-y-3">
            <div
              v-for="(cond, i) in form.conditions"
              :key="i"
              class="flex items-end gap-3 p-3 rounded-md bg-muted/30 border border-border/50"
            >
              <div class="flex-1">
                <label class="text-xs text-muted-foreground mb-1 block">Metric</label>
                <AppSelect
                  v-model="cond.type"
                  :options="CONDITION_TYPES"
                  placeholder="Select metric..."
                />
              </div>
              <div class="w-12 flex items-center justify-center pb-0.5">
                <span class="text-sm font-mono text-muted-foreground">&ge;</span>
              </div>
              <div class="w-36">
                <label class="text-xs text-muted-foreground mb-1 block">Threshold</label>
                <AppInput
                  v-model="cond.value"
                  type="number"
                  :prefix="cond.type === 'overdue_amount' ? countryStore.currencySymbol : undefined"
                  :suffix="cond.type === 'arrears_rate' ? '%' : undefined"
                  placeholder="0"
                />
              </div>
              <AppButton
                variant="danger-ghost"
                square
                size="sm"
                @click="removeCondition(i)"
                :disabled="form.conditions.length <= 1"
                title="Remove condition"
                class="mb-0.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </AppButton>
            </div>
          </div>

          <AppButton variant="ghost" size="sm" @click="addCondition" class="mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Condition
          </AppButton>
        </div>

        <!-- Save error -->
        <p v-if="saveError" class="text-xs text-destructive">{{ saveError }}</p>
      </div>

      <template #footer>
        <template v-if="modalStep === 'templates' && !editingRule">
          <AppButton variant="outline" @click="showRuleModal = false">Cancel</AppButton>
        </template>
        <template v-else>
          <AppButton variant="outline" @click="showRuleModal = false">Cancel</AppButton>
          <AppButton
            @click="saveRule"
            :loading="saving"
            :disabled="!canSave()"
          >
            {{ editingRule ? 'Save Changes' : 'Create Rule' }}
          </AppButton>
        </template>
      </template>
    </AppModal>

    <!-- ── Delete Confirmation Modal ─────────────────────────────────── -->
    <AppModal
      :show="showDeleteConfirm"
      title="Delete Rule"
      size="sm"
      @close="showDeleteConfirm = false"
    >
      <p class="text-sm text-muted-foreground">
        Are you sure you want to delete <strong class="text-foreground">{{ deletingRule?.name }}</strong>?
        This action cannot be undone.
      </p>

      <template #footer>
        <AppButton variant="outline" @click="showDeleteConfirm = false">Cancel</AppButton>
        <AppButton variant="danger" @click="deleteRule" :loading="deleting">Delete</AppButton>
      </template>
    </AppModal>

  </div>
</template>
