<template>
    <div class="employee-settings">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Mitarbeitereinstellungen') }}</h1>
        </div>
        
        <!-- Admin: Default Multipliers Section -->
        <div v-if="isAdmin" class="default-multipliers-section">
            <h2>{{ t('timetracking', 'Standard-Multiplikatoren') }}</h2>
            <p class="hint">{{ t('timetracking', 'Diese Standardwerte werden in Kunden- und Projektberichten verwendet, wenn kein projektspezifischer Multiplikator gesetzt ist.') }}</p>
            
            <div class="multiplier-grid">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Geschäftsführer') }}</label>
                    <input v-model.number="defaultMultipliers.director" type="number" step="any" min="0.01" max="2" placeholder="1.0" @change="saveDefaultMultipliers">
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Festanstellung / Teilzeit') }}</label>
                    <input v-model.number="defaultMultipliers.contract" type="number" step="any" min="0.01" max="2" placeholder="1.0" @change="saveDefaultMultipliers">
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Freiberufler / Stundenkontingent') }}</label>
                    <input v-model.number="defaultMultipliers.freelance" type="number" step="any" min="0.01" max="2" placeholder="1.0" @change="saveDefaultMultipliers">
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Praktikant') }}</label>
                    <input v-model.number="defaultMultipliers.intern" type="number" step="any" min="0.01" max="2" placeholder="1.0" @change="saveDefaultMultipliers">
                </div>
                <div class="form-group">
                    <label>{{ t('timetracking', 'Werkstudent') }}</label>
                    <input v-model.number="defaultMultipliers.student" type="number" step="any" min="0.01" max="2" placeholder="1.0" @change="saveDefaultMultipliers">
                </div>
            </div>
        </div>
        
        <!-- Admin: User Selection -->
        <div v-if="isAdmin" class="user-selection">
            <div class="form-group">
                <label>{{ t('timetracking', 'Mitarbeiter auswählen') }}</label>
                <select v-model="selectedUserId" @change="loadUserSettings">
                    <option v-for="user in allUsers" :key="user.id" :value="user.id">
                        {{ user.displayName || user.id }}
                    </option>
                </select>
            </div>
        </div>
        
        <!-- Info for non-admins -->
        <div v-if="!isAdmin" class="info-box">
            <p>{{ t('timetracking', 'Diese Einstellungen können nur von Administratoren geändert werden.') }}</p>
        </div>
        
        <div v-if="loading" class="loading">{{ t('timetracking', 'Laden...') }}</div>
        
        <div v-else class="settings-form">
            <!-- Existing Periods List (Admin Only) -->
            <div v-if="isAdmin && periods.length > 0" class="periods-section">
                <h3>{{ t('timetracking', 'Zeiträume') }}</h3>
                <p class="hint">{{ t('timetracking', 'Die Beschäftigungsart und andere Einstellungen können sich im Laufe der Zeit ändern. Hier sehen Sie alle Zeiträume.') }}</p>
                
                <div class="periods-list">
                    <div v-for="period in periods" :key="period.id" 
                         class="period-item" 
                         :class="{ active: isCurrentPeriod(period), editing: editingPeriodId === period.id }">
                        <div class="period-header">
                            <span class="period-dates">
                                {{ formatDate(period.validFrom) || t('timetracking', 'Anfang') }} 
                                - 
                                {{ formatDate(period.validTo) || t('timetracking', 'Heute') }}
                            </span>
                            <span class="period-type">{{ getEmploymentTypeLabel(period.employmentType) }}</span>
                            <span class="period-hours">{{ period.weeklyHours }} h/{{ t('timetracking', 'Woche') }}</span>
                            <div class="period-actions">
                                <NcButton type="tertiary" @click="editPeriod(period)">
                                    {{ t('timetracking', 'Bearbeiten') }}
                                </NcButton>
                                <NcButton v-if="periods.length > 1" type="tertiary-no-background" @click="deletePeriod(period)">
                                    {{ t('timetracking', 'Löschen') }}
                                </NcButton>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="new-period-actions">
                    <NcButton v-if="!showNewPeriodForm" type="secondary" @click="startNewPeriod">
                        {{ t('timetracking', 'Neuen Zeitraum anlegen') }}
                    </NcButton>
                </div>
            </div>
            
            <!-- New Period Form or Edit Form -->
            <form @submit.prevent="saveSettings">
                <div v-if="showNewPeriodForm || editingPeriodId" class="period-form-header">
                    <h3>{{ editingPeriodId ? t('timetracking', 'Zeitraum bearbeiten') : t('timetracking', 'Neuer Zeitraum') }}</h3>
                    <p v-if="showNewPeriodForm" class="hint">
                        {{ t('timetracking', 'Der vorherige Zeitraum wird automatisch zum Tag vor diesem neuen Zeitraum beendet.') }}
                    </p>
                </div>
                
                <div v-if="isAdmin && (showNewPeriodForm || editingPeriodId)" class="form-row">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Gültig von') }} *</label>
                        <input v-model="form.validFrom" type="date" required>
                    </div>
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Gültig bis') }}</label>
                        <input v-model="form.validTo" type="date">
                        <p class="hint">{{ t('timetracking', 'Leer lassen für aktuellen/offenen Zeitraum') }}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsbeginn') }}</label>
                    <input v-model="form.employmentStart" type="date" :disabled="!isAdmin">
                </div>
        
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsart') }} *</label>
                    <select v-model="form.employmentType" required :disabled="!isAdmin" @change="onEmploymentTypeChange">
                        <option value="director">{{ t('timetracking', 'Geschäftsführer') }}</option>
                        <option value="contract">{{ t('timetracking', 'Festanstellung / Teilzeit') }}</option>
                        <option value="freelance">{{ t('timetracking', 'Freiberufler / Stundenkontingent') }}</option>
                        <option value="intern">{{ t('timetracking', 'Praktikant') }}</option>
                        <option value="student">{{ t('timetracking', 'Werkstudent') }}</option>
                    </select>
                    <p class="hint">
                        <strong>Festanstellung:</strong> Reguläre Arbeitsverträge mit Urlaubsanspruch und ArbZG-Prüfung<br>
                        <strong>Geschäftsführer:</strong> Wie Festanstellung, aber keine ArbZG-Prüfung<br>
                        <strong>Freiberufler:</strong> Maximale Gesamtstundenzahl, keine Urlaubstage<br>
                        <strong>Praktikant:</strong> Maximale Gesamtstundenzahl, keine Urlaubstage<br>
                        <strong>Werkstudent:</strong> Reduzierte Stundenzahl
                    </p>
                </div>
                
                <!-- Contract/Minijob/Executive Settings -->
                <div v-if="['director', 'contract', 'student'].includes(form.employmentType)" class="contract-settings">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Wochenstunden') }} *</label>
                        <input v-model.number="form.weeklyHours" type="number" step="0.5" min="0" max="60" required :disabled="!isAdmin">
                        <p class="hint">
                            Vertragliche Arbeitsstunden pro Woche (z.B. 40, 30, 20)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Urlaubstage pro Jahr') }}</label>
                        <input v-model.number="form.vacationDaysPerYear" type="number" min="0" max="50" :disabled="!isAdmin">
                        <p class="hint">
                            Gesetzliches Minimum: 20 Tage (bei 5-Tage-Woche)
                        </p>
                    </div>
                    
                    <div v-if="isAdmin" class="form-group">
                        <label>{{ t('timetracking', 'Stundensatz (€)') }}</label>
                        <input v-model.number="form.hourlyRate" type="number" step="0.01" min="0">
                        <p class="hint">
                            Stundensatz für Kundenabrechnung (optional)
                        </p>
                    </div>
                </div>
                
                <!-- Freelance/Intern Settings -->
                <div v-else-if="['freelance', 'intern'].includes(form.employmentType)" class="freelance-settings">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Maximale Gesamtstunden') }} *</label>
                        <input v-model.number="form.maxTotalHours" type="number" step="1" min="0" required :disabled="!isAdmin">
                        <p class="hint">
                            Gesamtes Stundenkontingent für diesen Auftrag/Vertrag.<br>
                            Bei Praktikanten werden keine Urlaubstage oder ArbZG-Prüfungen angewendet.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Durchschnittliche Wochenstunden') }}</label>
                        <input v-model.number="form.weeklyHours" type="number" step="0.5" min="0" :disabled="!isAdmin">
                        <p class="hint">
                            Optional: Für persönliche Planung (nicht für Compliance)
                        </p>
                    </div>
                    
                    <div v-if="isAdmin" class="form-group">
                        <label>{{ t('timetracking', 'Stundensatz (€)') }}</label>
                        <input v-model.number="form.hourlyRate" type="number" step="0.01" min="0">
                        <p class="hint">
                            Stundensatz für Kundenabrechnung (optional)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Urlaubstage pro Jahr') }}</label>
                        <input v-model.number="form.vacationDaysPerYear" type="number" min="0" max="50" :disabled="!isAdmin">
                        <p class="hint">
                            Falls gewünscht, können Sie auch als Praktikant Urlaubstage tracken
                        </p>
                    </div>
                </div>
                
                <!-- Common Settings -->
                
                <div v-if="isAdmin" class="form-actions">
                    <NcButton type="submit">
                        {{ editingPeriodId ? t('timetracking', 'Zeitraum speichern') : (showNewPeriodForm ? t('timetracking', 'Zeitraum anlegen') : t('timetracking', 'Einstellungen Speichern')) }}
                    </NcButton>
                    <NcButton v-if="showNewPeriodForm || editingPeriodId" type="tertiary" @click="cancelEdit">
                        {{ t('timetracking', 'Abbrechen') }}
                    </NcButton>
                </div>
            </form>
            
            <!-- Current Status Display -->
            <div v-if="savedSettings && !showNewPeriodForm && !editingPeriodId" class="current-status">
                <h3>{{ t('timetracking', 'Aktueller Status') }}</h3>
                
                <div v-if="savedSettings.employmentType === 'freelance' && savedSettings.maxTotalHours" class="status-card">
                    <div class="status-item">
                        <span class="label">{{ t('timetracking', 'Kontingent gesamt:') }}</span>
                        <span class="value">{{ savedSettings.maxTotalHours }} h</span>
                    </div>
                    <div class="status-item">
                        <span class="label">{{ t('timetracking', 'Bereits erfasst:') }}</span>
                        <span class="value">{{ usedHours }} h</span>
                    </div>
                    <div class="status-item">
                        <span class="label">{{ t('timetracking', 'Verbleibend:') }}</span>
                        <span class="value" :class="{ warning: remainingPercentage < 20 }">
                            {{ remainingHours }} h ({{ remainingPercentage }}%)
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: usedPercentage + '%' }" 
                             :class="{ warning: usedPercentage > 80, danger: usedPercentage > 95 }">
                        </div>
                    </div>
                </div>
                
                <div v-else class="status-card">
                    <div class="status-item">
                        <span class="label">{{ t('timetracking', 'Wochenstunden:') }}</span>
                        <span class="value">{{ savedSettings.weeklyHours }} h</span>
                    </div>
                    <div class="status-item">
                        <span class="label">{{ t('timetracking', 'Urlaubstage/Jahr:') }}</span>
                        <span class="value">{{ savedSettings.vacationDaysPerYear }}</span>
                    </div>
                    <div v-if="isAdmin && savedSettings.hourlyRate" class="status-item">
                        <span class="label">{{ t('timetracking', 'Stundensatz:') }}</span>
                        <span class="value">{{ savedSettings.hourlyRate }} €</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton } from '@nextcloud/vue'

export default {
    name: 'EmployeeSettings',
    components: {
        NcButton,
    },
    data() {
        return {
            loading: true,
            savedSettings: null,
            periods: [],
            usedHours: 0,
            isAdmin: getCurrentUser()?.isAdmin || false,
            allUsers: [],
            selectedUserId: getCurrentUser()?.uid || '',
            showNewPeriodForm: false,
            editingPeriodId: null,
            defaultMultipliers: {
                director: 1.0,
                contract: 1.0,
                freelance: 1.0,
                intern: 1.0,
                student: 1.0,
            },
            form: {
                employmentType: 'contract',
                weeklyHours: 40,
                maxTotalHours: null,
                vacationDaysPerYear: 20,
                hourlyRate: null,
                employmentStart: '',
                validFrom: '',
                validTo: '',
            },
        }
    },
    computed: {
        remainingHours() {
            if (!this.savedSettings?.maxTotalHours) return 0
            return Math.max(0, this.savedSettings.maxTotalHours - this.usedHours).toFixed(2)
        },
        usedPercentage() {
            if (!this.savedSettings?.maxTotalHours) return 0
            return Math.min(100, (this.usedHours / this.savedSettings.maxTotalHours) * 100).toFixed(1)
        },
        remainingPercentage() {
            return (100 - this.usedPercentage).toFixed(1)
        },
    },
    mounted() {
        if (this.isAdmin) {
            this.loadAllUsers()
            this.loadDefaultMultipliers()
        }
        this.loadSettings()
        this.loadUsedHours()
    },
    methods: {
        t,
        formatDate(dateStr) {
            if (!dateStr) return null
            const date = new Date(dateStr)
            return date.toLocaleDateString()
        },
        getEmploymentTypeLabel(type) {
            const labels = {
                director: t('timetracking', 'Geschäftsführer'),
                contract: t('timetracking', 'Festanstellung / Teilzeit'),
                freelance: t('timetracking', 'Freiberufler / Stundenkontingent'),
                intern: t('timetracking', 'Praktikant'),
                student: t('timetracking', 'Werkstudent'),
            }
            return labels[type] || type
        },
        isCurrentPeriod(period) {
            const today = new Date().toISOString().split('T')[0]
            const validFrom = period.validFrom || '1970-01-01'
            const validTo = period.validTo || '2099-12-31'
            return validFrom <= today && today <= validTo
        },
        async loadDefaultMultipliers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/default-multipliers'))
                this.defaultMultipliers = {
                    director: response.data.director ?? 1.0,
                    contract: response.data.contract ?? 1.0,
                    freelance: response.data.freelance ?? 1.0,
                    intern: response.data.intern ?? 1.0,
                    student: response.data.student ?? 1.0,
                }
            } catch (error) {
                console.error('Error loading default multipliers:', error)
            }
        },
        async saveDefaultMultipliers() {
            try {
                await axios.put(
                    generateUrl('/apps/timetracking/api/admin/default-multipliers'),
                    { multipliers: this.defaultMultipliers }
                )
                showSuccess(t('timetracking', 'Standard-Multiplikatoren gespeichert'))
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Speichern der Standard-Multiplikatoren'))
                console.error('Error saving default multipliers:', error)
            }
        },
        async loadAllUsers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/users'))
                this.allUsers = response.data
                // Ensure selected user is set
                if (this.allUsers.length > 0) {
                    // If current user is in list, keep it selected, otherwise select first
                    const currentUserInList = this.allUsers.find(u => u.id === this.selectedUserId)
                    if (!currentUserInList) {
                        this.selectedUserId = this.allUsers[0].id
                    }
                }
            } catch (error) {
                console.error('Error loading users:', error)
                console.error('Response:', error.response?.data)
            }
        },
        async loadUserSettings() {
            // Called when admin selects a different user
            this.loading = true
            this.cancelEdit()
            await this.loadSettings()
            await this.loadUsedHoursForUser()
        },
        async loadSettings() {
            try {
                let url = generateUrl('/apps/timetracking/api/employee-settings')
                if (this.isAdmin && this.selectedUserId) {
                    url = generateUrl(`/apps/timetracking/api/employee-settings/${this.selectedUserId}`)
                }
                const response = await axios.get(url)
                
                // New structure: { current, periods }
                if (response.data.periods !== undefined) {
                    this.savedSettings = response.data.current
                    this.periods = response.data.periods || []
                } else {
                    // Backwards compatibility
                    this.savedSettings = response.data
                    this.periods = response.data ? [response.data] : []
                }
                
                if (this.savedSettings) {
                    this.form.employmentType = this.savedSettings.employmentType
                    this.form.weeklyHours = this.savedSettings.weeklyHours
                    this.form.maxTotalHours = this.savedSettings.maxTotalHours
                    this.form.vacationDaysPerYear = this.savedSettings.vacationDaysPerYear
                    this.form.hourlyRate = this.savedSettings.hourlyRate
                    this.form.employmentStart = this.savedSettings.employmentStart || ''
                    this.form.validFrom = this.savedSettings.validFrom || ''
                    this.form.validTo = this.savedSettings.validTo || ''
                }
            } catch (error) {
                console.error(error)
            } finally {
                this.loading = false
            }
        },
        async loadUsedHours() {
            try {
                // Get all time entries to calculate total used hours
                const response = await axios.get(generateUrl('/apps/timetracking/api/time-entries'))
                this.usedHours = response.data.reduce((sum, entry) => {
                    return sum + ((entry.durationMinutes || 0) / 60)
                }, 0).toFixed(2)
            } catch (error) {
                console.error(error)
            }
        },
        async loadUsedHoursForUser() {
            if (!this.isAdmin || !this.selectedUserId) {
                return this.loadUsedHours()
            }
            try {
                // For admin viewing another user - use admin time entries endpoint
                const response = await axios.get(generateUrl(`/apps/timetracking/api/admin/time-entries?userId=${this.selectedUserId}`))
                this.usedHours = response.data.reduce((sum, entry) => {
                    return sum + ((entry.durationMinutes || 0) / 60)
                }, 0).toFixed(2)
            } catch (error) {
                console.error(error)
                this.usedHours = 0
            }
        },
        onEmploymentTypeChange() {
            if (this.form.employmentType === 'freelance') {
                this.form.vacationDaysPerYear = 0
            } else if (this.form.vacationDaysPerYear === 0) {
                this.form.vacationDaysPerYear = 20
            }
        },
        startNewPeriod() {
            this.showNewPeriodForm = true
            this.editingPeriodId = null
            // Set default validFrom to tomorrow
            const tomorrow = new Date()
            tomorrow.setDate(tomorrow.getDate() + 1)
            this.form.validFrom = tomorrow.toISOString().split('T')[0]
            this.form.validTo = ''
            // Keep other values from current settings for convenience
        },
        editPeriod(period) {
            this.editingPeriodId = period.id
            this.showNewPeriodForm = false
            this.form.employmentType = period.employmentType
            this.form.weeklyHours = period.weeklyHours
            this.form.maxTotalHours = period.maxTotalHours
            this.form.vacationDaysPerYear = period.vacationDaysPerYear
            this.form.hourlyRate = period.hourlyRate
            this.form.employmentStart = period.employmentStart || ''
            this.form.validFrom = period.validFrom ? period.validFrom.split('T')[0] : ''
            this.form.validTo = period.validTo ? period.validTo.split('T')[0] : ''
        },
        cancelEdit() {
            this.showNewPeriodForm = false
            this.editingPeriodId = null
            // Restore form from current settings
            if (this.savedSettings) {
                this.form.employmentType = this.savedSettings.employmentType
                this.form.weeklyHours = this.savedSettings.weeklyHours
                this.form.maxTotalHours = this.savedSettings.maxTotalHours
                this.form.vacationDaysPerYear = this.savedSettings.vacationDaysPerYear
                this.form.hourlyRate = this.savedSettings.hourlyRate
                this.form.employmentStart = this.savedSettings.employmentStart || ''
                this.form.validFrom = this.savedSettings.validFrom || ''
                this.form.validTo = this.savedSettings.validTo || ''
            }
        },
        async deletePeriod(period) {
            if (!confirm(t('timetracking', 'Möchten Sie diesen Zeitraum wirklich löschen?'))) {
                return
            }
            try {
                await axios.delete(generateUrl(`/apps/timetracking/api/employee-settings/${period.id}`))
                showSuccess(t('timetracking', 'Zeitraum gelöscht'))
                this.loadSettings()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Löschen'))
                console.error(error)
            }
        },
        async saveSettings() {
            try {
                const data = { ...this.form }
                if (this.isAdmin && this.selectedUserId) {
                    data.targetUserId = this.selectedUserId
                }
                
                if (this.editingPeriodId) {
                    // Update existing period
                    data.periodId = this.editingPeriodId
                    await axios.put(
                        generateUrl('/apps/timetracking/api/employee-settings'),
                        data
                    )
                    showSuccess(t('timetracking', 'Zeitraum gespeichert'))
                } else if (this.showNewPeriodForm) {
                    // Create new period
                    await axios.post(
                        generateUrl('/apps/timetracking/api/employee-settings'),
                        data
                    )
                    showSuccess(t('timetracking', 'Neuer Zeitraum angelegt'))
                } else {
                    // Regular update (backwards compatible)
                    await axios.put(
                        generateUrl('/apps/timetracking/api/employee-settings'),
                        data
                    )
                    showSuccess(t('timetracking', 'Einstellungen gespeichert'))
                }
                
                this.cancelEdit()
                this.loadSettings()
                if (this.isAdmin) {
                    this.loadUsedHoursForUser()
                } else {
                    this.loadUsedHours()
                }
            } catch (error) {
                if (error.response?.status === 403) {
                    showError(t('timetracking', 'Nur Administratoren können Einstellungen ändern'))
                } else {
                    showError(t('timetracking', 'Fehler beim Speichern'))
                }
                console.error(error)
            }
        },
    },
}
</script>

<style scoped>
/* Component-specific styles only - common styles are in App.vue */
.employee-settings {
    max-width: 800px;
}

.user-selection {
    background: var(--color-background-hover);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid var(--color-border);
}

.user-selection .form-group {
    margin-bottom: 0;
}

.user-selection label {
    color: var(--color-main-text);
    font-weight: bold;
}

.info-box {
    background: var(--color-background-hover);
    border-left: 4px solid var(--color-primary);
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box p {
    margin: 0;
    color: var(--color-text-maxcontrast);
}

.settings-form {
    background: var(--color-main-background);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--color-border);
}

.contract-settings,
.freelance-settings {
    background: var(--color-background-hover);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-actions {
    margin-top: 30px;
}

.current-status {
    margin-top: 40px;
    padding: 1rem;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.status-card {
    background: var(--color-main-background);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-border);
}

.status-item:last-child {
    border-bottom: none;
}

.status-item .label {
    font-weight: bold;
}

.status-item .value {
    color: var(--color-primary);
    font-size: 18px;
}

.status-item .value.warning {
    color: var(--color-warning);
}

.progress-bar {
    margin-top: 20px;
    height: 30px;
    background: var(--color-background-dark);
    border-radius: 15px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--color-success);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.progress-fill.warning {
    background: var(--color-warning);
}

.progress-fill.danger {
    background: var(--color-error);
}

/* Period Management Styles */
.periods-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--color-border);
}

.periods-list {
    margin: 15px 0;
}

.period-item {
    background: var(--color-background-hover);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}

.period-item.active {
    border-color: var(--color-primary);
    background: var(--color-primary-element-light);
}

.period-item.editing {
    border-color: var(--color-warning);
}

.period-header {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.period-dates {
    font-weight: bold;
    min-width: 200px;
}

.period-type {
    color: var(--color-text-maxcontrast);
    flex: 1;
}

.period-hours {
    color: var(--color-primary);
    font-weight: bold;
}

.period-actions {
    display: flex;
    gap: 5px;
}

.new-period-actions {
    margin-top: 15px;
}

.period-form-header {
    background: var(--color-primary-element-light);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.period-form-header h3 {
    margin: 0 0 5px 0;
    color: var(--color-primary);
}

.form-row {
    display: flex;
    gap: 20px;
}

.form-row .form-group {
    flex: 1;
}

/* Default Multipliers Section */
.default-multipliers-section {
    background: var(--color-background-hover);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.default-multipliers-section h2 {
    margin: 0 0 10px 0;
    font-size: 1.2em;
}

.default-multipliers-section .hint {
    color: var(--color-text-maxcontrast);
    margin-bottom: 15px;
}

.multiplier-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.multiplier-grid .form-group {
    margin-bottom: 0;
}

.multiplier-grid input {
    width: 100%;
}
</style>
