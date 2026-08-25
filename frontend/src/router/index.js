/**
 * Router.
 *
 * Path, nama route, dan aturan akses dipertahankan sama persis dengan aplikasi
 * sebelumnya supaya URL yang sudah dipakai/di-bookmark user tetap valid.
 */
import { createRouter, createWebHistory } from 'vue-router'
import { authState, isAdmin, isAuthenticated, restoreSession } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true, layout: 'auth' },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { guest: true, layout: 'auth' },
  },

  // ---- Area user ----
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { auth: true, title: 'Dashboard' },
  },
  {
    path: '/transactions',
    name: 'transactions',
    component: () => import('@/views/TransactionsView.vue'),
    meta: { auth: true, title: 'Transaksi' },
  },
  {
    path: '/templates',
    name: 'templates',
    component: () => import('@/views/TemplatesView.vue'),
    meta: { auth: true, title: 'Template' },
  },
  {
    path: '/tutorials',
    name: 'tutorials',
    component: () => import('@/views/TutorialsView.vue'),
    meta: { auth: true, title: 'Panduan' },
  },
  {
    path: '/bonuses',
    name: 'bonuses',
    component: () => import('@/views/BonusesView.vue'),
    meta: { auth: true, title: 'Bonus' },
  },
  {
    path: '/plugins',
    name: 'plugins',
    component: () => import('@/views/PluginsView.vue'),
    meta: { auth: true, title: 'Plugin & Lisensi' },
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('@/views/ProfileView.vue'),
    meta: { auth: true, title: 'Profil' },
  },

  // ---- Area admin ----
  {
    path: '/admin',
    redirect: { name: 'admin-dashboard' },
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: () => import('@/views/admin/AdminDashboardView.vue'),
    meta: { auth: true, admin: true, title: 'Dashboard Admin' },
  },
  {
    path: '/admin/transactions',
    name: 'admin-transactions',
    component: () => import('@/views/admin/AdminTransactionsView.vue'),
    meta: { auth: true, admin: true, title: 'Verifikasi Transaksi' },
  },
  {
    path: '/admin/users',
    name: 'admin-users',
    component: () => import('@/views/admin/AdminUsersView.vue'),
    meta: { auth: true, admin: true, title: 'Manajemen User' },
  },
  {
    path: '/admin/templates',
    name: 'admin-templates',
    component: () => import('@/views/admin/AdminTemplatesView.vue'),
    meta: { auth: true, admin: true, title: 'Kelola Template' },
  },
  {
    path: '/admin/plugins',
    name: 'admin-plugins',
    component: () => import('@/views/admin/AdminPluginsView.vue'),
    meta: { auth: true, admin: true, title: 'Kelola Plugin' },
  },
  {
    path: '/admin/tutorials',
    name: 'admin-tutorials',
    component: () => import('@/views/admin/AdminTutorialsView.vue'),
    meta: { auth: true, admin: true, title: 'Kelola Panduan' },
  },
  {
    path: '/admin/bonuses',
    name: 'admin-bonuses',
    component: () => import('@/views/admin/AdminBonusesView.vue'),
    meta: { auth: true, admin: true, title: 'Kelola Bonus' },
  },
  {
    path: '/admin/groups',
    name: 'admin-groups',
    component: () => import('@/views/admin/AdminGroupsView.vue'),
    meta: { auth: true, admin: true, title: 'Kelola Group' },
  },

  // Root diarahkan sesuai peran (perilaku lama).
  {
    path: '/',
    redirect: () => (isAdmin.value ? '/admin/dashboard' : '/dashboard'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: 'Halaman tidak ditemukan' },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, saved) {
    return saved || { top: 0 }
  },
})

router.beforeEach(async (to) => {
  // Sesi hanya di-restore sekali; navigasi berikutnya memakai state di memori.
  if (!authState.ready) {
    await restoreSession()
  }

  if (to.meta.auth && !isAuthenticated.value) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.admin && !isAdmin.value) {
    return { name: 'dashboard' }
  }

  // User yang sudah login tidak perlu melihat halaman login/register lagi.
  if (to.meta.guest && isAuthenticated.value) {
    return isAdmin.value ? { name: 'admin-dashboard' } : { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title
    ? `${to.meta.title} — Apeiron Portal`
    : 'Apeiron Portal'
})
