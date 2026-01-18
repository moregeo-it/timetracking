<template>
    <div class="employee-settings">
        <div class="page-header">
            <h1>{{ t('timetracking', 'Mitarbeitereinstellungen') }}</h1>
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
            <form @submit.prevent="saveSettings">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsart') }} *</label>
                    <select v-model="form.employmentType" required :disabled="!isAdmin" @change="onEmploymentTypeChange">
                        <option value="contract">{{ t('timetracking', 'Festanstellung / Teilzeit') }}</option>
                        <option value="freelance">{{ t('timetracking', 'Praktikant / Stundenkontingent') }}</option>
                        <option value="mini_job">{{ t('timetracking', 'Werkstudent') }}</option>
                    </select>
                    <p class="hint">
                        <strong>Festanstellung:</strong> Reguläre Arbeitsverträge mit Urlaubsanspruch und ArbZG-Prüfung<br>
                        <strong>Praktikant:</strong> Maximale Gesamtstundenzahl, keine Urlaubstage<br>
                        <strong>Werkstudent:</strong> Reduzierte Stundenzahl
                    </p>
                </div>
                
                <!-- Contract/Minijob Settings -->
                <div v-if="form.employmentType === 'contract' || form.employmentType === 'mini_job'" class="contract-settings">
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
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Bundesland') }}</label>
                        <select v-model="form.federalState" :disabled="!isAdmin">
                            <option value="">{{ t('timetracking', 'Bitte wählen') }}</option>
                            <option value="BW">Baden-Württemberg</option>
                            <option value="BY">Bayern</option>
                            <option value="BE">Berlin</option>
                            <option value="BB">Brandenburg</option>
                            <option value="HB">Bremen</option>
                            <option value="HH">Hamburg</option>
                            <option value="HE">Hessen</option>
                            <option value="MV">Mecklenburg-Vorpommern</option>
                            <option value="NI">Niedersachsen</option>
                            <option value="NW">Nordrhein-Westfalen</option>
                            <option value="RP">Rheinland-Pfalz</option>
                            <option value="SL">Saarland</option>
                            <option value="SN">Sachsen</option>
                            <option value="ST">Sachsen-Anhalt</option>
                            <option value="SH">Schleswig-Holstein</option>
                            <option value="TH">Thüringen</option>
                        </select>
                        <p class="hint">
                            Für bundeslandspezifische Feiertage
                        </p>
                    </div>
                </div>
                
                <!-- Freelance Settings -->
                <div v-if="form.employmentType === 'freelance'" class="freelance-settings">
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
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsbeginn') }}</label>
                    <input v-model="form.employmentStart" type="date" :disabled="!isAdmin">
                </div>
                
                <div v-if="isAdmin" class="form-actions">
                    <NcButton type="primary" native-type="submit">
                        {{ t('timetracking', 'Einstellungen Speichern') }}
                    </NcButton>
                </div>
            </form>
            
            <!-- Current Status Display -->
            <div v-if="savedSettings" class="current-status">
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
            usedHours: 0,
            isAdmin: getCurrentUser()?.isAdmin || false,
            allUsers: [],
            selectedUserId: getCurrentUser()?.uid || '',
            form: {
                employmentType: 'contract',
                weeklyHours: 40,
                maxTotalHours: null,
                vacationDaysPerYear: 20,
                hourlyRate: null,
                federalState: '',
                employmentStart: '',
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
        }
        this.loadSettings()
        this.loadUsedHours()
    },
    methods: {
        t,
        async loadAllUsers() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/admin/users'))
                console.log('Loaded users:', response.data)
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
                this.savedSettings = response.data
                
                this.form.employmentType = response.data.employmentType
                this.form.weeklyHours = response.data.weeklyHours
                this.form.maxTotalHours = response.data.maxTotalHours
                this.form.vacationDaysPerYear = response.data.vacationDaysPerYear
                this.form.hourlyRate = response.data.hourlyRate
                this.form.federalState = response.data.federalState || ''
                this.form.employmentStart = response.data.employmentStart || ''
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
        async saveSettings() {
            try {
                const data = { ...this.form }
                if (this.isAdmin && this.selectedUserId) {
                    data.targetUserId = this.selectedUserId
                }
                await axios.put(
                    generateUrl('/apps/timetracking/api/employee-settings'),
                    data
                )
                showSuccess(t('timetracking', 'Einstellungen gespeichert'))
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
</style>
