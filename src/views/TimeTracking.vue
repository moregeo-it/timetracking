<template>
    <div class="time-tracking">
        <h1>Zeiterfassung</h1>
        
        <div class="timer-section" v-if="runningTimer">
            <div class="timer-card running">
                <h2>Laufender Timer</h2>
                <div class="timer-display">{{ timerDisplay }}</div>
                <p class="timer-project">{{ runningProjectName }}</p>
                <p class="timer-description" v-if="runningTimer.description">{{ runningTimer.description }}</p>
                <button @click="stopTimer" class="button primary">Timer Stoppen</button>
            </div>
        </div>
        
        <div class="timer-section" v-else>
            <div class="timer-card">
                <h2>Timer Starten</h2>
                <form @submit.prevent="startTimer">
                    <div class="form-group">
                        <label>Projekt *</label>
                        <select v-model="timerForm.projectId" required>
                            <option value="">Bitte wählen</option>
                            <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                                {{ project.name }} ({{ getCustomerName(project.customerId) }})
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Beschreibung</label>
                        <input v-model="timerForm.description" type="text" placeholder="Was machen Sie?">
                    </div>
                    <button type="submit" class="button primary">Timer Starten</button>
                </form>
            </div>
        </div>
        
        <div class="manual-entry-section">
            <h2>Manueller Zeiteintrag</h2>
            <form @submit.prevent="addManualEntry" class="manual-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Projekt *</label>
                        <select v-model="manualForm.projectId" required>
                            <option value="">Bitte wählen</option>
                            <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Datum *</label>
                        <input v-model="manualForm.date" type="date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start *</label>
                        <input v-model="manualForm.startTime" type="time" required>
                    </div>
                    <div class="form-group">
                        <label>Ende *</label>
                        <input v-model="manualForm.endTime" type="time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Beschreibung</label>
                    <input v-model="manualForm.description" type="text">
                </div>
                <div class="form-group">
                    <label>
                        <input v-model="manualForm.billable" type="checkbox">
                        Abrechenbar
                    </label>
                </div>
                <button type="submit" class="button primary">Eintrag Hinzufügen</button>
            </form>
        </div>
        
        <div class="entries-section">
            <h2>Letzte Einträge</h2>
            <div class="date-filter">
                <label>
                    Von:
                    <input v-model="filterStartDate" type="date" @change="loadEntries">
                </label>
                <label>
                    Bis:
                    <input v-model="filterEndDate" type="date" @change="loadEntries">
                </label>
            </div>
            
            <table v-if="entries.length > 0">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Projekt</th>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Dauer</th>
                        <th>Beschreibung</th>
                        <th>Aktionen</th>
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
                            <button @click="deleteEntry(entry.id)" class="icon-delete" title="Löschen"></button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else>Keine Einträge vorhanden</p>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'TimeTracking',
    data() {
        const today = new Date().toISOString().split('T')[0]
        const weekAgo = new Date()
        weekAgo.setDate(weekAgo.getDate() - 7)
        
        return {
            runningTimer: null,
            runningProjectName: '',
            timerDisplay: '00:00:00',
            timerInterval: null,
            projects: [],
            customers: [],
            entries: [],
            filterStartDate: weekAgo.toISOString().split('T')[0],
            filterEndDate: today,
            timerForm: {
                projectId: '',
                description: '',
            },
            manualForm: {
                projectId: '',
                date: today,
                startTime: '09:00',
                endTime: '17:00',
                description: '',
                billable: true,
            },
        }
    },
    computed: {
        activeProjects() {
            return this.projects.filter(p => p.active)
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
                showError('Fehler beim Laden der Einträge')
                console.error(error)
            }
        },
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
                    this.timerForm
                )
                this.runningTimer = response.data
                this.runningProjectName = this.getProjectName(this.runningTimer.projectId)
                this.startTimerDisplay()
                showSuccess('Timer gestartet')
                this.timerForm.projectId = ''
                this.timerForm.description = ''
            } catch (error) {
                showError('Fehler beim Starten des Timers')
                console.error(error)
            }
        },
        async stopTimer() {
            try {
                await axios.post(generateUrl('/apps/timetracking/api/time-entries/stop'))
                showSuccess('Timer gestoppt')
                this.runningTimer = null
                this.runningProjectName = ''
                if (this.timerInterval) {
                    clearInterval(this.timerInterval)
                    this.timerInterval = null
                }
                this.loadEntries()
            } catch (error) {
                showError('Fehler beim Stoppen des Timers')
                console.error(error)
            }
        },
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
.timer-section {
    margin-bottom: 40px;
}

.timer-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 30px;
    max-width: 600px;
}

.timer-card.running {
    border-color: var(--color-success);
    background: var(--color-background-hover);
}

.timer-display {
    font-size: 48px;
    font-weight: bold;
    color: var(--color-success);
    text-align: center;
    margin: 20px 0;
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
    padding: 20px;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.manual-form {
    max-width: 800px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

.date-filter {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.date-filter label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.date-filter input {
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

th {
    font-weight: bold;
    background-color: var(--color-background-dark);
}

.actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    opacity: 0.7;
}

.actions button:hover {
    opacity: 1;
}

.button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background-color: var(--color-background-dark);
}

.button.primary {
    background-color: var(--color-primary);
    color: white;
}

.button:hover {
    opacity: 0.8;
}
</style>
