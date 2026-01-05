<template>
    <div class="employee-settings">
        <NcAppContentDetails>
            <template #title>
                {{ t('timetracking', 'Mitarbeitereinstellungen') }}
            </template>
        </NcAppContentDetails>
        
        <div v-if="loading" class="loading">{{ t('timetracking', 'Laden...') }}</div>
        
        <div v-else class="settings-form">
            <form @submit.prevent="saveSettings">
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsart') }} *</label>
                    <select v-model="form.employmentType" required @change="onEmploymentTypeChange">
                        <option value="contract">{{ t('timetracking', 'Festanstellung / Teilzeit') }}</option>
                        <option value="freelance">{{ t('timetracking', 'Freiberufler / Stundenkontingent') }}</option>
                        <option value="mini_job">{{ t('timetracking', 'Minijob') }}</option>
                    </select>
                    <p class="hint">
                        <strong>Festanstellung:</strong> Reguläre Arbeitsverträge mit Urlaubsanspruch und ArbZG-Prüfung<br>
                        <strong>Freiberufler:</strong> Maximale Gesamtstundenzahl, keine Urlaubstage<br>
                        <strong>Minijob:</strong> Geringfügige Beschäftigung, reduzierte Stundenzahl
                    </p>
                </div>
                
                <!-- Contract/Minijob Settings -->
                <div v-if="form.employmentType === 'contract' || form.employmentType === 'mini_job'" class="contract-settings">
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Wochenstunden') }} *</label>
                        <input v-model.number="form.weeklyHours" type="number" step="0.5" min="0" max="60" required>
                        <p class="hint">
                            Vertragliche Arbeitsstunden pro Woche (z.B. 40, 30, 20)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Urlaubstage pro Jahr') }}</label>
                        <input v-model.number="form.vacationDaysPerYear" type="number" min="0" max="50">
                        <p class="hint">
                            Gesetzliches Minimum: 20 Tage (bei 5-Tage-Woche)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Stundensatz (€)') }}</label>
                        <input v-model.number="form.hourlyRate" type="number" step="0.01" min="0">
                        <p class="hint">
                            Stundensatz für Kundenabrechnung (optional)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Bundesland') }}</label>
                        <select v-model="form.federalState">
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
                        <input v-model.number="form.maxTotalHours" type="number" step="1" min="0" required>
                        <p class="hint">
                            Gesamtes Stundenkontingent für diesen Auftrag/Vertrag.<br>
                            Bei Freiberuflern werden keine Urlaubstage oder ArbZG-Prüfungen angewendet.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Durchschnittliche Wochenstunden') }}</label>
                        <input v-model.number="form.weeklyHours" type="number" step="0.5" min="0">
                        <p class="hint">
                            Optional: Für persönliche Planung (nicht für Compliance)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Stundensatz (€)') }}</label>
                        <input v-model.number="form.hourlyRate" type="number" step="0.01" min="0">
                        <p class="hint">
                            Stundensatz für Kundenabrechnung (optional)
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ t('timetracking', 'Urlaubstage pro Jahr') }}</label>
                        <input v-model.number="form.vacationDaysPerYear" type="number" min="0" max="50">
                        <p class="hint">
                            Falls gewünscht, können Sie auch als Freiberufler Urlaubstage tracken
                        </p>
                    </div>
                </div>
                
                <!-- Common Settings -->
                <div class="form-group">
                    <label>{{ t('timetracking', 'Beschäftigungsbeginn') }}</label>
                    <input v-model="form.employmentStart" type="date">
                </div>
                
                <div class="form-actions">
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
                    <div v-if="savedSettings.hourlyRate" class="status-item">
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
import { NcButton, NcAppContentDetails } from '@nextcloud/vue'

export default {
    name: 'EmployeeSettings',
    components: {
        NcButton,
        NcAppContentDetails,
    },
    data() {
        return {
            loading: true,
            savedSettings: null,
            usedHours: 0,
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
        this.loadSettings()
        this.loadUsedHours()
    },
    methods: {
        t,
        async loadSettings() {
            try {
                const response = await axios.get(generateUrl('/apps/timetracking/api/employee-settings'))
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
        onEmploymentTypeChange() {
            if (this.form.employmentType === 'freelance') {
                this.form.vacationDaysPerYear = 0
            } else if (this.form.vacationDaysPerYear === 0) {
                this.form.vacationDaysPerYear = 20
            }
        },
        async saveSettings() {
            try {
                await axios.put(
                    generateUrl('/apps/timetracking/api/employee-settings'),
                    this.form
                )
                showSuccess(t('timetracking', 'Einstellungen gespeichert'))
                this.loadSettings()
                this.loadUsedHours()
            } catch (error) {
                showError(t('timetracking', 'Fehler beim Speichern'))
                console.error(error)
            }
        },
    },
}
</script>

<style scoped>
.employee-settings {
    max-width: 800px;
}

.settings-form {
    background: var(--color-main-background);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
}

.contract-settings,
.freelance-settings {
    background: var(--color-background-hover);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
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
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
}

.hint {
    margin-top: 5px;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    line-height: 1.4;
}

.form-actions {
    margin-top: 30px;
}

.current-status {
    margin-top: 40px;
    padding: 20px;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.status-card {
    background: var(--color-main-background);
    padding: 20px;
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
