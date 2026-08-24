import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'
import { setUnauthorizedHandler } from './lib/api'
import { clearSession } from './stores/auth'
import { resetNotifications } from './stores/notifications'

import './styles/tokens.css'
import './styles/base.css'
import './styles/auth.css'

// Sesi kedaluwarsa di sisi backend → bersihkan state lalu antar ke login.
setUnauthorizedHandler(() => {
  clearSession()
  resetNotifications()
  if (router.currentRoute.value.meta.auth) {
    router.push({
      name: 'login',
      query: { redirect: router.currentRoute.value.fullPath },
    })
  }
})

createApp(App).use(router).mount('#app')
