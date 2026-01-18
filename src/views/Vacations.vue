<template>
    <div class="vacation-management">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Urlaubsverwaltung') }}</h1>
        </div>
        
        <!-- Admin: Pending Requests Section -->
        <div v-if="isAdmin && pendingRequests.length > 0" class="pending-requests-card">
            <h3>{{ t('timetracking', 'Ausstehende Genehmigungen') }} ({{ pendingRequests.length }})</h3>
            
            <table class="vacation-table">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Mitarbeiter') }}</th>
                        <th>{{ t('timetracking', 'Zeitraum') }}</th>
                        <th>{{ t('timetracking', 'Tage') }}</th>
                        <th>{{ t('timetracking', 'Notizen') }}</th>
                        <th>{{ t('timetracking', 'Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="request in pendingRequests" :key="request.id" class="status-pending">
                        <td>{{ request.userId }}</td>
                        <td>
                            <strong>{{ formatDate(request.startDate) }}</strong>
                            <span v-if="request.startDate !== request.endDate">
                                - {{ formatDate(request.endDate) }}
                            </span>
                        </td>
                        <td>{{ request.days }}</td>
                        <td class="notes-cell">{{ request.notes || '-' }}</td>
                        <td class="actions-cell">
                            <NcButton type="primary" @click="approveRequest(request.id)">
                                {{ t('timetracking', 'Genehmigen') }}
                            </NcButton>
                            <NcButton type="error" @click="rejectRequest(request.id)">
                                {{ t('timetracking', 'Ablehnen') }}
                            </NcButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Vacation Balance Card -->
        <div class="balance-card">
            <div class="balance-header">
                <h3>{{ t('timetracking', 'Urlaubssaldo') }} {{ currentYear }}</h3>
                <select v-model="currentYear" @change="loadVacations" class="year-selector">
                    <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                </select>
            </div>
            
            <div v-if="balance" class="balance-stats">
                <div class="stat-item">
                    <div class="stat-label">{{ t('timetracking', 'Jahresanspruch') }}</div>
                    <div class="stat-value primary">{{ balance.totalDays }} {{ t('timetracking', 'Tage') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">{{ t('timetracking', 'Genommen') }}</div>
                    <div class="stat-value used">{{ balance.usedDays }} {{ t('timetracking', 'Tage') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">{{ t('timetracking', 'Beantragt') }}</div>
                    <div class="stat-value pending">{{ balance.pendingDays }} {{ t('timetracking', 'Tage') }}</div>
                </div>
                <div class="stat-item highlight">
                    <div class="stat-label">{{ t('timetracking', 'Verfügbar') }}</div>
                    <div class="stat-value success">{{ balance.remainingDays }} {{ t('timetracking', 'Tage') }}</div>
                </div>
            </div>
            
            <div class="balance-progress">
                <div class="progress-bar">
                    <div class="progress-segment used" :style="{ width: usedPercentage + '%' }"></div>
                    <div class="progress-segment pending" :style="{ width: pendingPercentage + '%' }"></div>
                    <div class="progress-segment remaining" :style="{ width: remainingPercentage + '%' }"></div>
                </div>
                <div class="progress-legend">
                    <span class="legend-item">
                        <span class="legend-color used"></span> Genommen ({{ balance?.usedDays || 0 }})
                    </span>
                    <span class="legend-item">
                        <span class="legend-color pending"></span> Beantragt ({{ balance?.pendingDays || 0 }})
                    </span>
                    <span class="legend-item">
                        <span class="legend-color remaining"></span> Verfügbar ({{ balance?.remainingDays || 0 }})
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Add New Vacation Button -->
        <div class="actions">
            <NcButton type="primary" @click="showModal = true">
                <template #icon>
                    <Plus :size="20" />
                </template>
                {{ t('timetracking', 'Urlaub beantragen') }}
            </NcButton>
        </div>
        
        <!-- Vacation List -->
        <div class="vacation-list">
            <h3>{{ t('timetracking', 'Meine Urlaubsanträge') }}</h3>
            
            <div v-if="loading" class="loading">
                {{ t('timetracking', 'Laden...') }}
            </div>
            
            <div v-else-if="vacations.length === 0" class="empty-state">
                <p>{{ t('timetracking', 'Keine Urlaubsanträge vorhanden') }}</p>
            </div>
            
            <table v-else class="vacation-table">
                <thead>
                    <tr>
                        <th>{{ t('timetracking', 'Zeitraum') }}</th>
                        <th>{{ t('timetracking', 'Tage') }}</th>
                        <th>{{ t('timetracking', 'Status') }}</th>
                        <th>{{ t('timetracking', 'Notizen') }}</th>
                        <th>{{ t('timetracking', 'Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vacation in vacations" :key="vacation.id" :class="'status-' + vacation.status">
                        <td>
                            <strong>{{ formatDate(vacation.startDate) }}</strong>
                            <span v-if="vacation.startDate !== vacation.endDate">
                                - {{ formatDate(vacation.endDate) }}
                            </span>
                        </td>
                        <td>{{ vacation.days }}</td>
                        <td>
                            <span :class="['status-badge', 'status-' + vacation.status]">
                                {{ getStatusLabel(vacation.status) }}
                            </span>
                        </td>
                        <td class="notes-cell">{{ vacation.notes || '-' }}</td>
                        <td class="actions-cell">
                            <NcButton
                                v-if="isAdmin && vacation.status === 'pending'"
                                type="primary"
                                @click="approveRequest(vacation.id)">
                                {{ t('timetracking', 'Genehmigen') }}
                            </NcButton>
                            <NcButton
                                v-if="isAdmin && vacation.status === 'pending'"
                                type="warning"
                                @click="rejectRequest(vacation.id)">
                                {{ t('timetracking', 'Ablehnen') }}
                            </NcButton>
                            <NcButton
                                v-if="vacation.status === 'pending' || isAdmin"
                                type="tertiary"
                                @click="editVacation(vacation)">
                                {{ t('timetracking', 'Bearbeiten') }}
                            </NcButton>
                            <NcButton
                                v-if="vacation.status === 'pending' || isAdmin"
                                type="error"
                                @click="deleteVacation(vacation.id)">
                                {{ t('timetracking', 'Löschen') }}
                            </NcButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Add/Edit Modal -->
        <NcModal v-if="showModal" @close="closeModal" size="normal">
            <div class="modal-content">
                <h2>{{ editingVacation ? t('timetracking', 'Urlaub bearbeiten') : t('timetracking', 'Urlaub beantragen') }}</h2>
                
                <form @submit.prevent="saveVacation">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Startdatum') }} *</label>
                        <input v-model="form.startDate" type="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Enddatum') }} *</label>
                        <input v-model="form.endDate" type="date" required :min="form.startDate">  
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Anzahl Tage') }} *</label>
                        <input v-model.number="form.days" type="number" min="0.5" step="0.5" required :placeholder="t('timetracking', 'Anzahl der Urlaubstage eingeben')">
                        <p class="hint">{{ t('timetracking', 'Bitte Wochenenden und Feiertage nicht mitzählen. Halbe Tage sind möglich (z.B. 0.5).') }}</p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Notizen') }}</label>
                        <textarea v-model="form.notes" rows="3" :placeholder="t('timetracking', 'Optional: Grund, besondere Hinweise...')"></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <NcButton type="secondary" native-type="button" @click="closeModal">
                            {{ t('timetracking', 'Abbrechen') }}
                        </NcButton>
                        <NcButton type="primary" native-type="submit">
                            {{ t('timetracking', 'Speichern') }}
                        </NcButton>
                    </div>
                </form>
            </div>
        </NcModal>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcModal } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
    name: 'Vacations',
    components: {
        NcButton,
        NcModal,
        Plus,
    },
    data() {
        const now = new Date()
        return {
            loading: true,
            currentYear: now.getFullYear(),
            vacations: [],
            pendingRequests: [],
            balance: null,
            showModal: false,
            editingVacation: null,
            isAdmin: getCurrentUser()?.isAdmin || false,
            form: {
                startDate: '',
                endDate: '',
                days: '',
                notes: '',
            },
        }
    },
    computed: {
        availableYears() {
            const currentYear = new Date().getFullYear()
            return [currentYear - 1, currentYear, currentYear + 1]
        },
        usedPercentage() {
            if (!this.balance) return 0
            return (this.balance.usedDays / this.balance.totalDays) * 100
        },
        pendingPercentage() {
            if (!this.balance) return 0
            return (this.balance.pendingDays / this.balance.totalDays) * 100
        },
        remainingPercentage() {
            if (!this.balance) return 0
            return (this.balance.remainingDays / this.balance.totalDays) * 100
        },
    },
    mounted() {
        this.loadVacations()
        this.loadBalance()
        if (this.isAdmin) {
            this.loadPendingRequests()
        }
    },
    methods: {
        t,
        async loadVacations() {
            try {
                this.loading = true
                const response = await axios.get(
                    generateUrl('/apps/timetracking/api/vacations'),
                    { params: { year: this.currentYear } }
                )
                this.vacations = response.data
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Laden der Urlaubsdaten'))
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        async loadBalance() {
            try {
                const response = await axios.get(
                    generateUrl(`/apps/timetracking/api/vacations/balance/${this.currentYear}`)
                )
                this.balance = response.data
            } catch (error) {
                console.error(error)
            }
        },
        extractDateString(dateInput) {
            if (!dateInput) return ''
            
            // Handle PHP DateTime JSON object
            if (typeof dateInput === 'object' && dateInput.date) {
                const datePart = dateInput.date.split(' ')[0]
                return datePart
            }
            
            // Handle string formats
            if (typeof dateInput === 'string') {
                // Remove time part if present (T or space separator)
                if (dateInput.includes('T')) {
                    return dateInput.split('T')[0]
                }
                if (dateInput.includes(' ')) {
                    return dateInput.split(' ')[0]
                }
                return dateInput
            }
            
            return String(dateInput)
        },
        editVacation(vacation) {
            this.editingVacation = vacation
            this.form.startDate = this.extractDateString(vacation.startDate)
            this.form.endDate = this.extractDateString(vacation.endDate)
            this.form.days = vacation.days
            this.form.notes = vacation.notes || ''
            this.showModal = true
        },
        async saveVacation() {
            try {
                if (this.editingVacation) {
                    await axios.put(
                        generateUrl(`/apps/timetracking/api/vacations/${this.editingVacation.id}`),
                        this.form
                    )
                    showSuccess(t('timetracking', 'Urlaub aktualisiert'))
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/vacations'),
                        this.form
                    )
                    showSuccess(t('timetracking', 'Urlaubsantrag erstellt'))
                }
                
                this.closeModal()
                this.loadVacations()
                this.loadBalance()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Speichern'))
                console.error(error)
            }
        },
        async deleteVacation(id) {
            if (!confirm(t('timetracking', 'Urlaubsantrag wirklich löschen?'))) {
                return
            }
            
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/vacations/${id}`))
                showSuccess(t('timetracking', 'Urlaubsantrag gelöscht'))
                this.loadVacations()
                this.loadBalance()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
            }
        },
        closeModal() {
            this.showModal = false
            this.editingVacation = null
            this.form = {
                startDate: '',
                endDate: '',
                days: '',
                notes: '',
            }
        },
        formatDate(dateInput) {
            if (!dateInput) return ''
            
            let dateString = dateInput
            
            // Handle PHP DateTime JSON object format: { date: "2026-01-02 00:00:00.000000", timezone_type: 3, timezone: "UTC" }
            if (typeof dateInput === 'object' && dateInput.date) {
                dateString = dateInput.date
            }
            
            // Handle different date formats
            let date
            if (typeof dateString === 'string') {
                // If it's YYYY-MM-DD HH:MM:SS.microseconds format (PHP DateTime)
                if (dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/)) {
                    const [datePart] = dateString.split(' ')
                    const [year, month, day] = datePart.split('-')
                    date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
                }
                // If it's YYYY-MM-DD format
                else if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    const [year, month, day] = dateString.split('-')
                    date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
                } else {
                    date = new Date(dateString)
                }
            } else {
                date = new Date(dateString)
            }
            
            if (isNaN(date.getTime())) return String(dateInput)
            
            return date.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        getStatusLabel(status) {
            const labels = {
                pending: this.t('timetracking', 'Beantragt'),
                approved: this.t('timetracking', 'Genehmigt'),
                rejected: this.t('timetracking', 'Abgelehnt'),
            }
            return labels[status] || status
        },
        async loadPendingRequests() {
            try {
                const response = await axios.get(
                    generateUrl('/apps/timetracking/api/vacations/pending')
                )
                this.pendingRequests = response.data
            } catch (error) {
                console.error('Error loading pending requests:', error)
            }
        },
        async approveRequest(id) {
            try {
                await axios.post(
                    generateUrl(`/apps/timetracking/api/vacations/${id}/approve`)
                )
                showSuccess(t('timetracking', 'Urlaubsantrag genehmigt'))
                this.loadPendingRequests()
                this.loadVacations()
                this.loadBalance()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Genehmigen'))
                console.error(error)
            }
        },
        async rejectRequest(id) {
            if (!confirm(t('timetracking', 'Urlaubsantrag wirklich ablehnen?'))) {
                return
            }
            
            try {
                await axios.post(
                    generateUrl(`/apps/timetracking/api/vacations/${id}/reject`)
                )
                showSuccess(t('timetracking', 'Urlaubsantrag abgelehnt'))
                this.loadPendingRequests()
                this.loadVacations()
                this.loadBalance()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Ablehnen'))
                console.error(error)
            }
        },
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.vacation-management {
    max-width: 1200px;
}

.balance-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.balance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.balance-header h3 {
    margin: 0;
}

.year-selector {
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
}

.balance-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: var(--color-background-hover);
    border-radius: 8px;
}

.stat-item.highlight {
    background: var(--color-primary-light);
}

.stat-label {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
}

.stat-value.primary {
    color: var(--color-primary);
}

.stat-value.used {
    color: #e67700;
}

.stat-value.pending {
    color: #0082c9;
}

.stat-value.success {
    color: #2e7d32;
    font-weight: bold;
}

.balance-progress {
    margin-top: 16px;
}

.progress-bar {
    height: 30px;
    background: #f5f5f5;
    border-radius: 15px;
    overflow: hidden;
    display: flex;
}

.progress-segment {
    height: 100%;
    transition: width 0.3s ease;
}

.progress-segment.used {
    background: #e67700;
}

.progress-segment.pending {
    background: #0082c9;
}

.progress-segment.remaining {
    background: #2e7d32;
}

.progress-legend {
    display: flex;
    gap: 20px;
    margin-top: 12px;
    font-size: 13px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.legend-color.used {
    background: #e67700;
}

.legend-color.pending {
    background: #0082c9;
}

.legend-color.remaining {
    background: #2e7d32;
}

.vacation-actions {
    margin-bottom: 24px;
}

.vacation-list h3 {
    margin-bottom: 16px;
}

.vacation-table {
    border-radius: 8px;
    overflow: hidden;
}

.notes-cell {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.actions-cell {
    display: flex;
    gap: 8px;
}

.pending-requests-card {
    background: var(--color-main-background);
    border: 2px solid #e67700;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.pending-requests-card h3 {
    color: #e67700;
    margin-bottom: 16px;
}
</style>
