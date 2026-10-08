import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '@/pages/Dashboard.vue'
import Tokens from '@/pages/Tokens.vue'
import Tasks from '@/pages/Tasks.vue'
import Logs from '@/pages/Logs.vue'
import Settings from '@/pages/Settings.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: Dashboard,
    },
    {
      path: '/tokens',
      name: 'tokens',
      component: Tokens,
    },
    {
      path: '/tasks',
      name: 'tasks',
      component: Tasks,
    },
    {
      path: '/logs',
      name: 'logs',
      component: Logs,
    },
    {
      path: '/settings',
      name: 'settings',
      component: Settings,
    },
  ],
})

export default router