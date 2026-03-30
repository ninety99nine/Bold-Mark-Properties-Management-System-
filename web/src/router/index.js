import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  // Auth
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

  // App shell
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/dashboard/PortfolioDashboard.vue'),
      },
      {
        path: 'communities',
        name: 'communities',
        component: () => import('@/pages/communities/CommunitiesIndex.vue'),
      },
      {
        path: 'communities/:id',
        name: 'community-detail',
        component: () => import('@/pages/communities/CommunityDetail.vue'),
      },
      {
        path: 'communities/:communityId/units',
        name: 'units',
        component: () => import('@/pages/units/UnitsIndex.vue'),
      },
      {
        path: 'owners',
        name: 'owners',
        component: () => import('@/pages/owners/OwnersIndex.vue'),
      },
      {
        path: 'levies',
        name: 'levies',
        component: () => import('@/pages/levies/LeviesIndex.vue'),
      },
      {
        path: 'debt',
        name: 'debt',
        component: () => import('@/pages/debt/DebtDashboard.vue'),
      },
      {
        path: 'finances',
        name: 'finances',
        component: () => import('@/pages/finances/FinancesIndex.vue'),
      },
      {
        path: 'compliance',
        name: 'compliance',
        component: () => import('@/pages/compliance/CompliancePlanner.vue'),
      },
      {
        path: 'tasks',
        name: 'tasks',
        component: () => import('@/pages/tasks/TasksIndex.vue'),
      },
      {
        path: 'communications',
        name: 'communications',
        component: () => import('@/pages/communications/CommunicationsIndex.vue'),
      },
      {
        path: 'documents',
        name: 'documents',
        component: () => import('@/pages/documents/DocumentsIndex.vue'),
      },
    ],
  },

  // 404
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
