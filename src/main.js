import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import { loadState } from '@nextcloud/initial-state'
import { register } from '@nextcloud/l10n'
import App from './App.vue'
import Dashboard from './views/Dashboard.vue'
import Customers from './views/Customers.vue'
import Projects from './views/Projects.vue'
import TimeTracking from './views/TimeTracking.vue'
import Reports from './views/Reports.vue'
import Vacations from './views/Vacations.vue'
import PublicHolidays from './views/PublicHolidays.vue'
import EmployeeSettings from './views/EmployeeSettings.vue'

// Load translations from initial state and register them
try {
    const translations = loadState('timetracking', 'translations', {})
    if (Object.keys(translations).length > 0) {
        register('timetracking', translations)
    }
} catch (e) {
    console.warn('Could not load translations from initial state:', e)
}

const routes = [
    { path: '/', component: Dashboard },
    { path: '/customers', component: Customers },
    { path: '/projects', component: Projects },
    { path: '/tracking', component: TimeTracking },
    { path: '/reports', component: Reports },
    { path: '/vacations', component: Vacations },
    { path: '/public-holidays', component: PublicHolidays },
    { path: '/settings', component: EmployeeSettings },
]

const router = createRouter({
    history: createWebHashHistory(),
    routes,
})


document.addEventListener('DOMContentLoaded', () => {
  const app = createApp(App)
  app.use(router)
  app.mount('#content')
})
