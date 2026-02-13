import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

/**
 * Mixin for timer functionality shared between Dashboard and TimeTracking views.
 * 
 * Components using this mixin must implement:
 * - onTimerStopped(): Called after the timer is successfully stopped to refresh data
 * - projects: Array of projects (for StopTimerDialog)
 * - customers: Array of customers (for StopTimerDialog)
 */
export default {
    data() {
        return {
            runningTimer: null,
            timerInterval: null,
            timerDisplay: 'n/a',
            timerSeconds: 0,
            showStopDialog: false,
        }
    },
    computed: {
        isOvertime() {
            return this.timerSeconds >= 6 * 3600 // 6 hours in seconds
        },
    },
    beforeUnmount() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
    },
    methods: {
        startTimerDisplay() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval)
            }
            
            // Update immediately
            this.updateTimerDisplay()
            
            // Then update every second
            this.timerInterval = setInterval(() => {
                this.updateTimerDisplay()
            }, 1000)
        },
        updateTimerDisplay() {
            const startTimeStr = this.runningTimer.startTime
            // Parse ISO 8601 string - the Date constructor handles timezone correctly
            const start = new Date(startTimeStr)
            const now = new Date()
            // Ensure diff is never negative (handles clock drift between client and server)
            const diff = Math.max(0, Math.floor((now - start) / 1000))
            
            this.timerSeconds = diff
            
            const hours = Math.floor(diff / 3600)
            const minutes = Math.floor((diff % 3600) / 60)
            
            this.timerDisplay = 
                hours.toString().padStart(2, '0') + ':' +
                minutes.toString().padStart(2, '0') + ' '
        },
        /**
         * Start a timer. If startTime is provided (ISO 8601 string), the timer
         * starts at that time instead of "now".
         * @param {string|null} startTime Optional ISO 8601 datetime string
         */
        async startTimer(startTime = null) {
            try {
                const payload = {}
                if (startTime) {
                    payload.startTime = startTime
                }
                const response = await axios.post(
                    generateUrl('/apps/timetracking/api/time-entries/start'),
                    payload
                )
                this.runningTimer = response.data
                this.onTimerStarted()
                this.startTimerDisplay()
                showSuccess(t('timetracking', 'Timer gestartet'))
            } catch (error) {
                if (error.response?.data?.error) {
                    showError(error.response.data.error)
                } else {
                    showError(t('timetracking', 'Fehler beim Starten des Timers'))
                }
                console.error(error)
            }
        },
        openStopDialog() {
            this.showStopDialog = true
        },
        async cancelTimer() {
            try {
                await axios.post(generateUrl('/apps/timetracking/api/time-entries/cancel'))
                showSuccess(t('timetracking', 'Timer abgebrochen'))
                this.runningTimer = null
                if (this.timerInterval) {
                    clearInterval(this.timerInterval)
                    this.timerInterval = null
                }
                this.timerDisplay = 'n/a'
                this.timerSeconds = 0
                this.onTimerCancelled()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Abbrechen des Timers'))
                console.error(error)
            }
        },
        async stopTimerWithDetails(details) {
            try {
                await axios.post(generateUrl('/apps/timetracking/api/time-entries/stop'), {
                    projectId: details.projectId,
                    description: details.description,
                    billable: details.billable,
                })
                showSuccess(t('timetracking', 'Timer gestoppt'))
                this.showStopDialog = false
                this.runningTimer = null
                if (this.timerInterval) {
                    clearInterval(this.timerInterval)
                    this.timerInterval = null
                }
                this.onTimerStopped()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(t('timetracking', 'Zeiteintrag überschneidet sich mit einem bestehenden Eintrag'))
                } else {
                    showError(t('timetracking', 'Fehler beim Stoppen des Timers'))
                }
                console.error(error)
            }
        },
        // Default implementations - can be overridden by component
        onTimerStarted() {
            // Override in component if needed
        },
        onTimerStopped() {
            // Override in component to refresh data
        },
        onTimerCancelled() {
            // Override in component if needed
        },
    },
}
