<template>
    <div class="dashboard">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Dashboard') }}</h1>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>{{ t('timetracking', 'Heute') }}</h3>
                <div class="stat-value">{{ todayHours }} {{ t('timetracking', 'Stunden') }}</div>
            </div>
            
            <div class="dashboard-card">
                <h3>{{ t('timetracking', 'Diese Woche') }}</h3>
                <div class="stat-value">{{ weekHours }} {{ t('timetracking', 'Stunden') }}</div>
            </div>
            
            <div class="dashboard-card">
                <h3>{{ t('timetracking', 'Dieser Monat') }}</h3>
                <div class="stat-value">{{ monthHours }} {{ t('timetracking', 'Stunden') }}</div>
            </div>
            
            <div class="dashboard-card">
                <h3>{{ t('timetracking', 'Zeiterfassung') }}</h3>
                <div v-if="runningTimer" class="timer-running">
                    <div :class="['timer-display', { overtime: isOvertime }]">{{ timerDisplay }}</div>
                    <p v-if="isOvertime" class="overtime-warning">⚠️ {{ t('timetracking', 'Bitte machen Sie eine Pause!') }}</p>
                    <NcButton type="button" @click="openStopDialog" style="width: 100%">{{ t('timetracking', 'Timer Stoppen') }}</NcButton>
                </div>
                <div v-else>
                    <p class="no-timer-display">{{ t('timetracking', 'Kein aktiver Timer') }}</p>
                    <NcButton type="button" @click="startTimer" style="width: 100%">{{ t('timetracking', 'Timer Starten') }}</NcButton>
                </div>
            </div>
        </div>
        
        <StopTimerDialog
            :show="showStopDialog"
            :timer-display="timerDisplay"
            :projects="projects"
            :customers="customers"
            :initial-project-id="runningTimer?.projectId"
            :initial-description="runningTimer?.description"
            @cancel="showStopDialog = false"
            @confirm="stopTimerWithDetails"
        />
        
        <div class="recent-entries">
            <h2>{{ t('timetracking', 'Letzte Zeiteinträge') }}</h2>
            <table v-if="recentEntries.length > 0">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Datum') }}</th>
                        <th>{{ t('timetracking', 'Projekt') }}</th>
                        <th>{{ t('timetracking', 'Dauer (Std.)') }}</th>
                        <th>{{ t('timetracking', 'Beschreibung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in recentEntries" :key="entry.id">
                        <td>{{ formatDate(entry.startTime) }}</td>
                        <td>{{ getProjectName(entry.projectId) }}</td>
                        <td>{{ formatDuration(entry.durationMinutes) }}</td>
                        <td>{{ entry.description || '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else>{{ t('timetracking', 'Keine Zeiteinträge vorhanden') }}</p>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import StopTimerDialog from '../components/StopTimerDialog.vue'
import timerMixin from '../mixins/timerMixin.js'

export default {
    name: 'Dashboard',
    components: {
        NcButton,
        StopTimerDialog,
    },
    mixins: [timerMixin],
    data() {
        return {
            todayHours: 0,
            weekHours: 0,
            monthHours: 0,
            runningProject: null,
            recentEntries: [],
            projects: [],
            customers: [],
        }
    },
    mounted() {
        this.loadData()
        this.loadProjects()
        this.loadCustomers()
    },
    methods: {
        async loadData() {
            try {
                const today = new Date()
                
                // Start of today in local time, converted to ISO 8601
                const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 0, 0, 0)
                const todayEnd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59)
                
                // Get this week's Monday at start of day
                const monday = new Date(today)
                const dayOfWeek = today.getDay()
                const daysToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
                monday.setDate(today.getDate() + daysToMonday)
                monday.setHours(0, 0, 0, 0)
                
                // Get first day of month at start of day
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1, 0, 0, 0)
                
                const todayEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + todayStart.toISOString() + '&endDate=' + todayEnd.toISOString())
                )
                this.todayHours = this.calculateHours(todayEntriesResponse.data)
                
                const weekEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + monday.toISOString())
                )
                this.weekHours = this.calculateHours(weekEntriesResponse.data)
                
                const monthEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + monthStart.toISOString())
                )
                this.monthHours = this.calculateHours(monthEntriesResponse.data)
                
                this.recentEntries = monthEntriesResponse.data.slice(0, 10)
                
                // Check for running timer
                const runningEntry = monthEntriesResponse.data.find(e => !e.endTime)
                if (runningEntry) {
                    this.runningTimer = runningEntry
                    this.startTimerDisplay()
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error)
            }
        },
        async loadProjects() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/projects'))
                this.projects = response.data
                if (this.runningTimer) {
                    this.runningProject = this.projects.find(p => p.id === this.runningTimer.projectId)
                }
            } catch (error) {
                console.error('Error loading projects:', error)
            }
        },
        async loadCustomers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/customers'))
                this.customers = response.data
            } catch (error) {
                console.error('Error loading customers:', error)
            }
        },
        calculateHours(entries) {
            const totalMinutes = entries.reduce((sum, entry) => sum + (entry.durationMinutes || 0), 0)
            return (totalMinutes / 60).toFixed(2)
        },
        formatDate(isoDateTimeStr) {
            // Parse ISO 8601 string and display as local date
            if (!isoDateTimeStr) return '-'
            return new Date(isoDateTimeStr).toLocaleDateString('de-DE')
        },
        formatDuration(minutes) {
            if (!minutes) return '-'
            const hours = Math.floor(minutes / 60)
            const mins = minutes % 60
            return `${hours}:${mins.toString().padStart(2, '0')}`
        },
        getProjectName(projectId) {
            const project = this.projects.find(p => p.id === projectId)
            return project ? project.name : this.t('timetracking', 'Unbekannt')
        },
        t,
        onTimerStarted() {
            this.runningProject = null
        },
        onTimerStopped() {
            this.runningProject = null
            this.loadData()
        },
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.dashboard {
    max-width: 1200px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.dashboard-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 1rem;
}

.dashboard-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: var(--color-primary);
    margin: 10px 0 0 0;
}

.timer-running {
    text-align: center;
}

.dashboard-card > div:not(.stat-value) {
    text-align: center;
}

.no-timer-display {
    font-size: 14px;
    font-weight: bold;
    color: #757575;
    margin: 10px 0;
}

.timer-display {
    font-size: 28px;
    font-weight: bold;
    color: #2e7d32;
    margin: 10px 0;
}

.timer-display.overtime {
    color: #c62828;
}

.overtime-warning {
    color: #c62828;
    font-weight: bold;
    margin: 5px 0;
}

.recent-entries {
    margin-top: 40px;
}
</style>
