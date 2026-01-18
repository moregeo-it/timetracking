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
                <h3>{{ t('timetracking', 'Timer') }}</h3>
                <div v-if="runningTimer" class="timer-running">
                    <div class="timer-display">{{ timerDisplay }}</div>
                    <NcButton type="primary" @click="stopTimer">{{ t('timetracking', 'Timer Stoppen') }}</NcButton>
                    <p>{{ runningProject?.name }}</p>
                </div>
                <div v-else>
                    <p>{{ t('timetracking', 'Kein aktiver Timer') }}</p>
                    <NcButton :to="'/tracking'">{{ t('timetracking', 'Timer Starten') }}</NcButton>
                </div>
            </div>
        </div>
        
        <div class="recent-entries">
            <h2>{{ t('timetracking', 'Letzte Zeiteinträge') }}</h2>
            <table v-if="recentEntries.length > 0">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Datum') }}</th>
                        <th>{{ t('timetracking', 'Projekt') }}</th>
                        <th>{{ t('timetracking', 'Dauer') }}</th>
                        <th>{{ t('timetracking', 'Beschreibung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in recentEntries" :key="entry.id">
                        <td>{{ formatDate(entry.date) }}</td>
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

export default {
    name: 'Dashboard',
    components: {
        NcButton,
    },
    data() {
        return {
            todayHours: 0,
            weekHours: 0,
            monthHours: 0,
            runningTimer: null,
            runningProject: null,
            recentEntries: [],
            projects: [],
            timerInterval: null,
            timerDisplay: '00:00:00',
        }
    },
    mounted() {
        this.loadData()
        this.loadProjects()
    },
    beforeUnmount() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
    },
    methods: {
        async loadData() {
            try {
                const today = new Date()
                const todayStr = today.toISOString().split('T')[0]
                
                // Get this week's Monday
                const monday = new Date(today)
                const dayOfWeek = today.getDay()
                const daysToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
                monday.setDate(today.getDate() + daysToMonday)
                const mondayStr = monday.toISOString().split('T')[0]
                
                // Get first day of month
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
                const monthStartStr = monthStart.toISOString().split('T')[0]
                
                const todayEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + todayStr + '&endDate=' + todayStr)
                )
                this.todayHours = this.calculateHours(todayEntriesResponse.data)
                
                const weekEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + mondayStr)
                )
                this.weekHours = this.calculateHours(weekEntriesResponse.data)
                
                const monthEntriesResponse = await axios.get(
                    generateUrl('/apps/timetracking/api/time-entries?startDate=' + monthStartStr)
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
        calculateHours(entries) {
            const totalMinutes = entries.reduce((sum, entry) => sum + (entry.durationMinutes || 0), 0)
            return (totalMinutes / 60).toFixed(2)
        },
        formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('de-DE')
        },
        formatDuration(minutes) {
            if (!minutes) return '-'
            const hours = Math.floor(minutes / 60)
            const mins = minutes % 60
            return `${hours}:${mins.toString().padStart(2, '0')}h`
        },
        getProjectName(projectId) {
            const project = this.projects.find(p => p.id === projectId)
            return project ? project.name : this.t('timetracking', 'Unbekannt')
        },
        t,
        startTimerDisplay() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval)
            }
            
            this.timerInterval = setInterval(() => {
                const start = new Date(this.runningTimer.startTime)
                const now = new Date()
                const diff = Math.floor((now - start) / 1000)
                
                const hours = Math.floor(diff / 3600)
                const minutes = Math.floor((diff % 3600) / 60)
                const seconds = diff % 60
                
                this.timerDisplay = 
                    hours.toString().padStart(2, '0') + ':' +
                    minutes.toString().padStart(2, '0') + ':' +
                    seconds.toString().padStart(2, '0')
            }, 1000)
        },
        async stopTimer() {
            try {
                await axios.post(generateUrl('/apps/timetracking/api/time-entries/stop'))
                this.runningTimer = null
                this.runningProject = null
                if (this.timerInterval) {
                    clearInterval(this.timerInterval)
                    this.timerInterval = null
                }
                this.loadData()
            } catch (error) {
                console.error('Error stopping timer:', error)
            }
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
    padding: 20px;
}

.dashboard-card h2 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: var(--color-primary);
    margin: 10px 0;
}

.timer-running {
    text-align: center;
}

.timer-display {
    font-size: 28px;
    font-weight: bold;
    color: var(--color-success);
    margin: 10px 0;
}

.recent-entries {
    margin-top: 40px;
}
</style>
