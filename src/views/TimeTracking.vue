<template>
    <div class="time-tracking">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Zeiterfassung') }}</h1>
        </div>
        
        <div class="timer-section" v-if="runningTimer">
            <div class="timer-card running">
                <h3>{{ t('timetracking', 'Zeiterfassung') }}</h3>
                <div :class="['timer-display', { overtime: isOvertime }]">{{ timerDisplay }}</div>
                <p v-if="isOvertime" class="overtime-warning">⚠️ {{ t('timetracking', 'Bitte machen Sie eine Pause!') }}</p>
                <p class="timer-project" v-if="runningProjectName">{{ runningProjectName }}</p>
                <p class="timer-description" v-if="runningTimer.description">{{ runningTimer.description }}</p>
                <NcButton type="button" @click="openStopDialog" style="width: 100%">{{ t('timetracking', 'Timer Stoppen') }}</NcButton>
            </div>
        </div>
        
        <div class="timer-section" v-else>
            <div class="timer-card">
                <h3>{{ t('timetracking', 'Zeiterfassung') }}</h3>
                <p class="no-timer-display">{{ t('timetracking', 'Kein aktiver Timer') }}</p>
                <NcButton type="button" @click="startTimer" style="width: 100%">{{ t('timetracking', 'Timer Starten') }}</NcButton>
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
        
        <div class="manual-entry-section">
            <h3>{{ t('timetracking', 'Manueller Zeiteintrag') }}</h3>
            <form @submit.prevent="addManualEntry" class="manual-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Projekt') }} *</label>
                        <select v-model="manualForm.projectId" required>
                            <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                            <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                                {{ project.name }} ({{ getCustomerName(project.customerId) }})
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Datum') }} *</label>
                        <input v-model="manualForm.date" type="date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Start') }} *</label>
                        <input v-model="manualForm.startTime" type="time" required>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Ende') }} *</label>
                        <input v-model="manualForm.endTime" type="time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschreibung') }}</label>
                    <input v-model="manualForm.description" type="text">
                </div>
                <p v-if="manualEntryOvertime" class="overtime-warning">
                    ⚠️ {{ t('timetracking', 'Arbeitszeit über 6 Stunden - Bitte Pausenzeiten einhalten!') }}
                </p>
                <div class="form-group">
                    <label>
                        <input v-model="manualForm.billable" type="checkbox" style="margin: 0 0.5rem 0 0">
                        {{ t('timetracking', 'Abrechenbar') }}
                    </label>
                </div>
                <NcButton type="submit">{{ t('timetracking', 'Eintrag Hinzufügen') }}</NcButton>
            </form>
        </div>
        
        <div class="entries-section">
            <h2>{{ t('timetracking', 'Letzte Einträge') }}</h2>
            <div class="date-filter">
                <label>
                    {{ t('timetracking', 'Von') }}:
                    <input v-model="filterStartDate" type="date" @change="loadEntries" size="12">
                </label>
                <label>
                    {{ t('timetracking', 'Bis') }}:
                    <input v-model="filterEndDate" type="date" @change="loadEntries" size="12">
                </label>
            </div>
            
            <table v-if="entries.length > 0">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Datum') }}</th>
                        <th>{{ t('timetracking', 'Projekt') }}</th>
                        <th>{{ t('timetracking', 'Start') }}</th>
                        <th>{{ t('timetracking', 'Ende') }}</th>
                        <th>{{ t('timetracking', 'Dauer') }}</th>
                        <th>{{ t('timetracking', 'Beschreibung') }}</th>
                        <th>{{ t('timetracking', 'Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in entries" :key="entry.id">
                        <td>{{ formatDate(entry.date) }}</td>
                        <td>{{ getProjectName(entry.projectId) }}</td>
                        <td>{{ formatTime(entry.startTime) }}</td>
                        <td>{{ formatTime(entry.endTime) }}</td>
                        <td>{{ formatDuration(entry.durationMinutes) }}</td>
                        <td>{{ entry.description || '-' }}</td>
                        <td class="actions">
                            <NcButton type="button" @click="deleteEntry(entry.id)" :title="t('timetracking', 'Löschen')">
                                <template #icon>
                                    <Delete :size="20" />
                                </template>
                            </NcButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else>{{ t('timetracking', 'Keine Einträge vorhanden') }}</p>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import StopTimerDialog from '../components/StopTimerDialog.vue'

export default {
    name: 'TimeTracking',
    components: {
        NcButton,
        Delete,
        StopTimerDialog,
    },
    data() {
        const today = new Date().toISOString().split('T')[0]
        const weekAgo = new Date()
        weekAgo.setDate(weekAgo.getDate() - 7)
        
        return {
            runningTimer: null,
            runningProjectName: '',
            timerDisplay: '00:00:00',
            timerSeconds: 0,
            timerInterval: null,
            projects: [],
            customers: [],
            entries: [],
            filterStartDate: weekAgo.toISOString().split('T')[0],
            filterEndDate: today,
            showStopDialog: false,
            manualForm: {
                projectId: '',
                date: today,
                startTime: '09:00',
                endTime: '',
                description: '',
                billable: true,
            },
        }
    },
    computed: {
        activeProjects() {
            return this.projects.filter(p => p.active)
        },
        isOvertime() {
            return this.timerSeconds >= 6 * 3600 // 6 hours in seconds
        },
        manualEntryDuration() {
            if (!this.manualForm.startTime || !this.manualForm.endTime) return 0
            const [startH, startM] = this.manualForm.startTime.split(':').map(Number)
            const [endH, endM] = this.manualForm.endTime.split(':').map(Number)
            const startMinutes = startH * 60 + startM
            const endMinutes = endH * 60 + endM
            return endMinutes - startMinutes
        },
        manualEntryOvertime() {
            return this.manualEntryDuration > 6 * 60 // 6 hours in minutes
        },
    },
    mounted() {
        this.loadProjects()
        this.loadCustomers()
        this.loadEntries()
        this.checkRunningTimer()
    },
    beforeUnmount() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
    },
    methods: {
        async loadProjects() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/projects'))
                this.projects = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadCustomers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/customers'))
                this.customers = response.data
            } catch (error) {
                console.error(error)
            }
        },
        async loadEntries() {
            try {
                const url = `/apps/timetracking/api/time-entries?startDate=${this.filterStartDate}&endDate=${this.filterEndDate}`
                const response = await axios.get(generateUrl(url))
                this.entries = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden der Einträge'))
                console.error(error)
            }
        },
        t,
        async checkRunningTimer() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/time-entries'))
                const running = response.data.find(e => !e.endTime)
                if (running) {
                    this.runningTimer = running
                    this.runningProjectName = this.getProjectName(running.projectId)
                    this.startTimerDisplay()
                }
            } catch (error) {
                console.error(error)
            }
        },
        async startTimer() {
            try {
                const response = await axios.post(
                    generateUrl('/apps/timetracking/api/time-entries/start'),
                    {}
                )
                this.runningTimer = response.data
                this.runningProjectName = ''
                this.startTimerDisplay()
                showSuccess(this.t('timetracking', 'Timer gestartet'))
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Starten des Timers'))
                console.error(error)
            }
        },
        openStopDialog() {
            this.showStopDialog = true
        },
        async stopTimerWithDetails(details) {
            try {
                await axios.post(generateUrl('/apps/timetracking/api/time-entries/stop'), {
                    projectId: details.projectId,
                    description: details.description,
                    billable: details.billable,
                })
                showSuccess(this.t('timetracking', 'Timer gestoppt'))
                this.showStopDialog = false
                this.runningTimer = null
                this.runningProjectName = ''
                if (this.timerInterval) {
                    clearInterval(this.timerInterval)
                    this.timerInterval = null
                }
                this.loadEntries()
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Stoppen des Timers'))
                console.error(error)
            }
        },
        startTimerDisplay() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval)
            }
            
            // Update display immediately
            this.updateTimerDisplay()
            
            // Then update every second
            this.timerInterval = setInterval(() => {
                this.updateTimerDisplay()
            }, 1000)
        },
        updateTimerDisplay() {
            const startTimeStr = this.runningTimer.startTime
            // Parse as local time - add local timezone offset to prevent UTC interpretation
            let start
            if (startTimeStr.includes('Z') || startTimeStr.includes('+')) {
                start = new Date(startTimeStr)
            } else {
                // Parse without timezone indicator - treat as local time
                const [datePart, timePart] = startTimeStr.replace(' ', 'T').split('T')
                const [year, month, day] = datePart.split('-').map(Number)
                const [hours, minutes, seconds] = timePart.split(':').map(Number)
                start = new Date(year, month - 1, day, hours, minutes, seconds || 0)
            }
            const now = new Date()
            const diff = Math.floor((now - start) / 1000)
            
            this.timerSeconds = diff
            
            const hours = Math.floor(diff / 3600)
            const minutes = Math.floor((diff % 3600) / 60)
            const seconds = diff % 60
            
            this.timerDisplay = 
                hours.toString().padStart(2, '0') + ':' +
                minutes.toString().padStart(2, '0') + ':' +
                seconds.toString().padStart(2, '0')
        },
        async addManualEntry() {
            try {
                const startDateTime = `${this.manualForm.date}T${this.manualForm.startTime}:00`
                const endDateTime = `${this.manualForm.date}T${this.manualForm.endTime}:00`
                
                await axios.post(generateUrl('/apps/timetracking/api/time-entries'), {
                    projectId: this.manualForm.projectId,
                    date: this.manualForm.date,
                    startTime: startDateTime,
                    endTime: endDateTime,
                    description: this.manualForm.description,
                    billable: this.manualForm.billable,
                })
                
                showSuccess('Eintrag hinzugefügt')
                this.manualForm.description = ''
                this.loadEntries()
            } catch (error) {
                showError('Fehler beim Hinzufügen')
                console.error(error)
            }
        },
        async deleteEntry(id) {
            if (!confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                return
            }
            
            try {
                await axios.delete(generateUrl('/apps/timetracking/api/time-entries/' + id))
                showSuccess('Eintrag gelöscht')
                this.loadEntries()
            } catch (error) {
                showError('Fehler beim Löschen')
                console.error(error)
            }
        },
        getProjectName(projectId) {
            const project = this.projects.find(p => p.id === projectId)
            return project ? project.name : 'Unbekannt'
        },
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : 'Unbekannt'
        },
        formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('de-DE')
        },
        formatTime(dateTimeStr) {
            if (!dateTimeStr) return '-'
            return new Date(dateTimeStr).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
        },
        formatDuration(minutes) {
            if (!minutes) return '-'
            const hours = Math.floor(minutes / 60)
            const mins = minutes % 60
            return `${hours}:${mins.toString().padStart(2, '0')}h`
        },
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.time-tracking {
    max-width: 1200px;
}

.timer-section {
    margin-bottom: 40px;
}

.timer-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 1rem;
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}

.timer-card h3 {
    margin-top: 0;
}

.timer-card.running {
    border-color: #2e7d32;
    background: var(--color-background-hover);
}

.no-timer-display {
    font-size: 14px;
    font-weight: bold;
    color: #757575;
    margin: 10px 0;
}

.timer-display {
    font-size: 48px;
    font-weight: bold;
    color: #2e7d32;
    text-align: center;
    margin: 20px 0;
}

.timer-display.overtime {
    color: #c62828;
}

.overtime-warning {
    color: #c62828;
    font-weight: bold;
    text-align: center;
    margin: 10px 0;
}

.timer-project {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    margin: 10px 0;
}

.timer-description {
    text-align: center;
    color: var(--color-text-maxcontrast);
    margin-bottom: 20px;
}

.manual-entry-section {
    margin-bottom: 40px;
    padding: 1rem;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.manual-entry-section h3 {
    margin-top: 0;
}

.entries-section h2 {
    margin-bottom: 16px;
}
</style>
