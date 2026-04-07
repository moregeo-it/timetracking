<template>
    <div class="time-tracking">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Zeiterfassung') }}</h1>
        </div>
        
        <!-- Compliance Warnings Banner -->
        <div v-if="complianceAlerts.length > 0" class="compliance-alerts">
            <div v-for="(alert, index) in complianceAlerts" :key="index" 
                 :class="['compliance-alert', alert.severity]">
                <span class="alert-icon">{{ alert.severity === 'high' ? '🚨' : '⚠️' }}</span>
                <span class="alert-message">{{ alert.message }}</span>
            </div>
        </div>
        
        <div class="timer-section" v-if="runningTimer">
            <div class="timer-card running">
                <h3>{{ t('timetracking', 'Zeiterfassung') }}</h3>
                <div :class="['timer-display', { overtime: isOvertime }]">{{ timerDisplay }}</div>
                <p v-if="isOvertime" class="overtime-warning">⚠️ {{ t('timetracking', 'Bitte machen Sie eine Pause!') }}</p>
                <p class="timer-started-at">{{ t('timetracking', 'Gestartet um') }}: {{ formatTimerStartTime() }}</p>
                <div class="timer-actions">
                    <NcButton type="button" @click="openStopDialog">{{ t('timetracking', 'Speichern') }}</NcButton>
                    <NcButton type="button" @click="confirmCancelTimer">{{ t('timetracking', 'Löschen') }}</NcButton>
                </div>
            </div>
        </div>
        
        <div class="timer-section" v-else>
            <div class="timer-card">
                <h3>{{ t('timetracking', 'Zeiterfassung') }}</h3>
                <p class="no-timer-display">{{ t('timetracking', 'Kein aktiver Timer') }}</p>
                <div class="timer-start-options">
                    <div class="timer-start-row">
                        <NcButton type="button" @click="startTimer()" style="flex: 1">{{ t('timetracking', 'Starten') }}</NcButton>
                    </div>
                    <div class="timer-start-row custom-start">
                        <label>{{ t('timetracking', 'Startzeit') }}:</label>
                        <input v-model="customStartTime" type="time" class="custom-time-input">
                        <NcButton type="button" @click="startTimerAtCustomTime">{{ t('timetracking', 'Timer ab Uhrzeit starten') }}</NcButton>
                    </div>
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
                    <label>{{ t('timetracking', 'Beschreibung') }}{{ isDescriptionRequired(manualForm.projectId) ? ' *' : '' }}</label>
                    <input v-model="manualForm.description" type="text" :required="isDescriptionRequired(manualForm.projectId)">
                </div>
                <p v-if="manualEntryOvertime" class="overtime-warning">
                    ⚠️ {{ t('timetracking', 'Arbeitszeit über 6 Stunden - Bitte Pausenzeiten einhalten!') }}
                </p>
                <div class="form-group">
                    <label>
                        <input :checked="manualForm.billable" type="checkbox" style="margin: 0 0.5rem 0 0" @change="onManualBillableChange($event)">
                        {{ t('timetracking', 'Abrechenbar') }}
                    </label>
                </div>
                <NcButton type="submit">{{ t('timetracking', 'Eintrag Hinzufügen') }}</NcButton>
            </form>
        </div>
        
        <TimeEntryList
            :entries="entries"
            :projects="projects"
            :customers="customers"
            :can-edit-all="employmentType === 'director'"
            :loading="loading"
            :save-fn="saveEntry"
            :delete-fn="deleteEntry"
            @filter-change="onFilterChange"
        />
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import StopTimerDialog from '../components/StopTimerDialog.vue'
import TimeEntryList from '../components/TimeEntryList.vue'
import timerMixin from '../mixins/timerMixin.js'

export default {
    name: 'TimeTracking',
    components: {
        NcButton,
        StopTimerDialog,
        TimeEntryList,
    },
    mixins: [timerMixin],
    data() {
        const today = new Date().toISOString().split('T')[0]
        const weekAgo = new Date()
        weekAgo.setDate(weekAgo.getDate() - 7)
        
        return {
            projects: [],
            customers: [],
            entries: [],
            complianceAlerts: [],
            complianceCheckInterval: null,
            employmentType: null,
            filterStartDate: weekAgo.toISOString().split('T')[0],
            filterEndDate: today,
            customStartTime: '',
            manualForm: {
                projectId: '',
                date: today,
                startTime: '09:00',
                endTime: '',
                description: '',
                billable: true,
            },
            loading: false,
        }
    },
    computed: {
        activeProjects() {
            return this.sortedProjects.filter(p => p.active)
        },
        sortedProjects() {
            return [...this.projects]
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
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
        this.loadEmployeeSettings()
        this.checkRunningTimer()
        this.checkDailyCompliance()
        // Check compliance every 5 minutes while the page is open
        this.complianceCheckInterval = setInterval(() => {
            this.checkDailyCompliance()
        }, 5 * 60 * 1000)
    },
    beforeUnmount() {
        if (this.complianceCheckInterval) {
            clearInterval(this.complianceCheckInterval)
        }
    },
    methods: {
        async checkDailyCompliance() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/time-entries/compliance'))
                const result = response.data
                
                // Combine violations and warnings into a single alerts array
                const alerts = []
                
                if (result.violations && result.violations.length > 0) {
                    for (const violation of result.violations) {
                        alerts.push({
                            severity: 'high',
                            type: violation.type,
                            message: violation.message,
                        })
                    }
                }
                
                if (result.warnings && result.warnings.length > 0) {
                    for (const warning of result.warnings) {
                        alerts.push({
                            severity: warning.severity || 'medium',
                            type: warning.type,
                            message: warning.message,
                        })
                    }
                }
                
                this.complianceAlerts = alerts
            } catch (error) {
                console.error('Error checking daily compliance:', error)
            }
        },
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
        async loadEmployeeSettings() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/employee-settings'))
                if (response.data && response.data.employmentType) {
                    this.employmentType = response.data.employmentType
                }
            } catch (error) {
                // No settings found, that's okay
                console.error('Could not load employee settings:', error)
            }
        },
        async loadEntries() {
            this.loading = true
            try {
                // Send dates with timezone info - convert local date boundaries to ISO 8601
                const startOfDay = new Date(this.filterStartDate + 'T00:00:00')
                const endOfDay = new Date(this.filterEndDate + 'T23:59:59')
                const url = `/apps/timetracking/api/time-entries?startDate=${startOfDay.toISOString()}&endDate=${endOfDay.toISOString()}`
                const response = await axios.get(generateUrl(url))
                this.entries = response.data
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Laden der Einträge'))
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        onFilterChange({ startDate, endDate }) {
            this.filterStartDate = startDate
            this.filterEndDate = endDate
            this.loadEntries()
        },
        async saveEntry({ id, form }) {
            try {
                const [year, month, day] = form.date.split('-').map(Number)
                const [startHours, startMinutes] = form.startTime.split(':').map(Number)
                const [endHours, endMinutes] = form.endTime.split(':').map(Number)
                const startDateTime = new Date(year, month - 1, day, startHours, startMinutes, 0)
                const endDateTime = new Date(year, month - 1, day, endHours, endMinutes, 0)

                await axios.put(generateUrl(`/apps/timetracking/api/time-entries/${id}`), {
                    projectId: form.projectId,
                    startTime: startDateTime.toISOString(),
                    endTime: endDateTime.toISOString(),
                    description: form.description,
                    billable: form.billable,
                })
                showSuccess(this.t('timetracking', 'Eintrag aktualisiert'))
                this.loadEntries()
                this.checkDailyCompliance()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(this.t('timetracking', 'Zeiteintrag überschneidet sich mit einem bestehenden Eintrag'))
                } else if (error.response?.data?.code === 'DESCRIPTION_REQUIRED') {
                    showError(this.t('timetracking', 'Dieses Projekt erfordert eine Beschreibung'))
                } else {
                    showError(this.t('timetracking', 'Fehler beim Speichern'))
                }
                console.error(error)
                throw error
            }
        },
        async deleteEntry(id) {
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/time-entries/${id}`))
                showSuccess(this.t('timetracking', 'Eintrag gelöscht'))
                this.loadEntries()
                this.checkDailyCompliance()
            } catch (error) {
                showError(this.t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
                throw error
            }
        },
        t,
        async checkRunningTimer() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/time-entries'))
                const running = response.data.find(e => !e.endTime)
                if (running) {
                    this.runningTimer = running
                    this.startTimerDisplay()
                }
            } catch (error) {
                console.error(error)
            }
        },
        startTimerAtCustomTime() {
            if (!this.customStartTime) {
                showError(this.t('timetracking', 'Bitte eine Startzeit eingeben'))
                return
            }
            // Build ISO 8601 datetime from today's date + the chosen time
            const today = new Date()
            const [hours, minutes] = this.customStartTime.split(':').map(Number)
            const startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), hours, minutes, 0)
            // Don't allow future times
            if (startDate > new Date()) {
                showError(this.t('timetracking', 'Startzeit darf nicht in der Zukunft liegen'))
                return
            }
            this.startTimer(startDate.toISOString())
        },
        confirmCancelTimer() {
            if (confirm(this.t('timetracking', 'Timer wirklich abbrechen? Die erfasste Zeit wird nicht gespeichert.'))) {
                this.cancelTimer()
            }
        },
        formatTimerStartTime() {
            if (!this.runningTimer?.startTime) return '-'
            return new Date(this.runningTimer.startTime).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        },
        onTimerStarted() {
            this.customStartTime = ''
            this.checkDailyCompliance()
        },
        onTimerStopped() {
            this.loadEntries()
            this.checkDailyCompliance()
        },
        onTimerCancelled() {
            this.checkDailyCompliance()
        },
        async addManualEntry() {
            try {
                // Client-side check for required description
                if (this.isDescriptionRequired(this.manualForm.projectId) && !this.manualForm.description.trim()) {
                    showError(this.t('timetracking', 'Dieses Projekt erfordert eine Beschreibung'))
                    return
                }

                // Parse date and time components explicitly to ensure local time interpretation
                const [year, month, day] = this.manualForm.date.split('-').map(Number)
                const [startHours, startMinutes] = this.manualForm.startTime.split(':').map(Number)
                const [endHours, endMinutes] = this.manualForm.endTime.split(':').map(Number)
                
                // Create Date objects using local time components (month is 0-indexed)
                const startDateTime = new Date(year, month - 1, day, startHours, startMinutes, 0)
                const endDateTime = new Date(year, month - 1, day, endHours, endMinutes, 0)
                
                await axios.post(generateUrl('/apps/timetracking/api/time-entries'), {
                    projectId: this.manualForm.projectId,
                    startTime: startDateTime.toISOString(), // Converts local time to UTC
                    endTime: endDateTime.toISOString(), // Converts local time to UTC
                    description: this.manualForm.description,
                    billable: this.manualForm.billable,
                })
                
                showSuccess(this.t('timetracking', 'Eintrag hinzugefügt'))
                this.manualForm.description = ''
                this.loadEntries()
                this.checkDailyCompliance()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(this.t('timetracking', 'Zeiteintrag überschneidet sich mit einem bestehenden Eintrag'))
                } else if (error.response?.data?.code === 'DESCRIPTION_REQUIRED') {
                    showError(this.t('timetracking', 'Dieses Projekt erfordert eine Beschreibung'))
                } else {
                    showError(this.t('timetracking', 'Fehler beim Hinzufügen'))
                }
                console.error(error)
            }
        },
        getCustomerName(customerId) {
            const customer = this.customers.find(c => c.id === customerId)
            return customer ? customer.name : 'Unbekannt'
        },
        isDescriptionRequired(projectId) {
            if (!projectId) return false
            const project = this.projects.find(p => p.id === parseInt(projectId))
            return project ? !!project.requireDescription : false
        },
        onManualBillableChange(event) {
            if (!event.target.checked) {
                if (!confirm(this.t('timetracking', 'Sind Sie sicher, dass dieser Eintrag nicht abrechenbar sein soll?'))) {
                    event.target.checked = true
                    return
                }
            }
            this.manualForm.billable = event.target.checked
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

.timer-started-at {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    margin: 0 0 15px 0;
}

.timer-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.timer-start-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.timer-start-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.timer-start-row.custom-start {
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
}

.timer-start-row.custom-start label {
    white-space: nowrap;
    font-size: 13px;
}

.custom-time-input {
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    width: 100px;
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

/* Compliance Alerts */
.compliance-alerts {
    margin-bottom: 20px;
}

.compliance-alert {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 8px;
    font-weight: 500;
}

.compliance-alert.high {
    background-color: #ffebee;
    border: 1px solid #f44336;
    color: #c62828;
}

.compliance-alert.medium {
    background-color: #fff3e0;
    border: 1px solid #ff9800;
    color: #e65100;
}

.compliance-alert.low {
    background-color: #e3f2fd;
    border: 1px solid #2196f3;
    color: #1565c0;
}

.compliance-alert .alert-icon {
    margin-right: 12px;
    font-size: 1.2em;
}

.compliance-alert .alert-message {
    flex: 1;
}
</style>
