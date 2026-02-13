<template>
    <div class="sick-day-management">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Krankmeldungen') }}</h1>
        </div>
        
        <!-- Admin: Employee Selection -->
        <div v-if="isAdmin" class="employee-selection">
            <div class="form-group">
                <label>{{ t('timetracking', 'Mitarbeiter auswählen') }}</label>
                <select v-model="selectedUserId" @change="onEmployeeChange">
                    <option value="">{{ t('timetracking', 'Alle Mitarbeiter') }}</option>
                    <option v-for="user in allUsers" :key="user.id" :value="user.id">
                        {{ user.displayName || user.id }}
                    </option>
                </select>
            </div>
        </div>
        
        <!-- Summary Card -->
        <div class="balance-card">
            <div class="balance-header">
                <h3>
                    {{ t('timetracking', 'Krankheitstage') }} {{ currentYear }}
                    <span v-if="selectedEmployeeName"> - {{ selectedEmployeeName }}</span>
                </h3>
                <select v-model="currentYear" @change="onYearChange" class="year-selector">
                    <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                </select>
            </div>
            
            <div v-if="summary" class="balance-stats">
                <div class="stat-item">
                    <div class="stat-label">{{ t('timetracking', 'Krankheitstage gesamt') }}</div>
                    <div class="stat-value used">{{ summary.totalDays }} {{ t('timetracking', 'Tage') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">{{ t('timetracking', 'Lohnfortzahlung (max. 42 Tage/Fall)') }}</div>
                    <div class="stat-value primary">{{ t('timetracking', 'EFZG §3') }}</div>
                </div>
            </div>
        </div>
        
        <!-- Add New Sick Day Button -->
        <div class="actions">
            <NcButton type="button" @click="showModal = true">
                <template #icon>
                    <Plus :size="20" />
                </template>
                {{ t('timetracking', 'Krankmeldung erfassen') }}
            </NcButton>
        </div>
        
        <!-- Sick Day List -->
        <div class="sick-day-list">
            <h3>
                {{ t('timetracking', 'Krankmeldungsübersicht') }}
                <span v-if="selectedEmployeeName"> - {{ selectedEmployeeName }}</span>
            </h3>
            
            <div v-if="loading" class="loading">
                {{ t('timetracking', 'Laden...') }}
            </div>
            
            <div v-else-if="sickDays.length === 0" class="empty-state">
                <p>{{ t('timetracking', 'Keine Krankmeldungen vorhanden') }}</p>
            </div>
            
            <table v-else class="sick-day-table">
                <thead>
                    <tr>
                        <th v-if="!selectedUserId">{{ t('timetracking', 'Mitarbeiter') }}</th>
                        <th>{{ t('timetracking', 'Zeitraum') }}</th>
                        <th>{{ t('timetracking', 'Tage') }}</th>
                        <th>{{ t('timetracking', 'Notizen') }}</th>
                        <th>{{ t('timetracking', 'Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sickDay in sickDays" :key="sickDay.id">
                        <td v-if="!selectedUserId">{{ sickDay.displayName || sickDay.userId }}</td>
                        <td>
                            <strong>{{ formatDate(sickDay.startDate) }}</strong>
                            <span v-if="sickDay.startDate !== sickDay.endDate">
                                - {{ formatDate(sickDay.endDate) }}
                            </span>
                        </td>
                        <td>{{ sickDay.days }}</td>
                        <td class="notes-cell">{{ sickDay.notes || '-' }}</td>
                        <td class="actions-cell">
                            <NcButton
                                v-if="sickDay.canEdit"
                                type="button"
                                @click="editSickDay(sickDay)"
                                :title="t('timetracking', 'Bearbeiten')">
                                <template #icon>
                                    <Pencil :size="20" />
                                </template>
                            </NcButton>
                            <NcButton
                                v-if="sickDay.canDelete"
                                type="button"
                                @click="deleteSickDay(sickDay.id)"
                                :title="t('timetracking', 'Löschen')">
                                <template #icon>
                                    <Delete :size="20" />
                                </template>
                            </NcButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Add/Edit Modal -->
        <NcModal v-if="showModal" @close="closeModal" size="normal">
            <div class="modal-content">
                <h2>{{ editingSickDay ? t('timetracking', 'Krankmeldung bearbeiten') : t('timetracking', 'Krankmeldung erfassen') }}</h2>
                
                <form @submit.prevent="saveSickDay">
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
                        <input v-model.number="form.days" type="number" min="1" step="1" required :placeholder="t('timetracking', 'Anzahl der Krankheitstage eingeben')">
                        <p class="hint">{{ t('timetracking', 'Bitte Wochenenden und Feiertage nicht mitzählen.') }}</p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Notizen') }}</label>
                        <textarea v-model="form.notes" rows="3" :placeholder="t('timetracking', 'Optional: Hinweise zur Krankmeldung...')"></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <NcButton type="button" @click="closeModal">
                            {{ t('timetracking', 'Abbrechen') }}
                        </NcButton>
                        <NcButton type="submit">
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
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
    name: 'SickDays',
    components: {
        NcButton,
        NcModal,
        Plus,
        Pencil,
        Delete,
    },
    data() {
        const now = new Date()
        return {
            loading: true,
            currentYear: now.getFullYear(),
            sickDays: [],
            summary: null,
            showModal: false,
            editingSickDay: null,
            isAdmin: getCurrentUser()?.isAdmin || false,
            allUsers: [],
            selectedUserId: getCurrentUser()?.uid || '',
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
        selectedEmployeeName() {
            if (!this.selectedUserId) return null
            const user = this.allUsers.find(u => u.id === this.selectedUserId)
            return user ? (user.displayName || user.id) : null
        },
    },
    async mounted() {
        if (this.isAdmin) {
            await this.loadAllUsers()
        }
        this.loadSickDays()
        this.loadSummary()
    },
    methods: {
        t,
        async loadAllUsers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/users'))
                this.allUsers = response.data
            } catch (error) {
                console.error('Error loading users:', error)
            }
        },
        onEmployeeChange() {
            this.loadSickDays()
            this.loadSummary()
        },
        onYearChange() {
            this.loadSickDays()
            this.loadSummary()
        },
        async loadSickDays() {
            try {
                this.loading = true
                const params = { year: this.currentYear }
                if (this.selectedUserId) {
                    params.userId = this.selectedUserId
                }
                const response = await axios.get(
                    generateUrl('/apps/timetracking/api/sick-days'),
                    { params }
                )
                this.sickDays = response.data
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Laden der Krankmeldungen'))
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        async loadSummary() {
            try {
                let url = generateUrl(`/apps/timetracking/api/sick-days/summary/${this.currentYear}`)
                if (this.selectedUserId) {
                    url += `?userId=${this.selectedUserId}`
                }
                const response = await axios.get(url)
                this.summary = response.data
            } catch (error) {
                console.error(error)
            }
        },
        extractDateString(dateInput) {
            if (!dateInput) return ''
            if (typeof dateInput === 'object' && dateInput.date) {
                return dateInput.date.split(' ')[0]
            }
            if (typeof dateInput === 'string') {
                if (dateInput.includes('T')) return dateInput.split('T')[0]
                if (dateInput.includes(' ')) return dateInput.split(' ')[0]
                return dateInput
            }
            return String(dateInput)
        },
        editSickDay(sickDay) {
            this.editingSickDay = sickDay
            this.form.startDate = this.extractDateString(sickDay.startDate)
            this.form.endDate = this.extractDateString(sickDay.endDate)
            this.form.days = sickDay.days
            this.form.notes = sickDay.notes || ''
            this.showModal = true
        },
        async saveSickDay() {
            try {
                const data = { ...this.form }
                // Admin creating sick day for selected employee
                if (this.isAdmin && this.selectedUserId) {
                    data.userId = this.selectedUserId
                }

                if (this.editingSickDay) {
                    await axios.put(
                        generateUrl(`/apps/timetracking/api/sick-days/${this.editingSickDay.id}`),
                        data
                    )
                    showSuccess(t('timetracking', 'Krankmeldung aktualisiert'))
                } else {
                    await axios.post(
                        generateUrl('/apps/timetracking/api/sick-days'),
                        data
                    )
                    showSuccess(t('timetracking', 'Krankmeldung erstellt'))
                }
                
                this.closeModal()
                await this.loadSickDays()
                await this.loadSummary()
            } catch (error) {
                if (error.response?.status === 409) {
                    showError(t('timetracking', 'Krankmeldung überschneidet sich mit einer bestehenden Krankmeldung'))
                } else {
                    showError(t('timetracking', 'Fehler beim Speichern'))
                }
                console.error(error)
            }
        },
        async deleteSickDay(id) {
            if (!confirm(t('timetracking', 'Krankmeldung wirklich löschen?'))) {
                return
            }
            
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/sick-days/${id}`))
                showSuccess(t('timetracking', 'Krankmeldung gelöscht'))
                await this.loadSickDays()
                await this.loadSummary()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
            }
        },
        closeModal() {
            this.showModal = false
            this.editingSickDay = null
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
            if (typeof dateInput === 'object' && dateInput.date) {
                dateString = dateInput.date
            }
            
            let date
            if (typeof dateString === 'string') {
                if (dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/)) {
                    const [datePart] = dateString.split(' ')
                    const [year, month, day] = datePart.split('-')
                    date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
                } else if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    const [year, month, day] = dateString.split('-')
                    date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
                } else {
                    date = new Date(dateString)
                }
            } else {
                date = new Date(dateString)
            }
            
            if (isNaN(date.getTime())) return String(dateInput)
            
            return date.toLocaleDateString(undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
    },
}
</script>

<style scoped>
.sick-day-management {
    max-width: 1200px;
}

.employee-selection {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
}

.employee-selection .form-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.employee-selection label {
    font-weight: 500;
    margin: 0;
}

.employee-selection select {
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    min-width: 250px;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: var(--color-background-hover);
    border-radius: 8px;
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
    font-size: 16px;
}

.stat-value.used {
    color: #c62828;
}

.sick-day-list h3 {
    margin-bottom: 16px;
}

.sick-day-table {
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
    white-space: nowrap;
}

.actions-cell :deep(button) {
    display: inline-block;
    margin-right: 8px;
}

.actions-cell :deep(button:last-child) {
    margin-right: 0;
}
</style>
