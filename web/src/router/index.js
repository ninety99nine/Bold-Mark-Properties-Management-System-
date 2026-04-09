import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  // ── Auth (public) ─────────────────────────────────────────────────
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/auth/LoginPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/pages/auth/ForgotPasswordPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/pages/auth/ResetPasswordPage.vue'),
    meta: { guest: true },
  },

  // ── App shell (authenticated) ─────────────────────────────────────
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      // Root redirect
      { path: '', redirect: '/dashboard' },

      // ── Phase 1 routes ────────────────────────────────────────────
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/dashboard/DashboardPage.vue'),
      },
      {
        path: 'estates',
        name: 'estates',
        component: () => import('@/pages/estates/EstatesPage.vue'),
      },
      {
        path: 'estates/:id',
        name: 'estate-detail',
        component: () => import('@/pages/estates/EstateDetailPage.vue'),
      },
      {
        path: 'estates/:estateId/units/:unitId',
        name: 'unit-detail',
        component: () => import('@/pages/estates/UnitDetailPage.vue'),
      },
      {
        path: 'estates/:estateId/units/:unitId/tenants/:tenantId',
        name: 'tenant-detail',
        component: () => import('@/pages/estates/TenantDetailPage.vue'),
      },
      {
        path: 'billing',
        name: 'billing',
        component: () => import('@/pages/billing/BillingPage.vue'),
      },
      {
        path: 'billing/invoices/:invoiceId',
        name: 'invoice-detail',
        component: () => import('@/pages/billing/InvoiceDetailPage.vue'),
      },
      {
        path: 'cashbook',
        name: 'cashbook',
        component: () => import('@/pages/cashbook/CashbookPage.vue'),
      },
      {
        path: 'cashbook/:entryId',
        name: 'cashbook-entry',
        component: () => import('@/pages/cashbook/CashbookEntryDetailPage.vue'),
      },
      {
        path: 'age-analysis',
        name: 'age-analysis',
        component: () => import('@/pages/age-analysis/AgeAnalysisPage.vue'),
      },
      {
        path: 'owners/:ownerId',
        name: 'owner-detail',
        component: () => import('@/pages/owners/OwnerDetailPage.vue'),
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/pages/users/UsersPage.vue'),
      },
      {
        path: 'users/:userId',
        name: 'user-detail',
        component: () => import('@/pages/users/UserDetailPage.vue'),
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('@/pages/settings/SettingsPage.vue'),
      },
    ],
  },

  // ── 404 ──────────────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/pages/NotFoundPage.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
