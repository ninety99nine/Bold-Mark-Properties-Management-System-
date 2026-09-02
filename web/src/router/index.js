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

  // ── Profile selection (authenticated, full-screen — the WeConnectU entry anchor) ──
  {
    path: '/select-profile',
    name: 'select-profile',
    component: () => import('@/pages/auth/SelectProfilePage.vue'),
    meta: { requiresAuth: true },
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
        path: 'communities',
        name: 'communities',
        component: () => import('@/pages/communities/CommunitiesPage.vue'),
      },
      {
        path: 'vacancies',
        name: 'vacancies',
        component: () => import('@/pages/vacancies/VacanciesPage.vue'),
      },
      {
        path: 'communications',
        name: 'communications',
        component: () => import('@/pages/communications/CommunicationsIndex.vue'),
      },
      {
        path: 'communities/:id',
        name: 'community-detail',
        component: () => import('@/pages/communities/CommunityDetailPage.vue'),
      },
      {
        path: 'communities/:communityId/take-on',
        name: 'community-take-on',
        component: () => import('@/pages/communities/CommunityTakeOnPage.vue'),
      },
      {
        path: 'communities/:communityId/units/:unitId',
        name: 'unit-detail',
        component: () => import('@/pages/communities/UnitDetailPage.vue'),
      },
      {
        path: 'communities/:communityId/units/:unitId/occupants/:occupantId',
        name: 'occupant-detail',
        component: () => import('@/pages/communities/OccupantDetailPage.vue'),
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
        path: 'cashbook/manual',
        name: 'cashbook-manual',
        component: () => import('@/pages/cashbook/CashbookManualPage.vue'),
      },
      {
        path: 'cashbook/rules',
        name: 'cashbook-rules',
        component: () => import('@/pages/cashbook/CashbookRulesPage.vue'),
      },
      {
        path: 'cashbook/:entryId',
        name: 'cashbook-entry',
        component: () => import('@/pages/cashbook/CashbookEntryDetailPage.vue'),
      },
      // ── Journals ───────────────────────────────────────────────────
      {
        path: 'journals',
        name: 'journals',
        component: () => import('@/pages/journals/JournalsPage.vue'),
      },
      {
        path: 'journals/:batchId',
        name: 'journal-batch',
        component: () => import('@/pages/journals/JournalBatchDetailPage.vue'),
      },
      // ── Compliance routes ──────────────────────────────────────────
      {
        path: 'compliance',
        name: 'compliance',
        component: () => import('@/pages/compliance/ComplianceDashboardPage.vue'),
      },
      {
        path: 'compliance/checklists/:checklistId',
        name: 'compliance-checklist',
        component: () => import('@/pages/compliance/ComplianceChecklistPage.vue'),
      },
      {
        path: 'compliance/templates',
        name: 'compliance-templates',
        component: () => import('@/pages/compliance/ComplianceTemplatesPage.vue'),
      },
      {
        path: 'compliance/templates/:templateId',
        name: 'compliance-template-detail',
        component: () => import('@/pages/compliance/ComplianceTemplateDetailPage.vue'),
      },

      {
        path: 'tasks',
        name: 'tasks',
        component: () => import('@/pages/tasks/TasksIndex.vue'),
      },
      {
        path: 'customer-management',
        name: 'customer-management',
        component: () => import('@/pages/customer-management/CustomerManagementPage.vue'),
      },
      {
        path: 'customers/invoice',
        name: 'customer-invoice',
        component: () => import('@/pages/customer-management/CustomerInvoicePage.vue'),
      },
      {
        path: 'customers/credit-note',
        name: 'credit-note',
        component: () => import('@/pages/customer-management/CreditNotePage.vue'),
      },
      {
        path: 'customers/manage',
        name: 'manage-customers',
        component: () => import('@/pages/customer-management/ManageCustomersPage.vue'),
      },
      {
        path: 'customers/manage/:ownerId',
        name: 'customer-detail',
        component: () => import('@/pages/customer-management/CustomerDetailPage.vue'),
      },
      {
        path: 'customers/status',
        name: 'customer-status',
        component: () => import('@/pages/customer-management/CustomerStatusPage.vue'),
      },
      {
        path: 'customers/status/batches',
        name: 'status-batches',
        component: () => import('@/pages/customer-management/StatusBatchesPage.vue'),
      },
      {
        path: 'customers/status/automatic',
        name: 'automatic-status-changes',
        component: () => import('@/pages/customer-management/AutomaticStatusChangesPage.vue'),
      },
      {
        path: 'customers/statements',
        name: 'customer-statements',
        component: () => import('@/pages/customer-management/CustomerStatementsPage.vue'),
      },
      {
        path: 'customers/ledger',
        name: 'detailed-customer-ledger',
        component: () => import('@/pages/customer-management/DetailedCustomerLedgerPage.vue'),
      },
      {
        path: 'suppliers/ledger',
        name: 'detailed-supplier-ledger',
        component: () => import('@/pages/supplier-management/DetailedSupplierLedgerPage.vue'),
      },
      {
        path: 'suppliers/invoices',
        name: 'supplier-invoices',
        component: () => import('@/pages/supplier-management/SupplierInvoicesPage.vue'),
      },
      {
        path: 'suppliers/age-analysis',
        name: 'supplier-age-analysis',
        component: () => import('@/pages/supplier-management/SupplierAgeAnalysisPage.vue'),
      },
      {
        path: 'suppliers/manage',
        name: 'manage-suppliers',
        component: () => import('@/pages/suppliers/ManageSuppliersPage.vue'),
      },
      {
        path: 'suppliers/manage/:supplierId',
        name: 'supplier-detail',
        component: () => import('@/pages/suppliers/SupplierDetailPage.vue'),
      },
      {
        path: 'age-analysis',
        name: 'age-analysis',
        component: () => import('@/pages/age-analysis/AgeAnalysisPage.vue'),
      },
      {
        path: 'age-analysis/notices',
        name: 'legal-notices',
        component: () => import('@/pages/age-analysis/LegalNoticesPage.vue'),
      },
      {
        path: 'age-analysis/customer-notices',
        name: 'customer-notices',
        component: () => import('@/pages/age-analysis/CustomerNoticesPage.vue'),
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
        redirect: '/settings/profile',
      },
      {
        path: 'settings/general',
        name: 'community-general-settings',
        component: () => import('@/pages/settings/CommunityGeneralSettingsPage.vue'),
      },
      {
        path: 'settings/address-contact',
        name: 'community-address-contact',
        component: () => import('@/pages/settings/CommunityAddressContactPage.vue'),
      },
      {
        path: 'settings/charges',
        name: 'community-charges',
        component: () => import('@/pages/settings/CommunityChargesSettingsPage.vue'),
      },
      {
        path: 'settings/default-billing-setup',
        name: 'default-billing-setup',
        component: () => import('@/pages/settings/DefaultBillingSetupPage.vue'),
      },
      {
        path: 'settings/users',
        name: 'community-users',
        component: () => import('@/pages/settings/CommunityUsersPage.vue'),
      },
      {
        path: 'settings/company',
        name: 'company-details',
        component: () => import('@/pages/settings/CompanyDetailsPage.vue'),
      },
      {
        path: 'settings/finance/financial',
        name: 'financial-setup',
        component: () => import('@/pages/settings/FinancialSettingsPage.vue'),
      },
      {
        path: 'settings/finance/budget',
        name: 'budget-setup',
        component: () => import('@/pages/settings/BudgetSetupPage.vue'),
      },
      {
        path: 'settings/finance/reserve-fund-budget',
        name: 'reserve-fund-budget',
        component: () => import('@/pages/settings/ReserveFundBudgetPage.vue'),
      },
      // ── GL Reports ─────────────────────────────────────────────────
      {
        path: 'reports/trial-balance',
        name: 'report-trial-balance',
        component: () => import('@/pages/reports/TrialBalancePage.vue'),
      },
      {
        path: 'reports/general-ledger',
        name: 'report-general-ledger',
        component: () => import('@/pages/reports/GeneralLedgerReportPage.vue'),
      },
      {
        path: 'reports/income-statement',
        name: 'report-income-statement',
        component: () => import('@/pages/reports/IncomeStatementPage.vue'),
      },
      {
        path: 'reports/reserve-fund-income-statement',
        name: 'report-reserve-fund-income-statement',
        component: () => import('@/pages/reports/IncomeStatementPage.vue'),
        props: { fund: 'reserve' },
      },
      {
        path: 'reports/actual-vs-budget',
        name: 'report-actual-vs-budget',
        component: () => import('@/pages/reports/ActualVsBudgetPage.vue'),
        props: { fund: 'main' },
      },
      {
        path: 'reports/reserve-fund-actual-vs-budget',
        name: 'report-reserve-fund-actual-vs-budget',
        component: () => import('@/pages/reports/ActualVsBudgetPage.vue'),
        props: { fund: 'reserve' },
      },
      {
        path: 'reports/vat-201',
        name: 'report-vat-201',
        component: () => import('@/pages/reports/Vat201Page.vue'),
      },
      {
        path: 'reports/cash-movement',
        name: 'report-cash-movement',
        component: () => import('@/pages/reports/CashMovementPage.vue'),
      },
      {
        path: 'settings/:tab',
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

  // After a page reload the auth token is restored from storage but the user
  // profile is not — hydrate it so the topbar (name/role) and permission checks
  // populate. Fire-and-forget: navigation is not blocked; the UI fills in
  // reactively once it resolves.
  if (auth.isAuthenticated && !auth.user) {
    auth.fetchUser().catch(() => {})
  }
})

export default router
