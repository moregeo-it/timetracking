<template>
    <div class="vacation-management">
        <NcAppContentDetails>
            <template #title>
                {{ t('timetracking', 'Urlaubsverwaltung') }}
            </template>
        </NcAppContentDetails>
        
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
                                v-if="vacation.status === 'pending'"
                                type="tertiary"
                                @click="editVacation(vacation)">
                                {{ t('timetracking', 'Bearbeiten') }}
                            </NcButton>
                            <NcButton
                                v-if="vacation.status === 'pending'"
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
                        <input v-model="form.startDate" type="date" required :min="minDate">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Enddatum') }} *</label>
                        <input v-model="form.endDate" type="date" required :min="form.startDate || minDate">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Anzahl Tage') }} *</label>
                        <input v-model.number="form.days" type="number" min="0.5" step="0.5" required>
                        <p class="hint">{{ t('timetracking', 'Halbe Tage sind möglich (z.B. 2.5)') }}</p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Notizen') }}</label>
                        <textarea v-model="form.notes" rows="3" :placeholder="t('timetracking', 'Optional: Grund, besondere Hinweise...')"></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <NcButton type="secondary" @click="closeModal">
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
import { NcButton, NcModal, NcAppContentDetails } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
    name: 'Vacations',
    components: {
        NcButton,
        NcModal,
        NcAppContentDetails,
        Plus,
    },
    data() {
        const now = new Date()
        return {
            loading: true,
            currentYear: now.getFullYear(),
            vacations: [],
            balance: null,
            showModal: false,
            editingVacation: null,
            form: {
                startDate: '',
                endDate: '',
                days: 1,
                notes: '',
            },
        }
    },
    computed: {
        availableYears() {
            const currentYear = new Date().getFullYear()
            return [currentYear - 1, currentYear, currentYear + 1]
        },
        minDate() {
            return new Date().toISOString().split('T')[0]
        },
        usedPercentage() {
            if (!this.balance) return 0
            return (this.balance.usedDays / this.balance.totalDays) * 100
        },
        pendingPercentage() {
            if (!this.balance) return 0
            return (this.balance.pendingDays / this.balance.totalDays) * 100
        },
    },
    mounted() {
        this.loadVacations()
        this.loadBalance()
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
        editVacation(vacation) {
            this.editingVacation = vacation
            this.form.startDate = vacation.startDate.split('T')[0]
            this.form.endDate = vacation.endDate.split('T')[0]
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
                days: 1,
                notes: '',
            }
        },
        formatDate(dateString) {
            if (!dateString) return ''
            const date = new Date(dateString)
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
    },
}
</script>

<style scoped>
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
    color: var(--color-success);
}

.balance-progress {
    margin-top: 16px;
}

.progress-bar {
    height: 30px;
    background: #e0e0e0;
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
    background: #e0e0e0;
}

.actions {
    margin-bottom: 24px;
}

.vacation-list h3 {
    margin-bottom: 16px;
}

.loading,
.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--color-text-maxcontrast);
}

.vacation-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--color-main-background);
    border-radius: 8px;
    overflow: hidden;
}

.vacation-table thead {
    background: var(--color-background-dark);
}

.vacation-table th,
.vacation-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.vacation-table tbody tr:hover {
    background: var(--color-background-hover);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.status-pending {
    background: #d4e8ff;
    color: #0082c9;
}

.status-badge.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-rejected {
    background: #f8d7da;
    color: #721c24;
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

.modal-content {
    padding: 24px;
}

.modal-content h2 {
    margin-bottom: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
}

.hint {
    margin-top: 5px;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
}
</style>
